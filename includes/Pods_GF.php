<?php
/**
 * Class Pods_GF
 */
class Pods_GF {

	/**
	 * Instances of Pods_GF
	 *
	 * @var Pods_GF[]
	 */
	private static $instances = array();

	/**
	 * Pods object or GF entry
	 *
	 * @var Pods|array
	 */
	public $pod;

	/**
	 * Item ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Entry ID (if editing an GF entry or just saved one)
	 *
	 * @var int
	 */
	public $entry_id;

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
	 * GF Actions / Filters that have already run
	 *
	 * @var array
	 */
	public static $actioned = array();

	/**
	 * Last ID for inserted item added by GF to Pods mapping
	 *
	 * @var int[]
	 */
	public static $gf_to_pods_id = array();

	/**
	 * To keep or delete files when deleting GF entries
	 *
	 * @var bool[]
	 */
	public static $keep_files = array();

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
	 * Array of options for Secondary Submits
	 *
	 * @var array
	 */
	public static $secondary_submits = array();

	/**
	 * Array of options for Save For Later
	 *
	 * @var array
	 */
	public static $save_for_later = array();

	/**
	 * Array of options for Confirmation
	 *
	 * @var array
	 */
	public static $confirmation = array();

	/**
	 * Array of options for Remember for next time
	 *
	 * @var array
	 */
	public static $remember = array();

	/**
	 * Array of options for Read Only fields
	 *
	 * @var array
	 */
	public static $read_only = array();

	/**
	 * Get Pods_GF instance unique to $form_id
	 *
	 * @param $pod
	 * @param $form_id
	 * @param $options
	 *
	 * @return Pods_GF
	 */
	public static function get_instance( $pod, $form_id, $options ) {

		if ( ! isset( self::$instances[ $form_id ] ) ) {
			self::$instances[ $form_id ] = new self( $pod, $form_id );
		}

		self::$instances[ $form_id ]->setup_options( $options );

		return self::$instances[ $form_id ];

	}

	/**
	 * Add Pods GF integration for a specific form
	 *
	 * @param string|Pods Pod      name (or Pods object)
	 * @param int         $form_id GF Form ID
	 * @param array       $options Form options for integration
	 */
	private function __construct( $pod, $form_id ) {

		// Pod object
		if ( is_object( $pod ) ) {
			$this->pod =& $pod;
			$this->id  = $this->pod->id;
		}
		// Pod name
		elseif ( ! is_array( $pod ) ) {
			$this->pod = pods( $pod );
			$this->id  = $this->pod->id;
		}
		// GF entry
		elseif ( isset( $pod['id'] ) ) {
			$this->pod      = $pod;
			$this->id       = $pod['id'];
			$this->entry_id = $this->id;
		}

		$this->form_id = $form_id;

	}

