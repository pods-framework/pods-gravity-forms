<?php

namespace Pods_Gravity_Forms;

/**
 * Plugin specific functionality.
 *
 * @since   1.5.0
 *
 * @package Pods_Gravity_Forms
 */
class Plugin {

	/**
	 * Constant that stores the current plugin version.
	 *
	 * @since 1.0.0
	 */
	const VERSION = '1.5.0-b-1';

	/**
	 * Constant that stores the minimum supported PHP version.
	 *
	 * @since 1.0.0
	 */
	const MIN_PHP_VERSION = '5.6';

	/**
	 * Constant that stores the minimum supported WP version.
	 *
	 * @since 1.0.0
	 */
	const MIN_WP_VERSION = '5.5';

	/**
	 * Constant that stores the minimum supported Pods version.
	 *
	 * @since 1.0.0
	 */
	const MIN_PODS_VERSION = '2.7';

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * The plugin name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_name = '';

	/**
	 * The plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_file = '';

	/**
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_slug = 'pods-gravity-forms';

	/**
	 * Plugin directory URL.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_dir_url = '';

	/**
	 * Plugin constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Set the plugin name.
		$this->plugin_name = __( 'Pods Gravity Forms Add-On', 'pods-gravity-forms' );

		// Store the plugin directory URL for assets usage later.
		$this->plugin_dir_url = plugin_dir_url( $this->plugin_file );

		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Setup and get the instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file The plugin file.
	 *
	 * @return self The instance of the class.
	 */
	public static function instance( $plugin_file ) {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		self::$instance->plugin_file = $plugin_file;

		return self::$instance;
	}

	/**
	 * Handle init of plugin functionality.
	 *
	 * @since 1.0.0
	 */
	public function init() {
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

		require_once __DIR__ . '/Integration.php';

		// Setup instances and run their hooks.
		$integrations = [
			Integration::instance(),
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
	 * @since 1.0.0
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
	 * @since 1.0.4
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
	 * @since 1.0.0
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
