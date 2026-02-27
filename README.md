# HWS — Hourly Bill Tracking System

Internal billing platform for managing hourly client work across multiple employees. Scans employee Google Sheets for billable time entries, generates Stripe draft invoices, tracks payment status, and updates sheet rows when marked as billed.

**Stack:** PHP 8.2+ / Laravel 11 / MySQL / Blade + Tailwind CSS + Alpine.js  
**Domain:** `billing.hexawebsystems.com`  
**Server:** WHM/cPanel VPS with EasyApache4  
**Repo:** `hws-hourly-bill-tracking-system`

---

## Table of Contents

- [System Overview](#system-overview)
- [Billing Workflow](#billing-workflow)
- [Modules](#modules)
- [File Structure](#file-structure)
- [Database Schema](#database-schema)
- [Google Sheet Setup](#google-sheet-setup)
- [API Integrations](#api-integrations)
- [Email Templates & Shortcodes](#email-templates--shortcodes)
- [Installation Guide](#installation-guide)
- [Coding Standards](#coding-standards)
- [Key Design Decisions](#key-design-decisions)
- [Troubleshooting](#troubleshooting)

---

## System Overview

HWS is a centralized billing dashboard for a web development agency. Each employee logs their hours in a personal Google Sheet. The system:

1. **Scans** employee Google Sheets for new billable rows
2. **Validates** data (client name match, numeric time, non-empty fields)
3. **Groups** valid rows by client
4. **Creates** Stripe draft invoices per client
5. **Tracks** invoice lifecycle (draft → sent → paid)
6. **Writes back** to Google Sheets when rows are marked as billed (updates status + applies light red background)
7. **Sends** invoice notification emails via Brevo SMTP with customizable templates
8. **Manages** prepaid credit balances for retainer clients with low-balance alerts

Single admin authentication, built with multi-user structure (role field on users table) for future expansion.

---

## Billing Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                        BILLING CYCLE                            │
│                                                                 │
│  1. Employee logs hours in their Google Sheet                   │
│     ↓                                                           │
│  2. Admin clicks "Run Scan" in HWS                              │
│     → Reads all active employee sheets                          │
│     → Filters rows where primary_key > scan cursor              │
│     → Collects rows where billed_status = "pending"             │
│     → Validates client names against local database             │
│     → Groups valid rows by client                               │
│     ↓                                                           │
│  3. Admin reviews scan results                                  │
│     → Sees rows grouped by client with totals                   │
│     → Sees any validation errors (unmatched clients, etc.)      │
│     ↓                                                           │
│  4. Admin clicks "Create Invoices"                              │
│     → Creates Stripe draft invoice per client                   │
│     → Stores invoice + line items locally                       │
│     → Deducts hours from credit balance (hourly_credits only)   │
│     ↓                                                           │
│  5. Admin reviews draft invoices in Invoices module             │
│     → View line items                                           │
│     → Mark as Billed (writes "billed" to sheet, red highlight)  │
│     → Finalize & Send via Stripe                                │
│     → Or send custom email via Brevo using templates            │
│     ↓                                                           │
│  6. Admin periodically refreshes payment status                 │
│     → Polls Stripe for each unpaid invoice                      │
│     → Updates local status when paid                            │
│     → Stores payment details (method, date, amount)             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Modules

### 1. Dashboard
Overview page showing:
- Invoice summary cards (draft/sent/paid counts and totals)
- Low-credit client flags with alert-sent indicator
- Active employee list with scan cursor positions
- Recent scan activity log (last 10 scans)
- System health (PHP version, last scan time, active counts)

### 2. Clients
- **Import** from Stripe using comma-separated Customer IDs (`cus_xxxxx`)
- Debug panel shows success/skip/error for each import attempt
- Local fields: hourly rate (default $100), billing type (from Lists module), credit balance
- Credit adjustment form: add or deduct hours with optional note
- Low-credit threshold flag at configurable hour limit (default: 4 hours)
- Active/inactive toggle — inactive clients are excluded from billing scans

### 3. Employees
- Each employee has a Google Sheet ID (accepts full URL or plain ID)
- Sheet access validated on create/update (checks service account can read/write)
- Extracts sheet ID from URL automatically
- Tracks two cursors per employee:
  - `scan_start_primary_key` — where the next scan begins
  - `last_billing_primary_key` — where the previous billing ended (for rollback)
- Recent scan history displayed on edit page

### 4. Billing Scan
- Reads all active employee sheets in one operation
- Filters rows where `primary_key > scan_start_primary_key`
- Only collects rows where `billed_status = "pending"`
- Validates:
  - Client name is non-empty
  - Client name exactly matches a client in the system
  - Time value is numeric and positive
  - Date is non-empty
- Groups valid rows by client for review
- Detailed error log per row (type, row number, message)
- Stores grouped results in session for the "Create Invoices" step

### 5. Invoices
- Created as Stripe draft invoices with a single line item per client
- Line item description: `"Company Name — X.XX hours @ $Y.YY/hr"`
- Stores per-employee primary key ranges in JSON for traceability
- Actions per invoice:
  - **View line items** — organized by employee with subtotals
  - **Mark as Billed** — writes "billed" to sheet rows, applies red background, advances scan cursors
  - **Reverse Billing** — resets sheet rows to "pending", rolls back cursors, restores credit balance
  - **Finalize & Send via Stripe** — finalizes draft and triggers Stripe's email delivery
  - **Send Email** — compose email using templates with shortcode substitution
  - **Refresh Payment Status** — polls Stripe for current status and payment details
  - **Refresh All Unpaid** — bulk refresh for all outstanding invoices

### 6. Email Templates
- Multiple templates per use case (e.g., `invoice_notification`, `low_credit_alert`, `custom`)
- One template per use case can be marked as primary (default selection)
- All fields support shortcodes: from name, from email, reply-to, CC, subject, body
- Preview/test functionality — sends test email with sample data
- HTML body editor (textarea with monospace font)

### 7. Lists
- Generic, extensible key-value lookup system for dropdowns
- Default list: `customer_billing_type` with values: `hourly_open`, `hourly_credits`, `fixed`
- Admin can add new list keys and values via UI
- Items can be toggled active/inactive or deleted
- Used by the Clients module for billing type dropdown

### 8. Settings
- Runtime-editable settings grouped by category (email, google, system)
- Settings stored in database with key/value/group/type/label structure
- Input types: text, textarea, password, boolean
- SMTP test email button
- Server information panel (PHP version, OS, disk space, uptime, Laravel version)

---

## File Structure

```
hws-hourly-bill-tracking-system/
│
├── .env.example                         # Environment template — every variable documented
├── README.md                            # This file
│
├── config/
│   └── hws.php                          # ALL system constants — zero hardcoded values elsewhere
│
├── database/
│   ├── migrations/
│   │   ├── 000001_create_settings_table.php
│   │   ├── 000002_create_clients_table.php
│   │   ├── 000003_create_employees_table.php
│   │   ├── 000004_create_invoices_table.php
│   │   ├── 000005_create_invoice_line_items_table.php
│   │   ├── 000006_create_email_templates_table.php
│   │   ├── 000007_create_lists_table.php
│   │   ├── 000008_create_scan_logs_table.php
│   │   └── 000009_add_role_to_users_table.php
│   └── seeders/
│       └── HwsSeeder.php                # Default admin, lists, email templates, settings
│
├── app/
│   ├── Models/
│   │   ├── Setting.php                  # Key/value config with static get/set helpers
│   │   ├── Client.php                   # Stripe-imported client with billing config
│   │   ├── Employee.php                 # Google Sheet per employee with scan cursors
│   │   ├── Invoice.php                  # Stripe draft invoice with lifecycle status
│   │   ├── InvoiceLineItem.php          # Individual billable row from employee sheet
│   │   ├── EmailTemplate.php            # Canned email with shortcode support
│   │   ├── ListItem.php                 # Generic lookup value (maps to "lists" table)
│   │   └── ScanLog.php                  # Per-employee billing scan log entry
│   │
│   ├── Services/
│   │   ├── GenericService.php           # Shared utilities (logging, formatting, parsing)
│   │   ├── StripeService.php            # ALL Stripe API calls — single wrapper
│   │   ├── GoogleSheetsService.php      # ALL Google Sheets API calls — single wrapper
│   │   ├── BillingService.php           # Scan orchestration, invoice creation, mark/reverse
│   │   └── EmailService.php             # THE single email function for the entire system
│   │
│   ├── Http/Controllers/
│   │   ├── DashboardController.php      # Overview stats and system health
│   │   ├── ClientController.php         # CRUD + Stripe import + credit management
│   │   ├── EmployeeController.php       # CRUD + Google Sheet validation
│   │   ├── InvoiceController.php        # Lifecycle actions, email, payment refresh
│   │   ├── ScanController.php           # Billing scan execution + invoice creation
│   │   ├── SettingController.php        # System settings + SMTP test
│   │   ├── EmailTemplateController.php  # Template CRUD + preview/test send
│   │   └── ListController.php           # Dynamic lookup list management
│   │
│   └── Providers/
│       └── HwsServiceProvider.php       # Service container bindings
│
├── resources/views/
│   ├── layouts/
│   │   └── app.blade.php               # Main layout with sidebar navigation
│   ├── dashboard/
│   │   └── index.blade.php             # Dashboard overview
│   ├── clients/
│   │   ├── index.blade.php             # Client list table
│   │   ├── import.blade.php            # Stripe import tool + debug panel
│   │   └── edit.blade.php              # Billing config + credit management
│   ├── employees/
│   │   ├── index.blade.php             # Employee list table
│   │   ├── create.blade.php            # New employee form
│   │   └── edit.blade.php              # Edit form + scan history
│   ├── invoices/
│   │   ├── index.blade.php             # Invoice list with status filter + actions
│   │   ├── show.blade.php              # Line items grouped by employee
│   │   └── email.blade.php             # Email compose with template selector
│   ├── scan/
│   │   ├── index.blade.php             # Run scan button
│   │   └── results.blade.php           # Scan results review + create invoices
│   ├── emails/
│   │   ├── index.blade.php             # Templates grouped by use case
│   │   ├── create.blade.php            # New template form
│   │   └── edit.blade.php              # Edit form + test send
│   ├── lists/
│   │   └── index.blade.php             # List management (add/toggle/delete)
│   └── settings/
│       └── index.blade.php             # Grouped settings form + SMTP test
│
└── routes/
    └── web.php                          # All routes behind auth middleware
```

---

## Database Schema

### `users` (Laravel default + role)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| name | varchar | Display name |
| email | varchar | Login email |
| role | varchar | Default: 'admin' — for future RBAC |
| password | varchar | Hashed |
| timestamps | | created_at, updated_at |

### `settings`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| key | varchar UNIQUE | Setting identifier (e.g., 'smtp_host') |
| value | text NULL | Setting value |
| group | varchar | Category (email, google, system) |
| type | varchar | Input type (text, textarea, password, boolean) |
| label | varchar NULL | Human-readable label |
| sort_order | int | Display order within group |
| timestamps | | created_at, updated_at |

### `clients`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| name | varchar | Display name from Stripe |
| email | varchar NULL | Email from Stripe |
| stripe_customer_id | varchar UNIQUE | cus_xxxxx |
| hourly_rate | decimal(10,2) | Default: 100.00 |
| billing_type | varchar NULL | From Lists: hourly_open, hourly_credits, fixed |
| credit_balance_hours | decimal(10,2) | Default: 0 — for hourly_credits clients |
| credit_alert_sent | boolean | Default: false — low balance alert flag |
| is_active | boolean | Default: true |
| notes | text NULL | Admin notes |
| timestamps | | created_at, updated_at |
| deleted_at | timestamp NULL | Soft delete |

### `employees`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| name | varchar | Display name |
| google_sheet_id | varchar | Sheet ID extracted from URL |
| scan_start_primary_key | int | Default: 0 — next scan starts after this PK |
| last_billing_primary_key | int | Default: 0 — where last billing ended |
| is_active | boolean | Default: true |
| timestamps | | created_at, updated_at |
| deleted_at | timestamp NULL | Soft delete |

### `invoices`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| stripe_invoice_id | varchar NULL UNIQUE | in_xxxxx |
| client_id | FK → clients | CASCADE on delete |
| total_minutes | int | Sum of all line item minutes |
| total_amount | decimal(10,2) | (minutes / 60) × hourly_rate |
| status | varchar | draft → sent → paid (or void) |
| stripe_payment_details | JSON NULL | Payment method, date, amount when paid |
| employee_ranges | JSON NULL | {"emp_id": {"start": X, "end": Y}} |
| stripe_invoice_url | varchar NULL | Hosted invoice URL |
| timestamps | | created_at, updated_at |
| deleted_at | timestamp NULL | Soft delete |

### `invoice_line_items`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| invoice_id | FK → invoices | CASCADE on delete |
| employee_id | FK → employees | CASCADE on delete |
| primary_key | int | From the Google Sheet row |
| date | date | Work date |
| time_minutes | int | Duration in minutes |
| description | text NULL | Work description |
| client_name | varchar | Snapshot of client name at scan time |
| domain | varchar NULL | Stored, not processed |
| sheet_row_number | int NULL | 1-indexed row for write-back |
| timestamps | | created_at, updated_at |

### `email_templates`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| use_case | varchar | Category: invoice_notification, etc. |
| name | varchar | Template display name |
| is_primary | boolean | Default template for its use_case |
| from_name | varchar NULL | Supports shortcodes |
| from_email | varchar NULL | Supports shortcodes |
| reply_to | varchar NULL | Supports shortcodes |
| cc | varchar NULL | Comma-separated, supports shortcodes |
| subject | varchar NULL | Supports shortcodes |
| body | longText NULL | HTML with shortcodes |
| is_active | boolean | Default: true |
| timestamps | | created_at, updated_at |

### `lists`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| list_key | varchar INDEXED | Category (e.g., 'customer_billing_type') |
| list_value | varchar | Option value (e.g., 'hourly_open') |
| sort_order | int | Display order |
| is_active | boolean | Default: true |
| timestamps | | created_at, updated_at |

### `scan_logs`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| employee_id | FK → employees NULL | SET NULL on delete |
| scan_type | varchar | Default: 'billing' |
| rows_scanned | int | Total rows read from sheet |
| rows_collected | int | Valid pending rows |
| errors | JSON NULL | [{type, row, message}] |
| started_at | timestamp NULL | Scan start time |
| completed_at | timestamp NULL | Scan end time |
| status | varchar | running → completed or failed |
| timestamps | | created_at, updated_at |

---

## Google Sheet Setup

### Required Column Headers (Row 1)

Every employee's Google Sheet must have these exact column headers in Row 1:

| Column | Header Name | Type | Description |
|--------|-------------|------|-------------|
| A | `primary_key` | Integer | Auto-incrementing ID — used as scan cursor |
| B | `date` | Date | Date work was performed |
| C | `time` | Integer | Duration in minutes |
| D | `billed_status` | Text | Must be `pending` to be collected. System writes `billed` |
| E | `description` | Text | Free-text work description |
| F | `client` | Text | Client name — **must exactly match** a client in the system |
| G | `domain` | Text | Domain/project reference — stored but not processed |

### Example Sheet

| primary_key | date | time | billed_status | description | client | domain |
|-------------|------|------|---------------|-------------|--------|--------|
| 1 | 2026-02-15 | 60 | pending | Homepage redesign | Acme Corp | acme.com |
| 2 | 2026-02-15 | 30 | pending | Bug fix on contact form | Acme Corp | acme.com |
| 3 | 2026-02-16 | 120 | pending | API integration | Beta LLC | beta.io |
| 4 | 2026-02-17 | 45 | billed | Logo design | Acme Corp | acme.com |

### Sharing Requirements

Each employee sheet must be shared with the Google service account email (found in your credentials JSON file) as an **Editor**. The service account email looks like:

```
your-service-account@your-project.iam.gserviceaccount.com
```

This email is also displayed on the Employee create/edit pages and in Settings.

---

## API Integrations

### Stripe API

All calls go through `App\Services\StripeService`. Used endpoints:

| Action | Endpoint | When |
|--------|----------|------|
| Get Customer | `GET /v1/customers/{id}` | Client import |
| Create Invoice | `POST /v1/invoices` | After scan → create invoices |
| Create Invoice Item | `POST /v1/invoiceitems` | Adding line item to draft |
| Finalize Invoice | `POST /v1/invoices/{id}/finalize` | When admin clicks finalize |
| Send Invoice | `POST /v1/invoices/{id}/send` | When admin sends via Stripe |
| Get Invoice | `GET /v1/invoices/{id}` | Payment status refresh |
| Get Payment Intent | `GET /v1/payment_intents/{id}` | Payment details when paid |

### Google Sheets API v4

All calls go through `App\Services\GoogleSheetsService`. Used methods:

| Action | Method | When |
|--------|--------|------|
| Validate access | `spreadsheets.values.get` (Row 1) | Employee create/update |
| Read rows | `spreadsheets.values.get` (A2:Z10000) | Billing scan |
| Write billed_status | `spreadsheets.values.batchUpdate` | Mark as billed |
| Highlight rows | `spreadsheets.batchUpdate` (RepeatCell) | Mark as billed (red background) |
| Get sheet metadata | `spreadsheets.get` | Get internal sheet ID for formatting |

### Brevo SMTP

All email goes through `App\Services\EmailService`. Configuration:

| Setting | Value |
|---------|-------|
| Host | `smtp-relay.brevo.com` |
| Port | `587` (STARTTLS) |
| Auth | Username = Brevo login email, Password = Brevo API key |

---

## Email Templates & Shortcodes

### Available Shortcodes

| Shortcode | Description | Example Value |
|-----------|-------------|---------------|
| `{{client_name}}` | Client display name | Acme Corp |
| `{{client_email}}` | Client email | billing@acme.com |
| `{{invoice_total}}` | Formatted invoice amount | $1,250.00 |
| `{{invoice_hours}}` | Total invoice hours | 12.50 |
| `{{invoice_date}}` | Invoice creation date | 2026-02-27 |
| `{{invoice_stripe_url}}` | Stripe hosted invoice URL | https://invoice.stripe.com/... |
| `{{work_log}}` | HTML table of all line items | (renders full table in email body) |
| `{{credit_balance}}` | Client remaining credit hours | 6.50 |
| `{{company_name}}` | Company name from settings | Hexa Web Systems |

Shortcodes work in **all** template fields: from name, from email, reply-to, CC, subject, and body.

### Default Templates (seeded)

1. **Standard Invoice Notification** (`invoice_notification`) — includes work log table and Stripe payment link
2. **Low Credit Balance Alert** (`low_credit_alert`) — notifies client of remaining prepaid hours

---

## Installation Guide

### Step 1 — Server Prerequisites (WHM/cPanel)

**PHP Extensions** — enable in EasyApache4 (WHM → Software → EasyApache4):

`bcmath` `ctype` `curl` `dom` `fileinfo` `json` `mbstring` `mysqlnd` `openssl` `pdo` `pdo_mysql` `tokenizer` `xml` `zip`

**Critical:** `mysqlnd` (MySQL Native Driver) must be installed for PHP to talk to MySQL. If missing:

```bash
# Check your PHP version first
php -v

# Install MySQL driver for your PHP version (adjust ea-php84 to match)
yum install ea-php84-php-mysqlnd
systemctl restart httpd
```

**Install Composer** — the PHP package manager:

```bash
# The standard installer may segfault on some systems. Download the phar directly:
curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer
chmod +x /usr/local/bin/composer
composer --version
```

### Step 2 — Create Subdomain in cPanel

In cPanel → Domains → Create a New Domain:
- Domain: `billing.hexawebsystems.com`
- Document root: `/home/hexawebsystems/public_html/billing.hexawebsystems.com`

### Step 3 — Create MySQL Database in cPanel

In cPanel → MySQL Databases:
1. Create database (e.g., `hexawebsystems_billing`)
2. Create user (e.g., `hexa_billing_user`) with a strong password
3. Add user to database with **ALL PRIVILEGES**

### Step 4 — Install Laravel

```bash
cd /home/hexawebsystems/public_html
composer create-project laravel/laravel billing-temp
```

### Step 5 — Clone HWS Repo

```bash
cd /home/hexawebsystems/public_html/billing.hexawebsystems.com

# Initialize git and pull the HWS codebase
git init
git config --global --add safe.directory /home/hexawebsystems/public_html/billing.hexawebsystems.com
git remote add origin https://github.com/mikeyperes/hws-billing-system-hourly.git
git pull origin main
```

### Step 6 — Merge Laravel Framework into HWS

```bash
# Copy Laravel's framework files into HWS (won't overwrite existing HWS files)
cp -rn /home/hexawebsystems/public_html/billing-temp/* /home/hexawebsystems/public_html/billing.hexawebsystems.com/
cp -rn /home/hexawebsystems/public_html/billing-temp/.* /home/hexawebsystems/public_html/billing.hexawebsystems.com/ 2>/dev/null

# Clean up the temp Laravel install
rm -rf /home/hexawebsystems/public_html/billing-temp
```

### Step 7 — Configure Environment

```bash
cd /home/hexawebsystems/public_html/billing.hexawebsystems.com

# Write the .env file (edit the DB credentials to match your setup)
cat > .env << 'ENVFILE'
APP_NAME="HWS Billing"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://billing.hexawebsystems.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hexawebsystems_billing
DB_USERNAME=hexa_billing_user
DB_PASSWORD="YOUR_DB_PASSWORD_HERE"

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
SESSION_DOMAIN=billing.hexawebsystems.com
CACHE_STORE=file
QUEUE_CONNECTION=sync

STRIPE_SECRET_KEY=sk_test_your_stripe_key_here

GOOGLE_CREDENTIALS_PATH=/home/hexawebsystems/public_html/billing.hexawebsystems.com/storage/app/google-credentials.json
GOOGLE_SERVICE_ACCOUNT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com

HWS_SMTP_HOST=smtp-relay.brevo.com
HWS_SMTP_PORT=587
HWS_SMTP_USERNAME=your_brevo_login_email
HWS_SMTP_PASSWORD=your_brevo_api_key
HWS_FROM_NAME="Hexa Web Systems"
HWS_FROM_EMAIL=billing@hexawebsystems.com

HWS_COMPANY_NAME="Hexa Web Systems"
HWS_DEFAULT_HOURLY_RATE=100
HWS_CREDIT_LOW_THRESHOLD=4
HWS_TIMEZONE=America/New_York
ENVFILE

# Generate Laravel encryption key
php artisan key:generate
```

### Step 8 — Redirect Domain Root to /public

The domain points to the project root, but Laravel serves from `/public`. Add a redirect:

```bash
cat > /home/hexawebsystems/public_html/billing.hexawebsystems.com/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
EOF
```

### Step 9 — Install PHP Dependencies

```bash
cd /home/hexawebsystems/public_html/billing.hexawebsystems.com

composer require stripe/stripe-php
composer require google/apiclient
composer require phpmailer/phpmailer
```

### Step 10 — Set Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R hexawebsystems:hexawebsystems storage bootstrap/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
chown -R hexawebsystems:hexawebsystems storage
```

### Step 11 — Run Migrations and Seed

```bash
php artisan migrate --force
php artisan db:seed --class=HwsSeeder --force
```

### Step 12 — Upload Google Credentials

Place your Google service account JSON key file at:

```
storage/app/google-credentials.json
```

(Or wherever `GOOGLE_CREDENTIALS_PATH` points in `.env`)

### Step 13 — Login

Navigate to `https://billing.hexawebsystems.com/login`

- **Email:** `admin@hexawebsystems.com`
- **Password:** `changeme123`

**Change the password immediately after first login.**

### Updating the Code

To pull the latest changes from the repo:

```bash
cd /home/hexawebsystems/public_html/billing.hexawebsystems.com
git pull origin main
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Troubleshooting Quick Reference

```bash
# 419 PAGE EXPIRED — CSRF/session issue
# Add to .env:
# SESSION_SECURE_COOKIE=false
# SESSION_DOMAIN=billing.hexawebsystems.com
php artisan config:clear

# 500 error — check the log
cat storage/logs/laravel.log

# "could not find driver" — MySQL driver missing
yum install ea-php84-php-mysqlnd
systemctl restart httpd

# Permission errors
chmod -R 775 storage bootstrap/cache
chown -R hexawebsystems:hexawebsystems storage bootstrap/cache

# Git "dubious ownership" error
git config --global --add safe.directory /home/hexawebsystems/public_html/billing.hexawebsystems.com

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Composer not found
curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### Post-Install Checklist

- [ ] Changed default admin password
- [ ] Configured Stripe secret key in `.env`
- [ ] Uploaded Google service account JSON credentials
- [ ] Configured Brevo SMTP credentials in `.env`
- [ ] Sent test email from Settings → Test SMTP
- [ ] Added at least one employee with validated Google Sheet
- [ ] Imported at least one client from Stripe
- [ ] Verified billing scan finds test rows

---

## Coding Standards

- **Zero hardcoded values** — everything lives in `config/hws.php` or `.env`
- **PSR-4 namespaces** — `App\Services`, `App\Models`, `App\Http\Controllers`
- **Single responsibility services** — each API integration has exactly one service wrapper
- **One email function** — `EmailService::send()` is the only way email leaves the system
- **PHPDoc on every function** — `@param`, `@return`, `@throws`
- **Comment on every line** — all code has inline comments explaining intent
- **Structured logging** — all operations log to `storage/logs/hws.log` via `GenericService::log()`
- **Typed casts** — all model attributes use `$casts` for proper PHP types
- **Soft deletes** — clients, employees, and invoices are flagged, not removed from DB
- **No business logic in controllers** — controllers are thin, services contain all logic

---

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Credits in **hours**, not dollars | Simpler math, clearer for clients, rate changes don't affect balance |
| **Exact name match** for clients | Prevents partial/fuzzy matching errors — sheet must match DB exactly |
| Fixed billing type is **label-only** | No special processing — just a tag for admin reference |
| Domain column **stored, not processed** | Available for future filtering but not part of billing logic |
| **Single admin** with multi-user structure | Role field exists for future RBAC without schema changes |
| Work log in **email body**, not attachment | `{{work_log}}` shortcode renders HTML table inline |
| **PHPMailer** over Laravel Mail | Direct SMTP control, no queue dependency, simpler Brevo integration |
| **Session storage** for scan results | Scan → review → create is a multi-step flow within one session |
| **Tailwind CDN** instead of build step | No Node.js/npm needed on cPanel server — zero build tooling |
| **Alpine.js** for interactivity | Lightweight, no build step, dropdowns and modals without full SPA |

---

## Troubleshooting

### "Sheet validation failed" when adding employee
- Ensure the Google Sheet is shared with the service account email as **Editor**
- Check that Row 1 has all required column headers (exact spelling matters)
- Verify `GOOGLE_CREDENTIALS_PATH` in `.env` points to a valid JSON file
- Run `php artisan config:clear` if you changed the path

### "Stripe secret key is not configured"
- Check `.env` file has `STRIPE_SECRET_KEY` set (starts with `sk_test_` or `sk_live_`)
- Run `php artisan config:clear` after changing `.env`
- Verify there are no extra spaces or quotes around the key

### Emails not sending
- Go to Settings → Test SMTP and send a test email
- Check `HWS_SMTP_USERNAME` (your Brevo login email) and `HWS_SMTP_PASSWORD` (your Brevo **API key**, not login password)
- Check `storage/logs/hws.log` for detailed error messages
- Ensure the "From" email is verified in your Brevo account

### Scan finds 0 rows
- Check employee's `scan_start_primary_key` on the edit page — scan only reads rows with PK **greater than** this value
- Verify the sheet has rows with `billed_status` = `pending` (not `billed` or empty)
- Check that the employee is marked as **Active**
- Try setting `scan_start_primary_key` to `0` to scan all rows

### Client name not matching during scan
- Client names must match **exactly** (case-sensitive) between the Google Sheet and the system
- Check for trailing spaces, typos, or encoding differences
- Look at the scan error log — it shows which client names couldn't be matched

### Invoice shows $0 amount
- Verify the client's `hourly_rate` is set (Clients → Edit → Hourly Rate)
- Check that `time` values in the sheet are in **minutes** (not hours)
- A 1-hour job should have `time` = `60`, not `1`

### Config changes not taking effect
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Permission errors after deployment
```bash
chmod -R 775 storage bootstrap/cache
chown -R account:account .
```

### 500 error / blank page
- Set `APP_DEBUG=true` in `.env` temporarily to see the actual error
- Check `storage/logs/laravel.log` for the error trace
- **Remember to set `APP_DEBUG=false` when done debugging**

---

## License

Internal use only — Hexa Web Systems.