	/**
	 * Setup options for Pods_GF
	 *
	 * @param array $options
	 */
	public function setup_options( $options ) {

		// Merge options together
		$this->options = array_merge( $this->options, $options );

		$form_id = $this->form_id;
		$options = $this->options;

		if ( ! wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

		if ( !wp_style_is( 'pods-gf', 'registered' ) ) {
			wp_register_style( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.css', array(), PODS_GF_VERSION );
		}

		// Save for Later setup
		if ( isset( $options['save_for_later'] ) && ! empty( $options['save_for_later'] ) ) {
			self::save_for_later( $form_id, $options['save_for_later'] );
		}

		if ( ! pods_v( 'admin', $options, 0 ) && ( is_admin() && RGForms::is_gravity_page() ) ) {
			if ( ! has_action( 'gform_post_update_entry_' . $form_id, array( $this, '_gf_post_update_entry' ) ) ) {
				add_action( 'gform_post_update_entry_' . $form_id, array( $this, '_gf_post_update_entry' ), 10, 2 );
				add_action( 'gform_after_update_entry_' . $form_id, array( $this, '_gf_after_update_entry' ), 10, 3 );
			}

			return;
		}

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $this, '_gf_pre_render' ), 10, 2 );
			add_filter( 'gform_pre_validation_' . $form_id, array( $this, '_gf_pre_render' ), 10, 1 );

			add_filter( 'gform_get_form_filter_' . $form_id, array( $this, '_gf_get_form_filter' ), 10, 2 );

			add_filter( 'gform_pre_submission_filter_' . $form_id, array( $this, '_gf_pre_submission_filter' ), 9, 1 );
			add_action( 'gform_after_submission_' . $form_id, array( $this, '_gf_after_submission' ), 10, 2 );
			add_action( 'gform_post_update_entry_' . $form_id, array( $this, '_gf_post_update_entry' ), 10, 2 );
			add_action( 'gform_after_update_entry_' . $form_id, array( $this, '_gf_after_update_entry' ), 10, 3 );

			// Hook into validation
			add_filter( 'gform_validation_' . $form_id, array( $this, '_gf_validation' ), 11, 1 );
			add_filter( 'gform_validation_message_' . $form_id, array( $this, '_gf_validation_message' ), 11, 2 );

			if ( isset( $options['fields'] ) && ! empty( $options['fields'] ) ) {
				foreach ( $options['fields'] as $field => $field_options ) {
					if ( is_array( $field_options ) && isset( $field_options['gf_field'] ) ) {
						$field = $field_options['gf_field'];
					}

					if ( ! has_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ) ) ) {
						add_filter( 'gform_field_validation_' . $form_id . '_' . $field, array( $this, '_gf_field_validation' ), 11, 4 );
					}
				}
			}
		}

		// Confirmation handling
		if ( isset( $options['confirmation'] ) && ! empty( $options['confirmation'] ) ) {
			self::confirmation( $form_id, $options['confirmation'] );
		}

		// Read Only handling
		if ( isset( $options['read_only'] ) && ! empty( $options['read_only'] ) ) {
			if ( ! has_filter( 'gform_pre_submission_filter_' . $form_id, array( 'Pods_GF', 'gf_read_only_pre_submission' ) ) ) {
				add_filter( 'gform_pre_submission_filter_' . $form_id, array( 'Pods_GF', 'gf_read_only_pre_submission' ), 10, 1 );
			}
		}

		// Editing
		if ( !has_filter( 'gform_entry_id_pre_save_lead' . $form_id, array( $this, '_gf_entry_pre_save_id' ) ) ) {
			add_filter( 'gform_entry_id_pre_save_lead' . $form_id, array( $this, '_gf_entry_pre_save_id' ), 10, 2 );
		}
		if ( !has_filter( 'gform_entry_id_pre_save_lead_' . $form_id, array( $this, '_gf_entry_pre_save_id' ) ) ) {
			add_filter( 'gform_entry_id_pre_save_lead_' . $form_id, array( $this, '_gf_entry_pre_save_id' ), 10, 2 );
		}

		// Saving
		if ( !has_filter( 'gform_entry_post_save', array( $this, '_gf_entry_post_save' ) ) ) {
			add_filter( 'gform_entry_post_save', array( $this, '_gf_entry_post_save' ), 10, 2 );
		}

	}

	/**
	 * Match a set of conditions, using similar syntax to WP_Query's meta_query
	 *
	 * @param array $conditions Conditions to match, using similar syntax to WP_Query's meta_query
	 * @param int   $form_id    GF Form ID
	 *
	 * @return bool Whether the conditions were met
	 */
	public static function conditions( $conditions, $form_id ) {

		$relation = 'AND';

		if ( isset( $conditions['relation'] ) && 'OR' == strtoupper( $conditions['relation'] ) ) {
			$relation = 'OR';
		}

		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		$valid = true;

		if ( isset( $conditions['field'] ) ) {
			$conditions = array( $conditions );
		}

		foreach ( $conditions as $field => $condition ) {
			if ( is_array( $condition ) && ! is_string( $field ) && ! isset( $condition['field'] ) ) {
				$condition_valid = self::conditions( $condition, $form_id );
			}
			else {
				$value = pods_v( 'input_' . $form_id . '_' . $field, 'post' );

				$condition_valid = self::condition( $condition, $value, $field );
			}

			if ( 'OR' == $relation ) {
				if ( $condition_valid ) {
					$valid = true;

					break;
				}
			}
			elseif ( ! $condition_valid ) {
				$valid = false;

				break;
			}
		}

		return $valid;

	}

	/**
	 * Match a condition, using similar syntax to WP_Query's meta_query
	 *
	 * @param array       $condition Condition to match, using similar syntax to WP_Query's meta_query
	 * @param mixed|array $value     Value to check
	 * @param null|string $field     (optional) GF Field ID
	 *
	 * @return bool Whether the condition was met
	 */
	public static function condition( $condition, $value, $field = null ) {

		$field_value   = pods_v( 'value', $condition );
		$field_check   = pods_v( 'check', $condition, 'value' );
		$field_compare = strtoupper( pods_v( 'compare', $condition, ( is_array( $field_value ? 'IN' : '=' ) ), true ) );

		// Restrict to supported checks
		$supported_checks = array(
			'value',
			'length'
		);

		$supported_checks = apply_filters( 'pods_gf_condition_supported_comparisons', $supported_checks );

		if ( ! in_array( $field_check, $supported_checks ) ) {
			$field_check = 'value';
		}

		// Restrict to supported comparisons
		if ( 'length' == $field_check ) {
			$supported_length_comparisons = array(
				'=',
				'===',
				'!=',
				'!==',
				'>',
				'>=',
				'<',
				'<=',
				'IN',
				'NOT IN',
				'BETWEEN',
				'NOT BETWEEN'
			);

			$supported_length_comparisons = apply_filters( 'pods_gf_condition_supported_length_comparisons', $supported_length_comparisons );

			if ( ! in_array( $field_compare, $supported_length_comparisons ) ) {
				$field_compare = '=';
			}
		}
		else {
			$supported_comparisons = array(
				'=',
				'===',
				'!=',
				'!==',
				'>',
				'>=',
				'<',
				'<=',
				'LIKE',
				'NOT LIKE',
				'IN',
				'NOT IN',
				'BETWEEN',
				'NOT BETWEEN',
				'EXISTS',
				'NOT EXISTS',
				'REGEXP',
				'NOT REGEXP',
				'RLIKE'
			);

			$supported_comparisons = apply_filters( 'pods_gf_condition_supported_comparisons', $supported_comparisons, $field_check );

			if ( ! in_array( $field_compare, $supported_comparisons ) ) {
				$field_compare = '=';
			}
		}

		// Restrict to supported array comparisons
		if ( is_array( $field_value ) && ! in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( in_array( $field_compare, array( '!=', 'NOT LIKE' ) ) ) {
				$field_compare = 'NOT IN';
			}
			else {
				$field_compare = 'IN';
			}
		}
		// Restrict to supported string comparisons
		elseif ( in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( ! is_array( $field_value ) ) {
				$check_value = preg_split( '/[,\s]+/', $field_value );

				if ( 1 < count( $check_value ) ) {
					$field_value = explode( ',', $check_value );
				}
				elseif ( in_array( $field_compare, array( 'NOT IN', 'NOT BETWEEN' ) ) ) {
					$field_compare = '!=';
				}
				else {
					$field_compare = '=';
				}
			}

			if ( is_array( $field_value ) ) {
				$field_value = array_filter( $field_value );
				$field_value = array_unique( $field_value );
			}
		}
		// Restrict to supported string comparisons
		elseif ( in_array( $field_compare, array( 'REGEXP', 'NOT REGEXP', 'RLIKE' ) ) ) {
			if ( is_array( $field_value ) ) {
				if ( in_array( $field_compare, array( 'REGEXP', 'RLIKE' ) ) ) {
					$field_compare = '===';
				}
				elseif ( 'NOT REGEXP' == $field_compare ) {
					$field_compare = '!==';
				}
			}
		}
		// Restrict value to null
		elseif ( in_array( $field_compare, array( 'EXISTS', 'NOT EXISTS' ) ) ) {
			$field_value = null;
		}

		// Restrict to two values, force = and != if only one value provided
		if ( in_array( $field_compare, array( 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			$field_value = array_values( array_slice( $field_value, 0, 2 ) );

			if ( 1 == count( $field_value ) ) {
				if ( 'NOT IN' == $field_compare ) {
					$field_compare = '!=';
				}
				else {
					$field_compare = '=';
				}
			}
		}

		// Empty array handling
		if ( in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) && empty( $field_value ) ) {
			$field_compare = 'EXISTS';
		}

		// Rebuild validated $condition
		$condition = array(
			'value'   => $field_value,
			'check'   => $field_check,
			'compare' => $field_compare
		);

		// Do comparisons
		$valid = false;

		if ( method_exists( get_class(), 'condition_validate_' . $condition['check'] ) ) {
			$valid = call_user_func( array( get_class(), 'condition_validate_' . $condition['check'] ), $condition, $value );
		}
		elseif ( is_callable( $condition['check'] ) ) {
			$valid = call_user_func( $condition['check'], $condition, $value );
		}

		$valid = apply_filters( 'pods_gf_condition_validate_' . $condition['check'], $valid, $condition, $value, $field );
		$valid = apply_filters( 'pods_gf_condition_validate', $valid, $condition, $value, $field );

		return $valid;

	}

	/**
	 * Validate the length of a value
	 *
	 * @param array       $condition Condition to match, using similar syntax to WP_Query's meta_query
	 * @param mixed|array $value     Value to check
	 *
	 * @return bool Whether the length was valid
	 */
	public static function condition_validate_length( $condition, $value ) {

		$valid = false;

		if ( is_array( $value ) ) {
			$valid = ! empty( $value );

			foreach ( $value as $val ) {
				$valid_val = self::condition_validate_length( $condition, $val );

				if ( ! $valid_val ) {
					$valid = false;

					break;
				}
			}
		}
		else {
			$condition['value'] = (int) $condition['value'];

			$value = strlen( $value );

			if ( '=' == $condition['compare'] ) {
				if ( $condition['value'] == $value ) {
					$valid = true;
				}
			}
			elseif ( '===' == $condition['compare'] ) {
				if ( $condition['value'] === $value ) {
					$valid = true;
				}
			}
			elseif ( '!=' == $condition['compare'] ) {
				if ( $condition['value'] != $value ) {
					$valid = true;
				}
			}
			elseif ( '!==' == $condition['compare'] ) {
				if ( $condition['value'] !== $value ) {
					$valid = true;
				}
			}
			elseif ( in_array( $condition['compare'], array( '>', '>=', '<', '<=' ) ) ) {
				if ( version_compare( (float) $value, (float) $condition['value'], $condition['compare'] ) ) {
					$valid = true;
				}
			}
			elseif ( 'IN' == $condition['compare'] ) {
				if ( in_array( $value, $condition['value'] ) ) {
					$valid = true;
				}
			}
			elseif ( 'NOT IN' == $condition['compare'] ) {
				if ( ! in_array( $value, $condition['value'] ) ) {
					$valid = true;
				}
			}
			elseif ( 'BETWEEN' == $condition['compare'] ) {
				if ( (float) $condition['value'][0] <= (float) $value && (float) $value <= (float) $condition['value'][1] ) {
					$valid = true;
				}
			}
			elseif ( 'NOT BETWEEN' == $condition['compare'] ) {
				if ( (float) $condition['value'][1] < (float) $value || (float) $value < (float) $condition['value'][0] ) {
					$valid = true;
				}
			}
		}

		return $valid;

	}

	/**
	 * Validate the value
	 *
	 * @param array       $condition Condition to match, using similar syntax to WP_Query's meta_query
	 * @param mixed|array $value     Value to check
	 *
	 * @return bool Whether the value was valid
	 */
	public static function condition_validate_value( $condition, $value ) {

		$valid = false;

		if ( is_array( $value ) ) {
			$valid = ! empty( $value );

			foreach ( $value as $val ) {
				$valid_val = self::condition_validate_value( $condition, $val );

				if ( ! $valid_val ) {
					$valid = false;

					break;
				}
			}
		}
		elseif ( '=' == $condition['compare'] ) {
			if ( $condition['value'] == $value ) {
				$valid = true;
			}
		}
		elseif ( '===' == $condition['compare'] ) {
			if ( $condition['value'] === $value ) {
				$valid = true;
			}
		}
		elseif ( '!=' == $condition['compare'] ) {
			if ( $condition['value'] != $value ) {
				$valid = true;
			}
		}
		elseif ( '!==' == $condition['compare'] ) {
			if ( $condition['value'] !== $value ) {
				$valid = true;
			}
		}
		elseif ( in_array( $condition['compare'], array( '>', '>=', '<', '<=' ) ) ) {
			if ( version_compare( (float) $value, (float) $condition['value'], $condition['compare'] ) ) {
				$valid = true;
			}
		}
		elseif ( 'LIKE' == $condition['compare'] ) {
			if ( false !== stripos( $value, $condition['value'] ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT LIKE' == $condition['compare'] ) {
			if ( false === stripos( $value, $condition['value'] ) ) {
				$valid = true;
			}
		}
		elseif ( 'IN' == $condition['compare'] ) {
			if ( in_array( $value, $condition['value'] ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT IN' == $condition['compare'] ) {
			if ( ! in_array( $value, $condition['value'] ) ) {
				$valid = true;
			}
		}
		elseif ( 'BETWEEN' == $condition['compare'] ) {
			if ( (float) $condition['value'][0] <= (float) $value && (float) $value <= (float) $condition['value'][1] ) {
				$valid = true;
			}
		}
		elseif ( 'NOT BETWEEN' == $condition['compare'] ) {
			if ( (float) $condition['value'][1] < (float) $value || (float) $value < (float) $condition['value'][0] ) {
				$valid = true;
			}
		}
		elseif ( 'EXISTS' == $condition['compare'] ) {
			if ( ! is_null( $value ) && '' !== $value ) {
				$valid = true;
			}
		}
		elseif ( 'NOT EXISTS' == $condition['compare'] ) {
			if ( is_null( $value ) || '' === $value ) {
				$valid = true;
			}
		}
		elseif ( in_array( $condition['compare'], array( 'REGEXP', 'RLIKE' ) ) ) {
			if ( preg_match( $condition['value'], $value ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT REGEXP' == $condition['compare'] ) {
			if ( ! preg_match( $condition['value'], $value ) ) {
				$valid = true;
			}
		}

		return $valid;

	}

	/**
	 * Setup GF to auto-delete the entry it's about to create
	 *
	 * @static
	 *
	 * @param int  $form_id    GF Form ID
	 * @param bool $keep_files To keep or delete files when deleting GF entries
	 */
	public static function auto_delete( $form_id = null, $keep_files = null ) {

		if ( null !== $keep_files ) {
			self::$keep_files[ $form_id ] = (boolean) $keep_files;
		}

		$form = ( ! empty( $form_id ) ? '_' . (int) $form_id : '' );

		add_action( 'gform_post_submission' . $form, array( get_class(), 'gf_delete_entry' ), 20, 1 );

	}

	/**
	 * Set a field's values to be dynamically pulled from a Pod
	 *
	 * @static
	 *
	 * @param int   $form_id  GF Form ID
	 * @param int   $field_id GF Field ID
	 * @param array $options  Dynamic select options
	 */
	public static function dynamic_select( $form_id, $field_id, $options ) {
		self::$dynamic_selects[] = array_merge(
			array(
				'form'        => $form_id,

				'gf_field'    => $field_id, // override $field
				'default'     => null, // override default selected value

				'options'     => null, // set to an array for a basic custom options list

				'pod'         => null, // set to a pod to use
				'field_text'  => null, // set to the field to show for text (option label)
				'field_value' => null, // set to field to use for value (option value)
				'params'      => null, // set to a $params array to override the default find()
			),
			$options
		);

		$class = get_class();

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_dynamic_select' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_dynamic_select' ), 10, 2 );
		}
	}

	/**
	 * Build the GF Choices array for use in field option overrides
	 *
	 * @param array  $values        Value array (value=>label)
	 * @param string $current_value Current value
	 * @param string $default       Default value
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
			if ( is_array( $label ) ) {
				$choices[] = $label;
			} else {
				$is_selected = false;

				if ( is_array( $current_value ) ) {
					if ( in_array( (string) $value, $current_value, true ) ) {
						$is_selected = true;
					}
				} elseif ( (string) $value === (string) $current_value ) {
					$is_selected = true;
				}

				$choices[] = array(
					'text'       => $label,
					'value'      => $value,
					'isSelected' => $is_selected,
				);
			}
		}

		return $choices;

	}

	/**
	 * Get the currently selected choice value/text from the GF Choices array
	 *
	 * @param array  $choices       GF Choices array
	 * @param string $current_value Current value
	 * @param string $default       Default value
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
			if ( ! is_array( $choice ) ) {
				$choice = array(
					'text'  => $choice,
					'value' => $value
				);
			}

			if ( 1 === (int) pods_v( 'isSelected', $choice ) || '' === $current_value || ( ! isset( $choice['isSelected'] ) && (string) $choice['value'] === (string) $current_value ) ) {
				$selected               = $choice;
				$selected['isSelected'] = true;

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
	 * @param int         $form_id GF Form ID
	 * @param string|Pods $pod     Pod name (or Pods object)
	 * @param int         $id      Pod item ID
	 * @param array       $fields  Field mapping to prepopulate from
	 */
	public static function prepopulate( $form_id, $pod, $id, $fields ) {
		self::$prepopulate = array(
			'form'   => $form_id,

			'pod'    => $pod,
			'id'     => $id,
			'fields' => $fields
		);

		$class = get_class();

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_prepopulate' ) ) ) {
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
			$form_id = $form_id['id'];
		}

		if ( is_array( $field_id ) ) {
			$field_id = $field_id['id'];
		}

		$class = get_class();

		if ( ! has_filter( 'pods_gf_field_value_' . $form_id . '_' . $field_id, array( $class, 'gf_prepopulate_value' ) ) ) {
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
	 * Setup Secondary Submits for a form
	 *
	 * @param int   $form_id GF Form ID
	 * @param array $options Secondary Submits options
	 */
	public static function secondary_submits( $form_id, $options = array() ) {

		self::$secondary_submits[ $form_id ] = array(
			'imageUrl'      => null,
			'text'          => 'Alt Submit',
			'action'        => 'alt',
			'value'         => 1,
			'value_from_ui' => ''
		);

		if ( is_array( $options ) ) {
			self::$secondary_submits[ $form_id ] = $options;
		}

		if ( ! has_filter( 'gform_submit_button_' . $form_id, array( 'Pods_GF', 'gf_secondary_submit_button' ) ) ) {
			add_filter( 'gform_submit_button_' . $form_id, array( 'Pods_GF', 'gf_secondary_submit_button' ), 10, 2 );
		}

		if ( ! wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

		if ( !wp_style_is( 'pods-gf', 'registered' ) ) {
			wp_register_style( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.css', array(), PODS_GF_VERSION );
		}

	}

	/**
	 * Add Secondary Submit button(s)
	 *
	 * Warning: Gravity Forms Duplicate Prevention plugin's JS *will* break this!
	 *
	 * @param string $button_input Button HTML
	 * @param array  $form         GF Form array
	 *
	 * @return string Button HTML
	 */
	public static function gf_secondary_submit_button( $button_input, $form ) {

		$secondary_submits = pods_v( $form['id'], self::$secondary_submits, array(), true );

		if ( ! empty( $secondary_submits ) ) {
			if ( isset( $secondary_submits['action'] ) ) {
				$secondary_submits = array( $secondary_submits );
			}

			$secondary_submits = array_reverse( $secondary_submits );

			wp_enqueue_script( 'pods-gf' );
			wp_enqueue_style( 'pods-gf' );

			$defaults = array(
				'imageUrl'      => null,
				'text'          => 'Alt Submit',
				'action'        => 'alt',
				'value'         => 1,
				'value_from_ui' => ''
			);

			foreach ( $secondary_submits as $secondary_submit ) {
				$secondary_submit = array_merge( $defaults, $secondary_submit );

				if ( ! empty( $secondary_submit['value_from_ui'] ) && class_exists( 'Pods_GF_UI' ) && ! empty( Pods_GF_UI::$pods_ui ) ) {
					if ( in_array( $secondary_submit['value_from_ui'], array( 'next_id', 'prev_id' ) ) ) {
						// Setup data
						Pods_GF_UI::$pods_ui->get_data();
					}

					if ( 'prev_id' == $secondary_submit['value_from_ui'] ) {
						$secondary_submit['value'] = Pods_GF_UI::$pods_ui->pod->prev_id();
					}
					elseif ( 'next_id' == $secondary_submit['value_from_ui'] ) {
						$secondary_submit['value'] = Pods_GF_UI::$pods_ui->pod->next_id();
					}

					if ( in_array( $secondary_submit['value_from_ui'], array( 'next_id', 'prev_id' ) ) ) {
						// No ID, hide button
						if ( empty( $secondary_submit['value'] ) ) {
							continue;
						}
					}
				}

				if ( empty( $secondary_submit['imageUrl'] ) ) {
					if ( null !== $secondary_submit['value'] && $secondary_submit['text'] !== $secondary_submit['value'] ) {
						$button_input .= ' <button type="submit" class="button gform_button pods-gf-secondary-submit pods-gf-secondary-submit-' . sanitize_title( $secondary_submit['action'] ) . '"'
							. ' name="pods_gf_ui_action_' . sanitize_title( $secondary_submit['action'] ) . '"'
							. ' value="' . esc_attr( $secondary_submit['value'] ) . '"'
							. ' onclick="if(window[\'gf_submitting\']){return false;} window[\'gf_submitting\']=true;"'
							. '>' . esc_html( $secondary_submit['text'] ) . '</button>';
					}
					else {
						$button_input .= ' <input type="submit" class="button gform_button pods-gf-secondary-submit pods-gf-secondary-submit-' . sanitize_title( $secondary_submit['action'] ) . '"'
							. ' name="pods_gf_ui_action_' . sanitize_title( $secondary_submit['action'] ) . '"'
							. ' value="' . esc_attr( $secondary_submit['text'] ) . '"'
							. ' onclick="if(window[\'gf_submitting\']){return false;} window[\'gf_submitting\']=true;" />';
					}
				}
				else {
					$button_input .= ' <input type="image" class="pods-gf-secondary-submit pods-gf-secondary-submit-' . sanitize_title( $secondary_submit['action'] ) . '"'
						. ' name="pods_gf_ui_action_' . sanitize_title( $secondary_submit['action'] ) . '"'
						. ' src="' . esc_attr( $secondary_submit['imageUrl'] ) . '"'
						. ' value="' . esc_attr( $secondary_submit['text'] ) . '"'
						. ' onclick="if(window[\'gf_submitting\']){return false;} window[\'gf_submitting\']=true;" />';
				}
			}
		}

		return $button_input;

	}

	/**
	 * Setup Save for Later for a form
	 *
	 * @param int   $form_id GF Form ID
	 * @param array $options Save for Later options
	 */
	public static function save_for_later( $form_id, $options = array() ) {

		self::$save_for_later[$form_id] = array(
			'redirect'      => null,
			'exclude_pages' => array(),
			'addtl_id'      => ''
		);

		if ( is_array( $options ) ) {
			self::$save_for_later[$form_id] = array_merge( self::$save_for_later[$form_id], $options );
		}

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_load' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_load' ), 9, 2 );
			add_filter( 'gform_submit_button_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_button' ), 10, 2 );
			add_action( 'gform_after_submission_' . $form_id, array( 'Pods_GF', 'gf_save_for_later_clear' ), 10, 2 );
		}

		if ( ! wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

	}

	/**
	 * Save for Later handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool  $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_save_for_later_load( $form, $ajax ) {

		$save_for_later = pods_v( $form['id'], self::$save_for_later, array(), true );

		if ( ! empty( $save_for_later ) && empty( $_POST ) ) {
			$save_for_later_data = self::gf_save_for_later_data( $form['id'] );

			if ( ! empty( $save_for_later_data ) ) {
				$_POST                                  = $save_for_later_data;
				$_POST['pods_gf_save_for_later_loaded'] = 1;
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

		$postdata = array();

		$addtl_id = '';

		$save_for_later = pods_v( $form_id, self::$save_for_later, array(), true );

		if ( ! empty( $save_for_later ) && isset( $save_for_later['addtl_id'] ) && ! empty( $save_for_later['addtl_id'] ) ) {
			$addtl_id = '_' . $save_for_later['addtl_id'];
		}

		if ( is_user_logged_in() ) {
			$postdata = get_user_meta( get_current_user_id(), '_pods_gf_saved_form_' . $form_id . $addtl_id, true );
		}

		if ( empty( $postdata ) ) {
			$postdata = pods_v( '_pods_gf_saved_form_' . $form_id . $addtl_id, 'cookie' );
		}

		if ( ! empty( $postdata ) ) {
			$postdata = @json_decode( $postdata, true );

			if ( ! empty( $postdata ) ) {
				return pods_slash( $postdata );
			}
		}

		return false;

	}

	/**
	 * Add Save for Later buttons
	 *
	 * @param string $button_input Button HTML
	 * @param array  $form         GF Form array
	 *
	 * @return string Button HTML
	 */
	public static function gf_save_for_later_button( $button_input, $form ) {

		$save_for_later = pods_v( $form['id'], self::$save_for_later, array(), true );

		if ( ! empty( $save_for_later ) ) {
			if ( 1 == pods_v( 'pods_gf_save_for_later_loaded', 'post' ) ) {
				$button_input .= '<input type="hidden" name="pods_gf_save_for_later_loaded" value="1" />';
			}

			if ( ! empty( $save_for_later['exclude_pages'] ) && in_array( GFFormDisplay::get_current_page( $form['id'] ), $save_for_later['exclude_pages'] ) ) {
				return $button_input;
			}

			wp_enqueue_script( 'pods-gf' );

			$button_input .= ' <input type="button" class="button gform_button pods-gf-save-for-later" value="' . esc_attr__( 'Save for Later', 'pods-gf-ui' ) . '" />';

			if ( 1 == pods_v( 'pods_gf_save_for_later_loaded', 'post' ) ) {
				$button_input .= ' <input type="button" class="button gform_button pods-gf-save-for-later-reset" value="' . esc_attr__( 'Reset Saved Form', 'pods-gf-ui' ) . '" />';
			}

			if ( ! empty( $save_for_later['redirect'] ) ) {
				if ( 0 === strpos( $save_for_later['redirect'], '?' ) ) {
					$path                       = explode( '?', $_SERVER['REQUEST_URI'] );
					$path                       = explode( '#', $path[0] );
					$save_for_later['redirect'] = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $path[0] . $save_for_later['redirect'];
				}

				$button_input .= '<input type="hidden" name="pods_gf_save_for_later_redirect" value="' . esc_attr( $save_for_later['redirect'] ) . '" />';
			}

			if ( ! empty( $save_for_later['addtl_id'] ) ) {
				$button_input .= '<input type="hidden" name="pods_gf_save_for_later_addtl_id" value="' . esc_attr( $save_for_later['addtl_id'] ) . '" />';
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
		$form_id = str_replace( 'gform_', '', pods_v( 'form_id', 'request' ) );

		if ( 0 < $form_id ) {
			$redirect = pods_v( 'pods_gf_save_for_later_redirect', 'post', '/?pods_gf_form_saved=' . $form_id, true );
			$redirect = pods_v( 'pods_gf_save_for_later_redirect', 'get', $redirect, true );

			$post = pods_unslash( $_POST );

			if ( isset( $post['pods_gf_save_for_later_redirect'] ) ) {
				unset( $post['pods_gf_save_for_later_redirect'] );
			}

			$addtl_id = pods_v( 'pods_gf_save_for_later_addtl_id', 'post', '', true );

			if ( 0 < strlen( $addtl_id ) ) {
				$addtl_id = '_' . $addtl_id;
			}

			// Clear saved form
			if ( 1 == pods_v( 'pods_gf_clear_saved_form' ) ) {
				self::gf_save_for_later_clear( array(), array( 'id' => $form_id ), true );
			}
			// Save $post for later
			else {
				// JSON encode to avoid serialization issues
				$postdata = json_encode( $post );

				if ( is_user_logged_in() ) {
					update_user_meta( get_current_user_id(), '_pods_gf_saved_form_' . $form_id . $addtl_id, $postdata );
				}

				pods_var_set( $postdata, '_pods_gf_saved_form_' . $form_id . $addtl_id, 'cookie' );
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
	 * @param array $form  GF Form array
	 */
	public static function gf_save_for_later_clear( $entry, $form, $force = false ) {

		global $user_ID;

		$save_for_later = pods_v( $form['id'], self::$save_for_later, array(), true );

		if ( ! empty( $save_for_later ) || $force ) {
			$addtl_id = '';

			if ( ! empty( $save_for_later ) && isset( $save_for_later['addtl_id'] ) && ! empty( $save_for_later['addtl_id'] ) ) {
				$addtl_id = $save_for_later['addtl_id'];
			}
			else {
				$addtl_id = pods_v( 'pods_gf_save_for_later_addtl_id', 'post', '', true );
			}

			if ( ! empty( $addtl_id ) ) {
				$addtl_id = '_' . $addtl_id;
			}

			if ( is_user_logged_in() ) {
				delete_user_meta( $user_ID, '_pods_gf_saved_form_' . $form['id'] . $addtl_id );
			}

			pods_var_set( '', '_pods_gf_saved_form_' . $form['id'] . $addtl_id, 'cookie' );
		}

	}

	/**
	 * Setup Remember for next time for a form
	 *
	 * @param int   $form_id GF Form ID
	 * @param array $options Save for Later options
	 */
	public static function remember( $form_id, $options = array() ) {

		self::$remember[$form_id] = array(
			'fields' => null
		);

		if ( is_array( $options ) ) {
			self::$remember[$form_id] = array_merge( self::$remember[$form_id], $options );
		}

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_remember_load' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( 'Pods_GF', 'gf_remember_load' ), 9, 2 );
			add_action( 'gform_after_submission_' . $form_id, array( 'Pods_GF', 'gf_remember_save' ), 10, 2 );
		}

		if ( ! wp_script_is( 'pods-gf', 'registered' ) ) {
			wp_register_script( 'pods-gf', PODS_GF_URL . 'ui/pods-gf.js', array( 'jquery' ), PODS_GF_VERSION, true );
		}

	}

	/**
	 * Remember for next time handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool  $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_remember_load( $form, $ajax ) {

		global $user_ID;

		$remember = pods_v( $form['id'], self::$remember, array(), true );

		if ( ! empty( $remember ) && empty( $_POST ) ) {
			$postdata = array();

			if ( is_user_logged_in() ) {
				$postdata = get_user_meta( $user_ID, '_pods_gf_remember_' . $form['id'], true );
			}

			if ( empty( $postdata ) ) {
				$postdata = pods_v( '_pods_gf_remember_' . $form['id'], 'cookie' );
			}

			if ( ! empty( $postdata ) ) {
				$postdata = @json_decode( $postdata, true );

				if ( ! empty( $postdata ) ) {
					$fields = pods_v( 'fields', $remember );

					if ( ! empty( $fields ) ) {
						foreach ( $fields as $field ) {
							if ( ! isset( $_POST['input_' . $field] ) && isset( $postdata['input_' . $field] ) ) {
								$_POST['input_' . $field] = pods_slash( $postdata['input_' . $field] );
							}
						}
					}
					else {
						$_POST = array_merge( pods_slash( $postdata ), $_POST );
					}

					$_POST['pods_gf_remember_loaded'] = 1;
				}
			}
		}

		return $form;

	}


	/**
	 * Save for Later handler for Gravity Forms: gform_after_submission_{$form_id}
	 *
	 * @param array $entry GF Entry array
	 * @param array $form  GF Form array
	 */
	public static function gf_remember_save( $entry, $form ) {

		global $user_ID;

		$remember = pods_v( $form['id'], self::$remember, array(), true );

		if ( ! empty( $remember ) ) {
			$fields = pods_v( 'fields', $remember );

			$post = pods_unslash( $_POST );

			$postdata = array();

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( isset( $post['input_' . $field] ) ) {
						$postdata['input_' . $field] = $post['input_' . $field];
					}
				}
			}
			else {
				$postdata = $post;

				foreach ( $postdata as $k => $v ) {
					if ( 0 !== strpos( $k, 'input_' ) ) {
						unset( $postdata[$k] );
					}
				}
			}

			if ( ! empty( $postdata ) ) {
				// JSON encode to avoid serialization issues
				$postdata = json_encode( $postdata );

				if ( is_user_logged_in() ) {
					update_user_meta( $user_ID, '_pods_gf_remember_' . $form['id'], $postdata );
				}

				pods_var_set( $postdata, '_pods_gf_remember_' . $form['id'], 'cookie' );
			}
		}

	}

	/**
	 * Map GF form fields to Pods fields
	 *
	 * @param array $form  GF Form array
	 * @param array $entry GF Entry array
	 *
	 * @return int Pod item ID
	 */
	public function _gf_to_pods_handler( $form, $entry = array() ) {

		$form = $this->setup_form( $form );

		$id = 0;

		if ( isset( $this->options['edit'] ) && $this->options['edit'] ) {
			$id = $this->get_current_id();
		}

		if ( isset( $this->options['save_id'] ) && ! empty( $this->options['save_id'] ) ) {
			$id = (int) $this->options['save_id'];
		}

		$save_action = 'add';

		if ( empty( $id ) && ! empty( $entry['id'] ) && ( ! empty( $this->options['edit'] ) || ! empty( $this->options['update_pod_item'] ) || apply_filters( 'pods_gf_to_pods_update_pod_items', false ) ) ) {
			$item_id  = (int) gform_get_meta( $entry['id'], '_pods_item_id' );
			$item_pod = gform_get_meta( $entry['id'], '_pods_item_pod' );

			if ( $item_id && $item_pod && is_object( $this->pod ) && $item_pod === $this->pod->pod ) {
				// Only use the item ID if it exists.
				if ( $this->pod->fetch( $item_id ) ) {
					$id = $item_id;
				}
			}
		}

		if ( ! empty( $id ) ) {
			$save_action = 'save';
		}

		if ( isset( $this->options['save_action'] ) ) {
			$save_action = $this->options['save_action'];
		}

		if ( 'bypass' === $save_action ) {
			return $id;
		}

		if ( empty( $id ) || ! in_array( $save_action, array( 'add', 'save' ), true ) ) {
			$save_action = 'add';
		}

		// Set pod_item_id for use later.
		$this->options['pod_item_id'] = $id;

		$data = self::gf_to_pods( $form, $this->options, $this->pod, $entry );

		$args = array(
			$data // Data
		);

		if ( 'save' === $save_action ) {
			$args[1] = null; // Value
			$args[2] = $id; // ID
		}

		if ( is_object( $this->pod ) ) {
			if ( ! empty( $this->pod->data->field_id ) && ! empty( $data[ $this->pod->data->field_id ] ) ) {
				$save_action = 'save';

				$args[1] = null; // Value
				$args[2] = $data[ $this->pod->data->field_id ]; // ID

				// Remove field, not saving it
				unset( $data[ $this->pod->data->field_id ] );
			}

			if ( empty( $id ) ) {
				if ( 'post_type' === $this->pod->pod_data['type'] ) {
					if ( ! empty( $form['postStatus'] ) && empty( $args[0]['post_status'] ) ) {
						$args[0]['post_status'] = $form['postStatus'];
					}

					if ( empty( $args[0]['post_author'] ) ) {
						if ( ! empty( $form['useCurrentUserAsAuthor'] ) && is_user_logged_in() ) {
							$args[0]['post_author'] = get_current_user_id();
						} elseif ( ! empty( $form['postAuthor'] ) ) {
							$args[0]['post_author'] = $form['postAuthor'];
						}
					}

					if ( ! empty( $form['postCategory'] ) && empty( $args[0]['category'] ) && ! empty( $this->pod->pod_data['object_fields']['category'] ) ) {
						$args[0]['category'] = $form['postCategory'];
					}

					if ( ! empty( $form['postFormat'] ) && empty( $args[0]['post_format'] ) && ! empty( $this->pod->pod_data['object_fields']['post_format'] ) ) {
						$args[0]['post_format'] = $form['postFormat'];
					}
				}
			}

			if ( ! empty( $this->pod->pod_data ) ) {
				$id = call_user_func_array( array( $this->pod, $save_action ), $args );

				$this->pod->id = $id;
				$this->pod->fetch( $id );
			}

			do_action( 'pods_gf_to_pods_' . $form['id'] . '_' . $this->pod->pod, $this->pod, $args, $save_action, $data, $id, $this );
			do_action( 'pods_gf_to_pods_' . $this->pod->pod, $this->pod, $args, $save_action, $data, $id, $this );
		}
		else {
			$id = apply_filters( 'pods_gf_to_pod_' . $form['id'] . '_' . $save_action, $id, $this->pod, $data, $this );

			$this->id = $id = apply_filters( 'pods_gf_to_pod_' . $save_action, $id, $this->pod, $data, $this );
		}

		// Set pod_item_id for use later.
		$this->options['pod_item_id'] = $id;

		self::$gf_to_pods_id[ $this->form_id ] = $id;

		if ( $this->pod && ! empty( $this->pod->pod_data ) ) {
			self::$gf_to_pods_id[ $this->form_id . '_permalink' ] = $this->pod->field( 'detail_url' );
		}

		if ( ! empty( $entry['id'] ) ) {
			gform_update_meta( $entry['id'], '_pods_item_id', $id );

			if ( is_object( $this->pod ) ) {
				gform_update_meta( $entry['id'], '_pods_item_pod', $this->pod->pod );
			}
		}

		do_action( 'pods_gf_to_pods', $this->pod, $save_action, $data, $id, $this );

		return $id;

	}

	/**
	 * Map GF form fields to Pods fields
	 *
	 * @param array      $form    GF Form array
	 * @param array      $options Form config
	 * @param Pods|array $pod     Pod object or entry array
	 * @param array      $entry   GF Entry array
	 *
	 * @return array Data array for saving
	 */
	public static function gf_to_pods( $form, $options, $pod = array(), $entry = array() ) {

		$data = array();

		if ( ! isset( $options['fields'] ) || empty( $options['fields'] ) ) {
			return $data;
		}

		/**
		 * @var $fields GF_Field[]
		 */
		$fields = $form['fields'];

		$gf_fields = array();

		if ( ! empty( $entry ) ) {
			$entry['form_title'] = $form['title'];
		}

		$extra_gf_fields = array(
			'id',
			'date_created',
			'ip',
			'source_url',
			'form_title',
			'transaction_id',
			'payment_amount',
			'payment_date',
			'payment_status',
		);

		$basic_gf_field_data = array(
			'id'         => '',
			'label'      => '',
			'type'       => 'text',
			'isRequired' => false,
			'visibility' => 'visible',
			'formId'     => $form['id'],
		);

		foreach ( $extra_gf_fields as $extra_gf_field ) {
			$basic_gf_field_data['id']    = $extra_gf_field;
			$basic_gf_field_data['label'] = $extra_gf_field;

			$gf_fields[ $extra_gf_field ] = GF_Fields::create( $basic_gf_field_data );
		}

		foreach ( $fields as $gf_field ) {
			$gf_fields[ (string) $gf_field->id ] = $gf_field;
		}

		if ( empty( $entry ) ) {
			if ( ! empty( $options['entry'] ) ) {
				$entry = $options['entry'];
			} else {
				$entry = GFFormsModel::get_current_lead();
			}
		}

		foreach ( $options['fields'] as $field => $field_options ) {
			$field = (string) $field;

			$field_options = array_merge(
				array(
					'gf_field' => $field,
					'field'    => $field_options,
					'value'    => null,
				),
				( is_array( $field_options ) ? $field_options : array() )
			);

			// No field set
			if ( empty( $field_options['gf_field'] ) || empty( $field_options['field'] ) || is_array( $field_options['field'] ) ) {
				continue;
			}

			$field = $field_options['gf_field'];

			// Get GF field object
			$gf_field = null;

			$field_full = null;

			/**
			 * @var $gf_field GF_Field
			 */
			if ( isset( $gf_fields[ (string) $field ] ) ) {
				$field_full = $field;

				$gf_field = $gf_fields[ (string) $field ];
			} elseif ( false !== strpos( $field, '.' ) ) {
				$field_full = $field;

				$gf_field_expanded = explode( '.', $field );

				if ( isset( $gf_fields[ (string) $gf_field_expanded[0] ] ) ) {
					$gf_field = $gf_fields[ (string) $gf_field_expanded[0] ];
				}
			}

			// GF input field
			$value = null;

			$field_data = array();

			if ( is_object( $pod ) && ! empty( $pod->pod_data ) ) {
				$field_data = $pod->fields( $field_options['field'] );
			}

			if ( $gf_field ) {
				$gf_params = array(
					'gf_field'         => $gf_field,
					'gf_field_options' => $field_options,
					'field'            => $field_full,
					'field_options'    => $field_data,
					'pod'              => $pod,
					'form'             => $form,
					'entry'            => $entry,
					'options'          => $options,
					'handle_files'     => true,
				);

				$value = self::get_gf_field_value( $value, $gf_params );
			}

			// Manual value override
			if ( null !== $field_options['value'] ) {
				$value = $field_options['value'];

				if ( is_string( $value ) && ! empty( $field_options['gf_merge_tags'] ) ) {
					$value = GFCommon::replace_variables( $value, $form, $entry );
				}
			}

			$value = apply_filters( 'pods_gf_to_pods_value_' . $form['id'] . '_' . $field, $value, $field, $field_options, $form, $gf_field, $data, $options );
			$value = apply_filters( 'pods_gf_to_pods_value_' . $form['id'], $value, $field, $field_options, $form, $gf_field, $data, $options );
			$value = apply_filters( 'pods_gf_to_pods_value', $value, $field, $field_options, $form, $gf_field, $data, $options );

			// If a file is not set, check if we are editing an item.
			if ( null === $value && in_array( $gf_field->type, array( 'fileupload', 'post_image' ), true ) ) {
				// If we are editing an item, don't attempt to save.
				if ( is_object( $pod ) && $pod->id ) {
					continue;
				}
			}

			// Set data
			if ( null !== $value ) {
				// @todo Support simple repeatable fields in Pods 2.9
				if ( $gf_field && 'list' === $gf_field->type && is_array( $value ) ) {
					if ( empty( $pod ) || empty( $gf_params['field_options']['type'] ) || 'pick' !== $gf_params['field_options']['type'] ) {
						$value = json_encode( $value );
					}
				}

				$data[ $field_options['field'] ] = $value;
			}
		}

		$data = apply_filters( 'pods_gf_to_pods_data_' . $form['id'], $data, $form, $options );
		$data = apply_filters( 'pods_gf_to_pods_data', $data, $form, $options );

		// Debug purposes.
		if ( 1 === (int) pods_v( 'pods_gf_debug_gf_to_pods', 'get', 0 ) && pods_is_admin( [ 'pods' ] ) ) {
			pods_debug( compact( 'data', 'entry', 'options' ) );
			die();
		}

		return $data;
	}

	/**
	 * Send notifications based on config
	 *
	 * @param array $entry   GF Entry array
	 * @param array $form    GF Form array
	 * @param array $options Form options
	 *
	 * @return bool Whether the notifications were sent
	 */
	public static function gf_notifications( $entry, $form, $options ) {

		if ( ! isset( $options['notifications'] ) || empty( $options['notifications'] ) ) {
			return false;
		}

		foreach ( $options['notifications'] as $template => $template_options ) {
			$template_options = (array) $template_options;

			if ( isset( $template_options['template'] ) ) {
				$template = $template_options['template'];
			}

			$template_data = apply_filters( 'pods_gf_template_object', null, $template, $template_options, $entry, $form, $options );

			$template_obj = null;

			// @todo Hook into GF notifications and use those instead, stopping the e-mail from being sent to original e-mail

			if ( ! is_array( $template_data ) ) {
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
					'cc'      => get_post_meta( $template_obj->ID, '_pods_gf_template_cc', true ),
					'bcc'     => get_post_meta( $template_obj->ID, '_pods_gf_template_bcc', true )
				);

				if ( empty( $template_data['subject'] ) ) {
					$template_data['subject'] = $template_obj->post_title;
				}
			}

			if ( ! isset( $template_data['subject'] ) || empty( $template_data['subject'] ) || ! isset( $template_data['content'] ) || empty( $template_data['content'] ) ) {
				continue;
			}

			$to     = array();
			$emails = array();

			foreach ( $template_options as $k => $who ) {
				if ( ! is_numeric( $k ) ) {
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
						'to'      => $user->user_email,
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
						'to'      => $who,
						'user_id' => ( ! empty( $user ) ? $user->ID : 0 )
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
							'to'      => $user->user_email,
							'user_id' => $user->ID
						);
					}
				}
			}

			foreach ( $emails as $email ) {
				$headers = array();

				if ( isset( $template_data['cc'] ) && ! empty( $template_data['cc'] ) ) {
					$template_data['cc'] = (array) $template_data['cc'];

					foreach ( $template_data['cc'] as $cc ) {
						$headers[] = 'Cc: ' . $cc;
					}
				}

				if ( isset( $template_data['bcc'] ) && ! empty( $template_data['bcc'] ) ) {
					$template_data['bcc'] = (array) $template_data['bcc'];

					foreach ( $template_data['cc'] as $cc ) {
						$headers[] = 'Bcc: ' . $cc;
					}
				}

				$email_template = array(
					'to'          => $email['to'],
					'subject'     => $template_data['subject'],
					'content'     => $template_data['content'],
					'headers'     => $headers,
					'attachments' => array(),
					'user_id'     => $email['user_id']
				);

				$email_template = apply_filters( 'pods_gf_template_email', $email_template, $entry, $form, $options );

				if ( empty( $email_template ) ) {
					continue;
				}

				wp_mail( $email_template['to'], $email_template['subject'], $email_template['content'], $email_template['headers'], $email_template['attachments'] );
			}
		}

		return true;
	}

	/**
	 * Delete a GF entry, because GF doesn't have an API to do this yet (the function itself is user-restricted)
	 *
	 * @param array $entry      GF Entry array
	 *
	 * @return bool If the entry was successfully deleted
	 */
	public static function gf_delete_entry( $entry ) {

		global $wpdb;

		if ( ! is_array( $entry ) && 0 < (int) $entry ) {
			$lead_id = (int) $entry;
		}
		elseif ( is_array( $entry ) && isset( $entry['id'] ) && 0 < (int) $entry['id'] ) {
			$lead_id = (int) $entry['id'];
		}
		else {
			return false;
		}

		GFAPI::delete_entry( $lead_id );

		return true;

	}

	/**
	 * Set dynamic option values for GF Form fields
	 *
	 * @param array      $form            GF Form array
	 * @param bool       $ajax            Whether the form was submitted using AJAX
	 * @param array|null $dynamic_selects The Dynamic select options to use
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_dynamic_select( $form, $ajax = false, $dynamic_selects = null ) {

		if ( null === $dynamic_selects ) {
			$dynamic_selects = self::$dynamic_selects;
		}

		if ( empty( $dynamic_selects ) ) {
			return $form;
		}

		if ( isset( self::$actioned[ $form['id'] ] ) && in_array( __FUNCTION__, self::$actioned[ $form['id'] ], true ) ) {
			return $form;
		} elseif ( ! isset( self::$actioned[ $form['id'] ] ) ) {
			self::$actioned[ $form['id'] ] = array();
		}

		self::$actioned[ $form['id'] ][] = __FUNCTION__;

		$field_keys = array();

		foreach ( $form['fields'] as $k => $field ) {
			$field_keys[ (string) $field['id'] ] = $k;
		}

		// Dynamic Select handler
		foreach ( $dynamic_selects as $field => $dynamic_select ) {
			$field = (string) $field;

			$dynamic_select = array_merge(
				array(
					'form'        => $form['id'],

					'gf_field'    => $field, // override $field
					'default'     => null, // override default selected value

					'options'     => null, // set to an array for a basic custom options list
					'select_text' => null, // set to the text to use for the empty option

					'pod'         => null, // set to a pod to use
					'field_text'  => null, // set to the field to show for text (option label)
					'field_value' => null, // set to field to use for value (option value)
					'params'      => null, // set to a $params array to override the default find()
				),
				( is_array( $dynamic_select ) ? $dynamic_select : array() )
			);

			if ( ! empty( $dynamic_select['gf_field'] ) ) {
				$field = (string) $dynamic_select['gf_field'];
			}

			if ( empty( $field ) || ! isset( $field_keys[$field] ) || $dynamic_select['form'] != $form['id'] ) {
				continue;
			}

			$field_key = $field_keys[$field];
			$field_obj = $form['fields'][$field_key];

			$choices = false;

			$field_options = array();

			if ( is_array( $dynamic_select['options'] ) && ! empty( $dynamic_select['options'] ) ) {
				$current_value = '';

				if ( ! empty( $_POST[ 'input_' . $field ] ) ) {
					$current_value = $_POST[ 'input_' . $field ];
				} elseif ( ! empty( $_GET[ 'pods_gf_field_' . $field ] ) ) {
					$current_value = $_GET[ 'pods_gf_field_' . $field ];
				}

				$default_value = '';

				if ( null !== $dynamic_select['default'] ) {
					$default_value = $dynamic_select['default'];
				}

				$choices = self::build_choices( $dynamic_select['options'], $current_value, $default_value );
			}
			elseif ( ! empty( $dynamic_select['pod'] ) ) {
				if ( ! is_object( $dynamic_select['pod'] ) ) {
					$pod = pods( $dynamic_select['pod'], null, false );
				}
				else {
					$pod = $dynamic_select['pod'];
				}

				if ( empty( $pod ) ) {
					continue;
				}

				$params = array(
					'orderby'    => 't.' . $pod->pod_data['field_index'],
					'limit'      => - 1,
					'search'     => false,
					'pagination' => false
				);

				if ( ! empty( $dynamic_select['field_text'] ) ) {
					$params['orderby'] = $dynamic_select['field_text'];
				}

				if ( is_array( $dynamic_select['params'] ) && ! empty( $dynamic_select['params'] ) ) {
					$params = array_merge( $params, $dynamic_select['params'] );
				}

				$pod->find( $params );

				$choices = array();

				while ( $pod->fetch() ) {
					if ( ! empty( $dynamic_select['field_text'] ) ) {
						$option_text = $pod->display( $dynamic_select['field_text'] );
					}
					else {
						$option_text = $pod->index();
					}

					if ( ! empty( $dynamic_select['field_value'] ) ) {
						$option_value = $pod->display( $dynamic_select['field_value'] );
					}
					else {
						$option_value = $pod->id();
					}

					$choices[] = array(
						'text'  => $option_text,
						'value' => $option_value
					);
				}
			}

			// Additional handling for showing an empty choice for fields that are not required.
			if ( empty( $field_obj->isRequired ) && empty( $field_obj->placeholder ) ) {
				if ( 'radio' === $field_obj->type || ( 'entry' !== rgget( 'view' ) && 'select' === $field_obj->type ) ) {
					$needs_empty = true;

					// Check if we have an empty option already.
					foreach ( $choices as $choice ) {
						if ( '' === $choice['value'] ) {
							$needs_empty = false;

							break;
						}
					}

					if ( $needs_empty ) {
						if ( ! empty( $dynamic_select['select_text'] ) ) {
							$empty_text = $dynamic_select['select_text'];
						} else {
							$empty_text = __( 'Select One', 'pods-gravity-forms' );

							if ( 'select' === $field_obj->type ) {
								$empty_text = sprintf( '-- %s --', $empty_text );
							}
						}

						$empty_choice = array(
							'text'  => $empty_text,
							'value' => '',
						);

						// Add empty choice to front of choices list.
						array_unshift( $choices, $empty_choice );
					}
				}
			}

			$choices = apply_filters( 'pods_gf_dynamic_choices_' . $form['id'] . '_' . $field, $choices, $dynamic_select, $field, $form, $dynamic_selects );
			$choices = apply_filters( 'pods_gf_dynamic_choices_' . $form['id'], $choices, $dynamic_select, $field, $form, $dynamic_selects );
			$choices = apply_filters( 'pods_gf_dynamic_choices', $choices, $dynamic_select, $field, $form, $dynamic_selects );

			if ( ! is_array( $choices ) ) {
				continue;
			}

			if ( null !== $dynamic_select['default'] ) {
				$field_obj->defaultValue = $dynamic_select['default'];
			}

			// Remove extra empty choices.
			$empty_choice_found = false;

			foreach ( $choices as $k => $choice ) {
				if ( '' === $choice['value'] ) {
					if ( $empty_choice_found ) {
						unset( $choices[ $k ] );

						continue;
					}

					$empty_choice_found = true;
				}
			}

			$choices = array_values( $choices );

			$field_obj->choices = $choices;

			// Additional handling for checkboxes
			if ( 'checkbox' === $field_obj->type ) {
				$inputs = array();

				$input_id = 0;

				foreach ( $choices as $choice ) {
					$input_id ++;

					// Workaround for GF bug with multiples of 10 (so that 5.1 doesn't conflict with 5.10)
					if ( 0 == $input_id % 10 ) {
						$input_id ++;
					}

					$inputs[] = array(
						'label' => $choice['text'],
						'name'  => '', // not used
						'id'    => $field . '.' . $input_id
					);
				}

				$field_obj->inputs = $inputs;

				if ( is_admin() && 'gf_edit_forms' === pods_v( 'page' ) && 'settings' === pods_v( 'view' ) && 'pods-gravity-forms' === pods_v( 'subview' ) ) {
					$field_obj->choices = array();
					$field_obj->inputs  = array();
				}
			}

			$form['fields'][$field_key] = $field_obj;
		}

		return $form;

	}

	/**
	 * Prepopulate a GF Form
	 *
	 * @param array      $form        GF Form array
	 * @param bool       $ajax        Whether the form was submitted using AJAX
	 * @param array|null $prepopulate The prepopulate array
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_prepopulate( $form, $ajax = false, $prepopulate = null ) {

		if ( null === $prepopulate ) {
			$prepopulate = self::$prepopulate;
		}

		if ( empty( $prepopulate ) ) {
			return $form;
		}

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $form;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		$field_keys = array();

		foreach ( $form['fields'] as $k => $field ) {
			$field_keys[(string) $field['id']] = $k;
		}

		$prepopulate = array_merge(
			array(
				'form'   => $form['id'],

				'pod'    => null,
				'id'     => null,
				'fields' => array()
			),
			$prepopulate
		);

		if ( $prepopulate['form'] != $form['id'] ) {
			return $form;
		}

		$pod = $prepopulate['pod'];
		$id  = $prepopulate['id'];

		if ( ! is_array( $pod ) && ! empty( $pod ) ) {
			if ( ! is_object( $pod ) ) {
				$pod = pods( $pod, $id );
			}
			elseif ( $pod->id != $id ) {
				$pod->fetch( $id );
			}
		}
		else {
			if ( empty( $prepopulate['fields'] ) ) {
				$fields = array();

				foreach ( $form['fields'] as $field ) {
					$fields[$field['id']] = array(
						'gf_field' => $field['id'],
						'field'    => $field['id']
					);
				}

				$prepopulate['fields'] = $fields;
			}

			if ( ! empty( $id ) ) {
				$pod = GFAPI::get_entry( $id );
			}
			else {
				$pod = array();
				$id  = 0;
			}
		}

		$basic_array = isset( $prepopulate['fields'][0] );

		// @todo Need to know list of Pod field >> GF field for name/address/etc fields.

		// Prepopulate values
		foreach ( $prepopulate['fields'] as $field => $field_options ) {
			if ( $basic_array && is_string( $field_options ) ) {
				$field_options = array(
					'gf_field' => $field_options,
					'field'    => $field_options,
					'value'    => null
				);
			}
			else {
				$field = (string) $field;

				$field_options = array_merge(
					array(
						'gf_field' => $field,
						'field'    => $field_options,
						'value'    => null
					),
					( is_array( $field_options ) ? $field_options : array() )
				);
			}

			if ( ! empty( $field_options['gf_field'] ) ) {
				$field = (string) $field_options['gf_field'];
			}

			$full_field = $field;

			$field_expanded = array(
				$field,
				'',
			);

			if ( false !== strpos( $field, '.' ) ) {
				$field_expanded = explode( '.', $field );

				$field = $field_expanded[0];
			}

			// No GF field set
			if ( empty( $field ) || ! isset( $field_keys[$field] ) ) {
				continue;
			}

			// No Pod field set
			if ( empty( $field_options['field'] ) || is_array( $field_options['field'] ) ) {
				continue;
			}

			$field_key = $field_keys[ $field ];

			$gf_field = $form['fields'][ $field_key ];

			// Allow for value to be overridden by existing prepopulation or callback
			$value_override = $field_options['value'];

			if ( null === $value_override && isset( $gf_field->allowsPrepopulate ) && $gf_field->allowsPrepopulate ) {
				// @todo handling for field types that have different $_POST input names

				if ( 'checkbox' === $gf_field->type ) {
					if ( isset( $_GET[ $gf_field->name ] ) ) {
						$value_override = $_GET[ $gf_field->name ];

						foreach ( $gf_field->choices as $k => $choice ) {
							$gf_field->choices[ $k ]['isSelected'] = false;

							if ( ( ! is_array( $value_override ) && $choice['value'] == $value_override ) || ( is_array( $value_override ) && in_array( $choice['value'], $value_override ) ) ) {
								$gf_field->choices[ $k ]['isSelected'] = true;

								break;
							}
						}
					}
				} elseif ( isset( $gf_field->inputName ) && isset( $_GET[ $gf_field->inputName ] ) ) {
					$value_override = $_GET[ $gf_field->inputName ];
				}
			}

			$autopopulate = true;

			if ( null === $value_override ) {
				$value = $value_override;

				$pod_field_type = '';

				if ( is_object( $pod ) ) {
					$pod_field_type = $pod->fields( $field_options['field'], 'type' );
				}

				if ( is_array( $pod ) && isset( $pod[ $field_options['field'] ] ) ) {
					$value_override = $pod[ $field_options['field'] ];
				}

				if ( $pod_field_type ) {
					if ( is_object( $pod ) ) {
						$value_override = $pod->field( $field_options['field'], array( 'output' => 'ids' ) );
					} elseif ( is_array( $pod ) && null !== $field_key && 'checkbox' === $gf_field->type ) {
						$value_override = array();

						$items   = 0;
						$counter = 1;

						$total_choices = count( $gf_field->choices );

						while ( $items < $total_choices ) {
							$field_key_counter = $field_options['field'] . '.' . $counter;

							if ( isset( $pod[ $field_key_counter ] ) ) {
								$choice_counter = 1;

								foreach ( $gf_field->choices as $k => $choice ) {
									$gf_field->choices[ $k ]['isSelected'] = false;

									if ( (string) $choice['value'] === (string) $pod[ $field_key_counter ] || $counter == $choice_counter ) {
										$gf_field->choices[ $k ]['isSelected'] = true;

										$value_override[ 'input_' . pods_v( 'id', $choice, $field_options['field'] . '.1', true ) ] = $choice['value'];
									}

									$choice_counter ++;

									if ( $choice_counter % 10 ) {
										$choice_counter ++;
									}
								}
							}

							$counter ++;

							if ( $counter % 10 ) {
								$counter ++;
							}

							$items ++;
						}

						$autopopulate = false;
					}

					$date_time_types = array(
						'date',
						'datetime',
					);

					$empty_values = array(
						'0000-00-00',
						'0000-00-00 00:00:00',
					);

					if ( in_array( $pod_field_type, $date_time_types, true ) && in_array( $value_override, $empty_values, true ) ) {
						$value_override = '';
					} elseif ( ! empty( $value_override ) ) {
						if ( 'list' === $gf_field->type ) {
							if ( is_string( $value_override ) ) {
								$list = @json_decode( $value_override, true );

								if ( ! $list || ! is_array( $list ) ) {
									$list = maybe_unserialize( $value_override );
								}

								$value_override = array();

								if ( $list && is_array( $list ) ) {
									$list = array_map( 'array_values', $list );
									$list = call_user_func_array( 'array_merge', $list );

									$value_override = $list;
								}
							} elseif ( 'pick' === $pod_field_type ) {
								$related_pod = false;

								if ( is_object( $pod ) ) {
									$related_pod = $pod->field( $field_options['field'], array( 'output' => 'find' ) );
								}

								$value_override = array();

								if ( $related_pod && is_a( $related_pod, 'Pods' ) && is_a( $gf_field, 'GF_Field_List' ) ) {
									$columns = wp_list_pluck( $gf_field->choices, 'text' );

									$columns = self::match_pod_fields_to_list_columns( $related_pod, $columns, $form, $gf_field );

									while ( $related_pod->fetch() ) {
										foreach ( $columns as $column ) {
											$column_value = $related_pod->field( $column, array( 'output' => 'ids' ) );

											$value_override[] = $column_value;
										}
									}
								}
							}
						} elseif ( 'time' === $gf_field->type && ! empty( $value_override ) ) {
							$format = empty( $gf_field->timeFormat ) ? '12' : esc_attr( $gf_field->timeFormat );

							if ( '12' === $format && preg_match( '/^(\d{1,2}):(\d{1,2})/', $value_override, $matches ) && 12 < (int) $matches[1] ) {
								$hour = (int) $matches[1];
								$hour -= 12;

								if ( $hour < 10 ) {
									$hour = '0' . $hour;
								}

								$value_override = sprintf( '%s:%s pm', $hour, $matches[2] );
							}
						} elseif ( 'address' === $gf_field->type ) {
							// @todo Figure out what to do for address values
						} elseif ( 'name' === $gf_field->type ) {
							// @todo This is beginning logic to setup mapping for each input, but needs value overrides.
							foreach ( $gf_field->inputs as $k => $input ) {
								$input['name'] = 'pods_gf_field_' . str_replace( '.', '_', $input['id'] );

								$gf_field->inputs[ $k ] = $input;
							}
						} elseif ( 'chainedselect' === $gf_field->type ) {
							// @todo Figure out what to do for chained select values
						} elseif ( 'checkbox' === $gf_field->type ) {
							$values = $value_override;

							$value_override = array();

							$choice_id = 1;

							foreach ( $gf_field->choices as $k => $choice ) {
								$gf_field->choices[$k]['isSelected'] = false;

								$is_selected = false;

								if ( 'boolean' === $pod_field_type && 1 === (int) $values && ! empty( $choice['value'] ) ) {
									$is_selected = true;
								} elseif ( ( ! is_array( $values ) && (string) $choice['value'] === (string) $values )
									|| ( is_array( $values ) && in_array( $choice['value'], $values ) ) ) {
									$is_selected = true;
								}

								if ( $is_selected ) {
									$gf_field->choices[$k]['isSelected'] = true;

									if ( ! empty( $choice['id'] ) ) {
										$choice_id = $choice['id'];
									}

									$value_override[ 'input_' . $choice_id ] = $choice['value'];
								}

								$choice_id ++;
							}

							$autopopulate = false;
						}
					}
				}
			}

			if ( null !== $field_key ) {
				$gf_field->allowsPrepopulate = $autopopulate;
				$gf_field->inputName         = 'pods_gf_field_' . $field;
			}

			$form['fields'][ $field_key ] = $gf_field;

			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form['id'] . '_' . $field, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$value_override = apply_filters( 'pods_gf_pre_populate_value_' . $form['id'], $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$value_override = apply_filters( 'pods_gf_pre_populate_value', $value_override, $field, $field_options, $form, $prepopulate, $pod );

			if ( null !== $value_override ) {
				if ( is_array( $value_override ) && 'list' === $gf_field->type ) {
					$choices = $gf_field->choices;

					$value_override_chunked = array_chunk( $value_override, count( $choices ) );

					foreach ( $value_override_chunked as $k => $v ) {
						$value_override_chunked[ $k ] = implode( '|', $v );
					}

					$value_override = implode( ',', $value_override_chunked );
				}

				$_GET[ 'pods_gf_field_' . $field ] = pods_slash( $value_override );
			}

			$post_value_override = null;
			$post_value_override = apply_filters( 'pods_gf_field_value_' . $form['id'] . '_' . $field, $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$post_value_override = apply_filters( 'pods_gf_field_value_' . $form['id'], $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );
			$post_value_override = apply_filters( 'pods_gf_field_value', $post_value_override, $value_override, $field, $field_options, $form, $prepopulate, $pod );

			if ( null !== $post_value_override ) {
				if ( is_array( $post_value_override ) && 'list' === $gf_field->type ) {
					$post_value_override = maybe_serialize( $post_value_override );
				}

				$_POST[ 'input_' . $field ] = pods_slash( $post_value_override );
			}
		}

		return $form;

	}

	/**
	 * Setup Confirmation for a form
	 *
	 * @param int   $form_id GF Form ID
	 * @param array $options Confirmation options
	 */
	public static function confirmation( $form_id, $options = array() ) {

		self::$confirmation[$form_id] = $options;

		if ( ! has_filter( 'gform_confirmation_' . $form_id, array( 'Pods_GF', 'gf_confirmation' ) ) ) {
			add_filter( 'gform_confirmation_' . $form_id, array( 'Pods_GF', 'gf_confirmation' ), 10, 4 );
		}

	}

	/**
	 * Submit Redirect URL customization
	 *
	 * @param array $form         GF Form array
	 * @param array $confirmation Confirmation array
	 */
	public static function gf_confirmation( $confirmation, $form, $lead, $ajax = false, $return_confirmation = false ) {

		$gf_confirmation = pods_v( $form['id'], self::$confirmation, array(), true );

		if ( ! empty( $gf_confirmation ) ) {
			$confirmation = $gf_confirmation;

			if ( ! is_array( $confirmation ) || empty( $confirmation['pods_gf'] ) ) {
				if ( ! is_array( $confirmation ) ) {
					if ( ( false !== strpos( $confirmation, '://' ) && strpos( $confirmation, '://' ) < 6 ) || 0 === strpos( $confirmation, '/' ) || 0 === strpos( $confirmation, '?' ) ) {
						$confirmation = array(
							'url'  => $confirmation,
							'type' => 'redirect'
						);
					}
					else {
						$confirmation = array(
							'message' => $confirmation,
							'type'    => 'message'
						);
					}
				}

				if ( isset( $confirmation['url'] ) ) {
					if ( 0 === strpos( $confirmation['url'], '?' ) ) {
						$path                = explode( '?', $_SERVER['REQUEST_URI'] );
						$path                = explode( '#', $path[0] );
						$confirmation['url'] = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $path[0] . $confirmation['url'];
					}

					$confirmation['type'] = 'redirect';
				}
				elseif ( isset( $confirmation['message'] ) ) {
					$confirmation['type'] = 'message';
				}

				$confirmation['isDefault'] = true;
				$confirmation['pods_gf']   = true;

				if ( $return_confirmation ) {
					return $confirmation;
				}

				if ( $confirmation['type'] == 'message' ) {
					$default_anchor = GFCommon::has_pages( $form ) ? 1 : 0;
					$anchor         = apply_filters( 'gform_confirmation_anchor_' . $form['id'], apply_filters( 'gform_confirmation_anchor', $default_anchor ) ) ? '<a id="gf_' . $form['id'] . '" name="gf_' . $form['id'] . '" class="gform_anchor" ></a>' : '';
					$nl2br          = rgar( $confirmation, 'disableAutoformat' ) ? false : true;
					$cssClass       = rgar( $form, 'cssClass' );

					if ( empty( $confirmation['message'] ) ) {
						$confirmation = $anchor . ' ';
					}
					else {
						$confirmation = $anchor
							. '<div id="gform_confirmation_wrapper_' . $form['id'] . '" class="gform_confirmation_wrapper ' . $cssClass . '">'
							. '<div id="gforms_confirmation_message" class="gform_confirmation_message_' . $form['id'] . '">'
							. GFCommon::replace_variables( $confirmation['message'], $form, $lead, false, true, $nl2br )
							. '</div></div>';
					}
				}
				else {
					if ( ! empty( $confirmation['pageId'] ) ) {
						$url = get_permalink( $confirmation['pageId'] );
					}
					else {
						$gf_to_pods_id = 0;
						$gf_to_pods_permalink = '';

						if ( ! empty( self::$gf_to_pods_id[ $form['id'] ] ) ) {
							$gf_to_pods_id = self::$gf_to_pods_id[ $form['id'] ];
						}

						if ( ! empty( self::$gf_to_pods_id[ $form['id'] . '_permalink' ] ) ) {
							$gf_to_pods_permalink = self::$gf_to_pods_id[ $form['id'] . '_permalink' ];
						}

						$confirmation['url'] = str_replace( '{@gf_to_pods_id}', $gf_to_pods_id, $confirmation['url'] );
						$confirmation['url'] = str_replace( '{@gf_to_pods_permalink}', $gf_to_pods_permalink, $confirmation['url'] );

						$url          = trim( GFCommon::replace_variables( trim( $confirmation['url'] ), $form, $lead, false, true ) );
						$url_info     = parse_url( $url );
						$query_string = trim( $url_info['query'] );

						if ( ! empty( $confirmation['queryString'] ) ) {
							$dynamic_query = trim( GFCommon::replace_variables( trim( $confirmation['queryString'] ), $form, $lead, true ) );

							if ( ! empty( $dynamic_query ) ) {
								if ( ! empty( $url_info['query'] ) ) {
									$query_string .= '&';
								}

								$query_string .= $dynamic_query;
							}
						}

						if ( ! empty( $url_info['fragment'] ) ) {
							$query_string .= '#' . $url_info['fragment'];
						}

						$url = $url_info['scheme'] . '://' . $url_info['host'];

						if ( ! empty( $url_info['port'] ) ) {
							$url .= ':' . $url_info['port'];
						}

						$url .= rgar( $url_info, 'path' );

						if ( ! empty( $query_string ) ) {
							$url .= '?' . $query_string;
						}
					}

					if ( headers_sent() || $ajax ) {
						//Perform client side redirect for AJAX forms, of if headers have already been sent
						$confirmation = self::gf_get_js_redirect_confirmation( $url, $ajax );
					}
					else {
						$confirmation = array( 'redirect' => $url );
					}
				}
			} elseif ( ! empty( $confirmation['redirect'] ) ) {
				$gf_to_pods_id = 0;
				$gf_to_pods_permalink = '';

				if ( ! empty( self::$gf_to_pods_id[ $form['id'] ] ) ) {
					$gf_to_pods_id = self::$gf_to_pods_id[ $form['id'] ];
				}

				if ( ! empty( self::$gf_to_pods_id[ $form['id'] . '_permalink' ] ) ) {
					$gf_to_pods_permalink = self::$gf_to_pods_id[ $form['id'] . '_permalink' ];
				}

				$confirmation['redirect'] = str_replace( '{@gf_to_pods_id}', $gf_to_pods_id, $confirmation['redirect'] );
				$confirmation['redirect'] = str_replace( '{@gf_to_pods_permalink}', $gf_to_pods_permalink, $confirmation['redirect'] );
			}
		}

		// @todo Is this needed still?
		/*if ( ! is_array( $confirmation ) ) {
			$confirmation = GFCommon::gform_do_shortcode( $confirmation ); //enabling shortcodes
		}*/

		return $confirmation;

	}

	/**
	 * A public access version of GFFormDisplay::get_js_redirect_confirmation
	 *
	 * @param string $url
	 * @param bool $ajax
	 *
	 * @return string
	 */
	public static function gf_get_js_redirect_confirmation( $url, $ajax ) {

		$confirmation = "<script type=\"text/javascript\">" . apply_filters( "gform_cdata_open", "" ) . " function gformRedirect(){document.location.href='$url';}";

		if ( ! $ajax ) {
			$confirmation .= "gformRedirect();";
		}

		$confirmation .= apply_filters( "gform_cdata_close", "" ) . "</script>";

		return $confirmation;

	}

	/**
	 * Enable Markdown Syntax for HTML fields
	 *
	 * @param array $form     GF Form array
	 * @param bool  $ajax     Whether the form was submitted using AJAX
	 * @param array $markdown Markdown options
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_markdown( $form, $ajax = false, $markdown = null ) {

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $form;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		if ( ! function_exists( 'Markdown' ) ) {
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

		foreach ( $form['fields'] as $k => $field ) {
			if ( 'html' == $field['type'] ) {
				$content = $field['content'];

				preg_match_all( "/\{([\w\:\.\_\-]*?)\}/", $content, $merge_tags, PREG_SET_ORDER );

				// Sanitize merge tags (from Markdown)
				foreach ( $merge_tags as $merge_tag_match ) {
					$merge_tag = $merge_tag_match[0];

					$merge_tag_sanitized = str_replace( $sanitize_from_markdown, $temporary_sanitization, $merge_tag );

					$content = str_replace( $merge_tag, $merge_tag_sanitized, $content );
				}

				// Run Markdown
				$content = Markdown( $content );

				// Unsanitize merge tags
				foreach ( $merge_tags as $merge_tag_match ) {
					$merge_tag = $merge_tag_match[0];

					$merge_tag_sanitized = str_replace( $sanitize_from_markdown, $temporary_sanitization, $merge_tag );

					$content = str_replace( $merge_tag_sanitized, $merge_tag, $content );
				}

				$form['fields'][$k]['content'] = $content;
			}
		}

		return $form;

	}

	/**
	 * Prepopulate a form with values from a Pod item
	 *
	 * @static
	 *
	 * @param int         $form_id GF Form ID
	 * @param string|Pods $pod     Pod name (or Pods object)
	 * @param int         $id      Pod item ID
	 * @param array       $fields  Field mapping to prepopulate from
	 */
	public static function read_only( $form_id, $fields = true, $exclude_fields = array() ) {
		self::$read_only = array(
			'form'           => $form_id,

			'fields'         => $fields,
			'exclude_fields' => $exclude_fields
		);

		$class = get_class();

		if ( ! has_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_read_only' ) ) ) {
			add_filter( 'gform_pre_render_' . $form_id, array( $class, 'gf_read_only' ), 10, 2 );

			add_filter( 'gform_pre_submission_filter_' . $form_id, array( $class, 'gf_read_only_pre_submission' ), 10, 1 );
		}
	}

	/**
	 * Enable Read Only for fields
	 *
	 * @param array $form      GF Form array
	 * @param bool  $ajax      Whether the form was submitted using AJAX
	 * @param array $read_only Read Only options
	 *
	 * @return array $form GF Form array
	 */
	public static function gf_read_only( $form, $ajax = false, $read_only = null ) {

		if ( null === $read_only ) {
			$read_only = self::$read_only;
		}
		else {
			self::$read_only = $read_only;
		}

		if ( empty( $read_only ) ) {
			return $form;
		}

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $form;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		$field_keys = array();

		foreach ( $form['fields'] as $k => $field ) {
			$field_keys[(string) $field['id']] = $k;
		}

		$read_only = array_merge(
			array(
				'form'           => $form['id'],

				'fields'         => array(),
				'exclude_fields' => array()
			),
			$read_only
		);

		self::$read_only = $read_only;

		if ( $read_only['form'] != $form['id'] || false === $read_only['fields'] ) {
			return $form;
		}

		if ( ! has_filter( 'gform_field_input', array( 'Pods_GF', 'gf_field_input_read_only' ) ) ) {
			add_filter( 'gform_field_input', array( 'Pods_GF', 'gf_field_input_read_only' ), 20, 5 );
		}

		if ( is_array( $read_only['fields'] ) && ! empty( $read_only['fields'] ) ) {
			foreach ( $read_only['fields'] as $field => $field_options ) {
				if ( is_array( $field_options ) && isset( $field_options['gf_field'] ) ) {
					$field = $field_options['gf_field'];
				}

				if ( is_array( $read_only['exclude_fields'] ) && ! empty( $read_only['exclude_fields'] ) && in_array( (string) $field, $read_only['exclude_fields'] ) ) {
					continue;
				}

				$gf_field = $form['fields'][$field_keys[$field]];

				$form['fields'][$field_keys[$field]]['isRequired'] = false;

				if ( 'list' == GFFormsModel::get_input_type( $gf_field ) ) {
					$columns = ( is_array( $gf_field->choices ) ? $gf_field->choices : array( array() ) );

					$col_number = 1;

					foreach ( $columns as $column ) {
						if ( ! has_filter( 'gform_column_input_content_' . $form['id'] . '_' . $field . '_' . $col_number, array( 'Pods_GF', 'gf_field_column_read_only' ) ) ) {
							add_filter( 'gform_column_input_content_' . $form['id'] . '_' . $field . '_' . $col_number, array( 'Pods_GF', 'gf_field_column_read_only' ), 20, 6 );
						}

						$col_number ++;
					}
				}
			}
		}
		else {
			foreach ( $form['fields'] as $k => $field ) {
				if ( is_array( $read_only['exclude_fields'] ) && ! empty( $read_only['exclude_fields'] ) && in_array( (string) $field['id'], $read_only['exclude_fields'] ) ) {
					continue;
				}

				$form['fields'][$k]['isRequired'] = false;

				if ( 'list' == GFFormsModel::get_input_type( $field ) ) {
					$columns = ( is_array( $field['choices'] ) ? $field['choices'] : array( array() ) );

					$col_number = 1;

					foreach ( $columns as $column ) {
						if ( ! has_filter( 'gform_column_input_content_' . $form['id'] . '_' . $field['id'] . '_' . $col_number, array( 'Pods_GF', 'gf_field_column_read_only' ) ) ) {
							add_filter( 'gform_column_input_content_' . $form['id'] . '_' . $field['id'] . '_' . $col_number, array( 'Pods_GF', 'gf_field_column_read_only' ), 20, 6 );
						}

						$col_number ++;
					}
				}
			}
		}

		return $form;

	}

	/**
	 * Override GF Field input to make read only
	 *
	 * @param string $input_html Input HTML override
	 * @param array  $field      GF Field array
	 * @param mixed  $value      Field value
	 * @param int    $lead_id    GF Lead ID
	 * @param int    $form_id    GF Form ID
	 *
	 * @return string Input HTML override
	 */
	public static function gf_field_input_read_only( $input_html, $field, $value, $lead_id, $form_id ) {

		if ( ! isset( self::$actioned[$form_id] ) ) {
			self::$actioned[$form_id] = array();
		}

		// Get / set $form for pagination info
		if ( ! isset( self::$actioned[$form_id]['form'] ) ) {
			$form = GFFormsModel::get_form_meta( $form_id );

			self::$actioned[$form_id]['form'] = $form;
		}
		else {
			$form = self::$actioned[$form_id]['form'];
		}

		if ( ! isset( self::$actioned[$form_id][__FUNCTION__] ) ) {
			self::$actioned[$form_id][__FUNCTION__] = 0;
		}

		$read_only = self::$read_only;

		if ( empty( $read_only ) || ! isset( $read_only['form'] ) || $read_only['form'] != $form_id ) {
			return $input_html;
		}

		if ( ! isset( $read_only['fields'] ) || false === $read_only['fields'] || ( is_array( $read_only['fields'] ) && ! in_array( (string) $field['id'], $read_only['fields'] ) ) ) {
			return $input_html;
		}

		if ( isset( $read_only['exclude_fields'] ) && is_array( $read_only['exclude_fields'] ) && ! empty( $read_only['exclude_fields'] ) && in_array( (string) $field['id'], $read_only['exclude_fields'] ) ) {
			return $input_html;
		}

		$last_page = self::$actioned[$form_id][__FUNCTION__];

		$non_read_only = array(
			'hidden',
			'captcha',
			'page',
			'section',
			'honeypot',
			'list'
		);

		$field_type = GFFormsModel::get_input_type( $field );

		if ( in_array( $field_type, $non_read_only ) ) {
			return $input_html;
		}

		$page_header = '';

		if ( isset( $field['pageNumber'] ) && 0 < $field['pageNumber'] && $last_page != $field['pageNumber'] ) {
			self::$actioned[$form_id][__FUNCTION__] = $field['pageNumber'];

			$page_header = '<h3 class="gf-page-title">' . $form['pagination']['pages'][( $field['pageNumber'] - 1 )] . '</h3>';
		}

		if ( 'html' == $field_type ) {
			$input_html = IS_ADMIN ? "<img class='gfield_html_block' src='" . GFCommon::get_base_url() . "/images/gf-html-admin-placeholder.jpg' alt='HTML Block'/>" : $field['content'];
			$input_html = GFCommon::replace_variables_prepopulate( $input_html ); //adding support for merge tags
			$input_html = do_shortcode( $input_html ); //adding support for shortcodes

			return $page_header . $input_html;
		}

		$input_field_name = 'input_' . $field['id'];

		if ( is_array( $value ) || ! empty( $field[ 'choices' ] ) ) {
			$labels = array();
			$values = array();

			if ( '' === $value || array( '' ) === $value ) {
				$value = array();
			}
			else {
				$value = (array) $value;
			}

			if ( isset( $field['choices'] ) ) {
				$choice_number = 1;

				foreach ( $field['choices'] as $choice ) {
					//hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
					if( $choice_number % 10 == 0 ) {
						$choice_number ++;
					}

					if ( in_array( $choice['value'], $value ) || ( empty( $value ) && $choice['isSelected'] ) ) {
						$values[$choice_number] = $choice['value'];
						$labels[]               = $choice['text'];
					}

					$choice_number ++;
				}
			}
			else {
				$choice_number = 1;

				foreach ( $value as $val ) {
					//hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
					if( $choice_number % 10 == 0 ) {
						$choice_number ++;
					}

					$values[$choice_number] = $val;

					$choice_number ++;
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

				if ( 'checkbox' == $field['type'] ) {
					$input_field_name_choice .= '.' . $choice_number;
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
	 * @param string $input      Input HTML
	 * @param array  $input_info GF List Field info (for select choices)
	 * @param array  $field      GF Field array
	 * @param string $text       GF List Field column name
	 * @param mixed  $value      Field value
	 * @param int    $form_id    GF Form ID
	 *
	 * @return string Input HTML override
	 */
	public static function gf_field_column_read_only( $input, $input_info, $field, $text, $value, $form_id ) {

		$read_only = self::$read_only;

		if ( empty( $read_only ) || $read_only['form'] != $form_id ) {
			return $input;
		}

		if ( false === $read_only['fields'] || ( is_array( $read_only['fields'] ) && ! in_array( $field['id'], $read_only['fields'] ) ) ) {
			return $input;
		}

		if ( empty( $read_only ) || ! isset( $read_only['form'] ) || $read_only['form'] != $form_id ) {
			return $input;
		}

		if ( ! isset( $read_only['fields'] ) || false === $read_only['fields'] || ( is_array( $read_only['fields'] ) && ! in_array( (string) $field['id'], $read_only['fields'] ) ) ) {
			return $input;
		}

		if ( isset( $read_only['exclude_fields'] ) && is_array( $read_only['exclude_fields'] ) && ! empty( $read_only['exclude_fields'] ) && in_array( (string) $field['id'], $read_only['exclude_fields'] ) ) {
			return $input;
		}

		$input_field_name = 'input_' . $field['id'] . '[]';

		$label = $value;

		if ( isset( $input_info['choices'] ) ) {
			foreach ( $input_info['choices'] as $choice ) {
				if ( $value == $choice['value'] ) {
					$label = $choice['text'];

					break;
				}
			}
		}
		elseif ( false !== strpos( $input, 'type="checkbox"' ) || false !== strpos( $input, 'type=\'checkbox\'' ) ) {
			$label = ( $value ? __( 'Yes', 'pods-gravity-forms' ) : __( 'No', 'pods-gravity-forms' ) );
		}
		elseif ( false !== strpos( $input, 'type="date"' ) || false !== strpos( $input, 'type=\'date\'' ) ) {
			$label = date_i18n( 'm/d/Y', strtotime( $value ) );
		}

		$input = esc_html( $label );
		$input .= '<input type="text" name="' . $input_field_name . '" value="' . esc_attr( $value ) . '" readonly="readonly" style="display:none;" class="hidden" />';

		return $input;

	}

	/**
	 * Override form fields that are read only, to not save, in GF Action: gform_pre_submission_filter_{$form_id}
	 *
	 * @param array $form GF Form array
	 */
	public static function gf_read_only_pre_submission( $form ) {

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $form;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		$read_only = self::$read_only;

		if ( empty( $read_only ) || ! isset( $read_only['form'] ) || $read_only['form'] != $form['id'] || false === $read_only['fields'] ) {
			return $form;
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		foreach ( $form['fields'] as $k => $field ) {
			// Exclude certain fields
			if ( isset( $read_only['exclude_fields'] ) && is_array( $read_only['exclude_fields'] ) && ! empty( $read_only['exclude_fields'] ) && in_array( (string) $field['id'], $read_only['exclude_fields'] ) ) {
				$form['fields'][$k]['displayOnly'] = false;
			}
			// Don't save read only fields
			elseif ( ! is_array( $read_only['fields'] ) || in_array( $field['id'], $read_only['fields'] ) ) {
				$form['fields'][$k]['displayOnly'] = true;
			}
		}

		return $form;

	}

	/**
	 * Action handler for Gravity Forms: gform_pre_render_{$form_id}
	 *
	 * @param array $form GF Form array
	 * @param bool  $ajax Whether the form was submitted using AJAX
	 *
	 * @return array $form GF Form array
	 */
	public function _gf_pre_render( $form, $ajax = false ) {
		$form = $this->setup_form( $form );

		if ( empty( $this->options ) ) {
			return $form;
		}

		return $form;
	}

	/**
	 * Action handler for Gravity Forms: gform_get_form_filter_{$form_id}
	 *
	 * @param string $form_string Form HTML
	 * @param array  $form        GF Form array
	 *
	 * @return string Form HTML
	 */
	public function _gf_get_form_filter( $form_string, $form ) {
		if ( isset( self::$actioned[ $form['id'] ] ) && in_array( __FUNCTION__, self::$actioned[ $form['id'] ], true ) ) {
			return $form_string;
		} elseif ( ! isset( self::$actioned[ $form['id'] ] ) ) {
			self::$actioned[ $form['id'] ] = array();
		}

		self::$actioned[ $form['id'] ][] = __FUNCTION__;

		// Cleanup $_GET
		if ( $_GET ) {
			foreach ( $_GET as $key => $value ) {
				if ( 0 === strpos( $key, 'pods_gf_field_' ) ) {
					unset( $_GET[ $key ] );
				}
			}
		}

		return $form_string;

	}

	/**
	 * Match pod fields to List field columns.
	 *
	 * @param Pods     $pod      Pods object.
	 * @param array    $columns  List of column names from a List field type.
	 * @param array    $form     Form data.
	 * @param GF_Field $gf_field Field data.
	 *
	 * @return array
	 */
	public static function match_pod_fields_to_list_columns( $pod, $columns, $form, $gf_field ) {

		$related_fields = $pod->fields();

		if ( $related_fields ) {
			$related_fields = wp_list_pluck( $related_fields, 'label', 'name' );

			foreach ( $columns as $k => $column ) {
				$field_found = array_search( $column, $related_fields, true );

				if ( $field_found ) {
					$columns[ $k ] = $field_found;
				}
			}
		}

		/**
		 * Filter list field columns for relationship field mapping purposes.
		 *
		 * @param array    $columns  List field columns.
		 * @param array    $form     GF form data.
		 * @param GF_Field $gf_field GF field data.
		 * @param Pods     $pod      Pods object.
		 *
		 * @since 1.4
		 */
		$columns = (array) gf_apply_filters( array( 'pods_gf_field_columns_mapping', $form['id'], $gf_field->id ), $columns, $form, $gf_field, $pod );

		return $columns;

	}

	/**
	 * Get GF field value
	 *
	 * @param mixed $value
	 * @param array $params
	 *
	 * @return mixed
	 */
	public static function get_gf_field_value( $value, $params ) {

		static $cached_field_value = array();

		$params = array_merge( array(
			'gf_field'         => null,
			'gf_field_options' => array(),
			'field'            => null,
			'field_options'    => array(),
			'pod'              => null,
			'form'             => null,
			'entry'            => null,
			'options'          => array(),
			'handle_files'     => false,
		), $params );

		$gf_field         = $params['gf_field'];
		$gf_field_options = $params['gf_field_options'];
		$full_field       = $params['field'];
		$field_options    = $params['field_options'];
		$pod              = $params['pod'];
		$form             = $params['form'];
		$entry            = $params['entry'];
		$options          = $params['options'];
		$handle_files     = $params['handle_files'];

		if ( empty( $entry ) && ! empty( $options['entry'] ) ) {
			$entry = $options['entry'];
		}

		if ( is_array( $gf_field ) ) {
			/**
			 * @var $fields GF_Field[]
			 */
			$fields = $form['fields'];

			$gf_fields = array();

			foreach ( $fields as $field ) {
				if ( (string) $field->id === (string) $gf_field['id'] ) {
					$gf_field = $field;

					break;
				}
			}

			if ( is_array( $gf_field ) ) {
				return $value;
			}
		}

		if ( empty( $full_field ) ) {
			$full_field = $gf_field->id;
		}

		$cache_key = false;

		if ( ! empty( $entry ) ) {
			$cache_key = $form['id'] . ':' . $entry['id'] . ':' . $full_field;

			if ( ! empty( $field_options['id'] ) ) {
				$cache_key .= ':' . $field_options['id'];
			}

			if ( isset( $cached_field_value[ $cache_key ] ) ) {
				return $cached_field_value[ $cache_key ]['value'];
			}
		}

		if ( null === $value ) {
			if ( ! empty( $entry ) && isset( $entry[ $full_field ] ) ) {
				$value = rgar( $entry, $full_field );
			} else {
				$forced_is_submit = false;

				$tmp_post = $_POST;

				if ( empty( $_POST[ 'is_submit_' . $form['id'] ] ) ) {
					// We need to force is_submit_{$form_id} on.
					$forced_is_submit = true;

					$_POST[ 'is_submit_' . $form['id'] ] = true;

					if ( ! empty( $gf_field->inputs ) && is_array( $gf_field->inputs ) ) {
						// Handle multi input fields.
						foreach ( $entry as $entry_field => $entry_value ) {
							if ( 0 === strpos( $entry_field, $gf_field->id . '.' ) ) {
								$new_entry_field = str_replace( '.', '_', $entry_field );

								$_POST[ 'input_' . $entry_field ]     = $entry_value;
								$_POST[ 'input_' . $new_entry_field ] = $entry_value;
							}
						}
					}
				}

				$value = GFFormsModel::get_field_value( $gf_field );

				if ( $forced_is_submit ) {
					unset( $_POST[ 'is_submit_' . $form['id'] ] );
				}

				$_POST = $tmp_post;
			}
		}

		if ( is_array( $value ) && ! empty( $gf_field->inputs ) && isset( $value[ $full_field ] ) ) {
			$value = $value[ $full_field ];
		}

		if ( 'multiselect' === $gf_field->type && ! is_array( $value ) && ! empty( $value ) && 0 === strpos( $value, '[' ) ) {
			$check_value = json_decode( $value );

			if ( is_array( $check_value ) ) {
				$value = $check_value;
			}
		}

		if ( 'list' === $gf_field->type && ! is_array( $value ) && ! empty( $value ) ) {
			$value = maybe_unserialize( $value );
		}

		if ( in_array( $gf_field->type, array( 'post_category', 'post_title', 'post_content', 'post_excerpt', 'post_tags', 'post_custom_field', 'post_image' ) ) ) {
			// Block new post being created in GF
			add_filter( 'gform_disable_post_creation_' . $form['id'], '__return_true' );
		}

		if ( in_array( $gf_field->type, array( 'post_category', 'post_tags' ), true ) && ! is_array( $value ) ) {
			$value = array(
				$value,
			);
		} elseif ( ! empty( $gf_field->enableEnhancedUI ) && is_string( $value ) ) {
			$json_test = json_decode( $value );

			if ( is_array( $json_test ) ) {
				$value = $json_test;
			}
		}

		if ( in_array( $gf_field->type, array( 'name' ), true ) && is_array( $value ) ) {
			$value = implode( ' ', array_filter( $value ) );
		} elseif ( in_array( $gf_field->type, array( 'email' ), true ) && is_array( $value ) ) {
			$value = current( $value );
		} elseif ( in_array( $gf_field->type, array( 'checkbox', 'post_category', 'post_tags' ), true ) && is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				if ( '' === $v ) {
					unset( $value[ $k ] );
				}
			}

			$value = array_values( $value );

			if ( in_array( $gf_field->type, array( 'post_category', 'post_tags' ), true ) ) {
				foreach ( $value as $k => $v ) {
					$v = explode( ':', $v );

					if ( 2 == count( $v ) ) {
						$value[ $k ] = $v[1];
					}
				}
			}

			if ( 'boolean' === pods_v( 'type', $field_options ) ) {
				if ( ! empty( $value ) ) {
					$value = 1;
				} else {
					$value = 0;
				}
			}
		} elseif ( in_array( $gf_field->type, array( 'address' ), true ) ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value ) );
			} elseif ( 'pick' === pods_v( 'type', $field_options ) ) {
				$pick_object = pods_v( 'pick_object', $field_options );

				if ( 'country' === $pick_object ) {
					$value_check = $gf_field->get_country_code( $value );

					if ( $value_check ) {
						$value = $value_check;
					}
				} elseif ( 'us_state' === $pick_object ) {
					$value = $gf_field->get_us_state_code( $value );

					if ( $value_check ) {
						$value = $value_check;
					}
				} elseif ( 'ca_province' === $pick_object ) {
					$provinces = $pod->fields( $field_options['name'], 'data' );

					if ( $provinces ) {
						$provinces = array_map( 'strtoupper', $provinces );

						$value_check = array_search( strtoupper( $value ), $provinces, true );

						if ( false !== $value_check ) {
							$value = $value_check;
						}
					}
				}
			}
		} elseif ( in_array( $gf_field->type, array( 'date' ), true ) ) {
			$format = empty( $gf_field->dateFormat ) ? 'mdy' : esc_attr( $gf_field->dateFormat );
			$value  = GFcommon::parse_date( $value, $format );

			if ( ! empty( $value ) ) {
				$value = array_map( 'absint', $value );

				if ( $value['month'] < 10 ) {
					$value['month'] = '0' . $value['month'];
				}

				if ( $value['day'] < 10 ) {
					$value['day'] = '0' . $value['day'];
				}

				// Format as: Y-m-d
				$value = sprintf( '%s-%s-%s', $value['year'], $value['month'], $value['day'] );
			} else {
				$value = '';
			}
		} elseif ( in_array( $gf_field->type, array( 'time' ), true ) ) {
			$format = empty( $gf_field->timeFormat ) ? '12' : esc_attr( $gf_field->timeFormat );

			if ( ! is_array( $value ) ) {
				if ( preg_match( '/^(\d{1,2}):(\d{1,2}) (\w{2})$/', $value, $matches ) ) {
					$value = array(
						$matches[1],
						$matches[2],
						$matches[3],
					);

					$format = '12';
				} elseif ( preg_match( '/^(\d{1,2}):(\d{1,2})$/', $value, $matches ) ) {
					$value = array(
						$matches[1],
						$matches[2],
					);

					$format = '24';
				}
			}

			if ( is_array( $value ) ) {
				// Enforce max value using min().
				$value[0] = min( absint( $value[0] ), 23 );
				$value[1] = min( absint( $value[1] ), 59 );

				// Handle am/pm conversion.
				if ( '12' === $format ) {
					$ampm = strtolower( $value[2] );

					if ( 'pm' === $ampm ) {
						$value[0] += 12;
					}

					if ( 24 === $value[0] || ( 'am' === $ampm && 12 === $value[0] ) ) {
						$value[0] = 0;
					}
				}

				if ( $value[0] < 10 ) {
					$value[0] = '0' . $value[0];
				}

				if ( $value[1] < 10 ) {
					$value[1] = '0' . $value[1];
				}

				// Format as: H:i
				$value = sprintf( '%s:%s:00', $value[0], $value[1] );
			}
		} elseif ( in_array( $gf_field->type, array( 'list' ), true ) && is_array( $value ) && ! empty( $value ) ) {
			$columns = array_keys( current( $value ) );

			if ( $columns ) {
				$related_obj = false;

				// Attempt to detect the field names from related pod field labels
				if ( is_object( $pod ) && ! empty( $field_options['type'] ) && 'pick' === $field_options['type'] ) {
					$object_type = $field_options['pick_object'];
					$object      = $field_options['pick_val'];

					if ( 'table' === $object_type ) {
						$pick_val = pods_v( 'pick_table', $field_options['options'], $object, true );
					}

					if ( 'pod' === $object_type ) {
						$related_obj = pods( $object, null, false );
					} else {
						$table = $pod->api->get_table_info( $object_type, $object, null, null, $field_options );

						if ( ! empty( $table['pod'] ) ) {
							$related_obj = pods( $table['pod']['name'], null, false );
						}
					}
				}

				if ( $related_obj ) {
					$columns = self::match_pod_fields_to_list_columns( $related_obj, $columns, $form, $gf_field );

					$related_id_field = $related_obj->data->field_id;

					$related_ids = array();

					// Handle insert/update of relationship data.
					foreach ( $value as $k => $row ) {
						$row = array_combine( $columns, $row );

						/**
						 * Filter list field row for relationship field saving purposes.
						 *
						 * @param array      $row         List field row.
						 * @param array      $columns     List field columns.
						 * @param int        $form_id     GF form data.
						 * @param GF_Field   $gf_field    GF field data.
						 * @param array      $options     Pods GF options.
						 * @param Pods|false $related_obj Related Pod object.
						 *
						 * @since 1.4
						 */
						$row = (array) gf_apply_filters( array( 'pods_gf_field_column_row', $form['id'], $gf_field->id ), $row, $columns, $form, $gf_field, $options, $related_obj );

						$related_id = 0;

						if ( isset( $row[ $related_id_field ] ) ) {
							$related_id = $row[ $related_id_field ];

							unset( $row[ $related_id_field ] );
						}

						if ( ! empty( $related_id ) ) {
							$related_obj->save( $row, null, $related_id );
						} else {
							$row_has_values = array_filter( $row );

							if ( ! $row_has_values ) {
								continue;
							}

							$related_id = $related_obj->add( $row );
						}

						if ( $related_id ) {
							$related_ids[] = $related_id;
						}
					}

					$single_multi = pods_v( $field_options['type'] . '_format_type', $field_options['options'], 'single' );

					if ( 'single' === $single_multi ) {
						if ( $related_ids ) {
							$related_ids = current( $related_ids );
						} else {
							$related_ids = 0;
						}
					}

					$value = $related_ids;
				}
			} else {
				$value = null;
			}
		} elseif ( $handle_files && in_array( $gf_field->type, array( 'fileupload', 'post_image' ), true ) ) {
			$value = null;

			$attachments = array();

			// The following uploader code was from David Smith from GF support
			$input_name = sprintf( 'input_%s', $gf_field->id );

			// Form already submitted
			if ( ! empty( $options['gf_to_pods_priority'] ) && 'submission' === $options['gf_to_pods_priority'] ) {
				if ( empty( $attachments ) ) {
					if ( ! empty( $entry ) ) {
						$file_value = rgar( $entry, $gf_field->id );
						$file_value = trim( $file_value, '|' );

						if ( ! empty( $file_value ) ) {
							$file_urls = array(
								$file_value
							);

							if ( ! empty( $gf_field->multipleFiles ) ) {
								$file_urls = json_decode( $file_value );
							}

							if ( is_array( $file_urls ) ) {
								foreach ( $file_urls as $file_url ) {
									$file_path = GFFormsModel::get_physical_file_path( $file_url );

									if ( $file_path ) {
										$attachments[] = $file_path;
									}
								}
							}
						}
					}
				}

				if ( empty( $attachments ) ) {
					$uploaded_files = array();

					if ( ! empty( GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] ) ) {
						$uploaded_files = (array) GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ];
					}

					if ( ! empty( $uploaded_files ) ) {
						foreach ( $uploaded_files as $uploaded_file_data ) {
							if ( is_array( $uploaded_file_data ) ) {
								if ( empty( $uploaded_file_data['temp_filename'] ) || empty( $uploaded_file_data['uploaded_filename'] ) ) {
									continue;
								}

								$uploaded_file_data['tmp_name'] = $uploaded_file_data['temp_filename'];
								$uploaded_file_data['name']     = $uploaded_file_data['uploaded_filename'];

								$uploaded_file = $uploaded_file_data['uploaded_filename'];
							} else {
								$uploaded_file = $uploaded_file_data;
							}

							$filepath = GFFormsModel::get_file_upload_path( $form['id'], $uploaded_file );

							if ( $filepath && ! empty( $filepath['url'] ) ) {
								$attachments[] = $filepath['url'];
							}
						}
					}
				}
			} else {
				$is_save_and_continue = false;

				if ( false !== rgget( 'gf_token' ) ) {
					$is_save_and_continue = true;
				}

				$is_gform_submit_set_manually = false;

				// hack alert: force retrieval of unique ID for filenames when continuing from saved entry
				if ( $is_save_and_continue && ! isset( $_POST['gform_submit'] ) ) {
					$is_gform_submit_set_manually = true;

					$_POST['gform_submit'] = $form['id'];
				}

				$uploaded_files = array();

				$file_urls = array();

				if ( isset( GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ] ) ) {
					$uploaded_files = GFFormsModel::$uploaded_files[ $form['id'] ][ $input_name ];
				}

				$file_info = $uploaded_files;

				if ( ! empty( $gf_field->multipleFiles ) || ! empty( $file_info ) ) {
					foreach ( $file_info as $file_info_data ) {
						$temp_file     = '';
						$uploaded_file = '';

						if ( is_array( $file_info_data ) ) {
							$temp_file     = $file_info_data['temp_filename'];
							$uploaded_file = $file_info_data['uploaded_filename'];
						} else {
							$uploaded_file = $file_info_data;
						}

						if ( $uploaded_file ) {
							$filepath = GFFormsModel::get_file_upload_path( $form['id'], $uploaded_file );

							if ( ! $filepath && empty( $filepath['url'] ) ) {
								continue;
							}

							if ( in_array( $filepath['url'], $file_urls, true ) ) {
								continue;
							}

							if ( $temp_file && $filepath && ! empty( $filepath['path'] ) && ! file_exists( $filepath['path'] ) ) {
								$file_urls[] = $filepath['url'];

								// @todo Support setting the file name so it's not the tmp name in pods_attachment_import

								$filepath = array(
									'path' => GFFormsModel::get_upload_path( $form['id'] ) . '/tmp/' . $temp_file,
									'url'  => GFFormsModel::get_upload_url( $form['id'] ) . '/tmp/' . $temp_file,
								);
							}

							$attachments[] = $filepath['url'];
						}
					}
				} else {
					$file_info_data = GFFormsModel::get_temp_filename( $form['id'], $input_name );

					if ( $file_info_data ) {
						$temp_file     = $file_info_data['temp_filename'];
						$uploaded_file = $file_info_data['uploaded_filename'];

						$filepath = GFFormsModel::get_file_upload_path( $form['id'], $uploaded_file );

						if ( $filepath && ! empty( $filepath['url'] ) ) {
							if ( $temp_file && ! empty( $filepath['path'] ) && ! file_exists( $filepath['path'] ) ) {
								// @todo Support setting the file name so it's not the tmp name in pods_attachment_import

								$filepath = array(
									'path' => GFFormsModel::get_upload_path( $form['id'] ) . '/tmp/' . $temp_file,
									'url'  => GFFormsModel::get_upload_url( $form['id'] ) . '/tmp/' . $temp_file,
								);

								if ( file_exists( $filepath['path'] ) ) {
									if ( ! in_array( $filepath['url'], $attachments, true ) ) {
										$attachments[] = $filepath['url'];
									}
								} elseif ( ! empty( $_FILES[ $input_name ]['tmp_name'] ) && empty( $_FILES[ $input_name ]['error'] ) ) {
								    require_once( ABSPATH . 'wp-admin/includes/file.php' );
								    require_once( ABSPATH . 'wp-admin/includes/image.php' );
								    require_once( ABSPATH . 'wp-admin/includes/media.php' );

									$attachment_id = media_handle_upload( $input_name, 0 );

									if ( is_wp_error( $attachment_id ) ) {
										$errors = $attachment_id->get_error_messages();

										//throw new Exception( 'File field #' . $gf_field->id . ' error - ' . implode( '</div><div>', $errors ) );
									} else {
										$attachments[] = (int) $attachment_id;
									}
								}
							} elseif ( file_exists( $filepath['path'] ) && ! in_array( $filepath['url'], $attachments, true ) ) {
								$attachments[] = $filepath['url'];
							}
						}
					}
				}

				if ( $is_save_and_continue && ! isset( $_POST['gform_submit'] ) ) {
					$_POST['gform_submit'] = $form['id'];
				}

				// hack alert: force retrieval of unique ID for filenames when continuing from saved entry
				if ( isset( $is_gform_submit_set_manually ) ) {
					unset( $_POST['gform_submit'] );
				}
			}

			if ( ! empty( $attachments ) ) {
				$value = array();

				foreach ( $attachments as $attachment ) {
					if ( empty( $attachment ) || ':' === $attachment ) {
						continue;
					}

					$attachment = explode( '|:|', $attachment );
					$attachment = $attachment[0];

					if ( is_string( $attachment ) ) {
						$attachment_id = pods_attachment_import( $attachment );
					} else {
						$attachment_id = absint( $attachment );
					}

					if ( 0 < $attachment_id ) {
						$value[] = $attachment_id;
					}
				}

				$value_count = count( $value );

				if ( 1 == $value_count ) {
					$value = current( $value );
				} elseif ( 0 == $value_count ) {
					$value = null;
				}
			}
		}

		if ( is_string( $value ) && ! empty( $gf_field_options['gf_merge_tags'] ) ) {
			$value = GFCommon::replace_variables( $value, $form, $entry );
		}

		if ( $cache_key ) {
			$cached_field_value[ $cache_key ] = array(
				'value' => $value,
			);
		}

		return $value;

	}

	/**
	 * Action handler for Gravity Forms: gform_field_validation_{$form_id}_{$field_id}
	 *
	 * @param array $validation_result GF validation result
	 * @param mixed $value             Value submitted
	 * @param array $form              GF Form array
	 * @param array|GF_Field $field             GF Form Field array
	 *
	 * @return array GF validation result
	 */
	public function _gf_field_validation( $validation_result, $value, $form, $field ) {

		if ( ! $validation_result['is_valid'] ) {
			return $validation_result;
		}

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__ . '_' . $field['id'], self::$actioned[$form['id']] ) ) {
			return $validation_result;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__ . '_' . $field['id'];

		if ( empty( $this->options ) ) {
			return $validation_result;
		}

		$field_options = array();

		$field_full = null;

		foreach ( $this->options['fields'] as $gf_field => $field_data ) {
			if ( is_array( $field_data ) && isset( $field_data['gf_field'] ) ) {
				$gf_field = $field_data['gf_field'];
			}

			/**
			 * @var $gf_field GF_Field
			 */
			if ( (string) $gf_field === (string) $field['id'] ) {
				$field_full = $gf_field;

				$field_options = $field_data;
			} elseif ( false !== strpos( $gf_field, '.' ) ) {
				$field_full = $gf_field;

				$gf_field_expanded = explode( '.', $gf_field );

				if ( (string) $gf_field_expanded[0] === (string) $field['id'] ) {
					$field_options = $field_data;
				}
			}
		}

		if ( empty( $field_options ) ) {
			return $validation_result;
		}

		if ( ! is_array( $field_options ) ) {
			$field_options = array(
				'field' => $field_options,
			);

			if ( ! empty( $field_full ) ) {
				$field_full = $field_options;
			}
		}

		$validate = true;

		if ( is_object( $this->pod ) ) {
			if ( empty( $this->pod->pod_data ) ) {
				$validate = 'Invalid pod for mapping';
			} else {
				$field_data = $this->pod->fields( $field_options['field'] );

				if ( empty( $field_data ) ) {
					return $validation_result;
				}

				$pods_api = pods_api();

				$gf_params = array(
					'gf_field'         => $field,
					'gf_field_options' => $field_options,
					'field'            => $field_full,
					'field_options'    => $field_data,
					'pod'              => $this->pod,
					'form'             => $form,
					'entry'            => GFFormsModel::get_current_lead(),
					'options'          => $this->options,
				);

				$gf_value = self::get_gf_field_value( $value, $gf_params );

				// If a file is not set, check if we are editing an item.
				if ( null === $gf_value && in_array( $field->type, array( 'fileupload', 'post_image' ), true ) ) {
					// If we are editing an item, return normal result, don't attempt to save.
					if ( is_object( $this->pod ) && $this->pod->id ) {
						return $validation_result;
					}
				}

				$validate = $pods_api->handle_field_validation( $gf_value, $field_options['field'], $this->pod->pod_data['object_fields'], $this->pod->pod_data['fields'], $this->pod, null );
			}
		}

		$validate = apply_filters( 'pods_gf_field_validation_' . $form['id'] . '_' . (string) $field['id'], $validate, $field['id'], $field_options, $value, $form, $field, $this );
		$validate = apply_filters( 'pods_gf_field_validation_' . $form['id'], $validate, $field['id'], $field_options, $value, $form, $field, $this );
		$validate = apply_filters( 'pods_gf_field_validation', $validate, $field['id'], $field_options, $value, $form, $field, $this );

		if ( false === $validate ) {
			$validate = 'There was an issue validating the field ' . $field['label'];
		}
		elseif ( true !== $validate ) {
			$validate = (array) $validate;
		}

		if ( ! is_bool( $validate ) && ! empty( $validate ) ) {
			$validation_result['is_valid'] = false;

			if ( is_array( $validate ) ) {
				if ( 1 == count( $validate ) ) {
					$validate = current( $validate );
				}
				else {
					$validate = 'The following issues occurred:' . "\n<ul><li>" . implode( "</li>\n<li>", $validate ) . "</li></ul>";
				}
			}

			$validation_result['message'] = $validate;
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

		if ( ! $validation_result['is_valid'] ) {
			return $validation_result;
		}

		$form = $validation_result['form'];

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $validation_result;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		if ( empty( $this->options ) ) {
			return $validation_result;
		}

		if ( empty( $this->options['gf_to_pods_priority'] ) || 'validation' == $this->options['gf_to_pods_priority'] ) {
			try {
				$this->_gf_to_pods_handler( $form );
			}
			catch ( Exception $e ) {
				$validation_result['is_valid'] = false;

				$this->gf_validation_message = 'Error saving: ' . $e->getMessage();

				return $validation_result;
			}
		}

		return $validation_result;

	}

	/**
	 * Action handler for Gravity Forms: gform_validation_message_{$form_id}
	 *
	 * @param string $validation_message GF validation message
	 * @param array  $form               GF Form array
	 *
	 * @return null
	 */
	public function _gf_validation_message( $validation_message, $form ) {

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $validation_message;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		if ( !empty( $this->gf_validation_message ) ) {
			if ( false === strpos( $validation_message, __( 'There was a problem with your submission.', 'pods-gravity-forms' ) . " " . __( 'Errors have been highlighted below.', 'pods-gravity-forms' ) ) ) {
				$validation_message .= "\n" . '<div class="validation_error">' . $this->gf_validation_message . '</div>';
			}
			else {
				$validation_message = '<div class="validation_error">' . $this->gf_validation_message . '</div>';
			}
		}

		return $validation_message;

	}

	/**
	 * Action handler for Gravity Forms: gform_entry_id_pre_save_lead_{$form_id}
	 *
	 * @param null|int $lead_id GF Entry ID
	 * @param array $form  GF Form array
	 */
	public function _gf_entry_pre_save_id( $lead_id, $form ) {

		if ( empty( $this->gf_validation_message ) ) {
			if ( empty( $this->options ) ) {
				return $lead_id;
			}

			if ( isset( $this->options['edit'] ) && $this->options['edit'] && is_array( $this->pod ) && empty( $entry ) ) {
				if ( 0 < $this->entry_id ) {
					$lead_id = $this->entry_id;
				} elseif ( 0 < $this->id ) {
					$lead_id = $this->id;
				}
			}
		}

		return $lead_id;

	}

	/**
	 * Setup form object based on features available.
	 *
	 * @param array $form Form object.
	 *
	 * @return array Form object.
	 */
	public function setup_form( $form ) {

		static $setup = array();

		if ( isset( $setup[ $form['id'] ] ) ) {
			return $setup[ $form['id'] ];
		}

		// Add Dynamic Selects
		if ( isset( $this->options['dynamic_select'] ) && ! empty( $this->options['dynamic_select'] ) ) {
			foreach ( $this->options['dynamic_select'] as $field_id => $dynamic_select ) {
				self::dynamic_select( $form['id'], $field_id, $dynamic_select );
			}
		}

		$form = self::gf_dynamic_select( $form );

		// Read Only handling
		if ( isset( $this->options['read_only'] ) && ! empty( $this->options['read_only'] ) ) {
			$read_only = array(
				'form'           => $form['id'],

				'fields'         => $this->options['read_only'],
				'exclude_fields' => array()
			);

			if ( is_array( $this->options['read_only'] ) && ( isset( $this->options['read_only']['fields'] ) || isset( $this->options['read_only']['exclude_fields'] ) ) ) {
				$read_only = array_merge( $read_only, $this->options['read_only'] );
			}

			self::read_only( $form['id'], $read_only['fields'], $read_only['exclude_fields'] );
		}

		$form = self::gf_read_only( $form );

		// Prepopulate values
		if ( isset( $this->options['prepopulate'] ) && ! empty( $this->options['prepopulate'] ) ) {
			$prepopulate = array(
				'pod'    => $this->pod,
				'id'     => pods_v( 'save_id', $this->options, $this->get_current_id(), true ),
				'fields' => $this->options['fields']
			);

			if ( is_array( $this->options['prepopulate'] ) ) {
				$prepopulate = array_merge( $prepopulate, $this->options['prepopulate'] );
			}

			self::prepopulate( $form['id'], $prepopulate['pod'], $prepopulate['id'], $prepopulate['fields'] );
		}

		$form = self::gf_prepopulate( $form );

		// Markdown Syntax for HTML
		if ( isset( $this->options['markdown'] ) && ! empty( $this->options['markdown'] ) ) {
			$form = self::gf_markdown( $form, $ajax, $this->options['markdown'] );
		}

		// Submit Button customization
		if ( isset( $this->options['submit_button'] ) && ! empty( $this->options['submit_button'] ) ) {
			if ( is_array( $this->options['submit_button'] ) ) {
				if ( isset( $this->options['submit_button']['imageUrl'] ) ) {
					$this->options['submit_button']['type'] = 'imageUrl';
				}
				elseif ( isset( $this->options['submit_button']['text'] ) ) {
					$this->options['submit_button']['type'] = 'text';
				}

				$button = $this->options['submit_button'];
			}
			elseif ( ( false !== strpos( $this->options['submit_button'], '://' ) && strpos( $this->options['submit_button'], '://' ) < 6 ) || 0 === strpos( $this->options['submit_button'], '/' ) ) {
				$button = array(
					'imageUrl' => $this->options['submit_button'],
					'type'     => 'imageUrl'
				);
			}
			else {
				$button = array(
					'text' => $this->options['submit_button'],
					'type' => 'text'
				);
			}

			$form['button'] = $button;
		}

		// Secondary Submit actions
		if ( isset( $this->options['secondary_submits'] ) && ! empty( $this->options['secondary_submits'] ) ) {
			self::secondary_submits( $form['id'], $this->options['secondary_submits'] );
		}

		// Save form object for later use.
		$setup[ $form['id'] ] = $form;

		return $setup[ $form['id'] ];

	}

	/**
	 * Action handler for Gravity Forms: gform_pre_submission_filter_{$form_id}
	 *
	 * @param array $form GF Form array
	 */
	public function _gf_pre_submission_filter( $form ) {

		return $this->setup_form( $form );

	}

	/**
	 * Action handler for Gravity Forms: gform_entry_post_save
	 *
	 * @param array $lead GF Entry array
	 * @param array $form GF Form array
	 */
	public function _gf_entry_post_save( $lead, $form ) {

		global $wpdb;

		if ( $form['id'] == $this->form_id && empty( $this->gf_validation_message ) ) {
			if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
				return $lead;
			}
			elseif ( ! isset( self::$actioned[$form['id']] ) ) {
				self::$actioned[$form['id']] = array();
			}

			self::$actioned[$form['id']][] = __FUNCTION__;

			$old_post = $_POST;

			$changed = array();

			$lead_detail_table = pods_gf_get_gf_table_name( 'entry_details' );

			$old_schema = version_compare( GFFormsModel::get_database_version(), '2.3-dev-1', '<' );

			$lead_id_column_name = $old_schema ? 'lead_id' : 'entry_id';

			$field_id_column = $old_schema ? 'field_number' : 'meta_key';

			$current_fields = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$field_id_column} FROM {$lead_detail_table} WHERE {$lead_id_column_name} = %d", $lead["id"] ) );

			foreach ( $form['fields'] as $field ) {
				$value = $original_value = null;

				if ( isset( $lead[$field['id']] ) ) {
					$value = $original_value = $lead[$field['id']];
				}

				$value = apply_filters( 'pods_gf_entry_save_' . $form['id'] . '_' . $field['id'], $value, $lead, $field, $form );

				$save_value = $value;

				if ( is_array( $value ) ) {
					$save_value = @serialize( $value );
				}

				if ( null !== $save_value && $original_value !== $save_value ) {
					$field['adminOnly'] = false;

					$_POST['input_' . $field['id']] = $value;

					$lead[ $field[ 'id' ] ] = $save_value;

					GFFormsModel::save_input( $form, $field, $lead, $current_fields, $field['id'] );

					$changed[ $field[ 'id' ] ] = array( 'old' => $original_value, 'new' => $save_value );
				}
			}

			$_POST = $old_post;
		}

		return $lead;

	}

	/**
	 * Action handler for Gravity Forms: gform_after_submission_{$form_id}
	 *
	 * @param array $entry GF Entry array
	 * @param array $form  GF Form array
	 */
	public function _gf_after_submission( $entry, $form ) {

		if ( ! empty( $this->gf_validation_message ) ) {
			return $entry;
		}

		remove_action( 'gform_post_submission_' . $form['id'], array( $this, '_gf_after_submission' ), 10 );

		if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
			return $entry;
		}
		elseif ( ! isset( self::$actioned[$form['id']] ) ) {
			self::$actioned[$form['id']] = array();
		}

		self::$actioned[$form['id']][] = __FUNCTION__;

		if ( is_array( $this->pod ) ) {
			$this->id = $entry['id'];
		}

		$this->entry_id = $entry['id'];

		if ( empty( $this->options ) ) {
			return $entry;
		}

		// Alternative gf_to_pods handling
		if ( ! empty( $this->options['gf_to_pods_priority'] ) && 'submission' == $this->options['gf_to_pods_priority'] ) {
			try {
				$this->options['entry'] = $entry;

				$this->_gf_to_pods_handler( $form, $entry );
			}
			catch ( Exception $e ) {
				// @todo Log something to the form entry
			}
		}

		// Send notifications
		self::gf_notifications( $entry, $form, $this->options );

		if ( pods_v( 'auto_delete', $this->options, false ) ) {
			self::gf_delete_entry( $entry );
		}

		do_action( 'pods_gf_after_submission_' . $form['id'], $entry, $form );
		do_action( 'pods_gf_after_submission', $entry, $form );

		// Redirect after
		if ( pods_v( 'redirect_after', $this->options, false ) ) {
			// Handle secondary submits and redirect to next ID
			$secondary_submits = (array) pods_v( 'secondary_submits', $this->options, array() );

			if ( ! empty( $secondary_submits ) ) {
				if ( isset( $secondary_submits['action'] ) ) {
					$secondary_submits = array( $secondary_submits );
				}

				$defaults = array(
					'imageUrl'      => null,
					'text'          => 'Alt Submit',
					'action'        => 'alt',
					'value'         => 1,
					'value_from_ui' => ''
				);

				foreach ( $secondary_submits as $secondary_submit ) {
					$secondary_submit = array_merge( $defaults, $secondary_submit );

					// Not set
					if ( ! isset( $_POST['pods_gf_ui_action_' . $secondary_submit['action']] ) ) {
						continue;
					}
					// No value
					elseif ( empty( $_POST['pods_gf_ui_action_' . $secondary_submit['action']] ) ) {
						break;
					}
					// Not auto handling
					elseif ( ! in_array( $secondary_submit['value_from_ui'], array( 'next_id', 'prev_id' ) ) ) {
						break;
					}

					pods_redirect( add_query_arg( array( 'id' => (int) $_POST['pods_gf_ui_action_' . $secondary_submit['action']] ) ) );

					break;
				}
			}

			$confirmation = self::gf_confirmation( $form['confirmation'], $form, $entry, false, true );

			if ( ! is_array( $confirmation ) || 'redirect' != $confirmation['type'] || ( ! isset( $confirmation['url'] ) && ! isset( $confirmation['redirect'] ) ) ) {
				pods_redirect( pods_var_update( array( 'action' => 'edit', 'id' => $this->get_current_id() ) ) );
			}
			else {
				$url = false;

				if ( isset( $confirmation['url'] ) ) {
					$url = $confirmation['url'];
				}
				elseif ( isset( $confirmation['redirect'] ) ) {
					$url = $confirmation['redirect'];
				}

				if ( $url ) {
					$gf_to_pods_id = 0;
					$gf_to_pods_permalink = '';

					if ( ! empty( self::$gf_to_pods_id[ $form['id'] ] ) ) {
						$gf_to_pods_id = self::$gf_to_pods_id[ $form['id'] ];
					}

					if ( ! empty( self::$gf_to_pods_id[ $form['id'] . '_permalink' ] ) ) {
						$gf_to_pods_permalink = self::$gf_to_pods_id[ $form['id'] . '_permalink' ];
					}

					$url = str_replace( '{@gf_to_pods_id}', $gf_to_pods_id, $url );
					$url = str_replace( '{@gf_to_pods_permalink}', $gf_to_pods_permalink, $url );

					pods_redirect( $url );
				}
			}
		}

		return $entry;

	}

	/**
	 * Action handler for Gravity Forms: gform_post_update_entry_{$form_id}
	 *
	 * @param array $entry          GF Entry array
	 * @param array $original_entry Original GF Entry array
	 */
	public function _gf_post_update_entry( $entry, $original_entry ) {

		if ( empty( $this->options['update_pod_item'] ) ) {
			return $entry;
		}

		$form_id = $entry['form_id'];

		$form = GFAPI::get_form( $form_id );

		if ( $form && empty( $this->gf_validation_message ) ) {
			if ( isset( self::$actioned[ $form['id'] ] ) && in_array( __FUNCTION__, self::$actioned[ $form['id'] ] ) ) {
				return $entry;
			} elseif ( ! isset( self::$actioned[ $form['id'] ] ) ) {
				self::$actioned[ $form['id'] ] = array();
			}

			self::$actioned[$form['id']][] = __FUNCTION__;

			return $this->_gf_after_update_entry( $form, $entry, $original_entry );
		}

	}

	/**
	 * Action handler for Gravity Forms: gform_after_update_entry_{$form_id}
	 *
	 * @param array $form           GF Form array
	 * @param array $entry          GF Entry array
	 * @param array $original_entry Original GF Entry array
	 */
	public function _gf_after_update_entry( $form, $entry, $original_entry ) {

		if ( empty( $this->options['update_pod_item'] ) ) {
			return $entry;
		}

		if ( ! is_array( $entry ) ) {
			$entry = GFAPI::get_entry( $entry );
		}

		if ( empty( $this->gf_validation_message ) ) {
			if ( isset( self::$actioned[$form['id']] ) && in_array( __FUNCTION__, self::$actioned[$form['id']] ) ) {
				return $entry;
			}
			elseif ( ! isset( self::$actioned[$form['id']] ) ) {
				self::$actioned[$form['id']] = array();
			}

			self::$actioned[$form['id']][] = __FUNCTION__;

			if ( is_array( $this->pod ) ) {
				$this->id = $entry['id'];
			}

			$this->entry_id = $entry['id'];

			if ( empty( $this->options ) ) {
				return $entry;
			}

			try {
				$this->options['entry'] = $entry;

				$this->_gf_to_pods_handler( $form, $entry );
			}
			catch ( Exception $e ) {
				// @todo Log something to the form entry
			}

			do_action( 'pods_gf_after_update_entry_' . $form['id'], $entry, $form );
			do_action( 'pods_gf_after_update_entry', $entry, $form );
		}

		return $entry;

	}

	/**
	 * Get current ID
	 *
	 * @return int
	 */
	public function get_current_id() {

		$id = (int) $this->id;

		if ( empty( $id ) ) {
			if ( is_object( $this->pod ) && $this->pod->exists() ) {
				// Pod object
				$id = (int) $this->pod->id();
			} elseif ( is_array( $this->pod ) ) {
				// GF entry
				$id = (int) pods_v( 'id', $this->pod, $id );
			}
		}

		return $id;

	}

	/**
	 * Get the value of a variable key, with a default fallback
	 *
	 * @param mixed        $name    Variable key to get value of
	 * @param array|object $var     Array or Object to get key value from
	 * @param null|mixed   $default Default value to use if key not found
	 * @param bool         $strict  Whether to force $default if $value is empty
	 *
	 * @return null|mixed Value of the variable key or default value
	 */
	public static function v( $name, $var, $default = null, $strict = false ) {

		$value = $default;

		if ( is_object( $var ) ) {
			if ( isset( $var->{$name} ) ) {
				$value = $var->{$name};
			}
		}
		elseif ( is_array( $var ) ) {
			if ( isset( $var[$name] ) ) {
				$value = $var[$name];
			}
		}

		if ( $strict && empty( $value ) ) {
			$value = $default;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		return $value;

	}

}
