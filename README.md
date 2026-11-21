# BeBrand

The BeBrand website as a WordPress theme.

The site itself is a WordPress page called "Home", made of one HTML block per section and shown with the theme's
"BeBrand landing page" template. The theme supplies the design (styles, pictures, script), writes that page when it is first
switched on, and receives the site's project briefs and call requests (saved under "Project requests" and emailed).

With Elementor running, the same site is also one Elementor element, "BeBrand site", on a page with the "BeBrand site (edited in
Elementor)" template. Every text, price, question, review and work photo of the page is a field of that element, and the theme
builds the public page from those fields, so the design, layout and animation are the same as the original and nothing of
Elementor's own markup or styles reaches the visitor. The fields are listed in `site/fields.json`, which the build writes from the
page itself.

This repository holds the built theme only. The live site (withbebrand.com) gets it by pulling this repository into
`wp-content/themes/bebrand`. Changes are made at the source and pushed here; files edited here by hand are overwritten by the next build.

Photographs: the sky is "Kloofendal 48d Partly Cloudy (Pure Sky)" from Poly Haven (CC0). The home and room pictures are stock photographs.
