<?php

namespace Pods_Gravity_Forms;

use Pods_Gravity_Forms\Platform\Gravity_Forms\Integration as Gravity_Forms_Integration;

/**
 * Plugin specific functionality.
 *
 * @since 2.0.0
 *
 * @package Pods_Gravity_Forms
 */
class Plugin {

	/**
	 * Constant that stores the current plugin version.
	 *
	 * @since 2.0.0
	 */
	const VERSION = '2.0.0-b-1';

	/**
	 * Constant that stores the minimum supported PHP version.
	 *
	 * @since 2.0.0
	 */
	const MIN_PHP_VERSION = '7.2';

	/**
	 * Constant that stores the minimum supported WP version.
	 *
	 * @since 2.0.0
	 */
	const MIN_WP_VERSION = '5.7';

	/**
	 * Constant that stores the minimum supported Pods version.
	 *
	 * @since 2.0.0
	 */
	const MIN_PODS_VERSION = '2.9';

	/**
	 * Plugin instance.
	 *
	 * @since 2.0.0
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * The plugin name.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_name = '';

	/**
	 * The plugin file.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_file = '';

	/**
	 * Plugin slug.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_slug = 'pods-gravity-forms';

	/**
	 * Plugin directory URL.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_dir_url = '';

	/**
	 * Plugin constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		// Set the plugin name.
		$this->plugin_name = __( 'Pods Gravity Forms Add-On', 'pods-gravity-forms' );

		// Store the plugin directory URL for assets usage later.
		$this->plugin_dir_url = plugin_dir_url( $this->plugin_file );

		// Run code that needs to run during plugins_loaded.
		$this->plugins_loaded();
	}

	/**
	 * Setup and get the instance of the class.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_file The plugin file.
	 *
	 * @return self The instance of the class.
	 */
	public static function instance( $plugin_file ) {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		self::$instance->plugin_file = $plugin_file;

		return self::$instance;
	}

	/**
	 * Handle plugins_loaded functionality.
	 *
	 * @since 2.0.0
	 */
	public function plugins_loaded() {
		global $wp_version;

		$requirements = [
			[
				'check'   => $wp_version && version_compare( self::MIN_WP_VERSION, $wp_version, '<=' ),
				// translators: %s: The WordPress version number.
				'message' => sprintf( __( 'You need WordPress %s+ installed in order to use the Pods Gravity Forms Add-On.', 'pods-gravity-forms' ), self::MIN_WP_VERSION ),
			],
			[
				'check'   => version_compare( self::MIN_PHP_VERSION, PHP_VERSION, '<=' ),
				// translators: %s: The PHP version number.
				'message' => sprintf( __( 'You need PHP %s+ installed in order to use the Pods Gravity Forms Add-On.', 'pods-gravity-forms' ), self::MIN_PHP_VERSION ),
			],
			[
				'check'   => defined( 'PODS_VERSION' ) && version_compare( self::MIN_PODS_VERSION, PODS_VERSION, '<=' ),
				// translators: %s: The Pods version number.
				'message' => sprintf( __( 'You need Pods Framework %s+ installed and activated in order to use the Pods Gravity Forms Add-On.', 'pods-gravity-forms' ), self::MIN_PODS_VERSION ),
			],
		];

		// Check if this add-on should load.
		if ( ! $this->check_requirements( $requirements ) ) {
			return;
		}

		require_once __DIR__ . '/Platform/Gravity_Forms/Integration.php';

		// Setup instances and run their hooks.
		$integrations = [
			Gravity_Forms_Integration::instance(),
		];

		foreach ( $integrations as $integration ) {
			if ( method_exists( $integration, 'get_requirements' ) ) {
				$requirements = $integration->get_requirements();

				if ( ! $this->check_requirements( $requirements ) ) {
					continue;
				}
			}

			$integration->hook();
		}
	}

	/**
	 * Check whether the requirements were met.
	 *
	 * @since 2.0.0
	 *
	 * @param array $requirements List of requirements.
	 *
	 * @return bool Whether the requirements were met.
	 */
	public function check_requirements( array $requirements ) {
		foreach ( $requirements as $requirement ) {
			// Check if requirement passed.
			if ( $requirement['check'] ) {
				continue;
			}

			// Show admin notice if there's a message to be shown.
			if ( ! empty( $requirement['message'] ) && $this->should_show_notices() ) {
				$this->message( $requirement['message'], 'error' );
			}

			return false;
		}

		return true;
	}

	/**
	 * Message / Notice handling for Admin UI.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message The notice / error message shown.
	 * @param string $type    The message type.
	 */
	public function message( $message, $type = null ) {
		if ( function_exists( 'pods_message' ) ) {
			pods_message( $message );

			return;
		}

		if ( empty( $type ) || ! in_array( $type, [ 'notice', 'error' ], true ) ) {
			$type = 'notice';
		}

		$class = '';

		if ( 'notice' === $type ) {
			$class = 'updated';
		} elseif ( 'error' === $type ) {
			$class = 'error';
		}

		echo '<div id="message" class="' . esc_attr( $class ) . ' fade"><p>' . $message . '</p></div>';
	}

	/**
	 * Check whether we should show notices.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Whether we should show notices.
	 */
	public function should_show_notices() {
		global $pagenow;

		// We only show notices on admin pages.
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		// We only show on the plugins.php page or on Pods Admin pages.
		if ( ( 'plugins.php' !== $pagenow && 0 !== strpos( $page, 'pods' ) ) || 0 === strpos( $page, 'pods-content' ) ) {
			return false;
		}

		return true;
	}

}
