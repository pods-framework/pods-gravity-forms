<?php

namespace Pods_Gravity_Forms\Platform\Gravity_Forms;

use GFAPI;
use GFFeedAddOn;
use GFFormsModel;
use PodsForm;
use Pods_GF;

/**
 * Class Feed_AddOn
 */
class Feed_AddOn extends GFFeedAddOn {

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_version = PODS_GF_VERSION;

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_path = PODS_GF_ADDON_FILE;

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_full_path = PODS_GF_FILE;

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_url = 'https://pods.io/';

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_slug = 'pods-gravity-forms';

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_title = 'Pods Gravity Forms Add-On';

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $_short_title = 'Pods';

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected $_capabilities = [
		'pods_gravityforms',
	];

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected $_capabilities_form_settings = [
		'pods_gravityforms',
		'pods',
	];

	/**
	 * The Pods_GF instance being used.
	 *
	 * @since 2.0.0
	 *
	 * @var Pods_GF[]
	 */
	public $pods_gf = [];

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since 2.0.0
	 *
	 * @var self
	 */
	private static $_instance;

	/**
	 * Get an instance of this class.
	 *
	 * @since 2.0.0
	 *
	 * @return self The instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new static();
		}

		return self::$_instance;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool Whether the feeds can be duplicated.
	 */
	public function can_duplicate_feed( $id ) {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @return array List of scripts to register.
	 */
	public function scripts() {
		$scripts = [
			[
				'handle'  => 'pods_gf_admin',
				'enqueue' => [
					[
						'admin_page' => [
							'form_settings',
						],
					],
				],
				'src'     => PODS_GF_URL . '/ui/pods-gf-admin.js',
				'version' => $this->_version,
				'deps'    => [
					'jquery',
				],
			],
		];

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Handle maybe detecting the feed mapping for a Pod.
	 *
	 * @param array  $gf_form          The Gravity Form data.
	 * @param string $pod_name         The Pod name.
	 * @param array  $existing_mapping Existing mapping to start with.
	 *
	 * @return array The feed field mapping.
	 */
	public function maybe_detect_feed_mapping( $gf_form, $pod_name, array $existing_mapping = [] ) {
		$mapped_field_ids = [];

		$mapping = [
			'object_fields' => [],
			'pod_fields'    => [],
			'custom_fields' => [],
		];

		// Maybe use existing mapping if we have it.
		if ( $existing_mapping ) {
			$mapping = [
				'object_fields' => $existing_mapping['object_fields'] ?? [],
				'pod_fields'    => $existing_mapping['pod_fields'] ?? [],
				'custom_fields' => $existing_mapping['custom_fields'] ?? [],
			];

			$mapped_field_ids = $existing_mapping['mapped_field_ids'] ?? [];
		}

		$gf_fields = [];

		if ( ! empty( $gf_form ) && ! empty( $gf_form['fields'] ) ) {
			$gf_fields = $gf_form['fields'];
		}

		$pods_api = pods_api();

		$pod_object = null;

		try {
			$pod_object = $pods_api->load_pod( [ 'name' => $pod_name ] );
		} catch ( \Exception $exception ) {
			// Nothing to do here.
		}

		// Pod not found.
		if ( empty( $pod_object ) ) {
			pods_ui_error( __( 'There was a problem detecting the feed mapping for your Pod.', 'pods-gravity-forms' ) );

			return $mapping;
		}

		$pod_type          = $pod_object['type'];
		$pod_fields        = $pod_object->get_fields();
		$pod_object_fields = $pod_object->get_object_fields();

		foreach ( $pod_fields as $field ) {
			foreach ( $gf_fields as $gf_field ) {
				// Check whether we have mapped this field already.
				if ( isset( $mapped_field_ids[ $gf_field['id'] ] ) ) {
					continue;
				}

				// Check whether the GF field label matches the Pod field label.
				if ( strtolower( $field['label'] ) !== strtolower( $gf_field['label'] ) ) {
					continue;
				}

				$mapping['pod_fields'][ $field['name'] ] = $gf_field['id'];

				$mapped_field_ids[ $gf_field['id'] ] = true;

				break;
			}
		}

		$ignore_object_fields = [
			// Ignored for Post types.
			'ID'                    => true,
			'post_type'             => true,
			'guid'                  => true,
			'menu_order'            => true,
			'post_mime_type'        => true,
			'ping_status'           => true,
			'post_date_gmt'         => true,
			'post_modified_gmt'     => true,
			'post_password'         => true,
			'post_status'           => true,
			'post_content_filtered' => true,
			'pinged'                => true,
			'to_ping'               => true,
			'comment_count'         => true,
			// Ignored for Taxonomies.
			'term_id'               => true,
			'term_taxonomy_id'      => true,
			'taxonomy'              => true,
			// Ignored for Comment types.
			'comment_type'          => true,
			'comment_status'        => true,
		];

		foreach ( $pod_object_fields as $field ) {
			if ( isset( $ignore_object_fields[ $field['name'] ] ) ) {
				continue;
			}

			foreach ( $gf_fields as $gf_field ) {
				// Check whether we have mapped this field already.
				if ( isset( $mapped_field_ids[ $gf_field['id'] ] ) ) {
					continue;
				}

				// Check whether the GF field label matches the Pod field label.
				if ( strtolower( $field['label'] ) !== strtolower( $gf_field['label'] ) ) {
					continue;
				}

				$mapping['object_fields'][ $field['name'] ] = $gf_field['id'];

				$mapped_field_ids[ $gf_field['id'] ] = true;

				break;
			}
		}

		if ( 'post_type' === $pod_type ) {
			// Detect featured image.
			foreach ( $gf_fields as $gf_field ) {
				// Only deal with this GF field type.
				if ( 'post_image' !== $gf_field['type'] ) {
					continue;
				}

				// Check whether we have mapped this field already.
				if ( isset( $mapped_field_ids[ $gf_field['id'] ] ) ) {
					continue;
				}

				$mapping['object_fields']['_thumbnail_id'] = $gf_field['id'];

				$mapped_field_ids[ $gf_field['id'] ] = true;

				break;
			}

			// Detect custom fields.
			foreach ( $gf_fields as $gf_field ) {
				if ( 'post_custom_field' !== $gf_field['type'] ) {
					continue;
				}

				// Check whether we have mapped this field already.
				if ( isset( $mapped_field_ids[ $gf_field['id'] ] ) ) {
					continue;
				}

				$mapping['custom_fields'][] = [
					'key'          => $gf_field['key'],
					'custom_key'   => $gf_field['custom_key'],
					'value'        => $gf_field['value'],
					'custom_value' => $gf_field['custom_value'],
				];

				$mapped_field_ids[ $gf_field['id'] ] = true;
			}
		}

		return $mapping;
	}

	/**
	 * Handle maybe creating the Pod.
	 *
	 * @param array  $gf_form      The Gravity Form data.
	 * @param string $pod_type     The Pod type.
	 * @param string $storage_type The Pod storage type.
	 */
	public function auto_create_pod( $gf_form, $pod_type, $storage_type ) {
		$pods_api = pods_api();

		$pod_label = $gf_form['title'];
		$pod_name  = pods_clean_name( substr( $pod_label, 0, 20 ) );

		$original_label = $pod_label;
		$original_name  = $pod_name;
		$counter        = 1;

		while ( $pods_api->pod_exists( [ 'name' => $pod_name ] ) ) {
			$counter ++;

			$pod_name  = substr( $original_name, 0, ceil( 19 - ( $counter / 10 ) ) ) . $counter;
			$pod_label = $original_label . $counter;
		}

		$new_pod_params = [
			'create_extend'         => 'create',
			'create_pod_type'       => $pod_type,
			'create_storage'        => $storage_type,
			'create_label_plural'   => $pod_label,
			'create_label_singular' => __( 'Item', 'pods-gravity-forms' ),
			'create_name'           => $pod_name,
		];

		$pod_id = $pods_api->add_pod( $new_pod_params );

		$save_params = [
			'fields' => [],
		];

		$gf_fields = [];

		if ( ! empty( $gf_form ) && ! empty( $gf_form['fields'] ) ) {
			$gf_fields = $gf_form['fields'];
		}

		$existing_mapping = [
			'object_fields'    => [],
			'pod_fields'       => [],
			'mapped_field_ids' => [],
		];

		// Auto-detect post fields.
		if ( 'post_type' === $pod_type ) {
			$save_params['supports_title'] = 1;

			foreach ( $gf_fields as $gf_field ) {
				switch ( $gf_field['type'] ) {
					case 'post_title':
						$existing_mapping['object_fields'][ $gf_field['type'] ] = $gf_field['id'];

						break;
					case 'post_content':
						$existing_mapping['object_fields'][ $gf_field['type'] ] = $gf_field['id'];

						$save_params['supports_editor'] = 1;

						break;
					case 'post_excerpt':
						$existing_mapping['object_fields'][ $gf_field['type'] ] = $gf_field['id'];

						$save_params['supports_excerpt'] = 1;

						break;
					case 'post_image':
						$existing_mapping['object_fields']['_thumbnail_id'] = $gf_field['id'];

						$save_params['supports_thumbnail'] = 1;

						break;
					case 'post_category':
						$existing_mapping['object_fields']['category'] = $gf_field['id'];

						$save_params['built_in_taxonomies_category'] = 1;

						break;
					case 'post_tags':
						$existing_mapping['object_fields']['post_tag'] = $gf_field['id'];

						$save_params['built_in_taxonomies_post_tag'] = 1;

						break;
					default:
						break;
				}
			}
		}

		// Setup custom fields.
		$ignore_field_types = [
			'post_title'    => true,
			'post_content'  => true,
			'post_excerpt'  => true,
			'post_image'    => true,
			'post_category' => true,
			'post_tags'     => true,
			'html'          => true,
			'section'       => true,
			'page'          => true,
			'product'       => true,
		];

		$pod_field_names = [];

		$mapped_field_types = [
			'text'        => 'text',
			'textarea'    => 'paragraph',
			'number'      => 'number',
			'select'      => 'pick',
			'checkbox'    => 'pick',
			'radio'       => 'pick',
			'multiselect' => 'pick',
			'hidden'      => 'text',
			'name'        => 'text',
			'date'        => 'date',
			'time'        => 'time',
			'phone'       => 'phone',
			'website'     => 'website',
			'email'       => 'email',
			'fileupload'  => 'file',
			'consent'     => 'boolean',
		];

		$pick_format_types = [
			'select'      => 'single',
			'checkbox'    => 'multi',
			'radio'       => 'single',
			'multiselect' => 'multi',
		];

		$pick_formats = [
			'select'      => 'dropdown',
			'checkbox'    => 'checkbox',
			'radio'       => 'radio',
			'multiselect' => 'multiselect',
		];

		foreach ( $gf_fields as $gf_field ) {
			if ( isset( $ignore_field_types[ $gf_field['type'] ] ) ) {
				continue;
			}

			$pod_field = [
				'label' => $gf_field['label'],
				'name'  => $gf_field['inputName'],
				'type'  => 'text',
			];

			if ( empty( $pod_field['name'] ) ) {
				$pod_field['name'] = pods_clean_name( $gf_field['label'] );
			}

			$gf_field_type = $gf_field['type'];

			// Handle custom fields.
			if ( 'post_custom_field' === $gf_field_type ) {
				$pod_field['name'] = $gf_field['postCustomFieldName'];

				// Override the field type we are using.
				if ( ! empty( $gf_field['inputType'] ) ) {
					$gf_field_type = $gf_field['inputType'];
				}
			}

			if ( isset( $mapped_field_types[ $gf_field_type ] ) ) {
				$pod_field['type'] = $mapped_field_types[ $gf_field_type ];
			}

			$inputs = [];

			$gf_inputs = $gf_field['inputs'] ?? [];

			// Custom handling based on field type.
			switch ( $gf_field_type ) {
				case 'textarea':
					if ( ! empty( $gf_field['useRichTextEditor'] ) ) {
						$pod_field['type'] = 'wysiwyg';
					}

					break;
				case 'number':
					// @todo Handle currency settings.
					// @todo Handle format settings (comma vs dot).

					break;
				case 'name':
				case 'address':
					foreach ( $gf_inputs as $gf_field_input ) {
						if ( ! empty( $gf_field_input['isHidden'] ) ) {
							continue;
						}

						$pod_input = $pod_field;

						$pod_input['label'] = $pod_input['label'] . ' (' . $gf_field_input['label'] . ')';
						$pod_input['name']  = $gf_field_input['name'] ?? '';

						if ( empty( $pod_input['name'] ) ) {
							$pod_input['name'] = $pod_field['name'] . '_' . pods_clean_name( $pod_input['label'] );
						}

						$inputs[ $gf_field_input['id'] ] = $pod_input;
					}

					break;
				case 'list':
					if ( is_array( $gf_field['choices'] ) && 1 < count( $gf_field['choices'] ) ) {
						// If there are multiple columns, use a code field.
						$pod_field['type'] = 'code';
					} else {
						// Single column list fields are repeatable text fields.
						$pod_field['repeatable'] = 1;
					}

					break;
				case 'select':
				case 'checkbox':
				case 'radio':
				case 'multiselect':
					$options = [];

					foreach ( $gf_field['choices'] as $choice ) {
						$choice['value'] = $choice['value'] ?? $choice['text'];

						// Skip non-values.
						if ( '' === $choice['value'] ) {
							continue;
						}

						$options[] =  $choice['value'] . '|' . $choice['text'];
					}

					$pod_field['pick_object']      = 'custom-simple';
					$pod_field['pick_custom']      = implode( "\n", $options );
					$pod_field['pick_format_type'] = $pick_format_types[ $gf_field_type ] ?? 'single';

					$pod_field[ 'pick_format_' . $pod_field['pick_format_type'] ] = $pick_formats[ $gf_field_type ] ?? 'list';

					break;
				default:
					break;
			}

			if ( empty( $inputs ) ) {
				if ( 'consent' === $gf_field_type ) {
					// Consent field should select the first input.
					$inputs[ $gf_field['id'] . '.1' ] = $pod_field;
				} else {
					$inputs[ $gf_field['id'] ] = $pod_field;
				}
			}

			foreach ( $inputs as $gf_field_id => $input ) {
				// Handle unique field name.
				$original_field_name = $input['name'];

				$counter = 1;

				if ( isset( $pod_field_names[ $input['name'] ] ) ) {
					$counter ++;

					$input['name'] = $original_field_name . $counter;
				}

				// Save the field.
				$save_params['fields'][ $input['name'] ] = $input;

				// Map the field.
				$existing_mapping['pod_fields'][ $input['name'] ] = $gf_field_id;
			}

			// Mark the whole field as mapped now.
			$existing_mapping['mapped_field_ids'][ $gf_field['id'] ] = true;
		}

		$save_params = array_filter( $save_params );

		if ( $save_params ) {
			$save_params['id']        = $pod_id;
			$save_params['name']      = $pod_name;
			$save_params['groups']    = [
				[
					'label'  => __( 'More Fields', 'pods-gravity-forms' ),
					'name'   => 'more_fields',
					'fields' => $save_params['fields'],
				],
			];

			unset( $save_params['fields'] );

			$pods_api->save_pod( $save_params );
		}

		$detected_mapping = $this->maybe_detect_feed_mapping( $gf_form, $pod_name, $existing_mapping );

		return [
			'pod_name' => $pod_name,
			'mapping'  => $detected_mapping,
		];
	}

	/**
	 * Handle maybe creating the Form Feed.
	 *
	 * @param array  $gf_form  The Gravity Form data.
	 * @param string $pod_name The Pod name.
	 */
	public function auto_create_feed( $gf_form, $pod_name, $mapping ) {
		$feed = [
			// Core feed info.
			'feedName'                                => __( 'Pods GF Auto-Generated Feed', 'pods-gravity-forms' ),
			// Pod name.
			'pod'                                     => $pod_name,
			// Default advanced options.
			'update_pod_item'                         => '0',
			'enable_markdown'                         => '0',
			'enable_current_post'                     => '0',
			'enable_prepopulate'                      => '0',
			'delete_entry'                            => '0',
			// Conditional logic defaults.
			'feed_condition_conditional_logic_object' => [],
			'feed_condition_conditional_logic'        => '0',
			// Set defaults for object fields.
			'wp_object_fields_post_title'             => '',
			'wp_object_fields_post_content'           => '',
			'wp_object_fields_post_excerpt'           => '',
			'wp_object_fields_post_author'            => '',
			'wp_object_fields_post_date'              => '',
			'wp_object_fields_post_name'              => '',
			'wp_object_fields_post_parent'            => '',
			'wp_object_fields_comments'               => '',
			'wp_object_fields__thumbnail_id'          => '',
		];

		if ( ! empty( $mapping['custom_fields'] ) ) {
			$feed['custom_fields'] = $mapping['custom_fields'];
		}

		if ( ! empty( $mapping['object_fields'] ) ) {
			foreach ( $mapping['object_fields'] as $field_name => $gf_field_id ) {
				$feed[ 'wp_object_fields_' . $field_name ] = $gf_field_id;
			}
		}

		if ( ! empty( $mapping['pod_fields'] ) ) {
			foreach ( $mapping['pod_fields'] as $field_name => $gf_field_id ) {
				$feed[ 'pod_fields_' . $field_name ] = $gf_field_id;
			}
		}

		return $this->insert_feed( $gf_form['id'], 1, $feed );
	}

	/**
	 * Handle maybe creating the Pod and Form Feed.
	 *
	 * @param array  $gf_form      The Gravity Form data.
	 * @param string $pod_type     The Pod type.
	 * @param string $storage_type The Pod storage type.
	 */
	public function maybe_auto_create_pod_and_feed( $gf_form, $pod_type, $storage_type ) {
		if ( 1 !== (int) pods_v( 'pods_auto_create' ) ) {
			return;
		}

		$pod_info = $this->auto_create_pod( $gf_form, $pod_type, $storage_type );

		$pod_name = $pod_info['pod_name'];
		$mapping  = $pod_info['mapping'];

		$feed_id  = $this->auto_create_feed( $gf_form, $pod_name, $mapping );

		if ( ! $feed_id ) {
			pods_ui_error( __( 'There was a problem auto-creating your feed.', 'pods-gravity-forms' ) );

			return;
		}

		pods_redirect( pods_query_arg( [
			'fid' => $feed_id,
			'pods_auto_create' => null,
		] ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @return array The list of feed setting fields.
	 */
	public function feed_settings_fields() {
		$form_id = (int) pods_v( 'id' );
		$feed_id = (int) pods_v( 'fid' );

		$gf_form = GFAPI::get_form( $form_id );

		$settings = [];

		if ( 1 === (int) pods_v( 'skcdebug' ) ) {
			pods_debug( $this->get_feed( $feed_id ) );
			pods_debug( $gf_form );
			die();
		}

		if ( 0 === $feed_id ) {
			$feeds = [];//$this->get_feeds( $form_id );

			if ( empty( $feeds ) ) {
				$this->maybe_auto_create_pod_and_feed( $gf_form, 'post_type', 'meta' );

				$settings['pod_mapping_create'] = [
					'title'  => __( 'Automatically create your configuration', 'pods-gravity-forms' ),
					'fields' => [],
				];

				$settings['pod_mapping_create']['fields'][] = [
					'type' => 'html',
					'name' => 'testingHtml',
					'html' => sprintf(
						'
							<p><strong>%1$s</strong> %2$s</p>
							<p><a href="%3$s" class="button">%4$s &raquo;</a></p>
						',
						__( 'NEW!', 'pods-gravity-forms' ),
						__( 'Now you can automatically create a new Pod and Form Feed mapping for this form.', 'pods-gravity-forms' ),
						esc_url( pods_query_arg( [ 'pods_auto_create' => 1 ] ) ),
						__( 'Auto-create a new Pod and Form Feed as a Custom Post Type', 'pods-gravity-forms' )
					),
				];
			}
		}

		///////////////////
		// Pod feed mapping
		///////////////////
		$settings['pod_mapping'] = [
			'title'  => __( 'Pod Feed Mapping', 'pods-gravity-forms' ),
			'fields' => [],
		];

		$settings['pod_mapping']['fields'][] = [
			'label'   => __( 'Name', 'pods-gravity-forms' ),
			'type'    => 'text',
			'name'    => 'feedName',
			'tooltip' => __( 'Name for this feed', 'pods-gravity-forms' ),
			'class'   => 'medium',
		];

		$gf_fields = [];

		if ( ! empty( $gf_form ) && ! empty( $gf_form['fields'] ) ) {
			$gf_fields = $gf_form['fields'];
		}

		$pods_api = pods_api();
		$all_pods = $pods_api->load_pods( [ 'labels' => true, 'names' => true ] );

		$pod_choice_list   = [];
		$pod_choice_list[] = [
			'label' => __( 'Select a Pod', 'pods-gravity-forms' ),
			'value' => '',
		];

		foreach ( $all_pods as $name => $label ) {
			$pod_choice_list[] = [
				'label' => $label . ' (' . $name . ')',
				'value' => $name,
			];
		}

		$settings['pod_mapping']['fields'][] = [
			'label'    => __( 'Pod', 'pods-gravity-forms' ),
			'type'     => 'select',
			'name'     => 'pod',
			'tooltip'  => __( 'Select the pod', 'pods-gravity-forms' ),
			'choices'  => $pod_choice_list,
			'onchange' => "jQuery(this).parents('form').submit();",
			'required' => true,
		];

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

		$pod_fields = [];
		$pod_type   = '';

		$detected_mapping = [];

		if ( ! empty( $selected_pod ) ) {
			$detected_mapping = $this->maybe_detect_feed_mapping( $gf_form, $selected_pod );

			$pod_object = $pods_api->load_pod( [ 'name' => $selected_pod ] );

			if ( ! empty( $pod_object ) ) {
				$pod_type = $pod_object['type'];

				foreach ( $pod_object['fields'] as $name => $field ) {
					$pod_fields[] = [
						'needs_process' => true,
						'name'          => $name,
						'field'         => $field,
					];
				}
			}
		}

		$feed_field_pod_fields = [
			'name'       => 'pod_fields',
			'label'      => '',
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => $pod_fields,
		];

		$ignore_object_fields = [
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
		];

		$wp_object_fields = [];

		if ( ! empty( $pod_object ) ) {
			foreach ( $pod_object['object_fields'] as $name => $field ) {
				if ( in_array( $name, $ignore_object_fields, true ) ) {
					continue;
				}

				if ( in_array( $pod_type, [ 'post_type', 'media' ], true ) ) {
					if ( 1 === $enable_current_post ) {
						$field['required'] = 0;
					} elseif ( in_array( $name, [ 'post_title', 'post_content' ], true ) ) {
						$field['required'] = 1;
					}
				} elseif ( 'taxonomy' === $pod_type ) {
					if ( 'name' === $name ) {
						$field['required'] = 1;
					}
				} elseif ( 'user' === $pod_type ) {
					if ( 1 === $enable_current_user ) {
						$field['required'] = 0;
					} elseif ( 'user_login' === $name ) {
						$field['required'] = 1;
					}
				}

				$wp_object_fields[ $name ] = [
					'needs_process' => true,
					'name'          => $name,
					'field'         => $field,
				];
			}
		}

		if ( 'post_type' === $pod_type ) {
			$wp_object_fields['_thumbnail_id'] = [
				'name'  => '_thumbnail_id',
				'label' => __( 'Featured Image', 'pods-gravity-forms' ),
			];
		}

		$feed_field_wp_object_fields = [
			'name'       => 'wp_object_fields',
			'label'      => '',
			'type'       => 'field_map',
			'dependency' => 'pod',
			'field_map'  => array_values( $wp_object_fields ),
		];

		$args_for_formatting = [
			'wp_object_fields'    => $wp_object_fields,
			'pod_type'            => $pod_type,
			'enable_current_post' => $enable_current_post,
			'enable_current_user' => $enable_current_user,
			'feed_id'             => $feed_id,
			'gf_fields'           => $gf_fields,
		];

		if ( ! empty( $feed_field_wp_object_fields['field_map'] ) ) {
			$feed_field_wp_object_fields['field_map'] = $this->format_field_map( $feed_field_wp_object_fields['field_map'], $args_for_formatting );

			$settings['pod_mapping_object_fields'] = [
				'title'  => __( 'Object Field Mapping', 'pods-gravity-forms' ),
				'fields' => [
					$feed_field_wp_object_fields,
				],
			];
		}

		if ( ! empty( $feed_field_pod_fields['field_map'] ) ) {
			$feed_field_pod_fields['field_map'] = $this->format_field_map( $feed_field_pod_fields['field_map'], $args_for_formatting );

			$settings['pod_mapping_fields'] = [
				'title'  => __( 'Field Mapping', 'pods-gravity-forms' ),
				'fields' => [
					$feed_field_pod_fields,
				],
			];
		}

		$blacklisted_keys = [];

		$blacklisted_keys[] = wp_list_pluck( $feed_field_pod_fields['field_map'], 'name' );

		if ( ! empty( $feed_field_wp_object_fields['field_map'] ) ) {
			$blacklisted_keys[] = wp_list_pluck( $feed_field_wp_object_fields['field_map'], 'name' );
		}

		$blacklisted_keys = array_merge( ...$blacklisted_keys );

		///////////////////
		// Custom fields
		///////////////////
		if ( in_array( $pod_type, [ 'post_type', 'taxonomy', 'user', 'media', 'comment' ], true ) ) {
			$settings['custom_fields'] = [
				'title'  => esc_html__( 'Custom Field Mapping', 'pods-gravity-forms' ),
				'fields' => [
					[
						'name'        => 'custom_fields',
						'label'       => '',
						'description' => esc_html__( 'If you have additional fields to map for this Pod, you can customize them here.', 'pods-gravity-forms' ),
						'type'        => 'generic_map',
						'key_field'   => [
							'choices'     => $this->get_meta_field_map( $selected_pod, $pod_type, $blacklisted_keys ),
							'placeholder' => esc_html__( 'Custom Field Name', 'pods-gravity-forms' ),
							'title'       => esc_html__( 'Name', 'pods-gravity-forms' ),
						],
						'value_field' => [
							'choices'      => 'form_fields',
							'custom_value' => false,
							'merge_tags'   => true,
							'placeholder'  => esc_html__( 'Custom Field Value', 'pods-gravity-forms' ),
						],
					],
				],
			];
		}

		///////////////////
		// Advanced
		///////////////////
		$settings['advanced'] = [
			'title'  => __( 'Advanced', 'pods-gravity-forms' ),
			'fields' => [],
		];

		$settings['advanced']['fields'][] = [
			'name'    => 'update_pod_item',
			'label'   => __( 'Support entry updates', 'pods-gravity-forms' ),
			'type'    => 'checkbox',
			'choices' => [
				[
					'value' => 1,
					'label' => __( 'Update pod item if the entry is updated', 'pods-gravity-forms' ),
					'name'  => 'update_pod_item',
				],
			],
		];

		$settings['advanced']['fields'][] = [
			'name'    => 'enable_markdown',
			'label'   => __( 'Enable Markdown', 'pods-gravity-forms' ),
			'type'    => 'checkbox',
			'choices' => [
				[
					'value' => 1,
					'label' => __( 'Enable Markdown in HTML Fields', 'pods-gravity-forms' ),
					'name'  => 'enable_markdown',
				],
			],
		];

		if ( 'user' === $pod_type ) {
			$settings['advanced']['fields'][] = [
				'name'    => 'enable_current_user',
				'label'   => __( 'Enable editing with this form using logged in user', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => [
					[
						'value' => 1,
						'label' => __( 'Enable editing with this form using the logged in user data', 'pods-gravity-forms' ),
						'name'  => 'enable_current_user',
					],
				],
			];

			$settings['advanced']['fields'][] = [
				'name'    => 'enable_prepopulate',
				'label'   => __( 'Enable populating field values for this form using logged in user', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => [
					[
						'value' => 1,
						'label' => __( 'Enable populating field values for this form using the logged in user data', 'pods-gravity-forms' ),
						'name'  => 'enable_prepopulate',
					],
				],
			];
		} elseif ( in_array( $pod_type, [ 'post_type', 'media' ], true ) ) {
			$settings['advanced']['fields'][] = [
				'name'    => 'enable_current_post',
				'label'   => __( 'Enable editing with this form using current post', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => [
					[
						'value' => 1,
						'label' => __( 'Enable editing with this form using the current post ID (only works on singular template)', 'pods-gravity-forms' ),
						'name'  => 'enable_current_post',
					],
				],
			];

			$settings['advanced']['fields'][] = [
				'name'    => 'enable_prepopulate',
				'label'   => __( 'Enable populating field values for this form using current post', 'pods-gravity-forms' ),
				'type'    => 'checkbox',
				'choices' => [
					[
						'value' => 1,
						'label' => __( 'Enable populating field values for this form using the current post ID (only works on singular template)', 'pods-gravity-forms' ),
						'name'  => 'enable_prepopulate',
					],
				],
			];
		}

		$settings['advanced']['fields'][] = [
			'name'    => 'delete_entry',
			'label'   => __( 'Delete Gravity Form Entry on submission', 'pods-gravity-forms' ),
			'type'    => 'checkbox',
			'choices' => [
				[
					'value' => 1,
					'label' => __( 'Delete entry after processing', 'pods-gravity-forms' ),
					'name'  => 'delete_entry',
				],
			],
		];

		add_filter( "gform_field_map_choices", [ $this, 'add_field_map_choices' ] );

		$settings['advanced']['fields'][] = [
			'name'           => 'feed_condition_conditional_logic',
			'label'          => __( 'Conditional Logic', 'pods-gravity-forms' ),
			'checkbox_label' => __( 'Enable', 'pods-gravity-forms' ),
			'type'           => 'feed_condition',
		];

		// Clear up the list of fields.
		foreach ( $settings as $section_name => $section ) {
			$settings[ $section_name ]['fields'] = array_filter( $section['fields'] );
		}

		return $settings;
	}

	protected function format_field_map( $fields, $args ) {
		// Build field mapping data arrays
		foreach ( $fields as $k => $field_map ) {
			if ( ! empty( $field_map['needs_process'] ) ) {
				$name  = $field_map['name'];
				$field = $field_map['field'];

				$field_required = false;

				if ( isset( $field['required'] ) && 1 === (int) $field['required'] ) {
					$field_required = true;

					if ( isset( $args['wp_object_fields'][ $name ] ) ) {
						if ( in_array( $args['pod_type'], [ 'post_type', 'media' ], true ) && 1 === $args['enable_current_post'] ) {
							$field_required = false;
						} elseif ( 'user' === $args['pod_type'] && 1 === $args['enable_current_user'] ) {
							$field_required = false;
						}
					}
				}

				$field_map = [
					'name'     => $name,
					'label'    => $field['label'],
					'required' => $field_required,
				];

				if ( 0 === $args['feed_id'] ) {
					foreach ( $args['gf_fields'] as $gf_field ) {
						if ( strtolower( $field['label'] ) === strtolower( $gf_field['label'] ) ) {
							$field_map['default_value'] = $gf_field['id'];
						}
					}
				}
			}

			// Add field name info to the label.
			$field_map['label'] = sprintf( '%s (%s)', esc_html( $field_map['label'] ), esc_html( $field_map['name'] ) );

			$fields[ $k ] = $field_map;
		}

		return $fields;
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
	 * @return array
	 * @uses GFFormsModel::get_custom_field_names()
	 *
	 */
	public function get_meta_field_map( $pod_name = '', $pod_type = '', $blacklisted_keys = [] ) {
		// Setup meta fields array.
		$meta_fields = [
			[
				'label' => '-- ' . esc_html__( 'Select a Custom Field Name', 'pods-gravity-forms' ) . ' --',
				'value' => '',
			],
		];

		///////////////////
		// Custom key
		///////////////////
		$meta_fields[] = [
			'label' => '-- ' . esc_html__( 'Add New Custom Field Name', 'pods-gravity-forms' ) . ' --',
			'value' => 'gf_custom',
		];

		///////////////////
		// Custom fields
		///////////////////

		// Get most used post meta keys
		$meta_keys = $this->get_custom_field_names( $pod_name, $pod_type );

		// If no meta keys exist, return an empty array.
		if ( empty( $meta_keys ) ) {
			return [];
		}

		// Add post meta keys to the meta fields array.
		foreach ( $meta_keys as $meta_key ) {
			$meta_fields[] = [
				'label' => $meta_key,
				'value' => $meta_key,
			];
		}

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
	public function get_custom_field_names( $pod_name = '', $pod_type = '', $blacklisted_keys = [] ) {
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

		$pods_blacklist_keys = [];

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

		$choices = array_merge( [
				// Add first choice back
				[
					'value' => '',
					'label' => __( 'Select a Field', 'pods-gravity-forms' ),
				],
				// Make custom override first option
				[
					'value' => 'gf_custom',
					'label' => __( 'Custom override value', 'pods-gravity-forms' ),
				],
			], $choices, [
				'label'   => esc_html__( 'Payment Properties', 'pods-gravity-forms' ),
				'choices' => [
					[
						'value' => 'transaction_id',
						'label' => 'Transaction ID',
					],
					[
						'value' => 'payment_amount',
						'label' => 'Payment Amount',
					],
					[
						'value' => 'payment_date',
						'label' => 'Payment Date',
					],
					[
						'value' => 'payment_status',
						'label' => 'Payment Status',
					],
				],
			] );

		foreach ( $choices as $k => $choice ) {
			if ( empty( $choice['value'] ) ) {
				continue;
			}

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
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a
	 *                     string
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
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @return string The field mapping title.
	 */
	public function field_map_title() {
		return __( 'Pod Field', 'pods-gravity-forms' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @return array The list of columns to show.
	 */
	public function feed_list_columns() {
		return [
			'feedName' => __( 'Name', 'pods-gravity-forms' ),
			'pod'      => __( 'Pod', 'pods-gravity-forms' ),
		];
	}

	/**
	 * Get the column value for the Pod feed option.
	 *
	 * @since 2.0.0
	 *
	 * @param array $feed The form feed configuration.
	 *
	 * @return string The column value output.
	 */
	public function get_column_value_pod( $feed ) {
		if ( empty( $feed['meta']['pod'] ) ) {
			return '<em>' . esc_html__( 'Invalid Pod', 'pods-gravity-forms' ) . '</em>';
		}

		try {
			$pod = pods_api()->load_pod( [
				'name' => $feed['meta']['pod'],
			] );
		} catch ( Exception $e ) {
			$pod = false;
		}

		if ( ! $pod ) {
			return '<em>' . esc_html__( 'Invalid Pod', 'pods-gravity-forms' ) . '</em>';
		}

		return sprintf(
			'<strong>%1$s (%2$s)</strong><br /><em>[%3$s]</em>',
			esc_html( $pod['label'] ),
			esc_html( $pod['name'] ),
			esc_html( $pod['type'] )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 *
	 * @return string The custom menu icon.
	 */
	public function get_menu_icon() {
		return pods_svg_icon( 'pods', 'dashicons-filter', 'svg' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function init_admin() {
		parent::init_admin();

		add_action( 'gform_field_standard_settings', [ $this, 'custom_field_settings_register' ] );
		add_filter( 'gform_tooltips', [ $this, 'custom_field_settings_tooltips' ] );
		add_action( 'gform_editor_js', [ $this, 'custom_field_settings_editor_script' ] );
	}

	/**
	 * Register our custom field settings.
	 *
	 * @since 2.0.0
	 *
	 * @param int $position The current setting section position.
	 */
	public function custom_field_settings_register( $position ) {
		// Output our field settings at the end of the settings fields (-1 is last one).
		if ( - 1 !== $position ) {
			return;
		}

		?>
		<li class="pods_populate_related_items_setting field_setting">
			<label for="pods_populate_related_items_value"><?php esc_html_e( 'Pods (requires a feed)', 'pods-gravity-forms' ); ?></label>

			<input type="checkbox" id="pods_populate_related_items_value" onclick="SetFieldProperty('pods_populate_related_items', this.checked);" />
			<label for="pods_populate_related_items_value" class="inline">
				<?php esc_html_e( 'Populate Choices from the mapped Pods Relationship Field', 'pods-gravity-forms' ); ?>
				<?php gform_tooltip( 'form_populate_related_items_value' ); ?>
			</label>
		</li>
		<?php
	}

	/**
	 * Output the form editor JS needed for the field settings.
	 *
	 * @since 2.0.0
	 */
	public function custom_field_settings_editor_script() {
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
	 * Add the tooltips needed for our field settings.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tooltips The list of tooltips.
	 *
	 * @return array The list of tooltips.
	 */
	public function custom_field_settings_tooltips( $tooltips ) {
		$tooltips['form_populate_related_items_value'] = sprintf( '<h6>%s</h6> %s', __( 'Populate Related Items from Pods', 'pods-gravity-forms' ), __( 'Check this box to populate the related items from Pods instead of keeping the list up-to-date manually.' ) );

		return $tooltips;
	}

	/**
	 * @todo Review filters/actions for refactor and all code below.
	 *
	 * Init integration.
	 */
	public function init() {
		parent::init();

		if ( ! $this->is_gravityforms_supported() ) {
			return;
		}

		// Handle normal forms.
		add_filter( 'gform_pre_render', [ $this, '_gf_pre_render' ], 9, 3 );
		add_filter( 'gform_admin_pre_render', [ $this, '_gf_pre_render' ], 9, 1 );
		add_filter( 'gform_pre_process', [ $this, '_gf_pre_process' ] );

		// Handle merge tags
		add_filter( 'gform_custom_merge_tags', [ $this, '_gf_custom_merge_tags' ], 10, 2 );
		add_filter( 'gform_merge_tag_data', [ $this, '_gf_add_merge_tags' ], 10, 3 );
		add_filter( 'gform_replace_merge_tags', [ $this, '_gf_replace_merge_tags' ], 10, 2 );

		// Handle entry detail edits.
		add_action( 'gform_pre_entry_detail', [ $this, '_gf_pre_entry_detail' ], 10, 2 );
		add_action( 'check_admin_referer', [ $this, '_check_admin_referer' ], 10, 2 );
		add_action( 'gform_entry_detail_content_before', [ $this, '_gf_entry_detail_content_before' ], 10, 2 );

		// Handle entry updates.
		add_action( 'gform_post_update_entry', [ $this, '_gf_post_update_entry' ], 9, 2 );
		add_action( 'gform_after_update_entry', [ $this, '_gf_after_update_entry' ], 9, 3 );

		// Handle Payment Add-on callbacks.
		add_action( 'gform_action_pre_payment_callback', [ $this, '_gf_action_pre_payment_callback' ], 10, 2 );
	}

	/**
	 * Processes feed action.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array $feed  The Feed Object currently being processed.
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form  The Form Object currently being processed.
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
		} catch ( Exception $e ) {
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
		remove_action( 'check_admin_referer', [ $this, '_check_admin_referer' ] );
		remove_action( 'gform_entry_detail_content_before', [ $this, '_gf_entry_detail_content_before' ] );

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

		static $setup = [];

		if ( ! empty( $setup[ $form['id'] ] ) ) {
			return $setup[ $form['id'] ];
		}

		$feeds = $this->get_feeds( $form['id'] );

		if ( empty( $feeds ) ) {
			$setup[ $form['id'] ] = $form;

			return $setup[ $form['id'] ];
		}

		$pod_fields = [];
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

			$dynamic_selects = [];

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
				$object_params = [
					'limit' => - 1,
				];

				$data = PodsForm::field_method( $pod_field_options['type'], 'get_field_data', $pod_field_options, [], $object_params );

				if ( empty( $data ) ) {
					continue;
				}

				if ( isset( $data[''] ) ) {
					unset( $data[''] );
				}

				$select_text = pods_v( $pod_field_options['type'] . '_select_text', $pod_field_options, __( '-- Select One --', 'pods-gravity-forms' ), true );

				$options = [
					'options' => $data,
				];

				if ( $select_text ) {
					$options['select_text'] = $select_text;
				}

				$dynamic_selects[ $gf_field->id ] = $options;
			}

			if ( ! empty( $dynamic_selects ) ) {
				pods_gf();

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
		static $setup = [];

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

		$options = [
			// array ( 'gf_field_id' => 'pod_field_name' )
			'fields'              => $fields,
			'update_pod_item'     => (int) pods_v( 'update_pod_item', $feed['meta'], 0 ),
			'markdown'            => (int) pods_v( 'enable_markdown', $feed['meta'], 0 ),
			'auto_delete'         => (int) pods_v( 'delete_entry', $feed['meta'], 0 ),
			'gf_to_pods_priority' => 'submission',
		];

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
		} elseif ( in_array( $pod->pod_data['type'], [ 'post_type', 'media' ], true ) && is_singular( $pod->pod ) ) {
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
			 * @param int    $prepopulate_id ID to use when prepopulating
			 * @param string $pod_name       Pod name
			 * @param int    $form_id        GF Form ID
			 * @param array  $feed           GF Form feed array
			 * @param array  $form           GF Form array
			 * @param array  $options        Pods GF options
			 * @param Pods   $pod            Pods object
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
		$merge_tags[] = [
			'tag'   => '{pods:id}',
			'label' => esc_html__( 'Pods GF Item ID', 'pods-gravity-forms' ),
		];

		$merge_tags[] = [
			'tag'   => '{pods:permalink}',
			'label' => esc_html__( 'Pods GF Item Permalink', 'pods-gravity-forms' ),
		];

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

		$data['pods'] = [
			'id'        => $id,
			'permalink' => $permalink,
		];

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
		$id_merge_tags = [
			'{pods:id}',
			'{gf_to_pods_id}',
			'{@gf_to_pods_id}',
		];

		// For backcompat purposes.
		$permalink_merge_tags = [
			'{pods:permalink}',
			'{gf_to_pods_permalink}',
			'{@gf_to_pods_permalink}',
		];

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

		$fields = [];

		$skip = [];

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
			$mapping_value = [
				'gf_field' => $gf_field,
				'field'    => $field_name,
			];

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
			return [];
		}

		$configs = $feed['meta']['custom_fields'];

		$fields = [];

		foreach ( $configs as $config ) {
			$config = array_map( 'trim', $config );

			$gf_field   = $config['value'];
			$field_name = $config['key'];

			if ( in_array( $field_name, [ 'gf_custom', '' ], true ) ) {
				$field_name = $config['custom_key'];
			}

			// Mapping value
			$mapping_value = [
				'gf_field' => $gf_field,
				'field'    => $field_name,
			];

			if ( in_array( $gf_field, [ 'gf_custom', '' ], true ) && ! empty( $config['custom_value'] ) ) {
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
			$entry_meta['_pods_item_id'] = [
				'label'                      => 'Pod Item ID',
				'is_numeric'                 => true,
				'is_default_column'          => true,
				'update_entry_meta_callback' => [ $this, 'update_entry_meta_pod_id' ],
				'filter'                     => [
					'operators' => [
						'is',
						'isnot',
						'>',
						'<',
					],
				],
			];
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
		add_filter( 'gform_export_form', [ $this, '_gf_export_form' ] );
		add_action( 'gform_forms_post_import', [ $this, '_gf_forms_post_import' ] );

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
	 * @return array
	 * @uses   GFFeedAddOn::get_feeds()
	 *
	 */
	public function _gf_export_form( $form ) {
		// Get feeds for form.
		$feeds = $this->get_feeds( $form['id'] );

		// If feeds array does not exist for form, create it.
		if ( ! isset( $form['feeds'] ) ) {
			$form['feeds'] = [];
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
