# eventsOs REST API Documentation for Frontend Integration

> **Version**: 1.0.0  
> **Base URL**: `http://localhost:8000/api/v1`  
> **Auth Scheme**: HTTP Bearer Token (`Sanctum`)  
> **Format**: `JSON`

This document provides complete, production-ready specifications for the `eventsOs` backend API. It is structured so that any AI agent (e.g., Claude, ChatGPT, v0) or Frontend Developer can quickly build the web or mobile frontend interface.

---

## Table of Contents
1. [Global API Standards & Headers](#1-global-api-standards--headers)
2. [Response Envelope Contracts](#2-response-envelope-contracts)
3. [User & Role Domain Architecture](#3-user--role-domain-architecture)
4. [Authentication & Onboarding API](#4-authentication--onboarding-api)
5. [User Profile & Device Management API](#5-user-profile--device-management-api)
6. [Organizer Management API](#6-organizer-management-api)
7. [Team Members API (RBAC)](#7-team-members-api-rbac)
8. [Staff Invitations API](#8-staff-invitations-api)
9. [Events Management API](#9-events-management-api)
10. [TypeScript Definitions & Axios Setup](#10-typescript-definitions--axios-setup)

---

## 1. Global API Standards & Headers

All requests sent to the backend **MUST** include the standard JSON headers:

```http
Content-Type: application/json
Accept: application/json
```

### Authentication Header
Protected endpoints require a Sanctum Bearer Token obtained from `/auth/login`, `/auth/register`, or `/auth/invitations/accept`:

```http
Authorization: Bearer <your_access_token>
```

### Active Organizer Context Header
When performing actions on behalf of a specific organization, pass the organization's UUID in either of these headers:

```http
X-Organizer-UUID: <organizer_uuid>
```
*(or path parameter `organizerUuid` in organization-scoped routes)*.

---

## 2. Response Envelope Contracts

All API endpoints return a uniform JSON payload format.

### Success Response (`200 OK`, `201 Created`)
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

### Validation Error Response (`422 Unprocessable Entity`)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password confirmation does not match."]
  }
}
```

### Authentication & Authorization Error (`401` / `403`)
```json
{
  "success": false,
  "message": "Unauthenticated.",
  "code": "UNAUTHENTICATED"
}
```
*(For `403 Forbidden`: `"code": "FORBIDDEN"` or `"Role owner or admin required."`)*

---

## 3. User & Role Domain Architecture

### Account Types (`accountType`)
- `customer`: Ticket buyers / event attendees.
- `organizer`: Organization owners, admins, managers, and staff.

### Staff Roles & Permissions Hierarchy (`role`)
| Role | Rank | Description |
| :--- | :--- | :--- |
| `owner` | **4** | Primary owner of the organization. Full control over all members, settings, and billing. |
| `admin` | **3** | Administrator. Can manage `manager` and `staff` roles. Cannot modify `owner` or `admin`. |
| `manager` | **2** | Team manager. Can add/manage `staff` members. |
| `staff` | **1** | Base team member. Operational event permissions only. |

---

## 4. Authentication & Onboarding API

### 4.1. User Registration
`POST /api/v1/auth/register`

Register a new `customer` account or a new `organizer` account.

#### Request Body (Customer):
```json
{
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "account_type": "customer"
}
```

#### Request Body (Organizer):
```json
{
  "first_name": "Kevin",
  "last_name": "Owner",
  "email": "kevin@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "account_type": "organizer",
  "organizer_type": "organization",
  "organization_name": "Apex Events Ltd"
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": "c71e8609-b4f2-45e0-aa33-85b40cf9e3fa",
      "firstName": "Kevin",
      "lastName": "Owner",
      "email": "kevin@example.com",
      "isEmailVerified": false,
      "accountType": "organizer"
    },
    "organizer": {
      "id": "bb9a800b-4850-4137-9b48-490782b77d6b",
      "name": "Apex Events Ltd",
      "slug": "apex-events-ltd",
      "type": "organization",
      "role": "owner",
      "isOwner": true
    },
    "token": "1|sanctum_access_token_here..."
  }
}
```

---

### 4.2. User Login
`POST /api/v1/auth/login`

Authenticate user credentials and issue a Bearer token.

#### Request Body:
```json
{
  "email": "kevin@example.com",
  "password": "Password123!",
  "remember": true
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": "c71e8609-b4f2-45e0-aa33-85b40cf9e3fa",
      "firstName": "Kevin",
      "lastName": "Owner",
      "email": "kevin@example.com",
      "isEmailVerified": true,
      "accountType": "organizer"
    },
    "organizer": {
      "id": "bb9a800b-4850-4137-9b48-490782b77d6b",
      "name": "Apex Events Ltd",
      "slug": "apex-events-ltd",
      "type": "organization",
      "role": "owner",
      "isOwner": true
    },
    "token": "2|sanctum_access_token_here..."
  }
}
```

---

### 4.3. Current User Context (`/me`)
`GET /api/v1/auth/me`

*Headers Required*: `Authorization: Bearer <token>`

Fetch details of the currently authenticated user and their active organization context.

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "User data fetched successfully",
  "data": {
    "user": {
      "id": "c71e8609-b4f2-45e0-aa33-85b40cf9e3fa",
      "firstName": "Kevin",
      "lastName": "Owner",
      "email": "kevin@example.com",
      "isEmailVerified": true,
      "accountType": "organizer"
    },
    "organizer": {
      "id": "bb9a800b-4850-4137-9b48-490782b77d6b",
      "name": "Apex Events Ltd",
      "slug": "apex-events-ltd",
      "type": "organization",
      "role": "owner",
      "isOwner": true
    },
    "email_verified": true
  }
}
```

---

### 4.4. Refresh Token
`POST /api/v1/auth/refresh`

*Headers Required*: `Authorization: Bearer <token>`

Revokes the current access token and returns a new 2-hour access token.

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Token refreshed successfully.",
  "data": {
    "user": { ... },
    "organizer": { ... },
    "token": "3|sanctum_fresh_access_token..."
  }
}
```

---

### 4.5. Logout
`POST /api/v1/auth/logout`

*Headers Required*: `Authorization: Bearer <token>`

Revokes the current Bearer token.

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### 4.6. Password Reset Flow

#### Forgot Password:
`POST /api/v1/auth/password/forgot`
```json
{ "email": "kevin@example.com" }
```

#### Reset Password:
`POST /api/v1/auth/password/reset`
```json
{
  "token": "reset_token_from_email",
  "email": "kevin@example.com",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

---

## 5. User Profile & Device Management API

### 5.1. Update Profile
`PUT /api/v1/auth/profile`

*Headers Required*: `Authorization: Bearer <token>`

#### Request Body:
```json
{
  "first_name": "Kevin",
  "last_name": "Updated",
  "phone": "+1987654321",
  "timezone": "America/New_York"
}
```

---

### 5.2. Update Password
`PUT /api/v1/auth/password`

*Headers Required*: `Authorization: Bearer <token>`

#### Request Body:
```json
{
  "current_password": "Password123!",
  "password": "NewSecurePassword123!",
  "password_confirmation": "NewSecurePassword123!"
}
```

---

### 5.3. Delete Account
`DELETE /api/v1/auth/account`

*Headers Required*: `Authorization: Bearer <token>`

Soft deletes the user account and invalidates all session tokens.

---

### 5.4. List Active Sessions / Devices
`GET /api/v1/auth/sessions`

*Headers Required*: `Authorization: Bearer <token>`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Active sessions retrieved.",
  "data": [
    {
      "id": 2,
      "name": "Mozilla/5.0 (Macintosh...)",
      "lastUsedAt": "2026-08-28T12:30:00.000000Z",
      "createdAt": "2026-08-28T10:00:00.000000Z",
      "isCurrent": true
    }
  ]
}
```

---

### 5.5. Logout Other Devices
`POST /api/v1/auth/logout-others`

*Headers Required*: `Authorization: Bearer <token>`

Revokes all active sessions except the token sending the request.

---

## 6. Organizer Management API

### 6.1. List My Organizations
`GET /api/v1/organizers`

*Headers Required*: `Authorization: Bearer <token>`

Returns a paginated list of all organizations owned by or attached to the user.

---

### 6.2. Create Organization
`POST /api/v1/organizers`

*Headers Required*: `Authorization: Bearer <token>`

#### Request Body:
```json
{
  "name": "Superfest Events",
  "type": "organization"
}
```

---

### 6.3. Get Single Organization
`GET /api/v1/organizers/{organizerUuid}`

*Headers Required*: `Authorization: Bearer <token>`

---

### 6.4. Update Organization
`PUT /api/v1/organizers/{organizerUuid}`

*Headers Required*: `Authorization: Bearer <token>`

#### Request Body:
```json
{
  "name": "Superfest Global Events",
  "type": "organization"
}
```

---

### 6.5. Delete Organization
`DELETE /api/v1/organizers/{organizerUuid}`

*Headers Required*: `Authorization: Bearer <token>` (Owner rank required)

---

## 7. Team Members API (RBAC)

All routes require `Authorization: Bearer <token>` and enforce rank permissions:
- `owner` (Rank 4) can manage `admin`, `manager`, `staff`.
- `admin` (Rank 3) can manage `manager`, `staff`.
- `manager` (Rank 2) can manage `staff`.

### 7.1. List Team Members
`GET /api/v1/organizers/{organizerUuid}/members`

---

### 7.2. Add Direct Member
`POST /api/v1/organizers/{organizerUuid}/members`

#### Request Body:
```json
{
  "email": "newmember@example.com",
  "role": "manager"
}
```

---

### 7.3. Update Member Role
`PUT /api/v1/organizers/{organizerUuid}/members/{memberUuid}`

#### Request Body:
```json
{
  "role": "staff"
}
```

---

### 7.4. Remove Team Member
`DELETE /api/v1/organizers/{organizerUuid}/members/{memberUuid}`

---

## 8. Staff Invitations API

Allows organization owners/admins to send email invitations to new or existing users.

### 8.1. List Pending Invitations
`GET /api/v1/organizers/{organizerUuid}/invitations`

*Headers Required*: `Authorization: Bearer <token>`

---

### 8.2. Create Staff Invitation
`POST /api/v1/organizers/{organizerUuid}/invitations`

*Headers Required*: `Authorization: Bearer <token>`

#### Request Body:
```json
{
  "email": "sarah.admin@example.com",
  "role": "admin"
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "message": "Invitation sent successfully.",
  "data": {
    "id": "e4a2119c-9892-4d2a-8991-5f2105151594",
    "email": "sarah.admin@example.com",
    "role": "admin",
    "token": "40_character_unique_invitation_token",
    "expiresAt": "2026-09-04T12:00:00.000000Z",
    "createdAt": "2026-08-28T12:00:00.000000Z"
  }
}
```

---

### 8.3. Resend Invitation
`POST /api/v1/organizers/{organizerUuid}/invitations/{invitationUuid}/resend`

Extends invitation expiration by 7 days.

---

### 8.4. Revoke Invitation
`DELETE /api/v1/organizers/{organizerUuid}/invitations/{invitationUuid}`

---

### 8.5. Accept Invitation (Public Onboarding)
`POST /api/v1/auth/invitations/accept`

*No Auth Required*. Used when an invited user clicks their invitation link.

#### Request Body:
```json
{
  "token": "40_character_unique_invitation_token",
  "first_name": "Sarah",
  "last_name": "Admin",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Invitation accepted successfully.",
  "data": {
    "user": {
      "id": "d1122334-5566-7788-9900-aabbccddeeff",
      "firstName": "Sarah",
      "lastName": "Admin",
      "email": "sarah.admin@example.com",
      "isEmailVerified": true,
      "accountType": "organizer"
    },
    "organizer": {
      "id": "bb9a800b-4850-4137-9b48-490782b77d6b",
      "name": "Apex Events Ltd",
      "slug": "apex-events-ltd",
      "type": "organization",
      "role": "admin",
      "isOwner": false
    },
    "token": "4|sanctum_access_token_here..."
  }
}
```

---

## 9. Events Management API

### 9.1. Event Metadata & Options
Public metadata endpoints used for frontend dropdown menus, event creation selects, and search filters.

#### List Event Formats:
`GET /api/v1/meta/event-formats`

*Public / No Auth Required*

##### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Event formats retrieved successfully.",
  "data": [
    {
      "id": "5f13459e-e37d-419b-a018-8f85f1c9d2f3",
      "name": "Conference",
      "slug": "conference"
    },
    {
      "id": "8a329d2b-6c47-4f62-bdf9-22a837c78491",
      "name": "Workshop",
      "slug": "workshop"
    },
    {
      "id": "3c98fa11-42cb-4bc1-9c87-99ea74b59302",
      "name": "Festival",
      "slug": "festival"
    },
    {
      "id": "4d12ab56-78ef-4190-87a1-12c84918ef03",
      "name": "Summit",
      "slug": "summit"
    }
  ]
}
```

#### List Supported Event Types:
`GET /api/v1/meta/event-types`

*Public / No Auth Required*

##### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Event types retrieved successfully.",
  "data": [
    { "key": "physical", "name": "Physical" },
    { "key": "virtual", "name": "Virtual" },
    { "key": "hybrid", "name": "Hybrid" }
  ]
}
```

#### List Standard Categories:
`GET /api/v1/meta/categories`

*Public / No Auth Required*

---

### 9.2. Explore Public Events
`GET /api/v1/explore/events`

*Public / No Auth Required*

Query Parameters:
- `search`: string (filters title, description, or venue name)
- `eventType`: string (`physical`, `virtual`, `hybrid`)
- `category`: string (matches category or format)
- `perPage`: integer (default 12)

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Public events retrieved.",
  "data": {
    "data": [
      {
        "id": "7b6f6f96-3c66-4f4d-b636-fbe68903c72b",
        "title": "Lagos Tech Fest 2026",
        "slug": "lagos-tech-fest-2026",
        "eventType": "physical",
        "eventFormat": "Festival",
        "eventCategory": ["Technology", "Startups"],
        "venueName": "Landmark Centre",
        "city": "Lagos",
        "startDate": "2026-10-15",
        "startTime": "09:00",
        "bannerUrl": "https://images.unsplash.com/photo-1540575467063-178a50c2df87",
        "status": "published"
      }
    ]
  }
}
```

---

### 9.2. Public Event Details by Slug
`GET /api/v1/explore/events/{slug}`

*Public / No Auth Required*. Returns full event details with `location`, `schedule`, `schedules` (agenda items), `lineup`, `ticketTypes`, `faqs`, and `galleryUrls`.

---

### 9.3. List Organizer Events
`GET /api/v1/organizers/{organizerUuid}/events`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`, `staff`

Query Parameters: `status`, `eventType`, `search`, `perPage`.

---

### 9.4. Get Single Organizer Event
`GET /api/v1/organizers/{organizerUuid}/events/{eventUuid}`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`, `staff`

---

### 9.5. Create Event
`POST /api/v1/organizers/{organizerUuid}/events`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`

#### Request Body:
```json
{
  "title": "Global Cloud Summit 2026",
  "description": "Comprehensive 3-day deep dive into modern systems.",
  "eventCountry": "Nigeria",
  "eventType": "physical",
  "eventFormat": "Conference",
  "eventCategory": ["Tech", "Cloud"],
  "venueName": "Eko Convention Centre",
  "city": "Lagos",
  "state": "Lagos",
  "scheduleType": "single",
  "scheduleTimezone": "Africa/Lagos",
  "startDate": "2026-11-20",
  "startTime": "09:00",
  "endDate": "2026-11-20",
  "endTime": "18:00",
  "bannerUrl": "https://example.com/banner.jpg",
  "faqs": [
    { "question": "Is parking available?", "answer": "Yes, on-site parking is free." }
  ],
  "galleryUrls": [
    "https://images.unsplash.com/photo-1540575467063-178a50c2df87"
  ],
  "schedules": [
    {
      "day": "Day 1",
      "title": "Keynote Address",
      "startTime": "09:00",
      "endTime": "10:30"
    }
  ],
  "lineup": [
    {
      "name": "Sarah Johnson",
      "role": "Principal Architect",
      "avatarUrl": "https://example.com/sarah.jpg"
    }
  ]
}
```

#### Success Response (`201 Created`):
Returns the created event resource envelope (`status: "draft"`).

---

### 9.6. Update Event
`PUT /api/v1/organizers/{organizerUuid}/events/{eventUuid}`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`

Accepts any subset of editable fields, including updated `faqs`, `galleryUrls`, `schedules`, and `lineup`.

---

### 9.7. Publish Event
`POST /api/v1/organizers/{organizerUuid}/events/{eventUuid}/publish`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`

Transitions event to `published`. Returns `422 Unprocessable Entity` (`CannotPublishWithoutTickets`) if no ticket types are created yet.

---

### 9.8. Unpublish Event
`POST /api/v1/organizers/{organizerUuid}/events/{eventUuid}/unpublish`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`

Reverts event status back to `draft`.

---

### 9.9. Duplicate / Clone Event
`POST /api/v1/organizers/{organizerUuid}/events/{eventUuid}/duplicate`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`

Performs a deep clone of the event, location, schedule, agenda items, lineup, and ticket types into a new `draft` event titled `"{Original Title} (Copy)"`. Reset ticket sold/reserved counts to 0.

#### Success Response (`201 Created`):
Returns the duplicated event resource.

---

### 9.10. Event Statistics & Metrics
`GET /api/v1/organizers/{organizerUuid}/events/{eventUuid}/stats`

*Headers Required*: `Authorization: Bearer <token>`  
*Allowed Roles*: `owner`, `admin`, `manager`, `staff`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Event statistics retrieved successfully.",
  "data": {
    "totalCapacity": 500,
    "isCapacityUnlimited": false,
    "soldTickets": 120,
    "reservedTickets": 15,
    "remainingTickets": 365,
    "totalRevenue": 1800000.0,
    "currency": "NGN",
    "checkedInCount": 42,
    "favoritesCount": 85,
    "ticketBreakdown": [
      {
        "id": "ticket-type-uuid-1",
        "name": "Early Bird",
        "type": "paid",
        "price": 15000.0,
        "currency": "NGN",
        "quantity": 100,
        "soldQuantity": 100,
        "reservedQuantity": 0,
        "revenue": 1500000.0,
        "status": "sold_out"
      }
    ]
  }
}
```

---

### 9.11. Archive Event
`POST /api/v1/organizers/{organizerUuid}/events/{eventUuid}/archive`

---

### 9.12. Cancel Event
`POST /api/v1/organizers/{organizerUuid}/events/{eventUuid}/cancel`

---

### 9.13. Delete Event
`DELETE /api/v1/organizers/{organizerUuid}/events/{eventUuid}`

---

## 10. TypeScript Definitions & Axios Setup

Pass this code directly to your frontend AI builder (React / Next.js / Vue / React Native):

### TypeScript Data Models (`types/api.ts`)
```typescript
export type AccountType = 'customer' | 'organizer';
export type OrganizerType = 'individual' | 'organization';
export type StaffRole = 'owner' | 'admin' | 'manager' | 'staff';

export interface User {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  isEmailVerified: boolean;
  accountType: AccountType;
}

export interface Organizer {
  id: string;
  name: string;
  slug: string;
  type: OrganizerType;
  role?: StaffRole;
  isOwner?: boolean;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface AuthPayload {
  user: User;
  organizer: Organizer | null;
  token: string;
}
```

### Axios Client Setup (`lib/apiClient.ts`)
```typescript
import axios from 'axios';

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request Interceptor: Attach Auth Token & Active Organizer UUID
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  const activeOrganizerUuid = localStorage.getItem('active_organizer_uuid');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  if (activeOrganizerUuid) {
    config.headers['X-Organizer-UUID'] = activeOrganizerUuid;
  }
  return config;
});

// Response Interceptor: Auto Refresh Token on 401
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true;
      try {
        const refreshRes = await apiClient.post<ApiResponse<AuthPayload>>('/auth/refresh');
        const newToken = refreshRes.data.data.token;
        localStorage.setItem('access_token', newToken);
        error.config.headers.Authorization = `Bearer ${newToken}`;
        return apiClient(error.config);
      } catch (refreshErr) {
        localStorage.removeItem('access_token');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);
```
