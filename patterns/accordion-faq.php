<?php
/**
 * Title: FAQ accordion
 * Slug: brightpath/accordion-faq
 * Lock Posture: slotted
 * Description: A question-and-answer accordion. This is the one band whose length is meant to vary, so the ITEMS are free: add, remove and reorder them freely. The band head and the accordion frame itself are locked in place — a client who deletes the accordion cannot get it back without rebuilding the page. Every answer ships closed, and autoclose keeps one answer open at a time. Never put an entrance reveal inside a panel — a closed panel is display:none and the observer never fires.
 * Categories: brightpath-bands
 * Keywords: faq, accordion, questions
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
<p class="bp-eyebrow has-x-small-font-size">Questions</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Things people ask us first</h2>
<!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:accordion {"autoclose":true,"metadata":{"name":"FAQ"}} -->
<div class="wp-block-accordion" role="group">
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item">
<!-- wp:accordion-heading {"title":"Do I need an approved NDIS plan before I contact you?","level":3} -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Do I need an approved NDIS plan before I contact you?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->
<!-- wp:accordion-panel -->
<div class="wp-block-accordion-panel" role="region"><!-- wp:paragraph -->
<p>No. Plenty of people ring us while they are still waiting on a plan, or while they are preparing for a planning meeting. If you are not eligible for the NDIS, we will tell you and point you toward other options.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel -->
</div>
<!-- /wp:accordion-item -->
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item">
<!-- wp:accordion-heading {"title":"How quickly can support start?","level":3} -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">How quickly can support start?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->
<!-- wp:accordion-panel -->
<div class="wp-block-accordion-panel" role="region"><!-- wp:paragraph -->
<p>Most participants start within two to three weeks of the first call. If something is urgent, say so when you ring and we will tell you honestly whether we have capacity.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel -->
</div>
<!-- /wp:accordion-item -->
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item">
<!-- wp:accordion-heading {"title":"Can I choose my support worker?","level":3} -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Can I choose my support worker?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->
<!-- wp:accordion-panel -->
<div class="wp-block-accordion-panel" role="region"><!-- wp:paragraph -->
<p>Yes. We shortlist workers who suit your goals and routine, you meet them, and you decide. If it is not working, tell your team leader and we will change it.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel -->
</div>
<!-- /wp:accordion-item -->
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item">
<!-- wp:accordion-heading {"title":"What areas do you cover?","level":3} -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">What areas do you cover?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->
<!-- wp:accordion-panel -->
<div class="wp-block-accordion-panel" role="region"><!-- wp:paragraph -->
<p>Eight regions of Greater Sydney. If your suburb is not listed, ring us anyway — we often cover the streets in between.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel -->
</div>
<!-- /wp:accordion-item -->
</div>
<!-- /wp:accordion -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
