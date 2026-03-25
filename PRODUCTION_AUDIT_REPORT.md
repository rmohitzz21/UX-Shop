# PRODUCTION AUDIT REPORT
## UX Pacific Merchandise — Full-Stack Production Readiness Review

**Date:** 2026-03-25 *(updated from 2026-02-19)*
**Auditor:** Claude Code (Automated + Static Analysis)
**Branch:** `main` @ `d126b96`
**Stack:** PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4 · XAMPP / Windows 11

---

## CHANGES SINCE LAST AUDIT (2026-02-19 → 2026-03-25)

| Item | Was | Now |
|---|---|---|
| `search.php` | Empty shell | ✅ Functional — search with category/price/sort filters |
| `wishlist.php` | Empty shell | ✅ Functional — localStorage wishlist with add-to-cart |
| `api/order/get.php` LEFT JOIN | INNER JOIN (broke deleted products) | ✅ LEFT JOIN |
| Product card image | Fixed 160px height (cropped) | ✅ `aspect-ratio: 1/1`, `object-fit: cover` |
| Product card hover | None | ✅ Lift + purple glow |
| Product page CTAs | Two identical gradient buttons | ✅ `btn-primary` (Buy Now) + `btn-ghost` (Add to Cart) |
| `btn-primary` base | `width: 120px; height: 35px` hardcoded | ✅ Removed — uses padding, `width: fit-content` |
| `signin.php` / `signup.php` button | Blue-purple gradient `#667eea`, `border-radius: 12px` | ✅ Site accent `#6f4bff`, `border-radius: 999px` |
| `cart.php` inline styles | `style="height:auto; width:auto"` hacks | ✅ Removed |
| `account.php` Add Address | `style="width:180px; height:41px"` | ✅ Removed |
| `forgot-password.php` success btn | Inline styles | ✅ Replaced with `.auth-submit` class |
| `reset-password.php` success btn | Inline styles | ✅ Replaced with `.auth-submit` class |

---

## FINAL VERDICT

> **NOT READY FOR PRODUCTION**
> Score: **65 / 100** *(was 61/100)*

| Category | Score | Change |
|---|---|---|
| Security | 48 / 100 | → no change |
| Functionality | 70 / 100 | ↑ +10 (search + wishlist fixed) |
| Code Quality | 72 / 100 | ↑ +4 (UI/CTA consistency, inline style cleanup) |
| Database | 65 / 100 | → no change |
| Performance | 58 / 100 | ↑ +3 (LEFT JOIN fixed) |
| Test Coverage | 78 / 100 | → no change |

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

| Table | Notes |
|---|---|
| users | 5 rows (2 admin, 3 customer) |
| products | 2 rows (1 live digital, 1 test physical) |
| orders | 3 rows, all Pending |
| order_items | ~3 rows |
| cart | ~0 rows (high AUTO_INCREMENT from dev churn) |
| addresses | ~3 rows |
| password_reset_tokens | 0 rows |

**Note:** High AUTO_INCREMENT vs row count disparity on `cart` (179/~0), `orders` (82/3), `users` (48/5) indicates extensive dev/test churn. Test data still present.

---

## 2. TEST SUITE RESULTS

**Test file:** `tests/test_suite.php`
**Last run:** 2026-02-19
**Runner:** PHP CLI (XAMPP)

```
RESULTS: 29 passed  /  0 failed  /  2 skipped
```

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

**Skipped (2):** `curl_multi_cleanup` not available in XAMPP PHP CLI — concurrent load tests could not run.

---

## 3. SECURITY FINDINGS

### CRITICAL

#### SEC-01 — SMTP Credentials Exposed in `.env`

**File:** `.env`

```
SMTP_PASS=FTkyPOScWAUm46zV
SMTP_USER=admin@surveypacific.com
```

The `.env` file is blocked by `.htaccess` via HTTP, but it is committed to git history. Real Brevo SMTP credentials are present and must be considered compromised.

