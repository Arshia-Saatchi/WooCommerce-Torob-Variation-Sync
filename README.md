# Torob Variable Product Exporter

افزونه مستقل ووکامرس برای تبدیل هر Variation به یک آیتم محصول مستقل، تولید فید JSON صفحه‌بندی‌شده، مدیریت همگام‌سازی و مشاهده گزارش‌ها.

**نویسنده:** ARSHIA  
**نسخه:** 1.2.2  
**مجوز:** GPL-2.0-or-later

---

## فارسی

### معرفی

Torob Variable Product Exporter یک افزونه مستقل برای WordPress و WooCommerce است که محصولات ساده و Variationهای محصولات متغیر را به آیتم‌های مستقل تبدیل می‌کند. این افزونه هیچ تغییری در هسته وردپرس، ووکامرس، محصولات، سفارش‌ها یا افزونه‌های دیگر ترب ایجاد نمی‌کند.

برای هر Variation اطلاعاتی مانند شناسه مستقل، شناسه محصول مادر، عنوان، ویژگی‌ها، SKU، قیمت، موجودی، تصویر و لینک مستقیم همان Variation تولید می‌شود.

### قابلیت‌ها

- خروجی محصولات ساده ووکامرس
- تبدیل هر Variation به یک محصول مستقل در فید
- ساخت لینک مستقیم با ویژگی‌های انتخاب‌شده و `variation_id`
- تنظیم قالب عنوان محصول
- انتخاب ویژگی‌های قابل نمایش در عنوان و فید
- حذف محصول، Variation یا دسته‌بندی از خروجی
- فید REST صفحه‌بندی‌شده با کش Transient
- همگام‌سازی دستی، ساعتی، هر ۶ ساعت یا روزانه
- پردازش مرحله‌ای برای فروشگاه‌های بزرگ
- نمایش زنده پیشرفت همگام‌سازی با AJAX
- صفحه گزارش با فیلتر، تازه‌سازی و صفحه‌بندی کاملاً AJAX
- خروجی UTF-8 با فرمت CSV و TXT
- ثبت موفقیت‌ها، هشدارها، محصولات نامعتبر و خطاهای API
- توکن اختیاری برای محافظت از فید
- سازگاری با HPOS ووکامرس
- رابط مدیریتی واکنش‌گرا و سازگار با RTL

### پیش‌نیازها

- PHP 8.0 یا جدیدتر
- WordPress 6.5 یا جدیدتر
- WooCommerce 8.0 یا جدیدتر
- فعال‌بودن REST API وردپرس
- WP-Cron یا Cron Job واقعی روی هاست برای همگام‌سازی زمان‌بندی‌شده

### نصب

1. فایل ZIP آماده را از بخش Releases دانلود کنید. اگر از سورس ZIP می‌سازید، فایل‌ها را داخل پوشه سطح اول `torob-variable-exporter` قرار دهید.
2. در مدیریت وردپرس به **افزونه‌ها ← افزودن افزونه تازه ← بارگذاری افزونه** بروید.
3. فایل ZIP را نصب و فعال کنید.
4. به **ووکامرس ← Torob Variable Sync** بروید.
5. تنظیمات عنوان، ویژگی‌ها، حذف‌ها و زمان‌بندی را ذخیره کنید.
6. روی **Regenerate feed now** بزنید.
7. نتیجه را در **ووکامرس ← Torob Logs** بررسی کنید.

برای به‌روزرسانی، افزونه قبلی را حذف نکنید. ZIP جدید را بارگذاری و گزینه جایگزینی نسخه فعلی را انتخاب کنید تا تنظیمات و گزارش‌ها حفظ شوند.

### استفاده از فید فعلی

آدرس فید:

```text
https://example.com/wp-json/torob/v1/products
```

نمونه صفحه‌بندی:

```text
https://example.com/wp-json/torob/v1/products?page=1&per_page=25
```

پارامترها:

| پارامتر | توضیح |
|---|---|
| `page` | شماره صفحه؛ از ۱ شروع می‌شود |
| `per_page` | تعداد محصولات پایه در هر صفحه؛ حداکثر ۱۰۰ |

محصول متغیر ممکن است به چند آیتم خروجی تبدیل شود؛ بنابراین تعداد آیتم‌های `products` می‌تواند از `per_page` بیشتر باشد.

اگر در تنظیمات API Token تعیین شده باشد، کلاینت باید هدر زیر را ارسال کند:

```http
X-Torob-Token: YOUR_TOKEN
```

### نکته مهم درباره API رسمی ترب

