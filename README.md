# To-do Hub

A WordPress plugin for one central to-do list. Tasks are sorted by what needs doing first: overdue and due-today tasks come first, and tasks rise up the list as their due date gets close. Other plugins can add tasks and hear when they're done.

- **Quick add:** type a task, pick a category, priority, due date and repeat, press Add.
- **Sorted for you:** overdue, then due today, then by priority, with due-soon tasks counted higher (a low-priority task due in 3 days sits with high-priority tasks that have no date).
- **Views:** Up next, By category, Today, Done. Filter by category or by the plugin that added the task.
- **One click to tick off,** with Undo. Repeating tasks (daily, weekly, every 2 weeks, monthly, yearly, or every N days/weeks/months/years) add the next one when completed.
- **Menu badge and dashboard widget** show what's due now and what's next.
- **Private site option** sends logged-out visitors to the login page, for a site that only holds your list.

## For plugin developers

```php
if ( function_exists( 'todo_hub_add' ) ) {
	todo_hub_add( array(
		'title'    => 'Approve 5 new items',
		'source'   => 'where-to-buy',            // your plugin's slug
		'key'      => 'where-to-buy:approve',    // same key again updates this task instead of adding another
		'category' => 'Where to Buy',            // created if it doesn't exist
		'priority' => 'high',                    // high, normal, low
		'due'      => 'tomorrow',                // Y-m-d, timestamp, DateTime or a phrase
		'link'     => admin_url( 'edit.php?post_type=wtb_item&post_status=pending' ),
		'data'     => array( 'count' => 5 ),     // handed back when it's completed
	) );
}

add_action( 'todo_hub_task_completed_where-to-buy', function ( array $task ) {
	// $task['key'], $task['data'], ...
} );
```

No dependency check needed with the action versions, which do nothing when To-do Hub isn't active:

```php
do_action( 'todo_hub_add_task', array( 'title' => 'Renew the SSL certificate', 'due' => '2026-12-01' ) );
do_action( 'todo_hub_complete_task', 'my-plugin:renew-ssl' );   // by key or id
do_action( 'todo_hub_remove_task', 'my-plugin:renew-ssl' );     // it no longer needs doing
```

Functions: `todo_hub_add()`, `todo_hub_update()`, `todo_hub_complete()`, `todo_hub_reopen()`, `todo_hub_remove()`, `todo_hub_get()`, `todo_hub_open_tasks()`.
Hooks: `todo_hub_task_added`, `todo_hub_task_completed`, `todo_hub_task_completed_{source}`, `todo_hub_task_reopened`.
See [src/functions.php](src/functions.php) for every argument.

Coming next: a REST API and a connector so plugins on other sites can add tasks to the hub and get a callback when they're done, then Google Calendar sync.

## Install

Download `todo-hub-x.y.z.zip` from the [latest release](https://github.com/bignall/todo-hub/releases/latest) and upload it under **Plugins → Add New → Upload Plugin**. Requires PHP 8.1+ and WordPress 6.4+.

## Build from source

```bash
composer install      # installs WP Foundation and prefixes it with Strauss
composer test         # runs the unit tests (PHPUnit)
bin/build-zip.sh      # writes dist/todo-hub-<version>.zip
```

Pushing a tag such as `v0.1.0` that matches the `Version:` header builds the zip and publishes a GitHub release. Notes come from `release-notes/<tag>.md`.

Built on [WP Foundation](https://bignall.github.io/wp-foundation/). GPL-2.0-or-later.
