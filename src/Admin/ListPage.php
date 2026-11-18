<?php
/**
 * To-do → Task List: quick add, then open tasks first-to-do first, by category, due today,
 * or recently done. Tasks are ticked off with one click.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Priority;
use TodoHub\Ranking;
use TodoHub\Settings;
use TodoHub\Tasks;

final class ListPage {

	private const VIEWS = array( 'next', 'category', 'today', 'done' );

	public static function init(): void {
		add_action( 'admin_post_tdh_quick_add', array( self::class, 'handleQuickAdd' ) );
		add_action( 'admin_post_tdh_complete', array( self::class, 'handleComplete' ) );
		add_action( 'admin_post_tdh_reopen', array( self::class, 'handleReopen' ) );
	}

	/** @return array<string, string> Repeat choices for quick add. */
	private static function repeatChoices(): array {
		return array(
			''            => __( "Doesn't repeat", 'todo-hub' ),
			'daily'       => __( 'Daily', 'todo-hub' ),
			'weekly'      => __( 'Weekly', 'todo-hub' ),
			'fortnightly' => __( 'Every 2 weeks', 'todo-hub' ),
			'monthly'     => __( 'Monthly', 'todo-hub' ),
			'yearly'      => __( 'Yearly', 'todo-hub' ),
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Tasks::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view the task list.', 'todo-hub' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view filters.
		$view     = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'next';
		$view     = in_array( $view, self::VIEWS, true ) ? $view : 'next';
		$category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		$source   = isset( $_GET['source'] ) ? sanitize_key( $_GET['source'] ) : '';
		// phpcs:enable

		$filters = array_filter(
			array(
				'category' => $category,
				'source'   => $source,
			)
		);
		$tasks   = 'done' === $view ? Tasks::done() : Tasks::open( $filters );
		if ( 'today' === $view ) {
			$tasks = array_values( array_filter( $tasks, static fn ( $t ) => in_array( Format::dueState( $t['due'], $t['due_time'] ), array( Ranking::STATE_OVERDUE, Ranking::STATE_TODAY ), true ) ) );
		}
		?>
		<div class="wrap tdh">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'To-do', 'todo-hub' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Tasks::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Task with details', 'todo-hub' ); ?></a>
			<hr class="wp-header-end" />
			<?php
			self::notice();
			self::quickAdd( $category );
			self::views( $view, $filters );
			if ( 'done' !== $view ) {
				self::filters( $view, $category, $source );
			}

			if ( ! $tasks ) {
				echo '<p class="tdh-empty">' . esc_html( self::emptyText( $view, (bool) $filters ) ) . '</p>';
			} elseif ( 'category' === $view ) {
				self::byCategory( $tasks );
			} else {
				self::taskList( $tasks, 'done' === $view );
			}
			?>
		</div>
		<?php
	}

	private static function emptyText( string $view, bool $filtered ): string {
		if ( 'done' === $view ) {
			return __( 'Nothing done yet. Tick off a task and it shows here.', 'todo-hub' );
		}
		if ( 'today' === $view ) {
			return __( 'Nothing overdue or due today.', 'todo-hub' );
		}
		return $filtered ? __( 'No open tasks match these filters.', 'todo-hub' ) : __( 'All done. Add a task above.', 'todo-hub' );
	}

	private static function notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$message = isset( $_GET['tdh_msg'] ) ? sanitize_key( $_GET['tdh_msg'] ) : '';
		$task_id = isset( $_GET['task'] ) ? absint( $_GET['task'] ) : 0;
		// phpcs:enable
		if ( 'added' === $message ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Task added.', 'todo-hub' ) . '</p></div>';
		} elseif ( 'done' === $message && $task_id ) {
			$task = Tasks::get( $task_id );
			$undo = self::actionUrl( 'tdh_reopen', $task_id );
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: %s: task title. */
			echo esc_html( sprintf( __( 'Done: %s', 'todo-hub' ), $task ? $task['title'] : '' ) );
			echo ' <a href="' . esc_url( $undo ) . '">' . esc_html__( 'Undo', 'todo-hub' ) . '</a></p></div>';
		} elseif ( 'reopened' === $message ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Task reopened.', 'todo-hub' ) . '</p></div>';
		} elseif ( 'error' === $message ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'That didn\'t work. Give the task a title and try again.', 'todo-hub' ) . '</p></div>';
		}
	}

	private static function quickAdd( int $category ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => Tasks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$terms   = is_array( $terms ) ? $terms : array();
		$current = $category ? get_term( $category, Tasks::TAXONOMY ) : null;
		?>
		<form class="tdh-quick" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tdh_quick_add" />
			<?php wp_nonce_field( 'tdh_quick_add' ); ?>
			<input type="hidden" name="back" value="<?php echo esc_attr( self::backUrl() ); ?>" />
			<label class="screen-reader-text" for="tdh-title"><?php esc_html_e( 'Task', 'todo-hub' ); ?></label>
			<input id="tdh-title" class="tdh-quick__title" type="text" name="title" required placeholder="<?php esc_attr_e( 'Add a task…', 'todo-hub' ); ?>" />
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Category', 'todo-hub' ); ?></span>
				<input type="text" name="category" list="tdh-categories" placeholder="<?php esc_attr_e( 'Category', 'todo-hub' ); ?>" value="<?php echo esc_attr( $current instanceof \WP_Term ? $current->name : '' ); ?>" />
			</label>
			<datalist id="tdh-categories">
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->name ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Priority', 'todo-hub' ); ?></span>
				<select name="priority">
					<?php foreach ( Priority::all() as $value => $name ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( Priority::NORMAL, $value ); ?>><?php echo esc_html( Format::priority( $value ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Due date', 'todo-hub' ); ?></span>
				<input type="date" name="due" />
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Time (optional)', 'todo-hub' ); ?></span>
				<input type="time" name="due_time" title="<?php esc_attr_e( 'Time (optional)', 'todo-hub' ); ?>" />
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Repeat', 'todo-hub' ); ?></span>
				<select name="repeat">
					<?php foreach ( self::repeatChoices() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php submit_button( __( 'Add', 'todo-hub' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $filters Current filters, kept when switching views.
	 */
	private static function views( string $view, array $filters ): void {
		$labels = array(
			'next'     => __( 'Up next', 'todo-hub' ),
			'category' => __( 'By category', 'todo-hub' ),
			'today'    => __( 'Today', 'todo-hub' ),
			'done'     => __( 'Done', 'todo-hub' ),
		);
		$due_now = Tasks::dueNowCount();
		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $labels as $key => $label ) {
			$url   = Menu::url( array( 'view' => $key ) + ( 'done' === $key ? array() : $filters ) );
			$count = 'today' === $key && $due_now ? ' <span class="count">(' . number_format_i18n( $due_now ) . ')</span>' : '';
			$links[] = '<li><a href="' . esc_url( $url ) . '"' . ( $key === $view ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . $count . '</a>';
		}
		echo implode( " |</li>\n", $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</ul>';
	}

	private static function filters( string $view, int $category, string $source ): void {
		$sources = Tasks::sources();
		?>
		<form class="tdh-filters" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG ); ?>" />
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'        => Tasks::TAXONOMY,
					'name'            => 'category',
					'show_option_all' => __( 'All categories', 'todo-hub' ),
					'selected'        => $category,
					'hide_empty'      => false,
					'hierarchical'    => true,
					'hide_if_empty'   => true,
				)
			);
			if ( $sources ) :
				?>
				<select name="source">
					<option value=""><?php esc_html_e( 'All sources', 'todo-hub' ); ?></option>
					<?php foreach ( $sources as $name ) : ?>
						<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $source, $name ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			endif;
			submit_button( __( 'Filter', 'todo-hub' ), '', '', false );
			?>
		</form>
		<?php
	}

	/**
	 * @param list<array<string, mixed>> $tasks Sorted open tasks.
	 */
	private static function byCategory( array $tasks ): void {
		$groups = array();
		$names  = array();
		foreach ( $tasks as $task ) {
			$cats = $task['categories'] ? $task['categories'] : array(
				array(
					'id'   => 0,
					'name' => __( 'No category', 'todo-hub' ),
				),
			);
			foreach ( $cats as $cat ) {
				$groups[ $cat['id'] ][] = $task;
				$names[ $cat['id'] ]    = $cat['name'];
			}
		}
		// Categories with the most urgent first task first; "No category" last.
		uksort(
			$groups,
			static function ( $a, $b ) use ( $groups, $tasks ): int {
				if ( 0 === $a || 0 === $b ) {
					return 0 === $a ? 1 : -1;
				}
				return array_search( $groups[ $a ][0], $tasks, true ) <=> array_search( $groups[ $b ][0], $tasks, true );
			}
		);
		foreach ( $groups as $id => $group ) {
			echo '<h2 class="tdh-group">' . esc_html( $names[ $id ] ) . ' <span class="count">(' . esc_html( number_format_i18n( count( $group ) ) ) . ')</span></h2>';
			self::taskList( $group, false );
		}
	}

	/**
	 * @param list<array<string, mixed>> $tasks Tasks to show.
	 */
	private static function taskList( array $tasks, bool $done ): void {
		echo '<ul class="tdh-tasks">';
		foreach ( $tasks as $task ) {
			self::row( $task, $done );
		}
		echo '</ul>';
	}

	/**
	 * @param array<string, mixed> $task The task.
	 */
	private static function row( array $task, bool $done ): void {
		$state   = $done ? 'done' : Format::dueState( $task['due'], $task['due_time'] );
		$classes = array( 'tdh-task', 'tdh-task--' . $state, 'tdh-task--' . Priority::name( $task['priority'] ) );
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $done ) : ?>
				<a class="tdh-check tdh-check--done" href="<?php echo esc_url( self::actionUrl( 'tdh_reopen', $task['id'] ) ); ?>" title="<?php esc_attr_e( 'Reopen', 'todo-hub' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Reopen', 'todo-hub' ); ?></span></a>
			<?php else : ?>
				<a class="tdh-check" href="<?php echo esc_url( self::actionUrl( 'tdh_complete', $task['id'] ) ); ?>" title="<?php esc_attr_e( 'Mark done', 'todo-hub' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Mark done', 'todo-hub' ); ?></span></a>
			<?php endif; ?>
			<div class="tdh-task__main">
				<a class="tdh-task__title" href="<?php echo esc_url( $task['edit_url'] ); ?>"><?php echo esc_html( $task['title'] ); ?></a>
				<span class="tdh-task__meta">
					<?php if ( Priority::NORMAL !== $task['priority'] ) : ?>
						<span class="tdh-badge tdh-badge--<?php echo esc_attr( Priority::name( $task['priority'] ) ); ?>"><?php echo esc_html( Format::priority( $task['priority'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $done && $task['completed'] ) : ?>
						<span class="tdh-due">
							<?php
							/* translators: %s: date the task was completed. */
							echo esc_html( sprintf( __( 'Done %s', 'todo-hub' ), wp_date( get_option( 'date_format' ), (int) strtotime( $task['completed'] . ' UTC' ) ) ) );
							?>
						</span>
					<?php elseif ( '' !== $task['due'] ) : ?>
						<span class="tdh-due tdh-due--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( Format::due( $task['due'], $task['due_time'] ) ); ?></span>
					<?php endif; ?>
					<?php foreach ( $task['categories'] as $cat ) : ?>
						<a class="tdh-cat" href="<?php echo esc_url( Menu::url( array( 'category' => $cat['id'] ) ) ); ?>"><?php echo esc_html( $cat['name'] ); ?></a>
					<?php endforeach; ?>
					<?php if ( $task['repeat'] ) : ?>
						<span class="tdh-repeat"><?php echo esc_html( Format::repeat( $task['repeat'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $task['source'] ) : ?>
						<span class="tdh-source">
							<?php
							/* translators: %s: plugin or site that added the task. */
							echo esc_html( sprintf( __( 'from %s', 'todo-hub' ), $task['source'] ) );
							?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $task['link'] ) : ?>
						<a class="tdh-link" href="<?php echo esc_url( $task['link'] ); ?>"><?php esc_html_e( 'Go to it', 'todo-hub' ); ?> &rarr;</a>
					<?php endif; ?>
				</span>
			</div>
		</li>
		<?php
	}

	private static function actionUrl( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'task'   => $id,
					'back'   => rawurlencode( self::backUrl() ),
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $id
		);
	}

	/** The list page as it is now, without one-off message arguments. */
	private static function backUrl(): string {
		$keep = array();
		foreach ( array( 'view', 'category', 'source' ) as $arg ) {
			if ( isset( $_GET[ $arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$keep[ $arg ] = sanitize_key( wp_unslash( $_GET[ $arg ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return Menu::url( $keep );
	}

	/** @param array<string, mixed> $args Message arguments. */
	private static function redirect( array $args ): void {
		$back = isset( $_REQUEST['back'] ) ? esc_url_raw( rawurldecode( wp_unslash( (string) $_REQUEST['back'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$back = wp_validate_redirect( $back, Menu::url() );
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}

	public static function handleQuickAdd(): void {
		if ( ! current_user_can( Tasks::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to add tasks.', 'todo-hub' ), 403 );
		}
		check_admin_referer( 'tdh_quick_add' );
		$post = wp_unslash( $_POST );
		$args = array(
			'title'    => (string) ( $post['title'] ?? '' ),
			'priority' => (string) ( $post['priority'] ?? '' ),
			'due'      => (string) ( $post['due'] ?? '' ),
			'due_time' => (string) ( $post['due_time'] ?? '' ),
			'repeat'   => (string) ( $post['repeat'] ?? '' ),
		);
		$category = trim( (string) ( $post['category'] ?? '' ) );
		if ( '' !== $category ) {
			$args['category'] = $category;
		}
		$id = Tasks::add( $args );
		self::redirect( is_wp_error( $id ) ? array( 'tdh_msg' => 'error' ) : array( 'tdh_msg' => 'added' ) );
	}

	public static function handleComplete(): void {
		$id = isset( $_GET['task'] ) ? absint( $_GET['task'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next.
		check_admin_referer( 'tdh_complete_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You do not have permission to change this task.', 'todo-hub' ), 403 );
		}
		Tasks::complete( $id );
		self::redirect(
			array(
				'tdh_msg' => 'done',
				'task'    => $id,
			)
		);
	}

	public static function handleReopen(): void {
		$id = isset( $_GET['task'] ) ? absint( $_GET['task'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next.
		check_admin_referer( 'tdh_reopen_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You do not have permission to change this task.', 'todo-hub' ), 403 );
		}
		Tasks::reopen( $id );
		self::redirect( array( 'tdh_msg' => 'reopened' ) );
	}
}
