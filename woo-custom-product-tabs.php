<?php
/**
 * Plugin Name: WooCommerce Custom Product Tabs
 * Description: Take control of your Woocommerce product tabs.
 * Version: 1.0
 * Author: Matt Kevan
 * Author URI: https://www.kevan.tv
 */

// Register the custom tabs content type

function wct_register_product_tab_post_type() {
    $args = array(
    'public' => true,
    'label'  => 'Product Tabs',
    'show_in_menu' => true,
    'supports' => array('title', 'editor'),
    'menu_icon' => 'dashicons-index-card',
    'menu_position' => 56,
    'show_in_rest' => true,
);
    register_post_type('product_tab', $args);
}
add_action('init', 'wct_register_product_tab_post_type');

function wct_product_tab_content($key, $tab) {
    echo apply_filters('the_content', $tab['content']);
}

// Add the reorder tabs submenu item

function wct_product_tabs_menu() {
    add_submenu_page(
        'edit.php?post_type=product_tab',
        'Manage Tabs',
        'Manage Tabs',
        'manage_options',
        'reorder_tabs',
        'wct_reorder_tabs_callback'
    );
}
add_action('admin_menu', 'wct_product_tabs_menu');

function wct_enqueue_block_styles() {
    wp_enqueue_style('wp-block-library');
}
add_action('wp_enqueue_scripts', 'wct_enqueue_block_styles');

// Manage tabs admin page

function wct_reorder_tabs_callback() {

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Manage tabs</h1>';
    wp_nonce_field('wct_nonce_check', 'wct_nonce');

    $args = array(
        'post_type' => 'product_tab',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'show_in_rest' => true,
	    'supports' => array('title', 'editor', 'revisions')
    );

    $tabs = get_posts($args);
    if (!is_array($tabs)) {
        $tabs = array();
    }

    // Default WooCommerce tabs
    $default_tabs = array(
        'description' => 'Description',
        'additional_information' => 'Additional Information',
        'reviews' => 'Reviews'
    );

    // Get the saved tab order
    $saved_order = get_option('wct_tab_order', array());
    if (!is_array($saved_order)) {
        $saved_order = explode(',', $saved_order);
    }

    // Build the tab list based on saved order
    $ordered_tabs = [];
    foreach ($saved_order as $tab_key) {
        if (isset($default_tabs[$tab_key])) {
            $ordered_tabs[$tab_key] = $default_tabs[$tab_key];
            unset($default_tabs[$tab_key]);
        } elseif (strpos($tab_key, 'tab_') === 0) {
            $tab_id = str_replace('tab_', '', $tab_key);
            foreach ($tabs as $tab) {
                if ($tab->ID == $tab_id) {
                    $ordered_tabs[$tab_key] = $tab->post_title;
                    break;
                }
            }
        }
    }
    // Append any tabs that might not have been in the saved order
    $ordered_tabs += $default_tabs;
    foreach ($tabs as $tab) {
        $tab_key = 'tab_' . $tab->ID;
        if (!isset($ordered_tabs[$tab_key])) {
            $ordered_tabs[$tab_key] = $tab->post_title;
        }
    }

    // Fetching the saved visibility status of tabs
    $saved_visibility = get_option('wct_tab_visibility', array());

    echo '<ul id="sortable">';
    foreach ($ordered_tabs as $tab_key => $tab_name) {
        $isVisible = isset($saved_visibility[$tab_key]) ? $saved_visibility[$tab_key] : '1';
        $checked = $isVisible === '1' ? 'checked' : '';
        echo '<li id="'.$tab_key.'" class="menu-item-handle ui-sortable-handle">';
        echo '<input type="checkbox" name="tab_visibility[]" value="' . $tab_key . '" ' . $checked . '> ' . $tab_name;
        echo '</li>';
    }
    echo '</ul>';
    echo '<button id="save-tab-order">Save tabs</button>';

    // Enqueue the separate JS file
    wp_enqueue_script('wct-reorder-script', plugins_url('src/custom-tabs.js', __FILE__), array('jquery', 'jquery-ui-sortable'), '1.0.0', true);

    wp_enqueue_style('wct-reorder-style', plugins_url('src/custom-tabs.css', __FILE__), array(), '1.0.0');

 echo '</div>';  // Add this line
}

// Add admin scripts

