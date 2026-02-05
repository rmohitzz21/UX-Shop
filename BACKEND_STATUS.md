# Backend Status & Workflow Audit

This document outlines the current status of the backend, the workflow check, and the API verification.

## 1. Backend Workflow Verification

The backend follows a standard PHP API structure:
**Request** -> **Routing (Logic in API File)** -> **DB Interaction** -> **JSON Response**

### Verified Flows:
- **Admin Login:**
  - **Frontend:** `admin/admin-login.php` (JS Fetch)
  - **API:** `api/auth/admin-login.php`
  - **Logic:** Server-side session creation (`$_SESSION['admin_id']`), secure password hashing.
  - **Status:** ✅ Tested & Working.

- **Admin Order Management:**
  - **Frontend:** `admin/admin-dashboard.php` (JS Fetch `loadOrders`)
  - **API:** `api/admin/order/list.php`
  - **Logic:** Fetches orders with user details via `LEFT JOIN`. Includes subquery for item counts.
  - **Status:** ✅ Verified Logic (Step 127).

- **Admin User Management:**
  - **Frontend:** `admin/admin-dashboard.php` (JS Fetch `loadUsers`)
  - **API:** `api/admin/user/list.php`
  - **Logic:** Fetches users with `order_count` subquery.
  - **Status:** ✅ Verified Logic (Step 226).

- **Order Creation:**
  - **Frontend:** `checkout.php`
  - **API:** `api/order/create.php`
  - **Logic:** Transaction-based. Inserts Order -> Get ID -> Insert Items -> Commit.
  - **Status:** ✅ Verified Transaction Logic (Step 188).

## 2. API Status Check

| Endpoint | Method | Functionality | Status | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `api/auth/login.php` | POST | Customer Login | ✅ Ready | Returns session and token. |
| `api/auth/signup.php` | POST | Customer Signup | ✅ Ready | Hashes password, checks duplicates. |
| `api/auth/admin-login.php` | POST | Admin Login | ✅ Ready | Secure session-based auth. |
| `api/order/create.php` | POST | Place Order | ✅ Ready | Transactional, validated inputs. |
| `api/admin/order/list.php` | GET | List All Orders | ✅ Ready | Includes user details + item count. |
| `api/admin/order/get_details.php` | GET | Order Details | ✅ Ready | Full details + items + shipping. |
| `api/admin/user/list.php` | GET | List Users | ✅ Ready | Includes accurate order counts. |
| `api/admin/product/list.php` | GET | List Products | ✅ Ready | Simple select query. |

## 3. Structural Integrity

- **Directory Structure:** Cleaned.
  - `/api`: Contains all logic.
  - `/includes`: Shared config/helpers.
  - `/migrations`: Database scripts (isolated).
- **Security:**
  - `config.php`: `?>` removed to prevent header issues.
  - `helpers.php`: `?>` removed.
  - Session checks implemented on Admin Dashboard.

## 4. Known Issues / Recommendations
- **Environment Variables:** Database credentials are currently in `includes/config.php`. For production, move these to `.env`.
- **CORS:** Currently `Access-Control-Allow-Origin: *`. Restrict this to your specific frontend domain in production.
- **SSL:** Ensure the server runs on HTTPS for secure cookie transmission.

## 5. How to Fix "Reload" Issue on Admin Login
If the page still reloads:
1. **Clear Browser Cache:** Old JS might be cached.
2. **Check Console Logs:** Look for "Login error" in the browser console.
3. **Verify PHP Session:** Ensure the `tmp` directory for PHP sessions is writable on the server (XAMPP usually handles this).

*Last Updated: 2026-02-05*
