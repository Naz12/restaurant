# Mobile API Documentation

## Base URL
```
/api/mobile
```

## Authentication

All protected endpoints require authentication using Laravel Sanctum. Include the token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## Authentication Endpoints

### 1. Login
**POST** `/api/mobile/auth/login`

Login with email and password.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "restaurant_id": 1,
      "branch_id": 1,
      "roles": ["Waiter"]
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### 2. Send OTP
**POST** `/api/mobile/auth/otp/send`

Send OTP to user's email for login.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "OTP sent successfully"
}
```

---

### 3. Verify OTP
**POST** `/api/mobile/auth/otp/verify`

Verify OTP and login.

**Request Body:**
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "OTP verified successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "restaurant_id": 1,
      "branch_id": 1,
      "roles": ["Waiter"]
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

---

### 4. Get Authenticated User
**GET** `/api/mobile/auth/user`

Get current authenticated user information.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "restaurant_id": 1,
      "branch_id": 1,
      "roles": ["Waiter"],
      "permissions": ["Create Order", "Update Order"]
    }
  }
}
```

---

### 5. Logout
**POST** `/api/mobile/auth/logout`

Logout and revoke current token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### 6. Refresh Token
**POST** `/api/mobile/auth/refresh-token`

Revoke current token and create a new one.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

---

## Menu Endpoints

### 7. Get Menu Items
**GET** `/api/mobile/menu/items`

Get all menu items with optional filters.

**Query Parameters:**
- `category_id` (optional): Filter by category ID
- `search` (optional): Search by item name
- `order_type_id` (optional): Get contextual pricing for order type
- `delivery_app_id` (optional): Get contextual pricing for delivery app

**Response (200):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "item_name": "Pizza Margherita",
        "description": "Classic pizza with tomato and mozzarella",
        "price": 12.99,
        "image": "https://example.com/image.jpg",
        "category_id": 1,
        "category_name": "Pizza",
        "veg_non_veg": "veg",
        "has_variations": true,
        "variations": [
          {
            "id": 1,
            "name": "Small",
            "price": 10.99
          },
          {
            "id": 2,
            "name": "Large",
            "price": 15.99
          }
        ],
        "modifier_groups": [
          {
            "id": 1,
            "name": "Extra Toppings",
            "description": "Add extra toppings",
            "is_required": false,
            "min_selections": 0,
            "max_selections": 3,
            "options": [
              {
                "id": 1,
                "name": "Extra Cheese",
                "price": 2.00
              }
            ]
          }
        ]
      }
    ],
    "total": 1
  }
}
```

---

### 8. Get Single Menu Item
**GET** `/api/mobile/menu/items/{id}`

Get detailed information about a single menu item.

**Query Parameters:**
- `order_type_id` (optional): Get contextual pricing
- `delivery_app_id` (optional): Get contextual pricing

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "item_name": "Pizza Margherita",
    "description": "Classic pizza",
    "price": 12.99,
    "image": "https://example.com/image.jpg",
    "category_id": 1,
    "category_name": "Pizza",
    "veg_non_veg": "veg",
    "variations": [...],
    "modifier_groups": [...]
  }
}
```

---

### 9. Get Categories
**GET** `/api/mobile/menu/categories`

Get all menu categories.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "category_name": "Pizza",
        "items_count": 10
      }
    ],
    "total": 1
  }
}
```

---

### 10. Get Modifier Groups
**GET** `/api/mobile/menu/modifier-groups`

Get all modifier groups with their options.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "modifier_groups": [
      {
        "id": 1,
        "name": "Extra Toppings",
        "description": "Add extra toppings",
        "options": [
          {
            "id": 1,
            "name": "Extra Cheese",
            "price": 2.00
          }
        ]
      }
    ],
    "total": 1
  }
}
```

---

## Table Endpoints

### 11. Get Tables
**GET** `/api/mobile/tables`

Get all tables with optional filters.

**Query Parameters:**
- `area_id` (optional): Filter by area
- `status` (optional): Filter by status (available, occupied)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tables": [
      {
        "id": 1,
        "table_code": "T-01",
        "capacity": 4,
        "area_id": 1,
        "area_name": "Main Hall",
        "status": "available",
        "active_order": null
      },
      {
        "id": 2,
        "table_code": "T-02",
        "capacity": 6,
        "area_id": 1,
        "area_name": "Main Hall",
        "status": "occupied",
        "active_order": {
          "id": 1,
          "order_number": 1001,
          "formatted_order_number": "Order # 1001",
          "status": "kot"
        }
      }
    ],
    "total": 2
  }
}
```

---

### 12. Get Single Table
**GET** `/api/mobile/tables/{id}`

Get detailed information about a single table.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "table_code": "T-01",
    "capacity": 4,
    "area_id": 1,
    "area_name": "Main Hall",
    "status": "occupied",
    "active_order": {
      "id": 1,
      "order_number": 1001,
      "formatted_order_number": "Order # 1001",
      "status": "kot",
      "waiter": {
        "id": 1,
        "name": "John Doe"
      }
    }
  }
}
```

