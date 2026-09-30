# ScriptDock: sample data for realistic mockups

Use this content in every screen. Numbers are consistent across screens.

## Site
- **Site:** Lumen Coffee Roasters: an online coffee shop (WooCommerce) with a blog and brewing guides.
- **URL:** `https://lumencoffee.com`.
- **Admin:** **Maya Chen** (Administrator), avatar initials "MC".
- **WordPress:** 7.1 · theme "Lumen" · WooCommerce active · Complianz (cookie consent) active.
- **Timezone:** America/New_York.

## Snippet list (24 snippets)
Counts: **All 24 · Active 21 · Inactive 3 · Needs review 1 · Errors 1 · Trash 2**.

| # | ID | Title | Type | Placement | Targeting summary | Status | Badges | Prio | Tags | Updated |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | 101 | GA4 tag | HTML | Site header | Entire site | Active | Consent: Statistics | 5 | analytics | 2 hours ago |
| 2 | 102 | Google Tag Manager (head) | HTML | Site header | Entire site | Active | – | 1 | tracking | 3 days ago |
| 3 | 103 | Google Tag Manager (body) | HTML | After opening body | Entire site | Active | – | 1 | tracking | 3 days ago |
| 4 | 104 | Meta Pixel | HTML | Site header | Entire site | Active | Consent: Marketing | 10 | pixel, marketing | 1 week ago |
| 5 | 105 | Meta purchase event | HTML | Order received page | Thank-you page | Active | Consent: Marketing | 10 | woocommerce, pixel | 1 week ago |
| 6 | 106 | Chat widget (Crisp) | JavaScript | Site footer | Everywhere except Cart, Checkout | Active | On interaction | 10 | support | 2 days ago |
| 7 | 107 | Holiday announcement bar | HTML | After opening body | Entire site · Dec 1–Dec 26 | Active | Scheduled | 10 | seasonal, design | 5 days ago |
| 8 | 108 | Free shipping banner | HTML | Before post content | Products · Mobile & tablet | Active | Conditional | 10 | woocommerce | yesterday |
| 9 | 109 | Brand fonts & colours | CSS | Site header | Entire site | Active | Cached file | 5 | design | 3 weeks ago |
| 10 | 110 | Pricing page tweaks | CSS | Site header | 1 page (Pricing) | Active | Conditional | 10 | design | 4 days ago |
| 11 | 111 | Checkout trust badges | Universal | Before checkout form | Checkout | Active | – | 10 | woocommerce | 2 weeks ago |
| 12 | 112 | Disable emojis | PHP | Run everywhere | – | Active | – | 10 | performance | 1 month ago |
| 13 | 113 | Disable XML-RPC | PHP | Run everywhere | – | Active | – | 10 | security | 1 month ago |
| 14 | 114 | Excerpt length: 30 words | PHP | Run everywhere | – | Active | – | 10 | content | 1 month ago |
| 15 | 115 | Wholesale prices | PHP | Front end only | Logged in · Role: Wholesale customer | Active | Conditional | 20 | woocommerce | 6 days ago |
| 16 | 116 | Returning-customer discount | PHP | Run everywhere | – | **Inactive** (switched off automatically) | **Error** | 10 | woocommerce | 25 min ago |
| 17 | 117 | Hotjar (old) | HTML | Site header | Entire site | Active, **paused** | **Needs review** | 10 | analytics | changed outside ScriptDock 1 hour ago |
| 18 | 118 | Login page logo | CSS | Login page header | – | Active | – | 10 | branding | 2 months ago |
| 19 | 119 | Admin footer credit | PHP | Admin only | – | Active | – | 10 | admin | 2 months ago |
| 20 | 214 | Newsletter signup form | HTML | Shortcode or block only | `[scriptdock id="214"]` | Active | – | 10 | marketing | 1 week ago |
| 21 | 121 | Maintenance mode | PHP | Front end only | – | **Inactive** | – | 1 | maintenance | 3 months ago |
| 22 | 122 | Reading progress bar | HTML | After opening body | Single posts | Active | **Test mode** | 10 | design | 1 hour ago |
| 23 | 123 | Wide block editor | CSS | Block editor | – | Active | – | 10 | admin | 2 months ago |
| 24 | 124 | Warm the cache | PHP | Run on demand | – | **Inactive** | – | 10 | tools | 3 weeks ago |

