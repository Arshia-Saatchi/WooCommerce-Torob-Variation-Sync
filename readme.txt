=== Torob Variable Product Exporter ===
Contributors: arshia
Tags: woocommerce, torob, variable products, product feed, marketplace
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

افزونه مستقل ووکامرس برای تبدیل Variationها به محصولات مستقل، تولید فید صفحه‌بندی‌شده و مدیریت همگام‌سازی ترب.

== توضیحات فارسی ==

Torob Variable Product Exporter محصولات ساده و Variationهای ووکامرس را به آیتم‌های مستقل فید تبدیل می‌کند. افزونه هیچ تغییری در هسته وردپرس، ووکامرس، سفارش‌ها یا محصولات ایجاد نمی‌کند.

قابلیت‌ها:

* خروجی مستقل محصولات ساده و Variationها
* عنوان، ویژگی‌ها، SKU، قیمت، موجودی، تصویر و لینک مستقیم Variation
* قالب عنوان سفارشی و انتخاب ویژگی‌ها
* حذف محصول، Variation و دسته‌بندی
* همگام‌سازی دستی و زمان‌بندی‌شده با پردازش مرحله‌ای
* کش Transient و صفحه‌بندی برای فروشگاه‌های بزرگ
* API رسمی Torob Product API v3 با POST و پاسخ استاندارد
* اعتبارسنجی JWT امضاشده ترب با Ed25519، exp، nbf و aud
* کاتالوگ نسخه‌دار با صفحه‌های دقیقاً ۱۰۰ آیتمی
* جست‌وجو با page_urls و page_uniques و دو مرتب‌سازی رسمی
* نمایش آخرین درخواست معتبر ترب و ثبت آن در Torob Logs
* نمایش زنده پیشرفت همگام‌سازی
* فیلتر، تازه‌سازی، کارت‌های آماری و صفحه‌بندی AJAX در Torob Logs
* دانلود UTF-8 با فرمت CSV و TXT
* توکن اختیاری فید، بررسی دسترسی، Nonce، پاک‌سازی و Escape داده‌ها
* سازگاری با HPOS و رابط RTL

API رسمی v3 در آدرس `/wp-json/torob/v3/products` قرار دارد. این آدرس را برای پشتیبانی ترب ارسال کنید. ترب JWT امضاشده و هدر نسخه ۱ را ارسال می‌کند و افزونه امضا، تاریخ اعتبار و دامنه audience را بررسی می‌کند. فید GET نسخه v1 فقط برای سازگاری Legacy باقی مانده است.

== نصب فارسی ==

1. فایل ZIP افزونه را از بخش افزودن افزونه وردپرس بارگذاری کنید.
2. افزونه را فعال کنید.
3. وارد ووکامرس > Torob Variable Sync شوید.
4. تنظیمات را ذخیره و Regenerate feed now را اجرا کنید.
5. نتیجه را در ووکامرس > Torob Logs بررسی کنید.
6. آدرس Official Torob Product API v3 را از کارت بالای تنظیمات برای پشتیبانی ترب ارسال کنید.

برای به‌روزرسانی، افزونه قبلی را حذف نکنید. ZIP جدید را بارگذاری و نسخه فعلی را جایگزین کنید تا تنظیمات و گزارش‌ها حفظ شوند.

آدرس فید:

`https://YOUR-SITE.example/wp-json/torob/v1/products`

نمونه صفحه‌بندی:

`https://YOUR-SITE.example/wp-json/torob/v1/products?page=1&per_page=25`

در صورت تنظیم توکن، کلاینت باید هدر `X-Torob-Token` را ارسال کند.

آدرس API رسمی:

`https://YOUR-SITE.example/wp-json/torob/v3/products`

پس از Sync کامل، پیام `Feed synchronization completed` یعنی کاتالوگ سایت آماده است. زمانی که ترب واقعاً API را بخواند، زمان Last authenticated Torob request تغییر می‌کند و پیام `Torob Product API v3 request completed` در لاگ ثبت می‌شود.

== English Description ==

Torob Variable Product Exporter is an independent WooCommerce extension that exports simple products and converts every variable-product variation into a standalone feed item. It never modifies WordPress core, WooCommerce core, products, or orders.

Features:

* Independent simple-product and variation feed items
* Variation titles, attributes, SKU, prices, availability, images, and direct URLs
* Custom title templates and attribute controls
* Product, variation, and category exclusions
* Manual and scheduled batched synchronization
* Transient caching and pagination for large catalogs
* Official POST-based Torob Product API v3
* Ed25519 JWT validation with exp, nbf, and audience checks
* Exact 100-item, generation-based catalog pages
* page_urls/page_uniques lookup modes and both official sort modes
* Last authenticated Torob access visibility and logging
* Live synchronization progress
* AJAX log filtering, refresh, summary cards, and pagination
* UTF-8 CSV and TXT downloads
* Optional feed token, capability checks, nonces, sanitization, and escaping
* HPOS compatibility and responsive RTL-safe administration

The official API is available at `/wp-json/torob/v3/products`. Send this URL to Torob support. Torob supplies the signed JWT and token-version header; the plugin validates its signature, time claims, and audience. The v1 GET feed remains as a legacy compatibility endpoint.

== English Installation ==

1. Upload the plugin ZIP from WordPress Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin.
3. Open WooCommerce > Torob Variable Sync.
4. Save the settings and select Regenerate feed now.
5. Verify the result under WooCommerce > Torob Logs.
6. Send the Official Torob Product API v3 URL shown in settings to Torob support.

When updating, upload the new ZIP and replace the current plugin. Do not delete it first if you want to retain settings and logs.

Feed URL:

`https://YOUR-SITE.example/wp-json/torob/v1/products`

Pagination example:

`https://YOUR-SITE.example/wp-json/torob/v1/products?page=1&per_page=25`

When a token is configured, clients must send the `X-Torob-Token` header.

Official endpoint:

`https://YOUR-SITE.example/wp-json/torob/v3/products`

`Feed synchronization completed` means the local catalog is ready. An updated Last authenticated Torob request value and a `Torob Product API v3 request completed` log entry confirm that Torob actually contacted the API.

== Changelog ==

= 1.3.0 =
* Implemented the official POST-based Torob Product API v3.
* Added dependency-free Ed25519 JWT validation for exp, nbf, and audience.
* Added an atomic generation-based catalog with exact 100-item pages.
* Added page URL/unique lookups and both official date sort modes.
* Added authenticated Torob access status and logging.
* Kept the v1 GET feed as a legacy compatibility endpoint.

= 1.2.2 =
* Moved the plugin source to the Git repository root for a cleaner GitHub workflow.
* Updated repository documentation and ignored generated release ZIP files.

= 1.2.1 =
* Added complete Persian-first and English-second GitHub documentation.
* Synchronized the WordPress readme and release changelog policy.

= 1.2.0 =
* Added authenticated AJAX filtering, summary cards, refresh, and pagination to Torob Logs.
* Added accessible loading, error recovery, and browser History API support.

= 1.1.1 =
* Redesigned the settings and logs screens with a responsive, RTL-safe card layout.
* Fixed overlapping log filter and export controls.

= 1.1.0 =
* Added live AJAX synchronization progress and CSV/TXT log downloads.
* Added ARSHIA as the plugin author.

= 1.0.0 =
* Initial release.
