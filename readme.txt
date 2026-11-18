=== To-do Hub ===
Contributors: arbe
Tags: to-do, tasks, task list, reminders, productivity
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later

One central to-do list, sorted by what needs doing first. Other plugins can add tasks and hear when they're done.

== Description ==

* To-do → Task List: add a task in one line (title, category, priority, due date, optional time, repeat), then tick tasks off with one click. Undo is offered right away.
* Sorted for you: overdue tasks first, then tasks due today, then by priority. Tasks due within 3 days count two levels higher and within 7 days one level higher (both changeable in Settings), so things rise up the list as their date gets close.
* Views: Up next, By category, Today (overdue and due today) and Done. Filter by category or by the plugin that added the task.
* Due times: a task can have a time as well as a date. It counts as overdue once that time has passed, and on the same day timed tasks come first, earliest first.
* Repeating tasks: daily, weekly, every 2 weeks, monthly, yearly, or every N days, weeks, months or years. Completing one adds the next, due one step after the old due date (moved past today if you finished late). Monthly tasks on the 31st fall on the last day of shorter months.
* Each task can have notes, a link to where the work is done, and categories. Edit everything on the task's own screen.
* The To-do menu shows how many tasks are overdue or due today, and a dashboard widget lists what's next.
* Private site option: send visitors who aren't logged in to the login page, for a site that holds only your list.
* One list for all your sites: set the other sites to Connector mode and their tasks go to the hub, where each shows which site and plugin it came from. The hub tells a site when its tasks are done, and changes made while the hub is unreachable are sent later.
* REST API on the hub at /wp-json/todo-hub/v1, authenticated with application passwords.
* Google Calendar (on the hub): tasks with a due date go on a "To-do" calendar in your Google account, as all-day events or at their due time, and are updated, ticked or removed as the tasks change.
* Built on WP Foundation (activation, upgrades, uninstall, multisite, deactivation survey).

== For plugin developers ==

    if ( function_exists( 'todo_hub_add' ) ) {
        todo_hub_add( [ 'title' => 'Approve 5 new items', 'source' => 'my-plugin', 'key' => 'my-plugin:approve', 'priority' => 'high' ] );
    }
    add_action( 'todo_hub_task_completed_my-plugin', function ( $task ) { ... } );

Or, without checking whether To-do Hub is active: do_action( 'todo_hub_add_task', [...] ), do_action( 'todo_hub_complete_task', $key ), do_action( 'todo_hub_remove_task', $key ).

A task added again with the same key updates the open task instead of adding a duplicate.

== Connecting sites ==

1. On the hub: add a user with the To-do Connector role and create an application password for it (Users → Profile → Application Passwords).
2. On the other site: To-do → Settings → Role: Connector, then enter the hub's address, the user name and the application password.

The hub must use https. Run WP-Cron on a real schedule (a server cron job every five minutes) so waiting changes and retries go out on time.

== Google Calendar ==

To-do → Settings → Google Calendar, on the hub. You need your own Google OAuth client:

1. In the Google Cloud console, enable the Google Calendar API.
2. Set up the OAuth consent screen and set its publishing status to In production (while it's Testing, Google ends the connection after 7 days).
3. Create an OAuth client ID of type Web application with the redirect URI shown on the settings page.
4. Enter the client ID and secret, save, and press Connect Google Calendar.

To-do Hub only asks to manage a calendar it creates itself; it can't see your other calendars. Changes are copied from the task list to the calendar, not back.

== Changelog ==

= 0.3.0 =
* Google Calendar sync on the hub: dated tasks become events on a "To-do" calendar (all day, or at their due time), kept up to date as tasks change, are completed, reopened, trashed or deleted. Done tasks are ticked and shown as free, or removed. Retries when Google can't be reached, and a daily check.

= 0.2.0 =
* Connector mode: send this site's tasks to a hub on another site. The PHP API works the same in both modes.
* REST API on the hub, and a To-do Connector role for connected sites to log in as.
* The hub reports completed and reopened tasks back to the site that added them (for tasks with notify set), retrying if the site can't be reached.
* Changes made while the hub can't be reached are kept and sent in order.
* Tasks from connected sites show which site they came from.

= 0.1.0 =
* First release: task list with quick add, priority sorting, due dates with optional times, categories, repeating tasks, views, dashboard widget, private site option and the PHP API.