**Notes for the second line of list rows** (examples):
- #1 "Google Analytics 4 · G-8XK2N4P1QZ"
- #6 "Loads after the first scroll or tap to keep pages fast"
- #15 "Shows net wholesale prices to approved accounts"

## Errors & alerts
- **#116 Returning-customer discount:**
  - Error: `Call to undefined function wc_get_customer_order_count()` on line 12.
  - Switched off automatically 25 min ago while loading `/cart/`.
  - Email sent to maya@lumencoffee.com.
- **#117 Hotjar (old), needs review:** the code changed outside ScriptDock. The diff shows an added line near the top:
  ```html
  <script src="https://cdn-analytics-cache.xyz/h.js" async></script>
  ```
  This looks like injected code, so it is a good showcase for tamper protection. The rest is the normal Hotjar snippet.

## Overview numbers
- **Running:** 20 (21 active minus 1 paused for review) · **Inactive:** 3 · **Errors:** 1 · **Page scripts:** 4 pages.
- **Getting started:** 3 of 4 done (Save safe mode link ✓, Add first snippet ✓, Import ✓, Choose page-script content types ☐).

## Code samples for editor mockups
**PHP with a live syntax error** (line 3 is missing a semicolon):
```php
add_filter( 'woocommerce_get_price_html', function ( $price, $product ) {
	if ( ! current_user_can( 'wholesale_customer' ) ) {
		return $price
	}
	return wc_price( $product->get_meta( '_wholesale_price' ) ) . ' <small>net</small>';
}, 10, 2 );
```

