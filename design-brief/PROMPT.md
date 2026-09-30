# Claude Design prompts for ScriptDock

Paste the **kickoff prompt** first, with all brief files attached. Then send the **phase prompts** one at a time, reviewing each phase before moving on. Each phase builds on the one before it.

---

## Kickoff prompt (paste this first)

```
You are the lead product designer for ScriptDock, a premium-quality but completely free WordPress plugin that lets site owners add PHP, HTML, CSS and JavaScript anywhere on their site: site-wide, on specific pages, or by rules. It has crash protection, tamper protection, safe mode, revisions, a snippet library, per-page code and smart loading. It will run inside the WordPress 7.1 admin.

I've attached the full brief. Read all of it before designing anything:
- 01-features-and-constraints.md: every feature, screen, field, state and message the product has; what WordPress fixes around our screens and where we have freedom; the new multi-step "Where should it run?" targeting concept; confirmed decisions.
- 02-brand-guidelines.md: brand direction, colour tokens (accessibility-checked), typography, shapes, iconography, motion, dark code editor theme, and how the palette fits WordPress.
- 03-screens-and-flows.md: the design brief for every screen (S01–S21), the user flows to prototype and the responsive deliverables.
- 04-design-system.md: token names, the full component list with variants and states, accessibility and hand-off rules.
- 05-sample-data.md: realistic content (a coffee roaster's WooCommerce site). Use it everywhere; never use lorem ipsum.
- brand-reference.webp: a website the owner loves. Take its style as inspiration: white surfaces, bold near-black type, one warm orange gradient accent, soft peach glows, pill buttons, big rounded cards, thin-line geometric illustrations with a glowing orange dot. Do NOT copy its logo, name or copy. ScriptDock gets its own identity in that style.

Non-negotiables:
1. Every ScriptDock screen sits inside a realistic WordPress admin frame: 160px dark sidebar (#1E1E1E, active item highlighted in WordPress blue #3858E9) and 32px dark toolbar. We don't redesign that frame; our design lives in the content area. Leave a notice zone under our top bar for WordPress and other plugins' notices.
2. The brand orange (#FF6400) is an accent. Never use it for small text on white, and never put white text on orange: orange fills carry ink text. Orange text on white uses #C2410C. Follow the token pairs in 02; they pass WCAG AA.
3. Premium, calm and trustworthy, at the level of polish of the best SaaS dashboards, but warm and branded. Generous spacing, clear hierarchy, strong states, no clutter, no upsell banners (the product is free).
4. Build from the component library (04). Use the token and component names given there, so engineering can map your work straight to code.
5. Accessibility: WCAG 2.2 AA contrast, visible focus, keyboard patterns, no colour-only signals. Layouts must survive +40% text length and mirror for RTL.
6. Desktop artboards at 1440 × 900. Responsive variants are listed in 03 §4.
7. If you produce code or interactive prototypes: semantic HTML, CSS custom properties from 04, everything scoped under a .sd-app root class.

We'll work in 8 phases. Start with PHASE 1 now and stop when it's done, so I can review:

PHASE 1: Foundations & component library
- Wordmark and mark options for "scriptdock": explore using the orange gradient dot as part of the logo, in the spirit of the reference. Include a 20×20 single-colour sidebar icon.
- Colour, typography, spacing, radius, shadows and motion tokens (from 02/04), shown as a style guide.
- Icon style sample (about 20 of the 60 icons in 04 §3) and 3 illustrations in the line-art-plus-orange-dot style (welcome, empty snippets, crash recovered).
- Every component in 04 §2 with all its variants and states (default, hover, focus, active, disabled, loading, error, selected), on one or more Design System pages.
- Special attention to the signature pieces: the brand toggle (ink track + orange-dot knob), the primary ink pill button with the orange circle icon, the placement card (mini page wireframe with an orange dot where the code goes), the selectable content card, the type chips, the code editor frame (light and dark), and the floating top-bar pill.
Finish with a short list of any open questions or suggestions.
```

---

## Phase 2: App shell, Overview & Snippets list

```
PHASE 2. Using the approved component library, design:
- The app shell (03 §0): WordPress frame, our floating top-bar pill (full, ≤1280 collapsed and ≤782 mobile variants), notice zone with one sample WordPress notice, and the page-header pattern.
- S01 Overview in these states: healthy; issues present (1 needs review + 1 auto-deactivated error); brand-new site (empty, with onboarding CTA); safe mode on.
- S03 Snippets list with the 24 snippets from 05-sample-data.md: default view, a row hover with the ⋯ menu open, 4 rows selected with the floating bulk bar, the Needs review view with its explanatory banner, the Errors view, the Trash view, no search results, the empty state (no snippets at all), loading skeleton, and compact density.
- S03b Quick-view drawer for "GA4 tag".
Keep the numbers and content consistent with the sample data.
```

## Phase 3: Snippet editor

```
PHASE 3. Design S04, the Snippet editor, with the sticky editor bar, banners, main column (type selector, code card, "Where it runs" summary card, Loading card) and the right sidebar (Details, Safety, Run now, History, Options). Show these states:
1. New HTML snippet (empty editor with example placeholder)
2. "GA4 tag" (HTML) saved and active, with the targeting summary card filled in
3. "Wholesale prices" (PHP) with the live syntax error on line 3 (red line wash, lint status, gutter marker)
4. "Returning-customer discount" after it was switched off by a fatal error (danger banner with "Jump to line 12")
5. "Hotjar (old)" needing review (tamper banner with Approve / View history)
6. Read-only mode for a user without PHP rights
7. "Newsletter signup form" with the Shortcode placement (copy chip)
8. "Warm the cache" (on-demand PHP) with the dark Run-now console showing output
9. Dark code editor theme
10. Full-screen code mode
Also design S06 History drawer (timeline + split diff, using the Hotjar tamper diff) and S07 Smart tags palette, and the unsaved-changes dialog.
```

