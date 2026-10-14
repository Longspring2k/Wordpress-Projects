<?php
/**
 * Title: SIL vacancy enquiry form
 * Slug: brightpath/form-sil-enquiry
 * Description: The SIL property enquiry, designed to sit inside a property template -- the hidden property field fills itself from the record, so one pattern serves every vacancy. The heading and lede are open.
 * Categories: brightpath-forms
 * Keywords: form, sil-enquiry
 * Lock Posture: slotted
 * Viewport Width: 420
 *
 * @package brightpath
 */
?>
<!-- wp:group {"className":"sidebar-card","layout":{"type":"default"}} -->
<div class="wp-block-group sidebar-card">
<!-- wp:group {"className":"bp-slot","templateLock":false,"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-slot">
<!-- wp:group {"className":"bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group bp-reveal">
<!-- wp:heading {"className":"sidebar-card__title"} -->
<h2 class="wp-block-heading sidebar-card__title">Enquire about this home</h2>
<!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"bp-vacancy-alert","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-vacancy-alert"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"brightpath/parity","args":{"key":"vacancy_label"}}}},"className":"bp-vacancy-alert__title"} -->
<p class="bp-vacancy-alert__title"></p>
<!-- /wp:paragraph -->

<!-- wp:post-date {"format":"j F Y","metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"modified"}}}},"className":"bp-vacancy-alert__date"} /--></div>
<!-- /wp:group -->
<!-- wp:brightpath/form {"formSlug":"sil-enquiry"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
