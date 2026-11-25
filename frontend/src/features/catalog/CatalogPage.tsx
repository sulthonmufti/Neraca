import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertCircle,
  Boxes,
  Layers,
  Loader2,
  Package,
  Pencil,
  Plus,
  Ruler,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { api } from '../../lib/api';

interface Category {
  id: number;
  name: string;
  sort_order: number;
  is_active: boolean;
}

interface Unit {
  id: number;
  code: string;
  name: string;
}

interface VariantBarcode {
  id: number;
  barcode: string;
}

interface ProductVariant {
  id: number;
  sku: string;
  name: string;
  base_unit_id: number;
  cost_price: number;
  sell_price: number;
  is_active: boolean;
  base_unit?: Unit;
  barcodes?: VariantBarcode[];
}

interface Product {
  id: number;
  category_id: number | null;
  name: string;
  kind: 'goods' | 'service' | 'menu' | 'ingredient';
  track_stock: boolean;
  description: string | null;
  is_active: boolean;
  category?: Category | null;
  variants: ProductVariant[];
}

interface VariantFormRow {
  id?: number;
  sku: string;
  name: string;
  base_unit_id: string;
  cost_price: string;
  sell_price: string;
  barcode: string;
}

const formatRp = (n: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n);

const KIND_LABELS: Record<Product['kind'], string> = {
  goods: 'Barang Toko',
  menu: 'Menu Kafe/Resto',
  service: 'Jasa',
  ingredient: 'Bahan Baku',
};

