/**
 * Consent Doctor — front-end probe.
 *
 * Loaded only on a page opened with the doctor probe flag by a logged-in
 * administrator, inside a hidden iframe on the Consent Doctor screen.
 *
 * It answers the one question a server-side audit cannot: what did this page
 * ACTUALLY request? Resource Timing lists every network request the document
 * made, by URL, whether or not the plugin knew about it. A tag manager that
 * injects six vendors at runtime shows up here and nowhere else.
 *
 * Three things could make this lie, so each is measured and reported rather
 * than assumed away:
 *
 *   1. A content blocker in the administrator's browser stops the requests
 *      happening at all, producing a clean result that means nothing.
 *   2. An existing consent choice means the page under test is a CONSENTED
 *      page load, which is not the interesting case.
 *   3. Requests made inside a cross-origin iframe are invisible from here.
 *      The iframe's own URL is visible, which is usually the finding anyway.
 */
(function () {
    'use strict';

    var cfg = window.mbrCcDoctorProbe || {};
    var COLLECT_AFTER_LOAD_MS = 2500;

    function post(payload, stage) {
        try {
            window.parent.postMessage({
                mbrCcDoctor: true,
                stage: stage || 'result',
                payload: payload
            }, window.location.origin);
        } catch (e) {
            /* The parent is same-origin by construction; nothing to recover. */
        }
    }

    // Announce immediately. Everything after this point can stall — a slow
    // page, a blocked fetch, an exception in a browser I have not tested — and
    // without a heartbeat all of those look identical to "the script never
    // ran", which is a different fault with a different fix.
    post({ href: window.location.href }, 'alive');
    window.mbrCcDoctorProbeRan = true;

    /**
     * Detect a content blocker.
     *
     * Two independent signals: a bait element carrying class names filter lists
     * hide, and a request to a path they block by pattern.
     *
     * The bait is measured against a CONTROL element, identical in every way
     * except its class names. Without that control the measurement is worthless
     * in any context that does not lay out — and the first version of this ran
     * inside a display:none iframe, where nothing is laid out, so the bait was
     * always zero-height and every single run reported a content blocker that
     * was not there.
     *
     * If the control is also unmeasurable, layout is unavailable and the honest
     * answer is that we cannot tell, not that a blocker is present.
     */
    function detectBlocker(done) {
        var style = 'position:absolute;left:-9999px;top:-9999px;width:300px;height:250px;';

        var control = document.createElement('div');
        control.className = 'mbr-cc-probe-control';
        control.style.cssText = style;
        document.body.appendChild(control);

        var bait = document.createElement('div');
        bait.className = 'adsbox ad-banner pub_300x250 text-ad';
        bait.style.cssText = style;
        document.body.appendChild(bait);

        window.setTimeout(function () {
            function measurable(el) {
                return el.offsetParent !== null && el.offsetHeight > 0;
            }

            var canMeasure = measurable(control);
            // Only meaningful when the control proves layout is happening.
            var hiddenBait = canMeasure && !measurable(bait);

            if (control.parentNode) { control.parentNode.removeChild(control); }
            if (bait.parentNode) { bait.parentNode.removeChild(bait); }

            var probeUrl = cfg.baitUrl;

            if (!probeUrl) {
                done({ canMeasure: canMeasure, hiddenBait: hiddenBait, fetchBlocked: false });
                return;
            }

            // Served by this site, so a rejection means something intercepted
            // it. A 404 resolves rather than rejects, so a missing file cannot
            // masquerade as a blocker.
            fetch(probeUrl, { method: 'GET', cache: 'no-store', credentials: 'omit' })
                .then(function () {
                    done({ canMeasure: canMeasure, hiddenBait: hiddenBait, fetchBlocked: false });
                })
                .catch(function () {
                    done({ canMeasure: canMeasure, hiddenBait: hiddenBait, fetchBlocked: true });
                });
        }, 200);
    }

    function hostOf(url) {
        try {
            return new URL(url, window.location.href).hostname.toLowerCase();
        } catch (e) {
            return '';
        }
    }

    function collect() {
        var entries = [];

        if (window.performance && typeof window.performance.getEntriesByType === 'function') {
            entries = window.performance.getEntriesByType('resource') || [];
        }

        var here = window.location.hostname.toLowerCase();
        var seen = {};
        var requests = [];

        entries.forEach(function (entry) {
            var host = hostOf(entry.name);

            if (!host || host === here) {
                return;
            }

            // One row per host, with an example URL. The host is what maps to a
            // service; the full URLs are not sent, because they can carry
            // identifiers and this result is stored in the database.
            if (seen[host]) {
                seen[host].count++;
                return;
            }

            seen[host] = {
                host: host,
                count: 1,
                initiator: entry.initiatorType || '',
                example: String(entry.name).split('?')[0].slice(0, 200)
            };
            requests.push(seen[host]);
        });

        return requests;
    }

    function consentState() {
        var match = document.cookie.match(/(?:^|;\s*)mbr_cc_consent=([^;]*)/);

        if (!match) {
            return { present: false, raw: '' };
        }

        var raw = '';

        try {
            raw = decodeURIComponent(match[1]);
        } catch (e) {
            raw = match[1];
        }

        return { present: true, raw: raw.slice(0, 300) };
    }

    function run() {
        detectBlocker(function (signals) {
            window.setTimeout(function () {
                if (window.mbrCcDoctorCollected) { return; }
                window.mbrCcDoctorCollected = true;
                post({
                    requests: collect(),
                    consent: consentState(),
                    blocker: signals,
                    simulatedUnconsented: !!cfg.simulateUnconsented,
                    frames: document.querySelectorAll('iframe').length,
                    url: window.location.href.split('?')[0]
                });
            }, COLLECT_AFTER_LOAD_MS);
        });
    }

    function guarded() {
        try {
            run();
        } catch (e) {
            post({ error: String(e && e.message ? e.message : e) }, 'error');
        }
    }

    if (document.readyState === 'complete') {
        guarded();
    } else {
        window.addEventListener('load', guarded);
        // If load never fires — a stalled subresource will do it — collect
        // anyway rather than hanging for ever.
        window.setTimeout(function () {
            if (!window.mbrCcDoctorCollected) { guarded(); }
        }, 12000);
    }
})();
