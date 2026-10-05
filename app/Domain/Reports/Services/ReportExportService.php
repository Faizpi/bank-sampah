<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
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

    private const EXPORT_ROW_LIMIT = 10_000;

    private const PDF_DATA_ROWS_PER_PAGE = 44;

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
        $matchingRows = $this->matchingRowCount($actor, $type, $filters);
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
        $rows = $this->normalizedRows(
            $export->report_type,
            $this->reports->streamRecords($actor, $export->report_type, $export->filters, $export->sort, $export->direction),
        );
        $headers = $this->selectedHeaders($export->report_type, $export->columns);
        $rows = $rows->map(fn (array $row): array => array_intersect_key($row, array_flip($headers)));
        if ($export->format === ReportFormat::Csv) {
            return $this->csvStream($headers, $rows);
        }

        $records = [$headers];
        foreach ($rows as $row) {
            $records[] = array_values($row);
        }

        return $export->format === ReportFormat::Xlsx ? $this->xlsx($headers, $rows) : $this->pdf($records);
    }

    /**
     * @param  list<string>  $headers
     * @param  LazyCollection<int, array<string, mixed>>  $rows
     */
    private function csvStream(array $headers, LazyCollection $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Export stream unavailable.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_map($this->safeCell(...), $headers));
        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->safeCell(...), array_values($row)));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($content)) {
            throw new RuntimeException('Export stream unavailable.');
        }

        return $content;
    }

    /**
     * @param  LazyCollection<int, Model>  $records
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function normalizedRows(ReportType $type, LazyCollection $records): LazyCollection
    {
        return $records->flatMap(function (Model $record) use ($type): array {
            if ($type === ReportType::Deposits || $type === ReportType::Participation) {
                if (! $record instanceof Deposit) {
                    return [];
                }

                return $record->items->map(fn (DepositItem $item): array => $this->depositItemRow($record, $item))->all();
            }

            return [$this->recordRow($record, $type)];
        });
    }

    /** @return array<string, mixed> */
    private function depositItemRow(Deposit $deposit, DepositItem $item): array
    {
        return [
            'Nomor Setoran' => $deposit->deposit_number,
            'Tanggal Setoran' => $this->dateValue($deposit->occurred_at),
            'Nomor Nasabah' => (string) data_get($deposit, 'customer.customerProfile.customer_number', ''),
            'Nama Nasabah' => (string) data_get($deposit, 'customer.name', ''),
            'RT' => (string) data_get($deposit, 'customer.customerProfile.rt.name', ''),
            'Area Layanan' => $this->serviceAreas(data_get($deposit, 'customer.customerProfile.rt.serviceAreas')),
            'Petugas Setoran' => (string) data_get($deposit, 'staff.name', ''),
            'Metode Setoran' => $this->label($deposit->method),
            'Lokasi Setoran' => $deposit->location ?? '',
            'Status Setoran' => $this->label($deposit->status),
            'Total Berat (kg)' => $deposit->total_weight_kg,
            'Total Nilai (Rp)' => $deposit->effectiveTotalValue(),
            'Jenis Sampah' => $item->wasteType->name ?? '',
            'Kondisi Sampah' => $item->condition->name ?? '',
            'Berat Item (kg)' => $item->weight_kg,
            'Harga per Satuan (Rp)' => $item->price_per_unit,
            'Nilai Item (Rp)' => $item->subtotal,
        ];
    }

    /** @return array<string, mixed> */
    private function recordRow(Model $record, ReportType $type): array
    {
        return match ($type) {
            ReportType::Withdrawals => $record instanceof WithdrawalRequest ? [
                'Nomor Pencairan' => $record->request_number,
                'Tanggal Dibayar' => $this->dateValue($record->paid_at),
                'Nomor Nasabah' => (string) data_get($record, 'customer.customerProfile.customer_number', ''),
                'Nama Nasabah' => (string) data_get($record, 'customer.name', ''),
                'RT' => (string) data_get($record, 'customer.customerProfile.rt.name', ''),
                'Area Layanan' => $this->serviceAreas(data_get($record, 'customer.customerProfile.rt.serviceAreas')),
                'Petugas Pembayar' => (string) data_get($record, 'payer.name', ''),
                'Lokasi Pengambilan' => $record->pickup_location ?? '',
                'Tanggal Pengambilan' => $record->pickup_date ?? '',
                'Verifikasi Penerima' => $record->recipient_verification ?? '',
                'Referensi Penerima' => $record->recipient_reference ?? '',
                'Status Pencairan' => $this->label($record->status),
                'Jumlah Pencairan (Rp)' => $record->amount,
            ] : [],
            ReportType::Groceries => $record instanceof GroceryRedemption ? [
                'Nomor Penukaran' => $record->request_number,
                'Tanggal Diserahkan' => $this->dateValue($record->handed_over_at),
                'Nomor Nasabah' => (string) data_get($record, 'customer.customerProfile.customer_number', ''),
                'Nama Nasabah' => (string) data_get($record, 'customer.name', ''),
                'RT' => (string) data_get($record, 'customer.customerProfile.rt.name', ''),
                'Area Layanan' => $this->serviceAreas(data_get($record, 'customer.customerProfile.rt.serviceAreas')),
                'Nama Paket' => (string) (($record->package_snapshot ?? [])['name'] ?? ''),
                'Isi Paket' => (string) (($record->package_snapshot ?? [])['contents'] ?? ''),
                'Petugas Persiapan' => (string) data_get($record, 'preparedBy.name', ''),
                'Petugas Penyerahan' => (string) data_get($record, 'handoverActor.name', ''),
                'Catatan Ketersediaan' => $record->availability_note ?? '',
                'Verifikasi Penerima' => $record->recipient_verification ?? '',
                'Referensi Penerima' => $record->recipient_reference ?? '',
                'Status Penukaran' => $this->label($record->status),
                'Nilai Sembako (Rp)' => $record->value_snapshot,
            ] : [],
            ReportType::Pickups => $record instanceof PickupRequest ? [
                'Nomor Penjemputan' => $record->request_number,
                'Nama Nasabah' => (string) data_get($record, 'customer.name', ''),
                'RT' => (string) data_get($record, 'rt.name', ''),
                'Area Layanan' => (string) data_get($record, 'serviceArea.name', ''),
                'Alamat Penjemputan' => $record->address ?? '',
                'Tanggal Pilihan' => $this->dateValue($record->selected_date),
                'Tanggal Terjadwal' => $this->dateValue($record->scheduled_date),
                'Petugas Penjemputan' => $record->assignedStaff->name ?? '',
                'Estimasi Berat (kg)' => $record->estimated_weight_kg,
                'Catatan' => $record->notes ?? '',
                'Tanggal Selesai' => $this->dateValue($record->completed_at),
                'Status Penjemputan' => $this->label($record->status),
            ] : [],
            default => [],
        };
    }

    /** @param iterable<int, object>|null $serviceAreas */
    private function serviceAreas(?iterable $serviceAreas): string
    {
        if ($serviceAreas === null) {
            return '';
        }

        $names = [];
        foreach ($serviceAreas as $serviceArea) {
            $name = $serviceArea->name ?? '';
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    /** @param array<string, mixed> $filters */
    private function matchingRowCount(User $actor, ReportType $type, array $filters): int
    {
        $query = $this->reports->query($actor, $filters, $type);

        return match ($type) {
            ReportType::Deposits, ReportType::Participation => (clone $query)->has('items')->withCount('items')->get()->sum('items_count'),
            default => $query->count(),
        };
    }

    private function dateValue(?DateTimeInterface $value): string
    {
        return $value === null ? '' : DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
    }

    private function label(mixed $value): string
    {
        $raw = $value instanceof BackedEnum ? $value->value : (string) $value;

        return match ($raw) {
            'final' => 'Final', 'dikoreksi' => 'Dikoreksi', 'loket' => 'Loket',
            default => str_replace('_', ' ', ucfirst($raw)),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  LazyCollection<int, array<string, mixed>>  $records
     */
    private function xlsx(array $headers, LazyCollection $records): string
    {
        $zip = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'w9-xlsx-');
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('XLSX archive unavailable.');
        }

        $lastColumn = $this->columnName(count($headers));
        $rows = '<row r="1" s="1">'.$this->xlsxCells($headers, $headers, 1, true).'</row>';
        $rowIndex = 2;
        foreach ($records as $record) {
            $style = $rowIndex > 2 && ($record['Nomor Setoran'] ?? null) === ($previousDepositNumber ?? null) ? '' : ' s="2"';
            $rows .= '<row r="'.$rowIndex.'"'.$style.'>'.$this->xlsxCells($headers, array_values($record), $rowIndex, false).'</row>';
            $previousDepositNumber = $record['Nomor Setoran'] ?? null;
            $rowIndex++;
        }
        $range = 'A1:'.$lastColumn.($rowIndex - 1);
        $columns = '';
        foreach ($headers as $index => $header) {
            $width = min(28, max(12, strlen($header) + 3));
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $tableColumns = '';
        foreach ($headers as $index => $header) {
            $tableColumns .= '<tableColumn id="'.($index + 1).'" name="'.$this->xml($header).'"/>';
        }
        $styles = '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="3"><numFmt numFmtId="164" formatCode="yyyy-mm-dd hh:mm"/><numFmt numFmtId="165" formatCode="0.00&quot; kg&quot;"/><numFmt numFmtId="166" formatCode="&quot;Rp&quot; #,##0"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/></border><border><left/><right/><top style="thin"><color rgb="FF9EADBA"/></top><bottom/></border></borders><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/><xf numFmtId="166" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs></styleSheet>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData><autoFilter ref="'.$range.'"/><tableParts count="1"><tablePart r:id="rId1"/></tableParts></worksheet>');
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/></Relationships>');
        $zip->addFromString('xl/tables/table1.xml', '<?xml version="1.0" encoding="UTF-8"?><table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="LaporanTable" displayName="LaporanTable" ref="'.$range.'" totalsRowShown="0"><autoFilter ref="'.$range.'"/><tableColumns count="'.count($headers).'">'.$tableColumns.'</tableColumns><tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/></table>');
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);
        if (! is_string($content)) {
            throw new RuntimeException('XLSX archive unavailable.');
        }

        return $content;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $values
     */
    private function xlsxCells(array $headers, array $values, int $rowIndex, bool $header): string
    {
        $cells = '';
        foreach ($values as $columnIndex => $value) {
            $reference = $this->columnName($columnIndex + 1).$rowIndex;
            $columnHeader = $headers[$columnIndex];
            if (! $header && str_contains($columnHeader, 'Tanggal') && $value !== '') {
                $date = new DateTimeImmutable((string) $value, new DateTimeZone('Asia/Jakarta'));
                $excelDate = ($date->getTimestamp() - strtotime('1899-12-30 UTC')) / 86400;
                $cells .= '<c r="'.$reference.'" s="3"><v>'.$excelDate.'</v></c>';

                continue;
            }
            if ($header || ! is_numeric($value)) {
                $cells .= '<c r="'.$reference.'"'.($header ? ' s="1"' : '').' t="inlineStr"><is><t xml:space="preserve">'.$this->xml($this->safeCell($value)).'</t></is></c>';

                continue;
            }
            $style = $this->xlsxNumberStyle($columnHeader);
            $cells .= '<c r="'.$reference.'"'.($style === 0 ? '' : ' s="'.$style.'"').'><v>'.(string) $value.'</v></c>';
        }

        return $cells;
    }

    private function xlsxNumberStyle(string $header): int
    {
        if (str_contains($header, 'Tanggal')) {
            return 3;
        }

        return str_contains($header, 'Berat') ? 4 : (str_contains($header, 'Nilai') || str_contains($header, 'Harga') || str_contains($header, 'Total') ? 5 : 0);
    }

    /** @param list<string> $selectedColumns
     * @return list<string>
     */
    private function selectedHeaders(ReportType $type, array $selectedColumns): array
    {
        $columns = $this->reports->columnsFor($type);
        $headers = [];
        foreach ($columns as $column) {
            if (in_array($column, $selectedColumns, true)) {
                $headers[] = $this->headerForColumn($type, $column);
            }
        }
        if (in_array($type, [ReportType::Deposits, ReportType::Participation], true)) {
            $headers = array_merge($headers, ['Jenis Sampah', 'Kondisi Sampah', 'Berat Item (kg)', 'Harga per Satuan (Rp)', 'Nilai Item (Rp)']);
        }

        return array_values(array_unique($headers));
    }

    private function headerForColumn(ReportType $type, string $column): string
    {
        return match ($type->value.':'.$column) {
            'deposits:deposit_number', 'participation:deposit_number' => 'Nomor Setoran',
            'deposits:occurred_at', 'participation:occurred_at' => 'Tanggal Setoran',
            'deposits:customer_number', 'participation:customer_number', 'withdrawals:customer_number', 'groceries:customer_number' => 'Nomor Nasabah',
            'deposits:customer_name', 'participation:customer_name', 'withdrawals:customer_name', 'groceries:customer_name', 'pickups:customer_name' => 'Nama Nasabah',
            'deposits:rt', 'participation:rt', 'withdrawals:rt', 'groceries:rt', 'pickups:rt' => 'RT',
            'deposits:service_area', 'participation:service_area', 'withdrawals:service_area', 'groceries:service_area', 'pickups:service_area' => 'Area Layanan',
            'deposits:staff_name', 'participation:staff_name' => 'Petugas Setoran',
            'deposits:method', 'participation:method' => 'Metode Setoran',
            'deposits:location', 'participation:location' => 'Lokasi Setoran',
            'deposits:status', 'participation:status' => 'Status Setoran',
            'deposits:total_weight_kg', 'participation:total_weight_kg' => 'Total Berat (kg)',
            'deposits:total_value', 'participation:total_value' => 'Total Nilai (Rp)',
            'withdrawals:request_number' => 'Nomor Pencairan', 'withdrawals:paid_at' => 'Tanggal Dibayar', 'withdrawals:payer_name' => 'Petugas Pembayar', 'withdrawals:pickup_location' => 'Lokasi Pengambilan', 'withdrawals:pickup_date' => 'Tanggal Pengambilan', 'withdrawals:recipient_verification' => 'Verifikasi Penerima', 'withdrawals:recipient_reference' => 'Referensi Penerima', 'withdrawals:status' => 'Status Pencairan', 'withdrawals:amount' => 'Jumlah Pencairan (Rp)',
            'groceries:request_number' => 'Nomor Penukaran', 'groceries:handed_over_at' => 'Tanggal Diserahkan', 'groceries:package_name' => 'Nama Paket', 'groceries:package_contents' => 'Isi Paket', 'groceries:prepared_by_name' => 'Petugas Persiapan', 'groceries:handover_staff_name' => 'Petugas Penyerahan', 'groceries:availability_note' => 'Catatan Ketersediaan', 'groceries:recipient_verification' => 'Verifikasi Penerima', 'groceries:recipient_reference' => 'Referensi Penerima', 'groceries:status' => 'Status Penukaran', 'groceries:value_snapshot' => 'Nilai Sembako (Rp)',
            'pickups:request_number' => 'Nomor Penjemputan', 'pickups:address' => 'Alamat Penjemputan', 'pickups:selected_date' => 'Tanggal Pilihan', 'pickups:scheduled_date' => 'Tanggal Terjadwal', 'pickups:assigned_staff_name' => 'Petugas Penjemputan', 'pickups:estimated_weight_kg' => 'Estimasi Berat (kg)', 'pickups:notes' => 'Catatan', 'pickups:completed_at' => 'Tanggal Selesai', 'pickups:status' => 'Status Penjemputan',
            default => throw ValidationException::withMessages(['export' => 'Kolom ekspor tidak didukung oleh skema bisnis.']),
        };
    }

    /** @param list<list<mixed>> $records */
    private function pdf(array $records): string
    {
        $lines = [];
        foreach ($records as $record) {
            $lines[] = implode(' | ', array_map(fn (mixed $value): string => $this->safeCell($value), $record));
        }
        $header = $lines[0] ?? '';
        $rows = array_slice($lines, 1);
        $pages = [];
        foreach (array_chunk($rows, self::PDF_DATA_ROWS_PER_PAGE) as $pageRows) {
            $pages[] = array_merge([$header], $pageRows);
        }
        if ($pages === []) {
            $pages[] = [$header];
        }

        $pageCount = count($pages);
        $fontObject = 3 + (2 * $pageCount);
        $pageReferences = [];
        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            $pageReferences[] = (3 + (2 * $pageIndex)).' 0 R';
        }
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.$pageCount.' >>',
        ];
        foreach ($pages as $pageIndex => $pageLines) {
            $pageObject = 3 + (2 * $pageIndex);
            $contentObject = $pageObject + 1;
            $commands = ['BT', '/F1 8 Tf', '50 780 Td'];
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $commands[] = '0 -16 Td';
                }
                $commands[] = '('.$this->pdfEscape($line).') Tj';
            }
            $commands[] = 'ET';
            $stream = implode("\n", $commands);
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontObject.' 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }
        $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        return $this->serializePdf($objects);
    }

    /** @param array<int, string> $objects */
    private function serializePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $size = count($objects) + 1;
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".$size."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
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
