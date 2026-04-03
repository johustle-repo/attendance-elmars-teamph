<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            // SQLite does not support dropping foreign keys directly,
            // so we recreate the column as a plain nullable integer.
            $table->unsignedBigInteger('performed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('performed_by')->nullable(false)->change();
        });
    }
};
