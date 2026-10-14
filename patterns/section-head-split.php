<?php
/**
 * Title: Section head — with link
 * Slug: brightpath/section-head-split
 * Lock Posture: sealed
 * Description: Section head with a link to the matching archive. NEST-ONLY: drop this INSIDE a band’s own container, never as a sibling of a band — on its own it renders as a transparent group on the page background, above the band it was meant to introduce. Card grid — four up already carries this head.
 * Categories: brightpath-detail
 * Keywords: heading, intro, link
 * Viewport Width: 1176
 *
 * @package brightpath
 */
?>
<!-- wp:group {"className":"bp-reveal bp-head-split","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"metadata":{"name":"Section head split"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group bp-reveal bp-head-split">
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"className":"bp-eyebrow","fontSize":"x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Our services</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">How we can support you</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Eight core supports, delivered by people who turn up when they say they will.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:buttons {"layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline bp-icon-after bp-icon\u002d\u002darrow-right"} -->
<div class="wp-block-button is-style-outline bp-icon-after bp-icon--arrow-right"><a class="wp-block-button__link wp-element-button" href="#">View all services</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
