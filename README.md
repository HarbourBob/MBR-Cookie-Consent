# MBR Cookie Consent

[![GitHub Release](https://img.shields.io/github/v/release/harbourbob/MBR-Cookie-Consent)](https://github.com/harbourbob/MBR-Cookie-Consent/releases)
[![GitHub Downloads](https://img.shields.io/github/downloads/harbourbob/MBR-Cookie-Consent/total)](https://github.com/harbourbob/MBR-Cookie-Consent/releases)
[![GitHub Forks](https://img.shields.io/github/forks/harbourbob/MBR-Cookie-Consent?style=social)](https://github.com/harbourbob/MBR-Cookie-Consent)
[![GitHub Issues](https://img.shields.io/github/issues/harbourbob/MBR-Cookie-Consent)](https://github.com/harbourbob/MBR-Cookie-Consent/issues)

A comprehensive WordPress cookie consent plugin supporting GDPR, CCPA, and global privacy compliance.

## Description

MBR Cookie Consent provides a complete solution for managing cookie consent on your WordPress website. The plugin automatically blocks non-essential scripts until user consent is given and maintains detailed consent logs for compliance auditing.

From version 1.5.0 the plugin now offers multi-site enterprise-level support.

**Legal Disclaimer:** This plugin provides technical tools to help implement cookie consent mechanisms. It does not constitute legal advice. You are responsible for ensuring your use of this plugin complies with applicable laws and should consult with legal counsel regarding your specific compliance requirements.

## Features

#### Consent Management
- **Cookie Consent Banner**: Customizable banner with Accept/Reject/Customize options
- **Automatic Script Blocking**: Blocks non-essential cookies until explicit consent
- **Preference Center**: Allows users to manage cookie preferences by category
- **Revisit Consent Button**: Floating button for updating consent choices
- **CCPA "Do Not Sell"**: Optional link for California residents
- **Consent Logging**: Records and exports user consent in CSV format
- **GDPR-Compliant Storage**: IP anonymization and proper data handling
- **Multi-site support

#### Consent Mode Integration (Phase 2)
- **Google Consent Mode v2**: Full integration with Google's consent framework
  - Supports ad_storage, ad_user_data, ad_personalization, analytics_storage
  - Functionality and personalization storage controls
  - Configurable default consent states (granted/denied)
  - Ads data redaction for privacy compliance
  - Optional URL passthrough for cookieless conversion tracking
- **Microsoft UET Consent Mode**: Microsoft Advertising compliance
  - EU consent requirements support
  - Automatic tag behavior adjustment based on consent
  - Configurable default states for GDPR compliance

#### Internationalization & Accessibility (Phase 3)
- **Auto-Translation (40+ Languages)**: Automatically translates banner based on browser language
  - Supports 40+ languages including all major European, Asian, and global languages
  - Automatic browser language detection
  - WPML and Polylang integration for advanced multilingual sites
  - No configuration needed - works out of the box
- **Multilingual Plugin Compatibility**:
  - WPML: Full string registration and translation support
  - Polylang: Complete integration with string translation
  - Automatic detection and seamless integration
- **WCAG/ADA Compliance**:
  - Screen reader announcements for all interactions
  - Full keyboard navigation support (Tab, Shift+Tab, Escape, Enter)
  - Focus trap in modal dialogs
  - WCAG 2.1 AA compliant focus indicators
  - Proper ARIA labels, roles, and attributes
  - High contrast mode support
  - Reduced motion support for users with vestibular disorders
  - Semantic HTML structure

#### Banner Customization
- **Layout Options**: Bar (full width), Box (bottom left/right), Popup (center)
- **Position Options**: Top/bottom (for bar layout)
- **Color Customization**: Primary, accept, reject, and text colors
- **Custom Text**: Fully customizable heading, description, and button text
- **Reload on Consent**: Optional page reload after consent action

#### Cookie Scanner & Management
- **Automatic Scanning**: One-click scan to detect scripts on your site
- **Manual Management**: Add, edit, or delete cookies and scripts
- **Category Management**: Organize scripts by Necessary, Analytics, Marketing, Preferences

#### Legal Policy Tools
- **Policy Generator**: Creates WordPress Cookie Policy page template
- **Legal Disclaimers**: Built-in disclaimers throughout admin interface

## Installation

### Manual Installation

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/mbr-cookie-consent/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Cookie Consent > Dashboard** to configure

### From Little Web Shack

1. Visit [Little Web Shack](https://littlewebshack.com)
2. Download MBR Cookie Consent
3. Upload via WordPress admin or FTP
4. Activate and configure

## Quick Start Guide

### 1. Scan Your Website

1. Go to **Cookie Consent > Cookie Scanner**
2. Click "Start Scan" to detect scripts
3. Review detected scripts and add to blocked list

### 2. Configure Categories

1. Go to **Cookie Consent > Categories**
2. Customize category names and descriptions
3. Save changes

### 3. Customize Banner

1. Go to **Cookie Consent > Settings**
2. Set banner position (top/bottom)
3. Customize colors and text
4. Enable/disable features (reject button, CCPA link, etc.)
5. Save settings

### 4. Generate Cookie Policy

1. Go to **Cookie Consent > Dashboard**
2. Click "Generate Cookie Policy Page"
3. Review and publish the page
4. Link to it from your privacy page

### 5. Test the Banner

1. Visit your website in an incognito/private window
2. You should see the cookie banner
3. Test Accept All, Reject All, and Customize options
4. Verify scripts are blocked/unblocked correctly

## Setting Up Consent Mode (Google & Microsoft)

### Google Consent Mode v2 Setup

**Prerequisites:**
- Google Analytics 4 (GA4) or Google Ads tags already installed on your site
- Tags must load AFTER the consent mode initialization

**Setup Steps:**

1. **Enable Google Consent Mode**
   - Go to **Cookie Consent > Settings**
   - Scroll to "Consent Mode Integration"
   - Check "Enable Google Consent Mode v2"

2. **Configure Default Behavior**
   - ✅ **Default to "Denied"** - Recommended for EU/EEA compliance (GDPR)
   - ✅ **Enable Ads Data Redaction** - Recommended for privacy (redacts ad data when consent not given)
   - ⚠️ **Enable URL Passthrough** - Optional, use with caution (passes ad click info in URLs)

3. **Verify Tag Installation Order**
   - Consent mode script loads automatically in `<head>` BEFORE other tags
   - Your GA4/Google Ads tags should load normally (they'll receive consent signals)
   - No changes needed to existing Google tags

4. **Test Consent Mode**
   - Open browser DevTools > Console
   - Look for `dataLayer` commands showing consent state
   - Accept cookies and verify consent updates to "granted"
   - Check Google Tag Assistant for proper consent signals

**What It Does:**
- Sets initial consent state (default: denied for privacy)
- Signals to Google tags which categories are allowed
- Adjusts tag behavior based on consent (e.g., no cookies if denied)
- Enables conversion modeling when full consent not given
- Maintains measurement capabilities while respecting user choices

**Consent Types Controlled:**
- `ad_storage` - Advertising cookies (Google Ads)
- `ad_user_data` - User data for ads
- `ad_personalization` - Personalized advertising
- `analytics_storage` - Analytics cookies (GA4)
- `functionality_storage` - Functional cookies
- `personalization_storage` - Personalization cookies

### Microsoft UET Consent Mode Setup

**Prerequisites:**
- Microsoft UET (Universal Event Tracking) tag already installed
- UET tag must load AFTER consent mode initialization

**Setup Steps:**

1. **Enable Microsoft Consent Mode**
   - Go to **Cookie Consent > Settings**
   - Scroll to "Consent Mode Integration"
   - Check "Enable Microsoft UET Consent Mode"

2. **Configure Default Behavior**
   - ✅ **Default to "Denied"** - Recommended for EU compliance (GDPR)

3. **Verify UET Tag**
   - Your existing UET tag will automatically receive consent signals
   - No modifications needed to UET implementation
   - Consent mode script loads before UET tag

4. **Test UET Consent**
   - Open browser DevTools > Console
   - Look for `uetq` (UET queue) consent commands
   - Accept cookies and verify consent updates

**What It Does:**
- Controls Microsoft Advertising cookie behavior
- Signals consent state to UET tags
- Ensures GDPR compliance for Microsoft ads
- Maintains conversion tracking within consent parameters

### Testing Both Consent Modes

1. **Before Consent:**
   - Open DevTools > Console
   - Refresh page
   - Look for consent mode initialization (should show "denied" by default)

2. **Accept All Cookies:**
   - Click "Accept All"
   - Check console for consent "update" to "granted"
   - Verify analytics and ad cookies are now set

3. **Reject All / Partial Consent:**
   - Revisit consent settings
   - Reject all or customize categories
   - Verify consent updates and appropriate cookies are blocked

### Important Notes

- Consent mode does NOT replace cookie blocking - it works alongside it
- Tags still load but behave differently based on consent signals
- "Denied" state enables cookieless measurement (pings without cookies)
- Google and Microsoft can still measure conversions without full consent (aggregated/modeled data)
- Always test in your target regions (EU behavior may differ from US)

## Managing Blocked Scripts

### Auto-Detection via Scanner

The easiest way to manage scripts is using the built-in scanner:

1. **Cookie Consent > Cookie Scanner**
2. Click **Start Scan**
3. Review detected scripts
4. Click **Add to Blocked List** for scripts you want to block

### Manual Script Addition

To manually add scripts:

1. **Cookie Consent > Cookie Scanner**
2. Scroll to "Add Custom Script"
3. Fill in:
   - **Name**: Display name (e.g., "Google Analytics")
   - **Identifier**: Script source or content pattern (e.g., "google-analytics.com/analytics.js")
   - **Type**: src (external), inline (inline script), or iframe
   - **Category**: necessary, analytics, marketing, or preferences
4. Click **Add Script**

### Script Types

- **src**: External scripts with src attribute (e.g., `<script src="...">`)
- **inline**: Inline scripts with specific content (e.g., containing "gtag(")
- **iframe**: Embedded iframes (e.g., YouTube, Google Maps)

## Cookie Categories

### Necessary
- Always active
- Essential for website functionality
- Cannot be disabled by users
- Example: Session cookies, security cookies

### Analytics
- Tracks website usage and performance
- Helps understand visitor behavior
- Requires user consent
- Example: Google Analytics, Matomo

### Marketing
- Used for advertising and retargeting
- Tracks users across websites
- Requires user consent
- Example: Facebook Pixel, Google Ads

### Preferences
- Remembers user preferences
- Enhances user experience
- Requires user consent
- Example: Language preferences, UI settings

## Consent Logging

All consent actions are logged to the database with:
- Timestamp
- User ID (if logged in)
- Anonymized IP address
- Consent given (yes/no)
- Categories accepted
- Consent method (accept_all, reject_all, preferences)

### Exporting Logs

1. Go to **Cookie Consent > Consent Logs**
2. Optionally filter by date range
3. Click **Export to CSV**
4. Download the file for compliance records

### Deleting Old Logs

1. Go to **Cookie Consent > Consent Logs**
2. Specify number of days
3. Click **Delete Old Logs**
4. Logs older than specified days will be permanently deleted

## GDPR Compliance Features

- ✅ Explicit opt-in required for non-essential cookies
- ✅ Clear information about cookie usage
- ✅ Easy way to revoke consent
- ✅ IP address anonymization
- ✅ Consent logging and proof
- ✅ Granular category control
- ✅ Cookie policy generator

## CCPA Compliance Features

- ✅ "Do Not Sell or Share My Personal Information" link
- ✅ Opt-out mechanism
- ✅ Clear disclosure of data collection

## Developer Notes

### Programmatic Consent Check

Check if user has consented to a specific category:

```javascript
// Check if analytics is allowed
window.MbrCcConsent.hasCategoryConsent('analytics', function(allowed) {
    if (allowed) {
        // Load analytics script
    }
});
```

### Hooks & Filters

Coming in future versions.

### Script Blocking Mechanism

The plugin uses output buffering to intercept and modify HTML before it reaches the browser. Scripts matching the blocked list have their `type` attribute changed to `text/plain` and a `data-mbr-cc-blocked` attribute added. When consent is given, the scripts are unblocked and executed.

## Technical Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Support

For support, feature requests, or bug reports:
- Email: [Your contact email]
- Website: [Little Web Shack](https://littlewebshack.com)
- Documentation: See plugin admin pages

## Roadmap

### Phase 2 (Completed in v1.1.0)
- ✅ Google Consent Mode v2 integration
- ✅ Microsoft UET Consent Mode

### Phase 3 (Completed in v1.2.0)
- ✅ Auto-translation (40+ languages)
- ✅ WPML and Polylang compatibility
- ✅ WCAG/ADA accessibility enhancements

### Phase 4 (Completed in v1.3.0)
- ✅ Page-specific banner controls
- ✅ Custom CSS editor
- ✅ Subdomain consent sharing

### Phase 5 (Completed in v1.4.0)
- ✅ IAB TCF v2.3 framework
- ✅ Google Additional Consent Mode

### Future Enhancements
- Consent Mode API for developers
- Integration with popular form builders
- A/B testing for banner variations

## Changelog

### 1.5.1 - Minor Bug Fixing
- **Minor bug fixing to the Consent Log

### 1.5.0 - Multi-Site Support
- **Automatic Detection of multi-site set-up

### 1.4.1 - Privacy Policy Generator
- **Intelligent Privacy Policy Generator**
  - Analyzes site configuration and generates comprehensive privacy policy
  - Detects e-commerce, analytics, advertising, email marketing automatically
  - Customizes policy based on enabled features (TCF, ACM, GDPR, CCPA)
  - Includes sections for all major privacy frameworks
  - GDPR-compliant sections (legal basis, data retention, user rights)
  - CCPA-compliant sections (California rights, do not sell)
  - IAB TCF section when enabled
  - Children's privacy protection
  - International data transfers
  - Data security measures
  - Third-party service disclosure
  - E-commerce specific sections
  - Generated in draft status for legal review
  - One-click generation from dashboard

### 1.4.0 - Phase 5: Advanced Consent Management (IAB TCF v2.3 & Google ACM)
- **IAB Transparency & Consent Framework v2.3**
  - Full __tcfapi JavaScript API implementation
  - TC String generation and storage (euconsent-v2 cookie)
  - Support for 10 standard consent purposes
  - Support for 2 special features (geolocation, device scanning)
  - Global Vendor List (GVL) integration ready
  - GDPR applies detection
  - Publisher country code configuration
  - Purpose One Treatment support
  - Consent string encoding/decoding infrastructure
  - Event listener support for vendor tags
  - IAB CMP registration ready
- **Google Additional Consent Mode (ACM)**
  - AC String generation for Google Ad Tech Providers
  - Support for Google ATPs outside IAB GVL
  - Integration with Google Ads, DoubleClick, AdSense, etc.
  - AC String format: 1~{provider.ids}
  - Cookie storage (mbr_cc_ac_string)
  - Automatic provider consent mapping
  - Google FC (Funding Choices) compatibility
- **Combined TCF + ACM Support**
  - Simultaneous operation of both frameworks
  - Unified consent collection
  - Comprehensive ad tech coverage
  - Enterprise publisher compliance

### 1.3.0 - Phase 4: Enhanced Customization
- **Page-Specific Banner Controls**
  - Quick toggles for login, checkout, cart, and account pages
  - URL pattern exclusion with wildcard support
  - WooCommerce and Easy Digital Downloads detection
  - Regex-based pattern matching for flexible exclusions
- **Custom CSS Editor**
  - Built-in CSS editor in settings
  - Override any banner styles
  - Helpful class reference guide
  - Safe CSS sanitization
- **Subdomain Consent Sharing**
  - Share consent across all subdomains automatically
  - Auto-detection of root domain
  - Manual domain override for complex setups
  - Works with .co.uk and other TLDs
  - Proper cookie domain and path handling
  - Real-time configuration preview

### 1.2.0 - Phase 3: Internationalization & Accessibility
- **Auto-Translation (40+ Languages)**
  - Automatic browser language detection
  - Support for 40+ languages covering global audience
  - Seamless integration - no configuration required
  - Covers all major European languages (Spanish, French, German, Italian, Portuguese, Dutch, Polish, Russian, etc.)
  - Asian language support (Japanese, Chinese, Korean, Thai, Vietnamese, etc.)
  - Middle Eastern languages (Arabic, Hebrew, Turkish, Persian)
  - Nordic languages (Swedish, Danish, Finnish, Norwegian, Icelandic)
  - And many more regional languages
- **WPML & Polylang Compatibility**
  - Automatic string registration for translation
  - Full integration with WPML's string translation
  - Complete Polylang support with string translation
  - Automatic detection and setup
  - Category names and descriptions fully translatable
- **WCAG/ADA Compliance Features**
  - Screen reader announcements (ARIA live regions)
  - Full keyboard navigation with proper focus management
  - Focus trap in modal dialogs (Tab/Shift+Tab containment)
  - Escape key closes modals
  - WCAG 2.1 AA focus indicators (3px outline, 2px offset)
  - Semantic HTML with proper heading structure
  - ARIA labels, roles, and attributes throughout
  - Dialog roles for banner and modal
  - High contrast mode CSS support
  - Reduced motion support (respects prefers-reduced-motion)
  - Screen reader only content for context
  - Accessible form labels and descriptions

### 1.1.0 - Phase 2: Consent Mode Integration
- **Google Consent Mode v2 Integration**
  - Full support for all consent types (ad_storage, ad_user_data, ad_personalization, analytics_storage, functionality_storage, personalization_storage)
  - Automatic consent signal updates to Google tags
  - Configurable default consent states (recommended: denied for EU/EEA)
  - Ads data redaction when marketing consent not given
  - Optional URL passthrough for cookieless conversion tracking
  - Proper loading order (consent mode loads before Google tags)
- **Microsoft UET Consent Mode Integration**
  - Full Microsoft Advertising (Bing Ads) consent mode support
  - EU consent requirements compliance
  - Automatic UET tag behavior adjustment
  - Configurable default states for GDPR compliance
- **Enhanced Admin Interface**
  - New "Consent Mode Integration" settings section
  - Detailed configuration options for both platforms
  - Helpful tooltips and compliance recommendations
  - Important notices for proper tag installation

### 1.0.7 - Banner Layout Enhancements
- Added Box Layout (Bottom Left) - Compact banner in bottom left corner
- Added Box Layout (Bottom Right) - Compact banner in bottom right corner  
- Added Popup Layout (Center) - Modal-style banner in center with overlay
- Enhanced animations for new layouts
- Improved responsive behavior for box and popup layouts

### 1.0.0 - Phase 1 MVP
- Initial release
- Cookie consent banner with customization
- Script tag interception and blocking
- Cookie categories (Necessary, Analytics, Marketing, Preferences)
- Preference center modal
- Consent logging to database
- Cookie scanner
- CSV export
- Cookie policy generator
- GDPR and CCPA basic compliance features

## License

GPL v2 or later

## Credits

**Author**: Made by Robert  
**Website**: [madeberobert.com](https://madeberobert.co.uk)  
**Plugin Home**: [Little Web Shack](https://littlewebshack.com)

---

**Remember:** This plugin helps with technical implementation but does not provide legal advice. Always consult with a qualified legal professional for compliance guidance.
