<?php
/**
 * Bright Path — form rendering, validation, storage and notification.
 *
 * OWNER: the forms track. Paired with assets/css/forms.css, assets/js/forms.js
 * and patterns/form-*.php. Listed in functions.php's loader, which SKIPS a
 * shard that does not exist — so deleting this file plus forms.css is a
 * complete rollback of the layer with no edit to any Main-owned file. That
 * property is why this lives in the theme rather than a project plugin
 * (BUILD-CONTRACT.md 0.4 forbids plugins in any case).
 *
 * WHAT DECISION 8 OBLIGES, and where each obligation is discharged here:
 *
 *   custom table, not wp_posts       -> brightpath_forms_install(), section 1
 *   encryption at rest, key off-DB   -> section 2, key from a file outside the
 *                                       docroot named by BRIGHTPATH_FORMS_KEY_FILE
 *   explicit capability              -> brightpath_read_submissions, section 1.
 *                                       NOT manage_options: every admin plugin
 *                                       assumes it has that one.
 *   uploads outside the web root     -> section 6, served by a capability-checked
 *                                       handler, never by a URL under wp-content
 *   retention with a real job        -> section 7, DISABLED by default, --dry-run
 *
 * THE RULE THAT GOVERNS THE SENSITIVE FIELDS: refuse, never degrade.
 * If the encryption key cannot be read, a form carrying DOB / NDIS number /
 * communication needs is REFUSED and no row is written. It does not fall back
 * to storing them in the clear. Silently degrading to plaintext is how this
 * class of data leaks, and the failure would be invisible.
 *
 * @package brightpath
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bumped whenever the table changes shape. dbDelta runs only when the stored
 * version differs, so this is not a per-request cost.
 */
define( 'BRIGHTPATH_FORMS_DB_VERSION', '1' );
define( 'BRIGHTPATH_FORMS_CAP', 'brightpath_read_submissions' );
define( 'BRIGHTPATH_FORMS_ROLE', 'brightpath_intake' );

/* =====================================================================
 * 1. Schema, capability and role
 * ===================================================================== */

/**
 * The submissions table name. A dedicated table is the whole point: a
 * submission in wp_posts is reachable by any post query, is exposed to REST by
 * default, and lands in every export. Nothing registers this table with REST.
 */
function brightpath_forms_table() {
	global $wpdb;
	return $wpdb->prefix . 'brightpath_submissions';
}

function brightpath_forms_files_table() {
	global $wpdb;
	return $wpdb->prefix . 'brightpath_submission_files';
}

/**
 * Create or migrate the tables.
 *
 * Columns that are deliberately PLAINTEXT: name, email, phone, subject. They
 * are contact details, they must be searchable and sortable in the admin list,
 * and they are the positive control for the "plaintext is not in the database"
 * check — a grep of the dump MUST find the submitter's name, or the grep is
 * broken rather than the encryption working.
 *
 * Columns that are deliberately ENCRYPTED: payload_enc only. DOB, NDIS number
 * and the free-text needs field live inside it and nowhere else.
 */
function brightpath_forms_install() {
	global $wpdb;

	if ( get_option( 'brightpath_forms_db_version' ) === BRIGHTPATH_FORMS_DB_VERSION ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$t       = brightpath_forms_table();
	$ft      = brightpath_forms_files_table();

	dbDelta( "CREATE TABLE {$t} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		form_slug VARCHAR(32) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		status VARCHAR(20) NOT NULL DEFAULT 'new',
		submitter_name VARCHAR(200) NOT NULL DEFAULT '',
		submitter_email VARCHAR(200) NOT NULL DEFAULT '',
		submitter_phone VARCHAR(64) NOT NULL DEFAULT '',
		subject VARCHAR(255) NOT NULL DEFAULT '',
		payload_plain LONGTEXT NULL,
		payload_enc LONGBLOB NULL,
		enc_key_id VARCHAR(16) NOT NULL DEFAULT '',
		source_url VARCHAR(255) NOT NULL DEFAULT '',
		ip_hash CHAR(64) NOT NULL DEFAULT '',
		notified TINYINT NOT NULL DEFAULT 0,
		notify_error VARCHAR(255) NOT NULL DEFAULT '',
		retention_until DATE NULL DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY form_slug (form_slug),
		KEY created_at (created_at),
		KEY status (status),
		KEY ip_hash (ip_hash),
		KEY retention_until (retention_until)
	) {$charset};" );

	dbDelta( "CREATE TABLE {$ft} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		submission_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		field VARCHAR(32) NOT NULL DEFAULT '',
		original_name VARCHAR(255) NOT NULL DEFAULT '',
		stored_path VARCHAR(255) NOT NULL DEFAULT '',
		mime VARCHAR(100) NOT NULL DEFAULT '',
		bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY submission_id (submission_id)
	) {$charset};" );

	update_option( 'brightpath_forms_db_version', BRIGHTPATH_FORMS_DB_VERSION, false );
}

/**
 * The capability and the role that carries it.
 *
 * Administrator gets the capability so the site owner is not locked out of
 * their own data. Editor deliberately does NOT — an Editor is a content role,
 * and the check that catches "I used manage_options after all" is an Editor
 * being refused.
 */
function brightpath_forms_install_roles() {
	if ( ! get_role( BRIGHTPATH_FORMS_ROLE ) ) {
		add_role(
			BRIGHTPATH_FORMS_ROLE,
			'Intake',
			array(
				'read'                    => true,
				BRIGHTPATH_FORMS_CAP      => true,
			)
		);
	}

	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( BRIGHTPATH_FORMS_CAP ) ) {
		$admin->add_cap( BRIGHTPATH_FORMS_CAP );
	}
}

/**
 * Both run on every load, both are cheap no-ops once satisfied. There is no
 * activation hook available to a theme shard that is also correct after a
 * deploy that changes the schema.
 */
function brightpath_forms_maybe_install() {
	brightpath_forms_install();
	brightpath_forms_install_roles();
}
add_action( 'init', 'brightpath_forms_maybe_install', 5 );

/* =====================================================================
 * 2. Encryption at rest — and refusing rather than degrading
 * ===================================================================== */

/**
 * Read the key from the file named by BRIGHTPATH_FORMS_KEY_FILE.
 *
 * The key is NOT in the database and NOT derived from AUTH_KEY. A key in
 * wp_options travels in every database dump; a key derived from wp-config.php
 * travels in every backup that takes the file alongside the dump. Neither
 * defends against the threats decision 8 actually names.
 *
 * Returns null on any failure. Every caller must treat null as "refuse", never
 * as "store it in the clear".
 */
function brightpath_forms_key() {
	static $cache = false;

	if ( false !== $cache ) {
		return $cache;
	}

	$cache = null;

	/*
	 * ext-sodium is not guaranteed on a host we did not choose. Check it HERE,
	 * at the one place every encrypt/decrypt path passes through, because the
	 * comparison below names SODIUM_CRYPTO_SECRETBOX_KEYBYTES and an undefined
	 * constant is a fatal Error in PHP 8, not a warning.
	 */
	if ( ! extension_loaded( 'sodium' ) || ! defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' ) ) {
		return null;
	}

	if ( ! defined( 'BRIGHTPATH_FORMS_KEY_FILE' ) ) {
		return $cache;
	}

	$path = (string) BRIGHTPATH_FORMS_KEY_FILE;

	if ( '' === $path || ! is_readable( $path ) ) {
		return $cache;
	}

	$raw = file_get_contents( $path );

	// A short or empty key file is a corrupted key, not a usable one. Accepting
	// it would produce ciphertext nobody can decrypt later.
	if ( ! is_string( $raw ) || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen( $raw ) ) {
		return $cache;
	}

	$cache = $raw;

	return $cache;
}

function brightpath_forms_key_id() {
	return defined( 'BRIGHTPATH_FORMS_KEY_ID' ) ? (string) BRIGHTPATH_FORMS_KEY_ID : '';
}

/**
 * Is encryption available right now? The single question every sensitive path
 * asks before it does anything else.
 */
function brightpath_forms_can_encrypt() {
	return function_exists( 'sodium_crypto_secretbox' ) && null !== brightpath_forms_key();
}

/**
 * Encrypt an array. Returns null if the key is unavailable — the caller
 * refuses; it does not fall through.
 *
 * Format: "bp1:" . base64( nonce || ciphertext ). The version prefix exists so
 * a future key rotation can tell rows apart instead of guessing.
 */
function brightpath_forms_encrypt( array $data ) {
	$key = brightpath_forms_key();

	if ( null === $key ) {
		return null;
	}

	$json = wp_json_encode( $data );

	if ( false === $json ) {
		return null;
	}

	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

	return 'bp1:' . base64_encode( $nonce . sodium_crypto_secretbox( $json, $nonce, $key ) );
}

/**
 * Decrypt. Returns null on a missing key, a tampered payload, or a payload
 * written under a key we no longer hold. A null here is surfaced in the admin
 * as "cannot be decrypted", never as an empty field — an empty field reads as
 * "they did not answer", which is a different and misleading fact.
 */
function brightpath_forms_decrypt( $blob ) {
	$key = brightpath_forms_key();

	if ( null === $key || ! is_string( $blob ) || 0 !== strpos( $blob, 'bp1:' ) ) {
		return null;
	}

	$raw = base64_decode( substr( $blob, 4 ), true );

	if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return null;
	}

	$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

	if ( false === $plain ) {
		return null;
	}

	$data = json_decode( $plain, true );

	return is_array( $data ) ? $data : null;
}

/**
 * Say so in the admin when the key is missing.
 *
 * Without this, the only symptom of a missing key is referral forms refusing
 * submissions — visible to the public and to nobody who can fix it.
 */
