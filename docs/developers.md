# Developers

Any plugin can add tasks to To-do Hub, keep them up to date, and hear when they're done. On a connected site the same calls go to the hub, and changes made while the hub can't be reached are sent later.

## Adding a task

```php
if ( function_exists( 'todo_hub_add' ) ) {
	todo_hub_add( array(
		'title'    => 'Approve 5 new items',
		'source'   => 'where-to-buy',            // your plugin's slug
		'key'      => 'where-to-buy:approve',    // same key again updates this task instead of adding another
		'category' => 'Where to Buy',            // created if it doesn't exist
		'priority' => 'high',                    // high, normal, low
		'due'      => 'tomorrow 3pm',            // Y-m-d, timestamp, DateTime or a phrase; a time is kept
		'link'     => admin_url( 'edit.php?post_type=wtb_item&post_status=pending' ),
		'data'     => array( 'count' => 5 ),     // handed back when it's completed
	) );
}
```

Calling it again with the same `key` updates that task, while it's open, instead of adding another. So you can call it whenever something changes, without checking first.

| Argument | Value |
|---|---|
| `title` | Required. |
| `notes` | Longer notes; HTML is allowed, as in a post. |
| `category` | A name, slug or id, or a list of them. New names are created. |
| `priority` | `high`, `normal` (the default) or `low`, or 3, 2, 1. |
| `due` | `Y-m-d`, a timestamp, a date object, or a phrase such as `tomorrow` or `next friday`. A time in it (`2026-12-01 14:30`, `tomorrow 3pm`) is kept. Empty for no due date. |
| `due_time` | A time of day such as `14:30` or `2:30pm`. It's only used with a due date. |
| `source` | Your plugin's slug. The list can be filtered by it. |
| `key` | Your own id for the task, such as `my-plugin:renew-ssl`. |
| `link` | Where to go to do the task. |
| `repeat` | `daily`, `weekly`, `monthly` or `yearly`, or `array( 'every' => 2, 'unit' => 'week' )`. |
| `notify` | On a connected site: have the hub report back when it's done, so the completion hooks fire on this site. |
| `data` | Anything you want back when the task is completed. |

It returns the task id or a `WP_Error`. On a connected site it returns the hub's task id, or 0 when the hub can't be reached right now and the task will be sent later.

## The other functions

```php
todo_hub_update( 'my-plugin:renew-ssl', array( 'due' => '2026-12-15' ) );  // only the fields you pass
todo_hub_complete( 'my-plugin:renew-ssl' );   // by key or id; false if there's no such open task
todo_hub_reopen( 42 );                         // by id
todo_hub_remove( 'my-plugin:renew-ssl' );     // it no longer needs doing (moved to the trash)
todo_hub_get( 'my-plugin:renew-ssl' );        // the task as an array, or null
todo_hub_open_tasks( array( 'source' => 'my-plugin' ) );   // open tasks, first to do first
```

## Without checking that To-do Hub is active

These actions simply do nothing when To-do Hub isn't active:

```php
do_action( 'todo_hub_add_task', array( 'title' => 'Renew the SSL certificate', 'due' => '2026-12-01' ) );
do_action( 'todo_hub_complete_task', 'my-plugin:renew-ssl' );
do_action( 'todo_hub_remove_task', 'my-plugin:renew-ssl' );
```

## Hearing when a task is done

```php
add_action( 'todo_hub_task_completed_my-plugin', function ( array $task ) {
	// $task['key'], $task['data'], ...
} );
```

| Hook | Arguments | When |
|---|---|---|
| `todo_hub_task_added` | `$task` | A task was added |
| `todo_hub_task_changed` | `$id` | A task was changed |
| `todo_hub_task_completed` | `$task` | Any task was completed |
| `todo_hub_task_completed_{source}` | `$task` | One of your tasks was completed |
| `todo_hub_task_reopened` | `$task` | A task was reopened |
| `todo_hub_outbox_dropped` | `$item, $message` | A connected site gave up sending a change |
| `todo_hub_webhook_failed` | `$task_id, $event, $attempt, $response, $will_retry` | The hub couldn't tell a site about a completion |

Filters: `todo_hub_private_site_allow` (return true to let a request through on a private site) and `todo_hub_google_urls` (point Google Calendar at other addresses).

On a connected site, the completion hooks fire when the hub reports back, for tasks added with `'notify' => true`.

## Building from source

```bash
composer install      # installs WP Foundation and prefixes it with Strauss
composer test         # unit tests
bin/build-zip.sh      # writes dist/todo-hub-<version>.zip
npm install && npm run site:build   # this site, into _site/
```

Pushing a tag such as `v0.3.1` that matches the `Version:` header builds the zip and publishes a GitHub release.
