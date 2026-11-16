# Neraca

This project is currently under active development.

---

## Overview

Neraca is a modular monolith application built with Laravel and PostgreSQL. The codebase is organized into self-contained modules under `app/Modules/`, each of which can be enabled or disabled independently per outlet.

```
backend/
└── app/
    └── Modules/
        ├── Core/
        ├── Catalog/
        ├── Inventory/
        ├── Sales/
        ├── Cash/
        ├── Member/
        ├── Cafe/
        ├── Canteen/
        └── Koperasi/
```

---

## Tech Stack

### Backend

| Component   | Choice         |
|-------------|----------------|
| Language    | PHP 8.4        |
| Framework   | Laravel 13     |
| Database    | PostgreSQL 16  |
| Cache/Queue | Redis          |
| Web Server  | Nginx (Alpine) |

### Infrastructure

| Component        | Choice                     |
|------------------|----------------------------|
| Containerization | Docker Compose             |
| Environments     | Local, Staging, Production |

---

## Getting Started

### Requirements

- Docker and Docker Compose
- Git

### Setup

1. Clone the repository

   ```bash
   git clone <repo-url>
   cd Neraca
   ```

2. Copy the environment files

   ```bash
   cp .env.example .env
   cp backend/.env.example backend/.env
   ```

3. Start the containers

   ```bash
   docker compose up -d
   ```

4. Generate the application key

   ```bash
   docker compose exec app php artisan key:generate
   ```

5. Run migrations

   ```bash
   docker compose exec app php artisan migrate
   ```

The application will be available at `http://localhost:8000`.

### Verify Containers are Running

```bash
docker compose ps
```

All four containers should have a status of `Up`: `neraca_app`, `neraca_webserver`, `neraca_db`, and `neraca_redis`.

### Stop the Containers

```bash
docker compose down
```

To stop and remove all data volumes as well (this will delete the database):

```bash
docker compose down -v
```

### Ports

| Service     | Port   |
|-------------|--------|
| Web (Nginx) | `8000` |
| PostgreSQL  | `5433` |
| Redis       | `6379` |

---

## Repository Structure

```
Neraca/
├── backend/            # Laravel application
├── docker/             # Docker configuration (Nginx, etc.)
├── docker-compose.yml
├── Dockerfile
├── .env.example
├── .gitignore
└── README.md
```
## Packages

| Package | Purpose |
|---------|---------|
| `laravel/sanctum` | API token authentication |
| `spatie/laravel-permission` | Role & permission management |
| `spatie/laravel-activitylog` | Audit log / activity history |

---

## Modules

| Module | Status | Description |
|--------|--------|-------------|
| **Core** | ✅ Active | Tenant, outlet, user, payment methods, taxes, settings, devices |
| **Catalog** | ✅ Active | Products, variants, units, barcodes, price lists, prices, promotions |
| **Sales** | ✅ Active | Transaction processing, sale items, discounts, payments, receipt counters |
| **Cash** | ✅ Active | Cash register management, shift open/close, cash movements |
| **Inventory** | ✅ Active | Supplier management, goods receipts (GRN), stock ledger, stock opnames |
| **Member** | 🔲 Planned | Member management (not yet implemented) |
| **Cafe** | 🔲 Planned | Table & kitchen order management (not yet implemented) |
| **Canteen** | 🔲 Planned | Canteen-specific features (not yet implemented) |
| **Koperasi** | 🔲 Planned | Cooperative features (not yet implemented) |

### Module Details

#### Core
Models: `Tenant`, `Outlet`, `OutletModule`, `PaymentMethod`, `Tax`
Traits: `BelongsToTenant` — applied to all tenant-scoped models for automatic query scoping.

#### Catalog
Models: `Category`, `Product`, `ProductVariant`, `Unit`, `VariantUnit`, `VariantBarcode`, `PriceList`, `Price`, `Promotion`
Seeders: `CatalogSeeder`

#### Sales
Models: `Sale`, `SaleItem`, `SaleDiscount`, `SalePayment`, `ReceiptCounter`
Services: `CreateSaleService` — processes a sale transaction within a single atomic DB transaction.
Controllers: `SaleController`

