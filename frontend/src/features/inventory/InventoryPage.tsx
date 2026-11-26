import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertCircle,
  AlertTriangle,
  ArrowDownToLine,
  Boxes,
  ClipboardCheck,
  Loader2,
  Plus,
  Search,
  Trash2,
  Truck,
  X,
} from 'lucide-react';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';

interface StockLevel {
  id: number;
  outlet_id: number;
  variant_id: number;
  qty: number;
  reorder_point: number;
  variant?: {
    id: number;
    sku: string;
    name: string;
    base_unit_id: number;
    cost_price: number;
    product?: {
      id: number;
      name: string;
      track_stock: boolean;
    };
  };
}

interface Supplier {
  id: number;
  code: string;
  name: string;
  contact_person: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
}

interface GoodsReceipt {
  id: number;
  receipt_no?: string;
  supplier_invoice_no: string | null;
  note: string | null;
  received_at: string;
  total_cost?: number;
  supplier?: Supplier | null;
  user?: { id: number; name: string } | null;
  items: {
    id: number;
    variant_id: number;
    qty: number;
    unit_cost: number;
    variant?: { sku: string; name: string };
  }[];
}

interface StockOpname {
  id: number;
  opname_no?: string;
  note: string | null;
  opname_at: string;
  user?: { id: number; name: string } | null;
  items: {
    id: number;
    variant_id: number;
    system_qty?: number;
    physical_qty: number;
    diff_qty?: number;
    reason: string | null;
    variant?: { sku: string; name: string };
  }[];
}

interface CatalogProduct {
  id: number;
  name: string;
  track_stock: boolean;
  variants: {
    id: number;
    sku: string;
    name: string;
    base_unit_id: number;
    cost_price: number;
    base_unit?: { id: number; code: string; name: string };
  }[];
}

const formatRp = (n: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n);

