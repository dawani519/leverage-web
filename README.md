# Leverage Web

The Laravel rebuild of Leverage, a peer-to-peer marketplace with escrow and a Paystack wallet for Nigeria (NGN). Laravel and MySQL are the source of truth; the original Flutter and Firebase app is meant to move onto an API later.

## Stack

- Laravel 13, PHP 8.3+, Livewire 4, Flux (free), Fortify, MySQL
- Installable PWA with a conservative service worker that never caches pages

## How the money works

- **Double-entry ledger:** `LedgerService::post` writes an append-only ledger in integer kobo, with idempotency keys and locked accounts. Balances move only through this service.
- **Escrow:** `EscrowService` is a state machine: pending payment, funded, delivered, disputed, released, refunded, cancelled, with admin dispute resolution. System ledger accounts are sharded (`config/ledger.php`, `ledger:ensure-shards`), and each escrow records its shard.
- **Paystack:** the webhook only verifies the HMAC signature and queues `ProcessPaystackCharge`.
- **Audit:** `ledger:audit` runs hourly.
- **Settings:** the 2% fee and 7-day auto-release are admin settings (`SettingsService`). Secrets are encrypted, and the Paystack secret is write-only.

## Features

- Public marketplace, selling, wallet, transactions and chat
- Broker applications
- Admin area at `/admin` (non-admins get a 404)
- PWA (manifest, offline-safe service worker, icons)

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate   # then set the DB_* values
php artisan migrate --seed
php artisan ledger:ensure-shards
npm install && npm run build
php artisan serve
```

Add your Paystack keys in the admin settings, not in the repository.

## Documentation

[`docs/SCALING.md`](docs/SCALING.md) covers the planned infrastructure (Redis, read replicas, S3/CDN, search, load balancing, WAF) for large concurrent load. None of it is provisioned yet.

## Testing

```bash
php artisan test
php artisan test --filter=EscrowTest   # run targeted tests; the full suite is slow
```

Tests use a separate MySQL database `leverage_web_testing`. The suite has 185 tests; it has not been re-run as a single batch since the last fixes.

## Open items

- A Sanctum API layer, then the Flutter upgrade
- Withdrawals are not built; payout rules are an unresolved business decision
- Broker permissions are undefined
- No content security policy yet, no load test, and Redis configuration is unverified
- Browser testing has not been done
