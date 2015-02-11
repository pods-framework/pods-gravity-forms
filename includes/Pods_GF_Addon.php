<?php
// addon settings page
// addon feed
// addon mapping save

require_once( PODS_GF_DIR . 'includes/Pods_GF.php' );

class Pods_GF_Addon extends GFFeedAddOn {

	protected $_version = PODS_GF_VERSION;
	protected $_min_gravityforms_version = '1.8.8';
	protected $_path = PODS_GF_ADDON_FILE;
	protected $_full_path = PODS_GF_FILE;
	protected $_url = 'http://pods.io/';
	protected $_slug = 'pods-gravity-forms';
	protected $_title = 'Pods Gravity Forms Add-On';
	protected $_short_title = 'Pods';

	protected $_capabilities = array( 'pods_gravityforms', 'pods_gravityforms_uninstall' );

	public function plugin_page () {
		?>
		This page appears in the Forms menu
	<?php
	}

	public function feed_settings_fields () {

		$feed_field_name = array(
			'label'   => 'Name',
			'type'    => 'text',
			'name'    => 'feedName',
			'tooltip' => 'Name for this feed',
			'class'   => 'medium'
		);

		$pods_api          = pods_api();
		$all_pods          = $pods_api->load_pods( array( 'names' => true ) );
		$pod_choice_list   = array();
		$pod_choice_list[] = array( 'label' => __( 'Select a Pod', 'pods-gravity-forms' ),
									'value' => '' );
		foreach ( $all_pods as $name => $label ) {
			$pod_choice_list[] = array( 'label' => $label,
										'value' => $name );
		}

		$feed_field_pod = array(
			'label'    => 'Pod',
			'type'     => 'select',
			'name'     => 'pod',
			'tooltip'  => 'Select the pod',
			'choices'  => $pod_choice_list,
			'onchange' => "jQuery(this).parents('form').submit();",
			'required' => true
		);

		$selected_pod = $this->get_setting( 'pod' );
		$pod_fields   = array();
		if ( ! empty( $selected_pod ) ) {
			$pod_object = $pods_api->load_pod( array( 'name' => $selected_pod ) );
			if ( ! empty( $pod_object ) ) {
				foreach ( $pod_object['fields'] as $name => $field ) {
					$pod_fields[] = array( 'name'     => $name,
										   'label'    => $field['label'],
										   'required' => ( '0' == $field['options']['required'] ) ? false : true );
				}
			}
		}

		$feed_field_pod_fields = array(
			'name'       => 'pod_fields',
			'label'      => 'Pod Fields',
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => $pod_fields
		);

		$wp_object_fields = array();
		if ( ! empty( $pod_object ) ) {
			foreach ( $pod_object['object_fields'] as $name => $field ) {
				$wp_object_fields[] = array( 'name'  => $name,
											 'label' => $field['label'] );
			}
		}

		$feed_field_wp_object_fields = array(
			'name'       => 'wp_object_fields',
			'label'      => 'WP Object Fields',
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => $wp_object_fields
		);

		/*$feed_field_wp_object_fields = array(
			'name'              => 'wp_object_fields',
			'label'             => 'WP Object Fields',
			'type'              => 'dynamic_field_map',
			'dependency'        => 'pod',
			'key_choices'       => $wp_object_fields,
			'enable_custom_key' => false
		);*/


		return array(
			array(
				'title'  => 'Pods Feed Settings',
				'fields' => array(
					$feed_field_name,
					$feed_field_pod,
					$feed_field_pod_fields,
					$feed_field_wp_object_fields
				)
			)
		);
	}

	public function field_map_title () {
		return __( 'Pod Field', 'pods-gravity-forms' );
	}

	public function feed_list_columns () {
		return array(
			'feedName' => __( 'Name', 'pods-gravity-forms' ),
			'pod'      => __( 'Pod', 'pods-gravity-forms' )
		);
	}

	public function get_column_value_pod ( $feed ) {
		return "<b>" . $feed['meta']['pod'] . "</b>";
	}

	public function init_frontend () {
		parent::init_frontend();
		add_action( 'gform_pre_process', array( $this, '_gf_pre_process' ) );
	}

	public function _gf_pre_process ( $form ) {
		$feeds = $this->get_feeds( $form['id'] );
		if ( ! empty( $feeds ) && 1 == count( $feeds ) ) {
			$feed              = $feeds[0];
			$pod_fields        = array_flip( $this->get_field_map_fields( $feed, 'pod_fields' ) );
			$object_fields     = array_flip( $this->get_field_map_fields( $feed, 'wp_object_fields' ) );
			$options['fields'] = $pod_fields + $object_fields; //array ( 'GF_field_ID' => 'pod_field_name' )
			//$options['save_for_later']
			//$options['confirmation']
			//$options['read_only']
			//$options['dynamic_select']
			//$options['prepopulate']
			//$options['markdown']
			//$options['submit_button']
			//$options['secondary_submits']
			//$options['save_id']
			//$options['save_action']
			//$options['edit']
			//$options['auto_delete']
			//$options['keep_files']
			//see Pods_GF_UI->$actions

			pods_gf( $feed['meta']['pod'], $form['id'], $options );
		}
	}

	public static function get_field_map_fields ( $feed, $field_name ) {
		$fields = array();
		$prefix = "{$field_name}_";

		foreach ( $feed['meta'] as $name => $value ) {
			if ( ( strpos( $name, $prefix ) === 0 ) && ! empty( $value ) ) {
				$name          = str_replace( $prefix, '', $name );
				$fields[$name] = $value;
			}
		}

		return $fields;
	}

	public function get_entry_meta ( $entry_meta, $form_id ) {
		if ( $this->has_feed( $form_id ) ) {
			$entry_meta['pod_id'] = array(
				'label'                      => 'Pod ID',
				'is_numeric'                 => true,
				'is_default_column'          => true,
				'update_entry_meta_callback' => array( $this, 'update_entry_meta_pod_id' ),
				'filter'                     => array(
					'operators' => array( 'is', 'isnot', '>', '<' )
				)
			);
		}

		return $entry_meta;
	}

	public function update_entry_meta_pod_id ( $key, $entry, $form ) {
		if ( ! empty( Pods_GF::$gf_to_pods_id ) ) {
			$value = Pods_GF::$gf_to_pods_id;
		}
		else {
			$value = $entry[$key];
		}

		return $value;
	}
}

new Pods_GF_Addon();