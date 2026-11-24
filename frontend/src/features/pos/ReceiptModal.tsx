import { CheckCircle2, Printer, X } from 'lucide-react';
import { useAuthStore } from '../../stores/authStore';

export interface ReceiptData {
  receipt_no?: string;
  sold_at?: string;
  cashier_name?: string;
  member_name?: string | null;
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

export interface OutletReceiptInfo {
  tenant_name: string;
  name: string;
  address?: string | null;
  phone?: string | null;
  receipt_header?: string | null;
  receipt_footer?: string | null;
}

const formatRp = (n: number) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(n);

interface Props {
  receipt: ReceiptData;
  outlet?: OutletReceiptInfo | null;
  onClose: () => void;
}

export function ReceiptModal({ receipt, outlet, onClose }: Props) {
  const user = useAuthStore((s) => s.user);
  const cashierName = receipt.cashier_name || user?.name || 'Kasir';
  const totalQty = receipt.items.reduce((sum, item) => sum + Number(item.qty), 0);

  const handlePrint = () => {
    const printEl = document.getElementById('receipt-print-area');
    if (!printEl) return;

    // Hitung tinggi konten struk dalam mm agar pas 1 halaman di Save as PDF & Thermal
    const heightMm = Math.max(90, Math.ceil(printEl.scrollHeight * 0.264583) + 16);

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) return;

    // Salin semua stylesheet aktif (Tailwind) agar tampilan struk identik
    const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
      .map((node) => node.outerHTML)
      .join('');

    doc.open();
    doc.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8" />
          ${styles}
          <style>
            @page {
              size: 80mm ${heightMm}mm;
              margin: 0;
            }
            html, body {
              width: 80mm;
              margin: 0;
              padding: 0;
              background: #ffffff;
            }
            #receipt-print-area {
              width: 80mm !important;
              background: #ffffff !important;
              border-radius: 0 !important;
              padding: 4mm 5mm !important;
              box-sizing: border-box;
            }
          </style>
        </head>
        <body>${printEl.outerHTML}</body>
      </html>
    `);
    doc.close();

    iframe.onload = () => {
      iframe.contentWindow?.focus();
      iframe.contentWindow?.print();
      setTimeout(() => document.body.removeChild(iframe), 500);
    };
  };

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
        <div
          id="receipt-print-area"
          className="bg-[#F5F6FA] rounded-2xl p-5 font-mono text-[11px] leading-relaxed text-[#1c1c1e]"
        >
          {/* Header Toko (Dinamis dari DB) */}
          <div className="text-center pb-3 border-b border-dashed border-slate-400">
            <div className="font-bold text-sm tracking-wide uppercase">
              {outlet?.tenant_name || 'NERACA POS'}
            </div>
            {outlet?.name && (
              <div className="text-[10px] font-semibold text-slate-700 mt-0.5">
                {outlet.name}
              </div>
            )}
            {outlet?.address && (
              <div className="text-[10px] text-slate-500 mt-0.5">
                {outlet.address}
              </div>
            )}
            {outlet?.phone && (
              <div className="text-[10px] text-slate-500">
                Telp: {outlet.phone}
              </div>
            )}
            {outlet?.receipt_header && (
              <div className="text-[10px] text-slate-600 mt-1 whitespace-pre-line">
                {outlet.receipt_header}
              </div>
            )}
          </div>

          {/* Info Transaksi */}
          <div className="py-2.5 border-b border-dashed border-slate-400 space-y-0.5 text-[10px]">
            <div className="flex justify-between">
              <span className="text-slate-500">No. Struk</span>
              <span className="font-semibold">{receipt.receipt_no || '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Tanggal</span>
              <span>
                {new Date(receipt.sold_at || Date.now()).toLocaleString('id-ID', {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Kasir</span>
              <span>{cashierName}</span>
            </div>
            {receipt.member_name && (
              <div className="flex justify-between">
                <span className="text-slate-500">Member</span>
                <span>{receipt.member_name}</span>
              </div>
            )}
          </div>

          {/* Daftar Item */}
          <div className="py-2.5 border-b border-dashed border-slate-400 space-y-2">
            {receipt.items.map((item, idx) => (
              <div key={idx}>
                <div className="font-semibold text-[#1c1c1e]">{item.name_snapshot}</div>
                <div className="flex justify-between text-slate-600">
                  <span>
                    {item.qty} x {formatRp(item.unit_price)}
                  </span>
                  <span className="font-medium text-[#1c1c1e]">
                    {formatRp(item.qty * item.unit_price)}
                  </span>
                </div>
              </div>
            ))}
          </div>

          {/* Ringkasan Pembayaran */}
          <div className="py-2.5 border-b border-dashed border-slate-400 space-y-1">
            <div className="flex justify-between text-slate-600">
              <span>Subtotal ({totalQty} item)</span>
              <span>{formatRp(receipt.subtotal)}</span>
            </div>
            {receipt.discount_total > 0 && (
              <div className="flex justify-between text-emerald-700">
                <span>Diskon Member</span>
                <span>-{formatRp(receipt.discount_total)}</span>
              </div>
            )}
            <div className="flex justify-between font-bold text-xs pt-1.5 mt-1 border-t border-dashed border-slate-300">
              <span>TOTAL</span>
              <span>{formatRp(receipt.grand_total)}</span>
            </div>
            <div className="flex justify-between pt-1">
              <span>Bayar ({receipt.payment_method_name})</span>
              <span>{formatRp(receipt.paid_total)}</span>
            </div>
            <div className="flex justify-between font-bold">
              <span>Kembalian</span>
              <span>{formatRp(receipt.change_total)}</span>
            </div>
          </div>

          {/* Footer Toko (Dinamis dari DB) */}
          <div className="text-center pt-3 space-y-1">
            <div className="font-semibold text-[11px] whitespace-pre-line">
              {outlet?.receipt_footer || 'Terima kasih atas kunjungan Anda!'}
            </div>
            <div className="text-[9px] text-slate-400 pt-2">
              --- Powered by Neraca POS ---
            </div>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-3 mt-5 print:hidden">
          <button
            type="button"
            onClick={handlePrint}
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