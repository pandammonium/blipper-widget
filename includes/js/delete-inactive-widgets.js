jQuery( document ).ready( function($) {
  console.log( 'In delete-inactive-widgets.js' );
  $( '#inactive-widgets-control-remove' ).on( 'click', function() {
    console.log( 'In on-click callback function' );
    $.ajax( {
      url: delete_inactive_widgets_ajax.ajax_url,
      type: 'POST',
      data: {
          action: 'bw_on_delete_inactive_widgets_from_backend',
          nonce: delete_inactive_widgets_ajax.nonce
      },
      success: function( response ) {
          console.log( 'Inactive widget data deleted:', response );
      },
      error: function( error ) {
        console.error( 'Error:', error );
      }
    } );
  } );
} );
