# Gravity Forms Klaviyo Add-On

A WordPress plugin that integrates Gravity Forms with the Klaviyo email marketing platform, allowing you to automatically create and update subscribers in your Klaviyo account from form submissions.

## Features

- **Easy Integration**: Seamlessly connect Gravity Forms with Klaviyo using the official Klaviyo API
- **Profile Management**: Create and update profiles in Klaviyo with standard and custom properties
- **List Subscription**: Subscribe profiles to one or more Klaviyo lists (required)
- **Consent Management**: Specify consent types (Email, SMS, Web) for compliance
- **Field Mapping**: Map form fields to Klaviyo profile fields including standard fields and custom properties
- **Multiple Feeds**: Support for multiple feeds per form with different settings
- **Conditional Logic**: Send profiles to Klaviyo only when specific conditions are met
- **Tag Support**: Automatically apply tags to profiles in Klaviyo
- **Error Handling**: Comprehensive error logging and graceful failure handling
- **Security**: Follows WordPress and Gravity Forms security best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Gravity Forms 2.5 or higher
- Klaviyo account with API access

## Installation

1. Download or clone this repository
2. Upload the `gravity-forms-klaviyo` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Forms → Settings → Klaviyo to configure your API credentials

## Configuration

### Getting Your Klaviyo API Key

1. **Private API Key**: 
   - Log in to your Klaviyo account
   - Go to Settings → Account → API Keys
   - Copy your Private API Key (not the Public API Key)
   - The Private API Key is required for this integration

### Plugin Settings

1. Navigate to **Forms → Settings → Klaviyo**
2. Enter your **Private API Key**
3. The plugin will automatically validate your credentials (green tick = valid, red X = invalid)
4. Save settings

### Creating a Feed

1. Go to **Forms → [Your Form] → Settings → Klaviyo**
2. Click **Add New** to create a new feed
3. Configure the feed:
   - **Feed Name**: Give your feed a descriptive name
   - **Email Address**: Map to a form field containing an email address (required)
   - **Profile Information**: Map form fields to standard Klaviyo fields (first name, last name, phone number, organization, title)
   - **Custom Properties**: Map form fields to Klaviyo custom properties (address fields, company, industry, etc. should go here)
   - **Lists**: Select one or more Klaviyo lists to subscribe the person to (required)
   - **Consent**: Select the consent type (Email, SMS, Email and SMS, or Web) - required for compliance
   - **Tags**: Enter comma-separated tags to apply to profiles
   - **Conditional Logic**: Set conditions for when to send to Klaviyo
4. Save the feed

## Field Mapping

### Required Fields
- **Email**: Must be mapped to a Gravity Forms email field
- **Lists**: At least one Klaviyo list must be selected
- **Consent**: Consent type must be selected

### Standard Klaviyo Profile Fields
- First Name
- Last Name
- Phone Number (note: Klaviyo uses "phone_number" as the field name)
- Organization
- Title

### Custom Properties
You can map any Gravity Forms field to Klaviyo custom properties. Simply specify the property name in Klaviyo and select the corresponding form field. Address fields, company information, and other custom data should be mapped as custom properties.

## API Documentation

This plugin uses the Klaviyo API. For more information, visit:
- [Klaviyo API Documentation](https://developers.klaviyo.com/)
- [Profiles API](https://developers.klaviyo.com/en/reference/create-profile)
- [Lists API](https://developers.klaviyo.com/en/reference/get-lists)
- [Profile Subscription API](https://developers.klaviyo.com/en/reference/subscribe-profiles)

## How It Works

When a form is submitted:

1. **Profile Creation/Update**: The plugin creates or updates a profile in Klaviyo with the mapped form data
2. **List Subscription**: The profile is then subscribed to the selected Klaviyo list(s) with the specified consent type

This two-step process ensures that profiles are properly created and subscribed according to Klaviyo's requirements.

## Security

- All inputs are sanitized
- All outputs are escaped
- WordPress nonces are used for form submissions
- API credentials are stored securely in WordPress options
- Follows WordPress and Gravity Forms coding standards

## Error Handling

- API errors are logged using Gravity Forms logging system
- Form submissions continue even if Klaviyo API is unavailable
- Admin notices are displayed for connection failures
- Detailed error messages help with troubleshooting
- Entry notes are added for successful and failed submissions

## Support

For issues, questions, or contributions, please open an issue on GitHub.

## License

GPL-2.0+

## Changelog

### 1.0.0
- Initial release
- Basic integration with Klaviyo API
- Profile creation and updates
- List subscription support
- Custom properties mapping
- Consent management
- Conditional logic support
- Tag support

