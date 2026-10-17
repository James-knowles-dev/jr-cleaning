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
