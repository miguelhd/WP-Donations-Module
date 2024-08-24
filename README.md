
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

### New Features:
- **Donation Logging:** Automatically log and track donations in the database.
- **Real-Time Donation Metrics:** The plugin now updates the total amount raised and the number of donations in real-time.
- **Security:** Enhanced security with nonce verification to ensure only legitimate donations are processed.
- **Error Logging:** Detailed logging of errors for easier troubleshooting and debugging.
- **Updated JSON Responses:** Enhanced the structure of JSON responses to include the current total donations.
- **Improved Error Handling:** Refined error handling for nonce verification and database insertion.
- **Changed SDK:** Transitioned from the PayPal general JavaScript SDK to the Donate SDK.

## Features

- Accept donations via PayPal.
- Real-time donation progress tracking.
- Secure nonce verification to prevent unauthorized submissions.
- Detailed error logging for easier troubleshooting.
- Easy integration using the `[donations_form]` shortcode.

## Installation

1. Upload the `donations-module` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Use the `[donations_form]` shortcode to display the donation form on your site.

## Usage

### Shortcode `[donations_form]`

Place this shortcode on any page or post where you want to display the donation form.

### Handling Donations

When a user submits a donation:
- The plugin validates and sanitizes the input data.
- It checks for a valid nonce to ensure the request is secure.
- The donation data (transaction ID and amount) is stored in the database.
- Real-time metrics (total amount raised and number of donations) are updated.
- The plugin returns a JSON response indicating the success or failure of the donation.

### Error Logging

- The plugin logs every attempt to trigger the `save_donation` function, including failed nonce verifications and database insertion errors.
- Check your WordPress debug log (`wp-content/debug.log`) to review error messages.

## Frequently Asked Questions

### How do I ensure my donation data is secure?

The plugin uses WordPress's built-in nonce verification to protect against unauthorized submissions. Always ensure that your site is running the latest version of WordPress for the best security.

## Changelog

### 1.1.0
- Added donation logging and real-time metrics.
- Improved security with nonce verification.
- Enhanced error logging.
- Updated JSON responses to include current total donations.
- Improved error handling for nonce verification and database insertion.

### 1.0.0
- Initial release.

## License

This plugin is licensed under the GPLv2 or later. See the LICENSE file for more details.
