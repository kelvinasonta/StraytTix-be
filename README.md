# Strayt Tix - Backend API

A high-performance, modular backend engine powering event ticketing, organizer management, payment processing, fraud-resilient scan check-ins, and multi-tenant event operations. Built with modern Laravel 13, modular DDD architecture, and Pest testing.

---

## Architecture & Modules

The platform is designed with a strict modular structure (`Modules/`) using `nwidart/laravel-modules`:

- **Auth**: Sanctum-powered authentication, email verification, password resets, rate limiting, and session security.
- **Admin**: Platform oversight, feature flags (Pennant), verification reviews, and administrative audit trails.
- **Organizer**: Organizer workspaces, multi-role team management, bank accounts, and payout settings.
- **Events**: Event lifecycle (draft, published, cancelled), scheduling, lineups, custom questions, discovery scoring, and geo-search.
- **Tickets**: Ticket tiers, capacities, purchase reservations, barcode generation, attendee check-ins, and CSV exports.
- **Payments**: Multi-gateway payment orchestration (Stripe, Paystack, Flutterwave) and automated order reconciliation.
- **Notification**: Transactional email templates, mailer integration, and queue-driven alert dispatches.
- **Audit**: Cryptographically auditable activity logging for organizers, team members, and attendees.
- **Shared**: App settings cache engine, high-throughput image sanitization/upload engine with S3/MinIO storage.

---

## Tech Stack & Infrastructure

- **PHP**: 8.3 / 8.5
- **Framework**: Laravel 13
- **Database**: MySQL 8.0
- **Cache & Queues**: Redis
- **Storage**: AWS S3 & MinIO (S3-compatible local/staging storage)
- **Containerization**: Docker & Docker Compose (Nginx, PHP-FPM, MySQL, Redis, MinIO, Mailpit, phpMyAdmin)
- **Testing**: Pest PHP v4 (170+ feature & unit tests)
- **Code Standards**: Laravel Pint

---

## Getting Started

### 1. Prerequisites
- Docker & Docker Compose
- Composer & PHP 8.3+

### 2. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Ensure your `.env` contains your database and S3/MinIO storage keys:
```env
APP_NAME="Strayt Tix"
APP_URL=http://localhost:8080

FILESYSTEM_DISK=s3
AWS_BUCKET=strayttix
AWS_ENDPOINT=http://minio:9000
AWS_URL=http://localhost:9000/strayttix
```

### 3. Start Local Environment (Docker)
```bash
docker compose up -d
```
Services will be accessible at:
- **API (Nginx)**: [http://localhost:8080](http://localhost:8080)
- **MinIO S3 Console**: [http://localhost:9001](http://localhost:9001) (`minioadmin` / `minioadmin`)
- **Mailpit**: [http://localhost:8025](http://localhost:8025)
- **phpMyAdmin**: [http://localhost:8081](http://localhost:8081)

### 4. Database Migrations & Seeds
```bash
docker compose exec app php artisan migrate
```

---

## Testing & Quality

Run the comprehensive test suite with Pest:
```bash
php artisan test --compact
```

Run code styling fixes with Laravel Pint:
```bash
vendor/bin/pint --format agent
```

---

## Git Workflow & Branching Strategy

- **`main`**: Production-only branch. Direct commits and direct PRs from feature branches to `main` are strictly prohibited.
- **`staging`**: Integration and pre-release branch. All feature branches and PRs must target `staging`.
- **Releases**: PRs into `main` are only permitted from `staging` after quality checks and staging testing pass.

---

## License

Proprietary. All rights reserved by Strayt Tix.
