=== Torob Variable Product Exporter ===
Contributors: arshia
Tags: woocommerce, torob, variable products, product feed, marketplace
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 1.2.2
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
* نمایش زنده پیشرفت همگام‌سازی
* فیلتر، تازه‌سازی، کارت‌های آماری و صفحه‌بندی AJAX در Torob Logs
* دانلود UTF-8 با فرمت CSV و TXT
* توکن اختیاری فید، بررسی دسترسی، Nonce، پاک‌سازی و Escape داده‌ها
* سازگاری با HPOS و رابط RTL

هشدار API: آدرس `/wp-json/torob/v1/products` یک فید GET مستقل است. API رسمی جدید ترب v3 به POST، JWT و ساختار متفاوت نیاز دارد. تا دریافت تأیید پشتیبانی ترب، فید فعلی را آزمایشی/Legacy در نظر بگیرید.

== نصب فارسی ==

1. فایل ZIP افزونه را از بخش افزودن افزونه وردپرس بارگذاری کنید.
2. افزونه را فعال کنید.
3. وارد ووکامرس > Torob Variable Sync شوید.
4. تنظیمات را ذخیره و Regenerate feed now را اجرا کنید.
5. نتیجه را در ووکامرس > Torob Logs بررسی کنید.

برای به‌روزرسانی، افزونه قبلی را حذف نکنید. ZIP جدید را بارگذاری و نسخه فعلی را جایگزین کنید تا تنظیمات و گزارش‌ها حفظ شوند.

آدرس فید:

`https://YOUR-SITE.example/wp-json/torob/v1/products`

نمونه صفحه‌بندی:

`https://YOUR-SITE.example/wp-json/torob/v1/products?page=1&per_page=25`

در صورت تنظیم توکن، کلاینت باید هدر `X-Torob-Token` را ارسال کند.

== English Description ==

Torob Variable Product Exporter is an independent WooCommerce extension that exports simple products and converts every variable-product variation into a standalone feed item. It never modifies WordPress core, WooCommerce core, products, or orders.

Features:

* Independent simple-product and variation feed items
* Variation titles, attributes, SKU, prices, availability, images, and direct URLs
* Custom title templates and attribute controls
* Product, variation, and category exclusions
* Manual and scheduled batched synchronization
* Transient caching and pagination for large catalogs
* Live synchronization progress
* AJAX log filtering, refresh, summary cards, and pagination
* UTF-8 CSV and TXT downloads
* Optional feed token, capability checks, nonces, sanitization, and escaping
* HPOS compatibility and responsive RTL-safe administration

API notice: `/wp-json/torob/v1/products` is an independent GET feed. The newer official Torob API v3 requires POST, JWT validation, and a different schema. Treat the current feed as experimental/legacy until Torob support confirms the shop connection method.

== English Installation ==

1. Upload the plugin ZIP from WordPress Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin.
3. Open WooCommerce > Torob Variable Sync.
4. Save the settings and select Regenerate feed now.
5. Verify the result under WooCommerce > Torob Logs.

When updating, upload the new ZIP and replace the current plugin. Do not delete it first if you want to retain settings and logs.

Feed URL:

`https://YOUR-SITE.example/wp-json/torob/v1/products`

Pagination example:

`https://YOUR-SITE.example/wp-json/torob/v1/products?page=1&per_page=25`

When a token is configured, clients must send the `X-Torob-Token` header.

== Changelog ==

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
