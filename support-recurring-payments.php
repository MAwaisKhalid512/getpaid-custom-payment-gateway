<?php

// Enable accepting recurring payments

/**
 * add_filter( 'wpinv_{GATEWAY_ID}_support_subscription', '__return_true' );
 */
add_filter( 'wpinv_2co_support_subscription', '__return_true' );
Disable accepting recurring payments

/**
 * add_filter( 'wpinv_{GATEWAY_ID}_support_subscription', '__return_false' );
 */
add_filter( 'wpinv_2co_support_subscription', '__return_false' );