<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->text('damage_description')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table): void {
            $table->dropColumn('damage_description');
        });
    }
};
