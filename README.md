# Neraca

A modular Web POS (Point of Sale) system for cafes, canteens, and cooperatives, built on a modular monolith architecture with Laravel and PostgreSQL.

> **Status:** Backend under active development. Frontend (React PWA) not yet started.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [Module Status](#module-status)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [Seeders & Demo Data](#seeders--demo-data)
- [Infrastructure & Docker](#infrastructure--docker)
- [Getting Started](#getting-started)
- [Roadmap](#roadmap)

---

## Overview

Neraca is designed as a single application that can serve multiple business types (cafe, canteen, cooperative store) from one codebase. Each outlet can independently enable only the relevant modules through the `outlet_modules` table — no separate deployments needed.

**Core design principles:**
- **Append-only ledgers** for stock, cash drawer, and member balances — nothing is ever deleted; every change is a new row.
- **All monetary values** are stored as `BIGINT` in Indonesian Rupiah (no floats, no decimals).
- **All quantities** use `DECIMAL(14,3)` to support bulk or weight-based goods.
- **Transaction snapshots** — `sale_items` stores the product name, price, and cost at the time of sale, so historical reports are never affected by master data changes.
- **Client-generated UUIDs** on `sales` and `shifts` for idempotency and offline sync support.
- **Multi-tenant, multi-outlet** — all transaction tables carry both `tenant_id` and `outlet_id`.

---

## Tech Stack

### Backend

| Component | Choice | Version |
|---|---|---|
| Language | PHP | ^8.3 |
| Framework | Laravel | ^13.17 |
| API Authentication | Laravel Sanctum | ^4.3 |
| Roles & Permissions | spatie/laravel-permission | ^8.3 |
| Audit Log | spatie/laravel-activitylog | ^5.1 |
| Database | PostgreSQL | 16 (Alpine) |
| Cache & Queue | Redis | Alpine |
| Web Server | Nginx | Alpine |
| Unit Testing | PHPUnit | ^12.5 |
| Code Quality | Laravel Pint | ^1.27 |
| Debugging | Laravel Pail | ^1.2.5 |

### Frontend *(planned, not yet implemented)*

React + TypeScript + Vite, TanStack Query, Zustand, Tailwind CSS + shadcn/ui, React Hook Form + Zod, vite-plugin-pwa (PWA/Offline), Dexie (IndexedDB)

### Infrastructure

| Component | Choice |
|---|---|
| Containerization | Docker Compose |
| CI/CD | GitHub Actions *(planned)* |
| Environments | Local, Staging, Production |

---

## Architecture

```
Neraca/
├── backend/           ← Laravel (active)
└── frontend/          ← React PWA (not yet started)
```

The backend follows a **Controller → Validator → Service → Model** pattern:

- **Controller** is thin — validates the request and delegates to a service.
- **Service** contains all business logic, executed inside `DB::transaction()`.
- **Model** only defines relationships and fillable attributes.
- Modules communicate with each other only through public service interfaces, never by querying another module's tables directly.

**`BelongsToTenant` trait** — automatically injects `tenant_id` from the authenticated user on `creating`, and adds a global scope to filter by `tenant_id` on all queries for models that use it.

**Module toggles** — the `outlet_modules` table stores which modules are enabled per outlet. The frontend will load menus and features based on a `/api/v1/bootstrap` endpoint that returns the list of active modules, permissions, and settings.

---

## Project Structure

```
Neraca/
├── Brainstorming/
│   ├── Blueprint Web POS Tech Stack, Skema, dan Roadmap.md   ← Main design document
│   ├── fitur-pos-kafe-kantin-koperasi.md
│   └── POS Features 93 Key Functions.txt
│
├── backend/                          ← Laravel Application
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   └── Controller.php
│   │   ├── Models/
│   │   │   └── User.php
│   │   └── Modules/
│   │       ├── Core/                 ✅ Implemented
│   │       │   ├── Database/Seeders/CoreSeeder.php
│   │       │   ├── Models/
│   │       │   │   ├── Outlet.php
│   │       │   │   ├── OutletModule.php
│   │       │   │   ├── PaymentMethod.php
│   │       │   │   ├── Tax.php
│   │       │   │   └── Tenant.php
│   │       │   └── Traits/BelongsToTenant.php
│   │       │
│   │       ├── Catalog/              ✅ Implemented
│   │       │   ├── Database/Seeders/CatalogSeeder.php
│   │       │   └── Models/
│   │       │       ├── Category.php
│   │       │       ├── Price.php
│   │       │       ├── PriceList.php
│   │       │       ├── Product.php
│   │       │       ├── ProductVariant.php
│   │       │       ├── Promotion.php
│   │       │       ├── Unit.php
│   │       │       ├── VariantBarcode.php
│   │       │       └── VariantUnit.php
│   │       │
│   │       ├── Sales/                ✅ Implemented
│   │       │   ├── Http/Controllers/SaleController.php
│   │       │   ├── Models/
│   │       │   │   ├── Sale.php
│   │       │   │   ├── SaleDiscount.php
│   │       │   │   ├── SaleItem.php
│   │       │   │   ├── SalePayment.php
│   │       │   │   └── ReceiptCounter.php
│   │       │   └── Services/
│   │       │       ├── CreateSaleService.php
│   │       │       └── VoidSaleService.php
│   │       │
│   │       ├── Cash/                 ✅ Implemented
│   │       │   ├── Http/Controllers/CashController.php
│   │       │   ├── Models/
│   │       │   │   ├── CashMovement.php
│   │       │   │   ├── Register.php
│   │       │   │   └── Shift.php
│   │       │   └── Services/
│   │       │       ├── OpenShiftService.php
│   │       │       ├── CloseShiftService.php
│   │       │       └── RecordCashMovementService.php
│   │       │
│   │       ├── Inventory/            ✅ Implemented
│   │       │   ├── Models/
│   │       │   │   ├── StockLevel.php
│   │       │   │   └── StockMovement.php
│   │       │   └── Services/
│   │       │       └── RecordStockMovementService.php
│   │       │
│   │       ├── Member/               🔲 Placeholder
│   │       ├── Cafe/                 🔲 Placeholder
│   │       ├── Canteen/              🔲 Placeholder
│   │       └── Koperasi/             🔲 Placeholder
│   │
│   ├── database/
│   │   ├── migrations/               12 migration files
│   │   └── seeders/
│   │       └── DatabaseSeeder.php
│   │
│   └── routes/
│       └── api.php
│
├── docker/
│   └── nginx/conf.d/
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

---

## Module Status

| Module | Status | Notes |
|---|---|---|
| **Core** | ✅ Done | Tenant, Outlet, User, PaymentMethod, Tax, roles/permissions, demo seeder |
| **Catalog** | ✅ Done | Product, Variant, Unit, tiered pricing, multi-unit barcodes, promotions, demo seeder |
| **Sales** | ✅ Done | CreateSale, VoidSale, split payment, atomic receipt counter |
| **Cash** | ✅ Done | Register, Shift (open/close), CashMovement ledger, cash reconciliation |
| **Inventory** | ✅ Done | StockLevel, StockMovement ledger, unit conversion on sale |
| **Member** | 🔲 Not started | Wallet, credit, points — empty folder |
| **Cafe** | 🔲 Not started | Tables, modifiers, kitchen tickets — empty folder |
| **Canteen** | 🔲 Not started | Daily menus, pre-orders, queue — empty folder |
| **Koperasi** | 🔲 Not started | Savings, profit-sharing — empty folder |
| **Frontend** | 🔲 Not started | React PWA — no code yet |

---

## Database Schema

> Convention: all monetary values use `BIGINT` (Rupiah), all quantities use `DECIMAL(14,3)`, all timestamps use `timestampsTz()`.

### Core

#### `tenants`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Tenant / organization name |
| `code` | string UNIQUE | |
| `domain` | string nullable | Custom domain |
| `is_active` | boolean | |
| `settings` | jsonb nullable | Free-form config (currency, allow_negative_stock, etc.) |

#### `outlets`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `code` | string(50) | UNIQUE per tenant |
| `name` | string | |
| `address` | text nullable | |
| `phone` | string(30) nullable | |
| `npwp` | string(50) nullable | Tax ID for receipts |
| `timezone` | string(50) | Default `Asia/Jakarta` |
| `receipt_header` | text nullable | Receipt header text |
| `receipt_footer` | text nullable | Receipt footer text |
| `is_active` | boolean | |
| `deleted_at` | timestampTz nullable | Soft delete |

#### `users` *(altered via core migration)*
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants nullable | |
| `name` | string | |
| `email` | string UNIQUE | |
| `password` | string | |
| `pin_hash` | string nullable | Cashier PIN for quick login |
| `is_active` | boolean | |
| `last_login_at` | timestampTz nullable | |
| `deleted_at` | timestampTz nullable | Soft delete |

#### `user_outlets` *(pivot)*
| Column | Type | Description |
|---|---|---|
| `user_id` | FK → users | Composite PK |
| `outlet_id` | FK → outlets | Composite PK |
| `is_default` | boolean | User's default outlet |

#### `outlet_modules`
| Column | Type | Description |
|---|---|---|
| `outlet_id` | FK → outlets | Composite PK |
| `module_key` | string(50) | Composite PK, e.g. `cafe`, `koperasi` |
| `is_enabled` | boolean | Module toggle |
| `config` | jsonb nullable | Module-specific configuration |

#### `settings`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `outlet_id` | FK → outlets nullable | null = tenant-level global setting |
| `key` | string(100) | UNIQUE per (tenant_id, outlet_id, key) |
| `value` | jsonb | |

#### `taxes`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `name` | string(100) | e.g. `PPN 11%` |
| `kind` | string(20) | `tax` or `service` |
| `rate_percent` | decimal(5,2) | |
| `is_inclusive` | boolean | Whether tax is already included in the price |
| `is_active` | boolean | |

#### `payment_methods`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `code` | string(50) | UNIQUE per tenant |
| `name` | string(100) | |
| `type` | string(30) | `cash`, `qris`, `transfer`, `member_balance`, `member_credit` |
| `sort_order` | integer | Display order at cashier |
| `is_active` | boolean | |
| `config` | jsonb nullable | |

#### `devices`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `outlet_id` | FK → outlets | |
| `name` | string(100) | Cashier device name |
| `last_seen_at` | timestampTz nullable | |
| `last_sync_at` | timestampTz nullable | |

#### `idempotency_keys`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `key` | string(100) UNIQUE | |
| `user_id` | FK → users nullable | |
| `endpoint` | string | |
| `response_code` | smallint | |
| `response_body` | jsonb nullable | Stored response for replay |

---

### Catalog

#### `categories`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `parent_id` | FK → categories nullable | Self-referencing for nested categories |
| `name` | string | |
| `sort_order` | integer | |
| `is_active` | boolean | |

#### `units`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `code` | string(30) | UNIQUE per tenant, e.g. `pcs`, `kg`, `cup` |
| `name` | string(100) | |

#### `products`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `category_id` | FK → categories nullable | |
| `name` | string | |
| `kind` | string(30) | `goods`, `service`, `menu`, `ingredient` |
| `image_path` | string nullable | |
| `track_stock` | boolean | Whether stock is tracked |
| `tax_id` | FK → taxes nullable | |
| `description` | text nullable | |
| `attributes` | jsonb nullable | Free-form product attributes |
| `is_active` | boolean | |
| `deleted_at` | timestampTz nullable | Soft delete |

#### `product_variants`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `product_id` | FK → products | |
| `sku` | string(100) | UNIQUE per tenant |
| `name` | string | Variant name, e.g. `Regular`, `Large` |
| `base_unit_id` | FK → units | Base unit for stock calculation |
| `cost_price` | bigint | Cost of goods (Rupiah) |
| `sell_price` | bigint | Default selling price (Rupiah) |
| `is_active` | boolean | |
| `deleted_at` | timestampTz nullable | Soft delete |

#### `variant_units`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK → product_variants | UNIQUE per (variant_id, unit_id) |
| `unit_id` | FK → units | |
| `factor` | decimal(14,3) | Conversion factor to base unit, e.g. 1 box = 40 pcs |
| `sell_price` | bigint nullable | Price override for this specific unit |

#### `variant_barcodes`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `variant_id` | FK → product_variants | |
| `unit_id` | FK → units nullable | Unit-specific barcode |
| `barcode` | string(100) | UNIQUE per tenant |

#### `price_lists`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `name` | string(100) | e.g. `Retail Price`, `Member Price` |
| `kind` | string(30) | `retail`, `wholesale`, `member` |
| `is_active` | boolean | |

#### `prices`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `price_list_id` | FK → price_lists | |
| `variant_id` | FK → product_variants | |
| `unit_id` | FK → units | |
| `min_qty` | decimal(14,3) | Minimum quantity for this price to apply |
| `price` | bigint | Price (Rupiah) |
| `starts_at` | timestampTz nullable | |
| `ends_at` | timestampTz nullable | |

#### `promotions`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `name` | string | |
| `kind` | string(30) | `percent`, `amount`, `buy_x_get_y` |
| `rules` | jsonb | Promotion rule definition |
| `member_type_id` | bigint nullable | FK → `member_types` (Member module, not yet created) |
| `starts_at` | timestampTz nullable | |
| `ends_at` | timestampTz nullable | |
| `is_active` | boolean | |

---

### Sales

#### `sales`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | uuid UNIQUE | Client-generated UUID for idempotency & offline sync |
| `tenant_id` | FK → tenants | |
| `outlet_id` | FK → outlets | |
| `shift_id` | bigint nullable | FK → shifts (nullable if no active shift) |
| `receipt_no` | string(100) | UNIQUE per outlet, format `INV-YYYYMMDD-NNNNN` |
| `cashier_id` | FK → users | |
| `member_id` | bigint nullable | FK → members (Member module, not yet created) |
| `order_type` | string(30) | `dine_in`, `take_away`, `delivery` |
| `table_id` | bigint nullable | FK → `dining_tables` (Cafe module, not yet created) |
| `status` | string(30) | `hold`, `completed`, `voided` |
| `subtotal` | bigint | |
| `discount_total` | bigint | |
| `tax_total` | bigint | |
| `service_total` | bigint | |
| `rounding` | bigint | |
| `grand_total` | bigint | subtotal − discount + tax + service + rounding |
| `paid_total` | bigint | Total amount paid by customer |
| `change_total` | bigint | Change returned |
| `note` | text nullable | |
| `sold_at` | timestampTz | Transaction time on the cashier device |
| `voided_at` | timestampTz nullable | |
| `void_reason` | text nullable | |
| `voided_by` | bigint nullable | FK → users |
| `approved_by` | bigint nullable | FK → users |

#### `sale_items`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `variant_id` | FK → product_variants | |
| `unit_id` | FK → units | Unit of measure used for the sale |
| `name_snapshot` | string | Product name at time of sale (snapshot) |
| `sku_snapshot` | string(100) | SKU at time of sale (snapshot) |
| `qty` | decimal(14,3) | In the selling unit |
| `unit_price` | bigint | Unit price at time of sale |
| `cost_snapshot` | bigint | Cost of goods at time of sale (for profit calculation) |
| `discount_amount` | bigint | |
| `tax_amount` | bigint | |
| `line_total` | bigint | (qty × unit_price) − discount + tax |
| `modifiers` | jsonb nullable | Add-on selections (sugar level, toppings, etc.) |
| `note` | text nullable | |
| `kitchen_status` | string(30) | `pending`, `cooking`, `ready`, `served` |

#### `sale_discounts`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `sale_item_id` | FK → sale_items nullable | null = transaction-level discount |
| `promotion_id` | bigint nullable | FK → promotions |
| `kind` | string(30) | `percent`, `amount` |
| `value` | decimal(10,2) | Discount value (10 for 10%, or 5000 for fixed amount) |
| `amount` | bigint | Actual discount deduction in Rupiah |
| `reason` | string nullable | |
| `approved_by` | bigint nullable | FK → users |

#### `sale_payments`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `payment_method_id` | FK → payment_methods | |
| `amount` | bigint | Amount paid with this method |
| `reference` | string(100) nullable | EDC / Transfer / QRIS reference number |
| `status` | string(30) | `paid`, `pending`, `failed`, `void` |
| `paid_at` | timestampTz | |
| `gateway_payload` | jsonb nullable | Raw payment gateway response |

#### `receipt_counters`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `outlet_id` | FK → outlets | UNIQUE per (outlet_id, counter_date) |
| `counter_date` | date | |
| `last_number` | bigint | Last issued receipt number for the day |

---

### Cash

#### `registers`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `outlet_id` | FK → outlets | |
| `name` | string(100) | e.g. `Cashier 1`, `Front POS` |
| `code` | string(50) | UNIQUE per outlet |
| `is_active` | boolean | |

#### `shifts`
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | uuid UNIQUE | |
| `tenant_id` | FK → tenants | |
| `outlet_id` | FK → outlets | |
| `register_id` | FK → registers | |
| `user_id` | FK → users | Cashier who opened the shift |
| `opened_at` | timestampTz | |
| `closed_at` | timestampTz nullable | |
| `opening_cash` | bigint | Starting cash in drawer |
| `expected_cash` | bigint | System-calculated cash (opening + sales − payouts) |
| `counted_cash` | bigint nullable | Physical cash count at close |
| `diff_cash` | bigint | Discrepancy (counted − expected) |
| `total_cash_sales` | bigint | Total cash sales during shift |
| `total_non_cash_sales` | bigint | Total non-cash sales during shift |
| `total_cash_in` | bigint | Total manual cash-ins |
| `total_cash_out` | bigint | Total manual cash-outs |
| `status` | string(30) | `open`, `closed`, `reconciled` |
| `note` | text nullable | Opening note |
| `close_note` | text nullable | Closing note |

#### `cash_movements` *(Cash Ledger, append-only)*
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `shift_id` | FK → shifts | |
| `type` | string(30) | `sale_cash`, `refund_cash`, `cash_in`, `cash_out`, `topup_cash` |
| `amount` | bigint | Signed: positive = in, negative = out |
| `category` | string(50) nullable | |
| `reason` | text | Required description |
| `sale_id` | FK → sales nullable | Reference to the related sale if applicable |
| `user_id` | FK → users | |

---

### Inventory

#### `stock_levels` *(Stock Cache, Composite PK)*
| Column | Type | Description |
|---|---|---|
| `outlet_id` | FK → outlets | Composite PK |
| `variant_id` | FK → product_variants | Composite PK |
| `qty` | decimal(14,3) | Current stock (cache derived from ledger) |
| `reorder_point` | decimal(14,3) | Minimum level before reorder alert |

#### `stock_movements` *(Stock Ledger, append-only)*
| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `outlet_id` | FK → outlets | |
| `variant_id` | FK → product_variants | |
| `type` | string(30) | `sale`, `sale_void`, `return`, `receipt`, `adjustment`, `opname`, `transfer_out`, `transfer_in`, `recipe_use`, `waste` |
| `qty_change` | decimal(14,3) | Quantity change (positive = in, negative = out) |
| `qty_after` | decimal(14,3) | Stock balance after this movement |
| `unit_cost` | bigint | Cost of goods at time of movement |
| `ref_type` | string(50) nullable | Source reference type (e.g. `sales`) |
| `ref_id` | bigint nullable | Source reference ID |
| `batch_id` | bigint nullable | For batch/expiry tracking (Phase 3) |
| `note` | text nullable | |
| `user_id` | FK → users | |
| `created_at` | timestampTz | *No `updated_at` — append-only* |

---

### ERD Summary

```
tenants
  ├── outlets (tenant_id)
  │     ├── user_outlets ←── users
  │     ├── outlet_modules
  │     ├── devices
  │     ├── settings
  │     ├── registers
  │     │     └── shifts
  │     │           └── cash_movements (ledger)
  │     ├── stock_levels (cache)
  │     │     └── stock_movements (ledger)
  │     ├── sales
  │     │     ├── sale_items
  │     │     │     └── sale_discounts
  │     │     ├── sale_discounts (transaction-level)
  │     │     └── sale_payments
  │     └── receipt_counters
  ├── users (tenant_id)
  ├── taxes (tenant_id)
  ├── payment_methods (tenant_id)
  ├── categories (tenant_id, self-ref)
  ├── units (tenant_id)
  ├── price_lists (tenant_id)
  ├── promotions (tenant_id)
  └── products (tenant_id)
        └── product_variants
              ├── variant_units
              ├── variant_barcodes
              └── prices
```

---

## API Endpoints

All endpoints are under `auth:sanctum` middleware and the `/api/v1` prefix.

| Method | Endpoint | Controller | Description |
|---|---|---|---|
| `POST` | `/sales` | `SaleController@store` | Create a new sale transaction |
| `POST` | `/sales/{id}/void` | `SaleController@voidSale` | Void a transaction |
| `POST` | `/shifts/open` | `CashController@openShift` | Open a cashier shift |
| `POST` | `/shifts/{id}/movements` | `CashController@storeMovement` | Record a manual cash-in or cash-out |
| `POST` | `/shifts/{id}/close` | `CashController@closeShift` | Close and reconcile a shift |

### `POST /api/v1/sales` Flow

1. Validate request (items, payments, outlet_id).
2. Find the active shift by `shift_id` or by the cashier's open shift at that outlet.
3. Atomically generate `receipt_no` with `SELECT ... FOR UPDATE` on `receipt_counters`.
4. Calculate `subtotal`, `grand_total`, and `change_total` on the backend.
5. Save `Sale` + bulk insert `sale_items`.
6. Deduct stock per item with selling unit → base unit conversion, write `stock_movements` ledger.
7. Save `sale_payments`, detect cash vs non-cash payment methods.
8. If an active shift exists, write `cash_movements` ledger + update shift summary cache.
9. Return `Sale` with `items`, `payments`, and `cashier` relations loaded.

### Sample `POST /api/v1/sales` Payload

```json
{
  "outlet_id": 1,
  "shift_id": 1,
  "order_type": "dine_in",
  "items": [
    {
      "variant_id": 1,
      "unit_id": 1,
      "name_snapshot": "Instant Noodle Goreng Special",
      "sku_snapshot": "TOKO-MIE-001",
      "qty": 2,
      "unit_price": 3500,
      "cost_snapshot": 2800,
      "discount_amount": 0,
      "tax_amount": 0
    }
  ],
  "discount_total": 0,
  "tax_total": 0,
  "payments": [
    {
      "payment_method_id": 1,
      "amount": 10000
    }
  ]
}
```

---

## Seeders & Demo Data

Run the seeders to populate initial data:

```bash
docker compose exec app php artisan db:seed
```

**CoreSeeder** creates:
- Tenant: `Neraca Sejahtera Utama` (code: `neraca-demo`)
- Outlet: `Outlet Pusat (Kafe & Koperasi)` (code: `OUT-001`)
- Active modules: `cafe` ✅, `koperasi` ✅, `canteen` ❌
- Roles: `admin`, `manager`, `supervisor`, `kasir`
- Permissions: `sale.create`, `sale.void`, `sale.return`, `discount.apply`, `shift.open`, `shift.close`, `catalog.manage`, `inventory.manage`, `member.manage`, `report.view`, `settings.manage`
- Tax: VAT 11% (exclusive)
- Payment methods: Cash, QRIS, Bank Transfer, Member Balance, Credit / Payroll Deduction
- Demo users: `admin@neraca.test` (PIN: 111111) and `kasir@neraca.test` (PIN: 123456), both with password `password`

**CatalogSeeder** creates:
- Units: `pcs`, `dus` (box), `cup`, `kg`
- Categories: `Minuman Kafe` (Cafe Drinks), `Sembako & Toko` (Grocery & Store)
- Price lists: Retail price, Cooperative member price
- Cafe product: `Es Kopi Susu Gula Aren` — kind: menu, track_stock: false, Rp 18,000/cup
- Store product: `Mie Instan Goreng Spesial` — kind: goods, track_stock: true, Rp 3,500/pcs, Rp 132,000/box (40 pcs), member price Rp 3,200/pcs, with two separate barcodes per unit

---

## Infrastructure & Docker

```yaml
# docker-compose.yml — 4 containers
services:
  neraca_app       # PHP-FPM 8.3, internal port 9000
  neraca_webserver # Nginx Alpine, port 8000:80
  neraca_db        # PostgreSQL 16 Alpine, port 5433:5432
  neraca_redis     # Redis Alpine, port 6379:6379
```

### Ports

| Service | Host Port |
|---|---|
| Web (Nginx) | `8000` |
| PostgreSQL | `5433` |
| Redis | `6379` |

---

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Git

### Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd Neraca

# 2. Copy environment files
cp .env.example .env
cp backend/.env.example backend/.env
# Edit backend/.env: set DB_HOST=db, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# to match the values in the root .env used by docker-compose

# 3. Start containers
docker compose up -d

# 4. Generate application key
docker compose exec app php artisan key:generate

# 5. Run migrations
docker compose exec app php artisan migrate

# 6. (Optional) Seed demo data
docker compose exec app php artisan db:seed
```

The application will be available at `http://localhost:8000`.

### Verify Containers

```bash
docker compose ps
# All 4 containers should be Up: neraca_app, neraca_webserver, neraca_db, neraca_redis
```

### Stop Containers

```bash
docker compose down          # Stop containers (data preserved)
docker compose down -v       # Stop + remove database volume (data lost!)
```

### Run Tests

```bash
docker compose exec app php artisan test
```

---

## Roadmap

Based on the blueprint in `Brainstorming/`.

| Phase | Target | Status |
|---|---|---|
| **Phase 0** | Repo setup, Docker, CI, module scaffolding, authentication | ✅ Done |
| **Phase 1 — Sprint 1** | Master data: outlet, user, role, catalog, Excel import | 🔄 Partial (models & migrations done, CRUD API not yet) |
| **Phase 1 — Sprint 2** | Core transaction: cashier screen, cart, CreateSale, stock deduction | ✅ Done |
| **Phase 1 — Sprint 3** | Cash shift, receipt printing, void, returns | ✅ Shift & void done. Receipts & returns not yet |
| **Phase 1 — Sprint 4** | Daily reports, basic member, goods receipt, stock opname | 🔲 Not started |
| **Phase 2** | Member wallet, credit, offline mode, purchasing, digital receipts | 🔲 Not started |
| **Phase 3** | Cafe, Canteen, Cooperative (store) modules | 🔲 Not started |
| **Phase 4** | Cooperative savings & loans | 🔲 Not started |

---

## License

Private project. All rights reserved.
