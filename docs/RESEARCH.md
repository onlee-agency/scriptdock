# Research: premium snippet & script managers (September 2026)

## 1. The WordPress.org listing question

The WordPress.org Plugin Developer FAQ (last updated **1 September 2026**) says:

> "We also do not accept new plugins that allow arbitrary code insertion or execution. Examples include PHP or JavaScript editors, file managers, and AI tools that generate code intended to be executed on the site. HTML output is permitted, provided it is properly escaped and handled securely."
> — https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#are-there-plugins-you-dont-accept

WPCode, Code Snippets and FluentSnippets remain listed because they were approved years ago; the review team has said that is not a basis for approving new ones. **Decision:** ScriptDock is distributed from GitHub with a built-in updater, and is built to WordPress coding and security standards anyway (Plugin Check passes except for the expected "updater detected" notice).

If a WordPress.org presence is wanted later, the only realistic route is a separate "no free-form code" edition (validated tracking tags, consent handling, site files, per-page toggles); even that faces the "hundreds of similar plugins" rule.

## 2. Competitors

| Plugin | Price (1 site / top tier) | Paywalled in the free version | Standout | Common complaints |
|---|---|---|---|---|
| **WPCode** (3M+ installs) | $49/yr intro, renews $99; up to $349 | Page-level scripts, device targeting, revisions, scheduling, testing mode, smart tags, blocks, CSS-selector insertion, Woo/EDD locations, file editor, access control | Largest library, conversion pixels add-on | Upsells, per-page scripts paywalled, hacked sites hiding malware in its storage |
| **Code Snippets** (1M+) | $39/yr; lifetime $137+ | CSS/JS snippets, conditions, cloud, blocks, minify | Safe mode, imports, snippet locking | Updates breaking sites, CSS/JS paid |
| **Header Footer Code Manager** (600k+) | Pro $35/yr | PHP, after-`<body>`, logged-in targeting, priority | Simple page targeting | Page picker does not scale |
| **Simple Custom CSS & JS** (600k+) | Pro $48.50 perpetual | URL rules, revisions, SASS/LESS, minify, preview links | Shareable preview links | No page targeting free, no PHP |
| **Woody Code Snippets** (50k+) | $39/yr | Device/geo conditions, revisions | Universal & ad snippets | Review-nag popup; CVE-2024-3105 contributor RCE |
| **WPCodeBox 2** | $39–79/yr, $199 lifetime | Paid only | Monaco editor, SCSS, condition builder, MCP support | No free tier |
| **Advanced Scripts / Scripts Organizer** | Lifetime $20–69 | Paid only | SCSS partials, file sync | Builder-centric, slow updates |
| **FluentSnippets** | Free | — | File-based storage, standalone mode | No revisions, no per-page UI |
| **Perfmatters** | $30–125/yr | Paid only | Delay JS, script manager | "Delay all" breaks sites |

