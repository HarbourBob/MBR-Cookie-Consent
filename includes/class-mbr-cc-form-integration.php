<?php
/**
 * Form Builder Integration
 *
 * Blocks form submission on sites where cookie consent has not been granted.
 * Supports Contact Form 7, WPForms, Gravity Forms, and Elementor Forms.
 *
 * When a visitor tries to submit a form without having accepted cookies, the
 * submission is rejected with a clear message. The form builder's own error
 * handling displays the message inline — no custom UI needed.
 *
 * @package MBR_Cookie_Consent
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks form submission on supported form builders until consent is granted.
 */
class MBR_CC_Form_Integration {

	/**
	 * Single instance.
	 *
	 * @var MBR_CC_Form_Integration|null
	 */
	private static $instance = null;

	/**
	 * The error message shown when consent is missing.
	 *
	 * @var string
	 */
	private $error_message;

	/**
	 * Get instance.
	 *
	 * @return MBR_CC_Form_Integration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the error message, applying the translated default lazily.
	 *
	 * The default is not set in the constructor because __() must not be
	 * called before the 'init' action (WordPress 6.7+).
	 *
	 * @return string
	 */
	private function get_error_message() {
		if ( empty( $this->error_message ) ) {
			$this->error_message = __( 'Please accept cookies before submitting this form.', 'mbr-cookie-consent' );
		}
		return $this->error_message;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Only hook when the feature is enabled.
		if ( ! get_option( 'mbr_cc_form_integration_enabled', false ) ) {
			return;
		}

		$this->error_message = get_option(
			'mbr_cc_form_integration_message',
			'' // Default set lazily in get_error_message() to avoid early translation loading.
		);

		// ── Contact Form 7 ────────────────────────────────────────────────
		// wpcf7_spam is the only reliable hard-block in CF7. Returning true
		// from this filter marks the submission as spam and stops all processing.
		// We also hook wpcf7_before_send_mail as a belt-and-braces fallback.
		add_filter( 'wpcf7_spam', array( $this, 'block_cf7_spam' ), 10, 1 );
		add_filter( 'wpcf7_before_send_mail', array( $this, 'block_cf7_mail' ), 10, 3 );

		// ── WPForms ───────────────────────────────────────────────────────
		// wpforms_process_before sets the error, but we also need to hook
		// wpcf7_process to abort before the entry is saved/emailed.
		add_action( 'wpforms_process', array( $this, 'block_wpforms' ), 1, 3 );

		// ── Gravity Forms ─────────────────────────────────────────────────
		add_filter( 'gform_validation', array( $this, 'block_gravity_forms' ) );

		// ── Elementor Forms ───────────────────────────────────────────────
		add_action( 'elementor_pro/forms/validation', array( $this, 'block_elementor_forms' ), 10, 2 );
		// Enqueue the modal JS/CSS on the frontend when Elementor Forms is active.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_elementor_modal_assets' ) );
	}

	// ── Consent check ─────────────────────────────────────────────────────

