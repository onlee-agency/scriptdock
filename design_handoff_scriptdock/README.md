# Handoff: ScriptDock — WordPress plugin admin UI

## Overview

ScriptDock is a free WordPress plugin that lets site owners add PHP, HTML, CSS and JavaScript anywhere on their site — site-wide, on specific pages, or by rules. It includes crash protection, tamper protection, safe mode, revisions, a snippet library, per-page code and smart loading. Everything competitors sell as "Pro" is included free; there are no upsells, ads or tracking.

This bundle contains the complete redesign: a brand foundation, a component library, and all 21 screens (S01–S21) with their responsive variants.

Target environment: **WordPress 7.1 admin**, PHP + JavaScript. The designs assume React (WordPress ships `@wordpress/element`) for the app screens, and native WordPress patterns for the surfaces WordPress owns.

---

## About the design files

The `.dc.html` files in this bundle are **design references**, not production code. They are static HTML prototypes showing intended look, layout, states and copy. Every screen is drawn as a fixed-size artboard on a canvas, with annotation labels above each one.

Your task is to **recreate these designs inside the WordPress plugin**, using the codebase's own patterns — not to copy the HTML. In particular:

- The artboards render a fake WordPress admin frame (dark toolbar, 160px sidebar). **Do not build that** — WordPress provides it. Build only what lives in the content area.
- Annotation labels (`S03 · A`, "Views and states", etc.) are documentation for the designer and must not ship.
- Inline styles are an artefact of the prototyping tool. Ship a real stylesheet using the CSS custom properties listed under Design tokens, all scoped under a `.sd-app` root class.

## Fidelity

