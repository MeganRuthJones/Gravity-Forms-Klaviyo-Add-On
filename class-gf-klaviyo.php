<?php
/**
 * Gravity Forms Klaviyo Add-On
 *
 * @package GF_Klaviyo
 * @author  Megan Jones
 * @version 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Include the Gravity Forms Feed Add-On Framework, mirroring official add-ons like EmailOctopus.
GFForms::include_feed_addon_framework();

/**
 * Main add-on class
 *
 * @since 1.0.0
 */
class GF_Klaviyo extends GFFeedAddOn {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version = GF_KLAVIYO_VERSION;

	/**
	 * Minimum Gravity Forms version required.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = GF_KLAVIYO_MIN_GF_VERSION;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	protected $_slug = 'gravityforms-klaviyo';

	/**
	 * Plugin path
	 *
	 * @var string
	 */
	protected $_path = 'klaviyo-gravity-forms.php';

	/**
	 * Full path to this file
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Title of the plugin
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms Klaviyo Add-On';

	/**
	 * Short title of the plugin
	 *
	 * @var string
	 */
	protected $_short_title = 'Klaviyo';

	/**
	 * Capabilities required to access plugin settings
	 *
	 * @var string
	 */
	protected $_capabilities_settings_page = 'gravityforms_edit_settings';

	/**
	 * Capabilities required to access plugin form settings
	 *
	 * @var string
	 */
	protected $_capabilities_form_settings = 'gravityforms_edit_forms';

	/**
	 * Capabilities required to uninstall plugin
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'gravityforms_uninstall';

	/**
	 * Permissions required to access plugin
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_edit_forms', 'gravityforms_edit_settings' );

	/**
	 * Singleton instance
	 *
	 * @var GF_Klaviyo
	 */
	private static $_instance = null;

	/**
	 * Cached result of the last API initialization attempt.
	 *
	 * @since 1.0.0
	 *
	 * @var bool|null True if valid, false if invalid, null if not yet checked.
	 */
	protected $api_initialized = null;

