=== Shopnook – Shoppable Images for WooCommerce ===
Contributors: sanju-shankar
Donate link: https://buymeacoffee.com/sanjayshankar
Tags: shoppable images, woocommerce, lookbook, shop the look, image hotspots
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shoppable images for WooCommerce: tag products on any image or gallery in the block editor, and see the views, clicks and sales they bring.

== Description ==

Shopnook turns the photos in your posts and pages into shoppable images. Switch on "Shoppable image" on any Image block, click the photo to drop pins, and pick a WooCommerce product for each. Visitors tap a pin to see the product, add it to the cart without leaving the page, or open the product page.

There is no separate builder, shortcode or custom post type to learn. You tag products where the image already lives: in the block editor, or once in the Media Library so the photo is shoppable everywhere you use it. Turn the switch off and it is a normal image again.

Then the dashboard shows what each image actually does for your shop: views, pin opens, product clicks, add to carts, orders and revenue.

👉 **Try it before you install:** click **Live Preview** at the top of this page to open a demo shop with a shoppable lookbook and three gallery layouts.

= Who it is for =

* **Fashion and apparel stores:** turn outfit photos and lookbooks into "shop the look" pages, one pin per piece.
* **Home, furniture and decor shops:** tag every item in a styled room, from the sofa to the vase on the shelf.
* **Beauty and skincare brands:** tag the products in a routine, a flat lay or a "what's in my bag" photo.
* **Food, kitchen and recipe sites:** tag the cookware, ingredients and tableware in a recipe photo.
* **Gift guides and seasonal campaigns:** one photo per guide with every gift tagged, plus a "Shop this look" list underneath.
* **Brand merch and company stores:** show tees, hoodies, mugs and stickers styled together on a desk or in the wild.
* **Blogs that sell:** keep writing as you do now and make the photos in your posts shop for you.
* **Instagram-style feeds:** turn a gallery into a shoppable grid or a stories carousel that feels familiar to social shoppers.

= How it works =

1. Select an Image block in any post or page and switch on **Shoppable image** in the Shopnook panel.
2. Click **Tag products**, click the photo where each product is, and search for the product by name or SKU.
3. Publish. Pins appear on the photo, and the dashboard starts counting.

Prefer to tag once and reuse? Open the image in the **Media Library**, click **Tag products**, and those pins follow the image into every post where it is shoppable. If one post needs different products, click **Customize for this post** in the block settings.

The most specific setting wins: **the block in your post, then the Media Library image, then the plugin settings.**

= Features =

**Tagging**

* "Shoppable image" switch on the core Image block, and "Shoppable gallery" on the core Gallery block. No new blocks.
* Pin editor right in the block editor: click to place, drag to move, arrow keys to nudge, Delete to remove.
* Product search by name or SKU, with image, price and stock shown as you pick.
* Optional label per pin, shown on hover.
* Unlimited pins per image.
* Tag images in the Media Library and reuse the pins everywhere, or give one post its own pins.
* A "Products" column in the Media Library list shows how many products each image has.

**Pins**

* Seven styles: dot, pulsing dot, plus, number, shopping bag, price tag, or your own image (your logo works well).
* Three sizes, your own pin and icon colours, and pins always visible or only on hover.
* Hover labels with the product name.

**How the product opens**

* **Tooltip card:** a small card next to the pin.
* **Panel below the image:** the product opens under the photo, and the next pin swaps it.
* **Side drawer:** a panel slides in from the edge of the screen.
* **"Shop this look" list:** every product listed under the photo with numbered pins that highlight their product.
* **On phones:** a bottom sheet that is easy to use with one thumb, the desktop view, or a panel below the image.

**Product cards**

* Choose what the card shows: product image, name, price, sale badge, star rating, short description, Add to cart and View product.
* Your own button text and button colour.
* **Add to cart in place** for simple products, with your theme's mini cart updated and a "View cart" link.
* Variable and grouped products show "Select options" and link to the product page. External products link to their own site.
* Out of stock products are labelled, and pins for deleted or unpublished products hide themselves.

**Shoppable galleries**

* **Instagram grid:** square tiles with a product count badge. A photo opens in a popup with its pins on one side and its products on the other, like an Instagram post. Arrow keys move between photos.
* **Masonry:** Pinterest-style columns that keep each photo's shape, with the same popup.
* **Stories carousel:** tall slides visitors swipe through, with pins on every slide. Each slide crops around its pins so they stay in view.
* Set the number of columns per gallery, and the default layout, columns, spacing and product count badge for the whole site.

