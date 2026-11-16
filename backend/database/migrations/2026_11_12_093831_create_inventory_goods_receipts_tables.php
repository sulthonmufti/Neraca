<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel Master Supplier (Per Tenant)
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name', 150);
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestampsTz();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        //Tabel Counter Nomor Dokumen Goods Receipt (Per Outlet Per Hari - Bebas Race Condition)
        if (!Schema::hasTable('goods_receipt_counters')) {
            Schema::create('goods_receipt_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
                $table->date('counter_date');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestampsTz();

                $table->unique(['outlet_id', 'counter_date']);
            });
        }

        //Tabel Header Penerimaan Barang (Goods Receipts)
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('receipt_no', 50);
                $table->string('supplier_invoice_no', 100)->nullable();
                $table->string('status', 20)->default('completed'); // draft, completed, voided
                $table->bigInteger('total_cost')->default(0);
                $table->text('note')->nullable();
                $table->timestampTz('received_at');
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->timestampsTz();

                $table->unique(['outlet_id', 'receipt_no']);
                $table->index(['outlet_id', 'received_at']);
            });
        }

        //Tabel Detail Item Penerimaan Barang
        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('qty', 14, 3);
                $table->decimal('conversion_factor', 14, 3)->default(1);
                $table->decimal('base_qty', 14, 3); // qty * conversion_factor (satuan dasar stok)
                $table->bigInteger('unit_cost');    // Harga beli per unit_id yang diinput
                $table->bigInteger('base_unit_cost'); // Harga beli per satuan dasar (unit_cost / factor)
                $table->bigInteger('line_total');   // qty * unit_cost
                $table->string('batch_no', 50)->nullable();
                $table->date('expired_date')->nullable();
                $table->timestampsTz();

                $table->index('goods_receipt_id');
                $table->index('variant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('goods_receipt_counters');
        Schema::dropIfExists('suppliers');
    }
};