function brightpath_forms_key_admin_notice() {
	if ( brightpath_forms_can_encrypt() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>Bright Path: the submissions encryption key is unreadable.</strong> ';
	echo 'Forms that collect date of birth, NDIS number or communication needs are being <em>refused</em> rather than stored unencrypted. ';
	echo 'Check <code>BRIGHTPATH_FORMS_KEY_FILE</code> and the file it names. ';
	echo 'Existing encrypted submissions cannot be read until the key is restored; a copy is in the private <code>backup/</code> directory.</p></div>';
}
add_action( 'admin_notices', 'brightpath_forms_key_admin_notice' );

/* =====================================================================
 * 3. The field schema — ONE source of truth for render AND validate
 * ===================================================================== */

/**
 * Option lists.
 *
 * VALUES are in code. LABELS are filterable and option-overridable, because
 * BUILD-CONTRACT.md 9 opens "labels, option lists, success/error copy and the
 * recipient address" to the client while locking the name attributes and the
 * required-field set.
 *
 * The split matters: a client who renames "Transport Assistance" must not
 * thereby change the stored value, or every historical row's label silently
 * stops matching and reports break. Values are data; labels are copy.
 */
function brightpath_forms_options( $list ) {
	$defaults = array(
		'c-topic' => array(
			'new'       => 'Starting support',
			'existing'  => 'I am already a participant',
			'referral'  => 'Making a referral',
			'sil'       => 'SIL or accommodation',
			'careers'   => 'Working at BrightPath',
			'feedback'  => 'Feedback or a complaint',
			'other'     => 'Something else',
		),
		'req-support' => array(
			'daily-living'                 => 'Daily Living',
			'community-participation'      => 'Community Participation',
			'supported-independent-living' => 'Supported Independent Living',
			'short-term-accommodation'     => 'Short Term Accommodation',
			'support-coordination'         => 'Support Coordination',
			'transport-support'            => 'Transport Assistance',
			'life-skills'                  => 'Life Skills',
			'in-home-support'              => 'In-Home Support',
			'not-sure'                     => 'I am not sure yet',
		),
		'req-time' => array(
			'morning'   => 'Morning (8.30am – 12pm)',
			'afternoon' => 'Afternoon (12pm – 5pm)',
			'text'      => 'Please text me first',
		),
		'ref-role' => array(
			'coordinator'  => 'Support coordinator',
			'plan-manager' => 'Plan manager',
			'family'       => 'Family member or guardian',
			'health'       => 'Health professional',
			'self'         => 'I am the participant',
			'other'        => 'Other',
		),
		'supports' => array(
			'daily-living'                 => 'Daily Living',
			'community-participation'      => 'Community Participation',
			'supported-independent-living' => 'Supported Independent Living',
			'short-term-accommodation'     => 'Short Term Accommodation',
			'support-coordination'         => 'Support Coordination',
			'transport-support'            => 'Transport Assistance',
			'life-skills'                  => 'Life Skills',
			'in-home-support'              => 'In-Home Support',
		),
		'funding' => array(
			'self'    => 'Self-managed',
			'plan'    => 'Plan-managed',
			'ndia'    => 'NDIA-managed',
			'pending' => 'Plan not yet approved',
		),
		'start-flex' => array(
			'urgent'   => 'Urgent — support is needed within a week',
			'firm'     => 'Fixed date, cannot move',
			'flexible' => 'Flexible by a week or two',
			'planning' => 'Planning ahead, no rush',
		),
		'start-hours' => array(
			'under-10' => 'Under 10 hours',
			'10-20'    => '10 to 20 hours',
			'20-40'    => '20 to 40 hours',
			'40-plus'  => 'More than 40 hours',
			'sil'      => 'SIL — 24/7 arrangement',
		),
		'consent-who' => array(
			'participant' => 'The participant',
			'guardian'    => 'Legally appointed guardian',
			'nominee'     => 'NDIS nominee',
			'parent'      => 'Parent of a participant under 18',
		),
		'referral-consent' => array(
			'participant' => 'The participant has consented to this referral',
			'info'        => 'I have permission to share the information in this form',
			'privacy'     => 'I have read how BrightPath handles personal information',
		),
		'job-avail' => array(
			'weekdays' => 'Weekdays only',
			'weekends' => 'Weekends only',
			'both'     => 'Weekdays and weekends',
			'nights'   => 'Including overnight shifts',
		),
		'enq-role' => array(
			'participant' => 'A participant',
			'family'      => 'Family member or guardian',
			'coordinator' => 'Support coordinator',
			'other'       => 'Someone else',
		),
		'fb-type' => array(
			'compliment' => 'Compliment',
			'complaint'  => 'Complaint',
			'general'    => 'General feedback',
		),
		'fb-anon' => array(
			'named'     => 'With my name',
			'anonymous' => 'Anonymously',
		),
		'fb-response' => array(
			'yes' => 'Yes, please contact me',
			'no'  => 'No response needed',
		),
	);

	$options = isset( $defaults[ $list ] ) ? $defaults[ $list ] : array();

	// Option-backed override: labels only, and only for keys that already
	// exist. A stored override cannot invent a value, which is what keeps the
	// override from widening what validation accepts.
	$stored = get_option( 'brightpath_forms_labels', array() );

	if ( is_array( $stored ) && ! empty( $stored[ $list ] ) && is_array( $stored[ $list ] ) ) {
		foreach ( $stored[ $list ] as $value => $label ) {
			if ( isset( $options[ $value ] ) && is_string( $label ) && '' !== trim( $label ) ) {
				$options[ $value ] = $label;
			}
		}
	}

	/**
	 * Filter an option list's LABELS.
	 *
	 * Same rule as the option: a filter that adds a key it invented is ignored
	 * by validation, because validation reads the value set from this same
	 * function — so an invented key would render and then be rejected. The
	 * intersect below makes that explicit instead of surprising.
	 */
	$filtered = apply_filters( 'brightpath_forms_options', $options, $list );

	if ( is_array( $filtered ) ) {
		$options = array_intersect_key( $filtered, $options ) + $options;
	}

	return $options;
}

/**
 * Per-option descriptions shown under radio/checkbox choices (design copy).
 *
 * @param string $list Option list key.
 * @return array<string,string>
 */
function brightpath_forms_option_hints( $list ) {
	$hints = array(
		'funding' => array(
			'self' => 'The participant or their nominee pays invoices directly.',
			'plan' => 'A plan manager pays invoices on their behalf.',
			'ndia' => 'The NDIA pays providers directly.',
		),
		'fb-type' => array(
			'compliment' => 'Something or someone that went well.',
			'complaint'  => 'Something went wrong and you want it addressed.',
			'general'    => 'A suggestion or an observation.',
		),
		'fb-anon' => array(
			'named'     => 'We can follow up with you and tell you what we did.',
			'anonymous' => 'We will still investigate, but we cannot come back to you.',
		),
		'referral-consent' => array(
			'privacy' => 'See our privacy policy for the detail.',
		),
	);
	return $hints[ $list ] ?? array();
}

/**
 * The six forms.
 *
 * This array is the ONLY description of a field. The renderer builds the
 * markup from it and the validator checks against it, so a field cannot be
 * rendered-but-unvalidated or validated-but-unrendered — the two cannot drift
 * because there is only one of them.
 *
 * Per-field keys:
 *   type       text|email|tel|date|textarea|select|checkbox|radio|checkboxes|file
 *   label      the visible label; also what the staff email names
 *   required   BUILD-CONTRACT.md 9 LOCKS this set. It is not client-editable.
 *   options    a key into brightpath_forms_options()
 *   enc        true  -> stored only inside payload_enc, never in a plain column
 *   map        name|email|phone -> also copied to the plaintext indexed column
 *   depends    array(field, value) -> only meaningful when that answer is given
 *   step       referral only; the locked 7-step sequence
 *
 * The `name` attributes are carried VERBATIM from the prototype because 9
 * locks them: changing one silently orphans every historical row's payload key.
 */
function brightpath_forms_schema() {
	static $schema = null;

	if ( null !== $schema ) {
		return $schema;
	}

	$schema = array(

		'contact' => array(
			'title'   => 'Contact',
			'subject' => 'Website contact enquiry',
			'fields'  => array(
				'c-name'    => array( 'type' => 'text', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'map' => 'name' ),
				'c-email'   => array( 'type' => 'email', 'label' => 'Email', 'required' => true, 'autocomplete' => 'email', 'map' => 'email' ),
				'c-phone'   => array( 'type' => 'tel', 'label' => 'Phone', 'required' => false, 'autocomplete' => 'tel', 'map' => 'phone', 'placeholder' => '0412 345 678' ),
				'c-topic'   => array( 'type' => 'select', 'label' => 'What is this about?', 'required' => true, 'options' => 'c-topic' ),
				'c-message' => array( 'type' => 'textarea', 'label' => 'Your message', 'required' => true, 'rows' => 6 ),
				'consent'   => array( 'type' => 'checkbox', 'label' => 'I am happy for BrightPath Supports to reply to this enquiry.', 'required' => true ),
			),
		),

		'request-support' => array(
			'title'   => 'Request support',
			'subject' => 'Support request',
			'fields'  => array(
				'req-name'    => array( 'type' => 'text', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'map' => 'name' ),
				'req-email'   => array( 'type' => 'email', 'label' => 'Email', 'required' => true, 'autocomplete' => 'email', 'map' => 'email' ),
				'req-phone'   => array( 'type' => 'tel', 'label' => 'Phone', 'required' => true, 'autocomplete' => 'tel', 'map' => 'phone', 'placeholder' => '0412 345 678' ),
				'req-suburb'  => array( 'type' => 'text', 'label' => 'Suburb', 'required' => true, 'autocomplete' => 'address-level2', 'placeholder' => 'Parramatta' ),
				'req-support' => array( 'type' => 'select', 'label' => 'Support needed', 'required' => true, 'options' => 'req-support', 'hint' => 'Not sure? Choose the last option and we will work it out together.' ),
				'req-time'    => array( 'type' => 'select', 'label' => 'Preferred contact time', 'required' => false, 'options' => 'req-time', 'placeholder' => 'Any time during business hours' ),
				'req-message' => array( 'type' => 'textarea', 'label' => 'Tell us a bit about what you need', 'required' => false, 'rows' => 4 ),
				'consent'     => array( 'type' => 'checkbox', 'label' => 'I am happy for BrightPath Supports to contact me about this request.', 'required' => true ),
			),
		),

		'sil-enquiry' => array(
			'title'   => 'SIL property enquiry',
			'subject' => 'SIL vacancy enquiry',
			'fields'  => array(
				'enq-name'    => array( 'type' => 'text', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'map' => 'name' ),
				'enq-email'   => array( 'type' => 'email', 'label' => 'Email', 'required' => true, 'autocomplete' => 'email', 'map' => 'email' ),
				'enq-phone'   => array( 'type' => 'tel', 'label' => 'Phone', 'required' => true, 'autocomplete' => 'tel', 'map' => 'phone' ),
				'enq-role'    => array( 'type' => 'select', 'label' => 'You are', 'required' => true, 'options' => 'enq-role' ),
				'enq-message' => array( 'type' => 'textarea', 'label' => 'Your message', 'required' => false, 'rows' => 4 ),
				'enq-property' => array( 'type' => 'hidden', 'label' => 'Property', 'default' => 'post_title' ),
				'consent'     => array( 'type' => 'checkbox', 'label' => 'I agree to Bright Path storing these details so they can respond to my enquiry.', 'required' => true ),
			),
		),

		'job-application' => array(
			'title'   => 'Job application',
			'subject' => 'Job application',
			'fields'  => array(
				'job-name'  => array( 'type' => 'text', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name', 'map' => 'name' ),
				'job-email' => array( 'type' => 'email', 'label' => 'Email', 'required' => true, 'autocomplete' => 'email', 'map' => 'email' ),
				'job-phone' => array( 'type' => 'tel', 'label' => 'Phone', 'required' => true, 'autocomplete' => 'tel', 'map' => 'phone' ),
				'job-avail' => array( 'type' => 'select', 'label' => 'Your availability', 'required' => true, 'options' => 'job-avail' ),
				'job-about' => array( 'type' => 'textarea', 'label' => 'Why this work?', 'required' => false, 'rows' => 4, 'placeholder' => 'A few sentences is plenty. We are interested in you, not a template.' ),
				'job-role'  => array( 'type' => 'hidden', 'label' => 'Role applied for', 'default' => 'post_title' ),
				'job-cv'    => array(
					'type'     => 'file',
					'label'    => 'Your resume',
					'required' => false,
					'hint'     => 'PDF or Word, up to 10 MB.',
					'accept'   => array( 'pdf', 'doc', 'docx' ),
				),
			),
		),

		'feedback' => array(
			'title'   => 'Feedback',
			'subject' => 'Website feedback',
			'fields'  => array(
				'fb-type'     => array( 'type' => 'radio', 'label' => 'What kind of feedback is this?', 'required' => true, 'options' => 'fb-type' ),
				'fb-message'  => array( 'type' => 'textarea', 'label' => 'Tell us what happened', 'required' => true, 'rows' => 6, 'hint' => 'Include dates, locations and names where you can. It helps us look into it properly.' ),
				'fb-anon'     => array( 'type' => 'radio', 'label' => 'How would you like to give this feedback?', 'required' => true, 'options' => 'fb-anon' ),
				// The three identity fields are only meaningful when fb-anon is
				// "named". The renderer marks them data-depends and forms.js
				// DISABLES them when anonymous, so the browser does not post
				// them at all; the handler drops them regardless. Two
				// independent mechanisms, because the JS one can be turned off.
				'fb-name'     => array( 'type' => 'text', 'label' => 'Your name', 'required' => false, 'autocomplete' => 'name', 'map' => 'name', 'depends' => array( 'fb-anon', 'named' ) ),
				'fb-email'    => array( 'type' => 'email', 'label' => 'Email', 'required' => false, 'autocomplete' => 'email', 'map' => 'email', 'depends' => array( 'fb-anon', 'named' ) ),
				'fb-phone'    => array( 'type' => 'tel', 'label' => 'Phone', 'required' => false, 'autocomplete' => 'tel', 'map' => 'phone', 'placeholder' => '0412 345 678', 'depends' => array( 'fb-anon', 'named' ) ),
				'fb-response' => array( 'type' => 'radio', 'label' => 'Would you like a response?', 'required' => false, 'options' => 'fb-response', 'depends' => array( 'fb-anon', 'named' ) ),
			),
		),

		'referral' => array(
			'title'   => 'Refer a participant',
			'subject' => 'Participant referral',
			// A 44-field clinical intake is the one form on this site where an
			// accidental navigation is catastrophic, so forms.js keeps a
			// SESSION-scoped draft of it. Opt-in per form, and only this one is
			// opted in: the other five are short enough that a draft would be
			// more privacy surface than help. Fields marked `enc` are excluded
			// from the draft -- see the note on brightpath_forms_render_field().
			'draft'   => true,
			// BUILD-CONTRACT.md 9 locks this sequence.
			'steps'   => array(
				'referrer'    => 'Referrer details',
				'participant' => 'Participant details',
				'supports'    => 'Support required',
				'funding'     => 'Funding',
				'start'       => 'Start date',
				'additional'  => 'Additional information',
				'consent'     => 'Consent',
			),
			'fields'  => array(
				'ref-name'  => array( 'step' => 'referrer', 'type' => 'text', 'label' => 'Your full name', 'required' => true, 'autocomplete' => 'name', 'map' => 'name' ),
				'ref-org'   => array( 'step' => 'referrer', 'type' => 'text', 'label' => 'Organisation', 'required' => false, 'autocomplete' => 'organization', 'hint' => 'Leave blank if referring as a family member.' ),
				'ref-role'  => array( 'step' => 'referrer', 'type' => 'select', 'label' => 'Your relationship to the participant', 'required' => true, 'options' => 'ref-role' ),
				'ref-email' => array( 'step' => 'referrer', 'type' => 'email', 'label' => 'Email', 'required' => true, 'autocomplete' => 'email', 'map' => 'email' ),
				'ref-phone' => array( 'step' => 'referrer', 'type' => 'tel', 'label' => 'Phone', 'required' => true, 'autocomplete' => 'tel', 'map' => 'phone', 'placeholder' => '0412 345 678' ),

				'p-first'    => array( 'step' => 'participant', 'type' => 'text', 'label' => 'Participant first name', 'required' => true ),
				'p-last'     => array( 'step' => 'participant', 'type' => 'text', 'label' => 'Participant surname', 'required' => true ),
				// The three fields decision 8 names by name.
				'p-dob'      => array( 'step' => 'participant', 'type' => 'date', 'label' => 'Date of birth', 'required' => true, 'enc' => true ),
				'p-ndis'     => array( 'step' => 'participant', 'type' => 'text', 'label' => 'NDIS number', 'required' => false, 'enc' => true, 'hint' => 'Nine digits. Leave blank if the plan is not yet approved.' ),
				'p-suburb'   => array( 'step' => 'participant', 'type' => 'text', 'label' => 'Suburb', 'required' => true ),
				'p-postcode' => array( 'step' => 'participant', 'type' => 'text', 'label' => 'Postcode', 'required' => true, 'pattern' => '\d{4}' ),
				'p-comm'     => array( 'step' => 'participant', 'type' => 'textarea', 'label' => 'Communication needs', 'required' => true, 'rows' => 4, 'enc' => true, 'hint' => 'Preferred language, interpreter or Auslan requirements, communication aids.' ),

				'supports'   => array( 'step' => 'supports', 'type' => 'checkboxes', 'label' => 'Which supports are you referring for? Choose as many as apply.', 'required' => false, 'options' => 'supports' ),
				'sup-detail' => array( 'step' => 'supports', 'type' => 'textarea', 'label' => 'Tell us about the support needed', 'required' => true, 'rows' => 4, 'enc' => true, 'hint' => 'Daily routine, hours required, any manual handling or complex health needs.' ),

				'funding'     => array( 'step' => 'funding', 'type' => 'radio', 'label' => 'How is the participant\'s plan managed?', 'required' => false, 'options' => 'funding' ),
				'plan-start'  => array( 'step' => 'funding', 'type' => 'date', 'label' => 'Plan start date', 'required' => false ),
				'plan-end'    => array( 'step' => 'funding', 'type' => 'date', 'label' => 'Plan end date', 'required' => false ),
				'plan-budget' => array( 'step' => 'funding', 'type' => 'textarea', 'label' => 'Relevant budget or line items', 'required' => false, 'rows' => 3, 'enc' => true, 'hint' => 'If known. For example: Core — Assistance with Daily Life, $48,000.' ),

				'start-when'  => array( 'step' => 'start', 'type' => 'date', 'label' => 'Preferred start date', 'required' => true ),
				'start-flex'  => array( 'step' => 'start', 'type' => 'select', 'label' => 'How flexible is this date?', 'required' => true, 'options' => 'start-flex' ),
				'start-hours' => array( 'step' => 'start', 'type' => 'select', 'label' => 'Indicative hours per week', 'required' => false, 'options' => 'start-hours' ),

				'add-risks' => array( 'step' => 'additional', 'type' => 'textarea', 'label' => 'Known risks or behaviours of concern', 'required' => false, 'rows' => 4, 'enc' => true, 'hint' => 'This helps us match the right workers and put the right supports in place. It never affects whether we accept a referral.' ),
				'add-notes' => array( 'step' => 'additional', 'type' => 'textarea', 'label' => 'Anything else', 'required' => false, 'rows' => 4, 'enc' => true ),
				'ref-docs'  => array(
					'step'     => 'additional',
					'type'     => 'file',
					'label'    => 'Supporting documents',
					'required' => false,
					'multiple' => true,
					'hint'     => 'NDIS plan, assessments, behaviour support plan or referral letters. PDF, Word or image files up to 10 MB each.',
					'accept'   => array( 'pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg' ),
				),

				'consent'     => array( 'step' => 'consent', 'type' => 'checkboxes', 'label' => 'Consent', 'required' => true, 'options' => 'referral-consent' ),
				'consent-who' => array( 'step' => 'consent', 'type' => 'select', 'label' => 'Who provided consent', 'required' => true, 'options' => 'consent-who' ),
			),
		),
	);

	/**
	 * Filter the schema. Deliberately AFTER the static cache is set, so a
	 * filter cannot be added halfway through a request and produce a renderer
	 * and a validator that disagree — the check that matters here is that both
	 * read the same array in the same request.
	 */
	$schema = apply_filters( 'brightpath_forms_schema', $schema );

	return $schema;
}

/**
 * One form, or null. Every entry point goes through this, so an unknown slug
 * fails in exactly one place.
 */
function brightpath_forms_get( $slug ) {
	$schema = brightpath_forms_schema();

	return isset( $schema[ $slug ] ) ? $schema[ $slug ] : null;
}

/* =====================================================================
 * 4. Rendering
 * ===================================================================== */

/**
 * The HMAC-signed timing floor.
 *
 * A hidden field carrying issue-time plus an HMAC over it. On submit the
 * handler recomputes the HMAC and rejects a token that was issued less than
 * BRIGHTPATH_FORMS_MIN_SECONDS ago, or more than two hours ago.
 *
 * Signed rather than stored so it needs no table and no session, and signed
 * rather than a bare timestamp so a bot cannot simply post an older number.
 * This is one of the three spam measures decision-recorded as sufficient; the
 * escalation if it is not is at the server edge, NOT a CAPTCHA. A CAPTCHA on a
 * disability services referral form is an accessibility barrier in front of the
 * people the site exists to serve.
 */
define( 'BRIGHTPATH_FORMS_MIN_SECONDS', 3 );
define( 'BRIGHTPATH_FORMS_MAX_SECONDS', 7200 );

function brightpath_forms_timing_token() {
	$issued = time();
	$sig    = hash_hmac( 'sha256', (string) $issued, wp_salt( 'nonce' ) );

	return $issued . '.' . $sig;
}

function brightpath_forms_timing_check( $token ) {
	if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
		return 'missing';
	}

	list( $issued, $sig ) = explode( '.', $token, 2 );

	if ( ! hash_equals( hash_hmac( 'sha256', $issued, wp_salt( 'nonce' ) ), $sig ) ) {
		return 'forged';
	}

	$age = time() - (int) $issued;

	if ( $age < BRIGHTPATH_FORMS_MIN_SECONDS ) {
		return 'too-fast';
	}

	if ( $age > BRIGHTPATH_FORMS_MAX_SECONDS ) {
		return 'stale';
	}

	return 'ok';
}

/**
 * Escape and echo one field's markup.
 *
 * Markup and class names follow the prototype so forms.css is a port rather
 * than a rewrite. Every control gets:
 *   - a real <label for>, never a placeholder standing in for one
 *   - aria-describedby pointing at its hint AND its error node
 *   - aria-invalid, set server-side when that field failed
 * The error <p> is rendered EMPTY rather than omitted, so forms.js and the
 * server write into the same node and screen readers announce a change in a
 * node that already existed.
 */
/**
 * Pairs of fields the design sets side by side in a .form__row--2 (markup
 * only). Key opens the row, value closes it.
 */
function brightpath_forms_row_pairs() {
	return array(
		'c-name' => 'c-email', 'c-phone' => 'c-topic',
		'req-name' => 'req-email', 'req-phone' => 'req-suburb',
		'ref-org' => 'ref-role', 'ref-email' => 'ref-phone',
		'p-first' => 'p-last', 'p-dob' => 'p-ndis', 'p-suburb' => 'p-postcode',
		'plan-start' => 'plan-end',
		'fb-name' => 'fb-email',
	);
}

/**
 * Render a field, wrapping design pairs in a two-column row.
 */
function brightpath_forms_render_field_in_row( $name, $field, $form_slug, $values, $errors ) {
	$pairs = brightpath_forms_row_pairs();
	if ( isset( $pairs[ $name ] ) ) {
		echo '<div class="form__row form__row--2">';
	}
	brightpath_forms_render_field( $name, $field, $form_slug, $values, $errors );
	if ( in_array( $name, $pairs, true ) ) {
		echo '</div>';
	}
}

function brightpath_forms_render_field( $name, $field, $form_slug, $values, $errors ) {
	$type     = isset( $field['type'] ) ? $field['type'] : 'text';
	$id       = 'f-' . $form_slug . '-' . $name;
	$err_id   = $id . '-error';
	$hint_id  = $id . '-hint';
	$required = ! empty( $field['required'] );
	$label    = isset( $field['label'] ) ? $field['label'] : $name;
	$value    = isset( $values[ $name ] ) ? $values[ $name ] : '';
	$error    = isset( $errors[ $name ] ) ? $errors[ $name ] : '';
	$hint     = isset( $field['hint'] ) ? $field['hint'] : '';

	$describedby = trim( ( $hint ? $hint_id . ' ' : '' ) . $err_id );

	if ( 'hidden' === $type ) {
		printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( is_string( $value ) ? $value : '' ) );
		return;
	}

	/*
	 * Decision 8 marks these fields encrypted at rest and keeps them out of the
	 * notification email. The same posture has to reach the browser, because a
	 * session draft in sessionStorage is plaintext the encryption never sees.
	 *
	 * The marker is emitted from the schema so forms.js can EXCLUDE these
	 * fields by reading the DOM, rather than carrying its own copy of the list.
	 * A hand-written list in the script would go stale the first time a field
	 * gains `enc`, and it would go stale silently -- the draft would simply
	 * start persisting health data with nothing to notice it.
	 */
	$enc_attr = ! empty( $field['enc'] ) ? ' data-field-enc="1"' : '';

	$depends_attr = '';
	if ( ! empty( $field['depends'] ) ) {
		$depends_attr = sprintf(
			' data-depends="%s" data-depends-value="%s"',
			esc_attr( $field['depends'][0] ),
			esc_attr( $field['depends'][1] )
		);
	}

	echo '<div class="field' . ( $error ? ' field--invalid' : '' ) . '"' . $enc_attr . $depends_attr . '>';

	// ---- grouped controls: radio, checkboxes ----
	if ( 'radio' === $type || 'checkboxes' === $type ) {
		$options  = brightpath_forms_options( $field['options'] );
		$selected = (array) ( is_array( $value ) ? $value : ( '' === $value ? array() : array( $value ) ) );

		echo '<fieldset class="field__group"' . ( $error ? ' aria-invalid="true"' : '' ) . ' aria-describedby="' . esc_attr( $describedby ) . '">';
		echo '<legend class="field__label">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="field__required" aria-hidden="true">*</span>';
		}
		echo '</legend>';

		if ( $hint ) {
			echo '<p class="field__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
		}

		// Design layout of the option list (markup only; names unchanged).
		$layouts = array( 'fb-type' => 'grid-3', 'supports' => 'grid-2', 'fb-response' => 'inline' );
		$layout  = isset( $layouts[ $name ] ) ? $layouts[ $name ] : 'stack';
		$card    = 'inline' !== $layout ? ' check--card' : '';
		echo '<div class="field__options field__options--' . esc_attr( $layout ) . '">';

		$i     = 0;
		$ohint = brightpath_forms_option_hints( $field['options'] );
		foreach ( $options as $ov => $ol ) {
			if ( isset( $ohint[ $ov ] ) ) {
				$ol_html = '<span class="check__title">' . esc_html( $ol ) . '</span><span class="check__hint">' . esc_html( $ohint[ $ov ] ) . '</span>';
			} else {
				$ol_html = esc_html( $ol );
			}
			$oid = $id . '-' . sanitize_html_class( (string) $ov );
			printf(
				'<label class="check%s" for="%s"><input class="check__input" type="%s" id="%s" name="%s" value="%s"%s%s><span class="check__mark" aria-hidden="true"></span><span class="check__text">%s</span></label>',
				$card,
				esc_attr( $oid ),
				'radio' === $type ? 'radio' : 'checkbox',
				esc_attr( $oid ),
				esc_attr( 'checkboxes' === $type ? $name . '[]' : $name ),
				esc_attr( (string) $ov ),
				in_array( (string) $ov, array_map( 'strval', $selected ), true ) ? ' checked' : '',
				// forms.js reads `required` off a group's first control; without it
				// a required radio group was never checked client-side.
				( $required && 'radio' === $type ) ? ' required' : '',
				$ol_html
			);
			$i++;
		}

		echo '</div></fieldset>';
		brightpath_forms_render_error( $err_id, $error );
		echo '</div>';
		return;
	}

	// ---- single checkbox (consent) ----
	if ( 'checkbox' === $type ) {
		printf(
			'<label class="check" for="%s"><input class="check__input" type="checkbox" id="%s" name="%s" value="1"%s%s%s aria-describedby="%s"><span class="check__mark" aria-hidden="true"></span><span class="check__text">%s%s</span></label>',
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( $name ),
			$value ? ' checked' : '',
			$required ? ' required' : '',
			$error ? ' aria-invalid="true"' : '',
			esc_attr( $describedby ),
			esc_html( $label ),
			$required ? ' <span class="field__required" aria-hidden="true">*</span>' : ''
		);
		brightpath_forms_render_error( $err_id, $error );
		echo '</div>';
		return;
	}

	// ---- everything with a plain <label for> ----
	printf(
		'<label class="field__label" for="%s">%s%s</label>',
		esc_attr( $id ),
		esc_html( $label ),
		$required ? ' <span class="field__required" aria-hidden="true">*</span>' : ''
	);

	if ( $hint ) {
		echo '<p class="field__hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $hint ) . '</p>';
	}

	$common = sprintf(
		' id="%s" name="%s" aria-describedby="%s"%s%s',
		esc_attr( $id ),
		esc_attr( 'file' === $type && ! empty( $field['multiple'] ) ? $name . '[]' : $name ),
		esc_attr( $describedby ),
		$required ? ' required' : '',
		$error ? ' aria-invalid="true"' : ''
	);

	if ( ! empty( $field['autocomplete'] ) ) {
		$common .= ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '"';
	}

	switch ( $type ) {
		case 'textarea':
			printf(
				'<textarea class="textarea" rows="%d"%s placeholder="%s">%s</textarea>',
				(int) ( isset( $field['rows'] ) ? $field['rows'] : 4 ),
				$common,
				esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
				esc_textarea( is_string( $value ) ? $value : '' )
			);
			break;

		case 'select':
			echo '<select class="select"' . $common . '>';
			printf( '<option value="">%s</option>', esc_html( isset( $field['placeholder'] ) ? $field['placeholder'] : 'Please choose' ) );
			foreach ( brightpath_forms_options( $field['options'] ) as $ov => $ol ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( (string) $ov ),
					(string) $value === (string) $ov ? ' selected' : '',
					esc_html( $ol )
				);
			}
			echo '</select>';
			break;

		case 'file':
			printf(
				'<label class="upload" data-upload><input class="upload__input" type="file"%s%s accept="%s"><span class="upload__title">%s</span><span class="upload__hint">%s</span><span class="upload__list" data-upload-list></span></label>',
				$common,
				! empty( $field['multiple'] ) ? ' multiple' : '',
				esc_attr( '.' . implode( ',.', (array) $field['accept'] ) ),
				esc_html( ! empty( $field['multiple'] ) ? 'Choose files or drag them here' : 'Choose a file or drag it here' ),
				esc_html( sprintf( '%s, up to %d MB each.', strtoupper( implode( ', ', (array) $field['accept'] ) ), brightpath_forms_max_upload_mb() ) )
			);
			break;

		default:
			printf(
				'<input class="input" type="%s" value="%s"%s%s%s>',
				esc_attr( $type ),
				esc_attr( is_string( $value ) ? $value : '' ),
				$common,
				! empty( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '',
				! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''
			);
	}

	brightpath_forms_render_error( $err_id, $error );
	echo '</div>';
}

