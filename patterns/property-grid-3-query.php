<?php
/**
 * Title: Property grid — three live SIL homes, light band
 * Slug: brightpath/property-grid-3-query
 * Lock Posture: slotted
 * Description: The SIL page's "Homes with vacancies now" band. Three real sil_property records on a light band, each card a photograph frame, a vacancy badge, the home's name, its region and its access rung. This is the LIGHT twin of brightpath/sil-feature-query: same records, same card, different surface -- the dark forest band is the home page's one dark content band and is not repeated on /sil/.
 *
 * WHY THIS EXISTS RATHER THAN A FLAG ON card-grid-3-query: /sil/ was composed
 * from card-grid-3-query, which is the SERVICE card -- an icon tile, a title
 * and an excerpt, and no media frame at all. MEASURED against the prototype:
 * 6 media frames on the prototype's /sil/ against 3 in the build, and the
 * property card 239px tall against the prototype's 500px. The three missing
 * frames are these. card-grid-3-query is also the home page's service grid, so
 * adding a frame there would have put an empty photograph slot on eight
 * service cards that the prototype does not give one.
 *
 * Categories: brightpath-bands
 * Keywords: sil, property, homes, vacancies, query, cards
 * NOTE: deliberately NO `Post Types:` header -- see sil-feature-query for why.
 * Viewport Width: 1440
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"sand-50","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-sand-50-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"bp-slot","templateLock":false} --><div class="wp-block-group bp-slot"><!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"sil_property","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"className":"bp-query","layout":{"type":"default"}} -->
<div class="wp-block-query bp-query"><!-- wp:post-template {"className":"bp-grid-3 bp-query-grid","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<!-- wp:group {"backgroundColor":"white","className":"bp-panel bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-panel bp-reveal has-white-background-color has-background">
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
<!-- wp:post-excerpt {"excerptLength":32,"className":"card__text","fontSize":"small"} /-->
<!-- wp:post-terms {"term":"sil_access","className":"card__specs bp-icon bp-icon\u002d\u002daccessibility","fontSize":"small"} /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div><!-- /wp:group -->
</div>
<!-- /wp:group -->
