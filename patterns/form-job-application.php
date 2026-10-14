<?php
/**
 * Title: Job application form
 * Slug: brightpath/form-job-application
 * Description: The job application form, designed to sit inside a job template -- the hidden role field fills itself from the record, so one pattern serves every vacancy. Resumes are stored outside the web root and are only readable by staff who hold the submissions capability.
 * Categories: brightpath-forms
 * Keywords: form, job-application
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
<p class="bp-eyebrow has-x-small-font-size">Apply</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Apply for this role</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Send us your details and your resume. We read every application, and we will be in touch either way.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:brightpath/form {"formSlug":"job-application"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
