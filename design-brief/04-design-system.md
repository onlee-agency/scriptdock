# ScriptDock: design system specification

Build the component library first, then compose every screen from it. Names below are the names engineering will use; please keep them. All values come from `02-brand-guidelines.md`.

---

## 1. Tokens (use these names, as CSS custom properties)

Everything is scoped to our root element, `.sd-app`. Nothing may style WordPress itself.

### Colour
```
--sd-ember-50  #FFF4EC   --sd-ember-100 #FFE6D5   --sd-ember-200 #FFCDA8
--sd-ember-300 #FEA969   --sd-ember-400 #FF8A1F   --sd-ember-500 #FF6400
--sd-ember-600 #E25300   --sd-ember-700 #C2410C   --sd-ember-800 #913C05
--sd-ember-900 #562B0C   --sd-ember-950 #1E160F
--sd-amber-400 #FB8E01   --sd-amber-300 #F79C02

--sd-ink-950 #0C0C0C     --sd-ink-900 #111111
--sd-gray-700 #4F504D    --sd-gray-600 #6B6C69   --sd-gray-500 #8A8B87
--sd-gray-400 #8F8E8A    --sd-gray-300 #D9D8D4   --sd-gray-200 #E7E5E1
--sd-gray-100 #F3F2EF    --sd-gray-50  #F9F8F6   --sd-white #FFFFFF

--sd-success #00A32A  --sd-success-text #007017  --sd-success-bg #EDFAEF
--sd-warning #DBA617  --sd-warning-text #8A6100  --sd-warning-bg #FCF9E8
--sd-danger  #D63638  --sd-danger-text  #B32D2E  --sd-danger-bg  #FCF0F1
--sd-info    #111111  --sd-info-bg      #F3F2EF

--sd-type-php-fg #5B4BC4        --sd-type-php-bg #EFEDFB
--sd-type-html-fg #BE2D48       --sd-type-html-bg #FDECEF
--sd-type-css-fg #1B63B0        --sd-type-css-bg #E8F2FC
--sd-type-js-fg #8A6A00         --sd-type-js-bg #FFF6D6
--sd-type-universal-fg #0F7C5A  --sd-type-universal-bg #E6F6EF
```

**Semantic aliases** (what components actually use):
```
--sd-bg-app: gray-50        --sd-bg-surface: white     --sd-bg-subtle: gray-100
--sd-border: gray-200       --sd-border-control: gray-400   --sd-border-strong: ink-900
--sd-text: ink-900          --sd-text-body: gray-700   --sd-text-muted: gray-600   --sd-text-disabled: gray-500
--sd-accent: ember-500      --sd-accent-text: ember-700   --sd-accent-soft: ember-50
--sd-focus-ring: 0 0 0 2px var(--sd-ink-900), 0 0 0 4px var(--sd-ember-200)
```

### Gradients
```
--sd-gradient-dot:      linear-gradient(135deg, #FB8E01 0%, #FF6400 55%, #FE6100 100%)
--sd-gradient-headline: linear-gradient(90deg, #1E160F 0%, #562B0C 20%, #913C05 40%, #DD5701 65%, #FD6B00 82%, #F79C02 100%)
--sd-gradient-glow:     radial-gradient(closest-side, rgba(255,156,97,.35), rgba(255,205,168,.25) 45%, rgba(255,239,231,0) 100%)
```

### Typography
- `--sd-font-sans: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`
- `--sd-font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace`

| Token | Size / line height | Weight | Tracking | Case |
|---|---|---|---|---|
| display | 40/44 | 800 | -0.03em | |
| h1 | 28/34 | 800 | -0.02em | |
| h2 | 20/28 | 700 | -0.01em | |
| h3 | 16/24 | 700 | | |
| body | 14/22 | 400 | | |
| body-strong | 14/22 | 600 | | |
| small | 13/20 | 400 | | |
| caption | 12/16 | 500 | | |
| overline | 11/16 | 700 | +0.08em | uppercase |
| code | 13/20 mono | | | |

