import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useAuthStore } from './stores/authStore';
import { LoginPage } from './features/auth/LoginPage';
import { MainLayout } from './components/layout/MainLayout';
import { OverviewPage } from './features/dashboard/OverviewPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const token = useAuthStore((s) => s.token);
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
}

function DefaultHomeRedirect() {
  const hasPermission = useAuthStore((s) => s.hasPermission);
  return <Navigate to={hasPermission('report.view') ? '/overview' : '/pos'} replace />;
}

function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="bg-white rounded-[24px] p-8 shadow-[0_4px_24px_rgba(20,20,40,0.03)]">
      <h2 className="text-lg font-semibold text-[#1c1c1e]">{title}</h2>
      <p className="text-sm text-slate-400 mt-1">
        Halaman ini siap dibangun pada tahap berikutnya.
      </p>
    </div>
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route
            path="/"
            element={
              <ProtectedRoute>
                <MainLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<DefaultHomeRedirect />} />
            <Route path="overview" element={<OverviewPage />} />
            <Route path="pos" element={<PlaceholderPage title="Layar Kasir (POS)" />} />
            <Route path="shift" element={<PlaceholderPage title="Manajemen Shift & Kas" />} />
            <Route path="catalog" element={<PlaceholderPage title="Master Produk" />} />
            <Route path="inventory" element={<PlaceholderPage title="Stok Gudang" />} />
            <Route path="members" element={<PlaceholderPage title="Data Member" />} />
            <Route path="reports" element={<PlaceholderPage title="Laporan Penjualan" />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}