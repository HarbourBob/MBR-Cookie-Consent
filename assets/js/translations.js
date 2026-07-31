/**
 * Applies a community translation to the consent banner.
 *
 * The page always arrives in the site's own wording so that it can be cached
 * and served to every visitor alike. This script runs in the head, works out
 * whether the visitor's browser is asking for a language the site has approved,
 * and swaps the text before the banner is shown.
 *
 * Anything it cannot do — no match, a failed fetch, a missing key — leaves the
 * original wording in place, which is text the site owner wrote and is always
 * safe to display.
 */
(function () {
    'use strict';

    if (typeof mbrCcI18n === 'undefined' || !mbrCcI18n.languages) {
        return;
    }

    /**
     * Pick the best approved language for this visitor.
     *
     * navigator.languages is in the visitor's own order of preference, so the
     * first entry the site has approved wins. Region is dropped — pt-BR is
     * served the pt catalogue — because these are generic consent notices
     * rather than regionally specific text.
     */
    function chooseLanguage() {
        var wanted = navigator.languages && navigator.languages.length
            ? navigator.languages
            : [navigator.language || ''];

        for (var i = 0; i < wanted.length; i++) {
            var code = String(wanted[i]).toLowerCase().split('-')[0];

            if (code && Object.prototype.hasOwnProperty.call(mbrCcI18n.languages, code)) {
                return code;
            }
        }

        return null;
    }

    /**
     * Replace the text of every marked element.
     *
     * Keys the site owner has rewritten are skipped: there is no community
     * translation of their wording, and their English is more accurate than a
     * generic sentence that says something slightly different.
     */
    function apply(strings, direction) {
        var skip = mbrCcI18n.skip || [];

        document.querySelectorAll('[data-mbr-cc-i18n]').forEach(function (el) {
            var key = el.getAttribute('data-mbr-cc-i18n');

            if (!key || skip.indexOf(key) !== -1) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(strings, key) && strings[key]) {
                el.textContent = strings[key];
            }
        });

        // Attribute translations, declared as "attribute:key".
        document.querySelectorAll('[data-mbr-cc-i18n-attr]').forEach(function (el) {
            var spec = (el.getAttribute('data-mbr-cc-i18n-attr') || '').split(':');

            if (spec.length !== 2) {
                return;
            }

            var attr = spec[0];
            var key = spec[1];

            if (skip.indexOf(key) !== -1) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(strings, key) && strings[key]) {
                el.setAttribute(attr, strings[key]);
            }
        });

        if (direction === 'rtl') {
            document.querySelectorAll('.mbr-cc-banner, .mbr-cc-modal').forEach(function (el) {
                el.setAttribute('dir', 'rtl');
            });
        }
    }

    var lang = chooseLanguage();

    if (!lang) {
        return;
    }

    var url = mbrCcI18n.baseUrl + lang + '.json?v=' + encodeURIComponent(mbrCcI18n.version);

    fetch(url, { credentials: 'omit' })
        .then(function (res) {
            return res.ok ? res.json() : null;
        })
        .then(function (data) {
            if (!data || !data.strings) {
                return;
            }

            var direction = (mbrCcI18n.languages[lang] || {}).direction || 'ltr';

            // The banner may not exist yet if this resolved unusually fast.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    apply(data.strings, direction);
                });
            } else {
                apply(data.strings, direction);
            }
        })
        .catch(function () {
            // Network failure, bad JSON, anything else — the site's own wording
            // stays on screen. Never leave the visitor without a consent notice.
        });
})();