### Space, radius, shadow, motion, layers
```
--sd-space: 4 8 12 16 20 24 32 40 48 64 (px)
--sd-radius-pill: 999px  --sd-radius-card: 20px  --sd-radius-modal: 24px
--sd-radius-input: 12px  --sd-radius-editor: 16px  --sd-radius-sm: 8px
--sd-shadow-sm: 0 1px 2px rgba(17,17,17,.06)
--sd-shadow-md: 0 8px 24px rgba(17,17,17,.06)
--sd-shadow-lg: 0 24px 48px rgba(17,17,17,.10)
--sd-shadow-glow: 0 12px 32px rgba(255,100,0,.18)
--sd-duration-fast: 120ms  --sd-duration: 200ms  --sd-duration-slow: 300ms  --sd-ease: cubic-bezier(.2,.8,.2,1)
--sd-z-sticky: 100  --sd-z-drawer: 100000  --sd-z-modal: 100010  --sd-z-toast: 100020
```

The modal and drawer layers sit above WordPress's toolbar, which uses z-index 99999.

**Breakpoints:** 390 · 782 (WordPress mobile) · 1024 · 1280 · 1440.

---

## 2. Components (anatomy · variants · sizes · states)

**Global states:** every interactive component needs **default, hover, focus-visible, active/pressed, disabled**, plus **loading** and **error** where they apply.

**Minimum hit area:** 40px desktop, 44px on touch.

### Actions
| Component | Variants | Sizes | Notes |
|---|---|---|---|
| **sd-Button** | primary (ink pill, white text, optional orange-dot icon circle) · secondary (white, gray-300 border) · tertiary/ghost · accent (ember gradient, ink text; one per screen at most) · danger (outline) · danger-solid | sm 32 · md 40 · lg 48 | Leading and trailing icon slots; loading spinner replaces the icon; full-width option |
| **sd-IconButton** | outline circle (reference arrows) · ghost · filled ink | 32 · 40 · 44 | Always has an aria-label and a tooltip |
| **sd-SplitButton** | primary + menu | md | "Save ▾" |
| **sd-CopyChip** | inline mono chip + copy icon | – | "Copied ✓" state for 1.5s |
| **sd-Link** | inline (ember-700) · standalone with arrow ↗ | – | |

### Navigation
| Component | Notes |
|---|---|
| **sd-TopBar** | Floating pill: logo, tabs, search, safe-mode pill, primary action. Collapsed variants at 1280 and 782. |
| **sd-Tabs** | Underline tabs with an ember dot indicator · pill tabs (status filters) · vertical tabs (page-code modal, settings nav) · count badge slot |
| **sd-Stepper** | Horizontal, 5 steps: number/check circle, label, optional "Optional" caption. States: upcoming, current (ink ring + ember dot), complete (ink fill + check), error. Compact mobile variant: "Step 2 of 5" plus a progress bar. |
| **sd-Breadcrumb** | "Snippets / Title" |
| **sd-Pagination** | "1–20 of 312", prev/next icon buttons, per-page select; a "Load more" variant |
| **sd-CommandPalette** | Search input, grouped results, keyboard hints |

### Inputs
| Component | Notes |
|---|---|
| **sd-TextField** | Label, optional hint, input (40px, radius 12, gray-400 border), prefix/suffix slot, error message, character count. Mono variant for IDs and hooks. |
| **sd-TextArea** | Auto-grow; mono variant for plain-text files |
| **sd-NumberStepper** | − value + (priority, paragraph number, every N) |
| **sd-Select** | Native-looking custom select with grouped options (optgroups) |
| **sd-Combobox** | Searchable single/multi select with async results, loading, empty and keyboard states |
| **sd-TokenInput** | Chips + input; creates new tags; removable chips; overflow "+3" |
| **sd-Checkbox** / **sd-Radio** | Custom-styled; 20px; ink check |
| **sd-Switch** | Brand toggle: off gray-200 track and white knob; **on ink track and orange-gradient knob with ✓**. sm 16 / md 20. Busy state. |
| **sd-SegmentedControl** | 2–5 options, pill container, ink selected thumb |
| **sd-RadioCard** | Large selectable card: icon/illustration, title, description, check badge. Used for scope, schedule mode, load strategy, placement. |
| **sd-DateTimePicker** | Calendar popover + time; range variant |
| **sd-TimeRange** | from–to |
| **sd-WeekdayChips** | Mon–Sun toggles |
| **sd-KeyValueField** | name + value (value hidden for exists/does not exist) |
| **sd-FileDropzone** | Idle, drag-over (ember dashed border + glow), file selected, error |
| **sd-SearchField** | Pill search with ⌘K hint and clear |

