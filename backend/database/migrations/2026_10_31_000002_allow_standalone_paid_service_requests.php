<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_service_requests', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->change();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('product_name')->nullable();
            $table->string('serial_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paid_service_requests', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone', 'product_name', 'serial_number']);
        });
    }
};
