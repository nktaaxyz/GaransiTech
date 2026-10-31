<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->date('received_at')->nullable();
            $table->text('received_condition')->nullable();
            $table->text('receipt_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropColumn(['received_at', 'received_condition', 'receipt_note']);
        });
    }
};
