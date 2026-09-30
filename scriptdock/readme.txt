=== ScriptDock ===
Contributors: scriptdock
Tags: code snippets, header footer, custom css, custom javascript, php
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add PHP, HTML, CSS and JavaScript anywhere: site-wide, per page or by rules. Crash protection, tamper protection, revisions, no upsells.

== Description ==

ScriptDock is a complete, free code manager for WordPress. Everything that other snippet plugins sell as "Pro" is included, with no locked features, no ads and no tracking.

= Add code anywhere =

* **Five code types:** PHP, HTML, CSS, JavaScript and Universal (HTML with embedded PHP).
* **30+ locations:** site header, after `<body>`, footer, before/after content, before/after paragraph N, between posts, excerpts, admin screens, the login page, the block editor, WooCommerce (shop, product, cart, checkout, thank-you page, account), any custom action hook, or only where you place a shortcode or the ScriptDock block.
* **Run PHP** everywhere, on the front end only, in the admin only, or on demand with a "Run now" button for one-off tasks.

= Per-page code =

Every post and page gets a ScriptDock panel with header, body, footer, before-content, after-content, CSS and JavaScript fields, plus switches to turn off site-wide snippets on that page. In the block editor it is a sidebar panel with a full-screen code window; the classic editor keeps a meta box. Either way the code is saved when you update the page.

= Conditional logic =

Show or hide snippets with AND/OR rule groups: page type, post type, specific posts, child pages, categories/tags/terms, page template, author, custom fields, URL (contains, wildcard, regex), URL parameters, referrer, cookies, login status, user role, user meta, device, browser, operating system, day of week, time of day, date, language (Polylang and WPML aware), your own PHP function, and WooCommerce cart total or contents.

A five-step wizard walks through placement, pages and content, audience and schedule, and writes the rules in plain language as you go — "Runs in the site header on Pricing and Contact, for logged-out visitors, on mobile" — with a live count of how many pages match and a URL tester. The raw rule builder is still there behind an "Advanced rules" switch.

= Made to be lived in =

* **Overview:** what is running, what needs attention, what you edited last, and the one thing worth doing next.
* **Command palette:** press Cmd K or Ctrl K anywhere in ScriptDock to jump to a snippet or run an action.
* **Guided setup** on the first visit, which saves your safe mode link and offers to bring snippets over from another plugin.
* **History:** every save is a version you can compare side by side and restore.

= Safety first =

* **Crash protection:** PHP is syntax-checked while you type and before saving, and snippets that run everywhere are test-run before they are switched on. A snippet that causes a fatal error, even inside a hook it registered earlier, is identified precisely and switched off automatically, with an optional email to the site admin.
* **Safe mode:** a secret recovery link, an admin bar switch and a `wp-config.php` constant stop all snippets while you fix things.
* **Tamper protection:** snippets are signed with a secret key from `wp-config.php`. Code injected straight into the database (a common way malware hides in snippet plugins) does not run until an administrator reviews it.
* **Permissions:** managing snippets requires `manage_options` and `unfiltered_html`; PHP additionally requires `edit_plugins`, so `DISALLOW_FILE_EDIT` also locks PHP snippets. On multisite only super admins can manage code.

= Fast and privacy-friendly loading =

* Active snippets are compiled into one cached, signed option: no extra database queries on page loads.
* CSS and JavaScript can load as cached files; CSS can be minified.
* Load strategies for JavaScript and tracking codes: normal, defer, async, when idle, or on first user interaction (scroll, tap, click, key press).
* Cookie consent: hold any script until the visitor consents to a category, using the WP Consent API supported by Complianz, CookieYes, Cookiebot and others.

= Everything else =

