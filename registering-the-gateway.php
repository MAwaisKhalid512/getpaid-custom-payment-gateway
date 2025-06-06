<?php

public function add_gateway( $gateways = array() ) {
    /**
     * $gateways[{GATEWAY_ID}] = array(
     *     'admin_label'    => __( 'Custom Gateway', 'my-textdomain' ),      // Gateway title to displayed in backend
     *     'checkout_label' => __( 'Custom Gateway', 'my-textdomain' ),      // Gateway title to displayed in checkout
     *     'ordering'       => 2                                             // Gateway display order
     * );
     */
    $gateways['2co'] = array(
        'admin_label'    => __( '2Checkout', 'wpinv-2co' ),
        'checkout_label' => __( '2Checkout - Credit / Debit Card', 'wpinv-2co' ),
        'ordering'       => 2
    );
    
    return $gateways;
}
add_action( 'wpinv_payment_gateways', array( $this, 'add_gateway' ) );