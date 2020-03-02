<?php
/**
 * Class Pods_GF_UI
 */
class Pods_GF_UI {

	/**
	 * @var Pods_GF
	 */
	public static $pods_gf;

	/**
	 * @var PodsUI
	 */
	public static $pods_ui;

	/**
	 * @var Pods|array Pods object or an array of GF entries
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
			'export'
		),
		'fields' => array(),
		'restrict' => array()
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
				'heading' => 'Title used as action on action page',
				'header' => 'Title used on action page',

				'fields' => array(
					'1' => 'pod_field_name', // GF field ID or $_POST name, mapped to a pod field
					'2' => array(
						'field' => 'pod_field_name'
					),
					// All fields processed, even if not found/set in GF/$_POST
					'_faux' => array(
						'field' => 'pod_field_name',
						'value' => 'Manual value to use'
					)
				),
				'pod' => 'mypod', // Can be used on other actions to override the pod used too

				'save_action' => 'add', // What action to use when saving (add|save)
				'save_id' => 123, // ID to override what to save to (if save action is save)

				'callback' => null, // Function to callback on action page
				'access_callback' => null, // Access callback to override access rights

				'disabled' => false, // Whether action is disabled

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
						'default' => 14, // Default value to select
						'params' => array(), // Params to use for find()
					)
				),

				'field' => 'status', // Pod field name for action to interact with (used with Status action)
				'data' => array(), // Array of options available for field (used with Status action)

				'prepopulate' => false, // Disable value prepopulate, defaults to true

				// Redirect after submission (confirmation redirect, or action=edit&id={entry_id})
				'redirect_after' => false, // Disable redirection after submission (defaults to false, true for 'edit')

				// Add a button to Save for Later the form being submitted, to come back to
				'save_for_later' => true, // simple
				'save_for_later' => array(
					'redirect' => '/custom-url-to-redirect-to-on-save/', // Redirect to URL on Save for Later
					'exclude_pages' => array( 1 ) // Exclude Pages from showing button
				),

				// Remember values when they are submitted and prepopulate on next form load
				'remember' => true, // simple
				'remember' => array(
					'fields' => array(
						'1',
						'5'
					)
				),

				// Make inputs read only
				'read_only' => true, // simple
				'read_only' => array(
					'fields' => array(
						'1',
						'5'
					)
				),

				// Automatically delete the GF entry created, defaults to false
				'auto_delete' => true,

				// Enable Markdown syntax for GF HTML fields, defaults to false
				'markdown' => true,

				// Enable editing leads instead of inserting them
				'edit' => true,

				// Customize the submit text of a form submit button on the fly
				'submit_button' => 'http://mysite.com/my/site/my-image.png', // simple button image url
				'submit_button' => 'Time to Submit', // simple button text
				'submit_button' => array(
					'imageUrl' => '/my/site/my-image.png', // use image url for button

					'text' => 'Submit!' // use text for button
				),

				// Add a secondary submit action to the form
				'secondary_submits' => array(
					'imageUrl' => '/my/site/my-image.png', // use image url for button

					'text' => 'Submit!', // use text for button

					'action' => 'secondary-action', // name of button: pods_gf_ui_action_{$action}

					'value' => 'custom-value', // custom value of button (default is 1)

					'value_from_ui' => 'next_id' // get value from PodsUI object (next_id|prev_id)
				),
				// Multiple secondary submit buttons
				'secondary_submits' => array(
					array(
						'imageUrl' => '/my/site/my-image.png', // use image url for button

						'text' => 'Submit!', // use text for button

						'action' => 'secondary-action', // name of button: pods_gf_ui_action_{$action}

						'value' => 'custom-value', // custom value of button (default is 1)

						'value_from_ui' => 'prev_id' // get value from PodsUI object (next_id|prev_id)
					),
					array(
						'imageUrl' => '/my/site/my-image2.png', // use image url for button

						'text' => 'Submit2!', // use text for button

						'action' => 'secondary-action2', // name of button: pods_gf_ui_action_{$action}

						'value' => 'custom-value2', // custom value of button (default is 1)

						'value_from_ui' => 'next_id' // get value from PodsUI object (next_id|prev_id)
					)
				)

				// Customize the submit redirect URL of a form on the fly
				'confirmation' => 'http://mysite.com/my/site/?id={entry_id}', // simple redirect
				'confirmation' => '/my/site/?id={entry_id}', // simple redirect (relative site)
				'confirmation' => '?id={entry_id}', // simple redirect (relative to path)
				'confirmation' => 'Totally Awesome, Great Job!', // simple text
				'confirmation' => array(
					'url' => '/my/site/?id={entry_id}', // redirect to a url

					'message' => 'Thanks!' // show a message
				)
			),
		 */

