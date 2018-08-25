<?php

namespace Pods\GF\Tests;

use Codeception\TestCase\WPTestCase;
use PHPUnit\Framework\Assert;

class Test_Helper {

	/**
	 * Test Organizers, Venues, and Etc data.
	 *
	 * @var array
	 */
	public static $data = [];

	/**
	 * Helper for before running each test.
	 */
	public static function before_test() {

		/**
		 * @var $wpdb \wpdb
		 */
		global $wpdb;

		$rebuild_data = filter_var( getenv( 'TEST_REBUILD_DATA' ), FILTER_VALIDATE_BOOLEAN );

		if ( $rebuild_data ) {
			$sql = "
				SELECT `TABLE_NAME`
				FROM `INFORMATION_SCHEMA`.`TABLES`
				WHERE
					`TABLE_SCHEMA` = %s
					AND (
						`TABLE_NAME` LIKE %s
						OR `TABLE_NAME` LIKE %s
						OR `TABLE_NAME` LIKE %s
						OR `TABLE_NAME` LIKE %s
						OR `TABLE_NAME` LIKE %s
					)
			";

			$prepare = [
				DB_NAME,
				$wpdb->esc_like( $wpdb->prefix . 'rg_' ) . '%',
				$wpdb->esc_like( $wpdb->prefix . 'gf_' ) . '%',
				$wpdb->esc_like( $wpdb->prefix . 'pods' ) . '%',
				$wpdb->esc_like( $wpdb->prefix . 'post' ) . '%',
				$wpdb->esc_like( $wpdb->prefix . 'term' ) . '%',
			];

			$tables = $wpdb->get_col( $wpdb->prepare( $sql, $prepare ) );

			foreach ( $tables as $table ) {
				$wpdb->query( "TRUNCATE `{$table}`" );
			}

			pods_init()->reset();
		}

		if ( $rebuild_data ) {
			wp_set_current_user( 1 );

			// do setup
			self::setup_forms();

			echo "\nData rebuilt, you can now export the updated SQL to tests/_data/test-data.sql\n";
			die();
		}

	}

