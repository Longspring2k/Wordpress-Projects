<?php
/**
 * Title: Card grid — live
 * Slug: brightpath/card-grid-query
 * Lock Posture: contentOnly
 * Description: Query-driven card grid. Post template Styles: 3 columns (default) or 4 columns. Choose post type in Query settings.
 * Categories: brightpath-bands
 * Keywords: cards, grid, query, live
 * NOTE: deliberately NO `Post Types:` header. That header RESTRICTS the pattern
 * to the editor of that type -- a band carrying `Post Types: team_member` would
 * vanish from the PAGE inserter, which is the only place it is ever used. The
 * type this band queries is in its query attribute, not in its header.
 * Viewport Width: 1440
 *
 * @package brightpath
 */

?>
<!-- wp:group {"templateLock":"contentOnly","align":"full","backgroundColor":"sand-50","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-sand-50-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"bp-slot"} --><div class="wp-block-group bp-slot"><!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"service","order":"asc","orderBy":"title","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"className":"bp-query","layout":{"type":"default"}} -->
<div class="wp-block-query bp-query"><!-- wp:post-template {"className":"is-style-cols-3 bp-query-grid","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<!-- wp:group {"backgroundColor":"white","className":"bp-panel bp-panel\u002d\u002dmd bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-panel bp-panel--md bp-reveal has-white-background-color has-background">
<!-- wp:paragraph {"className":"icon-tile"} -->
<p class="icon-tile">◆</p>
<!-- /wp:paragraph -->
<!-- wp:post-title {"level":3,"isLink":true,"className":"card__title"} /-->
<!-- wp:post-excerpt {"excerptLength":26,"className":"card__text","fontSize":"small"} /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div><!-- /wp:group -->
</div>
<!-- /wp:group -->