#### Cash
Models: `Register`, `Shift`, `CashMovement`
Services:
- `OpenShiftService` — opens a new shift, validates that no active shift is already open on the same register.
- `CloseShiftService` — closes a shift and calculates the difference between physical cash count and the system-expected amount.
- `RecordCashMovementService` — records cash-in / cash-out movements during an active shift.

Controllers: `CashController`

#### Inventory
Models: `Supplier`, `GoodsReceipt`, `GoodsReceiptItem`, `GoodsReceiptCounter`, `StockLevel`, `StockMovement`
Services:
- `CreateGoodsReceiptService` — processes a Goods Receipt (GRN), updates the Moving Average Cost, and writes stock ledger entries atomically.
- `RecordStockMovementService` — records each stock movement to `stock_movements` and updates the `stock_levels` balance.

Controllers: `GoodsReceiptController`, `SupplierController`

---

## API Endpoints

Base URL: `/api/v1` — all endpoints require authentication (`auth:sanctum`).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/sales` | Create a new sale transaction |
| `POST` | `/shifts/open` | Open a cashier shift |
| `POST` | `/shifts/{id}/movements` | Record a cash movement during a shift |
| `POST` | `/shifts/{id}/close` | Close a cashier shift |
| `GET` | `/suppliers` | List all suppliers |
| `POST` | `/suppliers` | Create a new supplier |
| `GET` | `/inventory/goods-receipts` | List goods receipts |
| `POST` | `/inventory/goods-receipts` | Create a new Goods Receipt (GRN) |

---

## Database Schema

> All monetary columns use `BIGINT` type in **Rupiah** (no decimal). Quantities use `DECIMAL(14,3)` to support bulk/weight-based goods.

---

### Core

#### `tenants`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `name` | string | Tenant name |
| `code` | string UNIQUE | Unique tenant code |
| `domain` | string UNIQUE nullable | Custom domain |
| `is_active` | boolean | |
| `settings` | jsonb nullable | Free-form configuration |

#### `users`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` nullable | |
| `name` | string | |
| `email` | string UNIQUE | |
| `password` | string | |
| `pin_hash` | string nullable | Cashier PIN |
| `is_active` | boolean | |
| `last_login_at` | timestampTz nullable | |

#### `outlets`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `code` | string(50) | Unique per tenant |
| `name` | string | |
| `address` | text nullable | |
| `phone` | string(30) nullable | |
| `npwp` | string(50) nullable | Tax identification number |
| `timezone` | string(50) | Default `Asia/Jakarta` |
| `receipt_header` | text nullable | |
| `receipt_footer` | text nullable | |
| `is_active` | boolean | |

#### `user_outlets` *(pivot)*
| Column | Type | Description |
|--------|------|-------------|
| `user_id` | FK → `users` | Composite PK |
| `outlet_id` | FK → `outlets` | Composite PK |
| `is_default` | boolean | User's default outlet |

#### `outlet_modules`
| Column | Type | Description |
|--------|------|-------------|
| `outlet_id` | FK → `outlets` | Composite PK |
| `module_key` | string(50) | Composite PK, e.g. `cafe`, `koperasi` |
| `is_enabled` | boolean | |
| `config` | jsonb nullable | Module-specific configuration |

#### `settings`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` nullable | null = tenant-level global setting |
| `key` | string(100) | UNIQUE together with `tenant_id` & `outlet_id` |
| `value` | jsonb | |

#### `taxes`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `name` | string(100) | |
| `kind` | string(20) | |
| `rate_percent` | decimal(5,2) | |
| `is_inclusive` | boolean | |
| `is_active` | boolean | |

#### `payment_methods`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `code` | string(50) | UNIQUE per tenant |
| `name` | string(100) | |
| `type` | string(30) | e.g. `cash`, `edc`, `qris` |
| `sort_order` | integer | |
| `is_active` | boolean | |
| `config` | jsonb nullable | |

#### `devices`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `outlet_id` | FK → `outlets` | |
| `name` | string(100) | |
| `last_seen_at` | timestampTz nullable | |
| `last_sync_at` | timestampTz nullable | |

#### `idempotency_keys`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `key` | string(100) UNIQUE | |
| `user_id` | FK → `users` nullable | |
| `endpoint` | string | |
| `response_code` | smallint | |
| `response_body` | jsonb nullable | |

---

### Catalog

#### `categories`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `parent_id` | FK → `categories` nullable | Self-referencing for nested categories |
| `name` | string | |
| `sort_order` | integer | |
| `is_active` | boolean | |