		'manage' => array( // table ui manage
			'form' => 0,
			'fields' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => false,
			'prepopulate' => true
		),
		'add' => array( // form
			'form' => 0,
			'fields' => array(),
			'dynamic_select' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => false,
			'prepopulate' => true
		),
		'edit' => array( // alternate form or original form (pre-populate data)
			'form' => 0,
			'fields' => array(),
			'dynamic_select' => array(),
			'callback' => null,
			'access_callback' => null,
			'edit' => true,
			'disabled' => false,
			'redirect_after' => true,
			'prepopulate' => true
		),
		'status' => array( // switching status, can define field name (default 'status')
			'label' => 'Change Status',
			'form' => 0,
			'field' => 'status',
			'data' => array(), // what stati to use (dynamically build, based on pod/field)
			'fields' => array(),
			'dynamic_select' => array(),
			'callback' => null,
			'access_callback' => null,
			'edit' => true,
			'disabled' => true
		),
		'view' => array( // view details
			'label' => 'View Details',
			'fields' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => true,
			'read_only' => true,
			'prepopulate' => true
		),
		'delete' => array(
			'callback' => null,
			'access_callback' => null,
			'disabled' => true,
			'keep_files' => false
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
				'params' => array(

				),
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
	 * Setup Pods_GF_UI object
	 *
	 * @param array $options Pods_GF_UI option overrides
	 */
	public function __construct( $options ) {

		$this->init_ui();

		if ( is_array( $options ) && !empty( $options ) ) {
			foreach ( $options as $option => $value ) {
				if ( isset( $this->{$option} ) && is_array( $this->{$option} ) && is_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						if ( isset( $this->{$option}[ $k ] ) && 'ui' != $option ) {
							$this->{$option}[ $k ] = array_merge( $this->{$option}[ $k ], $v );
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

		$this->setup_ui();

		foreach ( $this->actions as $action => $action_data ) {
			$form_id = (int) pods_v( 'form', $action_data );

			if ( !pods_v( 'disabled', $action_data ) && 0 < $form_id && $this->action == $action ) {
				$pod = pods_v( 'pod', $action_data, $this->pod );

				$pods_gf = pods_gf( $pod, $form_id, $action_data );

				self::$pods_gf = $pods_gf;

				break;
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

			if ( isset( $this->actions[ $action ][ 'form_id' ] ) ) {
				$this->actions[ $action ][ 'form' ] = $this->actions[ $action ][ 'form_id' ];
			}
		}

		$this->actions[ 'add' ][ 'callback' ] = array( $this, '_action_add' );
		$this->actions[ 'edit' ][ 'callback' ] = array( $this, '_action_edit' );
		$this->actions[ 'view' ][ 'callback' ] = array( $this, '_action_view' );
		$this->actions[ 'delete' ][ 'callback' ] = array( $this, '_action_delete' );

		$this->action = pods_v( 'action', 'get', $this->action, true );

	}

	/**
	 * Setup UI from options
	 */
	private function setup_ui() {

		$defaults = array(
			'heading'         => '',
			'header'          => '',
			'label'           => '',
			'label_alt'       => '',
			'form'            => 0,
			'edit'            => false,
			'fields'          => array(),
			'dynamic_select'  => array(),
			'callback'        => null,
			'callback_copy'   => null,
			'access_callback' => null,
			'content'         => null,
			'action_data'     => array(),
			'disabled'        => false,
			'prepopulate'     => true,
			'save_for_later'  => array(),
		);

		$id = (int) pods_v( 'id' );

		if ( 0 < $this->id ) {
			$id = $this->id;
		}

		foreach ( $this->actions as $action => $options ) {
			$this->actions[ $action ] = $options = array_merge( $defaults, $options );

			if ( !empty( $options[ 'heading' ] ) && empty( $this->ui[ 'heading' ][ $action ] ) ) {
				$this->ui[ 'heading' ][ $action ] = $options[ 'heading' ];
			}

			if ( !empty( $options[ 'header' ] ) && empty( $this->ui[ 'header' ][ $action ] ) ) {
				$this->ui[ 'header' ][ $action ] = $options[ 'header' ];
			}

			if ( !empty( $options[ 'label' ] ) ) {
				$this->ui[ 'label' ][ $action ] = $options[ 'label' ];

				if ( empty( $this->ui[ 'heading' ][ $action ] ) ) {
					$this->ui[ 'heading' ][ $action ] = $options[ 'label' ];
				}

				if ( empty( $this->ui[ 'header' ][ $action ] ) ) {
					$this->ui[ 'header' ][ $action ] = $options[ 'label' ];
				}

				if ( 'add' == $action ) {
					$this->ui[ 'label' ][ 'add_new' ] = $options[ 'label' ];
				}
			}
			elseif ( 'add' == $action && !empty( $options[ 'label_alt' ] ) ) {
				$this->ui[ 'label' ][ 'add_new' ] = $options[ 'label_alt' ];
			}

			if ( !empty( $options[ 'callback' ] ) ) {
				if ( in_array( $action, array( 'add', 'edit' ) ) ) {
					$this->ui[ 'actions_custom' ][ $action ] = array(
						'callback' => $options[ 'callback' ],
					);
				}
				else {
					$this->ui[ 'actions_custom' ][ $action ] = array(
						'callback' => $options[ 'callback' ],
					);
				}
			}
			elseif ( !empty( $options[ 'callback_copy' ] ) ) {
				if ( is_array( $this->ui[ 'actions_custom' ][ $options[ 'callback_copy' ] ] ) && isset( $this->ui[ 'actions_custom' ][ $options[ 'callback_copy' ] ][ 'callback' ] ) ) {
					$this->ui[ 'actions_custom' ][ $action ] = array(
						'callback' => $this->ui[ 'actions_custom' ][ $options[ 'callback_copy' ] ][ 'callback' ],
					);
				}
				else {
					$this->ui[ 'actions_custom' ][ $action ] = array(
						'callback' => $this->ui[ 'actions_custom' ][ $options[ 'callback_copy' ] ],
					);
				}
			} elseif ( ( ! empty( $options['form'] ) || ! empty( $options['content'] ) ) && 'manage' !== $action ) {
				$this->ui[ 'actions_custom' ][ $action ] = array(
					'callback' => array( $this, '_action_custom' ),
				);
			}
			else {
				$this->ui['actions_custom'][ $action ] = array(
					'label' => $action,
				);
			}

			if ( ! empty( $options['content'] ) ) {
				$this->ui[ 'actions_custom' ][ $action ]['content'] = $options['content'];
			}

			if ( !empty( $options[ 'action_data' ] ) ) {
				$this->ui[ 'actions_custom' ][ $action ] = array_merge( $this->ui[ 'actions_custom' ][ $action ], (array) pods_v( 'action_data', $options, null, true ) );
			}

			if ( !empty( $options[ 'action_link' ] ) ) {
				$this->ui[ 'action_links' ][ $action ] = pods_v( 'action_link', $options, null, true );
			}

			if ( !empty( $options[ 'fields' ] ) ) {
				$this->ui[ 'fields' ][ $action ] = $options[ 'fields' ];
			}
		}

		if ( 0 < $this->actions[ 'manage' ][ 'form' ] ) {
			$this->pod = array();

			$total_found = 0;

			if ( 0 < $id ) {
				$lead = GFAPI::get_entry( $id );

				if ( !empty( $lead ) && ! is_wp_error( $lead ) ) {
					$this->pod = array(
						$lead[ 'id' ] => $lead
					);

					// @todo Replace the below code when GF adds functionality to get all lead meta
					global $wpdb, $_gform_lead_meta;

					$gf_meta_table = pods_gf_get_gf_table_name( 'entry_meta' );

					$old_schema = version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' );

					$lead_id_column_name = $old_schema ? 'lead_id' : 'entry_id';

					$gf_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$gf_meta_table} WHERE {$lead_id_column_name} = %d", $lead[ 'id' ] ) );

					foreach ( $gf_meta as $gf_meta_value ) {
						$meta_value = maybe_unserialize( $gf_meta_value->meta_value );

						$cache_key = $lead[ 'id' ] . "_" . $gf_meta_value->meta_key;

						$_gform_lead_meta[ $cache_key ] = $meta_value;

						$this->pod[ $lead[ 'id' ] ][ $gf_meta_value->meta_key ] = $meta_value;
					}

					$this->id = $id;
				}

				$total_found = count( $this->pod );
			}
			else {
				$search_criteria = apply_filters( 'pods_gf_ui_search_criteria', array() );
				$sorting = apply_filters( 'pods_gf_ui_sorting', null );
				$paging = array(
					'offset' => 0,
					'page_size' => 20
				);

				$page = (int) pods_v( 'pg', 'get', 1, true );
				$offset = ( ( $page - 1 ) * $paging[ 'page_size' ] );

				$paging[ 'offset' ] += $offset;

				$leads = GFAPI::get_entries( $this->actions[ 'manage' ][ 'form' ], $search_criteria, $sorting, $paging, $total_found );

				// @todo Hook into save for later data and display saved entries in the list like normal entries

				foreach ( $leads as $lead ) {
					$this->pod[ $lead[ 'id' ] ] = $lead;

					// @todo Replace the below code when GF adds functionality to get all lead meta
					global $wpdb, $_gform_lead_meta;

					$gf_meta_table = pods_gf_get_gf_table_name( 'entry_meta' );

					$old_schema = version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' );

					$lead_id_column_name = $old_schema ? 'lead_id' : 'entry_id';

					$gf_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$gf_meta_table} WHERE {$lead_id_column_name} = %d", $lead[ 'id' ] ) );

					foreach ( $gf_meta as $gf_meta_value ) {
						$meta_value = maybe_unserialize( $gf_meta_value->meta_value );

						$cache_key = $lead[ 'id' ] . "_" . $gf_meta_value->meta_key;

						$_gform_lead_meta[ $cache_key ] = $meta_value;

						$this->pod[ $lead[ 'id' ] ][ $gf_meta_value->meta_key ] = $meta_value;
					}
				}
			}

			$this->pod = apply_filters( 'pods_gf_ui_leads', $this->pod, $this->action, $this );

			$default_ui = array(
				'data' => $this->pod,
				'total' => count( $this->pod ),
				'total_found' => $total_found,
				'searchable' => false,
				'sortable' => false,
				'pagination' => true
			);

			if ( 0 < $this->id && !empty( $this->pod ) ) {
				$this->pod = current( $this->pod );
			}

			if ( empty( $this->ui[ 'fields' ][ 'manage' ] ) ) {
				$this->ui[ 'fields' ][ 'manage' ] = array(
					'id' => array(
						'label' => 'ID',
						'type' => 'number'
					),
					'created_by' => array(
						'label' => 'Submitter',
						'type' => 'pick',
						'pick_val' => 'user'
					),
					'date_created' => array(
						'label' => 'Date Created',
						'type' => 'datetime'
					)
				);
			}

			$this->ui = array_merge( $default_ui, $this->ui );
		}

		if ( is_array( $this->pod ) ) {
			$default_ui = array(
				'data' => $this->pod,
				'total' => count( $this->pod ),
				'total_found' => count( $this->pod ),
				'searchable' => false,
				'sortable' => false,
				'pagination' => false
			);

			$this->ui = array_merge( $default_ui, $this->ui );
		}
		elseif ( !is_object( $this->pod ) && !empty( $this->pod ) ) {
			$this->pod = pods( $this->pod, ( 0 < $id ? $id : null ) );
			$this->id  = $this->pod->id();
		}

		if ( 0 < $this->id ) {
			$_GET[ 'id' ] = $this->id;
		}

		foreach ( $this->actions as $action => $options ) {
			if ( false === pods_v( 'disabled', $this->actions[ $action ], false, true ) ) {
				if ( in_array( $action, $this->ui[ 'actions_disabled' ] ) ) {
					unset( $this->ui[ 'actions_disabled' ][ array_search( $action, $this->ui[ 'actions_disabled' ] ) ] );
				}
			}
			elseif ( !in_array( $action, $this->ui[ 'actions_disabled' ] ) ) {
				$this->ui[ 'actions_disabled' ][] = $action;
			}
		}

		if ( !isset( $this->actions[ $this->action ] ) || !$this->access( $this->action ) ) {
			$this->action = 'manage';
		}

		$_GET[ 'action' ] = $this->action;
		$this->ui[ 'action' ] = $this->action;

	}

	/**
	 * Handle current action
	 *
	 * @param array $args
	 *
	 * @return bool|mixed|PodsUI
	 */
	public function action( $args = array() ) {

		$ui = false;

		if ( isset( $GLOBALS[ 'pods-gf-ui-off' ] ) && $GLOBALS[ 'pods-gf-ui-off' ] ) {
			return $ui;
		}

		$GLOBALS[ 'pods-gf-ui-off' ] = true;

		$args = array_merge(
			array(
				'action' => $this->action
			),
			$args
		);

		$action = $args[ 'action' ];

		if ( isset( $this->actions[ $action ] ) ) {
			// Pods object
			// $this->pod = pods( 'pod_name' );
			if ( is_object( $this->pod ) ) {
				$ui = $this->pod->ui( $this->ui, true );
			}
			// An array of GF entries $lead_id => $entry
			// $this->pod = array( .... );
			elseif ( is_array( $this->pod ) ) {
				$ui = pods_ui( $this->ui );
			}
			else {
				do_action( 'pods_gf_ui_action_' . $action, $this, $args );
			}
		}

		$GLOBALS[ 'pods-gf-ui-off' ] = false;

		return $ui;

	}

	/**
	 * Run UI, just shorthand for action()
	 *
	 * @param array $args
	 *
	 * @return bool|mixed|PodsUI
	 * @see action
	 */
	public function ui( $args = array() ) {

		return $this->action( $args );

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
		if ( true === pods_v( 'disabled', $this->actions[ $action ], false, true ) || in_array( $action, $this->ui[ 'actions_disabled' ] ) ) {
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
					$constraints[ $constraint ] = array(
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
		$access_callback = pods_v( 'access_callback', $this->actions[ $action ], null, true );

		if ( null !== $access_callback && is_callable( $access_callback ) ) {
			$access = call_user_func( $access_callback, $access, $this );
		}

		return (boolean) $access;

	}

	/**
	 * Handle access constraints
	 *
	 * @param array $constraints Constraints arrays
	 *
	 * @return bool Whether the constraint rules all passed
	 */
	public function access_constraints( $constraints ) {

		$access = true;

		if ( is_array( $constraints ) && !empty( $constraints ) && is_object( $this->pod ) ) {
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
	 *
	 * @param PodsUI $obj
	 */
	public function _action_add( $obj ) {

		self::$pods_ui =& $obj;

		if ( $obj->restricted( $obj->action ) ) {
			return;
		}
?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo $obj->header[ 'add' ];

			if ( !in_array( 'manage', $obj->actions_disabled ) && !in_array( 'manage', $obj->actions_hidden ) && ! $obj->restricted( 'manage' ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );

				if ( !empty( $obj->action_links[ 'manage' ] ) ) {
					$link = $obj->action_links[ 'manage' ];
				}
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php echo sprintf( __( 'Back to %s', 'pods-gravity-forms' ), $obj->heading[ 'manage' ] ); ?></a>
		<?php
			}
		?>
	</h2>

	<?php
		do_action( 'pods_gf_ui' . __FUNCTION__ . '_pre', $this->pod, $obj, $this );

		if ( isset( $this->actions[ $this->action ][ 'form' ] ) && 0 < $this->actions[ $this->action ][ 'form' ] ) {
			gravity_form_enqueue_scripts( $this->actions[ $this->action ][ 'form' ] );

			wp_print_scripts();
			wp_print_styles();

			gravity_form( $this->actions[ $this->action ][ 'form' ], false, false );
		}
		elseif ( is_object( $this->pod ) ) {
			$this->pod->form();
		}
		else {
			do_action( 'pods_gf_ui' . __FUNCTION__ . '_form', $this->pod, $obj, $this );
		}

		do_action( 'pods_gf_ui' . __FUNCTION__ . '_post', $this->pod, $obj, $this );
	?>
</div>
<?php

	}

	/**
	 * Embed Edit form
	 *
	 * @param bool $duplicate
	 * @param PodsUI $obj
	 */
	public function _action_edit( $duplicate, $obj = null  ) {

		if ( is_object( $duplicate ) ) {
			$obj = $duplicate;
			$duplicate = false;
		}

		// Hackarounds because of current state of callback variable usage
		$duplicate = false;

		self::$pods_ui =& $obj;

        if ( empty( $obj->row ) ) {
            $obj->get_row();
		}

		if ( empty( $obj->row ) ) {
			$obj->message( sprintf( __( '%s not found.', 'pods-gravity-forms' ), $obj->item ) );

			return;
		}

		if ( $obj->restricted( $obj->action, $obj->row ) ) {
			return $obj->error( sprintf( __( '<strong>Error:</strong> You do not have access to this %s.', 'pods' ), $obj->item ) );
		}
?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo $obj->do_template( $duplicate ? $obj->header[ 'duplicate' ] : $obj->header[ $obj->action ] );

			if ( !in_array( 'manage', $obj->actions_disabled ) && !in_array( 'manage', $obj->actions_hidden ) && ! $obj->restricted( 'manage' ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );

				if ( !empty( $obj->action_links[ 'manage' ] ) ) {
					$link = $obj->action_links[ 'manage' ];
				}
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php echo sprintf( __( 'Back to %s', 'pods-gravity-forms' ), $obj->heading[ 'manage' ] ); ?></a>
		<?php
			}
		?>
	</h2>

	<?php
		do_action( 'pods_gf_ui' . __FUNCTION__ . '_pre', $this->pod, $obj, $this );

		if ( isset( $this->actions[ $this->action ][ 'form' ] ) && 0 < $this->actions[ $this->action ][ 'form' ] ) {
			gravity_form_enqueue_scripts( $this->actions[ $this->action ][ 'form' ] );

			gravity_form( $this->actions[ $this->action ][ 'form' ], false, false );
		}
		elseif ( is_object( $this->pod ) ) {
			$this->pod->form();
		}
		else {
			do_action( 'pods_gf_ui' . __FUNCTION__ . '_form', $this->pod, $duplicate, $obj, $this );
		}

		do_action( 'pods_gf_ui' . __FUNCTION__ . '_post', $this->pod, $obj, $this );
	?>
</div>
<?php

	}

	/**
	 * Output all fields
	 *
	 * @param PodsUI $obj
	 */
	public function _action_view( $obj = null ) {

		self::$pods_ui =& $obj;

		$fields = pods_v( 'fields', $this->actions[ $this->action ] );

        if ( empty( $obj->row ) ) {
            $obj->get_row();
		}

		if ( empty( $obj->row ) ) {
			$obj->message( sprintf( __( '%s not found.', 'pods-gravity-forms' ), $obj->item ) );

			return;
		}

		if ( $obj->restricted( $obj->action, $obj->row ) ) {
			return;
		}
?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo $obj->do_template( $obj->header[ $obj->action ] );

			if ( !in_array( 'manage', $obj->actions_disabled ) && !in_array( 'manage', $obj->actions_hidden ) && ! $obj->restricted( 'manage' ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );

				if ( !empty( $obj->action_links[ 'manage' ] ) ) {
					$link = $obj->action_links[ 'manage' ];
				}
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php echo sprintf( __( 'Back to %s', 'pods-gravity-forms' ), $obj->heading[ 'manage' ] ); ?></a>
		<?php
			}
		?>
	</h2>

	<?php
		do_action( 'pods_gf_ui' . __FUNCTION__ . '_pre', $this->pod, $obj, $this );

		if ( isset( $this->actions[ $this->action ][ 'form' ] ) && 0 < $this->actions[ $this->action ][ 'form' ] ) {
			gravity_form_enqueue_scripts( $this->actions[ $this->action ][ 'form' ] );

			gravity_form( $this->actions[ $this->action ][ 'form' ], false, false );
		}
		elseif ( is_object( $this->pod ) ) {
			echo $this->pod->view( $fields );
		}
		else {
			do_action( 'pods_gf_ui' . __FUNCTION__, $this->pod, $obj, $this );
		}

		do_action( 'pods_gf_ui' . __FUNCTION__ . '_post', $this->pod, $obj, $this );
	?>
</div>
<?php

	}

	/**
	 * Handle delete
	 *
	 * @param PodsUI $obj
	 */
	public function _action_delete( $id, $obj = null ) {

		self::$pods_ui =& $obj;

		if ( $obj->restricted( $obj->action, $obj->row ) ) {
			return;
		}

		if ( is_object( $this->pod ) ) {
			return; // continue as normal
		}

		if ( is_array( $this->pod ) ) {
			pods_gf();

			Pods_GF::gf_delete_entry( $id, $this->actions[ 'delete' ][ 'keep_files' ] );

			unset( $obj->data[ $id ] );

			$obj->total = count( $obj->data );
			$obj->total_found = count( $obj->data );
		}
		else {
			do_action( 'pods_gf_ui' . __FUNCTION__, $this->id, $this->pod, $obj, $this );
		}

		$obj->message( sprintf( __( '%s deleted successfully.', 'pods-gravity-forms' ), $obj->item ) );

		return null;

	}

	/**
	 * Embed Custom form
	 *
	 * @param PodsUI $obj
	 */
	public function _action_custom( $obj ) {

		self::$pods_ui =& $obj;

		if ( $obj->restricted( $obj->action, $obj->row ) ) {
			return;
		}
?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo $obj->header[ $this->action ];

			if ( !in_array( 'manage', $obj->actions_disabled ) && !in_array( 'manage', $obj->actions_hidden ) && ! $obj->restricted( 'manage' ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );

				if ( !empty( $obj->action_links[ 'manage' ] ) ) {
					$link = $obj->action_links[ 'manage' ];
				}
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php echo sprintf( __( 'Back to %s', 'pods-gravity-forms' ), $obj->heading[ 'manage' ] ); ?></a>
		<?php
			}
		?>
	</h2>

	<?php
		do_action( 'pods_gf_ui' . __FUNCTION__ . '_pre', $this->pod, $obj, $this );

		if ( ! empty( $this->actions[ $this->action ]['content'] ) ) {
			/**
			 * Filter content for page
			 *
			 * @param string     $content
			 * @param Pods_GF_UI $pods_gf_ui
			 */
			$content = apply_filters( 'pods_gf_ui_content', $this->actions[ $this->action ]['content'], $this );
			$content = apply_filters( 'the_content', $content, 0 );

			echo $content;
		} elseif ( isset( $this->actions[ $this->action ]['form'] ) && 0 < $this->actions[ $this->action ]['form'] ) {
			gravity_form_enqueue_scripts( $this->actions[ $this->action ]['form'] );

			wp_print_scripts();
			wp_print_styles();

			gravity_form( $this->actions[ $this->action ]['form'], false, false );
		} elseif ( is_object( $this->pod ) ) {
			$this->pod->form();
		} else {
			do_action( 'pods_gf_ui' . __FUNCTION__ . '_form', $this->pod, $obj, $this );
		}

		do_action( 'pods_gf_ui' . __FUNCTION__ . '_post', $this->pod, $obj, $this );
	?>
</div>
<?php

	}

}