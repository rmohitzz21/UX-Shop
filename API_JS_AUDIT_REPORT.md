# API Integration Audit Report

## 1. Executive Summary
A comprehensive audit of the application's API endpoints and JavaScript integration was conducted to ensure the backend serves as the **Single Source of Truth** for all business logic, data validation, and security enforcement. Several critical vulnerabilities were identified and remediated, specifically regarding order total calculation, admin authorization, and user account management.

## 2. Critical Findings & Remediation

### A. Order Calculation Integrity (Critical)
*   **Issue:** The `api/order/create.php` endpoint blindly accepted the `total`, `price`, and `subtotal` values sent from the frontend. A malicious user could modify the payload to purchase items for free or incorrect prices.
*   **Fix:** Refactored `api/order/create.php` to:
    *   Ignore frontend totals.
    *   Fetch product prices and stock directly from the database using individual item IDs.
    *   Perform server-side calculation of Subtotal, Tax (18%), Shipping ($50 flat rate), and Grand Total.
    *   Enforce stock validation and deduction within a database transaction.
    *   Return the authoritative calculated totals to the frontend.
*   **JS Update:** Updated `script.js` (`handleCheckout`) to prioritize the server-returned totals for the Order Confirmation page, resolving data consistency issues.

### B. Admin Authorization Bypass (Critical)
*   **Issue:** The following Admin APIs lacked a check for the `admin` role, allowing any logged-in user (or even unauthenticated users in some cases) to perform admin actions:
    *   `api/admin/product/create.php`
    *   `api/admin/product/update.php`
    *   `api/admin/product/delete.php`
*   **Fix:** Added strict session validation `if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')` to all listed endpoints.
*   **Logic Fix:** Corrected a logic error in `api/admin/order/delete.php` where the condition `=== 'admin'` prevented admins from deleting orders. It is now `!== 'admin'`.

### C. Account Deletion Security (High)
*   **Issue:** The "Delete Account" feature in `account.php` was implemented purely in JavaScript, only clearing `localStorage` and redirecting the user. The user's account and data remained in the database.
*   **Fix:**
    *   Restored and secured `api/user/delete_account.php`.
    *   Implemented server-side password verification before deletion.
    *   Added a check to prevent deletion if the user has active (pending/processing) orders.
    *   Updated `account.php` to prompt for a password and call this secure API.

## 3. API & Integration Status

| Endpoint | Status | Security Check | Logic source | Details |
| :--- | :---: | :---: | :---: | :--- |
| `api/auth/login.php` | ✅ Secure | Session Set | DB | Verifies credentials, sets PHP session. |
| `api/user/update_password.php` | ✅ Secure | Session ID | DB | Verifies old password hash. |
| `api/user/delete_account.php` | ✅ Secure | Session + Pwd | DB | Verifies password, checks active orders. |
| `api/user/update_profile.php` | ✅ Secure | Session ID | DB | Validates input. |
| `api/order/create.php` | ✅ Secure | Session ID | **Server** | Calculates all totals, updates stock. |
| `api/admin/product/*` | ✅ Secure | **Role: Admin** | DB | CRUD operations now fully protected. |
| `api/admin/order/delete.php` | ✅ Secure | **Role: Admin** | DB | Permissions fixed. |

## 4. Remaining Risks & Technical Debt

### Address Management
*   **Current State:** User addresses are currently managed via `localStorage` on the frontend for the Account page. While `api/order/create.php` saves a snapshot of the shipping address to a DB table (`addresses`) during checkout, there is no active API for the frontend to *retrieve* or *manage* these saved addresses across devices.
*   **Risk:** Low security risk (as checkout validates input), but poor User Experience (addresses don't sync across devices).
*   **Recommendation:** Re-implement `api/address/get.php`, `create.php`, `delete.php` and update `account.php` / `checkout.php` to sync with the database.

### Tax & Shipping Logic
*   **Current State:** Tax (18%) and Shipping ($50) are hardcoded in both JS (for display) and PHP (for billing).
*   **Risk:** Maintenance overhead. If rates change, both files must be updated.
*   **Recommendation:** Expose a configuration API (e.g., `api/config/rates.php`) so the frontend can fetch current rates, ensuring synchronization.

## 5. Conclusion
The application's core transactional and administrative security has been significantly hardened. The backend now rightfully acts as the Single Source of Truth for pricing, inventory, and authorization. The frontend has been updated to reflect these authoritative states, ensuring users see accurate information and are protected from client-side manipulation risks.
