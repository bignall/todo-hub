# To-do Hub

A WordPress plugin for one central to-do list. Tasks are sorted by what needs doing first: overdue and due-today tasks come first, and tasks rise up the list as their due date gets close. Other plugins can add tasks and hear when they're done.

- **Quick add:** type a task, pick a category, priority, due date, optional time and repeat, press Add.
- **Sorted for you:** overdue, then due today, then by priority, with due-soon tasks counted higher (a low-priority task due in 3 days sits with high-priority tasks that have no date).
- **Views:** Up next, By category, Today, Done. Filter by category or by the plugin that added the task.
- **One click to tick off,** with Undo. Repeating tasks (daily, weekly, every 2 weeks, monthly, yearly, or every N days/weeks/months/years) add the next one when completed.
- **Menu badge and dashboard widget** show what's due now and what's next.
- **Private site option** sends logged-out visitors to the login page, for a site that only holds your list.

**Guides:** https://bignall.github.io/todo-hub/ (built from `docs/` and `site/` by `.github/workflows/pages.yml`).

## For plugin developers

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

## One list for all your sites

Install To-do Hub on every site. One site is the **hub** and keeps the list; the others are **connectors** (To-do → Settings → Role) that send their tasks to it.

1. On the hub, add a user with the **To-do Connector** role. In that user's profile, create an application password named after the other site.
2. On the other site, choose **Connector** and enter the hub's address, that user name and the application password. The settings page says whether the connection works.

Plugins on a connected site use the same `todo_hub_*()` calls as above; the tasks show up on the hub as "from where-to-buy on example.com". Add `'notify' => true` to a task and the hub tells the site when it's done, so `todo_hub_task_completed` fires there too. If the hub can't be reached, changes wait and are sent in order every five minutes.

The hub needs https (application passwords require it), and WP-Cron should run regularly on both sites. On SiteGround, add a cron job that loads `wp-cron.php` every five minutes.

### REST API

The hub's API lives at `/wp-json/todo-hub/v1` (or `/?rest_route=/todo-hub/v1` without pretty permalinks). Log in with an application password.

| Method and path | What it does |
| --- | --- |
| `GET /ping` | Check the connection. |
| `GET /tasks` | Open tasks, first to do first. `?status=done`, `site`, `key`, `source`, `category`, `per_page`. |
| `POST /tasks` | Add a task (fields as for `todo_hub_add()`, plus `site` and `callback`). An open task with the same `site` and `key` is updated instead. |
| `GET`, `PATCH`, `DELETE /tasks/{id}` | Read, change, or move to the trash. |
| `POST /tasks/{id}/complete`, `/reopen` | Mark done, or open again. |

When a task with `notify` set and a `callback` URL is completed or reopened, the hub POSTs `{"event": "completed", "task_id": 12, "site": "...", "key": "..."}` to the callback, retrying for about 14 hours if that fails. The message carries no secret: the connected site fetches the task back from the hub to see what really happened before it acts.

## Google Calendar

On the hub, To-do → Settings → **Google Calendar** puts tasks with a due date on a "To-do" calendar in your Google account: all-day events for tasks with only a date, events of a set length (30 minutes by default) for tasks with a time. Event descriptions carry the task's notes, its link and a link back to the task. When a task is done its event gets a ✓ and shows as free, or is removed (your choice). Tasks with no due date, in the trash or deleted have no event. It's one way: changes made in Google Calendar aren't copied back.

1. In the [Google Cloud console](https://console.cloud.google.com/), enable the **Google Calendar API** for a project.
2. Set up the OAuth consent screen for your own account and set its publishing status to **In production**. While it's "Testing", Google ends the connection after 7 days. For your own use you don't need Google's verification; you'll see an "unverified app" warning you can continue past.
3. Create an OAuth client ID of type **Web application** with the redirect URI shown on the settings page (`/wp-admin/admin-post.php?action=tdh_google_callback`).
4. Paste the client ID and secret into the settings, save, and press **Connect Google Calendar**.

To-do Hub asks only for the `calendar.app.created` scope: it can create its own calendar and manage events on it, and can't see your other calendars. Changes go out at the end of the request that made them. Anything Google can't take right away is retried every five minutes, and everything is checked again once a day (only events that would look different are sent). Developers can point the plugin at other addresses with the `todo_hub_google_urls` filter.

## Install

Download `todo-hub-x.y.z.zip` from the [latest release](https://github.com/bignall/todo-hub/releases/latest) and upload it under **Plugins → Add New → Upload Plugin**. Requires PHP 8.1+ and WordPress 6.4+.

## Build from source

```bash
composer install      # installs WP Foundation and prefixes it with Strauss
composer test         # runs the unit tests (PHPUnit)
npm install && npm run site:build   # the guides site, into _site/
bin/build-zip.sh      # writes dist/todo-hub-<version>.zip
```

Pushing a tag such as `v0.1.0` that matches the `Version:` header builds the zip and publishes a GitHub release. Notes come from `release-notes/<tag>.md`.

Built on [WP Foundation](https://bignall.github.io/wp-foundation/). GPL-2.0-or-later.
