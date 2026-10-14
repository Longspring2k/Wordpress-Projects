<?php
/**
 * Title: Section head
 * Slug: brightpath/section-head
 * Lock Posture: sealed
 * Description: Eyebrow, H2 and lede. NEST-ONLY: drop this INSIDE a band’s own container, never as a sibling of a band — on its own it renders as a transparent group on the page background, above the band it was meant to introduce. The grid and steps bands already carry their own head.
 * Categories: brightpath-detail
 * Keywords: heading, intro, eyebrow
 * Viewport Width: 1176
 *
 * @package brightpath
 */
?>
<!-- wp:group {"className":"bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"metadata":{"name":"Section head"}} -->
<div class="wp-block-group bp-reveal">
<!-- wp:paragraph {"className":"bp-eyebrow","fontSize":"x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">How it works</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Getting started is simpler than most people expect</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Four steps from first phone call to your first shift. Most participants start within two to three weeks.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
