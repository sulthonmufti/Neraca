import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  LayoutGrid,
  ShoppingCart,
  Clock,
  Package,
  Warehouse,
  Users,
  BarChart3,
  LogOut,
  Search,
  Bell,
  Settings,
} from 'lucide-react';
import { useAuthStore } from '../../stores/authStore';
import { NeracaLogo } from '../brand/NeracaLogo';

const navItems = [
  {
    to: '/overview',
    label: 'Overview',
    subtitle: 'Ringkasan performa toko Anda',
    icon: LayoutGrid,
    permission: 'report.view',
  },
  {
    to: '/pos',
    label: 'Kasir (POS)',
    subtitle: 'Transaksi penjualan & pembayaran',
    icon: ShoppingCart,
    permission: 'sale.create',
  },
  {
    to: '/shift',
    label: 'Shift & Kas',
    subtitle: 'Kelola buka/tutup shift dan laci kas',
    icon: Clock,
    permission: 'shift.open',
  },
  {
    to: '/catalog',
    label: 'Produk',
    subtitle: 'Master kategori, produk, dan varian',
    icon: Package,
    permission: 'catalog.manage',
  },
  {
    to: '/inventory',
    label: 'Stok Gudang',
    subtitle: 'Barang masuk, stok opname, dan reorder point',
    icon: Warehouse,
    permission: 'inventory.manage',
  },
  {
    to: '/members',
    label: 'Member',
    subtitle: 'Data pelanggan, kartu member, dan diskon',
    icon: Users,
    permission: 'member.manage',
  },
  {
    to: '/reports',
    label: 'Laporan',
    subtitle: 'Analisis omzet, produk, dan kasir',
    icon: BarChart3,
    permission: 'report.view',
  },
];

export function MainLayout() {
  const { user, hasPermission, logout } = useAuthStore();
  const navigate = useNavigate();
  const location = useLocation();

  const visibleNavItems = navItems.filter((item) => hasPermission(item.permission));
  const currentNav =
    navItems.find((item) => location.pathname.startsWith(item.to)) ?? visibleNavItems[0];

  const primaryRole = user?.roles?.[0] ?? 'user';
  const initials = (user?.name ?? 'U')
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen flex bg-[#eef0f4] text-[#1c1c1e] p-3 sm:p-4 gap-4 sm:gap-6">
      {/* Left Floating Dark Icon Rail */}
      <aside className="w-[72px] shrink-0 flex flex-col items-center justify-between py-1">
        {/* Top Dark Pill */}
        <div className="w-full bg-[#1c1c1e] rounded-[28px] py-4 px-2.5 flex flex-col items-center gap-3 shadow-[0_10px_30px_rgba(20,20,40,0.12)]">
          <div className="mb-2">
            <NeracaLogo size={42} />
          </div>

          <nav className="flex flex-col items-center gap-2.5 w-full">
            {visibleNavItems.map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                title={label}
                className={({ isActive }) =>
                  `w-11 h-11 rounded-2xl flex items-center justify-center transition-all duration-200 ${
                    isActive
                      ? 'bg-white text-[#1c1c1e] shadow-sm scale-105'
                      : 'bg-white/10 text-slate-300 hover:bg-white/20 hover:text-white'
                  }`
                }
              >
                <Icon className="w-5 h-5" />
              </NavLink>
            ))}
          </nav>
        </div>

        {/* Bottom Utility & Logout */}
        <div className="flex flex-col items-center gap-3 mt-6">
          <button
            type="button"
            title="Pengaturan"
            className="w-11 h-11 rounded-2xl flex items-center justify-center text-slate-500 hover:bg-white hover:text-[#1c1c1e] transition cursor-pointer"
          >
            <Settings className="w-5 h-5" />
          </button>

          <button
            type="button"
            onClick={handleLogout}
            title="Keluar"
            className="w-12 h-12 rounded-2xl bg-[#1c1c1e] hover:bg-red-600 text-white flex items-center justify-center shadow-[0_8px_20px_rgba(20,20,40,0.12)] transition cursor-pointer"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </aside>

      {/* Main Workspace */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header */}
        <header className="flex items-center justify-between gap-4 pb-5 pt-1">
          <div>
            <h1 className="text-2xl sm:text-[28px] font-semibold tracking-tight text-[#1c1c1e]">
              {currentNav?.label ?? 'Neraca POS'}
            </h1>
            <p className="text-xs sm:text-sm text-slate-400 mt-0.5">
              {currentNav?.subtitle ?? 'Sistem kasir & manajemen toko'}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              title="Pencarian"
              className="w-11 h-11 rounded-full bg-white flex items-center justify-center text-slate-600 shadow-[0_4px_16px_rgba(20,20,40,0.04)] hover:text-[#1c1c1e] transition cursor-pointer"
            >
              <Search className="w-4 h-4" />
            </button>

            <button
              type="button"
              title="Notifikasi"
              className="relative w-11 h-11 rounded-full bg-white flex items-center justify-center text-slate-600 shadow-[0_4px_16px_rgba(20,20,40,0.04)] hover:text-[#1c1c1e] transition cursor-pointer"
            >
              <Bell className="w-4 h-4" />
              <span className="absolute -top-0.5 -right-0.5 w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-semibold flex items-center justify-center">
                2
              </span>
            </button>

            <div className="flex items-center gap-2.5 bg-white pl-1.5 pr-3.5 py-1.5 rounded-full shadow-[0_4px_16px_rgba(20,20,40,0.04)]">
              <div className="w-8 h-8 rounded-full bg-[#1c1c1e] text-[#b9aef0] text-xs font-semibold flex items-center justify-center">
                {initials}
              </div>
              <div className="hidden sm:block leading-tight">
                <div className="text-xs font-semibold text-[#1c1c1e] max-w-[120px] truncate">
                  {user?.name}
                </div>
                <div className="text-[10px] uppercase tracking-wider text-slate-400">
                  {primaryRole}
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}