Endpoint فعلی `/torob/v1/products` یک فید GET مستقل است. مستندات رسمی جدید Torob Product API v3 به درخواست POST، احراز هویت JWT، صفحه‌های دقیقاً ۱۰۰تایی و فیلدهای متفاوت نیاز دارد. تا زمان تأیید روش اتصال توسط پشتیبانی ترب، Endpoint فعلی را به‌عنوان فید آزمایشی/Legacy در نظر بگیرید، نه پیاده‌سازی نهایی API v3.

پشتیبانی از API رسمی v3 پس از دریافت اطلاعات احراز هویت و تأیید روش اتصال فروشگاه در نسخه بعدی برنامه‌ریزی شده است.

### گزارش‌ها و AJAX

صفحه **Torob Logs** امکانات زیر را بدون رفرش کامل صفحه ارائه می‌کند:

- انتخاب کارت‌های وضعیت
- فیلتر کشویی
- تازه‌سازی گزارش‌ها
- صفحه‌بندی
- به‌روزرسانی شمارنده‌ها
- پشتیبانی از Back و Forward مرورگر

دانلود CSV/TXT به‌صورت دانلود عادی مرورگر انجام می‌شود، اما همیشه از آخرین فیلتر فعال AJAX استفاده می‌کند.

### توسعه و شخصی‌سازی

برای تغییر هر آیتم بدون ویرایش فایل‌های افزونه می‌توان از فیلترهای زیر استفاده کرد:

```php
add_filter( 'tves_simple_product_item', function ( array $item, WC_Product $product ): array {
	return $item;
}, 10, 2 );

add_filter( 'tves_variation_item', function ( array $item, WC_Product_Variation $variation, WC_Product $parent ): array {
	return $item;
}, 10, 3 );
```

### ساختار پروژه

```text
WooCommerce-Torob-Variation-Sync/
├── torob-variable-exporter.php
├── includes/
│   ├── class-product-handler.php
│   ├── class-variation-handler.php
│   ├── class-feed-generator.php
│   ├── class-torob-api.php
│   ├── class-admin-settings.php
│   ├── class-sync-manager.php
│   ├── class-logger.php
│   └── class-exclusion-manager.php
├── admin/
│   ├── settings-page.php
│   ├── logs-page.php
│   └── logs-results.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── logs/
├── uninstall.php
├── readme.txt
└── README.md
```

### امنیت

- بررسی سطح دسترسی `manage_woocommerce`
- Nonce برای عملیات مدیریتی و AJAX
- پاک‌سازی ورودی‌ها و Escape خروجی‌ها
- Endpoint فقط‌خواندنی
- توکن اختیاری فید
- جلوگیری از CSV Formula Injection
- عدم ذخیره لاگ در فایل عمومی؛ گزارش‌ها در جدول اختصاصی دیتابیس قرار می‌گیرند

### مشارکت در GitHub

1. Repository را Fork کنید.
2. یک Branch برای تغییر خود بسازید.
3. تغییرات را با استانداردهای WordPress و PHP 8 انجام دهید.
4. شماره نسخه و هر دو فایل `README.md` و `readme.txt` را هماهنگ کنید.
5. Pull Request همراه با توضیح و روش تست ارسال کنید.

### تاریخچه نسخه‌ها

#### 1.2.2

- انتقال سورس افزونه به ریشه Repository متصل به GitHub Desktop
- هماهنگ‌سازی مستندات با ساختار جدید و جلوگیری از Commit شدن ZIPهای خروجی

#### 1.2.1

- ایجاد مستندات کامل GitHub به زبان فارسی و انگلیسی
- هماهنگ‌سازی سیاست مستندسازی و Changelog

#### 1.2.0

- AJAX کامل برای فیلتر، کارت‌های وضعیت، تازه‌سازی و صفحه‌بندی لاگ‌ها
- اضافه‌شدن Loading، مدیریت خطا و History API

#### 1.1.1

- بازطراحی UI/UX صفحات تنظیمات و گزارش‌ها
- رفع هم‌پوشانی کنترل‌های فیلتر و دانلود در RTL

#### 1.1.0

- دانلود CSV/TXT
- نمایش زنده پیشرفت همگام‌سازی
- ثبت نویسنده ARSHIA

#### 1.0.0

- انتشار اولیه

---

## English

### Overview

Torob Variable Product Exporter is an independent WordPress and WooCommerce extension that exports simple products and converts every variable-product variation into a standalone feed item. It does not modify WordPress core, WooCommerce core, product/order data, or another Torob plugin.

