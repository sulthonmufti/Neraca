<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\VariantBarcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogManagementController extends Controller
{
    // =========================================================================
    // KATEGORI (CATEGORIES)
    // =========================================================================

    public function listCategories(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi kategori gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create([
            'name' => $request->input('name'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi kategori gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->update([
            'name' => $request->input('name'),
            'sort_order' => $request->input('sort_order', $category->sort_order),
            'is_active' => $request->input('is_active', $category->is_active),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui',
            'data' => $category,
        ]);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Hanya tolak jika masih dipakai oleh produk AKTIF
        if (Product::where('category_id', $category->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak dapat dihapus karena masih digunakan oleh produk aktif.',
            ], 400);
        }

        DB::transaction(function () use ($category) {
            // Lepaskan relasi dari produk yang sudah terhapus (soft-deleted)
            Product::onlyTrashed()
                ->where('category_id', $category->id)
                ->update(['category_id' => null]);

            $category->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus',
        ]);
    }

    // =========================================================================
    // SATUAN (UNITS)
    // =========================================================================

    public function listUnits(): JsonResponse
    {
        $units = Unit::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $units,
        ]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('units', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi satuan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $unit = Unit::create([
            'code' => strtolower(trim($request->input('code'))),
            'name' => $request->input('name'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil ditambahkan',
            'data' => $unit,
        ], 201);
    }

    public function destroyUnit(int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        if (ProductVariant::withTrashed()->where('base_unit_id', $unit->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Satuan tidak dapat dihapus karena masih digunakan oleh produk (termasuk riwayat produk terhapus).',
            ], 400);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Satuan berhasil dihapus',
        ]);
    }

    // =========================================================================
    // PRODUK & VARIAN (PRODUCTS + VARIANTS + BARCODES)
    // =========================================================================

    public function listProducts(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $products = Product::with(['category', 'variants.baseUnit', 'variants.barcodes'])
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhereHas('variants', function ($vq) use ($search) {
                            $vq->where('sku', 'ilike', "%{$search}%")
                                ->orWhereHas('barcodes', fn ($bq) => $bq->where('barcode', 'ilike', "%{$search}%"));
                        });
                });
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'kind' => 'required|in:goods,service,menu,ingredient',
            'track_stock' => 'required|boolean',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'variants' => 'required|array|min:1',
            'variants.*.sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->where('tenant_id', $tenantId),
            ],
            'variants.*.name' => 'required|string|max:255',
            'variants.*.base_unit_id' => 'required|integer|exists:units,id',
            'variants.*.cost_price' => 'required|integer|min:0',
            'variants.*.sell_price' => 'required|integer|min:0',
            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('variant_barcodes', 'barcode')->where('tenant_id', $tenantId),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi produk gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = DB::transaction(function () use ($request) {
            $product = Product::create([
                'category_id' => $request->input('category_id'),
                'name' => $request->input('name'),
                'kind' => $request->input('kind'),
                'track_stock' => $request->boolean('track_stock'),
                'description' => $request->input('description'),
                'is_active' => $request->input('is_active', true),
            ]);

            foreach ($request->input('variants') as $v) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => trim($v['sku']),
                    'name' => trim($v['name']),
                    'base_unit_id' => $v['base_unit_id'],
                    'cost_price' => (int) $v['cost_price'],
                    'sell_price' => (int) $v['sell_price'],
                    'is_active' => true,
                ]);

                if (!empty($v['barcode'])) {
                    VariantBarcode::create([
                        'variant_id' => $variant->id,
                        'unit_id' => $variant->base_unit_id,
                        'barcode' => trim($v['barcode']),
                    ]);
                }
            }

            return $product->load(['category', 'variants.baseUnit', 'variants.barcodes']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product,
        ], 201);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product = Product::with('variants.barcodes')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'kind' => 'required|in:goods,service,menu,ingredient',
            'track_stock' => 'required|boolean',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'nullable|integer',
            'variants.*.sku' => 'required|string|max:100',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.base_unit_id' => 'required|integer|exists:units,id',
            'variants.*.cost_price' => 'required|integer|min:0',
            'variants.*.sell_price' => 'required|integer|min:0',
            'variants.*.barcode' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pembaruan produk gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updated = DB::transaction(function () use ($request, $product, $tenantId) {
                $product->update([
                    'category_id' => $request->input('category_id'),
                    'name' => $request->input('name'),
                    'kind' => $request->input('kind'),
                    'track_stock' => $request->boolean('track_stock'),
                    'description' => $request->input('description'),
                    'is_active' => $request->input('is_active', $product->is_active),
                ]);

                $keptVariantIds = [];

                foreach ($request->input('variants') as $v) {
                    $variantId = $v['id'] ?? null;
                    $sku = trim($v['sku']);

                    // Cek duplikasi SKU pada tenant yang sama
                    $skuExists = ProductVariant::where('tenant_id', $tenantId)
                        ->where('sku', $sku)
                        ->when($variantId, fn ($q) => $q->where('id', '!=', $variantId))
                        ->exists();

                    if ($skuExists) {
                        throw new \Exception("SKU '{$sku}' sudah digunakan oleh produk lain.");
                    }

                    if ($variantId) {
                        /** @var ProductVariant $variant */
                        $variant = ProductVariant::where('product_id', $product->id)
                            ->where('id', $variantId)
                            ->firstOrFail();

                        $variant->update([
                            'sku' => $sku,
                            'name' => trim($v['name']),
                            'base_unit_id' => $v['base_unit_id'],
                            'cost_price' => (int) $v['cost_price'],
                            'sell_price' => (int) $v['sell_price'],
                        ]);
                    } else {
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $sku,
                            'name' => trim($v['name']),
                            'base_unit_id' => $v['base_unit_id'],
                            'cost_price' => (int) $v['cost_price'],
                            'sell_price' => (int) $v['sell_price'],
                            'is_active' => true,
                        ]);
                    }

                    $keptVariantIds[] = $variant->id;

                    // Sinkronisasi barcode utama (jika diisi)
                    $barcode = isset($v['barcode']) ? trim((string) $v['barcode']) : '';
                    if ($barcode !== '') {
                        $barcodeTaken = VariantBarcode::where('tenant_id', $tenantId)
                            ->where('barcode', $barcode)
                            ->where('variant_id', '!=', $variant->id)
                            ->exists();

                        if ($barcodeTaken) {
                            throw new \Exception("Barcode '{$barcode}' sudah digunakan oleh produk lain.");
                        }

                        /** @var VariantBarcode|null $firstBarcode */
                        $firstBarcode = $variant->barcodes()->first();
                        if ($firstBarcode) {
                            $firstBarcode->update([
                                'unit_id' => $variant->base_unit_id,
                                'barcode' => $barcode,
                            ]);
                        } else {
                            VariantBarcode::create([
                                'variant_id' => $variant->id,
                                'unit_id' => $variant->base_unit_id,
                                'barcode' => $barcode,
                            ]);
                        }
                    }
                }

                // soft-delete varian lama yang dihapus dari form
                $product->variants()->whereNotIn('id', $keptVariantIds)->delete();

                return $product->fresh(['category', 'variants.baseUnit', 'variants.barcodes']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroyProduct(int $id): JsonResponse
    {
        $product = Product::with('variants')->findOrFail($id);

        DB::transaction(function () use ($product) {
            foreach ($product->variants as $variant) {
                $variant->delete();
            }
            $product->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
        ]);
    }
}