Sources: vendor pricing and docs pages and WordPress.org support forums, collected 18 September 2026 (wpcode.com, codesnippets.pro, draftpress.com, silkypress.com, woodysnippet.com, wpcodebox.com, perfmatters.io, wordpress.org/plugins/*).

## 3. Feature comparison

✅ included, 💲 paid add-on/tier, — not available

| Feature | ScriptDock (free) | WPCode Pro | Code Snippets Pro | HFCM Pro | SCCJ Pro |
|---|---|---|---|---|---|
| PHP / HTML / CSS / JS / Universal | ✅ | 💲 | 💲 | 💲 (no Universal) | CSS/JS/HTML only |
| Head / body / footer, content & paragraph insertion | ✅ | ✅ | partial | ✅ | head/footer |
| WooCommerce locations | ✅ | 💲 | — | — | — |
| Custom action hook location | ✅ | — | — | — | — |
| Per-page code box (block + classic editor) | ✅ | 💲 | — | — | — |
| Disable site-wide code per page | ✅ | — | — | — | — |
| AND/OR conditional logic, 25+ rules | ✅ | 💲 | 💲 | partial | 💲 URL only |
| Device, browser, OS rules | ✅ | 💲 | — | device | — |
| Custom field / user meta / PHP function rules | ✅ | 💲 | — | — | 💲 |
| Scheduling | ✅ | 💲 | — | — | — |
| Test mode (admins only) | ✅ | 💲 | — | — | 💲 preview |
| Revisions with restore | ✅ | 💲 | — | — | 💲 |
| Live PHP syntax check + test run before activation | ✅ | partial | partial | — | — |
| Fatal error auto-deactivation incl. front end & hooks | ✅ | admin only | activation only | — | — |
| Safe mode (URL, admin bar, constant) | ✅ | ✅ | ✅ | — | — |
| Tamper protection (signed code) | ✅ | — | — | — | — |
| CSS/JS as cached files, CSS minify | ✅ | ✅ | 💲 | — | ✅ / 💲 |
| Defer / idle / on-interaction loading | ✅ | — | — | — | — |
| Cookie-consent gating (WP Consent API) | ✅ | — | — | — | — |
| Smart tags incl. WooCommerce order data | ✅ | 💲 | — | — | — |
| Snippet library | ✅ 47 bundled | ✅ cloud | 💲 cloud | — | — |
| ads.txt / llms.txt / security.txt / robots.txt | ✅ | 💲 (no llms/security) | — | — | — |
| Admin bar "what runs here" | ✅ | ✅ | ✅ | — | — |
| Import from other plugins | ✅ 4 plugins | ✅ | ✅ | — | — |
| WP-CLI | ✅ | — | 💲 | — | — |
| Shortcode + block with attributes | ✅ | 💲 attributes | 💲 | shortcode | 💲 |
| Self-hosted updates, no nags, no tracking | ✅ | — | — | — | — |

## 4. Differentiators built into ScriptDock

1. **Everything free**: no locked features, no upsells, no tracking.
2. **Per-page code** with the ability to switch off site-wide snippets on a page.
3. **Crash protection that finds the exact snippet**, even when the error happens later inside a hook, thanks to the in-memory stream wrapper (no `eval()`, nothing written to disk).
4. **Tamper protection**: HMAC-signed snippets, page code, global code and runtime cache. This targets the "malware hidden in the snippet plugin" attack seen on WPCode sites.
5. **Loading strategies** (defer, idle, first interaction) and **consent gating** via the WP Consent API. No free snippet manager offers these.
6. **Smart tags with safe escaping** and WooCommerce order data verified by order key.
7. **Migration** from WPCode, Code Snippets, HFCM and Simple Custom CSS & JS, including conditions, priorities and tags.
8. **llms.txt and security.txt** editing, which no competitor offers.

## 5. Roadmap (not built yet)

| Idea | Why | Effort |
|---|---|---|
| SCSS/LESS compilation (scssphp, MIT) | Parity with WPCodeBox, SCCJ Pro | Medium |
| Insert before/after/replace a CSS selector | WPCode Pro feature | Medium |
| Reusable named condition sets | Code Snippets Pro feature | Small |
| Network-wide snippets on multisite | Agencies | Medium |
| Revision diff inside the ScriptDock editor | Nicer than core's revision screen | Small |
| Shareable preview links for client approval | SCCJ Pro feature | Medium |
| Scripts on category/tag archive pages | Per-term page scripts | Small |
| Audit log of who changed what | Security teams | Small |
| Password re-confirmation before saving PHP | Extra hardening | Small |
| Git/file sync and "export as plugin" | Developer workflow (WPCodeBox, SnipVault) | Medium |
| WordPress Abilities API / MCP so AI agents can manage snippets | WPCodeBox added it in 2026 | Medium; must stay capability-gated |
| Geo-targeting | WPCode/Woody paid feature | Needs an external GeoIP source |
