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
	 * @var Pods_GF[]
	 */
	public $pods_gf = array();

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

	/**
	 * @return array
	 */
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

		$gf_form = GFAPI::get_form( pods_v( 'id' ) );

		if ( ! empty( $gf_form ) && ! empty( $gf_form['fields'] ) ) {
			$gf_fields = $gf_form['fields'];
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

		$pod_fields = array();
		$pod_type   = '';

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
					if ( 1 === $enable_current_post ) {
						$field['options']['required'] = 0;
					} elseif ( in_array( $name, array( 'post_title', 'post_content' ), true ) ) {
						$field['options']['required'] = 1;
					}
				} elseif ( 'taxonomy' === $pod_type ) {
					if ( 'name' === $name ) {
						$field['options']['required'] = 1;
					}
				} elseif ( 'user' === $pod_type ) {
					if ( 1 === $enable_current_user ) {
						$field['options']['required'] = 0;
					} elseif ( 'user_login' === $name ) {
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

					if ( 0 === (int) pods_v( 'fid' ) ) {
						foreach ( $gf_fields as $gf_field ) {
							if ( strtolower( $field['label'] ) === strtolower( $gf_field['label'] ) ) {
								$field_map['default_value'] = $gf_field['id'];
							}
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
			'name'    => 'update_pod_item',
			'label'   => __( 'Support entry updates', 'pods-gravity-forms' ),
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'value' => 1,
					'label' => __( 'Update pod item if the entry is updated', 'pods-gravity-forms' ),
					'name'  => 'update_pod_item',
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
			$choices,
			array(
				array(
					'value' => 'transaction_id',
					'label' => 'Transaction ID',
				),
				array(
					'value' => 'payment_amount',
					'label' => 'Payment Amount',
				),
				array(
					'value' => 'payment_date',
					'label' => 'Payment Date',
				),
				array(
					'value' => 'payment_status',
					'label' => 'Payment Status',
				),
			)
		);

		foreach ( $choices as $k => $choice ) {
			if ( '_pods_item_id' === $choice['value'] ) {
				unset( $choices[ $k ] );

				$choices = array_values( $choices );

				break;
			}
		}

		return $choices;

	}

	/***
	 * Renders and initializes a drop down field based on the $field array
	 *
	 * @param array $field - Field array containing the configuration options of this field
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string
	 *
	 * @return string The HTML for the field
	 */
	public function settings_select( $field, $echo = true ) {

		$has_gf_custom = false;

		if ( ! empty( $field['choices'] ) ) {
			foreach ( $field['choices'] as $choice ) {
				if ( isset( $choice['value'] ) && 'gf_custom' === $choice['value'] ) {
					$has_gf_custom = true;

					break;
				}
			}
		}

		// Select has no custom choice or we already took over the first select.
		if ( empty( $field['choices'] ) || ! $has_gf_custom || ! empty( $field['_pods_custom_select'] ) ) {
			return parent::settings_select( $field, $echo );
		}

		// Already doing custom select.
		if ( ! empty( $field['type'] ) && 'generic_map' === $field['type'] ) {
			return parent::settings_select( $field, $echo );
		}

		$field['_pods_custom_select'] = true;

		return parent::settings_select_custom( $field, $echo );

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
	 * Init integration.
	 */
	public function init() {
		parent::init();

		if ( ! $this->is_gravityforms_supported() ) {
			return;
		}

		// Handle normal forms.
		add_filter( 'gform_pre_render', array( $this, '_gf_pre_render' ), 9, 3 );
		add_filter( 'gform_admin_pre_render', array( $this, '_gf_pre_render' ), 9, 1 );
		add_filter( 'gform_pre_process', array( $this, '_gf_pre_process' ) );

		// Handle merge tags
		add_filter( 'gform_custom_merge_tags', array( $this, '_gf_custom_merge_tags' ), 10, 2 );
		add_filter( 'gform_merge_tag_data', array( $this, '_gf_add_merge_tags' ), 10, 3 );
		add_filter( 'gform_replace_merge_tags', array( $this, '_gf_replace_merge_tags' ), 10, 2 );

		// Handle entry detail edits.
		add_action( 'gform_pre_entry_detail', array( $this, '_gf_pre_entry_detail' ), 10, 2 );
		add_action( 'check_admin_referer', array( $this, '_check_admin_referer' ), 10, 2 );
		add_action( 'gform_entry_detail_content_before', array( $this, '_gf_entry_detail_content_before' ), 10, 2 );

		// Handle entry updates.
		add_action( 'gform_post_update_entry', array( $this, '_gf_post_update_entry' ), 9, 2 );
		add_action( 'gform_after_update_entry', array( $this, '_gf_after_update_entry' ), 9, 3 );

		// Handle Payment Add-on callbacks.
		add_action( 'gform_action_pre_payment_callback', array( $this, '_gf_action_pre_payment_callback' ), 10, 2 );
	}

	/**
	 * Processes feed action.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array  $feed  The Feed Object currently being processed.
	 * @param array  $entry The Entry Object currently being processed.
	 * @param array  $form  The Form Object currently being processed.
	 *
	 * @return array|null Returns a modified entry object or null.
	 */
	public function process_feed( $feed, $entry, $form ) {

		if ( empty( $this->pods_gf[ $feed['id'] ] ) ) {
			return null;
		}

		$form = $this->_gf_pre_render( $form, false, $entry );

		/** @var Pods_GF $pods_gf */
		$pods_gf = $this->pods_gf[ $feed['id'] ];

		try {
			$pods_gf->options['entry'] = $entry;

			$id = $pods_gf->_gf_to_pods_handler( $form, $entry );

			// Set post_id if we have it.
			if ( 'post_type' === $pods_gf->pod->pod_data['type'] ) {
				$entry['post_id'] = $id;

				return $entry;
			}
		}
		catch ( Exception $e ) {
			// @todo Log something to the form entry
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::warning( 'Feed processing error: ' . $e->getMessage() );
			}
		}

		return null;

	}

	/**
	 * Action handler for Gravity Forms: gform_action_pre_payment_callback.
	 *
	 * @param array $action Action data being saved.
	 * @param array $entry  GF Entry array.
	 */
	public function _gf_action_pre_payment_callback( $action, $entry ) {

		$form = GFAPI::get_form( $entry['form_id'] );

		$this->_gf_pre_process( $form );

	}

	/**
	 * Action handler for Gravity Forms: gform_post_update_entry.
	 *
	 * @param array $entry          GF Entry array
	 * @param array $original_entry Original GF Entry array
	 */
	public function _gf_post_update_entry( $entry, $original_entry ) {

		$form = GFAPI::get_form( $entry['form_id'] );

		$this->_gf_pre_process( $form );

	}

	/**
	 * Action handler for Gravity Forms: gform_after_update_entry.
	 *
	 * @param array $form           GF Form array
	 * @param array $entry          GF Entry array
	 * @param array $original_entry Original GF Entry array
	 */
	public function _gf_after_update_entry( $form, $entry, $original_entry ) {

		$this->_gf_pre_process( $form );

	}

	/**
	 * Hook into action to setup Pods GF add-on hooks before form entry edit form is shown.
	 *
	 * @param array|object $form  GF form data.
	 * @param array|object $entry GF entry data.
	 */
	public function _gf_pre_entry_detail( $form, $entry ) {

		// Remove other hooks for workarounds we don't need if this hook now exists. Not in GF when this was written.
		remove_action( 'check_admin_referer', array( $this, '_check_admin_referer' ) );
		remove_action( 'gform_entry_detail_content_before', array( $this, '_gf_entry_detail_content_before' ) );

		$this->_gf_pre_render( $form, false, $entry, true );

	}

	/**
	 * Hook into check_admin_referer to setup Pods GF add-on hooks before updating form entry.
	 *
	 * @param string    $action The nonce action.
	 * @param false|int $result False if the nonce is invalid, 1 if the nonce is valid and generated between
	 *                          0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
	 */
	public function _check_admin_referer( $action, $result ) {

		// So hacky, we need GF to add a better hook than this.
		if ( $result && 'gforms_save_entry' === $action && class_exists( 'GFEntryDetail' ) ) {
			$form = GFEntryDetail::get_current_form();

			if ( $form ) {
				$this->_gf_pre_process( $form );
			}
		}

	}

	/**
	 * Hook into action to setup Pods GF add-on hooks before form entry edit form is shown.
	 *
	 * @param array|object $form  GF form data.
	 * @param array|object $entry GF entry data.
	 */
	public function _gf_entry_detail_content_before( $form, $entry ) {

		$this->_gf_pre_render( $form, false, $entry, true );

	}

	/**
	 * @param $form
	 *
	 * @return mixed
	 */
	public function _gf_pre_render( $form, $ajax = false, $entry = null, $admin_edit = false ) {
		// Bad form / form ID.
		if ( empty( $form ) ) {
			return $form;
		}

		static $setup = array();

		if ( ! empty( $setup[ $form['id'] ] ) ) {
			return $setup[ $form['id'] ];
		}

		$feeds = $this->get_feeds( $form['id'] );

		if ( empty( $feeds ) ) {
			$setup[ $form['id'] ] = $form;

			return $setup[ $form['id'] ];
		}

		$pod_fields = array();
		$pod_name   = '';
		$feed       = null;

		if ( empty( $entry ) ) {
			$entry = GFFormsModel::get_current_lead();
		}

		foreach ( $feeds as $feed ) {
			if ( 1 !== (int) $feed['is_active'] ) {
				continue;
			}

			if ( $admin_edit && empty( $feed['meta']['update_pod_item'] ) ) {
				continue;
			}

			$pod_fields    = self::get_field_map_fields_with_custom_values( $feed, 'pod_fields' );
			$object_fields = self::get_field_map_fields_with_custom_values( $feed, 'wp_object_fields' );

			$pod_fields = array_merge( $pod_fields, $object_fields );

			$pod_name = $feed['meta']['pod'];

			break;
		}

		if ( $pod_fields && $pod_name ) {
			$pod_obj = pods( $pod_name, null, false );

			if ( empty( $pod_obj ) || ! $pod_obj->valid() ) {
				$setup[ $form['id'] ] = $form;

				return $setup[ $form['id'] ];
			}

			$dynamic_selects = array();

			/**
			 * @var GF_Field $gf_field
			 */
			foreach ( $form['fields'] as $gf_field ) {
				if ( empty( $gf_field->pods_populate_related_items ) ) {
					//continue;
				}

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

				if ( empty( $pod_field_options ) ) {
					continue;
				}

				// Override limit for autocomplete
				$object_params = array(
					'limit' => -1,
				);

				$data = PodsForm::field_method( $pod_field_options['type'], 'get_field_data', $pod_field_options, array(), $object_params );

				if ( empty( $data ) ) {
					continue;
				}

				if ( isset( $data[''] ) ) {
					unset( $data[''] );
				}

				$select_text = pods_v( $pod_field_options['type'] . '_select_text', $pod_field_options['options'], __( '-- Select One --', 'pods' ), true );

				$options = array(
					'options' => $data,
				);

				if ( $select_text ) {
					$options['select_text'] = $select_text;
				}

				$dynamic_selects[ $gf_field->id ] = $options;
			}

			if ( ! empty( $dynamic_selects ) ) {
				$form = Pods_GF::gf_dynamic_select( $form, false, $dynamic_selects );
			}
		}

		$form = $this->_gf_pre_process( $form );

		$setup[ $form['id'] ] = $form;

		return $setup[ $form['id'] ];

	}

	/**
	 * @param $form
	 *
	 * @return mixed
	 */
	public function _gf_pre_process( $form ) {

		static $setup = array();

		if ( ! empty( $setup[ $form['id'] ] ) ) {
			return $setup[ $form['id'] ];
		}

		$feeds = $this->get_feeds( $form['id'] );

		$entry = GFFormsModel::get_current_lead();

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					continue;
				}

				$this->setup_pods_gf( $form, $feed );
			}
		}

		$setup[ $form['id'] ] = $form;

		return $setup[ $form['id'] ];

	}

	/**
	 * @param array $form Form object.
	 * @param array $feed Feed object.
	 *
	 * @return boolean
	 */
	public function setup_pods_gf( $form, $feed ) {

		// Block new post being created in GF
		add_filter( 'gform_disable_post_creation_' . $form['id'], '__return_true' );

		$pod_fields    = self::get_field_map_fields_with_custom_values( $feed, 'pod_fields' );
		$object_fields = self::get_field_map_fields_with_custom_values( $feed, 'wp_object_fields' );
		$custom_fields = self::get_field_map_custom_fields_with_custom_values( $feed );

		$fields = array_merge( $pod_fields, $object_fields, $custom_fields );

		$options = array(
			// array ( 'gf_field_id' => 'pod_field_name' )
			'fields'              => $fields,
			'update_pod_item'     => (int) pods_v( 'update_pod_item', $feed['meta'], 0 ),
			'markdown'            => (int) pods_v( 'enable_markdown', $feed['meta'], 0 ),
			'auto_delete'         => (int) pods_v( 'delete_entry', $feed['meta'], 0 ),
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

		$this->pods_gf[ $feed['id'] ] = pods_gf( $pod, $form['id'], $options );

		$setup[ $form['id'] ] = true;

		return true;

	}

	/**
	 * Add custom merge tags for Pods GF.
	 *
	 * @param array $merge_tags Merge tags.
	 * @param int   $form_id    Form ID.
	 *
	 * @return array Merge tags,
	 */
	public function _gf_custom_merge_tags( $merge_tags, $form_id ) {

		$merge_tags[] = array(
			'tag'   => '{pods:id}',
			'label' => esc_html__( 'Pods GF Item ID', 'pods-gravity-forms' ),
		);

		$merge_tags[] = array(
			'tag'   => '{pods:permalink}',
			'label' => esc_html__( 'Pods GF Item Permalink', 'pods-gravity-forms' ),
		);

		return $merge_tags;

	}

	/**
	 * Add custom merge tags for Pods GF.
	 *
	 * @param array       $data Merge tag data.
	 * @param string      $text Content to replace custom merge tags in.
	 * @param false|array $form GF form object.
	 *
	 * @return array Merge tag data.
	 */
	public function _gf_add_merge_tags( $data, $text, $form ) {

		if ( empty( $form ) || empty( $form['id'] ) ) {
			return $data;
		}

		$form_id = $form['id'];

		$id        = 0;
		$permalink = '';

		if ( ! empty( Pods_GF::$gf_to_pods_id[ $form_id ] ) ) {
			$id = Pods_GF::$gf_to_pods_id[ $form_id ];
		}

		if ( ! empty( Pods_GF::$gf_to_pods_id[ $form_id . '_permalink' ] ) ) {
			$permalink = Pods_GF::$gf_to_pods_id[ $form_id . '_permalink' ];
		}

		$data['pods'] = array(
			'id'        => $id,
			'permalink' => $permalink,
		);

		return $data;

	}

	/**
	 * Replace custom merge tags in content for Pods GF.
	 *
	 * @param string      $content Content to replace custom merge tags in.
	 * @param false|array $form    GF form object.
	 *
	 * @return string Content with custom merge tags replaced.
	 */
	public function _gf_replace_merge_tags( $content, $form ) {

		if ( empty( $form ) || empty( $form['id'] ) ) {
			return $content;
		}

		$form_id = $form['id'];

		$id        = 0;
		$permalink = '';

		if ( ! empty( Pods_GF::$gf_to_pods_id[ $form_id ] ) ) {
			$id = Pods_GF::$gf_to_pods_id[ $form_id ];
		}

		if ( ! empty( Pods_GF::$gf_to_pods_id[ $form_id . '_permalink' ] ) ) {
			$permalink = Pods_GF::$gf_to_pods_id[ $form_id . '_permalink' ];
		}

		// For backcompat purposes.
		$id_merge_tags = array(
			'{pods:id}',
			'{gf_to_pods_id}',
			'{@gf_to_pods_id}',
		);

		// For backcompat purposes.
		$permalink_merge_tags = array(
			'{pods:permalink}',
			'{gf_to_pods_permalink}',
			'{@gf_to_pods_permalink}',
		);

		$content = str_replace( $id_merge_tags, $id, $content );
		$content = str_replace( $permalink_merge_tags, $permalink, $content );

		return $content;

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

		$prefix = $field_name . '_';

		$old_custom_prefix = $prefix . 'override_custom_';

		$fields = array();

		$skip = array();

		foreach ( $feed['meta'] as $config_field_name => $config ) {
			$config_field_name = (string) $config_field_name;
			$gf_field_custom   = sprintf( '%s_custom', $config_field_name );

			if ( in_array( $config_field_name, $skip, true ) ) {
				continue;
			}

			if ( 0 === strpos( $config_field_name, $old_custom_prefix ) ) {
				// Skip override values (old way)
				continue;
			}

			if ( 0 !== strpos( $config_field_name, $prefix ) && $field_name !== $config_field_name ) {
				continue;
			}

			// Get field name
			$field_name = substr( $config_field_name, strlen( $prefix ) );

			// Mapping value is the GF field ID
			$gf_field = trim( $config );

			// Mapping value
			$mapping_value = array(
				'gf_field' => $gf_field,
				'field'    => $field_name,
			);

			if ( 'gf_custom' === $gf_field ) {
				// Support override value settings (new way)
				$gf_field = sprintf( '_pods_gf_custom_%s', $field_name );

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

				if ( ! empty( $feed['meta'][ $old_custom_prefix ] ) ) {
					$value = trim( $feed['meta'][ $old_custom_prefix ] );

					if ( ! empty( $value ) ) {
						$mapping_value['value'] = $value;

						$mapping_value['gf_merge_tags'] = true;
					}
				}
			}

			$skip[] = $gf_field_custom;

			if ( ! empty( $gf_field ) ) {
				$fields[] = $mapping_value;
			}
		}

		return $fields;

	}

	/**
	 * Get field map field values with support for custom override values.
	 *
	 * This differs from get_field_map_fields() in that it returns an array
	 * that is already formatted for Pods GF field mapping usage.
	 *
	 * @param array $feed
	 *
	 * @return array
	 */
	public static function get_field_map_custom_fields_with_custom_values( $feed ) {

		if ( empty( $feed['meta']['custom_fields'] ) ) {
			return array();
		}

		$configs = $feed['meta']['custom_fields'];

		$fields = array();

		foreach ( $configs as $config ) {
			$config = array_map( 'trim', $config );

			$gf_field   = $config['value'];
			$field_name = $config['key'];

			if ( in_array( $field_name, array( 'gf_custom', '' ), true ) ) {
				$field_name = $config['custom_key'];
			}

			// Mapping value
			$mapping_value = array(
				'gf_field' => $gf_field,
				'field'    => $field_name,
			);

			if ( in_array( $gf_field, array( 'gf_custom', '' ), true ) && ! empty( $config['custom_value'] ) ) {
				$mapping_value['gf_field']      = sprintf( '_pods_gf_custom_%s', $field_name );
				$mapping_value['value']         = $config['custom_value'];
				$mapping_value['gf_merge_tags'] = true;
			}

			if ( '' === $mapping_value['gf_field'] || '' === $mapping_value['field'] ) {
				continue;
			}

			$fields[] = $mapping_value;
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
			$entry_meta['_pods_item_id'] = array(
				'label'                      => 'Pod Item ID',
				'is_numeric'                 => true,
				'is_default_column'          => true,
				'update_entry_meta_callback' => array( $this, 'update_entry_meta_pod_id' ),
				'filter'                     => array(
					'operators' => array(
						'is',
						'isnot',
						'>',
						'<',
					),
				),
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

	/**
	 * Registers hooks which need to be included before the init hook is triggered.
	 *
	 * @since  1.4.2
	 * @access public
	 */
	public function pre_init() {

		add_filter( 'gform_export_form', array( $this, '_gf_export_form' ) );
		add_action( 'gform_forms_post_import', array( $this, '_gf_forms_post_import' ) );

		parent::pre_init();

	}

	/**
	 * Adds form feeds to form object during export.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array $form The form to be exported.
	 *
	 * @uses   GFFeedAddOn::get_feeds()
	 *
	 * @return array
	 */
	public function _gf_export_form( $form ) {

		// Get feeds for form.
		$feeds = $this->get_feeds( $form['id'] );

		// If feeds array does not exist for form, create it.
		if ( ! isset( $form['feeds'] ) ) {
			$form['feeds'] = array();
		}

		// Add feeds to form.
		$form['feeds'][ $this->_slug ] = $feeds;

		return $form;

	}

	/**
	 * Imports the feeds for the newly imported forms.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array $forms The imported forms.
	 *
	 * @uses   GFAPI::add_feed()
	 * @uses   GFAPI::get_form()
	 * @uses   GFAPI::update_form()
	 * @uses   GFFeedAddOn::update_feed_active()
	 */
	public function _gf_forms_post_import( $forms ) {

		// Loop through imported forms.
		foreach ( $forms as $import_form ) {

			// Get latest version of form object.
			$form = GFAPI::get_form( $import_form['id'] );

			// If no feeds are found for form, skip.
			if ( ! rgars( $form, 'feeds/' . $this->_slug ) ) {
				continue;
			}

			// Import feeds.
			foreach ( $form['feeds'][ $this->_slug ] as $feed ) {

				// Add feed.
				$new_feed_id = GFAPI::add_feed( $form['id'], $feed['meta'], $this->_slug );

				// Set active status.
				if ( ! $feed['is_active'] ) {
					$this->update_feed_active( $new_feed_id, false );
				}

			}

			// Remove Pods feeds from form object.
			unset( $form['feeds'][ $this->_slug ] );

			// If no other feeds are found, remove feeds array.
			if ( empty( $form['feeds'] ) ) {
				unset( $form['feeds'] );
			}

			// Save form.
			GFAPI::update_form( $form );

		}

	}

}

new Pods_GF_Addon();
