# Donations Module

**Contributors:** miguelhd  
**Donate link:** https://miguelhd.com/donate  
**Tags:** donations, PayPal, non-profit  
**Requires at least:** 5.0  
**Tested up to:** 5.8  
**Stable tag:** 1.1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

A plugin to accept donations via PayPal/Braintree for non-profits.

## Description

The Donations Module Plugin allows non-profit organizations to accept donations via PayPal on their WordPress site. It includes features such as real-time donation tracking, secure nonce verification for donation processing, and detailed error logging for troubleshooting.

## Features

- Accept donations via PayPal with customizable buttons.
- Real-time donation progress tracking.
- Secure nonce verification to prevent unauthorized submissions.
- Detailed error logging for easier troubleshooting.
- Easy integration using the `[donations_form]` shortcode.

## Installation

1. Upload the `donations-module` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.

## Frequently Asked Questions

**How do I configure the plugin?**

Navigate to the Donations Module settings page from the WordPress admin menu. Here you can set your PayPal Client ID, customize the donation button, and configure the donation progress bar.

**How do I display the donation form?**

Use the `[donations_form]` shortcode on any page or post.

**What happens if there's an error during the donation process?**

The plugin includes detailed error logging, which can be accessed in the `debug.log` file within your WordPress installation. This will help you identify and resolve any issues.

## Changelog

### 1.1.0
- Added PayPal button integration with `wp_enqueue_script` for proper loading.
- Implemented nonce verification in the `save_donation` function to enhance security.
- Added debug logging to track the donation process and troubleshoot issues.
- Updated donation progress bar and summary to reflect real-time donation totals.

### 1.0.0
- Initial release

## Upgrade Notice

### 1.1.0
- Critical security and functionality improvements. Please update to ensure your site remains secure and functional.

### 1.0.0
- Initial release
