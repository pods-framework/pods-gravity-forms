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

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @used-by Pods_GF_Addon::get_instance()
	 *
	 * @var object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses Pods_GF_Addon
	 * @uses Pods_GF_Addon::$_instance
	 *
	 * @return object Pods_GF_Addon
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new Pods_GF_Addon;
		}

		return self::$_instance;

	}

	public function scripts() {

		$scripts = array(
			array(
				'handle'  => 'pods_gf_admin',
				'enqueue' => array( array( 'admin_page' => array( 'form_settings' ) ) ),
				'src'     => PODS_GF_URL . '/ui/pods-gf-admin.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
			),
		);

		return array_merge( parent::scripts(), $scripts );

	}

	/*public function plugin_page() {

		?>
		This page appears in the Forms menu
		<?php

	}*/

	public function feed_settings_fields() {

		$feed_field_name = array(
			'label'   => __( 'Name', 'pods-gravity-forms' ),
			'type'    => 'text',
			'name'    => 'feedName',
			'tooltip' => __( 'Name for this feed', 'pods-gravity-forms' ),
			'class'   => 'medium',
		);

		$gf_fields = array();

		if ( 0 === (int) pods_v( 'fid' ) ) {
			$gf_form = GFAPI::get_form( pods_v( 'id' ) );

			if ( ! empty( $gf_form ) && ! empty( $gf_form['fields'] ) ) {
				$gf_fields = $gf_form['fields'];
			}
		}

		$pods_api = pods_api();
		$all_pods = $pods_api->load_pods( array( 'names' => true ) );

		$pod_choice_list   = array();
		$pod_choice_list[] = array(
			'label' => __( 'Select a Pod', 'pods-gravity-forms' ),
			'value' => '',
		);

		foreach ( $all_pods as $name => $label ) {
			$pod_choice_list[] = array(
				'label' => $label . ' (' . $name . ')',
				'value' => $name,
			);
		}

		$feed_field_pod = array(
			'label'    => __( 'Pod', 'pods-gravity-forms' ),
			'type'     => 'select',
			'name'     => 'pod',
			'tooltip'  => __( 'Select the pod', 'pods-gravity-forms' ),
			'choices'  => $pod_choice_list,
			'onchange' => "jQuery(this).parents('form').submit();",
			'required' => true,
		);

		$selected_pod = $this->get_setting( 'pod' );
		$pod_fields   = array();
		$pod_type     = '';

		$custom_name = '_gaddon_setting_pod_fields_custom_override_%s';
		$custom_value_name = 'pod_fields_custom_override_%s';

		$after_select = '
			<div class="pods-custom-override%%s">
				<label for="%s">
					%s:
				</label>
				<input type="text" name="%s" value="%%s" placeholder="%s" class="fieldwidth-3" id="%s" />
			</div>
		';

		$after_select = sprintf(
			$after_select,
			esc_attr( $custom_name ),
			esc_html__( 'Override value', 'pods-gravity-forms' ),
			esc_attr( $custom_name ),
			esc_attr__( 'Enter text here', 'pods-gravity-forms' ),
			esc_attr( $custom_name )
		);

		if ( ! empty( $selected_pod ) ) {
			$pod_object = $pods_api->load_pod( array( 'name' => $selected_pod ) );

			if ( ! empty( $pod_object ) ) {
				$pod_type = $pod_object['type'];

				foreach ( $pod_object['fields'] as $name => $field ) {
					$pod_fields[] = array(
						'needs_process' => true,
						'name'          => $name,
						'field'         => $field,
					);
				}
			}
		}

		$feed_field_pod_fields = array(
			'name'       => 'pod_fields',
			'label'      => __( 'Pod Fields', 'pods-gravity-forms' ),
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => $pod_fields,
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
				if ( in_array( $name, $ignore_object_fields, true ) ) {
					continue;
				}

				$wp_object_fields[] = array(
					'needs_process' => true,
					'name'          => $name,
					'field'         => $field,
				);
			}
		}

		if ( 'post_type' === $pod_type ) {
			$wp_object_fields[] = array(
				'name' => '_thumbnail_id',
				'label' => __( 'Featured Image', 'pods-gravity-forms' ),
			);
		}

		$feed_field_wp_object_fields = array(
			'name'       => 'wp_object_fields',
			'label'      => __( 'WP Object Fields', 'pods-gravity-forms' ),
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => $wp_object_fields,
		);

		$settings = array(
			'title'  => __( 'Pods Feed Settings', 'pods-gravity-forms' ),
			'fields' => array(
				$feed_field_name,
				$feed_field_pod,
				$feed_field_pod_fields,
			),
		);

		if ( ! empty( $feed_field_wp_object_fields['field_map'] ) ) {
			$settings['fields'][] = $feed_field_wp_object_fields;
		}

		// Build field mapping data arrays
		foreach ( $settings['fields'] as $k => $field_set ) {
			if ( empty( $field_set['field_map'] ) ) {
				continue;
			}

			foreach ( $field_set['field_map'] as $kf => $field_map ) {
				if ( ! empty( $field_map['needs_process'] ) ) {
					$name  = $field_map['name'];
					$field = $field_map['field'];

					$field_value        = $this->get_setting( $name );
					$custom_field_value = $this->get_setting( sprintf( $custom_value_name, $name ) );

					$field_required = false;

					if ( isset( $field['options']['required'] ) && 1 === (int) $field['options']['required'] ) {
						$field_required = true;
					}

					$container_class = ' hidden';

					if ( '_pods_custom' === $field_value ) {
						$container_class = '';
					}

					$field_map = array(
						'name'         => $name,
						'label'        => $field['label'],
						'required'     => $field_required,
						'after_select' => sprintf(
							$after_select,
							esc_attr( $container_class ),
							esc_attr( $name ),
							esc_attr( $name ),
							esc_attr( $custom_field_value ),
							esc_attr( $name )
						),
					);

					foreach ( $gf_fields as $gf_field ) {
						if ( $field['label'] === $gf_field['label'] ) {
							$field_map['default_value'] = $gf_field['id'];
						}
					}
				}

				// Add field names to labels
				$field_map['label'] = sprintf(
					'%s<br /><small>(%s)</small>',
					esc_html( $field_map['label'] ),
					esc_html( $field_map['name'] )
				);

				$settings['fields'][ $k ]['field_map'][ $kf ] = $field_map;
			}
		}

		$settings['fields'][] = array(
			'name'    => 'delete_entry',
			'label'   => __( 'Delete Gravity Form Entry on submission', 'pods-gravity-forms' ),
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
			'label'   => __( 'Enable Markdown', 'pods-gravity-forms' ),
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'value' => 1,
					'label' => __( 'Enable Markdown in HTML Fields', 'pods-gravity-forms' ),
					'name'  => 'enable_markdown',
				),
			),
		);

		if ( 'user' === $pod_type ) {
			$settings['fields'][] = array(
				'name'    => 'enable_current_user',
				'label'   => __( 'Enable editing with this form using logged in user', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'value' => 1,
						'label' => __( 'Enable editing with this form using the logged in user data', 'pods-gravity-forms' ),
						'name'  => 'enable_current_user',
					),
				),
			);

			$settings['fields'][] = array(
				'name'    => 'enable_prepopulate',
				'label'   => __( 'Enable populating field values for this form using logged in user', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'value' => 1,
						'label' => __( 'Enable populating field values for this form using the logged in user data', 'pods-gravity-forms' ),
						'name'  => 'enable_prepopulate',
					),
				),
			);
		} elseif ( 'post_type' === $pod_type ) {
			$settings['fields'][] = array(
				'name'    => 'enable_current_post',
				'label'   => __( 'Enable editing with this form using current post', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'value' => 1,
						'label' => __( 'Enable editing with this form using the current post ID (only works on singular template)', 'pods-gravity-forms' ),
						'name'  => 'enable_current_post',
					),
				),
			);

			$settings['fields'][] = array(
				'name'    => 'enable_prepopulate',
				'label'   => __( 'Enable populating field values for this form using current post', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'value' => 1,
						'label' => __( 'Enable populating field values for this form using the current post ID (only works on singular template)', 'pods-gravity-forms' ),
						'name'  => 'enable_prepopulate',
					),
				),
			);
		}

		$addon_slug = $this->get_slug();

		add_filter( "gform_{$addon_slug}_field_map_choices", array( $this, 'add_field_map_choices' ) );

		$setting_fields = array(
			$settings,
		);

		return $setting_fields;

	}

	public function add_field_map_choices( $choices ) {

		$choices[] = array(
			'value' => '_pods_custom',
			'label' => __( 'Custom override value', 'pods-gravity-forms' ),
		);

		return $choices;

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

	    if ( -1 === $position ) {
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

	public function init() {

		parent::init();

		if ( $this->is_gravityforms_supported() ) {
			add_filter( 'gform_pre_render', array( $this, '_gf_pre_render' ) );
			add_filter( 'gform_pre_process', array( $this, '_gf_pre_process' ) );
		}

	}

	public function _gf_pre_render( $form ) {

		$feeds = $this->get_feeds( $form['id'] );

		if ( empty( $feeds ) ) {
			return $form;
		}

		$pod_fields = array();
		$pod_name   = '';
		$feed       = null;

		foreach ( $feeds as $feed ) {
			if ( 1 !== (int) $feed['is_active'] ) {
				continue;
			}

			$pod_fields    = $this->get_field_map_fields_with_custom_values( $feed, 'pod_fields' );
			$object_fields = $this->get_field_map_fields_with_custom_values( $feed, 'wp_object_fields' );

			$pod_fields = array_merge( $pod_fields, $object_fields );

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
				if ( ! empty( $gf_field->pods_populate_related_items ) ) {
					$pod_field = null;

					foreach ( $pod_fields as $k => $field_options ) {
						if ( (string) $gf_field->id === (string) $field_options['gf_field'] ) {
							$pod_field = $field_options['field'];
						}
					}

					if ( empty( $pod_field ) ) {
						continue;
					}

					$data = $pod_obj->fields( $pod_field, 'data' );

					if ( empty( $data ) ) {
						continue;
					}

					$options = array(
						'options' => $data,
					);

					Pods_GF::dynamic_select( $form['id'], (string) $gf_field->id, $options );
				}
			}

			// Support other options like prepopulating etc
			$this->_gf_pre_process( $form );
		}

		return $form;

	}

	public function _gf_pre_process( $form ) {

		static $setup = array();

		if ( ! empty( $setup[ $form['id'] ] ) ) {
			return $form;
		}

		$feeds = $this->get_feeds( $form['id'] );

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] ) {
					continue;
				}

				// Block new post being created in GF
				add_filter( 'gform_disable_post_creation_' . $form['id'], '__return_true' );

				$pod_fields    = $this->get_field_map_fields_with_custom_values( $feed, 'pod_fields' );
				$object_fields = $this->get_field_map_fields_with_custom_values( $feed, 'wp_object_fields' );

				$fields = array_merge( $pod_fields, $object_fields );

				$options = array(
					// array ( 'gf_field_id' => 'pod_field_name' )
					'fields'              => $fields,
					'auto_delete'         => (int) pods_v( 'delete_entry', $feed['meta'], 0 ),
					'markdown'            => (int) pods_v( 'enable_markdown', $feed['meta'], 0 ),
					'gf_to_pods_priority' => 'submission',
				);

				// Setup pod object
				$pod = pods( $feed['meta']['pod'] );

				$edit_id        = 0;
				$prepopulate    = false;
				$prepopulate_id = 0;

				if ( 'user' === $pod->pod_data['type'] && is_user_logged_in() ) {
					// Support user data editing
					if ( 1 === (int) pods_v( 'enable_current_user', $feed['meta'], 0 ) ) {
						$edit_id = get_current_user_id();
					}

					// Support prepopulating
					if ( 1 === (int) pods_v( 'enable_prepopulate', $feed['meta'], 0 ) ) {
						$prepopulate = true;

						$prepopulate_id = get_current_user_id();
					}
				} elseif ( 'post_type' === $pod->pod_data['type'] && is_singular( $pod->pod ) ) {
					// Support post data editing
					if ( 1 === (int) pods_v( 'enable_current_post', $feed['meta'], 0 ) ) {
						$edit_id = get_the_ID();
					}

					// Support prepopulating
					if ( 1 === (int) pods_v( 'enable_prepopulate', $feed['meta'], 0 ) ) {
						$prepopulate = true;

						$prepopulate_id = get_the_ID();
					}
				}

				/**
				 * Allow filtering of which item ID to use when editing (default none, always add new items)
				 *
				 * @param int    $edit_id  Edit ID
				 * @param string $pod_name Pod name
				 * @param int    $form_id  GF Form ID
				 * @param array  $feed     GF Form feed array
				 * @param array  $form     GF Form array
				 * @param array  $options  Pods GF options
				 * @param Pods   $pod      Pods object
				 */
				$edit_id = (int) apply_filters( 'pods_gf_addon_edit_id', $edit_id, $feed['meta']['pod'], $form['id'], $feed, $form, $options, $pod );

				/**
				 * Allow filtering of whether to prepopulate form fields (default none)
				 *
				 * @param bool   $prepopulate Whether to prepopulate or not
				 * @param string $pod_name    Pod name
				 * @param int    $form_id     GF Form ID
				 * @param array  $feed        GF Form feed array
				 * @param array  $form        GF Form array
				 * @param array  $options     Pods GF options
				 * @param Pods   $pod         Pods object
				 */
				$prepopulate = (boolean) apply_filters( 'pods_gf_addon_prepopulate', $prepopulate, $feed['meta']['pod'], $form['id'], $feed, $form, $options, $pod );

				if ( empty( $edit_id ) && $prepopulate ) {
					/**
					 * Allow filtering of which item ID to use when prepopulating form fields (default is same as Edit ID)
					 *
					 * @param int    $prepopulate_id  ID to use when prepopulating
					 * @param string $pod_name Pod name
					 * @param int    $form_id  GF Form ID
					 * @param array  $feed     GF Form feed array
					 * @param array  $form     GF Form array
					 * @param array  $options  Pods GF options
					 * @param Pods   $pod      Pods object
					 */
					$prepopulate_id = (int) apply_filters( 'pods_gf_addon_prepopulate_id', $prepopulate_id, $feed['meta']['pod'], $form['id'], $feed, $form, $options, $pod );
				}

				if ( 0 < $edit_id ) {
					$options['edit'] = true;

					if ( $prepopulate ) {
						$options['prepopulate'] = true;
					}

					$pod->fetch( $edit_id );
				} elseif ( $prepopulate && 0 < $prepopulate_id ) {
					$options['prepopulate'] = true;

					$pod->fetch( $prepopulate_id );
				}

				/**
				 * Allow filtering of Pods GF options to set custom settings apart from Pods GF add-on options
				 *
				 * @param array  $options  Pods GF options
				 * @param string $pod_name Pod name
				 * @param int    $form_id  GF Form ID
				 * @param array  $feed     GF Form feed array
				 * @param array  $form     GF Form array
				 * @param Pods   $pod      Pods object
				 */
				$options = apply_filters( 'pods_gf_addon_options', $options, $feed['meta']['pod'], $form['id'], $feed, $form, $pod );

				pods_gf( $pod, $form['id'], $options );

				$setup[ $form['id'] ] = true;
			}
		}

		return $form;

	}

	/**
	 * Get field map field values with support for custom override values.
	 *
	 * This differs from get_field_map_fields() in that it returns an array
	 * that is already formatted for Pods GF field mapping usage.
	 *
	 * @param array  $feed
	 * @param string $field_name
	 *
	 * @return array
	 */
	public static function get_field_map_fields_with_custom_values( $feed, $field_name ) {

		$prefix        = $field_name . '_';
		$custom_prefix = $prefix . 'override_custom_';

		$fields = array();

		foreach ( $feed['meta'] as $config_field_name => $value ) {
			$config_field_name = (string) $config_field_name;

			if ( 0 === strpos( $config_field_name, $custom_prefix ) ) {
				// Skip override values
				continue;
			} elseif ( 0 === strpos( $config_field_name, $prefix ) ) {
				// Get field name
				$field_name = substr( $config_field_name, strlen( $prefix ) );

				// Mapping value is the GF field ID
				$gf_field = trim( $value );

				// Mapping value
				$mapping_value = array(
					'gf_field' => $gf_field,
					'field'    => $field_name,
				);

				// Support override value settings
				if ( '_pods_custom' === $gf_field ) {
					$gf_field = sprintf( '_pods_gf_custom_%s', $field_name );

					$mapping_value = array(
						'gf_field' => $gf_field,
						'field'    => $field_name,
						'value'    => '',
					);

					if ( ! empty( $feed['meta'][ $custom_prefix ] ) ) {
						$value = trim( $feed['meta'][ $custom_prefix ] );

						if ( ! empty( $value ) ) {
							$mapping_value['value'] = $value;
						}
					}
				}

				if ( ! empty( $gf_field ) ) {
					$fields[] = $mapping_value;
				}
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

		if ( ! empty( Pods_GF::$gf_to_pods_id[ $form['id'] ] ) ) {
			$value = Pods_GF::$gf_to_pods_id[ $form['id'] ];
		} else {
			$value = 0;
		}

		return $value;

	}
}

new Pods_GF_Addon();