/**
 * The error node. Always rendered, empty when there is no error, because
 * forms.js writes into the same node — a node that appears and disappears is
 * announced inconsistently by screen readers.
 */
function brightpath_forms_render_error( $err_id, $error ) {
	printf(
		'<p class="field__error" id="%s"><svg class="field__error-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>%s</p>',
		esc_attr( $err_id ),
		$error ? '<span class="field__error-text">' . esc_html( $error ) . '</span>' : '<span class="field__error-text"></span>'
	);
}

/**
 * Render a whole form.
 *
 * The error summary is rendered FIRST, is a real <ul> of links to the failing
 * fields, and carries tabindex="-1" so it can receive focus. forms.js and the
 * server-side path both focus it. BUILD-CONTRACT's check is that
 * document.activeElement IS the summary — "a summary exists" and "the summary
 * has focus" are different facts and only the second one helps anyone.
 */
function brightpath_forms_render( $slug, $atts = array() ) {
	$form = brightpath_forms_get( $slug );

	if ( ! $form ) {
		return '';
	}

	$state   = brightpath_forms_last_state( $slug );
	$values  = isset( $state['values'] ) ? $state['values'] : array();
	$errors  = isset( $state['errors'] ) ? $state['errors'] : array();
	$sent    = ! empty( $state['sent'] );
	$fatal   = isset( $state['fatal'] ) ? $state['fatal'] : '';
	$form_id = 'bp-form-' . $slug;

	ob_start();

	echo '<div class="bp-form-wrap" id="' . esc_attr( $form_id ) . '-wrap">';

	// ---- success ----
	if ( $sent ) {
		// The slug is on the attribute because this is the ONLY signal forms.js
		// gets that a submission succeeded: the form element is gone from the
		// page by now, so there is nothing else left to read the slug from.
		echo '<div class="form-success" role="status" tabindex="-1" data-form-success="' . esc_attr( $slug ) . '">';
		echo '<h2 class="form-success__title">' . esc_html( brightpath_forms_copy( $slug, 'success_title' ) ) . '</h2>';
		echo '<p class="form-success__body">' . esc_html( brightpath_forms_copy( $slug, 'success_body' ) ) . '</p>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	echo '<form class="form" id="' . esc_attr( $form_id ) . '" method="post" data-bp-form="' . esc_attr( $slug ) . '"';
	if ( ! empty( $form['draft'] ) ) {
		echo ' data-draft-scope="' . esc_attr( $slug ) . '"';
	}
	echo ' action="' . esc_url( brightpath_forms_action_url() ) . '" novalidate';
	if ( brightpath_forms_has_upload( $slug ) ) {
		echo ' enctype="multipart/form-data"';
	}
	echo '>';

	// ---- a refusal the visitor must see ----
	if ( $fatal ) {
		echo '<div class="form-summary form-summary--fatal" role="alert" tabindex="-1" data-form-summary>';
		echo '<p class="form-summary__title">' . esc_html( $fatal ) . '</p>';
		echo '</div>';
	} elseif ( $errors ) {
		printf(
			'<div class="form-summary" role="alert" tabindex="-1" data-form-summary><p class="form-summary__title">%s</p><ul class="form-summary__list">',
			esc_html( sprintf(
				_n( 'There is %d thing to fix before this can be sent.', 'There are %d things to fix before this can be sent.', count( $errors ), 'brightpath' ),
				count( $errors )
			) )
		);
		foreach ( $errors as $fname => $message ) {
			printf(
				'<li><a href="#f-%s-%s">%s</a></li>',
				esc_attr( $slug ),
				esc_attr( $fname ),
				esc_html( $message )
			);
		}
		echo '</ul></div>';
	}

	// ---- the three spam measures ----
	wp_nonce_field( 'brightpath_form_' . $slug, 'bp_nonce' );
	printf( '<input type="hidden" name="bp_form" value="%s">', esc_attr( $slug ) );
	printf( '<input type="hidden" name="bp_t" value="%s">', esc_attr( brightpath_forms_timing_token() ) );
	printf( '<input type="hidden" name="bp_redirect" value="%s">', esc_attr( brightpath_forms_current_url() ) );

	// The honeypot. Hidden from sight AND from assistive technology, and never
	// hidden with `display:none` alone in a way a screen reader would read out.
	// aria-hidden + tabindex -1 + autocomplete off: a human never reaches it.
	echo '<div class="bp-hp" aria-hidden="true">';
	echo '<label for="' . esc_attr( $form_id ) . '-website">Leave this field empty</label>';
	echo '<input type="text" id="' . esc_attr( $form_id ) . '-website" name="bp_website" value="" tabindex="-1" autocomplete="off">';
	echo '</div>';

	// ---- the fields ----
	if ( ! empty( $form['steps'] ) ) {
		brightpath_forms_render_steps( $slug, $form, $values, $errors );
	} else {
		foreach ( $form['fields'] as $fname => $field ) {
			if ( 'hidden' === ( $field['type'] ?? '' ) && ! isset( $values[ $fname ] ) ) {
				// A hidden field on a form that lives inside a record template
				// takes its value from the record, so the same pattern works on
				// every job and every SIL property without being edited. The
				// marker's data-bp-* attribute still wins if one is set.
				if ( isset( $atts[ $fname ] ) ) {
					$values[ $fname ] = $atts[ $fname ];
				} elseif ( 'post_title' === ( $field['default'] ?? '' ) && is_singular() ) {
					$values[ $fname ] = get_the_title();
				}
			}
			brightpath_forms_render_field_in_row( $fname, $field, $slug, $values, $errors );
		}
	}

	printf(
		'<div class="form__actions"><button class="btn btn--accent btn--lg" type="submit">%s</button></div>',
		esc_html( brightpath_forms_copy( $slug, 'submit' ) )
	);

	echo '</form></div>';

	return ob_get_clean();
}

/**
 * The referral's locked 7-step sequence, plus the stepper.
 *
 * Every step is rendered into the DOM and the steps are navigated by
 * forms.js. With JavaScript off every step is visible and the form is one long
 * page that still submits and still validates — the no-JS path is a working
 * form, not a broken one.
 */
function brightpath_forms_render_steps( $slug, $form, $values, $errors ) {
	$steps = $form['steps'];
	$n     = count( $steps );

	echo '<div class="stepper" data-stepper>';
	echo '<p class="stepper__progress-text" data-stepper-text>Step 1 of ' . (int) $n . '</p>';
	echo '<div class="stepper__bar"><span class="stepper__fill" data-stepper-fill></span></div>';
	echo '<ol class="stepper__dots">';
	$i = 0;
	foreach ( $steps as $key => $title ) {
		$i++;
		printf(
			'<li class="stepper__dot%s" data-stepper-dot="%s"><span class="stepper__dot-mark">%d</span><span class="stepper__dot-label">%s</span></li>',
			1 === $i ? ' is-current' : '',
			esc_attr( $key ),
			$i,
			esc_html( $title )
		);
	}
	echo '</ol></div>';

	$i = 0;
	foreach ( $steps as $key => $title ) {
		$i++;
		printf(
			'<section class="form-step" data-step="%s" data-step-index="%d" aria-label="%s">',
			esc_attr( $key ),
			$i,
			esc_attr( sprintf( 'Step %d of %d: %s', $i, $n, $title ) )
		);
		echo '<div class="form-step__head"><h2>' . esc_html( $title ) . '</h2></div>';

		foreach ( $form['fields'] as $fname => $field ) {
			if ( ( $field['step'] ?? '' ) === $key ) {
				brightpath_forms_render_field_in_row( $fname, $field, $slug, $values, $errors );
			}
		}

		echo '<div class="form-step__nav">';
		if ( $i > 1 ) {
			echo '<button class="btn btn--ghost" type="button" data-step-prev>Back</button>';
		}
		echo '<div class="form-step__nav-right">';
		if ( $i < $n ) {
			echo '<button class="btn btn--primary" type="button" data-step-next>Continue</button>';
		}
		echo '</div></div></section>';
	}
}

function brightpath_forms_has_upload( $slug ) {
	$form = brightpath_forms_get( $slug );

	foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
		if ( 'file' === ( $field['type'] ?? '' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Success / submit / error copy. Client-editable per BUILD-CONTRACT.md 9,
 * through the same option + filter pair as the option labels.
 */
function brightpath_forms_copy( $slug, $key ) {
	$defaults = array(
		'submit'        => 'Send',
		'success_title' => 'Thank you — we have your message.',
		'success_body'  => 'A member of the team will be in touch within one business day.',
	);

	$per_form = array(
		'contact' => array(
			'submit'        => 'Send message',
		),
		'request-support' => array(
			'submit'        => 'Send request',
			'success_title' => 'Thank you — your request is with us.',
			'success_body'  => 'Someone from the intake team will call you within one business day to talk about what you need.',
		),
		'referral' => array(
			'submit'        => 'Submit referral',
			'success_title' => 'Referral received.',
			'success_body'  => 'Our intake team will review the referral and contact you within one business day. If this is urgent, please call us.',
		),
		'job-application' => array(
			'submit'        => 'Send application',
			'success_title' => 'Application received.',
			'success_body'  => 'Thank you for your interest in working with Bright Path. We read every application and will be in touch.',
		),
		'feedback' => array(
			'submit'        => 'Submit feedback',
			'success_title' => 'Thank you for telling us.',
			'success_body'  => 'Feedback and complaints go straight to our quality team. If you asked for a response we will contact you.',
		),
		'sil-enquiry' => array(
			'submit'        => 'Send enquiry',
			'success_title' => 'Enquiry received.',
			'success_body'  => 'We will be in touch about this property within one business day.',
		),
	);

	$value = $per_form[ $slug ][ $key ] ?? $defaults[ $key ] ?? '';

	$stored = get_option( 'brightpath_forms_copy', array() );
	if ( is_array( $stored ) && ! empty( $stored[ $slug ][ $key ] ) && is_string( $stored[ $slug ][ $key ] ) ) {
		$value = $stored[ $slug ][ $key ];
	}

	return (string) apply_filters( 'brightpath_forms_copy', $value, $slug, $key );
}

function brightpath_forms_current_url() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

	return esc_url_raw( home_url( $path ) );
}

/**
 * Forms POST to themselves. admin-post.php would work but would put the form's
 * handler behind a URL that looks administrative and is easy to mistake for a
 * protected one; posting to the page keeps the redirect trivially correct.
 */
function brightpath_forms_action_url() {
	return brightpath_forms_current_url();
}

/* =====================================================================
 * 5. Placing a form in a page: the render_block marker
 * ===================================================================== */

/**
 * A pattern cannot call PHP at render time — core/html stores literal markup
 * and `do_blocks` hands it back unchanged. So each form pattern ships a
 * core/html block containing ONLY a marker element, and this filter swaps the
 * marker for the rendered form.
 *
 * Why a data attribute on a div rather than an HTML comment: WordPress escapes
 * `--` to -- inside block-comment attributes (BUILD-CONTRACT.md 1),
 * and an HTML comment carrying `--` is invalid besides. A div with a data
 * attribute round-trips through the editor, the REST API and the serializer
 * untouched, which is the property the marker actually needs.
 *
 * Proven by round-trip on form-contact.php before the other five were written.
 */
function brightpath_forms_render_marker( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/html' ) {
		return $block_content;
	}

	if ( false === strpos( $block_content, 'data-brightpath-form' ) ) {
		return $block_content;
	}

	if ( ! preg_match( '/data-brightpath-form="([a-z0-9-]+)"/', $block_content, $m ) ) {
		return $block_content;
	}

	$slug = $m[1];

	if ( ! brightpath_forms_get( $slug ) ) {
		return $block_content;
	}

	// In the editor the marker stays a marker: rendering a live form into the
	// canvas would put a nonce and a timing token into saved post content.
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $block_content;
	}

	$atts = array();
	if ( preg_match_all( '/data-bp-([a-z-]+)="([^"]*)"/', $block_content, $am, PREG_SET_ORDER ) ) {
		foreach ( $am as $a ) {
			$atts[ $a[1] ] = $a[2];
		}
	}

	return brightpath_forms_render( $slug, $atts );
}
add_filter( 'render_block', 'brightpath_forms_render_marker', 10, 2 );

/**
 * Editor preview of a form (editor-facing only; the front render path above
 * is untouched).
 *
 * GET /brightpath/v1/form-preview/<slug> returns the inner markup of the
 * .bp-form-wrap the front prints, with the per-request spam fields (nonce,
 * referer, timing token, redirect) removed and the action neutralised. The
 * editor block (assets/js/editor-parity-D.js) shows it inert. Requires
 * edit_posts; nothing here is stored or submitted.
 */
function brightpath_forms_preview_html( $slug ) {
	$html = brightpath_forms_render( $slug, array() );

	// Unwrap the .bp-form-wrap: the editor block itself is the wrap.
	$html = preg_replace( '/^<div class="bp-form-wrap"[^>]*>/', '', $html, 1 );
	$html = preg_replace( '/<\/div>\s*$/', '', $html, 1 );

	// Per-request tokens have no place in a preview.
	$html = preg_replace( '/<input type="hidden"[^>]*name="(?:bp_nonce|_wp_http_referer|bp_t|bp_redirect)"[^>]*>/', '', $html );

	// Never submits.
	$html = preg_replace( '/ action="[^"]*"/', ' action="#"', $html, 1 );

	return (string) $html;
}

function brightpath_forms_register_preview_route() {
	register_rest_route(
		'brightpath/v1',
		'/form-preview/(?P<slug>[a-z0-9-]+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => function ( WP_REST_Request $request ) {
				$slug = sanitize_key( $request['slug'] );
				if ( ! brightpath_forms_get( $slug ) ) {
					return new WP_Error( 'bp_form_unknown', 'Unknown form.', array( 'status' => 404 ) );
				}
				return array(
					'slug' => $slug,
					'html' => brightpath_forms_preview_html( $slug ),
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'brightpath_forms_register_preview_route' );

/**
 * Inserter patterns: each form on its own (editor-only, registered for the
 * inserter so a deleted form block can be put back). The pattern content is
 * the same marker the section patterns ship; the front swaps it as usual.
 */
function brightpath_forms_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$labels = array(
		'contact'         => 'Contact form',
		'request-support' => 'Support request form',
		'referral'        => 'Referral form',
		'feedback'        => 'Feedback form',
		'job-application' => 'Job application form',
		'sil-enquiry'     => 'SIL enquiry form',
	);

	foreach ( $labels as $slug => $label ) {
		if ( ! brightpath_forms_get( $slug ) ) {
			continue;
		}

		$marker = '<div data-brightpath-form="' . $slug . '" style="display:block;padding:var(--wp--preset--spacing--40);border:2px dashed var(--wp--custom--color--border-hover);border-radius:var(--wp--custom--radius--md);background-color:var(--wp--custom--color--bg-soft);color:var(--wp--custom--color--text-body);text-align:center"><strong style="display:block;font-weight:700;color:var(--wp--custom--color--text);margin-bottom:var(--wp--preset--spacing--10)">' . $label . '</strong><span style="display:block">Rendered from the field schema when someone views this page.</span></div>';

		register_block_pattern(
			'brightpath/form-block-' . $slug,
			array(
				'title'       => $label . ' (form only)',
				'description' => 'Just the ' . strtolower( $label ) . ', without a heading or aside cards. Rendered from the theme field schema on the live page.',
				'categories'  => array( 'brightpath-forms' ),
				'keywords'    => array( 'form', $slug ),
				'inserter'    => true,
				'content'     => '<!-- wp:html {"metadata":{"name":"' . $label . '"}} -->' . "\n" . $marker . "\n" . '<!-- /wp:html -->',
			)
		);
	}
}
// Removed 2026-09-30: theme patterns/form-*.php already offer the brightpath/form block.
// add_action( 'init', 'brightpath_forms_register_block_patterns', 12 );

/**
 * forms.js. There is no script-shard list the way there is a style-shard list,
 * so this enqueue lives here rather than in Main's inc/assets.php — which keeps
 * the "delete two files to roll the layer back" property intact.
 *
 * Deferred, not async: async can execute before the document has parsed the
 * fields it binds to.
 */
function brightpath_forms_enqueue() {
	$rel  = 'assets/js/forms.js';
	$path = get_theme_file_path( $rel );

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'brightpath-forms',
		get_theme_file_uri( $rel ),
		array(),
		(string) filemtime( $path ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'brightpath_forms_enqueue' );

/* =====================================================================
 * 6. Handling a submission
 * ===================================================================== */

function brightpath_forms_max_upload_mb() {
	return (int) apply_filters( 'brightpath_forms_max_upload_mb', 10 );
}

/**
 * A salted hash of the IP. Used for rate limiting only.
 *
 * The raw IP is never stored. It is personal information under the Privacy Act
 * and it buys nothing here — rate limiting needs to know "the same source
 * again", not "which source". Salted with wp_salt so the hash is not a rainbow
 * table lookup away from the address.
 */
function brightpath_forms_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

	return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
}

/**
 * Per-ip_hash rate limit: BRIGHTPATH_FORMS_RATE submissions per hour, counted
 * from the table itself rather than a transient, so a cache flush does not
 * reset it.
 */
define( 'BRIGHTPATH_FORMS_RATE', 8 );

function brightpath_forms_rate_exceeded( $ip_hash ) {
	global $wpdb;

	$t = brightpath_forms_table();

	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$t} WHERE ip_hash = %s AND created_at > %s",
		$ip_hash,
		gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
	) );

	return $count >= (int) apply_filters( 'brightpath_forms_rate', BRIGHTPATH_FORMS_RATE );
}