#### `units`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `code` | string(30) | UNIQUE per tenant |
| `name` | string(100) | e.g. `pcs`, `box`, `kg` |

#### `products`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `category_id` | FK → `categories` nullable | |
| `name` | string | |
| `kind` | string(30) | `goods`, `service`, `menu`, `ingredient` |
| `image_path` | string nullable | |
| `track_stock` | boolean | |
| `tax_id` | FK → `taxes` nullable | |
| `description` | text nullable | |
| `attributes` | jsonb nullable | Free-form product attributes |
| `is_active` | boolean | |

#### `product_variants`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `product_id` | FK → `products` | |
| `sku` | string(100) | UNIQUE per tenant |
| `name` | string | |
| `base_unit_id` | FK → `units` | Base unit of measure |
| `cost_price` | bigint | Cost of goods (Rupiah) |
| `sell_price` | bigint | Default selling price (Rupiah) |
| `is_active` | boolean | |

#### `variant_units`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `variant_id` | FK → `product_variants` | |
| `unit_id` | FK → `units` | |
| `factor` | decimal(14,3) | Conversion factor to base unit, e.g. 1 box = 24 pcs |
| `sell_price` | bigint nullable | Override price for this unit |

#### `variant_barcodes`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `variant_id` | FK → `product_variants` | |
| `unit_id` | FK → `units` nullable | |
| `barcode` | string(100) | UNIQUE per tenant |

#### `price_lists`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `name` | string(100) | e.g. Retail, Wholesale, Member |
| `kind` | string(30) | `retail`, `wholesale`, `member` |
| `is_active` | boolean | |

#### `prices`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `price_list_id` | FK → `price_lists` | |
| `variant_id` | FK → `product_variants` | |
| `unit_id` | FK → `units` | |
| `min_qty` | decimal(14,3) | Minimum quantity for this price to apply |
| `price` | bigint | Price (Rupiah) |
| `starts_at` | timestampTz nullable | |
| `ends_at` | timestampTz nullable | |

#### `promotions`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `name` | string | |
| `kind` | string(30) | `percent`, `amount`, `buy_x_get_y` |
| `rules` | jsonb | Promotion rule definition |
| `member_type_id` | bigint nullable | FK to `member_types` (not yet created) |
| `starts_at` | timestampTz nullable | |
| `ends_at` | timestampTz nullable | |
| `is_active` | boolean | |

---

### Sales

#### `sales`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `uuid` | uuid UNIQUE | Client-generated UUID for idempotency & offline sync |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` | |
| `shift_id` | bigint nullable | FK → `shifts` |
| `receipt_no` | string(100) | UNIQUE per outlet |
| `cashier_id` | FK → `users` | |
| `member_id` | bigint nullable | FK to `members` (not yet created) |
| `order_type` | string(30) | `dine_in`, `take_away`, `delivery` |
| `table_id` | bigint nullable | FK to `tables` (Cafe module) |
| `status` | string(30) | `hold`, `completed`, `voided` |
| `subtotal` | bigint | |
| `discount_total` | bigint | |
| `tax_total` | bigint | |
| `service_total` | bigint | |
| `rounding` | bigint | |
| `grand_total` | bigint | |
| `paid_total` | bigint | |
| `change_total` | bigint | |
| `note` | text nullable | |
| `sold_at` | timestampTz | Transaction timestamp on the cashier device |
| `voided_at` | timestampTz nullable | |
| `void_reason` | text nullable | |
| `voided_by` | bigint nullable | FK → `users` |
| `approved_by` | bigint nullable | FK → `users` |

#### `sale_items`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `sale_id` | FK → `sales` | |
| `variant_id` | FK → `product_variants` | |
| `unit_id` | FK → `units` | |
| `name_snapshot` | string | Product name at time of purchase |
| `sku_snapshot` | string(100) | SKU at time of purchase |
| `qty` | decimal(14,3) | |
| `unit_price` | bigint | Unit price at time of transaction |
| `cost_snapshot` | bigint | Cost of goods at time of transaction (for profit calculation) |
| `discount_amount` | bigint | |
| `tax_amount` | bigint | |
| `line_total` | bigint | `(qty × unit_price) − discount + tax` |
| `modifiers` | jsonb nullable | Add-on selections, e.g. sugar level, toppings |
| `note` | text nullable | |
| `kitchen_status` | string(30) | `pending`, `cooking`, `ready`, `served` |

