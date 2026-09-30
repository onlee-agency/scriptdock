# ScriptDock: brand guidelines for the admin UI

Source of truth for the look and feel. The direction comes from the reference image `brand-reference.webp` (the "onlee" agency site the product owner likes). All colour values were sampled from that image and then tuned for accessibility inside WordPress.

**In one sentence:** clean white surfaces, near-black type, one warm **ember-orange** accent used with restraint, soft peach glows, pill shapes and big rounded cards, thin-line geometric illustrations with a glowing orange dot.

---

## 1. Name & wordmark
- Product name: **ScriptDock**, one word, capital S and D, in running text.
- The wordmark may be lowercase like the reference (`scriptdock`). Explore replacing a letter or the dot of the **i** with the **orange gradient dot**, echoing the reference logo, where the "o" is a gradient dot.
- Provide a **mark**: the dot, or a dot plus a minimal code/dock glyph, for small places.
- Provide a monochrome 20×20 version for the WordPress sidebar. WordPress recolours it to match the user's admin colour scheme, so it must work as a single colour.
- Tone of voice: confident, calm, plain English. Short sentences, no hype. Prefer "Add snippet" to "Create a new code snippet now!".

## 2. Colour

### 2.1 Ember (brand orange), sampled
| Token | Hex | Use |
|---|---|---|
| ember-50 | #FFF4EC | Tinted backgrounds, selected-card fill |
| ember-100 | #FFE6D5 | Hover tint, soft badges |
| ember-200 | #FFCDA8 | Peach glow, illustrations |
| ember-300 | #FEA969 | Glow highlight (sampled) |
| ember-400 | #FF8A1F | Accents on dark backgrounds |
| **ember-500** | **#FF6400** | **Core brand orange (sampled from the logo dot)** |
| ember-600 | #E25300 | Hover or pressed state of orange fills |
| ember-700 | #C2410C | **Orange text and links on white** (5.2:1 ✓) |
| ember-800 | #913C05 | Deep accent (sampled from the headline gradient) |
| ember-900 | #562B0C | Burnt brown (sampled) |
| ember-950 | #1E160F | Warm near-black (sampled, gradient start) |
| amber-400 | #FB8E01 | Gradient highlight (sampled) |
| amber-300 | #F79C02 | Gradient end (sampled) |

### 2.2 Signature gradients
- **Ember dot** (logo, status dots, icon accents): radial or 135° linear `#FB8E01 → #FF6400 → #FE6100`.
- **Ember headline** (one highlighted word in a big heading, like "Solutions" in the reference): 90° linear `#1E160F 0% → #562B0C 20% → #913C05 40% → #DD5701 65% → #FD6B00 82% → #F79C02 100%`. Use at most once per screen and only for large display text (≥ 28px).
- **Peach glow** (atmosphere behind hero areas, onboarding, empty states and wizard headers): large, heavily blurred radial blobs `#FF9C61 @ 35% → #FFCDA8 @ 25% → #FFEFE7 → transparent`, placed off-centre toward an edge. Never behind dense text or tables.

### 2.3 Neutrals (warm)
| Token | Hex | Use |
|---|---|---|
| ink-950 | #0C0C0C | Headlines (sampled) |
| ink-900 | #111111 | Primary text, primary buttons (sampled from the nav) |
| gray-700 | #4F504D | Body text (sampled; 8.1:1 on white) |
| gray-600 | #6B6C69 | Secondary text (5.3:1) |
| gray-500 | #8A8B87 | Placeholders, disabled text (large text only) |
| gray-400 | #8F8E8A | **Form control borders** (inputs, selects, checkboxes). 3.3:1 on white, the minimum for controls. |
| gray-300 | #D9D8D4 | Secondary button borders, hover dividers (decorative only) |
| gray-200 | #E7E5E1 | Card borders, dividers |
| gray-100 | #F3F2EF | Subtle fills, table header |
| gray-50 | #F9F8F6 | App background inside our screens |
| white | #FFFFFF | Cards, panels, navigation pill |

### 2.4 Semantic colours (aligned with WordPress's own notice colours)
These reuse the hues WordPress uses for its notices, so a core "Update available" notice and our messages look like one system. They are kept visibly different from the brand orange, so the accent is never mistaken for a warning.

| Role | Icon / border (WordPress hue) | Text | Soft background (WordPress notice background) |
|---|---|---|---|
| Success / active | #00A32A | #007017 (5.9:1) | #EDFAEF |
| Warning | #DBA617 (borders and fills only) | #8A6100 (5.2:1; also for warning icons on white) | #FCF9E8 |
| Danger / error | #D63638 | #B32D2E (5.7:1) | #FCF0F1 |
| Info | #111111 (ink; we avoid WordPress blue) | #111111 | #F3F2EF |