## Phase 4: Targeting wizard (flagship)

```
PHASE 4. The flagship experience: S05 "Where should it run?". Follow 01 §7 and 03 S05 closely and make it feel premium, fast and delightful. Design a full-screen overlay with a stepper, a main area, a sticky summary rail (live sentence + selection tray + warnings) and a bottom bar.
- Step 1 Placement: all placement card groups with mini page wireframes (orange dot = where the code goes). Show a selected "After paragraph" card with its inline number stepper, the PHP variant ("When to run" cards) and a disabled card with a reason tooltip.
- Step 2 Pages & content:
  - The three scope cards.
  - Pages tab in grid view: "Select all pages (24)" toggle, search, filters, cards with thumbnails and paths, 3 pages selected, a parent page with the "Include 3 child pages" switch, and a Draft and a Scheduled page.
  - The same tab in list view.
  - Posts tab with "Select all posts" switched on (cards locked as included, with "Exclude some").
  - Categories & tags tab with the tree and the "Apply to" choice.
  - Special pages tab.
  - URL rules tab with the "Test a URL" field showing ✓ and ✗.
  - Selection tray with grouped chips and "≈ 14 pages".
- Step 3 Audience: default "Everyone"; then logged-out + mobile & tablet selected, with caching info chips.
- Step 4 Schedule: Mon–Fri 09:00–17:00.
- Step 5 Review: the big sentence from 05 ("Targeting example") with chips, per-step summary cards and one warning.
- Advanced rules mode (S05f): the rule builder with 2 OR groups.
- Mobile (390): step 2 as full-screen pages with the summary as a bottom sheet.
Annotate the micro-interactions: selection, step transitions, hover, keyboard.
```

## Phase 5: Block editor integration & front-end inspector

```
PHASE 5. Design how ScriptDock lives inside WordPress's block editor and front end (03 S08, S15, S16):
- The block editor (WordPress 7.1 look) editing the "Pricing" page: the ScriptDock dot button in the top bar with a badge, and our 280px sidebar panel (Page code rows with line counts, "Site-wide snippets on this page" with "Chat widget (Crisp)" switched off, "Turn off all site-wide code here"). Inside WordPress's own panel, follow Gutenberg's component style with our ink and ember accents.
- S08b, the full-screen branded "Page code · Pricing" modal on the CSS tab (vertical tabs with content dots, dark editor, smart tags, Done).
- S08c, the classic-editor fallback meta box.
- S16, the "ScriptDock Snippet" block placeholder in all 4 states.
- S15, the front-end admin bar inspector on /pricing/ (normal and safe-mode red states), shown over a blurred mock of the storefront.
```

## Phase 6: Library, Header & Footer, Site Files, Import & Export, Settings, Tags

```
PHASE 6. Design the remaining main screens (03 S09–S14):
- S10 Library: the grid with category pills and counts, search, cards with category line-art icons, Added and blocked states, empty search. Then S10b, the template modal for "Google Analytics 4": code preview with highlighted placeholder, the field with a validation error ("GA-12345"), then valid; Where it runs; consent; Activate now; the 2-part variant for Google Tag Manager.
- S09 Header & Footer: filled state with the sticky save bar visible, plus the tampered warning state.
- S11 Site Files: ads.txt Live, llms.txt Live, security.txt Off, robots.txt in Add mode, one file "Overridden by a server file", and the subfolder warning.
- S12 Import & Export: WPCode and HFCM detected (WPCode still active, with warning), drag-over dropzone, and S12b import results with the 2 warnings.
- S13 Settings: every section, the sticky save bar, the safe mode card, the tamper card with "1 needs review", the PHP card, and the danger-zone uninstall.
- S14 Tags modal and the tag chip input in the editor.
```

## Phase 7: Onboarding, command palette, feedback set, email, system states

```
PHASE 7. Design:
- S02 Onboarding: all 4 steps, with peach glow and illustrations.
- S20 Command palette with the results for "ga".
- S17 feedback set: every banner variant in context, the toast stack (success, error with Retry), and all confirm dialogs listed in 03 S17.
- S18 Safe mode: top-bar pill, landing banner, admin bar state.
- S19 HTML email "We switched off a snippet to keep your site running" (600px).
- S21 system states: skeletons, 5 empty-state illustrations, a load-error page, a save-failed banner, a no-permission page.
```

## Phase 8: Responsive, RTL & hand-off

```
PHASE 8. Final pass:
- Responsive variants from 03 §4: Overview, Snippets, Editor and Targeting at 1024 and 390; Library at 390.
- An RTL (right-to-left) version of the Snippets list.
- A consistency audit: same tokens, spacing and component names everywhere. Fix anything off-system.
- A hand-off summary page: screen index (S01–S21) with links, the component usage per screen, sticky and scroll behaviours, the interaction notes, and any new components you introduced (named in the sd- convention, with their variants and states).
List anything you changed from the brief and why.
```

---

## Tips while iterating
- Ask for one fix at a time and name the screen and component ("S05 step 2, sd-ContentCard: make the selected state stronger").
- If something breaks a rule in the brief (contrast, orange text, WordPress frame), point to the section.
- When a phase is approved, say "Approved. Keep this as the reference for later phases."
