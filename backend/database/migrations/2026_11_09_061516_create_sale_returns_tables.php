<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
                $table->string('return_no', 50)->unique();
                $table->string('reason', 255);
                $table->foreignId('refund_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->bigInteger('refund_total')->default(0);
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->timestampsTz();

                $table->index('sale_id');
            });
        }

        if (!Schema::hasTable('sale_return_items')) {
            Schema::create('sale_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_id')->constrained('sale_returns')->cascadeOnDelete();
                $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
                $table->decimal('qty', 14, 3);
                $table->bigInteger('amount');
                $table->boolean('restock')->default(true);
                $table->timestampsTz();

                $table->index('return_id');
                $table->index('sale_item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};