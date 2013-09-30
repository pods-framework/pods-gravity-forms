<?php
/**
 * Class Pods_GF
 */
class Pods_GF {

	/**
	 * GF Actions / Filters that have already run
	 *
	 * @var array
	 */
	public static $actioned = array();

	/**
	 * Pods object
	 *
	 * @var Pods
	 */
	public $pod;

	/**
	 * Item ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * GF Form ID
	 *
	 * @var int
	 */
	public $form_id = 0;

	/**
	 * Form config
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Gravity Forms validation message override
	 *
	 * @var string|null
	 */
	public $gf_validation_message;

	/**
	 * To keep or delete files when deleting GF entries
	 *
	 * @var bool
	 */
	public static $keep_files = false;

    /**
     * Array of options for Dynamic Select
     *
     * @var array
     */
    public static $dynamic_selects = array();

    /**
     * Array of options for Prepopulating forms
     *
     * @var array
     */
    public static $prepopulate = array();

    /**
     * Array of options for Save For Later
     *
     * @var array
     */
	public static $save_for_later = array();

    /**
     * Array of options for Remember for next time
     *
     * @var array
     */
	public static $remember = array();

	/**
	 * Add Pods GF integration for a specific form
	 *
	 * @param string|Pods Pod name (or Pods object)
	 * @param int $form_id GF Form ID
	 * @param array $options Form options for integration
	 */
	public function __construct( $pod, $form_id, $options = array() ) {

		// Pod object
		if ( is_object( $pod ) ) {
			$this->pod =& $pod;
			$this->id =& $this->pod->id;
		}
		// Pod name
		elseif ( !is_array( $pod ) ) {
			$this->pod = pods( $pod );
			$this->id =& $this->pod->id;
		}
		// GF entry
		elseif ( isset( $pod[ 'id' ] ) ) {
			$this->pod = $pod;
			$this->id = $pod[ 'id' ];
		}

		$this->form_id = $form_id;
		$this->options = $options;

		if ( !wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

		if ( isset( $this->options[ 'save_for_later' ] ) && !empty( $this->options[ 'save_for_later' ] ) ) {
			self::save_for_later( $form_id, $this->options[ 'save_for_later' ] );
		}

		if ( !pods_var( 'admin', $this->options, 0 ) && ( is_admin() && RGForms::is_gravity_page() ) ) {
			return;
		}

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ), 10, 2 );
			add_filter( 'gform_get_form_filter_' . $form_id, array( $this, '_gf_get_form_filter' ), 10, 2 );

			add_filter( 'gform_validation_' . $form_id, array( $this, '_gf_validation' ), 11, 1 );
			add_filter( 'gform_validation_message_' . $form_id, array( $this, '_gf_validation_message' ), 11, 2 );

