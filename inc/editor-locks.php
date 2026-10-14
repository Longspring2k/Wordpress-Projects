<?php
/**
 * Bright Path — editor editability (formerly "lock enforcement")
 *
 * OWNER REQUIREMENT: every section and every block, on every page, post type,
 * template and pattern, must be addable, editable, movable and deletable by
 * anyone who can edit the content. The earlier design locked structure
 * (`lock.move` / `lock.remove` on blocks, `templateLock` all/contentOnly on
 * containers, "Unlock" and "Hide block" removed for non-admins). That is
 * retired: no lock attributes ship in patterns, templates or parts, and the
 * two editor restrictions below are gone.
 *
 * Kept loaded by functions.php so the include stays valid. Do not add a
 * `template_lock` to a post type, `allowed_block_types_all`, or
 * `lock`/`templateLock` attributes to patterns: they take editing away.
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}
