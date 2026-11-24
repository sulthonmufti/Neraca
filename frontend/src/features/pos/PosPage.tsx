import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertCircle,
  Clock,
  CreditCard,
  Loader2,
  Minus,
  PauseCircle,
  Plus,
  Search,
  ShoppingBag,
  Trash2,
  UserCheck,
  X,
} from 'lucide-react';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore, type CatalogItem } from '../../stores/cartStore';
import { ReceiptModal, type ReceiptData } from './ReceiptModal';

interface Category {
  id: number;
  name: string;
}

interface PaymentMethod {
  id: number;
  code: string;
  name: string;
  type: string;
}

interface HoldSale {
  id: number;
  receipt_no: string | null;
  grand_total: number;
  created_at: string;
  member?: {
    id: number;
    name: string;
    member_no: string;
    member_type?: { discount_percent: number };
  } | null;
  items: {
    id: number;
    variant_id: number;
    unit_id: number;
    name_snapshot: string;
    sku_snapshot: string;
    qty: number;
    unit_price: number;
  }[];
}

const formatRp = (n: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n);

export function PosPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const outletId = useAuthStore((s) => s.outletId);

  const {
    activeHoldId,
    items,
    member,
    addItem,
    updateQty,
    removeItem,
    setMember,
    loadHoldSale,
    clearCart,
  } = useCartStore();

  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<number | 'all'>('all');
  const [memberCode, setMemberCode] = useState('');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Modal Pembayaran (untuk transaksi baru atau bayar hold bill)
  const [payModalOpen, setPayModalOpen] = useState(false);
  const [payingHoldSale, setPayingHoldSale] = useState<HoldSale | null>(null);
  const [selectedMethodId, setSelectedMethodId] = useState<number | null>(null);
  const [paidAmount, setPaidAmount] = useState('');

  // Modal Daftar Hold Bill & Struk
  const [holdModalOpen, setHoldModalOpen] = useState(false);
  const [receipt, setReceipt] = useState<ReceiptData | null>(null);

  // 1. Cek Shift Aktif
  const { data: currentShift, isLoading: loadingShift } = useQuery({
    queryKey: ['current-shift', outletId],
    queryFn: async () => {
      const res = await api.get('/shifts/current', { params: { outlet_id: outletId } });
      return res.data?.data ?? null;
    },
  });

  // 2. Ambil Katalog Produk
  const { data: catalogData, isLoading: loadingCatalog } = useQuery<{
    categories: Category[];
    items: CatalogItem[];
  }>({
    queryKey: ['pos-catalog', outletId],
    queryFn: async () => {
      const res = await api.get('/pos/catalog', { params: { outlet_id: outletId } });
      return res.data?.data ?? { categories: [], items: [] };
    },
    enabled: !!currentShift,
  });

  // 3. Ambil Metode Pembayaran
  const { data: paymentMethods = [] } = useQuery<PaymentMethod[]>({
    queryKey: ['payment-methods'],
    queryFn: async () => {
      const res = await api.get('/payment-methods');
      return res.data?.data ?? [];
    },
    enabled: !!currentShift,
  });

  // 4. Ambil Daftar Hold Bill
  const { data: holdSales = [] } = useQuery<HoldSale[]>({
    queryKey: ['hold-sales', outletId],
    queryFn: async () => {
      const res = await api.get('/sales/hold', { params: { outlet_id: outletId } });
      return res.data?.data ?? [];
    },
    enabled: !!currentShift,
  });

  // Filter produk
  const filteredProducts = useMemo(() => {
    const list = catalogData?.items ?? [];
    const q = search.trim().toLowerCase();
    return list.filter((p) => {
      const matchCat = selectedCategory === 'all' || p.category_id === selectedCategory;
      const matchSearch =
        !q ||
        p.name.toLowerCase().includes(q) ||
        p.sku.toLowerCase().includes(q) ||
        p.barcodes.some((b) => b.toLowerCase().includes(q));
      return matchCat && matchSearch;
    });
  }, [catalogData, search, selectedCategory]);

  // Scan barcode (tekan Enter di kolom search)
  const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== 'Enter') return;
    const q = search.trim().toLowerCase();
    if (!q) return;

    const exact = (catalogData?.items ?? []).find(
      (p) => p.sku.toLowerCase() === q || p.barcodes.some((b) => b.toLowerCase() === q)
    );
    if (exact) {
      addItem(exact);
      setSearch('');
    }
  };

  // Lookup Member
  const handleLookupMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!memberCode.trim()) return;
    setErrorMsg(null);
    try {
      const res = await api.get('/members/lookup', { params: { code: memberCode.trim() } });
      const m = res.data?.data;
      if (m) {
        setMember({
          id: m.id,
          name: m.name,
          member_no: m.member_no,
          discount_percent: Number(m.member_type?.discount_percent ?? 0),
        });
        setMemberCode('');
      }
    } catch {
      setErrorMsg('Kode member tidak ditemukan.');
    }
  };

  // Hitung Total Keranjang
  const subtotal = items.reduce((sum, i) => sum + i.unit_price * i.qty, 0);
  const discountPercent = member?.discount_percent ?? 0;
  const discountTotal = Math.round((subtotal * discountPercent) / 100);
  const grandTotal = Math.max(0, subtotal - discountTotal);

  const activeTotalToPay = payingHoldSale ? payingHoldSale.grand_total : grandTotal;
  const numericPaid = Number(paidAmount) || 0;
  const changeTotal = Math.max(0, numericPaid - activeTotalToPay);

  // Mutation: Simpan Transaksi (Baru atau Update Hold Bill)
  const checkoutMutation = useMutation({
    mutationFn: async (mode: 'completed' | 'hold') => {
      const methodId = selectedMethodId ?? paymentMethods[0]?.id;
      const mappedItems = items.map((i) => ({
        variant_id: i.variant_id,
        unit_id: i.unit_id,
        name_snapshot: i.name_snapshot,
        sku_snapshot: i.sku_snapshot,
        qty: i.qty,
        unit_price: i.unit_price,
      }));

      // Jika sedang mengedit Hold Bill yang sudah ada
      if (activeHoldId) {
        const updateRes = await api.put(`/sales/${activeHoldId}/hold`, {
          member_id: member?.id ?? null,
          items: mappedItems,
        });

        if (mode === 'hold') {
          return { res: updateRes.data, mode, methodId };
        }

        const payRes = await api.post(`/sales/${activeHoldId}/pay`, {
          payments: [
            {
              payment_method_id: methodId,
              amount: numericPaid || grandTotal,
            },
          ],
        });
        return { res: payRes.data, mode, methodId };
      }

      // Transaksi baru
      const payload: Record<string, unknown> = {
        outlet_id: outletId,
        shift_id: currentShift?.shift?.id,
        member_id: member?.id ?? null,
        uuid: crypto.randomUUID(),
        status: mode,
        items: mappedItems,
      };

      if (mode === 'completed') {
        payload.payments = [
          {
            payment_method_id: methodId,
            amount: numericPaid || grandTotal,
          },
        ];
      }

      const res = await api.post('/sales', payload);
      return { res: res.data, mode, methodId };
    },
    onSuccess: ({ res, mode, methodId }) => {
      const sale = res?.data;
      if (mode === 'completed') {
        const methodName = paymentMethods.find((m) => m.id === methodId)?.name ?? 'Tunai';
        setReceipt({
          receipt_no: sale?.receipt_no,
          sold_at: sale?.sold_at,
          items: items.map((i) => ({
            name_snapshot: i.name_snapshot,
            qty: i.qty,
            unit_price: i.unit_price,
          })),
          subtotal,
          discount_total: discountTotal,
          grand_total: sale?.grand_total ?? grandTotal,
          paid_total: numericPaid || grandTotal,
          change_total: Math.max(0, (numericPaid || grandTotal) - (sale?.grand_total ?? grandTotal)),
          payment_method_name: methodName,
        });
      }
      clearCart();
      setPayModalOpen(false);
      setPaidAmount('');
      setErrorMsg(null);
      queryClient.invalidateQueries({ queryKey: ['pos-catalog', outletId] });
      queryClient.invalidateQueries({ queryKey: ['hold-sales', outletId] });
      queryClient.invalidateQueries({ queryKey: ['current-shift', outletId] });
    },
    onError: (err: any) => {
      setErrorMsg(
        err.response?.data?.error ||
          err.response?.data?.message ||
          'Gagal memproses transaksi.'
      );
    },
  });

  // Mutation: Bayar Hold Bill langsung dari modal Hold
  const payHoldMutation = useMutation({
    mutationFn: async () => {
      if (!payingHoldSale) return;
      const methodId = selectedMethodId ?? paymentMethods[0]?.id;
      const res = await api.post(`/sales/${payingHoldSale.id}/pay`, {
        payments: [
          {
            payment_method_id: methodId,
            amount: numericPaid || payingHoldSale.grand_total,
          },
        ],
      });
      return { res: res.data, methodId };
    },
    onSuccess: (result) => {
      if (!result || !payingHoldSale) return;
      const methodName = paymentMethods.find((m) => m.id === result.methodId)?.name ?? 'Tunai';
      setReceipt({
        receipt_no: result.res?.data?.receipt_no ?? payingHoldSale.receipt_no ?? undefined,
        items: payingHoldSale.items,
        subtotal: payingHoldSale.grand_total,
        discount_total: 0,
        grand_total: payingHoldSale.grand_total,
        paid_total: numericPaid || payingHoldSale.grand_total,
        change_total: Math.max(
          0,
          (numericPaid || payingHoldSale.grand_total) - payingHoldSale.grand_total
        ),
        payment_method_name: methodName,
      });
      setPayingHoldSale(null);
      setPayModalOpen(false);
      setPaidAmount('');
      queryClient.invalidateQueries({ queryKey: ['hold-sales', outletId] });
      queryClient.invalidateQueries({ queryKey: ['pos-catalog', outletId] });
      queryClient.invalidateQueries({ queryKey: ['current-shift', outletId] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal membayar hold bill.');
    },
  });

  // Mutation: Batalkan Hold Bill
  const cancelHoldMutation = useMutation({
    mutationFn: async (holdId: number) => {
      await api.post(`/sales/${holdId}/cancel-hold`, {
        void_reason: 'Dibatalkan pelanggan',
      });
    },
    onSuccess: (_, holdId) => {
      if (activeHoldId === holdId) {
        clearCart();
      }
      queryClient.invalidateQueries({ queryKey: ['hold-sales', outletId] });
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || 'Gagal membatalkan hold bill.');
    },
  });

  // Muat ulang Hold Bill ke keranjang untuk diedit
  const handleResumeHold = (h: HoldSale) => {
    const restoredItems = h.items.map((it) => ({
      variant_id: it.variant_id,
      unit_id: it.unit_id,
      name_snapshot: it.name_snapshot,
      sku_snapshot: it.sku_snapshot,
      unit_code: 'pcs',
      unit_price: Number(it.unit_price),
      qty: Number(it.qty),
    }));
    const restoredMember = h.member
      ? {
          id: h.member.id,
          name: h.member.name,
          member_no: h.member.member_no,
          discount_percent: Number(h.member.member_type?.discount_percent ?? 0),
        }
      : null;

    loadHoldSale(h.id, restoredItems, restoredMember);
    setHoldModalOpen(false);
  };

  const openPaymentModal = (holdSale: HoldSale | null = null) => {
    setErrorMsg(null);
    setPayingHoldSale(holdSale);
    const defaultMethod = paymentMethods[0]?.id ?? null;
    setSelectedMethodId(defaultMethod);
    setPaidAmount(String(holdSale ? holdSale.grand_total : grandTotal));
    setPayModalOpen(true);
  };

  if (loadingShift) {
    return (
      <div className="flex items-center justify-center h-64 text-slate-400 gap-2 text-sm">
        <Loader2 className="w-5 h-5 animate-spin" />
        Memeriksa status shift...
      </div>
    );
  }

  // Jika belum buka shift
  if (!currentShift) {
    return (
      <div className="max-w-lg mx-auto mt-10 bg-white rounded-[28px] p-8 text-center shadow-[0_8px_30px_rgba(20,20,40,0.04)]">
        <div className="w-14 h-14 mx-auto rounded-2xl bg-[#1c1c1e] text-[#b9aef0] flex items-center justify-center mb-4">
          <Clock className="w-6 h-6" />
        </div>
        <h2 className="text-xl font-semibold text-[#1c1c1e]">Shift Belum Dibuka</h2>
        <p className="text-sm text-slate-400 mt-1 mb-6">
          Buka shift terlebih dahulu di halaman Shift & Kas sebelum melakukan transaksi penjualan.
        </p>
        <button
          type="button"
          onClick={() => navigate('/shift')}
          className="px-6 h-12 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium cursor-pointer"
        >
          Buka Halaman Shift & Kas
        </button>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 pb-6">
      {/* KIRI: Katalog Produk (8 cols) */}
      <div className="xl:col-span-8 flex flex-col gap-4">
        {/* Search & Hold Bill Bar */}
        <div className="bg-white rounded-[24px] p-4 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={handleSearchKeyDown}
              placeholder="Cari produk atau scan barcode lalu Enter..."
              className="w-full h-11 pl-11 pr-4 rounded-xl bg-[#F5F6FA] text-sm text-[#1c1c1e] placeholder:text-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#8b7fd0]"
            />
          </div>
          <button
            type="button"
            onClick={() => setHoldModalOpen(true)}
            className="h-11 px-4 rounded-xl bg-[#F5F6FA] hover:bg-slate-200/70 text-[#1c1c1e] text-sm font-medium flex items-center justify-center gap-2 shrink-0 cursor-pointer"
          >
            <PauseCircle className="w-4 h-4 text-[#8b7fd0]" />
            Hold Bill
            {holdSales.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-[#1c1c1e] text-white text-xs">
                {holdSales.length}
              </span>
            )}
          </button>
        </div>

        {/* Filter Kategori */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1">
          <button
            type="button"
            onClick={() => setSelectedCategory('all')}
            className={`px-4 py-2 rounded-full text-xs font-medium shrink-0 transition cursor-pointer ${
              selectedCategory === 'all'
                ? 'bg-[#1c1c1e] text-white'
                : 'bg-white text-slate-600 hover:bg-slate-100'
            }`}
          >
            Semua Produk
          </button>
          {(catalogData?.categories ?? []).map((cat) => (
            <button
              key={cat.id}
              type="button"
              onClick={() => setSelectedCategory(cat.id)}
              className={`px-4 py-2 rounded-full text-xs font-medium shrink-0 transition cursor-pointer ${
                selectedCategory === cat.id
                  ? 'bg-[#1c1c1e] text-white'
                  : 'bg-white text-slate-600 hover:bg-slate-100'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </div>

        {/* Grid Produk */}
        {loadingCatalog ? (
          <div className="flex items-center justify-center h-48 text-slate-400 text-sm gap-2">
            <Loader2 className="w-4 h-4 animate-spin" /> Memuat katalog...
          </div>
        ) : filteredProducts.length === 0 ? (
          <div className="bg-white rounded-[24px] p-12 text-center text-slate-400 text-sm">
            Produk tidak ditemukan.
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
            {filteredProducts.map((product) => {
              const outOfStock = product.track_stock && product.stock_qty <= 0;
              return (
                <button
                  key={product.variant_id}
                  type="button"
                  disabled={outOfStock}
                  onClick={() => addItem(product)}
                  className="text-left bg-white rounded-[22px] p-4 shadow-[0_4px_20px_rgba(20,20,40,0.03)] hover:shadow-md hover:-translate-y-0.5 transition disabled:opacity-50 cursor-pointer flex flex-col justify-between min-h-[132px]"
                >
                  <div>
                    <div className="flex items-start justify-between gap-2">
                      <span className="text-[11px] font-medium text-slate-400">
                        {product.sku}
                      </span>
                      {product.track_stock && (
                        <span
                          className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                            outOfStock
                              ? 'bg-rose-50 text-rose-600'
                              : 'bg-emerald-50 text-emerald-700'
                          }`}
                        >
                          Stok: {product.stock_qty}
                        </span>
                      )}
                    </div>
                    <h3 className="text-sm font-semibold text-[#1c1c1e] mt-1.5 line-clamp-2">
                      {product.name}
                    </h3>
                  </div>

                  <div className="mt-4 flex items-center justify-between">
                    <span className="text-sm font-bold text-[#7c6fd6]">
                      {formatRp(product.sell_price)}
                    </span>
                    <span className="w-7 h-7 rounded-xl bg-[#F5F6FA] text-[#1c1c1e] flex items-center justify-center">
                      <Plus className="w-4 h-4" />
                    </span>
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* KANAN: Keranjang & Checkout (4 cols) */}
      <aside className="xl:col-span-4 bg-white rounded-[24px] p-5 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col h-fit xl:sticky xl:top-4">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <ShoppingBag className="w-5 h-5 text-[#1c1c1e]" />
            <h2 className="text-base font-semibold text-[#1c1c1e]">Keranjang</h2>
          </div>
          {items.length > 0 && (
            <button
              type="button"
              onClick={clearCart}
              className="text-xs text-rose-500 hover:underline cursor-pointer"
            >
              Kosongkan
            </button>
          )}
        </div>

        {/* Badge Edit Hold Bill */}
        {activeHoldId && (
          <div className="mt-3 px-3 py-2 rounded-xl bg-[#ede9fe] text-[#5b4bc4] text-xs font-medium flex items-center justify-between">
            <span>Mengedit Hold #{activeHoldId}</span>
            <button
              type="button"
              onClick={clearCart}
              className="underline hover:text-[#1c1c1e] cursor-pointer"
            >
              Batal Edit
            </button>
          </div>
        )}

        {/* Input Member */}
        <div className="py-3 border-b border-slate-100">
          {member ? (
            <div className="flex items-center justify-between bg-[#F5F6FA] px-3 py-2 rounded-xl text-xs">
              <div className="flex items-center gap-2 text-[#1c1c1e] font-medium">
                <UserCheck className="w-4 h-4 text-emerald-600" />
                <span>
                  {member.name}
                  {discountPercent > 0 && ` (Diskon ${discountPercent}%)`}
                </span>
              </div>
              <button
                type="button"
                onClick={() => setMember(null)}
                className="text-slate-400 hover:text-rose-500 cursor-pointer"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            </div>
          ) : (
            <form onSubmit={handleLookupMember} className="flex gap-2">
              <input
                type="text"
                value={memberCode}
                onChange={(e) => setMemberCode(e.target.value)}
                placeholder="Kode / kartu member (opsional)"
                className="flex-1 h-9 px-3 rounded-xl bg-[#F5F6FA] text-xs focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
              />
              <button
                type="submit"
                className="px-3 h-9 rounded-xl bg-[#1c1c1e] text-white text-xs font-medium cursor-pointer"
              >
                Cek
              </button>
            </form>
          )}
        </div>

        {errorMsg && (
          <div className="mt-3 p-3 rounded-xl bg-rose-50 text-rose-600 text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            {errorMsg}
          </div>
        )}

        {/* Daftar Item Keranjang */}
        <div className="py-4 space-y-3 max-h-[340px] overflow-y-auto">
          {items.length === 0 ? (
            <p className="text-center text-xs text-slate-400 py-10">
              Belum ada produk dipilih. Klik produk di sebelah kiri.
            </p>
          ) : (
            items.map((item) => (
              <div key={item.variant_id} className="flex items-center justify-between gap-2">
                <div className="min-w-0 flex-1">
                  <div className="text-sm font-medium text-[#1c1c1e] truncate">
                    {item.name_snapshot}
                  </div>
                  <div className="text-xs text-slate-400">
                    {formatRp(item.unit_price)} / {item.unit_code}
                  </div>
                </div>

                <div className="flex items-center gap-1.5">
                  <button
                    type="button"
                    onClick={() => updateQty(item.variant_id, -1)}
                    className="w-7 h-7 rounded-lg bg-[#F5F6FA] flex items-center justify-center text-slate-600 hover:bg-slate-200 cursor-pointer"
                  >
                    <Minus className="w-3.5 h-3.5" />
                  </button>
                  <span className="w-6 text-center text-xs font-semibold">{item.qty}</span>
                  <button
                    type="button"
                    onClick={() => updateQty(item.variant_id, 1)}
                    className="w-7 h-7 rounded-lg bg-[#F5F6FA] flex items-center justify-center text-slate-600 hover:bg-slate-200 cursor-pointer"
                  >
                    <Plus className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeItem(item.variant_id)}
                    className="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-500 flex items-center justify-center cursor-pointer"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Ringkasan & Tombol Aksi */}
        <div className="pt-4 border-t border-slate-100 space-y-2 text-sm">
          <div className="flex justify-between text-slate-500">
            <span>Subtotal</span>
            <span>{formatRp(subtotal)}</span>
          </div>
          {discountTotal > 0 && (
            <div className="flex justify-between text-emerald-600">
              <span>Diskon Member</span>
              <span>-{formatRp(discountTotal)}</span>
            </div>
          )}
          <div className="flex justify-between text-base font-bold text-[#1c1c1e] pt-1">
            <span>Total</span>
            <span>{formatRp(grandTotal)}</span>
          </div>

          <div className="grid grid-cols-3 gap-2 pt-3">
            <button
              type="button"
              disabled={items.length === 0 || checkoutMutation.isPending}
              onClick={() => checkoutMutation.mutate('hold')}
              className="h-12 rounded-xl bg-[#F5F6FA] hover:bg-slate-200 text-[#1c1c1e] text-xs font-semibold disabled:opacity-50 cursor-pointer"
            >
              {activeHoldId ? 'Simpan Hold' : 'Hold'}
            </button>
            <button
              type="button"
              disabled={items.length === 0}
              onClick={() => openPaymentModal(null)}
              className="col-span-2 h-12 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
            >
              <CreditCard className="w-4 h-4" />
              Bayar
            </button>
          </div>
        </div>
      </aside>

      {/* MODAL PEMBAYARAN */}
      {payModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-[28px] w-full max-w-md p-6 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-[#1c1c1e]">
                {payingHoldSale ? `Bayar Hold #${payingHoldSale.id}` : 'Pembayaran'}
              </h3>
              <button
                type="button"
                onClick={() => setPayModalOpen(false)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="bg-[#1c1c1e] text-white rounded-2xl p-4 mb-4">
              <div className="text-xs text-slate-400">Total Tagihan</div>
              <div className="text-2xl font-bold mt-1">{formatRp(activeTotalToPay)}</div>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-500 mb-2">
                  Metode Pembayaran
                </label>
                <div className="grid grid-cols-2 gap-2">
                  {paymentMethods.map((m) => (
                    <button
                      key={m.id}
                      type="button"
                      onClick={() => setSelectedMethodId(m.id)}
                      className={`h-10 px-3 rounded-xl text-xs font-medium transition cursor-pointer ${
                        selectedMethodId === m.id
                          ? 'bg-[#8b7fd0] text-white'
                          : 'bg-[#F5F6FA] text-slate-700 hover:bg-slate-200'
                      }`}
                    >
                      {m.name}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-500 mb-1.5">
                  Nominal Diterima (Rp)
                </label>
                <input
                  type="number"
                  min={activeTotalToPay}
                  value={paidAmount}
                  onChange={(e) => setPaidAmount(e.target.value)}
                  className="w-full h-12 px-4 rounded-xl bg-[#F5F6FA] text-base font-semibold text-[#1c1c1e] focus:outline-none focus:ring-2 focus:ring-[#8b7fd0]"
                />
              </div>

              <div className="flex justify-between items-center bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl text-sm">
                <span>Kembalian</span>
                <span className="font-bold">{formatRp(changeTotal)}</span>
              </div>

              <button
                type="button"
                disabled={
                  numericPaid < activeTotalToPay ||
                  checkoutMutation.isPending ||
                  payHoldMutation.isPending
                }
                onClick={() =>
                  payingHoldSale ? payHoldMutation.mutate() : checkoutMutation.mutate('completed')
                }
                className="w-full h-12 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
              >
                {(checkoutMutation.isPending || payHoldMutation.isPending) && (
                  <Loader2 className="w-4 h-4 animate-spin" />
                )}
                Konfirmasi & Cetak Struk
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL DAFTAR HOLD BILL */}
      {holdModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-[28px] w-full max-w-md p-6 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-[#1c1c1e]">Daftar Hold Bill</h3>
              <button
                type="button"
                onClick={() => setHoldModalOpen(false)}
                className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {holdSales.length === 0 ? (
              <p className="text-sm text-slate-400 text-center py-8">
                Tidak ada transaksi yang ditahan.
              </p>
            ) : (
              <div className="space-y-3 max-h-80 overflow-y-auto">
                {holdSales.map((h) => (
                  <div
                    key={h.id}
                    className="p-4 rounded-2xl bg-[#F5F6FA] flex items-center justify-between gap-3"
                  >
                    <div>
                      <div className="text-sm font-semibold text-[#1c1c1e]">
                        Hold #{h.id} ({h.items.length} item)
                      </div>
                      <div className="text-xs text-slate-500 mt-0.5">
                        Total: {formatRp(h.grand_total)}
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <button
                        type="button"
                        onClick={() => handleResumeHold(h)}
                        className="px-3 h-9 rounded-xl bg-white hover:bg-slate-200 text-[#1c1c1e] text-xs font-medium cursor-pointer"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          setHoldModalOpen(false);
                          openPaymentModal(h);
                        }}
                        className="px-3 h-9 rounded-xl bg-[#1c1c1e] text-white text-xs font-medium cursor-pointer"
                      >
                        Bayar
                      </button>
                      <button
                        type="button"
                        disabled={cancelHoldMutation.isPending}
                        onClick={() => {
                          if (window.confirm(`Batalkan Hold #${h.id}?`)) {
                            cancelHoldMutation.mutate(h.id);
                          }
                        }}
                        className="w-9 h-9 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center cursor-pointer"
                        title="Batalkan Hold Bill"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL STRUK */}
      {receipt && <ReceiptModal receipt={receipt} onClose={() => setReceipt(null)} />}
    </div>
  );
}