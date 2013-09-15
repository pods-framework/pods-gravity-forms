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
			'delete',
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
					'1' => 'pod_field_name' // GF field ID or $_POST name, mapped to a pod field
					'2' => array(
						'field' => 'pod_field_name'
					)
					// All fields processed, even if not found/set in GF/$_POST
					'_faux' => array(
						'field' => 'pod_field_name'
						'value' => 'Manual value to use'
					)
				),

				'save_action' => 'add', // What action to use when saving (add|save)
				'save_id' => 123, // ID to override what to save to (if save action is save)

				'callback' => null, // Function to callback on action page
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
			'form' => 0,
			'fields' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => false
		),
		'add' => array( // form
			'form' => 0,
			'fields' => array(),
			'dynamic_select' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => false,
			'prepopulate' => false,
			'redirect_after' => false
		),
		'edit' => array( // alternate form or original form (pre-populate data)
			'form' => 0,
			'fields' => array(),
			'dynamic_select' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => false
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
			'disabled' => true
		),
		'view' => array( // view details
			'label' => 'View Details',
			'fields' => array(),
			'callback' => null,
			'access_callback' => null,
			'disabled' => true
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

		$forms = array(
			'add',
			'edit'
		);

		foreach ( $forms as $form ) {
			if ( isset( $this->actions[ $form ] ) && !$this->actions[ $form ][ 'disabled' ] && 0 < (int) pods_var( 'form', $this->actions[ $form ] ) ) {
				$form_id = (int) pods_var( 'form', $this->actions[ $form ] );

				$pods_gf = pods_gf( $this->pod, $form_id, $this->actions[ $form ] );

				if ( $this->action == $form ) {
					self::$pods_gf = $pods_gf;
				}
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
			if ( null !== pods_var_raw( 'heading', $options, null, null, true ) ) {
				$this->ui[ 'heading' ][ $action ] = $options[ 'heading' ];
			}

			if ( null !== pods_var_raw( 'header', $options, null, null, true ) ) {
				$this->ui[ 'header' ][ $action ] = $options[ 'header' ];
			}

			if ( null !== pods_var_raw( 'label', $options, null, null, true ) ) {
				$this->ui[ 'label' ][ $action ] = $options[ 'label' ];

				if ( 'add' == $action ) {
					$this->ui[ 'label' ][ 'add_new' ] = $options[ 'label' ];
				}
			}
			elseif ( 'add' == $action && null !== pods_var_raw( 'label_alt', $options, null, null, true ) ) {
				$this->ui[ 'label' ][ 'add_new' ] = $options[ 'label_alt' ];
			}

			if ( null !== pods_var_raw( 'callback', $options, null, null, true ) ) {
				$this->ui[ 'actions_custom' ][ $action ] = $options[ 'callback' ];
			}

			if ( array() !== pods_var_raw( 'fields', $options, array(), null, true ) ) {
				$this->ui[ 'fields' ][ $action ] = $options[ 'fields' ];
			}
		}

		if ( 0 < $this->actions[ 'manage' ][ 'form' ] ) {
			$leads = RGFormsModel::get_leads( $this->actions[ 'manage' ][ 'form' ], 0, 'DESC', '', 0, 999 );

			$this->pod = array();

			foreach ( $leads as $lead ) {
				$this->pod[ $lead[ 'id' ] ] = RGFormsModel::get_lead( $lead[ 'id' ] );
			}

			$default_ui = array(
				'data' => $this->pod,
				'total' => count( $this->pod ),
				'total_found' => count( $this->pod ),
				'search' => false,
				'searchable' => false,
				'sortable' => false,
				'pagination' => false
			);

			$id = (int) pods_var( 'id' );

			if ( 0 < $id && isset( $this->pod[ $id ] ) ) {
				$this->pod = $this->pod[ $id ];
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
				'search' => false,
				'searchable' => false,
				'sortable' => false,
				'pagination' => false
			);

			$this->ui = array_merge( $default_ui, $this->ui );
		}
		elseif ( !is_object( $this->pod ) && !empty( $this->pod ) ) {
			$this->pod = pods( $this->pod, ( 0 < $this->id ? $this->id : null ) );
		}

		if ( 0 < $this->id ) {
			$_GET[ 'id' ] = $this->id;
		}

		foreach ( $this->actions as $action => $options ) {
			if ( false === pods_var_raw( 'disabled', $this->actions[ $action ], false, null, true ) ) {
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
	 * @return bool|mixed|PodsUI
	 */
	public function action() {

		$ui = false;

		if ( isset( $GLOBALS[ 'pods-gf-ui-off' ] ) && $GLOBALS[ 'pods-gf-ui-off' ] ) {
			return $ui;
		}

		$GLOBALS[ 'pods-gf-ui-off' ] = true;

		$args = func_get_args();

		if ( empty( $args ) ) {
			$args = array(
				'action' => $this->action
			);
		}

		$action = array_shift( $args );

		if ( isset( $this->actions[ $action ] ) ) {
			if ( is_object( $this->pod ) ) {
				$ui = $this->pod->ui( $this->ui, true );
			}
			elseif ( is_array( $this->pod ) ) {
				$ui = pods_ui( $this->ui );
			}
			else {
				do_action( 'pods_gf_ui_action_' . $action, $this );
			}
		}

		$GLOBALS[ 'pods-gf-ui-off' ] = false;

		return $ui;

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
		$access_callback = pods_var_raw( 'access_callback', $this->actions[ $action ], null, null, true );

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

?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo $obj->header[ 'add' ];

			$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );
		?>
		<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php _e( 'Back to', 'pods' ); ?> <?php echo $obj->heading[ 'manage' ]; ?></a>
	</h2>

	<?php
		if ( isset( $this->actions[ $this->action ][ 'form' ] ) && 0 < $this->actions[ $this->action ][ 'form' ] ) {
			gravity_form_enqueue_scripts( $this->actions[ $this->action ][ 'form' ] );

			gravity_form( $this->actions[ $this->action ][ 'form' ], false, false );
		}
		elseif ( is_object( $this->pod ) ) {
			$this->pod->form();
		}
		else {
			do_action( 'pods_gf_ui' . __FUNCTION__ . '_form', $this->pod, $obj, $this );
		}
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
	public function _action_edit( $duplicate, $obj ) {

?>
<div class="wrap pods-admin pods-ui">
	<div id="icon-edit-pages" class="icon32"<?php if ( false !== $obj->icon ) { ?> style="background-position:0 0;background-size:100%;background-image:url(<?php echo $obj->icon; ?>);"<?php } ?>><br /></div>
	<h2>
		<?php
			echo ( $duplicate ? $obj->header[ 'duplicate' ] : $obj->header[ 'edit' ] );

			if ( !in_array( 'add', $obj->actions_disabled ) && !in_array( 'add', $obj->actions_hidden ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'add', 'id' . $obj->num => '', 'do' . $obj->num = '' ), PodsUI::$allowed, $obj->exclusion() );

				if ( !empty( $obj->action_links[ 'add' ] ) )
					$link = $obj->action_links[ 'add' ];
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2"><?php echo $obj->heading[ 'add' ]; ?></a>
		<?php
			}
			elseif ( !in_array( 'manage', $obj->actions_disabled ) && !in_array( 'manage', $obj->actions_hidden ) ) {
				$link = pods_var_update( array( 'action' . $obj->num => 'manage', 'id' . $obj->num => '' ), PodsUI::$allowed, $obj->exclusion() );
		?>
			<a href="<?php echo $link; ?>" class="add-new-h2">&laquo; <?php echo sprintf( __( 'Back to %s', 'pods' ), $obj->heading[ 'manage' ] ); ?></a>
		<?php
			}
		?>
	</h2>

	<?php
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
	?>
</div>
<?php

	}

}