* Smart tags such as `{{post_title}}`, `{{page_url}}`, `{{user_role}}` and WooCommerce order data (`{{wc_order_total}}`, `{{wc_order_items|json}}`) for conversion tracking. The order-received page verifies the order key, so order data never leaks.
* 47 ready-made snippets: Google Analytics 4, Google Tag Manager, Meta Pixel, TikTok, LinkedIn, Pinterest, Microsoft Clarity and UET, Hotjar, Plausible, Fathom, Matomo, GA4 and Meta purchase events, performance and security tweaks, and more. Tracking IDs are validated before they are inserted.
* Scheduling (start and end dates), test mode (only administrators see a snippet), priorities, tags, notes, duplicate, bulk actions.
* Header & Footer screen for quick site-wide code.
* Site Files: edit ads.txt, app-ads.txt, llms.txt, security.txt and robots.txt from the dashboard.
* Admin bar inspector that shows which snippets run on the current page.
* Import and export JSON; migrate from WPCode, Insert Headers and Footers, Code Snippets, Header Footer Code Manager and Simple Custom CSS and JS.
* WP-CLI: `wp scriptdock list|activate|deactivate|approve|export|import|run|rebuild|safe-mode-url`.
* Site Health test and debug information.
* Translation ready.

== Installation ==

1. Upload the `scriptdock` folder to `/wp-content/plugins/`, or upload the zip under Plugins > Add New > Upload Plugin.
2. Activate ScriptDock.
3. Setup opens the first time you visit ScriptDock. It takes a minute: save the safe mode link somewhere outside WordPress, and bring your snippets over from another plugin if you have one.

You can skip setup and do the same things later under ScriptDock > Settings and ScriptDock > Import & Export.

== Frequently Asked Questions ==

= A snippet broke my site. What now? =

ScriptDock switches off a snippet that causes a fatal error automatically, so reloading the page is usually enough. If you are still locked out, open your safe mode link (ScriptDock > Settings), or add `define( 'SCRIPTDOCK_SAFE_MODE', true );` to `wp-config.php`.

= Why does a snippet say "Needs review"? =

Its code changed without going through ScriptDock, for example directly in the database, by a search-and-replace during a migration, or because your WordPress security keys changed. Open it, check the code and save it to approve it. To keep signatures valid when you rotate salts, define a dedicated `SCRIPTDOCK_SIGNING_KEY` in `wp-config.php`.

= Can editors use it? =

No. Snippets can contain unfiltered code, so only administrators with the `unfiltered_html` capability can manage them. Editors can still place snippets that use the "Shortcode or block only" location.

= Does it work with page caching? =

Yes. Rules based on the page, post type, URL and schedule work with full-page caching. Rules that change per visitor (device, browser, cookies, referrer, login status) need a cache that varies by those, as noted in the editor.

= How do I harden it? =

* `define( 'SCRIPTDOCK_DISABLE_PHP', true );` switches off all PHP snippets.
* `DISALLOW_FILE_EDIT` stops PHP snippet editing, like the core file editors. `SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT` overrides that.
* `DISALLOW_UNFILTERED_HTML` stops all snippet management.

= Does it contact external servers? =

Only to check for updates: WordPress asks GitHub's releases API whether github.com/onlee-agency/scriptdock has a newer version, a few times a day. Nothing about your site is sent beyond what any web request carries. To switch this off, add `define( 'SCRIPTDOCK_UPDATE_REPO', '' );` to `wp-config.php`. Snippets you add, such as analytics tags, load their own third-party services.

== Credits ==

ScriptDock's screens use two fonts that ship with the plugin, so the admin never loads anything from a font service: Plus Jakarta Sans (The Plus Jakarta Sans Project Authors) and JetBrains Mono (The JetBrains Mono Project Authors). Both are licensed under the SIL Open Font License 1.1; the licence texts are in assets/fonts/.

== Changelog ==

= 1.0.0 =
* First release.
* Add PHP, HTML, CSS, JavaScript and Universal code in 33 places, site-wide or on one page.
* Five-step targeting wizard with a plain-language summary, a live page count and a URL tester; 33 conditional rules.
* Crash protection: syntax checks, a test run before activation, automatic switch-off on a fatal error, and an email saying which snippet, which line and which page.
* Safe mode by secret link, admin bar or wp-config.php constant.
* Tamper protection: signed snippets, so code changed in the database waits for review.
* History with side-by-side comparison and restore.
* 47-snippet library, smart tags, Header & Footer screen, site files editor, admin bar inspector.
* Importers for WPCode, Insert Headers and Footers, Code Snippets, Header Footer Code Manager and Simple Custom CSS and JS.
* Cached compiled runtime, CSS/JS as files, defer/async/idle/on-interaction loading, WP Consent API gating.
* WP-CLI commands and a Site Health check.
