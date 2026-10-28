<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\VariantBarcode;
use App\Modules\Catalog\Models\VariantUnit;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('code', 'neraca-demo')->firstOrFail();
        $taxPpn = Tax::where('tenant_id', $tenant->id)->where('kind', 'tax')->first();

        // 1. Satuan Dasar & Konversi (Units)
        $unitPcs = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'pcs'],
            ['name' => 'Pieces (Pcs)']
        );
        $unitDus = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'dus'],
            ['name' => 'Dus / Karton']
        );
        $unitCup = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'cup'],
            ['name' => 'Cup / Gelas']
        );
        $unitKg = Unit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'kg'],
            ['name' => 'Kilogram (Kg)']
        );

        // 2. Kategori Produk
        $catMinuman = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Minuman Kafe'],
            ['sort_order' => 1, 'is_active' => true]
        );
        $catSembako = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sembako & Toko'],
            ['sort_order' => 2, 'is_active' => true]
        );

        // 3. Kelompok Harga (Price Lists)
        $plRetail = PriceList::firstOrCreate(
            ['tenant_id' => $tenant->id, 'kind' => 'retail'],
            ['name' => 'Harga Umum (Retail)', 'is_active' => true]
        );
        $plMember = PriceList::firstOrCreate(
            ['tenant_id' => $tenant->id, 'kind' => 'member'],
            ['name' => 'Harga Anggota Koperasi', 'is_active' => true]
        );

        // 4. Contoh Produk 1: Menu Kafe (Es Kopi Susu Gula Aren)
        $prodKopi = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Es Kopi Susu Gula Aren'],
            [
                'category_id' => $catMinuman->id,
                'kind' => 'menu',
                'track_stock' => false,
                'tax_id' => $taxPpn?->id,
                'description' => 'Kopi susu espresso dengan gula aren asli',
                'is_active' => true,
            ]
        );

        $varKopiReg = ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'CAFE-KOP-001'],
            [
                'product_id' => $prodKopi->id,
                'name' => 'Regular',
                'base_unit_id' => $unitCup->id,
                'cost_price' => 8000,
                'sell_price' => 18000,
                'is_active' => true,
            ]
        );

        // 5. Contoh Produk 2: Barang Koperasi/Toko dengan Multi-Satuan (Mie Instan Goreng)
        $prodMie = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Mie Instan Goreng Spesial'],
            [
                'category_id' => $catSembako->id,
                'kind' => 'goods',
                'track_stock' => true,
                'tax_id' => $taxPpn?->id,
                'description' => 'Mie instan goreng kemasan standar',
                'is_active' => true,
            ]
        );

        $varMie = ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'TOKO-MIE-001'],
            [
                'product_id' => $prodMie->id,
                'name' => 'Default',
                'base_unit_id' => $unitPcs->id,
                'cost_price' => 2800,
                'sell_price' => 3500,
                'is_active' => true,
            ]
        );

        // Konversi Satuan: 1 Dus = 40 Pcs dengan harga jual khusus Rp 132.000
        VariantUnit::firstOrCreate(
            ['variant_id' => $varMie->id, 'unit_id' => $unitDus->id],
            [
                'factor' => 40.000,
                'sell_price' => 132000,
            ]
        );

        // Barcode untuk Pcs & Barcode untuk Dus
        VariantBarcode::firstOrCreate(
            ['tenant_id' => $tenant->id, 'barcode' => '8998866200011'],
            [
                'variant_id' => $varMie->id,
                'unit_id' => $unitPcs->id,
            ]
        );
        VariantBarcode::firstOrCreate(
            ['tenant_id' => $tenant->id, 'barcode' => '8998866200012'],
            [
                'variant_id' => $varMie->id,
                'unit_id' => $unitDus->id,
            ]
        );

        // Harga Khusus Anggota Koperasi untuk Mie Instan (Rp 3.200 / pcs)
        Price::firstOrCreate(
            [
                'price_list_id' => $plMember->id,
                'variant_id' => $varMie->id,
                'unit_id' => $unitPcs->id,
                'min_qty' => 1.000,
            ],
            [
                'price' => 3200,
            ]
        );
    }
}