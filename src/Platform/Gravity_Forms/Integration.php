<?php

namespace Pods_Gravity_Forms\Platform\Gravity_Forms;

use GFForms;
use WP_CLI;

/**
 * Gravity Forms integration functionality.
 *
 * @since 2.0.0
 *
 * @package Pods_Gravity_Forms
 */
class Integration {

	/**
	 * Integration instance.
	 *
	 * @since 2.0.0
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Empty constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		/*
		──────█▀▄─▄▀▄─▀█▀─█─█─▀─█▀▄─▄▀▀▀─────
		──────█─█─█─█──█──█▀█─█─█─█─█─▀█─────
		──────▀─▀──▀───▀──▀─▀─▀─▀─▀──▀▀──────
		─────────────────────────────────────
		───────────────▀█▀─▄▀▄───────────────
		────────────────█──█─█───────────────
		────────────────▀───▀────────────────
		─────────────────────────────────────
		─────█▀▀▄─█▀▀█───█──█─█▀▀─█▀▀█─█▀▀───
		─────█──█─█──█───█▀▀█─█▀▀─█▄▄▀─█▀▀───
		─────▀▀▀──▀▀▀▀───▀──▀─▀▀▀─▀─▀▀─▀▀▀───
		─────────────────────────────────────
		─────────▄███████████▄▄──────────────
		──────▄██▀──────────▀▀██▄────────────
		────▄█▀────────────────▀██───────────
		──▄█▀────────────────────▀█▄─────────
		─█▀──██──────────────██───▀██────────
		█▀──────────────────────────██───────
		█──███████████████████───────█───────
		█────────────────────────────█───────
		█────────────────────────────█───────
		█────────────────────────────█───────
		█────────────────────────────█───────
		█────────────────────────────█───────
		█▄───────────────────────────█───────
		▀█▄─────────────────────────██───────
		─▀█▄───────────────────────██────────
		──▀█▄────────────────────▄█▀─────────
		───▀█▄──────────────────██───────────
		─────▀█▄──────────────▄█▀────────────
		───────▀█▄▄▄──────▄▄▄███████▄▄───────
		────────███████████████───▀██████▄───
		─────▄███▀▀────────▀███▄──────█─███──
		───▄███▄─────▄▄▄▄────███────▄▄████▀──
		─▄███▓▓█─────█▓▓█───████████████▀────
		─▀▀██▀▀▀▀▀▀▀▀▀▀███████████────█──────
		────█─▄▄▄▄▄▄▄▄█▀█▓▓─────██────█──────
		────█─█───────█─█─▓▓────██────█──────
		────█▄█───────█▄█──▓▓▓▓▓███▄▄▄█──────
		────────────────────────██──────────
		────────────────────────██───▄███▄───
		────────────────────────██─▄██▓▓▓██──
		───────────────▄██████████─█▓▓▓█▓▓██▄
		─────────────▄██▀───▀▀███──█▓▓▓██▓▓▓█
		─▄███████▄──███───▄▄████───██▓▓████▓█
		▄██▀──▀▀█████████████▀▀─────██▓▓▓▓███
		██▀─────────██──────────────██▓██▓███
		██──────────███──────────────█████─██
		██───────────███──────────────█─██──█
		██────────────██─────────────────█───
		██─────────────██────────────────────
		██─────────────███───────────────────
		██──────────────███▄▄────────────────
		███──────────────▀▀███───────────────
		─███─────────────────────────────────
		──███────────────────────────────────
		*/
	}

	/**
	 * Setup and get the instance of the class.
	 *
	 * @since 2.0.0
	 *
	 * @return self The instance of the class.
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add the class hooks.
	 *
	 * @since 2.0.0
	 */
	public function hook() {
		$this->run();
	}

	/**
	 * Remove the class hooks.
	 *
	 * @since 2.0.0
	 */
	public function unhook() {

	}

	/**
	 * Get the list of requirement checks and error messages.
	 *
	 * @since 2.0.0
	 *
	 * @return array List of requirement checks and error messages.
	 */
	public function get_requirements() {
		return [
			[
				// Gravity Forms should be the minimum required version.
				'check'   => class_exists( 'GFForms' ) && version_compare( '2.6', GFForms::$version, '<=' ),
				'message' => __( 'You need Gravity Forms 2.6+ installed and activated in order to use the Pods Gravity Forms Add-On.', 'pods-gravity-forms' ),
			],
		];
	}

	/**
	 * Run the main integration code.
	 *
	 * @since 2.0.0
	 */
	public function run() {
		// Include Gravity Forms Add-On framework if needed.
		GFForms::include_addon_framework();
		GFForms::include_feed_addon_framework();

		// Include our Add-On code.
		require_once __DIR__ . '/Feed_AddOn.php';

		// Set up instance for the first time which kicks off GF Add-On framework __construct code.
		Feed_AddOn::get_instance();

		// Register the Pods GF CLI command.
		if ( defined( 'WP_CLI' ) ) {
			require_once __DIR__ . '/CLI_Command.php';

			WP_CLI::add_command( 'pods-gf', CLI_Command::class );
		}
	}

}
