<?php

function wpinv_2co_process_ipn() {
    $request = wpinv_get_post_data( 'post' );

    if ( empty( $request['message_type'] ) || empty( $request['vendor_id'] ) ) {
        die( '-1' );
    }
    
    if ( empty( $request['sale_id'] ) || empty( $request['invoice_id'] ) || empty( $request['md5_hash'] ) ) {
        die( '-2' );
    }
    
    if ( !wpinv_2co_validate_ipn( $request['md5_hash'], $request['sale_id'], $request['vendor_id'], $request['invoice_id'] ) ) {
        die( '-3' );
    }
    
    $vendor_order_id    = sanitize_text_field( $request['vendor_order_id'] );
    $invoice            = wpinv_get_invoice( $vendor_order_id );
    if ( empty( $invoice ) ) {
        die( '-4' );
    }

    $invoice_id         = $invoice->ID;
    $invoice_status     = isset($request['invoice_status']) ? $request['invoice_status'] : '';
    $current_status     = $invoice->status;
    $item_index         = wpinv_2co_response_get_item_index( $request ); // Response contains main item.
    
    if ( empty( $item_index ) && strtoupper( $request['message_type'] ) != 'REFUND_ISSUED' ) {
        die( '-5' );
    }
    
    switch( strtoupper( $request['message_type'] ) ) {
        case 'ORDER_CREATED' :
            if ($invoice_status == 'approved' || $invoice_status == 'deposited') {
                wpinv_update_payment_status( $invoice_id, 'publish' );
                wpinv_set_payment_transaction_id( $invoice_id, $request['invoice_id'] );
                wpinv_insert_payment_note( $invoice_id, sprintf( __( '2Checkout Sale id: %s, Invoice id: %s', 'wpinv-2co' ), $request['sale_id'], $request['invoice_id'] ) );
                
                if ( !empty( $request['recurring'] ) ) {
                    sleep(1);
                    wpinv_2co_record_subscription_signup( $request, $invoice );
                }
            } else if ($invoice_status == 'declined') {
                wpinv_update_payment_status( $invoice_id, 'wpi-failed' );
                wpinv_record_gateway_error( __( '2CHECKOUT IPN ERROR', 'wpinv-2co' ), __( 'Payment failed due to invalid purchase found.', 'wpinv-2co' ), $invoice_id );
            } else if ($invoice_status == 'pending') {
                wpinv_update_payment_status( $invoice_id, 'wpi-pending' );
            }
            
            die( '1' );
            break;
            
        case 'INVOICE_STATUS_CHANGED' :
            break;

        case 'REFUND_ISSUED' :
            $parent_invoice_id  = $invoice_id;
            $invoice_id = wpinv_get_id_by_transaction_id( $request['invoice_id'] );
            
            if ( empty( $invoice_id ) ) {
                $invoice_id = $parent_invoice_id;
            }
            
            if ( !empty( $request['recurring'] ) && !empty( $request['item_type_' . $item_index] ) && $request['item_type_' . $item_index] == 'refund' ) {
                wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
                
                do_action( 'wpinv_2co_process_refund', $request, $invoice_id, 'item' );
                
                wpinv_update_payment_status( $invoice_id, 'wpi-refunded' );
                
                die( '1' );
            }
            
            $refunded_amount  = NULL;
            for ( $i = 1; $i <= (int)$request['item_count']; $i++ ) {
                $refunded_amount += $request['item_cust_amount_' . $i];
            }
            if ( $refunded_amount !== NULL ) {
                $refunded_amount = wpinv_sanitize_amount( $refunded_amount );
            }
            
            if ( $refunded_amount !== NULL && $refunded_amount get_total() ) {
                $refunded_amount = wpinv_price( $refunded_amount );
                
                wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: Partial refund of %s processed. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $refunded_amount, $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
                
                do_action( 'wpinv_2co_process_refund', $request, $invoice_id, 'partial' );
            } else {
                wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
                
                do_action( 'wpinv_2co_process_refund', $request, $invoice_id, 'full' );
                
                wpinv_update_payment_status( $invoice_id, 'wpi-refunded' );
            }
            
            die( '1' );
            
            break;

        case 'RECURRING_INSTALLMENT_SUCCESS' :
            wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
            
            wpinv_2co_record_subscription_payment( $request, $invoice );
            
            die( '1' );
            break;

        case 'RECURRING_INSTALLMENT_FAILED' :
            wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
            
            die( '1' );
            break;

        case 'RECURRING_STOPPED' :
            wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
            
            $invoice->stop_subscription();
            
            die( '1' );
            break;

        case 'RECURRING_COMPLETE' :
            wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
            
            $invoice->complete_subscription();
            
            die( '1' );
            break;

        case 'RECURRING_RESTARTED' :
            wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout: %s. Sale id: %s. Invoice id: %s', 'wpinv-2co' ), $request['message_description'], $request['sale_id'], $request['invoice_id'] ) );
            
            $invoice->restart_subscription();
            
            die( '1' );
            break;

        case 'FRAUD_STATUS_CHANGED' :
            switch ( $request['fraud_status'] ) {
                case 'pass':
                    wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout fraud review passed. Sale id: %s, Invoice id: %s', 'wpinv-2co' ), $request['sale_id'], $request['invoice_id'] ) );
                    break;
                case 'fail':
                    wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout fraud review failed. Sale id: %s, Invoice id: %s', 'wpinv-2co' ), $request['sale_id'], $request['invoice_id'] ) );
                    break;
                case 'wait':
                    wpinv_insert_payment_note( $invoice_id, wp_sprintf( __( '2Checkout fraud review in process. Sale id: %s, Invoice id: %s', 'wpinv-2co' ), $request['sale_id'], $request['invoice_id'] ) );
                    break;
            }
            
            die( '1' );
            break;
    }
    do_action( 'wpinv_2co_process_ipn_type_' . strtolower( $request['message_type'] ), $request, $invoice );
    
    die( '2' );
}
add_action( 'wpinv_verify_2co_ipn', 'wpinv_2co_process_ipn' );