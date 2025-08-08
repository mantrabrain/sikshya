<?php
/**
 * Payment Settings Tab Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-settings-tab-content">
    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-credit-card"></i>
            <?php _e('Payment Gateways', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="payment_gateway"><?php _e('Primary Payment Gateway', 'sikshya'); ?></label>
                <select id="payment_gateway" name="payment_gateway">
                    <option value="" <?php selected(get_option('sikshya_payment_gateway', ''), ''); ?>><?php _e('Select Gateway', 'sikshya'); ?></option>
                    <option value="stripe" <?php selected(get_option('sikshya_payment_gateway', ''), 'stripe'); ?>><?php _e('Stripe', 'sikshya'); ?></option>
                    <option value="paypal" <?php selected(get_option('sikshya_payment_gateway', ''), 'paypal'); ?>><?php _e('PayPal', 'sikshya'); ?></option>
                    <option value="razorpay" <?php selected(get_option('sikshya_payment_gateway', ''), 'razorpay'); ?>><?php _e('Razorpay', 'sikshya'); ?></option>
                    <option value="mollie" <?php selected(get_option('sikshya_payment_gateway', ''), 'mollie'); ?>><?php _e('Mollie', 'sikshya'); ?></option>
                    <option value="manual" <?php selected(get_option('sikshya_payment_gateway', ''), 'manual'); ?>><?php _e('Manual Payment', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Primary payment gateway for processing transactions', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_test_mode" value="1" 
                           <?php checked(get_option('sikshya_enable_test_mode', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Test Mode', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Use test/sandbox mode for payment gateways', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="payment_methods"><?php _e('Accepted Payment Methods', 'sikshya'); ?></label>
                <div class="sikshya-checkbox-group">
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="accept_credit_cards" value="1" 
                               <?php checked(get_option('sikshya_accept_credit_cards', true)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Credit/Debit Cards', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="accept_bank_transfer" value="1" 
                               <?php checked(get_option('sikshya_accept_bank_transfer', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Bank Transfer', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="accept_digital_wallets" value="1" 
                               <?php checked(get_option('sikshya_accept_digital_wallets', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Digital Wallets (PayPal, Apple Pay, etc.)', 'sikshya'); ?>
                    </label>
                    <label class="sikshya-checkbox-label">
                        <input type="checkbox" name="accept_cryptocurrency" value="1" 
                               <?php checked(get_option('sikshya_accept_cryptocurrency', false)); ?>>
                        <span class="checkmark"></span>
                        <?php _e('Cryptocurrency', 'sikshya'); ?>
                    </label>
                </div>
                <p class="description"><?php _e('Select which payment methods to accept', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-stripe"></i>
            <?php _e('Stripe Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="stripe_publishable_key"><?php _e('Publishable Key', 'sikshya'); ?></label>
                <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" 
                       value="<?php echo esc_attr(get_option('sikshya_stripe_publishable_key', '')); ?>" 
                       placeholder="pk_test_...">
                <p class="description"><?php _e('Your Stripe publishable key (starts with pk_)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="stripe_secret_key"><?php _e('Secret Key', 'sikshya'); ?></label>
                <input type="password" id="stripe_secret_key" name="stripe_secret_key" 
                       value="<?php echo esc_attr(get_option('sikshya_stripe_secret_key', '')); ?>" 
                       placeholder="sk_test_...">
                <p class="description"><?php _e('Your Stripe secret key (starts with sk_)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="stripe_webhook_secret"><?php _e('Webhook Secret', 'sikshya'); ?></label>
                <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" 
                       value="<?php echo esc_attr(get_option('sikshya_stripe_webhook_secret', '')); ?>" 
                       placeholder="whsec_...">
                <p class="description"><?php _e('Stripe webhook endpoint secret for payment confirmations', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fab fa-paypal"></i>
            <?php _e('PayPal Settings', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="paypal_client_id"><?php _e('Client ID', 'sikshya'); ?></label>
                <input type="text" id="paypal_client_id" name="paypal_client_id" 
                       value="<?php echo esc_attr(get_option('sikshya_paypal_client_id', '')); ?>" 
                       placeholder="Your PayPal Client ID">
                <p class="description"><?php _e('Your PayPal application client ID', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="paypal_secret"><?php _e('Secret', 'sikshya'); ?></label>
                <input type="password" id="paypal_secret" name="paypal_secret" 
                       value="<?php echo esc_attr(get_option('sikshya_paypal_secret', '')); ?>" 
                       placeholder="Your PayPal Secret">
                <p class="description"><?php _e('Your PayPal application secret key', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="paypal_mode"><?php _e('PayPal Mode', 'sikshya'); ?></label>
                <select id="paypal_mode" name="paypal_mode">
                    <option value="sandbox" <?php selected(get_option('sikshya_paypal_mode', 'sandbox'), 'sandbox'); ?>><?php _e('Sandbox (Test)', 'sikshya'); ?></option>
                    <option value="live" <?php selected(get_option('sikshya_paypal_mode', 'sandbox'), 'live'); ?>><?php _e('Live (Production)', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('PayPal environment mode', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-percentage"></i>
            <?php _e('Pricing & Taxes', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="tax_rate"><?php _e('Tax Rate (%)', 'sikshya'); ?></label>
                <input type="number" id="tax_rate" name="tax_rate" 
                       value="<?php echo esc_attr(get_option('sikshya_tax_rate', 0)); ?>" 
                       min="0" max="100" step="0.01">
                <p class="description"><?php _e('Default tax rate applied to course prices', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="tax_inclusive" value="1" 
                           <?php checked(get_option('sikshya_tax_inclusive', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Tax Inclusive Pricing', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Course prices include tax (vs. tax added on top)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="currency_decimal_places"><?php _e('Decimal Places', 'sikshya'); ?></label>
                <select id="currency_decimal_places" name="currency_decimal_places">
                    <option value="0" <?php selected(get_option('sikshya_currency_decimal_places', 2), 0); ?>><?php _e('0 (Whole numbers)', 'sikshya'); ?></option>
                    <option value="2" <?php selected(get_option('sikshya_currency_decimal_places', 2), 2); ?>><?php _e('2 (e.g., $10.99)', 'sikshya'); ?></option>
                    <option value="3" <?php selected(get_option('sikshya_currency_decimal_places', 2), 3); ?>><?php _e('3 (e.g., $10.999)', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Number of decimal places for currency display', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-tags"></i>
            <?php _e('Discounts & Coupons', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_coupons" value="1" 
                           <?php checked(get_option('sikshya_enable_coupons', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Enable Coupons', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Allow students to use discount coupons', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="max_discount_percentage"><?php _e('Max Discount (%)', 'sikshya'); ?></label>
                <input type="number" id="max_discount_percentage" name="max_discount_percentage" 
                       value="<?php echo esc_attr(get_option('sikshya_max_discount_percentage', 50)); ?>" 
                       min="0" max="100">
                <p class="description"><?php _e('Maximum discount percentage allowed', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="coupon_expiry_days"><?php _e('Coupon Expiry (days)', 'sikshya'); ?></label>
                <input type="number" id="coupon_expiry_days" name="coupon_expiry_days" 
                       value="<?php echo esc_attr(get_option('sikshya_coupon_expiry_days', 30)); ?>" 
                       min="1" max="365">
                <p class="description"><?php _e('Default expiry period for new coupons', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-receipt"></i>
            <?php _e('Invoicing & Receipts', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_generate_invoices" value="1" 
                           <?php checked(get_option('sikshya_auto_generate_invoices', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-generate Invoices', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically generate invoices for successful payments', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="send_payment_receipts" value="1" 
                           <?php checked(get_option('sikshya_send_payment_receipts', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Send Payment Receipts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Email payment receipts to students', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="invoice_prefix"><?php _e('Invoice Number Prefix', 'sikshya'); ?></label>
                <input type="text" id="invoice_prefix" name="invoice_prefix" 
                       value="<?php echo esc_attr(get_option('sikshya_invoice_prefix', 'INV-')); ?>" 
                       placeholder="INV-">
                <p class="description"><?php _e('Prefix for invoice numbers (e.g., INV-2024-001)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 