**Fix:** Rotate credentials immediately. Move secrets to system environment variables outside the webroot. Add `.env` to `.gitignore` and purge from git history (BFG Repo Cleaner).

---

#### SEC-02 — Root MySQL User with No Password

**File:** `.env`

```
DB_USER=root
DB_PASS=
```

Any compromised web shell or SQL injection exploit would have full database admin privileges.

**Fix:**
```sql
CREATE USER 'ux_app'@'localhost' IDENTIFIED BY '<strong-random-password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON uxmerchandise.* TO 'ux_app'@'localhost';
FLUSH PRIVILEGES;
```

---

#### SEC-03 — Stored XSS in Admin Dashboard (Category Field) `STILL PRESENT`

**File:** `admin/admin-dashboard.js:281`

```javascript
// category rendered without escapeHtml():
const categoryBadge = `<span class="badge badge-info">${category}</span>`;
```

The `escapeHtml()` function exists and is used for other fields but not `category`. A product with a malicious category value executes in every admin's session.

**Fix:**
```javascript
const categoryBadge = `<span class="badge badge-info">${escapeHtml(category)}</span>`;
```

---

### HIGH

#### SEC-04 — CSRF Missing on Multiple State-Changing Endpoints `STILL PRESENT`

The following endpoints accept POST requests without CSRF token validation:

- `api/cart/add.php`
- `api/cart/remove.php`
- `api/cart/update.php`
- `api/cart/clear.php`
- `api/cart/merge.php`
- `api/address/add.php`
- `api/address/delete.php`
- `api/contact/send.php`

**Fix:** Apply the same CSRF validation pattern used in `api/auth/login.php` to all state-changing endpoints.

---

#### SEC-05 — Test Suite Accessible via HTTP `STILL PRESENT`

`.htaccess` blocks `/includes/`, `/migrations/`, `/core/`, `/logs/` but does **NOT** block `/tests/`. `tests/test_suite.php` is directly accessible at `http://host/ux/Ux-Merchandise/tests/test_suite.php`, executing the full test suite against the production database.

**Fix:**
```apache
RewriteRule ^tests(/|$) - [F,L]
```

---

#### SEC-06 — `requireAuth()` Passes for Admin Sessions on User Endpoints `STILL PRESENT`

**File:** `includes/helpers.php`

```php
function requireAuth() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
        sendResponse("error", "Unauthorized", null, 401);
    }
}
```

An admin session satisfies `requireAuth()`. User endpoints then read `$_SESSION['user_id']` (not set for admins), causing PHP Warnings and queries with `user_id = NULL`.

**Fix:** User-facing endpoints should require `$_SESSION['user_id']` explicitly.

---

#### SEC-07 — Admin Authentication Check via localStorage `STILL PRESENT`

**File:** `admin/editproduct.php:552`

```javascript
const adminSession = localStorage.getItem('adminSession');
if (!adminSession) { window.location.href = 'admin-login.php'; return; }
```

Client-side-only check — bypassable by any user. PHP session check is the real gate but this pattern misleads future developers.

**Fix:** Remove localStorage auth checks. Rely only on PHP session validation.

---

#### SEC-08 — Password Reset Token Exposed in GET URL

**Files:** `reset-password.php`, `api/auth/forgot-password.php`

Token and email appear in URL query parameters — visible in browser history, server access logs, and Referer headers.

**Fix:** Submit token via POST body, or use a one-time URL that immediately stores token in session on first load.

---

#### SEC-09 — Dynamic Inline `onclick` Handlers in Admin JS `STILL PRESENT`

**File:** `admin/admin-dashboard.js:951`

```javascript
btn.setAttribute('onclick', `toggleUserBlock(${userId}, this, ${newBlockedState})`);
```

**Fix:** Use data attributes and event delegation:
```javascript
btn.dataset.userId = userId;
btn.dataset.blocked = newBlockedState;
btn.addEventListener('click', handleToggleBlock);
```

---

### MEDIUM

#### SEC-10 — Debug `console.log` in Production `STILL PRESENT`

**File:** `admin/admin-login.php:115` — `console.log("Login submitted")` left in production code.

