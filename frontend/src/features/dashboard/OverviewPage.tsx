import { ArrowUpRight, Download, MoreVertical, TrendingUp } from 'lucide-react';

const monthlyBars = [
  { month: 'Jan', solid: 52, striped: 26 },
  { month: 'Feb', solid: 40, striped: 28 },
  { month: 'Mar', solid: 24, striped: 24 },
  { month: 'Apr', solid: 24, striped: 24 },
  { month: 'May', solid: 40, striped: 28 },
  { month: 'Jun', solid: 52, striped: 24 },
  { month: 'Jul', solid: 48, striped: 22, active: true },
  { month: 'Aug', solid: 18, striped: 36 },
  { month: 'Sep', solid: 10, striped: 52 },
  { month: 'Oct', solid: 24, striped: 22 },
  { month: 'Nov', solid: 42, striped: 26 },
  { month: 'Dec', solid: 26, striped: 24 },
];

const lastActivities = [
  { name: 'Kasir Utama', status: 'Paid', code: '#INV-00012', amount: 'Rp 145.000', initials: 'KU' },
  { name: 'Member VIP', status: 'Paid', code: '#INV-00011', amount: 'Rp 82.000', initials: 'MV' },
  { name: 'Retur Barang', status: 'Refund', code: '#RET-00003', amount: 'Rp 25.000', initials: 'RB' },
  { name: 'Kasir Utama', status: 'Paid', code: '#INV-00010', amount: 'Rp 210.000', initials: 'KU' },
];

const topProducts = [
  { name: 'Es Kopi Susu Gula Aren', stock: 118, price: 'Rp 22.000', discount: '10%', sold: 294 },
  { name: 'Americano Iced', stock: 84, price: 'Rp 18.000', discount: '0%', sold: 142 },
  { name: 'Croissant Butter', stock: 36, price: 'Rp 25.000', discount: '5%', sold: 98 },
];

