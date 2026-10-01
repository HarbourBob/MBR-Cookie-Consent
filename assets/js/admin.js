(function($) {
    'use strict';
    
    /**
     * Escape a value for use in HTML text or a quoted attribute.
     *
     * Scanner results are not trusted input. Names, identifiers and types come
     * from the HTML of the page that was scanned, so anybody able to influence
     * that page — a comment, a compromised third-party script, a plugin that
     * prints user content — could put markup in them. Before 2.6.0 they were
     * concatenated into the results table unescaped, and a crafted script URL
     * executed in the administrator's session. Every value interpolated into a
     * string of HTML on this screen goes through here.
     *
     * @param {*} value
     * @return {string}
     */
    function esc(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    var MbrCcAdmin = {
        
        init: function() {
            this.initColorPickers();
            this.bindEvents();
        },
        
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.mbr-cc-color-picker').wpColorPicker();
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Generate cookie policy
            $('#mbr-cc-generate-policy').on('click', function(e) {
                e.preventDefault();
                self.generatePolicy();
            });
            
            // Generate privacy policy
            $('#mbr-cc-generate-privacy-policy').on('click', function(e) {
                e.preventDefault();
                self.generatePrivacyPolicy();
            });
            
            // Regenerate privacy policy
            $('#mbr-cc-regenerate-privacy-policy').on('click', function(e) {
                e.preventDefault();
                self.regeneratePrivacyPolicy();
            });
            
            // Save settings
            $('#mbr-cc-save-settings').on('click', function(e) {
                e.preventDefault();
                self.saveSettings();
            });
            
            // Preview banner
            $('#mbr-cc-preview-banner').on('click', function(e) {
                e.preventDefault();
                self.previewBanner();
            });
            
            // Toggle scan type options
            $('input[name="scan_type"]').on('change', function() {
                if ($(this).val() === 'site-wide') {
                    $('#mbr-cc-single-scan-options').hide();
                    $('#mbr-cc-site-wide-info').show();
                } else {
                    $('#mbr-cc-single-scan-options').show();
                    $('#mbr-cc-site-wide-info').hide();
                }
            });
            
            // Start cookie scan
            $('#mbr-cc-start-scan').on('click', function(e) {
                e.preventDefault();
                self.startScan();
            });
            
            // Add scanned script to blocked list
            $(document).on('click', '.mbr-cc-add-script', function(e) {
                e.preventDefault();
                // .attr(), not .data(): jQuery's .data() parses values that look
                // like numbers or JSON, so an identifier of "123" arrived as a
                // number and broke every .substring() downstream.
                var $b = $(this);
                self.addBlockedScript({
                    name: $b.attr('data-name') || '',
                    identifier: $b.attr('data-identifier') || '',
                    type: $b.attr('data-type') || '',
                    category: $b.attr('data-category') || '',
                    $button: $b
                });
            });
            
            // Add custom blocked script
            $('#mbr-cc-add-blocked-script-form').on('submit', function(e) {
                e.preventDefault();
                self.addCustomScript();
            });
            
            // Remove blocked script
            $(document).on('click', '.mbr-cc-remove-script', function(e) {
                e.preventDefault();
                if (confirm(mbrCcAdmin.confirmDelete)) {
                    var index = $(this).data('index');
                    var $item = $(this).closest('.mbr-cc-script-item');
                    self.removeBlockedScript(index, $item);
                }
            });
            
            // Generate policy page
            $('#mbr-cc-generate-policy').on('click', function(e) {
                e.preventDefault();
                self.generatePolicy();
            });
            
            // Export logs
            $('#mbr-cc-export-logs').on('click', function(e) {
                e.preventDefault();
                self.exportLogs();
            });
            
            // Delete old logs
            $('#mbr-cc-delete-old-logs').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete old logs?')) {
                    self.deleteOldLogs();
                }
            });

            // Import / Export — the export button is a plain link (see
            // import-export.php); it needs no JS handler.

            // Import / Export — enable import button only when a file is chosen and confirmed
            $('#mbr-cc-import-file, #mbr-cc-import-confirm').on('change', function() {
                var hasFile = $('#mbr-cc-import-file').val() !== '';
                var confirmed = $('#mbr-cc-import-confirm').is(':checked');
                $('#mbr-cc-import-settings').prop('disabled', !(hasFile && confirmed));
            });

            // Import / Export — import settings
            $('#mbr-cc-import-settings').on('click', function(e) {
                e.preventDefault();
                self.importSettings();
            });

            // Import / Export — revert last import
            $('#mbr-cc-revert-import').on('click', function(e) {
                e.preventDefault();
                if (confirm('Revert the most recent import and restore the previous settings?')) {
                    self.revertImport();
                }
            });

            // Form integration — save settings
            $('#mbr-cc-form-save').on('click', function(e) {
                e.preventDefault();
                self.saveFormSettings();
            });

            // A/B testing — save enabled state
            $('#mbr-cc-ab-save-enabled').on('click', function(e) {
                e.preventDefault();
                self.saveAbEnabled();
            });

            // A/B testing — promote winner
            $(document).on('click', '.mbr-cc-ab-promote', function(e) {
                e.preventDefault();
                var label = $(this).data('label');
                if (confirm('Promote ' + label + ' to the live banner position? A/B testing will be disabled.')) {
                    self.promoteAbWinner();
                }
            });

            // A/B testing — reset stats
            $('#mbr-cc-ab-reset').on('click', function(e) {
                e.preventDefault();
                if (confirm('Reset all A/B test stats? This cannot be undone.')) {
                    self.resetAbStats();
                }
            });
            
            // Update categories
            $('#mbr-cc-save-categories').on('click', function(e) {
                e.preventDefault();
                self.saveCategories();
            });
        },
        
        gatherSettings: function() {
            var settings = {};
            
            $('[name^="mbr_cc_"]').each(function() {
                var $field = $(this);
                var name = $field.attr('name').replace('mbr_cc_', '');
                var value;
                
                if ($field.is(':checkbox')) {
                    value = $field.is(':checked');
                } else if ($field.is(':radio')) {
                    if (!$field.is(':checked')) {
                        return;
                    }
                    value = $field.val();
                } else {
                    value = $field.val();
                }
                
                settings[name] = value;
            });
            
            return settings;
        },
        
        // Preview the banner using whatever is on screen right now, saved or
        // not. Rendered server-side through the real front-end renderer and
        // shown in an iframe: the banner stylesheet is position:fixed and
        // heavily !important, so injecting it into wp-admin would break the
        // page around it.
        previewBanner: function() {
            var $button = $('#mbr-cc-preview-banner');
            var original = $button.html();
            
            $button.prop('disabled', true).text('Building preview...');
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_preview_banner',
                    nonce: mbrCcAdmin.nonce,
                    settings: this.gatherSettings()
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.document) {
                        MbrCcAdmin.openPreviewModal(response.data.document);
                    } else {
                        window.alert('The preview could not be generated.');
                    }
                },
                error: function() {
                    window.alert('The preview could not be generated.');
                },
                complete: function() {
                    $button.prop('disabled', false).html(original);
                }
            });
        },
        
        openPreviewModal: function(doc) {
            $('#mbr-cc-preview-modal').remove();
            
            var $modal = $(
                '<div id="mbr-cc-preview-modal" class="mbr-cc-preview-modal" role="dialog" aria-modal="true" aria-label="Banner preview">' +
                    '<div class="mbr-cc-preview-modal__overlay"></div>' +
                    '<div class="mbr-cc-preview-modal__panel">' +
                        '<div class="mbr-cc-preview-modal__head">' +
                            '<strong>Banner preview</strong>' +
                            '<span class="mbr-cc-preview-modal__note">Reflects unsaved changes. Buttons are inert.</span>' +
                            '<div class="mbr-cc-preview-modal__devices">' +
                                '<button type="button" class="button button-small is-active" data-width="100%">Desktop</button>' +
                                '<button type="button" class="button button-small" data-width="390px">Mobile</button>' +
                            '</div>' +
                            '<button type="button" class="mbr-cc-preview-modal__close" aria-label="Close preview">&times;</button>' +
                        '</div>' +
                        '<div class="mbr-cc-preview-modal__stage">' +
                            '<iframe class="mbr-cc-preview-modal__frame" title="Banner preview"></iframe>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            
            $('body').append($modal);
            
            // srcdoc keeps the preview in its own document with no network
            // round trip and no shared styles with wp-admin.
            $modal.find('iframe').attr('srcdoc', doc);
            
            var close = function() {
                $modal.remove();
                $(document).off('keydown.mbrCcPreview');
                $('#mbr-cc-preview-banner').trigger('focus');
            };
            
            $modal.on('click', '.mbr-cc-preview-modal__close', close);
            $modal.on('click', '.mbr-cc-preview-modal__overlay', close);
            
            $(document).on('keydown.mbrCcPreview', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    close();
                }
            });
            
            $modal.on('click', '.mbr-cc-preview-modal__devices button', function() {
                var $b = $(this);
                $b.siblings().removeClass('is-active');
                $b.addClass('is-active');
                $modal.find('.mbr-cc-preview-modal__frame').css('width', $b.data('width'));
            });
            
            $modal.find('.mbr-cc-preview-modal__close').trigger('focus');
        },
        
        saveSettings: function() {
            var self = this;
            var $button = $('#mbr-cc-save-settings');
            var settings = this.gatherSettings();

            // Tidy up the policy URL fields, but never refuse to save.
            //
            // This used to abort the whole save when a policy URL looked wrong.
            // Every tab is one form, so that meant a single field could stop
            // the entire settings screen from saving — and the check disagreed
            // with the server's in both directions, so it either blocked a URL
            // the server would have accepted or waved through one it refused.
            // The server now declines just that field and saves the rest, and
            // reports back which field it left alone.
            ['privacy', 'cookie'].forEach(function(policy) {
                var field = document.getElementById(policy + '_policy_url');
                if (!field) { return; }
                field.value = field.value.trim();
                field.setCustomValidity('');
                settings[policy + '_policy_url'] = field.value;
            });
            
            $button.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_save_settings',
                    nonce: mbrCcAdmin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response && response.success) {
                        var warnings = (response.data && response.data.warnings) || [];
                        if (warnings.length) {
                            // Saved, but something was declined. Say so, show
                            // the field and do NOT reload it away.
                            var texts = warnings.map(function(w) { return w.message; }).join(' ');
                            $('.wrap > h1').after(
                                $('<div class="notice notice-warning is-dismissible"><p></p></div>').find('p').text(texts).end()
                            );
                            var first = document.getElementById(warnings[0].field);
                            if (first) {
                                var tabId = $(first).closest('.mbr-cc-tab-content').attr('id');
                                if (tabId) {
                                    $('.mbr-cc-tab-button').filter(function() {
                                        return 'tab-' + $(this).data('tab') === tabId;
                                    }).trigger('click');
                                }
                                first.focus();
                            }
                            $button.prop('disabled', false).text('Save Settings');
                            return;
                        }
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>Settings saved successfully. Reloading...</p></div>');
                        $('.wrap > h1').after($notice);
                        
                        // Reload page after 1 second
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        // Show what the server actually said. A bare "Failed to
                        // save settings." with no reason cost a release cycle
                        // to diagnose; anything the server returns is more
                        // useful than that, including a non-JSON reply, which
                        // means something upstream printed a PHP error.
                        var detail = '';
                        if (response && response.data && response.data.message) {
                            detail = response.data.message;
                        } else if (typeof response === 'string' && response.length) {
                            detail = 'Unexpected server response: ' + $.trim(response).slice(0, 300);
                        } else {
                            detail = 'Failed to save settings, and the server gave no reason.';
                        }
                        $('.wrap > h1').after(
                            $('<div class="notice notice-error is-dismissible"><p></p></div>').find('p').text(detail).end()
                        );
                        $button.prop('disabled', false).text('Save Settings');
                    }
                },
                error: function(xhr) {
                    var detail = 'Could not save settings: the request to the server failed';
                    if (xhr && xhr.status) { detail += ' (HTTP ' + xhr.status + ')'; }
                    if (xhr && xhr.responseText) {
                        detail += '. Server said: ' + $.trim(xhr.responseText).slice(0, 300);
                    }
                    $('.wrap > h1').after(
                        $('<div class="notice notice-error is-dismissible"><p></p></div>').find('p').text(detail).end()
                    );
                    $button.prop('disabled', false).text('Save Settings');
                }
            });
        },
        
        startScan: function() {
            var self = this;
            var $button = $('#mbr-cc-start-scan');
            var $results = $('#mbr-cc-scan-results');
            var $progress = $('#mbr-cc-scan-progress');
            var scanType = $('input[name="scan_type"]:checked').val();
            var url = $('#mbr-cc-scan-url').val() || window.location.origin;
            
            $button.prop('disabled', true).text('Scanning...');
            $results.html('');
            
            if (scanType === 'site-wide') {
                $progress.show();
                $('#mbr-cc-progress-text').text('Scanning your website...');
                $('#mbr-cc-progress-bar').css('width', '10%');
            } else {
                $results.html('<p>Scanning page...</p>');
            }
            
            // A site-wide scan runs in batches. The server works for a fixed
            // spell, reports how far it reached, and this calls back with that
            // offset until it reports itself finished — so a large site takes
            // several short requests rather than one that cannot complete.
            var runScan = function(offset) {
                $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_scan_cookies',
                    nonce: mbrCcAdmin.nonce,
                    scan_type: scanType,
                    url: url,
                    offset: offset || 0
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (scanType === 'site-wide') {
                            var d = response.data;

                            if (d.done === false) {
                                var pct = d.total_urls
                                    ? Math.min(95, Math.round((d.offset / d.total_urls) * 100))
                                    : 10;
                                $('#mbr-cc-progress-bar').css('width', pct + '%');
                                $('#mbr-cc-progress-text').text(
                                    'Scanned ' + d.pages_scanned + ' of ' + d.total_urls +
                                    ' pages — ' + d.count + ' items found so far…'
                                );
                                runScan(d.offset);
                                return;
                            }

                            $('#mbr-cc-progress-bar').css('width', '100%');
                            $('#mbr-cc-progress-text').text('Scan complete!');
                            setTimeout(function() {
                                $progress.hide();
                                self.displayCategorizedResults(response.data);
                            }, 500);
                        } else {
                            self.displaySinglePageResults(response.data);
                        }
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Scan failed. Please try again.';
                        $results.html('<p class="error">' + esc(errorMsg) + '</p>');
                        $progress.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Scanner error:', xhr.responseText);
                    $results.html('<p class="error">Scan failed. Error: ' + esc(error) + '. Please check the browser console for details.</p>');
                    $progress.hide();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Start Scan');
                }
                });
            };

            runScan(0);
        },
        
        displaySinglePageResults: function(data) {
            var $results = $('#mbr-cc-scan-results');
            var html = '<h3>Scan Results (' + esc(data.count) + ' items found)</h3>';
            
            if (data.scripts && data.scripts.length > 0) {
                html += '<h4>Scripts</h4><table class="widefat"><thead><tr><th>Name</th><th>Type</th><th>Category</th><th>Action</th></tr></thead><tbody>';
                
                $.each(data.scripts, function(i, script) {
                    html += '<tr>';
                    html += '<td>' + esc(script.name) + '</td>';
                    html += '<td>' + esc(script.type) + '</td>';
                    html += '<td>' + esc(script.category) + '</td>';
                    html += '<td><button type="button" class="button mbr-cc-add-script" data-name="' + esc(script.name) + '" data-identifier="' + esc(script.identifier) + '" data-type="' + esc(script.type) + '" data-category="' + esc(script.category) + '">Add to Blocked List</button></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            if (data.iframes && data.iframes.length > 0) {
                html += '<h4>Iframes</h4><table class="widefat"><thead><tr><th>Name</th><th>Category</th><th>Action</th></tr></thead><tbody>';
                
                $.each(data.iframes, function(i, iframe) {
                    html += '<tr>';
                    html += '<td>' + esc(iframe.name) + '</td>';
                    html += '<td>' + esc(iframe.category) + '</td>';
                    html += '<td><button type="button" class="button mbr-cc-add-script" data-name="' + esc(iframe.name) + '" data-identifier="' + esc(iframe.identifier) + '" data-type="iframe" data-category="' + esc(iframe.category) + '">Add to Blocked List</button></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            if (data.count === 0) {
                html += '<p>No scripts or iframes found on the scanned page.</p>';
            }
            
            $results.html(html);
        },
        
        displayCategorizedResults: function(data) {
            var $results = $('#mbr-cc-scan-results');
            var html = '<div class="mbr-cc-scan-summary" style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #00a32a;">';
            html += '<h3>✓ Site-Wide Scan Complete</h3>';
            html += '<p><strong>' + esc(data.count) + ' unique scripts/iframes found</strong> across ' + esc(data.pages_scanned) + ' pages</p>';
            html += '</div>';
            
            var categories = data.by_category;
            var categoryNames = {
                'necessary': 'Necessary',
                'analytics': 'Analytics', 
                'marketing': 'Marketing',
                'preferences': 'Preferences'
            };
            
            var categoryColors = {
                'necessary': '#00a32a',
                'analytics': '#0073aa',
                'marketing': '#d63638',
                'preferences': '#f0a500'
            };
            
            $.each(categoryNames, function(slug, name) {
                if (!categories[slug] || categories[slug].length === 0) {
                    return;
                }
                
                var color = categoryColors[slug];
                var items = categories[slug];
                
                html += '<div class="mbr-cc-category-results" style="margin: 20px 0; border-left: 4px solid ' + color + '; background: #fff; padding: 15px;">';
                html += '<h4 style="margin-top: 0; color: ' + color + ';">' + name + ' (' + items.length + ')</h4>';
                html += '<table class="widefat"><thead><tr><th style="width: 30%;">Name</th><th style="width: 15%;">Type</th><th style="width: 35%;">Found On</th><th style="width: 20%;">Action</th></tr></thead><tbody>';
                
                $.each(items, function(i, item) {
                    var foundOnText = item.found_on ? item.found_on.length + ' page(s)' : '1 page';
                    var foundOnTitle = item.found_on ? item.found_on.slice(0, 5).join('\n') : '';
                    if (item.found_on && item.found_on.length > 5) {
                        foundOnTitle += '\n... and ' + (item.found_on.length - 5) + ' more';
                    }
                    
                    html += '<tr>';
                    var ident = String(item.identifier || '');
                    html += '<td><strong>' + esc(item.name) + '</strong><br><small style="color: #666;">' + esc(ident.substring(0, 50)) + (ident.length > 50 ? '...' : '') + '</small></td>';
                    html += '<td>' + esc(item.type) + '</td>';
                    html += '<td title="' + esc(foundOnTitle) + '">' + esc(foundOnText) + '</td>';
                    html += '<td><button type="button" class="button button-small mbr-cc-add-script" data-name="' + esc(item.name) + '" data-identifier="' + esc(ident) + '" data-type="' + esc(item.type) + '" data-category="' + esc(slug) + '">Add to Blocked</button></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            });
            
            if (data.count === 0) {
                html += '<p>No scripts or iframes found on your website.</p>';
            }
            
            $results.html(html);
        },
        
        addBlockedScript: function(data) {
            var self = this;
            // Filter rather than build a selector: an identifier containing a
            // quote or bracket made the old attribute selector throw.
            var $button = data.$button || $('.mbr-cc-add-script').filter(function() {
                return $(this).attr('data-identifier') === data.identifier;
            });
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Adding...');
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_add_blocked_script',
                    nonce: mbrCcAdmin.nonce,
                    name: data.name,
                    identifier: data.identifier,
                    type: data.type,
                    category: data.category
                },
                success: function(response) {
                    if (response.success) {
                        delete data.$button;
                        // Change button to success state
                        $button.text('✓ Added').css({
                            'background': '#00a32a',
                            'color': '#fff',
                            'border-color': '#00a32a'
                        });
                        
                        // Add to blocked scripts list
                        self.addToBlockedList(data);
                        
                        // Show success notice at top
                        var $notice = $('<div class="notice notice-success is-dismissible"><p><strong>' + esc(data.name) + '</strong> has been added to the blocked scripts list.</p></div>');
                        $('.wrap > h1').after($notice);
                        
                        // Auto-dismiss notice after 3 seconds
                        setTimeout(function() {
                            $notice.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        // Show error
                        $button.prop('disabled', false).text('Add to Blocked');
                        var $notice = $('<div class="notice notice-error is-dismissible"><p>Failed to add script: ' + esc(response.data ? response.data.message : 'Unknown error') + '</p></div>');
                        $('.wrap > h1').after($notice);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Add to Blocked');
                    var $notice = $('<div class="notice notice-error is-dismissible"><p>An error occurred while adding the script.</p></div>');
                    $('.wrap > h1').after($notice);
                }
            });
        },
        
        addToBlockedList: function(script) {
            console.log('addToBlockedList called with:', script);
            
            // Find the blocked scripts section - try multiple selectors
            var $blockedSection = $('.mbr-cc-blocked-scripts');
            console.log('Found blocked section:', $blockedSection.length);
            
            if ($blockedSection.length === 0) {
                console.log('Blocked section not found, creating it...');
                
                // Section doesn't exist, create it after the manual add form
                var sectionHtml = '<div class="mbr-cc-settings-section mbr-cc-blocked-scripts">';
                sectionHtml += '<h2>Currently Blocked Scripts</h2>';
                sectionHtml += '</div>';
                
                // Find the manual add script section and insert after it
                var $manualSection = $('#mbr-cc-add-blocked-script-form').closest('.mbr-cc-settings-section');
                if ($manualSection.length > 0) {
                    $manualSection.after(sectionHtml);
                } else {
                    // Fallback: add at the end
                    $('.mbr-cc-admin-wrap').append(sectionHtml);
                }
                
                $blockedSection = $('.mbr-cc-blocked-scripts');
                console.log('Created blocked section:', $blockedSection.length);
            }
            
            // Get the current number of blocked scripts to use as index
            var currentCount = $blockedSection.find('.mbr-cc-script-item').length;
            console.log('Current blocked script count:', currentCount);
            
            // Create new script item HTML
            var identifier = String(script.identifier || '');
            var scriptHtml = '<div class="mbr-cc-script-item" data-index="' + currentCount + '" data-identifier="' + esc(identifier) + '">';
            scriptHtml += '<div class="mbr-cc-script-info">';
            scriptHtml += '<h4>' + esc(script.name) + '</h4>';
            scriptHtml += '<p><strong>Type:</strong> ' + esc(script.type) + '</p>';
            scriptHtml += '<p><strong>Category:</strong> ' + esc(script.category) + '</p>';
            scriptHtml += '<p class="mbr-cc-script-meta"><code>' + esc(identifier.substring(0, 80));
            if (identifier.length > 80) {
                scriptHtml += '...';
            }
            scriptHtml += '</code></p>';
            if (script.description) {
                scriptHtml += '<p>' + esc(script.description) + '</p>';
            }
            scriptHtml += '</div>';
            scriptHtml += '<div class="mbr-cc-script-actions">';
            scriptHtml += '<button type="button" class="button mbr-cc-remove-script" data-index="' + currentCount + '">Remove</button>';
            scriptHtml += '</div>';
            scriptHtml += '</div>';
            
            console.log('Creating new script item HTML');
            
            // Add highlight animation
            var $newItem = $(scriptHtml);
            $newItem.css({
                'background': '#d7ffd9',
                'transition': 'background 2s ease'
            });
            
            // Append to blocked scripts section
            $blockedSection.append($newItem);
            console.log('Appended new item to blocked section');
            
            // Fade out highlight after a moment
            setTimeout(function() {
                $newItem.css('background', '#fff');
            }, 500);
            
            // Scroll to the new item
            $('html, body').animate({
                scrollTop: $newItem.offset().top - 100
            }, 500);
            
            console.log('addToBlockedList complete');
        },
        
        addCustomScript: function() {
            var self = this;
            var $form = $('#mbr-cc-add-blocked-script-form');
            
            var data = {
                action: 'mbr_cc_add_blocked_script',
                nonce: mbrCcAdmin.nonce,
                name: $form.find('[name="script_name"]').val(),
                identifier: $form.find('[name="script_identifier"]').val(),
                type: $form.find('[name="script_type"]').val(),
                category: $form.find('[name="script_category"]').val(),
                description: $form.find('[name="script_description"]').val()
            };
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Script added successfully.', 'success');
                        $form[0].reset();
                        location.reload();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },
        
        removeBlockedScript: function(index, $item) {
            var self = this;
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_remove_blocked_script',
                    nonce: mbrCcAdmin.nonce,
                    index: index
                },
                success: function(response) {
                    if (response.success) {
                        // Fade out and remove the item
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            
                            var $blockedSection = $('.mbr-cc-blocked-scripts');
                            var $remaining = $blockedSection.find('.mbr-cc-script-item');

                            if ($remaining.length === 0) {
                                // No scripts left — remove the whole section.
                                $blockedSection.remove();
                            } else {
                                // Re-index remaining remove buttons to match the
                                // server-side array_values() re-index after removal.
                                $remaining.each(function(newIndex) {
                                    $(this).find('.mbr-cc-remove-script').data('index', newIndex);
                                });
                            }
                        });
                        
                        // Show success notice
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>Script removed from blocked list.</p></div>');
                        $('.wrap > h1').after($notice);
                        
                        setTimeout(function() {
                            $notice.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        var $notice = $('<div class="notice notice-error is-dismissible"><p>Failed to remove script.</p></div>');
                        $('.wrap > h1').after($notice);
                    }
                }
            });
        },
        
        generatePolicy: function() {
            var self = this;
            var $button = $('#mbr-cc-generate-policy');
            
            $button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_generate_policy',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Cookie policy page created!', 'success', $('<a></a>').attr('href', response.data.edit_link).text('Edit page'));
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Cookie Policy Page');
                }
            });
        },
        
        exportLogs: function() {
            var dateFrom = $('#mbr-cc-export-date-from').val();
            var dateTo = $('#mbr-cc-export-date-to').val();
            
            var url = mbrCcAdmin.ajaxUrl + '?action=mbr_cc_export_logs&nonce=' + mbrCcAdmin.nonce;
            
            if (dateFrom) {
                url += '&date_from=' + dateFrom;
            }
            
            if (dateTo) {
                url += '&date_to=' + dateTo;
            }
            
            window.location.href = url;
        },
        
        deleteOldLogs: function() {
            var self = this;
            var days = parseInt($('#mbr-cc-delete-logs-days').val(), 10);
            
            // Guard: '0' is a truthy string, so a plain `val() || 365`
            // would let 0 through and delete EVERY log. Require >= 1.
            if (isNaN(days) || days < 1) {
                self.showNotice('Please enter a number of days (1 or more).', 'error');
                return;
            }
            
            if (!window.confirm('Permanently delete all consent logs older than ' + days + ' days? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_delete_logs',
                    nonce: mbrCcAdmin.nonce,
                    days: days
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },

        saveFormSettings: function() {
            var self = this;
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_save_form_settings',
                    nonce: mbrCcAdmin.nonce,
                    enabled: $('#mbr-cc-form-enabled').is(':checked') ? 1 : 0,
                    message: $('#mbr-cc-form-message').val(),
                    category: $('#mbr-cc-form-required-category').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },

        saveAbEnabled: function() {
            var self = this;
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_save_ab_enabled',
                    nonce: mbrCcAdmin.nonce,
                    enabled: $('#mbr-cc-ab-enabled').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },

        promoteAbWinner: function() {
            var self = this;
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_ab_promote_winner',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },

        resetAbStats: function() {
            var self = this;
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_ab_reset_stats',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },
        
        saveCategories: function() {
            var self = this;
            var categories = {};
            
            $('.mbr-cc-category-item').each(function() {
                var $item = $(this);
                var slug = $item.data('slug');
                
                categories[slug] = {
                    name: $item.find('.category-name').val(),
                    description: $item.find('.category-description').val(),
                    required: $item.find('.category-required').is(':checked'),
                    enabled: $item.find('.category-enabled').is(':checked')
                };
            });
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_update_categories',
                    nonce: mbrCcAdmin.nonce,
                    categories: categories
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Categories updated successfully.', 'success');
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },
        
        
        generatePolicy: function() {
            var self = this;
            
            if (!confirm('This will create a new Cookie Policy page in draft status. Continue?')) {
                return;
            }
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_generate_policy',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Cookie Policy page created successfully!', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.edit_link;
                        }, 1500);
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },
        
        // Rewrites the existing privacy policy page from current settings.
        // This is destructive to any wording the site owner has added by hand,
        // on a legal document, so the warning is deliberately blunt rather than
        // a generic "are you sure".
        regeneratePrivacyPolicy: function() {
            var self = this;
            var $button = $('#mbr-cc-regenerate-privacy-policy');

            if (!window.confirm(
                'Regenerate the Privacy Policy page?\n\n' +
                'The page content will be rewritten from your current plugin settings. ' +
                'Any wording you have edited or added by hand will be replaced.\n\n' +
                'The page keeps its title, URL and published status, and the current ' +
                'version is saved as a revision so you can restore it from the page ' +
                'editor if you need to.'
            )) {
                return;
            }

            var original = $button.text();
            $button.prop('disabled', true).text('Regenerating...');

            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_regenerate_privacy_policy',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response && response.success) {
                        self.showNotice(response.data.message || 'Privacy policy regenerated.', 'success');
                    } else {
                        self.showNotice(
                            (response && response.data && response.data.message) || 'Could not regenerate the privacy policy.',
                            'error'
                        );
                    }
                },
                error: function() {
                    self.showNotice('Could not regenerate the privacy policy.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(original);
                }
            });
        },
        
        generatePrivacyPolicy: function() {
            var self = this;
            
            if (!confirm('This will create a comprehensive Privacy Policy page based on your site configuration. The page will be created in draft status for you to review. Continue?')) {
                return;
            }
            
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_generate_privacy_policy',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice('Privacy Policy page created successfully! Please review before publishing.', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.edit_link;
                        }, 1500);
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                }
            });
        },
        
        importSettings: function() {
            var self = this;
            var input = document.getElementById('mbr-cc-import-file');

            if (!input || !input.files || input.files.length === 0) {
                self.showNotice('Please choose a settings file to import.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'mbr_cc_import_settings');
            formData.append('nonce', mbrCcAdmin.nonce);
            formData.append('mbr_cc_import_file', input.files[0]);

            var $btn = $('#mbr-cc-import-settings');
            $btn.prop('disabled', true);

            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.renderImportReport(response.data);
                        self.showNotice(response.data.message, 'success');
                        // Reveal the revert section without a full reload.
                        if (response.data.can_revert) {
                            $('#mbr-cc-revert-section').show();
                        }
                    } else {
                        self.showNotice(response.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    self.showNotice('The import request failed. Please try again.', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        renderImportReport: function(data) {
            var lines = [];
            // Everything here but the fixed sentences came out of the imported
            // file, which is exactly the kind of file that gets emailed around.
            lines.push('<strong>' + esc(data.message) + '</strong>');

            if (data.checksum_ok === false) {
                lines.push('<span style="color:#b32d2e;">Note: the file\'s integrity checksum did not match. It may have been edited by hand. The settings were still imported.</span>');
            }

            if (data.skipped_count && data.skipped_count > 0) {
                lines.push(esc(data.skipped_count) + ' unrecognised field(s) were ignored: <code>' + $.map(data.skipped || [], esc).join('</code>, <code>') + '</code>');
            }

            if (data.source_url) {
                lines.push('Source: ' + esc(data.source_url));
            }

            var $report = $('#mbr-cc-import-report');
            $report.html('<div class="notice notice-info inline" style="padding:8px 12px;"><p style="margin:0;">' + lines.join('<br>') + '</p></div>').show();
        },

        revertImport: function() {
            var self = this;
            $.ajax({
                url: mbrCcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mbr_cc_revert_import',
                    nonce: mbrCcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        $('#mbr-cc-revert-section').hide();
                        $('#mbr-cc-import-report').hide();
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showNotice('The revert request failed. Please try again.', 'error');
                }
            });
        },

        /**
         * Show an admin notice. The message is text, never markup: server
         * messages can echo back values that originated with a visitor or an
         * imported file. Pass a jQuery element as $extra to append a link.
         */
        showNotice: function(message, type, $extra) {
            var safeType = /^(success|error|warning|info)$/.test(type) ? type : 'info';
            var $p = $('<p></p>').text(message === undefined || message === null ? '' : String(message));
            if ($extra) {
                $p.append(' ').append($extra);
            }
            var $notice = $('<div class="notice is-dismissible"></div>').addClass('notice-' + safeType).append($p);
            $('.wrap > h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MbrCcAdmin.init();
    });
    
})(jQuery);