/**
 * Per-request state, handed from the handler to the renderer. Not a session and
 * not a transient: the handler runs on the same request that renders, or
 * redirects with a query flag on success (post/redirect/get, so a refresh does
 * not resubmit).
 */
function brightpath_forms_last_state( $slug, $set = null ) {
	static $state = array();

	if ( null !== $set ) {
		$state[ $slug ] = $set;
		return $set;
	}

	if ( isset( $state[ $slug ] ) ) {
		return $state[ $slug ];
	}

	// Success arrives back as a redirect, identified by a signed flag so the
	// success message cannot be produced by typing a URL.
	if ( isset( $_GET['bp_sent'], $_GET['bp_sig'] ) ) {
		$sent = sanitize_key( wp_unslash( $_GET['bp_sent'] ) );
		$sig  = sanitize_text_field( wp_unslash( $_GET['bp_sig'] ) );

		if ( $sent === $slug && hash_equals( hash_hmac( 'sha256', $sent, wp_salt( 'nonce' ) ), $sig ) ) {
			return array( 'sent' => true );
		}
	}

	return array();
}

/**
 * Validate the POST against the schema. Returns array(values, errors, enc).
 *
 * Reads brightpath_forms_schema() — the SAME array the renderer read — so a
 * field cannot be validated under different rules than it was rendered under.
 */
