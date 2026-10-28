<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel categories (Kategori bertingkat)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        //Tabel units (Satuan: pcs, dus, porsi, kg)
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code']);
        });

        //Tabel products (Data induk produk)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 30); // goods, service, menu, ingredient
            $table->string('image_path')->nullable();
            $table->boolean('track_stock')->default(true);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        //Tabel product_variants (Varian produk yang dijual & dihitung stoknya)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 100);
            $table->string('name');
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->bigInteger('cost_price')->default(0); // BIGINT rupiah (HPP)
            $table->bigInteger('sell_price')->default(0); // BIGINT rupiah (Harga jual dasar)
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'sku']);
        });

        //Tabel variant_units (Konversi ke satuan dasar, misal: 1 dus = 24 pcs)
        Schema::create('variant_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 14, 3); // NUMERIC(14,3)
            $table->bigInteger('sell_price')->nullable(); // Harga khusus satuan ini jika ada
            $table->timestampsTz();

            $table->unique(['variant_id', 'unit_id']);
        });

        //Tabel variant_barcodes (Barcode ganda per varian atau satuan)
        Schema::create('variant_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('barcode', 100);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'barcode']);
        });

        //Tabel price_lists (Kelompok harga: retail, wholesale, member)
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30); // retail, wholesale, member
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        //Tabel prices (Harga bertingkat & harga khusus)
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('min_qty', 14, 3)->default(1); // NUMERIC(14,3)
            $table->bigInteger('price'); // BIGINT rupiah
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        //Tabel promotions (Promo diskon persen, nominal, atau buy_x_get_y)
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 30); // percent, amount, buy_x_get_y
            $table->jsonb('rules');
            // Foreign key ke member_types akan dihubungkan setelah modul Member dibuat
            $table->unsignedBigInteger('member_type_id')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('variant_barcodes');
        Schema::dropIfExists('variant_units');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};