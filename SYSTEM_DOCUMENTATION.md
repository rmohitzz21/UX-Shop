# System Documentation: UX Merchandise

## 1️⃣ Project Overview

**System Architecture:** API-First Monolithic Application  
**Tech Stack:**
*   **Backend:** Core PHP (Vanilla, No Framework)
*   **Database:** MySQL (InnoDB Engine)
*   **Frontend:** HTML5, CSS3, JavaScript (Vanilla) + AJAX
*   **Server environment:** Apache (XAMPP)

**Key Features:**
*   **API-Driven:** The frontend communicates with the backend exclusively via JSON APIs.
*   **Session Management:** Secure PHP sessions with role-based access control.
*   **Transaction Safety:** Complex operations like Order Creation use Database Transactions.
*   **Responsive Design:** Mobile-first approach for the storefront.

---

## 2️⃣ Folder Structure Explanation

| Directory | Purpose |
| :--- | :--- |
| **`/api/`** | Contains all backend logic endpoints, organized by domain (`auth`, `cart`, `order`, `product`, `admin`). |
| **`/includes/`** | Core system files like `config.php` (Database, Session settings) and `helpers.php` (Auth middleware). |
| **`/admin/`** | Admin panel frontend interface. |
| **`/assets/`** | Static assets (CSS, JS, Fonts). |
| **`/img/`** | Uploaded product images and site graphics. |
| **Root (`/`)** | Public-facing pages (`index.php`, `shopAll.php`, `checkout.php`, `signup.php`). |

---

## 3️⃣ Database Structure

### **1. Users Table (`users`)**
Stores customer and admin account data.
*   `id`: Primary Key
*   `email`: Unique index
*   `password_hash`: Bcrypt hashed password
*   `role`: ENUM('customer', 'admin')
*   `is_blocked`: Boolean for ban system

### **2. Products Table (`products`)**
Inventory management.
*   `id`: Primary Key
*   `name`, `description`, `price`, `stock`
*   `available_type`: ENUM('physical', 'digital', 'both')
*   `commercial_price`: Optional pricing for commercial licenses
*   `image`: Path to main image

### **3. Cart Table (`cart`)**
Persistent shopping cart for logged-in users.
*   `user_id`: Foreign Key -> users
*   `product_id`: Foreign Key -> products
*   `quantity`: Integer
*   `size`: Selected size variant

### **4. Orders Table (`orders`)**
Order records.
*   `id`: Primary Key
*   `order_number`: Unique string (e.g., UXP-2026-X)
*   `user_id`: Foreign Key -> users
*   `total`, `subtotal`, `tax`, `shipping`: Financials
*   `status`: ENUM('Pending', 'Processing', 'Shipped')
*   `shipping_address`: JSON column storing snapshot of address at checkout

### **5. Order Items Table (`order_items`)**
Individual items within an order.
*   `order_id`: Foreign Key -> orders
*   `product_id`: Foreign Key -> products
*   `size`: Snapshot of selected size
*   `price`: Snapshot of price at purchase time
*   `product_name`: Snapshot of product name at purchase time
*   `product_image`: Snapshot of product image path

### **6. Addresses Table (`addresses`)**
Saved shipping/billing addresses for users.
*   `id`: Primary Key
*   `user_id`: Foreign Key -> users (CASCADE delete)
*   `first_name`, `last_name`: Recipient name
*   `address_line1`, `address_line2`: Street address
*   `city`, `state`, `zip_code`, `country`: Location
*   `phone`: Contact number
*   `label`: User-defined label (Home, Work, etc.)
*   `address_type`: ENUM('shipping', 'billing', 'both')
*   `is_default`: Boolean flag for default address

---

## 4️⃣ Complete Workflow Explanation

### **👤 User Workflow**
1.  **Signup:** User submits form -> `api/auth/signup.php` validates -> Hashes password -> Inserts to DB -> Returns Success.
2.  **Login:** User submits credentials -> `api/auth/login.php` verifies hash -> Starts PHP Session (`$_SESSION['user_id']`).
3.  **Shopping:** User browses products -> Clicks "Add to Cart" -> `api/cart/add.php` checks session -> Inserts/Updates `cart` table.
4.  **Checkout:**
    *   Frontend sends cart data + address to `api/order/create.php`.
    *   **Backend Transaction Starts:**
    *   Validates Stock (LOCK rows).
    *   Calculates Totals (Server-side trust).
    *   Creates Order.
    *   Moves Cart items to Order Items.
    *   Clears Cart.
    *   Updates Product Stock.
    *   **Commit Transaction.**

### **👑 Admin Workflow**
1.  **Login:** `api/auth/admin-login.php` checks credentials AND `role='admin'`.
2.  **Product Management:**
    *   **Create:** `api/admin/product/create.php` uploads image -> Validates inputs -> Inserts to DB.
    *   **Update:** `api/admin/product/update.php` updates details.
3.  **Order Management:**
    *   admins can view all orders via `api/admin/order/list.php`.
    *   admins can update status (e.g., Pending -> Shipped) via `api/admin/order/update_status.php`.