#### SEC-11 — `api/auth/session.php` Returns HTTP 200 When Unauthenticated `STILL PRESENT`

Returns `{"status":"error","message":"Not authenticated"}` with HTTP 200 instead of 401.

#### SEC-12 — User Profile Endpoints Return HTTP 200 on Auth Failure `STILL PRESENT`

**Files:** `api/user/profile.php`, `api/user/update_profile.php` — return HTTP 200 with error body instead of 401.

#### SEC-13 — No Rate Limiting on Signup

Login is rate-limited (5 attempts / 10-minute lockout). Signup is not.

---

## 4. BROKEN / NON-FUNCTIONAL FEATURES

### ~~BRK-01 — Search Page~~ `FIXED`

`search.php` now implements product search with category, price, and sort filters.

---

### ~~BRK-02 — Wishlist Page~~ `FIXED`

`wishlist.php` now implements localStorage-based wishlist with add-to-cart integration.

---

### BRK-03 — Payment Gateway (Card / UPI) `STILL MISSING`

`checkout.php` shows Card and UPI with a "Coming Soon" notice. Only COD is functional. **Digital products are effectively unpurchasable** — COD is blocked for digital, no payment alternative exists.

---

### BRK-04 — Google OAuth Login `STILL STUB`

**File:** `script.js:2295, 2301`

```javascript
// TODO: Implement Google OAuth
window.handleGoogleLogin = function() { showToast('Google login coming soon...', 'info'); }
```

No OAuth flow, no client ID, no backend handler.

---

### BRK-05 — Admin Analytics Tab `STILL STUB`

The Analytics tab shows "coming soon." No revenue charts or data.

---

### BRK-06 — Phone / OTP Login `STILL STUB`

Phone/OTP login UI exists but displays "coming soon." No backend OTP endpoint.

---

## 5. PERFORMANCE ISSUES

### PERF-01 — N+1 Query in Order History `STILL PRESENT`

**File:** `api/order/get.php`

For N orders: 1 + N database round-trips. With 50 orders = 51 queries per API call.

**Fix:** Use a single JOIN:
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

Returns all columns (`SELECT *`) for all active products. Also exposes `commercial_price` publicly — a pricing data leak.

---

### ~~PERF-03 — INNER JOIN Breaks Order History on Product Deletion~~ `FIXED`

`api/order/get.php` now uses `LEFT JOIN` — deleted products no longer cause order history rows to disappear.

---

### PERF-04 — No Database Indexes Beyond Primary Keys `STILL MISSING`

All queries on `user_id`, `order_id`, `status`, `created_at` perform full table scans.

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

1,000+ lines of inline `<style>` CSS in the PHP file — no caching benefit.

---

## 6. DATABASE ISSUES

### DB-01 — `product_type` Column `RESOLVED`

The conflicting `product_type` column is not present in the current schema. Only `available_type ENUM('physical','digital','both')` exists.

---

### DB-02 — No FK Constraint on `password_reset_tokens.user_id`

Deleting a user leaves orphaned reset tokens.

**Fix:**
```sql
ALTER TABLE password_reset_tokens
ADD CONSTRAINT fk_prt_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

---

### DB-03 — `order_items.product_id` FK Without `ON DELETE` Action

Defaults to RESTRICT — silently prevents product deletion when orders exist.

**Recommended:** `ON DELETE SET NULL` — allows deletion while preserving order history rows.

---

### DB-04 — `orders` Table Has No `updated_at` Column `STILL MISSING`

No timestamp for when order status last changed.

**Fix:**
```sql
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

### DB-05 — Test Data Present in Database

- User: `ts_admin@test.uxpacific.local`
- Product: `TS_Product_setup` (empty `image` field)

Must be deleted before production.

---

### DB-06 — `orders.shipping_address` Stored as JSON String

Full address in a TEXT column — unqueryable by city/state/country.

---

### DB-07 — Product Name Typo in Live Data

Product id=31 is named **"Webiste Template"** (missing 'r').