function brightpath_forms_validate( $slug, $post ) {
	$form   = brightpath_forms_get( $slug );
	$values = array();
	$errors = array();

	// A dependent field whose condition is not met is DROPPED before anything
	// else looks at it. This is the server half of "anonymous feedback stores
	// no name": forms.js disables the inputs so a browser does not post them,
	// and this drops them if something posts them anyway.
	foreach ( $form['fields'] as $name => $field ) {
		if ( empty( $field['depends'] ) ) {
			continue;
		}

		list( $dep_field, $dep_value ) = $field['depends'];

		if ( ( $post[ $dep_field ] ?? '' ) !== $dep_value ) {
			unset( $post[ $name ] );
		}
	}

	foreach ( $form['fields'] as $name => $field ) {
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$label    = $field['label'] ?? $name;
		$raw      = $post[ $name ] ?? ( 'checkboxes' === $type ? array() : '' );

		// A dependent field that was dropped above is simply absent.
		if ( ! empty( $field['depends'] ) ) {
			list( $dep_field, $dep_value ) = $field['depends'];
			if ( ( $post[ $dep_field ] ?? '' ) !== $dep_value ) {
				continue;
			}
		}

		switch ( $type ) {
			case 'file':
				// Handled separately, after the row exists.
				continue 2;

			case 'checkbox':
				$values[ $name ] = ! empty( $raw );
				if ( $required && empty( $values[ $name ] ) ) {
					$errors[ $name ] = sprintf( 'Please tick "%s" to continue.', $label );
				}
				break;

			case 'checkboxes':
				$allowed = array_keys( brightpath_forms_options( $field['options'] ) );
				$picked  = array_values( array_intersect( array_map( 'strval', (array) $raw ), array_map( 'strval', $allowed ) ) );
				$values[ $name ] = $picked;

				if ( $required && count( $picked ) < count( $allowed ) && 'consent' === $name ) {
					// The referral's three consents are each individually
					// required; "some of them" is not consent.
					$errors[ $name ] = 'Please confirm all three consent statements to continue.';
				} elseif ( $required && ! $picked ) {
					$errors[ $name ] = sprintf( 'Please choose at least one option for "%s".', $label );
				}
				break;

			case 'select':
			case 'radio':
				$allowed = array_keys( brightpath_forms_options( $field['options'] ) );
				$val     = is_string( $raw ) ? trim( $raw ) : '';

				if ( '' !== $val && ! in_array( $val, array_map( 'strval', $allowed ), true ) ) {
					// An unknown value is discarded, not stored. This is what
					// makes the label override safe: a filter can rename an
					// option but cannot widen what is accepted.
					$val = '';
				}

				$values[ $name ] = $val;

				if ( $required && '' === $val ) {
					$errors[ $name ] = sprintf( 'Please choose an option for "%s".', $label );
				}
				break;

			case 'email':
				$val = sanitize_email( is_string( $raw ) ? trim( $raw ) : '' );
				$values[ $name ] = $val;

				if ( $required && '' === $val ) {
					$errors[ $name ] = 'Please enter an email address so we can reply.';
				} elseif ( '' !== $val && ! is_email( $val ) ) {
					$errors[ $name ] = 'That email address does not look right. Please check it.';
				}
				break;

			case 'date':
				$val = is_string( $raw ) ? trim( $raw ) : '';
				if ( '' !== $val && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
					$errors[ $name ] = sprintf( 'Please enter "%s" as a date.', $label );
					$val = '';
				}
				$values[ $name ] = $val;

				if ( $required && '' === $val && ! isset( $errors[ $name ] ) ) {
					$errors[ $name ] = sprintf( 'Please enter "%s".', $label );
				}
				break;

			case 'textarea':
				$val = sanitize_textarea_field( is_string( $raw ) ? $raw : '' );
				$values[ $name ] = $val;

				if ( $required && '' === trim( $val ) ) {
					$errors[ $name ] = sprintf( 'Please tell us about "%s".', $label );
				}
				break;

			case 'hidden':
				$values[ $name ] = sanitize_text_field( is_string( $raw ) ? $raw : '' );
				break;

			default:
				$val = sanitize_text_field( is_string( $raw ) ? trim( $raw ) : '' );

				if ( ! empty( $field['pattern'] ) && '' !== $val && ! preg_match( '/^' . $field['pattern'] . '$/', $val ) ) {
					$errors[ $name ] = sprintf( 'Please check "%s".', $label );
				}

				$values[ $name ] = $val;

				if ( $required && '' === $val ) {
					$errors[ $name ] = sprintf( 'Please enter "%s".', $label );
				}
		}
	}

	return array( $values, $errors );
}

