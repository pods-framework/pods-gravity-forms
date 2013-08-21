<?php
if ( function_exists( 'pods' ) && class_exists( 'GFCommon' ) ) {
	/**
	 * Class Pods_GravityForms
	 */
	class Pods_GravityForms {

		/**
		 * @var Pods_GravityForms
		 */
		public static $obj;

		/**
		 * Setup Pods_GravityForms object
		 *
		 * @param array $options Pods_GravityForms option overrides
		 */
		public function __construct( $options ) {
			self::$obj =& $this;
		}

		/**
		 * Delete a GF entry, because GF doesn't have an API to do this yet (the function itself is user-restricted)
		 *
		 * @param array $entry GF Entry array
		 * @param bool $keep_files Whether to keep the files from the entry
		 * @return bool If the entry was successfully deleted
		 */
		public static function delete_entry( $entry, $keep_files = false ) {
			global $wpdb;

			if ( !is_array( $entry ) && 0 < (int) $entry ) {
				$lead_id = (int) $entry;
			}
			elseif ( is_array( $entry ) && isset( $entry[ 'id' ] ) && 0 < (int) $entry[ 'id' ] ) {
				$lead_id = (int) $entry[ 'id' ];
			}
			else {
				return false;
			}

			do_action( "gform_delete_lead", $lead_id );

			$lead_table = RGFormsModel::get_lead_table_name();
			$lead_notes_table = RGFormsModel::get_lead_notes_table_name();
			$lead_detail_table = RGFormsModel::get_lead_details_table_name();
			$lead_detail_long_table = RGFormsModel::get_lead_details_long_table_name();

			//deleting uploaded files
			if ( !$keep_files ) {
				RGFormsModel::delete_files( $lead_id );
			}

			//Delete from detail long
			$sql = $wpdb->prepare( " DELETE FROM $lead_detail_long_table
									WHERE lead_detail_id IN(
										SELECT id FROM $lead_detail_table WHERE lead_id=%d
									)", $lead_id );
			$wpdb->query( $sql );

			//Delete from lead details
			$sql = $wpdb->prepare( "DELETE FROM $lead_detail_table WHERE lead_id=%d", $lead_id );
			$wpdb->query( $sql );

			//Delete from lead notes
			$sql = $wpdb->prepare( "DELETE FROM $lead_notes_table WHERE lead_id=%d", $lead_id );
			$wpdb->query( $sql );

			//Delete from lead meta
			gform_delete_meta( $lead_id );

			//Delete from lead
			$sql = $wpdb->prepare( "DELETE FROM $lead_table WHERE id=%d", $lead_id );
			$wpdb->query( $sql );

			return true;
		}
	}
}