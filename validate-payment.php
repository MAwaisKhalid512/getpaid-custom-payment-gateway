<?php

function wpinv_2co_validate_checkout( $valid_data, $post ) {
    if ( !empty( $post['wpi-gateway'] ) && $post['wpi-gateway'] == '2co' ) {
        $invoice = wpinv_get_invoice_cart();
        
        if ( empty( $invoice ) ) {
            return;
        }

        if ( !( (float)$invoice->get_total() > 0 ) ) {
            wpinv_set_error( 'empty_total', __( '2Checkout unable to process the payment with invoice total equal to zero or in a negative number.', 'wpinv-2co' ) );
            return;
        }
        
        if ( $invoice->is_free_trial() ) {
            wpinv_set_error( 'empty_no_free_trial', __( '2Checkout payment gateway does not support free trial.', 'wpinv-2co'));
            return;
        }
        
        if ( $item = $invoice->get_recurring( true ) ) {
            if ( $item->get_recurring_period() == 'D' )
            wpinv_set_error( 'empty_invalid_period', __( '2Checkout does not process the payment with recurring period in "Day". Can use only # Week, # Month, # Year.', 'wpinv-2co'));
            return;
        }
    }
}
add_action( 'wpinv_checkout_error_checks', 'wpinv_2co_validate_checkout', 11, 2 );