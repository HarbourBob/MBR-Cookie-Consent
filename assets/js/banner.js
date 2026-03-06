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
        
        checkConsent: function() {
            var consent = this.getCookie(mbrCcBanner.categories ? 'mbr_cc_consent' : 'mbr_cc_consent');
            
            if (!consent) {
                this.showBanner();
            } else {
                this.showRevisitButton();
                this.unblockScripts(JSON.parse(consent));
            }
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
                document.cookie = 'mbr_cc_consent=' + consentJson +
                    '; expires=' + expDate.toUTCString() +
                    '; path=/; SameSite=Lax';
            }

            // Update consent modes (Google Consent Mode v2 & Microsoft UET).
            if (typeof window.MbrCcConsentModes !== 'undefined') {
                window.MbrCcConsentModes.updateAllConsent(consent);
            }

            // Hide banner and modal immediately — do not wait for AJAX.
            this.hideBanner();
            this.hidePreferences();

            // Trigger consent saved event for TCF, ACM, and our Elementor blocker.
            $(document).trigger('mbr_cc_consent_saved', [consent]);

            // Unblock scripts immediately.
            this.unblockScripts(consent);

            // Reload page if enabled (e.g. to restore Elementor videos).
            if (mbrCcConsent.reloadOnConsent) {
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
                }
            });
        },
        
        unblockScript: function($script, src) {
            if (src) {
                // External script - create new script tag
                var newScript = document.createElement('script');
                newScript.src = src;
                newScript.type = 'text/javascript';
                
                // Copy attributes
                $.each($script[0].attributes, function() {
                    if (this.name !== 'type' && this.name !== 'data-mbr-cc-blocked' && this.name !== 'data-mbr-cc-src') {
                        newScript.setAttribute(this.name, this.value);
                    }
                });
                
                $script[0].parentNode.replaceChild(newScript, $script[0]);
            } else {
                // Inline script - change type back to text/javascript
                $script.attr('type', 'text/javascript');
                $script.removeAttr('data-mbr-cc-blocked');
                
                // Re-execute inline script
                var code = $script.html();
                eval(code);
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
            
            document.cookie = name + '=' + (value || '') + expires + domain + path + '; SameSite=Lax';
        },
        
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
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