---

### 13. Get Table Active Order
**GET** `/api/mobile/tables/{id}/active-order`

Get the active order for a specific table.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "order_number": 1001,
      "formatted_order_number": "Order # 1001",
      "status": "kot",
      "waiter": {
        "id": 1,
        "name": "John Doe"
      }
    }
  }
}
```

---

### 14. Lock Table
**POST** `/api/mobile/tables/{id}/lock`

Lock a table to prevent concurrent access.

**Response (200):**
```json
{
  "success": true,
  "message": "Table locked successfully",
  "data": {
    "session_token": "abc123"
  }
}
```

**Response (409) - Table already locked:**
```json
{
  "success": false,
  "message": "This table is currently being handled by Jane Doe. Please try again later.",
  "data": {
    "locked_by": "Jane Doe",
    "locked_at": "14:30"
  }
}
```

---

### 15. Unlock Table
**POST** `/api/mobile/tables/{id}/unlock`

Unlock a table.

**Query Parameters:**
- `force` (optional): Force unlock (boolean)

**Response (200):**
```json
{
  "success": true,
  "message": "Table unlocked successfully"
}
```

---

### 16. Get Areas
**GET** `/api/mobile/tables/areas`

Get all table areas.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "areas": [
      {
        "id": 1,
        "area_name": "Main Hall",
        "tables_count": 10
      }
    ],
    "total": 1
  }
}
```

---

## Order Endpoints

### 17. Get Orders
**GET** `/api/mobile/orders`

Get all orders with optional filters.

**Query Parameters:**
- `status` (optional): Filter by status
- `table_id` (optional): Filter by table
- `waiter_id` (optional): Filter by waiter
- `start_date` (optional): Filter by start date
- `end_date` (optional): Filter by end date
- `per_page` (optional): Items per page (default: 15)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 1,
        "order_number": 1001,
        "formatted_order_number": "Order # 1001",
        "status": "kot",
        "table": {
          "id": 1,
          "table_code": "T-01",
          "area_name": "Main Hall"
        },
        "waiter": {
          "id": 1,
          "name": "John Doe"
        },
        "number_of_pax": 2,
        "sub_total": 50.00,
        "discount_amount": 5.00,
        "total": 45.00,
        "items": [...]
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

---

### 18. Create Order
**POST** `/api/mobile/orders`

Create a new order.

**Request Body:**
```json
{
  "table_id": 1,
  "order_type_id": 1,
  "waiter_id": 1,
  "number_of_pax": 2,
  "items": [
    {
      "menu_item_id": 1,
      "variation_id": 1,
      "quantity": 2,
      "modifiers": [1, 2],
      "note": "No onions"
    }
  ],
  "discount_type": "percent",
  "discount_value": 10,
  "tip_amount": 5.00,
  "delivery_fee": 0,
  "order_note": "Customer requested quick service"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "order_number": 1001,
    "formatted_order_number": "Order # 1001",
    "status": "placed",
    "items": [...],
    "sub_total": 50.00,
    "discount_amount": 5.00,
    "total": 45.00
  }
}
```

---

### 19. Get Single Order
**GET** `/api/mobile/orders/{id}`

