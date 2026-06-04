# The Admin Vault - Technical Documentation

> **Plugin Version:** 1.2.5  
> **Last Updated:** June 4, 2026  
> **PHP Requirement:** 8.0+  
> **Dependencies:** ACF Pro (required), WooCommerce (optional), Client Command Center (companion)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Architecture Overview](#2-architecture-overview)
3. [File Structure](#3-file-structure)
4. [Page & View Documentation](#4-page--view-documentation)
5. [Data Models](#5-data-models)
6. [Workflow Diagrams](#6-workflow-diagrams)
7. [API & AJAX Endpoints](#7-api--ajax-endpoints)
8. [Database Schema](#8-database-schema)
9. [Integration Points](#9-integration-points)
10. [Functionality Status Matrix](#10-functionality-status-matrix)
11. [Configuration & Settings](#11-configuration--settings)
12. [Security Considerations](#12-security-considerations)
13. [Work Remaining & Recommendations](#13-work-remaining--recommendations)
14. [Testing Checklist](#14-testing-checklist)

---

## 1. Executive Summary

### Purpose
The Admin Vault is a WordPress plugin providing a private CRM dashboard for managing **Storyteller profiles** — social handles, verified metrics, private contact information, and authenticity scores. It's the operator-facing component of the **Verified Storytellers** platform.

### Key Features
- ✅ Custom Post Type for Storytellers with ACF Pro fields
- ✅ Custom admin dashboard with modern UI
- ✅ Request management and fulfillment workflow
- ✅ Client management with status tracking
- ✅ Revenue analytics with Chart.js
- ✅ Email template customization
- ✅ Frontend admin portal at `/admin-dashboard/`

### Tech Stack
| Component | Technology |
|-----------|------------|
| Backend | WordPress 6.x, PHP 8.0+ |
| Fields | Advanced Custom Fields Pro |
| Charts | Chart.js 4.4.0 (CDN) |
| Icons | WordPress Dashicons |
| Fonts | Google Fonts (Inter) |
| E-commerce | WooCommerce (for revenue data) |

---

## 2. Architecture Overview

### High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         VERIFIED STORYTELLERS PLATFORM                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────┐         ┌─────────────────────────────────┐   │
│  │   CLIENT SIDE       │         │       ADMIN/OPERATOR SIDE       │   │
│  │                     │         │                                 │   │
│  │  ┌───────────────┐  │         │  ┌───────────────────────────┐  │   │
│  │  │ Client        │  │         │  │ The Admin Vault           │  │   │
│  │  │ Command       │◄─┼─────────┼──┤ (this plugin)             │  │   │
│  │  │ Center        │  │         │  │                           │  │   │
│  │  │               │  │ Request │  │  • Storyteller Management │  │   │
│  │  │ • Requests    │  │ Status  │  │  • Request Fulfillment    │  │   │
│  │  │ • Payments    │  │ Updates │  │  • Client Overview        │  │   │
│  │  │ • Reviews     │  │         │  │  • Revenue Analytics      │  │   │
│  │  └───────────────┘  │         │  └───────────────────────────┘  │   │
│  │                     │         │                                 │   │
│  └─────────────────────┘         └─────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      SHARED INFRASTRUCTURE                       │   │
│  │  ┌─────────────┐  ┌──────────┐  ┌────────────┐  ┌────────────┐  │   │
│  │  │ WordPress   │  │ ACF Pro  │  │ WooCommerce│  │ Ultimate   │  │   │
│  │  │ Core        │  │ Fields   │  │ Orders     │  │ Member     │  │   │
│  │  └─────────────┘  └──────────┘  └────────────┘  └────────────┘  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Plugin Internal Architecture

```
┌────────────────────────────────────────────────────────────────┐
│                    THE ADMIN VAULT PLUGIN                       │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                 the-admin-vault.php                       │  │
│  │  ┌────────────┐ ┌────────────┐ ┌────────────────────┐    │  │
│  │  │ CPT        │ │ Taxonomies │ │ ACF Field Groups   │    │  │
│  │  │ storyteller│ │ vs_niche   │ │ group_tav_         │    │  │
│  │  │            │ │ storyteller│ │ storyteller        │    │  │
│  │  │            │ │ _tag       │ │                    │    │  │
│  │  └────────────┘ └────────────┘ └────────────────────┘    │  │
│  │  ┌────────────┐ ┌────────────┐ ┌────────────────────┐    │  │
│  │  │ Admin      │ │ Capability │ │ Email Override     │    │  │
│  │  │ Columns    │ │ Management │ │ Filters            │    │  │
│  │  └────────────┘ └────────────┘ └────────────────────┘    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                 │
│                              ▼                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              admin/dashboard.php (Controller)             │  │
│  │  ┌─────────────┐ ┌──────────────┐ ┌─────────────────┐    │  │
│  │  │ Menu        │ │ View Router  │ │ AJAX Handlers   │    │  │
│  │  │ Registration│ │              │ │                 │    │  │
│  │  └─────────────┘ └──────────────┘ └─────────────────┘    │  │
│  │  ┌─────────────┐ ┌──────────────┐ ┌─────────────────┐    │  │
│  │  │ Data        │ │ Fulfillment  │ │ Asset           │    │  │
│  │  │ Helpers     │ │ Handler      │ │ Enqueue         │    │  │
│  │  └─────────────┘ └──────────────┘ └─────────────────┘    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                 │
│              ┌───────────────┼───────────────┐                 │
│              ▼               ▼               ▼                 │
│  ┌────────────────┐ ┌────────────────┐ ┌────────────────┐     │
│  │ admin/views/   │ │ frontend/      │ │ assets/        │     │
│  │                │ │                │ │                │     │
│  │ • dashboard    │ │ admin-portal   │ │ • CSS          │     │
│  │ • storytellers │ │ .php           │ │ • JS           │     │
│  │ • clients      │ │                │ │                │     │
│  │ • requests     │ │ /admin-        │ │ tav-dashboard  │     │
│  │ • fulfillment  │ │ dashboard/     │ │ .css/.js       │     │
│  │ • settings     │ │ page           │ │                │     │
│  │ • notifications│ │                │ │                │     │
│  └────────────────┘ └────────────────┘ └────────────────┘     │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## 3. File Structure

```
adriana-lacy-admin-vault/
│
├── the-admin-vault.php          # Main plugin file (1,158 lines)
│   ├── Plugin header & constants
│   ├── CPT registration (storyteller)
│   ├── Taxonomy registration (vs_niche, storyteller_tag)
│   ├── Capability management
│   ├── ACF field group definition
│   ├── Admin columns & filters
│   ├── Meta sync hooks
│   ├── Backfill migrations
│   └── Email filter overrides
│
├── admin/
│   ├── dashboard.php            # Dashboard controller (1,518 lines)
│   │   ├── Menu registration
│   │   ├── Fulfillment POST handler
│   │   ├── Asset enqueue
│   │   ├── 20+ data helper functions
│   │   ├── 4 AJAX endpoint handlers
│   │   └── Main view router
│   │
│   └── views/
│       ├── dashboard.php        # Home dashboard (230 lines)
│       ├── storytellers.php     # List/Add/Edit (236 lines)
│       ├── clients.php          # Client management (209 lines)
│       ├── requests.php         # Request inbox (1,198 lines)
│       ├── fulfillment.php      # Assignment UI (387 lines)
│       ├── settings.php         # Configuration (491 lines)
│       └── notifications.php    # Activity feed (275 lines)
│
├── frontend/
│   └── admin-portal.php         # Frontend portal (342 lines)
│       ├── Access guard
│       ├── Shortcode registration
│       ├── Page auto-creation
│       └── Standalone template render
│
├── assets/
│   ├── css/
│   │   ├── tav-dashboard.css    # Main styles (2,988 lines)
│   │   └── tav-admin-portal.css # Portal overrides (59 lines)
│   │
│   └── js/
│       └── tav-dashboard.js     # Client-side logic (489 lines)
│           ├── Sidebar toggle
│           ├── Revenue chart
│           ├── Storyteller modal
│           ├── Client modal
│           └── Filter toggles
│
├── README.md                    # Basic readme
├── ADMIN-TRAINING-GUIDE.md      # User guide
└── TECHNICAL-DOCUMENTATION.md   # This document
```

### Line Count Summary

| File Type | Count | Total Lines |
|-----------|-------|-------------|
| PHP       | 10    | ~5,844      |
| CSS       | 2     | ~3,047      |
| JS        | 1     | ~489        |
| **Total** | **13**| **~9,380**  |

---

## 4. Page & View Documentation

### 4.1 Dashboard (Home)

**URL:** `admin.php?page=tav-dashboard` or `admin.php?page=tav-dashboard&view=dashboard`

```
┌─────────────────────────────────────────────────────────────────┐
│ Welcome, [User Name]                                            │
│ Your current sales summary and activity.                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐            │
│  │ Active       │ │ Vetted       │ │ Satisfaction │            │
│  │ Requests     │ │ Storytellers │ │ Rate         │            │
│  │     12       │ │     247      │ │   4.2/5.0    │            │
│  └──────────────┘ └──────────────┘ └──────────────┘            │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Total Revenue                    [All Time][30d][7d][24h]│   │
│  │ $45,230                                                  │   │
│  │ ┌───────────────────────────────────────────────────┐   │   │
│  │ │            📈 Revenue Chart (Chart.js)            │   │   │
│  │ │                                                   │   │   │
│  │ └───────────────────────────────────────────────────┘   │   │
│  │ ● Received  ● Pending                                   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────┐ ┌───────────────────────┐ │
│  │ Request Fulfillment Center      │ │ Recent Activity       │ │
│  │ ├─ Project A (Paid) [View]      │ │ • New request: ...    │ │
│  │ ├─ Project B (Matching) [View]  │ │ • Payment received... │ │
│  │ └─ Project C (In Vetting)       │ │ • New storyteller...  │ │
│  │                    [View All →] │ │ • Client registered...│ │
│  └─────────────────────────────────┘ └───────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Data Sources:**
- `tav_get_active_requests_count()` - Active request count
- `tav_get_total_storytellers()` - Total storyteller count
- `tav_get_verified_count()` - Verified (score ≥70) count
- `tav_get_satisfaction_rate()` - Client satisfaction metrics
- `tav_get_revenue()` - Monthly and all-time revenue
- `tav_get_revenue_chart_data()` - Chart data by period
- `tav_get_activity_feed()` - Merged activity events
- `tav_get_pending_fulfillment_requests()` - Paid requests awaiting fulfillment

---

### 4.2 Storytellers

**URL:** `admin.php?page=tav-dashboard&view=storytellers`

```
┌─────────────────────────────────────────────────────────────────┐
│ Verified Storytellers                    [+ Add Storyteller]    │
│ Manage your exclusive talent list                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ [Search by name...] [Location...] [Niche ▼] [Filter] [Clear]   │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ NAME        │ LOCATION │ NICHE  │ PLATFORMS │ FOLLOWERS │  │  │
│ │ ENGAGEMENT  │ STATUS   │ ACTIONS                        │  │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 🧑 Sarah M. │ NYC      │Climate │ IG, TikTok│ 125K     │  │  │
│ │ 4.2%        │ ●Active  │              [✏️] [🗑️]        │  │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 🧑 David C. │ LA       │Tech    │ YouTube   │ 89K      │  │  │
│ │ 6.1%        │ ●Verified│              [✏️] [🗑️]        │  │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│                    « Prev  Page 1 of 5  Next »                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Sub-views:**
- **Add Storyteller** (`view=add-teller`) - ACF form for new storyteller
- **Edit Storyteller** (`view=edit-teller&post_id=X`) - ACF form for editing

**Filters:**
- Name search (WordPress `s` parameter)
- Location (meta `LIKE` query)
- Niche (taxonomy `vs_niche`)

**Table Columns:**
| Column | Source |
|--------|--------|
| Name | `post_title` + thumbnail |
| Location | ACF `location` field |
| Niche | `vs_niche` taxonomy terms |
| Platforms | `platforms_repeater` → names |
| Followers | `tav_total_followers` meta |
| Engagement | `tav_avg_engagement_rate` meta |
| Status | ACF `campaign_status` field |

---

### 4.3 Clients

**URL:** `admin.php?page=tav-dashboard&view=clients`

```
┌─────────────────────────────────────────────────────────────────┐
│ Clients                                                         │
│ Manage your client base                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ [Search clients...]                              [Search][Clear]│
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ NAME/EMAIL      │ ORG    │ REQUESTS │ SPENT  │ STATUS    │  │
│ │ JOINED          │ ACTIONS                                │  │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 👤 Jane Smith   │ ACME   │    5     │ $3,200 │ ● Active  │  │
│ │ jane@acme.com   │ Jan 15, 2026           [View Detail]   │  │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 👤 John Doe     │ StartUp│    2     │ $1,200 │ ● VIP     │  │
│ │ john@startup.io │ Feb 28, 2026           [View Detail]   │  │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Client Detail Modal (AJAX):**
```
┌─────────────────────────────────────────────┐
│ ACME Inc Account Details              [×]   │
├─────────────────────────────────────────────┤
│                                             │
│ 👤 Jane Smith                               │
│    ACME Inc                                 │
│    jane@acme.com                            │
│                                             │
│ Status: [Active ▼]                          │
│                                             │
│ Internal Notes:                             │
│ ┌─────────────────────────────────────────┐ │
│ │ VIP client, priority support           │ │
│ └─────────────────────────────────────────┘ │
│                                             │
│ Request History                             │
│ ┌─────────────────────────────────────────┐ │
│ │ Request    │ Package │ Date    │ Total  │ │
│ │ Campaign A │ Custom  │ Mar 15  │ $600   │ │
│ │ Campaign B │ Premium │ Apr 20  │ $900   │ │
│ └─────────────────────────────────────────┘ │
│                                             │
│                         [Save Changes]      │
└─────────────────────────────────────────────┘
```

**Data Sources:**
- `WP_User_Query` with roles `um_client` or `client`
- `organization_name` user meta
- `ccc_client_status` user meta (active/suspended/vip)
- `ccc_client_notes` user meta
- `count_user_posts()` for request count
- `wc_get_orders()` for total spent

---

### 4.4 Requests

**URL:** `admin.php?page=tav-dashboard&view=requests`

```
┌─────────────────────────────────────────────────────────────────┐
│ Search Requests Management                                      │
│ Manage and track all client storyteller search requests         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ [🔍 Search]                                         [Filters ▼] │
│                                                                 │
│ ┌─ Advanced Filters (collapsible) ────────────────────────────┐ │
│ │ Status: [All ▼] Client: [All ▼] Niche: [All ▼]             │ │
│ │ Date: [From] - [To]                    [Apply] [Clear]      │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ PROJECT    │ CLIENT  │ STATUS         │ SUBMITTED │ DUE  │  │
│ │ ACTIONS                                                   │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ Campaign A │ Jane S. │ ● Paid         │ Jun 1    │ Jun 8│  │
│ │                                    [Start Matching]       │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ Campaign B │ John D. │ ● Matching     │ May 28   │ Jun 5│  │
│ │                                   [Assign Storytellers]   │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ Campaign C │ Jane S. │ ● Ready Review │ May 20   │ —    │  │
│ │                                      [Awaiting Client]    │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│                    Page 1 of 3    [Previous] [Next]             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Request Status Pipeline:**

```
┌──────────────────────────────────────────────────────────────────────┐
│                        REQUEST STATUS WORKFLOW                        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   ┌─────────────┐     ┌─────────┐     ┌────────────┐                │
│   │ PENDING     │────▶│  PAID   │────▶│ IN_VETTING │                │
│   │ PAYMENT     │     │         │     │            │                │
│   └─────────────┘     └─────────┘     └────────────┘                │
│         │                                   │                        │
│         │ (Client pays                      │ (Admin starts          │
│         │  via WooCommerce)                 │  reviewing brief)      │
│         ▼                                   ▼                        │
│   ┌─────────────┐                    ┌────────────┐                  │
│   │  ARCHIVED   │                    │  MATCHING  │                  │
│   │ (cancelled) │                    │            │                  │
│   └─────────────┘                    └────────────┘                  │
│                                            │                         │
│                                            │ (Admin selects          │
│                                            │  5-8 storytellers)      │
│                                            ▼                         │
│                                     ┌────────────┐                   │
│                                     │READY_REVIEW│                   │
│                                     │            │                   │
│                                     └────────────┘                   │
│                                            │                         │
│                                            │ (Client reviews &       │
│                                            │  picks favorites)       │
│                                            ▼                         │
│                                     ┌────────────┐                   │
│                                     │  ASSIGNED  │                   │
│                                     │            │                   │
│                                     └────────────┘                   │
│                                            │                         │
│                                            │ (Project finished)      │
│                                            ▼                         │
│                                     ┌────────────┐                   │
│                                     │ COMPLETED  │                   │
│                                     │            │                   │
│                                     └────────────┘                   │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

**Status Actions:**

| Status | Available Actions |
|--------|-------------------|
| `pending_payment` | View Brief |
| `paid` | Start Matching → Fulfillment |
| `in_vetting` | Continue Vetting → Fulfillment |
| `matching` | Assign Storytellers → Fulfillment |
| `ready_review` | Awaiting Client (view only) |
| `assigned` | View Selection, Mark Complete |
| `completed` | View Report |
| `archived` | View Archive |

---

### 4.5 Fulfillment

**URL:** `admin.php?page=tav-dashboard&view=fulfill&request_id=X`

```
┌─────────────────────────────────────────────────────────────────┐
│ Fulfill Request: Campaign A                [← Back to Requests] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌───────────────────┐  ┌───────────────────────────────────────┐│
│ │ Client Brief      │  │ Select Storytellers                   ││
│ │                   │  │                                       ││
│ │ Client: Jane S.   │  │ [Search...][Location][Niche▼]         ││
│ │ Niche: Climate    │  │ [Platform▼][Followers▼][Engagement▼]  ││
│ │ Package: Custom   │  │ [Search] [Clear]                      ││
│ │ Count: 5          │  │                                       ││
│ │ Location: NYC     │  │ ┌─────────────────────────────────┐   ││
│ │ Timeline: 1 week  │  │ │ ☑ Sarah M.  │ NYC │ 125K │ 4.2%│   ││
│ │                   │  │ │ ☑ David C.  │ LA  │ 89K  │ 6.1%│   ││
│ │ ─────────────     │  │ │ ☐ Elena K.  │ BER │ 45K  │ 3.8%│   ││
│ │ Additional Info   │  │ │ ☐ Marcus J. │ CHI │ 200K │ 2.1%│   ││
│ │ Looking for       │  │ │                        [View] │   ││
│ │ authentic voices  │  │ └─────────────────────────────────┘   ││
│ │                   │  │                                       ││
│ │ ─────────────     │  │ 2 selected (required: 5-8, target: 5) ││
│ │ Match Deadline    │  │                [Assign to Project]    ││
│ │ June 8, 2026      │  │                                       ││
│ └───────────────────┘  └───────────────────────────────────────┘│
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Selection Rules:**
- Minimum: 5 storytellers
- Maximum: 8 storytellers
- Target: Based on `storyteller_count` meta (clamped to 5-8)

**On Submit:**
1. Validates selection count (5-8)
2. Updates `storytellers` meta with selected IDs
3. Sets status to `ready_review`
4. Publishes the request post
5. Sends notification email to client
6. Redirects to Requests with success message

---

### 4.6 Settings

**URL:** `admin.php?page=tav-dashboard&view=settings`

```
┌─────────────────────────────────────────────────────────────────┐
│ Settings                                                        │
│ Configure application settings and email templates              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Niche Management                                                │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ climate : Climate                                           │ │
│ │ health : Health                                             │ │
│ │ politics : Politics                                         │ │
│ │ tech : Tech                                                 │ │
│ │ ...                                                         │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ─────────────────────────────────────────────────────────────── │
│                                                                 │
│ Email Templates                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ [Request Received] [Storytellers Ready] [Payment] [Reset]  │ │
│ ├─────────────────────────────────────────────────────────────┤ │
│ │ Subject: Your Curated Storytellers are Ready!              │ │
│ │                                                             │ │
│ │ Body:                                                       │ │
│ │ ┌─────────────────────────────────────────────────────────┐ │ │
│ │ │ Hi {{client_name}},                                     │ │ │
│ │ │                                                         │ │ │
│ │ │ We have found some great storytellers for your project  │ │ │
│ │ │ {{project_name}}:                                       │ │ │
│ │ │                                                         │ │ │
│ │ │ {{storyteller_list}}                                    │ │ │
│ │ │                                                         │ │ │
│ │ │ Log in to view them here: {{platform_url}}              │ │ │
│ │ └─────────────────────────────────────────────────────────┘ │ │
│ │                                                             │ │
│ │ Available Placeholders:                                     │ │
│ │ {{client_name}} {{project_name}} {{request_id}}            │ │
│ │ {{storyteller_list}} {{platform_url}}                      │ │
│ │                                                             │ │
│ │                    [Preview Template] [Save Changes]        │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Email Templates:**

| Template | Trigger | Placeholders |
|----------|---------|--------------|
| Request Received | After payment confirmed | `{{client_name}}`, `{{project_name}}`, `{{request_id}}`, `{{package}}`, `{{delivery}}`, `{{platform_url}}` |
| Storytellers Ready | After fulfillment | `{{client_name}}`, `{{project_name}}`, `{{request_id}}`, `{{storyteller_list}}`, `{{platform_url}}` |
| Payment Receipt | WooCommerce order complete | `{{client_name}}`, `{{project_name}}`, `{{request_id}}`, `{{package}}`, `{{total_amount}}`, `{{delivery}}`, `{{platform_url}}` |
| Password Reset | WordPress reset request | `{{user_name}}`, `{{reset_link}}`, `{{site_name}}` |

---

### 4.7 Notifications

**URL:** `admin.php?page=tav-dashboard&view=notifications`

```
┌─────────────────────────────────────────────────────────────────┐
│ Notifications                                                   │
│ Recent activity and alerts requiring your attention             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ 📋 Pending Requests                                        │  │
│ │                                                            │  │
│ │    12                                         [View All →] │  │
│ │    Pending Requests                                        │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│ Recent Activity                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ 📋 Jane Smith submitted a new request: Campaign A         │  │
│ │    2 hours ago                              [View]         │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 💰 Jane Smith completed payment for Campaign B            │  │
│ │    5 hours ago                              [View]         │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ ✅ Request fulfilled: Campaign C — storytellers sent      │  │
│ │    1 day ago                                [View]         │  │
│ ├───────────────────────────────────────────────────────────┤  │
│ │ 👤 New storyteller added: Marcus Johnson                  │  │
│ │    2 days ago                               [View]         │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Activity Event Types:**

| Type | Icon | Source |
|------|------|--------|
| `new_request` | 📋 | Request with `pending_payment` status |
| `payment` | 💰 | Request transitioned to `paid` |
| `fulfilled` | ✅ | Request at `ready_review` status |
| `selections` | 💗 | Request at `assigned` status |
| `new_storyteller` | 👤 | New storyteller post |
| `new_client` | 👥 | New user with client role |

---

## 5. Data Models

### 5.1 Storyteller (Custom Post Type)

```
┌─────────────────────────────────────────────────────────────────┐
│                     STORYTELLER POST TYPE                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  wp_posts                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ ID              │ Post ID                               │   │
│  │ post_title      │ Storyteller name                      │   │
│  │ post_content    │ Extended bio (optional)               │   │
│  │ post_status     │ publish / draft / trash               │   │
│  │ post_type       │ 'storyteller'                         │   │
│  │ post_date       │ Date added                            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ACF Fields (group_tav_storyteller)                             │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ profile_image       │ Image ID (synced to thumbnail)    │   │
│  │ bio                 │ Textarea - short biography        │   │
│  │ location            │ Text - e.g. "Berlin, Germany"     │   │
│  │ private_contact     │ Email - never public              │   │
│  │ authenticity_score  │ Range 1-100                       │   │
│  │ campaign_status     │ Select: prospect/active/paused/   │   │
│  │                     │         completed/declined/verified│   │
│  │ organization_tags   │ Text - comma-separated            │   │
│  │ date_added          │ Date picker                       │   │
│  │ is_verified         │ True/False checkbox               │   │
│  │ verification_notes  │ WYSIWYG - internal notes          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  platforms_repeater (ACF Repeater)                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Row 1:                                                   │   │
│  │   platform_name   │ instagram/tiktok/youtube/twitter/   │   │
│  │                   │ facebook/linkedin/website/other     │   │
│  │   handle          │ @username                           │   │
│  │   follower_count  │ Number                              │   │
│  │   engagement_rate │ Number (decimal %)                  │   │
│  │   profile_url     │ URL                                 │   │
│  │ Row 2: ...                                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  sample_work (ACF Repeater)                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Row 1:                                                   │   │
│  │   content_title   │ Text                                 │   │
│  │   platform        │ Select                               │   │
│  │   view_count      │ Number                               │   │
│  │   url             │ URL                                  │   │
│  │ Row 2: ...                                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Derived Meta (auto-calculated on save)                         │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ tav_avg_engagement_rate │ Average of all platform rates │   │
│  │ tav_total_followers     │ Sum of all follower counts    │   │
│  │ tav_platforms           │ Comma-separated platform slugs│   │
│  │ _thumbnail_id           │ Synced from profile_image     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Taxonomies                                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ vs_niche         │ Hierarchical - Climate, Tech, etc.   │   │
│  │ storyteller_tag  │ Non-hierarchical tags                │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Request (External CPT - from CCC)

```
┌─────────────────────────────────────────────────────────────────┐
│                       REQUEST POST TYPE                          │
│                  (Defined in Client Command Center)              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  wp_posts                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ ID              │ Request ID                            │   │
│  │ post_title      │ Project/campaign name                 │   │
│  │ post_content    │ Brief details                         │   │
│  │ post_author     │ Client user ID                        │   │
│  │ post_status     │ publish / draft                       │   │
│  │ post_type       │ 'request'                             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Key Meta Fields (used by Admin Vault)                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ status               │ pending_payment/paid/in_vetting/ │   │
│  │                      │ matching/ready_review/assigned/  │   │
│  │                      │ completed/archived               │   │
│  │ storyteller_count    │ Requested number of storytellers │   │
│  │ storytellers         │ Array of assigned storyteller IDs│   │
│  │ client_feedback      │ JSON: {st_id: 'interested'/'pass'}│  │
│  │ client_selected_     │ Array of client's final picks   │   │
│  │   storytellers       │                                   │   │
│  │ package_tier         │ quick/custom/premium/retainer/   │   │
│  │                      │ enterprise                        │   │
│  │ due_date             │ Match deadline                    │   │
│  │ woo_order_id         │ WooCommerce order ID              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ACF Fields (from CCC)                                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ campaign_goal        │ Text                              │   │
│  │ location             │ Text                              │   │
│  │ timeline             │ Select                            │   │
│  │ audience_size        │ Select                            │   │
│  │ addons               │ Checkbox array                    │   │
│  │ special_requirements │ Textarea                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Taxonomies                                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ vs_niche         │ Shared with storyteller              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 5.3 Client (WordPress User)

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT USER                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  wp_users                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ ID                │ User ID                              │   │
│  │ user_login        │ Username                             │   │
│  │ user_email        │ Email address                        │   │
│  │ display_name      │ Full name                            │   │
│  │ user_registered   │ Registration date                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  wp_usermeta (TAV-relevant)                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ wp_capabilities   │ Contains 'um_client' or 'client'    │   │
│  │ organization_name │ Company name                         │   │
│  │ ccc_client_status │ active / suspended / vip             │   │
│  │ ccc_client_notes  │ Internal admin notes                 │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. Workflow Diagrams

### 6.1 Fulfillment Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                    FULFILLMENT WORKFLOW                          │
└─────────────────────────────────────────────────────────────────┘

       CLIENT                         ADMIN                    SYSTEM
         │                              │                         │
         │  1. Submits request          │                         │
         │  ─────────────────────────▶  │                         │
         │                              │                         │
         │  2. Completes payment        │                         │
         │  ─────────────────────────▶  │                         │
         │                              │                         │
         │                              │  3. Status → 'paid'     │
         │                              │  ◀──────────────────────│
         │                              │                         │
         │                              │  4. Views in dashboard  │
         │                              │  (Pending Fulfillment)  │
         │                              │                         │
         │                              │  5. Opens Fulfillment   │
         │                              │     view                │
         │                              │                         │
         │                              │  6. Searches/filters    │
         │                              │     storytellers        │
         │                              │                         │
         │                              │  7. Selects 5-8         │
         │                              │     storytellers        │
         │                              │                         │
         │                              │  8. Clicks "Assign"     │
         │                              │  ─────────────────────▶ │
         │                              │                         │
         │                              │  9. Validates count     │
         │                              │  ◀──────────────────────│
         │                              │                         │
         │                              │  10. Saves storytellers │
         │                              │      meta               │
         │                              │  ─────────────────────▶ │
         │                              │                         │
         │                              │  11. Status →           │
         │                              │      'ready_review'     │
         │                              │  ─────────────────────▶ │
         │                              │                         │
         │  12. Receives email          │                         │
         │  ◀─────────────────────────────────────────────────────│
         │                              │                         │
         │  13. Reviews storytellers    │                         │
         │  (on client dashboard)       │                         │
         │                              │                         │
         │  14. Marks interested/pass   │                         │
         │  ─────────────────────────────────────────────────────▶│
         │                              │                         │
         │                              │  15. Status →           │
         │                              │      'assigned'         │
         │                              │  ◀──────────────────────│
         │                              │                         │
         │                              │  16. Views selection    │
         │                              │      in modal           │
         │                              │                         │
         │                              │  17. Clicks "Mark       │
         │                              │      Complete"          │
         │                              │  ─────────────────────▶ │
         │                              │                         │
         │                              │  18. Status →           │
         │                              │      'completed'        │
         │                              │  ◀──────────────────────│
         ▼                              ▼                         ▼
```

### 6.2 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      DATA FLOW DIAGRAM                           │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│   Browser    │◀───────▶│  WordPress   │◀───────▶│   Database   │
│   (Client)   │  HTTP   │    PHP       │   SQL   │   (MySQL)    │
└──────────────┘         └──────────────┘         └──────────────┘
       │                        │                        │
       │                        │                        │
       ▼                        ▼                        ▼
┌──────────────┐         ┌──────────────┐         ┌──────────────┐
│ tav-dashboard│         │ dashboard.php│         │  wp_posts    │
│ .js          │         │              │         │              │
│              │         │ • Helpers    │         │ • storyteller│
│ • Chart.js   │ AJAX    │ • AJAX       │ WP_Query│ • request    │
│ • Modals     │◀───────▶│ • Views      │◀───────▶│              │
│ • Filters    │         │              │         │  wp_postmeta │
│              │         │              │         │  wp_users    │
└──────────────┘         └──────────────┘         └──────────────┘
       │                        │                        │
       │                        │                        │
       │                        ▼                        │
       │                 ┌──────────────┐                │
       │                 │  ACF Pro     │                │
       │                 │              │                │
       │                 │ • get_field  │                │
       │                 │ • acf_form   │                │
       │                 │ • Repeaters  │                │
       │                 └──────────────┘                │
       │                        │                        │
       │                        ▼                        │
       │                 ┌──────────────┐                │
       │                 │ WooCommerce  │                │
       │                 │              │                │
       │                 │ • wc_get_    │                │
       │                 │   orders()   │                │
       │                 │ • Revenue    │                │
       │                 └──────────────┘                │
       │                                                 │
       └─────────────────────────────────────────────────┘
                    Shared WordPress Database
```

---

## 7. API & AJAX Endpoints

### 7.1 AJAX Endpoints

| Action | Method | Auth | Capability | Handler |
|--------|--------|------|------------|---------|
| `tav_get_storyteller_details` | GET | Nonce | Any logged-in | `tav_ajax_get_storyteller_details()` |
| `tav_get_client_details` | GET | Nonce | `manage_options` | `tav_ajax_get_client_details()` |
| `tav_save_client_details` | POST | Nonce | `manage_options` | `tav_ajax_save_client_details()` |
| `tav_get_chart_data` | POST | Nonce | `edit_storytellers` | Anonymous closure |

### 7.2 Storyteller Details Endpoint

**Request:**
```
GET /wp-admin/admin-ajax.php
    ?action=tav_get_storyteller_details
    &st_id=123
    &nonce=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "title": "Sarah Miller",
    "bio": "Climate advocate and storyteller...",
    "location": "New York, NY",
    "thumbnail": "https://site.com/uploads/sarah.jpg",
    "platforms": [
      {
        "name": "instagram",
        "handle": "@sarahmiller",
        "followers": 125000,
        "url": "https://instagram.com/sarahmiller"
      }
    ],
    "samples": [
      {
        "title": "Climate Action Video",
        "platform": "tiktok",
        "views": 450000,
        "url": "https://tiktok.com/..."
      }
    ]
  }
}
```

### 7.3 Client Details Endpoint

**Request:**
```
GET /wp-admin/admin-ajax.php
    ?action=tav_get_client_details
    &client_id=456
    &nonce=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 456,
    "name": "Jane Smith",
    "email": "jane@acme.com",
    "company": "ACME Inc",
    "status": "active",
    "notes": "VIP client, priority support",
    "requests": [
      {
        "id": 789,
        "title": "Q2 Campaign",
        "package": "Custom Search",
        "date": "Mar 15, 2026",
        "total": "$600.00"
      }
    ]
  }
}
```

### 7.4 Save Client Details Endpoint

**Request:**
```
POST /wp-admin/admin-ajax.php

action=tav_save_client_details
client_id=456
status=vip
notes=Updated notes here
nonce=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Changes saved."
  }
}
```

### 7.5 Chart Data Endpoint

**Request:**
```
POST /wp-admin/admin-ajax.php

action=tav_get_chart_data
period=30days
nonce=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "labels": ["May 5", "May 6", "May 7", ...],
    "received": [1200, 0, 600, ...],
    "pending": [0, 300, 0, ...],
    "total": 45230
  }
}
```

---

## 8. Database Schema

### 8.1 Tables Used

The plugin uses **no custom database tables**. All data is stored in WordPress core tables:

```
┌─────────────────────────────────────────────────────────────────┐
│                    DATABASE TABLES USED                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  wp_posts                                                       │
│  ├── storyteller posts (post_type = 'storyteller')             │
│  └── request posts (post_type = 'request') - from CCC          │
│                                                                 │
│  wp_postmeta                                                    │
│  ├── ACF field values                                           │
│  ├── Derived meta (tav_avg_engagement_rate, etc.)              │
│  └── Request status, storytellers array                         │
│                                                                 │
│  wp_terms + wp_term_taxonomy + wp_term_relationships           │
│  ├── vs_niche taxonomy                                          │
│  └── storyteller_tag taxonomy                                   │
│                                                                 │
│  wp_users + wp_usermeta                                         │
│  ├── Client user accounts                                       │
│  └── Client meta (status, notes, organization)                  │
│                                                                 │
│  wp_options                                                      │
│  ├── Email template options                                     │
│  └── Migration flags                                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 8.2 Key Meta Keys

**Storyteller Meta (`wp_postmeta` where `post_type='storyteller'`):**

| Meta Key | Type | Description |
|----------|------|-------------|
| `profile_image` | int | Attachment ID |
| `bio` | string | Short biography |
| `location` | string | Geographic location |
| `private_contact` | string | Email address |
| `authenticity_score` | int | 1-100 rating |
| `campaign_status` | string | prospect/active/paused/completed/declined/verified |
| `is_verified` | bool | Vetting complete flag |
| `platforms_repeater` | serialized | ACF repeater data |
| `sample_work` | serialized | ACF repeater data |
| `verification_notes` | string | Internal notes |
| `organization_tags` | string | Comma-separated tags |
| `date_added` | string | Y-m-d format |
| `tav_avg_engagement_rate` | float | Calculated average |
| `tav_total_followers` | int | Calculated sum |
| `tav_platforms` | string | Comma-separated slugs |

**Request Meta (`wp_postmeta` where `post_type='request'`):**

| Meta Key | Type | Description |
|----------|------|-------------|
| `status` | string | Workflow status |
| `storytellers` | array | Assigned storyteller IDs |
| `client_feedback` | JSON/serialized | {st_id: 'interested'/'pass'} |
| `client_selected_storytellers` | array | Client's final picks |
| `package_tier` | string | Pricing tier |
| `storyteller_count` | int | Requested count |
| `due_date` | string | Deadline |
| `woo_order_id` | int | WooCommerce order ID |

**User Meta (`wp_usermeta`):**

| Meta Key | Type | Description |
|----------|------|-------------|
| `organization_name` | string | Company name |
| `ccc_client_status` | string | active/suspended/vip |
| `ccc_client_notes` | string | Admin notes |

### 8.3 Options

| Option Key | Default | Description |
|------------|---------|-------------|
| `tav_email_fulfill_subject` | "Your Curated Storytellers are Ready!" | Fulfillment email subject |
| `tav_email_fulfill_body` | (template) | Fulfillment email body |
| `tav_email_payment_subject` | (template) | Payment receipt subject |
| `tav_email_payment_body` | (template) | Payment receipt body |
| `tav_email_received_subject` | (template) | Request received subject |
| `tav_email_received_body` | (template) | Request received body |
| `tav_email_reset_subject` | (template) | Password reset subject |
| `tav_email_reset_body` | (template) | Password reset body |
| `tav_niches_list` | (empty) | Legacy niche list |
| `tav_engagement_backfill_done` | true | Migration flag |
| `tav_storyteller_filters_backfill_done` | true | Migration flag |

---

## 9. Integration Points

### 9.1 Plugin Dependencies

```
┌─────────────────────────────────────────────────────────────────┐
│                     PLUGIN DEPENDENCIES                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ REQUIRED                                                │    │
│  │                                                         │    │
│  │  ┌─────────────────────────────────────────────────┐   │    │
│  │  │ ACF Pro                                          │   │    │
│  │  │                                                  │   │    │
│  │  │ • acf_add_local_field_group()                   │   │    │
│  │  │ • get_field() / get_sub_field()                 │   │    │
│  │  │ • have_rows() / the_row()                       │   │    │
│  │  │ • acf_form() for frontend forms                 │   │    │
│  │  │ • acf_form_head() for form processing           │   │    │
│  │  │ • update_field()                                │   │    │
│  │  └─────────────────────────────────────────────────┘   │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ OPTIONAL                                                │    │
│  │                                                         │    │
│  │  ┌─────────────────────────────────────────────────┐   │    │
│  │  │ WooCommerce                                      │   │    │
│  │  │                                                  │   │    │
│  │  │ • wc_get_orders() - revenue queries             │   │    │
│  │  │ • Order totals for client spend                 │   │    │
│  │  │ • class_exists('WooCommerce') check             │   │    │
│  │  └─────────────────────────────────────────────────┘   │    │
│  │                                                         │    │
│  │  ┌─────────────────────────────────────────────────┐   │    │
│  │  │ Client Command Center (Companion)                │   │    │
│  │  │                                                  │   │    │
│  │  │ • 'request' CPT definition                      │   │    │
│  │  │ • ccc_get_pricing() function                    │   │    │
│  │  │ • ccc_is_client() function                      │   │    │
│  │  │ • Client role registration                      │   │    │
│  │  └─────────────────────────────────────────────────┘   │    │
│  │                                                         │    │
│  │  ┌─────────────────────────────────────────────────┐   │    │
│  │  │ Ultimate Member                                  │   │    │
│  │  │                                                  │   │    │
│  │  │ • 'um_client' user role                         │   │    │
│  │  │ • User registration flow                        │   │    │
│  │  └─────────────────────────────────────────────────┘   │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 9.2 Hooks & Filters

**Filters Provided:**

| Filter | Arguments | Description |
|--------|-----------|-------------|
| `tav_client_role_slugs` | `array` | Customize client role slugs |

**Actions Used:**

| Action | Callback |
|--------|----------|
| `init` | `tav_register_storytellers_cpt()` |
| `admin_menu` | `tav_register_dashboard_page()` |
| `admin_init` | `tav_backfill_engagement_rates()` |
| `admin_init` | `tav_backfill_storyteller_filter_meta()` |
| `admin_init` | `tav_maybe_create_admin_dashboard_page()` |
| `admin_head` | `tav_admin_column_styles()` |
| `acf/include_fields` | `tav_register_acf_fields()` |
| `acf/save_post` | `tav_persist_avg_engagement_rate()` |
| `acf/update_value/name=profile_image` | `tav_sync_profile_image()` |
| `pre_get_posts` | `tav_storyteller_query_mods()` |
| `restrict_manage_posts` | `tav_admin_filter_dropdowns()` |
| `template_redirect` | `tav_admin_portal_access_guard()` |
| `template_redirect` | `tav_admin_portal_render_full_page()` |
| `the_content` | `tav_admin_portal_the_content()` |
| `retrieve_password_title` | `tav_custom_password_reset_subject()` |
| `retrieve_password_message` | `tav_custom_password_reset_email()` |

---

## 10. Functionality Status Matrix

### 10.1 Core Features

| Feature | Status | Notes |
|---------|--------|-------|
| Storyteller CPT | ✅ Working | Full CRUD operations |
| ACF Field Groups | ✅ Working | All fields functional |
| Admin Dashboard | ✅ Working | All widgets loading |
| Revenue Chart | ✅ Working | Requires WooCommerce for data |
| Activity Feed | ✅ Working | All event types tracked |
| Storyteller List | ✅ Working | Pagination, filters |
| Storyteller Add/Edit | ✅ Working | ACF forms |
| Client Management | ✅ Working | List, modal, save |
| Request Management | ✅ Working | Full workflow |
| Fulfillment Flow | ✅ Working | Selection, email, status update |
| Settings Page | ✅ Working | Niches, email templates |
| Notifications | ✅ Working | Activity feed view |
| Frontend Portal | ✅ Working | `/admin-dashboard/` |

### 10.2 AJAX Functionality

| Endpoint | Status | Notes |
|----------|--------|-------|
| Get Storyteller Details | ✅ Working | Modal loads correctly |
| Get Client Details | ✅ Working | Modal loads correctly |
| Save Client Details | ✅ Working | Status/notes save |
| Get Chart Data | ✅ Working | Period switching works |

### 10.3 Email Functionality

| Email | Status | Notes |
|-------|--------|-------|
| Fulfillment Notification | ✅ Working | Sent on assignment |
| Password Reset Override | ✅ Working | Custom template used |
| Payment Receipt | ⚠️ Configured | Sent by CCC |
| Request Received | ⚠️ Configured | Sent by CCC |

---

## 11. Configuration & Settings

### 11.1 Plugin Constants

```php
define('TAV_VERSION', '1.2.5');
define('TAV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TAV_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### 11.2 Capabilities

**Added to Administrator role on activation:**

```php
$caps = [
    'edit_storyteller',
    'read_storyteller',
    'delete_storyteller',
    'edit_storytellers',
    'edit_others_storytellers',
    'publish_storytellers',
    'read_private_storytellers',
    'delete_storytellers',
    'delete_private_storytellers',
    'delete_published_storytellers',
    'delete_others_storytellers',
    'edit_private_storytellers',
    'edit_published_storytellers',
    'create_storytellers',
];
```

### 11.3 Default Niches

```php
[
    'climate'   => 'Climate',
    'health'    => 'Health',
    'politics'  => 'Politics',
    'tech'      => 'Tech',
    'fashion'   => 'Fashion',
    'lifestyle' => 'Lifestyle',
]
```

### 11.4 Fulfillment Limits

```php
$min = 5;    // Minimum storytellers to assign
$max = 8;    // Maximum storytellers to assign
$target = $requested > 0 ? max($min, min($max, $requested)) : 8;
```

---

## 12. Security Considerations

### 12.1 Authentication & Authorization

| Check | Location | Method |
|-------|----------|--------|
| Admin access | Dashboard menu | `edit_storytellers` capability |
| AJAX nonces | All endpoints | `check_ajax_referer()` |
| Manage options | Client AJAX | `current_user_can('manage_options')` |
| Fulfillment POST | dashboard.php | `wp_verify_nonce()` |
| Settings save | settings.php | `wp_verify_nonce()` |
| Portal access | admin-portal.php | `tav_is_operator()` |

### 12.2 Data Sanitization

| Input | Sanitization |
|-------|--------------|
| GET parameters | `sanitize_text_field()` |
| POST data | `sanitize_text_field()`, `sanitize_textarea_field()` |
| IDs | `(int)` cast |
| Arrays | `array_map('intval', ...)` |
| HTML content | `wp_kses_post()` |

### 12.3 Output Escaping

| Context | Function |
|---------|----------|
| HTML attributes | `esc_attr()` |
| URLs | `esc_url()` |
| HTML content | `esc_html()` |
| JSON | `wp_json_encode()` |
| Translations | `esc_html_e()`, `esc_attr_e()` |

### 12.4 Recommendations

1. **Remove debug logging** - `file_put_contents()` to `tav_debug.log` should use `error_log()` with `WP_DEBUG_LOG`
2. **Add ACF Pro check** - Display admin notice if ACF Pro is not active
3. **Rate limiting** - Consider adding rate limiting to AJAX endpoints

---

## 13. Work Remaining & Recommendations

### 13.1 Known Issues

| Issue | Priority | Description |
|-------|----------|-------------|
| Debug logging | Low | Uses `file_put_contents()` instead of WordPress logging |
| Verified metrics sort | Low | Disabled due to repeater complexity |

### 13.2 Recommended Enhancements

| Enhancement | Priority | Effort | Description |
|-------------|----------|--------|-------------|
| ACF Pro check | High | 1 hour | Admin notice if ACF not active |
| Export functionality | Medium | 4 hours | CSV export for storytellers/clients |
| Bulk actions | Medium | 3 hours | More bulk action options for requests |
| Search debouncing | Low | 2 hours | AJAX search instead of page reload |
| Activity pagination | Low | 2 hours | Load more for activity feed |

### 13.3 Technical Debt

| Item | Location | Recommendation |
|------|----------|----------------|
| Debug logging | `dashboard.php:51-60` | Use `WP_DEBUG_LOG` properly |
| Inline JS | Various view files | Move to main JS file |
| Inline CSS | `notifications.php` | Move to main CSS file |

---

## 14. Testing Checklist

### 14.1 Storyteller Management

- [ ] Create new storyteller with all fields
- [ ] Edit existing storyteller
- [ ] Delete storyteller
- [ ] Upload profile image (syncs to featured)
- [ ] Add multiple platforms via repeater
- [ ] Add sample work via repeater
- [ ] Filter by name
- [ ] Filter by location
- [ ] Filter by niche
- [ ] Pagination works correctly
- [ ] Derived meta calculates on save

### 14.2 Request Management

- [ ] View all requests
- [ ] Filter by status
- [ ] Filter by client
- [ ] Filter by niche
- [ ] Filter by date range
- [ ] Search by name
- [ ] View brief modal for pending requests
- [ ] View selection modal for assigned requests
- [ ] Mark complete works
- [ ] Pagination works

### 14.3 Fulfillment

- [ ] Load fulfillment page with valid request
- [ ] Error shown for invalid request
- [ ] Client brief displays correctly
- [ ] Storyteller search works
- [ ] Filter by niche
- [ ] Filter by location
- [ ] Filter by platform
- [ ] Filter by followers
- [ ] Filter by engagement
- [ ] Selection counter updates
- [ ] Cannot select more than 8
- [ ] Cannot submit with less than 5
- [ ] Submit assigns storytellers
- [ ] Status changes to ready_review
- [ ] Email sent to client
- [ ] Redirect with success message

### 14.4 Client Management

- [ ] View client list
- [ ] Search clients
- [ ] Open client detail modal
- [ ] View request history
- [ ] Change client status
- [ ] Save internal notes
- [ ] Pagination works

### 14.5 Dashboard

- [ ] Stats display correctly
- [ ] Revenue chart loads
- [ ] Chart period switching works
- [ ] Activity feed displays
- [ ] Pending fulfillment list shows
- [ ] Recent storytellers show

### 14.6 Settings

- [ ] Niche list saves
- [ ] Email templates save
- [ ] Template preview works
- [ ] Placeholder chips copy

### 14.7 Frontend Portal

- [ ] `/admin-dashboard/` accessible to operators
- [ ] Redirects non-operators
- [ ] Redirects to login if not logged in
- [ ] Dashboard content renders
- [ ] Chart works on frontend

### 14.8 Email

- [ ] Fulfillment email sends
- [ ] Placeholders replaced correctly
- [ ] Password reset uses custom template

---

## Appendix A: Function Reference

### Data Helper Functions (dashboard.php)

| Function | Returns | Description |
|----------|---------|-------------|
| `tav_get_fulfillment_selection_limits($request_id)` | `array` | Min/max/target for selection |
| `tav_normalize_platform_slug($name)` | `string` | Normalizes platform names |
| `tav_get_storyteller_total_followers($post_id)` | `int` | Total followers for storyteller |
| `tav_storyteller_matches_followers($post_id, $range)` | `bool` | Check if in follower range |
| `tav_storyteller_matches_platform($post_id, $platform)` | `bool` | Check if has platform |
| `tav_search_fulfillment_storytellers($filters)` | `array` | Search storytellers with filters |
| `tav_get_status_counts()` | `array` | Counts by campaign status |
| `tav_get_verified_count()` | `int` | Count of verified storytellers |
| `tav_get_total_storytellers()` | `int` | Total published storytellers |
| `tav_decode_client_feedback($raw)` | `array` | Decode feedback JSON/serialized |
| `tav_get_satisfaction_rate()` | `array` | Satisfaction metrics |
| `tav_get_revenue()` | `array` | Monthly and all-time revenue |
| `tav_get_revenue_chart_data($period)` | `array` | Chart data for period |
| `tav_get_request_title($request_id, $fallback)` | `string` | Human-readable request title |
| `tav_get_active_requests_count()` | `int` | Count of active requests |
| `tav_get_recent_storytellers($count)` | `array` | Recent storyteller posts |
| `tav_get_recent_requests($count)` | `array` | Recent request posts |
| `tav_get_pending_fulfillment_requests($limit)` | `array` | Requests needing fulfillment |
| `tav_get_activity_feed($limit)` | `array` | Merged activity events |
| `tav_get_initials($name)` | `string` | Initials from name |
| `tav_format_metric($num)` | `string` | Format number (125K) |
| `tav_time_ago($timestamp)` | `string` | Human-readable time ago |

---

## Appendix B: Glossary

| Term | Definition |
|------|------------|
| **Storyteller** | Influencer/content creator profile managed in the system |
| **Request** | Client's search request for storytellers |
| **Fulfillment** | Process of assigning storytellers to a request |
| **Operator** | Admin user who manages the platform |
| **Client** | Customer who submits requests and pays for services |
| **CCC** | Client Command Center - companion plugin |
| **ACF** | Advanced Custom Fields Pro |
| **CPT** | Custom Post Type |

---

*Document generated: June 4, 2026*  
*Plugin version: 1.2.5*
