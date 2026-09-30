# ScriptDock: screens, layouts, states & flows to design

This is the design brief for **every screen** of the redesigned product.
- Feature details, data and rules: `01-features-and-constraints.md`.
- Look and feel: `02-brand-guidelines.md`.
- Component specs: `04-design-system.md`.
- Realistic content for mockups: `05-sample-data.md`.

**Artboard sizes:** design at **1440 × 900** (the WordPress admin frame included) unless noted. Key screens are also needed at **1024** (tablet) and **390** (mobile) (see §4).

---

## 0. The app shell (appears on every ScriptDock screen)

### 0.1 WordPress frame (render it realistically; we don't design it)
- **Toolbar:** 32px, #1E1E1E. Left: WordPress logo, site name "Lumen Coffee Roasters", updates icon, comments, "+ New". Right: "Howdy, Maya" and an avatar.
- **Sidebar:** 160px, #1E1E1E, white text. Items: Dashboard, Posts, Media, Pages, Comments, WooCommerce, Products, Appearance, Plugins, Users, Tools, Settings, **ScriptDock**.
  - The ScriptDock item is active: WordPress highlights it in **#3858E9**, and its submenu is shown underneath.
  - Submenu: Overview, All Snippets (count bubble e.g. "2"), Add Snippet, Header & Footer, Library, Site Files, Import & Export, Settings.
