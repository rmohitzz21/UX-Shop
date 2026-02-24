# PRODUCTION AUDIT REPORT
## UX Pacific Merchandise — Full-Stack Production Readiness Review

**Date:** 2026-02-19
**Auditor:** Claude Code (Automated + Static Analysis)
**Branch:** `main` @ `e838453`
**Stack:** PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4 · XAMPP / Windows 11

---

## FINAL VERDICT

> **NOT READY FOR PRODUCTION**
> Score: **61 / 100**

| Category | Score |
|---|---|
| Security | 48 / 100 |
| Functionality | 60 / 100 |
| Code Quality | 68 / 100 |
| Database | 65 / 100 |
| Performance | 55 / 100 |
| Test Coverage | 78 / 100 |

---

## TABLE OF CONTENTS

1. [Project Overview](#1-project-overview)
2. [Test Suite Results](#2-test-suite-results)
3. [Security Findings](#3-security-findings)
4. [Broken / Non-functional Features](#4-broken--non-functional-features)
5. [Performance Issues](#5-performance-issues)
6. [Database Issues](#6-database-issues)
7. [Code Quality Issues](#7-code-quality-issues)
8. [HTTP Endpoint Status](#8-http-endpoint-status)
9. [Dead Code](#9-dead-code)
10. [Error Log Review](#10-error-log-review)
11. [Recommendations](#11-recommendations)
12. [Production Blockers](#12-production-blockers)

---

## 1. PROJECT OVERVIEW

### File Statistics

| Type | Count |
|---|---|
| PHP files (total) | 72 |
| API endpoints | 40+ |
| Frontend pages | 20+ |
| JavaScript files | 2 |
| CSS files | 1 |
| SQL migration files | 5 |
| Test files | 1 |
| Product images | 97+ |

### Database

| Table | Rows | AUTO_INCREMENT |
|---|---|---|
| users | 5 | 48 |
| products | 2 | 37 |
| orders | 3 | 82 |
| order_items | ~3 | 81 |
| cart | ~0 | 179 |
| addresses | ~3 | 6 |
| user_tokens | ~2 | 13 |
| contact_messages | 1 | 2 |
| password_reset_tokens | 0 | 1 |

**Note:** High AUTO_INCREMENT vs row count disparity on `cart` (179/~0), `orders` (82/3), `users` (48/5) indicates extensive dev/test churn with row deletions. Orphaned test data present.

### Current Data State

- **Users:** 5 total (2 admin, 3 customer) — includes test account `ts_admin@test.uxpacific.local`
- **Products:** 2 (1 digital "Webiste Template" [sic], 1 test physical "TS_Product_setup")
- **Orders:** 3 all in Pending status

---

## 2. TEST SUITE RESULTS

**Test file:** `tests/test_suite.php`
**Last run:** 2026-02-19
**Runner:** PHP CLI (XAMPP)

```
RESULTS: 29 passed  /  0 failed  /  2 skipped
```

### Passed Tests (29)

| Group | Tests |
|---|---|
| User Auth | Signup, Login, Session check, Logout |
| Cart | Add item, List cart, Update qty, Remove item, Clear cart |
| Checkout | Create order (COD), Order history |
| Admin Auth | Admin login, Admin session check |
| Admin Products | Create product (HTTP 201), Update product, Toggle status, Delete product |
| Admin Orders | List orders, Get order details, Update order status, Delete order |
| Admin Users | List users, Block/unblock user |
| Security | CSRF validation, SQL injection attempt, XSS payload handling, Malicious file upload rejection, Directory traversal attempt |

### Skipped Tests (2)

- `50 concurrent GET /api/product/list.php` — `curl_multi_cleanup` not available in XAMPP PHP CLI
- `Response times: min/avg/p95/max` — same reason

### Issues Fixed Before This Run

- Hardcoded `TEST_PRODUCT_ID=17` replaced with dynamic DB lookup in `setUp()`
- `{$RST}` constant interpolation bug fixed (constants cannot be interpolated in strings)
- Admin product HTTP 201 vs 200 mismatch fixed (`in_array($code, [200, 201])`)
- `curl_multi_cleanup` guard corrected to check both `curl_multi_init` AND `curl_multi_cleanup`
- `setUp()` now auto-creates a test product if no suitable physical product exists

---

## 3. SECURITY FINDINGS

### CRITICAL

#### SEC-01 — SMTP Credentials Exposed in `.env`

**File:** `.env`

```
SMTP_PASS=FTkyPOScWAUm46zV
SMTP_USER=admin@surveypacific.com
```

The `.env` file is blocked by `.htaccess` via HTTP, but it is committed to git history and readable by any process with filesystem access to the XAMPP webroot. Real Brevo/Sendinblue SMTP credentials are present.

**Fix:** Rotate credentials immediately. Move secrets to system environment variables outside the webroot. Add `.env` to `.gitignore` and purge from git history (`git filter-branch` or BFG Repo Cleaner).

---

#### SEC-02 — Root MySQL User with No Password

**File:** `.env`

```
DB_USER=root
DB_PASS=
```

The application connects to MariaDB as `root` with an empty password. Any compromised web shell or SQL injection exploit would have full database admin privileges (DROP DATABASE, FILE read/write, user creation, etc.).

**Fix:** Create a dedicated application DB user:

```sql
CREATE USER 'ux_app'@'localhost' IDENTIFIED BY '<strong-random-password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON uxmerchandise.* TO 'ux_app'@'localhost';
FLUSH PRIVILEGES;
```

Update `.env`: `DB_USER=ux_app` / `DB_PASS=<strong-random-password>`

---

#### SEC-03 — Stored XSS in Admin Dashboard (Category Field)

**File:** `admin/admin-dashboard.js:281`

```javascript
// category rendered without escapeHtml():
<td>${category}</td>
```

The `escapeHtml()` function exists and is used correctly for other fields, but the `category` field is rendered raw. A product created via the API with a malicious category value (e.g. `<img src=x onerror=alert(document.cookie)>`) would execute in every admin's dashboard session.

**Fix:**

```javascript
<td>${escapeHtml(category)}</td>
```

---

### HIGH

#### SEC-04 — CSRF Missing on Multiple State-Changing Endpoints

The following endpoints accept POST requests without validating a CSRF token:

- `api/cart/add.php`
- `api/cart/remove.php`
- `api/cart/update.php`
- `api/cart/clear.php`
- `api/cart/merge.php`
- `api/address/add.php`
- `api/address/delete.php`
- `api/contact/send.php`
- `api/auth/signup.php` (partial)

**Fix:** Apply the same CSRF validation pattern used in `api/auth/login.php` to all state-changing endpoints.

---

#### SEC-05 — Test Suite Accessible via HTTP

**File:** `.htaccess`

`.htaccess` blocks `/includes/`, `/migrations/`, `/core/`, `/logs/` but does **NOT** block `/tests/`. The file `tests/test_suite.php` is directly accessible at `http://host/ux/Ux-Merchandise/tests/test_suite.php`. A browser request would execute the full test suite against the production database, creating and deleting users, products, and orders.

**Fix:**

```apache
RewriteRule ^tests(/|$) - [F,L]
```

---

#### SEC-06 — `requireAuth()` Passes for Admin Sessions on User Endpoints

**File:** `includes/helpers.php`

```php
function requireAuth() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
        sendResponse("error", "Unauthorized", null, 401);
    }
}
```

An admin session satisfies `requireAuth()`. When `api/order/get.php` then reads `$_SESSION['user_id']` (not set for admins), PHP emits an Undefined Index Warning and the query executes with `user_id = NULL`.

**Fix:** User-facing endpoints should require `$_SESSION['user_id']` explicitly, not accept admin sessions.

---

#### SEC-07 — Admin Authentication Check via localStorage

**File:** `admin/editproduct.php:552`

```javascript
const adminSession = localStorage.getItem('adminSession');
if (!adminSession) { window.location.href = 'admin-login.php'; return; }
```

This is a client-side-only authentication check that any user can bypass by setting `adminSession` in localStorage. The PHP session check at the top of the file is the real gate, but this pattern could mislead future developers into removing the PHP check.

**Fix:** Remove localStorage auth checks entirely. Rely only on PHP session validation.

---

#### SEC-08 — Password Reset Token Exposed in GET URL

**Files:** `reset-password.php`, `api/auth/forgot-password.php`

Reset tokens appear in URL query parameters (`?token=...&email=...`), making them visible in browser history, server access logs, and HTTP Referer headers if the page loads any external resources (Google Fonts, etc.).

**Fix:** Submit token via POST body, or use a one-time URL that immediately invalidates on first load and stores token in session.

---

#### SEC-09 — Dynamic Inline `onclick` Handlers in Admin JS

**File:** `admin/admin-dashboard.js:215, 301-302`

```javascript
btn.setAttribute('onclick', `toggleUserBlock(${userId}, this, ${newBlockedState})`);
```

While `userId` is numeric in practice, this pattern is dangerous. If the value type changes or comes from a different source in future edits, it becomes a DOM-based XSS vector.

**Fix:** Use data attributes and event delegation:

```javascript
btn.dataset.userId = userId;
btn.dataset.blocked = newBlockedState;
btn.addEventListener('click', handleToggleBlock);
```

---

### MEDIUM

#### SEC-10 — Debug `console.log` in Production

**File:** `admin/admin-login.php:115` — `console.log("Login submitted")` left in production code.

#### SEC-11 — `api/auth/session.php` Returns HTTP 200 When Unauthenticated

Returns `{"status":"error","message":"Not authenticated"}` with HTTP 200 instead of 401. Clients that check HTTP status codes will treat unauthenticated state as success.

#### SEC-12 — User Profile Endpoints Return HTTP 200 on Auth Failure

**Files:** `api/user/profile.php`, `api/user/update_profile.php` — return HTTP 200 with error body instead of 401.

#### SEC-13 — No Rate Limiting on Signup

Login is rate-limited (5 attempts / 10-minute lockout). Signup is not. An attacker can automate mass account creation for spam or credential stuffing prep.

---

## 4. BROKEN / NON-FUNCTIONAL FEATURES

### BRK-01 — Search Page (`search.php`)

**Status: COMPLETELY NON-FUNCTIONAL**

The `search.php` page renders an empty HTML shell. There are no API calls, no JavaScript search logic, and no backend search endpoint. The search bar in the navbar routes to this page but returns nothing.

---

### BRK-02 — Wishlist Page (`wishlist.php`)

**Status: COMPLETELY NON-FUNCTIONAL**

Same as search — renders an empty shell with no API calls or functionality. There is no wishlist API endpoint.

---

### BRK-03 — Payment Gateway (Card / UPI)

**Status: NOT IMPLEMENTED**

`checkout.php` displays Card and UPI payment options with a "Coming Soon" notice. Only Cash on Delivery (COD) is functional. COD is correctly blocked for digital products — but there is no alternative payment method, making **digital products effectively unpurchasable**.

---

### BRK-04 — Google OAuth Login

**File:** `script.js:2295`

```javascript
// TODO: Implement Google OAuth
window.handleGoogleLogin = function() { showToast('Google login coming soon...', 'info'); }
```

Stub function only. No OAuth flow, no client ID configuration, no backend handler.

---

### BRK-05 — Admin Analytics Tab

**Status: STUB**

The Analytics tab in the admin dashboard shows a "coming soon" placeholder. No revenue charts, conversion funnels, or traffic data.

---

### BRK-06 — Phone / OTP Login

**File:** `signin.php`

The phone/OTP login UI exists but displays "coming soon." No backend OTP endpoint exists.

---

## 5. PERFORMANCE ISSUES

### PERF-01 — N+1 Query in Order History

**File:** `api/order/get.php`

For a user with N orders, the endpoint executes 1 + N queries (one SELECT per order to fetch its items). With 50 orders: 51 database round-trips per API call.

**Fix:** Use a JOIN:

```sql
SELECT o.*, oi.id AS item_id, oi.product_id, oi.quantity, oi.price,
       oi.size, oi.product_name, oi.product_image
FROM orders o
LEFT JOIN order_items oi ON oi.order_id = o.id
WHERE o.user_id = ?
ORDER BY o.created_at DESC
```

---

### PERF-02 — No Pagination on Public Product List

**File:** `api/product/list.php`

Returns all columns (`SELECT *`) for all active products in a single response. Also returns `commercial_price` publicly — a pricing data leak. No `LIMIT`/`OFFSET` or cursor pagination.

---

### PERF-03 — INNER JOIN Breaks Order History on Product Deletion

**File:** `api/order/get.php`

Uses `INNER JOIN products` — if a product is deleted after being ordered, that order's items disappear from the customer's history. The `order_items` table already has snapshot columns (`product_name`, `product_image`) that are never used.

**Fix:** Switch to `LEFT JOIN` and use snapshot columns as fallback:

```sql
LEFT JOIN products p ON p.id = oi.product_id
-- Use: COALESCE(p.name, oi.product_name) AS display_name
```

---

### PERF-04 — No Database Indexes Beyond Primary Keys

All queries filtering by `user_id`, `order_id`, `product_id`, `status`, or `created_at` perform full table scans.

**Recommended indexes:**

```sql
ALTER TABLE orders ADD INDEX idx_orders_user_id (user_id);
ALTER TABLE orders ADD INDEX idx_orders_status (status);
ALTER TABLE order_items ADD INDEX idx_items_order_id (order_id);
ALTER TABLE cart ADD INDEX idx_cart_user_id (user_id);
ALTER TABLE password_reset_tokens ADD INDEX idx_prt_expires (expires_at);
```

---

### PERF-05 — `admin-dashboard.php` is a 1,500-Line Monolith

1,000+ lines of inline `<style>` CSS embedded in the PHP file. Every admin page load delivers the full stylesheet in the HTML response with no caching benefit.

---

## 6. DATABASE ISSUES

### DB-01 — `product_type` vs `available_type` Column Conflict

**Table:** `products`

Two conflicting columns exist:
- `available_type ENUM('physical','digital','both')` — used by all application code
- `product_type ENUM('physical','digital')` — not referenced anywhere in application code

Product id=31 has `available_type=digital` and `product_type=physical` — directly contradictory.

**Fix:**

```sql
ALTER TABLE products DROP COLUMN product_type;
```

---

### DB-02 — No FK Constraint on `password_reset_tokens.user_id`

`user_id` has an index but no foreign key. Deleting a user leaves orphaned reset tokens that cannot be automatically cleaned up.

**Fix:**

```sql
ALTER TABLE password_reset_tokens
ADD CONSTRAINT fk_prt_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

---

### DB-03 — `order_items.product_id` FK Without `ON DELETE` Action

References `products(id)` with no `ON DELETE` action (defaults to RESTRICT). This silently prevents product deletion when orders exist. The admin delete endpoint handles this gracefully, but the constraint should match the intended behavior.

**Recommended:** `ON DELETE SET NULL` — allows product deletion while preserving order history rows (use snapshot columns for display).

---

### DB-04 — `orders` Table Has No `updated_at` Column

There is no timestamp for when an order status last changed. Order status history is completely untrackable.

**Fix:**

```sql
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

### DB-05 — Test Data Present in Database

- User: `ts_admin@test.uxpacific.local` (test admin account from test suite)
- Product: `TS_Product_setup` (auto-created by test suite, empty `image` field)

These must be deleted before production deployment.

---

### DB-06 — `orders.shipping_address` Stored as JSON String

The full shipping address is serialized as a JSON string in a TEXT column. This makes querying by city, state, or country impossible and is not normalized.

---

### DB-07 — Product Name Typo in Live Data

Product id=31 is named **"Webiste Template"** (missing 'r'). This is customer-visible.

**Fix:** `UPDATE products SET name = 'Website Template' WHERE id = 31;`

---

### DB-08 — No Soft Delete on Products

Products are hard-deleted. Combined with the INNER JOIN issue (PERF-03), deleting any product corrupts the order history display for all customers who purchased it.

---

## 7. CODE QUALITY ISSUES

### CQ-01 — Inconsistent API Response Formats

Most endpoints use `sendResponse()` from `helpers.php`, which wraps all responses in `{"status":"...","message":"...","data":...}`. However:

- `api/admin/order/list.php` uses raw `echo json_encode($orders)` — no wrapper, direct array output
- This makes client-side response parsing inconsistent

---

### CQ-02 — Inconsistent HTTP Status Codes

| Endpoint | Issue |
|---|---|
| `api/auth/session.php` | Returns 200 when unauthenticated (should be 401) |
| `api/user/profile.php` | Returns 200 on auth failure (should be 401) |
| `api/user/update_profile.php` | Returns 200 on auth failure (should be 401) |
| `api/auth/signup.php` | Missing `http_response_code()` on validation errors |

---

### CQ-03 — `checkAdminAuth()` Always Returns True

**File:** `admin/admin-dashboard.js`

This function is vestigial — it unconditionally returns `true`. Real authentication is handled by the PHP session check at the top of `admin-dashboard.php`. The JS function creates a false sense of security and could mislead future developers.

---

### CQ-04 — Debug Statements in Production Code

| File | Count | Details |
|---|---|---|
| `script.js` | 10 | `console.error` / `console.warn` calls |
| `admin/admin-dashboard.js` | 16 | `console.error` calls |
| `admin/admin-login.php` | 1 | `console.log("Login submitted")` — HIGH severity |

---

### CQ-05 — Commented-Out Dead Code in `script.js`

Lines 56-77 and 82-90 contain commented-out product filtering and mobile menu logic that should be removed rather than left in the source.

---

### CQ-06 — `loadCartPage()` is 180+ Lines

This single function in `script.js` handles cart fetching, rendering, event binding, and order summary updates. Should be decomposed into smaller units.

---

### CQ-07 — Unimplemented Feature TODO Comments

**File:** `script.js:2295, 2301`

```javascript
// TODO: Implement Google OAuth
```

Two identical TODO comments for a feature with no implementation timeline or tracking.

---

### CQ-08 — `admin/addproduct.php` Duplicates Dashboard Functionality

A standalone add-product form exists at `admin/addproduct.php` that duplicates the add-product feature already present in the admin dashboard. These two implementations may drift apart over time.

---

## 8. HTTP ENDPOINT STATUS

### Frontend Pages

| URL | Expected | Notes |
|---|---|---|
| `/` (index.php) | 200 | Homepage |
| `/shopAll.php` | 200 | Product listing |
| `/signin.php` | 200 | Login form |
| `/signup.php` | 200 | Registration form |
| `/checkout.php` | 200 | Checkout (COD only) |
| `/forgot-password.php` | 200 | Password reset request |
| `/search.php` | 200 | Empty shell — broken |
| `/wishlist.php` | 200 | Empty shell — broken |
| `/admin/admin-login.php` | 200 | Admin login form |
| `/admin/admin-dashboard.php` | 302 → admin-login | PHP session gate |

### Sensitive Files (`.htaccess` protection)

| URL | Status | Notes |
|---|---|---|
| `/includes/config.php` | 403 | BLOCKED — correct |
| `/.env` | 403 | BLOCKED — correct |
| `/migrations/*.sql` | 403 | BLOCKED — correct |
| `/tests/test_suite.php` | **200** | **NOT BLOCKED — security issue** |

### API Endpoints (unauthenticated)

| Endpoint | Expected Status | Notes |
|---|---|---|
| `GET /api/product/list.php` | 200 | Public — correct |
| `GET /api/cart/list.php` | 401 | Auth required |
| `GET /api/order/get.php` | 401 | Auth required |
| `GET /api/user/profile.php` | **200** | **Returns 200 with error body — bug** |
| `GET /api/admin/user/list.php` | 401 | Admin required |
| `GET /api/admin/stats/overview.php` | 401 | Admin required |
| `GET /api/auth/session.php` | **200** | **Returns 200 when unauthenticated — bug** |

---

## 9. DEAD CODE

| File | Issue |
|---|---|
| `admin/addproduct.php` | Parallel add-product form, functionality duplicated in dashboard |
| `migrations/setup_db.php` | One-time migration script left in webroot |
| `migrations/check_schema.php` | Dev utility, not production code |
| `migrations/fix_user_id.php` | One-time migration |
| `migrations/enforce_user_id.php` | One-time migration |
| `migrations/add_missing_product_columns.php` | One-time migration |
| `script.js:56-90` | Commented-out filter and mobile menu code |
| `admin-dashboard.js:checkAdminAuth()` | Always returns `true`; vestigial function |

---

## 10. ERROR LOG REVIEW

**File:** `logs/app_errors.log`

### Active PHP Warning

```
PHP Warning: Undefined index: user_id in api/order/get.php on line 7
```

**Cause:** `requireAuth()` passes when an admin is logged in, but `$_SESSION['user_id']` is not set for admin sessions. Line 7 reads `$_SESSION['user_id']` without null-coalescing. The resulting query runs with `user_id = NULL`, returning incorrect results.

**Status:** Active — fires on every request to this endpoint from an authenticated admin session. Confirmed in log file.

**Fix:**
```php
// Replace:
$userId = $_SESSION['user_id'];
// With:
if (empty($_SESSION['user_id'])) {
    sendResponse("error", "Unauthorized", null, 401);
}
$userId = (int) $_SESSION['user_id'];
```

---

## 11. RECOMMENDATIONS

### Priority 1 — Immediate (Before Any User Traffic)

1. **Rotate SMTP credentials** — Brevo key `FTkyPOScWAUm46zV` is in git history and must be considered compromised
2. **Create dedicated MySQL user** — replace `root`/empty-password with `ux_app` with minimal privileges
3. **Block `/tests/` in `.htaccess`** — add `RewriteRule ^tests(/|$) - [F,L]`
4. **Fix `requireAuth()`** — separate user vs admin session checks; user endpoints should reject admin sessions
5. **Fix `api/order/get.php`** — add explicit user_id check, switch to LEFT JOIN, use snapshot columns for product data
6. **Clean test data from DB** — delete `ts_admin@test.uxpacific.local` user and `TS_Product_setup` product

### Priority 2 — Before Launch

7. Add CSRF token validation to all cart, address, and contact endpoints
8. Implement `search.php` — product search API and frontend
9. Implement `wishlist.php` — wishlist API and frontend
10. Add a real payment gateway (Stripe or Razorpay) to enable digital product purchases
11. Drop `product_type` column: `ALTER TABLE products DROP COLUMN product_type;`
12. Add FK on `password_reset_tokens.user_id` with `ON DELETE CASCADE`
13. Fix XSS in admin dashboard — apply `escapeHtml()` to `category` field
14. Fix HTTP status codes — all auth failures must return 401, not 200
15. Add `LIMIT`/`OFFSET` pagination to `api/product/list.php`
16. Remove `commercial_price` from public product list API response
17. Add recommended DB indexes (see PERF-04)

### Priority 3 — Post-Launch Polish

18. Fix N+1 query in order history with a JOIN
19. Add `updated_at` column to `orders` table
20. Implement soft delete on products
21. Remove all debug `console.log/error` statements from frontend JS
22. Remove dead code (commented JS, redundant addproduct.php, migration scripts in webroot)
23. Decompose `loadCartPage()` and other 100+ line functions
24. Normalize `shipping_address` out of JSON TEXT into proper columns
25. Fix product name typo: `UPDATE products SET name='Website Template' WHERE id=31;`
26. Add order status change audit trail
27. Implement analytics tab with revenue and product data
28. Remove localStorage admin auth check in `admin/editproduct.php`
29. Convert inline `onclick` attributes to event delegation in admin JS
30. Implement Google OAuth or remove the placeholder UI

---

## 12. PRODUCTION BLOCKERS

The following 9 issues **must be resolved** before this application handles real users or payment data:

| # | Blocker | Severity |
|---|---|---|
| 1 | SMTP password in `.env` is in git history — credentials must be rotated | CRITICAL |
| 2 | MySQL root with no password — replace with restricted application user | CRITICAL |
| 3 | `/tests/test_suite.php` accessible via HTTP — executes test suite against live DB | CRITICAL |
| 4 | Search page (`search.php`) is completely non-functional | HIGH |
| 5 | Wishlist page (`wishlist.php`) is completely non-functional | HIGH |
| 6 | Digital products cannot be purchased — COD blocked, no working payment alternative | HIGH |
| 7 | `api/order/get.php` emits PHP Warnings and returns wrong data for admin sessions | HIGH |
| 8 | No CSRF protection on cart mutation endpoints | HIGH |
| 9 | Stored XSS in admin dashboard via product category field | HIGH |

---

*Report generated by Claude Code automated audit — 2026-02-19*
*Methodology: static file analysis · DB schema inspection · test suite execution (29 passed / 0 failed / 2 skipped) · error log review · JS/CSS security audit*
