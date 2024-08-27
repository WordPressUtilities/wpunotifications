/* AJAX */
document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    function update_notifications_count(_count) {
        if(_count < 0) {
            _count = document.querySelectorAll('#wpunotifications-notifications-list [data-is-read="0"]').length;
        }
        Array.prototype.forEach.call(document.querySelectorAll('[data-unread-notifications-count]'), function($pill) {
            $pill.setAttribute('data-unread-notifications-count', _count);
            $pill.innerText = _count;
        });

        if (_count == 0) {
            document.querySelector('[data-mark-notification-as-read="all"]').remove();
        }
    }

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
                        update_notifications_count(0);
                    }
                    if (notificationElement) {
                        notificationElement.remove();
                        update_notifications_count(-1);
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
                    update_notifications_count(0);
                } else {
                    var _item = document.querySelector('#wpunotifications-notification-' + _id);
                    _item.setAttribute('data-is-read', '1');
                    _item.querySelector('[data-mark-notification-as-read]').remove();
                    update_notifications_count(-1);
                }
            });
        });
    });
});
