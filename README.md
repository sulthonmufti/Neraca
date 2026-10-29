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
## Modules

- **Catalog**: Provides product catalog management with models `VariantUnit`, `VariantBarcode`, `PriceList`, `Price`, and `Promotion`. All models are tenant‑aware via the `BelongsToTenant` trait.
- **Sales**: Handles transaction processing. Includes migrations for `sales`, `sale_items`, `sale_discounts`, `sale_payments`, and `receipt_counters`.
- **Inventory**, **Cash**, **Member**, **Cafe**, **Canteen**, **Koperasi**: Placeholder modules for future extensions.

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
| `shift_id` | bigint nullable | FK to `shifts` (not yet created) |
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

### Entity Relationship (Summary ERD)

```
tenants
  ├── outlets (tenant_id)
  │     ├── user_outlets (outlet_id) ←─ users (user_id)
  │     ├── outlet_modules (outlet_id)
  │     ├── devices (outlet_id)
  │     ├── settings (outlet_id)
  │     ├── sales (outlet_id)
  │     │     ├── sale_items (sale_id)
  │     │     │     └── sale_discounts (sale_item_id)
  │     │     ├── sale_discounts (sale_id)
  │     │     └── sale_payments (sale_id)
  │     └── receipt_counters (outlet_id)
  ├── users (tenant_id)
  ├── settings (tenant_id)
  ├── taxes (tenant_id)
  ├── payment_methods (tenant_id)
  ├── categories (tenant_id, parent_id → self)
  ├── units (tenant_id)
  ├── price_lists (tenant_id)
  ├── promotions (tenant_id)
  └── products (tenant_id)
        └── product_variants (product_id)
              ├── variant_units (variant_id)
              ├── variant_barcodes (variant_id)
              └── prices (variant_id)
```


## License

This is a private project.
