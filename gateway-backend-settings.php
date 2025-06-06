<?php

public function gateway_settings( $settings = array() ) {
    /**
     * $settings[{GATEWAY_ID}_FIELD_NAME] = array(
     *     'id' => {GATEWAY_ID}_FIELD_NAME,                                         // Field name.
     *     'name' => __( 'Custom Field', 'my-textdomain' ),                         // Field title.
     *     'desc' => __( 'Description for custom field here.', 'my-textdomain' ),   // Field description.
     *     'type' => 'text',                                                        // Field type. Ex: text, checkbox, select etc.
     *     'size' => 'large',                                                       // Field input size. Ex: small, medium, large.
     *     'std' => __( 'Default value', 'my-textdomain' )                          // Field default value.
     * );
     */
    $settings['2co_desc'] = array(
        'id' => '2co_desc',
        'name' => __( 'Description', 'wpinv-2co' ),
        'desc' => __( 'This controls the description which the user sees during checkout.', 'wpinv-2co' ),
        'type' => 'text',
        'size' => 'large',
        'std' => __( 'Pay with your credit / debit card via 2Checkout gateway.', 'wpinv-2co' )
    );
    
    $settings['2co_sandbox'] = array(
        'type' => 'checkbox',
        'id' => '2co_sandbox',
        'name' => __( '2Checkout Sandbox', 'wpinv-2co' ),
        'desc' => __( '2Checkout sandbox can be used to test payments.', 'wpinv-2co' ),
        'std' => 1
    );
    
    $settings['2co_vendor_id'] = array(
        'type' => 'text',
        'id' => '2co_vendor_id',
        'name' => __( '2Checkout Account ID', 'wpinv-2co' ),
        'desc' => __( 'Enter your 2Checkout account id. Example : 1303908', 'wpinv-2co' ),
        'std' => '1303908',
    );

    $settings['2co_ipn_url'] = array(
        'type' => 'ipn_url',
        'id' => '2co_ipn_url',
        'name' => __( '2Checkout Instant Notification Url', 'wpinv-2co' ),
        'desc' => __( 'Configure Instant Notification url at your 2Checkout account. Set this url to Instant Notification Settings at Notifications page and enable all notifications. If you have sandbox mode enabled then set it here as well Sandbox Notifications.', 'wpinv-2co' ),
        'size' => 'large',
        'custom' => '2co',
        'readonly' => true,
        'std' => wpinv_get_ipn_url( '2co' ),
    );
    
    // Extra settings here
    
    return $settings;
}
add_filter( 'wpinv_gateway_settings_2co', array( $this->admin, 'gateway_settings' ) );