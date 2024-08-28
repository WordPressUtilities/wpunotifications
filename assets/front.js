/* AJAX */
document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    function update_notifications_count() {
        var $wrapper = document.querySelector('#wpunotifications-notifications-list'),
            $no_notifs = document.querySelector('.wpunotifications-no-notifications'),
            _count_total = $wrapper ? $wrapper.querySelectorAll('[data-is-read]').length : 0,
            _count_unread = $wrapper ? $wrapper.querySelectorAll('[data-is-read="0"]').length : 0;

        Array.prototype.forEach.call(document.querySelectorAll('[data-unread-notifications-count]'), function($pill) {
            $pill.setAttribute('data-unread-notifications-count', _count_unread);
            $pill.innerText = _count_unread;
        });

        if (_count_unread == 0) {
            document.querySelector('[data-mark-notification-as-read="all"]').style.display = 'none';
        }

        if ($no_notifs) {
            $no_notifs.style.display = 'none';
        }
        if (_count_total == 0) {
            document.querySelector('[data-delete-notification="all"]').style.display = 'none';
            if ($no_notifs) {
                $no_notifs.style.display = '';
            }
        }
    }
    update_notifications_count();

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
                        update_notifications_count();
                    }
                    if (notificationElement) {
                        notificationElement.remove();
                        update_notifications_count();
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
                    update_notifications_count();
                } else {
                    var _item = document.querySelector('#wpunotifications-notification-' + _id);
                    _item.setAttribute('data-is-read', '1');
                    _item.querySelector('[data-mark-notification-as-read]').remove();
                    update_notifications_count();
                }
            });
        });
    });
});
