<?php
/**
 * Title: SIL feature (live)
 * Slug: brightpath/sil-feature-query
 * Lock Posture: contentOnly
 * Description: Dark property band; cards are live sil_property records. contentOnly.
 * Categories: brightpath-bands
 * Keywords: sil, property, dark, query
 * NOTE: deliberately NO `Post Types:` header. That header RESTRICTS the pattern
 * to the editor of that type -- a band carrying `Post Types: team_member` would
 * vanish from the PAGE inserter, which is the only place it is ever used. The
 * type this band queries is in its query attribute, not in its header.
 * Viewport Width: 1440
 *
 * @package brightpath
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","backgroundColor":"forest-900","className":"bp-sil-feature","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"},"blockGap":"var:preset|spacing|80"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull bp-sil-feature has-forest-900-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"cta__shape"} -->
<div class="wp-block-group cta__shape"></div>
<!-- /wp:group --><!-- wp:group {"className":"bp-slot"} --><div class="wp-block-group bp-slot"><!-- wp:group {"className":"bp-reveal bp-head-split","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group bp-reveal bp-head-split">
<!-- wp:group {"className":"bp-head","style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
<div class="wp-block-group bp-head">
<!-- wp:paragraph {"className":"bp-eyebrow","fontSize":"x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Supported Independent Living</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">A place to live. Support to live your way.</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Our SIL homes are ordinary houses in ordinary streets — close to shops, transport and the people you already know. Housemates are matched carefully, and you meet them before anything is signed.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:buttons {"layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"bp-btn\u002d\u002dlg","backgroundColor":"sand-50","textColor":"forest-900"} -->
<div class="wp-block-button bp-btn--lg"><a class="wp-block-button__link has-forest-900-color has-text-color has-sand-50-background-color has-background wp-element-button" href="/sil-homes/">Explore SIL homes</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group --><!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"sil_property","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"className":"bp-query","layout":{"type":"default"}} -->
<div class="wp-block-query bp-query"><!-- wp:post-template {"className":"bp-grid-3 bp-query-grid","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<!-- wp:group {"backgroundColor":"white","className":"bp-panel bp-card-light bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-panel bp-card-light bp-reveal has-white-background-color has-background">
<!-- wp:group {"className":"bp-media bp-media\u002d\u002dproperty bp-media\u002d\u002dsquare-top","layout":{"type":"default"}} -->
<div class="wp-block-group bp-media bp-media--property bp-media--square-top">
<!-- wp:paragraph {"className":"bp-media__label","metadata":{"bindings":{"content":{"source":"brightpath/derived","args":{"key":"media_label"}}}}} -->
<p class="bp-media__label"></p>
<!-- /wp:paragraph -->
<!-- wp:post-featured-image /-->
</div>
<!-- /wp:group -->
<!-- wp:post-terms {"term":"sil_status","className":"bp-status","fontSize":"x-small"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"card__title"} /-->
<!-- wp:post-terms {"term":"sil_region","className":"card__meta bp-icon bp-icon\u002d\u002dmap-pin","fontSize":"small"} /-->
<!-- wp:post-terms {"term":"sil_access","className":"card__specs bp-icon bp-icon\u002d\u002daccessibility","fontSize":"small"} /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div><!-- /wp:group -->
</div>
<!-- /wp:group -->