/**
 * Does this form carry any field that MUST be encrypted?
 *
 * The answer decides whether a missing key refuses the submission. The contact
 * form has no encrypted field, so it keeps working when the key is gone; the
 * referral form does, so it refuses. That asymmetry is deliberate: taking the
 * whole site's forms down because of a key problem would be its own outage.
 */
function brightpath_forms_requires_encryption( $slug ) {
	$form = brightpath_forms_get( $slug );

	foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
		if ( ! empty( $field['enc'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The handler. Runs on `template_redirect` so it is before any output and can
 * still redirect.
 */
function brightpath_forms_handle() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['bp_form'] ) ) {
		return;
	}

	$slug = sanitize_key( wp_unslash( $_POST['bp_form'] ) );
	$form = brightpath_forms_get( $slug );

	if ( ! $form ) {
		return;
	}

	// ---- gate 1: nonce ----
	if ( ! isset( $_POST['bp_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['bp_nonce'] ), 'brightpath_form_' . $slug ) ) {
		brightpath_forms_last_state( $slug, array(
			'fatal' => 'This form has expired. Please check your answers and send it again.',
		) );
		return;
	}

	// ---- gate 2: honeypot ----
	// Silently accepted-looking: a bot that is told it failed learns what to
	// change. A human never sees this branch because a human never fills the
	// field. Nothing is stored and nothing is emailed.
	if ( ! empty( $_POST['bp_website'] ) ) {
		brightpath_forms_redirect_sent( $slug );
		return;
	}

	// ---- gate 3: HMAC-signed timing floor ----
	$timing = brightpath_forms_timing_check( isset( $_POST['bp_t'] ) ? wp_unslash( $_POST['bp_t'] ) : '' );

	if ( 'too-fast' === $timing ) {
		brightpath_forms_redirect_sent( $slug );
		return;
	}

	if ( 'ok' !== $timing ) {
		brightpath_forms_last_state( $slug, array(
			'fatal' => 'This form has been open too long to send safely. Please check your answers and send it again.',
		) );
		return;
	}

	// ---- gate 4: per-ip_hash rate limit ----
	$ip_hash = brightpath_forms_ip_hash();

	if ( brightpath_forms_rate_exceeded( $ip_hash ) ) {
		brightpath_forms_last_state( $slug, array(
			'fatal' => 'We have had several submissions from this connection recently. Please wait a little while, or call us on the number in the footer.',
		) );
		return;
	}

	// ---- validate ----
	$post = wp_unslash( $_POST );
	list( $values, $errors ) = brightpath_forms_validate( $slug, $post );

	if ( $errors ) {
		brightpath_forms_last_state( $slug, array( 'values' => $values, 'errors' => $errors ) );
		return;
	}

	// ---- REFUSE, NEVER DEGRADE ----
	// Checked AFTER validation so the visitor is not told "come back later"
	// while their form also has mistakes in it, and BEFORE any write so a
	// refusal leaves no partial row.
	if ( brightpath_forms_requires_encryption( $slug ) && ! brightpath_forms_can_encrypt() ) {
		error_log( sprintf( '[brightpath] form "%s" REFUSED: encryption key unavailable. No row written.', $slug ) );

		brightpath_forms_last_state( $slug, array(
			'values' => $values,
			'fatal'  => 'We cannot accept this form securely right now, so we have not stored it. Please call us on the number in the footer and we will take these details over the phone. This has been reported to the site administrators.',
		) );
		return;
	}

	$id = brightpath_forms_store( $slug, $values, $ip_hash );

	if ( ! $id ) {
		brightpath_forms_last_state( $slug, array(
			'values' => $values,
			'fatal'  => 'Something went wrong saving this form and we have not stored it. Please call us on the number in the footer.',
		) );
		return;
	}

	// Uploads come after the row exists, so a file is never orphaned.
	brightpath_forms_handle_uploads( $slug, $id );

	// The notification is LAST and cannot roll the submission back.
	brightpath_forms_notify( $slug, $id, $values );

	brightpath_forms_redirect_sent( $slug );
}
add_action( 'template_redirect', 'brightpath_forms_handle' );

/**
 * Post/redirect/get with a signed flag, so refreshing the success page does not
 * resubmit and so the success message cannot be conjured by typing a URL.
 */
function brightpath_forms_redirect_sent( $slug ) {
	$to = isset( $_POST['bp_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['bp_redirect'] ) ) : home_url( '/' );

	// Only ever redirect within this site.
	$to = wp_validate_redirect( $to, home_url( '/' ) );

	wp_safe_redirect( add_query_arg( array(
		'bp_sent' => $slug,
		'bp_sig'  => hash_hmac( 'sha256', $slug, wp_salt( 'nonce' ) ),
	), $to ) . '#bp-form-' . $slug . '-wrap' );

	exit;
}

/**
 * Write the row.
 *
 * Fields marked `enc` go into payload_enc and NOWHERE ELSE. Everything else
 * goes into payload_plain, with name/email/phone additionally copied to their
 * indexed columns so the admin list can search and sort without decrypting
 * every row.
 */
function brightpath_forms_store( $slug, $values, $ip_hash ) {
	global $wpdb;

	$form  = brightpath_forms_get( $slug );
	$plain = array();
	$enc   = array();
	$map   = array( 'name' => '', 'email' => '', 'phone' => '' );

	foreach ( $form['fields'] as $name => $field ) {
		if ( ! array_key_exists( $name, $values ) ) {
			continue;
		}

		if ( ! empty( $field['enc'] ) ) {
			$enc[ $name ] = $values[ $name ];
			continue;
		}

		$plain[ $name ] = $values[ $name ];

		if ( ! empty( $field['map'] ) && is_string( $values[ $name ] ) ) {
			$map[ $field['map'] ] = $values[ $name ];
		}
	}

	$payload_enc = null;
	$key_id      = '';

	if ( $enc ) {
		$payload_enc = brightpath_forms_encrypt( $enc );

		// Belt and braces: the handler already refused, but if encryption were
		// to fail HERE the row must not be written with the data in the clear.
		if ( null === $payload_enc ) {
			error_log( '[brightpath] encryption failed at store time; row not written.' );
			return 0;
		}

		$key_id = brightpath_forms_key_id();
	}

	$retention = brightpath_forms_retention_days( $slug );

	$ok = $wpdb->insert(
		brightpath_forms_table(),
		array(
			'form_slug'       => $slug,
			'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			'status'          => 'new',
			'submitter_name'  => mb_substr( $map['name'], 0, 200 ),
			'submitter_email' => mb_substr( $map['email'], 0, 200 ),
			'submitter_phone' => mb_substr( $map['phone'], 0, 64 ),
			'subject'         => mb_substr( (string) ( $form['subject'] ?? $slug ), 0, 255 ),
			'payload_plain'   => wp_json_encode( $plain ),
			'payload_enc'     => $payload_enc,
			'enc_key_id'      => $key_id,
			'source_url'      => mb_substr( brightpath_forms_current_url(), 0, 255 ),
			'ip_hash'         => $ip_hash,
			'notified'        => 0,
			'retention_until' => $retention ? gmdate( 'Y-m-d', time() + $retention * DAY_IN_SECONDS ) : null,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
	);

	return $ok ? (int) $wpdb->insert_id : 0;
}

/* =====================================================================
 * 7. Uploads — outside the web root, served through a capability check
 * ===================================================================== */

/**
 * The private upload directory. Under BRIGHTPATH_PRIVATE_ROOT, which is a
 * sibling of htdocs/ and NOT under it: a CV at a guessable wp-content/uploads
 * URL is public regardless of any WordPress setting, and .htaccess is not a
 * defence on a server whose web server may not read it.
 */
function brightpath_forms_upload_dir() {
	if ( ! defined( 'BRIGHTPATH_PRIVATE_ROOT' ) ) {
		return '';
	}

	return rtrim( BRIGHTPATH_PRIVATE_ROOT, '/' ) . '/uploads';
}

/**
 * Store the uploaded files.
 *
 * Deliberately NOT wp_handle_upload(): that function's entire job is to put a
 * file where the web server will serve it. This writes outside the docroot,
 * with a generated name, and records the path in a table.
 */
function brightpath_forms_handle_uploads( $slug, $submission_id ) {
	global $wpdb;

	$form = brightpath_forms_get( $slug );
	$dir  = brightpath_forms_upload_dir();

	if ( '' === $dir || ! is_dir( $dir ) ) {
		// Say so: otherwise an attached CV vanishes while the visitor is told
		// their application arrived, and nobody can find out why.
		foreach ( $form['fields'] as $name => $field ) {
			if ( 'file' === ( $field['type'] ?? '' ) && ! empty( $_FILES[ $name ]['name'] ) && array_filter( (array) $_FILES[ $name ]['name'] ) ) {
				error_log( sprintf( '[brightpath] upload DROPPED for submission #%d (%s): private upload dir "%s" is missing.', $submission_id, $slug, $dir ) );
				break;
			}
		}
		return;
	}

	$max = brightpath_forms_max_upload_mb() * MB_IN_BYTES;

	foreach ( $form['fields'] as $name => $field ) {
		if ( 'file' !== ( $field['type'] ?? '' ) ) {
			continue;
		}

		$key = ! empty( $field['multiple'] ) ? $name . '_arr' : $name;
		// PHP turns name="ref-docs[]" into $_FILES['ref-docs'] with array members.
		$slot = $_FILES[ $name ] ?? null;

		if ( ! $slot ) {
			continue;
		}

		$items = is_array( $slot['name'] )
			? array_map( null, $slot['name'], $slot['tmp_name'], $slot['size'], $slot['error'] )
			: array( array( $slot['name'], $slot['tmp_name'], $slot['size'], $slot['error'] ) );

		foreach ( $items as $item ) {
			list( $orig, $tmp, $size, $err ) = $item;

			if ( UPLOAD_ERR_OK !== (int) $err || ! $tmp || ! is_uploaded_file( $tmp ) ) {
				continue;
			}

			if ( $size > $max || $size <= 0 ) {
				continue;
			}

			// Extension allow-list from the schema, checked against WordPress's
			// own idea of the real type. An attacker-supplied filename is not
			// evidence of anything.
			$ext = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );

			if ( ! in_array( $ext, (array) $field['accept'], true ) ) {
				continue;
			}

			$check = wp_check_filetype_and_ext( $tmp, $orig );

			if ( empty( $check['type'] ) ) {
				continue;
			}

			// The stored name carries no part of the submitter's filename, so a
			// name cannot traverse, cannot collide, and cannot leak anything by
			// being guessed.
			$stored = sprintf( '%d-%s.%s', $submission_id, wp_generate_password( 20, false, false ), $ext );
			$path   = $dir . '/' . $stored;

			if ( ! move_uploaded_file( $tmp, $path ) ) {
				continue;
			}

			chmod( $path, 0600 );

			$wpdb->insert(
				brightpath_forms_files_table(),
				array(
					'submission_id' => $submission_id,
					'field'         => $name,
					'original_name' => mb_substr( sanitize_file_name( $orig ), 0, 255 ),
					'stored_path'   => $stored,
					'mime'          => $check['type'],
					'bytes'         => (int) $size,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d' )
			);
		}
	}
}

/**
 * Serve a stored file to someone who holds the capability, and to nobody else.
 *
 * Hooked on admin_post (logged in) ONLY — there is deliberately no
 * admin_post_nopriv counterpart, so an unauthenticated request cannot reach
 * this code at all rather than reaching it and being turned away.
 */
function brightpath_forms_serve_file() {
	if ( ! is_user_logged_in() || ! current_user_can( BRIGHTPATH_FORMS_CAP ) ) {
		wp_die( 'You do not have permission to view this file.', 'Forbidden', array( 'response' => 403 ) );
	}

	$id = isset( $_GET['file'] ) ? (int) $_GET['file'] : 0;

	if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'brightpath_file_' . $id ) ) {
		wp_die( 'Invalid request.', 'Forbidden', array( 'response' => 403 ) );
	}

	global $wpdb;
	$ft  = brightpath_forms_files_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ft} WHERE id = %d", $id ) );

	if ( ! $row ) {
		wp_die( 'Not found.', 'Not found', array( 'response' => 404 ) );
	}

	$dir = brightpath_forms_upload_dir();

	// basename() on the stored name, then a realpath containment check. The
	// stored name is generated and cannot traverse, but a containment check
	// that is only correct because of an invariant elsewhere is one refactor
	// from being wrong.
	$path = $dir . '/' . basename( $row->stored_path );
	$real = realpath( $path );

	if ( ! $real || 0 !== strpos( $real, realpath( $dir ) . '/' ) || ! is_readable( $real ) ) {
		wp_die( 'Not found.', 'Not found', array( 'response' => 404 ) );
	}

	nocache_headers();
	header( 'Content-Type: ' . $row->mime );
	header( 'Content-Length: ' . filesize( $real ) );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( $row->original_name ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Security-Policy: default-src \'none\'' );

	readfile( $real );
	exit;
}
add_action( 'admin_post_brightpath_form_file', 'brightpath_forms_serve_file' );

function brightpath_forms_file_url( $file_id ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=brightpath_form_file&file=' . (int) $file_id ),
		'brightpath_file_' . (int) $file_id
	);
}

/* =====================================================================
 * 8. Notification — a failure here is a VISIBLE PROBLEM, not a success
 * ===================================================================== */

/**
 * Where staff notifications go. Client-editable per BUILD-CONTRACT.md 9.
 */
function brightpath_forms_recipient( $slug ) {
	$stored = get_option( 'brightpath_forms_recipients', array() );
	$to     = '';

	if ( is_array( $stored ) ) {
		$to = $stored[ $slug ] ?? $stored['default'] ?? '';
	}

	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}

	return (string) apply_filters( 'brightpath_forms_recipient', $to, $slug );
}

/**
 * The staff email carries NO ENCRYPTED FIELD.
 *
 * This is a rule, not a convenience. Email is transported and stored in places
 * this project does not control — a mail provider, a mailbox, a phone, a
 * backup. Putting a participant's date of birth and NDIS number into it would
 * undo the encryption at rest completely, and it would do so invisibly.
 *
 * So the email NAMES what arrived and LINKS to the admin screen, where the
 * capability check and the key both apply.
 */
function brightpath_forms_notify( $slug, $id, $values ) {
	global $wpdb;

	$form   = brightpath_forms_get( $slug );
	$to     = brightpath_forms_recipient( $slug );
	$title  = $form['title'] ?? $slug;
	$has_enc = brightpath_forms_requires_encryption( $slug );

	$lines   = array();
	$lines[] = sprintf( 'A new "%s" submission arrived on the Bright Path website.', $title );
	$lines[] = '';
	$lines[] = 'Reference: #' . $id;
	$lines[] = 'Received:  ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'j F Y, g:ia' );
	$lines[] = '';

	// Names of the fields that arrived, and the non-sensitive contact details.
	// Nothing marked `enc` is named by value here, only by presence.
	foreach ( $form['fields'] as $name => $field ) {
		if ( ! array_key_exists( $name, $values ) ) {
			continue;
		}

		$label = $field['label'] ?? $name;

		if ( ! empty( $field['enc'] ) ) {
			$filled  = is_array( $values[ $name ] ) ? (bool) $values[ $name ] : '' !== trim( (string) $values[ $name ] );
			$lines[] = sprintf( '%s: %s', $label, $filled ? '[provided — read it in the admin]' : '[not provided]' );
			continue;
		}

		if ( ! empty( $field['map'] ) || in_array( $field['type'] ?? '', array( 'select', 'radio' ), true ) ) {
			$val = $values[ $name ];

			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			} elseif ( is_bool( $val ) ) {
				$val = $val ? 'yes' : 'no';
			}

			if ( '' !== trim( (string) $val ) ) {
				$lines[] = sprintf( '%s: %s', $label, $val );
			}
		}
	}

	$lines[] = '';

	if ( $has_enc ) {
		$lines[] = 'This submission contains personal information that is encrypted at rest.';
		$lines[] = 'It is deliberately NOT included in this email. Read it here:';
	} else {
		$lines[] = 'Read the full submission here:';
	}

	$lines[] = admin_url( 'admin.php?page=brightpath-submissions&submission=' . $id );

	$subject = sprintf( '[%s] %s #%d', get_bloginfo( 'name' ), $title, $id );

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	// Reply-To, never From: forging the From as the submitter is what gets a
	// domain's mail marked as spam, and the relay is not authorised for their
	// domain anyway.
	$reply = '';
	foreach ( $form['fields'] as $name => $field ) {
		if ( 'email' === ( $field['map'] ?? '' ) && ! empty( $values[ $name ] ) ) {
			$reply = $values[ $name ];
			break;
		}
	}
	if ( is_email( $reply ) ) {
		$headers[] = 'Reply-To: ' . $reply;
	}

	// Capture the reason, not just the fact. wp_mail_failed fires from
	// inc/mail.php's refusal AND from a real PHPMailer exception.
	$failure = '';
	$capture = function ( $error ) use ( &$failure ) {
		$failure = is_wp_error( $error ) ? $error->get_error_code() . ': ' . $error->get_error_message() : 'unknown';
	};
	add_action( 'wp_mail_failed', $capture );

	$sent = wp_mail( $to, $subject, implode( "\n", $lines ), $headers );

	remove_action( 'wp_mail_failed', $capture );

	// THE SUBMISSION IS ALREADY STORED. Nothing below rolls it back — losing
	// the enquiry as well would be a second failure on top of the first.
	if ( ! $sent ) {
		if ( '' === $failure ) {
			$failure = 'wp_mail returned false';
		}

		error_log( sprintf( '[brightpath] notification FAILED for submission #%d (%s): %s', $id, $slug, $failure ) );
	}

	$wpdb->update(
		brightpath_forms_table(),
		array(
			'notified'     => $sent ? 1 : 2,
			'notify_error' => $sent ? '' : mb_substr( $failure, 0, 255 ),
		),
		array( 'id' => $id ),
		array( '%d', '%s' ),
		array( '%d' )
	);

	return (bool) $sent;
}

