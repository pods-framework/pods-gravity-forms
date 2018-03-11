<?php
require_once( PODS_GF_DIR . 'includes/Pods_GF.php' );

/**
 * Class Pods_GF_Addon
 */
class Pods_GF_Addon extends GFFeedAddOn {

	/**
	 * @var string
	 */
	protected $_version = PODS_GF_VERSION;
	/**
	 * @var string
	 */
	protected $_min_gravityforms_version = '1.9';
	/**
	 * @var string
	 */
	protected $_path = PODS_GF_ADDON_FILE;
	/**
	 * @var string
	 */
	protected $_full_path = PODS_GF_FILE;
	/**
	 * @var string
	 */
	protected $_url = 'http://pods.io/';
	/**
	 * @var string
	 */
	protected $_slug = 'pods-gravity-forms';
	/**
	 * @var string
	 */
	protected $_title = 'Pods Gravity Forms Add-On';
	/**
	 * @var string
	 */
	protected $_short_title = 'Pods';

	/**
	 * @var array
	 */
	protected $_capabilities = array( 'pods_gravityforms', 'pods_gravityforms_uninstall' );
	/**
	 * @var array
	 */
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

	/**
	 * Override this function to allow the feed to being duplicated.
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return boolean|true
	 */
	public function can_duplicate_feed( $id ) {

		return true;

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

	/**
	 * @return array
	 */
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

		$selected_pod        = $this->get_setting( 'pod' );
		$enable_current_post = (int) $this->get_setting( 'enable_current_post' );
		$enable_current_user = (int) $this->get_setting( 'enable_current_user' );

		$posted_settings = $this->get_posted_settings();

		if ( isset( $posted_settings['enable_current_post'] ) ) {
			$enable_current_post = (int) $posted_settings['enable_current_post'];
		}

		if ( isset( $posted_settings['enable_current_user'] ) ) {
			$enable_current_user = (int) $posted_settings['enable_current_user'];
		}

		$pod_fields   = array();
		$pod_type     = '';

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

				if ( in_array( $pod_type, array( 'post_type', 'media' ), true ) ) {
					if ( in_array( $name, array( 'post_title', 'post_content' ), true ) ) {
						$field['options']['required'] = 1;
					}
				} elseif ( 'taxonomy' === $pod_type ) {
					if ( in_array( $name, array( 'name' ), true ) ) {
						$field['options']['required'] = 1;
					}
				} elseif ( 'user' === $pod_type ) {
					if ( in_array( $name, array( 'user_login' ), true ) ) {
						$field['options']['required'] = 1;
					}
				}

				$wp_object_fields[ $name ] = array(
					'needs_process' => true,
					'name'          => $name,
					'field'         => $field,
				);
			}
		}

		if ( 'post_type' === $pod_type ) {
			$wp_object_fields['_thumbnail_id'] = array(
				'name' => '_thumbnail_id',
				'label' => __( 'Featured Image', 'pods-gravity-forms' ),
			);
		}

		$feed_field_wp_object_fields = array(
			'name'       => 'wp_object_fields',
			'label'      => __( 'WP Object Fields', 'pods-gravity-forms' ),
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => array_values( $wp_object_fields ),
		);

		$settings = array();

		///////////////////
		// Pod feed mapping
		///////////////////
		$settings['pod_mapping'] = array(
			'title'  => __( 'Pod Feed Mapping', 'pods-gravity-forms' ),
			'fields' => array(
				$feed_field_name,
				$feed_field_pod,
				$feed_field_pod_fields,
			),
		);

		if ( ! empty( $feed_field_wp_object_fields['field_map'] ) ) {
			$settings['pod_mapping']['fields'][] = $feed_field_wp_object_fields;
		}

		$blacklisted_keys = array();

		// Build field mapping data arrays
		foreach ( $settings['pod_mapping']['fields'] as $k => $field_set ) {
			if ( empty( $field_set['field_map'] ) ) {
				continue;
			}

			foreach ( $field_set['field_map'] as $kf => $field_map ) {
				$blacklisted_keys[] = $field_map['name'];

				if ( ! empty( $field_map['needs_process'] ) ) {
					$name  = $field_map['name'];
					$field = $field_map['field'];

					$field_required = false;

					if ( isset( $field['options']['required'] ) && 1 === (int) $field['options']['required'] ) {
						$field_required = true;

						if ( isset( $wp_object_fields[ $name ] ) ) {
							if ( in_array( $pod_type, array( 'post_type', 'media' ), true ) && 1 === $enable_current_post ) {
								$field_required = false;
							} elseif ( 'user' === $pod_type && 1 === $enable_current_user ) {
								$field_required = false;
							}
						}
					}

					$field_map = array(
						'name'         => $name,
						'label'        => $field['label'],
						'required'     => $field_required,
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

				$settings['pod_mapping']['fields'][ $k ]['field_map'][ $kf ] = $field_map;
			}
		}

		///////////////////
		// Custom fields
		///////////////////
		if ( in_array( $pod_type, array( 'post_type', 'taxonomy', 'user', 'media', 'comment' ), true ) ) {
			$settings['custom_fields'] = array(
				'title'  => esc_html__( 'Custom Fields', 'pods-gravity-forms' ),
				'fields' => array(
					array(
						'name'        => 'custom_fields',
						'label'       => esc_html__( 'Custom Fields', 'pods-gravity-forms' ),
						'type'        => 'generic_map',
						'key_field'   => array(
							'choices'     => $this->get_meta_field_map( $selected_pod, $pod_type, $blacklisted_keys ),
							'placeholder' => esc_html__( 'Custom Field Name', 'pods-gravity-forms' ),
							'title'       => esc_html__( 'Name', 'pods-gravity-forms' ),
						),
						'value_field' => array(
							'choices'      => 'form_fields',
							'custom_value' => false,
							'merge_tags'   => true,
							'placeholder'  => esc_html__( 'Custom Field Value', 'pods-gravity-forms' ),
						),
					),
				),
			);
		}

		///////////////////
		// Advanced
		///////////////////
		$settings['advanced'] = array(
			'title'  => __( 'Advanced', 'pods-gravity-forms' ),
			'fields' => array(),
		);

		$settings['advanced']['fields'][] = array(
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

		$settings['advanced']['fields'][] = array(
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
			$settings['advanced']['fields'][] = array(
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

			$settings['advanced']['fields'][] = array(
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
		} elseif ( in_array( $pod_type, array( 'post_type', 'media' ), true ) ) {
			$settings['advanced']['fields'][] = array(
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

			$settings['advanced']['fields'][] = array(
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

		$settings['advanced']['fields'][] = array(
			'name'           => 'feed_condition',
			'label'          => __( 'Conditional Logic', 'pods-gravity-forms' ),
			'checkbox_label' => __( 'Enable', 'pods-gravity-forms' ),
			'type'           => 'feed_condition',
		);

		return $settings;

	}

	/**
	 * Prepare fields for meta field mapping.
	 *
	 * Props go to GF Post Creation add-on for the initial source of this method.
	 *
	 * @param string   $pod_name         Current pod name
	 * @param string   $pod_type         Current pod type
	 * @param string[] $blacklisted_keys Meta keys to exclude
	 *
	 * @uses GFFormsModel::get_custom_field_names()
	 *
	 * @return array
	 */
	public function get_meta_field_map( $pod_name = '', $pod_type = '', $blacklisted_keys = array() ) {

		// Setup meta fields array.
		$meta_fields = array(
			array(
				'label' => esc_html__( 'Select a Custom Field Name', 'pods-gravity-forms' ),
				'value' => '',
			),
		);

		///////////////////
		// Custom fields
		///////////////////

		// Get most used post meta keys
		$meta_keys = $this->get_custom_field_names( $pod_name, $pod_type );

		// If no meta keys exist, return an empty array.
		if ( empty( $meta_keys ) ) {
			return array();
		}

		// Add post meta keys to the meta fields array.
		foreach ( $meta_keys as $meta_key ) {
			$meta_fields[] = array(
				'label' => $meta_key,
				'value' => $meta_key,
			);
		}

		///////////////////
		// Custom key
		///////////////////
		$meta_fields[] = array(
			'label' => esc_html__( 'Add New Custom Field Name', 'pods-gravity-forms' ),
			'value' => 'gf_custom',
		);

		return $meta_fields;

	}

	/**
	 * Get most common custom field names from DB.
	 *
	 * @param string   $pod_name         Current pod name
	 * @param string   $pod_type         Current pod type
	 * @param string[] $blacklisted_keys Meta keys to exclude
	 *
	 * @return string[]
	 */
	public function get_custom_field_names( $pod_name = '', $pod_type = '', $blacklisted_keys = array() ) {

		global $wpdb;

		$object_table    = $wpdb->posts;
		$meta_table      = $wpdb->postmeta;
		$id_col          = 'ID';
		$meta_id_col     = 'post_id';
		$blacklist_where = "
				AND `object`.`post_type` LIKE '_pods_%'
		";

		if ( 'taxonomy' === $pod_type ) {
			$object_table    = $wpdb->terms;
			$meta_table      = $wpdb->termmeta;
			$id_col          = 'term_id';
			$meta_id_col     = 'term_id';
			$blacklist_where = '';
		} elseif ( 'user' === $pod_type ) {
			$object_table    = $wpdb->users;
			$meta_table      = $wpdb->usermeta;
			$id_col          = 'ID';
			$meta_id_col     = 'user_id';
			$blacklist_where = '';
		} elseif ( 'comment' === $pod_type ) {
			$object_table    = $wpdb->users;
			$meta_table      = $wpdb->usermeta;
			$id_col          = 'comment_ID';
			$meta_id_col     = 'comment_id';
			$blacklist_where = '';
		}

		$where = '';

		$pods_blacklist_keys = array();

		if ( $blacklist_where ) {
			$sql = "
				SELECT `meta`.`meta_key`
				FROM `{$meta_table}` AS `meta`
				LEFT JOIN `{$object_table}` AS `object` ON `object`.`{$id_col}` = `meta`.`{$meta_id_col}`
				WHERE
					`meta`.`meta_key` NOT LIKE '\_%' {$blacklist_where}
				GROUP BY `meta`.`meta_key`
			";

			$pods_blacklist_keys = $wpdb->get_col( $sql );
		}

		$pods_blacklist_keys = array_merge( $pods_blacklist_keys, $blacklisted_keys );
		$pods_blacklist_keys = array_unique( $pods_blacklist_keys );
		$pods_blacklist_keys = array_filter( $pods_blacklist_keys );

		if ( $pods_blacklist_keys ) {
			$placeholders = array_fill( 0, count( $pods_blacklist_keys ), '%s' );

			$where = "
				AND `meta`.`meta_key` NOT IN ( " . implode( ", ", $placeholders ) . " )
			";

			$where = $wpdb->prepare( $where, $pods_blacklist_keys );
		}

		$sql = "
			SELECT `meta`.`meta_key`, COUNT(*) AS `total_count`
			FROM `{$meta_table}` AS `meta`
			WHERE
				`meta`.`meta_key` NOT LIKE '\_%' {$where}
			GROUP BY `meta`.`meta_key`
			ORDER BY `total_count` DESC
			LIMIT 50
		";

		$meta_keys = $wpdb->get_col( $sql );

		if ( $meta_keys ) {
			natcasesort( $meta_keys );
		}

		return $meta_keys;

	}

	/**
	 * @param $choices
	 *
	 * @return array
	 */
	public function add_field_map_choices( $choices ) {

		// Remove first choice
		array_shift( $choices );

		$choices = array_merge(
			array(
				// Add first choice back
				array(
					'value' => '',
					'label' => __( 'Select a Field', 'gravityforms' ), // Use gravtiyforms text domain here
				),
				// Make custom override first option
				array(
					'value' => 'gf_custom',
					'label' => __( 'Custom override value', 'pods-gravity-forms' ),
				),
			),
			$choices
		);

		return $choices;

	}

	/**
	 * @return string|void
	 */
	public function field_map_title() {

		return __( 'Pod Field', 'pods-gravity-forms' );

	}

	/**
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feedName' => __( 'Name', 'pods-gravity-forms' ),
			'pod'      => __( 'Pod', 'pods-gravity-forms' )
		);

	}

	/**
	 * @param $feed
	 *
	 * @return string
	 */
	public function get_column_value_pod( $feed ) {

		return '<strong>' . $feed['meta']['pod'] . '</strong>';

	}

	/**
	 *
	 */
	public function init_admin() {

		parent::init_admin();

		add_action( 'gform_field_standard_settings', array( $this, 'populate_related_items_settings' ), 10, 2 );
		add_filter( 'gform_tooltips', array( $this, 'populate_related_items_tooltip' ) );
		add_action( 'gform_editor_js', array( $this, 'populate_related_items_editor_script' ) );

	}

	/**
	 * @param $position
	 * @param $form_id
	 */
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

	/**
	 *
	 */
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

	/**
	 * @param $tooltips
	 *
	 * @return mixed
	 */
	public function populate_related_items_tooltip( $tooltips ) {

	   $tooltips['form_populate_related_items_value'] = sprintf( '<h6>%s</h6> %s', __( 'Populate Related Items from Pods', 'pods-gravity-forms' ), __( 'Check this box to populate the related items from Pods instead of keeping the list up-to-date manually.' ) );

	   return $tooltips;

	}

	/**
	 *
	 */
	public function init() {

		parent::init();

		if ( $this->is_gravityforms_supported() ) {
			add_filter( 'gform_pre_render', array( $this, '_gf_pre_render' ) );
			add_filter( 'gform_pre_process', array( $this, '_gf_pre_process' ) );
		}

	}

	/**
	 * @param $form
	 *
	 * @return mixed
	 */
	public function _gf_pre_render( $form ) {

		$feeds = $this->get_feeds( $form['id'] );

		if ( empty( $feeds ) ) {
			return $form;
		}

		$pod_fields = array();
		$pod_name   = '';
		$feed       = null;

		$entry = GFFormsModel::get_current_lead();

		foreach ( $feeds as $feed ) {
			if ( 1 !== (int) $feed['is_active'] && $this->is_feed_condition_met( $feed, $form, $entry ) ) {
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

					$pod_field_options = $pod_obj->fields( $pod_field );

					// Override limit for autocomplete
					$object_params = array(
						'limit' => -1,
					);

					$data = PodsForm::field_method( 'pick', 'get_field_data', $pod_field_options, array(), $object_params );

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

	/**
	 * @param $form
	 *
	 * @return mixed
	 */
	public function _gf_pre_process( $form ) {

		static $setup = array();

		if ( ! empty( $setup[ $form['id'] ] ) ) {
			return $form;
		}

		$feeds = $this->get_feeds( $form['id'] );

		$entry = GFFormsModel::get_current_lead();

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] && $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					continue;
				}

				// Block new post being created in GF
				add_filter( 'gform_disable_post_creation_' . $form['id'], '__return_true' );

				$pod_fields    = $this->get_field_map_fields_with_custom_values( $feed, 'pod_fields' );
				$object_fields = $this->get_field_map_fields_with_custom_values( $feed, 'wp_object_fields' );
				$custom_fields = $this->get_field_map_fields_with_custom_values( $feed, 'custom_fields' );

				$fields = array_merge( $pod_fields, $object_fields, $custom_fields );

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
				} elseif ( in_array( $pod->pod_data['type'], array( 'post_type', 'media' ), true ) && is_singular( $pod->pod ) ) {
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
				// Skip override values (old way)
				continue;
			} elseif ( 0 === strpos( $config_field_name, $prefix ) ) {
				// Get field name
				$field_name = substr( $config_field_name, strlen( $prefix ) );

				// Mapping value is the GF field ID
				$gf_field = trim( $value );

				// Mapping value
				$mapping_value = array(
					'gf_field'      => $gf_field,
					'field'         => $field_name,
				);

				if ( 'gf_custom' === $gf_field ) {
					// Support override value settings (new way)
					$gf_field = sprintf( '_pods_gf_custom_%s', $field_name );
					$gf_field_custom = sprintf( '%s_custom', $field_name );

					$mapping_value['gf_field'] = $gf_field;
					$mapping_value['value']    = '';

					if ( ! empty( $feed['meta'][ $gf_field_custom ] ) ) {
						$value = trim( $feed['meta'][ $gf_field_custom ] );

						if ( ! empty( $value ) ) {
							$mapping_value['value'] = $value;

							$mapping_value['gf_merge_tags'] = true;
						}
					}
				} elseif ( '_pods_custom' === $gf_field ) {
					// Support override value settings (old way)
					$gf_field = sprintf( '_pods_gf_custom_%s', $field_name );

					$mapping_value['gf_field'] = $gf_field;
					$mapping_value['value']    = '';

					if ( ! empty( $feed['meta'][ $custom_prefix ] ) ) {
						$value = trim( $feed['meta'][ $custom_prefix ] );

						if ( ! empty( $value ) ) {
							$mapping_value['value'] = $value;

							$mapping_value['gf_merge_tags'] = true;
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

	/**
	 * @param array $entry_meta
	 * @param int   $form_id
	 *
	 * @return array
	 */
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

	/**
	 * @param $key
	 * @param $entry
	 * @param $form
	 *
	 * @return int
	 */
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
