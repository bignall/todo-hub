=== To-do Hub ===
Contributors: arbe
Tags: to-do, tasks, task list, reminders, productivity
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later

One central to-do list, sorted by what needs doing first. Other plugins can add tasks and hear when they're done.

== Description ==

* To-do → Task List: add a task in one line (title, category, priority, due date, repeat), then tick tasks off with one click. Undo is offered right away.
* Sorted for you: overdue tasks first, then tasks due today, then by priority. Tasks due within 3 days count two levels higher and within 7 days one level higher (both changeable in Settings), so things rise up the list as their date gets close.
* Views: Up next, By category, Today (overdue and due today) and Done. Filter by category or by the plugin that added the task.
* Repeating tasks: daily, weekly, every 2 weeks, monthly, yearly, or every N days, weeks, months or years. Completing one adds the next, due one step after the old due date (moved past today if you finished late). Monthly tasks on the 31st fall on the last day of shorter months.
* Each task can have notes, a link to where the work is done, and categories. Edit everything on the task's own screen.
* The To-do menu shows how many tasks are overdue or due today, and a dashboard widget lists what's next.
* Private site option: send visitors who aren't logged in to the login page, for a site that holds only your list.
* Built on WP Foundation (activation, upgrades, uninstall, multisite, deactivation survey).

== For plugin developers ==

    if ( function_exists( 'todo_hub_add' ) ) {
        todo_hub_add( [ 'title' => 'Approve 5 new items', 'source' => 'my-plugin', 'key' => 'my-plugin:approve', 'priority' => 'high' ] );
    }
    add_action( 'todo_hub_task_completed_my-plugin', function ( $task ) { ... } );

Or, without checking whether To-do Hub is active: do_action( 'todo_hub_add_task', [...] ), do_action( 'todo_hub_complete_task', $key ), do_action( 'todo_hub_remove_task', $key ).

A task added again with the same key updates the open task instead of adding a duplicate.

== Changelog ==

= 0.1.0 =
* First release: task list with quick add, priority sorting, categories, repeating tasks, views, dashboard widget, private site option and the PHP API.
