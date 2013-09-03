<?php
/**
 * Class Pods_GF
 */
class Pods_GF {

	/**
	 * Pods object
	 *
	 * @var Pods
	 */
	public $pod;

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
	 * Add Pods GF integration for a specific form
	 *
	 * @param string|Pods Pod name (or Pods object)
	 * @param int $form_id GF Form ID
	 * @param array $options Form options for integration
	 */
	public function __construct( $pod, $form_id, $options = array() ) {

		if ( is_object( $pod ) ) {
			$this->pod =& $pod;
		}
		else {
			$this->pod = pods( $pod );
		}

		$this->form_id = $form_id;
		$this->options = $options;

		if ( !pods_var( 'admin', $this->options, 0 ) && is_admin() ) {
			return;
		}

		if ( !has_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ), 10, 2 );
			add_filter( 'gform_get_form_filter_' . $form_id, array( $this, '_gf_get_form_filter' ), 10, 2 );

			add_filter( 'gform_validation_' . $form_id, array( $this, '_gf_validation' ), 11, 1 );
			add_filter( 'gform_validation_message_' . $form_id, array( $this, '_gf_validation_message' ), 11, 2 );

			add_action( 'gform_after_submission_' . $form_id, array( $this, '_gf_after_submission' ), 10, 2 );
		}

		if ( isset( $options[ 'fields' ] ) && !empty( $options[ 'fields' ] ) ) {
			foreach ( $options[ 'fields' ] as $field => $field_options ) {
				if ( is_array( $field_options ) && isset( $field_options[ 'gf_field' ] ) ) {
					$field = $field_options[ 'gf_field' ];
				}

				if ( !has_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ) ) ) {
					add_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ), 11, 4 );
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

		if ( empty( $prepopulate[ 'pod' ] ) || $prepopulate[ 'form' ] != $form[ 'id' ] ) {
			return $form;
		}

		$pod = $prepopulate[ 'pod' ];
		$id = $prepopulate[ 'id' ];

		if ( !is_object( $pod ) ) {
			$pod = pods( $pod, $id );
		}
		elseif ( $pod->id != $id ) {
			$pod->fetch( $id );
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

			$form[ 'fields' ][ $field_key ][ 'allowsPrepopulate' ] = true;
			$form[ 'fields' ][ $field_key ][ 'inputName' ] = 'pods_gf_field_' . $field;

			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form[ 'id' ] . '_' . $field, $value_override, $field, $field_options, $form, $prepopulate );
			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form[ 'id' ], $value_override, $field, $field_options, $form, $prepopulate );
			$value_override = apply_filters( 'pods_gf_pre_populate_value', $value_override, $field, $field_options, $form, $prepopulate );

			if ( null !== $value_override ) {
				$_GET[ 'pods_gf_field_' . $field ] = $pod->field( $field_options[ 'field' ] );
			}
		}

		return $form;

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

		if ( isset( $this->options[ 'dynamic_select' ] ) && !empty( $this->options[ 'dynamic_select' ] ) ) {
			$form = self::gf_dynamic_select( $form, $ajax, $this->options[ 'dynamic_select' ] );
		}

		// Prepopulate values
		if ( isset( $this->options[ 'prepopulate' ] ) && !empty( $this->options[ 'prepopulate' ] ) ) {
			$prepopulate = array(
				'pod' => $this->pod,
				'id' => pods_var( 'save_id', $this->options, $this->pod->id, null, true ),
				'fields' => $this->options[ 'fields' ]
			);

			if ( is_array( $this->options[ 'prepopulate' ] ) ) {
				$prepopulate = array_merge( $prepopulate, $this->options[ 'prepopulate' ] );
			}

			$form = self::gf_prepopulate( $form, $ajax, $prepopulate );
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

		$field_data = $this->pod->fields( $field_options[ 'field' ] );

		if ( empty( $field_data ) ) {
			return $validation_result;
		}

		$pods_api = pods_api();

		$validate = $pods_api->handle_field_validation( $value, $field_data, $this->pod->pod_data[ 'object_fields' ], $this->pod->pod_data[ 'fields' ], $this->pod, null );

		$validate = apply_filters( 'pods_gf_field_validation_' . $form[ 'id ' ] . '_' . (string) $field[ 'id ' ], $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );
		$validate = apply_filters( 'pods_gf_field_validation_' . $form[ 'id ' ], $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );
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

		$form = $validation_result[ 'form' ];

		if ( empty( $this->options ) ) {
			return $form;
		}

		$field_keys = array();

		foreach ( $form[ 'fields' ] as $k => $field ) {
			$field_keys[ (string) $field[ 'id' ] ] = $k;
		}

		$id = 0;
		$save_action = 'add';

		if ( !empty( $this->pod->id ) ) {
			$id = $this->pod->id;
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

			$id = call_user_func_array( array( $this->pod, $save_action ), $args );

			$this->pod->id = $id;
			$this->pod->fetch( $id );
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
			if ( empty( $this->options ) ) {
				return;
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
					pods_redirect( pods_var_update( array( 'action' => 'edit', 'id' => $this->pod->id ) ) );
				}
				else {
					pods_redirect( $confirmation[ 'redirect' ] );
				}
			}
		}

	}

}