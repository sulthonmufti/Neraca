import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  ShoppingCart,
  Clock,
  Package,
  Warehouse,
  Users,
  BarChart3,
  LogOut,
  Store,
} from 'lucide-react';
import { useAuthStore } from '../../stores/authStore';

const navItems = [
  { to: '/pos', label: 'Kasir (POS)', icon: ShoppingCart },
  { to: '/shift', label: 'Shift & Kas', icon: Clock },
  { to: '/catalog', label: 'Produk', icon: Package },
  { to: '/inventory', label: 'Stok Gudang', icon: Warehouse },
  { to: '/members', label: 'Member', icon: Users },
  { to: '/reports', label: 'Laporan', icon: BarChart3 },
];

export function MainLayout() {
  const { user, logout } = useAuthStore();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen flex bg-slate-50 text-slate-900">
      {/* Sidebar */}
      <aside className="w-60 bg-slate-900 text-slate-200 flex flex-col shrink-0">
        <div className="h-16 px-5 flex items-center gap-2.5 border-b border-slate-800">
          <Store className="w-5 h-5 text-emerald-400" />
          <span className="font-bold text-lg tracking-tight text-white">Neraca POS</span>
        </div>

        <nav className="flex-1 p-3 space-y-1">
          {navItems.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-emerald-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                }`
              }
            >
              <Icon className="w-4 h-4" />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="p-4 border-t border-slate-800">
          <div className="text-xs text-slate-400 mb-2 truncate">
            {user?.name} ({user?.email})
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-800 hover:bg-red-600 text-slate-200 hover:text-white text-sm font-medium transition-colors cursor-pointer"
          >
            <LogOut className="w-4 h-4" />
            Keluar
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-auto">
        <Outlet />
      </main>
    </div>
  );
}