### Selection (targeting)
| Component | Notes |
|---|---|
| **sd-PlacementCard** | Mini page wireframe (thin gray lines) with an **orange dot at the injection spot**, title, description. States: default, hover, selected (ember ring, ember-50 fill, check badge), disabled with reason tooltip. Inline "extra" area for a stepper, hook input or shortcode chip. Needs about 20 wireframe illustrations (head, body-open, footer, before/after content, after paragraph N, between posts, excerpt, admin header/footer, login, block editor, WooCommerce ×6 motifs, custom hook, shortcode, PHP everywhere/front/admin/on-demand). |
| **sd-ContentCard** | 16:10 thumbnail (image or generated placeholder), title (2 lines), path (mono, truncates in the middle), status chip, date, check circle. Variants: grid and list row. States: default, hover, selected, included-by-"select all" (locked, with an exclude affordance), disabled. Child-pages switch slot. |
| **sd-SelectAllCard** | "Select all pages (42)" switch card with a description of future items |
| **sd-TermTree** | Hierarchical checkbox tree with counts and expand/collapse |
| **sd-UrlRuleRow** | Match-type select, value field, remove; plus **sd-UrlTester** (input + ✓/✗ result) |
| **sd-SelectionTray** | Grouped expandable chips, clear all, match estimate |
| **sd-TargetingSentence** | Plain-language sentence with inline highlighted chips (ember-50 background, ink text), clickable to jump to a step |
| **sd-RuleGroup** / **sd-RuleRow** | Advanced builder: group card, OR pill between groups, AND label, rule/operator/value controls, remove |

### Data display
| Component | Notes |
|---|---|
| **sd-Table** | Header (sortable indicator), rows (comfortable 64 / compact 44), selectable, hover, skeleton rows, empty slot. Mobile card transform. |
| **sd-BulkBar** | Floating ink pill with count and actions |
| **sd-TypeChip** | 5 types × (icon + label); sm/md |
| **sd-Badge** | success · warning · danger · neutral · info · ember; with dot/icon; sm/md |
| **sd-StatusDot** | running (ember gradient, pulse) · off (gray) · error (red !) |
| **sd-StatTile** | Label, big number, trend/caption, icon, clickable |
| **sd-KeyValueList** | "Where it runs" rows with icons |
| **sd-Timeline** | Revision history list |
| **sd-Diff** | Split and inline code diff, line numbers, added/removed tints |
| **sd-Avatar** | User initials/photo, 24/32 |
| **sd-Tooltip** | ink background, white text, 12px, with an arrow |
| **sd-Popover** / **sd-DropdownMenu** | Menu items with icons, shortcuts, a danger item, separators |

### Code
| Component | Notes |
|---|---|
| **sd-CodeEditor** | Frame: toolbar (file tab with type icon + name, lint status pill, actions), gutter with line numbers and error markers, body, status bar (Ln/Col, lines, hint). Themes: light and dark (tokens in brand §8). States: default, focus, lint ok, lint error (line wash + gutter marker + tooltip), read-only (lock chip), full screen. |
| **sd-LintStatus** | ok (✓ success) · error (red, line + message, click to jump) · checking (spinner) |
| **sd-SmartTagPalette** | Search, groups, tag rows (mono token + description), modifiers footer |
| **sd-CodePreview** | Read-only snippet with highlighted `%%placeholders%%` |
| **sd-Console** | Dark output drawer for "Run now" (success/error header, mono output, copy) |

### Feedback
| Component | Notes |
|---|---|
| **sd-Banner** | info · success · warning · danger. Icon, title, text, primary/secondary actions, dismiss. Full-width and inline sizes. |
| **sd-Toast** | ink background, icon, message, optional action (Undo/Retry), auto-dismiss 4s, stacked |
| **sd-ConfirmDialog** | Title, consequence text, cancel + confirm (danger variant) |
| **sd-EmptyState** | Line-art illustration with orange dot, title, text, 1–3 actions |
| **sd-Skeleton** | Text, row, card, editor |
| **sd-ProgressRing** / **sd-ProgressBar** | Onboarding checklist, import |
| **sd-InlineValidation** | Error text with icon under fields |

