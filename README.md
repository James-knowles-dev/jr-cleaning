# JR Cleaning — wp-content

The custom code for jrcleaning.org. This repository mirrors `wp-content/`:

| Path | What it is |
|---|---|
| `themes/jrcleaning-new/` | The active theme (presentation only). Build: `npm install && npm run build` |
| `plugins/jrc-partners/` | Partner platform: accounts, jobs, reports, tiers, requests, exports |
| `plugins/jrc-connecteam/` | Connecteam shift-scheduler sync (requires jrc-partners) |
| `mu-plugins/jrc-site.php` + `mu-plugins/jrc-site/` | Must-use: legacy 301 redirects, security hardening, GTM, `DISALLOW_FILE_EDIT` |

## Deploying

All four paths must be deployed. In particular **`mu-plugins/` must be included** —
without it the legacy redirects, hardening and analytics are off (the theme shows an
admin notice when that happens).

Requires WordPress 6.5+, PHP 7.4+, WooCommerce, ACF Pro and Gravity Forms.

## Form conversion tracking

`mu-plugins/jrc-site/analytics.php` pushes one dataLayer event per Gravity Forms entry
that was actually accepted (not failed validation, not spam, not quote-wizard steps):

```js
{ event: 'jrc_form_confirmed', jrc_form_id: 1, jrc_form_name: 'Contact', jrc_lead_type: 'contact' }
```

`jrc_lead_type` is `contact`, `quote`, `partner` or `other`, from the form IDs in Theme Settings.

### GA4 / GTM setup that goes with it

1. **GA4 → Admin → Data streams → (web stream) → Enhanced measurement → Form interactions: OFF.**
   It sends its own `form_submit` on every form submit, double counting.
2. **GTM — remove** the GA4 Event tag `form_submit` and its *Form Submission* trigger (`/contact/` only).
3. **GTM — Data Layer Variables:** `jrc_form_id`, `jrc_form_name`, `jrc_lead_type`.
4. **GTM — Trigger:** Custom Event, event name `jrc_form_confirmed`.
5. **GTM — Tag:** GA4 Event, measurement ID `G-74VVSR2RKL`, event name `generate_lead`,
   parameters `lead_type = {{jrc_lead_type}}`, `form_id = {{jrc_form_id}}`, `form_name = {{jrc_form_name}}`.
   Fire on the trigger above.
6. **GA4 → Admin → Custom definitions:** event-scoped dimension `lead_type`.
7. **GA4 → Admin → Events:** mark `generate_lead` as a key event; un-mark `form_submit`.
8. Test in GTM Preview: a failed submission (empty required field) must fire nothing; a good one fires once.
9. After a month, `generate_lead` should match the Gravity Forms entry count, less spam
   (a few may be missing because of ad blockers).
