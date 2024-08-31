<?php
/*
Plugin Name: WPU Notifications
Plugin URI: https://github.com/WordPressUtilities/wpunotifications
Update URI: https://github.com/WordPressUtilities/wpunotifications
Description: Handle user notifications
Version: 0.9.0
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
    private $plugin_version = '0.9.0';
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
    private $messages;

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

        # Front Assets
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

        # AJAX Action
        add_action('wp_ajax_wpunotifications_ajax_action', array(&$this, 'wpunotifications_ajax_action'));

        # Front Items
        add_action('wpunotifications_display_notifications', array(&$this, 'wpunotifications_display_notifications'), 10, 2);
        add_action('wpunotifications_display_notifications_unread_pill', array(&$this, 'get_unread_notifications_pill'), 10, 2);

        # Redirect
        add_action('template_redirect', array(&$this, 'template_redirect'));

        # Hook to create notifications
        add_action('wp_loaded', array(&$this, 'wpunotifications__notification_creation'), 10, 1);

        # Hook to handle links
        add_action('wp', array(&$this, 'wpunotifications__handle_links'), 10, 1);
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
            'need_form_js' => false,
            'plugin_name' => 'WPU Notifications'
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

        $table_fields = array(
            'message' => array(
                'public_name' => 'Message',
                'type' => 'sql',
                'sql' => 'TEXT'
            ),
            'notif_time' => array(
                'public_name' => 'Date',
                'type' => 'sql',
                'sql' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
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
                'type' => 'sql',
                'sql' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0'
            ),
            'url' => array(
                'public_name' => 'URL',
                'type' => 'sql',
                'sql' => 'TEXT'
            )
        );

        $this->baseadmindatas->init(array(
            'handle_database' => false,
            'plugin_id' => $this->plugin_settings['id'],
            'plugin_pageid' => $this->admin_page_id,
            'table_name' => $this->plugin_settings['id'],
            'table_fields' => apply_filters('wpunotifications__table_fields', $table_fields)
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
            ),
            'settings__display_date' => array(
                'label' => __('Display date', 'wpunotifications'),
                'label_check' => __('Display date under notifications', 'wpunotifications'),
                'type' => 'checkbox',
                'section' => 'features'
            ),
            'settings__display_message_no_notifs' => array(
                'label_check' => __('Display a message when the user doesn’t have notifications', 'wpunotifications'),
                'label' => __('Message when no notifications', 'wpunotifications'),
                'type' => 'checkbox',
                'section' => 'features'
            ),
            'settings__delete_old_notifications' => array(
                'label' => __('Delete old notifications', 'wpunotifications'),
                'label_check' => __('Delete notifications older than 3 months', 'wpunotifications'),
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

        # MESSAGES
        if (is_admin()) {
            require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
            $this->messages = new \wpunotifications\WPUBaseMessages($this->plugin_settings['id']);
        }
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

        $this->read_or_delete_notification($_POST['notification_id'], $_POST['action_type']);

        wp_send_json_success(array(
            'ok' => '1'
        ), 200);
    }

    public function wpunotifications__cron_hook() {

        /* Delete notifications older than 3 months */
        if ($this->settings_obj->get_setting('settings__delete_old_notifications')) {
            global $wpdb;
            $table = $wpdb->prefix . $this->plugin_settings['id'];
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE notif_time < %s", date('Y-m-d H:i:s', strtotime('-3 months'))));
        }
    }

    public function wpunotifications__handle_links() {
        if (!isset($_GET['wpunotifications_link_id']) || !is_numeric($_GET['wpunotifications_link_id'])) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . $this->plugin_settings['id'];
        $notification = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d and user_id=%d", $_GET['wpunotifications_link_id'], get_current_user_id()));

        if (!$notification || !$notification->url) {
            return;
        }

        $wpdb->update($table, array('is_read' => 1), array('id' => $notification->id));

        wp_redirect($notification->url);
        exit;
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
                ),
                'url' => array(
                    'value' => '',
                    'label' => __('URL', 'wpunotifications'),
                    'type' => 'url'
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
        $url = (isset($_POST['url']) && filter_var($_POST['url'], FILTER_VALIDATE_URL)) ? $_POST['url'] : '';

        $this->create_notification(
            array(
                'message' => $message,
                'user_id' => $user_id,
                'notif_type' => $notif_type,
                'url' => $url,
                'is_read' => 0
            )
        );
        $this->messages->set_message('wpunotifications_test_notification_created', __('Test notification was sent', 'wpunotifications'), 'updated');

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

        if ($this->settings_obj->get_setting('settings__display_message_no_notifs')) {
            echo '<div class="wpunotifications-no-notifications">' . wpautop(__('You don’t have notifications for the moment.', 'wpunotifications')) . '</div>';
        }

        $default_css = $this->settings_obj->get_setting('settings__base_css');
        $display_date = $this->settings_obj->get_setting('settings__display_date');

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
            $message = $notification->message;
            if ($notification->url) {
                $notification_url = $this->get_notification_url($notification);
                $message = '<a data-mark-notification-as-read="' . $notification->id . '" target="_blank" href="' . $notification_url . '">' . $message . '</a>';
            }
            echo '<div class="wpunotifications-notification-content">' . wpautop($message) . '</div>';
            if ($display_date) {
                echo '<time class="wpunotifications-notification-time" datetime="' . $notification->notif_time . '">' . sprintf(__('%s ago', 'wpunotifications'), human_time_diff(strtotime($notification->notif_time))) . '</time>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="wpunotifications-notifications__buttons">';
        echo '<button type="button" data-delete-notification="all" class="wpunotifications-delete-notification wpunotifications-delete-all-notifications"><span>' . __('Delete all', 'wpunotifications') . '</span></button>';
        echo '<button type="button" data-mark-notification-as-read="all" class="wpunotifications-mark-notification-as-read wpunotifications-mark-all-notifications-as-read"><span>' . __('Mark all as read', 'wpunotifications') . '</span></button>';
        echo '</div>';
        echo '</div>';
    }

    public function get_unread_notifications_pill($args) {
        if (!is_array($args)) {
            $args = array();
        }
        $args = array_merge(array(
            'user_id' => get_current_user_id()
        ), $args);

        $notifications = $this->get_user_notifications($args['user_id']);

        $notifications_count = 0;
        if ($notifications) {
            foreach ($notifications as $notification) {
                if (!$notification->is_read) {
                    $notifications_count++;
                }
            }
        }

        echo '<span class="wpunotifications-unread-notifications-pill" data-unread-notifications-count="' . $notifications_count . '">' . $notifications_count . '</span>';

    }

    /* ----------------------------------------------------------
      Redirect
    ---------------------------------------------------------- */

    public function template_redirect() {
        if (!is_user_logged_in()) {
            return;
        }
        if (!isset($_GET['wpunotifications_id']) || !is_numeric($_GET['wpunotifications_id'])) {
            return;
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . $this->plugin_settings['id'];
        $notification = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d and user_id=%d", $_GET['wpunotifications_id'], $user_id));
        if (!$notification || !$notification->url) {
            return;
        }
        $wpdb->update($table, array('is_read' => 1), array('id' => $notification->id));
        wp_redirect($notification->url);
        exit;
    }

    /* ----------------------------------------------------------
      Create notifications
    ---------------------------------------------------------- */

    public function wpunotifications__notification_creation() {
        $notifications = apply_filters('wpunotifications__notifications', array());
        if (!is_array($notifications)) {
            return;
        }
        foreach ($notifications as $notification) {
            $this->create_notification($notification);
        }
    }

    /* ----------------------------------------------------------
      Getters
    ---------------------------------------------------------- */

    public function get_user_notifications($user_id) {

        $cache_id = 'wpunotifications_user_notification_' . $user_id;
        $cache_duration = 5;

        $notifications = wp_cache_get($cache_id);
        if ($notifications === false) {
            global $wpdb;
            $q = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->plugin_settings['id']} WHERE user_id = %d ORDER BY notif_time DESC", $user_id);
            $notifications = $wpdb->get_results($q);
            wp_cache_set($cache_id, $notifications, '', $cache_duration);
        }

        return $notifications;
    }

    public function get_notification_url($notification) {
        if (is_object($notification)) {
            $notification = (array) $notification;
        }
        return home_url('?wpunotifications_id=' . $notification['id']);
    }

    /* ----------------------------------------------------------
      CRUD
    ---------------------------------------------------------- */

    public function read_or_delete_notification($notification_id, $action_type) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . $this->plugin_settings['id'];
        if ($notification_id == 'all') {
            if ($action_type == 'delete') {
                $wpdb->delete($table, array('user_id' => $user_id));
            } else {
                $wpdb->update($table, array('is_read' => 1), array('user_id' => $user_id));
            }
        } elseif (is_numeric($notification_id)) {
            if ($action_type == 'mark_as_read') {
                $wpdb->update($table, array('is_read' => 1), array('id' => $notification_id, 'user_id' => $user_id));
            } else {
                $wpdb->delete($table, array('id' => $notification_id, 'user_id' => $user_id));
            }
        }
    }

    public function create_notification($args = array()) {
        /* Create notification */
        $args = array_merge(array(
            'message' => '',
            'notif_time' => current_time('mysql', true),
            'user_id' => get_current_user_id(),
            'url' => '',
            'is_read' => 0,
            'notif_type' => 'default'
        ), $args);
        $notif_id = $this->baseadmindatas->create_line($args);

        /* Hook with extra args */
        $args['id'] = $notif_id;
        $args['notification_url'] = ($args['url'] && filter_var($args['url'], FILTER_VALIDATE_URL)) ? $this->get_notification_url($args) : '';
        do_action('wpunotifications__notification_created', $args);
    }

}

$WPUNotifications = new WPUNotifications();
