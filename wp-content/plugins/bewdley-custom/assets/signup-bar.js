( function ( $ ) {
	var BAR_KEY = 'bfs_bar_dismissed';

	function showBar() {
		$( '#bfs-bar' ).addClass( 'bfs-bar--visible' );
	}

	function dismissBar() {
		$( '#bfs-bar' ).removeClass( 'bfs-bar--visible' );
		try { sessionStorage.setItem( BAR_KEY, '1' ); } catch ( e ) {}
	}

	$( document ).ready( function () {
		var $bar = $( '#bfs-bar' );
		if ( ! $bar.length ) { return; }

		// Don't show if already dismissed this session.
		try {
			if ( sessionStorage.getItem( BAR_KEY ) ) { return; }
		} catch ( e ) {}

		// Show after 3 seconds.
		setTimeout( showBar, 3000 );

		// Dismiss on X click.
		$( '#bfs-bar-close' ).on( 'click', function () {
			dismissBar();
		} );

		// Submit handler.
		$( '#bfs-bar-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			var $form = $( this );
			var $msg  = $( '#bfs-bar-msg' );
			var $btn  = $form.find( 'button[type="submit"]' );
			var email = $form.find( 'input[name="email"]' ).val().trim();

			$msg.text( '' ).removeClass( 'success error' );

			if ( ! email ) {
				$msg.text( bewdleyBar.emailError ).addClass( 'error' );
				return;
			}

			$btn.prop( 'disabled', true ).text( '…' );

			$.post(
				bewdleyBar.ajaxUrl,
				{
					action:  'bewdley_newsletter_signup',
					nonce:   bewdleyBar.nonce,
					email:   email,
					name:    '',
					consent: 1,
				},
				function ( response ) {
					if ( response.success ) {
						$msg.text( bewdleyBar.successMsg ).addClass( 'success' );
						$form[0].reset();
						$btn.text( '✓' );
						// Auto-dismiss after 4 seconds on success.
						setTimeout( dismissBar, 4000 );
					} else {
						$msg.text( response.data.message ).addClass( 'error' );
						$btn.prop( 'disabled', false ).text( 'Subscribe' );
					}
				}
			).fail( function () {
				$msg.text( bewdleyBar.emailError ).addClass( 'error' );
				$btn.prop( 'disabled', false ).text( 'Subscribe' );
			} );
		} );
	} );
} )( jQuery );
