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

| Component   | Choice          |
|-------------|-----------------|
| Language    | PHP 8.3+        |
| Framework   | Laravel 13      |
| Database    | PostgreSQL 16   |
| Cache/Queue | Redis           |
| Web Server  | Nginx (Alpine)  |

### Infrastructure

| Component       | Choice          |
|-----------------|-----------------|
| Containerization| Docker Compose  |
| Environments    | Local, Staging, Production |

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

2. Copy the environment file

   ```bash
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

### Ports

| Service    | Port   |
|------------|--------|
| Web (Nginx)| `8000` |
| PostgreSQL | `5432` |
| Redis      | `6379` |

---

## Repository Structure

```
Neraca/
├── backend/           # Laravel application
├── docker/            # Docker configuration (Nginx, etc.)
├── docker-compose.yml
├── .gitignore
└── README.md
```

---

## License

This is a private project.
