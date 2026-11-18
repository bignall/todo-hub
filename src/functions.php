<?php
/**
 * The PHP API other plugins on the same site use to add and complete tasks.
 *
 * Check that To-do Hub is active first:
 *
 *     if ( function_exists( 'todo_hub_add' ) ) {
 *         todo_hub_add( [ 'title' => 'Approve 5 new items', 'source' => 'where-to-buy', 'key' => 'where-to-buy:approve' ] );
 *     }
 *
 * Or fire the actions, which do nothing when To-do Hub isn't active:
 *
 *     do_action( 'todo_hub_add_task', [ 'title' => '...' ] );
 *     do_action( 'todo_hub_complete_task', 'where-to-buy:approve' );
 *
 * @package TodoHub
 */

declare(strict_types=1);

use TodoHub\Tasks;

if ( ! function_exists( 'todo_hub_add' ) ) {

	/**
	 * Add a task. If an open task already has the same key, that task is updated instead,
	 * so calling this again with the same key never makes duplicates.
	 *
	 * @param array<string, mixed> $args {
	 *     @type string            $title    Required.
	 *     @type string            $notes    Longer notes (HTML allowed, as in a post).
	 *     @type string|int|array  $category Category name, slug or id, or a list. New names are created.
	 *     @type string|int        $priority 'high', 'normal' (default) or 'low', or 3, 2, 1.
	 *     @type string|int|DateTimeInterface $due Y-m-d, a timestamp, a date object, or a phrase
	 *                                         such as 'tomorrow' or 'next friday'. Empty for none.
	 *     @type string            $source   Your plugin's slug, e.g. 'where-to-buy'.
	 *     @type string            $key      Your own id for this task, e.g. 'where-to-buy:approve-queue'.
	 *     @type string            $link     Where to go to do the task, e.g. an admin screen.
	 *     @type bool              $notify   Tell the source when it's done (used by connected sites).
	 *     @type string|array      $repeat   'daily', 'weekly', 'monthly', 'yearly', or
	 *                                       [ 'every' => 2, 'unit' => 'week' ].
	 *     @type array             $data     Anything you want back when the task is completed.
	 * }
	 * @return int|WP_Error The task id.
	 */
	function todo_hub_add( array $args ): int|WP_Error {
		return Tasks::add( $args );
	}

	/**
	 * Change a task. Only the fields you pass are changed.
	 *
	 * @param int|string           $id_or_key Task id, or the key of an open task.
	 * @param array<string, mixed> $args      Fields, as for todo_hub_add().
	 */
	function todo_hub_update( int|string $id_or_key, array $args ): true|WP_Error {
		$id = Tasks::resolve( $id_or_key );
		if ( ! $id ) {
			return new WP_Error( 'todo_hub_not_found', __( 'That task does not exist.', 'todo-hub' ) );
		}
		return Tasks::update( $id, $args );
	}

	/**
	 * Mark a task done. Fires todo_hub_task_completed, and adds the next copy of a repeating task.
	 *
	 * @param int|string $id_or_key Task id, or the key of an open task.
	 * @return bool False when there is no such open task.
	 */
	function todo_hub_complete( int|string $id_or_key ): bool {
		$id = Tasks::resolve( $id_or_key );
		return $id ? Tasks::complete( $id ) : false;
	}

	/**
	 * Reopen a done task.
	 *
	 * @param int $id Task id.
	 */
	function todo_hub_reopen( int $id ): bool {
		return Tasks::reopen( $id );
	}

	/**
	 * Remove a task that no longer needs doing (it goes to the trash, without firing
	 * todo_hub_task_completed). Use this when the thing the task was about went away.
	 *
	 * @param int|string $id_or_key Task id, or the key of an open task.
	 */
	function todo_hub_remove( int|string $id_or_key ): bool {
		$id = Tasks::resolve( $id_or_key );
		return $id ? Tasks::remove( $id ) : false;
	}

	/**
	 * A task as an array: id, title, notes, status (open, done or trash), priority (1-3),
	 * priority_name, due (Y-m-d or ''), categories, source, key, link, notify, repeat, data,
	 * created and completed (UTC, Y-m-d H:i:s), edit_url.
	 *
	 * @param int|string $id_or_key Task id, or the key of an open task.
	 * @return array<string, mixed>|null
	 */
	function todo_hub_get( int|string $id_or_key ): ?array {
		$id = Tasks::resolve( $id_or_key );
		return $id ? Tasks::get( $id ) : null;
	}

	/**
	 * Open tasks, first to do first.
	 *
	 * @param array{category?: int, source?: string} $filters Optional: a category id, or a source.
	 * @return list<array<string, mixed>>
	 */
	function todo_hub_open_tasks( array $filters = array() ): array {
		return Tasks::open( $filters );
	}
}