Each variation can include its own stable ID, parent ID, title, selected attributes, SKU, prices, availability, stock quantity, image, and direct variation URL.

### Features

- WooCommerce simple-product export
- One standalone feed item per variation
- Direct variation URLs with selected attributes and `variation_id`
- Configurable title formats and custom templates
- Dynamic title/export attribute selection
- Product, variation, and category exclusions
- Paginated REST feed with transient caching
- Manual, hourly, six-hour, or daily synchronization
- Batched processing for large catalogs
- Live AJAX synchronization progress
- Fully AJAX-powered log filters, refresh, counters, and pagination
- UTF-8 CSV and TXT log exports
- Structured success, warning, invalid-product, and API-error logging
- Optional feed access token
- WooCommerce HPOS compatibility declaration
- Responsive, RTL-safe admin interface

### Requirements

- PHP 8.0+
- WordPress 6.5+
- WooCommerce 8.0+
- WordPress REST API enabled
- WP-Cron or a real server cron job for scheduled synchronization

### Installation

1. Download the installable ZIP from GitHub Releases. When building from source, place the files inside a top-level `torob-variable-exporter` directory before creating the ZIP.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Install and activate the ZIP.
4. Open **WooCommerce → Torob Variable Sync**.
5. Save the title, attribute, exclusion, and schedule settings.
6. Select **Regenerate feed now**.
7. Verify the result under **WooCommerce → Torob Logs**.

When updating, do not delete the installed plugin. Upload the new ZIP and replace the current version so settings and logs remain intact.

### Current Feed Usage

Feed endpoint:

```text
https://example.com/wp-json/torob/v1/products
```

Pagination example:

```text
https://example.com/wp-json/torob/v1/products?page=1&per_page=25
```

| Parameter | Description |
|---|---|
| `page` | One-based source-product page |
| `per_page` | Source products per page; maximum 100 |

A variable source product can expand into several feed items, so the `products` array may contain more records than `per_page`.

When an API token is configured, clients must send:

```http
X-Torob-Token: YOUR_TOKEN
```

### Important Torob API Notice

The current `/torob/v1/products` endpoint is an independent GET feed. The newer official Torob Product API v3 specification requires POST requests, JWT validation, exact 100-item pages, and a different response schema. Until Torob support confirms the connection method for a shop, treat the current endpoint as an experimental/legacy feed—not a final v3 implementation.

Official v3 support is planned after the required authentication details and shop connection method are confirmed.

### Logs and AJAX

The **Torob Logs** screen updates the following without a full page reload:

- Status summary cards
- Status select filter
- Manual refresh
- Pagination
- Summary counters
- Browser Back/Forward navigation

CSV/TXT files use a normal browser download while remaining synchronized with the active AJAX filter.

### Extension Hooks

Customize feed items without editing plugin files:

```php
add_filter( 'tves_simple_product_item', function ( array $item, WC_Product $product ): array {
	return $item;
}, 10, 2 );

add_filter( 'tves_variation_item', function ( array $item, WC_Product_Variation $variation, WC_Product $parent ): array {
	return $item;
}, 10, 3 );
```

### Project Structure

See the project tree in the Persian section above. Runtime logs are stored in a dedicated WordPress database table; the `logs/` directory is intentionally protected and does not contain exported log data.

### Security

- `manage_woocommerce` capability checks
- Nonces for administrative and AJAX actions
- Input sanitization and output escaping
- Read-only feed endpoint
- Optional feed token
- CSV Formula Injection protection
- Database-backed logs instead of public log files

### Contributing

1. Fork the repository.
2. Create a focused feature branch.
3. Follow WordPress coding practices and PHP 8 compatibility.
4. Keep the plugin version, `README.md`, `readme.txt`, and both changelogs synchronized.
5. Open a pull request with a clear description and test instructions.

### Changelog

#### 1.2.2

- Moved the plugin source to the Git repository root for a cleaner GitHub Desktop workflow.
- Updated repository documentation and ignored generated release ZIP artifacts.

#### 1.2.1

- Added complete Persian-first and English-second GitHub documentation.
- Documented the synchronized README and changelog policy.

#### 1.2.0

- Added fully AJAX-powered log filtering, counters, refresh, and pagination.
- Added loading, error recovery, and browser History API support.

#### 1.1.1

- Redesigned the settings and logs UI/UX.
- Fixed overlapping filter/export controls in RTL layouts.

#### 1.1.0

- Added CSV/TXT downloads and live synchronization progress.
- Set the plugin author to ARSHIA.

#### 1.0.0

- Initial release.

### License

Licensed under the GNU General Public License v2.0 or later.
