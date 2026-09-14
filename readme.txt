=== Torob Variable Product Exporter ===
Contributors: arshia
Tags: woocommerce, torob, variable products, product feed, marketplace
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 2.0.0-beta.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

افزونه مستقل ووکامرس برای تبدیل Variationها به محصولات مستقل، تولید فید صفحه‌بندی‌شده و مدیریت همگام‌سازی ترب.

== توضیحات فارسی ==

Torob Variable Product Exporter محصولات ساده و Variationهای ووکامرس را به آیتم‌های مستقل فید تبدیل می‌کند. افزونه هیچ تغییری در هسته وردپرس، ووکامرس، سفارش‌ها یا محصولات ایجاد نمی‌کند.

قابلیت‌ها:

* خروجی مستقل محصولات ساده و Variationها
* ارسال محصولات بدون قیمت با current_price صفر و وضعیت ناموجود
* عنوان، ویژگی‌ها، SKU، قیمت، موجودی، تصویر و لینک مستقیم Variation
* قالب عنوان سفارشی و انتخاب ویژگی‌ها
* حذف محصول، Variation و دسته‌بندی
* همگام‌سازی دستی و زمان‌بندی‌شده با پردازش مرحله‌ای
* کش Transient و صفحه‌بندی برای فروشگاه‌های بزرگ
* API رسمی Torob Product API v3 با POST و پاسخ استاندارد
* اعتبارسنجی JWT امضاشده ترب با Ed25519، exp، nbf و aud
* پذیرش امن audience دامنه فروشگاه با www یا بدون www
* کاتالوگ نسخه‌دار با صفحه‌های دقیقاً ۱۰۰ آیتمی
* جست‌وجو با page_urls و page_uniques و دو مرتب‌سازی رسمی
* نرمال‌سازی URL برای www، اسلش پایانی، پروتکل، ترتیب پارامترها و تغییر شناسه داخلی Variation
* نمایش آخرین درخواست معتبر ترب و ثبت آن در Torob Logs
* نمایش زنده پیشرفت همگام‌سازی
* ادامه Batchهای سررسیدشده با AJAX در صورت اختلال WP-Cron
* فیلتر، تازه‌سازی، کارت‌های آماری و صفحه‌بندی AJAX در Torob Logs
* دانلود UTF-8 با فرمت CSV و TXT
* پاک‌سازی کامل لاگ‌ها به‌صورت AJAX، حذف خودکار موارد قدیمی‌تر از ۳۰ روز و سقف ۲۰٬۰۰۰ رکورد
* توکن اختیاری فید، بررسی دسترسی، Nonce، پاک‌سازی و Escape داده‌ها
* سازگاری با HPOS و رابط RTL

API رسمی v3 در آدرس `/wp-json/torob/v3/products` قرار دارد. این آدرس را برای پشتیبانی ترب ارسال کنید. ترب JWT امضاشده و هدر نسخه ۱ را ارسال می‌کند و افزونه امضا، تاریخ اعتبار و دامنه audience را بررسی می‌کند. فید GET نسخه v1 فقط برای سازگاری Legacy باقی مانده است.

== نصب فارسی ==