**HTML (GA4):**
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-8XK2N4P1QZ"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-8XK2N4P1QZ', { page_title: '{{page_title|js}}' });
</script>
```

**CSS (Pricing page tweaks):**
```css
.pricing-table .plan--featured {
	border: 2px solid #111;
	box-shadow: 0 24px 48px rgba(17, 17, 17, .1);
}
.pricing-table .plan__price { font-size: 44px; letter-spacing: -0.02em; }
```

**JavaScript (Chat widget):**
```js
window.$crisp = [];
window.CRISP_WEBSITE_ID = '7f3c2a1e-5b8d-4c6f-9a2e-1d4b6c8e0f21';
( function () {
	var s = document.createElement( 'script' );
	s.src = 'https://client.crisp.chat/l.js';
	s.async = true;
	document.head.appendChild( s );
} )();
```

## Content for the targeting picker
**Pages (24):**
- **Top-level pages:**
  - Home *(front page)* `/`
  - About us `/about/`, with children:
    - Our story `/about/our-story/`
    - Team `/about/team/`
  - Pricing `/pricing/`
  - Contact `/contact/`
  - Wholesale `/wholesale/`, with child:
    - Wholesale application `/wholesale/apply/`
  - Blog *(posts page)* `/blog/`
  - Shop `/shop/`
  - Cart `/cart/`
  - Checkout `/checkout/`
  - My account `/my-account/`
  - Brewing guides `/brewing-guides/`, with children:
    - Pour-over `/brewing-guides/pour-over/`
    - French press `/brewing-guides/french-press/`
    - Cold brew `/brewing-guides/cold-brew/`
  - Coffee subscriptions `/subscriptions/`
  - Gift cards `/gift-cards/`
  - FAQ `/faq/`
  - Shipping & returns `/shipping-returns/`
  - Privacy policy `/privacy-policy/`
  - Terms of service `/terms/`
- **Non-published pages:**
  - Careers `/careers/` (**Draft**)
  - Holiday gift guide `/holiday-gift-guide/` (**Scheduled**, Nov 20)

Featured images (describe as placeholders): coffee beans, a roastery interior, a pour-over kettle, a team photo, a latte-art close-up. Pages without an image use the generated gradient placeholder.

**Posts (18 total; show these):**
- How we source our single-origin beans · Origins · Sep 12
- The perfect pour-over in 5 steps · Brewing · Sep 8
- Meet Ana, our new head roaster · Behind the scenes · Aug 30
- Ethiopia Yirgacheffe is back · News · Aug 22
- Cold brew at home, the easy way · Recipes · Aug 15
- Why freshness matters more than origin · Brewing · Aug 2
- Our 2026 sustainability report · News · Jul 28
- Espresso ratios explained · Brewing · Jul 19

**Categories:** News (8) · Brewing (14) · Origins (6) · Behind the scenes (5) · Recipes (9).

**Tags:** espresso, pour-over, decaf, sustainability, single-origin.

**Products (8):**
| Product | Price | SKU |
|---|---|---|
| Ethiopia Yirgacheffe 250 g | $18 | LUM-ETH-250 |
| Colombia Huila 250 g | $16 | LUM-COL-250 |
| House espresso blend 1 kg | $48 | LUM-ESP-1K |
| Decaf Swiss Water 250 g | $17 | LUM-DEC-250 |
| Monthly subscription | $32/mo | LUM-SUB-M |
| Pour-over starter kit | $64 | LUM-KIT-PO |
| Burr grinder | $129 | LUM-GRD-01 |
| Gift card | $25–$100 | LUM-GIFT |

**Product categories:** Coffee (4) · Equipment (2) · Subscriptions (1) · Gifts (1).

**Custom post type "Workshops" (3):** Latte art basics · Home espresso 101 · Cupping night.

**Roles:** Administrator, Editor, Author, Customer, Wholesale customer, Subscriber.

**Targeting example (flow 5):**
> Runs in the **site header** on **3 pages** (About us, Pricing, Contact) and **posts in News**, for **logged-out** visitors on **mobile & tablet**, **Mon–Fri 09:00–17:00**.
>
> The estimate reads "≈ 14 pages".

## Library
- **Highlight cards:** Google Analytics 4, Meta Pixel, Microsoft Clarity.
- **Example field:** Measurement ID `G-8XK2N4P1QZ`. The error state is triggered by "GA-12345".

## Import & Export
- **Detected:** WPCode · 12 snippets (plugin still active) · Header Footer Code Manager · 3 snippets.
- **Import result:** "Imported 12 snippets · 9 active". Warnings:
  - "'Promo popup' was SCSS and was imported as plain CSS."
  - "'Mobile hero' used a WPCode condition that has no ScriptDock equivalent. Check its conditional logic."

## Revisions (GA4 tag)
1. Maya Chen · 2 hours ago · **Current**
2. Maya Chen · yesterday · "Added page_title parameter"
3. Maya Chen · Sep 10
4. Leo Park · Sep 3
5. Maya Chen · Aug 28 · Created from library

## Page scripts example
The **Pricing** page has:
- **CSS:** 14 lines.
- **Header:** 1 line (a verification meta tag).
- **Switched off here:** "Chat widget (Crisp)".
- **Other site-wide snippets on this page:** GA4 tag, Google Tag Manager (head/body), Meta Pixel, Brand fonts & colours.

## Admin bar inspector (viewing /pricing/)
"ScriptDock · 5", listing:
- GA4 tag (HTML · Site header)
- Google Tag Manager (head) (HTML · Site header)
- Google Tag Manager (body) (HTML · After opening body)
- Meta Pixel (HTML · Site header)
- Pricing page tweaks (CSS · Site header)

Plus "This page has its own code".

## Settings values
- Auto-deactivate: on · Email alerts: on (maya@lumencoffee.com).
- Page scripts: Posts ✓ Pages ✓ Products ✓ Workshops ☐.
- Cached files: on · Minify CSS: on.
- Editor theme: Dark · Revisions: 20 · Admin bar inspector: on · Delete data on uninstall: off.
- Safe mode link: `https://lumencoffee.com/wp-admin/index.php?scriptdock_safe_mode=q7m2x9w4k8r1t5v3n6p0`.
- Tamper protection: On (using WordPress security keys) · 1 snippet needs review.
- PHP snippets: Available.
- About: version 1.0.0 · 20 snippets running.