	/**
	 * Check whether the visitor has granted the required consent.
	 *
	 * We check the cookie server-side on form submission rather than relying
	 * on JavaScript, so the block cannot be bypassed by disabling JS.
	 *
	 * @return bool True if consent has been granted.
	 */
	private function has_consent() {
		$required_category = get_option( 'mbr_cc_form_required_category', 'necessary' );

		if ( ! isset( $_COOKIE['mbr_cc_consent'] ) ) {
			return false;
		}

		$raw = wp_unslash( $_COOKIE['mbr_cc_consent'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated for type/length below and only ever json_decode()'d, never stored or output.

		// Client-controlled value; reject oversized input before decoding.
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 2048 ) {
			return false;
		}

		$consent = json_decode( $raw, true );

		if ( ! is_array( $consent ) ) {
			return false;
		}

		// 'all' flag covers every category.
		if ( ! empty( $consent['all'] ) ) {
			return true;
		}

		// 'necessary' only — always pass (necessary cookies are always set).
		if ( 'necessary' === $required_category ) {
			return true;
		}

		return ! empty( $consent[ $required_category ] );
	}

	// ── Contact Form 7 ────────────────────────────────────────────────────

	/**
	 * Mark the CF7 submission as spam, which is the only reliable way to
	 * hard-stop CF7 before it sends mail or fires integrations.
	 *
	 * @param  bool $spam Whether CF7 has already flagged this submission as spam.
	 * @return bool
	 */
	public function block_cf7_spam( $spam ) {
		if ( $spam ) {
			return $spam; // Already flagged — don't interfere.
		}
		if ( $this->has_consent() ) {
			return false;
		}
		// Set a human-readable response so CF7's AJAX returns our message.
		$submission = WPCF7_Submission::get_instance();
		if ( $submission ) {
			$submission->set_response( $this->get_error_message() );
		}
		return true; // Mark as spam = hard stop.
	}

	/**
	 * Belt-and-braces fallback: abort just before CF7 sends the mail.
	 * Returning false from wpcf7_before_send_mail prevents sending.
	 *
	 * @param  WPCF7_ContactForm $cf7         The submitted form.
	 * @param  bool              $abort       Passed by reference; set true to abort sending.
	 * @param  WPCF7_Submission  $submission  The current submission.
	 * @return void
	 */
	public function block_cf7_mail( $cf7, &$abort, $submission ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $submission is required by CF7's wpcf7_before_send_mail filter signature.
		if ( $this->has_consent() ) {
			return;
		}
		$abort = true;
	}

	// ── WPForms ───────────────────────────────────────────────────────────

	/**
	 * Block WPForms by injecting an error via the process action.
	 * Setting errors['header'] AND calling process->errors stops WPForms
	 * from saving the entry or sending notifications.
	 *
	 * @param array $fields     Submitted field values.
	 * @param array $entry      Raw entry data.
	 * @param array $form_data  Form configuration.
	 */
	public function block_wpforms( $fields, $entry, $form_data ) {
		if ( $this->has_consent() ) {
			return;
		}
		$form_id = $form_data['id'];
		wpforms()->process->errors[ $form_id ]['header'] = $this->get_error_message();
		// Prevent entry save and notifications by marking process as errored.
		add_filter( 'wpforms_entry_save', '__return_false' );
		add_filter( 'wpforms_entry_email_send', '__return_false' );
	}

	// ── Gravity Forms ─────────────────────────────────────────────────────

	/**
	 * Block Gravity Forms by marking validation as failed.
	 * Setting is_valid = false is the correct hard-stop for GF.
	 *
	 * @param  array $validation_result Gravity Forms validation result.
	 * @return array
	 */
	public function block_gravity_forms( $validation_result ) {
		if ( $this->has_consent() ) {
			return $validation_result;
		}

		$validation_result['is_valid'] = false;
		$form                          = $validation_result['form'];

		// Attach the error message to the first non-hidden field so GF
		// renders it inline. GF will not submit when is_valid is false.
		foreach ( $form['fields'] as &$field ) {
			if ( 'hidden' !== $field->type ) {
				$field->failed_validation  = true;
				$field->validation_message = $this->get_error_message();
				break;
			}
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	// ── Elementor Forms ───────────────────────────────────────────────────

	/**
	 * Block Elementor Forms at the validation stage.
	 *
	 * Rather than attaching errors to form fields (which creates duplicate
	 * inline messages), we set a single flag that the Elementor AJAX response
	 * will carry. Our frontend JS reads this flag and shows a clean modal
	 * instead of letting Elementor render any error UI.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       The submitted form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Elementor's AJAX response handler.
	 */
	public function block_elementor_forms( $record, $ajax_handler ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- both are required by Elementor Pro's validation hook signature; the AJAX response is sent directly instead of using $ajax_handler.
		if ( $this->has_consent() ) {
			return;
		}

		// Hard-stop: intercept before Elementor can send anything.
		// We send our own JSON response directly and exit — this means
		// Elementor never runs its own response logic, so no field errors,
		// no footer message, nothing. Our JS detects the flag and shows
		// the modal cleanly.
		wp_send_json(
			array(
				'success' => false,
				'data'    => array(
					'mbr_cc_consent_required' => true,
					'mbr_cc_message'          => $this->get_error_message(),
				),
			)
		);
	}

	/**
	 * Enqueue the consent modal CSS and JS for Elementor Forms pages.
	 * Only loaded when Elementor Pro is active.
	 */
	public function enqueue_elementor_modal_assets() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}
		wp_enqueue_style(
			'mbr-cc-form-modal',
			MBR_CC_PLUGIN_URL . 'assets/css/form-modal.css',
			array( 'mbr-cc-banner' ),
			MBR_CC_VERSION
		);
		wp_enqueue_script(
			'mbr-cc-form-modal',
			MBR_CC_PLUGIN_URL . 'assets/js/form-modal.js',
			array( 'jquery', 'mbr-cc-banner' ),
			MBR_CC_VERSION,
			true
		);
		// Pass the blocked message to JS so the DOM sentinel can match it
		// exactly, regardless of language or admin customisation.
		wp_localize_script(
			'mbr-cc-form-modal',
			'mbrCcFormModal',
			array(
				'message' => $this->get_error_message(),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	// ── Static helpers used by admin UI ───────────────────────────────────

	/**
	 * Whether form-blocking is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( 'mbr_cc_form_integration_enabled', false );
	}

	/**
	 * List of detectable form plugins and whether they are active.
	 *
	 * @return array  [ 'name' => string, 'active' => bool ]
	 */
	public static function detect_form_plugins() {
		return array(
			array(
				'name'   => 'Contact Form 7',
				'active' => defined( 'WPCF7_VERSION' ),
			),
			array(
				'name'   => 'WPForms',
				'active' => defined( 'WPFORMS_VERSION' ),
			),
			array(
				'name'   => 'Gravity Forms',
				'active' => class_exists( 'GFForms' ),
			),
			array(
				'name'   => 'Elementor Forms',
				'active' => defined( 'ELEMENTOR_PRO_VERSION' ),
			),
		);
	}
}
