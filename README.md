# Monek.Checkout.WooCommerce
### Monek WooCommerce Plugin

Enhance your online store with the Monek WooCommerce Plugin, a powerful and seamless integration for secure payment processing. Accept payments effortlessly, streamline transactions, and provide a smooth checkout experience for your customers.




## Manual Installation Guide for Monek Gateway in WooCommerce

### Introduction
This guide provides step-by-step instructions for manually installing the Monek Gateway plugin for WooCommerce. Ensure that you have the necessary permissions and backups before proceeding.

### Prerequisites
- WordPress installed and activated.
- *Permalink structure setting not set to "plain". *(see known issue 2)
- WooCommerce plugin installed and activated.
- FTP client (e.g., FileZilla) or access to your web server's file manager.

### Step 1: Download the WooCommerce Monek Gateway Plugin from GitHub
Visit the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/) where the WooCommerce Monek Gateway plugin is hosted and download the plugin files. Github should allow you to download the repository as a ZIP archive.


### Step 2: Extract Plugin Files
After downloading the WooCommerce Monek Gateway plugin from the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/), extract the contents of the ZIP file to your local machine. This will reveal the plugin files and folders.


### Step 3: Connect to Your WordPress Site
Use your preferred FTP client or your web server's file manager to connect to your WordPress site. If you're not familiar with connecting via FTP, refer to your hosting provider's documentation for guidance. You'll need your FTP credentials (hostname, username, password).

If you're using an FTP client like FileZilla, enter the provided credentials and connect to your server. If you're using a web server's file manager, log in to your hosting account and locate the file manager tool.


### Step 4: Navigate to the Plugins Directory
Once connected to your WordPress site via FTP or file manager:

Navigate to the wp-content/plugins/ directory on your WordPress installation. This is the location where WordPress stores all plugin files.

Locate and access the plugins directory to prepare for uploading the WooCommerce Monek Gateway plugin.


### Step 5: Upload the WooCommerce Monek Gateway Plugin
With the wp-content/plugins/ directory open, upload the extracted WooCommerce Monek Gateway plugin folder to this location.

If using an FTP client like FileZilla, drag and drop the folder from your local machine to the plugins directory on the server.

If using a web server's file manager, look for an "Upload" or "Import" option and select the WooCommerce Monek Gateway plugin folder from your local machine.

Ensure that all files and folders within the WooCommerce Monek Gateway plugin are successfully uploaded to the wp-content/plugins/ directory.



### Step 6: Activate the WooCommerce Monek Gateway Plugin
Log in to your WordPress admin dashboard.

Navigate to "Plugins" from the left sidebar.

In the list of plugins, find "WooCommerce Monek Gateway."

Click the "Activate" link below the plugin name.


Once activated, you should see a success message indicating that the WooCommerce Monek Gateway plugin is now active.


### Step 7: Configure the WooCommerce Monek Gateway
After activation, navigate to "WooCommerce" > "Settings" from the left sidebar.

Go to the "Payments" tab.

In the list of available payment gateways, locate "Monek."

Click on "Manage" to access its configuration settings.

Fill in the required details:
- Enable/Disable: Toggle to enable the WooCommerce Monek Gateway.
- Monek ID: Enter the Monek ID provided to you.
- Echo Check Code (if applicable): If you have a transaction response echo set up, enter the provided echo check code.

Save changes to apply the configuration.

If you don't have the necessary information, such as the Monek ID or Echo Check Code, visit [Monek Contact Page](https://monek.com/contact) to get help. Ensure that all information entered is accurate to enable seamless payment processing on your WooCommerce store.





## Known Issue 1: Compatibility with WooCommerce Cart and Checkout Blocks
Starting with WooCommerce version 8.3, the Cart and Checkout Blocks become the default for new installations. However, this change may prevent the Monek Gateway from appearing in the checkout section.

### Issue Description:
#### - Affected Versions: 
WooCommerce 8.3 and later for new installations.
#### - Impact: 
The Monek Gateway may not be visible in the checkout section due to the default Cart and Checkout Blocks.
### Solution:
To resolve this issue and ensure the Monek Gateway appears on the checkout page, follow these steps:

### Revert to Classic Checkout Shortcode:

If you are experiencing this issue, you can revert to the classic checkout shortcode. This step ensures compatibility with the Monek Gateway.

Open the page where you have the checkout, likely titled "Checkout."
Replace any existing WooCommerce block with the classic shortcode `[woocommerce_checkout]`.
For more information on managing the Cart and Checkout Blocks, refer to the [WooCommerce documentation](https://woo.com/document/cart-checkout-blocks-status/#section-11).

#### Note: 
If your site is already using the classic checkout shortcode or is updating from an older version of WooCommerce, this issue does not apply.



## Known Issue 2: Permalink Structure Conflict Resulting in 3005 Error

When configuring your WooCommerce site, it's crucial to be aware of a known issue related to the permalink structure setting. Specifically, if your permalink structure is set to 'Plain,' it can lead to conflicts with a setting in the transaction call, resulting in a 3005 error from HTTP requests to the Monek payment page.

### Issue Description:
#### - Affected Versions:
All WooCommerce versions may be affected if the permalink structure is set to 'Plain.'
#### - Impact:
The 3005 error occurs when the Monek Gateway rejects the return URL passed to it due to the presence of a query string. This issue arises when the permalink structure is set to 'Plain,' affecting the ability to redirect to complete transactions on the Monek payment page.

### Solution:
To resolve the 3005 error and ensure seamless transactions with the Monek Gateway, it is recommended to update the permalink structure setting. Follow these steps:

### Update Permalink Structure:

1. Navigate to your WordPress admin dashboard.
2. Go to Settings > Permalinks.
3. Choose a permalink structure other than 'Plain.' Options like 'Post name' or 'Day and name' are commonly used without causing conflicts.
4. Save the changes.

#### Warning:
Updating the permalink structure can potentially cause issues on your site. Ensure that you carefully assess the impact of this change, particularly if your site relies on specific URL structures. It's advisable to test thoroughly in a staging environment before implementing changes on a live site.

By updating the permalink structure, you ensure that the Monek Gateway can handle the return URL correctly, preventing the occurrence of the 3005 error.

#### Note:
If your permalink structure is already set to a non-'Plain' option, or if your site is using a custom permalink structure, this issue does not apply.
