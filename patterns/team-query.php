<?php
/**
 * Title: Team — query band
 * Slug: brightpath/team-query
 * Lock Posture: slotted
 * Description: The people band, driven by the team_member type rather than typed cards. Set the query's "Items per page" to 3 on /about/ and 6 on /our-team/ — the same band, the same records, one place to correct a job title. The role beside each name is BOUND to the member's Role, never typed: a typed role drifts from the record the first time a title changes. team_member has no public URL on purpose, so the name is not a link.
 * Categories: brightpath-bands
 * Keywords: team, people, staff, query, about
 * NOTE: deliberately NO `Post Types:` header. That header RESTRICTS the pattern
 * to the editor of that type -- a band carrying `Post Types: team_member` would
 * vanish from the PAGE inserter, which is the only place it is ever used. The
 * type this band queries is in its query attribute, not in its header.
 * Viewport Width: 1440
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"sand-50","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-y","bottom":"var:preset|spacing|section-y"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-sand-50-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)">
<!-- wp:group {"className":"bp-slot","templateLock":false} --><div class="wp-block-group bp-slot"><!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"team_member","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[],"format":[]},"className":"bp-query","layout":{"type":"default"}} -->
<div class="wp-block-query bp-query"><!-- wp:post-template {"className":"bp-grid-3 bp-query-grid","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<!-- wp:group {"backgroundColor":"white","className":"bp-panel bp-reveal","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group bp-panel bp-reveal has-white-background-color has-background">
<!-- wp:group {"className":"bp-media bp-media\u002d\u002dportrait","layout":{"type":"default"}} -->
<div class="wp-block-group bp-media bp-media--portrait">
<!-- wp:paragraph {"className":"bp-media__label","metadata":{"bindings":{"content":{"source":"brightpath/derived","args":{"key":"media_label"}}}}} -->
<p class="bp-media__label"></p>
<!-- /wp:paragraph -->
<!-- wp:post-featured-image /-->
</div>
<!-- /wp:group -->
<!-- wp:post-title {"level":2,"isLink":false,"className":"card__title"} /-->
<!-- wp:paragraph {"className":"card__role","fontSize":"small","metadata":{"bindings":{"content":{"source":"brightpath/derived","args":{"key":"team_role"}}}}} -->
<p class="card__role has-small-font-size"></p>
<!-- /wp:paragraph -->
<!-- wp:post-excerpt {"excerptLength":32,"fontSize":"small"} /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div><!-- /wp:group -->
</div>
<!-- /wp:group -->