export function OverviewPage() {
  return (
    <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 pb-6">
      {/* Left Column (8 cols) */}
      <div className="xl:col-span-8 flex flex-col gap-5">
        {/* Sales Analytics Card */}
        <section className="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className="text-base font-semibold text-[#1c1c1e]">Sales Analytics</h2>
              <div className="flex items-center gap-4 mt-1 text-xs text-slate-400">
                <span className="flex items-center gap-1.5">
                  <span className="w-2 h-2 rounded-full bg-[#8b7fd0]" />
                  Penjualan
                </span>
                <span className="flex items-center gap-1.5">
                  <span className="w-2 h-2 rounded-full bg-[#dcd6f7]" />
                  Target
                </span>
              </div>
            </div>
            <button
              type="button"
              className="px-4 py-2 rounded-full border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 cursor-pointer"
            >
              Tahun ini
            </button>
          </div>

          <div className="h-48 flex items-end justify-between gap-2 sm:gap-3 pt-4">
            {monthlyBars.map((bar) => (
              <div key={bar.month} className="flex-1 flex flex-col items-center gap-2">
                <div className="w-full max-w-[28px] h-36 flex flex-col justify-end gap-1">
                  <div
                    style={{ height: `${bar.striped}%` }}
                    className="w-full rounded-t-lg rounded-b-sm bg-[#ede9fe]"
                  />
                  <div
                    style={{ height: `${bar.solid}%` }}
                    className={`w-full rounded-lg transition-all ${
                      bar.active ? 'bg-[#7c6fd6]' : 'bg-[#d4cdf7]'
                    }`}
                  />
                </div>
                <span className="text-[11px] text-slate-400">{bar.month}</span>
              </div>
            ))}
          </div>
        </section>

        {/* Middle Row: Donut + Last Activity */}
        <div className="grid grid-cols-1 md:grid-cols-12 gap-5">
          {/* Payment / Category Share */}
          <section className="md:col-span-5 bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)] flex flex-col justify-between">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-base font-semibold text-[#1c1c1e]">Metode Bayar</h2>
              <MoreVertical className="w-4 h-4 text-slate-400" />
            </div>

            <div className="flex items-center justify-center my-2">
              <div className="w-36 h-36 rounded-full border-[14px] border-[#1c1c1e] border-t-[#8b7fd0] border-r-[#b9aef0] flex flex-col items-center justify-center">
                <span className="text-xl font-bold text-[#1c1c1e]">62%</span>
                <span className="text-[11px] text-slate-400">Tunai</span>
              </div>
            </div>

            <div className="flex items-center justify-center gap-4 text-xs text-slate-500 mt-3">
              <span className="flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full bg-[#1c1c1e]" /> Tunai
              </span>
              <span className="flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full bg-[#8b7fd0]" /> QRIS
              </span>
              <span className="flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full bg-[#b9aef0]" /> Transfer
              </span>
            </div>
          </section>

          {/* Last Activity */}
          <section className="md:col-span-7 bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-base font-semibold text-[#1c1c1e]">Aktivitas Terakhir</h2>
              <ArrowUpRight className="w-4 h-4 text-slate-400" />
            </div>

            <div className="space-y-3.5">
              {lastActivities.map((act, idx) => (
                <div key={idx} className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="w-9 h-9 rounded-full bg-[#F5F6FA] text-xs font-semibold text-[#1c1c1e] flex items-center justify-center shrink-0">
                      {act.initials}
                    </div>
                    <div className="min-w-0">
                      <div className="text-sm font-medium text-[#1c1c1e] truncate">{act.name}</div>
                      <div
                        className={`text-xs ${
                          act.status === 'Refund' ? 'text-rose-500' : 'text-emerald-600'
                        }`}
                      >
                        {act.status}
                      </div>
                    </div>
                  </div>
                  <span className="text-xs text-slate-400 hidden sm:inline">{act.code}</span>
                  <span className="text-sm font-semibold text-[#1c1c1e]">{act.amount}</span>
                </div>
              ))}
            </div>
          </section>
        </div>

        {/* Product Sales Table */}
        <section className="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-[#1c1c1e]">Penjualan Produk</h2>
            <ArrowUpRight className="w-4 h-4 text-slate-400" />
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="text-xs text-slate-400 border-b border-slate-100">
                  <th className="pb-3 font-medium">Produk</th>
                  <th className="pb-3 font-medium">Stok</th>
                  <th className="pb-3 font-medium">Harga</th>
                  <th className="pb-3 font-medium">Diskon</th>
                  <th className="pb-3 font-medium text-right">Terjual</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {topProducts.map((p) => (
                  <tr key={p.name}>
                    <td className="py-3.5 font-medium text-[#1c1c1e]">{p.name}</td>
                    <td className="py-3.5 text-slate-600">{p.stock}</td>
                    <td className="py-3.5 text-slate-600">{p.price}</td>
                    <td className="py-3.5 text-emerald-600 font-medium">{p.discount}</td>
                    <td className="py-3.5 text-right font-semibold text-[#1c1c1e]">{p.sold}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>

      {/* Right Column (4 cols) */}
      <div className="xl:col-span-4 flex flex-col gap-5">
        {/* Dark Card: Total Customers / Members */}
        <section className="bg-[#1c1c1e] text-white rounded-[24px] p-6 shadow-[0_10px_30px_rgba(20,20,40,0.12)]">
          <div className="flex items-center justify-between">
            <span className="text-sm text-slate-300">Total Member</span>
            <MoreVertical className="w-4 h-4 text-slate-500" />
          </div>
          <div className="flex items-baseline gap-2.5 mt-3">
            <span className="text-3xl font-semibold">1.226</span>
            <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-400">
              <TrendingUp className="w-3.5 h-3.5" /> 19%
            </span>
          </div>
          <p className="text-xs text-slate-400 mt-1">683 transaksi bulan lalu</p>

          <div className="mt-5 flex items-center gap-2">
            <div className="w-[40%] h-9 rounded-xl bg-white/15 flex items-center px-3 text-xs font-medium">
              40%
            </div>
            <div className="w-[60%] h-9 rounded-xl bg-white text-[#1c1c1e] flex items-center justify-end px-3 text-xs font-semibold">
              60%
            </div>
          </div>
          <div className="flex items-center justify-between text-[11px] text-slate-400 mt-2.5">
            <span>• Member</span>
            <span>• Umum</span>
          </div>
        </section>

        {/* Lavender Gradient Card: Total Revenue */}
        <section className="rounded-[24px] p-6 bg-gradient-to-br from-[#7c83db] via-[#9b93e0] to-[#d9c2cb] text-white shadow-[0_4px_24px_rgba(20,20,40,0.05)]">
          <div className="flex items-center justify-between">
            <span className="text-sm text-white/90">Total Omzet</span>
            <MoreVertical className="w-4 h-4 text-white/70" />
          </div>
          <div className="flex items-baseline gap-2 mt-3">
            <span className="text-3xl font-semibold">Rp 12,4 jt</span>
            <span className="text-xs font-medium text-white/90">+10%</span>
          </div>
          <p className="text-xs text-white/75 mt-1">Rp 11,2 jt bulan lalu</p>

          <div className="mt-6 h-16 flex items-end gap-2">
            {[65, 50, 35, 35, 60, 45, 30].map((h, i) => (
              <div
                key={i}
                style={{ height: `${h}%` }}
                className={`flex-1 rounded-md ${i === 4 ? 'bg-[#1c1c1e]' : 'bg-white/85'}`}
              />
            ))}
          </div>
        </section>

        {/* Soft Peach Gradient Card: Total Orders */}
        <section className="rounded-[24px] p-6 bg-gradient-to-br from-[#fbf9f7] via-[#f5eae3] to-[#ead9d2] text-[#1c1c1e] shadow-[0_4px_24px_rgba(20,20,40,0.04)]">
          <div className="flex items-center justify-between">
            <span className="text-sm text-slate-600">Total Transaksi</span>
            <MoreVertical className="w-4 h-4 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2 mt-3">
            <span className="text-3xl font-semibold">482</span>
            <span className="text-xs font-medium text-emerald-600">+21%</span>
          </div>
          <p className="text-xs text-slate-500 mt-1">398 transaksi bulan lalu</p>

          <div className="mt-6 h-12 border-b border-slate-300/60 flex items-end justify-between text-[11px] text-slate-500 pb-1">
            <span>1 Nov</span>
            <span>8 Nov</span>
            <span>16 Nov</span>
            <span>25 Nov</span>
          </div>
        </section>

        {/* Dark CTA Button */}
        <button
          type="button"
          className="w-full h-13 rounded-[20px] bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 shadow-[0_8px_20px_rgba(20,20,40,0.12)] transition cursor-pointer"
        >
          <Download className="w-4 h-4" />
          Export Statistik
        </button>
      </div>
    </div>
  );
}