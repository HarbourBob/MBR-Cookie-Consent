(function($) {
    'use strict';
    
    var MbrCookieBanner = {

        // Set to true as soon as the user acts on the banner in this page session.
        // Prevents checkConsent() from re-showing the banner if the cookie read-back
        // fails immediately after writing (can happen with explicit domain scoping,
        // e.g. .example.com vs www.example.com, before the browser propagates it).
        _consentSaved: false,
        
        init: function() {
            this.checkConsent();
            this.bindEvents();
            this.setupAccessibility();
        },
        
        setupAccessibility: function() {
            // Add keyboard navigation
            var self = this;
            
            // Trap focus in modal when open
            $(document).on('keydown', function(e) {
                if ($('#mbr-cc-modal').is(':visible')) {
                    self.trapFocus(e, '#mbr-cc-modal');
                }
            });
            
            // Escape key closes modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#mbr-cc-modal').is(':visible')) {
                    self.hidePreferences();
                }
            });
        },
        
        trapFocus: function(e, container) {
            if (e.key !== 'Tab') return;
            
            var focusableElements = $(container).find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            var firstElement = focusableElements.first();
            var lastElement = focusableElements.last();
            
            if (e.shiftKey) { // Shift + Tab
                if (document.activeElement === firstElement[0]) {
                    lastElement.focus();
                    e.preventDefault();
                }
            } else { // Tab
                if (document.activeElement === lastElement[0]) {
                    firstElement.focus();
                    e.preventDefault();
                }
            }
        },
        
        announce: function(message) {
            // Announce to screen readers
            var $announce = $('#mbr-cc-sr-announce');
            if ($announce.length) {
                $announce.text(message);
                setTimeout(function() {
                    $announce.text('');
                }, 1000);
            }
        },
        
        /**
         * Parse the stored consent cookie.
         *
         * Returns null for anything unreadable rather than throwing. Since
         * blocking became unconditional this runs on every page load and is the
         * only thing that releases a consenting visitor's scripts — an
         * exception here would take the banner down with it and leave the
         * visitor with no way to consent at all.
         */
        parseConsent: function(raw) {
            if (!raw || typeof raw !== 'string' || raw.length > 2048) {
                return null;
            }

            try {
                var parsed = JSON.parse(raw);
                return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : null;
            } catch (e) {
                return null;
            }
        },

        /**
         * Show the placeholders belonging to embeds that remain blocked.
         *
         * They are rendered hidden so that visitors who consented never see
         * them flash. Anything still blocked once consent has been applied is
         * revealed here.
         */
        revealBlockedPlaceholders: function() {
            $('iframe[data-mbr-cc-blocked="true"], [data-mbr-cc-facade="true"]').each(function() {
                $(this).prev('.mbr-cc-blocked-wrapper').removeAttr('data-mbr-cc-pending');
            });
        },

        /**
         * Apply the visitor's regional configuration to the rendered banner.
         *
         * The document arrives in the site's own configuration, identical for
         * everyone, so that a page cache can serve one copy to the world. That
         * is the whole reason this runs here rather than in PHP: deciding on
         * the server which buttons a visitor sees means the first visitor's
         * region is written into the copy every later visitor receives, and a
         * cached US banner served to somebody in the EU is one with no Reject
         * button on it.
         *
         * Only the keys PHP marks with data-mbr-cc-region are touched, and the
         * set of those keys is fixed by MBR_CC_Region_Config::$client_keys. If
         * a region wants to change something not in that list, the answer is to
         * implement it here, not to add it to the PHP array and hope.
         */
        applyRegionConfig: function(config) {
            if (!config || typeof config !== 'object') {
                return;
            }

            document.querySelectorAll('[data-mbr-cc-region]').forEach(function(el) {
                var key = el.getAttribute('data-mbr-cc-region');

                if (!Object.prototype.hasOwnProperty.call(config, key)) {
                    return;
                }

                var value = config[key];

                if (typeof value === 'boolean') {
                    el.style.display = value ? '' : 'none';
                    return;
                }

                if (typeof value === 'string' && value !== '') {
                    // textContent, never innerHTML. This is text that arrived
                    // over the network, and banner copy has no need of markup.
                    el.textContent = value;

                    // A region that supplies its own wording outranks the
                    // community translation of the generic wording, which says
                    // something legally different. Same rule the translation
                    // layer already applies to text the site owner rewrote.
                    el.removeAttribute('data-mbr-cc-i18n');
                }
            });
        },

        /**
         * Fetch the visitor's region, then run the callback either way.
         *
         * Failure is not an error condition worth reporting to the visitor. A
         * blocked request, an offline CDN, a security plugin that has decided
         * the REST API is a threat — in every case the banner shown is the one
         * PHP already rendered from the site's own settings, which is a valid
         * banner. The only unacceptable outcome is no banner at all, so the
         * timeout is a hard deadline rather than a suggestion.
         */
        resolveRegion: function(done) {
            // No banner on this page — an excluded page showing only the
            // Cookie Settings button — so there is nothing regional to decide
            // and no reason to spend a request deciding it.
            if (!$('#mbr-cc-banner').length) {
                done();
                return;
            }

            if (!mbrCcBanner.geoEnabled || !mbrCcBanner.regionUrl || typeof window.fetch !== 'function') {
                done();
                return;
            }

            var self = this;
            var settled = false;

            var finish = function(config) {
                if (settled) {
                    return;
                }
                settled = true;

                if (config) {
                    self.applyRegionConfig(config);
                }

                done();
            };

            setTimeout(function() {
                finish(null);
            }, mbrCcBanner.regionTimeout || 1500);

            fetch(mbrCcBanner.regionUrl, {
                credentials: 'omit',
                cache: 'no-store'
            }).then(function(response) {
                return response.ok ? response.json() : null;
            }).then(function(data) {
                finish(data && data.config ? data.config : null);
            })['catch'](function() {
                finish(null);
            });
        },

        checkConsent: function() {
            var self = this;
            var consent = this.getCookie('mbr_cc_consent');
            
            // Check for Global Privacy Control (GPC) signal.
            // Required by 12+ US states as of January 2026.
            if (this.isGpcActive()) {
                this.handleGpcSignal(consent);
                return;
            }
            
            var parsed = this.parseConsent(consent);

            if (!parsed) {
                // No stored choice, or one we cannot read. Everything stays
                // blocked, which is the safe outcome, and the banner asks again.
                //
                // Placeholders first, and not inside the callback below. What
                // they say does not vary by region, so making a visitor stare
                // at a gap where a video should be while a lookup completes
                // buys nothing.
                this.revealBlockedPlaceholders();

                // The region lookup happens only on this path. A visitor who
                // has already chosen is not being asked anything, so there is
                // nothing regional to decide and no reason to spend a request
                // finding out where they are — which is most traffic, and the
                // reason this costs almost nothing in aggregate.
                this.resolveRegion(function() {
                    self.showBanner();
                });
            } else {
                this.showRevisitButton();
                this.unblockScripts(parsed);
            }
        },
        
        /**
         * Detect a Global Privacy Control signal.
         *
         * navigator.globalPrivacyControl only. The docblock here used to claim
         * this also consulted server-side detection passed through mbrCcGpc;
         * that was removed when the Sec-GPC header turned out to describe
         * whoever generated the cached page rather than whoever was reading it.
         */
        isGpcActive: function() {
            // Client-side: navigator.globalPrivacyControl
            if (typeof navigator !== 'undefined' && navigator.globalPrivacyControl === true) {
                return true;
            }
            
            // The Sec-GPC request header is deliberately not consulted here.
            // It was passed through from PHP, which meant it described whoever
            // generated the cached page rather than the person reading it.
            // navigator.globalPrivacyControl is set by every browser and
            // extension that sends the header, and is always the current
            // visitor's own signal.
            
            return false;
        },
        
        /**
         * Handle an active GPC signal.
         * Suppress marketing/advertising cookies, show opt-out confirmation,
         * and still allow the banner for categories not covered by GPC.
         */
        handleGpcSignal: function(existingConsent) {
            var self = this;
            var suppressedCategories = (typeof mbrCcGpc !== 'undefined' && mbrCcGpc.suppressCategories)
                ? mbrCcGpc.suppressCategories
                : ['marketing'];
            
            // Build a consent object that honours GPC:
            // - necessary: always true
            // - suppressed categories: forced false
            // - other categories: use existing consent or leave for banner
            var consent = this.parseConsent(existingConsent) || { necessary: true };
            
            // Force suppressed categories off regardless of stored consent
            for (var i = 0; i < suppressedCategories.length; i++) {
                consent[suppressedCategories[i]] = false;
            }
            
            // Remove the 'all' flag if it was set — GPC overrides blanket acceptance
            if (consent.all === true) {
                delete consent.all;
                // Restore non-suppressed categories to true since they had "all"
                if (mbrCcBanner.categories) {
                    $.each(mbrCcBanner.categories, function(slug) {
                        if (suppressedCategories.indexOf(slug) === -1) {
                            consent[slug] = true;
                        }
                    });
                }
            }
            
            // Apply the GPC-modified consent
            this.unblockScripts(consent);
            this.showRevisitButton();
            
            // Show the "Opt-Out Request Honored" confirmation (California requirement)
            if (typeof mbrCcGpc !== 'undefined' && mbrCcGpc.showHonoredConfirmation) {
                this.showGpcConfirmation(mbrCcGpc.honoredMessage || 'Opt-Out Request Honored');
            }
            
            // If the visitor has NOT yet interacted with the banner at all,
            // still show it so they can make choices for non-GPC categories.
            //
            // The region matters more here than anywhere, not less. A GPC
            // signal is largely a US instrument, and the "Do Not Sell or Share"
            // link this visitor is entitled to see is revealed by the US
            // regional configuration. Until 2.3.4 that link was switched on in
            // PHP from the Sec-GPC header, which meant it was switched on in
            // the cached copy for everyone else too.
            if (!existingConsent) {
                this.resolveRegion(function() {
                    self.showBanner();
                });
            }
        },
        
        /**
         * Show the GPC "Opt-Out Request Honored" confirmation toast.
         * Required by California CCPA regulations effective January 2026.
         * Brief, non-intrusive — appears for a few seconds then fades.
         */
        showGpcConfirmation: function(message) {
            // Don't show if already shown this session
            if (this._gpcConfirmShown) {
                return;
            }
            this._gpcConfirmShown = true;
            
            var $toast = $('<div/>', {
                'id': 'mbr-cc-gpc-toast',
                'role': 'status',
                'aria-live': 'polite',
                'style': 'position:fixed;bottom:20px;left:20px;z-index:999999;' +
                          'background:#1a7a1a;color:#fff;padding:12px 20px;border-radius:6px;' +
                          'font-size:14px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
                          'box-shadow:0 4px 12px rgba(0,0,0,0.2);display:none;max-width:360px;' +
                          'line-height:1.4;'
            }).html('&#x2714; ' + message);
            
            $('body').append($toast);
            $toast.fadeIn(300);
            
            setTimeout(function() {
                $toast.fadeOut(500, function() {
                    $toast.remove();
                });
            }, 5000);
        },
        
        bindEvents: function() {
            var self = this;
            
            // Accept All button
            $('#mbr-cc-accept-all').on('click', function(e) {
                e.preventDefault();
                self.acceptAll();
            });
            
            // Reject All button
            $('#mbr-cc-reject-all, #mbr-cc-reject-all-modal').on('click', function(e) {
                e.preventDefault();
                self.rejectAll();
            });
            
            // Close button (Italian law) - hides banner without saving consent
            $('#mbr-cc-close').on('click', function(e) {
                e.preventDefault();
                $('#mbr-cc-banner').fadeOut();
                // Hide popup overlay if present
                if ($('#mbr-cc-popup-overlay').length) {
                    $('#mbr-cc-popup-overlay').fadeOut().removeClass('active');
                }
            });
            
            // Customize button
            $('#mbr-cc-customize').on('click', function(e) {
                e.preventDefault();
                self.showPreferences();
            });
            
            // Save preferences
            $('#mbr-cc-save-preferences').on('click', function(e) {
                e.preventDefault();
                self.savePreferences();
            });
            
            // Close modal
            $('#mbr-cc-modal-close, .mbr-cc-modal__overlay').on('click', function(e) {
                e.preventDefault();
                self.hidePreferences();
            });
            
            // Revisit consent
            $('#mbr-cc-revisit').on('click', function(e) {
                e.preventDefault();
                self.showPreferences();
            });
            
            // CCPA opt-out
            $('#mbr-cc-ccpa-optout').on('click', function(e) {
                e.preventDefault();
                self.rejectAll();
            });
            
            // Popup overlay click (optional - close banner)
            // Uncomment if you want clicking outside to close the popup
            // $('#mbr-cc-popup-overlay').on('click', function(e) {
            //     e.preventDefault();
            //     $('#mbr-cc-banner').fadeOut();
            //     $(this).fadeOut().removeClass('active');
            // });
        },
        
        showBanner: function() {
            // Don't re-show if the user has already interacted with the banner
            // during this page session.
            if (this._consentSaved) {
                return;
            }

            // This page may be one the owner excluded the banner from, in which
            // case there is no banner element to show — but scripts are still
            // being held back, so the visitor needs some way to say yes. The
            // floating Cookie Settings button is that way.
            if (!$('#mbr-cc-banner').length) {
                this.showRevisitButton();
                return;
            }

            $('#mbr-cc-banner').fadeIn(300);
            // Show popup overlay if using popup layout
            if ($('#mbr-cc-popup-overlay').length) {
                $('#mbr-cc-popup-overlay').fadeIn(300).addClass('active');
            }
            // Announce to screen readers
            this.announce('Cookie consent banner displayed. Please review your privacy choices.');
            // Focus on first button
            setTimeout(function() {
                $('#mbr-cc-accept-all').focus();
            }, 350);
        },
        
        hideBanner: function() {
            $('#mbr-cc-banner').fadeOut(300);
            // Hide popup overlay
            if ($('#mbr-cc-popup-overlay').length) {
                $('#mbr-cc-popup-overlay').fadeOut(300).removeClass('active');
            }
            this.showRevisitButton();
        },
        
        showPreferences: function() {
            $('#mbr-cc-modal').fadeIn(300);
            $('body').addClass('mbr-cc-modal-open');
            this.announce('Cookie preferences dialog opened. Use Tab to navigate options.');
            // Focus on first checkbox
            setTimeout(function() {
                $('#mbr-cc-modal input[type="checkbox"]').first().focus();
            }, 350);
        },
        
        hidePreferences: function() {
            $('#mbr-cc-modal').fadeOut(300);
            $('body').removeClass('mbr-cc-modal-open');
        },
        
        showRevisitButton: function() {
            if (mbrCcBanner.revisitEnabled) {
                setTimeout(function() {
                    $('#mbr-cc-revisit').fadeIn(300);
                }, 1000);
            }
        },
        
        acceptAll: function() {
            var consent = { all: true };
            
            // Set all categories to true
            if (mbrCcBanner.categories) {
                $.each(mbrCcBanner.categories, function(slug, category) {
                    consent[slug] = true;
                });
            }
            
            this.announce('All cookies accepted. Your preferences have been saved.');
            this.saveConsent(consent, 'accept_all');
        },
        
        rejectAll: function() {
            var consent = { necessary: true };
            
            this.announce('All optional cookies rejected. Only necessary cookies will be used.');
            this.saveConsent(consent, 'reject_all');
        },
        
        savePreferences: function() {
            var consent = {};
            
            // Get selected categories
            $('#mbr-cc-categories input[type="checkbox"]').each(function() {
                var category = $(this).val();
                var checked = $(this).is(':checked');
                consent[category] = checked;
            });
            
            this.announce('Your cookie preferences have been saved.');
            this.saveConsent(consent, 'preferences');
        },
        
        saveConsent: function(consent, method) {
            var self = this;

            // Mark consent as saved immediately. This prevents showBanner() from
            // firing again if anything re-triggers checkConsent() before the cookie
            // is fully readable — which can happen with explicit domain scoping.
            this._consentSaved = true;

            // Set cookie immediately — this is the source of truth for consent.
            // Everything below (UI changes, script unblocking, AJAX logging) must
            // not be gated on the AJAX call succeeding. On cached sites the nonce
            // baked into the page may be stale, causing the AJAX to fail silently
            // while the cookie is already correctly set. If we waited for AJAX
            // success to hide the banner it would reappear every time that happened.
            var consentJson = JSON.stringify(consent);
            this.setCookie('mbr_cc_consent', consentJson, mbrCcConsent.cookieExpiry);

            // Verify the cookie was actually written correctly. If an explicit
            // domain scope (e.g. .example.com) causes the read-back to fail,
            // fall back to writing without a domain so the browser uses its default.
            if (!this.getCookie('mbr_cc_consent')) {
                // Write without domain — browser will scope to current host.
                var expDate = new Date();
                expDate.setTime(expDate.getTime() + (mbrCcConsent.cookieExpiry * 24 * 60 * 60 * 1000));
                document.cookie = 'mbr_cc_consent=' + encodeURIComponent(consentJson) +
                    '; expires=' + expDate.toUTCString() +
                    '; path=/; SameSite=Lax' +
                    (window.location.protocol === 'https:' ? '; Secure' : '');
            }

            // Update consent modes (Google Consent Mode v2 & Microsoft UET).
            if (typeof window.MbrCcConsentModes !== 'undefined') {
                window.MbrCcConsentModes.updateAllConsent(consent);
            }

            // Hide banner and modal immediately — do not wait for AJAX.
            this.hideBanner();
            this.hidePreferences();

            // Public event. Our Elementor blocker listens for this, and it is
            // the documented hook for third-party code that needs to react to
            // a consent decision.
            $(document).trigger('mbr_cc_consent_saved', [consent]);

            // Unblock scripts immediately.
            this.unblockScripts(consent);

            // Reload page if enabled (e.g. to restore Elementor videos).
            // Suppressed when the form consent modal is handling a re-submit.
            if (mbrCcConsent.reloadOnConsent && !window._mbrCcSuppressReload) {
                location.reload();
                return; // No point doing anything else if we're reloading.
            }

            // Fire-and-forget AJAX to log consent to the database.
            // The outcome does NOT affect the UI — if this fails (stale nonce,
            // security plugin blocking admin-ajax.php, slow server) the user
            // still has their consent cookie and the banner stays hidden.
            $.ajax({
                url: mbrCcConsent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_save_consent',
                    nonce: mbrCcConsent.nonce,
                    consent: JSON.stringify(consent),
                    method: method
                }
                // No success or error handlers — fire and forget.
                // Consent is already saved in the cookie regardless of outcome.
            });
        },
        
        unblockScripts: function(consent) {
            var self = this;
            
            // Find all blocked scripts and unblock per-category.
            $('script[data-mbr-cc-blocked="true"]').each(function() {
                var $script = $(this);
                var src      = $script.data('mbr-cc-src');
                var category = $script.data('mbr-cc-category') || 'marketing';
                
                if (consent.all || consent[category] === true) {
                    self.unblockScript($script, src);
                }
            });
            
            // Unblock iframes per-category.
            $('iframe[data-mbr-cc-blocked="true"]').each(function() {
                var $iframe  = $(this);
                var src      = $iframe.data('mbr-cc-src');
                var category = $iframe.data('mbr-cc-category') || 'marketing';
                
                if (consent.all || consent[category] === true) {
                    $iframe.attr('src', src)
                           .removeAttr('style')
                           .removeAttr('aria-hidden')
                           .removeAttr('data-mbr-cc-blocked');
                    // Hide the placeholder overlay that sits before this iframe.
                    $iframe.prev('.mbr-cc-blocked-wrapper').remove();
                } else {
                    // Still blocked, so the visitor should be told why and
                    // offered a way to change their mind. The placeholder is
                    // rendered hidden to spare consenting visitors a flash of
                    // it; this is where it becomes visible.
                    $iframe.prev('.mbr-cc-blocked-wrapper').removeAttr('data-mbr-cc-pending');
                }
            });

            // Unblock click-to-play video facades.
            $('[data-mbr-cc-facade="true"]').each(function() {
                var $facade  = $(this);
                var src      = $facade.attr('data-mbr-cc-src');
                var attr     = $facade.attr('data-mbr-cc-attr') || 'data-src';
                var category = $facade.attr('data-mbr-cc-category') || 'marketing';

                if (consent.all || consent[category] === true) {
                    // Hand the URL back under its original attribute name so
                    // the optimiser's own script finds the facade exactly as
                    // it left it.
                    $facade.attr(attr, src)
                           .removeAttr('data-mbr-cc-blocked')
                           .removeAttr('data-mbr-cc-facade')
                           .removeAttr('data-mbr-cc-attr')
                           .removeAttr('data-mbr-cc-category')
                           .removeAttr('data-mbr-cc-src')
                           .removeAttr('data-mbr-cc-hidden');

                    $facade.prev('.mbr-cc-blocked-wrapper').remove();

                    // That script has usually finished binding its handlers by
                    // now, so a facade restored mid-page would look clickable
                    // and do nothing. Build the embed ourselves if that turns
                    // out to be the case — checked on click so the facade keeps
                    // its point, which is not loading the video until asked.
                    self.bindFacadeFallback($facade, src);
                } else {
                    $facade.prev('.mbr-cc-blocked-wrapper').removeAttr('data-mbr-cc-pending');
                }
            });

            // Restore poster images withheld along with their embeds.
            $('[data-mbr-cc-image="true"]').each(function() {
                var $img     = $(this);
                var category = $img.attr('data-mbr-cc-category') || 'marketing';

                if (consent.all || consent[category] === true) {
                    $img.attr('src', $img.attr('data-mbr-cc-src'))
                        .removeAttr('data-mbr-cc-blocked')
                        .removeAttr('data-mbr-cc-image')
                        .removeAttr('data-mbr-cc-category')
                        .removeAttr('data-mbr-cc-src');
                }
            });
        },

        /**
         * Make a restored facade playable even if its own script has already run.
         *
         * Only acts if nothing else has built an iframe by the time the visitor
         * clicks, so an optimiser that does rebind keeps full control.
         */
        bindFacadeFallback: function($facade, src) {
            $facade.one('click', function() {
                if ($facade.find('iframe').length) {
                    return;
                }

                var iframe = document.createElement('iframe');
                iframe.src = src.indexOf('autoplay=') === -1
                    ? src + (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1'
                    : src;
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture');
                iframe.setAttribute('allowfullscreen', 'true');
                iframe.style.width = '100%';
                iframe.style.height = '100%';

                $facade.empty().append(iframe);
            });
        },
        
        unblockScript: function($script, src) {
            if (src) {
                // External script - create new script tag
                var newScript = document.createElement('script');
                newScript.src = src;
                newScript.type = 'text/javascript';

                // Dynamically created scripts default to async, which would let
                // restored scripts execute in download order rather than the
                // order they appear in the document. A tracker that expects its
                // loader to have run first would then fail intermittently, and
                // only for visitors who accepted. Setting async false restores
                // document-order execution.
                newScript.async = false;
                // Copy attributes
                $.each($script[0].attributes, function() {
                    if (this.name !== 'type' && this.name !== 'data-mbr-cc-blocked' && this.name !== 'data-mbr-cc-src') {
                        newScript.setAttribute(this.name, this.value);
                    }
                });
                
                $script[0].parentNode.replaceChild(newScript, $script[0]);
            } else {
                // Inline script. Replacing the element (rather than eval-ing
                // its contents) keeps execution in normal script scope and
                // works under a Content-Security-Policy without 'unsafe-eval',
                // matching how the external branch above restores scripts.
                var inlineScript = document.createElement('script');
                inlineScript.type = 'text/javascript';

                $.each($script[0].attributes, function() {
                    if (this.name !== 'type' && this.name !== 'data-mbr-cc-blocked' && this.name !== 'data-mbr-cc-src') {
                        inlineScript.setAttribute(this.name, this.value);
                    }
                });

                inlineScript.text = $script[0].textContent || $script[0].text || '';

                $script[0].parentNode.replaceChild(inlineScript, $script[0]);
            }
        },
        
        hasConsent: function(consent) {
            // Check if any non-necessary category is accepted
            for (var key in consent) {
                if (key !== 'necessary' && consent[key] === true) {
                    return true;
                }
            }
            return false;
        },
        
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            
            // Get cookie domain and path from settings (set by PHP)
            var domain = '';
            var path = '; path=/';
            
            if (typeof mbrCcConsent !== 'undefined') {
                if (mbrCcConsent.cookieDomain) {
                    domain = '; domain=' + mbrCcConsent.cookieDomain;
                }
                if (mbrCcConsent.cookiePath) {
                    path = '; path=' + mbrCcConsent.cookiePath;
                }
            }
            
            // Mark the cookie Secure on HTTPS so it is never transmitted in
            // cleartext, where a network attacker could read or rewrite the
            // visitor's recorded consent state.
            var secure = (window.location.protocol === 'https:') ? '; Secure' : '';

            // Percent-encode the value. The consent cookie holds JSON, so it
            // contains commas, braces and double quotes — all of which RFC 6265
            // excludes from a cookie value. Browsers tolerate them, which is
            // why this went unnoticed, but a CDN, WAF or reverse proxy in front
            // of the site is under no obligation to, and one that decides to
            // split on the comma leaves a visitor's consent unreadable.
            //
            // PHP percent-decodes $_COOKIE for us, so nothing on the server
            // needs to change. Old unencoded cookies keep working — see
            // getCookie() for how.
            document.cookie = name + '=' + encodeURIComponent(value || '') + expires + domain + path + '; SameSite=Lax' + secure;
        },
        
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) {
                    return this.decodeCookieValue(c.substring(nameEQ.length, c.length));
                }
            }
            return null;
        },
        
        /**
         * Read a cookie value written by either the current or the old format.
         *
         * Cookies written before 2.3.4 hold raw JSON. Those are still in
         * millions of browsers with up to a year left to run, and a visitor
         * whose stored choice suddenly becomes unreadable gets asked to consent
         * again — which looks like the plugin losing their preferences.
         *
         * Decoding is safe for both: raw JSON of booleans contains no percent
         * sign, so decodeURIComponent returns it untouched. The catch is there
         * for a stray percent in a hand-edited cookie, where the raw value is
         * still the better guess than nothing.
         */
        decodeCookieValue: function(value) {
            if (value === null || value === '') {
                return value;
            }
            
            try {
                return decodeURIComponent(value);
            } catch (e) {
                return value;
            }
        }
    };
    
    // Expose on window so other scripts (e.g. blocked-content.js) can call
    // showPreferences() without needing to be inside this closure.
    window.MbrCookieBanner = MbrCookieBanner;
    
    // Initialize on document ready
    $(document).ready(function() {
        MbrCookieBanner.init();
    });
    
})(jQuery);
