<?php
/**
 * English - Transaction Messages
 */

return [
    'created' => 'Transaction created successfully.',
    'not_found' => 'Transaction not found.',
    'already_paid' => 'Transaction has already been paid.',
    'already_expired' => 'Transaction has expired.',
    'already_processed' => 'Transaction has already been processed.',
    'invalid_amount' => 'Invalid payment amount.',
    'amount_required' => 'Amount must be greater than 0.',
    'order_id_exists' => 'Order ID already in use.',
    'channel_unavailable' => "Channel ':channel' is not available.",
    'channel_inactive' => "Channel ':channel' is inactive. Contact admin.",
    'api_key_missing' => ':provider API key is not configured.',
    'payment_success' => 'Payment successful!',
    'payment_pending' => 'Awaiting payment...',
    'payment_failed' => 'Payment failed.',
    'payment_expired' => 'Payment has expired.',
    'status_changed' => 'Transaction status changed from :old to :new.',
    'refund_success' => 'Refund processed successfully.',
    'refund_partial' => 'Partial refund of :amount processed.',
    'refund_max_exceeded' => 'Refund amount exceeds maximum.',
    'refund_not_eligible' => 'This transaction is not eligible for refund.',
    'invalid_email' => 'Invalid email format.',
    'invalid_phone' => 'Invalid phone number format.',

    // Status labels
    'status_pending' => 'Pending',
    'status_paid' => 'Paid',
    'status_failed' => 'Failed',
    'status_expired' => 'Expired',
    'status_refunded' => 'Refunded',
];
