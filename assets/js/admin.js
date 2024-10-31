jQuery( function( $ ) {
    'use strict';

    update_ppp_admin_options( $ );

    $( '#woocommerce_poco_paypal_pro_solution_type' ).on( 'change', function(){
        update_ppp_admin_options( $ );
    } )
} );

function update_ppp_admin_options( $ ) {
    var ppp_type = $('#woocommerce_poco_paypal_pro_solution_type').val();

    if ( ppp_type == '0' ) {
        $( '.hosted-solution-option' ).closest( 'tr' ).hide();
        $( '.direct-payment-option' ).closest( 'tr' ).show();
    } else {
        $( '.direct-payment-option' ).closest( 'tr' ).hide();
        $( '.hosted-solution-option' ).closest( 'tr' ).show();
    }
}