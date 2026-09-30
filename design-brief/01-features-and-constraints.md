# ScriptDock: feature inventory & design constraints

Input document for designing every ScriptDock screen and component in Claude Design.

- **Sections 1–5** describe what the plugin does today: every screen, field, action, state and message. The underlying code is already built and tested.
- **Section 6** covers what WordPress fixes around our screens and where we are free.
- **Section 7** specifies the new multi-step "Where should it run?" experience.
- **Sections 8–10** cover proposed new screens, the component inventory and the decisions still open.

> **Name.** "ScriptDock" is a working name and will change. Treat it as a swappable wordmark. The product is also referred to below as "the plugin".

---

## 1. Product snapshot

| | |
|---|---|
| What | A WordPress plugin that adds PHP, HTML, CSS and JavaScript anywhere on a site: site-wide, on specific pages, or by rules. It is a code-snippet manager, a header/footer script manager and a per-page script manager in one. |
| Positioning | Everything competitors sell as "Pro" (WPCode Pro, Code Snippets Pro, HFCM Pro…) is included free. No upsells, ads or tracking. Distributed from GitHub with its own updater. |
| Users | (1) Site owners and marketers adding tracking codes, pixels and banners. (2) Developers adding PHP hooks, custom CSS and JS. (3) Agencies managing many client sites. |
| Promise | "Add code safely." Crash protection, tamper protection, safe mode, revisions. |
| Feel we want | Premium, calm, trustworthy, developer-grade but friendly to non-developers. Clear hierarchy, generous spacing, strong states, no clutter. |

---

## 2. Navigation & information architecture

### 2.1 WordPress sidebar menu (exists)
Top-level item **ScriptDock** (code icon, position under Settings) with sub-items:

1. **All Snippets**: snippet list
2. **Add Snippet**: snippet editor (also used for editing)
3. **Header & Footer**: quick site-wide code
4. **Library**: 47 ready-made snippets
5. **Tags**: snippet tags (WordPress core screen)
6. **Site Files**: ads.txt, app-ads.txt, llms.txt, security.txt, robots.txt
7. **Import & Export**: migrate from other plugins, JSON import/export
8. **Settings**

### 2.2 Other surfaces
- **Post/page editor:** "Page scripts" box on every post and page (block editor and classic editor).
- **Block inserter:** "ScriptDock Snippet" block.
- **Front-end admin bar (toolbar):** "ScriptDock (N)" inspector menu.
- **Plugins screen:** row links "Snippets | Settings", plus update notices and a "View details" changelog from GitHub.
- **Tools › Site Health:** status test and debug information.
- **Admin notices:** safe mode, auto-deactivated snippet, snippets needing review.
- **Email:** alert to the site admin when a snippet is auto-deactivated.
- **WP-CLI:** command-line tool. Not visual, but may need a docs/help page.

---

## 3. Screen-by-screen inventory (as built)

Current visuals are WordPress-native with light custom CSS. **Everything below may be redesigned.** The lists describe content and behaviour, not the required look.

### 3.1 All Snippets (list)
**Header:** title "Snippets" + "Add Snippet" button. The notice area sits below the header.

**Empty state (no snippets at all):** welcome panel. Heading "Welcome to ScriptDock", one-line pitch, three large buttons: *Add your first snippet*, *Browse the library*, *Import from another plugin*.

**Views:** All (n) · Active (n) · Inactive (n) · Needs review (n), shown only when any exist · Trash (n).

**Toolbar:**
- Bulk actions: Activate, Deactivate, Export, Move to Trash. In Trash: Restore, Delete permanently.
- Filters: All types (PHP/HTML/CSS/JavaScript/Universal); All locations (grouped list of 33); All tags (only when tags exist). WordPress also adds "All dates", which can be removed.
- Search: searches titles and code.
- Pagination: 20 per page, adjustable.

**Columns:**
| Column | Content |
|---|---|
| ☐ | Bulk-select checkbox |
| Status | On/off switch. Toggles instantly via AJAX, with optimistic UI, a busy state, and an error toast that reverts the switch. Disabled when the user may not edit this type (e.g. PHP without permission). |
| Title | Link to the editor. Row actions on hover: Edit · Duplicate · Export · Trash. |
| Type | Coloured type chip (PHP, HTML, CSS, JavaScript, Universal). |
| Location | Location name. For custom hooks, the hook name in code style. For shortcode snippets, a click-to-copy `[scriptdock id="12"]` chip. Status badges below (next table). |
| Priority | Number (sortable). |
| Tags | Tag links. |
| Modified | "5 minutes ago", with the full date on hover (sortable, default sort newest first). |

**Status badges** (several can show at once):
| Badge | Meaning | Tone |
|---|---|---|
| Needs review | Code changed outside the plugin; paused until approved (tamper protection) | danger |
| Error | Last error recorded; message in tooltip | danger |
| Test mode | Only administrators see it | info |
| Conditional | Has targeting rules | neutral |
| Scheduled | Start date in the future (date in tooltip) | info |
| Expired | End date passed | warning |
| Consent | Waits for cookie consent (category in tooltip) | neutral |

**Feedback:**
- "N snippets activated/deactivated" after bulk actions.
- Per-item error messages (e.g. "has a syntax error and cannot be activated", "changed outside ScriptDock, review it first").

### 3.2 Add / Edit Snippet (the core screen)
**Layout today:** a main column plus a 320px sidebar; one column below 1100px.

**Banners** (top, only when relevant):
- **Tampered:** "This snippet was changed outside ScriptDock". Its code no longer matches its signature, so it is not running. Saving approves it. (danger)
- **No permission for PHP:** shows the exact reason, e.g. file editing is disabled with `DISALLOW_FILE_EDIT` and here is how to allow it. The whole form becomes read-only. (warning)
- **Last error:** message, line number, time ago and request URL, plus "Fix the code and save…". (danger)
- **Result messages after saving:** success/error/warning. For "Run now" results, the banner includes a preformatted output panel.

