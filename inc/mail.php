<?php
/**
 * Bright Path — outbound mail.
 *
 * OWNER: Main.
 *
 * Measured on this box on 2026-09-18, and the reason this file exists:
 *
 *   postfix/postqueue: warning: Mail system is down --
 *     Connect to the Postfix showq service: Connection refused
 *
 * Postfix is not running. Seventeen messages were sitting in the queue, the
 * oldest from the previous day, none delivered. The domain has no MX, no SPF,
 * no DKIM and no DMARC record. And `wp_mail()` returned **true** throughout,
 * because PHP's mail() hands the message to /usr/sbin/sendmail, sendmail writes
 * it to a queue, and the queue never drains.
 *
 * So the default state of this site was a function that cannot fail reporting
 * success for mail that would never arrive. For a contact form that is bad. For
 * a form that refers a participant to a disability services provider it is the
 * worst failure available: the sender is told it worked.
 *
 * This file does two things and nothing else:
 *
 *   1. routes wp_mail through SMTP when the constants in wp-config.php are set;
 *   2. makes wp_mail FAIL LOUDLY when they are not, instead of returning true
 *      into a dead queue.
 *
 * It is deliberately not a plugin: the user's decision (BUILD-CONTRACT.md 0.4)
 * is no plugins, and PHPMailer is already in core — this is thirty lines of
 * configuration, not a dependency.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is outbound mail configured well enough to be trusted?
 *
 * Returns true only when every constant needed to reach a real relay is present
 * and non-empty. "Defined but empty" is the state wp-config.php ships in, and it
 * must read as NOT configured — a half-set constant is how this fails quietly.
 */
function brightpath_mail_configured() {
	foreach ( array( 'BRIGHTPATH_SMTP_HOST', 'BRIGHTPATH_SMTP_USER', 'BRIGHTPATH_SMTP_PASS', 'BRIGHTPATH_SMTP_FROM' ) as $c ) {
		if ( ! defined( $c ) || '' === trim( (string) constant( $c ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Point PHPMailer at the relay.
 */
function brightpath_mail_smtp( $phpmailer ) {
	/*
	 * Set BEFORE the configured-guard, deliberately.
	 *
	 * PHPMailer's default Debugoutput is `echo`, and the sink for `echo` here is
	 * the HTTP response body a visitor is looking at. If anyone ever raises
	 * SMTPDebug to diagnose the relay, the SMTP conversation -- recipients and
	 * headers of a 44-field health-data intake -- prints onto the page.
	 *
	 * The first version of this line sat inside the branch below, which meant it
	 * applied only once SMTP was configured -- i.e. never, in the state the site
	 * is actually in, and not at all for the default mail() path. A hardening
	 * default has to be unconditional or it is decoration.
	 */
	$phpmailer->Debugoutput = 'error_log';

	if ( ! brightpath_mail_configured() ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = BRIGHTPATH_SMTP_HOST;
	$phpmailer->Port       = defined( 'BRIGHTPATH_SMTP_PORT' ) ? (int) BRIGHTPATH_SMTP_PORT : 587;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = BRIGHTPATH_SMTP_USER;
	$phpmailer->Password   = BRIGHTPATH_SMTP_PASS;
	$phpmailer->SMTPSecure = 465 === (int) $phpmailer->Port ? 'ssl' : 'tls';
	$phpmailer->SMTPAutoTLS = true;

	// The envelope sender must be a domain the relay is authorised for, or the
	// message is authenticated as nobody and lands in spam even though it sent.
	$phpmailer->setFrom(
		BRIGHTPATH_SMTP_FROM,
		defined( 'BRIGHTPATH_SMTP_FROM_NAME' ) ? BRIGHTPATH_SMTP_FROM_NAME : get_bloginfo( 'name' ),
		false
	);

	$phpmailer->Timeout = 15;
}
add_action( 'phpmailer_init', 'brightpath_mail_smtp' );

/**
 * Refuse to pretend.
 *
 * With no relay configured, PHP mail() would hand the message to a dead queue
 * and wp_mail() would return true. Short-circuiting to a WP_Error instead means
 * every caller learns the truth — and the forms layer is written to treat a
 * failed notification as a visible problem rather than as success.
 *
 * The submission is still STORED. Refusing to send is not refusing to record;
 * losing the enquiry as well would be a second failure on top of the first.
 */
function brightpath_mail_refuse_when_unconfigured( $return, $atts ) {
	if ( brightpath_mail_configured() ) {
		return $return;
	}

	$to = is_array( $atts['to'] ?? '' ) ? implode( ', ', $atts['to'] ) : (string) ( $atts['to'] ?? '' );

	error_log( sprintf(
		'[brightpath] wp_mail refused: no SMTP relay configured. to=%s subject=%s',
		$to,
		(string) ( $atts['subject'] ?? '' )
	) );

	do_action( 'wp_mail_failed', new WP_Error(
		'brightpath_mail_unconfigured',
		'No SMTP relay is configured, and this server\'s local mail queue does not deliver. The message was not sent.',
		$atts
	) );

	return false;
}
add_filter( 'pre_wp_mail', 'brightpath_mail_refuse_when_unconfigured', 10, 2 );

/**
 * Say so in the admin, where someone can act on it.
 *
 * A refusal nobody can see looks exactly like a feature nobody built.
 */
function brightpath_mail_admin_notice() {
	if ( brightpath_mail_configured() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>Bright Path: outbound email is not configured.</strong> ';
	echo 'This server\'s mail queue is not delivering, so form notifications would be silently lost. ';
	echo 'Set <code>BRIGHTPATH_SMTP_HOST</code>, <code>_USER</code>, <code>_PASS</code> and <code>_FROM</code> in <code>wp-config.php</code>. ';
	echo 'Submissions are still being stored and can be read in the admin.</p></div>';
}
add_action( 'admin_notices', 'brightpath_mail_admin_notice' );
