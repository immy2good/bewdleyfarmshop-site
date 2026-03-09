( function ( $ ) {
	$( document ).on( 'submit', '#bfs-signup-form', function ( e ) {
		e.preventDefault();

		var $form    = $( this );
		var $msg     = $( '#bfs-signup-msg' );
		var $btn     = $form.find( 'button[type="submit"]' );
		var $btnText = $btn.find( '.bfs-btn-text' );
		var email    = $form.find( 'input[name="email"]' ).val().trim();
		var name     = $form.find( 'input[name="name"]' ).val().trim();
		var consent  = $form.find( 'input[name="consent"]' ).is( ':checked' ) ? 1 : 0;

		$msg.text( '' ).removeClass( 'success error' );

		if ( ! name ) {
			$msg.text( bewdleySignup.nameError ).addClass( 'error' );
			return;
		}

		if ( ! email ) {
			$msg.text( bewdleySignup.emailError ).addClass( 'error' );
			return;
		}

		if ( ! consent ) {
			$msg.text( bewdleySignup.consentError ).addClass( 'error' );
			return;
		}

		$btn.prop( 'disabled', true );
		$btnText.text( 'Signing up…' );

		$.post(
			bewdleySignup.ajaxUrl,
			{
				action:  'bewdley_newsletter_signup',
				nonce:   bewdleySignup.nonce,
				email:   email,
				name:    name,
				consent: consent,
			},
			function ( response ) {
				if ( response.success ) {
					$msg.text( response.data.message ).addClass( 'success' );
					$form[0].reset();
					$btnText.text( '✓ Signed up' );
				} else {
					$msg.text( response.data.message ).addClass( 'error' );
					$btn.prop( 'disabled', false );
					$btnText.text( bewdleySignup.button || 'Yes please, sign me up' );
				}
			}
		).fail( function () {
			$msg.text( 'Something went wrong. Please try again.' ).addClass( 'error' );
			$btn.prop( 'disabled', false );
			$btnText.text( bewdleySignup.button || 'Yes please, sign me up' );
		} );
	} );
} )( jQuery );