**Fix:** `UPDATE products SET name = 'Website Template' WHERE id = 31;`

---

### DB-08 — No Soft Delete on Products

Products are hard-deleted. Combined with any INNER JOIN, deleting a product corrupts order history for customers who purchased it.

---

## 7. CODE QUALITY ISSUES

### CQ-01 — Inconsistent API Response Formats `STILL PRESENT`

`api/admin/order/list.php:45` uses `echo json_encode($orders)` — no `sendResponse()` wrapper, direct array output, inconsistent with all other endpoints.

---

### CQ-02 — Inconsistent HTTP Status Codes `STILL PRESENT`

| Endpoint | Issue |
|---|---|
| `api/auth/session.php` | Returns 200 when unauthenticated (should be 401) |
| `api/user/profile.php` | Returns 200 on auth failure (should be 401) |
| `api/user/update_profile.php` | Returns 200 on auth failure (should be 401) |

---

### CQ-03 — `checkAdminAuth()` Always Returns True `STILL PRESENT`

**File:** `admin/admin-dashboard.js:16-18`

```javascript
function checkAdminAuth() {
  return true;
}
```

Vestigial function — creates false sense of security.

---

### CQ-04 — Debug Statements in Production Code `STILL PRESENT`

| File | Detail |
|---|---|
| `script.js` | 10 `console.error` / `console.warn` calls |
| `admin/admin-dashboard.js` | 16 `console.error` calls |
| `admin/admin-login.php:115` | `console.log("Login submitted")` — HIGH severity |

---

### CQ-05 — Commented-Out Dead Code in `script.js`

Lines 56–77 and 82–90 contain commented-out product filtering and mobile menu logic.

---

### CQ-06 — `loadCartPage()` is 180+ Lines

Single function handles cart fetching, rendering, event binding, and order summary — needs decomposing.

---

### CQ-07 — Unimplemented Feature TODO Comments

**File:** `script.js:2295, 2301` — two identical `// TODO: Implement Google OAuth` with no tracking.

---

### CQ-08 — `admin/addproduct.php` Duplicates Dashboard Functionality

Standalone add-product form duplicates the feature in the admin dashboard — these two implementations will drift.

---

## 8. HTTP ENDPOINT STATUS

### Frontend Pages

| URL | Status | Notes |
|---|---|---|
| `/` (index.php) | 200 | Homepage |
| `/shopAll.php` | 200 | Product listing |
| `/search.php` | 200 | ✅ Now functional |
| `/wishlist.php` | 200 | ✅ Now functional |
| `/signin.php` | 200 | Login form |
| `/signup.php` | 200 | Registration form |
| `/checkout.php` | 200 | COD only |
| `/forgot-password.php` | 200 | Password reset |
| `/admin/admin-login.php` | 200 | Admin login |
| `/admin/admin-dashboard.php` | 302 → admin-login | PHP session gate |

### Sensitive Files (`.htaccess` protection)

| URL | Status | Notes |
|---|---|---|
| `/includes/config.php` | 403 | BLOCKED — correct |
| `/.env` | 403 | BLOCKED — correct |
| `/migrations/*.sql` | 403 | BLOCKED — correct |
| `/tests/test_suite.php` | **200** | **NOT BLOCKED — security issue** |

### API Endpoints (unauthenticated)

| Endpoint | Expected | Actual | Notes |
|---|---|---|---|
| `GET /api/product/list.php` | 200 | 200 | Public — correct |
| `GET /api/cart/list.php` | 401 | 401 | Correct |
| `GET /api/order/get.php` | 401 | 401 | Correct |
| `GET /api/user/profile.php` | 401 | **200** | Bug — returns error body with 200 |
| `GET /api/admin/user/list.php` | 401 | 401 | Correct |
| `GET /api/auth/session.php` | 401 | **200** | Bug — returns error body with 200 |

---

## 9. DEAD CODE

