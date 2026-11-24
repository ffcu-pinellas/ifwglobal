# IFW Global — Render + TiDB Cloud Deployment & Shared-Hosting Portability Guide

This guide provides the exact field-by-field values to complete the **Render "New Web Service"** setup for `ifwglobal` using Docker + TiDB Cloud Serverless, while preserving zero-code-change portability back to Hostinger / cPanel shared hosting.

---

## 1. Why the Previous Render Form Showed `$ yarn start` Validation Errors

In the Render **New Web Service** form screenshots, **Language** was set to `Node`, which caused Render to auto-fill:
- **Build Command:** `$ yarn`
- **Start Command:** `$ yarn start` *(highlighted with a red validation error because `ifwglobal` is a PHP/Apache application with no `package.json`)*

Render does not include a built-in native PHP runtime. Switching **Language** to **`Docker`** uses the repository's `Dockerfile` (`php:8.2-apache` + `mod_rewrite` + `ca-certificates` + `docker-entrypoint.sh`) and replaces the Node Build/Start commands automatically.

---

## 2. Pre-Deploy Steps (Required Before Clicking "Deploy web service")

### A. Commit & Push the Updated Repository to `origin/main`
Once you have reviewed the local changes in `c:\Users\USER\Downloads\ifwglobal`, commit and push to `https://github.com/ffcu-pinellas/ifwglobal.git` (`main`):
```bash
git add -A
git status   # Verify .env and *.sql are NOT staged
git commit -m "feat: add Render Docker + TiDB Cloud TLS support and shared-hosting portability"
git push origin main
```

### B. TiDB Cloud Network / IP Access List (Critical!)
Your TiDB Cloud connection modal showed:
> `Your current IP address (145.223.7.37) is allowed to connect.`

Render Free Tier containers use dynamic outbound IP addresses in `Ohio (us-east-2)` / AWS `us-east-1`. If TiDB Cloud only allows `145.223.7.37`, Render will be blocked by TiDB's firewall.
1. Open **TiDB Cloud Console** -> Cluster **`ifwGlobal`** -> **Settings / Networking / IP Access List**.
2. Add **`0.0.0.0/0`** (`Allow Access from Anywhere`) — connections remain protected by strong credentials and mandatory verified TLS (`isrgrootx1.pem`).

---

## 3. Exact Field-by-Field Values for Render "New Web Service"

Open **https://dashboard.render.com/web/new** and select repository **`ffcu-pinellas/ifwglobal`**:

| Render Form Field | Exact Value to Enter | Notes |
|---|---|---|
| **Name** | `ifwglobal` *(or your preferred service name)* | Determines your `.onrender.com` subdomain |
| **Language** | **`Docker`** | Fixes the `$ yarn start` validation error |
| **Branch** | `main` | Matches GitHub default branch |
| **Region** | `Ohio (US East)` | Matches your existing 2 services and sits adjacent to TiDB `aws.us-east-1` |
| **Root Directory** | *(leave blank)* | `Dockerfile` is at repo root |
| **Dockerfile Path** | `./Dockerfile` | Auto-filled when **Language = Docker** |
| **Docker Build Context Directory** | *(leave blank / `.`)* | Default |
| **Docker Command** | *(leave blank)* | Handled by `ENTRYPOINT` + `CMD` in `Dockerfile` |
| **Instance Type** | `Free ($0 / month)` | `0.1 CPU · 512 MB RAM` |

*(Alternatively, you can deploy via **New + -> Blueprint** using the committed `render.yaml` at repo root.)*

---

## 4. Environment Variables (Render Dashboard -> Environment Variables)

Click **+ Add Environment Variable** (or **Add from .env**) and enter these exact keys and values:

