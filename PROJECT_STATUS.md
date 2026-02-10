### Backend Functionality Test Report

#### 1. User Authentication
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| User Signup (API) | ✅ Passed | Creates user in DB with hashed password. |
| User Login (API) | ✅ Passed | Returns token and user details (including ID). |
| Session Handling | ✅ Passed | `user_id` stored in session & `localStorage`. |
| Admin Login | ✅ Passed | Separate API, strict role check. |

#### 2. Product Management
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| Product Seeding | ✅ Passed | Default products seeded into DB. |
| Product Mapping | ✅ Passed | Legacy string IDs map to DB IDs transparently. |

#### 3. Cart Functionality
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| Add to Cart (DB) | ✅ Passed | Items persist in `cart` table for logged-in users. |
| Get Cart (DB) | ✅ Passed | Fetches items joined with product details. |
| Remove Item | ✅ Passed | Successfully removes items from DB. |
| Update Quantity | ✅ Passed | Syncs quantity changes. |
| Guest Cart | ✅ Passed | Falls back to `localStorage` seamlessly. |

#### 4. Order Processing
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| Create Order API | ✅ Passed | `api/order/create.php` handles transactions. |
| Order Items | ✅ Passed | Items linked to Order ID and Product ID. |
| Cart Clearing | ✅ Passed | DB cart is emptied after successful order. |
| Order Status | ✅ Passed | Default 'Pending', Admin can update. |

#### 5. Security & Validation
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| API Auth Check | ✅ Passed | helper `requireAuth()` applied to protected endpoints. |
| Input Validation | ✅ Passed | Basic validation on all inputs. |
| SQL Injection | ✅ Passed | Prepared statements used everywhere. |

### 6. API Audit & Security Hardening (Completed)
| Verification Step | Status | Notes |
|-------------------|--------|-------|
| Admin Authorization | ✅ Secured | Added strict role checks to product/order admin APIs. |
| Order Integrity | ✅ Secured | Backend now calculates totals and validates stock. Frontend totals ignored. |
| Account Deletion | ✅ Secured | Server-side password verification and data removal implemented. |
| Frontend Integration | ✅ Updated | Checkout flow uses server-authoritative totals. |

### Pending / Next Steps
1. **Address Management**: Re-implement backend address storage to allow users to manage saved addresses across devices. currently relies on localStorage/checkout entry.
2. **Email Notifications**: Integrate generic SMTP mailer for order confirmation emails.
3. **Payment Gateway**: "Card" and "UPI" are currently simulated. Integration with Razorpay/Stripe needed for real payments.
4. **Frontend Order History**: Ensure `orders.php` fetches from `api/order/get.php` (already available).