1. فایل ZIP افزونه را از بخش افزودن افزونه وردپرس بارگذاری کنید.
2. افزونه را فعال کنید.
3. وارد ووکامرس > Torob Variable Sync شوید.
4. تنظیمات را ذخیره، Regenerate feed now را اجرا و صفحه را تا تکمیل عملیات باز نگه دارید.
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
* Products without prices exported as unavailable zero-price items
* Variation titles, attributes, SKU, prices, availability, images, and direct URLs
* Custom title templates and attribute controls
* Product, variation, and category exclusions
* Manual and scheduled batched synchronization
* Transient caching and pagination for large catalogs
* Official POST-based Torob Product API v3
* Ed25519 JWT validation with exp, nbf, and audience checks
* Secure audience compatibility for the shop host with or without www
* Exact 100-item, generation-based catalog pages
* page_urls/page_uniques lookup modes and both official sort modes
* URL lookup normalization for www, protocol, trailing slash, query order, and changed internal variation IDs
* Last authenticated Torob access visibility and logging
* Live synchronization progress
* AJAX continuation for due batches when WP-Cron stalls
* AJAX log filtering, refresh, summary cards, and pagination
* UTF-8 CSV and TXT downloads
* Confirmed AJAX log clearing, automatic 30-day cleanup, and a 20,000-record cap
* Optional feed token, capability checks, nonces, sanitization, and escaping
* HPOS compatibility and responsive RTL-safe administration
* Experimental second-generation operations UI with a connected API/catalog/sync status rail
* Instant exclusion-category filtering and clear exclusion-scope guidance
* Expandable technical context inside log rows and one-click endpoint copying

The official API is available at `/wp-json/torob/v3/products`. Send this URL to Torob support. Torob supplies the signed JWT and token-version header; the plugin validates its signature, time claims, and audience. The v1 GET feed remains as a legacy compatibility endpoint.

== English Installation ==

1. Upload the plugin ZIP from WordPress Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin.
3. Open WooCommerce > Torob Variable Sync.
4. Save the settings, select Regenerate feed now, and keep the page open until completion.
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

= 2.0.0-beta.1 =
* Completely redesigns settings and logs around a Torob operations-console identity.
* Adds a connected status rail for the secure API, catalog, and synchronization.
* Reorganizes controls around product output, exclusion scope, and catalog rebuilding.
* Adds instant category filtering and guidance for whole products, single variations, and categories.
* Adds endpoint copy controls and expandable technical context in log rows.
* Improves keyboard focus, reduced-motion support, RTL behavior, and mobile layouts.
* Preserves all API, synchronization, exclusion behavior, and version 1.5.1 fixes.

= 1.5.1 =
* Fixes empty single-product responses for historical URLs whose internal variation ID has changed.
* Adds a stable URL index based on the product path and selected attributes while keeping exact matches first.
* Automatically upgrades existing catalogs without deleting settings or logs.
* Logs counts and samples of unresolved lookup values for CSV/TXT diagnostics.

= 1.5.0 =
* Exports products and variations without prices as unavailable with current_price 0.
* Prevents missing-price products from being interpreted as free by forcing availability false.
* Adds an authenticated AJAX fallback for due batches when WP-Cron stalls.
* Normalizes page_urls lookups across www, protocol, trailing slash, and query-string order.
* Keeps exact-hash compatibility with existing catalogs until the next complete regeneration.

= 1.4.0 =
* Added a confirmed AJAX action for clearing all Torob logs.
* Refreshes the log table, pagination, and counters immediately after cleanup.
* Runs automatic 30-day cleanup and enforces a 20,000-record cap independently at most once per day.
* Includes the 1.3.4 spec-object response fix for simple products and existing payloads.

= 1.3.4 =
* Fixed empty spec values being converted from JSON objects to arrays when catalog payloads were read.
* Normalized spec as an object in every API v3 response, including previously stored payloads.
* Fixed api_v3_invalid_product for simple products without attributes; regeneration is not required.

= 1.3.3 =
* Added automatic stalled-sync detection and recovery.
* Added generation-specific batches, lost WP-Cron recovery, and a crash-tolerant mutex.
* Added three controlled retries while preserving the previous complete catalog.
* Reduced batch size to lower timeout risk and kept API reads isolated from active builds.
* Added live last-activity and recovery/retry counters to the synchronization status.

= 1.3.2 =
* Guaranteed a JSON object for every product spec field.
* Changed empty specs from [] to the Torob-required empty dictionary {}.
* Requires one complete catalog regeneration after upgrading.

= 1.3.1 =
* Added strictly scoped JWT audience support for the shop's www and non-www hosts.
* Kept signature and time checks mandatory and restricted requests to the same host pair.
* Added received audience and request-host context to API diagnostics.

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