	/**
	 * Helper for after running each test.
	 */
	public static function after_test() {

		/**
		 * @var $wpdb \wpdb
		 */
		global $wpdb;

		$wpdb->show_errors( true );
		$wpdb->suppress_errors( false );

		// Clear sessions/cookies
		\WP_User_Meta_Session_Tokens::drop_sessions();

		wp_set_current_user( 0 );

		$_SESSION = [];
		$_COOKIE  = [];

		// Clear caches
		global $wp_object_cache;

		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->stats          = [];
			$wp_object_cache->memcache_debug = [];

			// Make sure this is a public property, before trying to clear it
			try {
				$cache_property = new \ReflectionProperty( $wp_object_cache, 'cache' );
				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = [];
				}
				unset( $cache_property );
			} catch ( \ReflectionException $e ) {
			}

			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( [ $wp_object_cache, '__remoteset' ] ); // important
			}
		} else {
			wp_cache_flush();
		}

	}

	/**
	 * Get mapping config.
	 *
	 * @return array
	 */
	public static function get_mapping_config() {

		$mapping_config = array(
			array(
				'gf_type'  => 'text',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'password',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'phone',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'email',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'oembed',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'boolean',
			),
			array(
				'gf_type'  => 'text',
				'pod_type' => 'color',
			),
			array(
				'gf_type'  => 'text + enablePasswordInput=1',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'text + enablePasswordInput=1',
				'pod_type' => 'password',
			),
			array(
				'gf_type'  => 'textarea',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'textarea',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'textarea',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'textarea',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'textarea + useRichTextEditor=1',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'textarea + useRichTextEditor=1',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'textarea + useRichTextEditor=1',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'textarea + useRichTextEditor=1',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'phone',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'email',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'oembed',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'boolean',
			),
			array(
				'gf_type'  => 'select',
				'pod_type' => 'color',
			),
			array(
				'gf_type'  => 'multiselect',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'number',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'checkbox',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'phone',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'email',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'oembed',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'boolean',
			),
			array(
				'gf_type'  => 'radio',
				'pod_type' => 'color',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'password',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'phone',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'email',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'oembed',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'boolean',
			),
			array(
				'gf_type'  => 'hidden',
				'pod_type' => 'color',
			),
			array(
				'gf_type'  => 'name',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'name',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'name',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'name',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'date + dateType=datefield',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'date + dateType=datefield',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'date + dateType=datefield',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'date + dateType=datefield',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'date + dateType=datefield',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'date + dateType=datepicker',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'date + dateType=datepicker',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'date + dateType=datepicker',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'date + dateType=datepicker',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'date + dateType=datepicker',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'date + dateType=datedropdown',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'date + dateType=datedropdown',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'date + dateType=datedropdown',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'date + dateType=datedropdown',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'date + dateType=datedropdown',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'time + timeFormat=12',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'time + timeFormat=12',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'time + timeFormat=12',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'time + timeFormat=12',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'time + timeFormat=12',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'time + timeFormat=24',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'time + timeFormat=24',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'time + timeFormat=24',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'time + timeFormat=24',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'time + timeFormat=24',
				'pod_type' => 'time',
			),
			array(
				'gf_type'  => 'phone',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'phone',
				'pod_type' => 'phone',
			),
			array(
				'gf_type'  => 'phone',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'phone',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'phone',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'address',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'address',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'address',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'address',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'website',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'website',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'website',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'website',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'website',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'email',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'email',
				'pod_type' => 'email',
			),
			array(
				'gf_type'  => 'email',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'email',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'email',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'fileupload',
				'pod_type' => 'file',
			),
			array(
				'gf_type'  => 'list',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'list',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'list',
				'pod_type' => 'pick',
			),
			array(
				'gf_type'  => 'postcreation_post_title',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'postcreation_post_title',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'postcreation_post_title',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'postcreation_post_title',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'postcreation_post_content',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'postcreation_post_content',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'postcreation_post_content',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'postcreation_post_content',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'postcreation_post_excerpt',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'postcreation_post_excerpt',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'postcreation_post_excerpt',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'postcreation_post_excerpt',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'product',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'product',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'product',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'product',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'quantity',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'option',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'option',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'option',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'option',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'shipping',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'total',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'entry_id',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'entry_id',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'entry_id',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'entry_id',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'entry_id',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'entry_date',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'user_agent',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'user_agent',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'user_agent',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'user_agent',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'ip',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'ip',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'ip',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'ip',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'source_url',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'source_url',
				'pod_type' => 'website',
			),
			array(
				'gf_type'  => 'source_url',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'source_url',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'source_url',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'transaction_id',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'transaction_id',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'transaction_id',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'transaction_id',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'transaction_id',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'number',
			),
			array(
				'gf_type'  => 'payment_amount',
				'pod_type' => 'currency',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'datetime',
			),
			array(
				'gf_type'  => 'payment_date',
				'pod_type' => 'date',
			),
			array(
				'gf_type'  => 'payment_status',
				'pod_type' => 'text',
			),
			array(
				'gf_type'  => 'payment_status',
				'pod_type' => 'paragraph',
			),
			array(
				'gf_type'  => 'payment_status',
				'pod_type' => 'wysiwyg',
			),
			array(
				'gf_type'  => 'payment_status',
				'pod_type' => 'code',
			),
			array(
				'gf_type'  => 'payment_status',
				'pod_type' => 'pick',
			),
		);

		return $mapping_config;

	}

	/**
	 * Setup test forms.
	 */
	public static function setup_forms() {

		$mapping_config = self::get_mapping_config();

		$pods = array();

		$form_meta = array(
			'title' => 'Test form',
		);

		\GFAPI::add_form( $form_meta );

		$gf_fields = array();
		$pod_fields = array();

		foreach ( $mapping_config as $config ) {

		}

	}

	/**
	 * Short-circuits calls to the `wp_remote_post` and `wp_remote_get` functions and sets expectations on their call
	 * arguments.
	 *
	 * @param WPTestCase $test_case      Test case.
	 * @param null|int   $expected_times The number of times the `wp_remote_post` function is expected to be called;
	 *                                   passing `null` here means the function will be simply short-circuited.
	 * @param array      $expected_urls  An array of expected URLs for each call; the first one will be matched to the
	 *                                   first, the second one to the second call and so on; empty values will not be
	 *                                   matched.
	 * @param mixed $return The response return value; defaults to `false`.
	 */
	public static function mock_wp_remote_call( $test_case, $expected_times = null, array $expected_urls = [], $return = false ) {
		add_filter( 'pre_http_request', function ( $block, $r, $url ) use ( $expected_times, $expected_urls, $return ) {
			static $times;
			$times = null === $times ? 1 : ++ $times;

			if ( ! empty( $expected_urls[ $times - 1 ] ) ) {
				$expected_url = $expected_urls[ $times - 1 ];
				if ( $expected_url !== $url ) {
					$message = "Expected '{$expected_url}' at call {$times}; got '{$url}'";
					throw new \Exception( $message );
				}
			}

			if ( 0 < $expected_times ) {
				if ( $times === $expected_times ) {
					throw new MockedCall();
				}
			}

			return $return;
		}, 1, 3 );

		if ( null !== $expected_times ) {
			$test_case->expectException( MockedCall::class );
		}
	}

}
