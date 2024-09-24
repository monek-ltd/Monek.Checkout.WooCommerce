=== Monek Checkout ===
Contributors: humberstone83
Tags: Credit Card Payments, Monek, Monek Checkout, Monek Gateway, Monek Payments
Requires at least: 5.0
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 3.2.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monek Checkout for WooCommerce integrates seamlessly for payments, allowing customers to pay with debit/credit cards. 

== Description ==

Monek Checkout for WooCommerce integrates seamlessly for payments, allowing customers to pay with debit/credit cards. Install the plugin, create a Monek account, or connect an existing one to start accepting payments

✓ Streamlined checkout experience specifically crafted to minimize cart abandonment rates, it ensures a smooth transaction process for both merchants and customers.

✓ Designed with a mobile-first approach, our checkout page guarantees an intuitive and user-friendly shopping experience across all devices. This ensures that customers can easily complete their purchases from anywhere, at any time, contributing to higher satisfaction and increased sales for merchants. Whether customers are shopping from their smartphones, tablets, or desktops, the checkout process remains consistently efficient and hassle-free.

✓ Advanced security measures and multiple payment options, our platform builds trust and convenience, encouraging repeat business and fostering customer loyalty. In essence, we provide merchants with a robust, adaptable solution to enhance their online sales and grow their business in the digital marketplace.

= Benefits =
Provides a payment method suitable to your customers

= Features =
✓ Product List: Display a list of items in the cart, including product images, names, quantities, and prices.
✓ Subtotal, Shipping, and Taxes: Clearly display the subtotal, estimated shipping costs, and taxes. Update these in real-time as customers make changes.
✓ Total Price: Highlight the total price prominently.
✓ Contact Information: Require an email address or phone number to send order confirmations and updates
✓ Shipping Address: Collect the full shipping address, including name, street address, city, state, ZIP code, and country.
✓ Shipping Method: Offer various shipping options (e.g., standard, express) with corresponding prices and delivery estimates.
✓ Address Verification: Implement address verification to minimize errors and ensure accurate delivery.
✓ Billing Address: Provide an option to use the shipping address as the billing address or enter a separate one.
✓ Payment Methods: Offer multiple payment options such as credit/debit cards, PayPal, digital wallets (e.g., Apple Pay)
✓ Credit Card Details: Require card number, expiration date, security code (CVV), and name on the card.
✓ Secure Payment Badge: Display security badges (e.g., SSL certificate, trusted payment logos) to reassure customers about the safety of their payment information.
✓ Review Order: Present a final review of the order, including items, shipping method, billing, and shipping addresses.
✓ Promotional Codes: Allow customers to view discount or promo codes.
✓ Terms and Conditions: Include a checkbox to confirm acceptance of terms and conditions or the return policy before completing the purchase.
✓ Pay Button: Highlight the \"Pay\" button, making it clear and easy to find.
✓ Thank You Message: Redirect to the standard confirmation message once the purchase is complete.
✓ Mobile-Friendly: Ensure the checkout page is responsive and functions smoothly on mobile devices and tablets.

= What your customers will like =
Convenience and Speed
✓ Quick Transactions: A streamlined checkout process reduces the time it takes to complete a purchase, making it easy and fast for customers to buy what they want.
✓ Guest Checkout Options: Allowing customers to checkout without creating an account saves time and reduces friction, especially for first-time or one-time buyers.
✓ Autofill and Saved Information: Features like autofill for address and payment information help speed up the process, especially for returning customers who have saved their details.

Clarity and Transparency
✓ Clear Pricing: Customers benefit from transparent pricing, where all costs (including taxes, shipping, and any additional fees) are displayed upfront, preventing unexpected surprises at the end.
✓ Order Summary: Providing a detailed order summary helps customers verify their selections, ensuring they are purchasing exactly what they intend to.

Security and Trust
✓ Secure Transactions: Displaying security certifications and using secure payment gateways reassure customers that their personal and financial information is protected.
✓ Privacy Protection: Ensuring customer data is handled with care builds trust and encourages customers to complete their transactions.

Flexibility and Options
✓ Multiple Payment Methods: Offering a variety of payment options, such as credit/debit cards, PayPal, and digital wallets, caters to different customer preferences and increases the likelihood of completing a purchase.

User-Friendly Experience
✓ Responsive Design: A checkout process that works seamlessly across devices (desktop, mobile, tablet) ensures customers can complete purchases easily, regardless of how they access the site.
✓ Easy Navigation: A simple, intuitive layout helps customers move through the checkout steps without confusion or frustration.

Reduced Abandonment Rates
✓ Simplified Process: Minimizing the number of steps and removing unnecessary fields reduces the likelihood of cart abandonment, ensuring customers follow through with their purchases.

