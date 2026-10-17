# Translations

Text domain: `jrcleaning` (declared in `style.css`, registered by
`load_theme_textdomain()` in `functions/setup.php`).

Drop `jrcleaning-{locale}.mo` files here, e.g. `jrcleaning-cy_GB.mo`.

## Scope

Only the partner-programme layer is wrapped in `__()` / `_e()` — see the note in
`functions/setup.php` for why marketing copy in the templates deliberately is not.

## Regenerating the .pot

    wp i18n make-pot . languages/jrcleaning.pot --domain=jrcleaning --exclude=node_modules
