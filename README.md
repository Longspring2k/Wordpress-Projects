# Bright Path — brightpath-gutenburg

Block theme (Gutenberg Full-Site Editing) cho website **Bright Path** — dịch vụ NDIS.

- **Demo đang chạy:** https://brightpath-gutenburg.buildkitly.com
- **Site Name (DB):** `Bright Path` / Tagline: `Supports`
- **Theme Name:** `Bright Path` / Text Domain: `brightpath` / Version: `1.0.0`
- **Nguồn:** export từ VPS `167.86.115.187`, dir `/srv/sites/brightpath-gutenburg/html/wp-content/themes/brightpath` (branch này = nguyên trạng working-tree, đã loại `.git` và file `*.bak*`).

## Tính năng

- FSE đầy đủ: `templates/`, `parts/`, `patterns/`, `styles/`, `theme.json`
- Custom block `brightpath/form` (`blocks/form/`) + logic form/mail/CPT nằm trong theme (`inc/mail.php`, `inc/post-types.php`, `inc/forms.php`, `inc/form-block.php`)
- Icon library 36 icons + REST `/brightpath/v1/icons`, block Icon picker (`inc/icons.php`, `inc/icon-meta.php`)
- Mobile nav overlay, band-rhythm (nửa padding giữa 2 band cùng nền), `bp-page-<slug>` body class, starter-content tự import khi active
- CSS chia shard theo owner (`assets/css/`: foundation, bands, posts, forms...), enqueue có thứ tự sau `global-styles` (`inc/assets.php`)

## Yêu cầu

- WordPress >= 6.7 (tested up to 7.1)
- PHP >= 7.4
- Plugin gợi ý (không bắt buộc): `advanced-custom-fields`, `wordpress-seo`
- Lưu ý: plugin `brightpath-core` cũ đã được **fold-back vào theme**. Theme có guard `BRIGHTPATH_CORE_VERSION` nên cứ để plugin tắt để tránh redeclare. Không cần cài thêm gì để chạy.

## Cài đặt

### Cách 1 — clone branch này (khuyên dùng)

```bash
git clone -b brightpath-gutenburg https://github.com/Longspring2k/Wordpress-Projects.git brightpath
cp -r brightpath /path/to/wordpress/wp-content/themes/brightpath
```

Vào WP Admin → Appearance → Themes → Active **Bright Path**.

### Cách 2 — tải ZIP từ GitHub

Mở branch `brightpath-gutenburg` → Code → Download ZIP → giải nén thành thư mục `brightpath` → upload vào `wp-content/themes/` → Active.

### Cách 3 — copy từ VPS (nội bộ)

```bash
scp -r root@167.86.115.187:/srv/sites/brightpath-gutenburg/html/wp-content/themes/brightpath ./brightpath
```

Sau khi Active, starter-content tự import. Kiểm tra:

- Front-end load bình thường, không fatal
- `Appearance → Editor` mở được templates/patterns
- Form block (`brightpath/form`) render + gửi mail được (cấu hình SMTP của site)

## Cấu trúc

```
style.css          Theme header (không chứa CSS, CSS nằm ở shards)
theme.json         Global styles/settings
functions.php      Loader — chỉ require các shard trong inc/, không viết logic ở đây
inc/               setup, assets, patterns, editor-locks, nav-state, design-parity,
                   icons, icon-meta, mobile-nav, band-rhythm, page-classes,
                   starter-content, mail, post-types, forms, form-block
blocks/form/       Custom form block
assets/css|js/     CSS shards + JS (editor-canvas, mobile-nav, band-rhythm...)
parts|templates|patterns|styles|starter|tools
```

Muốn thêm tính năng: thêm 1 shard mới trong `inc/` rồi khai báo vào mảng trong `functions.php`. Shard chưa tồn tại sẽ được bỏ qua nên không sập site.

## License

GPL-2.0-or-later (kế thừa từ WordPress).
