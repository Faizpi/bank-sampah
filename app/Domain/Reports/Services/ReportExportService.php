<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Models\ReportExport;
use App\Models\User;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class ReportExportService
{
    private const DISK = 'media_private';

    private const int EXPORT_ROW_LIMIT = 10_000;

    public function __construct(private PermissionChecker $permissions, private ReportQueryService $reports, private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columns
     */
    public function export(User $actor, string $reportType, array $filters, string $format, ?array $columns = null, string $sort = 'occurred_at', string $direction = 'desc'): ReportExport
    {
        $this->authorize($actor, 'report.export');
        $type = ReportType::tryFrom($reportType);
        $fileFormat = ReportFormat::tryFrom($format);
        if ($type === null || $fileFormat === null) {
            throw ValidationException::withMessages(['export' => 'Jenis atau format ekspor tidak diizinkan.']);
        }
        $allowedColumns = $this->reports->columnsFor($type);
        $selectedColumns = $columns ?? $allowedColumns;
        if ($selectedColumns === [] || array_diff($selectedColumns, $allowedColumns) !== []) {
            throw ValidationException::withMessages(['export' => 'Kolom ekspor tidak diizinkan.']);
        }
        $matchingRows = $this->reports->query($actor, $filters, $type)->count();
        if ($matchingRows > self::EXPORT_ROW_LIMIT) {
            throw ValidationException::withMessages(['export' => 'Laporan terlalu besar untuk ekspor sinkron. Persempit periode atau filter laporan.']);
        }
        $uuid = (string) Str::uuid();
        $export = DB::transaction(function () use ($actor, $type, $fileFormat, $filters, $selectedColumns, $sort, $direction, $uuid): ReportExport {
            $record = new ReportExport;
            $record->forceFill(['uuid' => $uuid, 'requester_id' => $actor->id, 'report_type' => $type, 'filters' => $filters, 'columns' => array_values(array_unique($selectedColumns)), 'format' => $fileFormat, 'sort' => $sort, 'direction' => $direction, 'status' => ReportExportStatus::Pending, 'expires_at' => now()->addDay()])->save();
            $this->auditLogger->record($actor, 'report.export.requested', $record, [], ['report_type' => $type->value, 'format' => $fileFormat->value, 'filters' => $filters], $this->correlationId());

            return $record;
        });

        return $this->generate($actor, $export);
    }

    public function generate(User $actor, ReportExport $export): ReportExport
    {
        $owned = ReportExport::query()->whereKey($export->id)->where('requester_id', $actor->id)->first();
        if ($owned === null) {
            throw (new ModelNotFoundException)->setModel(ReportExport::class, [$export->id]);
        }
        if ($owned->status !== ReportExportStatus::Pending) {
            return $owned;
        }
        if ($owned->expires_at->isPast()) {
            return $this->expire($owned);
        }
        $owned->forceFill(['status' => ReportExportStatus::Processing])->save();
        $tempPath = 'exports/.tmp/'.(string) Str::uuid().'.tmp';
        $finalPath = 'exports/'.$owned->uuid.'/'.$this->serverFilename($owned);
        $disk = Storage::disk(self::DISK);
        try {
            $content = $this->buildContent($actor, $owned);
            if ($disk->put($tempPath, $content) === false || $disk->move($tempPath, $finalPath) === false) {
                throw new RuntimeException('Export storage failed.');
            }
            $media = DB::transaction(function () use ($actor, $owned, $finalPath): Media {
                $media = new Media;
                $media->forceFill(['uuid' => (string) Str::uuid(), 'disk' => self::DISK, 'path' => $finalPath, 'original_name' => $this->serverFilename($owned), 'mime_type' => $this->mimeType($owned->format), 'size' => Storage::disk(self::DISK)->size($finalPath), 'checksum' => hash('sha256', (string) Storage::disk(self::DISK)->get($finalPath)), 'visibility' => MediaVisibility::Private, 'uploader_id' => $actor->id, 'attachable_type' => ReportExport::class, 'attachable_id' => $owned->id])->save();
                $owned->forceFill(['status' => ReportExportStatus::Succeeded, 'disk' => self::DISK, 'path' => $finalPath, 'filename' => $this->serverFilename($owned), 'media_id' => $media->id, 'completed_at' => now()])->save();
                $this->auditLogger->record($actor, 'report.export.completed', $owned, ['status' => ReportExportStatus::Processing->value], ['status' => ReportExportStatus::Succeeded->value, 'format' => $owned->format->value], $this->correlationId());

                return $media;
            });
            unset($media);
        } catch (Throwable) {
            $disk->delete($tempPath);
            $disk->delete($finalPath);
            try {
                DB::transaction(function () use ($actor, $owned): void {
                    $owned->forceFill(['status' => ReportExportStatus::Failed, 'error_reference' => (string) Str::uuid()])->save();
                    $this->auditLogger->record($actor, 'report.export.failed', $owned, ['status' => ReportExportStatus::Processing->value], ['status' => ReportExportStatus::Failed->value], $this->correlationId());
                });
            } catch (Throwable) {
                $owned->forceFill(['status' => ReportExportStatus::Failed, 'error_reference' => (string) Str::uuid()])->saveQuietly();
            }
        }

        return $owned->fresh();
    }

    public function download(User $actor, ReportExport $export): Media
    {
        $this->authorize($actor, 'report.export');
        $owned = ReportExport::query()->with('media')->whereKey($export->id)->where('requester_id', $actor->id)->first();
        if ($owned === null) {
            throw (new ModelNotFoundException)->setModel(ReportExport::class, [$export->id]);
        }
        if (! $owned->isAvailable()) {
            throw (new ModelNotFoundException)->setModel(ReportExport::class, [$export->id]);
        }
        $media = $owned->media;
        if ($media === null || $media->getRawOriginal('visibility') !== MediaVisibility::Private->value) {
            throw (new ModelNotFoundException)->setModel(ReportExport::class, [$export->id]);
        }
        if (! is_file(Storage::disk(self::DISK)->path((string) $owned->path))) {
            throw (new ModelNotFoundException)->setModel(Media::class, [$media->id]);
        }
        $this->auditLogger->record($actor, 'report.export.downloaded', $owned, [], ['media_id' => $media->id], $this->correlationId());

        return $media;
    }

    public function expire(ReportExport $export): ReportExport
    {
        if ($export->status === ReportExportStatus::Succeeded && $export->path !== null) {
            Storage::disk(self::DISK)->delete($export->path);
        }
        if ($export->media_id !== null) {
            Media::query()->whereKey($export->media_id)->delete();
        }
        $export->forceFill(['status' => ReportExportStatus::Expired, 'path' => null, 'disk' => null, 'media_id' => null])->save();

        return $export;
    }

    private function buildContent(User $actor, ReportExport $export): string
    {
        $rows = $this->reports->streamRecords($actor, $export->report_type, $export->filters, $export->sort, $export->direction);
        $allowedColumns = $this->reports->columnsFor($export->report_type);
        $headers = $export->columns ?? $allowedColumns;
        if ($headers === [] || array_diff($headers, $allowedColumns) !== []) {
            throw ValidationException::withMessages(['export' => 'Kolom ekspor tidak diizinkan.']);
        }
        if ($export->format === ReportFormat::Csv) {
            return $this->csvStream($headers, $rows);
        }

        $records = [$headers];
        foreach ($rows as $record) {
            $records[] = array_map(fn (string $column): mixed => $this->valueFor($record, $column), $headers);
        }

        if ($export->format === ReportFormat::Xlsx) {
            return $this->xlsx($records);
        }

        return $this->pdf($records);
    }

    /**
     * @param  list<string>  $headers
     * @param  LazyCollection<int, Model>  $rows
     */
    private function csvStream(array $headers, LazyCollection $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Export stream unavailable.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_map(fn (string $value): string => $this->safeCell($value), $headers));
        foreach ($rows as $record) {
            $values = array_map(fn (string $column): mixed => $this->valueFor($record, $column), $headers);
            fputcsv($stream, array_map(fn (mixed $value): string => $this->safeCell($value), $values));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($content)) {
            throw new RuntimeException('Export stream unavailable.');
        }

        return $content;
    }

    /** @param list<list<mixed>> $records */
    private function xlsx(array $records): string
    {
        $zip = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'w9-xlsx-');
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('XLSX archive unavailable.');
        }
        $rows = '';
        foreach ($records as $rowIndex => $record) {
            $cells = '';
            foreach ($record as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$reference.'"><v>'.(string) $value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($this->safeCell($value)).'</t></is></c>';
                }
            }
            $rows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);
        if (! is_string($content)) {
            throw new RuntimeException('XLSX archive unavailable.');
        }

        return $content;
    }

    /** @param list<list<mixed>> $records */
    private function pdf(array $records): string
    {
        $lines = [];
        foreach ($records as $record) {
            $lines[] = implode(' | ', array_map(fn (mixed $value): string => $this->safeCell($value), $record));
        }
        $commands = ['BT', '/F1 8 Tf', '50 780 Td'];
        foreach (array_slice($lines, 0, 45) as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -16 Td';
            }
            $commands[] = '('.$this->pdfEscape(Str::limit($line, 180, '...')).') Tj';
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).' >>\nstream\n'.$stream.'\nendstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number + 1] = strlen($pdf);
            $pdf .= ($number + 1).' 0 obj\n'.$object.'\nendobj\n';
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1).'\n0000000000 65535 f \n';
        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf('%010d 00000 n \n', $offsets[$index]);
        }
        $pdf .= 'trailer\n<< /Size '.(count($objects) + 1).' /Root 1 0 R >>\nstartxref\n'.$xref."\n%%EOF";

        return $pdf;
    }

    private function valueFor(Model $record, string $column): mixed
    {
        $value = $record->getAttribute($column);
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
        }

        return $value;
    }

    private function safeCell(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@'], true) ? "'".$text : $text;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function serverFilename(ReportExport $export): string
    {
        return 'laporan-'.$export->report_type->value.'-'.$export->uuid.'.'.$export->format->value;
    }

    private function mimeType(ReportFormat $format): string
    {
        return match ($format) {
            ReportFormat::Csv => 'text/csv; charset=UTF-8',
            ReportFormat::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ReportFormat::Pdf => 'application/pdf',
        };
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses ekspor.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