Support and Assistance
✓ Error Handling: Clear error messages and guidance on how to correct mistakes help prevent customer frustration and abandonment.

== Installation ==
= Automatic Installation =
The automatic installation process is the most convenient method for adding WooCommerce to your WordPress site, as it eliminates the need for manual file handling. With this option, WordPress manages the entire file transfer process, allowing you to complete the installation without leaving your web browser.

To begin, log in to your WordPress dashboard, navigate to the “Plugins” menu, and click on “Add New.” In the search box that appears, type “Monek Checkout” and press “Search Plugins.” Once you locate Monek Checkout in the search results, you’ll be able to view key details about the plugin, including the current version, user ratings, and a brief description of its features. When you\'re ready to proceed, simply click the “Install Now” button, and WordPress will automatically handle the installation for you.

= Manual Installation =
If you prefer to install Monek Checkout manually, this method requires downloading the plugin directly from the WordPress plugin repository and uploading it to your web server via an FTP client. To start, download the Monek Checkout plugin file to your computer. Next, use an FTP application of your choice to upload the plugin files to the appropriate directory on your web server.

For detailed instructions on how to carry out a manual installation, refer to the WordPress Codex, which provides comprehensive guidance on the process. https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation

Configure Monek Settings:
- Navigate to WooCommerce > Settings > Payments.
- Find Monek Checkout in the list of available payment methods and click \'Manage\'.
- Enter your Monek ID (available from your Monek account) into the settings.
- Save changes to enable Monek Checkout on your WooCommerce store.

Start Accepting Payments:
- Once configured, Monek Checkout will seamlessly integrate into your checkout process.
- Customers can now securely pay using credit and debit cards via Monek.

Note: If you do not have a Monek ID, please visit Monek\'s website to sign up and obtain your ID. For any assistance with setup or integration, contact Monek directly through their website.

Experience the future of payment processing with Monek where simplicity meets sophistication.

Other Configuration Settings:

Enable GooglePay: 
Indicates if the Google Pay™ button will appear on the checkout page. `YES` or `NO` (default)

All merchants must adhere to the Google Pay APIs [Acceptable Use Policy](https://payments.developers.google.com/terms/aup) and accept the terms defined in the Google Pay API [Terms of Service](https://payments.developers.google.com/terms/sellertos). 

Google Pay is a trademark of Google LLC.

== Frequently Asked Questions ==
Why is my order in \'Pending Payment\' and not cancelling when the user fails to complete the payment?
Pending Payment means the order has been received, but no payment has been made. Pending payment orders are generally awaiting customer action. 

If the customer fails to complete the payment, the order will remain in \'Pending Payment\' status until it is manually cancelled or the payment is completed unless the timeout period is reached. 

The timeout period is set in the WooCommerce settings. To change the timeout period, navigate to WooCommerce > Settings > Products > Inventory > Hold Stock. Adjust the timeout period as needed.

This is a WooCommerce default behavior and not specific to the Monek Gateway plugin.

== Screenshots ==
1. Checkout Page
2. Plugin Configuration Settings
3. Payment Options

== Changelog ==

=3.2.1=
*Release Date - 2024-09-12*

* Added - Added some webhook logging to help with debugging
* Fixed - Removed webhook response method check to fix random method not allowed error

=3.2.0=
*Release Date - 2024-09-09*

* Added - Added a new feature to allow the user to enable GooglePay as a payment method
* Fixed - If no shipping options set, fixed trying to access empty array
* Added - Added FAQ to repo README.md
* Fixed - Made the GB Postcode validation case insensitive. 

=3.1.0=
*Release Date - 2024-08-28*

* Update - Updated text-domain to match slug. Updated text-domain in all files
* Added - Added additional sanitisations to input fields
* Update - Updated names to use a unique prefix to avoid conflicts with other plugins
* Update - Updated the callback identification check
* Fixed - Prevented direct access to plugin files

=3.0.3=
*Release Date - 2024-08-23*

* Fixed - Sanitized input fields
* Fixed - Changed to use wp_safe_redirect
* Fixed - Changed root folder to match slug monek-checkout
* Update - Updated to use new PHP 7.4 features and updated required PHP version
* Added - Added PHPDoc comments to classes and functions
* Tweak - Extracted some logic into new classes and created new folder structure to uncrowd the root folder
* Tweak - Cleaned up some of the naming conventions

=3.0.2=
*Release Date - 2024-08-14*

* Fixed - Trim basket descriptions

=3.0.1=
*Release Date - 2024-07-23*

* Tweak - Add nonce tokens

=3.0.0=
*Release Date - 2024-07-16*

* Feature - Implement new checkout page
* Remove - Remove deprecated methods
* Update - Change plugin name
* Added - readme.txt file
* Added - reference to license

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/monek-checkout/trunk/changelog.txt) delivered with the plugin.