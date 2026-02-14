# 🍪 MBR Cookie Consent

[![WordPress Plugin Downloads](https://img.shields.io/badge/downloads-0-blue.svg)](https://github.com/harbourbob/mbr-cookie-consent)
[![WordPress Plugin Version](https://img.shields.io/badge/version-1.4.1-green.svg)](https://github.com/harbourbob/mbr-cookie-consent/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Made by Robert](https://img.shields.io/badge/made%20by-Robert-orange.svg)](https://madebyrobert.com)

**The completely free, professional-grade cookie consent solution for WordPress.** 

GDPR, CCPA, and global privacy law compliant cookie consent management with automatic script blocking, intelligent scanning, and comprehensive consent logging. No subscriptions, no limitations, no upsells—just enterprise-level functionality available to everyone.

---

## ✨ Why MBR Cookie Consent?

**Premium Features, Zero Cost**
- 🆓 **100% Free Forever** - No "pro" versions, no hidden costs
- 🚀 **Enterprise-Grade** - Features that typically cost $200-500/year
- 🎯 **Easy Setup** - Working in minutes, not hours
- 🛡️ **Privacy First** - Your data stays on your server
- 🌍 **Italian Law Compliant** - Optional X close button for Italian regulations
- 📊 **Intelligent Scanner** - Auto-detect scripts across 1000+ pages

---

## 🎯 Key Features

### 🎨 **Professional Customization**
- **5 Banner Layouts**: Full bar (top/bottom), corner boxes (left/right), center popup
- **Complete Branding**: Add your logo (150x150px recommended)
- **Full Color Control**: Match your brand perfectly
- **Policy Links**: Privacy Policy & Cookie Policy integration
- **Responsive Design**: Perfect on all devices

### 🔍 **Intelligent Cookie Scanner**
- **Site-Wide Scanning**: Analyze up to 1000 pages in one click
- **Auto-Detection**: Finds scripts, iframes, and third-party services
- **Category Organization**: Automatically sorts by Necessary, Analytics, Marketing, Preferences
- **Smart Deduplication**: Shows unique scripts with page occurrence count
- **One-Click Blocking**: Add scripts to blocked list without page refresh
- **Real-Time Updates**: See blocked scripts appear instantly with green glow animation

### 🛡️ **Advanced Compliance**
- **GDPR Compliant**: Full EU privacy regulation support
- **CCPA Support**: California Consumer Privacy Act ready
- **Italian Law**: Optional X close button (required by Italian regulations)
- **Consent Logging**: Track and export all consent records
- **Script Blocking**: Automatic blocking until consent given
- **Granular Control**: 4 cookie categories with user customization

### 📋 **Cookie Categories**
1. **Necessary** - Always active (essential site functions)
2. **Analytics** - Usage tracking and performance
3. **Marketing** - Advertising and remarketing
4. **Preferences** - User settings and personalization

### 🎛️ **Banner Options**
- Accept All / Reject All / Customize buttons
- Floating "Cookie Settings" revisit button
- Auto-reload on consent (optional)
- Customizable expiry (1-730 days)
- Multiple positions and layouts

### 📊 **Consent Management**
- Database logging of all consent events
- CSV export for compliance records
- User preference tracking
- Consent modification history
- CCPA "Do Not Sell" opt-out link

### 🔧 **Developer Friendly**
- Clean, documented code
- WordPress coding standards
- Extensible architecture
- No jQuery conflicts
- Minimal performance impact

---

## 📸 Screenshots

### Banner Layouts
The plugin offers 5 flexible banner layouts to match your design:

**Full-Width Bars**
- Bottom Bar (default)
- Top Bar

**Corner Boxes**
- Bottom Left Corner
- Bottom Right Corner

**Center Popup**
- Animated dropdown from top
- Centered on screen

### Settings Interface
Clean, intuitive admin interface with real-time previews.

### Cookie Scanner
Intelligent scanning with category organization and one-click blocking.

---

## 🚀 Quick Start

### Installation

1. **Download** the latest release from the [Releases page](https://github.com/harbourbob/mbr-cookie-consent/releases)
2. **Upload** to WordPress via Plugins > Add New > Upload Plugin
3. **Activate** the plugin
4. Navigate to **Cookie Consent** in WordPress admin
5. **Configure** your banner settings
6. **Scan** your site for cookies
7. **Go live!**

### Basic Configuration

```
1. Settings Tab
   ├── Choose banner layout (bar, corner box, or popup)
   ├── Set your brand colors
   ├── Add your logo (optional)
   ├── Configure policy links
   └── Enable Italian X button if needed

2. Cookie Scanner Tab
   ├── Run site-wide scan (up to 1000 pages)
   ├── Review detected scripts by category
   ├── Click "Add to Blocked" for each script
   └── Scripts automatically blocked on frontend

3. Test
   ├── Open your site in private/incognito window
   ├── Verify banner appears correctly
   ├── Test Accept/Reject/Customize flows
   └── Confirm scripts are blocked/unblocked properly
```

---

## 🎨 Customization Examples

### Example 1: Minimal Bottom Bar
```
Position: Bottom
Layout: Bar
Primary Color: #2196F3
Show Reject Button: No
Show Close Button: No
```

### Example 2: Italian Compliant Corner Box
```
Position: Bottom Right
Layout: Box (Right)
Primary Color: #4CAF50
Show Close Button: Yes (Italian law)
Policy Links: Both enabled
```

### Example 3: Premium Branded Popup
```
Position: Center
Layout: Popup
Logo: Your 150x150 logo
Primary Color: Match brand
Policy Links: Enabled
Accept Color: Brand primary
Reject Color: Brand secondary
```

---

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Modern Browser**: For admin interface

---

## 🔧 Advanced Usage

### Manual Script Blocking

You can manually add scripts to block in the Scanner tab:

1. Click "Add Custom Script"
2. Enter script identifier (src URL, inline pattern, or iframe URL)
3. Select type (External, Inline, or Iframe)
4. Choose category
5. Click "Add Script"

### Consent Logging

All consent events are logged to the database:
- Timestamp
- User IP (anonymized option available)
- Consent choices
- User agent
- Page URL

Export logs via Settings > Consent Logs > Export CSV

### Policy Generator

Built-in cookie policy generator:
1. Navigate to Policy Generator tab
2. Select detected cookies
3. Generate policy content
4. Copy to your Cookie Policy page

---

## 🌟 What Makes This Different?

### vs. Other Free Plugins
- ❌ **Other plugins**: Basic bar only, limited customization
- ✅ **MBR Cookie Consent**: 5 layouts, full branding, advanced scanner

### vs. Premium Plugins ($200-500/year)
- ❌ **Premium plugins**: Subscription required, vendor lock-in
- ✅ **MBR Cookie Consent**: 100% free forever, self-hosted

### vs. SaaS Solutions ($19-99/month)
- ❌ **SaaS**: Monthly fees, external dependencies
- ✅ **MBR Cookie Consent**: No recurring costs, complete control

---

## 🗺️ Roadmap

### Coming in Phase 2
- [ ] Geo-targeting (show banner only for EU visitors)
- [ ] A/B testing for consent rates
- [ ] Cookie blocking by category
- [ ] Integration with Google Tag Manager
- [ ] Multilingual support
- [ ] Custom CSS editor
- [ ] Consent analytics dashboard
- [ ] API for developers

**Want to contribute?** Submit feature requests via [GitHub Issues](https://github.com/harbourbob/mbr-cookie-consent/issues)!

---

## 🤝 Contributing

Contributions are welcome! Here's how:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- Code follows WordPress coding standards
- Features are well-documented
- Changes are tested across browsers
- Commit messages are descriptive

---

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Latest: Version 1.4.1 (February 2026)
- ✨ Added 3 new banner layouts (corner boxes + center popup)
- 🎨 Enhanced layout system with animations
- 📱 Improved mobile responsiveness
- 🐛 Various bug fixes and optimizations

---

## 🆘 Support

### Documentation
- [Installation Guide](https://github.com/harbourbob/mbr-cookie-consent/wiki/Installation)
- [Configuration Guide](https://github.com/harbourbob/mbr-cookie-consent/wiki/Configuration)
- [FAQ](https://github.com/harbourbob/mbr-cookie-consent/wiki/FAQ)
- [Troubleshooting](https://github.com/harbourbob/mbr-cookie-consent/wiki/Troubleshooting)

### Community
- [GitHub Issues](https://github.com/harbourbob/mbr-cookie-consent/issues) - Bug reports & feature requests
- [Discussions](https://github.com/harbourbob/mbr-cookie-consent/discussions) - Questions & ideas

### Professional Support
Need customization or implementation help? Contact [Made by Robert](https://madebyrobert.co.uk)

---

## ⚖️ Legal

### License
MBR Cookie Consent is licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

### Legal Disclaimer
This plugin provides technical tools to help implement cookie consent mechanisms. **It does not constitute legal advice.** Users are responsible for ensuring their use of this plugin complies with applicable laws and should consult with legal counsel regarding their specific compliance requirements.

### Privacy
This plugin:
- ✅ Stores all data on your WordPress database
- ✅ Makes no external API calls
- ✅ Collects no usage statistics
- ✅ Shares no data with third parties
- ✅ Is 100% self-hosted

---

## 👨‍💻 About the Author

**Made by Robert** specializes in creating professional WordPress solutions that are accessible to everyone.

- 🌐 Website: [madebyrobert.com](https://madebyrobert.co.uk)
- 🔧 Plugin Hub: [Little Web Shack](https://littlewebshack.com)
- 💼 Available for custom WordPress development

### Other Free Plugins
- **MBR Live Radio Player** - Stream audio with metadata display
- **MBR AI Chatbot** - Claude & ChatGPT integration
- **MBR Performance** - Site optimization toolkit
- **MBR WebP Converter** - Automatic image optimization

---

## 🌟 Show Your Support

If this plugin has helped you achieve GDPR/CCPA compliance without breaking the bank, please:

- ⭐ **Star this repository** on GitHub
- 📢 **Share** with other WordPress users
- 🐛 **Report bugs** to help improve the plugin
- 💡 **Suggest features** for future releases
- ✍️ **Write a review** on WordPress.org (when available)

---

## 📊 Stats

![GitHub stars](https://img.shields.io/github/stars/harbourbob/mbr-cookie-consent?style=social)
![GitHub forks](https://img.shields.io/github/forks/harbourbob/mbr-cookie-consent?style=social)
![GitHub watchers](https://img.shields.io/github/watchers/harbourbob/mbr-cookie-consent?style=social)

---

**Built with ❤️ by [Robert](https://madebyrobert.com) • Empowering websites with privacy compliance since 2026**


