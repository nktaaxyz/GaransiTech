<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->string('evidence_photo_path')->nullable();
            $table->string('evidence_photo_mime_type', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropColumn(['evidence_photo_path', 'evidence_photo_mime_type']);
        });
    }
};
