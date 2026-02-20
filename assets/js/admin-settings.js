/**
 * Settings Page JavaScript
 * Handles tab switching and layout selection
 */

(function($) {
    'use strict';
    
    console.log('MBR Cookie Consent Settings JS Loaded');
    
    $(document).ready(function() {
        
        console.log('Settings page ready');
        
        // Tab switching
        $('.mbr-cc-tab-button').on('click', function(e) {
            e.preventDefault();
            console.log('Tab clicked:', $(this).data('tab')); console.log('Active tabs:', $('.mbr-cc-tab-content.active').length);
            var tab = $(this).data('tab');
            
            // Update buttons
            $('.mbr-cc-tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update content
            $('.mbr-cc-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });
        
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
        
        // Logo upload
        $('.mbr-cc-upload-logo').on('click', function(e) {
            e.preventDefault();
            
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('WordPress media library not loaded.');
                return;
            }
            
            var mediaUploader = wp.media({
                title: 'Select Logo',
                button: {
                    text: 'Use this logo'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#banner_logo_url').val(attachment.url);
                
                if ($('.mbr-cc-logo-preview').length) {
                    $('.mbr-cc-logo-preview img').attr('src', attachment.url);
                } else {
                    $('#banner_logo_url').after('<div class="mbr-cc-logo-preview"><img src="' + attachment.url + '" alt="Logo Preview"></div>');
                }
            });
            
            mediaUploader.open();
        });
        
    });
    
})(jQuery);
