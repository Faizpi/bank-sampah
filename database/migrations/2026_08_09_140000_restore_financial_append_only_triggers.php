<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        match (Schema::getConnection()->getDriverName()) {
            'sqlite' => $this->restoreSqliteFinancialTriggers(),
            'mariadb', 'mysql' => $this->restoreMariaDbFinancialTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (Schema::getConnection()->getDriverName()) {
            'sqlite', 'mariadb', 'mysql' => $this->dropFinancialTriggers(),
            default => null,
        };
    }

    private function restoreSqliteFinancialTriggers(): void
    {
        $this->dropFinancialTriggers();

        Schema::getConnection()->unprepared("CREATE TRIGGER ledger_entries_prevent_update BEFORE UPDATE ON ledger_entries WHEN OLD.entry_number IS NOT NEW.entry_number OR OLD.ledger_account_id IS NOT NEW.ledger_account_id OR OLD.direction IS NOT NEW.direction OR OLD.kind IS NOT NEW.kind OR OLD.amount IS NOT NEW.amount OR OLD.source_type IS NOT NEW.source_type OR OLD.source_id IS NOT NEW.source_id OR OLD.source_key IS NOT NEW.source_key OR OLD.effective_at IS NOT NEW.effective_at OR OLD.balance_after IS NOT NEW.balance_after BEGIN SELECT RAISE(ABORT, 'Ledger entries are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER ledger_entries_prevent_delete BEFORE DELETE ON ledger_entries BEGIN SELECT RAISE(ABORT, 'Ledger entries are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER balance_holds_prevent_core_update BEFORE UPDATE OF hold_number, ledger_account_id, source_type, source_id, source_key, amount, held_at ON balance_holds BEGIN SELECT RAISE(ABORT, 'Balance hold identity is immutable.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER balance_holds_prevent_delete BEFORE DELETE ON balance_holds BEGIN SELECT RAISE(ABORT, 'Balance holds are append-only status histories.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_corrections_prevent_update BEFORE UPDATE ON transaction_corrections BEGIN SELECT RAISE(ABORT, 'Transaction corrections are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_corrections_prevent_delete BEFORE DELETE ON transaction_corrections BEGIN SELECT RAISE(ABORT, 'Transaction corrections are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_reversals_prevent_update BEFORE UPDATE ON transaction_reversals BEGIN SELECT RAISE(ABORT, 'Transaction reversals are append-only.'); END");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_reversals_prevent_delete BEFORE DELETE ON transaction_reversals BEGIN SELECT RAISE(ABORT, 'Transaction reversals are append-only.'); END");
    }

    private function restoreMariaDbFinancialTriggers(): void
    {
        $this->dropFinancialTriggers();

        Schema::getConnection()->unprepared("CREATE TRIGGER ledger_entries_prevent_update BEFORE UPDATE ON ledger_entries FOR EACH ROW BEGIN IF NEW.entry_number <> OLD.entry_number OR NEW.ledger_account_id <> OLD.ledger_account_id OR NEW.direction <> OLD.direction OR NEW.kind <> OLD.kind OR NEW.amount <> OLD.amount OR NEW.source_type <> OLD.source_type OR NEW.source_id <> OLD.source_id OR NEW.source_key <> OLD.source_key OR NEW.effective_at <> OLD.effective_at OR NOT (NEW.balance_after <=> OLD.balance_after) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are append-only.'; END IF; END");
        Schema::getConnection()->unprepared("CREATE TRIGGER ledger_entries_prevent_delete BEFORE DELETE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are append-only.'");
        Schema::getConnection()->unprepared("CREATE TRIGGER balance_holds_prevent_core_update BEFORE UPDATE ON balance_holds FOR EACH ROW BEGIN IF NEW.hold_number <> OLD.hold_number OR NEW.ledger_account_id <> OLD.ledger_account_id OR NEW.source_type <> OLD.source_type OR NEW.source_id <> OLD.source_id OR NEW.source_key <> OLD.source_key OR NEW.amount <> OLD.amount OR NEW.held_at <> OLD.held_at THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Balance hold identity is immutable.'; END IF; END");
        Schema::getConnection()->unprepared("CREATE TRIGGER balance_holds_prevent_delete BEFORE DELETE ON balance_holds FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Balance holds are append-only status histories.'");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_corrections_prevent_update BEFORE UPDATE ON transaction_corrections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction corrections are append-only.'");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_corrections_prevent_delete BEFORE DELETE ON transaction_corrections FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction corrections are append-only.'");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_reversals_prevent_update BEFORE UPDATE ON transaction_reversals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction reversals are append-only.'");
        Schema::getConnection()->unprepared("CREATE TRIGGER transaction_reversals_prevent_delete BEFORE DELETE ON transaction_reversals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction reversals are append-only.'");
    }

    private function dropFinancialTriggers(): void
    {
        foreach (['ledger_entries_prevent_update', 'ledger_entries_prevent_delete', 'balance_holds_prevent_core_update', 'balance_holds_prevent_delete', 'transaction_corrections_prevent_update', 'transaction_corrections_prevent_delete', 'transaction_reversals_prevent_update', 'transaction_reversals_prevent_delete'] as $trigger) {
            Schema::getConnection()->unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        }
    }
};
