<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel Counter Nomor Dokumen Stok Opname (Per Outlet Per Hari - Bebas Race Condition)
        if (!Schema::hasTable('stock_opname_counters')) {
            Schema::create('stock_opname_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
                $table->date('counter_date');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestampsTz();

                $table->unique(['outlet_id', 'counter_date']);
            });
        }

        //Tabel Header Stok Opname
        if (!Schema::hasTable('stock_opnames')) {
            Schema::create('stock_opnames', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
                $table->string('opname_no', 50);
                $table->string('status', 20)->default('completed'); // draft, completed
                $table->text('note')->nullable();
                $table->timestampTz('opname_at');
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->timestampsTz();

                $table->unique(['outlet_id', 'opname_no']);
                $table->index(['outlet_id', 'opname_at']);
            });
        }

        //Tabel Detail Item Stok Opname
        if (!Schema::hasTable('stock_opname_items')) {
            Schema::create('stock_opname_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
                $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
                $table->decimal('system_qty', 14, 3);   // Stok sistem saat opname dilakukan
                $table->decimal('physical_qty', 14, 3); // Stok fisik hasil hitung nyata
                $table->decimal('diff_qty', 14, 3);     // Selisih (physical_qty - system_qty)
                $table->bigInteger('unit_cost')->default(0); // HPP saat opname untuk valuasi selisih
                $table->string('reason', 255)->nullable();
                $table->timestampsTz();

                $table->index('stock_opname_id');
                $table->index('variant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_opname_counters');
    }
};