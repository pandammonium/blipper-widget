jQuery( document ).ready( function($) {
  console.log( 'In custom-widget-script.js' );
  $( '#inactive-widgets-control-remove' ).on( 'click', function() {
    console.log( 'In on click callback function' );
    $.ajax( {
      url: custom_widget_ajax.ajax_url,
      type: 'POST',
      data: {
          action: 'bw_on_delete_inactive_widgets_from_backend',
          nonce: custom_widget_ajax.nonce
      },
      success: function( response ) {
          console.log( 'Custom action executed:', response );
      },
      error: function( error ) {
        console.error( 'Error:', error );
      }
    } );
  } );
} );
