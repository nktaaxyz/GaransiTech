<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->foreignId('warranty_id')
                ->nullable()
                ->after('claim_code')
                ->constrained('warranties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropForeign(['warranty_id']);
            $table->dropColumn('warranty_id');
        });
    }
};
