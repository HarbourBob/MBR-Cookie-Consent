/**
 * Consent Doctor — admin driver for the live check.
 *
 * Opens the site's home page in a hidden same-origin iframe with a nonce-signed
 * probe flag, waits for the probe inside it to postMessage what it saw, sends
 * that to the server to be classified, and reloads the panel.
 */
(function () {
    'use strict';

    var cfg = window.mbrCcDoctor || {};
    var done = false;

    // This script and the data PHP localises for it are versioned together. If
    // the browser has one and the server sent the other, say so rather than
    // failing in a way that looks like a fault on the site being checked.
    var NEEDS = ['home', 'homeHost', 'probeFlag', 'probeNonce'];

    function configIsStale() {
        for (var i = 0; i < NEEDS.length; i++) {
            if (!cfg[NEEDS[i]]) { return true; }
        }
        return false;
    }

    function status(text) {
        var el = document.getElementById('mbr-cc-doctor-status');
        if (el) { el.textContent = text; }
    }

    function finish(payload) {
        if (done) { return; }
        done = true;

        status('Classifying what was seen…');

        var body = new URLSearchParams();
        body.append('action', 'mbr_cc_doctor_probe_result');
        body.append('nonce', cfg.nonce);
        body.append('result', JSON.stringify(payload));
        body.append('target', currentTarget);

        fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function () {
                status('Done. Reloading…');
                window.location.reload();
            })
            .catch(function () {
                status('The result could not be saved. Try again.');
            });
    }

    window.addEventListener('message', function (event) {
        // Same-origin by construction; anything else is not ours.
        if (event.origin !== window.location.origin) { return; }
        if (!event.data || event.data.mbrCcDoctor !== true) { return; }

        if (event.data.stage === 'alive') {
            probeAlive = true;
            status('Probe running on the page. Collecting requests…');
            return;
        }

        if (event.data.stage === 'error') {
            done = true;
            var b = document.getElementById('mbr-cc-doctor-run');
            if (b) { b.disabled = false; }
            status('The probe hit an error on that page: ' +
                   ((event.data.payload && event.data.payload.error) || 'unknown') +
                   '. Tell me what it says.');
            return;
        }

        finish(event.data.payload);
    });

    var currentTarget = cfg.home || '';
    var frameLoaded = false;
    var probeAlive = false;

    /**
     * Work out why nothing came back.
     *
     * The frame is same-origin, so its document can be read. Each branch below
     * is a different fault with a different fix, and saying "the probe may have
     * been blocked" covers all of them while helping with none.
     */
    function diagnose(frame, target) {
        if (!frameLoaded) {
            return 'The page never finished loading in the background frame. It may be slow, ' +
                   'or something is refusing to let it be framed — check for an X-Frame-Options ' +
                   'or Content-Security-Policy header from a security plugin or your host.';
        }

        var doc = null;

        try {
            doc = frame.contentDocument;
        } catch (e) {
            return 'The page loaded but could not be read, which means the browser treated it ' +
                   'as a different origin. Check whether the URL redirected to another domain ' +
                   'or between www and non-www.';
        }

        if (!doc) {
            return 'The page loaded but its content was not accessible. Check for a redirect to ' +
                   'another domain.';
        }

        var landed = '';

        try {
            landed = frame.contentWindow.location.href;
        } catch (e) {}

        if (probeAlive) {
            return 'The probe started on that page but never finished collecting. Something on ' +
                   'the page is stalling it — most often a subresource that never loads. Try ' +
                   'the "Open the probe page directly" link below and watch the browser console.';
        }

        var ran = false;

        try {
            ran = !!frame.contentWindow.mbrCcDoctorProbeRan;
        } catch (e) {}

        if (ran) {
            return 'The probe ran but could not report back to this page. That normally means a ' +
                   'Content-Security-Policy is blocking the message. Try the "Open the probe ' +
                   'page directly" link below.';
        }

        var marker = doc.querySelector('meta[name="mbr-cc-doctor-probe"]');

        if (!marker) {
            var note = 'The page loaded, but the probe was never added to it. Usually the ' +
                       'diagnostic parameters were dropped — a redirect that strips the query ' +
                       'string, a page cache serving a stored copy, or a security plugin ' +
                       'rewriting the request.';

            if (landed && landed.indexOf(cfg.probeFlag) === -1) {
                note += ' The frame ended up at ' + landed + ', which no longer carries them, ' +
                        'so a redirect is the likely cause.';
            }

            return note;
        }

        return 'The probe was added to the page but never reported. It was most likely held ' +
               'back by a performance plugin that delays JavaScript until someone interacts ' +
               'with the page — nobody ever interacts with a background frame. Exclude ' +
               'doctor-probe.js from JavaScript delay or deferral and try again.';
    }

    /**
     * Build the probe URL for a chosen page.
     *
     * Confined to this site. A foreign origin would fail the postMessage check
     * anyway, but failing here gives a usable message instead of a timeout.
     */
    function probeUrlFor(target, simulate) {
        var url;

        try {
            url = new URL(target, cfg.home);
        } catch (e) {
            return null;
        }

        if (url.hostname.toLowerCase() !== String(cfg.homeHost).toLowerCase()) {
            return null;
        }

        url.searchParams.set(cfg.probeFlag, '1');
        url.searchParams.set('_mbrnonce', cfg.probeNonce);

        if (simulate) {
            url.searchParams.set('unconsented', '1');
        }

        url.searchParams.set('_', String(Date.now()));

        return url.toString();
    }

    // Choosing a suggestion fills the free-text field, so what will actually be
    // checked is always visible rather than implied by a dropdown's state.
    var preset = document.getElementById('mbr-cc-doctor-preset');
    var urlField = document.getElementById('mbr-cc-doctor-url');

    if (preset && urlField) {
        preset.addEventListener('change', function () {
            if (preset.value) {
                urlField.value = preset.value;
            } else {
                urlField.value = '';
                urlField.focus();
            }
        });

        // Keep the dropdown honest if the field is edited by hand.
        urlField.addEventListener('input', function () {
            var match = '';

            for (var i = 0; i < preset.options.length; i++) {
                if (preset.options[i].value && preset.options[i].value === urlField.value) {
                    match = preset.options[i].value;
                    break;
                }
            }

            preset.value = match;
        });
    }

    var btn = document.getElementById('mbr-cc-doctor-run');

    if (!btn) { return; }

    if (configIsStale()) {
        status('This screen is running a cached copy of its own script. Hard-refresh ' +
               '(Ctrl-F5, or Cmd-Shift-R on a Mac) before running a check.');
    }

    btn.addEventListener('click', function () {
        var frame = document.getElementById('mbr-cc-doctor-frame');
        var simulate = document.getElementById('mbr-cc-doctor-simulate');
        var field = document.getElementById('mbr-cc-doctor-url');

        if (!frame) { return; }

        var target = (field && field.value.trim()) ? field.value.trim() : cfg.home;
        var url = probeUrlFor(target, simulate && simulate.checked);

        if (!url) {
            status('That URL is not on this site. Enter a page from this site.');
            return;
        }

        // Never hand the frame something that is not a URL. A stale cached copy
        // of this script once built the literal string "undefined&_=1234567890"
        // out of a config key that no longer existed, and the only symptom was
        // a hang and a 404 in the console. Fail loudly and say what to do.
        if (typeof url !== 'string' || url.indexOf('undefined') !== -1 ||
            !/^https?:\/\//i.test(url)) {
            status('This screen is running a cached copy of its own script. ' +
                   'Hard-refresh the page (Ctrl-F5, or Cmd-Shift-R on a Mac) and try again. ' +
                   'If it persists, purge your page and asset caches.');
            return;
        }

        currentTarget = target;
        done = false;
        frameLoaded = false;
        probeAlive = false;

        var direct = document.getElementById('mbr-cc-doctor-direct');
        if (direct) { direct.href = url; direct.style.display = 'inline'; }
        btn.disabled = true;
        status('Loading that page in the background…');

        frame.onload = function () {
            frameLoaded = true;
            status('Page loaded. Waiting for the probe to report…');
        };

        frame.src = url;

        // A probe that never reports is itself a finding, but "something went
        // wrong" is not a usable one. Work out WHICH thing went wrong, using
        // the fact that the frame is same-origin and can be inspected.
        window.setTimeout(function () {
            if (done) { return; }
            done = true;
            btn.disabled = false;
            status(diagnose(frame, target));
        }, 30000);
    });
})();
