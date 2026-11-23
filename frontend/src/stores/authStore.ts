import { create } from 'zustand';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  tenant_id: number;
  outlet_id?: number;
  roles?: string[];
  permissions?: string[];
}

interface AuthState {
  token: string | null;
  user: AuthUser | null;
  outletId: number;
  setAuth: (token: string, user: AuthUser) => void;
  setOutletId: (outletId: number) => void;
  hasPermission: (permission: string) => boolean;
  logout: () => void;
}

const savedToken = localStorage.getItem('neraca_token');
const savedUser = localStorage.getItem('neraca_user');

export const useAuthStore = create<AuthState>((set, get) => ({
  token: savedToken,
  user: savedUser ? (JSON.parse(savedUser) as AuthUser) : null,
  outletId: 1,

  setAuth: (token, user) => {
    localStorage.setItem('neraca_token', token);
    localStorage.setItem('neraca_user', JSON.stringify(user));
    set({
      token,
      user,
      outletId: user.outlet_id ?? 1,
    });
  },

  setOutletId: (outletId) => set({ outletId }),

  hasPermission: (permission) => {
    const user = get().user;
    if (!user) return false;
    if (user.roles?.includes('admin')) return true;
    return user.permissions?.includes(permission) ?? false;
  },

  logout: () => {
    localStorage.removeItem('neraca_token');
    localStorage.removeItem('neraca_user');
    set({ token: null, user: null });
  },
}));