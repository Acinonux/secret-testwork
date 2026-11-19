<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->char('id', 32)->primary();
            $table->binary('payload');
            $table->unsignedInteger('size');
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
        });

        // BLOB в MySQL ограничен 64 КБ, для файлов нужен MEDIUMBLOB (16 МБ).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE secrets MODIFY payload MEDIUMBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