export function CatalogPage() {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'products' | 'categories' | 'units'>('products');

  // Filter Produk
  const [search, setSearch] = useState('');
  const [filterCategory, setFilterCategory] = useState<string>('');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Form Kategori & Satuan
  const [catName, setCatName] = useState('');
  const [unitCode, setUnitCode] = useState('');
  const [unitName, setUnitName] = useState('');

  // Modal Produk
  const [productModalOpen, setProductModalOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [prodName, setProdName] = useState('');
  const [prodCategoryId, setProdCategoryId] = useState('');
  const [prodKind, setProdKind] = useState<Product['kind']>('goods');
  const [prodTrackStock, setProdTrackStock] = useState(true);
  const [prodActive, setProdActive] = useState(true);
  const [prodDesc, setProdDesc] = useState('');
  const [variants, setVariants] = useState<VariantFormRow[]>([]);
  const [modalError, setModalError] = useState<string | null>(null);

  // 1. Queries
  const { data: categories = [], isLoading: loadingCategories } = useQuery<Category[]>({
    queryKey: ['catalog-categories'],
    queryFn: async () => {
      const res = await api.get('/catalog/categories');
      return res.data?.data ?? [];
    },
  });

  const { data: units = [], isLoading: loadingUnits } = useQuery<Unit[]>({
    queryKey: ['catalog-units'],
    queryFn: async () => {
      const res = await api.get('/catalog/units');
      return res.data?.data ?? [];
    },
  });

  const { data: products = [], isLoading: loadingProducts } = useQuery<Product[]>({
    queryKey: ['catalog-products', search, filterCategory],
    queryFn: async () => {
      const res = await api.get('/catalog/products', {
        params: {
          search: search.trim() || undefined,
          category_id: filterCategory || undefined,
        },
      });
      return res.data?.data ?? [];
    },
  });

  // 2. Mutations: Kategori
  const createCategoryMutation = useMutation({
    mutationFn: async () => {
      await api.post('/catalog/categories', { name: catName.trim() });
    },
    onSuccess: () => {
      setCatName('');
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['catalog-categories'] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal menambah kategori.');
    },
  });

  const deleteCategoryMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/catalog/categories/${id}`);
    },
    onSuccess: () => {
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['catalog-categories'] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal menghapus kategori.');
    },
  });

  // 3. Mutations: Satuan
  const createUnitMutation = useMutation({
    mutationFn: async () => {
      await api.post('/catalog/units', {
        code: unitCode.trim(),
        name: unitName.trim(),
      });
    },
    onSuccess: () => {
      setUnitCode('');
      setUnitName('');
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['catalog-units'] });
    },
    onError: (err: any) => {
      const firstErr = Object.values(err.response?.data?.errors ?? {})[0] as string[] | undefined;
      setErrorMsg(firstErr?.[0] || err.response?.data?.message || 'Gagal menambah satuan.');
    },
  });

  const deleteUnitMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/catalog/units/${id}`);
    },
    onSuccess: () => {
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['catalog-units'] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal menghapus satuan.');
    },
  });

  // 4. Mutations: Produk
  const saveProductMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: prodName.trim(),
        category_id: prodCategoryId ? Number(prodCategoryId) : null,
        kind: prodKind,
        track_stock: prodTrackStock,
        is_active: prodActive,
        description: prodDesc.trim() || null,
        variants: variants.map((v) => ({
          id: v.id,
          sku: v.sku.trim(),
          name: v.name.trim() || 'Default',
          base_unit_id: Number(v.base_unit_id),
          cost_price: Number(v.cost_price) || 0,
          sell_price: Number(v.sell_price) || 0,
          barcode: v.barcode.trim() || null,
        })),
      };

      if (editingProduct) {
        await api.put(`/catalog/products/${editingProduct.id}`, payload);
      } else {
        await api.post('/catalog/products', payload);
      }
    },
    onSuccess: () => {
      setProductModalOpen(false);
      setModalError(null);
      queryClient.invalidateQueries({ queryKey: ['catalog-products'] });
      queryClient.invalidateQueries({ queryKey: ['pos-catalog'] });
    },
    onError: (err: any) => {
      const firstErr = Object.values(err.response?.data?.errors ?? {})[0] as string[] | undefined;
      setModalError(firstErr?.[0] || err.response?.data?.message || 'Gagal menyimpan produk.');
    },
  });

  const deleteProductMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/catalog/products/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['catalog-products'] });
      queryClient.invalidateQueries({ queryKey: ['pos-catalog'] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal menghapus produk.');
    },
  });

  // Helpers Modal Produk
  const openCreateModal = () => {
    setEditingProduct(null);
    setProdName('');
    setProdCategoryId(categories[0]?.id ? String(categories[0].id) : '');
    setProdKind('goods');
    setProdTrackStock(true);
    setProdActive(true);
    setProdDesc('');
    setVariants([
      {
        sku: '',
        name: 'Default',
        base_unit_id: units[0]?.id ? String(units[0].id) : '',
        cost_price: '0',
        sell_price: '0',
        barcode: '',
      },
    ]);
    setModalError(null);
    setProductModalOpen(true);
  };

  const openEditModal = (product: Product) => {
    setEditingProduct(product);
    setProdName(product.name);
    setProdCategoryId(product.category_id ? String(product.category_id) : '');
    setProdKind(product.kind);
    setProdTrackStock(product.track_stock);
    setProdActive(product.is_active);
    setProdDesc(product.description ?? '');
    setVariants(
      product.variants.map((v) => ({
        id: v.id,
        sku: v.sku,
        name: v.name,
        base_unit_id: String(v.base_unit_id),
        cost_price: String(v.cost_price),
        sell_price: String(v.sell_price),
        barcode: v.barcodes?.[0]?.barcode ?? '',
      }))
    );
    setModalError(null);
    setProductModalOpen(true);
  };

  const updateVariantField = (idx: number, field: keyof VariantFormRow, value: string) => {
    setVariants((prev) => prev.map((row, i) => (i === idx ? { ...row, [field]: value } : row)));
  };

  const addVariantRow = () => {
    setVariants((prev) => [
      ...prev,
      {
        sku: '',
        name: '',
        base_unit_id: units[0]?.id ? String(units[0].id) : '',
        cost_price: '0',
        sell_price: '0',
        barcode: '',
      },
    ]);
  };

  const removeVariantRow = (idx: number) => {
    setVariants((prev) => prev.filter((_, i) => i !== idx));
  };

  return (
    <div className="space-y-5 pb-8">
      {/* Header & Tabs */}
      <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-[#1c1c1e]">Master Produk & Katalog</h1>
          <p className="text-xs text-slate-400 mt-0.5">
            Kelola produk, varian harga, barcode, kategori, dan satuan barang.
          </p>
        </div>

        <div className="flex items-center gap-2 bg-[#F5F6FA] p-1.5 rounded-2xl w-fit">
          <button
            type="button"
            onClick={() => {
              setActiveTab('products');
              setErrorMsg(null);
            }}
            className={`px-4 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer ${
              activeTab === 'products'
                ? 'bg-[#1c1c1e] text-white'
                : 'text-slate-600 hover:text-[#1c1c1e]'
            }`}
          >
            <Package className="w-3.5 h-3.5" />
            Produk ({products.length})
          </button>
          <button
            type="button"
            onClick={() => {
              setActiveTab('categories');
              setErrorMsg(null);
            }}
            className={`px-4 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer ${
              activeTab === 'categories'
                ? 'bg-[#1c1c1e] text-white'
                : 'text-slate-600 hover:text-[#1c1c1e]'
            }`}
          >
            <Layers className="w-3.5 h-3.5" />
            Kategori ({categories.length})
          </button>
          <button
            type="button"
            onClick={() => {
              setActiveTab('units');
              setErrorMsg(null);
            }}
            className={`px-4 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer ${
              activeTab === 'units'
                ? 'bg-[#1c1c1e] text-white'
                : 'text-slate-600 hover:text-[#1c1c1e]'
            }`}
          >
            <Ruler className="w-3.5 h-3.5" />
            Satuan ({units.length})
          </button>
        </div>
      </div>

      {errorMsg && (
        <div className="p-4 rounded-2xl bg-rose-50 text-rose-600 text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {errorMsg}
        </div>
      )}

      {/* TAB 1: PRODUK */}
      {activeTab === 'products' && (
        <div className="space-y-4">
          <div className="bg-white rounded-[24px] p-4 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama produk, SKU, atau barcode..."
                className="w-full h-11 pl-11 pr-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#8b7fd0]"
              />
            </div>

            <select
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              className="h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm text-[#1c1c1e] focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
            >
              <option value="">Semua Kategori</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>

            <button
              type="button"
              onClick={openCreateModal}
              className="h-11 px-5 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 shrink-0 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Tambah Produk
            </button>
          </div>

          <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] overflow-x-auto">
            {loadingProducts ? (
              <div className="flex items-center justify-center py-12 text-slate-400 text-sm gap-2">
                <Loader2 className="w-4 h-4 animate-spin" /> Memuat produk...
              </div>
            ) : products.length === 0 ? (
              <div className="text-center py-12 text-slate-400 text-sm">
                Belum ada produk. Klik <b>Tambah Produk</b> untuk mulai mengisi katalog.
              </div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="py-3 px-3">Produk</th>
                    <th className="py-3 px-3">Kategori & Jenis</th>
                    <th className="py-3 px-3">Varian / SKU / Barcode</th>
                    <th className="py-3 px-3 text-right">Harga Jual</th>
                    <th className="py-3 px-3 text-center">Status</th>
                    <th className="py-3 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">
                  {products.map((p) => (
                    <tr key={p.id} className="hover:bg-[#F5F6FA]/60 transition">
                      <td className="py-3.5 px-3">
                        <div className="font-semibold text-[#1c1c1e]">{p.name}</div>
                        <div className="text-[11px] text-slate-400">
                          {p.track_stock ? 'Lacak stok aktif' : 'Tanpa lacak stok'}
                        </div>
                      </td>
                      <td className="py-3.5 px-3">
                        <div className="text-xs font-medium text-slate-700">
                          {p.category?.name ?? '-'}
                        </div>
                        <div className="text-[11px] text-slate-400">{KIND_LABELS[p.kind]}</div>
                      </td>
                      <td className="py-3.5 px-3">
                        <div className="space-y-1">
                          {p.variants.map((v) => (
                            <div key={v.id} className="text-xs">
                              <span className="font-medium text-[#1c1c1e]">{v.name}</span>
                              <span className="text-slate-400"> · {v.sku}</span>
                              {v.barcodes?.[0]?.barcode && (
                                <span className="ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 text-[10px] font-mono text-slate-600">
                                  {v.barcodes[0].barcode}
                                </span>
                              )}
                            </div>
                          ))}
                        </div>
                      </td>
                      <td className="py-3.5 px-3 text-right">
                        <div className="space-y-1">
                          {p.variants.map((v) => (
                            <div key={v.id} className="text-xs font-bold text-[#7c6fd6]">
                              {formatRp(v.sell_price)}
                              <span className="font-normal text-slate-400">
                                {' '}
                                / {v.base_unit?.code ?? 'pcs'}
                              </span>
                            </div>
                          ))}
                        </div>
                      </td>
                      <td className="py-3.5 px-3 text-center">
                        <span
                          className={`px-2.5 py-1 rounded-full text-[11px] font-medium ${
                            p.is_active
                              ? 'bg-emerald-50 text-emerald-700'
                              : 'bg-slate-100 text-slate-500'
                          }`}
                        >
                          {p.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </td>
                      <td className="py-3.5 px-3 text-right">
                        <div className="inline-flex items-center gap-1.5">
                          <button
                            type="button"
                            onClick={() => openEditModal(p)}
                            className="w-8 h-8 rounded-xl bg-[#F5F6FA] hover:bg-slate-200 text-slate-700 flex items-center justify-center cursor-pointer"
                            title="Edit Produk"
                          >
                            <Pencil className="w-3.5 h-3.5" />
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              if (window.confirm(`Hapus produk "${p.name}"?`)) {
                                deleteProductMutation.mutate(p.id);
                              }
                            }}
                            className="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center cursor-pointer"
                            title="Hapus Produk"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: KATEGORI */}
      {activeTab === 'categories' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <div className="lg:col-span-4 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] h-fit">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Tambah Kategori</h2>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (catName.trim()) createCategoryMutation.mutate();
              }}
              className="space-y-3"
            >
              <input
                type="text"
                required
                value={catName}
                onChange={(e) => setCatName(e.target.value)}
                placeholder="Contoh: Minuman Dingin, ATK, Snack"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <button
                type="submit"
                disabled={createCategoryMutation.isPending}
                className="w-full h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium cursor-pointer"
              >
                {createCategoryMutation.isPending ? 'Menyimpan...' : 'Simpan Kategori'}
              </button>
            </form>
          </div>

          <div className="lg:col-span-8 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Daftar Kategori</h2>
            {loadingCategories ? (
              <div className="py-8 text-center text-slate-400 text-sm">Memuat...</div>
            ) : (
              <div className="divide-y divide-slate-100">
                {categories.map((c) => (
                  <div key={c.id} className="py-3 flex items-center justify-between">
                    <span className="text-sm font-medium text-[#1c1c1e]">{c.name}</span>
                    <button
                      type="button"
                      onClick={() => {
                        if (window.confirm(`Hapus kategori "${c.name}"?`)) {
                          deleteCategoryMutation.mutate(c.id);
                        }
                      }}
                      className="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center cursor-pointer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 3: SATUAN */}
      {activeTab === 'units' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <div className="lg:col-span-4 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] h-fit">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Tambah Satuan</h2>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (unitCode.trim() && unitName.trim()) createUnitMutation.mutate();
              }}
              className="space-y-3"
            >
              <input
                type="text"
                required
                value={unitCode}
                onChange={(e) => setUnitCode(e.target.value)}
                placeholder="Kode satuan (misal: btl, pack, porsi)"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <input
                type="text"
                required
                value={unitName}
                onChange={(e) => setUnitName(e.target.value)}
                placeholder="Nama satuan (misal: Botol, Pack, Porsi)"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <button
                type="submit"
                disabled={createUnitMutation.isPending}
                className="w-full h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium cursor-pointer"
              >
                {createUnitMutation.isPending ? 'Menyimpan...' : 'Simpan Satuan'}
              </button>
            </form>
          </div>

          <div className="lg:col-span-8 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Daftar Satuan</h2>
            {loadingUnits ? (
              <div className="py-8 text-center text-slate-400 text-sm">Memuat...</div>
            ) : (
              <div className="divide-y divide-slate-100">
                {units.map((u) => (
                  <div key={u.id} className="py-3 flex items-center justify-between">
                    <div>
                      <span className="text-sm font-semibold text-[#1c1c1e]">{u.name}</span>
                      <span className="ml-2 px-2 py-0.5 rounded bg-[#F5F6FA] text-xs font-mono text-slate-500">
                        {u.code}
                      </span>
                    </div>
                    <button
                      type="button"
                      onClick={() => {
                        if (window.confirm(`Hapus satuan "${u.name}"?`)) {
                          deleteUnitMutation.mutate(u.id);
                        }
                      }}
                      className="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center cursor-pointer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL TAMBAH / EDIT PRODUK */}
      {productModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-[28px] w-full max-w-2xl p-6 shadow-xl my-8">
            <div className="flex items-center justify-between mb-5">
              <div className="flex items-center gap-2">
                <Boxes className="w-5 h-5 text-[#8b7fd0]" />
                <h3 className="text-lg font-semibold text-[#1c1c1e]">
                  {editingProduct ? 'Edit Produk' : 'Tambah Produk Baru'}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setProductModalOpen(false)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {modalError && (
              <div className="mb-4 p-3 rounded-xl bg-rose-50 text-rose-600 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                {modalError}
              </div>
            )}

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveProductMutation.mutate();
              }}
              className="space-y-4 max-h-[75vh] overflow-y-auto pr-1"
            >
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-medium text-slate-500 mb-1">
                    Nama Produk *
                  </label>
                  <input
                    type="text"
                    required
                    value={prodName}
                    onChange={(e) => setProdName(e.target.value)}
                    placeholder="Contoh: Teh Tarik Dingin / Gula Pasir 1Kg"
                    className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-500 mb-1">Kategori</label>
                  <select
                    value={prodCategoryId}
                    onChange={(e) => setProdCategoryId(e.target.value)}
                    className="w-full h-11 px-3 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                  >
                    <option value="">Tanpa Kategori</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-500 mb-1">
                    Jenis Produk *
                  </label>
                  <select
                    value={prodKind}
                    onChange={(e) => setProdKind(e.target.value as Product['kind'])}
                    className="w-full h-11 px-3 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                  >
                    <option value="goods">Barang Toko (Goods)</option>
                    <option value="menu">Menu Kafe/Resto (Menu)</option>
                    <option value="service">Jasa (Service)</option>
                    <option value="ingredient">Bahan Baku (Ingredient)</option>
                  </select>
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-6 pt-1">
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={prodTrackStock}
                    onChange={(e) => setProdTrackStock(e.target.checked)}
                    className="rounded accent-[#1c1c1e]"
                  />
                  Lacak Stok Gudang
                </label>
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={prodActive}
                    onChange={(e) => setProdActive(e.target.checked)}
                    className="rounded accent-[#1c1c1e]"
                  />
                  Produk Aktif (Tampil di Kasir)
                </label>
              </div>

              {/* Daftar Varian */}
              <div className="pt-3 border-t border-slate-100 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#1c1c1e]">
                    Varian, Harga & Barcode
                  </span>
                  <button
                    type="button"
                    onClick={addVariantRow}
                    className="text-xs font-medium text-[#7c6fd6] hover:underline flex items-center gap-1 cursor-pointer"
                  >
                    <Plus className="w-3.5 h-3.5" /> Tambah Varian
                  </button>
                </div>

                {variants.map((v, idx) => (
                  <div key={idx} className="p-4 rounded-2xl bg-[#F5F6FA] space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-[11px] font-semibold text-slate-500">
                        Varian #{idx + 1}
                      </span>
                      {variants.length > 1 && !v.id && (
                        <button
                          type="button"
                          onClick={() => removeVariantRow(idx)}
                          className="text-xs text-rose-500 hover:underline cursor-pointer"
                        >
                          Hapus
                        </button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                      <input
                        type="text"
                        required
                        value={v.name}
                        onChange={(e) => updateVariantField(idx, 'name', e.target.value)}
                        placeholder="Nama varian (Default/Large)"
                        className="h-10 px-3 rounded-xl bg-white text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      />
                      <input
                        type="text"
                        required
                        value={v.sku}
                        onChange={(e) => updateVariantField(idx, 'sku', e.target.value)}
                        placeholder="SKU unik *"
                        className="h-10 px-3 rounded-xl bg-white text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      />
                      <select
                        required
                        value={v.base_unit_id}
                        onChange={(e) => updateVariantField(idx, 'base_unit_id', e.target.value)}
                        className="h-10 px-3 rounded-xl bg-white text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      >
                        <option value="">Pilih Satuan *</option>
                        {units.map((u) => (
                          <option key={u.id} value={u.id}>
                            {u.name} ({u.code})
                          </option>
                        ))}
                      </select>

                      <input
                        type="number"
                        min={0}
                        required
                        value={v.cost_price}
                        onChange={(e) => updateVariantField(idx, 'cost_price', e.target.value)}
                        placeholder="Harga Modal (Rp)"
                        className="h-10 px-3 rounded-xl bg-white text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      />
                      <input
                        type="number"
                        min={0}
                        required
                        value={v.sell_price}
                        onChange={(e) => updateVariantField(idx, 'sell_price', e.target.value)}
                        placeholder="Harga Jual (Rp) *"
                        className="h-10 px-3 rounded-xl bg-white text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      />
                      <input
                        type="text"
                        value={v.barcode}
                        onChange={(e) => updateVariantField(idx, 'barcode', e.target.value)}
                        placeholder="Barcode (opsional)"
                        className="h-10 px-3 rounded-xl bg-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                      />
                    </div>
                  </div>
                ))}
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setProductModalOpen(false)}
                  className="px-5 h-11 rounded-xl bg-[#F5F6FA] hover:bg-slate-200 text-sm font-medium text-[#1c1c1e] cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={saveProductMutation.isPending}
                  className="px-6 h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                >
                  {saveProductMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
                  {editingProduct ? 'Simpan Perubahan' : 'Simpan Produk'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}