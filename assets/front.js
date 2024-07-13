/* AJAX */
document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    var $delete_notifications = document.querySelectorAll('[data-delete-notification]');
    Array.prototype.forEach.call($delete_notifications, function(el) {
        el.addEventListener('click', function() {
            var _id = el.getAttribute('data-delete-notification');
            wp.ajax.post("wpunotifications_ajax_action", {
                'notification_id': _id,
                'action_type': 'delete'
            }).done(function(response) {
                if (response.ok == '1') {
                    var notificationElement = document.querySelector('#wpunotifications-notification-' + _id);
                    if (_id == 'all') {
                        notificationElement = document.querySelector('#wpunotifications-notifications-list');
                    }
                    if (notificationElement) {
                        notificationElement.remove();
                    }
                }
            });
        });
    });

    var $mark_notifications = document.querySelectorAll('[data-mark-notification-as-read]');
    Array.prototype.forEach.call($mark_notifications, function(el) {
        el.addEventListener('click', function() {
            var _id = el.getAttribute('data-mark-notification-as-read');
            wp.ajax.post("wpunotifications_ajax_action", {
                'notification_id': _id,
                'action_type': 'mark_as_read'
            }).done(function(response) {
                if (response.ok != '1') {
                    return;
                }
                if (_id == 'all') {
                    Array.prototype.forEach.call(document.querySelectorAll('#wpunotifications-notifications-list [data-is-read]'), function(el) {
                        el.setAttribute('data-is-read', '1');
                    });
                    Array.prototype.forEach.call(document.querySelectorAll('#wpunotifications-notifications-list [data-mark-notification-as-read]'), function(el) {
                        el.remove();
                    });
                } else {
                    var _item = document.querySelector('#wpunotifications-notification-' + _id);
                    _item.setAttribute('data-is-read', '1');
                    _item.querySelector('[data-mark-notification-as-read]').remove();
                }
            });
        });
    });

});
