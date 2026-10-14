<?php
/**
 * Title: Contact form
 * Slug: brightpath/form-contact
 * Description: The general contact form. The form itself is a single locked block that renders from the field schema in inc/forms.php — the field list, their name attributes, the required set and the consent checkbox are locked there, not here. The heading and the lede above it are open: edit them freely. Option labels and the success message are NOT editable from wp-admin: there is no form settings screen. They are stored in the brightpath_forms_labels and brightpath_forms_copy options and are changed by the developer. Ask rather than editing this pattern -- editing it here would not change them.
 * Categories: brightpath-forms
 * Keywords: contact, form, enquiry
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
<p class="bp-eyebrow has-x-small-font-size">Contact</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Send us a message</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Tell us what you need and we will get back to you within one business day. If it is urgent, please call instead.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:brightpath/form {"formSlug":"contact"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
