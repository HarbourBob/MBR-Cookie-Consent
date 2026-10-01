/** Consent lifecycle and best-effort, idempotent delivery. @since 2.3.7 */
(function($) {
    'use strict';
    var cfg = window.mbrCcConsent || {};
    var queue = [], sending = false, reloadTimer = null, shutdownHandlers = {};
    var maxAttempts = 3, maxAge = 60 * 60 * 1000;

    function emit(name, detail) {
        try { document.dispatchEvent(new CustomEvent(name, {detail: detail})); } catch (e) {}
    }
    function persist() {
        try {
            if (!cfg.queueKey) { return; }
            if (queue.length) { window.sessionStorage.setItem(cfg.queueKey, JSON.stringify(queue)); }
            else { window.sessionStorage.removeItem(cfg.queueKey); }
        } catch (e) { /* In-memory delivery still works with storage disabled. */ }
    }
    function reportFailure(event, reason) {
        emit('mbr_cc_log_status', {eventId: event.id, recorded: false, reason: reason});
        if (window.console && window.console.warn) {
            window.console.warn('MBR Cookie Consent: choice applied, but log delivery was not confirmed (' + reason + ').');
        }
    }
    function loadQueue() {
        try {
            var raw = cfg.queueKey ? window.sessionStorage.getItem(cfg.queueKey) : null;
            if (!raw) { return; }
            if (raw.length > 32000) { persist(); return; }
            var saved = JSON.parse(raw);
            if (!Array.isArray(saved)) { persist(); return; }
            queue = saved.slice(-8).filter(function(item) {
                return item && /^[a-f0-9]{32}$/.test(item.id) &&
                    typeof item.consent === 'string' && item.consent.length <= 2048 &&
                    typeof item.method === 'string' && item.method.length <= 32 &&
                    typeof item.created === 'number' && item.created <= Date.now() &&
                    Date.now() - item.created < maxAge &&
                    Number.isInteger(item.attempts) && item.attempts >= 0 && item.attempts < maxAttempts;
            });
        } catch (e) { queue = []; }
        persist();
    }
    function newId() {
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(bytes); }
        else {
            // Collision key only, never an authentication token.
            for (var i = 0; i < bytes.length; i++) { bytes[i] = Math.floor(Math.random() * 256); }
        }
        return Array.prototype.map.call(bytes, function(b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }
    function deliver() {
        if (sending || !queue.length) { return; }
        var item = queue[0];
        if (item.attempts >= maxAttempts || Date.now() - item.created >= maxAge) {
            queue.shift(); persist(); reportFailure(item, 'retry_limit'); deliver(); return;
        }
        sending = true;
        item.attempts++;
        persist(); // Before I/O or any event listener can navigate.
        var done = false, timer;
        function finish(response, permanent) {
            if (done) { return; }
            done = true; clearTimeout(timer); sending = false;
            var recorded = !!(response && response.success && response.data && response.data.recorded === true);
            if (recorded || permanent || item.attempts >= maxAttempts) {
                queue = queue.filter(function(entry) { return entry.id !== item.id; });
                persist();
                if (recorded) { emit('mbr_cc_log_status', {eventId: item.id, recorded: true}); }
                else { reportFailure(item, permanent ? 'server_rejected' : 'retry_limit'); }
            }
            if (queue.length) { setTimeout(deliver, recorded ? 0 : item.attempts * 1000); }
        }
        var fields = {action: 'mbr_cc_save_consent', nonce: cfg.nonce || '',
            consent: item.consent, method: item.method, event_id: item.id};
        var body = Object.keys(fields).map(function(key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(fields[key]);
        }).join('&');
        timer = setTimeout(function() { finish(null, false); }, 3500);
        function receive(response) {
            finish(response, !!(response && response.data && response.data.retryable === false));
        }
        try {
            if (window.fetch) {
                window.fetch(cfg.ajaxUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: true,
                    headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body: body
                }).then(function(response) { return response.json(); }).then(receive)['catch'](function() { finish(null, false); });
            } else {
                $.ajax({url: cfg.ajaxUrl, type: 'POST', data: fields, timeout: 3000,
                    success: receive, error: function(xhr) {
                        if (xhr.responseJSON) { receive(xhr.responseJSON); }
                        else { finish(null, false); }
                    }
                });
            }
        } catch (e) { finish(null, false); }
    }
    function readConsent() {
        var name = (cfg.cookieName || 'mbr_cc_consent') + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(name) !== 0) { continue; }
            var value = part.slice(name.length);
            if (value.length > 6144) { return {}; }
            try {
                try { value = decodeURIComponent(value); } catch (e) {}
                var parsed = JSON.parse(value);
                return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
            } catch (e) { return {}; }
        }
        return {};
    }
    function allowed(consent, category) {
        return !!(consent && (consent[category] === true || (consent.all === true && consent[category] !== false)));
    }
    function required(category) {
        var categories = window.mbrCcBanner && window.mbrCcBanner.categories || {};
        return category === 'necessary' || !!(categories[category] && categories[category].required);
    }
    function normalise(consent) {
        var result = {necessary: true};
        Object.keys(consent || {}).forEach(function(key) {
            if (key !== 'all' && typeof consent[key] === 'boolean') { result[key] = consent[key]; }
        });
        var categories = window.mbrCcBanner && window.mbrCcBanner.categories || {};
        Object.keys(categories).forEach(function(key) {
            if (consent && consent.all === true && consent[key] !== false) { result[key] = true; }
            if (required(key)) { result[key] = true; }
        });
        result.necessary = true;
        if (typeof navigator !== 'undefined' && navigator.globalPrivacyControl === true) {
            var suppressed = window.mbrCcGpc && window.mbrCcGpc.suppressCategories || ['marketing'];
            suppressed.forEach(function(key) { if (!required(key)) { result[key] = false; } });
        }
        return result;
    }
    function cookieScopes() {
        var domains = [''], parts = window.location.hostname.split('.');
        // Browser rejects public suffixes; never derive an unrelated domain.
        for (var i = 0; i < parts.length - 1; i++) { domains.push(parts.slice(i).join('.')); }
        if (cfg.cookieDomain) { domains.push(cfg.cookieDomain); }
        var paths = ['/', cfg.cookiePath || '/'], path = window.location.pathname || '/';
        paths.push(path);
        for (var j = 1; j < path.length; j++) {
            if (path[j] === '/') { paths.push(path.slice(0, j), path.slice(0, j + 1)); }
        }
        return {domains: domains.filter(function(v, n, a) { return a.indexOf(v) === n; }),
            paths: paths.filter(function(v, n, a) { return a.indexOf(v) === n; })};
    }
    function eraseCookie(name) {
        if (!/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(name)) { return; }
        var scopes = cookieScopes();
        scopes.domains.forEach(function(domain) {
            scopes.paths.forEach(function(path) {
                document.cookie = name + '=; Max-Age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + path +
                    (domain ? '; domain=' + domain : '') + '; SameSite=Lax' +
                    (window.location.protocol === 'https:' ? '; Secure' : '');
            });
        });
    }
    function matches(name, patterns) {
        return Array.isArray(patterns) && patterns.some(function(pattern) {
            if (typeof pattern !== 'string' || !pattern || pattern === '*') { return false; }
            return pattern.slice(-1) === '*' ? name.indexOf(pattern.slice(0, -1)) === 0 : name === pattern;
        });
    }
    function cleanup(category) {
        if (required(category)) { return; }
        var rules = (cfg.cleanupRules || {})[category] || {};
        document.cookie.split(';').forEach(function(part) {
            var name = part.trim().split('=')[0];
            if (name !== cfg.cookieName && matches(name, rules.cookies)) { eraseCookie(name); }
        });
        ['localStorage', 'sessionStorage'].forEach(function(type) {
            try {
                var storage = window[type];
                for (var i = storage.length - 1; i >= 0; i--) {
                    var key = storage.key(i);
                    if (key !== cfg.queueKey && matches(key, rules[type])) { storage.removeItem(key); }
                }
            } catch (e) {}
        });
    }
    window.MbrCcConsent = {
        readConsent: readConsent,
        normalise: normalise,
        clearConsentCookie: function() { eraseCookie(cfg.cookieName || 'mbr_cc_consent'); },
        getConsent: function(callback) { if (callback) { callback(readConsent()); } },
        hasConsent: function(callback) { if (callback) { callback(Object.keys(readConsent()).length > 0); } },
        hasCategoryConsent: function(category, callback) { if (callback) { callback(allowed(normalise(readConsent()), category)); } },
        // A previously executing tag may recreate storage between cleanup and
        // reload. Recheck only after a stored decision exists, never on an
        // undecided first visit, and never clear a still-allowed category.
        cleanupDenied: function(consent) {
            Object.keys(cfg.cleanupRules || {}).forEach(function(category) {
                if (!allowed(consent, category)) { cleanup(category); }
            });
        },
        /** Hooks run synchronously; reload handles arbitrary JS already executed. */
        registerShutdown: function(category, handler) {
            if (typeof handler !== 'function' || required(category)) { return; }
            (shutdownHandlers[category] = shutdownHandlers[category] || []).push(handler);
        },
        applyWithdrawal: function(previous, next, force) {
            var categories = Object.keys(window.mbrCcBanner && window.mbrCcBanner.categories || {});
            categories = categories.concat(Object.keys(previous || {}), Object.keys(cfg.cleanupRules || {}));
            var withdrawn = categories.filter(function(category, index) {
                return category !== 'all' && !required(category) && categories.indexOf(category) === index &&
                    !allowed(next, category) && (force || allowed(previous, category));
            });
            withdrawn.forEach(function(category) {
                (shutdownHandlers[category] || []).forEach(function(handler) {
                    try { handler(next); } catch (e) { /* Keep running the other integrations. */ }
                });
                // Restored MBR elements retain category metadata. The reload
                // rebuilds the PHP placeholders after immediate neutralisation.
                document.querySelectorAll('[data-mbr-cc-category]').forEach(function(node) {
                    if (node.getAttribute('data-mbr-cc-category') !== category) { return; }
                    if (node.tagName === 'IFRAME') { node.removeAttribute('srcdoc'); node.setAttribute('src', 'about:blank'); }
                    else { node.querySelectorAll('iframe').forEach(function(frame) { frame.removeAttribute('srcdoc'); frame.setAttribute('src', 'about:blank'); }); }
                    if (node.tagName === 'IMG') { node.removeAttribute('src'); node.removeAttribute('srcset'); }
                });
                cleanup(category);
            });
            if (withdrawn.length) { emit('mbr_cc_consent_withdrawn', {categories: withdrawn, consent: next}); }
            return withdrawn.length > 0;
        },
        logConsent: function(consent, method) {
            var item = {id: newId(), consent: JSON.stringify(consent), method: method, created: Date.now(), attempts: 0};
            if (queue.length >= 8) { reportFailure(queue.shift(), 'queue_full'); }
            queue.push(item); persist(); deliver();
            return item.id;
        },
        requestReload: function() {
            if (reloadTimer !== null) { return; }
            // Never wait indefinitely for evidence delivery to stop a tracker.
            reloadTimer = setTimeout(function() { window.location.reload(); }, 800);
        },
        revokeConsent: function(callback) {
            if (window.MbrCookieBanner) {
                window.MbrCookieBanner.saveConsent({necessary: true}, 'revoked');
                if (callback) { callback(true); } // Local choice, not server receipt.
            } else if (callback) { callback(false); }
        }
    };
    loadQueue();
    $(function() { deliver(); });
})(jQuery);
