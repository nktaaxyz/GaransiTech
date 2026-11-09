<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateUnitIds = DB::table('warranties')
            ->select('product_unit_id')
            ->groupBy('product_unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_unit_id');

        if ($duplicateUnitIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce one warranty per product unit. Resolve duplicate warranties for product unit IDs: '
                    .$duplicateUnitIds->implode(', '),
            );
        }

        Schema::table('warranties', function (Blueprint $table): void {
            $table->unique('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('warranties', function (Blueprint $table): void {
            $table->dropUnique(['product_unit_id']);
        });
    }
};
