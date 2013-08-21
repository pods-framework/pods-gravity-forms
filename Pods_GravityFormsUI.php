<?php
if ( function_exists( 'pods' ) && class_exists( 'GFCommon' ) ) {
	/**
	 * Class Pods_GravityFormsUI
	 */
	class Pods_GravityFormsUI {

		/**
		 * @var Pods_GravityFormsUI
		 */
		public static $obj;

		/**
		 * @var Pods
		 */
		public $pod;

		/**
		 * @var int Current item ID
		 */
		public $id = 0;

		/**
		 * @var array UI options for PodsUI
		 */
		public $ui = array(
			'label' => array(),
			'header' => array(),
			'actions_custom' => array(),
			'actions_disabled' => array(
				'reorder',
				'duplicate',
				'delete',
				'export'
			),
			'fields' => array()
		);

		/**
		 * @var string Current action
		 */
		public $action = 'manage';

		/**
		 * @var array Available actions
		 */
		public $actions = array(
			/*
				'action_name' => array(
					'label' => 'Label used in action link',
					'title' => 'Title used on action page',
					'fields' => array(
						'1' => 'pod_field_name' // GF field ID or $_POST name, mapped to a pod field
						'2' => array(
							'field' => 'pod_field_name',
							'callback' => 'gf_pod_field_name', // Filter the value sent to Pods
							'value_callback' => 'gf_pod_field_name_value' // Filter the value sent to GF
						)
						// All fields processed, even if not found/set in GF/$_POST
						'faux' => array(
							'field' => 'pod_field_name',
							'callback' => 'gf_pod_field_name' // Required if field not found/set
						)
					),
					'save_action' => 'add', // What action to use when saving (add|save)
					'callback' => null, // Function to callback on action page
					'access' => null, // Access matrix checks
					'access_callback' => null, // Access callback to override access rights
					'disabled' => false // Whether action is disabled

					// GF specific
					'form' => 123, // Form ID
					'dynamic_select' => array(
						'1' => array( // GF Field ID
							'pod' => 'other_pod', // Pod to pull data from
							'field_text' => 'post_title', // Field to pull option text from, defaults to 'name'
							'field_value' => 'ID', // Field to pull option value from, defaults to id()
							'options' => array( // Custom array of options to use
								array(
									'text' => 'Option 1',
									'value' => 1
								),
								array(
									'text' => 'Option 2',
									'value' => 2
								)
							),
							'callback' => null, // Function to callback for choices values
							'default' => 14, // Default value to select
							'params' => array(), // Params to use for find()
						)
					),
					'field' => 'status', // Pod field name for action to interact with (used with Status action)
					'data' => array(), // Array of options available for field (used with Status action)
					'prepopulate' => false, // Disable value prepopulate, defaults to true
					'auto_delete => true, // Automatically delete the GF entry created
				),
			 */
			'manage' => array( // table ui manage
				'label' => 'Manage',
				'title' => 'Manage',
				'fields' => array(),
				'callback' => null,
				'access' => null,
				'access_callback' => null,
				'disabled' => false
			),
			'add' => array( // form
				'label' => 'Add',
				'title' => 'Add',
				'form' => 0,
				'fields' => array(),
				'dynamic_select' => array(),
				'callback' => null,
				'access' => null,
				'access_callback' => null,
				'disabled' => false,
				'prepopulate' => false
			),
			'edit' => array( // alternate form or original form (pre-populate data)
				'label' => 'Edit',
				'title' => 'Edit',
				'form' => 0,
				'fields' => array(),
				'dynamic_select' => array(),
				'callback' => null,
				'access' => null,
				'access_callback' => null,
				'disabled' => false
			),
			'status' => array( // switching status, can define field name (default 'status')
				'label' => 'Change Status',
				'title' => 'Change Status',
				'form' => 0,
				'field' => 'status',
				'data' => array(), // what stati to use (dynamically build, based on pod/field)
				'fields' => array(),
				'dynamic_select' => array(),
				'callback' => null,
				'access' => null,
				'access_callback' => null,
				'disabled' => false
			),
			'view' => array( // view details
				'label' => 'View Details',
				'title' => 'View Details',
				'fields' => array(),
				'callback' => null,
				'access' => null,
				'access_callback' => null,
				'disabled' => false
			)
		);

		/**
		 * @var string|null Access denied reason text
		 */
		public $access_reason;

		/**
		 * @var array Workflow constraints for actions
		 */
		public $workflow_constraints = array(
			'add' => array(
				'limit' => -1, // simple limit
				'limit_status' => array( // enhanced limit with params
					'type' => 'limit',
					'compare' => '<',
					'value' => -1,
					'params' => array(),
				)
			)
		);

		/**
		 * @var array Notification configs
		 */
		public $notifications = array(
			/*
				'action_name' => array(
					// User IDs, E-mails, or Roles
					'template-name-or-id' => array(
						1,
						2,
						'email@site.com',
						'administrator'
					),
					'template-name-or-id2' => 1, // Simple user
					'template-name-or-id3' => 'email@site.com', // Simple e-mail
					'template-name-or-id4' => 'administrator', // Simple role mapping
				),
			 */
		);

		/**
		 * @var string|null Gravity Forms validation message override
		 */
		public $gf_validation_message;

		/**
		 * Setup Pods_GravityFormsUI object
		 *
		 * @param array $options Pods_GravityFormsUI option overrides
		 */
		public function __construct( $options ) {
			self::$obj =& $this;

			$this->init_ui();

			if ( is_array( $options ) && !empty( $options ) ) {
				foreach ( $options as $option => $value ) {
					if ( isset( $this->{$option} ) ) {
						if ( is_array( $this->{$option} ) && is_array( $value ) ) {
							foreach ( $value as $k => $v ) {
								if ( isset( $this->{$option}[ $k ] ) && 'ui' != $option ) {
									$this->{$option}[ $k ] = array_merge( $this->{$option}, $v );
								}
								else {
									$this->{$option}[ $k ] = $v;
								}
							}
						}
						else {
							$this->{$option} = $value;
						}
					}
				}
			}

			$this->setup_ui();

			$forms = array(
				'add',
				'edit'
			);

			foreach ( $forms as $form ) {
				if ( isset( $this->actions[ $form ] ) && 0 < (int) pods_var( 'form', $this->actions[ $form ] ) ) {
					$form_id = (int) pods_var( 'form', $this->actions[ $form ] );

					add_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ), 10, 2 );
					add_filter( 'gform_get_form_filter_' . $form_id, array( $this, '_gf_get_form_filter' ), 10, 2 );

					foreach ( $this->actions[ $form ][ 'fields' ] as $field => $field_options ) {
						add_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ), 11, 4 );
					}

					add_filter( 'gform_validation_' . $form_id, array( $this, '_gf_validation' ), 11, 1 );
					add_filter( 'gform_validation_message_' . $form_id, array( $this, '_gf_validation_message' ), 11, 2 );

					add_action( 'gform_after_submission_' . $form_id, array( $this, '_gf_after_submission' ), 10, 2 );
				}
			}
		}

		/**
		 * Initialize the default options
		 */
		private function init_ui() {
			foreach ( $this->actions as $action => $options ) {
				if ( !$this->access( $action ) ) {
					$this->actions[ $action ][ 'disabled' ] = true;

					$this->ui[ 'actions_disabled' ][ $action ] = $action;
				}
			}

			$this->actions[ 'add' ][ 'callback' ] = array( $this, '_action_add' );
			$this->actions[ 'edit' ][ 'callback' ] = array( $this, '_action_edit' );

			$this->action = pods_var_raw( 'action', 'get', $this->action, null, true );
		}

		/**
		 * Setup UI from options
		 */
		private function setup_ui() {
			foreach ( $this->actions as $action => $options ) {
				if ( null !== pods_var_raw( 'label', $options, null, null, true ) )
					$this->ui[ 'label' ][ $action ] = $options[ 'label' ];

				if ( null !== pods_var_raw( 'title', $options, null, null, true ) )
					$this->ui[ 'header' ][ $action ] = $options[ 'title' ];

				if ( null !== pods_var_raw( 'callback', $options, null, null, true ) )
					$this->ui[ 'actions_custom' ][ $action ] = $options[ 'callback' ];

				if ( array() !== pods_var_raw( 'fields', $options, array(), null, true ) )
					$this->ui[ 'fields' ][ $action ] = $options[ 'fields' ];
			}

			if ( !is_object( $this->pod ) )
				$this->pod = pods( $this->pod, ( 0 < $this->id ? $this->id : null ) );

			if ( 0 < $this->id )
				$_GET[ 'id' ] = $this->id;

			foreach ( $this->actions as $action => $options ) {
				if ( true !== pods_var_raw( 'disabled', $this->actions[ $action ], false, null, true ) && in_array( $action, $this->ui[ 'actions_disabled' ] ) ) {
					$this->actions[ $action ][ 'disabled' ] = false;

					unset( $this->ui[ 'actions_disabled' ][ array_search( $action, $this->ui[ 'actions_disabled' ] ) ] );
				}
			}

			if ( !isset( $this->actions[ $this->action ] ) || !$this->access( $this->action ) )
				$this->action = 'manage';
		}

		/**
		 * Get $obj instance
		 *
		 * @return bool|Pods_GravityFormsUI Current instance
		 */
		public static function get_instance() {
			if ( is_object( self::$obj ) )
				return self::$obj;

			return false;
		}

		/**
		 * Handle current action
		 *
		 * @return bool|mixed|PodsUI
		 */
		public function action() {
			$args = func_get_args();

			if ( empty( $args ) )
				$args = array( 'action' => $this->action );

			$action = array_shift( $args );

			if ( isset( $this->actions[ $action ] ) ) {
				// @todo replace callbacks with apply_filters or do_action
				$callback = pods_var_raw( 'callback', $this->actions[ $action ], null, null, true );

				if ( null !== $callback && is_callable( $callback ) ) {
					$args[ 'obj' ] =& $this;

					return call_user_func_array( $callback, $args );
				}
				else {
					return $this->pod->ui( $this->ui );
				}
			}

			return false;
		}

		/**
		 * Run UI, just shorthand for action()
		 *
		 * @return bool|mixed|PodsUI
		 * @see action
		 */
		public function ui() {
			return $this->action();
		}

		/**
		 * Check if user has access to a specific action
		 *
		 * @param string $action Action name
		 *
		 * @return bool Whether user has access to a specific action
		 */
		private function access( $action ) {
			$access = true;

			// Action disabled
			if ( true === pods_var_raw( 'disabled', $this->actions[ $action ], false, null, true ) || in_array( $action, $this->ui[ 'actions_disabled' ] ) ) {
				$access = false;
				$this->access_reason = 'Action disabled';
			}

			// Workflow constraints
			if ( $access && isset( $this->workflow_constraints[ $action ] ) ) {
				$constraints = array();

				foreach ( $this->workflow_constraints[ $action ] as $constraint => $constraint_options ) {
					$constraints[ $constraint ] = $constraint_options;

					// Non-arrays are always limit
					if ( !is_array( $constraint_options ) ) {
						$constraints[ $constraint ][ 'value' ] = array(
							'type' => 'limit',
							'compare' => '<',
							'value' => (int) $constraint_options
						);
					}
				}

				if ( !empty( $constraints ) ) {
					$access = $this->access_constraints( $constraints );
				}
			}

			// @todo replace callbacks with apply_filters or do_action
			// Access Callback
			$access_callback = pods_var_raw( 'access_callback', $this->actions[ $action ], null, null, true );

			if ( null !== $access_callback && is_callable( $access_callback ) )
				$access = call_user_func( $access_callback, $access, $this );

			return (boolean) $access;
		}

		/**
		 * Handle access constraints
		 *
		 * @param array $constraints Constraints arrays
		 * @return bool Whether the constraint rules all passed
		 */
		public function access_constraints( $constraints ) {
			$access = true;

			if ( is_array( $constraints ) && !empty( $constraints ) ) {
				$check = pods( $this->pod->pod );

				foreach ( $constraints as $constraint ) {
					$constraint = array_merge(
						array(
							'type' => 'limit',
							'compare' => '<',
							'value' => -1,
							'params' => array()
						),
						$constraint
					);

					// Limit constraints
					if ( 'limit' == $constraint[ 'type' ] ) {
						$constraint[ 'value' ] = (int) $constraint[ 'value' ];

						// Invalid value
						if ( $constraint[ 'value' ] < 0 ) {
							continue;
						}

						$params = array_merge(
							array(
								'limit' => 1 // doesn't matter what's returned, we look at total_found()
							),
							$constraint[ 'params' ]
						);

						$check->find( $params );

						// version_compare is the poor developer's math comparison function
						if ( !version_compare( (string) $check->total_found(), (string) $constraint[ 'value' ], $constraint[ 'compare' ] ) ) {
							$access = false;
							$this->access_reason = 'Limit constraint reached';
						}
					}
				}
			}

			return $access;
		}

		/**
		 * Embed Add form
		 */
		private function _action_add() {
			if ( isset( $this->actions[ 'add' ][ 'form' ] ) && 0 < $this->actions[ 'add' ][ 'form' ] ) {
				gravity_form( $this->actions[ 'add' ][ 'form' ] );
			}
			else {
				$this->pod->form();
			}
		}

		/**
		 * Embed Edit form
		 */
		private function _action_edit() {
			if ( isset( $this->actions[ 'edit' ][ 'form' ] ) && 0 < $this->actions[ 'edit' ][ 'form' ] ) {
				gravity_form( $this->actions[ 'edit' ][ 'form' ] );
			}
			else {
				$this->pod->form();
			}
		}

		/**
		 * Action handler for Gravity Forms: gform_pre_render_{$form_id}
		 *
		 * @param array $form GF Form array
		 * @param bool $ajax Whether the form was submitted using AJAX
		 * @return array $form GF Form array
		 */
		private function _gf_pre_render( $form, $ajax ) {
			$action = $this->actions[ $this->action ];

			if ( !isset( $action[ 'form' ] ) || $form[ 'id' ] != $action[ 'form' ] ) {
				return $form;
			}

			if ( !isset( $action[ 'dynamic_select' ] ) || empty( $action[ 'dynamic_select' ] ) ) {
				return $form;
			}

			$field_keys = array();

			foreach ( $form[ 'fields' ] as $k => $field ) {
				$field_keys[ (string) $field[ 'id' ] ] = $k;
			}

			// Dynamic Select handler
			foreach ( $action[ 'dynamic_select' ] as $field => $dynamic_select ) {
				$field = (string) $field;

				if ( !isset( $field_keys[ $field ] ) ) {
					continue;
				}

				$field_key = $field_keys[ $field ];

				$choices = false;

				if ( isset( $dynamic_select[ 'options' ] ) && is_array( $dynamic_select[ 'options' ] ) ) {
					$choices = $dynamic_select[ 'options' ];
				}
				elseif ( isset( $dynamic_select[ 'pod' ] ) && !empty( $dynamic_select[ 'pod' ] ) ) {
					$pod = pods( $dynamic_select[ 'pod' ] );

					$params = array(
							'orderby' => 't.' . $pod->pod_data[ 'field_index' ],
							'limit' => -1,
							'search' => false,
							'pagination' => false
					);

					if ( isset( $dynamic_select[ 'field_text' ] ) && !empty( $dynamic_select[ 'field_text' ] ) ) {
						$params[ 'orderby' ] = $dynamic_select[ 'field_text' ];
					}

					if ( isset( $dynamic_select[ 'params' ] ) && is_array( $dynamic_select[ 'params' ] ) ) {
						$params = array_merge( $params, $dynamic_select[ 'params' ] );
					}

					$pod->find( $params );

					$choices = array();

					while ( $pod->fetch() ) {
						if ( isset( $dynamic_select[ 'field_text' ] ) && !empty( $dynamic_select[ 'field_text' ] ) ) {
							$option_text = $pod->display( $dynamic_select[ 'field_text' ] );
						}
						else {
							$option_text = $pod->index();
						}

						if ( isset( $dynamic_select[ 'field_text' ] ) && !empty( $dynamic_select[ 'field_value' ] ) ) {
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

				// @todo replace callbacks with apply_filters or do_action
				if ( isset( $dynamic_select[ 'callback' ] ) && is_callable( $dynamic_select[ 'callback' ] ) ) {
					$choices = call_user_func( $dynamic_select[ 'callback' ], $choices, $dynamic_select, $this );
				}

				if ( !is_array( $choices ) ) {
					continue;
				}

				if ( isset( $dynamic_select[ 'default' ] ) && null !== $dynamic_select[ 'default' ] ) {
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

			// Prepopulate values
			if ( !isset( $action[ 'prepopulate' ] ) || $action[ 'prepopulate' ] ) {
				foreach ( $action[ 'fields' ] as $field => $field_options ) {
					$field = (string) $field;

					if ( !isset( $field_keys[ $field ] ) ) {
						continue;
					}

					$field_key = $field_keys[ $field ];

					if ( !is_array( $field_options ) ) {
						$field_options = array(
							'field' => $field_options
						);
					}

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

					// @todo replace callbacks with apply_filters or do_action
					if ( isset( $field_options[ 'value_callback' ] ) && is_callable( $field_options[ 'value_callback' ] ) ) {
						$value_override = call_user_func( $field_options[ 'value_callback' ], $value_override, $field, $field_options, $this );
					}

					if ( null !== $value_override ) {
						$_GET[ 'pods_gf_field_' . $field ] = $this->pod->field( $field_options[ 'field' ] );
					}
				}
			}

			return $form;
		}

		/**
		 * Action handler for Gravity Forms: gform_get_form_filter_{$form_id}
		 *
		 * @param string $form_string Form HTML
		 * @param array $form GF Form array
		 * @return string Form HTML
		 */
		private function _gf_get_form_filter( $form_string, $form ) {
			return $form_string;
		}

		/**
		 * Action handler for Gravity Forms: gform_field_validation_{$form_id}_{$field_id}
		 *
		 * @param array $validation_result GF validation result
		 * @param mixed $value Value submitted
		 * @param array $form GF Form array
		 * @param array $field GF Form Field array
		 * @return array GF validation result
		 */
		private function _gf_field_validation( $validation_result, $value, $form, $field ) {
			if ( !$validation_result[ 'is_valid' ] ) {
				return $validation_result;
			}

			$action = $this->actions[ $this->action ];

			$field_options = array();

			if ( isset( $action[ 'fields' ][ (string) $field[ 'id' ] ] ) ) {
				$field_options = $action[ 'fields' ][ (string) $field[ 'id' ] ];
			}
			elseif ( isset( $action[ 'fields' ][ (int) $field[ 'id' ] ] ) ) {
				$field_options = $action[ 'fields' ][ (int) $field[ 'id' ] ];
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

			// @todo replace callbacks with apply_filters or do_action
			if ( isset( $field_options[ 'validation_callback' ] ) && is_callable( $field_options[ 'validation_callback' ] ) ) {
				$validate = call_user_func( $field_options[ 'validation_callback' ], $validate, $field[ 'id' ], $field_options, $value, $form, $field, $this );
			}

			if ( false === $validate )
				$validate = 'There was an issue validating the field ' . $field[ 'label' ];
			elseif ( true !== $validate )
				$validate = (array) $validate;

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
		 * @return array GF Validation result
		 */
		private function _gf_validation( $validation_result ) {
			if ( !$validation_result[ 'is_valid' ] ) {
				return $validation_result;
			}

			$form = $validation_result[ 'form' ];

			$field_keys = array();

			foreach ( $form[ 'fields' ] as $k => $field ) {
				$field_keys[ (string) $field[ 'id' ] ] = $k;
			}

			$action = $this->actions[ $this->action ];

			$save_action = $this->action;

			if ( isset( $action[ 'save_action' ] ) ) {
				$save_action = $action[ 'save_action' ];
			}

			if ( !in_array( $save_action, array( 'add', 'save' ) ) ) {
				$save_action = 'add';
			}

			$data = $this->_gf_to_pods( $form, $action );

			try {
				// Setup object
				$this->id = call_user_func( array( $this->pod, $save_action ), $data );
				$this->pod->fetch( $this->id );
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
		 * @param $validation_message
		 * @param $form
		 * @return null
		 */
		private function _gf_validation_message( $validation_message, $form ) {
			if ( !empty( $this->gf_validation_message ) )
				$validation_message = $this->gf_validation_message;

			return $validation_message;
		}

		/**
		 * Action handler for Gravity Forms: gform_after_submission_{$form_id}
		 *
		 * @param array $entry GF Entry array
		 * @param array $form GF Form array
		 */
		private function _gf_after_submission( $entry, $form ) {
			if ( !empty( $this->gf_validation_message ) ) {
				// Send notifications
				$this->_gf_notifications( $entry, $form );

				$confirmation = GFFormDisplay::handle_confirmation( $form, $entry );

				if ( 'redirect' != $form[ 'confirmation' ][ 'type' ] || !is_array( $confirmation ) || !isset( $confirmation[ 'redirect' ] ) ) {
					pods_redirect( pods_var_update( array( 'action' => 'edit', 'id' => $this->id ) ) );
				}
				else {
					pods_redirect( $confirmation[ 'redirect' ] );
				}

				if ( isset( $action[ 'auto_delete' ] ) && $action[ 'auto_delete' ] ) {
					$keep_files = false;

					if ( isset( $action[ 'keep_files' ] ) && $action[ 'keep_files' ] ) {
						$keep_files = true;
					}

					pods_gf();

					Pods_GravityForms::delete_entry( $entry, $keep_files );
				}
			}
		}

		/**
		 * Map GF form fields to Pods fields
		 *
		 * @param array $form GF Form array
		 * @param array|null $action Action array
		 * @return array Data array for saving
		 */
		private function _gf_to_pods( $form, $action = null ) {
			if ( !is_array( $action ) ) {
				if ( isset( $this->actions[ $action ] ) ) {
					$action = $this->actions[ $action ];
				}
				else {
					$action = $this->actions[ $this->action ];
				}
			}

			$data = array();

			foreach ( $action[ 'fields' ] as $field => $field_options ) {
				$field = (string) $field;

				if ( !is_array( $field_options ) ) {
					$field_options = array(
						'field' => $field_options
					);
				}

				// get value from
				$value = pods_var_raw( 'input_' . $field, 'post' );

				if ( isset( $field_options[ 'callback' ] ) && is_callable( $field_options[ 'callback' ] ) ) {
					$field_data = array();

					if ( isset( $field_keys[ $field ] ) ) {
						$field_data = $form[ 'fields' ][ $field_keys[ $field ] ];
					}

					// @todo replace callbacks with apply_filters or do_action
					$value = call_user_func( $field_options[ 'callback' ], $value, $field, $field_options, $form, $field_data, $data, $this );
				}

				if ( null !== $value ) {
					$data[ $field_options[ 'field' ] ] = $value;
				}
			}

			return $data;
		}

		/**
		 * Send notifications based on config
		 *
		 * @param array $entry GF Entry array
		 * @param array $form GF Form array
		 * @return bool Whether the notifications were sent
		 */
		private function _gf_notifications( $entry, $form ) {
			// handle notifications access matrix and templates
			if ( isset( $this->notifications[ $this->action ] ) && !empty( $this->notifications[ $this->action ] ) ) {
				foreach ( $this->notifications[ $this->action ] as $template => $template_options ) {
					$template_options = (array) $template_options;

					if ( isset( $template_options[ 'template' ] ) ) {
						$template = $template_options[ 'template' ];
					}

					$template_data = apply_filters( 'pods_gf_template_object', null, $template, $template_options, $entry, $form, $this );

					$template_obj = null;

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

						$email_template = apply_filters( 'pods_gf_template_email', $email_template, $entry, $form, $this );

						if ( empty( $email_template ) ) {
							continue;
						}

						wp_mail( $email_template[ 'to' ], $email_template[ 'subject' ], $email_template[ 'content' ], $email_template[ 'headers' ], $email_template[ 'attachments' ] );
					}
				}

				return true;
			}

			return false;
		}
	}
}