	/**
	 * Get instance of this class
	 *
	 * @return GF_Klaviyo
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point
	 */
	public function init() {
		parent::init();

		// Add filter to modify plugin row meta
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		
		// Add Settings link to plugin action links - use plugin basename
		$plugin_basename = plugin_basename( GF_KLAVIYO_PLUGIN_FILE );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'plugin_action_links' ) );
		
		// Enqueue scripts and styles for the details popup
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Plugin settings fields
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Klaviyo API Settings', 'gravityforms-klaviyo' ),
				'fields' => array(
					array(
						'name'              => 'api_key',
						'label'             => esc_html__( 'Private API Key', 'gravityforms-klaviyo' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						// Use live validation for feedback, mirroring the Kit add-on behaviour.
						'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
						'description'       => sprintf(
							/* translators: %s: Link to Klaviyo API documentation */
							esc_html__( 'Enter your Klaviyo Private API Key. You can find this in your Klaviyo account under Settings > Account > API Keys. %s', 'gravityforms-klaviyo' ),
							'<a href="https://www.klaviyo.com/account#api-keys" target="_blank">' . esc_html__( 'Get your API key', 'gravityforms-klaviyo' ) . '</a>'
						),
					),
				),
			),
		);
	}

	/**
	 * Feed settings fields
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		// Get Klaviyo lists for the dropdown
		$list_choices = $this->get_klaviyo_list_choices();

		$standard_fields = array(
			array(
				'name'     => 'feedName',
				'label'    => esc_html__( 'Feed Name', 'gravityforms-klaviyo' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Feed Name', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Enter a name for this feed. This will help you identify it later.', 'gravityforms-klaviyo' ),
			),
			array(
				'name'     => 'lists',
				'label'    => esc_html__( 'List', 'gravityforms-klaviyo' ),
				'type'     => 'select',
				'required' => true,
				'choices'  => $list_choices,
				'tooltip'  => '<h6>' . esc_html__( 'List', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Select a Klaviyo list to subscribe the person to. This field is required.', 'gravityforms-klaviyo' ),
			),
		);

		// Klaviyo standard profile fields (different from Drip)
		$standard_klaviyo_fields = array(
			array(
				'name'     => 'email',
				'label'    => esc_html__( 'Email Address', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Email Address', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Select the form field that contains the email address. This field is required.', 'gravityforms-klaviyo' ),
			),
			array(
				'name'     => 'title',
				'label'    => esc_html__( 'Title', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'first_name',
				'label'    => esc_html__( 'First Name', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'last_name',
				'label'    => esc_html__( 'Last Name', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'phone_number',
				'label'    => esc_html__( 'Phone Number', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => false,
				'tooltip'  => '<h6>' . esc_html__( 'Phone Number', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Note: Klaviyo uses "phone_number" as the field name (not "phone").', 'gravityforms-klaviyo' ),
			),
			array(
				'name'     => 'organization',
				'label'    => esc_html__( 'Organization', 'gravityforms-klaviyo' ),
				'type'     => 'field_select',
				'required' => false,
			),
		);

		// Custom properties - using generic_map like Drip
		$custom_fields = array(
			array(
				'name'           => 'custom_properties',
				'label'          => esc_html__( 'Custom Properties', 'gravityforms-klaviyo' ),
				'type'           => 'generic_map',
				'key_field'      => array(
					'allow_custom' => true,
					'placeholder'  => esc_html__( 'Property Name', 'gravityforms-klaviyo' ),
				),
				'value_field'    => array(
					'allow_custom' => true,
					'placeholder'  => esc_html__( 'Select a Value', 'gravityforms-klaviyo' ),
				),
				'disable_custom' => false,
				'description'    => '<p>' . esc_html__( 'Map form fields to Klaviyo custom properties. Enter or create a property name (left column) and map it to a Gravity Forms field (right column). Address fields and other custom data should be mapped here.', 'gravityforms-klaviyo' ) . '</p>',
				'tooltip'        => '<h6>' . esc_html__( 'Custom Properties', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Map form fields to Klaviyo custom properties. The left column is the property name in Klaviyo, and the right column allows you to select the form field to map to it.', 'gravityforms-klaviyo' ),
			),
		);

		$additional_settings = array(
			array(
				'name'    => 'tags',
				'label'   => esc_html__( 'Tags', 'gravityforms-klaviyo' ),
				'type'    => 'text',
				'class'   => 'large',
				'tooltip' => '<h6>' . esc_html__( 'Tags', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'Enter tags separated by commas. These tags will be applied to the profile in Klaviyo.', 'gravityforms-klaviyo' ),
			),
			array(
				'name'    => 'feed_condition',
				'label'   => esc_html__( 'Conditional Logic', 'gravityforms-klaviyo' ),
				'type'    => 'feed_condition',
				'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityforms-klaviyo' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be sent to Klaviyo when the conditions are met. When disabled, all form submissions will be sent to Klaviyo.', 'gravityforms-klaviyo' ),
			),
		);

		return array(
			array(
				'title'  => esc_html__( 'Feed Settings', 'gravityforms-klaviyo' ),
				'fields' => $standard_fields,
			),
			array(
				'title'  => esc_html__( 'Standard Fields', 'gravityforms-klaviyo' ),
				'fields' => $standard_klaviyo_fields,
			),
			array(
				'title'  => esc_html__( 'Custom Properties', 'gravityforms-klaviyo' ),
				'fields' => $custom_fields,
			),
			array(
				'title'  => esc_html__( 'Additional Settings', 'gravityforms-klaviyo' ),
				'fields' => $additional_settings,
			),
		);
	}

	/**
	 * Form settings page title
	 *
	 * @since 1.0.0
	 * @return string Form Settings Title
	 */
	public function feed_settings_title() {
		return esc_html__( 'Feed Settings', 'gravityforms-klaviyo' );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.0.0
	 *
	 * @return string SVG content or empty string on failure
	 */
	public function get_menu_icon() {
		// Use plugin_dir_path with the main plugin file - this ensures the path is always
		// relative to wherever the plugin is installed, regardless of folder name
		$icon_path = plugin_dir_path( GF_KLAVIYO_PLUGIN_FILE ) . 'images/menu-icon.svg';
		
		// Normalize path separators for cross-platform compatibility
		$icon_path = wp_normalize_path( $icon_path );
		
		if ( file_exists( $icon_path ) ) {
			$icon_content = file_get_contents( $icon_path );
			if ( false !== $icon_content && ! empty( $icon_content ) ) {
				return $icon_content;
			}
		}
		
		// Fallback: try using the plugin directory constant
		$fallback_path = wp_normalize_path( GF_KLAVIYO_PLUGIN_DIR . 'images/menu-icon.svg' );
		if ( file_exists( $fallback_path ) ) {
			$icon_content = file_get_contents( $fallback_path );
			if ( false !== $icon_content && ! empty( $icon_content ) ) {
				return $icon_content;
			}
		}
		
		// If we get here, log an error but don't break the plugin
		$this->log_error( __METHOD__ . '(): Menu icon file not found. Tried: ' . $icon_path . ' and ' . $fallback_path );
		return '';
	}

	/**
	 * Initialize API connection
	 *
	 * @return bool True if API is initialized, false otherwise
	 */
	public function initialize_api() {
		if ( null !== $this->api_initialized ) {
			return $this->api_initialized;
		}

		$api_key = $this->get_plugin_setting( 'api_key' );

		if ( empty( $api_key ) ) {
			$this->api_initialized = false;
			return false;
		}

		// Test the API connection
		$result = $this->test_api_connection( $api_key );

		if ( is_wp_error( $result ) ) {
			$this->api_initialized = false;
			$this->log_error( __METHOD__ . '(): Klaviyo API could not be initialized. ' . $result->get_error_message() );
			return false;
		}

		$this->api_initialized = true;
		$this->log_debug( __METHOD__ . '(): Klaviyo API credentials are valid.' );

		return true;
	}

	/**
	 * Test API connection
	 *
	 * @param string $api_key API key
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function test_api_connection( $api_key = '' ) {
		// Fall back to saved settings if explicit value not provided.
		if ( empty( $api_key ) ) {
			$api_key = $this->get_plugin_setting( 'api_key' );
		}

		// Sanitize and trim to avoid subtle issues with copy/pasted spaces.
		$api_key = sanitize_text_field( $api_key );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_credentials', esc_html__( 'API key is required.', 'gravityforms-klaviyo' ) );
		}

		// Test by fetching account info - using a simple endpoint to validate credentials
		$url = 'https://a.klaviyo.com/api/accounts/';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Klaviyo-API-Key ' . $api_key,
					'Content-Type'  => 'application/json',
					'Revision'      => '2024-10-15', // Klaviyo API version
					'User-Agent'     => 'Gravity Forms Klaviyo Add-On/' . $this->_version,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API connection test failed: ' . $response->get_error_message() );
			return new WP_Error( 'connection_error', esc_html__( 'Failed to connect to Klaviyo API. Please check your credentials and try again.', 'gravityforms-klaviyo' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Log the response for debugging.
		$this->log_debug( __METHOD__ . '(): Klaviyo API response code: ' . $response_code );

		if ( 200 !== $response_code ) {
			$error_data    = json_decode( $response_body, true );
			$error_message = esc_html__( 'Unable to verify your Klaviyo API credentials. Please check your API key, save your settings, and try again.', 'gravityforms-klaviyo' );

			if ( ! empty( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
				$first_error = $error_data['errors'][0];
				if ( isset( $first_error['detail'] ) ) {
					$error_message = $first_error['detail'];
				} elseif ( isset( $first_error['message'] ) ) {
					$error_message = $first_error['message'];
				}
			}

			$this->log_error( __METHOD__ . '(): API connection test failed: HTTP ' . $response_code . ' - ' . $error_message );

			return new WP_Error( 'invalid_credentials', $error_message );
		}

		// Success - log for debugging.
		$this->log_debug( __METHOD__ . '(): Klaviyo API connection test successful.' );

		return true;
	}

	/**
	 * Get available Klaviyo lists for use in the feed settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of choices for select field
	 */
	public function get_klaviyo_list_choices() {
		$choices = array();

		// Add a placeholder option
		$choices[] = array(
			'label' => esc_html__( 'Select a List', 'gravityforms-klaviyo' ),
			'value' => '',
		);

		// Make sure we have valid credentials before calling the API.
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Klaviyo API could not be initialized. No lists loaded.' );
			return $choices;
		}

		$api_key = $this->get_plugin_setting( 'api_key' );

		if ( rgblank( $api_key ) ) {
			return $choices;
		}

		// Check cache first - but only if it has actual data
		$cache_key = 'gf_klaviyo_lists_' . md5( $api_key );
		$cached_lists = GFCache::get( $cache_key );

		if ( false !== $cached_lists && is_array( $cached_lists ) && ! empty( $cached_lists ) ) {
			$this->log_debug( __METHOD__ . '(): Using cached Klaviyo lists. Found ' . count( $cached_lists ) . ' lists.' );
			// Merge placeholder with cached lists
			return array_merge( array( $choices[0] ), $cached_lists );
		}

		// If cache exists but is empty, clear it and fetch fresh
		if ( false !== $cached_lists && empty( $cached_lists ) ) {
			$this->log_debug( __METHOD__ . '(): Cached lists were empty, clearing cache and fetching fresh.' );
			GFCache::delete( $cache_key );
		}

		// Fetch lists from Klaviyo API with pagination support
		$list_choices = array();
		$page_cursor = null;
		$has_more = true;

		while ( $has_more ) {
			$url = 'https://a.klaviyo.com/api/lists/';
			
			// Add pagination if we have a cursor
			if ( $page_cursor ) {
				$url = add_query_arg( 'page[cursor]', $page_cursor, $url );
			}

			$this->log_debug( __METHOD__ . '(): Attempting to fetch lists from: ' . $url );
			$response = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Klaviyo-API-Key ' . sanitize_text_field( $api_key ),
						'Content-Type'  => 'application/json',
						'Revision'      => '2024-10-15',
						'User-Agent'    => 'Gravity Forms Klaviyo Add-On/' . $this->_version,
					),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_error( __METHOD__ . '(): Failed to retrieve Klaviyo lists. ' . $response->get_error_message() );
				break;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body_raw = wp_remote_retrieve_body( $response );
			
			if ( 200 !== $code ) {
				$this->log_error( __METHOD__ . '(): Unexpected response code when retrieving Klaviyo lists: ' . $code . '. Response body: ' . $body_raw );
				break;
			}

			$body = json_decode( $body_raw, true );

			// Log the raw response for debugging (only on first page to avoid spam)
			if ( ! $page_cursor ) {
				$this->log_debug( __METHOD__ . '(): Klaviyo API response body: ' . print_r( $body, true ) );
			}

			// Klaviyo returns data in JSON:API format
			if ( empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
				$this->log_debug( __METHOD__ . '(): No lists found in Klaviyo response.' );
				break;
			}

			// Parse JSON:API format
			foreach ( $body['data'] as $list ) {
				if ( empty( $list['id'] ) || empty( $list['attributes']['name'] ) ) {
					continue;
				}

				$list_id = $list['id'];
				$list_name = $list['attributes']['name'];

				$list_choices[] = array(
					'label' => esc_html( $list_name ),
					'value' => esc_attr( $list_id ),
				);
			}

			// Check for pagination
			if ( ! empty( $body['links']['next'] ) ) {
				// Extract cursor from next link
				$next_url = $body['links']['next'];
				$parsed_url = parse_url( $next_url );
				if ( ! empty( $parsed_url['query'] ) ) {
					parse_str( $parsed_url['query'], $query_params );
					if ( ! empty( $query_params['page']['cursor'] ) ) {
						$page_cursor = $query_params['page']['cursor'];
					} else {
						$has_more = false;
					}
				} else {
					$has_more = false;
				}
			} else {
				$has_more = false;
			}
		}

		// Sort lists alphabetically by name
		if ( ! empty( $list_choices ) ) {
			usort( $list_choices, function( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			});
		}

		// Cache the results for 30 minutes (reduced from 1 hour for fresher data)
		if ( ! empty( $list_choices ) ) {
			GFCache::set( $cache_key, $list_choices, 1800 );
			$this->log_debug( __METHOD__ . '(): Cached ' . count( $list_choices ) . ' Klaviyo lists for 30 minutes.' );
		} else {
			// If no lists found, log a warning but don't cache empty results
			$this->log_error( __METHOD__ . '(): No lists found in Klaviyo account. Please check your Klaviyo account has lists created.' );
		}

		// Merge placeholder with list choices
		$choices = array_merge( array( $choices[0] ), $list_choices );

		// Log how many choices were loaded for debugging
		$this->log_debug( __METHOD__ . '(): Loaded ' . count( $list_choices ) . ' Klaviyo list choices from API.' );

		return $choices;
	}

	/**
	 * Clear the cached API initialization result when settings are updated.
	 *
	 * This ensures the feedback callback uses fresh values after saving.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The settings being saved.
	 *
	 * @return void
	 */
	public function update_plugin_settings( $settings ) {
		// Clear the cached API initialization result so feedback callbacks use fresh values.
		$this->api_initialized = null;

		// Clear cached lists when API key changes or settings are updated
		if ( isset( $settings['api_key'] ) ) {
			$old_api_key = $this->get_plugin_setting( 'api_key' );
			if ( $old_api_key !== $settings['api_key'] ) {
				// Clear cache for old API key
				$old_cache_key = 'gf_klaviyo_lists_' . md5( $old_api_key );
				GFCache::delete( $old_cache_key );
				// Clear cache for new API key too (will be regenerated on next fetch)
				$new_cache_key = 'gf_klaviyo_lists_' . md5( $settings['api_key'] );
				GFCache::delete( $new_cache_key );
				$this->log_debug( __METHOD__ . '(): Cleared list cache due to API key change.' );
			}
		}

		parent::update_plugin_settings( $settings );
	}

	/**
	 * Feedback callback for API Key in plugin settings.
	 *
	 * Mirrors the Kit (ConvertKit) add-on: validates the currently entered
	 * values (not just the saved ones) so the tick/cross reflects what the
	 * user has typed before they save.
	 *
	 * Returning null when the field is empty prevents any icon being shown.
	 *
	 * @since 1.0.0
	 *
     * @param string                                        $value The value of the field being validated.
     * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Text $field  The field object being validated.
	 *
	 * @return bool|null True if valid, false if invalid, null if not enough data.
	 */
	public function plugin_settings_fields_feedback_callback( $value, $field ) {

		// If the current field is empty, do not show any icon yet.
		if ( empty( $value ) ) {
			return null;
		}

		// Sanitize value before testing.
		$api_key = sanitize_text_field( $value );

		// Test the API connection with this credential.
		$result = $this->test_api_connection( $api_key );

		// Return true for success (green tick), false for failure (red X).
		if ( is_wp_error( $result ) ) {
			// Log the error so it appears in Gravity Forms logs.
			$this->log_error( __METHOD__ . '(): Klaviyo API validation failed for field ' . $field->name . '. ' . $result->get_error_message() );

			return false;
		}

		// Success - log and return true for green tick.
		$this->log_debug( __METHOD__ . '(): Klaviyo API credentials are valid for field ' . $field->name . '.' );

		return true;
	}

	/**
	 * Process the feed
	 *
	 * @param array $feed  Feed object
	 * @param array $entry Entry object
	 * @param array $form  Form object
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		// Check if feed conditions are met
		if ( ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
			$this->log_debug( 'Feed condition not met. Skipping feed processing.' );
			return;
		}

		// Get API credentials
		$api_key = $this->get_plugin_setting( 'api_key' );

		if ( empty( $api_key ) ) {
			$this->log_error( 'API credentials are not configured.' );
			$this->add_feed_error( esc_html__( 'Klaviyo API key is not configured.', 'gravityforms-klaviyo' ), $feed, $entry, $form );
			return;
		}

		// Get email field
		$email_field_id = rgars( $feed, 'meta/email' );
		if ( empty( $email_field_id ) ) {
			$this->log_error( 'Email field is not mapped in feed.' );
			$this->add_feed_error( esc_html__( 'Email field is not mapped in feed.', 'gravityforms-klaviyo' ), $feed, $entry, $form );
			return;
		}

		$email = rgar( $entry, $email_field_id );
		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->log_error( 'Invalid email address: ' . $email );
			$this->add_feed_error( esc_html__( 'Invalid email address.', 'gravityforms-klaviyo' ), $feed, $entry, $form );
			return;
		}

		// Get list - required (now a single value from dropdown)
		$list_id = rgars( $feed, 'meta/lists' );
		if ( empty( $list_id ) ) {
			$this->log_error( 'No list selected in feed.' );
			$this->add_feed_error( esc_html__( 'A Klaviyo list must be selected.', 'gravityforms-klaviyo' ), $feed, $entry, $form );
			return;
		}

		// Convert single list ID to array for subscription method
		$lists = array( $list_id );

		// Build profile data in JSON:API format
		$profile_data = $this->build_profile_data( $feed, $entry, $form, $email );

		// Step 1: Create or update profile
		$profile_result = $this->create_or_update_profile( $api_key, $profile_data, $entry );

		if ( is_wp_error( $profile_result ) ) {
			$this->log_error( 'Failed to create/update profile in Klaviyo: ' . $profile_result->get_error_message() );
			$this->add_feed_error( esc_html__( 'Failed to create/update profile in Klaviyo: ', 'gravityforms-klaviyo' ) . $profile_result->get_error_message(), $feed, $entry, $form );
			return;
		}

		// Step 2: Subscribe to lists
		$subscription_result = $this->subscribe_to_lists( $api_key, $email, $lists, $feed, $entry );

		if ( is_wp_error( $subscription_result ) ) {
			$this->log_error( 'Failed to subscribe to lists in Klaviyo: ' . $subscription_result->get_error_message() );
			$this->add_feed_error( esc_html__( 'Failed to subscribe to lists in Klaviyo: ', 'gravityforms-klaviyo' ) . $subscription_result->get_error_message(), $feed, $entry, $form );
			return;
		}

		// Success - add note to entry
		$this->add_note( $entry['id'], esc_html__( 'Successfully added to Klaviyo.', 'gravityforms-klaviyo' ), 'success' );
		$this->log_debug( 'Profile successfully created/updated and subscribed to lists in Klaviyo: ' . $email );
	}

	/**
	 * Build profile data from feed and entry
	 *
	 * @param array $feed  Feed object
	 * @param array $entry Entry object
	 * @param array $form  Form object
	 * @param string $email Email address
	 * @return array Profile data in JSON:API format
	 */
	private function build_profile_data( $feed, $entry, $form, $email ) {
		$attributes = array(
			'email' => sanitize_email( $email ),
		);

		// Map standard fields
		$standard_fields = array( 'first_name', 'last_name', 'phone_number', 'organization', 'title' );
		foreach ( $standard_fields as $field_name ) {
			$field_id = rgars( $feed, 'meta/' . $field_name );
			if ( ! empty( $field_id ) ) {
				$field_value = rgar( $entry, $field_id );
				if ( ! empty( $field_value ) ) {
					$attributes[ $field_name ] = sanitize_text_field( $field_value );
				}
			}
		}

		// Map custom properties
		$custom_properties = rgars( $feed, 'meta/custom_properties' );
		if ( ! empty( $custom_properties ) && is_array( $custom_properties ) ) {
			$attributes['properties'] = array();

			foreach ( $custom_properties as $mapped_field ) {
				// Handle both old format (associative array) and new format (array of objects).
				if ( isset( $mapped_field['key'] ) && isset( $mapped_field['value'] ) ) {
					// New format: array of objects with 'key' and 'value'.
					$property_name = $mapped_field['key'];
					$gf_field_id   = $mapped_field['value'];
				} else {
					// Old format: associative array (for backward compatibility).
					$property_name = key( $mapped_field );
					$gf_field_id   = current( $mapped_field );
				}

				if ( empty( $property_name ) || empty( $gf_field_id ) ) {
					continue;
				}

				$field_value = rgar( $entry, $gf_field_id );
				if ( $field_value === '' || $field_value === null ) {
					continue;
				}

				$attributes['properties'][ sanitize_text_field( $property_name ) ] = sanitize_text_field( $field_value );
			}
		}

		// Add tags if provided
		$tags = rgars( $feed, 'meta/tags' );
		if ( ! empty( $tags ) ) {
			$tags_array = array_map( 'trim', explode( ',', $tags ) );
			$tags_array = array_filter( $tags_array );
			if ( ! empty( $tags_array ) ) {
				$attributes['properties']['$tags'] = array_map( 'sanitize_text_field', $tags_array );
			}
		}

		// Build JSON:API format
		$profile_data = array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => $attributes,
			),
		);

		return $profile_data;
	}

	/**
	 * Create or update profile in Klaviyo
	 *
	 * @param string $api_key     API key
	 * @param array  $profile_data Profile data in JSON:API format
	 * @param array  $entry       Entry object
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function create_or_update_profile( $api_key, $profile_data, $entry ) {
		$url = 'https://a.klaviyo.com/api/profiles/';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Klaviyo-API-Key ' . sanitize_text_field( $api_key ),
					'Content-Type'  => 'application/json',
					'Revision'      => '2024-10-15',
					'User-Agent'    => 'Gravity Forms Klaviyo Add-On/' . $this->_version,
				),
				'body'    => wp_json_encode( $profile_data ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 201 !== $response_code && 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = esc_html__( 'Unknown error occurred.', 'gravityforms-klaviyo' );

			if ( ! empty( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
				$first_error = $error_data['errors'][0];
				if ( isset( $first_error['detail'] ) ) {
					$error_message = $first_error['detail'];
				} elseif ( isset( $first_error['message'] ) ) {
					$error_message = $first_error['message'];
				}
			}

			$this->log_error( 'Klaviyo API error (HTTP ' . $response_code . '): ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}

		$this->log_debug( 'Profile successfully created/updated in Klaviyo: ' . $profile_data['data']['attributes']['email'] );
		return true;
	}

	/**
	 * Subscribe profile to lists in Klaviyo
	 *
	 * @param string $api_key API key
	 * @param string $email   Email address
	 * @param array  $lists   Array of list IDs
	 * @param array  $feed    Feed object
	 * @param array  $entry   Entry object
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function subscribe_to_lists( $api_key, $email, $lists, $feed, $entry ) {
		// Determine consent automatically based on form data
		// Always include email consent since form was submitted
		$consent = array( 'email' );

		// Check if phone number is mapped and has a value
		$phone_field_id = rgars( $feed, 'meta/phone_number' );
		if ( ! empty( $phone_field_id ) ) {
			$phone_value = rgar( $entry, $phone_field_id );
			if ( ! empty( $phone_value ) ) {
				// Phone number is mapped and has a value, add SMS consent
				$consent[] = 'sms';
			}
		}

		$this->log_debug( 'Determined consent for subscription: ' . implode( ', ', $consent ) );

		// Build profile attributes with email
		$profile_attributes = array(
			'email' => sanitize_email( $email ),
		);

		// Add consent only if we have it (make it optional to avoid API errors)
		if ( ! empty( $consent ) && is_array( $consent ) ) {
			$profile_attributes['consent'] = $consent;
		}

		$subscription_data = array(
			'data' => array(
				'type'       => 'profile-subscription-bulk-create-job',
				'attributes' => array(
					'profiles' => array(
						'data' => array(
							array(
								'type'       => 'profile',
								'attributes' => $profile_attributes,
							),
						),
					),
					'list_ids' => array_map( 'sanitize_text_field', $lists ),
				),
			),
		);

		$url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Klaviyo-API-Key ' . sanitize_text_field( $api_key ),
					'Content-Type'  => 'application/json',
					'Revision'      => '2024-10-15',
					'User-Agent'    => 'Gravity Forms Klaviyo Add-On/' . $this->_version,
				),
				'body'    => wp_json_encode( $subscription_data ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Log the request and response for debugging
		$this->log_debug( 'Klaviyo subscription API request: ' . wp_json_encode( $subscription_data ) );
		$this->log_debug( 'Klaviyo subscription API response code: ' . $response_code );
		$this->log_debug( 'Klaviyo subscription API response body: ' . $response_body );

		if ( 202 !== $response_code && 201 !== $response_code && 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = esc_html__( 'Unknown error occurred.', 'gravityforms-klaviyo' );

			if ( ! empty( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
				$first_error = $error_data['errors'][0];
				if ( isset( $first_error['detail'] ) ) {
					$error_message = $first_error['detail'];
				} elseif ( isset( $first_error['message'] ) ) {
					$error_message = $first_error['message'];
				}
			}

			$this->log_error( 'Klaviyo subscription API error (HTTP ' . $response_code . '): ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}

		$this->log_debug( 'Successfully subscribed to lists in Klaviyo: ' . $email );
		return true;
	}

	/**
	 * Validate feed settings before saving
	 *
	 * @param array $field Field configuration
	 * @param array $field_setting Field setting value
	 * @return void
	 */
	public function validate_feed_settings( $field, $field_setting ) {
		// Validate email field is mapped
		if ( 'email' === $field['name'] && empty( $field_setting ) ) {
			$this->set_field_error( $field, esc_html__( 'Email field is required.', 'gravityforms-klaviyo' ) );
		}

		// Validate list is selected
		if ( 'lists' === $field['name'] && empty( $field_setting ) ) {
			$this->set_field_error( $field, esc_html__( 'A list must be selected.', 'gravityforms-klaviyo' ) );
		}
	}

	/**
	 * Get plugin settings
	 *
	 * @return array
	 */
	public function get_plugin_settings() {
		$settings = parent::get_plugin_settings();
		return $settings;
	}

	/**
	 * Sanitize plugin settings
	 *
	 * @param array $settings Settings to sanitize
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		$sanitized = parent::sanitize_settings( $settings );

		// Sanitize API key
		if ( isset( $sanitized['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $sanitized['api_key'] );
		}

		return $sanitized;
	}

	/**
	 * Enable feed duplication.
	 *
	 * @since 1.0.0
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {
		return true;
	}

	/**
	 * Configure which columns should be displayed on the feed list page.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feed_name' => esc_html__( 'Name', 'gravityforms-klaviyo' ),
			'form_id'   => esc_html__( 'Form', 'gravityforms-klaviyo' ),
		);
	}

	/**
	 * Return the value to be displayed in the Feed Name column.
	 *
	 * @since 1.0.0
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_feed_name( $feed ) {
		$name = rgar( $feed['meta'], 'feedName' );

		if ( empty( $name ) ) {
			$name = sprintf(
				/* translators: %d is the feed ID. */
				esc_html__( 'Klaviyo Feed #%d', 'gravityforms-klaviyo' ),
				rgar( $feed, 'id' )
			);
		}

		return $name;
	}

	/**
	 * Returns the value for the Form column.
	 *
	 * @since 1.0.0
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_form_id( $feed ) {
		$form_id = rgar( $feed, 'form_id' );

		if ( empty( $form_id ) ) {
			return '';
		}

		$form = GFAPI::get_form( $form_id );

		return is_wp_error( $form ) || empty( $form ) ? '' : esc_html( rgar( $form, 'title' ) );
	}

	/**
	 * Add Settings link to plugin action links
	 *
	 * @param array $links Existing plugin action links
	 * @return array Modified plugin action links
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) ) . '">' . esc_html__( 'Settings', 'gravityforms-klaviyo' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Modify plugin row meta to add "View details" link
	 *
	 * @param array  $plugin_meta Array of plugin meta links
	 * @param string $plugin_file Plugin file path
	 * @return array Modified plugin meta links
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		// Check if this is our plugin using plugin basename
		$plugin_basename = plugin_basename( GF_KLAVIYO_PLUGIN_FILE );
		if ( $plugin_file !== $plugin_basename ) {
			return $plugin_meta;
		}

		// Remove "Visit plugin site" if it exists
		foreach ( $plugin_meta as $key => $meta ) {
			if ( strpos( $meta, 'Visit plugin site' ) !== false ) {
				unset( $plugin_meta[ $key ] );
			}
		}

		// Add "View details" link
		$plugin_meta[] = '<a href="#" class="gf-klaviyo-view-details" data-plugin="' . esc_attr( $this->_slug ) . '">' . esc_html__( 'View details', 'gravityforms-klaviyo' ) . '</a>';

		return $plugin_meta;
	}

	/**
	 * Enqueue admin scripts and styles for the details popup and settings UI.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Ensure the Klaviyo icon in the Gravity Forms settings navigation doesn't shrink
		// when the sidebar is collapsed or space is constrained.
		// This applies to all Gravity Forms pages (settings, feeds, etc.)
		// Check if we're on any Gravity Forms admin page
		if ( strpos( $screen->id, 'forms_page_' ) === 0 || strpos( $screen->id, 'toplevel_page_gf_' ) === 0 ) {
			$css = '
			/* Prevent the Klaviyo icon from being collapsed by flexbox in the GF Settings UI */
			/* Target the icon in all contexts: settings page, feed settings page, etc. */
			svg.gf-klaviyo-icon,
			.gf-addon-menu-item svg.gf-klaviyo-icon,
			.gf-addon-menu-item[data-slug="gravityforms-klaviyo"] svg,
			#gform-settings-page svg.gf-klaviyo-icon,
			.gform-settings-page svg.gf-klaviyo-icon {
				flex-shrink: 0 !important;
				min-width: 24px !important;
				width: 24px !important;
				height: 24px !important;
				display: inline-block !important;
			}
			/* Also target any parent containers that might be causing shrinking */
			.gf-addon-menu-item[data-slug="gravityforms-klaviyo"] {
				flex-shrink: 0;
				min-width: 24px;
			}
			';

			// Register a lightweight admin style handle for our inline CSS.
			wp_register_style( 'gf-klaviyo-admin', false );
			wp_enqueue_style( 'gf-klaviyo-admin' );
			wp_add_inline_style( 'gf-klaviyo-admin', $css );
		}

		// Scripts and styles for the plugin row "View details" popup on the Plugins screen.
		if ( 'plugins' === $screen->id ) {
			// Enqueue WordPress's built-in thickbox for modal
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_style( 'thickbox' );

			// Add inline styles for the changelog popup (without style tags)
			$styles = "
			.gf-klaviyo-changelog {
				padding: 20px;
			}
			.gf-klaviyo-changelog h2 {
				margin-top: 0;
				margin-bottom: 20px;
				font-size: 18px;
				font-weight: 600;
				line-height: 1.4;
				color: #23282d;
			}
			.gf-klaviyo-changelog-content {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 15px 20px;
				margin-top: 15px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.gf-klaviyo-changelog-content h4 {
				margin-top: 0;
				margin-bottom: 12px;
				font-size: 14px;
				font-weight: 600;
				color: #23282d;
			}
			.gf-klaviyo-changelog-content ul {
				margin: 0 0 0 20px;
				padding: 0;
				list-style-type: disc;
			}
			.gf-klaviyo-changelog-content li {
				margin-bottom: 8px;
				line-height: 1.6;
				color: #50575e;
			}
			";

			// Add inline styles
			wp_add_inline_style( 'thickbox', $styles );

			// Get changelog content
			$changelog = $this->get_changelog();
			$popup_title = esc_js( $this->_title ) . ' v' . esc_js( $this->_version ) . ' ' . esc_js( __( 'Changelog', 'gravityforms-klaviyo' ) );
			$popup_content = '<div id="gf-klaviyo-details-popup" style="display:none;"><div class="gf-klaviyo-changelog">' . wp_kses_post( $changelog ) . '</div></div>';

			// Add inline script for the popup
			$script = "
			(function($) {
				$(document).ready(function() {
					// Add popup content to body if it doesn't exist
					if ($('#gf-klaviyo-details-popup').length === 0) {
						$('body').append(" . json_encode( $popup_content ) . ");
					}
					
					// Handle click on View details link
					$(document).on('click', '.gf-klaviyo-view-details', function(e) {
						e.preventDefault();
						if (typeof tb_show !== 'undefined') {
							tb_show(" . json_encode( $popup_title ) . ", '#TB_inline?inlineId=gf-klaviyo-details-popup&width=600&height=500');
						}
					});
				});
			})(jQuery);
			";

			wp_add_inline_script( 'thickbox', $script );
		}
	}

	/**
	 * Get changelog content for the popup
	 *
	 * @return string HTML content
	 */
	private function get_changelog() {
		$changelog = '<h2>' . esc_html__( 'Changelog', 'gravityforms-klaviyo' ) . '</h2>';
		$changelog .= '<div class="gf-klaviyo-changelog-content">';
		$changelog .= '<h4>Version 1.0.0</h4>';
		$changelog .= '<ul>';
		$changelog .= '<li>' . esc_html__( 'Initial release', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Basic integration with Klaviyo API', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Profile creation and updates', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'List subscription support', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Custom properties mapping', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Consent management', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Conditional logic support', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Tag support', 'gravityforms-klaviyo' ) . '</li>';
		$changelog .= '</ul>';
		$changelog .= '</div>';

		return $changelog;
	}
}