**Look and feel per image**

Every look setting (pin style, size, colours, display mode, phone behaviour, card fields, button text and colour) can be set site-wide, overridden for one image in the Media Library, and overridden again for one post in the block settings.

= Analytics: see what your images sell =

The Shopnook dashboard shows, for any date range and compared with the previous period:

* **Revenue** from shoppable images, front and centre.
* **Views, pin opens, product clicks, add to carts, orders and items sold.**
* A **trend chart** for each measure, with a table view.
* A **view-to-sale funnel** with the conversion rate at every step.
* **Top images, top products and top posts and galleries**, ranked by revenue.
* Orders that came from an image are marked on the WooCommerce order screen.

**How sales are credited:** when a visitor clicks a product on a shoppable image (or adds it to the cart from the card), Shopnook remembers the product and the image. If that product is bought within the credit window (7 days by default, adjustable from 1 to 90), the order line is credited to the image. The most recent click wins. Only paid orders count, and cancelled, refunded or failed orders are taken back out.

= Privacy first =

* Counts are anonymous daily totals. No personal data or IP addresses are stored.
* One first-party cookie, `shopnook_ref`, remembers which image led to a product, for the credit window only. It holds product and image IDs and nothing else.
* Works with cookie consent plugins that support the WP Consent API: nothing is counted and no cookie is set until visitors allow statistics cookies.
* Visits by logged-in editors and admins are not counted (you can change this), and neither are bots.
* Suggested text is added to your privacy policy page.
* Choose how long stats are kept: 3 months to forever.
* No third-party tracking scripts. Shoppable image stats stay in your WordPress database. The only outside services are the optional, admin-only newsletter sign-up and deactivation survey described under External services.

= Fast and light =

* Scripts and styles load only on pages with a shoppable image or gallery.
* The front-end script has no dependencies: no jQuery, no frameworks.
* Pins are added when the page renders, so they work with page caching.
* Stats are stored as small daily totals, so the table stays small even on busy shops.

= Accessible =

* Pins are real buttons with product names for screen readers.
* Full keyboard support: Tab to a pin, Enter to open, Escape to close, and focus returns to the pin.
* The drawer, bottom sheet and gallery popup trap focus while open.
* Respects "reduce motion" settings.

= Built the WordPress way =

* Uses the core Image and Gallery blocks. Deactivate Shopnook and you have ordinary images and galleries again; nothing in your content breaks.
* Works with block themes and classic themes.
* Compatible with WooCommerce High-Performance Order Storage (HPOS) and the Cart and Checkout blocks.
* Translation ready.

= For developers =

