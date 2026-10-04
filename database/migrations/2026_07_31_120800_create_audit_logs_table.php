<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_retention_context', function (Blueprint $table): void {
            $table->string('token', 64)->primary();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('actor_type', 80);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('auditable_type', 160);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->uuid('correlation_id');
            $table->timestamp('occurred_at');

            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'audit_logs_auditable_occurred_index');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audit_logs_actor_occurred_index');
            $table->index(['action', 'occurred_at'], 'audit_logs_action_occurred_index');
            $table->index('correlation_id', 'audit_logs_correlation_index');
        });

        // Append-only triggers are defence-in-depth: the application layer already
        // centralises audit writes. On managed / binary-logging MySQL (where the
        // database user often lacks SUPER and log_bin_trust_function_creators is off),
        // CREATE TRIGGER can abort a fresh migration. Treat a refusal as non-fatal and
        // surface it as a warning so deployment is not blocked.
        try {
            match (Schema::getConnection()->getDriverName()) {
                'sqlite' => $this->createSqliteAppendOnlyTriggers(),
                'mariadb', 'mysql' => $this->createMariaDbAppendOnlyTriggers(),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning(
                'Audit log append-only triggers could not be created; audit integrity will rely on the application layer. '.$exception->getMessage(),
            );
        }
    }

    public function down(): void
    {
        match (Schema::getConnection()->getDriverName()) {
            'sqlite', 'mariadb', 'mysql' => $this->dropAppendOnlyTriggers(),
            default => null,
        };

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('audit_retention_context');
    }

    private function createSqliteAppendOnlyTriggers(): void
    {
        Schema::getConnection()->unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs WHEN NOT EXISTS (SELECT 1 FROM audit_retention_context) BEGIN SELECT RAISE(ABORT, 'Audit logs are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs WHEN NOT EXISTS (SELECT 1 FROM audit_retention_context) BEGIN SELECT RAISE(ABORT, 'Audit logs are append-only.'); END");
    }

    private function createMariaDbAppendOnlyTriggers(): void
    {
        Schema::getConnection()->unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM audit_retention_context) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are append-only.'; END IF; END");
        Schema::getConnection()->unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM audit_retention_context) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are append-only.'; END IF; END");
    }

    private function dropAppendOnlyTriggers(): void
    {
        Schema::getConnection()->unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        Schema::getConnection()->unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
    }
};
