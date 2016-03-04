<?php
require_once( PODS_GF_DIR . 'includes/Pods_GF.php' );

class Pods_GF_Addon extends GFFeedAddOn {

	protected $_version = PODS_GF_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_path = PODS_GF_ADDON_FILE;
	protected $_full_path = PODS_GF_FILE;
	protected $_url = 'http://pods.io/';
	protected $_slug = 'pods-gravity-forms';
	protected $_title = 'Pods Gravity Forms Add-On';
	protected $_short_title = 'Pods';

	protected $_capabilities = array( 'pods_gravityforms', 'pods_gravityforms_uninstall' );
	protected $_capabilities_form_settings = array( 'pods_gravityforms', 'pods' );

	/*public function plugin_page() {

		?>
		This page appears in the Forms menu
		<?php

	}*/

	public function feed_settings_fields() {

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
		$pod_choice_list[] = array(
			'label' => __( 'Select a Pod', 'pods-gravity-forms' ),
			'value' => ''
		);
		foreach ( $all_pods as $name => $label ) {
			$pod_choice_list[] = array(
				'label' => $label,
				'value' => $name
			);
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
		$pod_type     = '';
		if ( ! empty( $selected_pod ) ) {
			$pod_object = $pods_api->load_pod( array( 'name' => $selected_pod ) );
			if ( ! empty( $pod_object ) ) {
				$pod_type = $pod_object['type'];
				foreach ( $pod_object['fields'] as $name => $field ) {
					$pod_fields[] = array(
						'name'     => $name,
						'label'    => $field['label'],
						'required' => ( '0' == $field['options']['required'] ) ? false : true
					);
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

		$ignore_object_fields = array(
			'ID',
			'post_type',
			'comment_type',
			'taxonomy',
			'guid',
			'menu_order',
			'post_mime_type',
			'comment_count',
			'comment_status',
			'ping_status',
			'post_date_gmt',
			'post_modified_gmt',
			'post_password',
			'post_status',
			'post_content_filtered',
			'pinged',
			'to_ping',
		);

		$wp_object_fields = array();
		if ( ! empty( $pod_object ) ) {
			foreach ( $pod_object['object_fields'] as $name => $field ) {
				if ( in_array( $name, $ignore_object_fields ) ) {
					continue;
				}

				$wp_object_fields[] = array(
					'name'  => $name,
					'label' => $field['label']
				);
			}
		}

		if ( 'post_type' == $pod_type ) {
			$wp_object_fields[] = array( 'name' => '_thumbnail_id', 'label' => 'Featured Image' );
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

		$settings = array(
			'title'  => 'Pods Feed Settings',
			'fields' => array(
				$feed_field_name,
				$feed_field_pod,
				$feed_field_pod_fields,
				$feed_field_wp_object_fields
			)
		);

		$settings['fields'][] = array(
			'name'    => 'delete_entry',
			'label'   => 'Delete Gravity Form Entry on submission',
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'value' => 1,
					'label' => __( 'Delete entry after processing', 'pods-gravity-forms' ),
					'name'  => 'delete_entry',
				),
			),
		);

		$settings['fields'][] = array(
			'name'    => 'enable_markdown',
			'label'   => 'Enable Markdown',
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'value' => 1,
					'label' => __( 'Enable Markdown in HTML Fields', 'pods-gravity-forms' ),
					'name'  => 'enable_markdown',
				),
			),
		);

		return array( $settings );

	}

	public function field_map_title() {

		return __( 'Pod Field', 'pods-gravity-forms' );

	}

	public function feed_list_columns() {

		return array(
			'feedName' => __( 'Name', 'pods-gravity-forms' ),
			'pod'      => __( 'Pod', 'pods-gravity-forms' )
		);

	}

	public function get_column_value_pod( $feed ) {

		return '<strong>' . $feed['meta']['pod'] . '</strong>';

	}

	public function init_admin() {

		parent::init_admin();

		add_action( 'gform_field_standard_settings', array( $this, 'populate_related_items_settings' ), 10, 2 );
		add_filter( 'gform_tooltips', array( $this, 'populate_related_items_tooltip' ) );
		add_action( 'gform_editor_js', array( $this, 'populate_related_items_editor_script' ) );

	}

	public function populate_related_items_settings( $position, $form_id ) {

	    if ( -1 == $position ) {
	        ?>
	        <li class="pods_populate_related_items_setting field_setting">
                <?php _e( 'Pods', 'pods-gravity-forms' ); ?><br />

	            <input type="checkbox" id="pods_populate_related_items_value" onclick="SetFieldProperty('pods_populate_related_items', this.checked);" />
	            <label for="pods_populate_related_items_value" class="inline">
		            <?php _e( 'Populate Related Items (requires a feed configured)', 'pods-gravity-forms' ); ?>
	                <?php gform_tooltip( 'form_populate_related_items_value' ) ?>
	            </label>
	        </li>
	        <?php
	    }

	}

	public function populate_related_items_editor_script() {

?>
	<script type='text/javascript'>
	    fieldSettings['select'] += ', .pods_populate_related_items_setting';
	    fieldSettings['multiselect'] += ', .pods_populate_related_items_setting';
	    fieldSettings['checkbox'] += ', .pods_populate_related_items_setting';
	    fieldSettings['radio'] += ', .pods_populate_related_items_setting';

	    jQuery( document ).bind( 'gform_load_field_settings', function ( event, field, form ) {
		    jQuery( '#pods_populate_related_items_value' ).attr( 'checked', field['pods_populate_related_items'] == true );
	    } );
	</script>
<?php

	}

	public function populate_related_items_tooltip( $tooltips ) {

	   $tooltips['form_populate_related_items_value'] = sprintf( '<h6>%s</h6> %s', __( 'Populate Related Items from Pods', 'pods-gravity-forms' ), __( 'Check this box to populate the related items from Pods instead of keeping the list up-to-date manually.' ) );

	   return $tooltips;

	}

	public function init_frontend() {

		parent::init_frontend();

		add_filter( 'gform_pre_render', array( $this, '_gf_pre_render' ) );
		add_action( 'gform_pre_process', array( $this, '_gf_pre_process' ) );

	}

	public function _gf_pre_render( $form ) {

		$feeds = $this->get_feeds( $form['id'] );

		if ( empty( $feeds ) ) {
			return $form;
		}

		$pod_fields = array();
		$pod_name = '';

		foreach ( $feeds as $feed ) {
			if ( 1 !== (int) $feed['is_active'] ) {
				continue;
			}

			$pod_fields    = $this->get_field_map_fields( $feed, 'pod_fields' );
			$object_fields = $this->get_field_map_fields( $feed, 'wp_object_fields' );

			$pod_fields = array_flip( array_merge( $pod_fields, $object_fields ) );

			$pod_name = $feed['meta']['pod'];

			break;
		}

		if ( $pod_fields && $pod_name ) {
			$pod_obj = pods( $pod_name, null, false );

			if ( empty( $pod_obj ) || ! $pod_obj->valid() ) {
				return $form;
			}

			/**
			 * @var GF_Field $gf_field
			 */
			foreach ( $form['fields'] as $gf_field ) {
				if ( ! empty( $gf_field->pods_populate_related_items ) && ! empty( $pod_fields[ (string) $gf_field->id ] ) ) {
					$data = $pod_obj->fields( $pod_fields[ (string) $gf_field->id ], 'data' );

					if ( empty( $data ) ) {
						continue;
					}

					$options = array(
						'options' => $data,
					);

					Pods_GF::dynamic_select( $form['id'], $gf_field->id, $options );
				}
			}
		}

		// @todo Add support for Pods_GF::remember()

		return $form;

	}

	public function _gf_pre_process( $form ) {

		$feeds = $this->get_feeds( $form['id'] );

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] ) {
					continue;
				}