#### `sale_discounts`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `sale_id` | FK → `sales` | |
| `sale_item_id` | FK → `sale_items` nullable | null = transaction-level discount |
| `promotion_id` | bigint nullable | FK → `promotions` |
| `kind` | string(30) | `percent`, `amount` |
| `value` | decimal(10,2) | Discount value (e.g. 10 for 10%, or 5000 for fixed amount) |
| `amount` | bigint | Actual discount deduction in Rupiah |
| `reason` | string nullable | |
| `approved_by` | bigint nullable | FK → `users` |

#### `sale_payments`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `sale_id` | FK → `sales` | |
| `payment_method_id` | FK → `payment_methods` | |
| `amount` | bigint | |
| `reference` | string(100) nullable | EDC / Transfer / QRIS reference number |
| `status` | string(30) | `paid`, `pending`, `failed`, `void` |
| `paid_at` | timestampTz | |
| `gateway_payload` | jsonb nullable | Raw response from payment gateway |

#### `receipt_counters`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `outlet_id` | FK → `outlets` | UNIQUE with `counter_date` |
| `counter_date` | date | |
| `last_number` | bigint | Last issued receipt number for the day |

---

### Cash

#### `registers`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` | |
| `name` | string(100) | e.g. Cashier 1, Front POS |
| `code` | string(50) | UNIQUE per outlet |
| `is_active` | boolean | |

#### `shifts`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `uuid` | uuid UNIQUE | |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` | |
| `register_id` | FK → `registers` | |
| `user_id` | FK → `users` | Cashier who opened the shift |
| `opened_at` | timestampTz | |
| `closed_at` | timestampTz nullable | |
| `opening_cash` | bigint | Opening cash amount |
| `expected_cash` | bigint | System-calculated expected cash (opening + cash-in − cash-out) |
| `counted_cash` | bigint nullable | Physical cash count at shift close |
| `diff_cash` | bigint | Difference between counted and expected cash |
| `total_cash_sales` | bigint | Total cash sales during the shift |
| `total_non_cash_sales` | bigint | Total non-cash sales during the shift |
| `total_cash_in` | bigint | Total cash deposited during the shift |
| `total_cash_out` | bigint | Total cash withdrawn during the shift |
| `status` | string(30) | `open`, `closed`, `reconciled` |
| `note` | text nullable | |
| `close_note` | text nullable | |

#### `cash_movements`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `shift_id` | FK → `shifts` | |
| `type` | string(30) | e.g. `cash_in`, `cash_out` |
| `amount` | bigint | |
| `category` | string(50) nullable | |
| `reason` | text | |
| `sale_id` | FK → `sales` nullable | Linked sale if movement is from a transaction |
| `user_id` | FK → `users` | |

---

### Inventory

#### `suppliers`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `code` | string(50) | UNIQUE per tenant |
| `name` | string(100) | |
| `phone` | string(30) nullable | |
| `address` | text nullable | |
| `is_active` | boolean | |

