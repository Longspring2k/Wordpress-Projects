# Bright Path — brightpath-gutenburg

Gutenberg Full-Site Editing (FSE) block theme for the **Bright Path** NDIS services website.

- **Site Name (DB):** `Bright Path` / Tagline: `Supports`
- **Theme Name:** `Bright Path` / Text Domain: `brightpath` / Version: `1.0.0`
- **Source:** exported from VPS `167.86.115.187`, dir `/srv/sites/brightpath-gutenburg/html/wp-content/themes/brightpath` (this branch mirrors the working tree, excluding `.git` and `*.bak*` files).

## Features

- Full FSE: `templates/`, `parts/`, `patterns/`, `styles/`, `theme.json`
- Custom `brightpath/form` block (`blocks/form/`) with form/mail/CPT logic bundled in the theme (`inc/mail.php`, `inc/post-types.php`, `inc/forms.php`, `inc/form-block.php`)
- Icon library (36 icons) + REST endpoint `/brightpath/v1/icons` and block Icon picker (`inc/icons.php`, `inc/icon-meta.php`)
- Mobile nav overlay, band-rhythm (half padding between adjacent same-background bands), `bp-page-<slug>` body class, starter content auto-imported on activation
- Owner-sharded CSS (`assets/css/`: foundation, bands, posts, forms, ...) enqueued in order after `global-styles` (`inc/assets.php`)

## Requirements

- WordPress >= 6.7 (tested up to 7.1)
- PHP >= 7.4
- Suggested (optional) plugins: `advanced-custom-fields`, `wordpress-seo`
- Note: the legacy `brightpath-core` plugin has been **folded back into the theme**. The theme ships a `BRIGHTPATH_CORE_VERSION` guard, so keep that plugin deactivated to avoid redeclaration. Nothing else is required to run.

## Installation

### Option 1 — clone this branch (recommended)

```bash
git clone -b brightpath-gutenburg https://github.com/Longspring2k/Wordpress-Projects.git brightpath
cp -r brightpath /path/to/wordpress/wp-content/themes/brightpath
```

Then go to WP Admin → Appearance → Themes → Activate **Bright Path**.

### Option 2 — download ZIP from GitHub

Open the `brightpath-gutenburg` branch → Code → Download ZIP → extract as folder `brightpath` → upload to `wp-content/themes/` → Activate.

### Option 3 — copy from VPS (internal)

```bash
scp -r root@167.86.115.187:/srv/sites/brightpath-gutenburg/html/wp-content/themes/brightpath ./brightpath
```

After activation, starter content is imported automatically. Verify:

- Front end loads with no fatal errors
- `Appearance → Editor` opens templates/patterns correctly
- The form block (`brightpath/form`) renders and sends mail (uses the site SMTP configuration)

## Structure

```
style.css          Theme header only (CSS lives in shards)
theme.json         Global styles/settings
functions.php      Loader — only requires shards from inc/, no logic here
inc/               setup, assets, patterns, editor-locks, nav-state, design-parity,
                   icons, icon-meta, mobile-nav, band-rhythm, page-classes,
                   starter-content, mail, post-types, forms, form-block
blocks/form/       Custom form block
assets/css|js/     CSS shards + JS (editor-canvas, mobile-nav, band-rhythm...)
parts|templates|patterns|styles|starter|tools
```

To add a feature: add a new shard file under `inc/` and register it in the array inside `functions.php`. A listed shard that does not exist yet is skipped, so the site never goes down.

## License

GPL-2.0-or-later (inherited from WordPress).
