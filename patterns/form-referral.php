<?php
/**
 * Title: Referral form
 * Slug: brightpath/form-referral
 * Description: The seven-step participant referral. The step sequence, the field list and the three consent statements are LOCKED -- they are a clinical intake record, not layout. Date of birth, NDIS number and the free-text needs fields are encrypted at rest and never appear in the notification email. Edit the heading and lede here; everything else is in inc/forms.php.
 * Categories: brightpath-forms
 * Keywords: form, referral
 * Lock Posture: slotted
 * Viewport Width: 1176
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"sand-100","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-sand-100-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"bp-measure bp-slot","templateLock":false,"style":{"spacing":{"blockGap":"var:preset|spacing|70"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group bp-measure bp-slot">
<!-- wp:group {"className":"bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group bp-reveal">
<!-- wp:paragraph {"className":"bp-eyebrow","fontSize":"x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Refer a participant</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Make a referral</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>For support coordinators, plan managers, families and health professionals. It takes about ten minutes. Everything is saved securely and only our intake team can read it.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:brightpath/form {"formSlug":"referral"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