export function InventoryPage() {
  const queryClient = useQueryClient();
  const outletId = useAuthStore((s) => s.outletId);

  const [activeTab, setActiveTab] = useState<'stocks' | 'receipts' | 'opnames' | 'suppliers'>(
    'stocks'
  );
  const [search, setSearch] = useState('');
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Edit Reorder Point
  const [editingReorder, setEditingReorder] = useState<StockLevel | null>(null);
  const [reorderInput, setReorderInput] = useState('');

  // Form Supplier
  const [supCode, setSupCode] = useState('');
  const [supName, setSupName] = useState('');
  const [supContact, setSupContact] = useState('');
  const [supPhone, setSupPhone] = useState('');
  const [supAddress, setSupAddress] = useState('');

  // Modal Goods Receipt (Barang Masuk)
  const [grModalOpen, setGrModalOpen] = useState(false);
  const [grSupplierId, setGrSupplierId] = useState('');
  const [grInvoiceNo, setGrInvoiceNo] = useState('');
  const [grNote, setGrNote] = useState('');
  const [grItems, setGrItems] = useState<
    { variant_id: string; unit_id: string; qty: string; unit_cost: string }[]
  >([]);

  // Modal Stock Opname
  const [opnameModalOpen, setOpnameModalOpen] = useState(false);
  const [opnameNote, setOpnameNote] = useState('');
  const [opnameItems, setOpnameItems] = useState<
    { variant_id: string; physical_qty: string; reason: string }[]
  >([]);

  // 1. Queries
  const { data: stocks = [], isLoading: loadingStocks } = useQuery<StockLevel[]>({
    queryKey: ['inventory-stocks', outletId, lowStockOnly],
    queryFn: async () => {
      const res = await api.get('/inventory/stocks', {
        params: { outlet_id: outletId, low_stock: lowStockOnly || undefined },
      });
      return res.data?.data ?? [];
    },
  });

  const { data: suppliers = [], isLoading: loadingSuppliers } = useQuery<Supplier[]>({
    queryKey: ['suppliers'],
    queryFn: async () => {
      const res = await api.get('/suppliers');
      return res.data?.data ?? [];
    },
  });

  const { data: receipts = [], isLoading: loadingReceipts } = useQuery<GoodsReceipt[]>({
    queryKey: ['goods-receipts', outletId],
    queryFn: async () => {
      const res = await api.get('/inventory/goods-receipts', {
        params: { outlet_id: outletId },
      });
      return res.data?.data ?? [];
    },
  });

  const { data: opnames = [], isLoading: loadingOpnames } = useQuery<StockOpname[]>({
    queryKey: ['stock-opnames', outletId],
    queryFn: async () => {
      const res = await api.get('/inventory/stock-opnames', {
        params: { outlet_id: outletId },
      });
      return res.data?.data ?? [];
    },
  });

  // Daftar semua varian produk (untuk pilihan dropdown Barang Masuk & Opname)
  const { data: catalogProducts = [] } = useQuery<CatalogProduct[]>({
    queryKey: ['catalog-products-for-inventory'],
    queryFn: async () => {
      const res = await api.get('/catalog/products');
      return res.data?.data ?? [];
    },
  });

  const allVariants = useMemo(() => {
    return catalogProducts.flatMap((p) =>
      p.variants.map((v) => ({
        id: v.id,
        label: `${p.name} — ${v.name} (${v.sku})`,
        base_unit_id: v.base_unit_id,
        unit_code: v.base_unit?.code ?? 'pcs',
        cost_price: v.cost_price,
      }))
    );
  }, [catalogProducts]);

  const filteredStocks = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return stocks;
    return stocks.filter((s) => {
      const prodName = s.variant?.product?.name?.toLowerCase() ?? '';
      const varName = s.variant?.name?.toLowerCase() ?? '';
      const sku = s.variant?.sku?.toLowerCase() ?? '';
      return prodName.includes(q) || varName.includes(q) || sku.includes(q);
    });
  }, [stocks, search]);

  // 2. Mutations
  const updateReorderMutation = useMutation({
    mutationFn: async () => {
      if (!editingReorder) return;
      await api.put('/inventory/stocks/reorder-point', {
        outlet_id: outletId,
        variant_id: editingReorder.variant_id,
        reorder_point: Number(reorderInput) || 0,
      });
    },
    onSuccess: () => {
      setEditingReorder(null);
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal memperbarui reorder point.');
    },
  });

  const createSupplierMutation = useMutation({
    mutationFn: async () => {
      await api.post('/suppliers', {
        code: supCode.trim(),
        name: supName.trim(),
        contact_person: supContact.trim() || null,
        phone: supPhone.trim() || null,
        address: supAddress.trim() || null,
      });
    },
    onSuccess: () => {
      setSupCode('');
      setSupName('');
      setSupContact('');
      setSupPhone('');
      setSupAddress('');
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['suppliers'] });
    },
    onError: (err: any) => {
      const firstErr = Object.values(err.response?.data?.errors ?? {})[0] as string[] | undefined;
      setErrorMsg(firstErr?.[0] || err.response?.data?.message || 'Gagal menambah supplier.');
    },
  });

  const createGrMutation = useMutation({
    mutationFn: async () => {
      await api.post('/inventory/goods-receipts', {
        outlet_id: outletId,
        supplier_id: grSupplierId ? Number(grSupplierId) : null,
        supplier_invoice_no: grInvoiceNo.trim() || null,
        note: grNote.trim() || null,
        items: grItems.map((i) => ({
          variant_id: Number(i.variant_id),
          unit_id: Number(i.unit_id),
          qty: Number(i.qty),
          unit_cost: Number(i.unit_cost) || 0,
        })),
      });
    },
    onSuccess: () => {
      setGrModalOpen(false);
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['goods-receipts'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['pos-catalog'] });
    },
    onError: (err: any) => {
      const firstErr = Object.values(err.response?.data?.errors ?? {})[0] as string[] | undefined;
      setErrorMsg(firstErr?.[0] || err.response?.data?.message || 'Gagal menyimpan barang masuk.');
    },
  });

  const createOpnameMutation = useMutation({
    mutationFn: async () => {
      await api.post('/inventory/stock-opnames', {
        outlet_id: outletId,
        note: opnameNote.trim() || null,
        items: opnameItems.map((i) => ({
          variant_id: Number(i.variant_id),
          physical_qty: Number(i.physical_qty),
          reason: i.reason.trim() || null,
        })),
      });
    },
    onSuccess: () => {
      setOpnameModalOpen(false);
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['stock-opnames'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['pos-catalog'] });
    },
    onError: (err: any) => {
      const firstErr = Object.values(err.response?.data?.errors ?? {})[0] as string[] | undefined;
      setErrorMsg(firstErr?.[0] || err.response?.data?.message || 'Gagal menyimpan stok opname.');
    },
  });

  // Helpers Modal Goods Receipt
  const openGrModal = () => {
    setErrorMsg(null);
    setGrSupplierId(suppliers[0]?.id ? String(suppliers[0].id) : '');
    setGrInvoiceNo('');
    setGrNote('');
    const first = allVariants[0];
    setGrItems(
      first
        ? [
            {
              variant_id: String(first.id),
              unit_id: String(first.base_unit_id),
              qty: '1',
              unit_cost: String(first.cost_price),
            },
          ]
        : []
    );
    setGrModalOpen(true);
  };

  const handleGrVariantChange = (idx: number, variantId: string) => {
    const found = allVariants.find((v) => String(v.id) === variantId);
    setGrItems((prev) =>
      prev.map((row, i) =>
        i === idx
          ? {
              ...row,
              variant_id: variantId,
              unit_id: found ? String(found.base_unit_id) : row.unit_id,
              unit_cost: found ? String(found.cost_price) : row.unit_cost,
            }
          : row
      )
    );
  };

  // Helpers Modal Opname
  const openOpnameModal = () => {
    setErrorMsg(null);
    setOpnameNote('');
    const first = allVariants[0];
    setOpnameItems(first ? [{ variant_id: String(first.id), physical_qty: '0', reason: '' }] : []);
    setOpnameModalOpen(true);
  };

  return (
    <div className="space-y-5 pb-8">
      {/* Header & Tabs */}
      <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-[#1c1c1e]">Stok Gudang & Inventori</h1>
          <p className="text-xs text-slate-400 mt-0.5">
            Pantau saldo stok, terima barang dari supplier, dan lakukan stok opname.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-1.5 bg-[#F5F6FA] p-1.5 rounded-2xl w-fit">
          <button
            type="button"
            onClick={() => setActiveTab('stocks')}
            className={`px-3.5 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              activeTab === 'stocks' ? 'bg-[#1c1c1e] text-white' : 'text-slate-600'
            }`}
          >
            <Boxes className="w-3.5 h-3.5" />
            Stok ({stocks.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('receipts')}
            className={`px-3.5 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              activeTab === 'receipts' ? 'bg-[#1c1c1e] text-white' : 'text-slate-600'
            }`}
          >
            <ArrowDownToLine className="w-3.5 h-3.5" />
            Barang Masuk
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('opnames')}
            className={`px-3.5 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              activeTab === 'opnames' ? 'bg-[#1c1c1e] text-white' : 'text-slate-600'
            }`}
          >
            <ClipboardCheck className="w-3.5 h-3.5" />
            Stok Opname
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('suppliers')}
            className={`px-3.5 h-9 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              activeTab === 'suppliers' ? 'bg-[#1c1c1e] text-white' : 'text-slate-600'
            }`}
          >
            <Truck className="w-3.5 h-3.5" />
            Supplier ({suppliers.length})
          </button>
        </div>
      </div>

      {errorMsg && (
        <div className="p-4 rounded-2xl bg-rose-50 text-rose-600 text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0" />
          {errorMsg}
        </div>
      )}

      {/* TAB 1: SALDO STOK */}
      {activeTab === 'stocks' && (
        <div className="space-y-4">
          <div className="bg-white rounded-[24px] p-4 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div className="relative flex-1 w-full">
              <Search className="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Cari nama produk atau SKU..."
                className="w-full h-11 pl-11 pr-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#8b7fd0]"
              />
            </div>

            <label className="flex items-center gap-2 px-4 h-11 rounded-xl bg-[#F5F6FA] text-xs font-medium text-slate-700 cursor-pointer shrink-0">
              <input
                type="checkbox"
                checked={lowStockOnly}
                onChange={(e) => setLowStockOnly(e.target.checked)}
                className="rounded accent-[#1c1c1e]"
              />
              Hanya Stok Menipis
            </label>
          </div>

          <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] overflow-x-auto">
            {loadingStocks ? (
              <div className="flex items-center justify-center py-12 text-slate-400 text-sm gap-2">
                <Loader2 className="w-4 h-4 animate-spin" /> Memuat saldo stok...
              </div>
            ) : filteredStocks.length === 0 ? (
              <div className="text-center py-12 text-slate-400 text-sm">
                Belum ada data stok. Input <b>Barang Masuk</b> untuk mulai mencatat stok.
              </div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="py-3 px-3">Produk & Varian</th>
                    <th className="py-3 px-3">SKU</th>
                    <th className="py-3 px-3 text-right">Stok Saat Ini</th>
                    <th className="py-3 px-3 text-right">Batas Min (Reorder)</th>
                    <th className="py-3 px-3 text-center">Status</th>
                    <th className="py-3 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">
                  {filteredStocks.map((s) => {
                    const isLow = Number(s.qty) <= Number(s.reorder_point);
                    return (
                      <tr key={s.id} className="hover:bg-[#F5F6FA]/60 transition">
                        <td className="py-3.5 px-3">
                          <div className="font-semibold text-[#1c1c1e]">
                            {s.variant?.product?.name ?? '-'}
                          </div>
                          <div className="text-xs text-slate-400">{s.variant?.name ?? '-'}</div>
                        </td>
                        <td className="py-3.5 px-3 font-mono text-xs text-slate-600">
                          {s.variant?.sku ?? '-'}
                        </td>
                        <td className="py-3.5 px-3 text-right font-bold text-[#1c1c1e]">
                          {Number(s.qty)}
                        </td>
                        <td className="py-3.5 px-3 text-right text-slate-500">
                          {Number(s.reorder_point)}
                        </td>
                        <td className="py-3.5 px-3 text-center">
                          {isLow ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[11px] font-medium">
                              <AlertTriangle className="w-3 h-3" /> Menipis
                            </span>
                          ) : (
                            <span className="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-medium">
                              Aman
                            </span>
                          )}
                        </td>
                        <td className="py-3.5 px-3 text-right">
                          <button
                            type="button"
                            onClick={() => {
                              setEditingReorder(s);
                              setReorderInput(String(s.reorder_point));
                            }}
                            className="px-3 h-8 rounded-xl bg-[#F5F6FA] hover:bg-slate-200 text-xs font-medium text-[#1c1c1e] cursor-pointer"
                          >
                            Atur Batas
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: BARANG MASUK (GOODS RECEIPTS) */}
      {activeTab === 'receipts' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              type="button"
              onClick={openGrModal}
              className="h-11 px-5 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center gap-2 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Catat Barang Masuk
            </button>
          </div>

          <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] overflow-x-auto">
            {loadingReceipts ? (
              <div className="py-12 text-center text-slate-400 text-sm">Memuat riwayat...</div>
            ) : receipts.length === 0 ? (
              <div className="py-12 text-center text-slate-400 text-sm">
                Belum ada riwayat barang masuk.
              </div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="py-3 px-3">Tanggal</th>
                    <th className="py-3 px-3">Supplier & Faktur</th>
                    <th className="py-3 px-3">Item Diterima</th>
                    <th className="py-3 px-3 text-right">Total Nilai</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">
                  {receipts.map((r) => {
                    const total = r.items.reduce(
                      (sum, i) => sum + Number(i.qty) * Number(i.unit_cost),
                      0
                    );
                    return (
                      <tr key={r.id}>
                        <td className="py-3.5 px-3 text-xs text-slate-500">
                          {new Date(r.received_at).toLocaleString('id-ID')}
                        </td>
                        <td className="py-3.5 px-3">
                          <div className="font-semibold text-[#1c1c1e]">
                            {r.supplier?.name ?? 'Tanpa Supplier'}
                          </div>
                          <div className="text-xs text-slate-400">
                            {r.supplier_invoice_no || r.receipt_no || `#GR-${r.id}`}
                          </div>
                        </td>
                        <td className="py-3.5 px-3 text-xs space-y-1">
                          {r.items.map((it) => (
                            <div key={it.id}>
                              <span className="font-medium text-[#1c1c1e]">
                                {it.variant?.sku ?? '-'}
                              </span>{' '}
                              — {Number(it.qty)} x {formatRp(Number(it.unit_cost))}
                            </div>
                          ))}
                        </td>
                        <td className="py-3.5 px-3 text-right font-bold text-[#7c6fd6]">
                          {formatRp(r.total_cost ?? total)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* TAB 3: STOK OPNAME */}
      {activeTab === 'opnames' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              type="button"
              onClick={openOpnameModal}
              className="h-11 px-5 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center gap-2 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Buat Stok Opname
            </button>
          </div>

          <div className="bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] overflow-x-auto">
            {loadingOpnames ? (
              <div className="py-12 text-center text-slate-400 text-sm">Memuat riwayat...</div>
            ) : opnames.length === 0 ? (
              <div className="py-12 text-center text-slate-400 text-sm">
                Belum ada riwayat stok opname.
              </div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="py-3 px-3">Tanggal</th>
                    <th className="py-3 px-3">Petugas & Catatan</th>
                    <th className="py-3 px-3">Penyesuaian Item</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">
                  {opnames.map((op) => (
                    <tr key={op.id}>
                      <td className="py-3.5 px-3 text-xs text-slate-500">
                        {new Date(op.opname_at).toLocaleString('id-ID')}
                      </td>
                      <td className="py-3.5 px-3">
                        <div className="font-semibold text-[#1c1c1e]">
                          {op.user?.name ?? 'Admin'}
                        </div>
                        <div className="text-xs text-slate-400">{op.note || '-'}</div>
                      </td>
                      <td className="py-3.5 px-3 text-xs space-y-1">
                        {op.items.map((it) => (
                          <div key={it.id}>
                            <span className="font-medium text-[#1c1c1e]">
                              {it.variant?.sku ?? '-'}
                            </span>{' '}
                            → Fisik: <b>{Number(it.physical_qty)}</b>
                            {it.reason && <span className="text-slate-400"> ({it.reason})</span>}
                          </div>
                        ))}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* TAB 4: SUPPLIER */}
      {activeTab === 'suppliers' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
          <div className="lg:col-span-4 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] h-fit">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Tambah Supplier</h2>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                createSupplierMutation.mutate();
              }}
              className="space-y-3"
            >
              <input
                type="text"
                required
                value={supCode}
                onChange={(e) => setSupCode(e.target.value)}
                placeholder="Kode supplier (misal: SUP-001) *"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <input
                type="text"
                required
                value={supName}
                onChange={(e) => setSupName(e.target.value)}
                placeholder="Nama supplier *"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <input
                type="text"
                value={supContact}
                onChange={(e) => setSupContact(e.target.value)}
                placeholder="Nama PIC / Kontak"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <input
                type="text"
                value={supPhone}
                onChange={(e) => setSupPhone(e.target.value)}
                placeholder="No. Telepon"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <textarea
                rows={2}
                value={supAddress}
                onChange={(e) => setSupAddress(e.target.value)}
                placeholder="Alamat supplier"
                className="w-full p-3 rounded-xl bg-[#F5F6FA] text-sm focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <button
                type="submit"
                disabled={createSupplierMutation.isPending}
                className="w-full h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium cursor-pointer"
              >
                {createSupplierMutation.isPending ? 'Menyimpan...' : 'Simpan Supplier'}
              </button>
            </form>
          </div>

          <div className="lg:col-span-8 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <h2 className="text-base font-semibold text-[#1c1c1e] mb-4">Daftar Supplier</h2>
            {loadingSuppliers ? (
              <div className="py-8 text-center text-slate-400 text-sm">Memuat...</div>
            ) : suppliers.length === 0 ? (
              <div className="py-8 text-center text-slate-400 text-sm">Belum ada supplier.</div>
            ) : (
              <div className="divide-y divide-slate-100">
                {suppliers.map((sup) => (
                  <div key={sup.id} className="py-3.5 flex items-start justify-between gap-4">
                    <div>
                      <div className="font-semibold text-sm text-[#1c1c1e]">
                        {sup.name}{' '}
                        <span className="ml-1.5 px-2 py-0.5 rounded bg-[#F5F6FA] text-[11px] font-mono text-slate-500">
                          {sup.code}
                        </span>
                      </div>
                      <div className="text-xs text-slate-400 mt-0.5">
                        {sup.contact_person || '-'} · {sup.phone || '-'}
                      </div>
                      {sup.address && (
                        <div className="text-xs text-slate-400 mt-0.5">{sup.address}</div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL ATUR REORDER POINT */}
      {editingReorder && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-[28px] w-full max-w-sm p-6 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-semibold text-[#1c1c1e]">Atur Batas Minimum Stok</h3>
              <button
                type="button"
                onClick={() => setEditingReorder(null)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
            <p className="text-xs text-slate-500 mb-3">
              {editingReorder.variant?.product?.name} ({editingReorder.variant?.sku})
            </p>
            <input
              type="number"
              min={0}
              value={reorderInput}
              onChange={(e) => setReorderInput(e.target.value)}
              className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm font-semibold mb-4 focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
            />
            <button
              type="button"
              disabled={updateReorderMutation.isPending}
              onClick={() => updateReorderMutation.mutate()}
              className="w-full h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium cursor-pointer"
            >
              {updateReorderMutation.isPending ? 'Menyimpan...' : 'Simpan Batas Minimum'}
            </button>
          </div>
        </div>
      )}

      {/* MODAL BARANG MASUK */}
      {grModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-[28px] w-full max-w-2xl p-6 shadow-xl my-8">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-[#1c1c1e]">Catat Barang Masuk</h3>
              <button
                type="button"
                onClick={() => setGrModalOpen(false)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                createGrMutation.mutate();
              }}
              className="space-y-4"
            >
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-500 mb-1">Supplier</label>
                  <select
                    value={grSupplierId}
                    onChange={(e) => setGrSupplierId(e.target.value)}
                    className="w-full h-11 px-3 rounded-xl bg-[#F5F6FA] text-sm"
                  >
                    <option value="">Tanpa Supplier</option>
                    {suppliers.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-500 mb-1">
                    No. Faktur Supplier
                  </label>
                  <input
                    type="text"
                    value={grInvoiceNo}
                    onChange={(e) => setGrInvoiceNo(e.target.value)}
                    placeholder="Contoh: INV-SUP-001"
                    className="w-full h-11 px-3 rounded-xl bg-[#F5F6FA] text-sm"
                  />
                </div>
              </div>

              <div className="space-y-2.5 max-h-64 overflow-y-auto">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#1c1c1e]">Daftar Barang</span>
                  <button
                    type="button"
                    onClick={() => {
                      const first = allVariants[0];
                      if (!first) return;
                      setGrItems((prev) => [
                        ...prev,
                        {
                          variant_id: String(first.id),
                          unit_id: String(first.base_unit_id),
                          qty: '1',
                          unit_cost: String(first.cost_price),
                        },
                      ]);
                    }}
                    className="text-xs font-medium text-[#7c6fd6] hover:underline cursor-pointer"
                  >
                    + Tambah Baris
                  </button>
                </div>

                {grItems.map((row, idx) => (
                  <div
                    key={idx}
                    className="grid grid-cols-12 gap-2 items-center bg-[#F5F6FA] p-3 rounded-2xl"
                  >
                    <select
                      value={row.variant_id}
                      onChange={(e) => handleGrVariantChange(idx, e.target.value)}
                      className="col-span-6 h-10 px-2.5 rounded-xl bg-white text-xs"
                    >
                      {allVariants.map((v) => (
                        <option key={v.id} value={v.id}>
                          {v.label}
                        </option>
                      ))}
                    </select>
                    <input
                      type="number"
                      min={0.001}
                      step="any"
                      required
                      value={row.qty}
                      onChange={(e) =>
                        setGrItems((prev) =>
                          prev.map((r, i) => (i === idx ? { ...r, qty: e.target.value } : r))
                        )
                      }
                      placeholder="Qty"
                      className="col-span-2 h-10 px-2.5 rounded-xl bg-white text-xs"
                    />
                    <input
                      type="number"
                      min={0}
                      required
                      value={row.unit_cost}
                      onChange={(e) =>
                        setGrItems((prev) =>
                          prev.map((r, i) => (i === idx ? { ...r, unit_cost: e.target.value } : r))
                        )
                      }
                      placeholder="Harga Beli"
                      className="col-span-3 h-10 px-2.5 rounded-xl bg-white text-xs"
                    />
                    <button
                      type="button"
                      disabled={grItems.length <= 1}
                      onClick={() => setGrItems((prev) => prev.filter((_, i) => i !== idx))}
                      className="col-span-1 h-10 flex items-center justify-center text-rose-500 disabled:opacity-30 cursor-pointer"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>

              <input
                type="text"
                value={grNote}
                onChange={(e) => setGrNote(e.target.value)}
                placeholder="Catatan penerimaan (opsional)"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm"
              />

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setGrModalOpen(false)}
                  className="px-5 h-11 rounded-xl bg-[#F5F6FA] text-sm font-medium cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createGrMutation.isPending || grItems.length === 0}
                  className="px-6 h-11 rounded-xl bg-[#1c1c1e] text-white text-sm font-medium cursor-pointer"
                >
                  {createGrMutation.isPending ? 'Memproses...' : 'Simpan Barang Masuk'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL STOK OPNAME */}
      {opnameModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-[28px] w-full max-w-2xl p-6 shadow-xl my-8">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-[#1c1c1e]">Buat Stok Opname</h3>
              <button
                type="button"
                onClick={() => setOpnameModalOpen(false)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                createOpnameMutation.mutate();
              }}
              className="space-y-4"
            >
              <div className="space-y-2.5 max-h-64 overflow-y-auto">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#1c1c1e]">
                    Hasil Hitung Fisik Gudang
                  </span>
                  <button
                    type="button"
                    onClick={() => {
                      const first = allVariants[0];
                      if (!first) return;
                      setOpnameItems((prev) => [
                        ...prev,
                        { variant_id: String(first.id), physical_qty: '0', reason: '' },
                      ]);
                    }}
                    className="text-xs font-medium text-[#7c6fd6] hover:underline cursor-pointer"
                  >
                    + Tambah Item
                  </button>
                </div>

                {opnameItems.map((row, idx) => (
                  <div
                    key={idx}
                    className="grid grid-cols-12 gap-2 items-center bg-[#F5F6FA] p-3 rounded-2xl"
                  >
                    <select
                      value={row.variant_id}
                      onChange={(e) =>
                        setOpnameItems((prev) =>
                          prev.map((r, i) => (i === idx ? { ...r, variant_id: e.target.value } : r))
                        )
                      }
                      className="col-span-5 h-10 px-2.5 rounded-xl bg-white text-xs"
                    >
                      {allVariants.map((v) => (
                        <option key={v.id} value={v.id}>
                          {v.label}
                        </option>
                      ))}
                    </select>
                    <input
                      type="number"
                      min={0}
                      step="any"
                      required
                      value={row.physical_qty}
                      onChange={(e) =>
                        setOpnameItems((prev) =>
                          prev.map((r, i) =>
                            i === idx ? { ...r, physical_qty: e.target.value } : r
                          )
                        )
                      }
                      placeholder="Stok Fisik"
                      className="col-span-2 h-10 px-2.5 rounded-xl bg-white text-xs"
                    />
                    <input
                      type="text"
                      value={row.reason}
                      onChange={(e) =>
                        setOpnameItems((prev) =>
                          prev.map((r, i) => (i === idx ? { ...r, reason: e.target.value } : r))
                        )
                      }
                      placeholder="Alasan selisih (opsional)"
                      className="col-span-4 h-10 px-2.5 rounded-xl bg-white text-xs"
                    />
                    <button
                      type="button"
                      disabled={opnameItems.length <= 1}
                      onClick={() => setOpnameItems((prev) => prev.filter((_, i) => i !== idx))}
                      className="col-span-1 h-10 flex items-center justify-center text-rose-500 disabled:opacity-30 cursor-pointer"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>

              <input
                type="text"
                value={opnameNote}
                onChange={(e) => setOpnameNote(e.target.value)}
                placeholder="Catatan opname (misal: Opname rutin akhir bulan)"
                className="w-full h-11 px-4 rounded-xl bg-[#F5F6FA] text-sm"
              />

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setOpnameModalOpen(false)}
                  className="px-5 h-11 rounded-xl bg-[#F5F6FA] text-sm font-medium cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createOpnameMutation.isPending || opnameItems.length === 0}
                  className="px-6 h-11 rounded-xl bg-[#1c1c1e] text-white text-sm font-medium cursor-pointer"
                >
                  {createOpnameMutation.isPending ? 'Memproses...' : 'Simpan Stok Opname'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}