<?php
/*
Plugin Name: WPU Notifications
Plugin URI: https://github.com/WordPressUtilities/wpunotifications
Update URI: https://github.com/WordPressUtilities/wpunotifications
Description: Handle user notifications
Version: 0.3.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpunotifications
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit();
}

class WPUNotifications {
    private $plugin_version = '0.3.0';
    private $plugin_settings = array(
        'id' => 'wpunotifications',
        'name' => 'WPU Notifications'
    );
    private $basetoolbox;
    private $basecron;
    private $adminpages;
    private $baseadmindatas;
    private $settings;
    private $settings_obj;
    private $settings_details;
    private $plugin_description;
    private $admin_page_id = 'wpunotifications-notifications';

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        # Front Assets
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        # AJAX Action
        add_action('wp_ajax_wpunotifications_ajax_action', array(&$this, 'wpunotifications_ajax_action'));

        # Front Items
        add_action('wpunotifications_display_notifications', array(&$this, 'wpunotifications_display_notifications'), 10, 2);
    }

    public function plugins_loaded() {
        # TRANSLATION
        if (!load_plugin_textdomain('wpunotifications', false, dirname(plugin_basename(__FILE__)) . '/lang/')) {
            load_muplugin_textdomain('wpunotifications', dirname(plugin_basename(__FILE__)) . '/lang/');
        }
        $this->plugin_description = __('Handle user notifications', 'wpunotifications');
        # TOOLBOX
        require_once __DIR__ . '/inc/WPUBaseToolbox/WPUBaseToolbox.php';
        $this->basetoolbox = new \wpunotifications\WPUBaseToolbox(array(
            'need_form_js' => false
        ));
        # CUSTOM PAGE
        $admin_pages = array(
            'main' => array(
                'icon_url' => 'dashicons-warning',
                'menu_name' => $this->plugin_settings['name'],
                'name' => $this->plugin_settings['name'],
                'function_content' => array(&$this,
                    'page_content__main'
                ),
                'function_action' => array(&$this,
                    'page_action__main'
                )
            ),
            'settings' => array(
                'parent' => 'main',
                'name' => __('Settings', 'wpunotifications'),
                'settings_link' => true,
                'settings_name' => __('Settings', 'wpunotifications'),
                'has_form' => false,
                'function_content' => array(&$this,
                    'page_content__settings'
                )
            ),
            'notifications' => array(
                'parent' => 'main',
                'name' => __('Notifications', 'wpunotifications'),
                'has_form' => false,
                'function_content' => array(&$this,
                    'page_content__notifications'
                )
            )

        );
        $pages_options = array(
            'id' => $this->plugin_settings['id'],
            'level' => 'manage_options',
            'basename' => plugin_basename(__FILE__)
        );
        // Init admin page
        require_once __DIR__ . '/inc/WPUBaseAdminPage/WPUBaseAdminPage.php';
        $this->adminpages = new \wpunotifications\WPUBaseAdminPage();
        $this->adminpages->init($pages_options, $admin_pages);
        # CUSTOM TABLE
        require_once __DIR__ . '/inc/WPUBaseAdminDatas/WPUBaseAdminDatas.php';
        $this->baseadmindatas = new \wpunotifications\WPUBaseAdminDatas();

        $this->baseadmindatas->init(array(
            'handle_database' => false,
            'plugin_id' => $this->plugin_settings['id'],
            'plugin_pageid' => $this->admin_page_id,
            'table_name' => $this->plugin_settings['id'],
            'table_fields' => array(
                'message' => array(
                    'public_name' => 'Message',
                    'type' => 'sql',
                    'sql' => 'TEXT'
                ),
                'user_id' => array(
                    'public_name' => 'User ID',
                    'type' => 'number'
                ),
                'notif_type' => array(
                    'public_name' => 'Notification type',
                    'type' => 'varchar'
                ),
                'is_read' => array(
                    'public_name' => 'Is read',
                    'type' => 'number'
                )
            )
        ));
        # SETTINGS
        $this->settings_details = array(
            # Admin page
            'create_page' => true,
            'plugin_basename' => plugin_basename(__FILE__),
            # Default
            'plugin_name' => $this->plugin_settings['name'],
            'plugin_id' => $this->plugin_settings['id'],
            'option_id' => $this->plugin_settings['id'] . '_options',
            'sections' => array(
                'features' => array(
                    'name' => __('Features', 'wpunotifications')
                )
            )
        );
        $this->settings = array(
            'settings__base_css' => array(
                'label' => __('Enable default CSS', 'wpunotifications'),
                'type' => 'checkbox',
                'section' => 'features'
            )
        );
        require_once __DIR__ . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpunotifications\WPUBaseSettings($this->settings_details, $this->settings);
        /* Include hooks */
        require_once __DIR__ . '/inc/WPUBaseCron/WPUBaseCron.php';
        $this->basecron = new \wpunotifications\WPUBaseCron(array(
            'pluginname' => $this->plugin_settings['name'],
            'cronhook' => 'wpunotifications__cron_hook',
            'croninterval' => 3600
        ));
        /* Callback when hook is triggered by the cron */
        add_action('wpunotifications__cron_hook', array(&$this,
            'wpunotifications__cron_hook'
        ), 10);
    }

    public function wp_enqueue_scripts() {
        /* Front Style */
        wp_register_style('wpunotifications_front_style', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version);
        if ($this->settings_obj->get_setting('settings__base_css') == '1') {
            wp_enqueue_style('wpunotifications_front_style');
        }
        /* Front Script with localization / variables */
        wp_register_script('wpunotifications_front_script', plugins_url('assets/front.js', __FILE__), array('wp-util'), $this->plugin_version, true);
        wp_localize_script('wpunotifications_front_script', 'wpunotifications_settings', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
        wp_enqueue_script('wpunotifications_front_script');
    }

    public function admin_enqueue_scripts() {
        /* Back Style */
        wp_register_style('wpunotifications_back_style', plugins_url('assets/back.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpunotifications_back_style');
    }

    public function wpunotifications_ajax_action() {

        if (!isset($_POST['notification_id'], $_POST['action_type']) || !is_user_logged_in()) {
            wp_send_json_error(array(
                'error' => 'No notification'
            ), 400);
        }

        if (!in_array($_POST['action_type'], array('mark_as_read', 'delete'))) {
            wp_send_json_error(array(
                'error' => 'Invalid action'
            ), 400);
        }
        $action_type = $_POST['action_type'];

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . $this->plugin_settings['id'];
        if ($_POST['notification_id'] == 'all') {
            if ($action_type == 'delete') {
                $wpdb->delete($table, array('user_id' => $user_id));
            } else {
                $wpdb->update($table, array('is_read' => 1), array('user_id' => $user_id));
            }
        } elseif (is_numeric($_POST['notification_id'])) {
            if ($action_type == 'mark_as_read') {
                $wpdb->update($table, array('is_read' => 1), array('id' => $_POST['notification_id'], 'user_id' => $user_id));
            } else {
                $wpdb->delete($table, array('id' => $_POST['notification_id'], 'user_id' => $user_id));
            }
        }

        wp_send_json_success(array(
            'ok' => '1'
        ), 200);
    }

    public function create_notification($args = array()) {
        $defaults = array(
            'message' => '',
            'user_id' => get_current_user_id(),
            'notif_type' => 'default'
        );
        $this->baseadmindatas->create_line(array_merge($defaults, $args));
    }

    public function wpunotifications__cron_hook() {

    }

    public function page_content__main() {

        echo '<h2>' . __('Send a notification', 'wpunotifications') . '</h2>';
        echo $this->basetoolbox->get_form_html(
            'wpunotifications_form',
            array(
                'message' => array(
                    'value' => 'Default message',
                    'label' => __('Message', 'wpunotifications'),
                    'type' => 'textarea'
                ),
                'user_id' => array(
                    'value' => get_current_user_id(),
                    'label' => __('User ID', 'wpunotifications'),
                    'type' => 'number'
                ),
                'notif_type' => array(
                    'value' => 'default',
                    'label' => __('Notification type', 'wpunotifications'),
                    'type' => 'text'
                )
            ),
            array(
                'button_classname' => 'button button-primary',
                'has_nonce' => false,
                'form_element' => false
            )
        );

    }

    public function page_action__main() {

        $user_id = get_current_user_id();
        if (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && get_user_by('ID', $_POST['user_id'])) {
            $user_id = $_POST['user_id'];
        }

        $message = isset($_POST['message']) ? $_POST['message'] : '';
        $notif_type = (isset($_POST['notif_type']) && $_POST['notif_type']) ? $_POST['notif_type'] : 'default';

        $this->create_notification(
            array(
                'message' => $message,
                'user_id' => $user_id,
                'notif_type' => $notif_type,
                'is_read' => 0
            )
        );
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    public function page_content__settings() {
        settings_errors();
        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->settings_details['plugin_id']);
        submit_button(__('Save Changes', 'wpunotifications'));
        echo '</form>';
    }

    /* ----------------------------------------------------------
      List
    ---------------------------------------------------------- */

    public function page_content__notifications() {

        add_filter('wpubaseadmindatas_cellcontent', array(&$this, 'wpubaseadmindatas_cellcontent'), 10, 3);

        echo $this->baseadmindatas->get_admin_table(
            false,
            array(
                'perpage' => 50,
                'columns' => array(
                    'id' => __('ID', 'wpunotifications'),
                    'creation' => __('Date', 'wpunotifications'),
                    'user_id' => __('Account', 'wpunotifications'),
                    'notif_type' => __('Notif type', 'wpunotifications'),
                    'is_read' => __('Read', 'wpunotifications'),
                    'message' => __('Message', 'wpunotifications')
                )
            )
        );
    }

    public function wpubaseadmindatas_cellcontent($cellcontent, $cell_id, $settings) {
        $admin_url = admin_url('admin.php?page=' . $this->admin_page_id);
        $filter_url = $admin_url . '&' . http_build_query(array(
            'filter_key' => $cell_id,
            'filter_value' => $cellcontent
        ));
        if ($cell_id == 'user_id' && is_numeric($cellcontent)) {
            $user_id = $cellcontent;
            $user = get_user_by('id', $user_id);
            if ($user) {
                $login = '<a href="' . esc_url($filter_url) . '">' . esc_html($user->user_login) . '</a>';
                $cellcontent = '<img loading="lazy" style="height:16px;width:16px;vertical-align:middle;margin-right:0.3em" src="' . esc_url(get_avatar_url($user->ID, array('size' => 16))) . '" />';
                $cellcontent .= '<strong style="vertical-align:middle">' . $login . '</strong>';
            }
        }
        return $cellcontent;

    }

    /* ----------------------------------------------------------
      Getters
    ---------------------------------------------------------- */

    public function get_user_notifications($user_id) {
        global $wpdb;
        $q = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->plugin_settings['id']} WHERE user_id = %d", $user_id);
        return $wpdb->get_results($q);
    }

    /* ----------------------------------------------------------
      Front Items
    ---------------------------------------------------------- */

    public function wpunotifications_display_notifications($args = array()) {
        if (!is_array($args)) {
            $args = array();
        }
        $args = array_merge(array(
            'user_id' => get_current_user_id()
        ), $args);

        $notifications = $this->get_user_notifications($args['user_id']);

        if (empty($notifications)) {
            return;
        }

        $default_css = $this->settings_obj->get_setting('settings__base_css');

        echo '<div id="wpunotifications-notifications-list" class="wpunotifications-notifications-list" data-use-default-css="' . esc_attr($default_css) . '">';
        echo '<div class="wpunotifications-notifications">';
        $has_unread = false;
        foreach ($notifications as $notification) {
            echo '<div id="wpunotifications-notification-' . $notification->id . '" data-is-read="' . $notification->is_read . '" class="wpunotifications-notification wpunotifications-notification--' . $notification->notif_type . '">';
            echo '<button type="button" class="wpunotifications-delete-notification wpunotifications-delete-single-notification" data-delete-notification="' . $notification->id . '"><span>' . __('Delete', 'wpunotifications') . '</span></button>';
            if (!$notification->is_read) {
                $has_unread = true;
                echo '<button type="button" class="wpunotifications-mark-notification-as-read wpunotifications-mark-single-notification-as-read" data-mark-notification-as-read="' . $notification->id . '"><span>' . __('Mark as read', 'wpunotifications') . '</span></button>';
            }
            echo '<p>' . $notification->message . '</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" data-delete-notification="all" class="wpunotifications-delete-notification wpunotifications-delete-all-notifications"><span>' . __('Delete all', 'wpunotifications') . '</span></button>';
        if ($has_unread) {
            echo '<button type="button" data-mark-notification-as-read="all" class="wpunotifications-mark-notification-as-read wpunotifications-mark-all-notifications-as-read"><span>' . __('Mark all as read', 'wpunotifications') . '</span></button>';
        }
        echo '</div>';
    }

}

$WPUNotifications = new WPUNotifications();
