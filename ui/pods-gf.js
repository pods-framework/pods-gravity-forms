jQuery( function() {
	if ( jQuery( '.pods-gf-save-for-later' )[ 0 ] && 'undefined' != typeof ajaxurl ) {
		jQuery( '.gform_page_footer' ).on( 'click', '.pods-gf-save-for-later', function( e ) {
			e.preventDefault();

			var $this = jQuery( this ),
				$form = $this.closest( 'form' );

			// Save all current $_POST
			$form.prop( 'action', ajaxurl + '?action=pods_gf_save_for_later&form_id=' + $form.prop( 'id' ) );
			$form.submit();
		} ).on( 'click', '.pods-gf-save-for-later-reset', function( e ) {
			e.preventDefault();

			if ( !confirm( 'Are you sure you want to reset your saved form?' ) ) {
				return;
			}

			var $this = jQuery( this ),
				$form = $this.closest( 'form' );

			// Clear saved form
			$form.prop( 'action', ajaxurl + '?action=pods_gf_save_for_later&form_id=' + $form.prop( 'id' ) + '&pods_gf_clear_saved_form=1&pods_gf_save_for_later_redirect=' + encodeURI( document.location.href ) );
			$form.submit();
		} );

		jQuery( 'div.gform_wrapper' ).each( function() {
			var $this = jQuery( this ),
				$pods_save_for_later = jQuery( '.pods-gf-save-for-later', $this ),
				$pods_save_for_later_reset = jQuery( '.pods-gf-save-for-later-reset', $this );

			if ( $pods_save_for_later[ 0 ] ) {
				jQuery( '.gform_page_footer', $this ).each( function() {
					var $t = jQuery( this );

					if ( !jQuery( '.pods-gf-save-for-later', $t )[ 0 ] ) {
						var $new_save_button = $pods_save_for_later.clone();

						$new_save_button.appendTo( $t );

						if ( $pods_save_for_later_reset[ 0 ] ) {
							var $new_reset_button = $pods_save_for_later_reset.clone();

							$new_reset_button.appendTo( $t );
						}
					}
				} );
			}
		} );
	}

	if ( jQuery( '.pods-gf-secondary-submit' )[ 0 ] ) {
		jQuery( 'div.gform_wrapper' ).each( function() {
			var $this = jQuery( this ),
				$pods_secondary_submit = jQuery( '.pods-gf-secondary-submit', $this );

			if ( $pods_secondary_submit[ 0 ] ) {
				jQuery( '.gform_page_footer', $this ).each( function() {
					var $t = jQuery( this ),
						$secondary_submit = jQuery( '.pods-gf-secondary-submit', $t );

					$secondary_submit.each( function() {
						var $secondary_t = jQuery( this ),
							$submit = $this.find( 'input[id^="gform_submit_button_"]' ),
							events = $submit.data( 'events' );

						if ( 'undefined' != typeof events ) {
							// Iterate through all event types
							jQuery.each( events, function( eventType, eventArray ) {
								if ( 'undefined' != typeof eventArray ) {
									// Iterate through every bound handler
									jQuery.each( eventArray, function( index, event ) {
										// Take event namespaces into account
										var eventToBind = ( '' != event.namespace )
											? ( event.type + '.' + event.namespace )
											: ( event.type );

										// Bind event
										$secondary_t.on( eventToBind, event.data, event.handler );
									} );
								}
							} );
						}
					} );
				} );

				// Secondary submits for each page of a form
				$pods_secondary_submits = jQuery( 'div.gform_page_footer.top_label .pods-gf-secondary-submit', $this );

				$pods_secondary_submits.each( function() {
					var $secondary_submit = jQuery( this );

					jQuery( '.gform_page_footer' ).not( '.top_label' ).each( function() {
						var $page_footer = jQuery( this );

						$new_secondary = $secondary_submit.clone();

						$new_secondary.insertBefore( jQuery( 'img.spinner', $page_footer ) );
					} );
				} );
			}
		} );
	}
} );