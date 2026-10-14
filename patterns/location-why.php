<?php
/**
 * Title: Location — why-us band
 * Slug: brightpath/location-why
 * Description: Why BrightPath split band: bound heading, tick list, bound team button, bound map label. Headings read the location title through the brightpath/parity binding, so the band stays dynamic inside post content.
 * Categories: brightpath-bands
 * Keywords: location
 * Viewport Width: 1440
 * Inserter: false
 *
 * @package brightpath
 */
?>
<!-- wp:group {"align": "full", "className": "bp-loc-band bp-loc-band\u002d\u002dwhy", "style": {"spacing": {"padding": {"top": "var:preset|spacing|section-y", "bottom": "var:preset|spacing|section-y"}, "blockGap": "var:preset|spacing|60"}}, "layout": {"type": "constrained"}} -->
<div class="wp-block-group alignfull bp-loc-band bp-loc-band--why" style="padding-top:var(--wp--preset--spacing--section-y);padding-bottom:var(--wp--preset--spacing--section-y)"><!-- wp:columns {"className": "bp-split-cols bp-split-cols\u002d\u002dlead", "style": {"spacing": {"blockGap": {"left": "var:preset|spacing|70", "top": "var:preset|spacing|50"}}}} -->
<div class="wp-block-columns bp-split-cols bp-split-cols--lead"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className": "bp-reveal", "style": {"spacing": {"blockGap": "var:preset|spacing|40"}}, "layout": {"type": "constrained"}} -->
<div class="wp-block-group bp-reveal"><!-- wp:paragraph {"className": "bp-eyebrow", "fontSize": "x-small"} -->
<p class="bp-eyebrow has-x-small-font-size">Why BrightPath</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"metadata": {"bindings": {"content": {"source": "brightpath/parity", "args": {"key": "location_why_h"}}}}} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:list {"className": "tick-list"} -->
<ul class="wp-block-list tick-list"><!-- wp:list-item -->
<li>A local office you can walk into, not just a phone number</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Workers who live in the area and know the transport</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Short-notice cover arranged within the local team</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Multilingual workers matched on language and culture</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>A SIL home in the area with occasional vacancies</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Established relationships with local health and community services</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:buttons {"layout": {"type": "flex", "flexWrap": "wrap"}, "style": {"spacing": {"blockGap": "var:preset|spacing|30"}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className": "is-style-outline","metadata":{"bindings":{"text":{"source":"brightpath/parity","args":{"key":"location_team_btn"}}}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/contact/">Talk to the local team</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className": "bp-media bp-media\u002d\u002dratio-4-3 bp-media\u002d\u002daccent bp-media\u002d\u002dlabelled"} -->
<div class="wp-block-group bp-media bp-media--ratio-4-3 bp-media--accent bp-media--labelled"><!-- wp:paragraph {"className": "bp-media__label","metadata":{"bindings":{"content":{"source":"brightpath/parity","args":{"key":"location_map_label"}}}}} -->
<p class="bp-media__label">Map — Service Area</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

</div>
<!-- /wp:group -->