Get detailed information about a single order.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "order_number": 1001,
    "formatted_order_number": "Order # 1001",
    "status": "kot",
    "table": {...},
    "waiter": {...},
    "items": [
      {
        "id": 1,
        "menu_item": {
          "id": 1,
          "name": "Pizza Margherita"
        },
        "variation": {
          "id": 1,
          "name": "Large"
        },
        "quantity": 2,
        "price": 12.99,
        "amount": 25.98,
        "modifiers": [
          {
            "id": 1,
            "name": "Extra Cheese",
            "price": 2.00
          }
        ],
        "note": "No onions"
      }
    ],
    "sub_total": 50.00,
    "discount_amount": 5.00,
    "total": 45.00
  }
}
```

---

### 20. Update Order
**PUT** `/api/mobile/orders/{id}`

Update an existing order.

**Request Body:**
```json
{
  "waiter_id": 2,
  "number_of_pax": 3,
  "discount_type": "fixed",
  "discount_value": 10,
  "tip_amount": 5.00,
  "order_note": "Updated note",
  "order_status": "confirmed"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Order updated successfully",
  "data": {...}
}
```

---

### 21. Add Item to Order
**POST** `/api/mobile/orders/{id}/items`

Add an item to an existing order.

**Request Body:**
```json
{
  "menu_item_id": 2,
  "variation_id": null,
  "quantity": 1,
  "modifiers": [1],
  "note": "Extra spicy"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Item added to order successfully",
  "data": {...}
}
```

---

### 22. Update Order Item
**PUT** `/api/mobile/orders/{orderId}/items/{itemId}`

Update an order item.

**Request Body:**
```json
{
  "quantity": 3,
  "modifiers": [1, 2],
  "note": "Updated note"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Order item updated successfully",
  "data": {...}
}
```

---

### 23. Delete Order Item
**DELETE** `/api/mobile/orders/{orderId}/items/{itemId}`

Delete an item from an order.

**Response (200):**
```json
{
  "success": true,
  "message": "Order item deleted successfully",
  "data": {...}
}
```

---

## KOT Endpoints

### 24. Get KOTs
**GET** `/api/mobile/kots`

Get all KOTs with optional filters.

**Query Parameters:**
- `kitchen_place_id` (optional): Filter by kitchen place
- `status` (optional): Filter by status
- `start_date` (optional): Filter by start date
- `end_date` (optional): Filter by end date
- `filter_orders` (optional): pending_confirmation, in_kitchen, ready
- `per_page` (optional): Items per page

**Response (200):**
```json
{
  "success": true,
  "data": {
    "kots": [
      {
        "id": 1,
        "kot_number": 501,
        "status": "in_kitchen",
        "token_number": 1,
        "order": {
          "id": 1,
          "order_number": 1001,
          "formatted_order_number": "Order # 1001"
        },
        "table": {
          "id": 1,
          "table_code": "T-01",
          "area_name": "Main Hall"
        },
        "waiter": {
          "id": 1,
          "name": "John Doe"
        },
        "kitchen_place": {
          "id": 1,
          "name": "Main Kitchen"
        },
        "items_count": 3,
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "pagination": {...}
  }
}
```

---

### 25. Get Single KOT
**GET** `/api/mobile/kots/{id}`

Get detailed information about a single KOT.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "kot_number": 501,
    "status": "in_kitchen",
    "items": [
      {
        "id": 1,
        "menu_item": {
          "id": 1,
          "name": "Pizza Margherita"
        },
        "variation": {
          "id": 1,
          "name": "Large"
        },
        "quantity": 2,
        "note": "No onions",
        "status": "preparing",
        "modifiers": [...]
      }
    ]
  }
}
```

---

### 26. Confirm KOT
**POST** `/api/mobile/kots/{id}/confirm`

Move KOT from pending to in_kitchen status.

**Response (200):**
```json
{
  "success": true,
  "message": "KOT confirmed successfully",
  "data": {...}
}
```

---

### 27. Mark KOT as Ready
**POST** `/api/mobile/kots/{id}/ready`

Mark KOT as ready.

**Response (200):**
```json
{
  "success": true,
  "message": "KOT marked as ready",
  "data": {...}
}
```

---

### 28. Cancel KOT
**POST** `/api/mobile/kots/{id}/cancel`

Cancel a KOT.

**Request Body:**
```json
{
  "cancel_reason_id": 1,
  "cancel_note": "Out of ingredients"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "KOT cancelled successfully",
  "data": {...}
}
```

---

### 29. Update KOT Item Status
**PUT** `/api/mobile/kots/{kotId}/items/{itemId}/status`

Update the status of a KOT item.

**Request Body:**
```json
{
  "status": "ready"
}
```

**Valid statuses:** pending, preparing, ready, cancelled

**Response (200):**
```json
{
  "success": true,
  "message": "KOT item status updated successfully",
  "data": {...}
}
```

---

### 30. Cancel KOT Item
**POST** `/api/mobile/kots/{kotId}/items/{itemId}/cancel`

Cancel a specific KOT item.

**Request Body:**
```json
{
  "cancel_reason_id": 1,
  "cancel_note": "Customer cancelled"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "KOT item cancelled successfully",
  "data": {...}
}
```

---

### 31. Get KOT Places
**GET** `/api/mobile/kots/places`

Get all kitchen places/stations.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "places": [
      {
        "id": 1,
        "name": "Main Kitchen"
      }
    ],
    "total": 1
  }
}
```

---

### 32. Get Cancel Reasons
**GET** `/api/mobile/kots/cancel-reasons`

Get all KOT cancel reasons.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "reasons": [
      {
        "id": 1,
        "reason": "Out of stock"
      }
    ],
    "total": 1
  }
}
```

