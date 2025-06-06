<?php

function wpinv_2co_process_payment( $purchase_data ) {
    if( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'wpi-gateway' ) ) {
        wp_die( __( 'Nonce verification has failed', 'wpinv-2co' ), __( 'Error', 'wpinv-2co' ), array( 'response' => 403 ) );
    }
    
    // Collect payment data
    $payment_data = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'invoice_key'   => $purchase_data['invoice_key'],
        'currency'      => wpinv_get_currency(),
        'items'         => $purchase_data['items'],
        'user_info'     => $purchase_data['user_info'],
        'cart_details'  => $purchase_data['cart_details'],
        'gateway'       => '2co',
        'status'        => 'wpi-pending'
    );

    // Record the pending payment
    $invoice = wpinv_get_invoice( $purchase_data['invoice_id'] );
    
    if ( !empty( $invoice ) ) {
        $quantities_enabled = wpinv_item_quantities_enabled();
        $invoice_id = $invoice->ID;
        $subscription_item = $invoice->get_recurring( true );
        $is_recurring = !empty( $subscription_item ) ? true : false; // Is recurring payment?
        
        $params                         = array();
        $params['sid']                  = wpinv_get_option( '2co_vendor_id', false );
        $params['mode']                 = '2CO';
        $params['currency_code']        = wpinv_get_currency();
        $params['merchant_order_id']    = $invoice_id;
        $params['total']                = wpinv_sanitize_amount( $invoice->get_total() );
        $params['key']                  = $invoice->get_key();
        $params['card_holder_name']     = $invoice->get_user_full_name();
        $params['email']                = $invoice->get_email();
        $params['street_address']       = wp_strip_all_tags( $invoice->get_address(), true );
        $params['country']              = $invoice->country;
        $params['state']                = $invoice->state;
        $params['city']                 = $invoice->city;
        $params['zip']                  = $invoice->zip;
        $params['phone']                = $invoice->phone;
        
        $initial_amount = wpinv_format_amount( $invoice->get_total() );
        $recurring_amount = wpinv_format_amount( $invoice->get_recurring_details( 'total' ) );
            
        // Recurring payment
        $recurrence = '';
        $duration = '';
        if ( !empty( $is_recurring ) ) {
            $interval = $subscription_item->get_recurring_interval();
            $period = $subscription_item->get_recurring_period();
            $bill_times = (int)$subscription_item->get_recurring_limit();
            $time_period = wpinv_2co_get_time_period( $interval, $period );
            $recurrence = $time_period['interval'] . ' ' . $time_period['period'];
            $time_duration = $bill_times > 0 ? wpinv_2co_get_time_period( $bill_times, $period ) : array();
            $duration = !empty($time_duration) ? $time_duration['interval'] . ' ' . $time_duration['period'] : 'Forever';
        }
        
        $i = 0;
        // Items
        foreach ( $invoice->get_cart_details() as $item ) {
            $quantity = $quantities_enabled && !empty( $item['quantity'] ) && $item['quantity'] > 0 ? $item['quantity'] : 1;
            
            $params['li_' . $i . '_type']       = 'product';
            $params['li_' . $i . '_name']       = $item['name'];
            $params['li_' . $i . '_quantity']   = $quantity;
            $params['li_' . $i . '_price']      = wpinv_sanitize_amount( $item['item_price'] );
            $params['li_' . $i . '_tangible']   = 'N';
            $params['li_' . $i . '_product_id'] = $item['id'];
            
            if ( $recurrence ) {
                $params['li_' . $i . '_recurrence'] = $recurrence;
            }
            if ( $duration ) {
                $params['li_' . $i . '_duration'] = $duration;
            }
            $i++;
        }
        
        $adjust = 0;
        // Discount
        if ( ( $discount = $invoice->get_discount() ) > 0 ) {
            $params['li_' . $i . '_type']       = 'coupon';
            $params['li_' . $i . '_name']       = __( 'Discount', 'wpinv-2co' );
            $params['li_' . $i . '_quantity']   = 1;
            $params['li_' . $i . '_price']      = wpinv_sanitize_amount( $discount );
            $params['li_' . $i . '_tangible']   = 'N';
            $params['li_' . $i . '_product_id'] = 'discount';
            
            if ( $recurrence ) {
                $params['li_' . $i . '_recurrence'] = $recurrence;
            }
            if ( $is_recurring && $initial_amount != $recurring_amount ) {
                $params['li_' . $i . '_duration'] = $recurrence;
                $adjust = $recurring_amount - $initial_amount - $discount;
            } else {
                if ( $duration ) {
                    $params['li_' . $i . '_duration'] = $duration;
                }
            }
            $i++;
        }
        
        // Tax
        if ( wpinv_use_taxes() && ( $tax = $invoice->get_tax() ) > 0 ) {
            if ( $adjust > 0 ) {
                $tax += $adjust;
            }
            $params['li_' . $i . '_type']       = 'tax';
            $params['li_' . $i . '_name']       = __( 'Tax', 'wpinv-2co' );
            $params['li_' . $i . '_quantity']   = 1;
            $params['li_' . $i . '_price']      = wpinv_sanitize_amount( $tax );
            $params['li_' . $i . '_tangible']   = 'N';
            $params['li_' . $i . '_product_id'] = 'tax';
            
            if ( $adjust > 0 ) {
                $params['li_0_startup_fee'] = $adjust * -1;
            }
            if ( $recurrence ) {
                $params['li_' . $i . '_recurrence'] = $recurrence;
            }
            if ( $duration ) {
                $params['li_' . $i . '_duration'] = $duration;
            }
        }
        
        $params['purchase_step']        = 'payment-method';
        $params['x_receipt_link_url']   = wpinv_get_ipn_url( '2co' );
        
        $params = apply_filters( 'wpinv_2co_form_extra_parameters', $params, $invoice );
        
        $redirect_text  = __( 'Redirecting to 2Checkout site, click on button if not redirected.', 'wpinv-2co' );
        $redirect_text  = apply_filters( 'wpinv_2co_redirect_text', $redirect_text, $invoice );
        
        // Empty the shopping cart
        wpinv_empty_cart();
        ?>