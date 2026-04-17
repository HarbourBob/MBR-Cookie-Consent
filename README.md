# MBR Cookie Consent

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2-green?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Downloads](https://img.shields.io/github/downloads/harbourbob/mbr-cookie-consent/total)](https://github.com/harbourbob/mbr-cookie-consent/releases)
[![GDPR](https://img.shields.io/badge/GDPR-compliant-success?style=flat-square)](https://littlewebshack.com)
[![UK DUAA](https://img.shields.io/badge/UK%20DUAA%202025-compliant-success?style=flat-square)](https://littlewebshack.com)
[![IAB TCF](https://img.shields.io/badge/IAB%20TCF-v2.3-orange?style=flat-square)](https://iabeurope.eu)
[![GPC](https://img.shields.io/badge/Global%20Privacy%20Control-supported-blueviolet?style=flat-square)](https://globalprivacycontrol.org)

![MBR Cookie Consent](head.webp)

> **Enterprise-grade privacy compliance for WordPress — GDPR, UK DUAA, CCPA, 20 US state laws, LGPD, PIPEDA, India DPDP, and Global Privacy Control — completely free, forever. No upsells. No vendor lock-in.**

---

## What's New in Version 2.0.0

Version 2.0.0 is a major update that brings the plugin in line with the international privacy law changes that came into force between mid-2025 and early 2026.

### UK Split from EU — Data Use and Access Act 2025

The UK's Data Use and Access Act received Royal Assent on 19 June 2025, and the PECR cookie amendments came into force on 5 February 2026. The UK now has a meaningfully different cookie consent regime from the EU. Analytics cookies used solely for aggregate statistics, functionality cookies, security cookies, and software-update cookies are now **exempt from consent** under the DUAA — but transparency and an easy opt-out are still required. Advertising and marketing cookies still need explicit consent, and PECR fines have jumped to £17.5 million or 4% of global turnover (a 35-fold increase from the old £500,000 cap).

The plugin now detects UK visitors separately from EU visitors. UK visitors see a banner that defaults analytics and preference toggles to ON (reflecting the DUAA exemptions) while still requiring explicit consent for marketing cookies. EU visitors continue to see strict GDPR opt-in for everything.

### Global Privacy Control (GPC) Support

As of January 2026, twelve US states legally require businesses to honour the Global Privacy Control browser signal: California, Colorado, Connecticut, Montana, Nebraska, New Hampshire, New Jersey, Minnesota, Maryland, Delaware, Oregon, and Texas. A new `MBR_CC_GPC_Handler` class detects the `Sec-GPC: 1` header server-side and `navigator.globalPrivacyControl` client-side. When the signal is detected, marketing cookies are automatically suppressed without requiring the visitor to interact with the banner.

California's updated CCPA regulations (effective January 2026) require a visible confirmation when a GPC opt-out is processed. The plugin now shows a brief "Opt-Out Request Honored" toast to satisfy this requirement.

### US Multi-State Privacy Law Coverage

The old single "CCPA" region has been replaced with a broader "US Multi-State" region that reflects the reality of 20 states now having comprehensive privacy laws (including Indiana, Kentucky, and Rhode Island which took effect on 1 January 2026). The banner text for US visitors now references GPC signal support, and the "Do Not Sell or Share My Personal Information" link is shown by default.

### India — Digital Personal Data Protection Act 2023

India is now recognised as a distinct privacy region. The DPDP Act enters enforcement in phases, with Consent Manager registration opening in November 2026 and full compliance mandatory by May 2027. Indian visitors see a granular opt-in consent banner with one-click withdrawal, matching the Act's requirements.

### Early Translation Loading Fix

A WordPress 6.7+ compatibility fix resolves the `_load_textdomain_just_in_time` notice that appeared on sites running WordPress 6.7 or later. The form integration class was calling `__()` during `plugins_loaded` (before `init`), which is no longer permitted. Translation loading is now deferred to point of use.

---

## Why MBR Cookie Consent?

| | MBR Cookie Consent | Typical Premium Plugins |
|---|---|---|
| **Price** | Free forever | £99–£299/year |
| **IAB TCF v2.3** | Included | Premium only |
| **Google Consent Mode v2** | Included | Premium only |
| **Global Privacy Control (GPC)** | Included | Premium only |
| **UK DUAA 2025 compliance** | Included | Premium only |
| **US multi-state (20 states)** | Included | Premium only |
| **40+ Language Auto-Translation** | Included | Premium only |
| **Form Builder Integration** | Included | Premium only |
| **A/B Testing** | Included | Premium only |
| **Geolocation Detection** | Included | Premium only |
| **Multisite Support** | Included | Premium only |
| **Vendor lock-in** | None | Proprietary |

---

## Features

### Consent Management
- **Customisable Banner** — Accept All, Reject All, and Customise options
- **Automatic Script Blocking** — blocks non-essential scripts until explicit consent is given
- **Preference Centre** — granular category-by-category control for visitors
- **Revisit Consent Button** — floating button so visitors can update preferences any time
- **CCPA "Do Not Sell or Share"** — required link for US visitors under CCPA/CPRA
- **Global Privacy Control** — automatic detection and honouring of the GPC browser signal *(v2.0.0)*
- **Consent Logging** — every interaction recorded, exportable to CSV
- **GDPR-Compliant Storage** — IP anonymisation and proper data handling
- **Geolocation Detection** — auto-detects visitor country and displays the appropriate banner *(v1.6.0)*
- **Multisite Support** — network-aware, adjusts settings across sites automatically *(v1.5.0)*

---

### Geolocation and Regional Compliance

The plugin detects visitor location and applies the correct privacy regime automatically.

| Region | Law | Consent Model | Since |
|---|---|---|---|
| **EU (27 states)** | GDPR / ePrivacy Directive | Strict opt-in for all non-essential cookies | v1.6.0 |
| **United Kingdom** | UK GDPR + DUAA 2025 | Analytics and functionality exempt (opt-out); advertising requires consent | v2.0.0 |
| **United States** | CCPA/CPRA + 20 state laws | Opt-out model with GPC signal support (12+ states) | v2.0.0 |
| **Brazil** | LGPD | Opt-in, similar to GDPR | v1.6.0 |
| **Canada** | PIPEDA / CASL | Meaningful consent; Quebec requires express opt-in | v1.6.0 |
| **India** | DPDP Act 2023 | Granular opt-in with one-click withdrawal | v2.0.0 |
| **Rest of World** | Best practices | Configurable — implied or explicit consent | v1.6.0 |

Legacy region keys from v1.9.x (`eu_uk`, `ccpa`) are mapped automatically. Cached geolocation transients from earlier versions will resolve correctly until they expire, but clearing the geolocation cache after updating is recommended.

---

### Global Privacy Control (GPC) *(v2.0.0)*

The GPC signal is a browser-level opt-out that is now legally mandated in twelve US states. When a visitor's browser sends the `Sec-GPC: 1` header (supported by Firefox, Brave, DuckDuckGo, and extensions like Privacy Badger), the plugin:

- Detects the signal both server-side (PHP) and client-side (JavaScript)
- Automatically suppresses marketing cookies without requiring banner interaction
- Shows a brief "Opt-Out Request Honored" confirmation toast (required by California CCPA regulations)
- Logs the GPC detection status alongside the consent record
- Provides a server-side backstop that forces marketing consent to `false` regardless of cookie state

**Configuration:**

GPC handling is enabled by default. To also suppress analytics cookies (if your analytics setup constitutes "sale or sharing" under state law), enable the `mbr_cc_gpc_suppress_analytics` option. Developers can filter the suppressed categories via `mbr_cc_gpc_suppressed_categories`.

---

### Consent Mode Integration
- **Google Consent Mode v2** — full integration with `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, `functionality_storage`, `personalization_storage`
- **Microsoft UET Consent Mode** — EU consent requirements for Microsoft Advertising
- Configurable default states (recommended: denied for EU/EEA)
- Ads data redaction and optional URL passthrough

---

### Internationalisation and Accessibility
- **40+ Language Auto-Translation** — detects browser language, no configuration needed
- **WPML and Polylang** compatible — full string registration and translation support
- **WCAG 2.1 AA** compliant — full keyboard navigation, screen reader support, focus traps, ARIA labels, high contrast and reduced motion support

---

### Banner Customisation
- **Layout Options** — Bar (full width), Box (bottom left/right), Popup (centre)
- **Colour Customisation** — primary, accept, reject, and text colours
- **Custom Text** — fully customisable heading, description, and all button labels
- **Reload on Consent** — optional page reload after consent action

---

### Cookie Scanner and Management
- **One-Click Scanner** — detects scripts and iframes across your site automatically
- **Manual Management** — add, edit, or remove blocked scripts at any time
- **Category Management** — organise by Necessary, Analytics, Marketing, Preferences

---

### Form Builder Integration *(v1.9.0)*

Blocks form submissions **server-side** until consent is granted — cannot be bypassed by disabling JavaScript.

![Blocked content placeholder](block.png)

- **Supported builders** — Contact Form 7, WPForms, Gravity Forms, Elementor Forms
- **Elementor modal** — clean dark overlay modal replaces inline errors, with Accept Cookies and Not Now buttons
- **Auto re-submit** — after accepting cookies the pending form re-submits automatically with all data intact
- **Configurable** — choose required consent category and customise the blocked message

---

### A/B Testing *(v1.9.0)*

Optimise your consent rate by testing banner position variants against real visitor data.

- **Three variants** — Bottom bar (A), Popup (B), Box-left (C)
- **Session persistence** — same visitor always sees the same variant
- **Conversion tracking** — impressions and accept-all rate tracked per variant
- **Results dashboard** — live table with accept rates, bar charts, and winner indicator
- **Promote winner** — one click sets the winning variant as your live position

---

### Legal Policy Tools
- **Privacy Policy Generator** — creates a comprehensive WordPress privacy policy page
- **Cookie Policy Generator** — creates a WordPress cookie policy page template
- **Legal Disclaimers** — built-in throughout the admin interface

---

## Installation

### From Little Web Shack *(recommended)*

1. Visit [littlewebshack.com](https://littlewebshack.com) and download MBR Cookie Consent
2. Upload via **Plugins > Add New > Upload Plugin** in WordPress admin
3. Activate the plugin
4. Add to `wp-config.php` to enable geolocation:
   ```php
   define('MBR_CC_FORCE_GEOLOCATION', true);
   ```
5. Go to **Cookie Consent > Dashboard** to configure

### Manual Installation

1. Download the plugin ZIP from [GitHub Releases](https://github.com/HarbourBob/mbr-cookie-consent/releases)
2. Upload to `/wp-content/plugins/mbr-cookie-consent/`
3. Activate through the **Plugins** menu
4. Add the geolocation constant to `wp-config.php` (see above)

### Upgrading from v1.9.x

The v2.0.0 update changes how geolocation regions are stored. Cached transients from earlier versions are handled via a legacy mapping, but it is recommended to clear the geolocation cache after updating: go to **Cookie Consent > Settings > Geolocation** and click **Clear Geolocation Cache**. Any custom code that references the old `eu_uk` or `ccpa` region keys will continue to work through backwards-compatible aliases.

---

## Quick Start

### 1. Scan your site
Go to **Cookie Consent > Cookie Scanner**, click **Start Scan**, review detected scripts, and add anything non-essential to the blocked list.

### 2. Configure categories
Go to **Cookie Consent > Categories** and customise category names and descriptions to match your privacy policy.

### 3. Customise your banner
Go to **Cookie Consent > Settings** — set position, colours, text, and enable any optional features (Reject button, CCPA link, etc.).

### 4. Generate your Cookie Policy
Go to **Cookie Consent > Dashboard**, click **Generate Cookie Policy Page**, review the draft, and publish.

### 5. Test
Open an incognito window, visit your site, and verify Accept All / Reject All / Customise all behave correctly and scripts are blocked or unblocked as expected. To test GPC, use Firefox or Brave with the GPC signal enabled and confirm the "Opt-Out Request Honored" toast appears.

---

## Google and Microsoft Consent Mode Setup

### Google Consent Mode v2

1. Go to **Cookie Consent > Settings > Consent Mode Integration**
2. Enable **Google Consent Mode v2**
3. Set defaults — **Denied** is recommended for EU/EEA compliance
4. Enable **Ads Data Redaction** for additional privacy protection
5. Your existing GA4/Google Ads tags will automatically receive consent signals — no changes needed

**Consent types controlled:** `ad_storage` · `ad_user_data` · `ad_personalization` · `analytics_storage` · `functionality_storage` · `personalization_storage`

### Microsoft UET Consent Mode

1. Go to **Cookie Consent > Settings > Consent Mode Integration**
2. Enable **Microsoft UET Consent Mode**
3. Set default to **Denied** for GDPR compliance
4. Your existing UET tags will automatically receive consent signals

Consent mode works **alongside** script blocking, not instead of it. Tags still load but behave differently based on consent signals.

---

## Managing Blocked Scripts

### Via the Scanner *(easiest)*
**Cookie Consent > Cookie Scanner** > **Start Scan** > **Add to Blocked List**

### Manually
**Cookie Consent > Cookie Scanner** > scroll to **Add Custom Script** and fill in:

| Field | Description | Example |
|---|---|---|
| **Name** | Display name | `Google Analytics` |
| **Identifier** | URL or content pattern | `google-analytics.com/analytics.js` |
| **Type** | `src`, `inline`, or `iframe` | `src` |
| **Category** | `necessary`, `analytics`, `marketing`, `preferences` | `analytics` |

---

## Cookie Categories

| Category | Description | Always Active |
|---|---|---|
| **Necessary** | Essential for site functionality — session, security | Yes |
| **Analytics** | Usage tracking — Google Analytics, Matomo | Consent required (EU, India); exempt with opt-out (UK DUAA) |
| **Marketing** | Advertising and retargeting — Facebook Pixel, Google Ads | Consent required everywhere |
| **Preferences** | User preference storage — language, UI settings | Consent required (EU, India); exempt with opt-out (UK DUAA) |

---

## Consent Logging

All consent interactions are logged with:

- Timestamp
- User ID (if logged in)
- Anonymised IP address
- Consent given (yes/no)
- Categories accepted
- Consent method (accept_all / reject_all / preferences)
- GPC signal detected (yes/no) *(v2.0.0)*

**Export:** Cookie Consent > Consent Logs > **Export to CSV**

**Housekeeping:** Cookie Consent > Consent Logs > specify days > **Delete Old Logs**

---

## Compliance Summary

### EU — GDPR / ePrivacy Directive
- Explicit opt-in for all non-essential cookies
- Clear information about cookie usage
- Easy consent revocation
- IP address anonymisation
- Full consent audit log
- Granular category control
- Cookie and Privacy Policy generator

### United Kingdom — UK GDPR + DUAA 2025
- Analytics and functionality cookies exempt from consent (DUAA Schedule A1)
- Transparency and easy opt-out still required for exempt categories
- Advertising and marketing cookies require explicit consent
- PECR fines now up to £17.5M or 4% of global turnover
- Formal complaints procedure required by June 2026

### United States — CCPA/CPRA + 20 State Laws
- "Do Not Sell or Share My Personal Information" link
- Global Privacy Control (GPC) signal honoured automatically (12+ states)
- Visible "Opt-Out Request Honored" confirmation (California requirement)
- Opt-out based model
- Clear disclosure of data collection

### Brazil — LGPD
- Opt-in consent with Portuguese language defaults
- Consent revocation
- Data minimisation

### Canada — PIPEDA / CASL
- Meaningful consent with purpose disclosure
- CASL classifies cookies as computer programs requiring consent
- Quebec requires express opt-in

### India — DPDP Act 2023
- Granular consent with one-click withdrawal
- Standalone privacy notice
- Verifiable parental consent for minors
- Consent Manager registration opens November 2026 (India-incorporated entities only)
- Full compliance mandatory by May 2027

### IAB TCF v2.3
- Full `__tcfapi` JavaScript API
- TC String generation and storage
- 10 standard consent purposes
- Global Vendor List integration ready

---

## Developer Notes

### Programmatic Consent Check

```javascript
// Check if analytics consent has been granted
window.MbrCcConsent.hasCategoryConsent('analytics', function(allowed) {
    if (allowed) {
        // Load your analytics script
    }
});
```

### GPC Detection

```php
// Check if GPC signal is active for the current request
if (function_exists('mbr_cc_gpc') && mbr_cc_gpc()->is_gpc_active()) {
    // Visitor has opted out via Global Privacy Control
}
```

```javascript
// Client-side GPC check (also available via mbrCcGpc localized data)
if (navigator.globalPrivacyControl === true) {
    // GPC signal is active
}
```

### Filtering GPC Suppressed Categories

```php
// Add analytics to the list of categories suppressed by GPC
add_filter('mbr_cc_gpc_suppressed_categories', function($categories) {
    $categories[] = 'analytics';
    return $categories;
});
```

### Script Blocking Mechanism

The plugin uses PHP output buffering to intercept HTML before it reaches the browser. Blocked scripts have their `type` attribute changed to `text/plain` and receive a `data-mbr-cc-blocked` attribute. On consent, scripts are restored and executed client-side.

### Hooks and Filters

Coming in a future version.

---

## Technical Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| MySQL | 5.6 or higher |

---

## Roadmap

- ✅ Google Consent Mode v2 *(v1.1.0)*
- ✅ Microsoft UET Consent Mode *(v1.1.0)*
- ✅ Auto-translation — 40+ languages *(v1.2.0)*
- ✅ WPML and Polylang compatibility *(v1.2.0)*
- ✅ WCAG/ADA accessibility *(v1.2.0)*
- ✅ Page-specific banner controls *(v1.3.0)*
- ✅ Custom CSS editor *(v1.3.0)*
- ✅ Subdomain consent sharing *(v1.3.0)*
- ✅ IAB TCF v2.3 *(v1.4.0)*
- ✅ Google Additional Consent Mode *(v1.4.0)*
- ✅ Privacy Policy Generator *(v1.4.1)*
- ✅ Multisite support *(v1.5.0)*
- ✅ Geolocation detection *(v1.6.0)*
- ✅ Form builder integration *(v1.9.0)*
- ✅ A/B testing for banner variations *(v1.9.0)*
- ✅ UK DUAA 2025 compliance *(v2.0.0)*
- ✅ Global Privacy Control (GPC) support *(v2.0.0)*
- ✅ US multi-state privacy law coverage *(v2.0.0)*
- ✅ India DPDP Act 2023 region *(v2.0.0)*
- Consent Mode API for developers

---

## Changelog

### 2.0.0 — International Privacy Law Update
- **New: UK separated from EU** — the Data Use and Access Act 2025 (Royal Assent 19 June 2025, PECR amendments in force 5 February 2026) gives the UK a distinct cookie consent regime. Analytics, functionality, security, and software-update cookies are now exempt from consent under DUAA Schedule A1, with transparency and easy opt-out still required. Advertising cookies still require explicit consent. PECR fines increased to £17.5M or 4% of turnover
- **New: Global Privacy Control (GPC)** — server-side and client-side detection of the `Sec-GPC` browser signal, now legally required in 12 US states. Marketing cookies automatically suppressed when GPC is active. California-mandated "Opt-Out Request Honored" toast confirmation. Filterable suppressed categories via `mbr_cc_gpc_suppressed_categories`. Logged alongside consent records
- **New: US multi-state coverage** — region renamed from "CCPA" to "US Multi-State" reflecting 20 states with comprehensive privacy laws as of January 2026 (including Indiana, Kentucky, and Rhode Island). Banner text updated to reference GPC support. "Do Not Sell or Share My Personal Information" link shown by default
- **New: India DPDP Act 2023** — new geolocation region for Indian visitors with granular opt-in consent and one-click withdrawal, matching the Digital Personal Data Protection Act requirements. Consent Manager registration opens November 2026, full compliance mandatory May 2027
- **Fix: WordPress 6.7+ translation notice** — resolved `_load_textdomain_just_in_time` notice caused by `__()` being called during `plugins_loaded` in the form integration class. Translation loading deferred to point of use via lazy getter
- **Backwards compatibility** — legacy region keys `eu_uk` and `ccpa` mapped automatically. Cached geolocation transients from v1.9.x resolve correctly. `is_eu_uk()` and `is_ccpa()` helper methods retained as aliases
- **Admin UI updated** — geolocation settings page now shows seven region compliance cards (EU, UK, US, Brazil, Canada, India, Rest of World) with current penalty figures and enforcement details. Test tool displays GPC and DUAA status

### 1.9.2 — Bug Fixes
- **Button colours** — set in admin now correctly apply to the preferences modal Save and Reject buttons
- **Banner close X** now correctly inherits the admin-set text colour
- **All colour declarations** hardened with !important and extended to cover :hover/:focus states to prevent theme/Elementor CSS overrides

### 1.9.1 — Bug Fixes
- **Elementor Forms modal** — dual-strategy intercept (fetch + XHR) ensures the modal always shows instead of inline errors
- **Form auto re-submit** — raw request body captured and replayed after consent; page no longer reloads and clears the form
- **Form blocking hard-stops** — CF7 uses `wpcf7_spam` filter; WPForms blocks entry saving and email notifications; Elementor uses direct `wp_send_json` response
- **Remove last blocked script** — DOM re-indexes remaining items after each removal to stay in sync with server
- **Delete Old Logs UI** — restored to Consent Logs page (handler existed, HTML form was missing)
- **Blocked content placeholder** — always renders when an iframe is blocked, regardless of admin toggle
- **Service-specific messaging** — placeholder shows e.g. "YouTube video blocked"
- **Branding logo** — recommended size corrected to 150x150 px

### 1.9.0 — Form Integration and A/B Testing
- **New:** Form Builder Integration — CF7, WPForms, Gravity Forms, Elementor Forms
- **New:** A/B Testing — three banner position variants with conversion tracking and one-click winner promotion

### 1.8.1 — Bug Fixes
- Banner reappearance after consent resolved
- Cookie write verification and domain scoping fallback
- Blocked content placeholder style update and service-specific messaging

### 1.8.0 — Elementor Video Blocking
- Elementor YouTube widget blocking pending consent
- Built-in service library (YouTube, Vimeo, Google Maps, and more)
- WP Rocket lazy-load compatibility
- Per-category unblocking fix

### 1.7.0 — Blocked Content Overlay
- Branded placeholder shown in place of blocked iframes

### 1.6.0 — Geolocation
- Auto-detects visitor country, displays region-appropriate banner (GDPR/CCPA/LGPD/PIPEDA)

### 1.5.0 — Multisite
- Network-aware with automatic detection and settings adjustment

### 1.4.1 — Privacy Policy Generator
- Intelligent generator that analyses site configuration

### 1.4.0 — IAB TCF v2.3 and Google ACM
- Full `__tcfapi` implementation, TC String generation, Google Additional Consent Mode

### 1.3.0 — Enhanced Customisation
- Page-specific controls, custom CSS editor, subdomain consent sharing

### 1.2.0 — Internationalisation and Accessibility
- 40+ language auto-translation, WPML/Polylang, WCAG 2.1 AA

### 1.1.0 — Consent Mode Integration
- Google Consent Mode v2, Microsoft UET Consent Mode

### 1.0.0 — Initial Release
- Banner, script blocking, categories, preference centre, consent logging, scanner, CSV export, cookie policy generator

---

## Support

| Channel | Link |
|---|---|
| Website | [littlewebshack.com](https://littlewebshack.com) |
| Email | [rob@littlewebshack.com](mailto:rob@littlewebshack.com) |
| GitHub | [github.com/HarbourBob](https://github.com/HarbourBob) |
| Docs | See plugin admin pages |

---

## License

GPL v2 or later — free to use, modify, and distribute.

---

> **Legal Disclaimer:** This plugin provides technical tools to help implement cookie consent mechanisms. It does not constitute legal advice. Privacy laws change frequently. Always consult a qualified legal professional for compliance guidance specific to your situation.

---

<div align="center">

Made with care by Robert Palmer in Cleethorpes, England

**[Little Web Shack](https://littlewebshack.com)** · **[Made by Robert](https://madebyrobert.co.uk)**

</div>
