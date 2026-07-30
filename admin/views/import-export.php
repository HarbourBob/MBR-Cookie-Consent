<?php
/**
 * Import / Export View
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$mbr_cc_ie      = MBR_CC_Import_Export::get_instance();
$mbr_cc_backup  = get_option(MBR_CC_Import_Export::BACKUP_OPTION, null);
$mbr_cc_has_bak = $mbr_cc_ie->has_backup();

// The export is a plain link rather than a JS-driven navigation. A download IS
// a navigation, so a link expresses it natively: it keeps working even if the
// admin JS fails to load or another script on the page throws, it supports
// right-click "Save link as" and middle-click, it is keyboard accessible, and
// if the server side ever errors the browser shows the response instead of
// failing silently. The nonce is baked in here and checked by the handler.
$mbr_cc_export_url = add_query_arg(
    array(
        'action' => 'mbr_cc_export_settings',
        'nonce'  => wp_create_nonce('mbr_cc_admin_nonce'),
    ),
    admin_url('admin-ajax.php')
);
?>

<div class="wrap mbr-cc-admin-wrap">
    <h1><?php esc_html_e('Import / Export Settings', 'mbr-cookie-consent'); ?></h1>

    <p class="description" style="max-width:70ch;">
        <?php esc_html_e('Move a tuned configuration between sites. Export a settings file here, then import it on another install to reproduce the same banner, categories, blocked scripts, consent-mode and geolocation configuration.', 'mbr-cookie-consent'); ?>
    </p>

    <!-- EXPORT -->
    <div class="mbr-cc-settings-section">
        <h2><?php esc_html_e('Export', 'mbr-cookie-consent'); ?></h2>
        <p>
            <?php esc_html_e('Download this site\'s configuration as a JSON file.', 'mbr-cookie-consent'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url($mbr_cc_export_url); ?>"
               id="mbr-cc-export-settings"
               class="button button-primary mbr-cc-icon-button">
                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                <?php esc_html_e('Download settings file', 'mbr-cookie-consent'); ?>
            </a>
        </p>
        <p class="description" style="max-width:70ch;">
            <strong><?php esc_html_e('Included:', 'mbr-cookie-consent'); ?></strong>
            <?php esc_html_e('banner appearance and text, behaviour, cookie categories, blocked scripts, consent-mode (Google/Microsoft), GPC, IAB TCF, form integration, and all geolocation regional headings and descriptions.', 'mbr-cookie-consent'); ?>
        </p>
        <p class="description" style="max-width:70ch;">
            <strong><?php esc_html_e('Not included:', 'mbr-cookie-consent'); ?></strong>
            <?php esc_html_e('consent logs (visitor personal data — use the CSV export on the Consent Logs screen), and site-local values such as policy page IDs and the geolocation IP cache, which would not be valid on another site.', 'mbr-cookie-consent'); ?>
        </p>
    </div>

    <!-- IMPORT -->
    <div class="mbr-cc-settings-section">
        <h2><?php esc_html_e('Import', 'mbr-cookie-consent'); ?></h2>
        <p style="max-width:70ch;">
            <?php esc_html_e('Upload a settings file exported from this plugin. Matching settings on this site will be overwritten. Unknown fields are ignored and every value is re-validated on import.', 'mbr-cookie-consent'); ?>
        </p>

        <p>
            <input type="file" id="mbr-cc-import-file" accept="application/json,.json" />
        </p>

        <p>
            <label>
                <input type="checkbox" id="mbr-cc-import-confirm" />
                <?php esc_html_e('I understand this will overwrite matching settings on this site.', 'mbr-cookie-consent'); ?>
            </label>
        </p>

        <p>
            <button type="button" id="mbr-cc-import-settings" class="button button-primary mbr-cc-icon-button" disabled>
                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                <?php esc_html_e('Import settings', 'mbr-cookie-consent'); ?>
            </button>
        </p>

        <div id="mbr-cc-import-report" style="display:none; margin-top:12px;"></div>

        <p class="description" style="max-width:70ch;">
            <?php esc_html_e('A one-step backup of the changed settings is taken automatically before importing, so you can undo the most recent import below.', 'mbr-cookie-consent'); ?>
        </p>
    </div>

    <!-- REVERT -->
    <div class="mbr-cc-settings-section" id="mbr-cc-revert-section" <?php echo $mbr_cc_has_bak ? '' : 'style="display:none;"'; ?>>
        <h2><?php esc_html_e('Undo last import', 'mbr-cookie-consent'); ?></h2>
        <?php if ($mbr_cc_has_bak && !empty($mbr_cc_backup['created_at'])) : ?>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: date/time the backup was taken. */
                    esc_html__('Backup taken %s (UTC).', 'mbr-cookie-consent'),
                    esc_html(gmdate('Y-m-d H:i', strtotime($mbr_cc_backup['created_at'])))
                );
                if (!empty($mbr_cc_backup['source_url'])) {
                    echo ' ';
                    printf(
                        /* translators: %s: source site URL of the imported file. */
                        esc_html__('Imported from %s.', 'mbr-cookie-consent'),
                        esc_html($mbr_cc_backup['source_url'])
                    );
                }
                ?>
            </p>
        <?php endif; ?>
        <p>
            <button type="button" id="mbr-cc-revert-import" class="button button-secondary">
                <?php esc_html_e('Revert last import', 'mbr-cookie-consent'); ?>
            </button>
        </p>
    </div>
</div>
