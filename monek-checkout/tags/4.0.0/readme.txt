=== Monek Checkout ===
Contributors: humberstone83
Tags: credit card, payments, monek, woocommerce
Requires at least: 6.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monek Checkout connects your WooCommerce store to Monek’s secure payment platform. Version 4.0 introduces the WooCommerce Checkout Blocks experience and retires the legacy shortcode-based checkout so merchants get a faster, more reliable checkout flow.

== Description ==

The plugin mounts Monek’s hosted payment fields and optional express wallets directly inside the WooCommerce Checkout Blocks experience. Customers stay on your site while their card details are captured securely. Merchants benefit from a guided setup that walks them through collecting API keys, enabling Apple Pay, and optionally confirming payments via webhook.

Key features:
* Secure embedded payment fields rendered inside WooCommerce Checkout Blocks.
* Optional Apple Pay and other express wallets when configured in Odin.
* Payment Confirmed order status triggered by verified webhooks for added assurance.
* Simple configuration steps designed for non-technical store owners.

== Installation ==

1. Install the plugin from **Plugins → Add New** by searching for "Monek Checkout" (or upload the ZIP from GitHub).
2. Activate the plugin.
3. Visit **WooCommerce → Settings → Payments** and click **Monek**.
4. Enable the payment method and save. Continue with the configuration steps below to enter your credentials.

== Configuration ==

=== 1. Gather your API credentials from Odin ===
1. Sign in to the [Odin merchant portal](https://merchant.odin.com/) and open the **Integrations** tab.
2. Locate or create an access key for WooCommerce.
3. Copy the **publishable (public) key** and **secret key**.
4. (Optional) If you plan to offer Apple Pay, add your website domain to this access key before leaving the page.

=== 2. Add the credentials in WooCommerce ===
1. In WordPress, go to **WooCommerce → Settings → Payments** and select **Monek**.
2. Enter your **Monek ID**, **publishable key**, and **secret key**.
3. Save your changes.

=== 3. (Optional) Enable Apple Pay ===
1. Ensure your website domain is listed against the access key you created in Odin.
2. Test checkout from a supported Apple device or browser. The Apple Pay button will appear automatically when Apple confirms the domain.

=== 4. (Optional) Configure a webhook for Payment Confirmed ===
1. In Odin, create or edit an SVIX webhook in the **Integrations** tab.
2. Set the destination URL to your site domain with `/wp-json/monek/v1/webhook` appended (for example, `https://example.com/wp-json/monek/v1/webhook`). The plugin exposes this REST endpoint automatically.
3. (Optional) Enter the **webhook endpoint signing secret** in the plugin settings to verify that incoming events are from SVIX. If you leave this field blank, the plugin treats every webhook as valid.
4. When a verified webhook confirms payment, the plugin can move the order into the **Payment Confirmed** status. Use this status to track orders that have securely completed the Monek payment flow.

== Frequently Asked Questions ==

= Do I have to set up a webhook? =
No. The webhook and signing secret are optional. Without a signing secret, webhooks are automatically trusted, and the order can still move to **Payment Confirmed** when the event is received.

= Can I keep using the classic WooCommerce checkout? =
No. Version 4.0 requires WooCommerce Checkout Blocks and no longer supports the legacy shortcode-based checkout. Update WooCommerce to the latest version and enable Blocks to take advantage of the improved experience.

== Support ==

Need help? Contact [Monek Support](https://monek.com/contact) or visit the [WordPress.org support forum](https://wordpress.org/support/plugin/monek-checkout/).

== Changelog ==

= 4.0.0 =
*Breaking change release requiring WooCommerce Checkout Blocks.*

* Introduced the new checkout experience powered by WooCommerce Checkout Blocks.
* Added clear steps for collecting publishable and secret keys from Odin.
* Documented optional Apple Pay domain setup and SVIX webhook configuration.
* Added the Payment Confirmed status to help merchants track securely verified payments.
