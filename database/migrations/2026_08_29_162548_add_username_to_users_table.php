<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 60)->nullable()->after('name');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                "UPDATE users SET username = LOWER(REPLACE(REPLACE(SUBSTR('000000000000' || CAST(id AS TEXT), -12, 12), ' ', ''), '-', '')) WHERE username IS NULL"
            );
        } else {
            DB::unprepared(
                "UPDATE users SET username = LOWER(REPLACE(REPLACE(LPAD(id, 12, '0'), ' ', ''), '-', '')) WHERE username IS NULL"
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