**Main column:**
1. **Title**: large input, placeholder "Snippet title".
2. **Code type selector**: 5 options as pills. The selected pill is outlined in its type colour, and a one-line description of the selected type sits below:
   - PHP: code that runs on the server, e.g. hooks and filters.
   - HTML: markup printed as-is, including `<script>`/`<style>`; ideal for tracking codes.
   - CSS: styles, inline or as a cached file.
   - JavaScript: JS, inline or as a cached file.
   - Universal: HTML with embedded `<?php ?>`, like a theme template. The UI could label this "HTML + PHP".
   - PHP and Universal are disabled, with a tooltip explaining why, when the user lacks PHP permission.
3. **Code editor**: CodeMirror with line numbers and syntax highlighting per type.
   - **Toolbar:** language label, live status, and a *Full screen* toggle (Esc exits).
   - **Live status:** "✓ No syntax errors" or "Line 3: syntax error, unexpected '}'" in red. PHP is checked on the server about 0.7s after typing stops, and the error line is highlighted. CSS, JS and HTML use the built-in linters, which show gutter markers.
   - **Shortcuts:** Ctrl/⌘+S saves; Ctrl/⌘+/ toggles a comment.
   - **Other behaviour:** light or dark theme (a setting), an example placeholder per type, and an "unsaved changes" warning when leaving.
4. **"Where should it run?"** panel (**to be redesigned; see §7**):
   - Location dropdown (33 locations, grouped, filtered to those allowed for the chosen type), with a description line below.
   - Extra fields by location:
     - Before/after paragraph: *Paragraph number* (1–999).
     - Between posts: *Insert after every N posts*.
     - Custom action hook: *Hook name* with 16 suggestions, plus a note that the priority is used as the hook priority and PHP can read `$hook_args`.
     - Shortcode/block only: a copyable shortcode chip, a hint about the block, and a note that attributes are available as `$atts` and `{{attr:name}}`.
     - Run on demand: *Run now* button (saved PHP only) and an explanation.
   - **Priority** number (default 10; lower runs first).
5. **Conditional logic** panel (**to be redesigned; see §7**):
   - Checkbox "Only run this snippet when certain rules match".
   - Show/Hide dropdown and the sentence "…this snippet when any group below matches (all rules in a group must match)".
   - Rule groups joined by **OR**; rules inside a group joined by **AND**. Each rule row has a rule dropdown (grouped by Page, Request, User, Device, Date & time, Site, WooCommerce), an operator dropdown, a value control, a remove "×", and an optional note (e.g. the page-caching warning).
   - Value controls:
     - Multi-select chips (+ "Add…" dropdown).
     - Search chips: type to search posts, pages, terms, users or products by name or ID. Results appear in a dropdown with keyboard support.
     - "One per line" textarea for URL and referrer patterns.
     - Key + value inputs; the value is hidden for exists/does not exist.
     - Time range ("09:00 and 17:00").
     - Date-time, number, text.
   - Actions: "+ Add rule (AND)" and "Add rule group (OR)".
6. **Loading** panel (CSS, JS and HTML only):
   - **Output** (CSS/JS): *Inline in the page* / *As a cached file*. The file option is disabled for locations that cannot load files, with a note.
   - **Load strategy** (JS/HTML):
     - Normal.
     - Defer: after the page is parsed.
     - Async: JS files only.
     - When idle: after the page has loaded.
     - On user interaction: first scroll, tap, click or key press.
     - A help line: delaying third-party scripts speeds up pages.
   - **Require cookie consent** (JS/HTML): No, Statistics, Anonymous statistics, Marketing, Preferences, Functional. The help text changes depending on whether a consent plugin (WP Consent API) is active.

**Sidebar:**
1. **Status**:
   - *Active* switch, and a *Test mode* checkbox ("only administrators see it").
   - Primary *Save snippet* / *Update snippet* button with the hint "or Ctrl/⌘ + S".
   - Meta: "Last saved: date", "N revisions" link, *Duplicate · Export · Move to Trash*.
2. **Schedule**: *Start* and *End* date-time (optional), with a note naming the site timezone.
3. **Details**:
   - *Tags* (comma separated) and *Notes* (textarea).
   - *Replace smart tags such as {{post_title}}* (HTML/JS).
   - *Run shortcodes inside this HTML* (HTML).
4. **Smart tags** reference (HTML/JS):
   - Help line about the `|js`, `|json` and `|url` modifiers.
   - Collapsible groups (Site & page, Post, User & time, WooCommerce). Clicking a tag inserts it at the cursor, and each tag shows a short description.

**Save outcomes** (each produces a message):
- Saved and active.
- Saved but inactive.
- PHP syntax error: saved and left inactive, with line and message.
- Fatal error during the automatic test run: saved and left inactive, with the error box. No white screen; the user lands back in the editor.
- Custom hook name missing: saved and left inactive.
- Tampered snippet: saving approves it.

### 3.3 Page scripts (inside the post/page editor)
- Box titled "ScriptDock: page scripts". It appears in the block editor's bottom "Meta boxes" area and in the classic editor. Enabled per content type in Settings (default: posts and pages).
- **Tabs:** Header · Body · Footer · Before content · After content · CSS · JavaScript · Site-wide snippets.
  - A green dot marks tabs that contain code; an amber dot on "Site-wide snippets" means something is switched off.
- **Code tabs:** a one-line description plus a code editor (created when the tab opens).
- **Site-wide snippets tab** (three radio options):
  - Run all matching site-wide snippets.
  - Turn off all site-wide snippets *and* the global header/footer on this page.
  - Turn off selected snippets: a checklist of active front-end snippets showing title plus "type · location".
  - A note that PHP "run everywhere" snippets cannot be switched off per page.
