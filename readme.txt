=== Pods Gravity Forms Add-On ===
Contributors: sc0ttkclark, jimtrue, naomicbush, gravityplus
Donate link: https://pods.io/friends-of-pods/
Tags: pods, gravity forms, form mapping
Requires at least: 4.6
Tested up to: 6.0
Requires PHP: 5.4
Stable tag: 1.4.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate with Gravity Forms to create a Pod item from a form submission.

== Description ==

Requires [Pods](https://wordpress.org/plugins/pods/) 2.4+, [Gravity Forms](http://www.gravityforms.com/) 1.9+.

Check out [pods.io](http://pods.io/) for our User Guide, Forums, and other resources to help you develop with Pods.

Please report bugs or request featured on [GitHub](https://github.com/pods-framework/pods-gravity-forms/)

Special thanks to Rocketgenius for their sponsorship support and to Naomi C. Bush for her help in the initial add-on UI work.

= WP-CLI Command for Syncing Entries =

This add-on provides the ability to sync entries from a Form Submission and Entry Edit screen. To bulk sync all entries even prior to setting up a Pods Gravity Form Feed, you can run a WP-CLI command.

**Example 1: Sync all entries for Form 123 first active Pod feed**

`wp pods-gf sync --form=123`

**Example 2: Sync all entries for Form 123 using a specific feed (even if it is inactive)**

`wp pods-gf sync --form=123 --feed=2`

= Mapping GF List Fields to a Pods Relationship field =

You can map a GF List field to a Relationship field related to another Pod. Using the below examples you can customize how the automatic mapping works. By default, the list columns will map to the pod fields with the same labels.

**Example 1: Customize what columns map to which Related Pod fields for Form ID 1, Field ID 2**

Customizing a list field row can be done by using the `pods_gf_field_columns_mapping` filter, which has Form ID and Field ID variations (`pods_gf_field_columns_mapping_{form_id}` and `pods_gf_field_columns_mapping_{form_id}_{field_id}`).

`
add_filter( 'pods_gf_field_columns_mapping_1_2', 'my_columns_mapping', 10, 4 );

/**
 * Filter list columns mapping for related pod fields.
 *
 * @param array    $columns  List field columns.
 * @param array    $form     GF form.
 * @param GF_Field $gf_field GF field data.
 * @param Pods     $pod      Pods object.
 *
 * @return array
 */
function my_columns_mapping( $columns, $form, $gf_field, $related_obj ) {

	$columns[0] = 'first_field';
	$columns[1] = 'second_field';
	$columns[2] = 'third_field';

	return $columns;

}
`

**Example 2: Customize a List row for Form ID 1, Field ID 2**

Customizing a list field row can be done by using the `pods_gf_field_column_row` filter, which has Form ID and Field ID variations (`pods_gf_field_column_row_{form_id}` and `pods_gf_field_column_row_{form_id}_{field_id}`).

`
add_filter( 'pods_gf_field_column_row_1_2', 'my_column_row_override', 10, 6 );

/**
 * Filter list field row for relationship field saving purposes.
 *
 * @param array      $row         List field row.
 * @param array      $columns     List field columns.
 * @param array      $form        GF form.
 * @param GF_Field   $gf_field    GF field data.
 * @param array      $options     Pods GF options.
 * @param Pods|false $related_obj Related Pod object.
 *
 * @return array
 */
function my_column_row_override( $row, $columns, $form, $gf_field, $options, $related_obj ) {

	// Update certain row fields based on the value of specific column.
	if ( ! empty( $row['user_relationship_field'] ) ) {
		$user = get_userdata( (int) $row['user'] );

		// Set the post_title to match the User display name.
		if ( $user && ! is_wp_error( $user ) ) {
			$row['post_title'] = $user->display_name;
		}
	}

	return $row;

}
`

== Screenshots ==

1. In the Pods Admin, create your Pods and Pod Fields: Pods Admin -> Add New
2. In the Pods Admin, create your Pods and Pod Fields: Pod Edit Screen
3. Create your Gravity Form that will be used to create a Pod item
4. Form Settings->Pods menu
5. Pods feed page
6. Map form fields to Pod fields
7. Example form
8. New Pod item created from form submission
9. Form entries page showing Pod ID

== Changelog ==

= 1.4.5 - July 22nd, 2022 =

* Tested against WP 6.0
* Added: Not seeing something map correctly? As a site admin, you now have the power to debug the form submission and see what might be going on. Add `?pods_gf_debug_gf_to_pods=1` to the URL of the form action before submitting to take advantage of the admin-only debug mode. This will output the values as they would be sent to Pods, the entry information used to reference it, and the feed options used at the time. It will stop the form from completely saving to Pods so you can tweak and debug your form feeds however much you'd like to perfect them. (@sc0ttkclark)
* Fixed: Conditional checks for feeds has been resolved and now won't get confused when there are multiple feeds for the same form in certain cases. (@sc0ttkclark)
* Fixed: Additional compatibility with Gravity Flow. #157 (@JoryHogeveen)
* Pods 2.9 is in beta and after it is released, new mapping of GF List Fields to Pods repeatable fields will be added. This add-on will also be updated with minimum version requirements updated for WP 5.5+, Pods 2.8+, and Gravity Forms 2.5+. Complete testing will be done at that time to ensure complete compatibility.

= 1.4.4 - October 6th, 2021 =

* Tested against WP 5.8
* Get ready for Pods 2.8 in just a week! This add-on will receive updates to ensure it is compatible with the latest Gravity Forms and the changes in Pods 2.8

= 1.4.3 - March 26th, 2020 =

* Added: Now requiring PHP 5.4+
* Added: Freemius support when running Pods 2.7.17
* Fixed: Prepopulate handling for relationship fields.
* Fixed: Prevent errors when form doesn't exist by the time it gets to our hook.

= 1.4.2 - March 2nd, 2020 =

* Fixed: Ajax handling for various callbacks that hook into `gform_pre_render`.
* Fixed: Cleaned up logic and prevent PHP notices with multi-select arrays when setting up choices arrays.
* Fixed: Make sure `Pods_GF_UI` does not return false on UI callbacks to prevent access errors.
* Fixed: Add mapping feeds to the import/export! (props @travislopes)

= 1.4.1 - October 16th, 2018 =

* Fixed: When syncing multiple entries, the field values were caching and not unique per entry resulting in what appeared to be duplicated content inserts/updates.

= 1.4 - October 16th, 2018 =

* Support: Added support for Gravity Forms 2.3 database tables changes (You may see a warning on the Edit Pod screen but this is a false positive because we cache a list of all tables to transients and it triggers the warning solved by removing those old "rg" tables)
* Changed: Backwards compatibility issue -- You can now more easily set custom override values, however the old style was not able to be brought over -- you'll want to update your feeds when possible, the old values will not show up and you'll have to select the custom override value option once more, then fill it in
* Changed: Backwards compatibility issue -- Now requiring WordPress 4.6+
* Feature: When editing entries in the admin area, changes now sync to the associated Pod item (except trash/deletes)
* Feature: New Bulk Entry Syncing to Pods WP-CLI command `wp pods-gf sync --form=123` or you can specify which feed (even if it is not active) with `wp pods-gf sync --form=123 --feed=2`
* Feature: Support for List field mapping to a Pod field which ends up serializing the value, but can be prepopulated back into the Gravity Form
* Feature: List field mapping to relationship fields related to another Pod (list columns map to individual fields in the related Pod) with new filters `pods_gf_field_columns_mapping` and `pods_gf_field_column_row`
* Feature: Support for Chained Select field mapping to a Pod field
* Feature: New Custom fields section added for Pods that support meta (Posts, Terms, Users, Media, and Comments), you can set additional custom fields including ability to set custom values there too
* Feature: Ability to set conditional processing per feed, based on specific values submitted
* Added: Whenever you create a new feed, mapping will automatically be associated between a Gravity Form field and a Pod field if the labels match
* Added: Custom override values now support GF merge tags by default (no insert UI yet) like `{form_id}` and any other merge tag
* Added: Required WP Object Fields in mapping are no longer required if you choose to 'Enable editing with this form using ____' option for Post/Media or User pod types
* Added: Support for E-mail field mappings with 'Confirm E-mail' enabled
* Added: Support for Date fields with multiple inputs (date dropdown / text fields)
* Added: Smarter requirement handling for WP object fields based on object type (only require what the WP insert API requires)
* Added: New mapping fields are now available for more Entry and Payment fields
* Added: New merge tags `{pods.id}` and `{pods.permalink}` are available for usage and in the merge tag selection dropdowns
* Improved: Added headings to each group of feed options so they are easier to work with
* Improved: Address field mapping for Country, State, and CA Provinces now convert properly to their Pods counterparts
* Updated: PHP Markdown library updated to 1.0.2
* Fixed: Issues with using 'bypass' as a save action
* Fixed: Dynamic select options should set the current value (as posted in form) properly
* Fixed: Date/time fields shouldn't auto populate with empty dates such as 0000-00-00 anymore
* Fixed: Additional attachment processing fixes
* Fixed: Lots of Pods GF UI issues resolved
* Fixed: Removed Autocomplete limit (was 30) that was being enforced, now all data from related field will show
* Fixed: Dynamic mapping value checking to support arrays of values
* Fixed: Lots of Prepopulating fixes
* Fixed: Now supports multi page form validation and prepopulating

= 1.3 - June 2nd, 2017 =

* Added: When creating new feeds mapping will automatically be detected based on matching field labels
* Added: New option to prepopulate the form fields with data based on the field mapping in the feed (same type of logic as edit). Limitations with certain field types, please submit issues with problems you find here.
* Added: Rewrote the whole File Upload field mapping logic and tested against Single/Multi file fields (props @mika31, @copperleaf, @zanematthew, @zorog, @chriswagoner for testing help, props @spivurno for official GF support code help)
* Added: Support for feeds with submissions from the forms embedded on the dashboard and in the admin area (props @richardW8k)
* Added: Field names to field mapping screen
* Added: Ability to define a custom override value for each field mapping
* Added: Ability to enable editing of user data using current logged in user ID (only for User pod feeds)
* Added: Ability to enable editing of post data using current post ID on singular templates (only for Post type pod feeds)
* Added: Ability to define custom 'content' in Pods GF UI custom actions instead of including a form
* Added: Ability to relate to GF forms using a relationship field (new option: Gravity Forms > Forms)
* Added: Ability to map Address and List fields
* Added: Ability to map Category and Post Tag fields
* Added: Ability to map sub fields to a pod field (Name [First Name], Address [Street Line 1], etc)
* Fixed: Ensure time fields get mapped correctly (props @mmarvin1)
* Fixed: Ensure default pods-gf-ui shortcode is only added/run on content within the loop (props @jamesgol)
* Fixed: Empty id used for Pods GF UI
* Fixed: Callback handling for Pods GF UI
* Fixed: Default Post Author mapping

= 1.2 - October 4th, 2016 =

* Added: When using a custom action and setting the form ID option in Pods GF UI, a new custom action will be used which embeds the GF form (if no callback provided in action_data option)
* Added: New Pods GF UI option, specific to each action, for `action_link` which corresponds to the `action_links` Pods UI option
* Fixed: Support for recent GF versions where pre_save_id hook uses a different Form-specific naming convention
* Fixed: Custom confirmation handling may have not been functioning properly in some cases
* Fixed: Removed some issues that were causing PHP notices


= 1.1 - June 13th, 2016 =

* Added: Support for edit mode when using the Pods GF add-on mapping in the GF UI -- Use the new filter `pods_gf_addon_edit_id`, just return the ID to edit and the options will automatically be set for you
* Added: When filtering the Pods data in `Pods_GF::gf_to_pods()` (via the `pods_gf_to_pods_data` and related filters), if you set the proper ID field in that array it will now be used to *save* over the existing item; Helpful for dynamic editing configurations based upon different processes and workflows in the code
* Added: `Pods_GF::confirmation()` now supports `{@gf_to_pods_id}` replacement in confirmation URLs, replacing the variable properly to the resulting saved ID
* Fixed: `Pods_GF::_gf_to_pods_handler()` would sometimes get the action improperly set to `edit`, but only `add`, `save`, or `bypass` are valid
* Fixed: When an invalid pod is called in `Pods_GF::_gf_to_pods_handler()`, there's now a proper fallback to avoid PHP errors/warnings/notices
* Fixed: When an invalid pod is called in `Pods_GF::_gf_field_validation()`, there's now a proper fallback to avoid PHP errors/warnings/notices
* Fixed: `Pods_GF::confirmation()` would add the `gform_confirmation_{$form_id}` filter incorrectly and would cause PHP warnings about the callback, causing the confirmation functionality to not work properly
* Fixed: `Pods_GF::confirmation()` confirmation URL replacement now handles a few more cases where previously PHP notices would result
* Changed: `Pods_GF` is now storing multiple instances statically, cannot be called with `new Pods_GF()`, must be called with `Pods_GF::get_instance()` but more importantly should be called through the standard `pods_gf()` helper function to remain backwards compatible with previous versions
* Changed: `Pods_GF::$gf_to_pods_id` is no longer an integer, but an array of integers keyed by the GF Form ID
* Changed: `Pods_GF::$keep_files` is no longer an boolean, but an array of booleans keyed by the GF Form ID

= 1.0 - March 4th, 2016 =

* Initial release
