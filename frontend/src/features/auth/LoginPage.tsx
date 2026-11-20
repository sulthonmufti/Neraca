import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Loader2, Eye, EyeOff, ArrowRight } from 'lucide-react';
import { api } from '../../lib/api';
import { useAuthStore } from '../../stores/authStore';
import { NeracaLogo } from '../../components/brand/NeracaLogo';

export function LoginPage() {
  const navigate = useNavigate();
  const setAuth = useAuthStore((s) => s.setAuth);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const res = await api.post('/auth/login', { email, password });
      const payload = res.data?.data ?? res.data;
      const token = payload.token ?? payload.access_token;
      const user = payload.user;

      if (!token || !user) {
        throw new Error('Format respons login tidak dikenali.');
      }

      setAuth(token, user);
      navigate('/pos', { replace: true });
    } catch (err: any) {
      const msg =
        err.response?.data?.message ||
        err.message ||
        'Gagal login. Periksa email dan password Anda.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const inputClass =
    'w-full h-12 px-4 rounded-xl bg-[#F5F6FA] border border-transparent text-sm text-[#1c1c1e] placeholder:text-slate-400 transition focus:outline-none focus:bg-white focus:border-[#1c1c1e] focus:ring-4 focus:ring-[#8b7fd0]/20';

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#eef0f4] p-4">
      <div className="w-full max-w-md bg-white rounded-[28px] p-8 sm:p-10 shadow-[0_8px_30px_rgba(20,20,40,0.05)]">
        <div className="flex items-center gap-3 mb-8">
          <NeracaLogo size={48} />
          <span className="text-xl font-semibold tracking-tight text-[#1c1c1e]">
            Neraca
          </span>
        </div>

        <h1 className="text-2xl font-semibold text-[#1c1c1e]">Selamat datang</h1>
        <p className="text-sm text-slate-400 mt-1 mb-8">
          Masuk ke akun kasir atau admin Anda
        </p>

        {error && (
          <div className="mb-5 px-4 py-3 rounded-xl bg-red-50 text-sm text-red-600">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-xs font-medium text-slate-500 mb-1.5">
              Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="kasir@neraca.test"
              className={inputClass}
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-500 mb-1.5">
              Password
            </label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className={`${inputClass} pr-11`}
              />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#1c1c1e] cursor-pointer"
                aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full h-12 rounded-xl bg-[#1c1c1e] hover:bg-black text-white text-sm font-medium flex items-center justify-center gap-2 transition disabled:opacity-60 cursor-pointer"
          >
            {loading ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Memproses...
              </>
            ) : (
              <>
                Masuk
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}