			add_action( 'gform_after_submission_' . $form_id, array( $this, '_gf_after_submission' ), 10, 2 );
		}

		$form = RGFormsModel::get_form_meta( $form_id );

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		$read_only = pods_var_raw( 'read_only', $this->options, false );

		if ( false !== $read_only ) {
			if ( !has_filter( 'gform_field_input', array( $this, '_gf_field_input_read_only' ) ) ) {
				add_filter( 'gform_field_input', array( $this, '_gf_field_input_read_only' ), 20, 5 );
			}
		}

		if ( isset( $options[ 'fields' ] ) && !empty( $options[ 'fields' ] ) ) {
			foreach ( $options[ 'fields' ] as $field => $field_options ) {
				if ( is_array( $field_options ) && isset( $field_options[ 'gf_field' ] ) ) {
					$field = $field_options[ 'gf_field' ];
				}

				if ( !has_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ) ) ) {
					add_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ), 11, 4 );
				}

				if ( true === $read_only || ( is_array( $read_only ) && in_array( $field, $read_only ) ) ) {
					$gf_field = $form[ 'fields' ][ $field_keys[ $field ] ];

					if ( 'list' == RGFormsModel::get_input_type( $gf_field ) ) {
						$columns = ( is_array( $gf_field[ 'choices' ] ) ? $gf_field[ 'choices' ] : array( array() ) );

						$col_number = 1;

						foreach ( $columns as $column ) {
							if ( !has_filter( 'gform_column_input_content_' . $form_id . '_' . $field . '_' . $col_number, array( $this, '_gf_field_column_read_only' ) ) ) {
								add_filter( 'gform_column_input_content_' . $form_id . '_' . $field . '_' . $col_number, array( $this, '_gf_field_column_read_only' ), 20, 6 );
							}

							$col_number++;
						}
					}
				}
			}
		}
		elseif ( true === $read_only ) {
			foreach ( $form[ 'fields' ] as $k => $field ) {
				if ( 'list' == RGFormsModel::get_input_type( $field ) && ( true === $read_only || ( is_array( $read_only ) && in_array( $field, $read_only ) ) ) ) {
					$columns = ( is_array( $field[ 'choices' ] ) ? $field[ 'choices' ] : array( array() ) );

					$col_number = 1;

					foreach ( $columns as $column ) {
						if ( !has_filter( 'gform_column_input_content_' . $form_id . '_' . $field[ 'id' ] . '_' . $col_number, array( $this, '_gf_field_column_read_only' ) ) ) {
							add_filter( 'gform_column_input_content_' . $form_id . '_' . $field[ 'id' ] . '_' . $col_number, array( $this, '_gf_field_column_read_only' ), 20, 6 );
						}

						$col_number++;
					}
				}
			}
		}

	}

	/**
	 * Setup GF to auto-delete the entry it's about to create
	 *
	 * @static
	 *
	 * @param int $form_id GF Form ID
	 * @param bool $keep_files To keep or delete files when deleting GF entries
	 */
	public static function auto_delete( $form_id = null, $keep_files = null ) {

		if ( null !== $keep_files ) {
			self::$keep_files = (boolean) $keep_files;
		}

		$form = ( !empty( $form_id ) ? '_' . (int) $form_id : '' );

		add_action( 'gform_post_submission' . $form, array( get_class(), 'delete_entry' ), 20, 1 );

	}

    /**
     * Set a field's values to be dynamically pulled from a Pod
     *
     * @static
     *
     * @param int $form_id GF Form ID
     * @param int $field_id GF Field ID
     * @param array $options Dynamic select options
     */
    public static function dynamic_select( $form_id, $field_id, $options ) {
        self::$dynamic_selects[] = array_merge(
			array(
				'form' => $form_id,

				'gf_field' => $field_id, // override $field
				'default' => null, // override default selected value

				'options' => null, // set to an array for a basic custom options list

				'pod' => null, // set to a pod to use
				'field_text' => null, // set to the field to show for text (option label)
				'field_value' => null, // set to field to use for value (option value)
				'params' => null, // set to a $params array to override the default find()
			),
			$options
        );

		$class = get_class();

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_dynamic_select' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_dynamic_select' ), 10, 2 );
		}
    }

	/**
	 * Build the GF Choices array for use in field option overrides
	 *
	 * @param array $values Value array (value=>label)
	 * @param string $current_value Current value
	 * @param string $default Default value
	 *
	 * @return array Choices array
	 */
	public static function build_choices( $values, $current_value = '', $default = '' ) {

		$choices = array();

		if ( null === $current_value || '' === $current_value ) {
			$current_value = '';

			if ( null !== $default ) {
				$current_value = $default;
			}
		}

		foreach ( $values as $value => $label ) {
			$choices[] = array(
				'text' => $label,
				'value' => $value,
				'isSelected' => ( (string) $value === (string) $current_value )
			);
		}

		return $choices;

	}

	/**
	 * Get the currently selected choice value/text from the GF Choices array
	 *
	 * @param array $choices GF Choices array
	 * @param string $current_value Current value
	 * @param string $default Default value
	 *
	 * @return array Selected choice array
	 */
	public static function get_selected_choice( $choices, $current_value = '', $default = '' ) {

		if ( null === $current_value || '' === $current_value ) {
			$current_value = '';

			if ( null !== $default ) {
				$current_value = $default;
			}
		}

		$selected = array();

		foreach ( $choices as $value => $choice ) {
			if ( !is_array( $choice ) ) {
				$choice = array(
					'text' => $choice,
					'value' => $value
				);
			}

			if ( 1 == pods_var( 'isSelected', $choice ) || '' === $current_value || ( !isset( $choice[ 'isSelected' ] ) && (string) $choice[ 'value' ] === (string) $current_value ) ) {
				$selected = $choice;
				$selected[ 'isSelected' ] = true;

				break;
			}
		}

		return $selected;

	}

    /**
     * Prepopulate a form with values from a Pod item
     *
     * @static
     *
     * @param int $form_id GF Form ID
     * @param string|Pods $pod Pod name (or Pods object)
     * @param int $id Pod item ID
	 * @param array $fields Field mapping to prepopulate from
     */
    public static function prepopulate( $form_id, $pod, $id, $fields ) {
        self::$prepopulate = array(
			'form' => $form_id,

			'pod' => $pod,
			'id' => $id,
			'fields' => $fields
		);

		$class = get_class();

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_prepopulate' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_prepopulate' ), 10, 2 );
		}
    }

	/**
	 * Override a field's value with the prepopulated value, hooks into the pods_gf_field_value filters
	 *
	 * @param int|array $form_id
	 * @param int|array $field_id
	 */
	public static function prepopulate_override_value( $form_id, $field_id ) {

		if ( is_array( $form_id ) ) {
			$form_id = $form_id[ 'id' ];
		}

		if ( is_array( $field_id ) ) {
			$field_id = $field_id[ 'id' ];
		}

		$class = get_class();

		if ( !has_filter( 'pods_gf_field_value_' . $form_id . '_' . $field_id, array( $class, 'gf_prepopulate_value' ) ) ) {
			add_filter( 'pods_gf_field_value_' . $form_id . '_' . $field_id, array( $class, 'gf_prepopulate_value' ), 10, 2 );
		}

	}

	/**
	 * Override a field's value with the prepopulated value (if set), for use with pods_gf_field_value filters
	 *
	 * @param mixed $post_value_override
	 * @param mixed $value_override
	 *
	 * @return mixed
	 */
	public static function gf_prepopulate_value( $post_value_override, $value_override ) {

		if ( null === $post_value_override ) {
			$post_value_override = $value_override;
		}

		return $post_value_override;

	}

	/**
	 * Setup Save for Later for a form
	 *
	 * @param int $form_id GF Form ID
	 * @param array $options Save for Later options
	 */
	public static function save_for_later( $form_id, $options = array() ) {

		self::$save_for_later[ $form_id ] = array(
			'redirect' => null,
			'exclude_pages' => array()
		);

		if ( is_array( $options ) ) {
			self::$save_for_later[ $form_id ] = array_merge( self::$save_for_later[ $form_id ], $options );
		}

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_load' ), 9, 2 ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_load' ), 9, 2 );
			add_filter( 'gform_submit_button_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_button' ), 10, 2 );
			add_action( 'gform_after_submission_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_clear' ), 10, 2 );
		}

		if ( !wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

	}

	/**
	 * Save for Later handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_save_for_later_load( $form, $ajax ) {

		$save_for_later = pods_var_raw( $form[ 'id' ], self::$save_for_later, array(), null, true );

		if ( !empty( $save_for_later ) && empty( $_POST ) ) {
			$save_for_later_data = self::gf_save_for_later_data( $form[ 'id' ] );

			if ( !empty( $save_for_later_data ) ) {
				$_POST = $save_for_later_data;
				$_POST[ 'pods_gf_save_for_later_loaded' ] = 1;
			}
		}

		return $form;

	}

	/**
	 * Get Save for Later data
	 *
	 * @param int $form_id GF Form ID
	 *
	 * @return array|bool
	 */
	public static function gf_save_for_later_data( $form_id ) {

		global $user_ID;

		$postdata = array();

		if ( is_user_logged_in() ) {
			$postdata = get_user_meta( $user_ID, '_pods_gf_saved_form_' . $form_id, true );
		}

		if ( empty( $postdata ) ) {
			$postdata = pods_var_raw( '_pods_gf_saved_form_' . $form_id, 'cookie' );
		}

		if ( !empty( $postdata ) ) {
			$postdata = @json_decode( $postdata, true );

			if ( !empty( $postdata ) ) {
				return $postdata;
			}
		}

		return false;

	}

	/**
	 * Add Save for Later buttons
	 *
	 * @param string $button_input Button HTML
	 * @param array $form GF Form array
	 *
	 * @return string Button HTML
	 */
	public static function gf_save_for_later_button( $button_input, $form ) {

		$save_for_later = pods_var_raw( $form[ 'id' ], self::$save_for_later, array(), null, true );

		if ( !empty( $save_for_later ) ) {
			if ( !empty( $save_for_later[ 'exclude_pages' ] ) && in_array( GFFormDisplay::get_current_page( $form[ 'id' ] ), $save_for_later[ 'exclude_pages' ] ) ) {
				return $button_input;
			}

			wp_enqueue_script( 'pods-gf' );

			$button_input .= ' <input type="button" class="button gform_button pods-gf-save-for-later" value="' . esc_attr__( 'Save for Later', 'pods-gf-ui' ) . '" />';

			$save_for_later_data = self::gf_save_for_later_data( $form[ 'id' ] );

			if ( !empty( $save_for_later_data ) ) {
				$button_input .= ' <input type="button" class="button gform_button pods-gf-save-for-later-reset" value="' . esc_attr__( 'Reset Form', 'pods-gf-ui' ) . '" />';
			}

			if ( !empty( $save_for_later[ 'redirect' ] ) ) {
				$button_input .= '<input type="hidden" name="pods_gf_save_for_later_redirect" value="' . esc_attr( $save_for_later[ 'redirect' ] ) . '" />';
			}

			$button_input .= '<script type="text/javascript">if ( \'undefined\' == typeof ajaxurl ) { var ajaxurl = \'' . get_admin_url( null, 'admin-ajax.php' ) . '\'; }</script>';
		}

		return $button_input;

	}

	/**
	 * AJAX Handler for Save for Later
	 */
	public static function gf_save_for_later_ajax() {

		global $user_ID;

		// Clear saved form
		$form_id = str_replace( 'gform_', '', pods_var( 'form_id', 'request' ) );

		if ( 0 < $form_id ) {
			$redirect = pods_var_raw( 'pods_gf_save_for_later_redirect', 'post', '/?pods_gf_form_saved=' . $form_id, null, true );
			$redirect = pods_var_raw( 'pods_gf_save_for_later_redirect', 'get', $redirect, null, true );

			if ( isset( $_POST[ 'pods_gf_save_for_later_redirect' ] ) ) {
				unset( $_POST[ 'pods_gf_save_for_later_redirect' ] );
			}

			// Clear saved form
			if ( 1 == pods_var( 'pods_gf_clear_saved_form' ) ) {
				self::gf_save_for_later_clear( array(), array( 'id' => $form_id ), true );
			}
			// Save $_POST for later
			else {
				// JSON encode to avoid serialization issues
				$postdata = json_encode( $_POST );

				if ( is_user_logged_in() ) {
					update_user_meta( $user_ID, '_pods_gf_saved_form_' . $form_id, $postdata );
				}

				pods_var_set( $postdata, '_pods_gf_saved_form_' . $form_id, 'cookie' );
			}

			pods_redirect( $redirect );
		}
		else {
			wp_die( 'Invalid form submission' );
		}

		die();

	}


	/**
	 * Save for Later handler for Gravity Forms: gform_after_submission_{$form_id}
	 *
	 * @param array $entry GF Entry array
	 * @param array $form GF Form array
	 */
	public static function gf_save_for_later_clear( $entry, $form, $force = false ) {

		global $user_ID;

		$save_for_later = pods_var_raw( $form[ 'id' ], self::$save_for_later, array(), null, true );

		if ( !empty( $save_for_later ) || $force ) {
			if ( is_user_logged_in() ) {
				delete_user_meta( $user_ID, '_pods_gf_saved_form_' . $form[ 'id' ] );
			}

			pods_var_set( '', '_pods_gf_saved_form_' . $form[ 'id' ], 'cookie' );
		}

	}

	/**
	 * Setup Remember for next time for a form
	 *
	 * @param int $form_id GF Form ID
	 * @param array $options Save for Later options
	 */
	public static function remember( $form_id, $options = array() ) {

		self::$remember[ $form_id ] = array(
			'fields' => null
		);

		if ( is_array( $options ) ) {
			self::$remember[ $form_id ] = array_merge( self::$remember[ $form_id ], $options );
		}

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_remember_load' ), 9, 2 ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_remember_load' ), 9, 2 );
			add_action( 'gform_after_submission_' . $form_id, array( 'Pods_GF', 'gf_remember_save' ), 10, 2 );
		}

		if ( !wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

	}

	/**
	 * Remember for next time handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_remember_load( $form, $ajax ) {

		global $user_ID;

		$remember = pods_var_raw( $form[ 'id' ], self::$remember, array(), null, true );

		if ( !empty( $remember ) && empty( $_POST ) ) {
			$postdata = array();

			if ( is_user_logged_in() ) {
				$postdata = get_user_meta( $user_ID, '_pods_gf_remember_' . $form[ 'id' ], true );
			}

			if ( empty( $postdata ) ) {
				$postdata = pods_var_raw( '_pods_gf_remember_' . $form[ 'id' ], 'cookie' );
			}

			if ( !empty( $postdata ) ) {
				$postdata = @json_decode( $postdata, true );

				if ( !empty( $postdata ) ) {
					$fields = pods_var_raw( 'fields', $remember );

					if ( !empty( $fields ) ) {
						foreach ( $fields as $field ) {
							if ( !isset( $_POST[ 'input_' . $field ] ) && isset( $postdata[ 'input_' . $field ] ) ) {
								$_POST[ 'input_' . $field ] = $postdata[ 'input_' . $field ];
							}
						}
					}
					else {
						$_POST = array_merge( $postdata, $_POST );
					}

					$_POST[ 'pods_gf_remember_loaded' ] = 1;
				}
			}
		}

		return $form;

	}


	/**
	 * Save for Later handler for Gravity Forms: gform_after_submission_{$form_id}
	 *
	 * @param array $entry GF Entry array
	 * @param array $form GF Form array
	 */
	public static function gf_remember_save( $entry, $form ) {

		global $user_ID;

		$remember = pods_var_raw( $form[ 'id' ], self::$remember, array(), null, true );

		if ( !empty( $remember ) ) {
			$fields = pods_var_raw( 'fields', $remember );

			$postdata = array();

			if ( !empty( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( isset( $_POST[ 'input_' . $field ] ) ) {
						$postdata[ 'input_' . $field ] = $_POST[ 'input_' . $field ];
					}
				}
			}
			else {
				$postdata = $_POST;

				foreach ( $postdata as $k => $v ) {
					if ( 0 !== strpos( $k, 'input_' ) ) {
						unset( $postdata[ $k ] );
					}
				}
			}

			if ( !empty( $postdata ) ) {
				// JSON encode to avoid serialization issues
				$postdata = json_encode( $postdata );

				if ( is_user_logged_in() ) {
					update_user_meta( $user_ID, '_pods_gf_remember_' . $form[ 'id' ], $postdata );
				}

				pods_var_set( $postdata, '_pods_gf_remember_' . $form[ 'id' ], 'cookie' );
			}
		}

	}

	/**
	 * Map GF form fields to Pods fields
	 *
	 * @param array $form GF Form array
	 * @param array $options Form config
	 *
	 * @return array Data array for saving
	 */
	public static function gf_to_pods( $form, $options ) {

		$data = array();

		if ( !isset( $options[ 'fields' ] ) || empty( $options[ 'fields' ] ) ) {
			return $data;
		}

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		foreach ( $options[ 'fields' ] as $field => $field_options ) {
			$field = (string) $field;

			$field_options = array_merge(
				array(
					'field' => $field_options,
					'value' => null
				),
				( is_array( $field_options ) ? $field_options : array() )
			);

			// No field set
			if ( empty( $field_options[ 'field' ] ) || is_array( $field_options[ 'field' ] ) ) {
				continue;
			}

			// GF input field
			$value = pods_var_raw( $field, 'post' );
			$value = pods_var_raw( 'input_' . str_replace( '.', '_', $field ), 'post', $value );
			$value = pods_var_raw( 'input_' . $field, 'post', $value );

			// Manual value override
			if ( null !== $field_options[ 'value' ] ) {
				$value = $field_options[ 'value' ];
			}

			// Filters
			$field_data = array();

			if ( isset( $field_keys[ $field ] ) ) {
				$field_data = $form[ 'fields' ][ $field_keys[ $field ] ];
			}

			$value = apply_filters( 'pods_gf_to_pods_value_' . $form[ 'id' ] . '_' . $field, $value, $field, $field_options, $form, $field_data, $data, $options );
			$value = apply_filters( 'pods_gf_to_pods_value_' . $form[ 'id' ], $value, $field, $field_options, $form, $field_data, $data, $options );
			$value = apply_filters( 'pods_gf_to_pods_value', $value, $field, $field_options, $form, $field_data, $data, $options );

			// Set data
			if ( null !== $value ) {
				$data[ $field_options[ 'field' ] ] = $value;
			}
		}

		$data = apply_filters( 'pods_gf_to_pods_data_' . $form[ 'id' ], $data, $form, $options );
		$data = apply_filters( 'pods_gf_to_pods_data', $data, $form, $options );

		return $data;
	}

	/**
	 * Send notifications based on config
	 *
	 * @param array $entry GF Entry array
	 * @param array $form GF Form array
	 * @param array $options Form options
	 *
	 * @return bool Whether the notifications were sent
	 */
	public static function gf_notifications( $entry, $form, $options ) {

		if ( !isset( $options[ 'notifications' ] ) || empty( $options[ 'notifications' ] ) ) {
			return false;
		}

		foreach ( $options[ 'notifications' ] as $template => $template_options ) {
			$template_options = (array) $template_options;

			if ( isset( $template_options[ 'template' ] ) ) {
				$template = $template_options[ 'template' ];
			}

			$template_data = apply_filters( 'pods_gf_template_object', null, $template, $template_options, $entry, $form, $options );

			$template_obj = null;

			// @todo Hook into GF notifications and use those instead, stopping the e-mail from being sent to original e-mail

			if ( !is_array( $template_data ) ) {
				if ( post_type_exists( 'gravity_forms_template' ) ) {
					continue;
				}

				$template_obj = get_page_by_path( $template, OBJECT, 'gravity_forms_template' );

				if ( empty( $template ) ) {
					continue;
				}

				$template_data = array(
					'subject' => get_post_meta( $template_obj->ID, '_pods_gf_template_subject', true ),
					'content' => $template_obj->post_content,
					'cc' => get_post_meta( $template_obj->ID, '_pods_gf_template_cc', true ),
					'bcc' => get_post_meta( $template_obj->ID, '_pods_gf_template_bcc', true )
				);

				if ( empty( $template_data[ 'subject' ] ) ) {
					$template_data[ 'subject' ] = $template_obj->post_title;
				}
			}

			if ( !isset( $template_data[ 'subject' ] ) || empty( $template_data[ 'subject' ] ) || !isset( $template_data[ 'content' ] ) || empty( $template_data[ 'content' ] ) ) {
				continue;
			}

			$to = array();
			$emails = array();

			foreach ( $template_options as $k => $who ) {
				if ( !is_numeric( $k ) ) {
					continue;
				}
				elseif ( is_numeric( $who ) ) {
					$user = get_userdata( $who );

					if ( empty( $user ) ) {
						continue;
					}

					if ( in_array( $user->user_email, $to ) ) {
						continue;
					}

					$to[] = $user->user_email;

					$emails[] = array(
						'to' => $user->user_email,
						'user_id' => $user->ID
					);
				}
				elseif ( false !== strpos( $who, '@' ) ) {
					if ( in_array( $who, $to ) ) {
						continue;
					}

					$to[] = $who;

					$user = get_user_by( 'email', $who );

					$emails[] = array(
						'to' => $who,
						'user_id' => ( !empty( $user ) ? $user->ID : 0 )
					);
				}
				else {
					$users = get_users( array( 'role' => $who, 'fields' => array( 'user_email' ) ) );

					foreach ( $users as $user ) {
						if ( in_array( $user->user_email, $to ) ) {
							continue;
						}

						$to[] = $user->user_email;

						$emails[] = array(
							'to' => $user->user_email,
							'user_id' => $user->ID
						);
					}
				}
			}

			foreach ( $emails as $email ) {
				$headers = array();

				if ( isset( $template_data[ 'cc' ] ) && !empty( $template_data[ 'cc' ] ) ) {
					$template_data[ 'cc' ] = (array) $template_data[ 'cc' ];

					foreach ( $template_data[ 'cc' ] as $cc ) {
						$headers[] = 'Cc: ' . $cc;
					}
				}

				if ( isset( $template_data[ 'bcc' ] ) && !empty( $template_data[ 'bcc' ] ) ) {
					$template_data[ 'bcc' ] = (array) $template_data[ 'bcc' ];

					foreach ( $template_data[ 'cc' ] as $cc ) {
						$headers[] = 'Bcc: ' . $cc;
					}
				}

				$email_template = array(
					'to' => $email[ 'to' ],
					'subject' => $template_data[ 'subject' ],
					'content' => $template_data[ 'content' ],
					'headers' => $headers,
					'attachments' => array(),
					'user_id' => $email[ 'user_id' ]
				);

				$email_template = apply_filters( 'pods_gf_template_email', $email_template, $entry, $form, $options );

				if ( empty( $email_template ) ) {
					continue;
				}

				wp_mail( $email_template[ 'to' ], $email_template[ 'subject' ], $email_template[ 'content' ], $email_template[ 'headers' ], $email_template[ 'attachments' ] );
			}
		}

		return true;
	}

	/**
	 * Delete a GF entry, because GF doesn't have an API to do this yet (the function itself is user-restricted)
	 *
	 * @param array $entry GF Entry array
	 * @param bool $keep_files Whether to keep the files from the entry
	 *
	 * @return bool If the entry was successfully deleted
	 */
	public static function gf_delete_entry( $entry, $keep_files = null ) {

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

		if ( null === $keep_files ) {
			$keep_files = self::$keep_files;
		}

		do_action( 'gform_delete_lead', $lead_id );

		$lead_table = RGFormsModel::get_lead_table_name();
		$lead_notes_table = RGFormsModel::get_lead_notes_table_name();
		$lead_detail_table = RGFormsModel::get_lead_details_table_name();
		$lead_detail_long_table = RGFormsModel::get_lead_details_long_table_name();

		//deleting uploaded files
		if ( !$keep_files ) {
			RGFormsModel::delete_files( $lead_id );
		}

		//Delete from detail long
		$sql = "
			DELETE FROM {$lead_detail_long_table}
			WHERE lead_detail_id IN(
				SELECT id FROM {$lead_detail_table} WHERE lead_id = %d
			)
		";

		$wpdb->query( $wpdb->prepare( $sql, $lead_id ) );

		//Delete from lead details
		$sql = "DELETE FROM {$lead_detail_table} WHERE lead_id = %d";
		$wpdb->query( $wpdb->prepare( $sql, $lead_id ) );

		//Delete from lead notes
		$sql = "DELETE FROM {$lead_notes_table} WHERE lead_id = %d";
		$wpdb->query( $wpdb->prepare( $sql, $lead_id ) );

		//Delete from lead meta
		gform_delete_meta( $lead_id );

		//Delete from lead
		$sql = "DELETE FROM {$lead_table} WHERE id = %d";
		$wpdb->query( $wpdb->prepare( $sql, $lead_id ) );

		return true;

	}

	/**
	 * Set dynamic option values for GF Form fields
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 * @param array|null $dynamic_selects The Dynamic select options to use
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_dynamic_select( $form, $ajax, $dynamic_selects = null ) {

		if ( null === $dynamic_selects ) {
			$dynamic_selects = self::$dynamic_selects;
		}

		if ( empty( $dynamic_selects ) ) {
			return $form;
		}

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
			return $form;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		// Dynamic Select handler
		foreach ( $dynamic_selects as $field => $dynamic_select ) {
			$field = (string) $field;

			$dynamic_select = array_merge(
				array(
					'form' => $form[ 'id' ],

					'gf_field' => $field, // override $field
					'default' => null, // override default selected value

					'options' => null, // set to an array for a basic custom options list

					'pod' => null, // set to a pod to use
					'field_text' => null, // set to the field to show for text (option label)
					'field_value' => null, // set to field to use for value (option value)
					'params' => null, // set to a $params array to override the default find()
				),
				( is_array( $dynamic_select ) ? $dynamic_selects : array() )
			);

			if ( !empty( $dynamic_select[ 'gf_field' ] ) ) {
				$field = (string) $dynamic_select[ 'gf_field' ];
			}

			if ( empty( $field ) || !isset( $field_keys[ $field ] ) || $dynamic_select[ 'form' ] != $form[ 'id' ] ) {
				continue;
			}

			$field_key = $field_keys[ $field ];

			$choices = false;

			if ( is_array( $dynamic_select[ 'options' ] ) && !empty( $dynamic_select[ 'options' ] ) ) {
				$choices = $dynamic_select[ 'options' ];
			}
			elseif ( !empty( $dynamic_select[ 'pod' ] ) ) {
				if ( !is_object( $dynamic_select[ 'pod' ] ) ) {
					$pod = pods( $dynamic_select[ 'pod' ], null, false );
				}
				else {
					$pod = $dynamic_select[ 'pod' ];
				}

				if ( empty( $pod ) ) {
					continue;
				}

				$params = array(
					'orderby' => 't.' . $pod->pod_data[ 'field_index' ],
					'limit' => -1,
					'search' => false,
					'pagination' => false
				);

				if ( !empty( $dynamic_select[ 'field_text' ] ) ) {
					$params[ 'orderby' ] = $dynamic_select[ 'field_text' ];
				}

				if ( is_array( $dynamic_select[ 'params' ] ) && !empty( $dynamic_select[ 'params' ] ) ) {
					$params = array_merge( $params, $dynamic_select[ 'params' ] );
				}

				$pod->find( $params );

				$choices = array();

				while ( $pod->fetch() ) {
					if ( !empty( $dynamic_select[ 'field_text' ] ) ) {
						$option_text = $pod->display( $dynamic_select[ 'field_text' ] );
					}
					else {
						$option_text = $pod->index();
					}

					if ( !empty( $dynamic_select[ 'field_value' ] ) ) {
						$option_value = $pod->display( $dynamic_select[ 'field_value' ] );
					}
					else {
						$option_value = $pod->id();
					}

					$choices[] = array(
						'text' => $option_text,
						'value' => $option_value
					);
				}
			}

			$choices = apply_filters( 'pods_gf_dynamic_choices_' . $form[ 'id' ] . '_' . $field, $choices, $dynamic_select, $field, $form, $dynamic_selects );
			$choices = apply_filters( 'pods_gf_dynamic_choices_' . $form[ 'id' ], $choices, $dynamic_select, $field, $form, $dynamic_selects );
			$choices = apply_filters( 'pods_gf_dynamic_choices', $choices, $dynamic_select, $field, $form, $dynamic_selects );

			if ( !is_array( $choices ) ) {
				continue;
			}

			if ( null !== $dynamic_select[ 'default' ] ) {
				$form[ 'fields' ][ $field_key ][ 'defaultValue' ] = $dynamic_select[ 'default' ];
			}

			$form[ 'fields' ][ $field_key ][ 'choices' ] = $choices;

			// Additional handling for checkboxes
			if ( 'checkbox' == $form[ 'fields' ][ $field_key ][ 'type' ] ) {
				$inputs = array();

				$input_id = 1;

				foreach ( $choices as $choice ) {
					// Workaround for GF bug with multiples of 10 (so that 5.1 doesn't conflict with 5.10)
					if ( 0 == $input_id % 10 ) {
						$input_id++;
					}

					$inputs[] = array(
						'label' => $choice[ 'text' ],
						'name' => '', // not used
						'id' => $field . '.' . $input_id
					);
				}

				$form[ 'fields' ][ $field_key ][ 'inputs' ] = $inputs;
			}
		}

		return $form;

	}

	/**
	 * Prepopulate a GF Form
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 * @param array|null $prepopulate The prepopulate array
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_prepopulate( $form, $ajax, $prepopulate = null ) {

		if ( null === $prepopulate ) {
			$prepopulate = self::$prepopulate;
		}

		if ( empty( $prepopulate ) ) {
			return $form;
		}

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
			return $form;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		$prepopulate = array_merge(
			array(
				'form' => $form[ 'id' ],

				'pod' => null,
				'id' => null,
				'fields' => array()
			),
			$prepopulate
		);

		if ( $prepopulate[ 'form' ] != $form[ 'id' ] ) {
			return $form;
		}

		$pod = $prepopulate[ 'pod' ];
		$id = $prepopulate[ 'id' ];

		if ( !is_array( $pod ) && !empty( $pod ) ) {
			if ( !is_object( $pod ) ) {
				$pod = pods( $pod, $id );
			}
			elseif ( $pod->id != $id ) {
				$pod->fetch( $id );
			}
		}
		else {
			if ( empty( $prepopulate[ 'fields' ] ) ) {
				$fields = array();

				foreach ( $form[ 'fields' ] as $field ) {
					$fields[ $field[ 'id' ] ] = array(
						'gf_field' => $field[ 'id' ],
						'field' => $field[ 'id' ]
					);
				}

				$prepopulate[ 'fields' ] = $fields;
			}

			if ( !empty( $id ) ) {
				$pod = RGFormsModel::get_lead( $id );
			}
			else {
				$pod = array();
				$id = 0;
			}
		}

		// Prepopulate values
		foreach ( $prepopulate[ 'fields' ] as $field => $field_options ) {
			$field = (string) $field;

			$field_options = array_merge(
				array(
					'gf_field' => $field,
					'field' => $field_options
				),
				( is_array( $field_options ) ? $field_options : array() )
			);

			if ( !empty( $field_options[ 'gf_field' ] ) ) {
				$field = (string) $field_options[ 'gf_field' ];
			}

			// No GF field set
			if ( empty( $field ) || !isset( $field_keys[ $field ] ) ) {
				continue;
			}

			// No Pod field set
			if ( empty( $field_options[ 'field' ] ) || is_array( $field_options[ 'field' ] ) ) {
				continue;
			}

			$field_key = $field_keys[ $field ];

			// Allow for value to be overridden by existing prepopulation or callback
			$value_override = null;

			if ( isset( $form[ 'fields' ][ $field_key ][ 'allowsPrepopulate' ] ) && $form[ 'fields' ][ $field_key ][ 'allowsPrepopulate' ] ) {
				if ( 'checkbox' == $form[ 'fields' ][ $field_key ][ 'type' ] && isset( $form[ 'fields' ][ $field_key ][ 'inputs' ] ) ) {
					// @todo do something different
				}
				elseif ( isset( $form[ 'fields' ][ $field_key ][ 'inputName' ] ) && isset( $_GET[ $form[ 'fields' ][ $field_key ][ 'inputName' ] ] ) ) {
					$value_override = $_GET[ $form[ 'fields' ][ $field_key ][ 'inputName' ] ];
				}
			}

			if ( null === $value_override ) {
				$value = $value_override;

				if ( is_object( $pod ) ) {
					$value_override = $pod->field( $field_options[ 'field' ] );
				}
				elseif ( !empty( $pod ) ) {
					if ( isset( $pod[ $field_options[ 'field' ] ] ) ) {
						$value_override = maybe_unserialize( $pod[ $field_options[ 'field' ] ] );

						if ( 'list' == $form[ 'fields' ][ $field_key ][ 'type' ] && !empty( $value_override ) ) {
							$list = $value_override;

							$value_override = array();

							foreach ( $list as $list_row ) {
								$value_override = array_merge( $value_override, array_values( $list_row ) );
							}
						}
					}
					elseif ( 'checkbox' == $form[ 'fields' ][ $field_key ][ 'type' ] ) {
						$items = 0;
						$counter = 1;

						while ( $items < count( $form[ 'fields' ][ $field_key ][ 'choices' ] ) ) {
							if ( isset( $pod[ $field_options[ 'field' ] . '.' . $counter ] ) ) {
								foreach ( $form[ 'fields' ][ $field_key ][ 'choices' ] as $k => $choice ) {
									if ( $choice[ 'value' ] == $pod[ $field_options[ 'field' ] . '.' . $counter ] ) {
										$form[ 'fields' ][ $field_key ][ 'choices' ][ $k ][ 'isSelected' ] = true;

										break;
									}
								}
							}

							$counter++;

							if ( $counter % 10 ) {
								$counter++;
							}

							$items++;
						}
					}
				}
			}

			$form[ 'fields' ][ $field_key ][ 'allowsPrepopulate' ] = true;
			$form[ 'fields' ][ $field_key ][ 'inputName' ] = 'pods_gf_field_' . $field;

			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form[ 'id' ] . '_' . $field, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form[ 'id' ], $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$value_override = apply_filters( 'pods_gf_pre_populate_value', $value_override, $field, $field_options, $form, $prepopulate, $pod );

			if ( null !== $value_override ) {
				$_GET[ 'pods_gf_field_' . $field ] = $value_override;
			}

			$post_value_override = null;
			$post_value_override = apply_filters( 'pods_gf_field_value_' . $form[ 'id' ] . '_' . $field, $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$post_value_override = apply_filters( 'pods_gf_field_value_' . $form[ 'id' ], $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$post_value_override = apply_filters( 'pods_gf_field_value', $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );

			if ( null !== $post_value_override ) {
				$_POST[ 'input_' . $field ] = $post_value_override;
			}
		}

		return $form;

	}

	/**
	 * Enable Markdown Syntax for HTML fields
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_markdown( $form, $ajax ) {

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
			return $form;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

		if ( !function_exists( 'Markdown' ) ) {
			include_once PODS_GF_DIR . 'includes/Markdown.php';
		}

		$sanitize_from_markdown = array(
			'-',
			'_'
		);

		$temporary_sanitization = array(
			'XXXXMERGEDASHXXXX',
			'XXXXMERGEUNDERSCOREXXXX'
		);

		foreach ( $form[ 'fields' ] as $k => $field ) {
			if ( 'html' == $field[ 'type' ] ) {
				$content = $field[ 'content' ];

				preg_match_all( "/\{([\w\:\.\_\-]*?)\}/", $content, $merge_tags, PREG_SET_ORDER );

				// Sanitize merge tags (from Markdown)
				foreach ( $merge_tags as $merge_tag_match ) {
					$merge_tag = $merge_tag_match[ 0 ];

					$merge_tag_sanitized = str_replace( $sanitize_from_markdown, $temporary_sanitization, $merge_tag );

					$content = str_replace( $merge_tag, $merge_tag_sanitized, $content );
				}

				// Run Markdown
				$content = Markdown( $content );

				// Unsanitize merge tags
				foreach ( $merge_tags as $merge_tag_match ) {
					$merge_tag = $merge_tag_match[ 0 ];

					$merge_tag_sanitized = str_replace( $sanitize_from_markdown, $temporary_sanitization, $merge_tag );

					$content = str_replace( $merge_tag_sanitized, $merge_tag, $content );
				}

				$form[ 'fields' ][ $k ][ 'content' ] = $content;
			}
		}

		return $form;

	}

	/**
	 * Override GF Field input to make read only
	 *
	 * @param string $input_html Input HTML override
	 * @param array $field GF Field array
	 * @param mixed $value Field value
	 * @param int $lead_id GF Lead ID
	 * @param int $form_id GF Form ID
	 *
	 * @return string Input HTML override
	 */
	public function _gf_field_input_read_only( $input_html, $field, $value, $lead_id, $form_id ) {

		if ( !isset( self::$actioned[ $form_id ] ) ) {
			self::$actioned[ $form_id ] = array();
		}

		if ( !isset( self::$actioned[ $form_id ][ 'form' ] ) ) {
			$form = RGFormsModel::get_form_meta( $form_id );

			self::$actioned[ $form_id ][ 'form' ] = $form;
		}
		else {
			$form = self::$actioned[ $form_id ][ 'form' ];
		}

		if ( !isset( self::$actioned[ $form_id ][ __FUNCTION__ ] ) ) {
			self::$actioned[ $form_id ][ __FUNCTION__ ] = 0;
		}

		$last_page = self::$actioned[ $form_id ][ __FUNCTION__ ];

		$read_only = pods_var_raw( 'read_only', $this->options, false );

		$non_read_only = array(
			'hidden',
			'captcha',
			'page',
			'section',
			'honeypot',
			'list'
		);

		$field_type = RGFormsModel::get_input_type( $field );

		$page_header = '';

		if ( $this->form_id != $form_id || in_array( $field_type, $non_read_only ) || ( false === $read_only || ( is_array( $read_only ) && !in_array( $field, $read_only ) ) ) ) {
			return $input_html;
		}

		if ( isset( $field[ 'pageNumber' ] ) && 0 < $field[ 'pageNumber' ] && $last_page != $field[ 'pageNumber' ] ) {
			self::$actioned[ $form_id ][ __FUNCTION__ ] = $field[ 'pageNumber' ];

			$page_header = '<h3 class="gf-page-title">' . $form[ 'pagination' ][ 'pages' ][ ( $field[ 'pageNumber' ] - 1 ) ] . '</h3>';
		}

		if ( 'html' == $field_type ) {
			$input_html = IS_ADMIN ? "<img class='gfield_html_block' src='" . GFCommon::get_base_url() . "/images/gf-html-admin-placeholder.jpg' alt='HTML Block'/>" : $field[ 'content' ];
			$input_html = GFCommon::replace_variables_prepopulate( $input_html ); //adding support for merge tags
			$input_html = do_shortcode( $input_html ); //adding support for shortcodes

			return $page_header . $input_html;
		}

		$input_field_name = 'input_' . $field[ 'id' ];

		if ( is_array( $value ) || isset( $field[ 'choices' ] ) ) {
			$labels = array();
			$values = array();

			$value = (array) $value;

			if ( isset( $field[ 'choices' ] ) ) {
            	$choice_number = 1;

				foreach ( $field[ 'choices' ] as $choice ) {
					if($choice_number % 10 == 0) //hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
						$choice_number++;

					if ( in_array( $choice[ 'value' ], $value ) ) {
						$values[ $choice_number ] = $choice[ 'value' ];
						$labels[] = $choice[ 'text' ];
					}

					$choice_number++;
				}
			}
			else {
            	$choice_number = 1;

				foreach ( $value as $val ) {
					if($choice_number % 10 == 0) //hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
						$choice_number++;

					$values[ $choice_number ] = $val;

					$choice_number++;
				}
			}

			$input_html = '<div class="ginput_container">';
			$input_html .= '<ul>';

			foreach ( $labels as $label ) {
				$input_html .= '<li>' . esc_html( $label ) . '</li>';
			}

			$input_html .= '</ul>';

			foreach ( $values as $choice_number => $val ) {
				$input_field_name_choice = $input_field_name;

				if ( 'checkbox' == $input_field_name_choice ) {
					$input_field_name_choice .= '_' . $choice_number;
				}

				$input_html .= '<input type="text" name="' . $input_field_name_choice . '" value="' . esc_attr( $val ) . '" readonly="readonly" style="display:none;" class="hidden" />';
			}

			$input_html .= '</div>';
		}
		else {
			$label = $value;

			if ( in_array( $field_type, array( 'total', 'donation', 'price' ) ) ) {
				if ( empty( $value ) ) {
					$value = 0;
				}

				$label = GFCommon::to_money( $value );
				$value = GFCommon::to_number( $value );
			}
			elseif ( in_array( $field_type, array( 'number' ) ) ) {
				if ( empty( $value ) ) {
					$value = 0;
				}

				$label = $value = GFCommon::to_number( $value );
			}

			$input_html = '<div class="ginput_container">';
			$input_html .= esc_html( $label );
			$input_html .= '<input type="text" name="' . $input_field_name . '" value="' . esc_attr( $value ) . '" readonly="readonly" style="display:none;" class="hidden" />';
			$input_html .= '</div>';
		}

		return $page_header . $input_html;

	}

	/**
	 * Override GF List Field column input to make read only
	 *
	 * @param string $input Input HTML
	 * @param array $input_info GF List Field info (for select choices)
	 * @param array $field GF Field array
	 * @param string $text GF List Field column name
	 * @param mixed $value Field value
	 * @param int $form_id GF Form ID
	 *
	 * @return string Input HTML override
	 */
	public function _gf_field_column_read_only( $input, $input_info, $field, $text, $value, $form_id ) {

		if ( $this->form_id != $form_id ) {
			return $input;
		}

		$input_field_name = 'input_' . $field[ 'id' ] . '[]';

		$label = $value;

		if ( isset( $input_info[ 'choices' ] ) ) {
			foreach ( $input_info[ 'choices' ] as $choice ) {
				if ( $value == $choice[ 'value' ] ) {
					$label = $choice[ 'text' ];

					break;
				}
			}
		}
		elseif ( false !== strpos( $input, 'type="checkbox"' ) || false !== strpos( $input, 'type=\'checkbox\'' ) ) {
			$label = ( $value ? __( 'Yes', 'pods-gravity-forms' ) : __( 'No', 'pods-gravity-forms' ) );
		}

		$input = esc_html( $label );
		$input .= '<input type="text" name="' . $input_field_name . '" value="' . esc_attr( $value ) . '" readonly="readonly" style="display:none;" class="hidden" />';

		return $input;

	}

	/**
	 * Action handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public function _gf_pre_render( $form, $ajax ) {
		if ( empty( $this->options ) ) {
			return $form;
		}

		// Add Dynamic Selects
		if ( isset( $this->options[ 'dynamic_select' ] ) && !empty( $this->options[ 'dynamic_select' ] ) ) {
			$form = self::gf_dynamic_select( $form, $ajax, $this->options[ 'dynamic_select' ] );
		}

		// Prepopulate values
		if ( isset( $this->options[ 'prepopulate' ] ) && !empty( $this->options[ 'prepopulate' ] ) ) {
			$prepopulate = array(
				'pod' => $this->pod,
				'id' => pods_var( 'save_id', $this->options, pods_var( 'id', $this->pod, $this->id, null, true ), null, true ),
				'fields' => $this->options[ 'fields' ]
			);

			if ( is_array( $this->options[ 'prepopulate' ] ) ) {
				$prepopulate = array_merge( $prepopulate, $this->options[ 'prepopulate' ] );
			}

			$form = self::gf_prepopulate( $form, $ajax, $prepopulate );
		}

		// Markdown Syntax for HTML
		if ( isset( $this->options[ 'markdown' ] ) && !empty( $this->options[ 'markdown' ] ) ) {
			$form = self::gf_markdown( $form, $ajax );
		}

		return $form;

	}

	/**
	 * Action handler for Gravity Forms: gform_get_form_filter_{$form_id}
	 *
	 * @param string $form_string Form HTML
	 * @param array $form GF Form array
	 *
	 * @return string Form HTML
	 */
	public function _gf_get_form_filter( $form_string, $form ) {

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
			return $form_string;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

		return $form_string;

	}

	/**
	 * Action handler for Gravity Forms: gform_field_validation_{$form_id}_{$field_id}
	 *
	 * @param array $validation_result GF validation result
	 * @param mixed $value Value submitted
	 * @param array $form GF Form array
	 * @param array $field GF Form Field array
	 *
	 * @return array GF validation result
	 */
	public function _gf_field_validation( $validation_result, $value, $form, $field ) {

		if ( !$validation_result[ 'is_valid' ] ) {
			return $validation_result;
		}

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__ . '_' . $field[ 'id' ], self::$actioned[ $form[ 'id' ] ] ) ) {
			return $validation_result;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__ . '_' . $field[ 'id' ];

		if ( empty( $this->options ) ) {
			return $form;
		}

		$field_options = array();

		if ( isset( $this->options[ 'fields' ][ (string) $field[ 'id' ] ] ) ) {
			$field_options = $this->options[ 'fields' ][ (string) $field[ 'id' ] ];
		}
		elseif ( isset( $this->options[ 'fields' ][ (int) $field[ 'id' ] ] ) ) {
			$field_options = $this->options[ 'fields' ][ (int) $field[ 'id' ] ];
		}

		if ( !is_array( $field_options ) ) {
			$field_options = array(
				'field' => $field_options
			);
		}

		$validate = true;

		if ( is_object( $this->pod ) ) {
			$field_data = $this->pod->fields( $field_options[ 'field' ] );

			if ( empty( $field_data ) ) {
				return $validation_result;
			}

			$pods_api = pods_api();

			$validate = $pods_api->handle_field_validation( $value, $field_data, $this->pod->pod_data[ 'object_fields' ], $this->pod->pod_data[ 'fields' ], $this->pod, null );
		}

		$validate = apply_filters( 'pods_gf_field_validation_' . $form[ 'id' ] . '_' . (string) $field[ 'id' ], $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );
		$validate = apply_filters( 'pods_gf_field_validation_' . $form[ 'id' ], $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );
		$validate = apply_filters( 'pods_gf_field_validation', $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );

		if ( false === $validate ) {
			$validate = 'There was an issue validating the field ' . $field[ 'label' ];
		}
		elseif ( true !== $validate ) {
			$validate = (array) $validate;
		}

		if ( !is_bool( $validate ) && !empty( $validate ) ) {
			$validation_result[ 'is_valid' ] = false;

			if ( is_array( $validate ) ) {
				if ( 1 == count( $validate ) ) {
					$validate = current( $validate );
				}
				else {
					$validate = 'The following issues occurred:' . "\n<ul><li>" . implode( "</li>\n<li>", $validate ) . "</li></ul>";
				}
			}

			$validation_result[ 'message' ] = $validate;
		}

		return $validation_result;

	}

	/**
	 * Action handler for Gravity Forms: gform_validation_{$form_id}
	 *
	 * @param array $validation_result GF Validation result
	 *
	 * @return array GF Validation result
	 */
	public function _gf_validation( $validation_result ) {

		if ( !$validation_result[ 'is_valid' ] ) {
			return $validation_result;
		}

		if ( isset( self::$actioned[ $validation_result[ 'form' ][ 'id' ] ] ) && in_array( self::$actioned[ $validation_result[ 'form' ][ 'id' ] ], __FUNCTION__ ) ) {
			return $validation_result;
		}
		elseif ( !isset( self::$actioned[ $validation_result[ 'form' ][ 'id' ] ] ) ) {
			self::$actioned[ $validation_result[ 'form' ][ 'id' ] ] = array();
		}

		self::$actioned[ $validation_result[ 'form' ][ 'id' ] ][] = __FUNCTION__;

		$form = $validation_result[ 'form' ];

		if ( empty( $this->options ) ) {
			return $form;
		}

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		$id = (int) pods_var( 'id', $this->pod, 0 );
		$save_action = 'add';

		if ( !empty( $id ) ) {
			$save_action = 'edit';
		}

		if ( isset( $this->options[ 'save_id' ] ) && !empty( $this->options[ 'save_id' ] ) ) {
			$id = (int) $this->options[ 'save_id' ];
		}

		if ( isset( $this->options[ 'save_action' ] ) ) {
			$save_action = $this->options[ 'save_action' ];
		}

		if ( empty( $id ) || !in_array( $save_action, array( 'add', 'save' ) ) ) {
			$save_action = 'add';
		}

		$data = self::gf_to_pods( $form, $this->options );

		try {
			$args = array(
				$data
			);

			if ( 'edit' == $save_action ) {
				$args[] = $id;
			}

			if ( is_object( $this->pod ) ) {
				$id = call_user_func_array( array( $this->pod, $save_action ), $args );

				$this->pod->id = $id;
				$this->pod->fetch( $id );

				do_action( 'pods_gf_to_pods_' . $this->pod->pod, $this->pod, $args, $save_action, $data, $id, $this );
			}
			else {
				$this->id = $id = apply_filters( 'pods_gf_to_pod_' . $save_action, $id, $this->pod, $data, $this );
			}

			do_action( 'pods_gf_to_pods', $this->pod, $save_action, $data, $id, $this );
		}
		catch ( Exception $e ) {
			$validation_result[ 'is_valid' ] = false;

			$this->gf_validation_message = 'Error saving: ' . $e->getMessage();

			return $validation_result;
		}

		return $validation_result;

	}

	/**
	 * Action handler for Gravity Forms: gform_validation_message_{$form_id}
	 *
	 * @param string $validation_message GF validation message
	 * @param array $form GF Form array
	 *
	 * @return null
	 */
	public function _gf_validation_message( $validation_message, $form ) {

		if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
			return $validation_message;
		}
		elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
			self::$actioned[ $form[ 'id' ] ] = array();
		}

		self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

		if ( !empty( $this->gf_validation_message ) ) {
			$validation_message .= "\n" . '<div class="validation_error">' . $this->gf_validation_message . '</div>';
		}

		return $validation_message;

	}

	/**
	 * Action handler for Gravity Forms: gform_after_submission_{$form_id}
	 *
	 * @param array $entry GF Entry array
	 * @param array $form GF Form array
	 */
	public function _gf_after_submission( $entry, $form ) {

		if ( empty( $this->gf_validation_message ) ) {
			if ( isset( self::$actioned[ $form[ 'id' ] ] ) && in_array( __FUNCTION__, self::$actioned[ $form[ 'id' ] ] ) ) {
				return $entry;
			}
			elseif ( !isset( self::$actioned[ $form[ 'id' ] ] ) ) {
				self::$actioned[ $form[ 'id' ] ] = array();
			}

			self::$actioned[ $form[ 'id' ] ][] = __FUNCTION__;

			if ( empty( $this->options ) ) {
				return $entry;
			}

			// Send notifications
			self::gf_notifications( $entry, $form, $this->options );

			if ( pods_var_raw( 'auto_delete', $this->options, false ) ) {
				$keep_files = false;

				if ( pods_var_raw( 'keep_files', $this->options, false ) ) {
					$keep_files = true;
				}

				self::gf_delete_entry( $entry, $keep_files );
			}

			if ( pods_var_raw( 'redirect_after', $this->options, true ) ) {
				$confirmation = GFFormDisplay::handle_confirmation( $form, $entry );

				if ( 'redirect' != $form[ 'confirmation' ][ 'type' ] || !is_array( $confirmation ) || !isset( $confirmation[ 'redirect' ] ) ) {
					pods_redirect( pods_var_update( array( 'action' => 'edit', 'id' => $this->id ) ) );
				}
				else {
					pods_redirect( $confirmation[ 'redirect' ] );
				}
			}
		}

	}

}