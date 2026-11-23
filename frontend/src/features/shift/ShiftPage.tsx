import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowDownLeft,
  ArrowUpRight,
  CheckCircle2,
  Clock,
  Lock,
  Loader2,
  Unlock,
  Wallet,
  AlertCircle,
} from 'lucide-react';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';

interface CashMovement {
  id: number;
  type: string;
  amount: number;
  reason: string | null;
  created_at: string;
}

interface ShiftData {
  id: number;
  outlet_id: number;
  register_id: number;
  opening_cash: number;
  opened_at: string;
  status: string;
}

interface CurrentShiftResponse {
  shift: ShiftData;
  expected_cash: number;
  movements: CashMovement[];
}

const formatRupiah = (amount: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount);

export function ShiftPage() {
  const queryClient = useQueryClient();
  const outletId = useAuthStore((s) => s.outletId);

  // Form Buka Shift
  const [openingCash, setOpeningCash] = useState('');

  // Form Mutasi Kas
  const [movementType, setMovementType] = useState<'cash_in' | 'cash_out'>('cash_in');
  const [movementAmount, setMovementAmount] = useState('');
  const [movementReason, setMovementReason] = useState('');

  // Form Tutup Shift
  const [countedCash, setCountedCash] = useState('');
  const [closeNote, setCloseNote] = useState('');

  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null
  );

  // Ambil shift aktif
  const { data, isLoading } = useQuery<CurrentShiftResponse | null>({
    queryKey: ['current-shift', outletId],
    queryFn: async () => {
      const res = await api.get('/shifts/current', { params: { outlet_id: outletId } });
      return res.data?.data ?? null;
    },
  });

  // Mutation: Buka Shift
  const openShiftMutation = useMutation({
    mutationFn: async () => {
      const res = await api.post('/shifts/open', {
        outlet_id: outletId,
        register_id: 1,
        opening_cash: Number(openingCash) || 0,
      });
      return res.data;
    },
    onSuccess: () => {
      setOpeningCash('');
      setFeedback({ type: 'success', message: 'Shift berhasil dibuka.' });
      queryClient.invalidateQueries({ queryKey: ['current-shift', outletId] });
    },
    onError: (err: any) => {
      setFeedback({
        type: 'error',
        message: err.response?.data?.message || 'Gagal membuka shift.',
      });
    },
  });

  // Mutation: Catat Kas Masuk / Keluar
  const movementMutation = useMutation({
    mutationFn: async () => {
      if (!data?.shift.id) return;
      const rawAmount = Math.abs(Number(movementAmount) || 0);
      const res = await api.post(`/shifts/${data.shift.id}/movements`, {
        type: movementType,
        amount: rawAmount,
        reason: movementReason,
      });
      return res.data;
    },
    onSuccess: () => {
      setMovementAmount('');
      setMovementReason('');
      setFeedback({ type: 'success', message: 'Mutasi kas berhasil dicatat.' });
      queryClient.invalidateQueries({ queryKey: ['current-shift', outletId] });
    },
    onError: (err: any) => {
      setFeedback({
        type: 'error',
        message: err.response?.data?.message || 'Gagal mencatat mutasi kas.',
      });
    },
  });

  // Mutation: Tutup Shift
  const closeShiftMutation = useMutation({
    mutationFn: async () => {
      if (!data?.shift.id) return;
      const res = await api.post(`/shifts/${data.shift.id}/close`, {
        counted_cash: Number(countedCash) || 0,
        note: closeNote || null,
      });
      return res.data;
    },
    onSuccess: () => {
      setCountedCash('');
      setCloseNote('');
      setFeedback({ type: 'success', message: 'Shift berhasil ditutup & direkonsiliasi.' });
      queryClient.invalidateQueries({ queryKey: ['current-shift', outletId] });
    },
    onError: (err: any) => {
      setFeedback({
        type: 'error',
        message: err.response?.data?.message || 'Gagal menutup shift.',
      });
    },
  });

  const inputClass =
    'w-full h-11 px-4 rounded-xl bg-[#F5F6FA] border border-transparent text-sm text-[#1c1c1e] placeholder:text-slate-400 transition focus:outline-none focus:bg-white focus:border-[#1c1c1e] focus:ring-4 focus:ring-[#8b7fd0]/20';

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64 text-slate-400 gap-2 text-sm">
        <Loader2 className="w-5 h-5 animate-spin" />
        Memuat status shift...
      </div>
    );
  }

  // Kondisi 1: Belum ada shift aktif -> Tampilkan Bento Card Buka Shift
  if (!data) {
    return (
      <div className="max-w-xl mx-auto mt-6">
        {feedback && (
          <div
            className={`mb-4 px-4 py-3 rounded-2xl text-sm flex items-center gap-2 ${
              feedback.type === 'success'
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-rose-50 text-rose-600'
            }`}
          >
            {feedback.type === 'success' ? (
              <CheckCircle2 className="w-4 h-4 shrink-0" />
            ) : (
              <AlertCircle className="w-4 h-4 shrink-0" />
            )}
            {feedback.message}
          </div>
        )}

        <div className="bg-white rounded-[28px] p-8 shadow-[0_8px_30px_rgba(20,20,40,0.04)]">
          <div className="w-12 h-12 rounded-2xl bg-[#1c1c1e] text-[#b9aef0] flex items-center justify-center mb-5">
            <Unlock className="w-5 h-5" />
          </div>
          <h2 className="text-xl font-semibold text-[#1c1c1e]">Belum Ada Shift Aktif</h2>
          <p className="text-sm text-slate-400 mt-1 mb-6">
            Masukkan nominal modal awal di laci kasir untuk memulai shift hari ini.
          </p>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              setFeedback(null);
              openShiftMutation.mutate();
            }}
            className="space-y-4"
          >
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1.5">
                Modal Awal Laci (Rp)
              </label>
              <input
                type="number"
                min={0}
                required
                value={openingCash}
                onChange={(e) => setOpeningCash(e.target.value)}
                placeholder="Contoh: 200000"
                className={inputClass}
              />
            </div>

            <button
              type="submit"
              disabled={openShiftMutation.isPending}
              className="w-full h-12 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 transition disabled:opacity-60 cursor-pointer"
            >
              {openShiftMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
              Buka Shift Sekarang
            </button>
          </form>
        </div>
      </div>
    );
  }

  // Kondisi 2: Shift Aktif -> Bento Grid 12 Kolom
  const { shift, expected_cash, movements } = data;
  const parsedCounted = countedCash === '' ? null : Number(countedCash);
  const diffCash = parsedCounted !== null ? parsedCounted - expected_cash : null;

  return (
    <div className="space-y-5 pb-6">
      {feedback && (
        <div
          className={`px-4 py-3 rounded-2xl text-sm flex items-center gap-2 ${
            feedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-700'
              : 'bg-rose-50 text-rose-600'
          }`}
        >
          {feedback.type === 'success' ? (
            <CheckCircle2 className="w-4 h-4 shrink-0" />
          ) : (
            <AlertCircle className="w-4 h-4 shrink-0" />
          )}
          {feedback.message}
        </div>
      )}

      {/* Top Summary Bento Row */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        {/* Dark Card: Status Shift */}
        <section className="bg-[#1c1c1e] text-white rounded-[24px] p-6 shadow-[0_10px_30px_rgba(20,20,40,0.12)]">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-slate-400">Status Shift</span>
            <span className="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 text-[11px] font-semibold">
              ● AKTIF
            </span>
          </div>
          <div className="mt-3 text-2xl font-semibold">Shift #{shift.id}</div>
          <div className="mt-2 flex items-center gap-1.5 text-xs text-slate-400">
            <Clock className="w-3.5 h-3.5" />
            Dibuka: {new Date(shift.opened_at).toLocaleString('id-ID')}
          </div>
        </section>

        {/* Lavender Gradient Card: Kas Diharapkan */}
        <section className="rounded-[24px] p-6 bg-gradient-to-br from-[#7c83db] via-[#9b93e0] to-[#d9c2cb] text-white shadow-[0_4px_24px_rgba(20,20,40,0.05)]">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-white/85">Ekspektasi Kas Laci</span>
            <Wallet className="w-4 h-4 text-white/80" />
          </div>
          <div className="mt-3 text-2xl sm:text-3xl font-semibold">
            {formatRupiah(expected_cash)}
          </div>
          <p className="mt-2 text-xs text-white/80">
            Modal awal {formatRupiah(shift.opening_cash)} + mutasi kas
          </p>
        </section>

        {/* Soft Peach Card: Total Mutasi */}
        <section className="rounded-[24px] p-6 bg-gradient-to-br from-[#fbf9f7] via-[#f5eae3] to-[#ead9d2] text-[#1c1c1e] shadow-[0_4px_24px_rgba(20,20,40,0.04)]">
          <span className="text-xs font-medium text-slate-500">Total Catatan Mutasi</span>
          <div className="mt-3 text-2xl sm:text-3xl font-semibold">{movements.length}</div>
          <p className="mt-2 text-xs text-slate-500">Transaksi tunai, kas masuk & kas keluar</p>
        </section>
      </div>

      {/* Main Bento Grid: Kiri (Mutasi & Riwayat) + Kanan (Tutup Shift) */}
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-5">
        {/* Kolom Kiri (7 cols) */}
        <div className="xl:col-span-7 flex flex-col gap-5">
          {/* Form Kas Masuk / Keluar */}
          <section className="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <h3 className="text-base font-semibold text-[#1c1c1e] mb-4">
              Catat Kas Masuk / Keluar
            </h3>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                setFeedback(null);
                movementMutation.mutate();
              }}
              className="space-y-4"
            >
              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setMovementType('cash_in')}
                  className={`h-11 rounded-xl text-sm font-medium flex items-center justify-center gap-2 transition cursor-pointer ${
                    movementType === 'cash_in'
                      ? 'bg-[#1c1c1e] text-white'
                      : 'bg-[#F5F6FA] text-slate-600 hover:bg-slate-200/70'
                  }`}
                >
                  <ArrowDownLeft className="w-4 h-4 text-emerald-400" />
                  Kas Masuk
                </button>
                <button
                  type="button"
                  onClick={() => setMovementType('cash_out')}
                  className={`h-11 rounded-xl text-sm font-medium flex items-center justify-center gap-2 transition cursor-pointer ${
                    movementType === 'cash_out'
                      ? 'bg-[#1c1c1e] text-white'
                      : 'bg-[#F5F6FA] text-slate-600 hover:bg-slate-200/70'
                  }`}
                >
                  <ArrowUpRight className="w-4 h-4 text-rose-400" />
                  Kas Keluar
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input
                  type="number"
                  min={1}
                  required
                  value={movementAmount}
                  onChange={(e) => setMovementAmount(e.target.value)}
                  placeholder="Nominal (Rp)"
                  className={inputClass}
                />
                <input
                  type="text"
                  required
                  value={movementReason}
                  onChange={(e) => setMovementReason(e.target.value)}
                  placeholder="Keterangan (misal: beli es batu)"
                  className={inputClass}
                />
              </div>

              <button
                type="submit"
                disabled={movementMutation.isPending}
                className="px-6 h-11 rounded-xl bg-[#8b7fd0] hover:bg-[#776ac4] text-white text-sm font-medium inline-flex items-center gap-2 transition disabled:opacity-60 cursor-pointer"
              >
                {movementMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
                Simpan Mutasi
              </button>
            </form>
          </section>

          {/* Riwayat Mutasi Kas */}
          <section className="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <h3 className="text-base font-semibold text-[#1c1c1e] mb-4">Riwayat Mutasi Kas</h3>

            {movements.length === 0 ? (
              <p className="text-sm text-slate-400 py-6 text-center">
                Belum ada mutasi kas pada shift ini.
              </p>
            ) : (
              <div className="divide-y divide-slate-100">
                {movements.map((m) => {
                  const isPositive = m.amount >= 0;
                  return (
                    <div key={m.id} className="py-3 flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <div className="text-sm font-medium text-[#1c1c1e] truncate">
                          {m.reason || m.type}
                        </div>
                        <div className="text-xs text-slate-400">
                          {m.type} • {new Date(m.created_at).toLocaleTimeString('id-ID')}
                        </div>
                      </div>
                      <span
                        className={`text-sm font-semibold shrink-0 ${
                          isPositive ? 'text-emerald-600' : 'text-rose-500'
                        }`}
                      >
                        {isPositive ? '+' : ''}
                        {formatRupiah(m.amount)}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </section>
        </div>

        {/* Kolom Kanan (5 cols): Tutup Shift & Rekonsiliasi */}
        <section className="xl:col-span-5 bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)] h-fit">
          <div className="flex items-center gap-2.5 mb-2">
            <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
              <Lock className="w-4 h-4" />
            </div>
            <h3 className="text-base font-semibold text-[#1c1c1e]">Tutup Shift & Rekonsiliasi</h3>
          </div>
          <p className="text-xs text-slate-400 mb-5">
            Hitung uang fisik di laci kasir lalu masukkan nominalnya untuk menutup shift.
          </p>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              setFeedback(null);
              closeShiftMutation.mutate();
            }}
            className="space-y-4"
          >
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1.5">
                Kas Fisik Terhitung (Rp)
              </label>
              <input
                type="number"
                min={0}
                required
                value={countedCash}
                onChange={(e) => setCountedCash(e.target.value)}
                placeholder="Masukkan total uang fisik"
                className={inputClass}
              />
            </div>

            {diffCash !== null && (
              <div
                className={`p-3.5 rounded-xl text-xs flex items-center justify-between ${
                  diffCash === 0
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-amber-50 text-amber-700'
                }`}
              >
                <span>Selisih Kas (Fisik - Sistem)</span>
                <span className="font-semibold text-sm">
                  {diffCash > 0 ? '+' : ''}
                  {formatRupiah(diffCash)}
                </span>
              </div>
            )}

            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1.5">
                Catatan Penutupan (Opsional)
              </label>
              <textarea
                rows={3}
                value={closeNote}
                onChange={(e) => setCloseNote(e.target.value)}
                placeholder="Catatan jika ada selisih kas..."
                className="w-full p-3.5 rounded-xl bg-[#F5F6FA] border border-transparent text-sm text-[#1c1c1e] placeholder:text-slate-400 transition focus:outline-none focus:bg-white focus:border-[#1c1c1e] focus:ring-4 focus:ring-[#8b7fd0]/20"
              />
            </div>

            <button
              type="submit"
              disabled={closeShiftMutation.isPending}
              className="w-full h-12 rounded-xl bg-[#1c1c1e] hover:bg-rose-600 text-white text-sm font-medium flex items-center justify-center gap-2 transition disabled:opacity-60 cursor-pointer"
            >
              {closeShiftMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
              Tutup Shift Sekarang
            </button>
          </form>
        </section>
      </div>
    </div>
  );
}