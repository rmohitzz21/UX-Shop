# Address Management System — Complete Design Document

## Executive Summary

This document outlines a production-ready Address Management System for UX Merchandise e-commerce platform. The system enables users to save, manage, and reuse shipping addresses during checkout, improving UX and reducing cart abandonment.

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [Issues Identified](#2-issues-identified)
3. [Proposed Architecture](#3-proposed-architecture)
4. [Database Schema](#4-database-schema)
5. [API Endpoints](#5-api-endpoints)
6. [Frontend Integration](#6-frontend-integration)
7. [Checkout Flow Changes](#7-checkout-flow-changes)
8. [Migration Strategy](#8-migration-strategy)
9. [Security Considerations](#9-security-considerations)
10. [Testing Checklist](#10-testing-checklist)

---

## 1. Current State Analysis

### 1.1 Existing Flow (Signin → Checkout)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CURRENT USER JOURNEY                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. SIGNUP/SIGNIN                                                            │
│     └─> api/auth/signup.php (creates user with email, name, phone)          │
│     └─> api/auth/login.php (sets $_SESSION['user_id'])                      │
│                                                                              │
│  2. BROWSE & ADD TO CART                                                     │
│     └─> api/cart/add.php (inserts to `cart` table)                          │
│     └─> Cart persists via user_id (logged-in) or localStorage (guest)       │
│                                                                              │
│  3. CHECKOUT (checkout.php)                                                  │
│     └─> User MANUALLY enters shipping address every time ❌                  │
│     └─> No saved address selection UI                                        │
│     └─> Form fields: firstName, lastName, email, phone,                      │
│                      address, city, state, zip, country                      │
│                                                                              │
│  4. ORDER CREATION (api/order/create.php)                                    │
│     └─> Address stored as JSON blob in `orders.shipping_address`            │
│     └─> Optional "saveAddress" flag exists BUT IS BROKEN ❌                  │
│                                                                              │
│  5. ORDER CONFIRMATION                                                       │
│     └─> Displays order summary from localStorage.lastOrder                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Existing Database Tables

**`addresses` table (current schema):**
```sql
id              INT PRIMARY KEY
user_id         INT FK -> users.id
first_name      VARCHAR(50)
last_name       VARCHAR(50)
address_line1   VARCHAR(255)    -- ⚠️ Note: column name
address_line2   VARCHAR(255)
city            VARCHAR(100)
state           VARCHAR(100)
zip_code        VARCHAR(20)     -- ⚠️ Note: column name
country         VARCHAR(100)
phone           VARCHAR(20)
is_default      TINYINT(1)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**`orders.shipping_address` (JSON blob format):**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@example.com",
  "phone": "+919876543210",
  "address": "123 Main St",       // ⚠️ Different key name
  "city": "Mumbai",
  "state": "Maharashtra",
  "zip": "400001",                // ⚠️ Different key name
  "country": "IN"
}
```

### 1.3 Existing API Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `api/address/get.php` | GET | ✅ Working | Returns all user addresses |
| `api/address/add.php` | POST | ✅ Working | Maps `address`→`address_line1`, `zip`→`zip_code` |
| `api/address/delete.php` | POST | ✅ Working | IDOR protected |
| `api/address/update.php` | — | ❌ Missing | No edit capability |
| `api/address/set-default.php` | — | ❌ Missing | No default toggle |

---

## 2. Issues Identified

### 2.1 Critical Bugs 🔴

| Issue | Location | Impact |
|-------|----------|--------|
| **saveAddress INSERT uses wrong columns** | `api/order/create.php:205` | Trying to INSERT `address`, `zip` but table has `address_line1`, `zip_code` — causes silent failure |
| **No address selection at checkout** | `checkout.php` | Users must re-enter address every order — poor UX |

### 2.2 Missing Features 🟡

| Feature | Impact |
|---------|--------|
| Address editing (update) | Users must delete & re-add to fix typos |
| Set default address | Manual workaround only |
| Address labels (Home, Work, etc.) | No organization for multiple addresses |
| Billing vs Shipping address | Currently only shipping supported |
| Address selection UI at checkout | Forces manual entry |
| Auto-populate from default address | Lost efficiency gain |

### 2.3 Schema Inconsistencies 🟠

```
FIELD NAME MAPPING CHAOS:
─────────────────────────────────────────────────────
Context              │ Address Field  │ ZIP Field
─────────────────────────────────────────────────────
addresses table      │ address_line1  │ zip_code
Checkout form (HTML) │ address        │ zip
Order JSON blob      │ address        │ zip
api/address/add.php  │ address → address_line1 (mapped)
api/order/create.php │ address (wrong column name)
─────────────────────────────────────────────────────
```

---

## 3. Proposed Architecture

### 3.1 Target Flow (After Implementation)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NEW USER JOURNEY                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. CHECKOUT (checkout.php)                                                  │
│     ├─> API call: GET api/address/get.php                                   │
│     │                                                                        │
│     ├─> IF user has saved addresses:                                        │
│     │   └─> Show "Saved Addresses" selector (radio cards)                   │
│     │   └─> Default address pre-selected                                    │
│     │   └─> Option: "Use a different address" (expands form)                │
│     │                                                                        │
│     └─> IF no saved addresses:                                               │
│         └─> Show manual entry form                                           │
│         └─> Checkbox: "Save this address for future orders"                 │
│                                                                              │
│  2. ADDRESS MANAGEMENT (account.php → Addresses tab)                        │
│     ├─> List all saved addresses                                            │
│     ├─> Add new address (modal/inline form)                                 │
│     ├─> Edit address (modal/inline form)                                    │
│     ├─> Delete address (with confirmation)                                  │
│     └─> Set as default (toggle)                                             │
│                                                                              │
│  3. ORDER CREATION                                                           │
│     ├─> If savedAddressId provided: fetch and embed full address            │
│     └─> If new address: use form data (optionally save)                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Component Interaction Diagram

```
┌──────────────────┐      ┌───────────────────┐      ┌─────────────────┐
│   checkout.php   │      │   account.php     │      │    API Layer    │
│   (Checkout)     │      │   (My Account)    │      │                 │
└────────┬─────────┘      └─────────┬─────────┘      └────────┬────────┘
         │                          │                         │
         │  GET /api/address/get    │                         │
         │─────────────────────────────────────────────────────>
         │                          │                         │
         │  <── addresses[] ────────────────────────────────────
         │                          │                         │
         │                          │ POST /api/address/add   │
         │                          │─────────────────────────>
         │                          │                         │
         │                          │ POST /api/address/update│
         │                          │─────────────────────────>
         │                          │                         │
         │                          │ POST /api/address/delete│
         │                          │─────────────────────────>
         │                          │                         │
         │                          │ POST /api/address/set-default
         │                          │─────────────────────────>
         │                          │                         │
         │  POST /api/order/create  │                         │
         │  {savedAddressId: 5}     │                         │
         │─────────────────────────────────────────────────────>
         │                          │                         │
```

---

## 4. Database Schema

### 4.1 Enhanced `addresses` Table

```sql
-- Migration: migrations/enhance_addresses_table.sql

-- Add new columns (non-breaking)
ALTER TABLE `addresses`
  ADD COLUMN `label` VARCHAR(50) DEFAULT NULL
    COMMENT 'User-defined label: Home, Work, Office, etc.'
    AFTER `phone`,
  ADD COLUMN `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both'
    COMMENT 'Address purpose'
    AFTER `label`;

-- Add index for faster lookups
ALTER TABLE `addresses`
  ADD INDEX `idx_user_default` (`user_id`, `is_default`),
  ADD INDEX `idx_user_type` (`user_id`, `address_type`);
```

### 4.2 Final Schema

```sql
CREATE TABLE `addresses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `zip_code` VARCHAR(20) NOT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'IN',
  `phone` VARCHAR(20) NOT NULL,
  `label` VARCHAR(50) DEFAULT NULL,              -- NEW: Home, Work, etc.
  `address_type` ENUM('shipping','billing','both') DEFAULT 'both',  -- NEW
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_default` (`user_id`, `is_default`),
  KEY `idx_user_type` (`user_id`, `address_type`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.3 Orders Table (No Changes Needed)

The `orders.shipping_address` TEXT column storing JSON is already flexible. We'll continue storing a snapshot at order time for historical accuracy.

---

## 5. API Endpoints

### 5.1 Complete Endpoint Specification

#### `GET api/address/get.php` ✅ (Existing — Minor Enhancement)

**Purpose:** Retrieve all addresses for authenticated user

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "address_line1": "123 Main Street",
      "address_line2": "Apt 4B",
      "city": "Mumbai",
      "state": "Maharashtra",
      "zip_code": "400001",
      "country": "IN",
      "phone": "+919876543210",
      "label": "Home",
      "address_type": "both",
      "is_default": 1,
      "created_at": "2026-03-01 10:00:00"
    }
  ]
}
```

---

#### `POST api/address/add.php` ✅ (Existing — Add Label/Type)

**Request:**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "address": "123 Main Street",
  "address2": "Apt 4B",
  "city": "Mumbai",
  "state": "Maharashtra",
  "zip": "400001",
  "country": "IN",
  "phone": "+919876543210",
  "label": "Home",
  "addressType": "shipping",
  "isDefault": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Address added successfully",
  "data": { "id": 5 }
}
```

---

#### `POST api/address/update.php` 🆕 NEW

**Purpose:** Update existing address

**Request:**
```json
{
  "id": 5,
  "firstName": "John",
  "lastName": "Doe",
  "address": "456 New Street",
  "address2": "",
  "city": "Delhi",
  "state": "Delhi",
  "zip": "110001",
  "country": "IN",
  "phone": "+919876543210",
  "label": "Work",
  "addressType": "both"
}
```

**Validation:**
- `id` required, must belong to `$_SESSION['user_id']`
- All address fields required except `address2`

**Response:**
```json
{
  "status": "success",
  "message": "Address updated successfully"
}
```

**Error (IDOR attempt):**
```json
{
  "status": "error",
  "message": "Address not found or access denied"
}
```

---

#### `POST api/address/set-default.php` 🆕 NEW

**Purpose:** Set an address as the default

**Request:**
```json
{
  "id": 5
}
```

**Logic:**
1. Verify `id` belongs to user
2. Unset previous default: `UPDATE addresses SET is_default = 0 WHERE user_id = ?`
3. Set new default: `UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?`

**Response:**
```json
{
  "status": "success",
  "message": "Default address updated"
}
```

---

#### `POST api/address/delete.php` ✅ (Existing — No Changes)

**Request:**
```json
{
  "id": 5
}
```

---

#### `POST api/order/create.php` — FIX REQUIRED

**Current Bug (Line 205):**
```php
// BROKEN - column names don't match table schema
$saveAddrStmt = $conn->prepare("INSERT INTO addresses
  (user_id, first_name, last_name, address, city, state, zip, country, phone)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
```

**Fixed Version:**
```php
// FIXED - use correct column names
$saveAddrStmt = $conn->prepare("INSERT INTO addresses
  (user_id, first_name, last_name, address_line1, address_line2, city, state, zip_code, country, phone, is_default, label)
  VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, ?, 0, 'Checkout')");
$saveAddrStmt->bind_param("isssssssss",
  $user_id,
  $ship['firstName'],
  $ship['lastName'],
  $ship['address'],          // Maps to address_line1
  $ship['city'],
  $ship['state'],
  $ship['zip'],              // Maps to zip_code
  $ship['country'],
  $ship['phone']
);
```

**New Feature — Use Saved Address:**

Add support for `savedAddressId` in order payload:
```php
// If user selected a saved address
if (!empty($data['savedAddressId'])) {
    $addrId = intval($data['savedAddressId']);
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addrId, $user_id);
    $stmt->execute();
    $savedAddr = $stmt->get_result()->fetch_assoc();

    if (!$savedAddr) {
        throw new Exception("Selected address not found");
    }

    // Build shipping JSON from saved address
    $shipping_data = [
        'firstName' => $savedAddr['first_name'],
        'lastName' => $savedAddr['last_name'],
        'email' => $data['shipping']['email'], // Email from form (not stored in address)
        'phone' => $savedAddr['phone'],
        'address' => $savedAddr['address_line1'],
        'address2' => $savedAddr['address_line2'] ?? '',
        'city' => $savedAddr['city'],
        'state' => $savedAddr['state'],
        'zip' => $savedAddr['zip_code'],
        'country' => $savedAddr['country']
    ];
    $shipping_address_json = json_encode($shipping_data);
}
```

---

## 6. Frontend Integration

### 6.1 Checkout Page Changes (`checkout.php`)

**Add Address Selector Section:**

```html
<!-- Add before the manual shipping form -->
<div id="saved-addresses-section" class="checkout-block" style="display: none;">
  <h2 class="block-title">Delivery Address</h2>

  <div id="saved-addresses-list" class="address-cards">
    <!-- Populated by JavaScript -->
  </div>

  <button type="button" id="add-new-address-btn" class="btn-outline">
    + Use a Different Address
  </button>
</div>

<!-- Existing form - now conditionally shown -->
<div id="new-address-form" class="checkout-block">
  <!-- existing shipping form fields -->

  <!-- Add save checkbox at the end -->
  <div class="form-field checkbox-field">
    <label>
      <input type="checkbox" id="save-address-checkbox" name="saveAddress" />
      <span>Save this address for future orders</span>
    </label>
  </div>
</div>
```

### 6.2 Address Card Component (CSS)

```css
/* Address Selection Cards */
.address-cards {
  display: grid;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.address-card {
  position: relative;
  padding: 1.25rem;
  border: 2px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  background: rgba(255,255,255,0.02);
  cursor: pointer;
  transition: all 0.2s ease;
}

.address-card:hover {
  border-color: rgba(111,75,255,0.4);
  background: rgba(111,75,255,0.05);
}

.address-card.selected {
  border-color: #6f4bff;
  background: rgba(111,75,255,0.1);
}

.address-card input[type="radio"] {
  position: absolute;
  opacity: 0;
}

.address-card-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.address-card-info h4 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #fff;
}

.address-card-info p {
  font-size: 0.875rem;
  color: rgba(255,255,255,0.7);
  line-height: 1.5;
  margin: 0;
}

.address-card-label {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: rgba(111,75,255,0.2);
  color: #9b8cff;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.address-card-default {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  background: rgba(34,197,94,0.2);
  color: #22c55e;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
  margin-left: 0.5rem;
}
```

### 6.3 JavaScript Functions (`script.js`)

```javascript
// ============================================
// ADDRESS MANAGEMENT FUNCTIONS
// ============================================

/**
 * Fetch user's saved addresses
 */
async function fetchSavedAddresses() {
  try {
    const response = await fetch('api/address/get.php', {
      headers: { 'X-CSRF-Token': getCsrfToken() }
    });
    const result = await response.json();

    if (result.status === 'success') {
      return result.data || [];
    }
    return [];
  } catch (error) {
    console.error('Error fetching addresses:', error);
    return [];
  }
}

/**
 * Render address selection cards at checkout
 */
function renderAddressSelector(addresses) {
  const container = document.getElementById('saved-addresses-list');
  const section = document.getElementById('saved-addresses-section');
  const newAddressForm = document.getElementById('new-address-form');

  if (!container || !section) return;

  if (addresses.length === 0) {
    section.style.display = 'none';
    newAddressForm.style.display = 'block';
    return;
  }

  section.style.display = 'block';
  newAddressForm.style.display = 'none'; // Hide by default when addresses exist

  container.innerHTML = addresses.map((addr, index) => `
    <label class="address-card ${addr.is_default ? 'selected' : ''}">
      <input type="radio" name="savedAddressId" value="${addr.id}"
             ${addr.is_default ? 'checked' : ''} />
      <div class="address-card-content">
        <div class="address-card-info">
          <h4>
            ${esc(addr.first_name)} ${esc(addr.last_name)}
            ${addr.label ? `<span class="address-card-label">${esc(addr.label)}</span>` : ''}
            ${addr.is_default ? '<span class="address-card-default">Default</span>' : ''}
          </h4>
          <p>
            ${esc(addr.address_line1)}<br>
            ${addr.address_line2 ? esc(addr.address_line2) + '<br>' : ''}
            ${esc(addr.city)}, ${esc(addr.state)} ${esc(addr.zip_code)}<br>
            ${esc(addr.country)} • ${esc(addr.phone)}
          </p>
        </div>
      </div>
    </label>
  `).join('');

  // Add click handlers for visual selection
  container.querySelectorAll('.address-card').forEach(card => {
    card.addEventListener('click', () => {
      container.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
    });
  });
}

/**
 * Toggle between saved address selection and new address form
 */
function setupAddressFormToggle() {
  const toggleBtn = document.getElementById('add-new-address-btn');
  const newAddressForm = document.getElementById('new-address-form');
  const savedSection = document.getElementById('saved-addresses-section');

  if (!toggleBtn) return;

  let showingNewForm = false;

  toggleBtn.addEventListener('click', () => {
    showingNewForm = !showingNewForm;

    if (showingNewForm) {
      newAddressForm.style.display = 'block';
      toggleBtn.textContent = '← Use Saved Address';
      // Uncheck any selected saved address
      document.querySelectorAll('input[name="savedAddressId"]').forEach(r => r.checked = false);
    } else {
      newAddressForm.style.display = 'none';
      toggleBtn.textContent = '+ Use a Different Address';
      // Re-select default address
      const defaultCard = document.querySelector('.address-card.selected input');
      if (defaultCard) defaultCard.checked = true;
    }
  });
}

/**
 * Enhanced loadCheckoutPage with address fetching
 */
async function loadCheckoutPage() {
  // ... existing cart loading code ...

  // Fetch and render saved addresses
  const userSession = getUserSession();
  if (userSession && userSession.id) {
    const addresses = await fetchSavedAddresses();
    renderAddressSelector(addresses);
    setupAddressFormToggle();

    // Pre-fill contact info from default address
    if (addresses.length > 0) {
      const defaultAddr = addresses.find(a => a.is_default) || addresses[0];
      prefillCheckoutContact(defaultAddr);
    }
  }
}

/**
 * Pre-fill contact fields from address
 */
function prefillCheckoutContact(addr) {
  const form = document.getElementById('checkout-form');
  if (!form) return;

  if (form.firstName && !form.firstName.value) form.firstName.value = addr.first_name;
  if (form.lastName && !form.lastName.value) form.lastName.value = addr.last_name;
  if (form.phone && !form.phone.value) form.phone.value = addr.phone;
}

/**
 * Modified handleCheckout to support saved address selection
 */
function handleCheckout(event) {
  event.preventDefault();

  // ... existing validation ...

  const savedAddressId = document.querySelector('input[name="savedAddressId"]:checked')?.value;
  const useNewAddress = document.getElementById('new-address-form').style.display !== 'none';

  const orderPayload = {
    // ... existing fields ...
    savedAddressId: savedAddressId && !useNewAddress ? parseInt(savedAddressId) : null,
    saveAddress: document.getElementById('save-address-checkbox')?.checked || false,
    shipping: useNewAddress || !savedAddressId ? {
      firstName: form.firstName?.value || '',
      lastName: form.lastName?.value || '',
      email: form.email?.value || '',
      phone: form.phone?.value || '',
      address: form.address?.value || '',
      city: form.city?.value || '',
      state: form.state?.value || '',
      zip: form.zip?.value || '',
      country: form.country?.value || 'IN'
    } : {
      email: form.email?.value || '' // Only email when using saved address
    }
  };

  // ... rest of checkout logic ...
}
```

### 6.4 Account Page Address Management

**Add to `account.php` Addresses Tab:**

```html
<div class="account-tab-content" id="addresses-tab">
  <div class="addresses-header">
    <h2>Saved Addresses</h2>
    <button class="btn-primary" onclick="openAddAddressModal()">+ Add New Address</button>
  </div>

  <div id="addresses-list" class="addresses-grid">
    <!-- Populated by JS -->
  </div>
</div>

<!-- Add Address Modal -->
<div id="address-modal" class="modal" style="display: none;">
  <div class="modal-content">
    <h3 id="address-modal-title">Add New Address</h3>
    <form id="address-form">
      <input type="hidden" id="address-id" name="id" />

      <div class="form-row">
        <div class="form-field">
          <label>First Name *</label>
          <input type="text" name="firstName" required />
        </div>
        <div class="form-field">
          <label>Last Name *</label>
          <input type="text" name="lastName" required />
        </div>
      </div>

      <div class="form-field">
        <label>Address Label</label>
        <select name="label">
          <option value="">Select...</option>
          <option value="Home">Home</option>
          <option value="Work">Work</option>
          <option value="Office">Office</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-field">
        <label>Street Address *</label>
        <input type="text" name="address" required />
      </div>

      <div class="form-field">
        <label>Apartment, Suite, etc.</label>
        <input type="text" name="address2" />
      </div>

      <div class="form-row">
        <div class="form-field">
          <label>City *</label>
          <input type="text" name="city" required />
        </div>
        <div class="form-field">
          <label>State *</label>
          <input type="text" name="state" required />
        </div>
      </div>

      <div class="form-row">
        <div class="form-field">
          <label>ZIP Code *</label>
          <input type="text" name="zip" required />
        </div>
        <div class="form-field">
          <label>Country *</label>
          <select name="country">
            <option value="IN">India</option>
            <option value="US">United States</option>
            <option value="UK">United Kingdom</option>
            <option value="CA">Canada</option>
            <option value="AU">Australia</option>
          </select>
        </div>
      </div>

      <div class="form-field">
        <label>Phone *</label>
        <input type="tel" name="phone" required />
      </div>

      <div class="form-field checkbox-field">
        <label>
          <input type="checkbox" name="isDefault" />
          <span>Set as default address</span>
        </label>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-ghost" onclick="closeAddressModal()">Cancel</button>
        <button type="submit" class="btn-primary">Save Address</button>
      </div>
    </form>
  </div>
</div>
```

---

## 7. Checkout Flow Changes

### 7.1 Updated Sequence Diagram

```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────┐
│  User   │     │ checkout.php │     │  API Layer  │     │ Database │
└────┬────┘     └──────┬───────┘     └──────┬──────┘     └────┬─────┘
     │                 │                     │                 │
     │ Visit Checkout  │                     │                 │
     │────────────────>│                     │                 │
     │                 │                     │                 │
     │                 │ GET /api/address/get│                 │
     │                 │────────────────────>│                 │
     │                 │                     │ SELECT addresses │
     │                 │                     │────────────────>│
     │                 │                     │<────────────────│
     │                 │<────────────────────│                 │
     │                 │                     │                 │
     │ Show Address    │                     │                 │
     │ Selector + Cart │                     │                 │
     │<────────────────│                     │                 │
     │                 │                     │                 │
     │ Select Address  │                     │                 │
     │ or Enter New    │                     │                 │
     │────────────────>│                     │                 │
     │                 │                     │                 │
     │ Place Order     │                     │                 │
     │────────────────>│                     │                 │
     │                 │ POST /api/order/create               │
     │                 │ {savedAddressId: 5} │                 │
     │                 │────────────────────>│                 │
     │                 │                     │ Fetch address   │
     │                 │                     │────────────────>│
     │                 │                     │ Create order    │
     │                 │                     │────────────────>│
     │                 │<────────────────────│                 │
     │                 │                     │                 │
     │ Order Confirmed │                     │                 │
     │<────────────────│                     │                 │
```

### 7.2 Validation Rules

| Scenario | Required Fields |
|----------|-----------------|
| Saved address selected | Email only (address fetched from DB) |
| New address (physical items) | firstName, lastName, email, phone, address, city, state, zip, country |
| New address (digital only) | firstName, lastName, email, phone |

---

## 8. Migration Strategy

### 8.1 Phase 1: Database Migration (Non-Breaking)

```sql
-- File: migrations/001_enhance_addresses_table.sql
-- Safe migration - only adds columns with defaults

ALTER TABLE `addresses`
  ADD COLUMN IF NOT EXISTS `label` VARCHAR(50) DEFAULT NULL
    AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both'
    AFTER `label`;

-- Add indexes for performance
ALTER TABLE `addresses`
  ADD INDEX IF NOT EXISTS `idx_user_default` (`user_id`, `is_default`),
  ADD INDEX IF NOT EXISTS `idx_user_type` (`user_id`, `address_type`);
```

### 8.2 Phase 2: Fix Existing Bug

```php
// File: api/order/create.php - Line 205
// Replace broken INSERT with fixed version

// BEFORE (broken):
$saveAddrStmt = $conn->prepare("INSERT INTO addresses (user_id, first_name, last_name, address, city, state, zip, country, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

// AFTER (fixed):
$saveAddrStmt = $conn->prepare("INSERT INTO addresses (user_id, first_name, last_name, address_line1, address_line2, city, state, zip_code, country, phone, is_default, label) VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, ?, 0, 'Checkout')");
```

### 8.3 Phase 3: New API Endpoints

Create files:
- `api/address/update.php`
- `api/address/set-default.php`

### 8.4 Phase 4: Frontend Updates

1. Update `checkout.php` HTML
2. Add CSS styles
3. Update `script.js` functions
4. Update `account.php` addresses tab

### 8.5 Rollback Plan

All changes are additive (new columns, new endpoints). To rollback:
1. Revert frontend JS to not call new endpoints
2. New columns can remain (unused but harmless)
3. New API files can be deleted

---

## 9. Security Considerations

### 9.1 IDOR Prevention

Every address operation MUST verify ownership:

```php
// ALWAYS check user_id ownership
$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $address_id, $_SESSION['user_id']);
```

### 9.2 Input Validation

```php
// Sanitize all inputs
$first_name = htmlspecialchars(trim($data['firstName']), ENT_QUOTES, 'UTF-8');
$phone = preg_replace('/[^\d+\-\s()]/', '', $data['phone']);
$zip_code = preg_replace('/[^\d\w\-\s]/', '', $data['zip']);
```

### 9.3 CSRF Protection

All POST endpoints must validate:
```php
validateCsrf(); // From helpers.php
```

### 9.4 Rate Limiting

Consider adding to `api/address/add.php`:
```php
// Max 10 addresses per user
$countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM addresses WHERE user_id = ?");
if ($count > 10) {
    sendResponse('error', 'Maximum 10 addresses allowed', null, 400);
}
```

---

## 10. Testing Checklist

### 10.1 API Testing

- [ ] `GET /api/address/get` returns correct addresses for user
- [ ] `GET /api/address/get` returns empty array for user with no addresses
- [ ] `POST /api/address/add` creates address with all fields
- [ ] `POST /api/address/add` auto-sets first address as default
- [ ] `POST /api/address/add` unsets previous default when `isDefault=true`
- [ ] `POST /api/address/update` updates existing address
- [ ] `POST /api/address/update` rejects non-owned address (IDOR test)
- [ ] `POST /api/address/set-default` changes default address
- [ ] `POST /api/address/delete` removes address
- [ ] `POST /api/address/delete` rejects non-owned address

### 10.2 Checkout Flow Testing

- [ ] Checkout shows address selector for users with saved addresses
- [ ] Default address is pre-selected
- [ ] "Use different address" shows manual form
- [ ] Order created with `savedAddressId` correctly fetches address
- [ ] Order created with new address and `saveAddress=true` saves the address
- [ ] Digital-only orders skip shipping address validation
- [ ] Physical orders require full address

### 10.3 Account Page Testing

- [ ] Addresses tab lists all user addresses
- [ ] Add address modal opens and submits
- [ ] Edit address pre-fills form and updates
- [ ] Delete address with confirmation works
- [ ] Set default updates correctly

### 10.4 Edge Cases

- [ ] User with 10 addresses cannot add 11th
- [ ] Deleted address cannot be selected at checkout
- [ ] Very long address strings handled (max 255 chars)
- [ ] Special characters in address (accents, apostrophes)
- [ ] International phone formats validated

---

## Appendix A: File Change Summary

| File | Action | Description |
|------|--------|-------------|
| `migrations/001_enhance_addresses_table.sql` | CREATE | Add label, address_type columns |
| `api/address/update.php` | CREATE | New endpoint for editing |
| `api/address/set-default.php` | CREATE | New endpoint for default toggle |
| `api/address/add.php` | MODIFY | Add label, addressType parameters |
| `api/order/create.php` | MODIFY | Fix saveAddress bug, add savedAddressId |
| `checkout.php` | MODIFY | Add address selector HTML |
| `script.js` | MODIFY | Add address functions, modify handleCheckout |
| `style.css` | MODIFY | Add address card styles |
| `account.php` | MODIFY | Enhance addresses tab |

---

## Appendix B: API Quick Reference

```
┌────────────────────────────────────────────────────────────────────────────┐
│                        ADDRESS MANAGEMENT APIs                              │
├─────────────────────────────┬──────────┬───────────────────────────────────┤
│ Endpoint                    │ Method   │ Description                       │
├─────────────────────────────┼──────────┼───────────────────────────────────┤
│ /api/address/get.php        │ GET      │ List all user addresses           │
│ /api/address/add.php        │ POST     │ Add new address                   │
│ /api/address/update.php     │ POST     │ Update existing address           │
│ /api/address/delete.php     │ POST     │ Delete address                    │
│ /api/address/set-default.php│ POST     │ Set address as default            │
├─────────────────────────────┼──────────┼───────────────────────────────────┤
│ /api/order/create.php       │ POST     │ Create order (accepts             │
│                             │          │ savedAddressId or shipping obj)   │
└─────────────────────────────┴──────────┴───────────────────────────────────┘
```

---

*Document Version: 1.0*
*Last Updated: 2026-03-26*
*Author: System Architect*