- **Content area:** starts 160px from the left. We paint it gray-50 (#F9F8F6).

### 0.2 ScriptDock top bar (ours)
A **floating white pill** (16px from the top and sides of the content area, 64px tall, radius 999px, `shadow-md`), echoing the reference navigation:
- **Left:** logo mark (orange gradient dot) and the "scriptdock" wordmark.
- **Centre:** section tabs: **Overview · Snippets · Header & Footer · Library · Site Files · Import & Export · Settings**. The active tab is ink bold with a small ember dot under it.
- **Right:**
  - Search button with a "⌘K" hint (opens the command palette).
  - **Safe-mode pill**: hidden normally; when on, a red pill "Safe mode on · Exit".
  - **New snippet** primary button: ink pill, white text, orange circle with "+".
- **Responsive:** ≤1280 the tabs collapse into "More ▾"; ≤782 the bar becomes logo + menu button + "New" icon button, and the menu opens a sheet.

### 0.3 Notice zone
Directly under the top bar. WordPress core and other plugins print their notices here, and our banners appear here too. Show one example WordPress notice ("WordPress 7.1.1 is available! Please update now.") in at least one mockup, to prove the layout copes.

### 0.4 Page header
- **H1** (28px/800), with an optional one-line description in gray-700 and optional count chips.
- Page actions on the right.
- Some pages add a subtle **peach glow** at the top-right (Overview, Library, empty states).

---

## 1. Screens

### S01 · Overview (new landing page)
**Purpose:** status at a glance and quick starts.

**Layout (1440):**
1. **Hero row:**
   - "Good morning, Maya", with the one-line status "Everything is running smoothly" or "2 things need your attention".
   - An ember-gradient accent word in the headline, used once, e.g. "Your site's code, **in control**."
   - Peach glow on the right.
2. **Health banner** (only when needed): cards for "1 snippet needs review" (danger), "1 snippet was switched off after an error" (danger) and "Safe mode is on" (warning), each with a CTA.
3. **Stat tiles** (4): Running (with the pulsing ember dot), Inactive, Errors, Page scripts (number of pages with their own code). Each tile is clickable and filters the list.
4. **Two columns:**
   - **Left:** "Recently edited" (5 rows: type chip, title, where it runs, status switch, time) and "Recent problems" (errors or review items with a "Fix" CTA).
   - **Right:**
     - "Quick actions" (New snippet, Browse library, Header & Footer, Import).
     - "Getting started" checklist with a progress ring: Save your safe mode link ✓, Add your first snippet ✓, Try the library, Choose page-script content types.
     - "Speed tip" card ("Delay chat widgets until the visitor interacts, pages load faster").
5. **Library highlights:** 3 template cards.

**States:** new site (empty: onboarding CTA plus library highlights), healthy, issues present, safe mode on.

### S02 · Onboarding (first run, new)
A full-screen overlay above the WordPress chrome, white with a large peach glow and thin-line illustrations with the orange dot. It has 4 steps with a dot stepper and can be skipped.
1. **Welcome:** "Add code anywhere. Safely." Three value props with icons: *Crash-proof* (auto-deactivates broken code), *Fast* (smart loading, cached files), *Free* (every feature, no upsells). Primary "Get started".
2. **Save your safe mode link:** explanation, a read-only link field with **Copy**, and "Email it to me". Checkbox "I saved it" enables Next.
3. **Bring your snippets:** auto-detected plugins (e.g. "WPCode · 12 snippets", "Header Footer Code Manager · 3 snippets"). Each has a toggle (default on) and "Keep active" (default off). There is a notice about deactivating the old plugin, and "Skip" if nothing is detected.
4. **You're ready:** three big choice cards (Add a snippet · Browse the library · Go to Overview), plus a mini tip about ⌘K.

### S03 · Snippets (custom list)
**Header:** H1 "Snippets" with a count chip ("24"); actions *Import* (secondary) and *New snippet* (primary).

**Status tabs (pills):** All 24 · Active 18 · Inactive 6 · **Needs review 1** (danger dot) · **Errors 1** · Trash 3.

**Toolbar:**
- Search: title, code, notes, tags; placeholder "Search snippets, code or tags…".
- Filter dropdowns with multi-select chips: Type, Location, Tags, Targeting (Entire site / Conditional / Scheduled).
- Sort: Last modified · Title · Priority · Type.
- Density toggle: comfortable/compact.

**Table (comfortable rows, 64px):**

| Column | Content |
|---|---|
| ☐ | Row select |
| Status | Brand toggle (ink track + orange knob). Disabled with a lock and tooltip when the user lacks PHP rights. |
| Snippet | **Title** (bold); second line with notes excerpt (gray-600, 1 line) and tag chips |
| Type | Type chip with icon |
| Where it runs | Placement ("Site header") plus a targeting summary ("3 pages · Mobile") |
| Status badges | Needs review, Error, Test mode, Scheduled, Expired, Consent, Cached file |
| Priority | Number |
| Updated | "5 min ago" (tooltip: date and author) |
| ⋯ | Menu: Edit, Duplicate, Export, History, Copy shortcode (when applicable), Move to trash |

- **Row hover:** background gray-100; the whole row is clickable to the editor.
- **Bulk action bar:** sticky at the bottom centre, a dark ink pill: "3 selected · Activate · Deactivate · Add tag · Export · Move to trash · ×".
- **Pagination:** "1–20 of 24", with 20/50/100 per page.

**Views and states:**
- Empty (no snippets): big illustration, "Add code to your site in seconds", and CTAs New snippet / Browse library / Import from another plugin.
- No results: "No snippets match", with Clear filters.
- Needs-review view: an explanatory banner ("These snippets changed outside ScriptDock, possibly by malware or a migration. Review before approving.").
- Errors view.
- Trash view: Restore, Delete permanently, Empty trash (with confirm).
- Loading: skeleton rows.
- Toggle busy, and a toggle error toast.

**Quick view (S03b):** clicking the type chip or "Preview" opens a **right drawer** (560px) with:
- Read-only code preview.
- "Where it runs" summary, status, last error, history link.
- Actions: Open editor, Duplicate, Export, Trash.

**Mobile (390):** rows become cards (title, type chip, switch, where it runs, badges); filters live in a bottom sheet.

### S04 · Snippet editor (new and edit)
**Sticky editor bar** (below the top bar, white, full width of the content area):
- Left: breadcrumb "Snippets / " and the **inline-editable title** (20px/700; placeholder "Untitled snippet").
- Right:
  - Unsaved-changes dot.
  - **Active** switch with label.
  - **Save** split button (ink); the dropdown holds Save & deactivate and Save as copy.
  - ⋯ menu: Duplicate, Export, History, Copy shortcode, Move to trash.

**Banners** (below the bar, as needed):
- Tampered, with **Approve** and **View history**.
- Last error, with **Jump to line 12**.
- PHP not permitted (read-only mode; every control disabled).
- Safe mode on ("Snippets are paused in your browser").

**Main column (left, fluid):**
1. **Code type selector:** 5 pill segments, each with a type icon; the selected one gets a type-colour outline and fill. A hint line below explains the type.
2. **Code card:**
   - **Toolbar:** type icon and virtual file name (e.g. `ga4-tag.html`); live status ("✓ No syntax errors" or a red "Line 3 · unexpected '}'"); buttons *Smart tags* (HTML/JS), *Theme* (light/dark), *Full screen*.
   - **Editor:** 480px tall by default, resizable. The error line has a red wash, with an inline gutter marker and tooltip.
   - **Status bar:** Ln 12, Col 4 · 128 lines · encoding · "⌘S to save".
3. **"Where it runs" summary card** (key new element):
   - Rows with icons:
     - **Placement:** "Site header"
     - **Pages:** "3 pages, all posts in News"
     - **Audience:** "Logged-out visitors on mobile"
     - **Schedule:** "Mon–Fri 09:00–17:00 until Oct 31"
   - The plain-language sentence on top.
   - **Edit targeting** button (opens S05).
   - Placement-specific quick info, such as the shortcode chip for "Shortcode or block only" and the hook name for custom hooks.
4. **Loading card** (HTML/CSS/JS):
   - Output: segmented Inline | Cached file (disabled with a reason when the placement cannot use files).
   - Load strategy: 5 radio cards with small icons (Normal, Defer, Async, When idle, On interaction) and a one-line explanation plus a "faster" badge.
   - Cookie consent: select (Not required, Statistics, Anonymous statistics, Marketing, Preferences, Functional) and a note on whether a consent plugin was detected.

**Sidebar (right, 340px):**
1. **Details:** Notes (textarea), Tags (chip input with autocomplete; creates new tags inline), Priority (stepper with "lower runs first").
2. **Safety:**
   - Test mode switch ("Only admins see it").
   - Signature status: "Signed ✓" (success) or "Needs review" (danger).
   - Last error summary.
3. **Run now** (on-demand PHP only): "Run now" button; the output appears in a dark console drawer from the bottom.
4. **History:** "12 revisions · Last saved 3 min ago by Maya" and *View history* (opens S06).
5. **Options:** "Replace smart tags" (HTML/JS), "Run shortcodes in this HTML" (HTML).

**Save feedback:**
- Success toast ("Snippet saved and active").
- Error toasts or banners for: syntax error (inactive), fatal during the automatic test (inactive, with the error banner and "Jump to line"), and missing hook name.
- A confirmation dialog when leaving with unsaved changes.

**Full-screen code mode:** the code card fills the viewport over the WordPress chrome, with a minimal top bar (title, lint status, smart tags, Save, Exit ⎋).

**States to show:**
- New HTML (empty, with example placeholder).
- PHP with a lint error.
- PHP after a fatal test run.
- Tampered (needs review).
- Read-only (no PHP rights).
- Shortcode placement.
- On-demand PHP with Run output.
- Dark editor theme.

**Tablet:** the sidebar moves below the main column. **Mobile:** single column; the code editor stays at full width; targeting opens full screen.

### S05 · Targeting wizard ("Where should it run?")
**Container:** full-screen overlay above the WordPress chrome.
- **Top:** title "Where should **GA4 tag** run?", the **stepper**, an "Advanced rules" toggle, and close ×.
- **Main:** the step content (left, ~64%).
- **Summary rail** (right, ~36%, sticky): the live plain-language sentence, the selection tray and warnings.
- **Bottom bar:** Back · (step x of 5) · **Next** / **Save targeting**.

**Step 1 · Placement** (`01 §7.3`)
- Grouped **placement cards** (4 per row). Each shows a **mini page wireframe with the orange dot where the code goes**, a title and a one-line description.
- Groups: Page layout, Inside content, Lists & archives, Admin & login, WooCommerce (when active), Advanced.
- PHP snippets start with "When to run" cards: Everywhere, Front end only, Admin only, On demand.
- **Selected card:** ember ring, ember-50 fill and a check badge. Inline extras appear inside the selected card: paragraph-number stepper, "every N posts", hook-name input with suggestions, shortcode chip with copy.
- Cards that are incompatible with the code type are dimmed, with the reason on hover ("CSS can't run between posts").

**Step 2 · Pages & content** (`01 §7.4`)
- **Scope cards** (3, horizontal): **Entire site** (default) · **Only on selected content** · **Everywhere except selected content**. Each has a small illustration.
- **Content picker** (shown for the second and third scopes):
  - **Tabs:** Pages · Posts · Products · Courses (example custom post type) · Categories & tags · Special pages · URL rules. Each tab carries a count badge of selected items.
  - **Tab header:**
    - A **"Select all pages (42)"** toggle card; when on, cards show as included, with "Exclude some…".
    - Search, filters (Status, Author, Parent/Category, Date), sort, and a Grid/List toggle.
  - **Content cards** (grid, 4 per row):
    - A 16:10 thumbnail (featured image, or a generated placeholder: soft gradient plus type icon).
    - Title (2 lines max), path `/about/our-story/`, a status chip (Draft/Private/Scheduled) and a date.
    - A check circle in the top-right; the selected state matches the placement cards.
    - For pages with children: a "Include 3 child pages" switch on the selected card.
    - A parent breadcrumb above the title for child pages.
  - **List view:** compact rows with a checkbox, title, path, status and date. Best for big sites.
  - "Showing 24 of 312 · Load more", with skeleton cards while loading.
  - **Categories & tags tab:**
    - Taxonomy switcher (Categories | Tags | Product categories).
    - A tree with checkboxes and post counts.
    - "Apply to": Posts in these terms · Archive pages · Both.
  - **Special pages tab:** icon cards for Home page, Blog page, Search results, 404, All archives, Author archives, Date archives, Shop, Cart, Checkout, Order received, My account.
  - **URL rules tab:**
    - Rule rows: match type (Contains, Starts with, Is exactly, Wildcard, Regex) and a value.
    - "+ Add rule".
    - A **"Test a URL"** input that shows ✓ Matches / ✗ No match live.
  - **Selection tray** (in the summary rail):
    - Grouped chips ("Pages · 3", "All posts", "Categories · 2", "URL rules · 1"). Each expands to show items, each removable.
    - "Clear all".
    - Match estimate "≈ 57 pages".

**Step 3 · Audience** (optional; `01 §7.5`)
- Section cards with a default of "Everyone":
  - **Visitors:** a segmented control plus role chips.
  - **Devices:** two big cards, Desktop and Mobile & tablet, with device illustrations. A "More: browsers, operating systems" disclosure opens chip groups.
  - **Language:** only when multilingual.
  - **Traffic source:** referrer, URL parameter, cookie.
  - **WooCommerce:** cart total range slider, "cart contains" product search.
  - **Advanced:** custom field, user meta, PHP function. The PHP function shows a lock when not permitted.
- Per-visitor options show an info chip: "May not work with full-page caching".

**Step 4 · Schedule** (optional)
- "Always" vs "Custom schedule" cards.
- Date range with calendars and times.
- Weekday chips.
- Time window.
- Timezone note, and a preview line ("Starts Oct 1 at 09:00 · Ends Oct 31").

**Step 5 · Review**
- A large sentence with highlighted inline chips, e.g. "Runs in the **site header** on **3 pages** and **all posts in News**, for **logged-out** visitors on **mobile**, **Mon–Fri 09:00–17:00**."
- Per-step summary cards with "Edit".
- A warnings list (warning style).
- **Save targeting**.

**Advanced rules (S05f):**
- A rule-builder view in the same visual language.
- A **Show/Hide** segmented control.
- **Group cards** separated by an "OR" pill; rule rows inside, joined by an "AND" label. Each row has a rule select (grouped), an operator select, a value control (chips / search chips / textarea / key-value / time range / date / number) and a remove button.
- "+ Add rule" and "+ Add group".
- Shows "Custom rules" on the summary card when these rules cannot be shown in the wizard.

**Keyboard:** arrow keys move between cards, Space selects, Enter continues, Esc closes (with a confirmation if there are changes).

**Mobile:** the steps become full-screen pages; the summary rail becomes a collapsible bottom sheet ("3 pages selected ▴").

### S06 · History drawer (new, replaces WordPress's revisions screen)
Right drawer, 720px:
- **Left:** timeline of revisions (avatar, "Maya · 3 min ago", a "Current" tag, an "Approved after review" tag).
- **Right:** a **side-by-side diff** (added lines green-tinted, removed red-tinted) with an Inline/Split toggle.
- Actions: *Restore this version* (confirm) and *Copy code*.

### S07 · Smart tags palette
A popover (or side sheet) from the editor toolbar:
- Search box.
- Groups: Site & page, Post, User & time, WooCommerce.
- Each tag row: `{{tag}}` in mono, a description, and an "Insert" affordance.
- A footer explaining the modifiers `|js`, `|json`, `|url`, `|raw`, with a mini example.

### S08 · Page scripts in the block editor
**S08a · Toolbar button and sidebar panel** (Gutenberg style, 280px):
- The ScriptDock **dot icon button** sits in the editor top bar, with a small ember badge when the page has code or has switched off snippets.
- Panel sections:
  1. **Page code:** 7 rows (Header, Body, Footer, Before content, After content, CSS, JavaScript), each with a status ("Empty" or "14 lines"), and an **Edit page code** button.
  2. **Site-wide snippets on this page:** a list of matching snippets (type chip, title, an "On this page" switch). Includes the "Turn off all site-wide code here" switch.
  3. Link: *Manage snippets ↗*.

**S08b · Full-screen "Page code" modal** (branded):
- Title "Page code · About us".
- **Left:** vertical tabs for the 7 slots, with content dots.
- **Right:** the slot description plus a code editor (dark or light), smart tags and full height.
- Footer note: "Saved when you update the page". Primary **Done**.

**S08c · Classic editor fallback:** a meta box with horizontal tabs and editors (restyled current box).

**States:** the block editor with the panel open; the modal on the CSS tab with code; a page with 2 snippets switched off.

### S09 · Header & Footer
- **Header:** H1, plus the description "The quickest way to add code to every page. For conditions or per-page code, create a snippet."
- **Three stacked code cards:** Header `<head>`, Body `<body>`, Footer `</body>`. Each has a description, a code editor (240px, resizable), a priority stepper and an "Output on wp_head" hint.
- **Sticky save bar** (appears when anything changes): "Unsaved changes · Discard · Save changes".
- **States:** empty, filled, tampered warning.

### S10 · Library
- **Header:** H1 "Library", a "47 ready-made snippets" chip, a subtle glow, and the note "Everything ships with the plugin. Nothing is downloaded."
- **Category pills** (with counts): All, Analytics & pixels 14, Performance 7, Security 5, Admin 5, Content & design 9, Login page 2, WooCommerce 5. Also a search box.
- **Cards** (3 per row at 1440):
  - A thin-line illustration icon with the orange dot, per category.
  - Title, a 2-line description, type chip(s), and meta chips (placement; "Waits for consent" when relevant).
  - Footer: **Add** (secondary pill with an orange "+" circle).
  - The "Added" state shows a success chip "Added ✓ · Open".
- **Blocked card:** dimmed, with a lock and the reason ("Requires WooCommerce").
- **S10b · Template modal** (on Add):
  - **Left:** read-only code preview with placeholders highlighted.
  - **Right:**
    - Required fields with validation ("Measurement ID", placeholder `G-XXXXXXXXXX`; the error reads "Use the format G-XXXXXXXXXX").
    - "Where it runs": the default placement, plus "Customize" (opens S05).
    - A "Wait for cookie consent" select.
    - An **Activate now** switch.
    - Primary **Add snippet**.
  - For multi-part templates: "This adds 2 snippets" with a list.
- **Empty search state** with an illustration.

### S11 · Site Files
- **Header:** H1 and a description.
- **File cards:** ads.txt, app-ads.txt, llms.txt, security.txt, robots.txt. Each has:
  - Filename in mono and its public path.
  - A status pill: **Live** (success), **Off** (neutral) or **Overridden by a server file** (warning).
  - A *View file ↗* link and a monospace editor.
  - An "Insert example" ghost button.
- **robots.txt card:** an extra segmented control, "Add to WordPress rules | Replace".
- **Subfolder-install warning** banner (example state).
- **Sticky save bar.**

### S12 · Import & Export
- **Card 1, "Move from another plugin":** rows for detected plugins, each with a generic icon, name and "12 snippets found", a "Keep active" switch and an **Import** button. A warning banner reads "WPCode is still active. Deactivate it after importing, or code will run twice." There is also an empty state ("No other snippet plugins found").
- **Card 2, "Import a file":** a **drag-and-drop zone** ("Drop a .json export here, or browse"), a "Keep snippets active" switch and an **Import** button. The zone has a file-selected state.
- **Card 3, "Export":** *Export all snippets* (primary), the hint "Select snippets on the list to export only some", and a CLI hint in mono.
- **S12b · Import results** (modal): "Imported 12 snippets · 9 active", with a list of warnings (warning icons, per-item messages) and **View imported snippets**.

### S13 · Settings
- **Layout:** section nav on the left (sticky list) and content cards on the right.
- Sections:
  1. **General:** admin bar inspector switch.
  2. **Error protection:** auto-deactivate switch; email alerts switch with the email shown.
  3. **Page scripts:** content-type checkboxes (Posts, Pages, Products, Courses…).
  4. **Performance:** cached files switch (with the uploads path), minify CSS switch.
  5. **Editor:** theme (Light/Dark with previews), revisions to keep (stepper).
  6. **Safety & security:**
     - **Safe mode card:** the secret link with Copy, "Generate new link" (with confirm), and the wp-config constant in a code chip.
     - **Tamper protection card:** On/Off status, key source, and the count of snippets needing review with *Review* and *Approve all* (with confirm).
     - **PHP snippets card:** Available/Unavailable plus the reason and the hardening constants.
  7. **Uninstall:** "Delete all data when the plugin is deleted" (danger zone styling).
  8. **About:** version, a "Free & open source · No tracking · No ads" badge row, and links (GitHub, changelog).
- **Sticky save bar** appears when anything changes.

### S14 · Tags management (replaces WordPress's tag screen)
- In the list's Tags filter dropdown: "Manage tags…" opens a **modal**.
- The modal lists tags with counts, rename inline, delete (with confirm) and merge (optional).
- In the editor: a tag chip input with autocomplete; "Create 'analytics'" appears as the last option.

### S15 · Front-end admin bar inspector
- Top item: dot icon and "ScriptDock · 5". In safe mode it becomes a **red** item, "Safe mode".
- Dropdown panel (dark, WordPress toolbar style, but a richer row design):
  - Header "5 snippets on this page".
  - Rows: type colour dot, title, and location in gray.
  - "This page has its own code".
  - Footer actions: Add snippet · Enter safe mode (this browser).

### S16 · "ScriptDock Snippet" block (in the block editor canvas)
Placeholder card in Gutenberg style with our dot icon. States:
- **Empty:** no eligible snippets, with a "Create a snippet ↗" link.
- **Choose:** a select.
- **Selected:** snippet name, type chip, and "Shown on the published page", plus *Edit snippet ↗*.
- **Missing:** the snippet was deleted or turned off (warning).

### S17 · Banners, toasts & dialogs (our feedback set)
- **Banners** (in context on relevant screens):
  - Safe mode on for you (warning, with **Exit**).
  - Safe mode forced for the whole site (warning, with the wp-config note).
  - Snippet switched off after a fatal error (danger: snippet name, error, line, **Fix it**, Dismiss).
  - Snippets need review (danger, **Review**).
  - Consent plugin missing (info).
- **Toasts** (bottom-left, ink background, 4s): "Snippet activated", "Copied to clipboard", "Saved", and an error variant with **Retry**.
- **Confirm dialogs:** Move to trash · Delete permanently · Empty trash · Approve all flagged snippets · Generate new safe-mode link · Leave with unsaved changes · Restore revision · Keep snippets active on import.

### S18 · Safe mode
- Top-bar safe-mode pill (red).
- A **Safe mode landing banner** after opening the secret link: "Safe mode is on in this browser. No snippets are running for you, so you can fix things. [Go to snippets] [Exit safe mode]".
- The admin bar red state (S15).

### S19 · Email: snippet switched off (HTML email)
- Content: logo; headline "We switched off a snippet to keep your site running"; a card with snippet name, error message, line, time and page; CTA **Fix the snippet**; a footer with the safe mode tip.
- Plain, 600px wide, with light-mode colours.

### S20 · Command palette ⌘K (new)
- A centred modal with a search input.
- Result groups:
  - Snippets (title, type chip, status).
  - Actions (New snippet, Open library, Header & Footer, Enter safe mode, Export all).
  - Settings.
- Keyboard hints in the footer.

### S21 · System states set
Design these reusable pieces:
- Skeleton loaders: list rows, cards, editor.
- Empty-state illustrations: 5, in the brand line-art style.
- An error page for when a screen fails to load.
- An offline/save-failed banner.
- A "no permission" page.

---

## 2. Key user flows to prototype (connected screens)
1. **First run:** Onboarding (S02) → import from WPCode detected → Overview with the imported snippets.
2. **Add GA4 from the library:** Library → Add → enter "G-8XK2N4P1QZ" → Activate now → the editor opens with a success toast → snippet list shows it active.
3. **Write PHP safely:** New snippet → PHP → type code with an error (live lint shows line 3) → fix → Save → the automatic test passes → toast "Saved and active".
4. **Crash recovery:**
   1. The front end breaks, and ScriptDock switches the snippet off.
   2. The next admin page shows the danger banner "Snippet 'Cart discount' was switched off: Call to undefined function… (line 12)", and the admin receives the email.
   3. Fix it → the editor opens with the error banner and "Jump to line 12" → save.
5. **Targeting:**
   1. Editor → Edit targeting.
   2. Placement: Site header.
   3. Only on selected content: Pages → select About, Pricing, Contact; Categories → News (Posts in this category).
   4. Audience: Logged out + Mobile & tablet.
   5. Schedule: Mon–Fri 09:00–17:00.
   6. Review → Save. The summary card updates.
6. **Per-page code:** edit the "Pricing" page in the block editor → ScriptDock button → sidebar shows 3 site-wide snippets → switch off "Chat widget" here → Edit page code → CSS tab → add styles → Done → Update the page.
7. **Tamper alert:** Overview shows "1 snippet needs review" → open it → banner and History diff → Approve.
8. **Safe mode:** open the secret link → landing banner → fix a snippet → Exit safe mode.
9. **Bulk:** select 4 rows → bulk bar → Add tag "tracking" → toast.

---

## 3. Content & microcopy rules
- Sentence case everywhere. Buttons are verbs ("Save snippet", "Add snippet", "Import").
- Explain the consequence, not the mechanism: "Only admins see it" rather than "Requires manage_options".
- Error messages give the fix: "Use the format G-XXXXXXXXXX", "Add a hook name, e.g. woocommerce_after_cart".
- Keep technical names visible but secondary (e.g. show `wp_head` in mono below "Site header").
- Use the realistic content from `05-sample-data.md`. Never lorem ipsum.

---

## 4. Responsive deliverables

| Screen | 1440 | 1024 | 390 |
|---|---|---|---|
| S01 Overview | ✓ | ✓ | ✓ |
| S03 Snippets | ✓ | ✓ | ✓ |
| S04 Editor | ✓ | ✓ | ✓ |
| S05 Targeting (steps 1, 2, 5) | ✓ | ✓ | ✓ |
| S08 Block editor panel + modal | ✓ | – | – |
| S10 Library | ✓ | – | ✓ |
| Others | ✓ | – | – |

**RTL:** one mirrored version of S03 Snippets (Arabic UI strings allowed as placeholder text).
