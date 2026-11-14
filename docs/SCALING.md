# Scaling and operations

Status key: **BUILT+TESTED** has automated tests that were executed. **CONFIG ONLY** is prepared but could not be
verified on a development laptop. **INFRA** is infrastructure you provision; no code in this repo can do it.

Nothing here has been load-tested. Treat all capacity statements as design intent until a load test proves them.

## What is built and tested

| Concern | Status | Where |
|---|---|---|
| Hot-row contention on system ledger accounts | BUILT+TESTED | `config/ledger.php`, `LedgerAccount::system/systemFor`, `ledger:ensure-shards`. Escrow holding, platform revenue and gateway clearing are split into `LEDGER_SHARDS` (default 8) accounts. Each escrow stores its shard at creation, so raising the count never remaps held funds. |
| Webhook doing network work inline | BUILT+TESTED | `PaystackWebhookController` verifies the HMAC and enqueues `ProcessPaystackCharge`; the Paystack verify call and ledger credit run in the queue with 8 tries and exponential backoff, unique per reference. |
| Rate limiting | BUILT+TESTED | `config/limits.php`, named limiters `webhooks`, `money`, `web` in `AppServiceProvider`. Webhook limit is tested; tune the numbers per environment. |
| Request tracing | BUILT+TESTED | `AssignRequestId` adds `X-Request-Id` to every response and log line. |
| Health endpoint | BUILT+TESTED | `GET /health` reports ok/fail for database, cache and failed-job backlog. |
| Ledger integrity audit | BUILT+TESTED | `php artisan ledger:audit` (hourly in the scheduler). Checks debits = credits, cached balances = entries, escrow holdings = open escrows. Exits non-zero and logs CRITICAL on any mismatch. Alert on that. |
| Auto-release of delivered escrows | BUILT | `escrow:auto-release` every 5 minutes. |

Run `php artisan schedule:work` (dev) or a cron entry `* * * * * php artisan schedule:run` (prod), and a queue worker:
`php artisan queue:work --tries=8 --max-time=3600`.

## What you must provision (not verified here)

### Redis for sessions, cache and queue: CONFIG ONLY
This machine has no Redis server and no `redis` PHP extension, so none of this has been run.
1. Install the `phpredis` extension (or `composer require predis/predis`).
2. Set in the production `.env`:
   `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`,
   `REDIS_HOST=...`, `REDIS_PASSWORD=...`.
3. Run workers under a supervisor (systemd or Supervisor). Use a separate Redis database or instance for the queue
   if you want to evict cache without losing jobs.
The database drivers stay the default for local development.

### MySQL primary plus replicas: INFRA
- Use a managed MySQL with a replica and automatic failover.
- Laravel supports read/write splitting in `config/database.php` (`read`/`write` host arrays, `sticky => true`).
  Add it only once a replica exists. Never read balances for a money decision from a replica: the ledger and escrow
  services must keep using the primary, because replica lag would let a stale balance pass a check.
- Put a connection pooler (for example ProxySQL) in front of MySQL when app servers multiply.

### Object storage and CDN for listing images: INFRA + one package
- `composer require league/flysystem-aws-s3-v3`, set `FILESYSTEM_DISK=s3`, and serve through a CDN.
- Store only the object key in `listings.images`; generate resized variants on upload.

### Search: INFRA + one package (not built yet)
- The marketplace UI is not built, so search is not either. Plan: Laravel Scout with Meilisearch, indexing
  active listings; MySQL full-text as a fallback.

### App tier: INFRA
- Run stateless app instances behind a load balancer. Sessions and cache must be in Redis first.
- Octane (FrankenPHP or RoadRunner) is optional and is not installed; Swoole is not supported on Windows, so
  benchmark on the Linux target, not on this laptop.
- Terminate TLS at the load balancer, set `TRUSTED_PROXIES`, and put a WAF in front of `/webhooks/*`,
  `/login` and `/register`.

## Operating checklist before real money
1. Rotate the Paystack keys that are exposed in the old Flutter app and `functions/main.py`; enter the new secret key
   in the admin settings (stored encrypted), never in `.env` or source.
2. Alert on: `ledger:audit` failing, `/health` returning 503, failed-job count rising, queue age, 5xx rate, and webhook
   401 spikes.
3. Load test the deposit and escrow paths against a production-sized database before launch.
4. Back up MySQL with point-in-time recovery and test a restore. The ledger is the source of truth for money.
