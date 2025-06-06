<?php

// If gateway has any credit form fields

function wpinv_2co_cc_form() {
    // Your form fields here
}
add_action( 'wpinv_2co_cc_form', 'wpinv_2co_cc_form' );
If gateway does not have any credit form fields

/**
 * add_action( 'wpinv_{GATEWAY_ID}_cc_form', '__return_false' );
 */
add_action( 'wpinv_2co_cc_form', '__return_false' );