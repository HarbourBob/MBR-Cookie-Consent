<?php
/**
 * Translations review screen.
 *
 * Community translations are unverified, and these are consent notices — legal
 * text. Nothing reaches a visitor until an administrator has read that language
 * and approved it here. An unapproved language falls back to the site's own
 * wording, so the failure mode is text the owner wrote.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$index      = MBR_CC_Translations::index();
$approved   = MBR_CC_Translations::approved_languages();
$defaults   = MBR_CC_Translations::default_strings();
$customised = MBR_CC_Translations::customised_keys();
$enabled    = MBR_CC_Translations::is_enabled();
$total      = (int) ($index['total_keys'] ?? 0);

// Reviewing a single language?
$reviewing = isset($_GET['review']) ? sanitize_key(wp_unslash($_GET['review'])) : '';
if ($reviewing && !isset($index['languages'][$reviewing])) {
    $reviewing = '';
}
?>
<div class="wrap mbr-cc-translations">

<?php if ($reviewing) :
    $strings = MBR_CC_Translations::catalogue($reviewing);
    $meta    = $index['languages'][$reviewing];
    $is_ok   = in_array($reviewing, $approved, true);
    ?>

    <h1>
        <?php
        printf(
            /* translators: %s: language name. */
            esc_html__('Review: %s', 'mbr-cookie-consent'),
            esc_html($meta['name'])
        );
        ?>
    </h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=mbr-cookie-consent-translations')); ?>">
            &larr; <?php esc_html_e('Back to all languages', 'mbr-cookie-consent'); ?>
        </a>
    </p>

    <div class="notice notice-warning inline">
        <p>
            <strong><?php esc_html_e('Read this before approving.', 'mbr-cookie-consent'); ?></strong>
            <?php esc_html_e('These translations are contributed by the community and have not been verified by a qualified translator or a lawyer. A consent notice is a legal statement, and an approximate translation of one carries real risk. Approve a language only once you are satisfied the wording says what you intend it to say.', 'mbr-cookie-consent'); ?>
        </p>
    </div>

    <?php if (count($strings) < $total) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php
                printf(
                    /* translators: 1: translated count, 2: total count. */
                    esc_html__('This language covers %1$d of %2$d strings. Anything missing falls back to your own wording, so visitors will see a mixture of languages. That is usually worse than showing no translation at all — bear it in mind when deciding whether to approve.', 'mbr-cookie-consent'),
                    count($strings),
                    $total
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="margin-top: 1em;">
        <thead>
            <tr>
                <th style="width: 18%;"><?php esc_html_e('String', 'mbr-cookie-consent'); ?></th>
                <th style="width: 41%;"><?php esc_html_e('Your wording', 'mbr-cookie-consent'); ?></th>
                <th style="width: 41%;"><?php echo esc_html($meta['name']); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($defaults as $key => $english) :
            $translated = $strings[$key] ?? '';
            $skipped    = in_array($key, $customised, true);
            ?>
            <tr>
                <td><code><?php echo esc_html($key); ?></code></td>
                <td<?php echo $skipped ? ' style="opacity:.55;"' : ''; ?>>
                    <?php echo esc_html($english); ?>
                    <?php if ($skipped) : ?>
                        <br><em style="font-size: 11px;"><?php esc_html_e('You have rewritten this string, so it is never auto-translated.', 'mbr-cookie-consent'); ?></em>
                    <?php endif; ?>
                </td>
                <td<?php echo ('rtl' === $meta['direction']) ? ' dir="rtl"' : ''; ?>>
                    <?php if ($skipped) : ?>
                        <span style="opacity:.45;">&mdash;</span>
                    <?php elseif ('' === $translated) : ?>
                        <span style="color:#b32d2e;"><?php esc_html_e('Not translated — falls back to your wording', 'mbr-cookie-consent'); ?></span>
                    <?php else : ?>
                        <?php echo esc_html($translated); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 1.5em;">
        <?php wp_nonce_field('mbr_cc_translation_review'); ?>
        <input type="hidden" name="action" value="mbr_cc_translation_review">
        <input type="hidden" name="lang" value="<?php echo esc_attr($reviewing); ?>">

        <?php if ($is_ok) : ?>
            <p><strong><?php esc_html_e('This language is approved and is being served to visitors whose browser requests it.', 'mbr-cookie-consent'); ?></strong></p>
            <button type="submit" name="approve" value="0" class="button">
                <?php esc_html_e('Withdraw approval', 'mbr-cookie-consent'); ?>
            </button>
        <?php else : ?>
            <p>
                <label>
                    <input type="checkbox" name="confirm" value="1" required>
                    <?php
                    printf(
                        /* translators: %s: language name. */
                        esc_html__('I have read the %s wording above and take responsibility for publishing it on my site.', 'mbr-cookie-consent'),
                        esc_html($meta['name'])
                    );
                    ?>
                </label>
            </p>
            <button type="submit" name="approve" value="1" class="button button-primary">
                <?php esc_html_e('Approve this language', 'mbr-cookie-consent'); ?>
            </button>
        <?php endif; ?>
    </form>

<?php else : ?>

    <h1><?php esc_html_e('Community Translations', 'mbr-cookie-consent'); ?></h1>

    <p class="description" style="max-width: 46em;">
        <?php esc_html_e('The banner can be shown in the visitor\u2019s own language. The translations below are contributed by the community rather than produced by professional translators, so each one has to be read and approved by you before it is shown to anybody. Until then, visitors see the wording you wrote.', 'mbr-cookie-consent'); ?>
    </p>

    <?php if (!$enabled) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php esc_html_e('Automatic translation is currently switched off, so nothing here is being served. Turn it on under Settings once you have approved at least one language.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    <?php elseif (empty($approved)) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php esc_html_e('Automatic translation is switched on, but no language has been approved yet — so every visitor still sees your own wording. Review a language below to start serving it.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="margin-top: 1em; max-width: 60em;">
        <thead>
            <tr>
                <th><?php esc_html_e('Language', 'mbr-cookie-consent'); ?></th>
                <th><?php esc_html_e('Coverage', 'mbr-cookie-consent'); ?></th>
                <th><?php esc_html_e('Status', 'mbr-cookie-consent'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($index['languages'] as $code => $meta) :
            $is_ok    = in_array($code, $approved, true);
            $covered  = (int) $meta['covered'];
            $complete = ($covered >= $total);
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($meta['name']); ?></strong>
                    <code style="margin-left: .5em;"><?php echo esc_html($code); ?></code>
                    <?php if ('rtl' === $meta['direction']) : ?>
                        <span class="dashicons dashicons-editor-rtl" title="<?php esc_attr_e('Right to left', 'mbr-cookie-consent'); ?>"></span>
                    <?php endif; ?>
                </td>
                <td<?php echo $complete ? '' : ' style="color:#b26200;"'; ?>>
                    <?php echo esc_html($covered . ' / ' . $total); ?>
                </td>
                <td>
                    <?php if ($is_ok) : ?>
                        <span style="color:#1d7a44; font-weight:600;"><?php esc_html_e('Approved — live', 'mbr-cookie-consent'); ?></span>
                    <?php else : ?>
                        <span style="opacity:.7;"><?php esc_html_e('Not reviewed', 'mbr-cookie-consent'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="button button-small"
                       href="<?php echo esc_url(admin_url('admin.php?page=mbr-cookie-consent-translations&review=' . rawurlencode($code))); ?>">
                        <?php echo $is_ok
                            ? esc_html__('View', 'mbr-cookie-consent')
                            : esc_html__('Review', 'mbr-cookie-consent'); ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="description" style="margin-top: 1.5em; max-width: 46em;">
        <?php
        printf(
            /* translators: %s: repository URL. */
            esc_html__('Spotted a mistake, or want to contribute a language? The translation files are plain JSON, one per language, at %s.', 'mbr-cookie-consent'),
            '<a href="https://github.com/HarbourBob/MBR-Cookie-Consent" target="_blank" rel="noopener">github.com/HarbourBob/MBR-Cookie-Consent</a>'
        );
        ?>
    </p>

<?php endif; ?>

</div>