| `KEY` | `VALUE` | Notes |
|---|---|---|
| `APP_NAME` | `IFW Global Recovery` | Used in email templates and portal branding |
| `APP_URL` | `https://ifwglobal.onrender.com` | Replace `ifwglobal` if you chose a different Render service name |
| `DB_HOST` | `gateway01.us-east-1.prod.aws.tidbcloud.com` | TiDB Cloud Serverless endpoint |
| `DB_PORT` | `4000` | TiDB TLS port |
| `DB_NAME` | `ifwglobal` | Dedicated application database (do **not** use `sys`) |
| `DB_USER` | `3khU18GFDCdjg3j.root` | TiDB cluster user |
| `DB_PASS` | `<your-tidb-password>` | Copy from TiDB Cloud **Connect** modal or `.env` (never commit plaintext) |
| `DB_SSL` | `true` | Enables PDO TLS connection |
| `DB_SSL_CA` | `/var/www/html/isrgrootx1.pem` | Committed ISRG Root X1 CA cert (or `/etc/secrets/isrgrootx1.pem` if uploaded as Secret File) |
| `DB_SSL_VERIFY` | `true` | Verifies TiDB server TLS certificate |
| `MAIL_HOST` | `smtp.hostinger.com` *(or your SMTP relay host)* | Primary SMTP server (5s timeout) |
| `MAIL_PORT` | `465` *(or `587` for STARTTLS)* | Port `465` uses SSL/SMTPS; `587` uses STARTTLS |
| `MAIL_USERNAME` | `<your-smtp-username>` | e.g. `notifications@ifwglobalrecovery.site` |
| `MAIL_PASSWORD` | `<your-smtp-password>` | Your mailbox / SMTP key |
| `MAIL_ENCRYPTION` | `ssl` *(or `tls` for port `587`)* | |
| `MAIL_FROM_ADDRESS` | `notifications@ifwglobalrecovery.site` | |
| `MAIL_FROM_NAME` | `IFW Global Recovery` | |
| `RESEND_API_KEY` | *(optional — recommended on Render Free Tier)* | HTTPS fallback via Resend API if Render blocks/times out outbound SMTP |
| `BREVO_API_KEY` | *(optional — alternative HTTPS fallback)* | HTTPS fallback via Brevo API if `MAIL_HOST` fails or is omitted |
| `TELEGRAM_BOT_TOKEN` | `<your-bot-token>` | Optional if already stored in DB `IFW_site_settings` |
| `TELEGRAM_CHAT_ID` | `<your-chat-id>` | Optional if already stored in DB `IFW_site_settings` |
| `CRON_SECRET` | Click **Generate** *(or enter a 32+ char random string)* | Required to trigger `cron_*.php` over HTTPS |

---

## 5. Advanced -> Secret Files

- **`isrgrootx1.pem`** is already committed in the repository at `/var/www/html/isrgrootx1.pem`, and `ca-certificates` is installed in the Docker image, so **no Secret File is strictly required**.
- If you prefer supplying the CA or `.env` via **Advanced -> Secret Files**:
  - **Filename:** `isrgrootx1.pem` (accessible at `/etc/secrets/isrgrootx1.pem`, which `config.php` auto-detects).
  - Or **Filename:** `.env` (Render exposes it at `/etc/secrets/.env` and `/var/www/html/.env`).

---

## 6. Ephemeral Storage Caveat (`uploads/` & `admin/backups/`)

On Render Free Tier (and Starter without a Persistent Disk), container filesystems are **ephemeral**:
- Files uploaded at runtime to `uploads/vault/`, `uploads/kyc/`, `uploads/payment_proofs/`, `uploads/chat/`, `uploads/documents/`, `uploads/avatars/`, or `admin/backups/` will **disappear whenever the service restarts, sleeps, or redeploys**.
- **For persistent uploads on Render:** upgrade to a paid plan and attach a **Render Disk** mounted at `/var/www/html/uploads`, or migrate file uploads to S3/R2 object storage later.
- Note: Historical files referenced in the Namecheap backup (`uploads/kyc/...`, `uploads/vault/...`) were not included in the Git repository and remain on the expired Namecheap host unless restored from a file backup.

---

## 7. Scheduled Cron Jobs on Render

Because Render Free Web Services sleep when idle and do not run system `crontab`, schedule an external cron service (e.g., **cron-job.org**, **EasyCron**, or a Render Cron Job) to call the secured HTTP endpoints with your `CRON_SECRET`:

- **Invoice & Late Fee Reminders (every 4 hours):**
  `https://ifwglobal.onrender.com/cron_invoice_reminders.php?token=<CRON_SECRET>`
- **Unread Chat Email Reminders (every 15 minutes):**
  `https://ifwglobal.onrender.com/cron_chat_unread_reminders.php?token=<CRON_SECRET>`
- **Chat SLA Monitor (every 10–15 minutes):**
  `https://ifwglobal.onrender.com/cron_sla_monitor.php?token=<CRON_SECRET>`
- **Weekly SQL Backup (CLI or HTTP, ephemeral unless persistent disk):**
  `https://ifwglobal.onrender.com/cron_backup.php?token=<CRON_SECRET>`

---

## 8. Moving Back to Hostinger / cPanel Shared Hosting (Zero Code Changes)

When moving to Hostinger or any standard cPanel Apache/LiteSpeed host:
1. Upload the repository files to `public_html/`.
2. Import the database into cPanel MySQL/MariaDB.
3. Copy `.env.example` to `.env` in `public_html/.env` and set:
   ```ini
   APP_URL=https://yourdomain.com
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=u_cpanel_db
   DB_USER=u_cpanel_user
   DB_PASS=your_cpanel_password
   DB_SSL=false
   ```
4. No PHP code edits are needed — `config.php` prioritizes `.env`, disables TiDB TLS on `127.0.0.1:3306` when `DB_SSL=false`, and `.htaccess` handles Apache/LiteSpeed routing and security out of the box.
