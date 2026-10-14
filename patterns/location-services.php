<?php
/**
 * Title: Location — services band
 * Slug: brightpath/location-services
 * Description: Available-locally band: bound heading, six most requested services (query), link to all services. Headings read the location title through the brightpath/parity binding, so the band stays dynamic inside post content.
 * Categories: brightpath-bands
 * Keywords: location
 * Viewport Width: 1440
 * Inserter: false
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align": "full", "backgroundColor": "sand-100", "className": "bp-loc-band bp-loc-band\u002d\u002dservices", "style": {"spacing": {"padding": {"top": "var:preset|spacing|section-y", "bottom": "var:preset|spacing|section-y"}, "blockGap": "var:preset|spacing|60"}}, "layout": {"type": "constrained"}} -->
<div class="wp-block-group alignfull bp-loc-band bp-loc-band--services has-sand-100-background-color has-background" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)"><!-- wp:group {"className": "bp-reveal bp-head", "style": {"spacing": {"blockGap": "var:preset|spacing|40"}}, "layout": {"type": "constrained"}} -->
<div class="wp-block-group bp-reveal bp-head"><!-- wp:paragraph {"className": "bp-eyebrow", "fontSize": "x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Available locally</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"metadata": {"bindings": {"content": {"source": "brightpath/parity", "args": {"key": "location_services_h"}}}}} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "lede"} -->
<p class="lede">All eight of our services run here. These are the six most requested locally.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

<!-- wp:query {"queryId": 0, "query": {"perPage": 6, "pages": 0, "offset": 0, "postType": "service", "order": "asc", "orderBy": "menu_order", "inherit": false}, "layout": {"type": "default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"className": "bp-cards bp-grid-3", "style": {"spacing": {"blockGap": {"top": "var:preset|spacing|50", "left": "var:preset|spacing|50"}}}, "layout": {"type": "grid", "columnCount": 3}} -->
<!-- wp:group {"backgroundColor": "white", "className": "bp-panel bp-panel\u002d\u002dmd bp-reveal card\u002d\u002dservice", "style": {"spacing": {"blockGap": "var:preset|spacing|30"}}} -->
<div class="wp-block-group bp-panel bp-panel--md bp-reveal card--service has-white-background-color has-background"><!-- wp:paragraph {"className":"icon-tile"} -->
<p class="icon-tile">◆</p>
<!-- /wp:paragraph -->

<!-- wp:post-title {"level":3,"isLink":true,"className":"card__title"} /-->

<!-- wp:post-excerpt {"excerptLength":24,"className":"card__text","showMoreOnNewLine":false,"fontSize":"small"} /-->
</div>
<!-- /wp:group -->

<!-- /wp:post-template -->

</div>
<!-- /wp:query -->

<!-- wp:paragraph -->
<p><a class="link-arrow" href="/services/">All eight services</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
