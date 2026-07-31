/**
 * Settings Page JavaScript
 * Handles tab switching and layout selection
 */

(function($) {
    'use strict';
    
    
    $(document).ready(function() {
        
        
        // Tab switching
        $('.mbr-cc-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Update buttons
            $('.mbr-cc-tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update content
            $('.mbr-cc-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
            
            // Remember the active tab so a settings save (which reloads the
            // page) returns the user to the tab they were on, not tab 1.
            try {
                sessionStorage.setItem('mbrCcActiveTab', tab);
            } catch (err) {
                // sessionStorage unavailable (private mode etc.) — degrade
                // gracefully; the page will simply reopen on the first tab.
            }
        });
        
        // Restore the last active tab after a save/reload. Falls back to the
        // default (first) tab if the stored tab no longer exists.
        try {
            var storedTab = sessionStorage.getItem('mbrCcActiveTab');
            if (storedTab) {
                var $btn = $('.mbr-cc-tab-button[data-tab="' + storedTab + '"]');
                if ($btn.length && $('#tab-' + storedTab).length) {
                    $('.mbr-cc-tab-button').removeClass('active');
                    $btn.addClass('active');
                    $('.mbr-cc-tab-content').removeClass('active');
                    $('#tab-' + storedTab).addClass('active');
                }
            }
        } catch (err) {
            // Ignore — default tab remains active.
        }
        
        // Layout selection
        $('input[name="mbr_cc_layout_option"]').on('change', function() {
            var value = $(this).val();
            var parts = value.split('-');
            
            if (value === 'popup') {
                $('#banner_layout').val('popup');
                $('#banner_position').val('bottom');
            } else if (value.startsWith('bar-')) {
                $('#banner_layout').val('bar');
                $('#banner_position').val(parts[1]);
            } else {
                $('#banner_layout').val(value);
                $('#banner_position').val('bottom');
            }
            
            // Update visual selection
            $('.mbr-cc-layout-card').removeClass('selected');
            $(this).closest('.mbr-cc-layout-card').addClass('selected');
        });
        
        // ── Banner logo: Media Library picker ──────────────────────
        // Replaces the previous handler, which was bound to a button that was
        // never added to the settings template and therefore never ran.
        var logoFrame = null;

        $('#mbr-cc-choose-logo').on('click', function (e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                window.alert('The WordPress media library could not be loaded. Paste the image URL into the field below instead.');
                return;
            }

            if (logoFrame) {
                logoFrame.open();
                return;
            }

            logoFrame = wp.media({
                title: 'Select banner logo',
                button: { text: 'Use this logo' },
                library: { type: 'image' },
                multiple: false
            });

            logoFrame.on('select', function () {
                var attachment = logoFrame.state().get('selection').first().toJSON();

                // Prefer a sensibly sized version over the full original — the
                // banner renders it at 150px, and shipping a 4000px original to
                // every visitor for that is wasteful.
                var url = attachment.url;

                if (attachment.sizes) {
                    if (attachment.sizes.medium) {
                        url = attachment.sizes.medium.url;
                    } else if (attachment.sizes.thumbnail) {
                        url = attachment.sizes.thumbnail.url;
                    }
                }

                $('#banner_logo_url').val(url);
                $('#banner_logo_id').val(attachment.id);
                $('#mbr-cc-logo-preview').attr('src', url);
                $('#mbr-cc-logo-preview-wrap').show();
                $('#mbr-cc-remove-logo').show();
            });

            logoFrame.open();
        });

        $('#mbr-cc-remove-logo').on('click', function (e) {
            e.preventDefault();

            $('#banner_logo_url').val('');
            $('#banner_logo_id').val(0);
            $('#mbr-cc-logo-preview').attr('src', '');
            $('#mbr-cc-logo-preview-wrap').hide();
            $(this).hide();
        });

        // Keep the preview honest when a URL is pasted or edited by hand.
        $('#banner_logo_url').on('change input', function () {
            var url = $.trim($(this).val());

            // A pasted URL is not a Media Library attachment.
            $('#banner_logo_id').val(0);

            if (url) {
                $('#mbr-cc-logo-preview').attr('src', url);
                $('#mbr-cc-logo-preview-wrap').show();
                $('#mbr-cc-remove-logo').show();
            } else {
                $('#mbr-cc-logo-preview-wrap').hide();
                $('#mbr-cc-remove-logo').hide();
            }
        });

        // ── Glassmorphism: sliders and live contrast readout ───────
        //
        // A fixed opacity floor cannot know whether a given banner is legible:
        // it depends on the banner colour and the text colour, not just on how
        // transparent the surface is. So instead of capping the slider, the
        // real WCAG 2.1 contrast ratio is computed for the chosen combination.
        //
        // A translucent banner sits over whatever the page happens to show, so
        // the surface is composited over BOTH white and black and the worse of
        // the two ratios is reported. If that figure passes, the banner is
        // legible over any background at all — which a floor cannot promise.
        //
        // Blur is ignored in the maths. It genuinely helps legibility by
        // removing detail from what shows through, but it does not change the
        // colours, so counting it would overstate the result.

        function hexToRgb(hex) {
            hex = String(hex || '').trim().replace(/^#/, '');

            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }

            if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
                return null;
            }

            return [
                parseInt(hex.slice(0, 2), 16),
                parseInt(hex.slice(2, 4), 16),
                parseInt(hex.slice(4, 6), 16)
            ];
        }

        // WCAG 2.1 relative luminance.
        function luminance(rgb) {
            var channels = rgb.map(function (v) {
                v = v / 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });

            return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        }

        function contrastRatio(a, b) {
            var l1 = luminance(a);
            var l2 = luminance(b);
            var lighter = Math.max(l1, l2);
            var darker = Math.min(l1, l2);

            return (lighter + 0.05) / (darker + 0.05);
        }

        // Source-over compositing of a translucent surface on a backdrop.
        function composite(surface, backdrop, alpha) {
            return surface.map(function (c, i) {
                return Math.round(alpha * c + (1 - alpha) * backdrop[i]);
            });
        }

        function updateContrast() {
            var $out = $('#mbr-cc-contrast');

            if (!$out.length) {
                return;
            }

            var bg = hexToRgb($('#primary_color').val());
            var fg = hexToRgb($('#text_color').val());
            var alpha = parseInt($('#glass_opacity').val(), 10) / 100;

            if (!bg || !fg || isNaN(alpha)) {
                $out.attr('class', 'mbr-cc-contrast').html('');
                return;
            }

            var overWhite = contrastRatio(fg, composite(bg, [255, 255, 255], alpha));
            var overBlack = contrastRatio(fg, composite(bg, [0, 0, 0], alpha));
            var worst = Math.min(overWhite, overBlack);
            var shown = worst.toFixed(2);

            var state, message;

            if (worst >= 4.5) {
                state = 'pass';
                message = 'Contrast ' + shown + ':1 — passes WCAG AA for normal text against any background.';
            } else if (worst >= 3) {
                state = 'warn';
                message = 'Contrast ' + shown + ':1 — passes AA for large text only. Body text on the banner may be hard to read over some pages.';
            } else {
                state = 'fail';
                message = 'Contrast ' + shown + ':1 — below the WCAG AA minimum of 4.5:1. Over some backgrounds this banner will be difficult to read.';
            }

            // Worth knowing which side is the problem.
            if (state !== 'pass') {
                message += (overWhite < overBlack)
                    ? ' The weak case is a light page behind the banner.'
                    : ' The weak case is a dark page behind the banner.';
            }

            $out.attr('class', 'mbr-cc-contrast is-' + state).text(message);
        }

        $('#banner_glassmorphism').on('change', function () {
            $('#mbr-cc-glass-controls').toggle($(this).is(':checked'));
            updateContrast();
        });

        $('#glass_opacity').on('input change', function () {
            $('#glass_opacity_out').text($(this).val() + '%');
            updateContrast();
        });

        $('#glass_blur').on('input change', function () {
            $('#glass_blur_out').text($(this).val() + 'px');
        });

        // The colour pickers drive the calculation too, so recompute when they
        // settle. wpColorPicker fires change on the underlying input.
        $('#primary_color, #text_color').on('change', updateContrast);
        $(document).on('mbr-cc-colors-changed', updateContrast);

        updateContrast();

    });
    
})(jQuery);
