<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\VariantBarcode;
use App\Modules\Core\Models\PaymentMethod;
use App\Modules\Core\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosCatalogController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $outletId = (int) $request->query('outlet_id', 1);

        $categories = Category::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $units = Unit::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

        $taxes = Tax::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $stockLevels = DB::table('stock_levels')
            ->where('outlet_id', $outletId)
            ->pluck('qty', 'variant_id');

        $barcodesByVariant = VariantBarcode::where('tenant_id', $tenantId)
            ->get()
            ->groupBy('variant_id');

        $variants = ProductVariant::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('product_id', $products->keys())
            ->get()
            ->map(function ($variant) use ($products, $units, $taxes, $stockLevels, $barcodesByVariant) {
                $product = $products->get($variant->product_id);
                $unit = $units->get($variant->base_unit_id);
                $tax = $product?->tax_id ? $taxes->get($product->tax_id) : null;
                $barcodes = $barcodesByVariant->get($variant->id, collect())
                    ->pluck('barcode')
                    ->values()
                    ->all();

                $displayName = $variant->name && strtolower($variant->name) !== 'default'
                    ? "{$product->name} ({$variant->name})"
                    : $product->name;

                return [
                    'variant_id' => $variant->id,
                    'product_id' => $product->id,
                    'name' => $displayName,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'category_id' => $product->category_id,
                    'base_unit_id' => $variant->base_unit_id,
                    'unit_code' => $unit?->code ?? 'pcs',
                    'sell_price' => (int) $variant->sell_price,
                    'track_stock' => (bool) $product->track_stock,
                    'stock_qty' => (float) ($stockLevels->get($variant->id, 0)),
                    'tax_rate' => $tax ? (float) $tax->rate_percent : 0,
                    'tax_inclusive' => $tax ? (bool) $tax->is_inclusive : false,
                    'barcodes' => $barcodes,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'items' => $variants,
            ],
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $methods = PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'type', 'sort_order']);

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }
}