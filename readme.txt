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

Shopnook turns the images in your posts and pages into shoppable images. Switch on "Shoppable image" on any Image block, click the photo to drop pins, and pick a WooCommerce product for each. Visitors tap a pin to see the product, add it to the cart without leaving the page, or open the product page.

There is no separate builder, shortcode or custom post type. You tag products right where the image lives: in the block editor, or once in the Media Library so the image is shoppable everywhere you use it. Turn the switch off and it is a normal image again.

👉 **Try it before you install:** click **Live Preview** at the top of this page to open a demo shop with a shoppable lookbook and galleries.

= Tag products where your images already are =

* **On the core Image block:** a "Shoppable image" switch in the block sidebar. No new block to learn, and your images stay ordinary Image blocks.
* **In the Media Library:** open any image and click **Tag products**. Those pins show wherever the image is shoppable.
* **Per post:** give an image its own pins and look in one post without touching the others.
* **Unlimited pins per image.** Click to place, drag to move, arrow keys to nudge.

The most specific setting wins: the block in your post, then the Media Library image, then the plugin settings.

= Pins and product cards your way =

* **Pin styles:** dot, pulsing dot, plus, number, shopping bag, price tag, or your own image (a logo works well).
* **Size, colours, and pins always visible or only on hover.**
* **How the product opens:** a tooltip card next to the pin, a panel below the image, a side drawer, or a "Shop this look" list under the image with numbered pins.
* **On phones:** a bottom sheet that is easy to use with one thumb, the desktop view, or a panel below the image.
* **Choose what the card shows:** product image, name, price, sale badge, star rating, short description, Add to cart and View product, with your own button text and colour.
* **Add to cart in place** for simple products, with your theme's mini cart updated. Variable and grouped products link to the product page.

= Shoppable galleries =

Switch on "Shoppable gallery" on the core Gallery block and pick a layout:

* **Instagram grid:** square tiles with a product count. A photo opens in a popup with its pins on one side and its products on the other, like an Instagram post.
* **Masonry:** Pinterest-style columns that keep each photo's shape, with the same popup.
* **Stories carousel:** tall slides visitors swipe through, with pins on every slide.

= Analytics: see what your images sell =

The Shopnook dashboard shows views, pin opens, product clicks, add to carts, orders, items sold and revenue for any date range, compared with the previous period. A trend chart, a view-to-sale funnel and tables of your top images, products, posts and galleries show what is working.

Sales are credited to an image when a visitor clicks a product on it and buys that product within the credit window (7 days by default, last click wins). Only paid orders count, and cancelled or refunded orders are taken back out.

= Privacy first =

* Counts are anonymous daily totals. No personal data or IP addresses are stored.
* One first-party cookie remembers which image led to a product, for the credit window only.
* Works with cookie consent plugins that support the WP Consent API: nothing is counted and no cookie is set until visitors allow statistics cookies.
* Your own visits as a logged-in editor or admin are not counted, and neither are bots.

= Built for WordPress =

* Uses core blocks, so deactivating Shopnook leaves ordinary images and galleries.
* No build step, no jQuery on the front end, and scripts load only on pages with a shoppable image.
* Keyboard and screen reader friendly pins, cards and popups.
* Compatible with WooCommerce High-Performance Order Storage and the Cart and Checkout blocks.

== Installation ==

1. Install and activate WooCommerce.
2. Install Shopnook from **Plugins → Add New** and activate it.
3. Edit a post or page, select an Image block, and switch on **Shoppable image** in the Shopnook panel.
4. Click **Tag products**, click the image to drop pins and pick a product for each.
5. Choose your default pin and card styles under **Shopnook → Settings**.

== Frequently Asked Questions ==

= Do I need a special block or shortcode? =

No. Shopnook adds a switch to the core Image and Gallery blocks. Your content stays ordinary blocks.

= What is the difference between Media Library pins and pins in a post? =

Media Library pins are saved on the image itself and show in every post where that image is shoppable. If one post needs different products, click **Customize for this post** in the block settings. That post keeps its own pins and the others are not affected.

= What happens if I deactivate the plugin? =

Your images and galleries show as normal images and galleries. Shopnook only adds pins when the page renders, so nothing in your saved content breaks.

= Which products can I tag? =

Any published WooCommerce product. Simple products can be added to the cart from the card. Variable and grouped products show "Select options" and link to the product page. External products link to their own site. Out of stock products are labelled. Pins for deleted or unpublished products are hidden automatically.

= Does it work with my theme? =

Shopnook works with block themes and classic themes. Pins and cards inherit your theme's fonts and you can set the colours.

= Does it work with page builders or the Classic Editor? =

Version 1.0 works in the block editor. Support for more editors is planned.

= How are sales credited to an image? =

When a visitor clicks a product on a shoppable image (or adds it to the cart from the card), Shopnook remembers the product and the image in a cookie. If that product is bought within the credit window, the order line is credited to the image. The most recent click wins. The sale is counted when the order is paid and removed if the order is cancelled, refunded or fails.

= Will my own testing inflate the numbers? =

No. Visits by logged-in users who can edit posts are not counted by default. You can change this under **Shopnook → Settings → Tracking**.

= Is it GDPR friendly? =

Shopnook stores only anonymous daily totals, never IP addresses or personal data. Its one cookie holds product and image IDs. With a consent plugin that supports the WP Consent API, nothing is tracked until visitors allow statistics cookies. Shopnook also adds suggested text to your privacy policy page.

= What does uninstalling remove? =

By default, deleting the plugin keeps your data in case you reinstall. Switch on **Delete all Shopnook data** under **Settings → Tracking** to remove settings, stats and the pins saved on images when the plugin is deleted.

== Screenshots ==

1. A shoppable image with a tooltip product card.
2. The "Shop this look" list with numbered pins.
3. An Instagram-style shoppable gallery and its popup.
4. The stories carousel.
5. Tagging products in the block editor.
6. The analytics dashboard.
7. Pin and card settings.

== Changelog ==

= 1.0.0 =
* First release: shoppable Image and Gallery blocks, Media Library tagging, pin styles, four display modes, Instagram, masonry and stories gallery layouts, sales attribution and the analytics dashboard.

== Upgrade Notice ==

= 1.0.0 =
First release.