- Inline tamper warning when stored code was changed outside the plugin.
- Saved together with the post (WordPress Update/Save button). Smart tags work in these fields.

### 3.4 Header & Footer
- Intro line. The tamper warning shows when relevant.
- Three panels (**Header** `<head>`, **Body** after `<body>`, **Footer** before `</body>`). Each has a description, an HTML code editor and a *Priority* number with a hook note.
- *Save changes* button, then a success message. Smart tags are supported.

### 3.5 Library
- Intro: "Tested snippets you can add in one click… nothing is downloaded."
- Toolbar:
  - Category filter buttons: All, Analytics & pixels, Performance, Security, Admin, Content & design, Login page, WooCommerce.
  - Search box.
- Card grid (minimum 300px wide). Each card has:
  - Title, type chip(s) and description.
  - Meta line: category · location · "waits for cookie consent".
  - Input fields where needed. These are monospace inputs with placeholders such as `G-XXXXXXXXXX`; values are validated on save.
  - Buttons *Add & activate* (primary) and *Add inactive*, plus "Creates 2 snippets" for multi-part templates.
- **Blocked card state:** "Requires WooCommerce", or the PHP permission reason.
- **Empty search state:** "No snippets match your search."
- **Result:**
  - Single snippet: redirect to its editor with a success message.
  - Multi-part: redirect to the list.
  - Invalid input: "Measurement ID is not in the expected format."

### 3.6 Tags
The WordPress core tag screen (add, edit and delete tags). A fixed WordPress layout; see §6.

### 3.7 Site Files
- Intro. A warning shows if WordPress is installed in a subfolder.
- Panels for **ads.txt**, **app-ads.txt**, **llms.txt** and **.well-known/security.txt**. Each has:
  - Name, plus a *View file* link when it has content.
  - Description, and a warning if a real file on the server overrides it.
  - Monospace textarea with a realistic example placeholder.
- **robots.txt** panel: *Add to the generated file* / *Replace it* radio, plus a textarea.
- *Save files* button.

### 3.8 Import & Export
Three panels in responsive columns:
1. **Move from another plugin**:
   - Detected sources with counts (WPCode, the WPCode/Insert Headers and Footers global header & footer, Code Snippets, Header Footer Code Manager, Simple Custom CSS and JS).
   - Each has a *Keep active snippets active* checkbox and an *Import* button.
   - A warning when another snippet plugin is still active ("code will run twice"). An empty state when nothing is found.
2. **Import a file**: .json file picker, *Keep snippets active…* checkbox, *Import file* button.
3. **Export**: *Download export file* button, plus a note that bulk export is also on the list and a CLI hint.

Results: "Imported N snippet(s); M active." A second message lists items needing attention, e.g. "SCSS was imported as plain CSS" or "latest posts targeting not supported, imported inactive".

### 3.9 Settings
**Left column** (settings form):
- **Error protection:** auto-deactivate a snippet that causes a fatal error; email the site admin (shows the admin email).
- **Page scripts:** content-type checkboxes (Posts, Pages, Products, custom types…).
- **Performance:** allow CSS/JS as cached files (with the file path); minify CSS.
- **Editor & admin:** code editor theme (Light/Dark); revisions to keep per snippet (0–200, default 20); admin bar inspector on/off.
- **Uninstall:** delete all data when the plugin is deleted (off by default).
- *Save Changes*.

**Right column** (info cards, 360px):
- **Safe mode:**
  - Explanation, and a read-only secret recovery URL with *Copy link*.
  - *Generate a new link* (the old one stops working).
  - The wp-config constant alternative.
- **Tamper protection:**
  - On/Off badge, explanation, and key source (WordPress keys, or a dedicated constant).
  - Number of snippets needing review with a *Review* link.
  - *Approve all flagged snippets*, with a confirmation dialog.
- **PHP snippets:** Available/Unavailable badge with the reason, plus the hardening constant.
- **About:** version, number of snippets running, "Free and open source (GPL). No tracking, no ads, no upsells."

### 3.10 Revisions
The WordPress core revisions screen: compare two versions with a slider and a diff, *Restore this revision*, and "Return to editor". A fixed WordPress layout; see §6.

### 3.11 Front-end admin bar inspector
- Top item **ScriptDock (N)** with a code icon, shown to admins on the front end only.
- Dropdown contents:
  - Each snippet running on the current page, as title plus "type · location", linking to its editor.
  - "No snippets on this page".
  - "This page has its own scripts", linking to the post editor.
  - *Add snippet*.
  - *Enter safe mode (this browser)*.
- **Safe mode state:** the item turns red ("ScriptDock: safe mode"), with *Exit safe mode*.