### Overlays & surfaces
| Component | Notes |
|---|---|
| **sd-Card** / **sd-Panel** | White, radius 20, border gray-200, header (title + actions), body, footer; "glow" highlight variant |
| **sd-SettingsRow** | Label + description on the left, control on the right |
| **sd-SaveBar** | Sticky bottom bar: "Unsaved changes · Discard · Save" |
| **sd-Modal** | sm 480 · md 640 · lg 880 · full-screen (wizard, page code). Header, scroll body, footer. |
| **sd-Drawer** | Right side: 560 (quick view) · 720 (history) |
| **sd-BottomSheet** | Mobile filters, targeting summary |
| **sd-TemplateCard** | Library: illustration icon, title, description, chips, Add button; added and blocked states |
| **sd-FileCard** | Site files: filename, path, status pill, editor, actions |
| **sd-PluginSourceRow** | Import: icon, name, count, keep-active switch, Import button |

### WordPress-integrated pieces (follow Gutenberg's look inside WordPress panels)
| Component | Notes |
|---|---|
| **sd-GutenbergSidebar** | 280px panel sections; uses WordPress components with ink/ember accents |
| **sd-BlockPlaceholder** | Block canvas placeholder states |
| **sd-AdminBarMenu** | Dark toolbar dropdown rows with type dots |
| **sd-MetaBoxFallback** | Classic-editor box with tabs |

---

## 3. Iconography & illustration kit
- **Icons:** 24px grid, 1.5px stroke, round caps and joins, ink. The "active" variant adds a 6px ember dot. Required set (about 60):
  - Types: php, html, css, js, universal.
  - Placements: head, body, footer, content, paragraph, archive, admin, login, editor, hook, shortcode, cart, checkout, product, shop, thank-you, account.
  - Content: page, post, product, category, tag, special, url, home, search, 404.
  - Devices: desktop, mobile, browser, os.
  - Actions: add, edit, duplicate, export, import, trash, restore, copy, history, run, search, filter, sort, more, close, check, lock, info, warning, error, external, drag.
  - States: running, paused, safe mode, shield-check (signed), shield-alert (tampered), clock (scheduled), consent.
- **Illustrations** (line art with one glowing orange dot, reference style): welcome, empty snippets, empty search, crash recovered ("broken orbit"), tamper alert (shield), safe mode (life ring), import (arrows into a dock), library categories (7 icons), all done/success.

## 4. Accessibility checklist (per component)
- Contrast: text ≥ 4.5:1, large text and icons/controls ≥ 3:1 (use the token pairs as specified).
- Focus-visible ring on every interactive element (`--sd-focus-ring`); never remove outlines without a replacement.
- ARIA patterns:
  - Tabs: `tablist`/`tab`/`tabpanel`, arrow-key navigation.
  - Switch: `role="switch"`, `aria-checked`.
  - Selectable cards: checkbox or radio semantics.
  - Stepper: `aria-current="step"`.
  - Combobox: ARIA 1.2.
  - Modal: dialog, focus trap, Esc.
  - Toast: `aria-live="polite"`.
  - Table: sortable headers with `aria-sort`.
- Colour is never the only signal: type chips have labels, and status has icons or text.
- Motion: respect `prefers-reduced-motion`.
- RTL: use logical properties (margin-inline-start and so on); mirror directional icons (arrows, chevrons), not logos or code.
- Text expansion: layouts survive +40% string length.

## 5. Hand-off requirements
- Name every component and variant exactly as above (e.g. `sd-Button / primary / md / hover`).
- Use tokens only (no hard-coded hex values inside components).
- Provide a **Design System page**: colour, type, spacing, radius, shadows, icons, illustrations, and every component with all states.
- For each screen, annotate:
  - Which components are used.
  - Scroll and sticky behaviour.
  - Empty, loading and error states.
  - Responsive changes.
- If you produce code (HTML/CSS or React), keep it semantic and accessible, use the CSS variables above, and scope it under `.sd-app` so it can be ported into the WordPress plugin.
