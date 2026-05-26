# Verified Storytellers — Admin Training Guide

> This guide covers everything an admin needs to operate the Verified Storytellers platform day-to-day: managing requests, fulfilling orders, maintaining the storyteller database, and configuring settings.

---

## Table of Contents

1. [Logging In](#1-logging-in)
2. [Admin Vault Dashboard](#2-admin-vault-dashboard)
3. [Managing Search Requests](#3-managing-search-requests)
4. [Fulfilling a Request](#4-fulfilling-a-request)
5. [Managing the Storyteller Database](#5-managing-the-storyteller-database)
6. [Managing Clients](#6-managing-clients)
7. [Email Templates & Settings](#7-email-templates--settings)
8. [Understanding the Client Experience](#8-understanding-the-client-experience)
9. [Common Tasks & Troubleshooting](#9-common-tasks--troubleshooting)

---

## 1. Logging In

1. Go to `yoursite.com/wp-admin`
2. Log in with your WordPress administrator credentials
3. In the left sidebar, click **Admin Vault** (shield icon)

You'll land on the Admin Vault Dashboard.

---

## 2. Admin Vault Dashboard

The dashboard gives you an at-a-glance overview of platform activity.

### Stat Cards (top row)

| Card | What it shows |
|------|--------------|
| **Total Revenue** | All-time revenue from WooCommerce orders. The badge shows this month's revenue. |
| **Active Requests** | Number of requests currently being worked on (In Vetting, Matching, or Ready to Review). |
| **Total Storytellers** | Total published storyteller profiles in the database. Badge shows how many are verified (score ≥ 70). |
| **Avg. Authenticity Score** | Average authenticity score across all storytellers. |

### Panels (bottom row)

- **Recent Requests / Activities** — The 5 most recent client requests with their current status
- **Recently Added Storytellers** — The 5 most recently added storyteller profiles

### Navigation (sidebar)

| Menu Item | What it does |
|-----------|-------------|
| Dashboard | This overview page |
| Storytellers | Browse, search, add, and edit storyteller profiles |
| Clients | View all registered clients, their request counts, and total spending |
| Requests | View and manage all search requests from clients |
| Settings | Configure email templates and manage niche categories |

---

## 3. Managing Search Requests

Navigate to **Admin Vault → Requests**.

### Request Statuses

| Status | Meaning | What to do |
|--------|---------|------------|
| **Payment Pending** | Client submitted the form but hasn't paid yet | Wait — or contact the client |
| **In Vetting** | Payment confirmed, admin should review the brief | Read the brief, then click Start Matching |
| **Matching** | Admin is actively searching for storytellers | Use the Fulfill tool to find and assign storytellers |
| **Ready to Review** | Storytellers assigned, client has been notified | Wait for client to review and select |
| **Assigned** | Client selected storytellers and requested introductions | Facilitate warm email introductions (outside the platform) |
| **Completed** | Introductions made, project finished | No action needed |
| **Archived** | Client archived the request | No action needed |

### Filtering Requests

Use the **status dropdown** above the table to filter by any status. You can also filter by client (click a client's name) or niche.

### Action Buttons

| Button | When it appears | What it does |
|--------|----------------|-------------|
| 🔍 (magnifying glass) | Payment Pending / In Vetting | Sets status to **Matching** — the client sees "Sourcing Storytellers" |
| ➡️ (arrow) | In Vetting / Matching / Ready to Review | Opens the **Fulfill** page to assign storytellers |
| 👁️ (eye) | All active statuses | Opens a modal showing the client's brief (or selected storytellers for assigned requests) |
| ✅ (checkmark) | Assigned | Marks the request as **Completed** (with confirmation prompt) |

### Bulk Actions

You can select multiple requests using the checkboxes and apply bulk actions:

1. Check the boxes on the left of each row (or use "Select All" at the top)
2. Choose an action from the **Bulk Actions** dropdown (Mark Complete or Archive)
3. Click **Apply**

---

## 4. Fulfilling a Request

This is the core admin workflow — matching storytellers to a client's brief.

### Step-by-step

1. From the Requests table, click the **arrow icon** (Fulfill) on a paid request
2. You'll see two columns:
   - **Left: Client Brief** — campaign goal, niche, location, package tier, special requirements
   - **Right: Storyteller Search** — filter and select storytellers

### Using the Filters

| Filter | How to use it |
|--------|--------------|
| **Search by name** | Type a storyteller's name |
| **Location** | Type a city or country (e.g. "Berlin" or "USA") |
| **Niche** | Select from the dropdown (Climate, Health, Tech, etc.) |
| **Platform** | Filter by social platform (Instagram, TikTok, YouTube, etc.) |
| **Followers** | Select a range: Under 10K, 10K–50K, 50K–100K, 100K+ |
| **Engagement** | Select a range: Under 2%, 2%–5%, 5%–10%, 10%+ |

Click **Search** to apply filters. Click **Clear Filters** to reset.

### Assigning Storytellers

1. Check the boxes next to the storytellers you want to assign (aim for 5-8 per the brief)
2. Click **"Save & Notify Client"**
3. The system will:
   - Save the selection
   - Update the request status to **Ready to Review**
   - Send the client an email: *"Your storytellers are ready!"*
   - The client's dashboard will show a **"Review Storytellers"** button

### Important Notes

- You can only fulfill requests that have been paid (In Vetting, Matching, or Ready to Review status)
- If a request is still Payment Pending, the system will block fulfillment with an error message
- You can re-fulfill a request (update the selection) by clicking Fulfill again

---

## 5. Managing the Storyteller Database

Navigate to **Admin Vault → Storytellers**.

### Viewing Storytellers

The table shows all storyteller profiles with columns for name, location, authenticity score, and campaign status. Use the search bar and niche filter to find specific storytellers.

### Adding a New Storyteller

1. Click **"Add Storyteller"**
2. Fill in the form:

| Field | Required | Notes |
|-------|----------|-------|
| **Name** | Yes | Full name of the storyteller |
| **Profile Image** | Recommended | Upload a real photo (not placeholder) |
| **Bio** | Yes | 2-3 sentences about the storyteller |
| **Location** | Yes | City, Country format (e.g. "Berlin, Germany") |
| **Platforms** | Yes | Click "Add Platform" for each social account |
| **Niches** | Yes | Select relevant niches from the dropdown |
| **Private Contact** | Yes | Email — this is NEVER shown to clients unless they select the storyteller |
| **Authenticity Score** | Yes | 1-100 rating based on your vetting process |
| **Campaign Status** | Yes | Usually set to "Verified" for vetted storytellers |
| **Sample Work** | Recommended | Click "Add Sample" to add content links with view counts |
| **Verification Notes** | Internal | Your private notes — never shown to clients |

3. Click **"Save"** (or Update)

### Platform Fields (per platform)

| Field | Example |
|-------|---------|
| Platform Name | Instagram |
| Handle | @sarah_eco |
| Follower Count | 85000 |
| Engagement Rate | 4.2 |
| Profile URL | https://instagram.com/sarah_eco |

### Calculating Engagement Rate

1. Review the storyteller's last 10-20 posts
2. Calculate: **(Average Likes + Comments) / Followers × 100**
3. Enter the result (e.g. "7.2" for 7.2%)
4. You can use tools like HypeAuditor, Social Blade, or Modash for reference

### Editing / Deleting

- Click a storyteller's name to edit
- Use WordPress's Trash function to delete (recoverable)

---

## 6. Managing Clients

Navigate to **Admin Vault → Clients**.

The table shows all registered clients (users with the `um_client` role) with:

| Column | What it shows |
|--------|--------------|
| **Name / Email** | Client's display name and email address |
| **Organization** | Company name (if provided during registration) |
| **Requests** | Number of search requests — click to see their requests |
| **Total Spent** | Total dollar amount from their WooCommerce orders |
| **Joined** | Registration date |

Use the search bar to find clients by name or email.

---

## 7. Email Templates & Settings

Navigate to **Admin Vault → Settings**.

### Niche Management

Add or edit the list of niches available across the platform. Enter one per line in the format:

```
climate : Climate
health : Health & Wellness
politics : Politics & Policy
```

### Email Templates

Three emails are customizable:

#### 1. Fulfillment Notification
**When sent:** After you assign storytellers to a request (click "Save & Notify Client")

#### 2. Payment Confirmed
**When sent:** Immediately after a client's Stripe payment is processed

#### 3. Request Received
**When sent:** Optional — when a new request is submitted (currently disabled pre-payment by design)

### Available Placeholders

Use these tokens in any subject line or email body:

| Placeholder | Replaced with |
|-------------|--------------|
| `{client_name}` | The client's display name |
| `{project_name}` | The request / project title |
| `{package}` | Package tier name (e.g. "Custom Search") |
| `{total}` | Formatted order total (e.g. "$600.00") |
| `{delivery}` | Delivery timeframe (e.g. "5 business days") |
| `{storyteller_list}` | Bulleted list of assigned storyteller names + bios (fulfillment email only) |
| `{link}` | Link to the client's dashboard or review page |

---

## 8. Understanding the Client Experience

Knowing what the client sees helps you serve them better.

### Client Flow

```
Register → Submit Request → Pay via Stripe → Wait → Review Storytellers → Select → Request Introductions
```

### What the Client's Dashboard Shows

| Status they see | What it means for you |
|----------------|----------------------|
| **Payment Pending** | They haven't paid yet — request form was submitted but checkout wasn't completed |
| **In Vetting** | Payment received — you should start reviewing their brief |
| **Sourcing Storytellers** | You clicked "Start Matching" — they know you're working on it |
| **Ready to Review** | You assigned storytellers — they can now see the profiles |
| **Assigned** | They selected storytellers and clicked "Request Introductions" — your turn to facilitate |

### What Clients Can Do

- **New Search Request** — submit a new search with package selection
- **Review Storytellers** — see assigned profiles with full details (platforms, followers, engagement, sample work)
- **Interested / Pass** — mark each storyteller
- **Request Introductions** — sends you an email with their selections
- **Archive / Delete** — remove completed or abandoned requests from their view
- **Billing History** — view past orders and invoices
- **Notifications** — bell icon shows status updates (payment confirmed, storytellers ready, etc.)

### What Clients CANNOT See

- Storyteller private contact email (until they select and request introductions)
- Verification notes
- Authenticity scores
- Other clients' requests or data

---

## 9. Common Tasks & Troubleshooting

### "A client paid but their dashboard still shows Payment Pending"

This can happen if the Stripe webhook didn't fire (common in test environments).

**Fix:** The system auto-syncs on the client's next page load. If it's still stuck:
1. Go to the WooCommerce order and confirm it's in "Processing" or "Completed"
2. Visit `/wp-admin/?ccc_rescue=1` — this runs a manual repair across all stuck requests

### "I need to change a request's status manually"

1. Go to **WordPress Admin → Requests** (in the left sidebar, not Admin Vault)
2. Edit the request post
3. Change the **Status** dropdown in the ACF fields
4. Save

### "I want to add a new niche"

1. Go to **Admin Vault → Settings**
2. Add a new line to the Niches textarea (format: `slug : Display Name`)
3. Save

Alternatively, go to **WordPress Admin → Storytellers → Niches** to manage them as a WordPress taxonomy.

### "A client says they can't log in"

The most common cause is email verification being enabled in Ultimate Member.

**Quick fix:** Go to **WordPress Admin → Users**, find the client, and check their `account_status` user meta. If it's anything other than `approved`, change it to `approved`.

### "I need to issue a refund"

1. Go to **WooCommerce → Orders**
2. Find the order
3. Click **Refund** and follow the WooCommerce refund process
4. Manually update the request status to "Archived" if needed

### "How do I seed demo storytellers?"

Visit: `/wp-admin/admin.php?page=tav-dashboard&tav_seed=1`

This creates 5 sample storyteller profiles for testing.

---

## Quick Reference: Daily Workflow

```
1. Log in → Check Admin Vault Dashboard
2. Look at Active Requests count
3. Go to Requests → Filter by "In Vetting" or "Matching"
4. For each:
   a. Read the client's brief
   b. Click Start Matching (if In Vetting)
   c. Click Fulfill → Search storytellers → Select 5-8 → Save & Notify
5. Check for "Assigned" requests → Facilitate introductions via email
6. Done!
```

**Target:** Each fulfillment should take less than 20 minutes once storytellers are vetted.

---

## Pricing Reference

| Tier | Price | Storytellers | Delivery |
|------|-------|-------------|----------|
| Quick Match | $400 | 3-5 | 5 business days |
| Custom Search | $600 | 5-8 | 5 business days |
| Premium Search | $900 | 8-10 | 48-72 hours |
| Monthly Retainer | $1,800/mo | 3 searches/month | Priority queue |
| Enterprise | Custom | Unlimited | 24-48 hours |

**Add-ons:** Rush Delivery (+$200), Extra Matches (+$150), Strategy Call (+$100)

---

## Support

For technical issues with the platform, contact the development team. For client-facing questions, use the email templates in Settings to maintain consistent communication.

---

*Last updated: March 2026 — Admin Vault v1.1.0 / Client Command Center v2.4.0*
