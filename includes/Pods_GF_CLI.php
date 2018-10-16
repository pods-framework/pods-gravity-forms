<?php

/**
 * Implements Pods GF command for WP-CLI
 */
class Pods_GF_CLI extends \WP_CLI_Command {

	/**
	 * Sync form entries to a Pod.
	 *
	 * ## OPTIONS
	 *
	 * --form=<form>
	 * : The Gravity Form ID.
	 *
	 * [--feed=<feed>]
	 * : The Gravity Form Pods Feed ID.
	 *
	 * ## EXAMPLES
	 *
	 * wp pods-gf sync --form=123 --feed=2
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function sync( $args, $assoc_args ) {

		add_filter( 'pods_gf_to_pods_update_pod_items', '__return_true' );

		$form_id = 0;

		if ( ! empty( $assoc_args['form'] ) ) {
			$form_id = absint( $assoc_args['form'] );
		}

		if ( empty( $form_id ) ) {
			\WP_CLI::error( esc_html__( 'Form ID is required.', 'pods-gravity-forms' ) );
		}

		$feed_id = 0;

		if ( ! empty( $assoc_args['feed'] ) ) {
			$feed_id = absint( $assoc_args['feed'] );
		}

		// Get form.
		$form = \GFAPI::get_form( $form_id );

		if ( empty( $form ) || is_wp_error( $form ) ) {
			\WP_CLI::error( esc_html__( 'Form not found.', 'pods-gravity-forms' ) );
		}

		$active_only = true;

		if ( 0 < $feed_id ) {
			$active_only = false;
		}

		// Get feed.
		$feeds = \GFAPI::get_feeds( $feed_id, $form_id, 'pods-gravity-forms', $active_only );

		if ( empty( $feeds ) || is_wp_error( $feeds ) ) {
			\WP_CLI::error( esc_html__( 'Feed not found.', 'pods-gravity-forms' ) );
		}

		// Use first feed.
		$feed = reset( $feeds );

		$feed_id = $feed['id'];

		if ( empty( $feed_id ) ) {
			\WP_CLI::error( esc_html__( 'Invalid feed.', 'pods-gravity-forms' ) );
		}

		/** @var Pods_GF_Addon $pods_gf_addon */
		$pods_gf_addon = Pods_GF_Addon::get_instance();

		$pods_gf_addon->setup_pods_gf( $form, $feed );

		$pods_gf_addon->pods_gf[ $feed_id ]->options['update_pod_item'] = 1;

		$total_entries = 0;

		$search_criteria = array(
			'status' => 'active',
		);

		$paging = array(
			'offset'    => 0,
			'page_size' => 50,
		);

		$entries = \GFAPI::get_entries( $form_id, $search_criteria, null, $paging, $total_entries );

		/** @var \cli\progress\Bar $progress_bar */
		/* translators: Total entries number is used in this message. */
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( esc_html_x( 'Syncing %s entries', 'Sync status message for WP-CLI feed sync using total entries count', 'pods-gravity-forms' ), number_format_i18n( $total_entries ) ), $total_entries );

		$entries_counter = 0;

		// Loop through all pages of entries and process feeds.
		do {
			// Loop through entries and process feed.
			foreach ( $entries as $entry ) {
				$pods_gf_addon->process_feed( $feed, $entry, $form );

				Pods_GF::$actioned = [];

				$progress_bar->tick();

				$entries_counter++;
			}

			$paging['offset'] = $entries_counter;

			$entries = \GFAPI::get_entries( $form_id, $search_criteria, null, $paging );
		} while ( $entries );

		$progress_bar->finish();

		/* translators: Feed ID is used in this message. */
		\WP_CLI::success( sprintf( esc_html_x( 'Form entries synced to Pods using feed %d.', 'Success message for WP-CLI feed sync using Feed ID', 'pods-gravity-forms' ), $feed_id ) );

	}

}
