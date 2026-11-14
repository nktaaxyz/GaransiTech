<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->index(['warranty_id', 'status'], 'warranty_claims_warranty_status_index');
            $table->index('claim_date', 'warranty_claims_claim_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropIndex('warranty_claims_warranty_status_index');
            $table->dropIndex('warranty_claims_claim_date_index');
        });
    }
};