| File | Issue |
|---|---|
| `admin/addproduct.php` | Parallel add-product form, duplicated in dashboard |
| `migrations/setup_db.php` | One-time migration left in webroot |
| `migrations/check_schema.php` | Dev utility |
| `migrations/fix_user_id.php` | One-time migration |
| `migrations/enforce_user_id.php` | One-time migration |
| `migrations/add_missing_product_columns.php` | One-time migration |
| `script.js:56-90` | Commented-out filter and mobile menu code |
| `admin-dashboard.js:checkAdminAuth()` | Always returns `true`; vestigial |

---

## 10. ERROR LOG REVIEW

**File:** `logs/app_errors.log`

### Active PHP Warning

```
PHP Warning: Undefined index: user_id in api/order/get.php on line 7
```

**Cause:** `requireAuth()` passes for admin sessions but `$_SESSION['user_id']` is not set for admins. Line 7 reads it without null-coalescing. Query runs with `user_id = NULL`.

**Status:** Active — fires on every request to this endpoint from an authenticated admin session.

**Fix:**
```php
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
3. **Block `/tests/` in `.htaccess`** — `RewriteRule ^tests(/|$) - [F,L]`
4. **Fix `requireAuth()`** — user endpoints must reject admin sessions; require `$_SESSION['user_id']` explicitly
5. **Fix `api/order/get.php`** — add explicit `user_id` check (resolves active PHP Warning)
6. **Clean test data from DB** — delete `ts_admin@test.uxpacific.local` and `TS_Product_setup`

### Priority 2 — Before Launch

7. Add CSRF token validation to all cart, address, and contact endpoints
8. Add a real payment gateway (Stripe or Razorpay) to enable digital product purchases
9. Fix XSS in admin dashboard — `escapeHtml(category)` in `admin-dashboard.js:281`
10. Fix HTTP status codes — all auth failures must return 401
11. Add `LIMIT`/`OFFSET` pagination to `api/product/list.php`
12. Remove `commercial_price` from public product list API response
13. Add recommended DB indexes (see PERF-04)
14. Add FK on `password_reset_tokens.user_id` with `ON DELETE CASCADE`
15. Add `updated_at` column to `orders` table

### Priority 3 — Post-Launch Polish

16. Fix N+1 query in order history with a JOIN
17. Implement soft delete on products
18. Remove all debug `console.log/error` from frontend JS
19. Remove dead code (commented JS, migration scripts in webroot, `addproduct.php`)
20. Decompose `loadCartPage()` and other 100+ line functions
21. Normalize `shipping_address` out of JSON TEXT
22. Fix product name typo: `UPDATE products SET name='Website Template' WHERE id=31;`
23. Add `updated_at` / order status audit trail
24. Implement analytics tab
25. Remove localStorage admin auth check in `admin/editproduct.php`
26. Convert inline `onclick` attributes to event delegation in admin JS
27. Implement Google OAuth or remove placeholder UI
28. Fix `checkAdminAuth()` function (remove or make real)

---

## 12. PRODUCTION BLOCKERS

The following **7 issues** must be resolved before this application handles real users or payment data:

| # | Blocker | Severity |
|---|---|---|
| 1 | SMTP password in `.env` is in git history — credentials must be rotated | CRITICAL |
| 2 | MySQL root with no password — replace with restricted application user | CRITICAL |
| 3 | `/tests/test_suite.php` accessible via HTTP — executes test suite against live DB | CRITICAL |
| 4 | Digital products cannot be purchased — COD blocked, no working payment alternative | HIGH |
| 5 | `api/order/get.php` emits PHP Warnings and returns wrong data for admin sessions | HIGH |
| 6 | No CSRF protection on cart mutation endpoints | HIGH |
| 7 | Stored XSS in admin dashboard via product category field | HIGH |

*Resolved since last audit: search.php non-functional (BRK-01), wishlist.php non-functional (BRK-02) — removed from blockers.*

---

*Report updated by Claude Code — 2026-03-25*
*Previous audit: 2026-02-19 · Score was 61/100 · Now 65/100*
*Methodology: static file analysis · DB schema inspection · git log review · error log review · JS/CSS/PHP audit*