**High fidelity.** Colours, typography, spacing, radii, shadows and copy are final and accessibility-checked. Recreate pixel-perfectly. All copy in the designs is final and should be used verbatim (it is written to WordPress's sentence-case convention and explains consequences rather than mechanisms).

---

## Non-negotiables

1. **The WordPress frame is not ours.** 32px dark toolbar (#1E1E1E), 160px dark sidebar, ScriptDock highlighted in WordPress blue #3858E9. Never restyle it. Our design starts in the content area, which we paint gray-50 (#F9F8F6).
2. **Leave a notice zone** directly under our top bar. WordPress core and other plugins print notices there; our banners appear there too. Every layout must cope with an arbitrary number of them.
3. **Orange is an accent.** Never use it for small text on white. Never put white text on orange. Orange fills carry ink text (#111 on #FF6400 = 6.4:1). Orange text on white is #C2410C (ember-700).
4. **WCAG 2.2 AA.** Text ≥ 4.5:1, controls and icons ≥ 3:1. Visible focus on everything. Colour is never the only signal.
5. **Layouts survive +40% text length and mirror for RTL.** No fixed widths on text containers; use logical properties for padding and margin. Only arrows and chevrons flip.
6. **Surfaces WordPress owns follow WordPress.** The Gutenberg sidebar panel, the block placeholder, the admin bar menu and the classic meta box use Gutenberg's language — #DDDDDD/#E0E0E0 borders, 2px radii, 13px text. They take only our dot, type chips and switches. Our own full-screen modals stay fully branded.

---

## Design tokens

All values are CSS custom properties declared on `.sd-app`.

### Colour — ember (brand)

| Token | Hex | Use |
|---|---|---|
| `--sd-ember-50` | `#FFF4EC` | selected card fill, hover tint |
| `--sd-ember-100` | `#FFE6D5` | placeholder highlight, "faster" badge |
| `--sd-ember-200` | `#FFCDA8` | selected card border, focus halo |
| `--sd-ember-300` | `#FEA969` | — |
| `--sd-ember-400` | `#FF8A1F` | dark-theme accents, spinners |
| `--sd-ember-500` | `#FF6400` | brand orange; fills only, ink text on top |
| `--sd-ember-600` | `#E25300` | pressed state of orange fills |
| `--sd-ember-700` | `#C2410C` | **orange text on white** (4.7:1); links |
| `--sd-ember-800` | `#913C05` | link hover, text on ember-50 |
| `--sd-ember-900` | `#562B0C` | gradient stop |
| `--sd-ember-950` | `#1E160F` | gradient stop |

### Colour — warm neutrals

| Token | Hex | Ratio on white | Use |
|---|---|---|---|
| `--sd-ink-950` | `#0C0C0C` | 20:1 | pressed ink |
| `--sd-ink-900` | `#111111` | 19:1 | primary text, primary buttons, switch track |
| `--sd-gray-700` | `#4F504D` | 8.1:1 | body text, **code gutters** |
| `--sd-gray-600` | `#6B6C69` | 5.3:1 | secondary text, captions on white only |
| `--sd-gray-500` | `#8A8B87` | 3.4:1 | non-text only |
| `--sd-gray-400` | `#8F8E8A` | 3.3:1 | input borders |
| `--sd-gray-300` | `#D9D8D4` | — | secondary button borders |
| `--sd-gray-200` | `#E7E5E1` | — | card borders, dividers |
| `--sd-gray-100` | `#F3F2EF` | — | table headers, chip fills |
| `--sd-gray-50` | `#F9F8F6` | — | app background |
| `--sd-white` | `#FFFFFF` | — | surfaces |

### Colour — semantic

Matched to WordPress's own notice hues so our banners sit beside core notices without clashing.

| Role | Border/icon | Text | Background |
|---|---|---|---|
| Success | `--sd-success` `#00A32A` | `--sd-success-text` `#007017` | `--sd-success-bg` `#EDFAEF` |
| Warning | `--sd-warning` `#DBA617` | `--sd-warning-text` `#8A6100` | `--sd-warning-bg` `#FCF9E8` |
| Danger | `--sd-danger` `#D63638` | `--sd-danger-text` `#B32D2E` | `--sd-danger-bg` `#FCF0F1` |
| Info | `--sd-ink-900` `#111111` | `--sd-ink-900` | `--sd-info-bg` `#F3F2EF` |

Info is deliberately **ink, not WordPress blue** — blue is reserved for WordPress's own chrome.

### Colour — code types

Always paired with a text label; never colour alone.

| Type | Foreground | Background | Ratio |
|---|---|---|---|
| PHP | `#5B4BC4` | `#EFEDFB` | 5.6:1 |
| HTML | `#BE2D48` | `#FDECEF` | 5.0:1 |
| CSS | `#1B63B0` | `#E8F2FC` | 5.4:1 |
| JavaScript | `#8A6A00` | `#FFF6D6` | 4.7:1 |
| Universal | `#0F7C5A` | `#E6F6EF` | 4.6:1 |

### Gradients

```css
--sd-gradient-dot: linear-gradient(135deg, #FB8E01 0%, #FF6400 55%, #FE6100 100%);
--sd-gradient-headline: linear-gradient(90deg, #1E160F 0%, #562B0C 20%, #913C05 40%, #DD5701 65%, #FD6B00 82%, #F79C02 100%);
/* glow — heroes and empty states only, never behind tables */
background: radial-gradient(closest-side, rgba(255,156,97,.34), rgba(255,205,168,.22) 45%, rgba(255,239,231,0) 100%);
```

`gradient-dot` is the brand mark and the switch knob. `gradient-headline` is used **once per screen**, on display text ≥ 28px only.

### Typography

Both fonts are OFL and **bundled with the plugin** — no CDN, no external requests.

```css
--sd-font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
--sd-font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
```

| Role | Size / line-height | Weight | Letter-spacing |
|---|---|---|---|
| display | 40 / 44 | 800 | −0.03em |
| h1 | 28 / 34 | 800 | −0.02em |
| h2 | 20 / 28 | 700 | −0.01em |
| h3 | 16 / 24 | 700 | — |
| body | 14 / 22 | 400 | — |
| body-strong | 14 / 22 | 600 | — |
| small | 13 / 20 | 400 | — |
| caption | 12 / 16 | 500 | — |
| overline | 11 / 16 | 700 | +0.08em, uppercase |
| code | 13 / 20 | 400 | mono |

### Spacing, radius, shadow, motion

```css
/* spacing — 4px base: 4 8 12 16 20 24 32 40 48 64 (24 = card padding) */

--sd-radius-pill:   999px;  /* buttons, chips, tabs, top bar */
--sd-radius-card:   20px;
--sd-radius-modal:  24px;
--sd-radius-editor: 16px;
--sd-radius-input:  12px;
--sd-radius-sm:     8px;

--sd-shadow-sm:   0 1px 2px rgba(17,17,17,.06);    /* resting cards */
--sd-shadow-md:   0 8px 24px rgba(17,17,17,.06);   /* top-bar pill, hover */
--sd-shadow-lg:   0 24px 48px rgba(17,17,17,.10);  /* modals, drawers */
--sd-shadow-glow: 0 12px 32px rgba(255,100,0,.18); /* one element per screen max */

--sd-focus-ring: 0 0 0 2px #111111, 0 0 0 4px #FFCDA8;

/* motion: fast 120ms · base 200ms · slow 300ms · cubic-bezier(.2,.8,.2,1) */
```

All motion respects `prefers-reduced-motion`. The pulsing "running" dot stops pulsing.

### Z-index

Above WordPress's toolbar, which sits at 99999.

| Layer | z-index |
|---|---|
| Drawer | 100000 |
| Modal / full-screen overlay | 100010 |
| Toast | 100020 |

---

## Signature components

These carry the brand. Get them right and the rest follows.

**Brand switch (`sd-Switch`)** — 44×24 track, 20×20 knob, 2px inset. Off: gray-200 track, white knob. On: ink-900 track, `gradient-dot` knob **with a check glyph inside it** so state is never colour-only. Busy: gray-700 track, centred spinner. Disabled/locked: gray-100 track with a dashed gray-300 border and a padlock in the knob. Small variant 34×18. `role="switch"` + `aria-checked`.

**Primary button** — ink-900 pill, white label flush left, a 28px `gradient-dot` circle on the right holding a dark icon. Heights: sm 32 / md 40 / lg 48. Hover lifts 2px with a stronger shadow; pressed drops 1px and the circle goes ember-600.

**Placement card (`sd-PlacementCard`)** — a mini page wireframe drawn in thin gray-300 lines with **an orange dot marking exactly where the code is injected**. Selected: 2px ember-500 border, ember-50 fill, glow shadow, ink check badge top-right, and inline extras (priority stepper, paragraph number, hook input, shortcode chip) revealed inside the card. Disabled: no wrapper opacity — gray-700 title, gray-600 reason with a lock icon, gray-600 dot.

**Content card (`sd-ContentCard`)** — 16:10 thumbnail (featured image or a generated gradient placeholder with a type icon), title, mono path, status chip, date, and a check circle top-right. Selected state matches the placement card. Pages with children reveal an "Include N child pages" switch when selected.

**Top bar (`sd-TopBar`)** — a floating white pill, 64px tall, `radius-pill`, `shadow-md`, inset 16px from the top and sides of the content area. Logo + wordmark, section tabs (active tab is ink bold with a small ember dot beneath), search with ⌘K hint, the safe-mode pill when active, and the primary New snippet button. ≤1280 the tabs collapse to "More ▾"; ≤782 it becomes logo + menu button + icon button.

**Code editor (`sd-CodeEditor`)** — `radius-editor`, toolbar with the virtual file name and live lint status, a gutter, the code area, and a status bar. Light and dark themes.
- **Build each line as its own block element in both the gutter and the code column.** Do not rely on newlines between inline elements.
- Gutter: `white-space: nowrap`, minimum 52px wide (66px where markers like `12 ●` appear), **gray-700 on gray-50** in light theme, `#8A847B` on `#1B1916` in dark.
- Highlighted (error/diff) lines need `width: max-content; min-width: 100%` so the tint spans the full line when scrolled horizontally.
- Dark theme: bg `#141210`, chrome `#1B1916`, borders `#2A2520`, text `#F5F1EC`, keywords `#FF8A1F`, strings `#F9C98B`, functions `#E6D5C3`, variables `#9FD3C7`, numbers `#FFB86B`, comments `#8A847B`, error wash `#3B1414` with a 2px `#D63638` left edge.

**Targeting sentence (`sd-TargetingSentence`)** — a plain-language sentence with inline chips: `ember-50` fill, `ember-200` border, ink text, pill radius. Chips **must be `display: inline-block; white-space: nowrap`** or they split across lines into two broken half-pills. Parts not yet chosen render as neutral gray-200 chips. Each chip links back to its wizard step.

---

## Screens

Every screen is drawn at 1440×900 inside the WordPress frame unless noted. Responsive variants are marked.

### S01 Overview — `ScriptDock S01 Overview.dc.html`
Landing page. Hero with a greeting and a one-line health status; health cards (danger/warning) only when something needs attention; four stat tiles (Running with a pulsing dot, Inactive, Errors, Page scripts) that filter the list on click; two columns holding Recently edited, Recent problems, Quick actions, a Getting-started checklist with a progress ring, and a Speed tip; then three library highlight cards.
**States:** issues present · healthy · safe mode on. **Responsive:** 1440, 1024, 390.
*Layout note:* health cards sit **above** the stat tiles so the problem is read first. The error tile is the only tile that changes colour.

### S02 Onboarding — `ScriptDock S02 S15-S21 Remaining.dc.html`
Full-screen overlay, four steps, dot stepper, skippable throughout. 720×560.
1. Welcome — "Add code anywhere. Safely." plus three value props (Crash-proof, Fast, Free).
2. Save your safe mode link — read-only link, Copy, "Email it to me", and an "I saved it" checkbox that gates Next.
3. Bring your snippets — detected plugins with an import toggle (on) and "Keep active" (off), plus the deactivate warning.
4. You're ready — three choice cards and the ⌘K tip.

### S03 Snippets — `ScriptDock S03 Snippets.dc.html`
The main list. Header with count chip and Import / New snippet. Six status tab pills. Toolbar: search, four filter dropdowns with removable chips, sort, density toggle. Nine-column table at 64px comfortable rows.
**Columns:** select · status switch · snippet (title + notes excerpt + tag chips) · type chip · where it runs (placement + targeting summary) · badges · priority · updated · row menu.
**States:** default with selection and bulk bar · needs-review view with its banner · empty · no results · skeleton rows · trash · toggle busy · toggle error toast.
**S03b quick view:** 560px right drawer — read-only code, where-it-runs summary, signature status, revisions, actions.
**Responsive:** 1440, 1024, 390 (rows become cards, filters move to a bottom sheet). **RTL** mirror included.
*Note:* a snippet paused by tamper protection uses a **dashed track with a padlock** — visually distinct from both "off" and "disabled", because "off because we stopped it" is a different state from "off because you turned it off".

### S04 Snippet editor — `ScriptDock S04 Editor.dc.html`
Sticky editor bar (breadcrumb, inline-editable title, unsaved dot, Active switch, Save split button, ⋯ menu). Banners as needed. Main column: code-type pill selector, code card, where-it-runs summary with the plain-language sentence and Edit targeting, and a loading card (Inline/Cached segmented, five load-strategy radio cards, consent select). Right sidebar 340px: Details, Safety, Run now, History, Options.
**States:** new HTML · PHP with lint error · PHP after a fatal test run · tampered · read-only (no PHP rights) · shortcode placement · on-demand with console output · dark theme.
**Responsive:** 1440, 1024, 390 (sidebar becomes tabs: Details / Loading / Safety), plus full-screen code mode.

### S05 Targeting wizard — `ScriptDock S05 Targeting.dc.html`
Full-screen overlay. Header with the stepper and an Advanced rules toggle; step content left (~64%); sticky summary rail right (~36%) holding the live sentence, the selection tray and warnings; bottom bar with Back / step count / Next.
- **Step 1 Placement** — grouped placement cards, four per row, with inline extras on the selected card and reasons on dimmed ones.
- **Step 2 Pages & content** — three scope cards, seven content tabs with counts, "Select all" toggle card, search and filters, grid/list toggle, content cards, load-more with skeletons, and the expandable selection tray with a live match estimate.
- **Step 3 Audience** (optional) — visitors segmented + role chips, device cards, language (hidden on single-language sites), traffic source, WooCommerce, advanced.
- **Step 4 Schedule** (optional) — Always vs custom, weekday chips, time window, timezone note.
- **Step 5 Review** — the large sentence with linked chips, per-step summary cards with Edit, warnings, Save targeting.
- **Advanced rules** — Show/Hide segmented, groups joined by an OR pill, rules inside joined by AND.
- **Condition catalogue** — 29 conditions in 7 groups with every operator and value control.
**Responsive:** steps become full-screen pages at 390, summary rail becomes a bottom sheet.
**Keyboard:** arrows move between cards, Space selects, Enter continues, Esc closes with a confirm if changed.

### S06 History · S07 Smart tags · S08 Block editor — `ScriptDock S06-S08 Editor Surfaces.dc.html`
- **S06 History drawer (720px)** — revision timeline left, diff right with +/− gutter markers and 3px coloured edges, Inline/Split toggle, and the tamper explanation. On a tampered snippet **Restore is the primary button and Approve is the outlined danger one** — the safe path should be the easy one.
- **S07 Smart tags** — palette with search, four groups, insert affordance, and a modifier footer (`|js`, `|url`, `|json`, `|raw`) with a live example. Plus no-results, unavailable-group and post-insert states.
- **S08a** Gutenberg sidebar panel (280px) — 7 page-code slots with status, Edit page code, and site-wide snippets with per-page switches.
- **S08b** Full-screen branded Page code modal — vertical tabs for the 7 slots with content dots, description, editor.
- **S08c** Classic editor meta box.
- **S16** The ScriptDock Snippet block, four states: choose, selected, missing, none available.

### S09 Header & Footer · S10 Library · S11 Site Files — `ScriptDock S09-S11 Content Screens.dc.html`
- **S09** — three stacked code cards (Header, Body, Footer) with descriptions, priority steppers, output hints and a sticky save bar. Includes the tampered state. Header/footer code is **not** auto-paused on tamper; it warns and offers a comparison instead.
- **S10 Library** — hero with glow, eight category pills with counts, cards with illustration, description, type and meta chips, and an Add button. States: default, added, blocked. **S10b** template modal with the code preview showing highlighted `%%PLACEHOLDER%%` values, required fields with validation, targeting default with Customize, consent select, Activate-now switch, and a multi-part variant. Empty search state. **Responsive:** 1440 and 390, where the modal becomes a bottom sheet with the code preview collapsed behind a disclosure.
- **S11 Site Files** — file cards for ads.txt, app-ads.txt, llms.txt, security.txt, robots.txt. Status pills: Live / Off / Overridden by a server file. robots.txt adds an "Add to WordPress rules | Replace" segmented control. Subfolder-install warning. Sticky save bar. An "Off" file shows a serve toggle rather than an editable field.

### S12 Import & Export · S13 Settings · S14 Tags — `ScriptDock S12-S14 Admin.dc.html`
- **S12** — detected-plugin rows with Keep-active toggles, the "still active" warning, a drag-and-drop zone (empty / drag-over / file-selected), the export card with a CLI hint, and a no-plugins-found state. **S12b** import results modal with per-item warnings and a Deactivate button.
- **S13 Settings** — sticky section nav with a danger count, and eight sections: General, Error protection, Page scripts, Performance, Editor (theme shown as two live code previews), Safety & security (safe-mode link, tamper protection, PHP availability), Uninstall (danger card), About.
- **S14 Tags** — modal with counts, inline rename, delete, unused tags, and "+ New tag".

### S15 · S18 · S19 · S20 · S21 — `ScriptDock S02 S15-S21 Remaining.dc.html`
- **S15** Front-end admin bar inspector — normal and red safe-mode states.
- **S18** Safe mode — landing banner after opening the secret link, and the forced-for-everyone variant.
- **S19** Alert email — 600px, light mode only, with the error card and the safe-mode tip.
- **S20** Command palette — ⌘K with snippet / action / settings groups and keyboard hints.
- **S21** System states — error page, no-permission page, offline save-failed banner, three skeleton loaders.

### S17 Banners, toasts, dialogs
Drawn in context on the screens that use them, and as a complete set in the component library.

---

## Interactions & behaviour

**Status switch** — toggles instantly via AJAX with optimistic UI. Shows a busy spinner in the knob while saving. On failure, reverts the switch and raises an error toast with Retry.

**Row click** — the whole table row opens the editor. The type chip and Preview open the quick-view drawer instead.

**Live lint** — PHP is checked on the server ~0.7s after typing stops; CSS/JS/HTML are checked in the browser. The error line gets a wash, a gutter marker and a tooltip.

**Save** — ⌘S anywhere in the editor. A snippet with a syntax error saves but stays inactive. A snippet that throws during the automatic test save saves, stays inactive, and shows the error banner with "Jump to line N".

**Leaving with unsaved changes** — confirmation dialog. Keep editing is primary.

**Targeting wizard** — every chip in the review sentence jumps back to its step. The match estimate recalculates as the selection changes. Esc closes with a confirm if anything changed.

**Toasts** — bottom-left, ink background, 4 seconds, `aria-live="polite"`.

**Tamper flow** — a changed snippet is paused, badged "Needs review", and surfaced on Overview. Approving marks the current code as trusted. Restoring an earlier revision is offered first.

---

## State

Per snippet: `id`, `title`, `type`, `code`, `active`, `testMode`, `priority`, `notes`, `tags[]`, `placement`, `placementExtras`, `targeting`, `loadStrategy`, `output`, `consentCategory`, `signatureStatus`, `lastError`, `revisions[]`, `updatedAt`, `updatedBy`.

Per screen: filter and sort state, selection set, density, drawer/modal open state, wizard step and its draft targeting object, unsaved-changes flag.

Endpoints the wizard needs: paginated content lists with thumbnails and counts per type, term trees with counts, special-page availability, a URL test endpoint, and a match estimate.

---

## Accessibility checklist

- Text ≥ 4.5:1; controls and icons ≥ 3:1. Verified across every screen.
- `--sd-focus-ring` on every interactive element, always visible on keyboard focus.
- Colour never the only signal: type chips keep labels, status badges keep icons, the switch knob keeps its check, diff lines keep a 3px edge and a +/− gutter marker.
- Roles in place: `role="switch"` + `aria-checked`, `aria-sort`, `aria-current="step"`, `tablist`/`tab`, `listbox`/`option`, `dialog` with focus trap and Esc.
- Every icon-only button has an `aria-label`.
- Touch targets ≥ 44px on mobile. Controls hold WordPress's 40px desktop height (36px compact).
- Layouts survive +40% text; RTL mirrors from logical properties, with code, IDs and type names isolated LTR.

---

## Two rules worth keeping

1. **Never dim a card with `opacity`.** A wrapper opacity composites every child, including the sentence explaining why the card is unavailable. Express "blocked" with tokens: gray-700 heading, gray-600 body and icons, lock chip at full ink, gray-50 ground.
2. **The code gutter is body text, not chrome.** Every error message points at a line number, so gutters use gray-700 in light theme — never gray-500.

---

## Assets

No bitmap images. Everything is inline SVG or CSS.

- **Icons** — a 60-icon set on a 24px grid, 1.5px stroke, round caps and joins, `currentColor`. About 20 are drawn in Foundations; the rest follow the same construction.
- **Illustrations** — thin black line work with a single ember dot as the focal point. The dot always means "your code". Used on welcome, empty states and error states.
- **Logo** — wordmark "scriptdock", lowercase geometric, 800 weight, −0.04em, with the ember gradient dot standing in for the "o" in "dock". The dot lifts out as the standalone mark at any size.
- **Sidebar icon** — 20×20, single flat `currentColor` path, no gradient (WordPress recolours it per the user's admin colour scheme). Bracket strokes at 1.5px, solid dot.
- **Fonts** — Plus Jakarta Sans and JetBrains Mono, both OFL, bundled with the plugin.

*Featured images in the content picker are placeholders in the designs. Real thumbnails come from WordPress; pages without one get the generated gradient placeholder with a type icon.*

---

## Files

| File | Contents |
|---|---|
| `ScriptDock Foundations.dc.html` | Wordmark options, marks, sidebar icon, all tokens, icon set, illustrations, usage rules |
| `ScriptDock Components.dc.html` | Every component with all variants and states |
| `ScriptDock S01 Overview.dc.html` | S01 at 1440 (two states), 1024, 390 |
| `ScriptDock S03 Snippets.dc.html` | S03 list, all views and states, S03b drawer, 1024, 390, RTL |
| `ScriptDock S04 Editor.dc.html` | S04 all states, full-screen mode, 1024, 390 |
| `ScriptDock S05 Targeting.dc.html` | S05 steps 1–5, advanced rules, condition catalogue, 390 |
| `ScriptDock S06-S08 Editor Surfaces.dc.html` | S06, S07, S08a/b/c, S16 |
| `ScriptDock S09-S11 Content Screens.dc.html` | S09, S10 + S10b (1440 and 390), S11 |
| `ScriptDock S12-S14 Admin.dc.html` | S12, S12b, S13, S14 |
| `ScriptDock S02 S15-S21 Remaining.dc.html` | S02, S15, S18, S19, S20, S21 |
| `support.js` | Runtime required to open the `.dc.html` files in a browser |

Open any file directly in a browser. Each is a canvas of labelled artboards; scroll and zoom to move between them.

---

## Sample data

Every screen uses one consistent fictional site: **Lumen Coffee Roasters**, a WooCommerce coffee shop. Admin is Maya Chen. 24 snippets, of which 20 are running, 3 inactive, 1 in error and 1 needing review. The error is a PHP snippet calling an undefined WooCommerce function on line 12; the review item is a Hotjar snippet with a script injected from an unknown domain. Keep this data when building — it makes states legible and the numbers are consistent across every screen.

---

## Open decisions

1. **Geolocation targeting** — not designed. Showing code by visitor country needs either a bundled lookup database or an external service on every request. The recommendation is to skip it; URL rules or the CDN cover most cases, and an external lookup conflicts with the no-tracking promise.
2. **Font bundling** — Plus Jakarta Sans and JetBrains Mono add roughly 400KB to the plugin. The alternative is `system-ui` with the webfonts loaded only on ScriptDock's own screens.
3. **Connected prototypes** — the nine user flows in the brief are designed as static screens, not as a clickable prototype.