---

## 5️⃣ Security Implementation Details

### **1. Authentication & Session**
*   **Password Storage:** Uses `password_hash()` (Bcrypt). Interactive login uses `password_verify()`.
*   **Session Handling:** `config.php` configures secure session cookies (`HttpOnly`, `SameSite` policies recommended).
*   **Role-Based Access Control (RBAC):** Middleware `requireAdmin()` and `requireAuth()` in `includes/helpers.php` protect sensitive APIs.

### **2. SQL Injection Prevention**
*   **Prepared Statements:** Used in 95% of the codebase (Login, Signup, Order Creation, Product Management).
*   **Input Sanitization:** Where prepared statements aren't strictly used (e.g., some cart logic), `intval()` and `real_escape_string` are applied.

### **3. CSRF Protection**
*   **Token Generation:** CSRF tokens are generated on session start.
*   **Verification:** APIs like `login.php` and `product/create.php` have logic to verify `$_POST['csrf_token']` against the session token.

### **4. Rate Limiting**
*   **Login Protection:** `api/auth/login.php` tracks failed attempts. 5 failed attempts result in a 10-minute lockout.

---

## 6️⃣ API Architecture Explanation

**Standard Response Format (JSON):**
```json
{
  "status": "success", "error",
  "message": "Human readable message",
  "data": { ... } // Optional payload
}
```

**Status Codes:**
*   `200 OK`: Success
*   `201 Created`: Resource created (Signup, Order)
*   `400 Bad Request`: Validation failure
*   `401 Unauthorized`: Not logged in
*   `403 Forbidden`: Logged in but insufficient permissions (e.g., User trying to access Admin)
*   `500 Server Error`: Database or execution failure

---

## 7️⃣ Production Readiness Status

**Security Score:** 8/10
*   ✅ Strong Auth & Hash
*   ✅ Role segregation
*   ✅ Rate limiting
*   ⚠️ **Recommendation:** Enforce HTTPS strictly in production.

**Code Quality:** 7.5/10
*   ✅ Modular structure
*   ✅ Consistent naming
*   ⚠️ **Refactor:** Standardize all SQL queries to Prepared Statements (Cart module).

**Scalability:** Medium
*   Database transactions ensure data integrity under load.
*   Session storage is file-based (default PHP); for high scale, move to Redis.

---

## 8️⃣ Future Enhancements

1.  **Email Verification:** Integrate PHPMailer to verify emails upon signup.
2.  **Payment Gateway:** Replace dummy checkout with Stripe/Razorpay integration.
3.  **API Versioning:** Introduce `/api/v1/` structure for long-term maintenance.
4.  **Frontend Framework:** Migrate frontend to React/Vue for a true Single Page Application (SPA) experience while keeping this robust backend.

---

## 9️⃣ Address Management System

> **Full Design Document:** See [`docs/ADDRESS_MANAGEMENT_SYSTEM.md`](docs/ADDRESS_MANAGEMENT_SYSTEM.md) for complete implementation details.

### **Overview**
The Address Management System enables users to save, manage, and reuse shipping addresses during checkout.

### **Current Status: Issues Identified**

| Issue | Location | Status |
|-------|----------|--------|
| `saveAddress` INSERT uses wrong column names | `api/order/create.php:205` | 🔴 Bug |
| No address selection UI at checkout | `checkout.php` | 🟡 Missing |
| No address edit (update) endpoint | `api/address/` | 🟡 Missing |
| No set-default endpoint | `api/address/` | 🟡 Missing |

### **Address API Endpoints**

| Endpoint | Method | Status | Description |
|----------|--------|--------|-------------|
| `/api/address/get.php` | GET | ✅ Working | List user's addresses |
| `/api/address/add.php` | POST | ✅ Working | Add new address |
| `/api/address/update.php` | POST | 🆕 Planned | Edit existing address |
| `/api/address/delete.php` | POST | ✅ Working | Delete address |
| `/api/address/set-default.php` | POST | 🆕 Planned | Set default address |

### **Checkout Integration Plan**

1. **Fetch saved addresses** on checkout page load
2. **Display address selector** (radio cards) if user has saved addresses
3. **Pre-select default address** automatically
4. **"Use different address"** option shows manual entry form
5. **"Save address" checkbox** for new addresses
6. **Order creation** accepts `savedAddressId` OR manual shipping data

### **Database Migration Required**

```sql
-- migrations/001_enhance_addresses_table.sql
ALTER TABLE `addresses`
  ADD COLUMN `label` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both';
```

### **Key Files to Modify**

| File | Changes |
|------|---------|
| `api/order/create.php` | Fix saveAddress bug (line 205), add savedAddressId support |
| `api/address/add.php` | Add label, addressType parameters |
| `checkout.php` | Add address selector HTML section |
| `script.js` | Add address fetch/render functions, modify handleCheckout |
| `style.css` | Add address card styles |
| `account.php` | Enhance addresses tab with edit/default functionality |

