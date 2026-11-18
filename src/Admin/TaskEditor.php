<?php
/**
 * The task edit screen: a "Task details" box with status, priority, due date, repeat and link,
 * plus Due and Priority columns on the All Tasks list.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Priority;
use TodoHub\Tasks;

final class TaskEditor {

	private const NONCE = 'tdh_details';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . Tasks::POST_TYPE, array( self::class, 'addBox' ) );
		add_action( 'save_post_' . Tasks::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( self::class, 'titlePlaceholder' ), 10, 2 );
		add_filter( 'manage_' . Tasks::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Tasks::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'display_post_states', array( self::class, 'postStates' ), 10, 2 );
		add_filter( 'views_edit-' . Tasks::POST_TYPE, array( self::class, 'views' ) );
	}

	/**
	 * On All Tasks, open tasks are "Open", not "Published".
	 *
	 * @param array<string, string> $views Status links.
	 * @return array<string, string>
	 */
	public static function views( array $views ): array {
		if ( isset( $views['publish'] ) ) {
			$views['publish'] = (string) preg_replace( '/>[^<>]+(<span class="count">)/', '>' . esc_html__( 'Open', 'todo-hub' ) . ' $1', $views['publish'], 1 );
		}
		return $views;
	}

	public static function titlePlaceholder( string $text, \WP_Post $post ): string {
		return Tasks::POST_TYPE === $post->post_type ? __( 'What needs doing?', 'todo-hub' ) : $text;
	}

	public static function addBox(): void {
		add_meta_box( 'tdh-details', __( 'Task details', 'todo-hub' ), array( self::class, 'render' ), Tasks::POST_TYPE, 'side', 'high' );
	}

	public static function render( \WP_Post $post ): void {
		$task   = Tasks::get( $post->ID );
		$done   = Tasks::STATUS_DONE === $post->post_status;
		$prio   = $task ? $task['priority'] : Priority::NORMAL;
		$due    = $task ? $task['due'] : '';
		$repeat = $task ? $task['repeat'] : null;
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<p>
			<label for="tdh-status"><strong><?php esc_html_e( 'Status', 'todo-hub' ); ?></strong></label><br />
			<select id="tdh-status" name="tdh_status">
				<option value="open" <?php selected( ! $done ); ?>><?php esc_html_e( 'Open', 'todo-hub' ); ?></option>
				<option value="done" <?php selected( $done ); ?>><?php esc_html_e( 'Done', 'todo-hub' ); ?></option>
			</select>
		</p>
		<p>
			<label for="tdh-priority"><strong><?php esc_html_e( 'Priority', 'todo-hub' ); ?></strong></label><br />
			<select id="tdh-priority" name="tdh_priority">
				<?php foreach ( Priority::all() as $value => $name ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $prio, $value ); ?>><?php echo esc_html( Format::priority( $value ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="tdh-due"><strong><?php esc_html_e( 'Due date', 'todo-hub' ); ?></strong></label><br />
			<input id="tdh-due" type="date" name="tdh_due" value="<?php echo esc_attr( $due ); ?>" />
		</p>
		<p>
			<label for="tdh-due-time"><strong><?php esc_html_e( 'Time', 'todo-hub' ); ?></strong> <span class="description"><?php esc_html_e( '(optional)', 'todo-hub' ); ?></span></label><br />
			<input id="tdh-due-time" type="time" name="tdh_due_time" value="<?php echo esc_attr( $task ? $task['due_time'] : '' ); ?>" />
		</p>
		<fieldset class="tdh-repeat-field">
			<legend><strong><?php esc_html_e( 'Repeat', 'todo-hub' ); ?></strong></legend>
			<label>
				<?php esc_html_e( 'Every', 'todo-hub' ); ?>
				<input type="number" name="tdh_repeat_every" min="1" max="999" class="small-text" value="<?php echo esc_attr( (string) ( $repeat['every'] ?? 1 ) ); ?>" />
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Unit', 'todo-hub' ); ?></span>
				<select name="tdh_repeat_unit">
					<?php foreach ( Format::units() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $repeat['unit'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<span class="description"><?php esc_html_e( 'When it\'s done, the next one is added automatically.', 'todo-hub' ); ?></span>
		</fieldset>
		<p>
			<label for="tdh-link"><strong><?php esc_html_e( 'Link', 'todo-hub' ); ?></strong></label><br />
			<input id="tdh-link" type="url" name="tdh_link" class="widefat" placeholder="https://" value="<?php echo esc_attr( $task ? $task['link'] : '' ); ?>" />
			<span class="description"><?php esc_html_e( 'Where to go to do it.', 'todo-hub' ); ?></span>
		</p>
		<?php if ( $task && ( '' !== $task['source'] || '' !== $task['key'] ) ) : ?>
			<p class="description">
				<?php
				if ( '' !== $task['source'] ) {
					/* translators: %s: plugin or site that added the task. */
					echo esc_html( sprintf( __( 'Added by: %s', 'todo-hub' ), $task['source'] ) ) . '<br />';
				}
				if ( '' !== $task['key'] ) {
					/* translators: %s: the task's key. */
					echo esc_html( sprintf( __( 'Key: %s', 'todo-hub' ), $task['key'] ) );
				}
				?>
			</p>
		<?php endif; ?>
		<?php if ( $task && $task['completed'] ) : ?>
			<p class="description">
				<?php
				/* translators: %s: date and time. */
				echo esc_html( sprintf( __( 'Completed %s', 'todo-hub' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $task['completed'] . ' UTC' ) ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE . '_nonce' ] ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = wp_unslash( $_POST );
		$unit  = sanitize_key( (string) ( $input['tdh_repeat_unit'] ?? '' ) );
		Tasks::update(
			$post_id,
			array(
				'priority' => (string) ( $input['tdh_priority'] ?? '' ),
				'due'      => (string) ( $input['tdh_due'] ?? '' ),
				'due_time' => (string) ( $input['tdh_due_time'] ?? '' ),
				'link'     => (string) ( $input['tdh_link'] ?? '' ),
				'repeat'   => '' === $unit ? null : array(
					'every' => absint( $input['tdh_repeat_every'] ?? 1 ),
					'unit'  => $unit,
				),
			)
		);
	}

	/**
	 * @param array<string, string> $columns List table columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['tdh_priority'] = __( 'Priority', 'todo-hub' );
				$out['tdh_due']      = __( 'Due', 'todo-hub' );
			}
		}
		return $out;
	}

	public static function column( string $column, int $post_id ): void {
		$task = Tasks::get( $post_id );
		if ( null === $task ) {
			return;
		}
		if ( 'tdh_priority' === $column ) {
			echo esc_html( Format::priority( $task['priority'] ) );
		} elseif ( 'tdh_due' === $column && '' !== $task['due'] ) {
			$state = 'done' === $task['status'] ? 'done' : Format::dueState( $task['due'], $task['due_time'] );
			echo '<span class="tdh-due tdh-due--' . esc_attr( $state ) . '">' . esc_html( 'done' === $task['status'] ? trim( $task['due'] . ' ' . $task['due_time'] ) : Format::due( $task['due'], $task['due_time'] ) ) . '</span>';
		}
	}

	/**
	 * Show "Done" after done tasks' titles on All Tasks.
	 *
	 * @param array<string, string> $states Post states.
	 * @return array<string, string>
	 */
	public static function postStates( array $states, \WP_Post $post ): array {
		if ( Tasks::POST_TYPE === $post->post_type && Tasks::STATUS_DONE === $post->post_status ) {
			$states['tdh_done'] = _x( 'Done', 'task status', 'todo-hub' );
		}
		return $states;
	}
}