function wct_enqueue_admin_scripts($hook) {
    if ('product_tab_page_reorder_tabs' == $hook) {
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('wct-reorder-style', plugins_url('src/custom-tabs.css', __FILE__), array(), '1.0.0');
    }
}
add_action('admin_enqueue_scripts', 'wct_enqueue_admin_scripts');

// Save tab ordering

function wct_save_tab_order() {
    if (!check_ajax_referer('wct_nonce_check', 'nonce', false)) {
        wp_send_json_error('Nonce failed!');
    }

    $order = $_POST['order'];
    update_option('wct_tab_order', $order);

    // Build the ordered_tabs array just like in the callback function
    $args = array(
        'post_type' => 'product_tab',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    $tabs = get_posts($args);
    $default_tabs = array(
        'description' => 'Description',
        'additional_information' => 'Additional Information',
        'reviews' => 'Reviews'
    );
    $saved_order = get_option('wct_tab_order', array());
    if (!is_array($saved_order)) {
        $saved_order = explode(',', $saved_order);
    }
    $ordered_tabs = array();
    foreach ($saved_order as $tab_key) {
        if (isset($default_tabs[$tab_key])) {
            $ordered_tabs[$tab_key] = $default_tabs[$tab_key];
        } elseif (strpos($tab_key, 'tab_') === 0) {
            $tab_id = str_replace('tab_', '', $tab_key);
            foreach ($tabs as $tab) {
                if ($tab->ID == $tab_id) {
                    $ordered_tabs[$tab_key] = $tab->post_title;
                    break;
                }
            }
        }
    }

    // Save the visibility status of tabs
    $visibility = $_POST['visibility'];
    $allTabs = array_keys($ordered_tabs);
    $visibilityArr = array();
    foreach ($allTabs as $tab_key) {
        $visibilityArr[$tab_key] = in_array($tab_key, $visibility) ? '1' : '0';
    }
    update_option('wct_tab_visibility', $visibilityArr);

    wp_send_json_success();
}
add_action('wp_ajax_wct_save_tab_order', 'wct_save_tab_order');

// Add the tabs to the product page

function wct_add_product_tabs($tabs) {
    // Fetch the saved visibility status of tabs
    $saved_visibility = get_option('wct_tab_visibility', array());

    // Handle default tabs first
    $default_tabs_keys = array('description', 'additional_information', 'reviews');
    foreach ($default_tabs_keys as $default_tab_key) {
        // If a default tab is set as invisible, remove it from the tabs
        if (isset($saved_visibility[$default_tab_key]) && $saved_visibility[$default_tab_key] === '0') {
            unset($tabs[$default_tab_key]);
        }
    }

    $args = array(
        'post_type' => 'product_tab',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    $product_tabs = get_posts($args);
    $saved_order = get_option('wct_tab_order', array());
    if (!is_array($saved_order)) {
        $saved_order = explode(',', $saved_order);
    }

    $order_indexes = [];
    foreach ($saved_order as $index => $key) {
        $order_indexes[$key] = $index;
    }

    foreach ($product_tabs as $tab) {
        $tab_key = 'tab_' . $tab->ID;

        if (isset($saved_visibility[$tab_key]) && $saved_visibility[$tab_key] === '0') {
            continue;  // Skip this tab if it's set to be invisible
        }

        $priority = isset($order_indexes[$tab_key]) ? $order_indexes[$tab_key] * 10 : 100;
        $tabs[$tab_key] = array(
            'title'    => $tab->post_title,
            'priority' => $priority,
            'callback' => 'wct_product_tab_content',
            'id'       => $tab_key,
            'content'  => $tab->post_content
        );
    }

    // Handle the priority of default tabs
    foreach ($default_tabs_keys as $default_tab_key) {
        if (isset($tabs[$default_tab_key])) {
            $priority = isset($order_indexes[$default_tab_key]) ? $order_indexes[$default_tab_key] * 10 : 50; // default to 50 if not set
            $tabs[$default_tab_key]['priority'] = $priority;
        }
    }

    uasort($tabs, function ($a, $b) {
        if ($a['priority'] == $b['priority']) return 0;
        return $a['priority'] < $b['priority'] ? -1 : 1;
    });

    return $tabs;
}

add_filter('woocommerce_product_tabs', 'wct_add_product_tabs', 999);

