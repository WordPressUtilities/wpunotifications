# WPU Notifications

Handle user notifications

[![PHP workflow](https://github.com/WordPressUtilities/wpunotifications/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpunotifications/actions) [![JS workflow](https://github.com/WordPressUtilities/wpunotifications/actions/workflows/js.yml/badge.svg 'JS workflow')](https://github.com/WordPressUtilities/wpunotifications/actions)

## Roadmap

- [x] Basic CSS
- [x] Handle notification view.
- [x] Mark a notification as viewed.
- [ ] Handle notification expiration.
- [ ] JS Hooks.

## How to

### Display notifications

```php
do_action('wpunotifications_display_notifications');
```

### Display notifications pill

```php
do_action('wpunotifications_display_notifications_unread_pill');
```

### Create a notification

```php
add_filter('wpunotifications__notifications', function ($notifications) {
    $notifications[] = array(
        'message' => 'Hello world',
        'user_id' => 3,
        'notif_type' => 'success'
    );
    return $notifications;
});
```

### Action when a notification is created

```php
add_action('wpunotifications__notification_created', function ($args) {
   error_log(json_encode($args));
});
```
