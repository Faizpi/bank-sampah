<?php

declare(strict_types=1);

namespace Tests\Feature\AuditReconciliation;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_appends_a_complete_sanitized_audit_record(): void
    {
        $actor = User::factory()->create();
        $auditable = User::factory()->create();
        $correlationId = '018f4ca4-2e67-7c16-a455-8f610f6f5642';
        $evidenceReference = str_repeat('e', 43);
        $audit = $this->logger()->record($actor, 'identity.user.updated', $auditable, ['name' => 'Before', 'password' => 'password-value', 'reset_token' => 'reset-value', 'attachment' => ['file_contents' => 'private-file-body']], ['name' => 'After', 'session' => ['cookie' => 'session-cookie'], 'api_secret' => 'secret-value', 'restore_verification_evidence_reference' => $evidenceReference, 'backup_pair_uuid' => $correlationId, 'database_sha256' => str_repeat('a', 64)], $correlationId);

        self::assertSame($actor->id, $audit->getAttribute('actor_id'));
        self::assertSame('user', $audit->getAttribute('actor_type'));
        self::assertSame('identity.user.updated', $audit->getAttribute('action'));
        self::assertSame(User::class, $audit->getAttribute('auditable_type'));
        self::assertSame($auditable->id, $audit->getAttribute('auditable_id'));
        self::assertSame($correlationId, $audit->getAttribute('correlation_id'));
        self::assertNotNull($audit->getAttribute('occurred_at'));
        self::assertSame(['name' => 'Before', 'password' => '[REDACTED]', 'reset_token' => '[REDACTED]', 'attachment' => ['file_contents' => '[REDACTED]']], $audit->getAttribute('old_values'));
        self::assertSame(['name' => 'After', 'session' => '[REDACTED]', 'api_secret' => '[REDACTED]', 'restore_verification_evidence_reference' => '[REDACTED]', 'backup_pair_uuid' => $correlationId, 'database_sha256' => str_repeat('a', 64)], $audit->getAttribute('new_values'));
        $this->assertDatabaseHas('audit_logs', ['id' => $audit->getKey(), 'actor_id' => $actor->id, 'action' => 'identity.user.updated', 'correlation_id' => $correlationId]);
    }

    public function test_it_commits_an_audit_row_with_the_enclosing_transaction(): void
    {
        $actor = User::factory()->create();
        $auditable = User::factory()->create();

        DB::transaction(function () use ($actor, $auditable): void {
            $this->logger()->record($actor, 'identity.user.updated', $auditable, [], [], '018f4ca4-2e67-7c16-a455-8f610f6f5642');
        });

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_it_is_transaction_compatible_and_leaves_no_audit_row_after_rollback(): void
    {
        $actor = User::factory()->create();
        $auditable = User::factory()->create();

        try {
            DB::transaction(function () use ($actor, $auditable): void {
                $this->logger()->record($actor, 'identity.user.updated', $auditable, [], [], '018f4ca4-2e67-7c16-a455-8f610f6f5642');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_is_impossible_to_update_or_delete_through_the_audit_model(): void
    {
        $audit = $this->createAudit();
        $audit->setAttribute('action', 'identity.user.deleted');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only.');
        $audit->save();
    }

    public function test_it_refuses_audit_model_deletion(): void
    {
        $audit = $this->createAudit();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only.');
        $audit->delete();
    }

    public function test_it_rejects_mass_query_updates_and_preserves_the_original_row(): void
    {
        $audit = $this->createAudit();

        $this->expectException(QueryException::class);
        try {
            AuditLog::query()->whereKey($audit->getKey())->update(['action' => 'identity.user.deleted']);
        } finally {
            $this->assertDatabaseHas('audit_logs', ['id' => $audit->getKey(), 'action' => 'identity.user.updated']);
        }
    }

    public function test_it_rejects_mass_query_deletes_and_preserves_the_original_row(): void
    {
        $audit = $this->createAudit();

        $this->expectException(QueryException::class);
        try {
            AuditLog::query()->whereKey($audit->getKey())->delete();
        } finally {
            $this->assertDatabaseHas('audit_logs', ['id' => $audit->getKey()]);
        }
    }

    public function test_it_records_system_actor_and_minimized_request_metadata(): void
    {
        $this->app->instance('request', Request::create('/', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.24',
            'HTTP_USER_AGENT' => str_repeat('agent-', 200),
        ]));

        $audit = $this->logger()->record(null, 'system.audit.checked', User::factory()->create(), [], [], '018f4ca4-2e67-7c16-a455-8f610f6f5642');

        self::assertSame('system', $audit->getAttribute('actor_type'));
        self::assertNull($audit->getAttribute('actor_id'));
        self::assertSame(hash('sha256', '203.0.113.24'), $audit->getAttribute('ip_address_hash'));
        self::assertSame(512, strlen((string) $audit->getAttribute('user_agent')));
        self::assertArrayNotHasKey('request', $audit->getAttributes());
        self::assertArrayNotHasKey('headers', $audit->getAttributes());
    }

    public function test_it_redacts_uploaded_file_objects_and_file_content(): void
    {
        $upload = UploadedFile::fake()->createWithContent('proof.pdf', 'private-file-content');
        $audit = $this->logger()->record(User::factory()->create(), 'media.uploaded', User::factory()->create(), [], ['upload' => $upload, 'file_contents' => 'private-file-content'], '018f4ca4-2e67-7c16-a455-8f610f6f5642');

        self::assertSame(['upload' => '[REDACTED]', 'file_contents' => '[REDACTED]'], $audit->getAttribute('new_values'));
    }

    public function test_audit_schema_exposes_the_required_columns_json_fields_and_indexes(): void
    {
        self::assertTrue(Schema::hasColumns('audit_logs', ['event_uuid', 'actor_type', 'actor_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address_hash', 'user_agent', 'correlation_id', 'occurred_at']));
        self::assertTrue((bool) collect(Schema::getColumns('audit_logs'))->firstWhere('name', 'actor_id')['nullable']);
        $expectedJsonStorageType = DB::getDriverName() === 'sqlite' ? 'text' : 'json';
        self::assertStringContainsString($expectedJsonStorageType, strtolower((string) collect(Schema::getColumns('audit_logs'))->firstWhere('name', 'old_values')['type_name']));
        self::assertStringContainsString($expectedJsonStorageType, strtolower((string) collect(Schema::getColumns('audit_logs'))->firstWhere('name', 'new_values')['type_name']));
        self::assertTrue($this->hasUniqueIndex(['event_uuid']));
        self::assertTrue($this->hasIndex(['auditable_type', 'auditable_id', 'occurred_at']));
        self::assertTrue($this->hasIndex(['actor_type', 'actor_id', 'occurred_at']));
        self::assertTrue($this->hasIndex(['action', 'occurred_at']));
    }

    /** @param list<string> $columns */
    private function hasIndex(array $columns): bool
    {
        return collect(DB::select("PRAGMA index_list('audit_logs')"))->contains(function (object $index) use ($columns): bool {
            $indexColumns = array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name');

            return $indexColumns === $columns;
        });
    }

    /** @param list<string> $columns */
    private function hasUniqueIndex(array $columns): bool
    {
        return collect(DB::select("PRAGMA index_list('audit_logs')"))->contains(function (object $index) use ($columns): bool {
            $indexColumns = array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name');

            return (int) $index->unique === 1 && $indexColumns === $columns;
        });
    }

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    private function createAudit(): AuditLog
    {
        return $this->logger()->record(User::factory()->create(), 'identity.user.updated', User::factory()->create(), [], [], '018f4ca4-2e67-7c16-a455-8f610f6f5642');
    }
}
