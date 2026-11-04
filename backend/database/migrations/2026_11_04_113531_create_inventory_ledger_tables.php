<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel stock_levels
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('qty', 14, 3)->default(0); // Kuantitas mendukung barang curah/timbangan
            $table->decimal('reorder_point', 14, 3)->default(0);
            $table->timestampsTz();

            $table->primary(['outlet_id', 'variant_id']);
        });

        //Tabel stock_movements
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('qty_change', 14, 3);
            $table->decimal('qty_after', 14, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            
            $table->timestampTz('created_at');

            $table->index(['outlet_id', 'variant_id', 'created_at']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};