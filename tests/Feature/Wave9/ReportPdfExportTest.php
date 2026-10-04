<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Services\ReportExportService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ReportPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_export_is_structurally_valid_escapes_values_and_keeps_all_rows_across_pages(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $specialReference = 'PDF\\(safe)';
        $this->seedDeposit($actor, 1, '2026-08-01 10:00:00', $specialReference);
        foreach (range(2, 90) as $number) {
            $this->seedDeposit($actor, $number, '2026-08-01 10:00:00', 'PDF-ROW-'.$number);
        }

        $export = app(ReportExportService::class)->export($actor, 'deposits', [
            'start' => '2026-08-01',
            'end' => '2026-08-02',
        ], 'pdf');
        $pdf = Storage::disk('media_private')->get((string) $export->path);

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $this->assertValidPdf($pdf);
        self::assertStringContainsString('/Count 3', $pdf);
        foreach (range(2, 90) as $number) {
            self::assertStringContainsString('PDF-ROW-'.$number.'-'.$actor->id, $pdf);
        }
        $escapedReference = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $specialReference.'-'.$actor->id);
        self::assertStringContainsString($escapedReference, $pdf);
    }

    public function test_pdf_download_preserves_private_media_metadata_and_response_contract(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $this->seedDeposit($actor, 20_000, '2026-08-01 10:00:00', 'PDF-DOWNLOAD');

        $export = app(ReportExportService::class)->export($actor, 'deposits', [
            'start' => '2026-08-01',
            'end' => '2026-08-02',
        ], 'pdf');
        $content = Storage::disk('media_private')->get((string) $export->path);
        $media = Media::query()->findOrFail($export->media_id);

        self::assertSame('media_private', $media->disk);
        self::assertSame('application/pdf', $media->mime_type);
        self::assertSame('private', $media->getRawOriginal('visibility'));
        self::assertSame(strlen($content), $media->size);
        self::assertSame(hash('sha256', $content), $media->checksum);

        $response = $this->actingAs($actor)->get(route('reports.export.download', $export));
        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('attachment; filename='.$export->filename, (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function assertValidPdf(string $pdf): void
    {
        self::assertStringStartsWith("%PDF-1.4\n", $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
        self::assertStringNotContainsString('\\n', $pdf);
        self::assertStringNotContainsString('\\nstream\\n', $pdf);
        self::assertStringNotContainsString('\\nendstream', $pdf);

        $xrefOffset = strpos($pdf, "xref\n");
        self::assertNotFalse($xrefOffset);
        $startxref = [];
        self::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\\z/', $pdf, $startxref));
        self::assertSame($xrefOffset, (int) $startxref[1]);

        $trailerOffset = strpos($pdf, "trailer\n", $xrefOffset);
        self::assertNotFalse($trailerOffset);
        $size = [];
        self::assertSame(1, preg_match('/trailer\n<< \/Size (\d+) \/Root 1 0 R >>\n/', $pdf, $size));
        $objectCount = (int) $size[1] - 1;
        $xrefLines = explode("\n", rtrim(substr($pdf, $xrefOffset, $trailerOffset - $xrefOffset), "\n"));

        self::assertSame(['xref', '0 '.($objectCount + 1), '0000000000 65535 f '], array_slice($xrefLines, 0, 3));
        for ($objectNumber = 1; $objectNumber <= $objectCount; $objectNumber++) {
            $entry = $xrefLines[$objectNumber + 2] ?? '';
            self::assertMatchesRegularExpression('/^\d{10} 00000 n $/', $entry);
            $offset = (int) substr($entry, 0, 10);
            self::assertSame($objectNumber.' 0 obj'."\n", substr($pdf, $offset, strlen($objectNumber.' 0 obj'."\n")));
        }
    }

    private function seedDeposit(User $owner, int $value, string $occurredAt, string $number): Deposit
    {
        return Deposit::query()->create([
            'deposit_number' => $number.'-'.$owner->id,
            'customer_id' => $owner->id,
            'staff_id' => $owner->id,
            'method' => 'loket',
            'occurred_at' => $occurredAt,
            'status' => 'final',
            'total_weight_kg' => '1.000',
            'total_value' => $value,
            'finalized_at' => $occurredAt,
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'pdf-'.uniqid(), 'description' => 'PDF export test']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