				// Block new post being created in GF
				add_filter( 'gform_disable_post_creation_' . $form['id'], '__return_true' );

				$pod_fields    = $this->get_field_map_fields( $feed, 'pod_fields' );
				$object_fields = $this->get_field_map_fields( $feed, 'wp_object_fields' );

				$fields = array_flip( array_merge( $pod_fields, $object_fields ) );

				$options = array(
					// array ( 'gf_field_id' => 'pod_field_name' )
					'fields'              => $fields,
					'auto_delete'         => (int) pods_v( 'delete_entry', $feed['meta'], 0 ),
					'markdown'            => (int) pods_v( 'enable_markdown', $feed['meta'], 0 ),
					'gf_to_pods_priority' => 'submission',
				);

				pods_gf( $feed['meta']['pod'], $form['id'], $options );
			}
		}

	}

	public static function get_field_map_fields( $feed, $field_name ) {

		$fields = array();
		$prefix = "{$field_name}_";

		foreach ( $feed['meta'] as $name => $value ) {
			if ( ( strpos( $name, $prefix ) === 0 ) && ! empty( $value ) ) {
				$name            = str_replace( $prefix, '', $name );
				$fields[ $name ] = $value;
			}
		}

		return $fields;

	}

	public function get_entry_meta( $entry_meta, $form_id ) {

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

	public function update_entry_meta_pod_id( $key, $entry, $form ) {

		if ( ! empty( Pods_GF::$gf_to_pods_id ) ) {
			$value = Pods_GF::$gf_to_pods_id;
		} else {
			$value = 0;
		}

		return $value;

	}
}

new Pods_GF_Addon();
