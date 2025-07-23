<div id="sdevs_subscription_options"
     class="panel woocommerce_options_panel option_group sdevs-form sdevs_panel show_if_simple" style="padding: 10px;">
    <div class="show_if_subscription">
        <input name="_subscript_nonce" type="hidden"
               value="<?php echo esc_attr( wp_create_nonce( '_subscript_edit_product_nonce' ) ); ?>"/>
        <strong style="margin: 10px;"><?php esc_html_e( 'Subscription Settings', 'wp_subscription' ); ?></strong>
        <?php

        woocommerce_wp_select(
            array(
                'id'          => 'subscrpt_timing',
                'label'       => __( 'Users will pay', 'wp_subscription' ),
                'value'       => $subscrpt_timing,
                'options'     => $timing_types,
                'description' => __( 'Set the length of each recurring subscription period to daily, weekly, monthly or annually.', 'wp_subscription' ),
                'desc_tip'    => true,
            )
        );
        ?>
        <p class="form-field subscrpt_field">
            <label for="subscrpt_trial_time"><?php esc_html_e( '	Free Trial Duration', 'wp_subscription' ); ?></label>
            <input type="number" class="short" name="subscrpt_trial_time" id="subscrpt_trial_time"
                   value="<?php echo esc_attr( $subscrpt_trial_time ); ?>"/>
            <select name="subscrpt_trial_timing" id="subscrpt_trial_timing">
                <?php foreach ( $trial_timing_types as $timing_type ) : ?>
                    <option value="<?php echo esc_attr( $timing_type['value'] ); ?>"
                        <?php
                        if ( $subscrpt_trial_timing === $timing_type['value'] ) {
                            echo 'selected';
                        }
                        ?>
                    ><?php echo esc_html( $timing_type['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <small
                class="description"><?php esc_html_e( 'Let users try the subscription for free before the first payment is collected.', 'wp_subscription' ); ?></small>
        </p>

        <?php

        woocommerce_wp_text_input(
            array(
                'id'          => 'subscrpt_cart_txt',
                'label'       => __( 'Button Text (Custom)', 'wp_subscription' ),
                'type'        => 'text',
                'value'       => $subscrpt_cart_txt,
                'description' => __( 'Customize the button label shown on the product or shop page. Default is "Subscribe"', 'wp_subscription' ),
                'desc_tip'    => true,
            )
        );

        woocommerce_wp_select(
            array(
                'id'          => 'subscrpt_user_cancel',
                'label'       => __( 'Allow User Cancellation?', 'wp_subscription' ),
                'value'       => $subscrpt_user_cancell,
                'options'     => array(
                    'yes' => __( 'Yes', 'wp_subscription' ),
                    'no'  => __( 'No', 'wp_subscription' ),
                ),
                'description' => __( 'Allow subscribers to cancel their subscription manually from their account dashboard.', 'wp_subscription' ),
                'desc_tip'    => true,
            )
        );

        woocommerce_wp_select(
            array(
                'id'          => 'subscrpt_limit',
                'label'       => __( 'Limit subscription', 'wp_subscription' ),
                'options'     => array(
                    'unlimited' => __( 'Do not limit', 'wp_subscription' ),
                    'one'       => __( 'allow only one active subscription', 'wp_subscription' ),
                    'only_one'  => __( 'allow only one subscription of any status', 'wp_subscription' ),
                ),
                'value'       => $subscrpt_limit,
                'description' => __( 'Set optional limits for this product subscription.', 'wp_subscription' ),
                'desc_tip'    => true,
            )
        );
        ?>
    </div>
</div>