/**
 * Surface un-notified submissions in the admin.
 *
 * Without this the failure mode is: the form says thank you, the row is stored
 * correctly, and nobody ever looks at it because nobody was told. A stored
 * submission that no human knows about is functionally a lost one.
 */
function brightpath_forms_unnotified_notice() {
	if ( ! current_user_can( BRIGHTPATH_FORMS_CAP ) ) {
		return;
	}

	global $wpdb;
	$t = brightpath_forms_table();

	// The table may not exist yet on the very first load after deploy.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
		return;
	}

	$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE notified = 2" );

	if ( ! $n ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>Bright Path: %d submission%s stored but NOT emailed.</strong> '
		. 'The submissions themselves are safe — only the notification failed. '
		. '<a href="%s">Review them</a>, and check the SMTP settings.</p></div>',
		$n,
		1 === $n ? ' was' : 's were',
		esc_url( admin_url( 'admin.php?page=brightpath-submissions&notified=2' ) )
	);
}
add_action( 'admin_notices', 'brightpath_forms_unnotified_notice' );

/* =====================================================================
 * 9. Retention — DISABLED by default, dry-run first
 * ===================================================================== */

/**
 * Retention periods, in days.
 *
 * THESE ARE PROPOSALS. They are not the client's decision yet, and the purge
 * job is disabled until they confirm. A purge job that runs before anyone has
 * read its output is a data-loss event with a ticket number.
 *
 * The reasoning offered to the client, per form:
 *   contact / sil-enquiry   1 year   — ordinary business correspondence
 *   request-support         2 years  — may become a participant record
 *   feedback                2 years  — complaints may be reviewed by the
 *                                      NDIS Commission; 7 years if it became
 *                                      a formal complaint, which is a manual
 *                                      status change rather than a default
 *   job-application         1 year   — unsuccessful applicants; the CV goes
 *                                      with the row
 *   referral                7 years  — if the referral became a participant,
 *                                      the operational record is the source of
 *                                      truth and this row is a duplicate; if it
 *                                      did not, 7 years matches the general
 *                                      health-record expectation
 */
function brightpath_forms_retention_days( $slug ) {
	$defaults = array(
		'contact'         => 365,
		'sil-enquiry'     => 365,
		'request-support' => 730,
		'feedback'        => 730,
		'job-application' => 365,
		'referral'        => 2555,
	);

	$stored = get_option( 'brightpath_forms_retention', array() );

	$days = $defaults[ $slug ] ?? 0;

	if ( is_array( $stored ) && isset( $stored[ $slug ] ) ) {
		$days = (int) $stored[ $slug ];
	}

	return (int) apply_filters( 'brightpath_forms_retention_days', $days, $slug );
}

/**
 * Is the purge job switched on? Default FALSE, and it stays false until the
 * client has confirmed the periods above.
 */
function brightpath_forms_purge_enabled() {
	return (bool) apply_filters( 'brightpath_forms_purge_enabled', (bool) get_option( 'brightpath_forms_purge_enabled', false ) );
}

/**
 * The purge.
 *
 * $dry_run true  -> counts and logs what it WOULD delete, touches nothing
 * $dry_run false -> deletes, and only if the job is enabled
 *
 * Files are unlinked before their row, so an interrupted run leaves an
 * orphaned row (visible, fixable) rather than an orphaned file on disk
 * (invisible, and exactly the thing retention exists to prevent).
 */
function brightpath_forms_purge( $dry_run = true ) {
	global $wpdb;

	$t  = brightpath_forms_table();
	$ft = brightpath_forms_files_table();

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, form_slug, created_at, retention_until FROM {$t}
		 WHERE retention_until IS NOT NULL AND retention_until < %s
		 ORDER BY id ASC",
		gmdate( 'Y-m-d' )
	) );

	$report = array(
		'dry_run' => (bool) $dry_run,
		'enabled' => brightpath_forms_purge_enabled(),
		'due'     => count( $rows ),
		'deleted' => 0,
		'files'   => 0,
		'by_form' => array(),
		'ids'     => array(),
	);

	foreach ( $rows as $row ) {
		$report['by_form'][ $row->form_slug ] = ( $report['by_form'][ $row->form_slug ] ?? 0 ) + 1;
		$report['ids'][] = (int) $row->id;
	}

	if ( $dry_run || ! brightpath_forms_purge_enabled() ) {
		if ( ! $dry_run && ! brightpath_forms_purge_enabled() ) {
			$report['skipped_reason'] = 'purge is disabled; enable brightpath_forms_purge_enabled once retention periods are confirmed';
		}

		return $report;
	}

	$dir = brightpath_forms_upload_dir();

	foreach ( $rows as $row ) {
		$files = $wpdb->get_results( $wpdb->prepare( "SELECT id, stored_path FROM {$ft} WHERE submission_id = %d", $row->id ) );

		foreach ( $files as $f ) {
			$path = $dir . '/' . basename( $f->stored_path );

			if ( is_file( $path ) && unlink( $path ) ) {
				$report['files']++;
			}
		}

		$wpdb->delete( $ft, array( 'submission_id' => $row->id ), array( '%d' ) );
		$wpdb->delete( $t, array( 'id' => $row->id ), array( '%d' ) );
		$report['deleted']++;
	}

	error_log( sprintf( '[brightpath] retention purge deleted %d submissions and %d files.', $report['deleted'], $report['files'] ) );

	return $report;
}