* `shopnook_product_card` filter: change the data a product card shows.
* Pins on an image are stored in the `_shopnook_image` attachment meta; per-post pins live in the block's `shopnook` attribute.
* REST routes under `shopnook/v1`.
* Development happens on [GitHub](https://github.com/sanjuacodez/shopnook). Bug reports and pull requests are welcome.

= External services =

Used only when an admin chooses to. Visitors never trigger them.

* **Newsletter (Brevo):** when an admin clicks Subscribe, their email, first name and the plugin name are sent to Brevo. [Terms](https://www.brevo.com/legal/termsofuse/), [Privacy](https://www.brevo.com/legal/privacypolicy/)
* **Deactivation survey (Google Apps Script):** when an admin clicks Send & deactivate, the reason, comment, plugin, WordPress, WooCommerce and PHP versions, admin language and, if the contact box is ticked, their name and email are sent to the author's Google Sheet. [Terms](https://policies.google.com/terms), [Privacy](https://policies.google.com/privacy)

= Support and feedback =

* Questions or problems: the [support forum](https://wordpress.org/support/plugin/shopnook-shoppable-images/).
* Code and issues: [GitHub](https://github.com/sanjuacodez/shopnook).
* More from the author: [sanjayshankar.me](https://sanjayshankar.me).
* If Shopnook helps your shop, a [review](https://wordpress.org/support/plugin/shopnook-shoppable-images/reviews/#new-post) or a [coffee](https://buymeacoffee.com/sanjayshankar) means a lot.

== Installation ==

1. Install and activate WooCommerce.
2. Go to **Plugins → Add New**, search for **Shopnook** and click **Install Now**, then **Activate**.
3. Edit a post or page, select an Image block, and switch on **Shoppable image** in the Shopnook panel. In WordPress 7.0 and later the panel is in the block sidebar's **Content** tab.
4. Click **Tag products**, click the photo to drop pins and pick a product for each.
5. Optional: choose your default pin, card and gallery styles under **Shopnook → Settings**.

== Frequently Asked Questions ==

= Do I need a special block or shortcode? =

No. Shopnook adds a switch to the core Image and Gallery blocks. Your content stays ordinary blocks.

= Where is the Shopnook panel in the block editor? =

Select an Image or Gallery block and open the block sidebar. In WordPress 7.0 and later it is in the **Content** tab; in earlier versions it is in the block **Settings**.

= I tagged products in the Media Library but the pins do not show. Why? =

Media Library pins show wherever the image is shoppable. Select the Image block in your post and switch on **Shoppable image**, then update the post. Featured images printed by your theme are not Image blocks, so they do not get pins.

= What is the difference between Media Library pins and pins in a post? =

Media Library pins are saved on the image itself and show in every post where that image is shoppable. If one post needs different products, click **Customize for this post** in the block settings. That post keeps its own pins and the others are not affected. **Use Media Library pins** switches it back.

= What happens if I deactivate the plugin? =

Your images and galleries show as normal images and galleries. Shopnook only adds pins when the page renders, so nothing in your saved content breaks.

= Which products can I tag? =

Any published WooCommerce product. Simple products can be added to the cart from the card. Variable and grouped products show "Select options" and link to the product page. External products link to their own site. Out of stock products are labelled. Pins for deleted or unpublished products are hidden automatically.

= Can I tag products from other stores, like Amazon? =

Not in this version. Shopnook tags products from your own WooCommerce store.

= Does it work with my theme? =

Shopnook works with block themes and classic themes. Pins and cards use your theme's fonts, and you set the colours. If a theme or another plugin puts a full-screen overlay or a floating button on top of the page, it can sit above the pins; tell us in the support forum and we will help.

= Does it work with page builders or the Classic Editor? =

Version 1.0 works in the block editor. Support for more editors is planned.

= Does it slow down my site? =

No. Shopnook loads its small script and stylesheet only on pages that have a shoppable image or gallery, and the script has no dependencies. Pins are part of the page HTML, so they work with page caching.

= How are sales credited to an image? =

When a visitor clicks a product on a shoppable image (or adds it to the cart from the card), Shopnook remembers the product and the image in a cookie. If that product is bought within the credit window, the order line is credited to the image. The most recent click wins. The sale is counted when the order is paid and removed if the order is cancelled, refunded or fails.

= Will my own testing inflate the numbers? =

No. Visits by logged-in users who can edit posts are not counted by default. You can change this under **Shopnook → Settings → Tracking**.

= Is it GDPR friendly? =

Shopnook stores only anonymous daily totals, never IP addresses or personal data. Its one cookie holds product and image IDs. With a consent plugin that supports the WP Consent API, nothing is tracked until visitors allow statistics cookies. Shopnook also adds suggested text to your privacy policy page.

= Can I turn tracking off? =

Yes. Switch off **Count views, clicks and sales** under **Shopnook → Settings → Tracking**. Pins and cards keep working.

= What does uninstalling remove? =

By default, deleting the plugin keeps your data in case you reinstall. Switch on **Delete all Shopnook data** under **Settings → Tracking** to remove settings, stats and the pins saved on images when the plugin is deleted.

== Screenshots ==

1. A shoppable image: tap a pin and a product card opens with the price, rating and Add to cart.
2. The "Shop this look" list: numbered pins and every product listed under the photo.
3. The Instagram-style gallery popup: the photo with its pins on one side and its products on the other.
4. The stories carousel: tall slides with pins on every slide.
5. A shoppable Instagram grid with product counts on each tile.
6. The side drawer display mode.
7. On phones, products open in a bottom sheet.
8. Tagging products: click the photo to drop pins and search products by name or SKU.
9. The Shopnook panel on the Image block, with Media Library pins and "Customize for this post".
10. Tagging an image in the Media Library, with its own look and display settings.
11. The analytics dashboard: revenue, views, clicks, the trend chart, the funnel and top images, products and posts.
12. Settings: pin styles with a live preview.
13. Settings: display modes, phone behaviour and product card fields.

== Changelog ==

= 1.0.0 =
* First release: shoppable Image and Gallery blocks, Media Library tagging, seven pin styles, four display modes plus a phone bottom sheet, Instagram, masonry and stories gallery layouts, sales attribution and the analytics dashboard.

== Upgrade Notice ==

= 1.0.0 =
First release.
