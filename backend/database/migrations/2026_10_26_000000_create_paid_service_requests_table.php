<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->text('issue_description');
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('requested');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_service_requests');
    }
};
