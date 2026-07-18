# Prestige Financial Concierge — Production Deployment

Live domain: **https://prestigecreditconconciergeservices.com**
Stack: Laravel 10 · PHP 8.1+ (built/tested on 8.3) · MySQL 8 · Authorize.Net (Accept.js + webhooks)

---

## 1. Server requirements
- PHP **8.1+** with extensions: `openssl, pdo_mysql, mbstring, tokenizer, xml, ctype, json, fileinfo, curl, bcmath`
- Composer 2
- MySQL 8 (or MariaDB 10.4+)
- A web server (nginx or Apache) with the **document root pointed at `public/`** — never the project root
- Valid **SSL certificate** (Let's Encrypt / Cloudflare). The whole app must be served over HTTPS.

## 2. Upload the code
Upload the entire project EXCEPT `vendor/`, `node_modules/`, and `.env` (those are environment-specific).
Then on the server:
```
composer install --no-dev --optimize-autoloader
```

## 3. Create the `.env`
Copy `.env.example` → `.env` and fill it in. The app key is already generated; if you want a fresh one:
```
php artisan key:generate
```
Set the database (the only values still marked `CHANGE_ME` in the shipped `.env`):
```
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```
Everything else (domain, Authorize.Net live keys, admin login, HTTPS session, webhook enforcement) is already set for production.

## 4. Database
Create the database, then:
```
php artisan migrate --force
```

## 5. Optimize for production
```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
> Re-run these any time you change `.env` or code. To undo: `php artisan optimize:clear`.

## 6. Permissions
The web-server user must be able to write to:
```
storage/        (and all subfolders)
bootstrap/cache/
```
e.g. `chown -R www-data:www-data storage bootstrap/cache`

## 7. Web-server document root
- **nginx**: `root /path/to/project/public;` with the standard Laravel `try_files $uri $uri/ /index.php?$query_string;`
- **Apache**: point the vhost `DocumentRoot` at `public/`; the included `public/.htaccess` handles the rest.
- Force HTTP → HTTPS at the web-server / Cloudflare level.

## 8. Authorize.Net webhook
In the Authorize.Net Merchant Interface → **Account → Webhooks**, the endpoint should be:
```
https://prestigecreditconconciergeservices.com/webhooks/authorize-net
```
(The app also accepts the webhook at the site root `/` as a fallback, which is how it's currently registered — either works.)
Subscribe to the payment, subscription and customer events you care about. Signature verification is **enforced** (`AUTHNET_WEBHOOK_ENFORCE_SIGNATURE=true`).

## 9. Smoke test after go-live
- Visit the site over HTTPS — every page should load, popup appears after 3s.
- `/admin/login` → sign in with the admin credentials in `.env`.
- Run ONE real low-risk card through `/checkout?plan=standard` (live keys = real charge) → confirm it appears under **Payments** and **Paid Credit Repair Clients**, then refund it from the Authorize.Net dashboard if it was only a test.
- Trigger a webhook (or wait for the test charge's webhook) → confirm it shows under **Webhooks** with a valid signature badge.

---

## Security posture (already configured)
- `APP_DEBUG=false`, `APP_ENV=production` — no stack traces leak.
- HTTPS forced for all generated URLs; HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` headers on every response.
- Secure, HTTP-only, SameSite session cookies scoped to the domain.
- Trusted proxies enabled so HTTPS/real IP are detected behind Cloudflare/nginx.
- **Card data is never stored or transmitted to the server** — Accept.js tokenizes in the browser; only an opaque one-time token reaches the backend. Card number/CVV/expiry never touch our database.
- Webhook signatures verified with HMAC-SHA512; forged events rejected.
- PCI `CardRedactor` masks any card-like data in dashboard payload viewers.
- Rate limiting: admin login `8/min`, checkout charge `10/min`, API `60/min`.
- Server-side price authority — the client cannot change the plan amount.

## Rollback notes
- If legitimate Authorize.Net webhooks ever show as **invalid** signature in the Webhooks page, set `AUTHNET_WEBHOOK_ENFORCE_SIGNATURE=false`, run `php artisan config:cache`, and check `storage/logs`. The initial charge is recorded directly at checkout, so payment data is never lost even if a webhook is rejected.
