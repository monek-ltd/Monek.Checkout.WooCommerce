# Monek.Checkout.WooCommerce
### Monek Checkout (For WooCommerce) Plugin

Enhance your online store with the Monek Checkout Plugin, a powerful and seamless integration for secure payment processing. Accept payments effortlessly, streamline transactions, and provide a smooth checkout experience for your customers.

Contents:
- Installation Instructions
- Support and Assistance
- Known Issues
- Repository Information
- Developer Information



## Installation Instructions for Monek Checkout
There are 2 ways to install the Monek Checkout plugin for WooCommerce, either through the WordPress Plugin Directory or manually by downloading the plugin files from GitHub. Follow the instructions below to install the Monek Checkout plugin on your WooCommerce store.


### Installation via WordPress Plugin Directory
In the back office of your WordPress site, you can install the Monek Checkout plugin directly from the WordPress Plugin Directory. Simply browse for the Monek Checkout plugin and install it with a few clicks. This is the easiest way to install the plugin, but we also provide a manual installation guide below.

### Manual Installation Guide for Monek Checkout

#### Introduction
This guide provides step-by-step instructions for manually installing the Monek Gateway plugin for WooCommerce. Ensure that you have the necessary permissions and backups before proceeding.

#### Prerequisites
- WordPress installed and activated.
- *Permalink structure setting not set to "plain". *(see known issue 2)
- WooCommerce plugin installed and activated.
- FTP client (e.g., FileZilla) or access to your web server's file manager.

#### Step 1: Download Monek Checkout from GitHub
Visit the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/) where the Monek Checkout plugin is hosted and download the [latest version](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/releases/latest) of the plugin files. Github should allow you to download the repository as a ZIP archive.


#### Step 2: Extract Plugin Files
After downloading the [latest version](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/releases/latest) Monek Checkout plugin from the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/), extract the contents of the ZIP file to your local machine. 

This will reveal the plugin files and folders, Locate the version you want to install under tags and then copy the plugin files. (Look for the "monek-checkout" folder, there is also a copy under trunk but this is considered a development version and is not recommended to use.)

You will need to use all the files and folders in the "monek-checkout" folder for the installation. 


#### Step 3: Connect to Your WordPress Site
Use your preferred FTP client or your web server's file manager to connect to your WordPress site. If you're not familiar with connecting via FTP, refer to your hosting provider's documentation for guidance. You'll need your FTP credentials (hostname, username, password).

If you're using an FTP client like FileZilla, enter the provided credentials and connect to your server. If you're using a web server's file manager, log in to your hosting account and locate the file manager tool.

(It is also possible to rezip just the "monek-checkout" folder and upload it directly to the site via the plugins tab of your site, if you do this skip the remaining steps and head straight to configuration.)


#### Step 4: Navigate to the Plugins Directory
Once connected to your WordPress site via FTP or file manager:

Navigate to the wp-content/plugins/ directory on your WordPress installation. This is the location where WordPress stores all plugin files.

Locate and access the plugins directory to prepare for uploading the Monek Checkout plugin.


#### Step 5: Upload the Monek Checkout Plugin
With the wp-content/plugins/ directory open, upload the extracted Monek Checkout plugin folder "monekcheckout" to this location.

If using an FTP client like FileZilla, drag and drop the folder from your local machine to the plugins directory on the server.

If using a web server's file manager, look for an "Upload" or "Import" option and select the Monek Checkout plugin folder from your local machine.

Ensure that folder "monek-checkout" is successfully uploaded to the wp-content/plugins/ directory.


#### Step 6: Activate the Monek checkout Plugin
Log in to your WordPress admin dashboard.

Navigate to "Plugins" from the left sidebar.

In the list of plugins, find "Monek Checkout."

Click the "Activate" link below the plugin name.



#### Step 7: Configure the Monek Checkout
After activation, navigate to "WooCommerce" > "Settings" from the left sidebar.

Go to the "Payments" tab.

In the list of available payment gateways, locate "Monek."

Click on "Manage" to access its configuration settings.

Fill in the required details:
- Enable/Disable: Toggle to enable the WooCommerce Monek Gateway.
- Monek ID: Enter the Monek ID provided to you.
  
Save changes to apply the configuration.