#### `goods_receipts` *(GRN — Goods Receipt Note)*
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` | |
| `supplier_id` | FK → `suppliers` nullable | |
| `receipt_no` | string(50) | Auto-generated: `GRN-YYYYMMDD-NNNNN`, UNIQUE per outlet |
| `supplier_invoice_no` | string(100) nullable | |
| `status` | string(20) | `completed` |
| `total_cost` | bigint | |
| `note` | text nullable | |
| `received_at` | timestampTz | |
| `user_id` | FK → `users` | |

#### `goods_receipt_items`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `goods_receipt_id` | FK → `goods_receipts` | |
| `variant_id` | FK → `product_variants` | |
| `unit_id` | FK → `units` | Receiving unit (may differ from base unit) |
| `qty` | decimal(14,3) | Quantity in receiving unit |
| `conversion_factor` | decimal(14,3) | Conversion factor to base unit |
| `base_qty` | decimal(14,3) | Quantity in base unit (`qty × conversion_factor`) |
| `unit_cost` | bigint | Cost per receiving unit |
| `base_unit_cost` | bigint | Cost per base unit (`unit_cost ÷ factor`) |
| `line_total` | bigint | `qty × unit_cost` |
| `batch_no` | string nullable | |
| `expired_date` | date nullable | |

#### `goods_receipt_counters`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `outlet_id` | FK → `outlets` | UNIQUE with `counter_date` |
| `counter_date` | date | |
| `last_number` | bigint | Last issued GRN number for the day |

#### `stock_levels`
| Column | Type | Description |
|--------|------|-------------|
| `outlet_id` | FK → `outlets` | Composite PK |
| `variant_id` | FK → `product_variants` | Composite PK |
| `qty` | decimal(14,3) | Current stock quantity (base unit) |
| `reorder_point` | decimal(14,3) | Minimum stock threshold for reorder alert |

#### `stock_movements` *(Ledger)*
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `outlet_id` | FK → `outlets` | |
| `variant_id` | FK → `product_variants` | |
| `type` | string(30) | e.g. `receipt`, `sale`, `opname`, `adjustment` |
| `qty_change` | decimal(14,3) | Positive = in, Negative = out |
| `qty_after` | decimal(14,3) | Stock balance after the movement |
| `unit_cost` | bigint | Cost of goods (COGS) per base unit at time of movement |
| `ref_type` | string(50) nullable | Source document type (e.g. `goods_receipts`) |
| `ref_id` | bigint nullable | Source document ID |
| `batch_id` | bigint nullable | |
| `note` | text nullable | |
| `user_id` | FK → `users` | |
| `created_at` | timestampTz | |

#### `stock_opname_counters`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `outlet_id` | FK → `outlets` | UNIQUE with `counter_date` |
| `counter_date` | date | |
| `last_number` | bigint | Last issued opname number for the day |

#### `stock_opnames`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `tenant_id` | FK → `tenants` | |
| `outlet_id` | FK → `outlets` | |
| `opname_no` | string(50) | UNIQUE per outlet |
| `status` | string(20) | `draft`, `completed` |
| `note` | text nullable | |
| `opname_at` | timestampTz | Timestamp of the physical count |
| `user_id` | FK → `users` | |

#### `stock_opname_items`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `stock_opname_id` | FK → `stock_opnames` | |
| `variant_id` | FK → `product_variants` | |
| `system_qty` | decimal(14,3) | Stock quantity according to system at time of opname |
| `physical_qty` | decimal(14,3) | Actual physical count |
| `diff_qty` | decimal(14,3) | Difference (`physical_qty − system_qty`) |
| `unit_cost` | bigint | Cost of goods (COGS) per base unit at time of opname (for variance valuation) |
| `reason` | string(255) nullable | Explanation for discrepancy |

---

### Entity Relationship (Summary ERD)

```
tenants
  ├── outlets (tenant_id)
  │     ├── user_outlets (outlet_id) ←─ users (user_id)
  │     ├── outlet_modules (outlet_id)
  │     ├── devices (outlet_id)
  │     ├── settings (outlet_id)
  │     ├── registers (outlet_id)
  │     │     └── shifts (register_id)
  │     │           └── cash_movements (shift_id)
  │     ├── sales (outlet_id)
  │     │     ├── sale_items (sale_id)
  │     │     │     └── sale_discounts (sale_item_id)
  │     │     ├── sale_discounts (sale_id)
  │     │     └── sale_payments (sale_id)
  │     ├── receipt_counters (outlet_id)
  │     ├── goods_receipts (outlet_id)
  │     │     ├── goods_receipt_items (goods_receipt_id)
  │     │     └── goods_receipt_counters (outlet_id)
  │     ├── stock_levels (outlet_id)
  │     ├── stock_movements (outlet_id)
  │     ├── stock_opnames (outlet_id)
  │     │     ├── stock_opname_items (stock_opname_id)
  │     │     └── stock_opname_counters (outlet_id)
  ├── users (tenant_id)
  ├── settings (tenant_id)
  ├── taxes (tenant_id)
  ├── payment_methods (tenant_id)
  ├── categories (tenant_id, parent_id → self)
  ├── units (tenant_id)
  ├── price_lists (tenant_id)
  ├── promotions (tenant_id)
  ├── suppliers (tenant_id)
  └── products (tenant_id)
        └── product_variants (product_id)
              ├── variant_units (variant_id)
              ├── variant_barcodes (variant_id)
              └── prices (variant_id)
```


## License

This is a private project.
