# Tipping API — Backend Documentation

This is the backend for the **Tipping Platform**, built with **Laravel 11** and **Laravel Sanctum**.
It provides authentication, email verification, password reset, tipping, payments, payouts, and role-based access endpoints.

---

##  Table of Contents

1. [Base URL](#-base-url)
2. [Authentication](#-authentication)
3. [Health Check](#-health-check)
4. [Authentication Endpoints](#-authentication-endpoints)

   * [Register User](#1-register-user)
   * [Login](#2-login)
   * [Logout](#3-logout)
5. [Email Verification with Mailtrap](#-email-verification-with-mailtrap)
6. [Password Reset Flow](#-password-reset-flow)
7. [Error Responses](#-error-responses)
8. [Environment Variables](#-environment-variables-backend)
9. [Mailtrap Setup](#-mailtrap-setup)
10. [Route Summary](#-route-summary)
11. [Notes](#-notes)
12. [Example Postman Setup](#-example-postman-setup)
13. [User Profile Management](#-user-profile-management)
14. [Chapa Payment Integration](#-chapa-payment-integration)
15. [Payouts, Analytics & Role Management](#-payouts-analytics--role-management)

* [Overview](#-overview)
* [Role Management](#-role-management)
* [Admin Registration](#-admin-registration)
* [Creator Endpoints](#-creator-endpoints)
* [Admin Endpoints](#-admin-endpoints)
* [Data Flow](#-data-flow)
* [Frontend Integration Guide](#-frontend-integration-guide)

16. [Final Notes](#note)

---

##  Base URL

```http
http://127.0.0.1:8000/api
```

All endpoints are prefixed with `/api`.

---

##  Authentication

* **Auth type:** Bearer token (Sanctum personal access tokens)
* **Headers:**

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

---

##  Health Check

### Endpoint

```
GET /health
```

### Response (200)

```json
{
  "status": "ok",
  "database": "connected"
}
```

---

##  Authentication Endpoints

### 1) Register User

```
POST /register
```

#### Request Body

```json
{
  "name": "Ada Lovelace",
  "email": "ada@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "tipper"
}
```

#### Response (201)

```json
{
  "message": "User registered successfully. Please verify your email.",
  "user": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "email_verified_at": null,
    "role": "tipper",
    "created_at": "2025-08-28T14:21:00Z"
  },
  "token": "1|2WJkTyhO..."
}
```

**Note:** A verification email is automatically sent via **Mailtrap**. See [Mailtrap Setup](#-mailtrap-setup).

---

### 2) Login

```
POST /login
```

#### Request Body

```json
{
  "email": "ada@example.com",
  "password": "secret123"
}
```

#### Response (200)

```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "email_verified_at": "2025-08-28T15:22:00Z",
    "role": "tipper"
  },
  "token": "1|xYzABC123..."
}
```

If the email is not verified, login is blocked:

```json
{ "message": "Please verify your email before logging in." }
```

---

### 3) Logout

```
POST /logout
Authorization: Bearer <token>
```

#### Response (200)

```json
{ "message": "Logged out successfully" }
```

---

##  Email Verification with Mailtrap

### Verification Link

```
GET /email/verify/{id}/{hash}
```

#### Response

```json
{ "message": "Email verified successfully" }
```

**Development Flow:**

* The email is sent to your **Mailtrap inbox**.
* Open Mailtrap → copy the verification link → paste in browser or hit with Postman.
* Once verified, the user can log in.

---

##  Password Reset Flow

### 1) Request Password Reset

```
POST /forgot-password
```

#### Request Body

```json
{ "email": "ada@example.com" }
```

#### Response (200)

```json
{ "message": "Password reset link sent to your email" }
```

**Development Flow:**

* The reset email will appear in your **Mailtrap inbox**.
* The link is customized to point to your frontend:

```
http://localhost:3000/reset-password?token=XYZ123&email=ada@example.com
```

---

### 2) Reset Password

```
POST /reset-password
```

#### Request Body

```json
{
  "token": "XYZ123",
  "email": "ada@example.com",
  "password": "newSecret123",
  "password_confirmation": "newSecret123"
}
```

#### Response (200)

```json
{ "message": "Password reset successful" }
```

---

##  Error Responses

### 401 Unauthorized

```json
{ "message": "Unauthenticated." }
```

### 403 Forbidden (Unverified Email)

```json
{ "message": "Please verify your email before logging in." }
```

### 422 Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

---

##  Environment Variables (Backend)

```env
APP_NAME="Tipping API"
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tipping_api_db
DB_USERNAME=root
DB_PASSWORD=

# Mailtrap config
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@tippingapi.com"
MAIL_FROM_NAME="Tipping API"
```

---

##  Mailtrap Setup

The API is preconfigured to use **[Mailtrap](https://mailtrap.io/)** for all outgoing mail.

* Create a free Mailtrap account.
* Copy the SMTP credentials into your `.env`.
* All verification and reset emails will appear in Mailtrap.
* In production, replace with real SMTP credentials (SendGrid, Mailgun, Gmail, etc.).

---

##  Route Summary

| Method | Path                        | Auth         | Purpose                                              |
| ------ | --------------------------- | ------------ | ---------------------------------------------------- |
| GET    | `/health`                   | —            | Health check                                         |
| POST   | `/register`                 | —            | Register new user + send Mailtrap verification email |
| POST   | `/login`                    | —            | Login & issue token (requires verified email)        |
| POST   | `/logout`                   | Bearer token | Revoke token                                         |
| GET    | `/email/verify/{id}/{hash}` | Signed link  | Verify user email                                    |
| POST   | `/forgot-password`          | —            | Send reset link (delivered via Mailtrap)             |
| POST   | `/reset-password`           | —            | Reset user password                                  |

---

##  Notes

* Save the token returned by `/login` or `/register`.
* Send it in the `Authorization: Bearer` header for protected requests.
* Always include `Accept: application/json` in headers.
* For password reset:

  * `/forgot-password` sends reset link via Mailtrap.
  * Frontend extracts token & email from URL.
  * Calls `/reset-password` with new password.

---

##  Example Postman Setup

1. **Environment Variables**

```json
{
  "baseUrl": "http://127.0.0.1:8000/api",
  "authToken": ""
}
```

2. **Save token after login**
   In the **Tests tab** for `/login`:

```js
let res = pm.response.json();
if (res.token) {
  pm.environment.set("authToken", res.token);
}
```

3. **Use token in requests**

```http
Authorization: Bearer {{authToken}}
```

---

##  User Profile Management

This module enables authenticated users to **view and update their profile**, including uploading an avatar.
All routes require a valid **Bearer token**.

### Database Schema (Users Table – Relevant Fields)

| Column      | Type              | Description                          |
| ----------- | ----------------- | ------------------------------------ |
| id          | bigint (PK)       | Unique user ID                       |
| name        | string            | Full name                            |
| email       | string (unique)   | Email address                        |
| role        | enum              | One of: `tipper`, `creator`, `admin` |
| bio         | text (nullable)   | Short biography                      |
| avatar      | string (nullable) | Avatar path (e.g. `avatars/xyz.png`) |
| balance     | decimal(12,2)     | Account balance (for tipping system) |
| created\_at | timestamp         | User creation date                   |
| updated\_at | timestamp         | Last profile update                  |

⚡ Always use `avatar_url` from API responses (never build manually).

---

### Get Current User Profile

```
GET /api/user
```

Headers:

```http
Authorization: Bearer {token}
Accept: application/json
```

Response Example:

```json
{
  "data": {
    "id": 7,
    "name": "Anteneh",
    "email": "anteneh8@gmail.com",
    "role": "tipper",
    "bio": "I am a new user",
    "avatar": "avatars/avatar123.png",
    "avatar_url": "http://127.0.0.1:8000/storage/avatars/avatar123.png",
    "balance": "0.00",
    "created_at": "2025-08-28T17:13:21.000000Z",
    "updated_at": "2025-08-28T17:32:28.000000Z"
  }
}
```

---

### Update Profile

```
PUT /api/user
```

Headers:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: multipart/form-data
```

Body (form-data):

* `name` (string, optional)
* `bio` (string, optional)
* `email` (string, optional, unique)
* `avatar` (file, optional, max 2MB)

Response Example:

```json
{
  "message": "Profile updated successfully.",
  "data": {
    "id": 7,
    "name": "Jane Smith",
    "email": "anteneh8@gmail.com",
    "role": "tipper",
    "bio": "I love Laravel",
    "avatar": "avatars/new_avatar.png",
    "avatar_url": "http://127.0.0.1:8000/storage/avatars/new_avatar.png",
    "balance": "0.00",
    "created_at": "2025-08-28T17:13:21.000000Z",
    "updated_at": "2025-08-29T11:00:00.000000Z"
  }
}
```

---

##  Chapa Payment Integration

This module integrates the **Chapa payment gateway** for tipping creators.
Features include: payment initialization, webhook handling, tip tracking, and balance updates.

### Environment Variables

```env
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-xxxx
CHAPA_SECRET_KEY=CHASECK_TEST-xxxx
CHAPA_WEBHOOK_SECRET=YourSecretHere
CHAPA_WEBHOOK_URL=https://<your-domain-or-ngrok>/api/chapa/webhook
CHAPA_RETURN_URL=https://<your-domain-or-ngrok>/payment-result
```

### Endpoints

1. `POST /api/creator/{id}/tips` → Initialize tip payment.
2. `GET /api/tips/{tx_ref}/status` → Check tip status.
3. `POST /api/chapa/webhook` → Webhook for Chapa notifications.
4. `GET /payment-result` → Optional confirmation page.

---

##  Payouts, Analytics & Role Management

This module introduces **payout handling for creators**, **analytics insights**, and **role-based access control (RBAC)**.

### Overview

* **Creators** earn money → request payouts.
* **Admins** approve/reject/mark payouts.
* **Analytics** gives insights into tips and balance.

### Role Management

Roles:

* `creator` → request payouts, view analytics.
* `admin` → manage payouts.

### Admin Registration

Admins register via `/api/register` with an extra `secret` matching `.env ADMIN_SECRET`.

Example:

```json
POST /api/register
{
  "name": "Alice Admin",
  "email": "alice@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "secret": "super-secret-key"
}
```

Response:

```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "Alice Admin",
    "email": "alice@example.com",
    "role": "admin"
  },
  "token": "eyJhbGciOiJIUzI1..."
}
```

---

### Creator Endpoints

* `POST /api/payouts` → Request payout.
* `GET /api/creator/analytics` → Fetch analytics.

### Admin Endpoints

* `GET /api/payouts` → View payouts.
* `PUT /api/payouts/{id}/approve` → Approve payout.
* `PUT /api/payouts/{id}/reject` → Reject payout & refund.
* `PUT /api/payouts/{id}/mark-paid` → Mark payout as paid.

---

### Data Flow

1. Creator requests payout → status `pending`.
2. Admin reviews → approve, reject (refund), or mark as paid.
3. Creator fetches analytics → tips count, earnings, balance.

---

### Frontend Integration Guide

* Always send `Authorization: Bearer <token>`.
* Creators → `/api/payouts (POST)` + `/api/creator/analytics`.
* Admins → `/api/payouts (GET/PUT)`.
* Use statuses (`pending`, `approved`, `rejected`, `paid`) for UI badges.

---

##  Note

* **Creators** → Request payouts, view analytics.
* **Admins** → Manage payouts (approve, reject, mark paid).
* **RBAC** → Secure access by role.
* **Admin accounts** are created with a secret key during registration.

