<div align="center">

# 🍪 MBR Cookie Consent

[![Version](https://img.shields.io/badge/version-2.1.0-blue?style=flat-square)](https://github.com/harbourbob/mbr-cookie-consent/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2-green?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Downloads](https://img.shields.io/github/downloads/harbourbob/mbr-cookie-consent/total)](https://github.com/harbourbob/mbr-cookie-consent/releases)

[![GDPR](https://img.shields.io/badge/GDPR%2FEEA-compliant-success?style=flat-square)](https://littlewebshack.com)
[![UK DUAA](https://img.shields.io/badge/UK%20DUAA%202025-compliant-success?style=flat-square)](https://littlewebshack.com)
[![Quebec Law 25](https://img.shields.io/badge/Quebec%20Law%2025-compliant-success?style=flat-square)](https://littlewebshack.com)
[![Switzerland nFADP](https://img.shields.io/badge/Swiss%20nFADP-compliant-success?style=flat-square)](https://littlewebshack.com)
[![Australia](https://img.shields.io/badge/Australia%20Privacy%20Act-compliant-success?style=flat-square)](https://littlewebshack.com)
[![IAB TCF](https://img.shields.io/badge/IAB%20TCF-v2.3-orange?style=flat-square)](https://iabeurope.eu)
[![GPC](https://img.shields.io/badge/Global%20Privacy%20Control-supported-blueviolet?style=flat-square)](https://globalprivacycontrol.org)

</div>

![MBR Cookie Consent](head.webp)

<div align="center">

### Enterprise-grade privacy compliance for WordPress.

🇪🇺 GDPR/EEA · 🇬🇧 UK DUAA · 🇺🇸 CCPA + 20 US state laws · 🇨🇦 Quebec Law 25 · 🇨🇭 Swiss nFADP · 🇦🇺 Australia Privacy Act · 🇧🇷 LGPD · 🇨🇦 PIPEDA · 🇮🇳 India DPDP · Global Privacy Control

**🆓 Completely free, forever. No upsells. No premium tier. No vendor lock-in.**

[**📥 Download**](#-installation) · [**🚀 Quick Start**](#-quick-start) · [**✨ Features**](#-features) · [**🌍 Coverage**](#-global-coverage) · [**🔧 Developer Notes**](#-developer-notes)

</div>

---

## 🆕 What's New in v2.1.0 — May 2026

The privacy law landscape kept moving in early 2026. v2.1.0 keeps up.

> [!NOTE]
> **Backwards compatible** — no breaking changes. All existing options, helpers, filters, and stored consent records work unchanged. Visitors whose region resolves to a new key (`ca_quebec`, `ch_nfadp`, `au_privacy`) see the appropriate banner on next page load; cached transients refresh automatically as they expire.

### 🇨🇦 Quebec (Law 25) — new region

Quebec is now detected separately from the rest of Canada. The Commission d'accès à l'information du Québec explicitly rejects implied consent for non-essential cookies, so visitors with a Canadian IP and ISO 3166-2 region `QC` now get an **express opt-in banner with French-default messaging** and the stricter Law 25 consent posture. Penalties reach the higher of **CA$25M or 4% of worldwide turnover**.

### 🇨🇭 Switzerland (revFADP / nFADP) — new region

Swiss visitors no longer fall through to the lenient default config. They now get a **GDPR-equivalent opt-in banner** reflecting the revised Federal Act on Data Protection in force since 1 September 2023.

### 🇦🇺 Australia (Privacy Act 1988, as amended) — new region

Visitors from Australia now receive an **APP-aligned banner with informed consent**, reflecting the Privacy and Other Legislation Amendment Act 2024. Statutory tort for serious privacy invasions in force since 10 June 2025; ADM transparency obligations and Children's Online Privacy Code due 10 December 2026.

### 🇮🇸 🇱🇮 🇳🇴 EEA gap closed

Iceland, Liechtenstein, and Norway apply GDPR via the EEA Agreement. They previously fell through to the default config — exactly the kind of bug nobody notices until an enforcement action does. **Now closed.**

### 🇬🇧 UK DUAA refreshed for ICO finalised guidance

The ICO's *Storage and Access Technologies* guidance was finalised on **29 April 2026**. The UK config now documents the five PECR exemption categories (communications transmission, requested service, statistical analytics, appearance/functionality, software updates / emergency assistance), references the "simple means of objecting" language, and notes purpose limitation as mandatory.

### 🇺🇸 California CCPA 2026 amendments

The new CCPA regulations effective 1 January 2026 are reflected:
- ✅ Mandatory visible opt-out confirmation when a GPC signal is processed
- ✅ Neural data and data of consumers under 16 added to sensitive PI
- ✅ False-urgency consent UX explicitly prohibited
- ✅ GPC state list updated to the actual 2026 mandate (CA, CO, CT, DE, MD, MN, MT, NE, NH, NJ, OR, TX)

### 🇮🇳 India DPDP Rules notified

The DPDP Rules were notified by MeitY on **13 November 2025** and gazetted on **14 November 2025**. Phased compliance through 13 May 2027; 72-hour breach notification documented.

### ⚙️ Plumbing

- 🌐 Sub-national region detection from ip-api.com, ipapi.co, and Cloudflare Enterprise
- 🔧 New helpers: `is_quebec()`, `is_switzerland()`, `is_australia()`, `get_region_code()`
- 🧪 Admin tester accepts an optional region/province code (try `CA` + `QC`)
- 🧹 `geolocation-ajax.php` refactored to call the canonical `determine_region()` via reflection — admin tester and live detection can no longer drift apart

---

## ⭐ Why MBR Cookie Consent?

| Feature | MBR Cookie Consent | Typical Premium Plugins |
|---|---|---|
| 💰 **Price** | 🆓 Free forever | £99–£299/year |
| 🌍 **Multi-region geolocation** | ✅ 10+ regions, sub-national detection | 💳 Premium only |
| 🇬🇧 **UK DUAA 2025 compliance** | ✅ Per ICO 29 Apr 2026 guidance | 💳 Premium only |
| 🇨🇦 **Quebec Law 25** | ✅ Express opt-in, French default | 💳 Premium only |
| 🇨🇭 **Swiss nFADP** | ✅ GDPR-equivalent | 💳 Premium only |
| 🇦🇺 **Australia Privacy Act** | ✅ APP-aligned | 💳 Premium only |
| 🇺🇸 **US multi-state (20 states)** | ✅ Included | 💳 Premium only |
| 🛡️ **Global Privacy Control (GPC)** | ✅ Server + client + toast | 💳 Premium only |
| 📊 **IAB TCF v2.3** | ✅ Full `__tcfapi` | 💳 Premium only |
| 📈 **Google Consent Mode v2** | ✅ Included | 💳 Premium only |
| 🌐 **40+ Language auto-translation** | ✅ Included | 💳 Premium only |
| 📝 **Form Builder Integration** | ✅ CF7, WPForms, Gravity, Elementor | 💳 Premium only |
| 🧪 **A/B Testing** | ✅ Three variants, conversion tracking | 💳 Premium only |
| 🌐 **Multisite Support** | ✅ Network-aware | 💳 Premium only |
| 🔒 **Vendor lock-in** | None | Proprietary |

---

## 🌍 Global Coverage

The plugin detects visitor location and applies the correct privacy regime automatically.

| | Region | Law | Consent Model | Since |
|---|---|---|---|---|
| 🇪🇺 | **EU/EEA (30)** | GDPR / ePrivacy Directive | Strict opt-in | v1.6.0 |
| 🇬🇧 | **United Kingdom** | UK GDPR + DUAA 2025 | Five PECR exemptions; advertising still requires consent | v2.0.0 |
| 🇺🇸 | **United States** | CCPA/CPRA + 20 state laws | Opt-out + GPC + opt-out confirmation toast | v2.0.0 |
| 🇨🇦 | **Quebec** | Law 25 (Loi 25) | **Express opt-in, French-default** | v2.1.0 |
| 🇨🇦 | **Canada (rest)** | PIPEDA / CASL | Meaningful consent | v1.6.0 |
| 🇨🇭 | **Switzerland** | revFADP / nFADP | GDPR-equivalent opt-in | v2.1.0 |
| 🇦🇺 | **Australia** | Privacy Act 1988 (as amended) | APP-aligned informed consent | v2.1.0 |
| 🇧🇷 | **Brazil** | LGPD | Opt-in, GDPR-style | v1.6.0 |
| 🇮🇳 | **India** | DPDP Act 2023 + DPDP Rules 2025 | Granular opt-in, one-click withdrawal | v2.0.0 |
| 🌐 | **Rest of World** | Best practices | Configurable | v1.6.0 |

> [!TIP]
> **EEA coverage now complete.** The EU/EEA region covers all 27 EU Member States plus Iceland 🇮🇸, Liechtenstein 🇱🇮, and Norway 🇳🇴 (which apply GDPR via the EEA Agreement). Legacy region keys from v1.9.x (`eu_uk`, `ccpa`) remain mapped automatically.

---

## ✨ Features

### 🛡️ Consent Management
- **Customisable Banner** — Accept All, Reject All, and Customise options
- **Automatic Script Blocking** — blocks non-essential scripts until explicit consent
- **Preference Centre** — granular category-by-category control
- **Revisit Consent Button** — floating button so visitors can update preferences any time
- **CCPA "Do Not Sell or Share"** — required link for US visitors
- **Global Privacy Control** — automatic detection and honouring of the GPC browser signal *(v2.0.0)*
- **Consent Logging** — every interaction recorded, exportable to CSV
- **GDPR-compliant storage** — IP anonymisation and proper data handling

### 🌍 Geolocation and Regional Compliance *(v2.1.0)*
- **Country detection** via ip-api.com, ipapi.co, or Cloudflare headers
- **Sub-national region detection** for accurate Canada/US routing — Quebec gets Law 25, the rest of Canada gets PIPEDA
- **Auto-translated banner text** based on resolved region
- **Helper API** — `is_eu()`, `is_uk()`, `is_us()`, `is_quebec()`, `is_switzerland()`, `is_australia()`, `is_lgpd()`, `is_dpdp()`, `get_region()`, `get_country()`, `get_region_code()`

### 🔒 Global Privacy Control (GPC) *(v2.0.0)*

Legally mandated in CA, CO, CT, DE, MD, MN, MT, NE, NH, NJ, OR, TX as of 2026.

When a visitor's browser sends `Sec-GPC: 1` (Firefox, Brave, DuckDuckGo, Privacy Badger), the plugin:

- ✅ Detects the signal both server-side (PHP) and client-side (JavaScript)
- ✅ Automatically suppresses marketing cookies without requiring banner interaction
- ✅ Shows a brief **"Opt-Out Request Honored"** confirmation toast (California mandate)
- ✅ Logs the GPC detection status alongside the consent record
- ✅ Provides a server-side backstop that forces marketing consent to `false` regardless of cookie state

To also suppress analytics cookies (if your analytics setup constitutes "sale or sharing" under state law), enable `mbr_cc_gpc_suppress_analytics`. Filterable via `mbr_cc_gpc_suppressed_categories`.

### 📈 Consent Mode Integration
- **Google Consent Mode v2** — full integration with `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, `functionality_storage`, `personalization_storage`
- **Microsoft UET Consent Mode** — EU consent requirements for Microsoft Advertising
- Configurable default states (recommended: denied for EU/EEA)
- Ads data redaction and optional URL passthrough

### 🌐 Internationalisation and Accessibility
- **40+ languages** — auto-translated banner text, no configuration needed
- **WPML and Polylang** — full string registration and translation support
- **WCAG 2.1 AA** — keyboard navigation, screen reader support, focus traps, ARIA labels, high contrast and reduced motion support

### 🎨 Banner Customisation
- **Layouts** — Bar (full width), Box (bottom left/right), Popup (centre)
- **Colour Customisation** — primary, accept, reject, and text colours
- **Custom Text** — fully customisable heading, description, and all button labels
- **Reload on Consent** — optional page reload after consent action

### 🔍 Cookie Scanner and Management
- **One-Click Scanner** — detects scripts and iframes across your site automatically
- **Manual Management** — add, edit, or remove blocked scripts at any time
- **Category Management** — organise by Necessary, Analytics, Marketing, Preferences

### 📝 Form Builder Integration *(v1.9.0)*

Blocks form submissions **server-side** until consent is granted — cannot be bypassed by disabling JavaScript.

![Blocked content placeholder](block.png)

- **Supported builders** — Contact Form 7, WPForms, Gravity Forms, Elementor Forms
- **Elementor modal** — clean dark overlay modal replaces inline errors, with Accept Cookies and Not Now buttons
- **Auto re-submit** — after accepting cookies the pending form re-submits automatically with all data intact
- **Configurable** — choose required consent category and customise the blocked message

### 🧪 A/B Testing *(v1.9.0)*

Optimise your consent rate by testing banner position variants against real visitor data.

- **Three variants** — Bottom bar (A), Popup (B), Box-left (C)
- **Session persistence** — same visitor always sees the same variant
- **Conversion tracking** — impressions and accept-all rate tracked per variant
- **Results dashboard** — live table with accept rates, bar charts, and winner indicator
- **Promote winner** — one click sets the winning variant as your live position

### 📜 Legal Policy Tools
- **Privacy Policy Generator** — creates a comprehensive WordPress privacy policy page
- **Cookie Policy Generator** — creates a WordPress cookie policy page template
- **Legal Disclaimers** — built-in throughout the admin interface

---

## 📥 Installation

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

1. Download the plugin ZIP from [GitHub Releases](https://github.com/harbourbob/mbr-cookie-consent/releases)
2. Upload to `/wp-content/plugins/mbr-cookie-consent/`
3. Activate through the **Plugins** menu
4. Add the geolocation constant to `wp-config.php` (see above)

### Upgrading from v2.0.0

> [!IMPORTANT]
> **No breaking changes.** v2.1.0 adds new region detection without altering existing options or stored data. Visitors from Quebec, Switzerland, Australia, Iceland, Liechtenstein, and Norway will start seeing region-appropriate banners on next page load. To force immediate region re-detection for existing cached visitors, go to **Cookie Consent > Settings > Geolocation** and click **Clear Geolocation Cache**.

### Upgrading from v1.9.x

The v2.0.0 update changes how geolocation regions are stored. Cached transients from earlier versions are handled via a legacy mapping, but it is recommended to clear the geolocation cache after updating. Custom code referencing the old `eu_uk` or `ccpa` region keys continues to work through backwards-compatible aliases.

---

## 🚀 Quick Start

### 1️⃣ Scan your site
**Cookie Consent > Cookie Scanner** > **Start Scan** > review detected scripts > add anything non-essential to the blocked list.

### 2️⃣ Configure categories
**Cookie Consent > Categories** > customise category names and descriptions to match your privacy policy.

### 3️⃣ Customise your banner
**Cookie Consent > Settings** > set position, colours, text, and enable any optional features (Reject button, CCPA link, etc.).

### 4️⃣ Generate your Cookie Policy
**Cookie Consent > Dashboard** > **Generate Cookie Policy Page** > review the draft > publish.

### 5️⃣ Test
Open an incognito window, visit your site, and verify Accept All / Reject All / Customise all behave correctly and scripts are blocked or unblocked as expected. To test GPC, use Firefox or Brave with the GPC signal enabled and confirm the **"Opt-Out Request Honored"** toast appears. To verify Quebec routing, use the testing tool with `CA` + `QC`.

---

## ⚙️ Google and Microsoft Consent Mode Setup

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

> Consent mode works **alongside** script blocking, not instead of it. Tags still load but behave differently based on consent signals.

---

## 🔧 Managing Blocked Scripts

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

## 🍪 Cookie Categories

| Category | Description | Consent Required |
|---|---|---|
| 🔒 **Necessary** | Essential for site functionality — session, security | Always active |
| 📊 **Analytics** | Usage tracking — Google Analytics, Matomo | EU/EEA, Quebec, India, Switzerland, Australia: **yes** · UK DUAA: **no** (opt-out) |
| 📣 **Marketing** | Advertising and retargeting — Facebook Pixel, Google Ads | **Always required** |
| 🎨 **Preferences** | User preference storage — language, UI settings | EU/EEA, Quebec, India: **yes** · UK DUAA: **no** (opt-out) |

---

## 📋 Consent Logging

All consent interactions are logged with:

- 🕒 Timestamp
- 👤 User ID (if logged in)
- 🌐 Anonymised IP address
- ✅ Consent given (yes/no)
- 📦 Categories accepted
- 🎬 Consent method (`accept_all` / `reject_all` / `preferences`)
- 🛡️ GPC signal detected (yes/no) *(v2.0.0)*

**Export:** Cookie Consent > Consent Logs > **Export to CSV**

**Housekeeping:** Cookie Consent > Consent Logs > specify days > **Delete Old Logs**

---

## 📜 Compliance Summary

<details>
<summary><strong>🇪🇺 EU/EEA — GDPR / ePrivacy Directive</strong></summary>

- Explicit opt-in for all non-essential cookies
- Clear information about cookie usage
- Easy consent revocation
- IP address anonymisation
- Full consent audit log
- Granular category control
- Cookie and Privacy Policy generator
- Applies in all 27 EU Member States plus Iceland, Liechtenstein, and Norway via the EEA Agreement
- Proposed ePrivacy Regulation withdrawn 11 February 2026 — Directive remains in force
- **Penalties:** up to €20M or 4% of annual global turnover
</details>

<details>
<summary><strong>🇬🇧 United Kingdom — UK GDPR + DUAA 2025</strong></summary>

- PECR amendments effective 5 February 2026
- ICO Storage and Access Technologies guidance finalised 29 April 2026
- Five exempt categories: communications transmission, requested service, statistical analytics, appearance/functionality, software updates / emergency assistance
- Advertising/marketing cookies still require explicit consent
- Clear information and a "simple means of objecting" required for exempt categories
- PECR fines now match UK GDPR: up to £17.5M or 4% of turnover (35× increase)
- Formal complaints procedure required by June 2026
- **Penalties:** up to £17.5M or 4% of annual global turnover
</details>

<details>
<summary><strong>🇺🇸 United States — CCPA/CPRA + 20 State Laws</strong></summary>

- "Do Not Sell or Share My Personal Information" link (CCPA)
- Opt-out based model — not opt-in
- Universal opt-out / GPC honoured in CA, CO, CT, DE, MD, MN, MT, NE, NH, NJ, OR, TX
- California (effective 1 Jan 2026): visible confirmation when GPC opt-out is processed
- California: sensitive PI now includes neural data and data of under-16s
- California: dark patterns including false-urgency consent UX explicitly prohibited
- Indiana, Kentucky, and Rhode Island took effect 1 January 2026
- Maryland MODPA effective 1 October 2025 with strict data-minimisation rules
- **Penalties:** up to $7,988 per intentional violation (CA); varies by state
</details>

<details>
<summary><strong>🇨🇦 Quebec — Law 25 (Loi 25)</strong></summary>

- Express, opt-in consent required for non-essential cookies (CAI rejects implied consent)
- Banner and privacy information must be available in French
- Detailed consent records must be maintained
- Withdrawal must be at least as easy as giving consent
- Heightened protections for minors
- Designated privacy officer required
- **Penalties:** up to CA$25M or 4% of worldwide turnover, whichever is higher
</details>

<details>
<summary><strong>🇨🇦 Canada (rest) — PIPEDA / CASL</strong></summary>

- Meaningful consent required
- Must identify purpose before collection
- Implied consent allowed in some low-risk cases
- CASL classifies cookies as "computer programs" requiring consent
- Quebec handled as a separate region (Law 25)
- Bill C-27 (CPPA) may introduce stricter rules — monitor progress
- **Penalties:** up to $10M CAD (PIPEDA); $10M per violation (CASL)
</details>

<details>
<summary><strong>🇨🇭 Switzerland — revFADP / nFADP</strong></summary>

- GDPR-equivalent consent expectations for non-essential cookies
- Transparent notice at point of collection
- Consent must be free, informed, and unambiguous where required
- Recognised as providing adequate protection by the EU Commission
- Applies to organisations targeting Swiss residents regardless of where based
- **Penalties:** personal fines up to CHF 250,000 against responsible individuals
</details>

<details>
<summary><strong>🇦🇺 Australia — Privacy Act 1988 (as amended)</strong></summary>

- Australian Privacy Principles (APPs) apply where cookies collect personal information
- APP 5 notification at point of collection
- APP 3 limits collection to what is reasonably necessary
- Sensitive information requires opt-in consent
- Statutory tort for serious invasions of privacy in force from 10 June 2025
- Automated-decision-making transparency obligations from 10 December 2026
- Children's Online Privacy Code due by 10 December 2026
- **Penalties:** up to AU$50M, 30% of adjusted turnover, or 3× the benefit obtained
</details>

<details>
<summary><strong>🇧🇷 Brazil — LGPD</strong></summary>

- Clear and specific consent required
- Must show legitimate purpose
- Users can revoke consent
- Data minimisation required
- Similar to GDPR requirements
- **Penalties:** up to 2% of revenue (max R$50M per violation)
</details>

<details>
<summary><strong>🇮🇳 India — DPDP Act 2023 + Rules 2025</strong></summary>

- DPDP Rules notified by MeitY 13 November 2025; gazetted 14 November 2025
- Standalone privacy notice in clear, plain language
- Granular consent with one-click withdrawal
- Verifiable parental consent for minors
- Data Protection Board operational from 13 November 2025
- Consent Manager registration opens 13 November 2026 (India-incorporated only)
- Full compliance mandatory by 13 May 2027
- 72-hour personal data breach notification
- Automated deletion with proof required
- **Penalties:** up to ₹250 crore (~£25M) per violation
</details>

<details>
<summary><strong>📊 IAB TCF v2.3</strong></summary>

- Full `__tcfapi` JavaScript API
- TC String generation and storage
- 10 standard consent purposes
- Global Vendor List integration ready
</details>

---

## 🔧 Developer Notes

### Programmatic Consent Check

```javascript
// Check if analytics consent has been granted
window.MbrCcConsent.hasCategoryConsent('analytics', function(allowed) {
    if (allowed) {
        // Load your analytics script
    }
});
```

### Region Detection

```php
$geo = mbr_cc_geolocation();

if ($geo->is_quebec()) {
    // Visitor is in Quebec — Law 25 applies
}

if ($geo->is_switzerland()) {
    // Visitor is in Switzerland — revFADP applies
}

if ($geo->is_australia()) {
    // Visitor is in Australia — Privacy Act applies
}

// Or get the canonical region key directly
$region = $geo->get_region();
// Returns: 'eu_gdpr' | 'uk_duaa' | 'us_multi' | 'ca_quebec' |
//          'pipeda' | 'ch_nfadp' | 'au_privacy' | 'lgpd' |
//          'india_dpdp' | 'default'

// Get sub-national region code where available (e.g. 'QC')
$province = $geo->get_region_code();
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

### Custom Region Banner Text

```php
// Set custom French banner text for Quebec visitors
update_option('mbr_cc_geolocation_quebec_heading', 'Vos préférences de cookies');
update_option('mbr_cc_geolocation_quebec_description', 'Texte personnalisé...');
```

Available option keys: `mbr_cc_geolocation_{region}_heading` and `mbr_cc_geolocation_{region}_description` for `eu`, `uk`, `us`, `quebec`, `switzerland`, `australia`, `lgpd`, `pipeda`, `india`.

### Script Blocking Mechanism

The plugin uses PHP output buffering to intercept HTML before it reaches the browser. Blocked scripts have their `type` attribute changed to `text/plain` and receive a `data-mbr-cc-blocked` attribute. On consent, scripts are restored and executed client-side.

---

## 🛠️ Technical Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| MySQL | 5.6 or higher |

---

## 🗺️ Roadmap

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
- ✅ Quebec Law 25 region with sub-national detection *(v2.1.0)*
- ✅ Switzerland revFADP / nFADP region *(v2.1.0)*
- ✅ Australia Privacy Act region *(v2.1.0)*
- ✅ EEA non-EU members (IS, LI, NO) added to GDPR detection *(v2.1.0)*
- ✅ ICO 29 April 2026 finalised guidance applied *(v2.1.0)*
- ✅ California CCPA 2026 amendments applied *(v2.1.0)*
- 🔜 Consent Mode API for developers
- 🔜 Brazil ANPD Consent Manager interoperability
- 🔜 India Consent Manager registration support (pending Nov 2026 framework)

---

## 📋 Changelog

### 2.1.0 — May 8, 2026 — Three new regions and EEA gap closure

- **🇨🇦 New: Quebec (Law 25)** detected as a separate region from the rest of Canada. Visitors with Canadian IP and ISO 3166-2 region `QC` get an express opt-in banner with French-default messaging, equally-prominent reject, and the stricter Law 25 consent recording posture. Visitors elsewhere in Canada continue to receive the PIPEDA / CASL config.
- **🇨🇭 New: Switzerland (revFADP / nFADP)** detected as a separate region. Swiss visitors no longer fall through to the lenient default config — they get a GDPR-equivalent opt-in banner reflecting the revised Federal Act on Data Protection in force since 1 September 2023.
- **🇦🇺 New: Australia (Privacy Act 1988, as amended)** detected as a separate region. Australian visitors receive an APP-aligned banner with informed consent and a clear opt-out path.
- **🇮🇸 🇱🇮 🇳🇴 New: EEA non-EU members** (Iceland, Liechtenstein, Norway) added to the GDPR detection list. These countries apply GDPR via the EEA Agreement and previously fell through to the default config — a compliance gap that is now closed.
- **🌐 New: Sub-national region capture** from ip-api.com, ipapi.co, and Cloudflare Enterprise so that province/state-level rules can be applied correctly. Cache layer extended to store the region code alongside the country.
- **🧪 New: Admin geolocation testing tool** now accepts an optional region/province code (e.g. CA + QC) so admins can verify Quebec-specific behaviour before going live.
- **🔧 New: Helper methods** `MBR_CC_Geolocation::is_quebec()`, `is_switzerland()`, `is_australia()`, and `get_region_code()` exposed for theme/plugin integrations.
- **🇬🇧 Compliance: UK DUAA region config** rewritten to align with the ICO's *Storage and Access Technologies* guidance finalised on 29 April 2026. Documents the five PECR exemption categories and the ICO's "simple means of objecting" expectation. Purpose limitation noted as mandatory.
- **🇺🇸 Compliance: US multi-state region config** updated for the California CCPA regulations effective 1 January 2026 — mandatory visible confirmation when an opt-out request (including a GPC signal) is processed, expanded "sensitive personal information" definition covering neural data and data of consumers under 16, and the new dark-pattern prohibition on false-urgency consent UX.
- **🛡️ Compliance: GPC handler** documentation updated to list the actual 2026 state mandate set (CA, CO, CT, DE, MD, MN, MT, NE, NH, NJ, OR, TX) instead of the previous "12+ states" placeholder.
- **🇮🇳 Compliance: India DPDP** region config updated to reflect that the DPDP Rules 2025 were notified by MeitY on 13 November 2025 and gazetted on 14 November 2025. Phased compliance dates documented through 13 May 2027. 72-hour breach notification noted.
- **🇪🇺 Compliance: EU/EEA region docstring** notes the formal withdrawal of the proposed ePrivacy Regulation by the European Commission on 11 February 2026; the 2002/58/EC Directive (as amended) remains the controlling instrument.
- **🎨 Improvement: Admin Geolocation & Regional Compliance settings panel** restructured — EU tile renamed to EU/EEA, UK tile rewritten around the five PECR exemptions, US tile updated for the CCPA 2026 amendments, PIPEDA tile clarifies that Quebec visitors are routed to Law 25, India tile reflects DPDP Rules notification. Three new tiles added for Quebec, Switzerland, and Australia.
- **🧹 Improvement: `admin/geolocation-ajax.php`** refactored to call `MBR_CC_Geolocation::determine_region()` via reflection rather than maintaining a duplicate country list. The admin tester and live detection can no longer drift apart.
- **🌐 Improvement: `ipapi.co` provider** switched to the JSON endpoint so country and region code can be fetched in a single request rather than two.
- **✅ No breaking API changes.** Existing options, helper functions (`mbr_cc_geolocation()`, `mbr_cc_region_config()`), filters, and stored consent records continue to work unchanged.

### 2.0.0 — International Privacy Law Update

- **🇬🇧 New: UK separated from EU** — the Data Use and Access Act 2025 (Royal Assent 19 June 2025, PECR amendments in force 5 February 2026) gives the UK a distinct cookie consent regime. PECR fines increased to £17.5M or 4% of turnover.
- **🛡️ New: Global Privacy Control (GPC)** — server-side and client-side detection of the `Sec-GPC` browser signal. Marketing cookies automatically suppressed when GPC is active. California-mandated "Opt-Out Request Honored" toast confirmation. Filterable suppressed categories. Logged alongside consent records.
- **🇺🇸 New: US multi-state coverage** — region renamed from "CCPA" to "US Multi-State" reflecting 20 states with comprehensive privacy laws as of January 2026 (including Indiana, Kentucky, and Rhode Island).
- **🇮🇳 New: India DPDP Act 2023** — new geolocation region for Indian visitors with granular opt-in consent and one-click withdrawal.
- **🐛 Fix: WordPress 6.7+ translation notice** — resolved `_load_textdomain_just_in_time` notice. Translation loading deferred to point of use via lazy getter.
- **♻️ Backwards compatibility** — legacy region keys `eu_uk` and `ccpa` mapped automatically. `is_eu_uk()` and `is_ccpa()` helper methods retained as aliases.

<details>
<summary><strong>📜 Earlier releases (v1.x)</strong></summary>

### 1.9.2 — Bug Fixes
- Button colours set in admin now correctly apply to the preferences modal Save and Reject buttons
- Banner close X now correctly inherits the admin-set text colour
- All colour declarations hardened with `!important` and extended to cover `:hover`/`:focus` states

### 1.9.1 — Bug Fixes
- Elementor Forms modal — dual-strategy intercept (fetch + XHR) ensures the modal always shows
- Form auto re-submit — raw request body captured and replayed after consent
- Form blocking hard-stops — CF7 uses `wpcf7_spam` filter; WPForms blocks entry saving and email notifications; Elementor uses direct `wp_send_json` response
- Delete Old Logs UI restored to Consent Logs page
- Blocked content placeholder always renders when an iframe is blocked
- Service-specific messaging on placeholder

### 1.9.0 — Form Integration and A/B Testing
- Form Builder Integration — CF7, WPForms, Gravity Forms, Elementor Forms
- A/B Testing — three banner position variants with conversion tracking and one-click winner promotion

### 1.8.x — Elementor Video Blocking
- Elementor YouTube widget blocking pending consent
- Built-in service library (YouTube, Vimeo, Google Maps, and more)
- WP Rocket lazy-load compatibility
- Per-category unblocking fix

### 1.7.0 — Blocked Content Overlay
- Branded placeholder shown in place of blocked iframes

### 1.6.0 — Geolocation
- Auto-detects visitor country, displays region-appropriate banner

### 1.5.0 — Multisite
- Network-aware with automatic detection and settings adjustment

### 1.4.x — IAB TCF v2.3, Google ACM, Privacy Policy Generator
- Full `__tcfapi` implementation, TC String generation
- Google Additional Consent Mode
- Intelligent Privacy Policy Generator

### 1.3.0 — Enhanced Customisation
- Page-specific controls, custom CSS editor, subdomain consent sharing

### 1.2.0 — Internationalisation and Accessibility
- 40+ language auto-translation, WPML/Polylang, WCAG 2.1 AA

### 1.1.0 — Consent Mode Integration
- Google Consent Mode v2, Microsoft UET Consent Mode

### 1.0.0 — Initial Release
- Banner, script blocking, categories, preference centre, consent logging, scanner, CSV export, cookie policy generator

</details>

---

## 📞 Support

| Channel | Link |
|---|---|
| 🌐 Website | [littlewebshack.com](https://littlewebshack.com) |
| 📧 Email | [rob@littlewebshack.com](mailto:rob@littlewebshack.com) |
| 🐙 GitHub | [github.com/HarbourBob](https://github.com/HarbourBob) |
| ☕ Buy me a coffee | [buymeacoffee.com/robertpalmer](https://buymeacoffee.com/robertpalmer) |
| 📖 Docs | See plugin admin pages |

---

## 📄 License

GPL v2 or later — free to use, modify, and distribute.

---

> [!WARNING]
> **Legal Disclaimer:** This plugin provides technical tools to help implement cookie consent mechanisms. It does **not** constitute legal advice. Privacy laws change frequently. Always consult a qualified legal professional for compliance guidance specific to your situation.

---

<div align="center">

**Made with care by Robert Palmer in Cleethorpes, England**

[**🌐 Little Web Shack**](https://littlewebshack.com) · [**👨‍💻 Made by Robert**](https://madebyrobert.co.uk) · [**☕ Buy me a coffee**](https://buymeacoffee.com/robertpalmer)

⭐ **If this plugin saved you money, give it a star.** ⭐

</div>
