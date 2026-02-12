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
