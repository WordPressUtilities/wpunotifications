/* AJAX */
document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    var $delete_notifications = document.querySelectorAll('[data-delete-notification]');
    Array.prototype.forEach.call($delete_notifications, function(el) {
        el.addEventListener('click', function() {
            var _id = el.getAttribute('data-delete-notification');
            wp.ajax.post("wpunotifications_ajax_action", {
                'notification_id': _id
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

});
