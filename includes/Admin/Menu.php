<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Menu class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Menu {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
    }

    /**
     * Create Subscriptions Menu.
     */
    public function create_admin_menu() {
        $slug = 'edit.php?post_type=subscrpt_order';
        add_menu_page( 
            __( 'Subscriptions', 'sdevs_subscrpt' ), 
            __( 'Subscriptions', 'sdevs_subscrpt' ), 
            'manage_options', 
            $slug, 
            false, 
            SUBSCRPT_ASSETS . '/images/icons/subscription-20-gray.png', 
            40 
        );
    }
}