/**
 * Daily schedule. The EVENT is always scheduled; what it does depends on the
 * switch. A cron entry that exists and does nothing is easy to inspect; one
 * that is conditionally scheduled is easy to believe in wrongly.
 */
function brightpath_forms_schedule_purge() {
	if ( ! wp_next_scheduled( 'brightpath_forms_purge_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'brightpath_forms_purge_event' );
	}
}
add_action( 'init', 'brightpath_forms_schedule_purge', 20 );

function brightpath_forms_purge_event() {
	if ( ! brightpath_forms_purge_enabled() ) {
		$report = brightpath_forms_purge( true );

		if ( $report['due'] > 0 ) {
			error_log( sprintf(
				'[brightpath] retention DRY RUN: %d submissions are past their proposed retention date (%s). Nothing deleted; the purge is disabled pending client confirmation.',
				$report['due'],
				wp_json_encode( $report['by_form'] )
			) );
		}

		return;
	}

	brightpath_forms_purge( false );
}
add_action( 'brightpath_forms_purge_event', 'brightpath_forms_purge_event' );

/**
 * WP-CLI: `wp brightpath forms purge [--dry-run]`.
 *
 * --dry-run is the DEFAULT. Deleting requires both --no-dry-run and the option
 * switched on, so neither a typo nor an enabled option alone destroys anything.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'brightpath forms purge', function ( $args, $assoc ) {
		$dry = ! isset( $assoc['dry-run'] ) || WP_CLI\Utils\get_flag_value( $assoc, 'dry-run', true );

		$report = brightpath_forms_purge( $dry );

		WP_CLI::log( 'dry-run : ' . ( $report['dry_run'] ? 'yes' : 'no' ) );
		WP_CLI::log( 'enabled : ' . ( $report['enabled'] ? 'yes' : 'no' ) );
		WP_CLI::log( 'due     : ' . $report['due'] );
		WP_CLI::log( 'by form : ' . wp_json_encode( $report['by_form'] ) );
		WP_CLI::log( 'ids     : ' . wp_json_encode( $report['ids'] ) );

		if ( isset( $report['skipped_reason'] ) ) {
			WP_CLI::warning( $report['skipped_reason'] );
		}

		if ( ! $report['dry_run'] && $report['enabled'] ) {
			WP_CLI::success( sprintf( 'Deleted %d submissions and %d files.', $report['deleted'], $report['files'] ) );
		} else {
			WP_CLI::success( 'Nothing was deleted.' );
		}
	} );
}

/**
 * The forms pattern category.
 *
 * Registered HERE rather than appended to Main's inc/patterns.php, on purpose:
 * it keeps "delete inc/forms.php + assets/css/forms.css + patterns/form-*.php
 * and the layer is gone" true, with no edit to a Main-owned file to undo.
 *
 * Priority 11 so it lands after Main's own categories on the same hook; the
 * order does not matter to correctness, only to the inserter's listing.
 */
function brightpath_forms_pattern_category() {
	register_block_pattern_category(
		'brightpath-forms',
		array(
			'label'       => __( 'Bright Path forms', 'brightpath' ),
			'description' => __( 'The six site forms. Structure is locked; headings, ledes and copy are open.', 'brightpath' ),
		)
	);
}
add_action( 'init', 'brightpath_forms_pattern_category', 11 );

/* =====================================================================
 * 10. The admin screens
 *
 * EVERY read path in this section checks BRIGHTPATH_FORMS_CAP, and none of
 * them checks manage_options. An Editor -- a logged-in user with broad content
 * rights -- must be refused here, which is the case that catches a capability
 * check written as manage_options out of habit.
 * ===================================================================== */

function brightpath_forms_admin_menu() {
	add_menu_page(
		'Form submissions',
		'Submissions',
		BRIGHTPATH_FORMS_CAP,
		'brightpath-submissions',
		'brightpath_forms_admin_page',
		'dashicons-feedback',
		26
	);
}
add_action( 'admin_menu', 'brightpath_forms_admin_menu' );

/**
 * The list and the single view.
 *
 * add_menu_page's capability already gates the menu, but a capability passed to
 * add_menu_page protects the MENU ITEM and the page callback -- not this
 * function if anything ever calls it directly. The explicit check is here
 * because "it is protected by the thing that calls it" is exactly the
 * assumption that produces an unprotected endpoint later.
 */
function brightpath_forms_admin_page() {
	if ( ! current_user_can( BRIGHTPATH_FORMS_CAP ) ) {
		wp_die( 'You do not have permission to read form submissions.', 'Forbidden', array( 'response' => 403 ) );
	}

	if ( isset( $_GET['submission'] ) ) {
		brightpath_forms_admin_single( (int) $_GET['submission'] );
		return;
	}

	brightpath_forms_admin_list();
}

function brightpath_forms_admin_list() {
	global $wpdb;

	$t      = brightpath_forms_table();
	$schema = brightpath_forms_schema();

	$slug     = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : '';
	$notified = isset( $_GET['notified'] ) ? (int) $_GET['notified'] : -1;
	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	$where = array( '1=1' );
	$args  = array();

	if ( $slug && isset( $schema[ $slug ] ) ) {
		$where[] = 'form_slug = %s';
		$args[]  = $slug;
	}

	if ( 0 <= $notified ) {
		$where[] = 'notified = %d';
		$args[]  = $notified;
	}

	if ( '' !== $search ) {
		$where[] = '(submitter_name LIKE %s OR submitter_email LIKE %s)';
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$args[]  = $like;
		$args[]  = $like;
	}

	$sql = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 200';
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );

	echo '<div class="wrap"><h1>Form submissions</h1>';

	if ( ! brightpath_forms_can_encrypt() ) {
		echo '<div class="notice notice-error inline"><p>The encryption key is unreadable, so encrypted fields below cannot be shown.</p></div>';
	}

	// filters
	echo '<form method="get"><input type="hidden" name="page" value="brightpath-submissions">';
	echo '<select name="form"><option value="">All forms</option>';
	foreach ( $schema as $s => $f ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $s ), selected( $slug, $s, false ), esc_html( $f['title'] ) );
	}
	echo '</select> ';
	printf( '<input type="search" name="s" value="%s" placeholder="Name or email"> ', esc_attr( $search ) );
	echo '<button class="button">Filter</button></form><br>';

	echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
	echo '<th style="width:4em">#</th><th>Form</th><th>From</th><th>Received</th><th>Email sent</th><th>Retention</th>';
	echo '</tr></thead><tbody>';

	if ( ! $rows ) {
		echo '<tr><td colspan="6">No submissions yet.</td></tr>';
	}

	foreach ( $rows as $row ) {
		$title = $schema[ $row->form_slug ]['title'] ?? $row->form_slug;

		echo '<tr>';
		printf(
			'<td><a href="%s"><strong>%d</strong></a></td>',
			esc_url( admin_url( 'admin.php?page=brightpath-submissions&submission=' . $row->id ) ),
			$row->id
		);
		printf( '<td>%s</td>', esc_html( $title ) );
		printf(
			'<td>%s%s</td>',
			esc_html( $row->submitter_name ? $row->submitter_name : '(anonymous)' ),
			$row->submitter_email ? '<br><span class="description">' . esc_html( $row->submitter_email ) . '</span>' : ''
		);
		printf( '<td>%s</td>', esc_html( get_date_from_gmt( $row->created_at, 'j M Y, g:ia' ) ) );

		if ( 1 === (int) $row->notified ) {
			echo '<td><span style="color:#2a7d2e">sent</span></td>';
		} elseif ( 2 === (int) $row->notified ) {
			printf( '<td><strong style="color:#a32e1e">FAILED</strong><br><span class="description">%s</span></td>', esc_html( $row->notify_error ) );
		} else {
			echo '<td>—</td>';
		}

		printf( '<td>%s</td>', $row->retention_until ? esc_html( $row->retention_until ) : '—' );
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '<p class="description">Encrypted fields are never shown in this list and are never included in notification emails. Open a submission to read them.</p>';
	echo '</div>';
}

function brightpath_forms_admin_single( $id ) {
	global $wpdb;

	$t   = brightpath_forms_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );

	if ( ! $row ) {
		echo '<div class="wrap"><h1>Not found</h1></div>';
		return;
	}

	$form   = brightpath_forms_get( $row->form_slug );
	$plain  = json_decode( (string) $row->payload_plain, true );
	$plain  = is_array( $plain ) ? $plain : array();
	$enc    = $row->payload_enc ? brightpath_forms_decrypt( $row->payload_enc ) : array();

	echo '<div class="wrap">';
	printf( '<h1>%s #%d</h1>', esc_html( $form['title'] ?? $row->form_slug ), $row->id );
	printf(
		'<p><a href="%s">&larr; All submissions</a></p>',
		esc_url( admin_url( 'admin.php?page=brightpath-submissions' ) )
	);

	if ( 2 === (int) $row->notified ) {
		printf(
			'<div class="notice notice-warning inline"><p><strong>This submission was stored but the notification email failed.</strong><br>%s</p></div>',
			esc_html( $row->notify_error )
		);
	}

	if ( $row->payload_enc && null === $enc ) {
		echo '<div class="notice notice-error inline"><p><strong>The encrypted fields on this submission cannot be read.</strong> ';
		echo 'This means the key has changed or is missing — it does NOT mean the fields were left blank. ';
		printf( 'The row was encrypted under key id <code>%s</code>.</p></div>', esc_html( $row->enc_key_id ) );
		$enc = array();
	}

	echo '<table class="form-table">';
	printf( '<tr><th>Received</th><td>%s</td></tr>', esc_html( get_date_from_gmt( $row->created_at, 'j F Y, g:ia' ) ) );
	printf( '<tr><th>Page</th><td><a href="%s">%s</a></td></tr>', esc_url( $row->source_url ), esc_html( $row->source_url ) );

	foreach ( (array) ( $form['fields'] ?? array() ) as $name => $field ) {
		if ( 'file' === ( $field['type'] ?? '' ) ) {
			continue;
		}

		$is_enc = ! empty( $field['enc'] );
		$has    = $is_enc ? array_key_exists( $name, (array) $enc ) : array_key_exists( $name, $plain );

		if ( ! $has ) {
			continue;
		}

		$value = $is_enc ? $enc[ $name ] : $plain[ $name ];

		if ( is_array( $value ) ) {
			$labels = ! empty( $field['options'] ) ? brightpath_forms_options( $field['options'] ) : array();
			$value  = implode( ', ', array_map( function ( $v ) use ( $labels ) {
				return $labels[ $v ] ?? $v;
			}, $value ) );
		} elseif ( is_bool( $value ) ) {
			$value = $value ? 'Yes' : 'No';
		} elseif ( ! empty( $field['options'] ) ) {
			$labels = brightpath_forms_options( $field['options'] );
			$value  = $labels[ $value ] ?? $value;
		}

		printf(
			'<tr><th>%s%s</th><td>%s</td></tr>',
			esc_html( $field['label'] ?? $name ),
			$is_enc ? ' <span class="dashicons dashicons-lock" title="Encrypted at rest"></span>' : '',
			nl2br( esc_html( (string) $value ) )
		);
	}

	// files
	$files = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . brightpath_forms_files_table() . ' WHERE submission_id = %d',
		$row->id
	) );

	if ( $files ) {
		echo '<tr><th>Attachments</th><td>';
		foreach ( $files as $f ) {
			printf(
				'<p><a href="%s">%s</a> <span class="description">(%s, %s KB) — stored outside the web root</span></p>',
				esc_url( brightpath_forms_file_url( $f->id ) ),
				esc_html( $f->original_name ),
				esc_html( $f->mime ),
				esc_html( (string) round( $f->bytes / 1024 ) )
			);
		}
		echo '</td></tr>';
	}

	printf( '<tr><th>Retention</th><td>%s</td></tr>', $row->retention_until
		? esc_html( 'Due for deletion after ' . $row->retention_until . ( brightpath_forms_purge_enabled() ? '' : ' (purge is currently DISABLED)' ) )
		: 'No retention period set' );

	echo '</table></div>';
}
