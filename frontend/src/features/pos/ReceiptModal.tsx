import { CheckCircle2, Printer, X } from 'lucide-react';

export interface ReceiptData {
  receipt_no?: string;
  sold_at?: string;
  items: {
    name_snapshot: string;
    qty: number;
    unit_price: number;
  }[];
  subtotal: number;
  discount_total: number;
  grand_total: number;
  paid_total: number;
  change_total: number;
  payment_method_name: string;
}

const formatRp = (n: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n);

interface Props {
  receipt: ReceiptData;
  onClose: () => void;
}

export function ReceiptModal({ receipt, onClose }: Props) {
  return (
    <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-[28px] w-full max-w-sm p-6 shadow-xl">
        <div className="flex items-center justify-between mb-4 print:hidden">
          <div className="flex items-center gap-2 text-emerald-600 font-semibold text-sm">
            <CheckCircle2 className="w-5 h-5" />
            Transaksi Berhasil
          </div>
          <button
            type="button"
            onClick={onClose}
            className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 hover:text-slate-900 cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Area Cetak Struk 80mm */}
        <div id="receipt-print-area" className="bg-[#F5F6FA] rounded-2xl p-4 font-mono text-xs text-[#1c1c1e]">
          <div className="text-center border-b border-dashed border-slate-300 pb-3 mb-3">
            <div className="font-bold text-sm">NERACA POS</div>
            <div className="text-[11px] text-slate-500">
              {receipt.receipt_no || 'STRUK PENJUALAN'}
            </div>
            <div className="text-[10px] text-slate-400 mt-0.5">
              {new Date(receipt.sold_at || Date.now()).toLocaleString('id-ID')}
            </div>
          </div>

          <div className="space-y-2 border-b border-dashed border-slate-300 pb-3 mb-3">
            {receipt.items.map((item, idx) => (
              <div key={idx}>
                <div className="font-medium">{item.name_snapshot}</div>
                <div className="flex justify-between text-slate-600">
                  <span>
                    {item.qty} x {formatRp(item.unit_price)}
                  </span>
                  <span>{formatRp(item.qty * item.unit_price)}</span>
                </div>
              </div>
            ))}
          </div>

          <div className="space-y-1">
            <div className="flex justify-between">
              <span>Subtotal</span>
              <span>{formatRp(receipt.subtotal)}</span>
            </div>
            {receipt.discount_total > 0 && (
              <div className="flex justify-between text-emerald-700">
                <span>Diskon</span>
                <span>-{formatRp(receipt.discount_total)}</span>
              </div>
            )}
            <div className="flex justify-between font-bold text-sm pt-1 border-t border-dashed border-slate-300">
              <span>TOTAL</span>
              <span>{formatRp(receipt.grand_total)}</span>
            </div>
            <div className="flex justify-between pt-1">
              <span>Bayar ({receipt.payment_method_name})</span>
              <span>{formatRp(receipt.paid_total)}</span>
            </div>
            <div className="flex justify-between font-semibold">
              <span>Kembalian</span>
              <span>{formatRp(receipt.change_total)}</span>
            </div>
          </div>

          <div className="text-center text-[10px] text-slate-400 mt-4 pt-3 border-t border-dashed border-slate-300">
            Terima kasih atas kunjungan Anda!
          </div>
        </div>

        <div className="grid grid-cols-2 gap-3 mt-5 print:hidden">
          <button
            type="button"
            onClick={() => window.print()}
            className="h-11 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 cursor-pointer"
          >
            <Printer className="w-4 h-4" />
            Cetak Struk
          </button>
          <button
            type="button"
            onClick={onClose}
            className="h-11 rounded-xl bg-[#F5F6FA] hover:bg-slate-200 text-[#1c1c1e] text-sm font-medium cursor-pointer"
          >
            Transaksi Baru
          </button>
        </div>
      </div>
    </div>
  );
}