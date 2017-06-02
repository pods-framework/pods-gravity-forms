=== Pods Gravity Forms Add-On ===
Contributors: sc0ttkclark, jimtrue, naomicbush, gravityplus
Donate link: https://pods.io/friends-of-pods/
Tags: pods, gravity forms, form mapping
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate with Gravity Forms to create a Pod item from a form submission.

== Description ==

Requires [Pods](https://wordpress.org/plugins/pods/) 2.4+, [Gravity Forms](http://www.gravityforms.com/) 1.9+.

Check out [pods.io](http://pods.io/) for our User Guide, Forums, and other resources to help you develop with Pods.

Please report bugs or request featured on [GitHub](https://github.com/pods-framework/pods-gravity-forms/)

Special thanks to Rocketgenius for their sponsorship support and to Naomi C. Bush for her help in the initial add-on UI work.

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