---

## Payment Endpoints

### 33. Get Payments
**GET** `/api/mobile/payments`

Get all payments with optional filters.

**Query Parameters:**
- `order_id` (optional): Filter by order
- `payment_method` (optional): Filter by payment method
- `start_date` (optional): Filter by start date
- `end_date` (optional): Filter by end date
- `per_page` (optional): Items per page

**Response (200):**
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 1,
        "order_id": 1,
        "order_number": 1001,
        "formatted_order_number": "Order # 1001",
        "payment_method": "cash",
        "amount": 45.00,
        "tip_amount": 5.00,
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "pagination": {...}
  }
}
```

---

### 34. Create Payment
**POST** `/api/mobile/payments`

Process a payment for an order.

**Request Body (Single Payment):**
```json
{
  "order_id": 1,
  "payment_method": "cash",
  "amount": 45.00,
  "tip_amount": 5.00,
  "notes": "Payment received"
}
```

**Request Body (Split Payment):**
```json
{
  "order_id": 1,
  "payment_method": "split",
  "split_payments": [
    {
      "method": "cash",
      "amount": 25.00
    },
    {
      "method": "card",
      "amount": 20.00
    }
  ],
  "notes": "Split payment"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "payment_amount": 45.00,
    "remaining_balance": 0.00,
    "order": {
      "id": 1,
      "total": 45.00,
      "paid": 45.00,
      "remaining": 0.00
    }
  }
}
```

---

### 35. Get Single Payment
**GET** `/api/mobile/payments/{id}`

Get detailed information about a single payment.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "order_id": 1,
    "order_number": 1001,
    "payment_method": "cash",
    "amount": 45.00,
    "tip_amount": 5.00,
    "order": {...},
    "table": {...},
    "waiter": {...},
    "notes": "Payment received",
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

### 36. Get Order Payments
**GET** `/api/mobile/orders/{orderId}/payments`

Get all payments for a specific order.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1,
      "total": 45.00,
      "paid": 45.00,
      "remaining": 0.00
    },
    "payments": [
      {
        "id": 1,
        "order_id": 1,
        "payment_method": "cash",
        "amount": 45.00,
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ]
  }
}
```

---

## Sync Endpoints

### 37. Pull Data
**POST** `/api/mobile/sync/pull`

Pull latest data from server for offline sync.

**Request Body:**
```json
{
  "last_sync": "2024-01-15T10:00:00Z",
  "sync_types": ["menu", "tables", "orders", "kots", "payments"]
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Data pulled successfully",
  "data": {
    "menu": {
      "items": [...],
      "categories": [...]
    },
    "tables": [...],
    "areas": [...],
    "orders": [...],
    "kots": [...],
    "payments": [...],
    "order_types": [...]
  },
  "sync_timestamp": "2024-01-15T10:30:00.000000Z"
}
```

---

### 38. Push Data
**POST** `/api/mobile/sync/push`

Push offline changes to server.

**Request Body:**
```json
{
  "orders": [
    {
      "temp_id": "temp_order_1",
      "table_id": 1,
      "order_type_id": 1,
      "items": [...]
    }
  ],
  "kots": [
    {
      "temp_id": "temp_kot_1",
      "order_id": 1,
      "status": "in_kitchen"
    }
  ],
  "payments": [
    {
      "temp_id": "temp_payment_1",
      "order_id": 1,
      "amount": 45.00,
      "payment_method": "cash"
    }
  ]
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Data synced successfully",
  "data": {
    "orders": [
      {
        "temp_id": "temp_order_1",
        "server_id": 1,
        "status": "success"
      }
    ],
    "kots": [...],
    "payments": [...]
  },
  "sync_timestamp": "2024-01-15T10:30:00.000000Z"
}
```

---

### 39. Get Sync Status
**GET** `/api/mobile/sync/status`

Get sync status and last update timestamps.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "last_order_updated": "2024-01-15T10:30:00.000000Z",
    "last_kot_updated": "2024-01-15T10:30:00.000000Z",
    "last_payment_updated": "2024-01-15T10:30:00.000000Z",
    "server_time": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
  "success": false,
  "message": "Error message"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Access denied"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### 500 Server Error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Notes

1. All timestamps are in ISO 8601 format (UTC)
2. All monetary values are in the restaurant's base currency
3. Pagination is available for list endpoints (default: 15 items per page)
4. All protected endpoints require valid authentication token
5. Token expires based on Sanctum configuration (default: no expiration)
6. Use refresh token endpoint to get a new token when needed

