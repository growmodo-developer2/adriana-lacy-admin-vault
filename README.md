# The Admin Vault

A private, admin-only WordPress plugin for managing Storyteller profiles on the Verified Storytellers platform. Stores social handles, verified metrics, private contact info, and authenticity scores. Built on ACF Pro.

The Admin Vault is the operator side of the platform. The companion plugin, **Client Command Center**, is the client-facing dashboard that reads what's published here.

---

## Requirements

- WordPress 6.x
- PHP 8.0 or newer
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/pro/) — required (the plugin uses `acf_add_local_field_group`, `update_field`, repeaters, and ACF forms)
- Companion plugin: **Client Command Center** (consumes the `request` CPT and the storyteller picks written here)

---

## What it ships

### Custom post type

| Post type | Public | Purpose |
|---|---|---|
| `storyteller` | No (admin-only) | One profile per content creator |

### Taxonomies

| Taxonomy | Attached to | Purpose |
|---|---|---|
| `storyteller_tag` | `storyteller` | Free-form labels (`Eco-Warrior`, `Tech`, `Berlin`, ...) |
| `vs_niche` | `storyteller` | Controlled-vocabulary niches managed from the Settings view |

### ACF field group

Bound to the `storyteller` post type:

- `location` — text
- `bio` — textarea
- `platforms_repeater` — repeater of `{ platform_name, handle, follower_count, engagement_rate, profile_url, niche_tags }`
- `campaign_status` — select (`pending` / `verified` / `active`)
- `authenticity_score` — number (0–100)
- `private_contact` — email (operator-only; never exposed to clients)
- `verification_notes` — textarea
- `profile_image` — image (synced to WP featured image automatically)

### Admin dashboard

Top-level menu `Admin Vault` → page slug `tav-dashboard`. Views:

| View | Slug | Purpose |
|---|---|---|
| Dashboard | `?page=tav-dashboard` | At-a-glance counts and recent activity |
| Requests | `&view=requests` | Inbox of client requests in every status |
| Fulfillment | `&view=fulfill&request_id=N` | Pick storytellers for a paid request, notify the client |
| Storytellers | `&view=storytellers` | Browse and edit the storyteller roster |
| Clients | `&view=clients` | List of registered clients and their request history |
| Settings | `&view=settings` | Editable email templates and niche taxonomy management |

### Editable email templates

Stored as WordPress options (operators edit them via the Settings view, no code changes needed):

| Option | Used by |
|---|---|
| `tav_email_fulfill_subject` / `tav_email_fulfill_body` | Sent to the client when storytellers are selected for their request |
| `tav_email_received_subject` / `tav_email_received_body` | Sent to the client after payment, confirming request receipt |
| `tav_email_payment_subject` / `tav_email_payment_body` | Sent to the client after payment, confirming the charge |

Template variables: `{client_name}`, `{project_name}`, `{package}`, `{total}`, `{delivery}`, `{link}`, `{storyteller_list}`.

> The `received` and `payment` emails are dispatched from Client Command Center's `woocommerce_payment_complete` hook. The `fulfill` email is dispatched from this plugin's fulfillment view.

---

## Installation

1. Copy this directory into `wp-content/plugins/the-admin-vault/`.
2. Make sure **Advanced Custom Fields PRO** is installed and active.
3. Activate **The Admin Vault** from `Plugins`.
4. Open **Admin Vault → Settings** and:
   - Configure the editable email templates.
   - Add your niche terms under the `vs_niche` taxonomy if you want a controlled vocabulary.

No database tables are created. All data lives in standard WP posts, post meta, terms, and options.

---

## Development

### Debug logging

Verbose fulfillment logging writes to `ABSPATH . tav_debug.log`. Every write is gated behind `WP_DEBUG`, so the log only appears when:

```php
define('WP_DEBUG', true);
```

is set in `wp-config.php`. In production (`WP_DEBUG` off), no log file is created and no PII is written to the webroot.

### Coding standards

- PHP 8.0 minimum — typed declarations and short-arrow syntax in use
- No autoloader; the plugin is intentionally flat for ease of review
- All admin-only callbacks check `current_user_can('manage_options')` or run inside `is_admin()` guards

### File layout

```
the-admin-vault.php          Plugin bootstrap, CPT, taxonomies, ACF fields
admin/
  dashboard.php              Menu registration, fulfillment POST handler
  views/
    dashboard.php            Landing view
    requests.php             Request inbox
    fulfillment.php          Storyteller picker for a single request
    storytellers.php         Storyteller list
    clients.php              Client list
    settings.php             Email templates + niche management
assets/
  css/tav-dashboard.css
  js/tav-dashboard.js
ADMIN-TRAINING-GUIDE.md      Operator-facing walkthrough
```

---

## Versioning

Current: **1.1.1**

Version is declared in the plugin header. Bump it whenever a release is cut so WP's update system and any deploy tooling can detect the change.

---

## License

GPL-2.0-or-later.