If you don't have the necessary information, such as your Monek ID, visit [Monek Contact Page](https://monek.com/contact) to get help. Ensure that all information entered is accurate to enable seamless payment processing on your WooCommerce store.


## Support and Assistance

Please feel free to contact Monek Support for any assistance or inquiries related to the Monek Checkout plugin. Our team is available to help you with installation, configuration, and troubleshooting to ensure a seamless payment processing experience for your online store.

For support, visit the [Monek Contact Page](https://monek.com/contact) or email us at support@monek.com



### Frequently Asked Questions:

#### Question 1: Why is my order in 'Pending Payment' and not cancelling when the user fails to complete the payment?
Pending Payment means the order has been received, but no payment has been made. Pending payment orders are generally awaiting customer action. 

If the customer fails to complete the payment, the order will remain in 'Pending Payment' status until it is manually cancelled or the payment is completed unless the timeout period is reached. 

The timeout period is set in the WooCommerce settings. To change the timeout period, navigate to WooCommerce > Settings > Products > Inventory > Hold Stock. Adjust the timeout period as needed.

This is a WooCommerce default behavior and not specific to the Monek Gateway plugin.



### Known Issues and Solutions

#### Known Issue 1: Permalink Structure Conflict Resulting in 3005 Error

When configuring your WooCommerce site, it's crucial to be aware of a known issue related to the permalink structure setting. Specifically, if your permalink structure is set to 'Plain,' it can lead to conflicts with a setting in the transaction call, resulting in a 3005 error from HTTP requests to the Monek payment page.

##### Issue Description:
###### - Affected Versions:
All WooCommerce + Wordpress versions may be affected if the permalink structure is set to 'Plain.'
###### - Impact:
The 3005 error occurs when the Monek Gateway rejects the return URL passed to it due to the presence of a query string. This issue arises when the permalink structure is set to 'Plain,' affecting the ability to redirect to complete transactions on the Monek payment page.

##### Solution:
To resolve the 3005 error and ensure seamless transactions with the Monek Gateway, it is recommended to update the permalink structure setting. Follow these steps:

##### Update Permalink Structure:

1. Navigate to your WordPress admin dashboard.
2. Go to Settings > Permalinks.
3. Choose a permalink structure other than 'Plain.' Options like 'Post name' or 'Day and name' are commonly used without causing conflicts.
4. Save the changes.

###### Warning:
Updating the permalink structure can potentially cause issues on your site. Ensure that you carefully assess the impact of this change, particularly if your site relies on specific URL structures. It's advisable to test thoroughly in a staging environment before implementing changes on a live site.

By updating the permalink structure, you ensure that the Monek Gateway can handle the return URL correctly, preventing the occurrence of the 3005 error.

###### Note:
If your permalink structure is already set to a non-'Plain' option, or if your site is using a custom permalink structure, this issue does not apply.



#### Known Issue 2: New Beta Product Page does not display Consignment Selection
When setting up a new product page in WooCommerce, you may encounter an issue where the consignment selection is not displayed on the product page. This issue can prevent customers from choosing the desired consignment option when purchasing the product.

##### Issue Description:
###### - Affected Versions:
From WooCommerce 7.9, anyone opted-in to test the new product form currently in beta. The new product management experience is available on WooCommerce stores as an opt-in feature for simple and variable physical products.
###### - Impact:
Unable to select the consignment option during the product setup, leading to incomplete product configurations and potential issues during checkout.

##### Solution:
With the current beta stage, you cannot use extensions that rely on product listings, such as Monek Checkout. We will continue to roll out additional features to support products with variations, extensibility, and many other features.

To resolve this issue please dsiable the new product form and use the classic product form.

##### Disable new product form:
You can disable the New Product Form by following the below steps:

Navigate to WooCommerce > Settings > Advanced > Features.
Uncheck �Try new product editor (Beta)� and save changes.

###### Note:
We will continue to monitor the progress of the new product form and provide updates on compatibility with the Monek Checkout plugin in the future as plugin support is added.



#### Known Issue 3: Payment Option Does Not Appear In Admin Dashboard When Using Wordpress Multisite Integration
If using this plugin with a wordpress multisite you may notice the following issue, with WooCommerce & Monek Checkout plugins installed and network activated, The Monek payment option does not appear within the WooCommerce payment methods list in the admin dashboard. This issue has been solved, please refer to the solution below.


##### Issue Description:
###### - Affected Versions:
All WooCommerce + Wordpress versions may be affected as the exact cause is unknown.
###### - Impact:
The Monek payment option does not appear within the WooCommerce payment methods list in the admin dashboard.


##### Solution:
Solution detailed on this [support thread](https://wordpress.org/support/topic/not-working-on-multisite-42/)

The plugin is not correctly detected when the WooCommerce plugin is network activated, to get round this activate individually on each site that is part of the multisite network. 

- Ensure WooCommerce is not network activated
- Activate WooCommerce on each site one at a time (Do not network activate)
- Activate Monek Checkout on each site one at a time (Do not network activate)
- Monek Checkout should now appear as a WooCommerce payment option within the settings



## Repository Information
This repository contains the source code for the Monek WooCommerce Plugin, a payment gateway integration for WooCommerce stores. The plugin enables secure payment processing, seamless transactions, and enhanced checkout experiences for online shoppers. This repository is maintained both using GitHub and SVN, allowing for version control and distribution through the WordPress Plugin Directory. 

Developers can contribute to the plugin's development, report issues, and suggest improvements through GitHub. For users, the repository provides access to the latest releases, installation instructions, known issues, and support details.

The SVN repository is used to push updates to the WordPress Plugin Directory, ensuring that users can easily install and update the Monek WooCommerce Plugin directly from their WordPress admin dashboard.

### Repository Contents:
- Assets: Contains images and icons used in the plugin.
- Tags: Contains versions of the plugin for download.
- Trunk: Contains development versions of the plugin.
- README.md: Provides information about the plugin, installation instructions, known issues, and support details.

### Repository Links:
- GitHub Repository: [Monek.Checkout.WooCommerce](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/)
- Wordpress SVN URL: [monek-checkout - Wordpress.org](https://plugins.svn.wordpress.org/monek-checkout)

### Plugin Links:
- Latest GitHub Release: [Monek Checkout - Releases (Latest)](https://github.com/monek-ltd/Monek.Checkout.WooCommerce/releases/latest)
- Wordpress Public URL: [Monek Checkout](https://wordpress.org/plugins/monek-checkout/)



## Developer Information

The Monek WooCommerce Plugin is developed and maintained by Monek Ltd, If you are a Monek developer looking for further information on the plugin's codebase, structure, or functionality, please refer to the following details:


### Important Information:
Should any issues arrise with the plugin, it is possible that the plugin will be closed until the issues are resolved. Please ensure that we are vigilant in maintaining the plugin and keeping it up to date as well as tackling any issues as they arrise.

Any code in trunk is considered development code but any code pushed to trunk should still be in a release ready state.

Before any release, please ensure that the following steps are taken:
- The plugin follows WordPress coding standards and best practices for secure and efficient development. Please refer to the [guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) here before crafting a new release. 
- Also ensure that any release is checked using the [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool before crafting a release.


### Developer Resources:

#### SVN Tips and Tricks

If you're new to SVN, it can be a little weird to get used to. Unlike Git repositories, SVN is a release system. This means you should only be pushing ready-to-be-used versions of your plugin. You can use any SVN Client you like, but we recommend TortoiseSVN for windows.

To get started, you should read this:

https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

Some common points of confusion are:

- SVN user IDs are the same as your WordPress.org login ID - not your email address
- User IDs are case sensitive (if your account is JoDoe123HAY then you must use that exact ID)
- You can set up your SVN credentials (if you haven't already) in the �Account & Security� section of your WordPress profile - https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
- Your readme content determines what information is shown on your WordPress.org public page - https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Your plugin banners, screenshots, and icons are handled via the special plugin assets folder - https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

#### Useful Links:
- [Using Subversion with the WordPress Plugin Directory](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [FAQ about the WordPress Plugin Directory](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [WordPress Plugin Directory readme.txt standard](https://wordpress.org/plugins/developers/#readme)
- [A readme.txt validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Assets (header images, etc)](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [WordPress Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Block Specific Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/block-specific-plugin-guidelines/)
- [Important notifications and updates](https://make.wordpress.org/plugins/)
- [Development Log](https://plugins.trac.wordpress.org/log/monek-checkout/)