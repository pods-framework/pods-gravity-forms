jQuery( function() {

	// Handle showing/hiding override value
	var $form_settings = jQuery( '.gforms_form_settings' );

	if ( $form_settings[0] ) {
		$form_settings.on( 'change', '.gaddon-setting.gaddon-select', function() {

			var $this = jQuery( this ),
				value = $this.val(),
				$parent_td = $this.closest( 'td' ),
				$custom_override = jQuery( '.pods-custom-override', $parent_td );

			if ( $custom_override[0] ) {
				$custom_override.toggleClass( 'hidden', ( '_pods_custom' !== value ) );
			}

		} );
	}

} );