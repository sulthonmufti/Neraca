import { create } from 'zustand';

export interface CatalogItem {
  variant_id: number;
  product_id: number;
  name: string;
  product_name: string;
  variant_name: string;
  sku: string;
  category_id: number | null;
  base_unit_id: number;
  unit_code: string;
  sell_price: number;
  track_stock: boolean;
  stock_qty: number;
  tax_rate: number;
  tax_inclusive: boolean;
  barcodes: string[];
}

export interface CartItem {
  variant_id: number;
  unit_id: number;
  name_snapshot: string;
  sku_snapshot: string;
  unit_code: string;
  unit_price: number;
  qty: number;
}

export interface SelectedMember {
  id: number;
  name: string;
  member_no: string;
  discount_percent?: number;
}

interface CartState {
  activeHoldId: number | null;
  items: CartItem[];
  member: SelectedMember | null;
  note: string;
  addItem: (product: CatalogItem) => void;
  updateQty: (variantId: number, delta: number) => void;
  removeItem: (variantId: number) => void;
  setMember: (member: SelectedMember | null) => void;
  setNote: (note: string) => void;
  loadHoldSale: (holdId: number, items: CartItem[], member: SelectedMember | null) => void;
  clearCart: () => void;
}

export const useCartStore = create<CartState>((set) => ({
  activeHoldId: null,
  items: [],
  member: null,
  note: '',

  addItem: (product) =>
    set((state) => {
      const existing = state.items.find((i) => i.variant_id === product.variant_id);
      if (existing) {
        return {
          items: state.items.map((i) =>
            i.variant_id === product.variant_id ? { ...i, qty: i.qty + 1 } : i
          ),
        };
      }
      return {
        items: [
          ...state.items,
          {
            variant_id: product.variant_id,
            unit_id: product.base_unit_id,
            name_snapshot: product.name,
            sku_snapshot: product.sku,
            unit_code: product.unit_code,
            unit_price: product.sell_price,
            qty: 1,
          },
        ],
      };
    }),

  updateQty: (variantId, delta) =>
    set((state) => ({
      items: state.items
        .map((i) => (i.variant_id === variantId ? { ...i, qty: i.qty + delta } : i))
        .filter((i) => i.qty > 0),
    })),

  removeItem: (variantId) =>
    set((state) => ({
      items: state.items.filter((i) => i.variant_id !== variantId),
    })),

  setMember: (member) => set({ member }),
  setNote: (note) => set({ note }),
  loadHoldSale: (holdId, items, member) => set({ activeHoldId: holdId, items, member }),
  clearCart: () => set({ activeHoldId: null, items: [], member: null, note: '' }),
}));