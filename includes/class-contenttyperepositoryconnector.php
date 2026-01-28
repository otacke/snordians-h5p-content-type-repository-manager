<?php
/**
 * Main plugin class file.
 *
 * @package snordians-h5p-content-type-repository-manager
 */

namespace Snordian\H5PContentTypeRepositoryManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Main plugin class.
 */
class ContentTypeRepositoryConnector {
	/**
	 * The slug for the plugin.
	 *
	 * @var string
	 */
	private const SLUG = 'snordians-h5p-content-type-repository-manager';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const HTTP_TIMEOUT = 30;

	/**
	 * H5P framework instance.
	 *
	 * @var \H5P_Plugin
	 */
	private $h5p_framework;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->h5p_framework = \H5P_Plugin::get_instance()->get_h5p_instance( 'interface' );
	}

	/**
	 * Fetch the latest content types from the H5P Content Type Hub.
	 *
	 * @return \stdClass An object containing the content types or an error message.
	 */
	private static function fetch_latest_content_types() {
		// In theory, the UUID would need to be registered with the H5P Content Type Hub, but it does not check.
		$site_uuid    = self::create_uuid();
		$postdata     = array( 'uuid' => $site_uuid );
		$endpoint_url = self::build_api_endpoint( null, 'content-types' );

		$response = wp_remote_post(
			$endpoint_url,
			array(
				'body'    => $postdata,
				'timeout' => self::HTTP_TIMEOUT,
			)
		);

		$result = new \stdClass();

		$error_message = 'Error fetching content types: %s';

		if ( is_wp_error( $response ) ) {
			$result->error = sprintf( $error_message, $response->get_error_message() );
			return $result;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$result->error = sprintf( $error_message, wp_remote_retrieve_response_code( $response ) );
			return $result;
		}

		$body = wp_remote_retrieve_body( $response );
		try {
			$result = json_decode( $body );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$result->error = sprintf( $error_message, json_last_error_msg() );
			}
		} catch ( \Exception $e ) {
			$result->error = sprintf( $error_message, $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Create a UUID.
	 *
	 * @return string The UUID.
	 */
	private static function create_uuid() {
		return preg_replace_callback(
			'/[xy]/',
			function ( $matches ) {
				$random   = random_int( 0, 15 );
				$new_char = 'x' === $matches[0] ? $random : ( $random & 0x3 ) | 0x8;
				return dechex( $new_char );
			},
			'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
		);
	}

	/**
	 * Build the API endpoint URL.
	 *
	 * @param string|null $machine_name The machine name of the content type.
	 * @param string      $endpoint The endpoint to use - default is 'content-types', and we don't need another here.
	 * @return string The complete API endpoint URL.
	 */
	private static function build_api_endpoint( $machine_name = null, $endpoint = 'content-types' ) {
		$protocol          = 'https';
		$endpoint_url_base = Options::get_endpoint_url_base();

		return "{$protocol}://{$endpoint_url_base}/{$endpoint}" . ( $machine_name ? "/{$machine_name}" : '' );
	}

	/**
	 * Check if the content type is restricted.
	 *
	 * @param string $machine_name The machine name of the content type.
	 * @param int    $major The major version of the content type.
	 * @param int    $minor The minor version of the content type.
	 * @return bool True if restricted, false otherwise.
	 */
	private static function is_content_type_restricted( $machine_name, $major, $minor ) {
		global $wpdb;

		if ( ! isset( $machine_name ) || ! isset( $major ) || ! isset( $minor ) ) {
			return false;
		}

	  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Not feasible here, won't be called repeatedly with same params.
		$result = (int) ( $wpdb->get_var(
			$wpdb->prepare(
				'SELECT restricted FROM %i WHERE name = %s AND major_version = %d AND minor_version = %d',
				$wpdb->prefix . 'h5p_libraries',
				$machine_name,
				$major,
				$minor
			)
		) ?? 0 );

		return 1 === $result;
	}

	/**
	 * Update the H5P library information in the database.
	 *
	 * @param int   $library_id The ID of the library to update.
	 * @param array $library The library information.
	 */
	private static function update_h5p_library_information( $library_id, $library ) {
		global $wpdb;

		if ( ! isset( $library_id ) || ! isset( $library ) || ! is_array( $library ) ) {
			return;
		}

		$params = array();
		if ( array_key_exists( 'tutorial', $library ) ) {
			$params['tutorial_url'] = $library['tutorial'];
		}
		if ( count( $params ) === 0 ) {
			return;
		}

	  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Not feasible here.
		$wpdb->update(
			$wpdb->prefix . 'h5p_libraries',
			$params,
			array( 'id' => $library_id ),
			array( '%s' ), // format for data.
			array( '%d' )  // format for where clause.
		);
	}

	/**
	 * Check if the core API version is compatible with the installed H5P version.
	 *
	 * @param object|null $core_api Object containing major and minor version properties.
	 * @return bool True if compatible or no core API provided, false if not compatible.
	 */
	private static function is_required_core_api( $core_api ) {
		if ( empty( $core_api ) ) {
			return true;
		}

    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Out of our control.
		$h5p_major_version = \H5PCore::$coreApi['majorVersion'];
    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Out of our control.
		$h5p_minor_version = \H5PCore::$coreApi['minorVersion'];

		$is_major_newer = $core_api->major > $h5p_major_version;
		$is_minor_newer = ( $core_api->major === $h5p_major_version ) && ( $core_api->minor > $h5p_minor_version );

		return ! ( $is_major_newer || $is_minor_newer );
	}

	/**
	 * Fetch the library archive from the H5P Content Type Hub.
	 *
	 * @param array $library The library information.
	 * @return \stdClass An object containing the result or error message.
	 */
	private static function fetch_library_archive( $library ) {
		$result        = new \stdClass();
		$result->error = null;

		$endpoint_url = self::build_api_endpoint( $library['machineName'], 'content-types' );

		$response = wp_remote_get(
			$endpoint_url,
			array(
				'timeout' => self::HTTP_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result->error = sprintf(
				'Error fetching content type %s: %s',
				$library['machineName'],
				$response->get_error_message()
			);
			return $result;
		}

		$result->result = wp_remote_retrieve_body( $response );
		return $result;
	}

	/**
	 * Execute a callback with the 'manage_h5p_libraries' capability.
	 *
	 * @param callable $callback The callback to execute.
	 * @return \stdClass An object containing the result or error message.
	 */
	private static function execute_with_h5p_managing_rights( $callback ) {
		$result        = new \stdClass();
		$result->error = null;

    // phpcs:ignore WordPress.WP.Capabilities.Unknown -- It is set.
		$current_user_can_manage_libraries = current_user_can( 'manage_h5p_libraries' );
		$current_user                      = wp_get_current_user();
		if ( ! $current_user_can_manage_libraries ) {
			$current_user->add_cap( 'manage_h5p_libraries' );
		}

		try {
			$result->result = call_user_func( $callback );
		} catch ( \Exception $e ) {
			$result->error = $e->getMessage();
		} finally {
			if ( ! $current_user_can_manage_libraries ) {
				$current_user->remove_cap( 'manage_h5p_libraries' );
			}

			return $result;
		}
	}

	/**
	 * Check if the H5P package is valid.
	 *
	 * @param \H5PValidator $h5p_validator The H5P validator instance.
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_h5p_package_valid( $h5p_validator ) {
		return $h5p_validator->isValidPackage( true, false );
	}

	/**
	 * Save the H5P package.
	 *
	 * @param \H5PStorage $storage The H5P storage instance.
	 */
	private static function save_h5p_package( $storage ) {
		$storage->savePackage( null, null, true );
	}

	/**
	 * Leave H5P cleanly by cleaning up temporary files and silencing messages.
	 *
	 * @param string      $target_folder_path The path to the target folder.
	 * @param string      $target_file_path The path to the target file.
	 * @param \H5P_Plugin $h5p_framework The H5P framework instance.
	 */
	private static function leave_h5p_cleanly( $target_folder_path, $target_file_path, $h5p_framework ) {
		self::clean_up_temporary_h5p_files( $target_folder_path, $target_file_path );
		self::silence_h5p_messages( $h5p_framework );
	}

	/**
	 * Clean up temporary H5P files.
	 *
	 * @param string $target_folder_path The path to the target folder.
	 * @param string $target_file_path The path to the target file.
	 */
	private static function clean_up_temporary_h5p_files( $target_folder_path, $target_file_path ) {
		\H5PCore::deleteFileTree( $target_folder_path );
		\H5PCore::deleteFileTree( $target_file_path );
	}

	/**
	 * Silence H5P messages to avoid displaying them in the UI.
	 *
	 * @param \H5P_Plugin $h5p_framework The H5P framework instance.
	 */
	public static function silence_h5p_messages( $h5p_framework ) {
		$h5p_framework->getMessages( 'error' );
		$h5p_framework->getMessages( 'info' );
	}

	/**
	 * Write the archive content to a temporary file.
	 *
	 * @param string $file_content The content of the file to write.
	 * @param string $target_file_path The path to the target file.
	 * @return \stdClass An object containing the result or error message.
	 */
	private static function write_archive_to_temporary_file( $file_content, $target_file_path ) {
		global $wp_filesystem;

		$result        = new \stdClass();
		$result->error = null;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			$result->error = 'Could not initialize WordPress filesystem.';
			return $result;
		}

		if ( ! $wp_filesystem->put_contents( $target_file_path, $file_content, FS_CHMOD_FILE ) ) {
			$result->error = sprintf(
				'Could not write to file %s.',
				$target_file_path
			);
			return $result;
		}

		return $result;
	}

	/**
	 * Check the library after installation.
	 *
	 * @param array    $library The library information.
	 * @param \H5PCore $h5p_core The H5P core instance.
	 * @return \stdClass An object containing the result or error message.
	 */
	private static function check_after_install( $library, $h5p_core ) {
		$result        = new \stdClass();
		$result->error = null;

		$versioned_library_name = \H5PCore::libraryToString( $library );
    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Out of our control.
		$library_json = $h5p_core->librariesJsonData[ $versioned_library_name ];
		if ( is_null( $library_json ) || ! array_key_exists( 'libraryId', $library_json ) ) {
			$result->error = sprintf(
				'Error while installing content type %s: %s',
				$versioned_library_name,
				'Library ID is missing.'
			);
		} else {
			$result->library_id = $library_json['libraryId'];
		}

		return $result;
	}

	/**
	 * Update the content type hub cache.
	 */
	public function update_content_type_hub_cache() {
		$core = \H5P_Plugin::get_instance()->get_h5p_instance( 'core' );
		$core->updateContentTypeCache();

		$h5p_framework = \H5P_Plugin::get_instance()->get_h5p_instance( 'interface' );
		self::silence_h5p_messages( $h5p_framework );
	}

	/**
	 * Install new content type versions.
	 */
	public function install_new_content_type_versions() {
		$content_types = self::fetch_latest_content_types();
		if ( ! empty( $content_types->error ) ) {
		  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Admins are supposed to see this in the log!
			error_log( self::SLUG . ': ' . $content_types->error );
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Out of our control.
		foreach ( $content_types->contentTypes as $content_type ) {
			if ( self::is_content_type_restricted( $content_type->id, $content_type->version->major, $content_type->version->minor ) ) {
				continue; // Admin has restricted use of this content type.
			}

    	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Out of our control.
			if ( ! self::is_required_core_api( $content_type->coreApiVersionNeeded ) ) {
				continue; // Content type to be installed is not compatible with the installed H5P core version.
			}

			$library = array(
				'machineName'  => $content_type->id,
				'majorVersion' => $content_type->version->major,
				'minorVersion' => $content_type->version->minor,
				'patchVersion' => $content_type->version->patch,
			);

			if ( isset( $content_type->example ) ) {
				$library['example'] = $content_type->example;
			}
			if ( isset( $content_type->tutorial ) ) {
				$library['tutorial'] = $content_type->tutorial;
			}

			$is_library_newer_than_installed = $this->is_library_newer_than_installed( $library );
			if ( ! $is_library_newer_than_installed ) {
				continue; // Content type is already installed and up to date.
			}

			$result = self::install_content_type( $library );
			if ( isset( $result->error ) ) {
			  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Admins are supposed to see this in the log!
				error_log( self::SLUG . ': ' . $result->error );
			}
		}
	}

	/**
	 * Check if the offered library version is newer than the installed version.
	 *
	 * @param array $library The library information.
	 * @return bool True if the offered library version is newer than the installed version, false otherwise.
	 */
	public function is_library_newer_than_installed( $library ) {
		global $wpdb;

		$library_id = $this->h5p_framework->getLibraryId( $library['machineName'] );
		if ( ! $library_id ) {
			return false; // Library is not installed.
		}

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Not feasible here, won't be called repeatedly with same params.
		$installed_version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT major_version, minor_version, patch_version FROM %i WHERE id = %d',
				$wpdb->prefix . 'h5p_libraries',
				$library_id
			),
			ARRAY_A
		);

		if ( $installed_version['major_version'] > $library['majorVersion'] ) {
			return false; // Installed version is newer.
		}
		if ( $installed_version['major_version'] < $library['majorVersion'] ) {
			return true; // Offered version is newer.
		}

		if ( $installed_version['minor_version'] > $library['minorVersion'] ) {
			return false; // Installed version is newer.
		}
		if ( $installed_version['minor_version'] < $library['minorVersion'] ) {
			return true; // Offered version is newer.
		}
		if ( $installed_version['patch_version'] > $library['patchVersion'] ) {
			return false; // Installed version is newer.
		}

		return true;
	}

	/**
	 * Install a content type.
	 *
	 * @param array $library The library information.
	 */
	private static function install_content_type( $library ) {
		$result        = new \stdClass();
		$result->error = null;

		// Not entirely sure, but to get fresh paths and validators, we need new instances here.
		$h5p_framework = \H5P_Plugin::get_instance()->get_h5p_instance( 'interface' );
		$h5p_validator = \H5P_Plugin::get_instance()->get_h5p_instance( 'validator' );
		$h5p_core      = \H5P_Plugin::get_instance()->get_h5p_instance( 'core' );

		$file_content = self::fetch_library_archive( $library );
		if ( isset( $file_content->error ) ) {
			$result->error = $file_content->error;
			return $result;
		}
		$file_content = $file_content->result;

		$target_file_path = $h5p_framework->getUploadedH5pPath(); // H5P will expect the archive here.
		$write_result     = self::write_archive_to_temporary_file( $file_content, $target_file_path );
		if ( isset( $write_result->error ) ) {
			$result->error = $write_result->error;
			return $result;
		}

		$target_folder_path = $h5p_framework->getUploadedH5pFolderPath(); // H5P will extract files here during validation.
		$validation_result  = self::execute_with_h5p_managing_rights(
			function () use ( $h5p_validator ) {
				return self::is_h5p_package_valid( $h5p_validator );
			}
		);

		if ( isset( $validation_result->error ) ) {
			$result->error = $validation_result->error;
			self::leave_h5p_cleanly( $target_folder_path, $target_file_path, $h5p_framework );
			return $result;
		} elseif ( ! $validation_result->result ) {
			$result->error = sprintf(
				'Not a valid H5P package for content type %s: %s',
				$library['machineName'],
				join( ' / ', $h5p_framework->getMessages( 'error' ) )
			);
			self::leave_h5p_cleanly( $target_folder_path, $target_file_path, $h5p_framework );
			return $result;
		}

		$storage      = new \H5PStorage( $h5p_framework, $h5p_core );
		$saved_result = self::execute_with_h5p_managing_rights(
			function () use ( $storage ) {
				return self::save_h5p_package( $storage );
			}
		);

		if ( isset( $saved_result->error ) ) {
			$result->error = $saved_result->error;
			self::leave_h5p_cleanly( $target_folder_path, $target_file_path, $h5p_framework );
			return $result;
		}

		self::leave_h5p_cleanly( $target_folder_path, $target_file_path, $h5p_framework );

		$installation_check_result = self::check_after_install( $library, $h5p_core );
		if ( isset( $installation_check_result->error ) ) {
			$result->error = $installation_check_result->error;
			return $result;
		}

		self::update_h5p_library_information( $installation_check_result->library_id, $library );

		$result->status = 'OK';

		return $result;
	}
}
