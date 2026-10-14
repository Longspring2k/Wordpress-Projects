<?php
/**
 * Title: Request support form
 * Slug: brightpath/form-request-support
 * Description: The support request form. The field list, name attributes, required set and consent checkbox are locked in inc/forms.php, not here. The heading and lede above are open. Option labels and the success message are NOT editable from wp-admin: there is no form settings screen. They are stored in the brightpath_forms_labels and brightpath_forms_copy options and are changed by the developer. Editing this pattern would not change them.
 * Categories: brightpath-forms
 * Keywords: form, request-support
 * Lock Posture: slotted
 * Viewport Width: 1176
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"bp-measure bp-slot","templateLock":false,"style":{"spacing":{"blockGap":"var:preset|spacing|70"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group bp-measure bp-slot">
<!-- wp:group {"className":"bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group bp-reveal">
<!-- wp:paragraph {"className":"bp-eyebrow","fontSize":"x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Get started</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Tell us what support you are looking for</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Fill this in and someone from the intake team will call you within one business day to talk it through. There is no obligation and no cost to ask.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:brightpath/form {"formSlug":"request-support"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
