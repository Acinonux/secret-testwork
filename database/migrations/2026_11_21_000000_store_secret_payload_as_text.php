<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ранние установки создали payload как бинарную колонку (BLOB/bytea), что ломало
 * вставку в PostgreSQL. Секреты одноразовые и короткоживущие, поэтому таблица
 * просто пересоздаётся с текстовой колонкой для base64.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getColumnType('secrets', 'payload'), ['text', 'mediumtext', 'longtext'], true)) {
            return;
        }

        Schema::drop('secrets');
        Schema::create('secrets', function (Blueprint $table) {
            $table->char('id', 32)->primary();
            $table->mediumText('payload');
            $table->unsignedInteger('size');
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        // Откат не нужен: текстовая колонка совместима со всеми драйверами.
    }
};
