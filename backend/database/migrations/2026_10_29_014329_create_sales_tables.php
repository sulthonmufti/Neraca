<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel sales (Transaksi utama / Bill / Struk)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // UUID dari klien untuk idempotensi & offline sync
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            // Foreign key ke shift dan member
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('receipt_no', 100); // Nomor struk unik per outlet
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('member_id')->nullable();
            
            $table->string('order_type', 30)->default('dine_in'); // dine_in, take_away, delivery
            $table->unsignedBigInteger('table_id')->nullable(); // Untuk modul Cafe
            $table->string('status', 30)->default('completed'); // hold, completed, voided
            
            // Seluruh nominal uang menggunakan tipe BIGINT (dalam satuan Rupiah)
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('service_total')->default(0);
            $table->bigInteger('rounding')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('paid_total')->default(0);
            $table->bigInteger('change_total')->default(0);
            
            $table->text('note')->nullable();
            $table->timestampTz('sold_at'); // Waktu transaksi terjadi di perangkat kasir
            
            // Kolom Void / Pembatalan
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestampsTz();

            // Indeks dan constraint unik per outlet
            $table->unique(['outlet_id', 'receipt_no']);
            $table->index(['outlet_id', 'sold_at']);
            $table->index(['member_id', 'sold_at']);
            $table->index('status');
        });

        //Tabel sale_items (Item barang yang dibeli beserta snapshot harga & HPP)
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            
            $table->string('name_snapshot'); // Nama produk saat dibeli (jika master diubah, histori aman)
            $table->string('sku_snapshot', 100);
            
            $table->decimal('qty', 14, 3); // Kuantitas (mendukung barang curah/timbangan)
            $table->bigInteger('unit_price'); // Harga satuan saat transaksi
            $table->bigInteger('cost_snapshot'); // HPP saat transaksi untuk perhitungan laba
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('line_total'); // (qty * unit_price) - diskon + pajak
            
            $table->jsonb('modifiers')->nullable(); // Untuk pilihan level gula/topping di kafe
            $table->text('note')->nullable();
            $table->string('kitchen_status', 30)->default('pending'); // pending, cooking, ready, served
            
            $table->timestampsTz();

            $table->index('sale_id');
            $table->index('variant_id');
        });

        //Tabel sale_discounts (Diskon per item atau diskon global transaksi)
        Schema::create('sale_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
            $table->unsignedBigInteger('promotion_id')->nullable();
            
            $table->string('kind', 30); // percent, amount
            $table->decimal('value', 10, 2); // Nilai diskon (misal: 10 untuk 10% atau 5000 untuk nominal)
            $table->bigInteger('amount'); // Nominal rupiah potongan diskon
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            
            $table->timestampsTz();
        });

        //Tabel sale_payments (Mendukung pembayaran tunggal maupun split payment)
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            
            $table->bigInteger('amount'); // Jumlah yang dibayar dengan metode ini
            $table->string('reference', 100)->nullable(); // Nomor referensi EDC / Transfer / QRIS
            $table->string('status', 30)->default('paid'); // paid, pending, failed, void
            $table->timestampTz('paid_at');
            $table->jsonb('gateway_payload')->nullable(); // Menyimpan respon mentah dari payment gateway jika ada
            
            $table->timestampsTz();

            $table->index('sale_id');
        });

        //Tabel receipt_counters (Penomoran struk harian yang atomik per outlet)
        Schema::create('receipt_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->date('counter_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestampsTz();

            $table->unique(['outlet_id', 'counter_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_counters');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_discounts');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};