### 3.12 "ScriptDock Snippet" block (block editor)
- Block placeholder with an icon, the title (or the selected snippet's name) and instructions.
- A dropdown listing snippets whose location is "Shortcode or block only", shown as "Title (TYPE)".
- An *Edit snippet* / *Create a snippet* link that opens in a new tab.
- Empty state: "No active snippets are set to 'Shortcode or block only' yet."
- The snippet output renders only on the published page, not in the editor.

### 3.13 Global admin notices
- **Safe mode on:** warning with an *Exit safe mode* button, or text explaining the wp-config constant.
- **Snippet auto-deactivated after a fatal error:** error notice with the snippet link, message and line, plus *Fix it* | *Dismiss*.
- **Snippets need review:** warning with a count and a *Review snippets* link, on plugin screens.

### 3.14 Site Health
- **Status test**, with a "Security" badge. Three possible results:
  - "ScriptDock snippets are healthy" (good).
  - "Some snippets were changed outside ScriptDock" (critical).
  - "A snippet was switched off after an error" (recommended).
- **Info section:** version, snippets running, waiting for review, PHP enabled, tamper protection, safe mode forced, update source.

### 3.15 Email alert
- Subject: `[Site] Snippet "X" was deactivated`.
- Body: error, line, and a link to fix it. Plain text today; could be designed as an HTML email later.

### 3.16 WP-CLI (reference)
`wp scriptdock list | activate | deactivate | approve | export | import | run | rebuild | safe-mode-url`

---

## 4. Reference data (for realistic designs)

### 4.1 Code types (current colours; free to redesign)
| Type | Label | Current chip colours (text on background) |
|---|---|---|
| php | PHP | #4B3FB1 on #ECEBFB (purple) |
| html | HTML | #B3401C on #FDEEE6 (orange) |
| css | CSS | #1C5EA8 on #E6F1FB (blue) |
| js | JavaScript | #7A5C00 on #FDF7D8 (yellow) |
| universal | Universal (HTML + PHP) | #1B7A4A on #E7F6EE (green) |

### 4.2 Locations (33)
| Group | Location | Allowed types | Can load as file |
|---|---|---|---|
| Run PHP | Run everywhere | PHP | – |
| | Front end only | PHP | – |
| | Admin only | PHP | – |
| | Run on demand ("Run now" button) | PHP | – |
| Site-wide | Site header `<head>` | HTML, CSS, JS, Universal, PHP | ✓ |
| | After opening `<body>` | HTML, JS, Universal, PHP | – |
| | Site footer | HTML, CSS, JS, Universal, PHP | ✓ |
| Page content | Before post content | HTML, Universal, PHP | – |
| | After post content | HTML, Universal, PHP | – |
| | Before paragraph # | HTML, Universal, PHP | – |
| | After paragraph # | HTML, Universal, PHP | – |
| Archives | Before excerpt / After excerpt | HTML, Universal, PHP | – |
| | Between posts (every N; classic themes) | HTML, Universal, PHP | – |
| Admin & login | Admin header / Admin footer | HTML, CSS, JS, Universal, PHP | ✓ |
| | Login page header | HTML, CSS, JS, Universal, PHP | ✓ |
| | Login page footer | HTML, JS, Universal, PHP | ✓ |
| | Block editor | CSS, JS | ✓ |
| WooCommerce (only when active) | Before/after shop product list · Before/after single product · Before/after add-to-cart form · Before/after cart · Before/after checkout form · Order received (thank-you) page · My Account dashboard | HTML, JS, Universal, PHP | – |
| Other | Custom action hook (named) | all | – |
| | Shortcode or block only | all | – |

### 4.3 Targeting rules (current engine, 25 rules)
| Group | Rule | Operators | Value control |
|---|---|---|---|
| Page | Page type | is / is not | multi: Front page, Blog page, Any single post/page, Single post, Page, Attachment, Any archive, Category archive, Tag archive, Custom taxonomy archive, Author archive, Date archive, Post type archive, Search results, 404, Privacy page; with WooCommerce also Shop, Single product, Product category, Product tag, Cart, Checkout, Order received, My account |
| | Post type | is / is not | multi (Posts, Pages, Products, custom types) |
| | Specific post or page | is / is not | search (any content) |
| | Child of page | is / is not | search (hierarchical pages) |
| | Category, tag or term | is / is not | search (all public taxonomies) |
| | Page template | is / is not | multi (theme templates) |
| | Post author | is / is not | search (users) |
| | Custom field (post meta) | exists / does not exist / equals / does not equal / contains | key + value |
| Request | Page URL | is / is not / contains / does not contain / starts with / ends with / wildcard `*` / regex | patterns, one per line |
| | URL parameter | exists / does not exist / equals / does not equal / contains | key + value |
| | Referrer | contains / does not contain / is / starts with / regex / exists / does not exist | patterns (per-visitor ⚠) |
| | Cookie | exists / does not exist / equals / does not equal / contains | key + value (per-visitor ⚠) |
| User | Login status | is | Logged in / Logged out |
| | User role | is / is not | multi (roles) |
| | User meta | exists / … / contains | key + value |
| Device | Device | is | Desktop / Mobile or tablet (per-visitor ⚠) |
| | Browser | is / is not | Chrome, Safari, Firefox, Edge, Opera, Samsung Internet, IE, Other (⚠) |
| | Operating system | is / is not | Windows, macOS, iOS/iPadOS, Android, Linux, ChromeOS, Other (⚠) |
| Date & time | Day of the week | is / is not | Mon–Sun |
| | Time of day | is between / is not between | from–to |
| | Date | is before / is after | date-time |
| Site | Language | is / is not | installed languages (Polylang/WPML aware) |
| | PHP function (admins with PHP rights only) | returns true / returns false | function name |
| WooCommerce | Cart total | > / ≥ / < / ≤ | amount (⚠) |
| | Cart contains product | is / is not | search (products) (⚠) |

⚠ = varies per visitor, so it may not work with full-page caching. The UI shows this as a note.

**Logic:** groups are OR'ed; rules inside a group are AND'ed; the whole set can **Show** or **Hide** the snippet.

### 4.4 Loading options
- **Load strategies:** Normal · Defer · Async (JS files) · When idle · On first interaction.
- **Consent categories:** Statistics · Anonymous statistics · Marketing · Preferences · Functional.
- **Output:** Inline · Cached file.

### 4.5 Smart tags
Written as `{{tag}}`, optionally with a modifier (`|js` `|json` `|url` `|attr` `|html` `|raw`).
- **Site & page:** site_name, site_url, page_url, page_path, page_title, page_type, language.
- **Post:** post_id, post_title, post_type, post_excerpt, post_date, post_modified, post_author, post_categories, post_tags, `post_meta:KEY`, term_name.
- **User & time:** user_id, user_role, user_logged_in, date, time, year, timestamp, `attr:NAME` (shortcode attributes).
- **WooCommerce:**
  - Order: wc_order_id, _number, _total, _subtotal, _tax, _shipping, _currency, _email, _coupons, _payment_method, wc_order_items (JSON), wc_order_item_ids.
  - Product: wc_product_id, _name, _sku, _price.
  - Cart and store: wc_cart_total, wc_cart_count, wc_currency.

### 4.6 Library (47 templates, 7 categories)
- **Analytics & pixels (14):** Google Analytics 4, Google Tag Manager (2 snippets), Meta Pixel, Google Ads, Microsoft Clarity, Hotjar, TikTok Pixel, LinkedIn Insight, Pinterest Tag, Microsoft UET, Plausible, Fathom, Matomo (2 fields), Search Console verification.
- **WooCommerce (5):** GA4 purchase event, Meta purchase event, change "Add to cart" text, auto-complete virtual orders, disable cart fragments.
- **Performance (7):** disable emojis, disable oEmbed, remove jQuery Migrate, clean up `<head>`, disable self-pingbacks, slow Heartbeat, remove Dashicons for visitors.
- **Security (5):** disable XML-RPC, hide WP version, block user enumeration, generic login errors, disable application passwords.
- **Admin (5):** hide admin bar for non-admins, custom admin footer text, clean dashboard, disable update emails, duplicate posts/pages.
- **Content & design (9):** excerpt length (field), disable comments, "Last updated" date, external links in new tab, featured images in RSS, back-to-top button, reading progress bar, announcement bar, maintenance mode.
- **Login page (2):** custom login logo (URL field), logo links to site.

### 4.7 Settings & hardening constants
- **Settings:** auto-deactivate on fatal error; email alert; page-script content types; cached files; minify CSS; editor theme; revisions count; admin bar inspector; delete data on uninstall; safe mode key.
- **wp-config constants** (shown as help text):
  - `SCRIPTDOCK_SAFE_MODE`: stop all snippets.
  - `SCRIPTDOCK_DISABLE_PHP`: never run PHP snippets.
  - `SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT`: allow PHP snippets even when file editing is disabled.
  - `SCRIPTDOCK_SIGNING_KEY`: dedicated tamper-protection key.
  - `SCRIPTDOCK_DISABLE_TAMPER_PROTECTION`: turn tamper protection off.
  - `SCRIPTDOCK_UPDATE_REPO`: GitHub repository for updates.

### 4.8 Permissions (affects what is shown or disabled)
- Managing snippets requires an administrator with `unfiltered_html`. On multisite, only super admins qualify.
- PHP/Universal additionally require file-editing rights. If missing, those types and the PHP function rule are disabled and the reason is shown.
- The "ScriptDock Snippet" block can be placed by any editor, but only lists snippets set to "Shortcode or block only".

---

## 5. States & edge cases to design

**Empty states:**
- No snippets.
- No search results.
- No tags.
- Library search with no match.
- No importable data.
- No active snippets for the page "switch off" list.
- Block with no eligible snippets.

**Loading and busy states:**
- Status switch saving.
- Save button "Saving…".
- Live lint running.
- Search results loading.
- Import in progress.

**Success, error and warning messages:** every save outcome in §3.2, library add, import results, settings saved, file saved.

**Snippet conditions:**
- Active / inactive.
- Needs review (tampered).
- Last error.
- Test mode.
- Scheduled / expired.
- Conditional.
- Waits for consent.
- Loaded as file.
- PHP not permitted (read-only).

**Site conditions:**
- Safe mode on for this browser.
- Safe mode forced for the whole site.
- Tamper protection off.
- WooCommerce inactive: WooCommerce locations, rules and templates are hidden or blocked.
- No consent plugin installed.
- WordPress installed in a subfolder.
- A real file overrides a virtual file.

**Content extremes:**
- Very long snippet titles.
- Very long code (e.g. 2,000 lines).
- 200+ snippets.
- Sites with 10,000+ pages (the targeting picker must be search-first).

**Localisation and layout:** right-to-left languages, long translated strings, narrow screens.

---

## 6. WordPress design constraints: what's fixed and what's free

### 6.1 Short answer
**Inside our own screens we can design anything.** Custom layouts, a branded header, cards, wizards, drawers, modals, dark code editor, custom fonts and icons: it is ordinary HTML/CSS/JS running in the admin. Because the plugin is **self-hosted, the WordPress.org review guidelines do not restrict the design either.**

What we **cannot** change is the WordPress frame around our screens, a few core screens we reuse, and the look of surfaces owned by the block editor. There are also technical rules (accessibility, right-to-left languages, colour schemes, responsiveness) that a good design must respect.

### 6.2 The fixed frame around every screen (measured on WordPress 7.1)
| Element | Value | Can we change it? |
|---|---|---|
| Left sidebar menu | 160px wide. Folds to a 36px icon rail below 960px; hidden behind a menu button below 782px. Default background #1E1E1E. | No. We control only our menu **icon** (20×20 single-colour SVG, recoloured by WordPress) and **text labels**. A count bubble is allowed (e.g. "Needs review 2"). |
| Top admin toolbar | 32px tall (46px below 782px), #1E1E1E, always on top. | No, except adding our own menu item (the front-end inspector). |
| Content area | Starts right of the sidebar with 20px left padding; background #F0F0F0. | Inside it, yes: full freedom. |
| Admin notices | WordPress and **other plugins** print notices at the top of every screen, including ours (e.g. "WordPress 7.1.1 is available", other plugins' messages). WordPress moves them below the page heading. | We can't stop other plugins' notices. **Reserve a notice zone below our page header.** We fully design our own notices, banners and toasts. |
| Browser tab title | "Page name ‹ Site name — WordPress" | Only the page-name part. |
| Footer | "Thank you for creating with WordPress" and the version, bottom of the content area. | Leave it. |

**User-selected colour schemes:** each user picks one of 9 admin colour schemes, which recolour the sidebar and toolbar. The default in WordPress 7.x is **"Modern": dark chrome, accent #3858E9.** The others are Default, Light, Blue, Coffee, Ectoplasm, Midnight, Ocean and Sunrise. Our brand colours must look good next to all of them, and the "Light" scheme makes the sidebar light.

**Native WordPress 7.x look** (useful only if we want to feel native or reuse core controls):
- System font stack, base 13px / 1.4 line height, page title 23px regular.
- Buttons and inputs 40px tall, 2px corners, 1px #949494 input borders.
- Primary button #3858E9 in the Modern scheme; focus ring 1.5px in the accent colour.

### 6.3 Screens and surfaces WordPress owns
| Surface | What's fixed | Options |
|---|---|---|
| **Snippet list** (currently WordPress's standard list table) | Views row, bulk-actions dropdown, filters, search box, pagination, sortable headers, hover row actions, "Screen Options/Help" tabs, mobile row-collapse. Columns and cell contents are ours. | **A.** Keep it and style the cells (native, less work). **B.** Replace it with a fully custom list page (cards/table, our own filters, bulk bar, pagination): **full design freedom**. Recommended for a premium feel. |
| **Tags** screen | Fixed WordPress layout. | Replace with inline tag management (chips with create/rename in the editor and a filter), or keep it. |
| **Revisions** screen | Fixed WordPress compare slider and diff. | Keep, or design our own "History" drawer with a side-by-side diff and restore. |
| **Post editor, page scripts** | Today a "meta box" in the block editor's bottom panel: postbox style, limited height and width. | **A.** Keep a meta box and style its insides. **B.** Premium option: a **ScriptDock button in the editor's top bar** that opens a **sidebar panel** (WordPress sidebars are ~280px and use Gutenberg's component style) with a summary and toggles, plus **"Edit page code" opening a large modal** with tabs and a full code editor. Inside the block editor, designs should follow Gutenberg's component language (grey borders, 2px radius, accent colour, 13px). Our own modal content can be more branded. |
| **ScriptDock Snippet block** | Block toolbar and settings sidebar belong to Gutenberg. | We design the placeholder card inside the content area (keep it Gutenberg-friendly). |
| **Front-end admin bar menu** | Dark dropdown with text rows (~26px), limited styling. | Icon, labels, counts and a coloured state (e.g. red in safe mode). Richer UI would need our own popover, which is possible but should stay small. |
| **Plugins list row, update "View details" popup, Site Health** | Fixed WordPress UI. | We provide text and links only. |
| **Admin email** | Plain text today. | Can be designed as an HTML email if wanted. |

### 6.4 Rules a good design must respect
1. **Responsive:**
   - Must work from 320px to ultrawide.
   - Key widths: 1440, 1280, 1024, **782** (the WordPress mobile breakpoint: bigger touch targets, sidebar hidden) and 390.
   - The content area loses 160px (or 36px when folded) to the sidebar.
2. **Accessibility, WCAG 2.2 AA:**
   - Contrast ≥ 4.5:1 for text; visible focus.
   - Full keyboard use.
   - Proper roles for custom widgets: tabs, switches, listbox or combobox for search, dialog, stepper, checkbox cards.
   - Never rely on colour alone: type chips keep their text labels.
   - Respect reduced motion.
3. **Right-to-left languages:** WordPress flips the admin for Arabic, Hebrew and similar languages. Our layouts must mirror, including directional icons.
4. **Translation:** every string is translatable. Allow ~30–40% text growth; no text inside images. Dates and times use the site's format and timezone.
5. **No remote assets:**
   - Bundle fonts and icons with the plugin; no Google Fonts or icon CDNs, for privacy (GDPR), speed and offline use.
   - WordPress's own Dashicons icon font (~340 icons) is available, but a **custom SVG icon set** is recommended for a premium look.
6. **Fonts:** either the native system font (feels built-in, zero cost) or **one bundled variable font** (e.g. ~100 KB, like Inter) scoped to our screens. Monospace for code: a bundled coding font (e.g. JetBrains Mono) or the system monospace.
7. **Scoped styling:** all our styles live under one root class so we never restyle WordPress or other plugins.
8. **Layering:** the admin toolbar sits very high (z-index 99999). Full-screen modals, the code editor full-screen mode and wizards may overlay the whole admin (WordPress's own Site Editor does this).
9. **Code editor:** CodeMirror 5, bundled with WordPress. We can design everything around it (tabs, toolbar, status bar, gutter, colours per token type: keyword, string, comment, tag, attribute, number, variable, property, operator, error). Monaco (the VS Code editor) is possible but adds ~3 MB; not recommended by default.
10. **Dark mode:** WordPress has no admin dark mode, and the sidebar follows the user's scheme. We can offer our own dark theme inside our screens. Recommended: **light UI with an optional dark code editor**, with a full dark UI later.
11. **Implementation reality:**
    - WordPress ships React and its component library (`@wordpress/components`), so rich interactive designs (wizard, card grids, drag and drop, virtualized lists) are feasible without extra frameworks.
    - Very large custom illustration sets or heavy animations should be avoided for admin performance; SVG illustrations are fine.

### 6.5 What we can freely brand
- A **branded page header** on all our screens: logo mark, product name, horizontal navigation tabs (e.g. Snippets, Header & Footer, Library, Site Files, Import/Export, Settings), and a global "Add snippet" button, a search/command bar and a safe-mode status pill.
- Custom colours, typography, iconography, illustrations, empty states, onboarding, toasts, modals, drawers, wizards and dashboards.
- The code editor theme (light and dark).
- The front-end admin bar entry icon and safe-mode colour.
- Inside the block editor: our modal and the insides of our sidebar panel. Follow Gutenberg's look for anything that sits in WordPress's own sidebar.

---

## 7. New UX: "Where should it run?" — multi-step targeting

### 7.1 Goals
- Replace the dropdown-plus-rule-builder with a **guided, visual, multi-step flow** that anyone can use. Keep an **advanced rule builder** for power users.
- Let users **see and pick actual content**: tabs for Pages, Posts, Products, Categories and so on, with **selectable cards**, **"Select all"** per type, search and filters.
- Make the result readable as **one plain-language sentence**.
- Feel **premium and branded**: polished cards, illustrations for placements, smooth transitions, clear progress.

### 7.2 Entry point
In the snippet editor, the "Where should it run?" panel becomes a **Targeting summary card**:

> **Runs in:** Site header · **On:** 3 pages + all posts in "News" · **For:** logged-out visitors on mobile · **When:** Mon–Fri, 09:00–17:00
> [Edit targeting]

"Edit targeting" opens the wizard as a **full-screen modal** (or a wide right-side drawer). A **stepper** shows the progress: *1 Placement → 2 Pages & content → 3 Audience → 4 Schedule → 5 Review*. Steps 3 and 4 are optional and can be skipped. Users can jump between completed steps.

### 7.3 Step 1: Placement ("Where on the page?")
- **Visual cards with mini wireframes** that highlight the injection spot:
  - Page layout: Header · After opening body · Footer.
  - Inside content: Before content · After content · After paragraph N (inline number stepper) · Before paragraph N.
  - Lists & archives: Between posts (every N) · Before/after excerpt.
  - Admin & login: Admin header/footer · Login header/footer · Block editor.
  - WooCommerce (only when active): 12 cards with small commerce icons (shop list, product, add to cart, cart, checkout, thank-you, account).
  - Advanced: Custom hook (hook name field with suggestions) · Shortcode/Block only (shows the shortcode to copy).
- **For PHP snippets**, the first group is **"When to run"**: Everywhere · Front end · Admin · On demand.
- Cards that don't fit the chosen code type are hidden, or disabled with a reason.
- An **Advanced** disclosure holds Priority, plus the loading strategy and consent for JS/HTML if we move those here.

### 7.4 Step 2: Pages & content ("On which pages?")
**Scope choice** first, as three large option cards:
1. **Entire site**: the default; no rules.
2. **Only on selected content**.
3. **Everywhere except selected content**.

When 2 or 3 is chosen, a **tabbed content picker** appears:

| Tab | Shows | Card content |
|---|---|---|
| **Pages** | All pages, hierarchical | Thumbnail (featured image or generated placeholder), title, path `/about/`, parent breadcrumb, status badge (Draft/Private), date; option "Include child pages" |
| **Posts** | All posts | Thumbnail, title, categories, date, author |
| **Products** (WooCommerce) | Products | Image, name, price, SKU |
| **Each custom post type** (e.g. Courses, Events) | Items | As for posts |
| **Categories & tags** | Term trees per taxonomy | Term name, post count, hierarchy; choose "posts in these terms", "the archive page", or both |
| **Special pages** | Front page, Blog page, Search results, 404, All archives, Author archives, Date archives; WooCommerce: Shop, Cart, Checkout, Thank-you, My account | Icon cards |
| **URL rules** | Custom patterns | Rows of *contains / starts with / exactly / wildcard / regex* + value, and a **"Test a URL"** field that shows ✓ match or ✗ no match live |

**Inside each content tab:**
- **"Select all Pages (42)"** toggle. This means all current and future items of that type. When on, the individual cards show as selected and locked, with a way to exclude specific items.
- Search: instant, by title, slug or ID.
- Filters: status, parent, category, author, date.
- Sort, and a **Grid/List view toggle** (compact list for large sites).
- Cards with a clear selected state (check badge, accent border); click or Space to toggle; shift-click for ranges.
- Paging or infinite scroll with lazy loading. Must stay fast with 10,000+ items.
- Tab labels show selection counts: "Pages · 3", "Posts · All".

**Selection tray** (sticky at the bottom or side):
- Chips grouped by type ("3 pages", "All posts", "2 categories", "1 URL rule"), each expandable and removable.
- *Clear all*.
- A live **match estimate** where possible ("Matches about 57 pages").

### 7.5 Step 3: Audience ("Who sees it?"), optional
Collapsible sections, each with a clear default of "Everyone":
- **Visitors:** Everyone / Logged in / Logged out (segmented), plus user role chips.
- **Devices:** cards for Desktop and Mobile/tablet with icons. Browser and OS chips sit in "More".
- **Language:** only on multilingual sites.
- **Traffic source:** referrer contains…, URL parameter (e.g. `utm_source = google`), cookie.
- **WooCommerce:** cart total (min/max) and "cart contains" product picker.
- **Advanced:** custom field, user meta, and PHP function (PHP-capable admins only).
- Per-visitor options carry a small **"may not work with page caching"** badge and tooltip.

### 7.6 Step 4: Schedule ("When?"), optional
- **Always**, or a **date range** (start/end with calendar and time).
- **Days of week** as toggle chips Mon–Sun.
- **Time window** from–to.
- A timezone note, plus a preview such as "Starts Oct 1, 09:00 · Ends Oct 31".

### 7.7 Step 5: Review
- A plain-language sentence, e.g. *"Runs in the **site header** on **3 pages** and **all posts in News**, for **logged-out visitors on mobile**, **Monday–Friday 09:00–17:00**, waiting for **marketing consent**."*
- A summary card per step with *Edit*.
- Warnings where relevant:
  - Page caching and per-visitor rules.
  - PHP "run everywhere" combined with page-based rules: it will run after the page is known.
  - WooCommerce block checkout not firing the classic hooks.
  - No consent plugin detected.
- Primary **Save targeting**.

### 7.8 Advanced mode
- A switch **"Advanced rules"** shows the full AND/OR rule builder from §3.2, redesigned in the same visual language.
- If rules were built in advanced mode and cannot be shown in the wizard, the summary card says **"Custom rules"** and opens advanced mode.

### 7.9 How it maps to the engine (for reference; I'll build this)
| Wizard input | Rules underneath |
|---|---|
| Placement | location + location args |
| Selected pages/posts/items | `post` is [IDs] |
| Include child pages | `post_parent` is [IDs] |
| Select all of a type | `post_type` is [type] |
| Terms | `taxonomy_term` is [IDs] |
| Special pages | `page_type` is [values] |
| URL rules | `url` rules |
| "Except" mode | the same rules negated |
| Audience & schedule | extra AND rules; date range uses the snippet schedule |

The content selections are OR'ed with each other. Audience and schedule apply to all of them.

### 7.10 Other places to reuse the picker and cards
- **Page scripts › Site-wide snippets tab:** snippet cards with toggles instead of a checklist.
- **Library › Add template:** optional "Where should it run?" step after entering the tracking ID.
- **Snippet list filter:** "Runs on this page" search.

### 7.11 Backend work this requires (my side, after design)
- Paginated content endpoints with thumbnails, counts per type and search.
- Term trees with counts, special-page availability, a URL test endpoint and the match estimate.
- Wizard ↔ rules conversion.
- The new React-based UI.

---

## 8. Proposed new screens (not built yet; optional for the premium redesign)
1. **Overview dashboard** (landing page):
   - Health tiles: running snippets, errors, needs review, safe mode.
   - Recent activity and quick actions (add snippet, open library, header & footer).
   - Getting-started checklist.
2. **First-run onboarding:**
   - Save your safe mode link.
   - Import from another plugin (auto-detected).
   - Add your first snippet or pick from the library.
3. **Snippet quick-view drawer** from the list: code preview, targeting summary, status, last error, actions.
4. **History drawer:** revisions with a side-by-side diff, instead of WordPress's revisions screen.
5. **Command palette** (Ctrl/⌘+K): jump to snippets, pages and actions.
6. **Toast system** for quick feedback (status toggles, copy, saved).
7. **Confirm dialogs:** move to trash, approve all flagged snippets, generate a new safe-mode link, keep snippets active on import.
8. **Safe mode status pill** in the branded header, with a one-click exit.

---

## 9. Component inventory for the design system

**Foundations:**
- Colour: brand, neutrals, semantic success/warning/danger/info, type colours ×5.
- Typography scale, spacing scale, radius, shadows/elevation, focus ring, motion.
- Icon set: types, placements, content types, devices, states, actions.

**Navigation:**
- Branded page header (logo, title, nav tabs, primary action, safe-mode pill).
- Tabs (page, content-picker and code tabs).
- Stepper (wizard), breadcrumbs, pagination.

**Actions:** buttons (primary, secondary, tertiary/ghost, danger, icon-only, sizes, loading), split button (Save ▾), link, copy-to-clipboard chip.

**Inputs:**
- Text, textarea, number stepper, select, searchable select/combobox.
- Checkbox, radio, radio cards, switch.
- Segmented control, chip/token input.
- Date-time picker, time range, day-of-week chips, file upload/dropzone, key–value pair.

**Selection:** selectable content card (grid and list variants: thumbnail, title, path, status, selected, disabled), placement card with mini wireframe, "select all" header, selection tray.

**Data display:**
- Snippet table/list row (status switch, title, type chip, location, badges, priority, tags, modified, row actions) and a bulk-action bar.
- Type chip ×5, status badges ×7, tag chip, count bubble, key–value summary, plain-language targeting sentence, stat tile.

**Code:** code editor frame (tabs, toolbar, language label, lint status, full-screen, gutter, error line), dark and light syntax themes, smart-tag inserter, read-only code preview.

**Rule builder:** rule row, group container (OR/AND separators), operator select, value controls.

**Feedback:**
- Inline banner (info, success, warning, danger, with action).
- Toast, admin-notice restyle (ours only), confirm dialog.
- Empty states (with illustration), skeleton loaders, progress, tooltip, popover, field validation.

**Overlays:** modal (standard and full-screen wizard), drawer, dropdown menu.

**Surfaces:** panel/card, settings row, info card (sidebar), library template card.

**Editor-integrated:** page-scripts panel (meta box and/or Gutenberg sidebar plus modal), block placeholder card, front-end admin bar menu.

---

## 10. Decisions (confirmed by the product owner)
1. **Name:** **ScriptDock** is the final name.
2. **Brand:** based on the reference image (`brand-reference.webp`): white surfaces, near-black type, ember-orange gradient accent, soft peach glows, pill buttons, big rounded cards, thin-line illustrations with an orange dot. Use the palette in `02-brand-guidelines.md`, kept compatible with WordPress, premium and branded.
3. **Snippet list:** **fully custom** list page, replacing WordPress's list table.
4. **Page scripts in the block editor:** **a ScriptDock button in the editor's top bar**, a sidebar summary panel and a **full-screen branded editor modal**. The bottom meta box stays only as the classic-editor fallback.
5. **Visual base:** a **distinct branded system** (bundled Plus Jakarta Sans and JetBrains Mono, our own components) that keeps WordPress's 40px control height and accessibility rules.
6. **Dark mode:** **code editor only** for v1. Full dark UI later, so everything must be tokenised.
7. **Scope:** **design the entire product**: every screen, state and component in this brief, including the new screens in §8.
