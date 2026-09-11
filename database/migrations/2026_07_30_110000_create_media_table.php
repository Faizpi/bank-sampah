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
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 128);
            $table->string('visibility', 32)->default('private');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('attachable');
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE media ADD UNIQUE KEY media_disk_path_unique (`disk`, `path`(700))');
        } else {
            Schema::table('media', function (Blueprint $table): void {
                $table->unique(['disk', 'path']);
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER media_visibility_private_insert
                BEFORE INSERT ON media
                WHEN NEW.visibility NOT IN ('private')
                BEGIN
                    SELECT RAISE(ABORT, 'media visibility must be private');
                END;
                CREATE TRIGGER media_visibility_private_update
                BEFORE UPDATE OF visibility ON media
                WHEN NEW.visibility NOT IN ('private')
                BEGIN
                    SELECT RAISE(ABORT, 'media visibility must be private');
                END;
                CREATE TRIGGER media_size_nonnegative_insert
                BEFORE INSERT ON media
                WHEN NEW.size < 0
                BEGIN
                    SELECT RAISE(ABORT, 'media size must be nonnegative');
                END;
                CREATE TRIGGER media_size_nonnegative_update
                BEFORE UPDATE OF size ON media
                WHEN NEW.size < 0
                BEGIN
                    SELECT RAISE(ABORT, 'media size must be nonnegative');
                END;
                SQL);
        } else {
            DB::statement("ALTER TABLE media ADD CONSTRAINT media_visibility_private CHECK (visibility IN ('private'))");
            DB::statement('ALTER TABLE media ADD CONSTRAINT media_size_nonnegative CHECK (size >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