### 2.5 Code-type colours
Chips, icons and filters. Redesigned so HTML no longer clashes with the brand orange.

| Type | Text | Background | Notes |
|---|---|---|---|
| PHP | #5B4BC4 | #EFEDFB | violet (5.6:1) |
| HTML | #BE2D48 | #FDECEF | coral red (5.0:1) |
| CSS | #1B63B0 | #E8F2FC | azure (5.4:1) |
| JavaScript | #8A6A00 | #FFF6D6 | mustard (4.7:1) |
| Universal (HTML + PHP) | #0F7C5A | #E6F6EF | emerald (4.6:1) |

Always pair the colour with the type label or icon: never colour alone. Every text/background pair above meets WCAG AA for small text.

### 2.6 Accessibility rules for orange
- `#FF6400` on white is **2.97:1**. Never use it for text, and never as the only indicator of state.
- **Orange fills carry dark text** (`#111111` on `#FF6400` = 6.4:1 ✓). White text on orange fails.
- Orange links and inline accents on white use **ember-700 #C2410C**.
- On the dark WordPress sidebar (#1E1E1E), ember-500 passes (5.6:1).
- Focus rings: 2px **ink** ring plus a 2px ember-200 outer halo, visible on white and on orange.

### 2.7 Why this palette works inside WordPress
- **Surfaces:** WordPress's admin is light grey (#F0F0F0) with white panels. Our warm gray-50 (#F9F8F6) and white cards sit naturally inside it.
- **Chrome:** the sidebar and toolbar are near-black (#1E1E1E) in the default "Modern" scheme. Our ink (#111111) typography and ink primary buttons match that frame, so the plugin feels part of WordPress, only more polished.
- **Accent:** WordPress uses blue (#3858E9 in "Modern") for its active menu item and its own primary buttons. Our ember orange is clearly different from it, and the two don't fight, because orange appears only inside our screens and only as an accent.
- **Colour schemes:** each user can choose one of 9 WordPress colour schemes, some with coloured sidebars (Sunrise is orange/red, Ectoplasm purple, Ocean teal, Coffee brown). Our screens do not depend on the sidebar colour: they use their own neutral surfaces, and the brand works next to every scheme.
- **Semantic colours:** these reuse WordPress's notice hues (green #00A32A, yellow #DBA617, red #D63638), so core notices, other plugins' notices and ours look consistent.
- **Inside the block editor:** where our UI sits in WordPress's own panels (sidebar panel, block placeholder), use WordPress's component style (grey borders, 2px radius, 13px text) with our ink and ember accents only. Our full-screen modal can be fully branded.
- **Controls:** WordPress 7.x uses 40px-tall controls. Our controls keep that 40px height (36px compact) so they feel familiar and meet touch-target sizes.

## 3. Typography
Bundle the fonts with the plugin: no Google Fonts or CDN. Use SIL Open Font License fonts only, so they can ship inside a GPL plugin.

| Role | Font | Notes |
|---|---|---|
| UI and headings | **Plus Jakarta Sans** (OFL, variable 200–800) | Closest free match to the reference's bold geometric grotesk. |
| Code | **JetBrains Mono** (OFL) | Ligatures off by default. |
| Fallback | system-ui stack | |

**Type scale** (inside our screens; WordPress itself uses 13px, so ours is slightly larger for readability):

| Style | Size / line height | Weight | Tracking |
|---|---|---|---|
| Display (onboarding, empty-state heroes) | 40/44 | 800 | -0.03em |
| H1 page title | 28/34 | 800 | -0.02em |
| H2 section | 20/28 | 700 | -0.01em |
| H3 card title | 16/24 | 700 | |
| Body | 14/22 | 400/500 | |
| Small | 13/20 | | |
| Caption | 12/16 | | |
| Overline / label (like "TURN OFF LIGHT" and "CONTACT" in the reference) | 11–12/16 | 600, uppercase | +0.08em |
| Code | 13/20 JetBrains Mono | | |

## 4. Shape, space, elevation
- **Radius:** pills 999px (buttons, chips, toggles, navigation, search) · cards and panels 20px · modals 24px · inputs 12px · small chips and badges 999px · code editor frame 16px.
- **Spacing scale (4px base):** 4, 8, 12, 16, 20, 24, 32, 40, 48, 64. Cards use 24px padding (20px on mobile).
- **Borders:** 1px gray-200 on cards. Selected cards use a 2px ink border, or an ember gradient ring plus an ember-50 fill.
- **Shadows (soft and warm, like the reference's floating nav pill):**
  - `shadow-sm` 0 1px 2px rgba(17,17,17,.06).
  - `shadow-md` 0 8px 24px rgba(17,17,17,.06).
  - `shadow-lg` 0 24px 48px rgba(17,17,17,.10).
  - `glow` 0 12px 32px rgba(255,100,0,.18): only for the primary floating action or a highlighted card.
- **Density:** comfortable by default (list rows 56px). Offer a compact mode for the snippet list (44px rows).

## 5. Components in brand style (key patterns from the reference)
- **Primary button:** ink #111 pill with white text. An optional **orange gradient circle icon** on the right holding the action glyph (↗, +, ✓), exactly like the reference's "CONTACT" button. Height 40px (36px compact). Hover: slight lift and `shadow-md`. Pressed: ink-950.
- **Secondary button:** white pill, 1px gray-300 border, ink text.
- **Ghost / tertiary:** text-only ink, underline on hover.
- **Accent button** (use sparingly, one per screen at most): ember gradient fill with ink text.
- **Danger:** white pill with red text and border; solid red for final confirms.
- **Circular icon buttons:** 44px outline circles with thin ink strokes (the reference's slider arrows). Use for prev/next, close and more.
- **Navigation:** a floating white **pill bar** with a soft shadow at the top of our screens (the reference's header). Left: logo; centre: section tabs; right: search, safe-mode pill and the "New snippet" primary button.
- **Cards:** white, 20px radius, 1px border, generous padding, large bold title, grey body text. A thin-line illustration with an orange dot sits in the top-left of feature cards (library, onboarding, empty states, placement cards).
- **Toggle switch (brand signature):** off = gray-200 track with a white knob; **on = ink track with an orange-gradient knob** (the "dot"). Include a check glyph in the knob for non-colour indication.
- **Status dot:** a small ember-gradient dot with a gentle pulse means "running". Grey means inactive; red with an exclamation means error.
- **Uppercase labels** with wide tracking for small section labels and meta ("ACTIVE", "SITE HEADER").
- **Vertical side tab** (like "Certified Partner" in the reference): can be reused for a subtle "Safe mode" or "Help" tab. Optional.

## 6. Illustration & iconography
- **Icons:** thin-line (1.5px stroke), geometric, rounded joins, 20/24px grid, ink colour. The active or highlighted state adds a small ember dot. Custom SVG set bundled with the plugin (no icon CDN). Include icons for:
  - Code types, placements, content types (page, post, product, category, tag, special page, URL), devices, browsers (generic, not brand logos), states and actions.
- **Illustrations:** the reference's style of **wireframe geometry** (thin black lines: stars, orbits, spheres) with **one glowing orange dot** as the focal point. Use for empty states, onboarding, errors (a "broken orbit" for crash recovery), library categories and loading moments.
- **Placement cards:** mini page wireframes in thin grey lines; the **orange dot marks where the code goes** (head, after body, footer, after paragraph 2…).
- **Third-party logos** (Google Analytics, Meta, WooCommerce…): plain monochrome glyphs or names in cards only, never altered. Real logos can be dropped in later.

## 7. Motion
- 150–250ms, ease-out. Cards lift 2px on hover. Steppers slide horizontally (200ms). Selection check marks scale in (120ms).
- The "running" dot pulses gently every 2.4s (off with reduced motion).
- Respect `prefers-reduced-motion`: no parallax or glow drift; fades only.

## 8. Dark code editor theme (v1 dark mode = editor only)
| Element | Colour |
|---|---|
| Background | #141210 (warm near-black) |
| Gutter | #1B1916 |
| Line numbers | #8A847B |
| Selection | #3A2A1E |
| Cursor | #FF8A1F |
| Keywords | #FF8A1F (ember-400) |
| Strings | #F9C98B |
| Numbers | #FFB86B |
| Comments | #8F887E italic |
| Functions / defs | #E6D5C3 |
| Variables | #F5F1EC |
| Tags | #FF7A59 |
| Attributes | #F9C98B |
| Properties | #9FD3C7 |
| Errors | red underline with a #3B1414 line background |

A matching **light theme** uses ink text on white, keywords in ember-700 and strings in #0F7C5A.

A full dark UI ("Turn off light", like the reference) is **v2**. Design light mode only for now, but keep all colours as tokens so a dark theme can be added.

## 9. Living inside WordPress (brand-specific notes)
- The WordPress sidebar and toolbar stay dark (#1E1E1E) in the default "Modern" scheme. Their **active menu highlight is WordPress blue #3858E9**, which we cannot change. Our orange appears only inside our screens, so avoid blue as a brand colour to prevent a clash.
- Paint our whole content area with gray-50 (#F9F8F6) and start with the floating navigation pill. Leave a **notice zone** under it for WordPress and other plugins' notices.
- Our menu icon in the sidebar is monochrome; WordPress recolours it.
