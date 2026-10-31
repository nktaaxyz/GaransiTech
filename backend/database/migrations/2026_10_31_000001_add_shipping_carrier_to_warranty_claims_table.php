<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->string('shipping_carrier')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropColumn('shipping_carrier');
        });
    }
};
