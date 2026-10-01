<?php
/**
 * Consent Doctor panel.
 *
 * Grouped by evidence tier rather than by subject, so the difference between
 * "configured this way" and "observed doing this" is structural and cannot be
 * skimmed past. The summary line deliberately counts findings rather than
 * pronouncing on compliance: "12 checks passed" is a fact, "you are compliant"
 * is a claim nobody should be making on a site owner's behalf.
 *
 * @package MBR_Cookie_Consent
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_script(
    'mbr-cc-doctor-admin',
    MBR_CC_PLUGIN_URL . 'assets/js/doctor-admin.js',
    array(),
    MBR_CC_VERSION,
    true
);
wp_localize_script('mbr-cc-doctor-admin', 'mbrCcDoctor', array(
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce('mbr_cc_admin_nonce'),
    'home'       => home_url('/'),
    'homeHost'   => strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST)),
    'probeFlag'  => 'mbr_cc_doctor_probe',
    'probeNonce' => wp_create_nonce('mbr_cc_doctor_probe'),
    'version'    => MBR_CC_VERSION,

    // Retained for a browser still holding an older copy of doctor-admin.js.
    // The URL selector in 2.4.3 replaced these with the parts above, and a
    // cached older script then read cfg.probeUrlUnconsented, got undefined, and
    // set the frame's src to the string "undefined&_=1234567890" — a 404 and a
    // hang, with nothing in the panel to suggest the script was the problem.
    // Removing a key that deployed code reads is a breaking change; these cost
    // nothing to keep.
    'probeUrl'            => MBR_CC_Doctor_Probe::probe_url(false),
    'probeUrlUnconsented' => MBR_CC_Doctor_Probe::probe_url(true),
));

$doctor  = MBR_CC_Doctor::get_instance();
$report  = $doctor->run();
$checks  = $report['checks'];
$summary = $report['summary'];

$status_labels = MBR_CC_Doctor::status_labels();
$tier_labels   = MBR_CC_Doctor::tier_labels();
$tier_descs    = MBR_CC_Doctor::tier_descriptions();

$colours = array(
    MBR_CC_Doctor::PASS    => array('#00a32a', '#f0f9f1'),
    MBR_CC_Doctor::NOTE    => array('#2271b1', '#f2f7fc'),
    MBR_CC_Doctor::WARN    => array('#dba617', '#fcf9f1'),
    MBR_CC_Doctor::FAIL    => array('#d63638', '#fdf3f3'),
    MBR_CC_Doctor::UNKNOWN => array('#8c8f94', '#f6f7f7'),
);

$order = array(MBR_CC_Doctor::TIER_SERVER, MBR_CC_Doctor::TIER_CONFIG, MBR_CC_Doctor::TIER_BROWSER);

$grouped = array();
foreach ($order as $t) {
    $grouped[$t] = array();
}
foreach ($checks as $c) {
    $grouped[$c['tier']][] = $c;
}

$problems = $summary[MBR_CC_Doctor::FAIL];
$reviews  = $summary[MBR_CC_Doctor::WARN];
?>
<div class="wrap mbr-cc-doctor">
    <h1><?php esc_html_e('Consent Doctor', 'mbr-cookie-consent'); ?></h1>

    <p class="description" style="max-width: 46em; font-size: 13px;">
        <?php esc_html_e('A read-only check of your consent setup. Nothing here changes any setting.', 'mbr-cookie-consent'); ?>
    </p>

    <div style="margin: 16px 0; padding: 14px 16px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid <?php echo esc_attr($problems ? '#d63638' : ($reviews ? '#dba617' : '#00a32a')); ?>;">
        <p style="margin: 0; font-size: 14px;">
            <strong>
                <?php
                printf(
                    /* translators: 1: problems, 2: items needing review, 3: observed-working count. */
                    esc_html__('%1$d problem(s), %2$d needing review, %3$d observed working.', 'mbr-cookie-consent'),
                    (int) $problems,
                    (int) $reviews,
                    (int) $summary[MBR_CC_Doctor::PASS]
                );
                ?>
            </strong>
        </p>
        <p style="margin: 6px 0 0 0; color: #50575e;">
            <?php esc_html_e('This is a count of findings, not a verdict on compliance. No automated check can tell you that your site is compliant, and this one is not trying to.', 'mbr-cookie-consent'); ?>
        </p>
    </div>

    <div style="margin: 16px 0; padding: 14px 16px; background: #fff; border: 1px solid #c3c4c7;">
        <p style="margin: 0 0 8px 0;"><strong><?php esc_html_e('Live check', 'mbr-cookie-consent'); ?></strong></p>
        <p style="margin: 0 0 10px 0; max-width: 46em; color: #50575e;">
            <?php esc_html_e('Loads your home page in a hidden frame and records every network request it actually makes. This is the only check that can show whether anything reached a third party before consent.', 'mbr-cookie-consent'); ?>
        </p>
        <p style="margin: 0 0 6px 0;">
            <label for="mbr-cc-doctor-url"><strong><?php esc_html_e('Page to check', 'mbr-cookie-consent'); ?></strong></label>
        </p>
        <?php
        /*
         * A visible <select>, not a <datalist>.
         *
         * The datalist this replaces was reported as "a little dropdown icon
         * that doesn't do anything", which is its usual failure: the options are
         * hidden until you interact, browsers filter them against whatever is
         * already in the field — and the field is pre-filled with the home URL —
         * and the labels are shown inconsistently or not at all. A control whose
         * contents you cannot see is not a control.
         *
         * The free-text field stays for any other URL on the site.
         */
        $mbr_cc_targets = MBR_CC_Doctor_Probe::suggested_targets();
        ?>
        <p style="margin: 0 0 6px 0;">
            <select id="mbr-cc-doctor-preset" style="max-width: 46em; width: 100%;">
                <?php foreach ($mbr_cc_targets as $t) : ?>
                    <option value="<?php echo esc_url($t['url']); ?>"><?php echo esc_html($t['label']); ?></option>
                <?php endforeach; ?>
                <option value=""><?php esc_html_e('Another page on this site…', 'mbr-cookie-consent'); ?></option>
            </select>
        </p>
        <p style="margin: 0 0 6px 0;">
            <input type="url" id="mbr-cc-doctor-url"
                   value="<?php echo esc_url(home_url('/')); ?>"
                   style="width: 100%; max-width: 46em;">
        </p>
        <?php if (count($mbr_cc_targets) < 2) : ?>
            <p style="margin: 0 0 10px 0; color: #996800;">
                <?php esc_html_e('No posts or pages were found to suggest, so only the home page is listed. Paste any URL from this site into the field above.', 'mbr-cookie-consent'); ?>
            </p>
        <?php endif; ?>
        <p style="margin: 0 0 10px 0; color: #50575e; max-width: 46em;">
            <?php esc_html_e('Any page on this site. The home page is often the least revealing — comment threads and embedded video usually live on inner pages, and those are where tracking tends to escape. The suggestions list your most-commented and most recent pages.', 'mbr-cookie-consent'); ?>
        </p>
        <p style="margin: 0 0 10px 0;">
            <label>
                <input type="checkbox" id="mbr-cc-doctor-simulate" checked>
                <?php esc_html_e('Simulate a first-time visitor (temporarily sets your own consent choice aside, then restores it)', 'mbr-cookie-consent'); ?>
            </label>
        </p>
        <button type="button" class="button button-primary" id="mbr-cc-doctor-run">
            <?php esc_html_e('Run live check', 'mbr-cookie-consent'); ?>
        </button>
        <span id="mbr-cc-doctor-status" style="margin-left: 10px; color: #50575e;"></span>
        <a id="mbr-cc-doctor-direct" href="#" target="_blank" rel="noopener"
           style="display:none;margin-left:10px;">
            <?php esc_html_e('Open the probe page directly', 'mbr-cookie-consent'); ?>
        </a>
        <p style="margin: 10px 0 0 0; color: #50575e; max-width: 46em;">
            <?php esc_html_e('Run it in a browser with no ad blocker. If an extension suppresses the requests, the result will look clean and mean nothing — the check detects this and says so.', 'mbr-cookie-consent'); ?>
        </p>
        <?php /*
            Positioned off-screen rather than display:none. A display:none frame
            is not laid out, so every element inside it measures as zero-height —
            which made the content-blocker bait look hidden on every single run —
            and lazy-loaded embeds never request anything, so real leaks were
            missed too. Off-screen keeps layout alive while staying invisible.
        */ ?>
        <iframe id="mbr-cc-doctor-frame"
                style="position:fixed;left:-10000px;top:0;width:1280px;height:900px;border:0;visibility:visible;"
                aria-hidden="true" tabindex="-1"
                ></iframe>
    </div>

    <?php foreach ($order as $tier) : ?>
        <?php if (empty($grouped[$tier])) { continue; } ?>

        <h2 style="margin-top: 28px; margin-bottom: 2px;"><?php echo esc_html($tier_labels[$tier]); ?></h2>
        <p class="description" style="margin-top: 0; max-width: 46em;">
            <?php echo esc_html($tier_descs[$tier]); ?>
        </p>

        <?php foreach ($grouped[$tier] as $check) : ?>
            <?php
            $c = isset($colours[$check['status']]) ? $colours[$check['status']] : $colours[MBR_CC_Doctor::UNKNOWN];
            ?>
            <div style="margin: 10px 0; padding: 12px 14px; background: <?php echo esc_attr($c[1]); ?>; border: 1px solid #dcdcde; border-left: 4px solid <?php echo esc_attr($c[0]); ?>;">
                <p style="margin: 0 0 4px 0;">
                    <strong style="font-size: 13px;"><?php echo esc_html($check['title']); ?></strong>
                    <span style="margin-left: 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: <?php echo esc_attr($c[0]); ?>;">
                        <?php echo esc_html($status_labels[$check['status']]); ?>
                    </span>
                </p>
                <p style="margin: 0; color: #3c434a;"><?php echo esc_html($check['detail']); ?></p>
                <?php if ('' !== $check['action']) : ?>
                    <p style="margin: 6px 0 0 0; color: #50575e;">
                        <em><?php echo esc_html($check['action']); ?></em>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <h2 style="margin-top: 28px;"><?php esc_html_e('What this panel still cannot tell you', 'mbr-cookie-consent'); ?></h2>
    <p style="max-width: 46em;">
        <?php esc_html_e('The live check covers the one page it loaded. Another page with different embeds or extra tags may behave differently, so a clean result is evidence about that page rather than the whole site.', 'mbr-cookie-consent'); ?>
    </p>
    <p style="max-width: 46em;">
        <?php esc_html_e('Requests made inside a cross-origin frame — the contents of a YouTube or Maps embed, for example — cannot be seen from outside it. The frame being loaded at all is usually the finding, and that is visible.', 'mbr-cookie-consent'); ?>
    </p>
</div>
