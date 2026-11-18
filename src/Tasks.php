<?php
/**
 * Tasks: a private post type, its categories, and the code that adds, changes, completes
 * and reads them. Everything else (admin screens, the PHP API) goes through this class.
 *
 * Storage: title = post_title, notes = post_content, open = 'publish', done = 'tdh_done',
 * categories = the tdh_category taxonomy, everything else in post meta.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Tasks {

	public const POST_TYPE   = 'tdh_task';
	public const TAXONOMY    = 'tdh_category';
	public const STATUS_OPEN = 'publish';
	public const STATUS_DONE = 'tdh_done';
	public const CAPABILITY  = 'manage_todo_hub';

	public const META_PRIORITY = '_tdh_priority';
	public const META_DUE      = '_tdh_due';
	public const META_DUE_TIME = '_tdh_due_time';
	public const META_SOURCE   = '_tdh_source';
	public const META_KEY      = '_tdh_key';
	public const META_LINK     = '_tdh_link';
	public const META_SITE     = '_tdh_site';
	public const META_CALLBACK = '_tdh_callback';
	public const META_NOTIFY   = '_tdh_notify';
	public const META_REPEAT   = '_tdh_repeat';
	public const META_DATA     = '_tdh_data';
	public const META_DONE_AT  = '_tdh_done_at';
	public const META_NEXT_ID  = '_tdh_next_id';

	/** Set while this class saves a task itself, so the editor's save handler stays out of the way. */
	private static bool $saving = false;

	/** @var array<int, string> Task id => 'done' or 'reopened', waiting for afterSave(). */
	private static array $changed = array();

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_filter( 'wp_insert_post_data', array( self::class, 'keepStatus' ), 10, 2 );
		add_action( 'transition_post_status', array( self::class, 'onTransition' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( self::class, 'afterSave' ), 10, 1 );
	}

	public static function register(): void {
		$cap = self::CAPABILITY;

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Tasks', 'todo-hub' ),
					'singular_name'      => __( 'Task', 'todo-hub' ),
					'add_new'            => __( 'Add Task', 'todo-hub' ),
					'add_new_item'       => __( 'Add Task', 'todo-hub' ),
					'edit_item'          => __( 'Edit Task', 'todo-hub' ),
					'new_item'           => __( 'New Task', 'todo-hub' ),
					'search_items'       => __( 'Search Tasks', 'todo-hub' ),
					'not_found'          => __( 'No tasks found.', 'todo-hub' ),
					'not_found_in_trash' => __( 'No tasks in the trash.', 'todo-hub' ),
					'all_items'          => __( 'All Tasks', 'todo-hub' ),
					'menu_name'          => __( 'To-do', 'todo-hub' ),
				),
				'public'          => false,
				// On a connected site tasks live on the hub, so there's nothing to edit here.
				'show_ui'         => ! Settings::isConnector(),
				'show_in_menu'    => Admin\Menu::SLUG,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'editor' ),
				'taxonomies'      => array( self::TAXONOMY ),
				'map_meta_cap'    => true,
				'capability_type' => 'tdh_task',
				'capabilities'    => array(
					'create_posts'           => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_published_posts'   => $cap,
					'edit_private_posts'     => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_published_posts' => $cap,
					'delete_private_posts'   => $cap,
					'read'                   => $cap,
				),
				'rewrite'         => false,
				'query_var'       => false,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Categories', 'todo-hub' ),
					'singular_name' => __( 'Category', 'todo-hub' ),
					'all_items'     => __( 'All Categories', 'todo-hub' ),
					'edit_item'     => __( 'Edit Category', 'todo-hub' ),
					'add_new_item'  => __( 'Add Category', 'todo-hub' ),
					'menu_name'     => __( 'Categories', 'todo-hub' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'show_in_rest'      => false,
				'rewrite'           => false,
				'query_var'         => false,
				'capabilities'      => array(
					'manage_terms' => $cap,
					'edit_terms'   => $cap,
					'delete_terms' => $cap,
					'assign_terms' => $cap,
				),
			)
		);

		register_post_status(
			self::STATUS_DONE,
			array(
				'label'                     => _x( 'Done', 'task status', 'todo-hub' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of tasks. */
				'label_count'               => _n_noop( 'Done <span class="count">(%s)</span>', 'Done <span class="count">(%s)</span>', 'todo-hub' ),
			)
		);
	}

	/**
	 * A task is either open (publish) or done. Draft, pending, private and scheduled saves from
	 * the classic editor become open, and a done task stays done unless it's reopened.
	 *
	 * @param array<string, mixed> $data    Post data about to be saved.
	 * @param array<string, mixed> $postarr Raw submitted data.
	 * @return array<string, mixed>
	 */
	public static function keepStatus( array $data, array $postarr ): array {
		if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}
		$status = (string) ( $data['post_status'] ?? '' );
		if ( in_array( $status, array( 'auto-draft', 'trash', 'inherit', self::STATUS_DONE ), true ) ) {
			return $data;
		}
		if ( ! self::$saving ) {
			// The task editor's Status field decides; other screens (quick edit) keep the current status.
			if ( isset( $postarr['tdh_status'] ) ) {
				$data['post_status'] = 'done' === $postarr['tdh_status'] ? self::STATUS_DONE : self::STATUS_OPEN;
				return $data;
			}
			if ( ! empty( $postarr['ID'] ) && get_post_status( (int) $postarr['ID'] ) === self::STATUS_DONE ) {
				$data['post_status'] = self::STATUS_DONE;
				return $data;
			}
		}
		$data['post_status'] = self::STATUS_OPEN;
		return $data;
	}

	/**
	 * Completing a task, however it happens (list screen, editor, API), records when, adds the
	 * next copy of a repeating task, and fires todo_hub_task_completed. Reopening clears that.
	 *
	 * The status change is noted here and acted on in afterSave(), once the editor has saved
	 * the task's fields and categories, so the next copy and the hook see the saved task.
	 */
	public static function onTransition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( self::POST_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( self::STATUS_DONE === $new_status ) {
			self::$changed[ $post->ID ] = 'done';
		} elseif ( self::STATUS_DONE === $old_status && self::STATUS_OPEN === $new_status ) {
			self::$changed[ $post->ID ] = 'reopened';
		}
	}

	public static function afterSave( int $post_id ): void {
		$change = self::$changed[ $post_id ] ?? null;
		if ( null === $change ) {
			return;
		}
		unset( self::$changed[ $post_id ] );

		if ( 'reopened' === $change ) {
			delete_post_meta( $post_id, self::META_DONE_AT );
			/**
			 * Fires when a done task is reopened.
			 *
			 * @param array<string, mixed> $task The task, as todo_hub_get() returns it.
			 */
			do_action( 'todo_hub_task_reopened', self::get( $post_id ) );
			return;
		}

		update_post_meta( $post_id, self::META_DONE_AT, current_time( 'mysql', true ) );
		$task = self::get( $post_id );
		self::addNextCopy( $post_id );
		if ( null === $task ) {
			return;
		}
		/**
		 * Fires when a task is marked done.
		 *
		 * @param array<string, mixed> $task The task, as todo_hub_get() returns it.
		 */
		do_action( 'todo_hub_task_completed', $task );
		if ( '' !== $task['source'] ) {
			/**
			 * Fires when a task from one source is marked done. The hook name ends with the
			 * source, e.g. todo_hub_task_completed_where-to-buy.
			 *
			 * @param array<string, mixed> $task The task.
			 */
			do_action( 'todo_hub_task_completed_' . $task['source'], $task );
		}
	}

	/**
	 * Add a task, or update the open task with the same key.
	 *
	 * @param array<string, mixed> $args See todo_hub_add().
	 */
	public static function add( array $args ): int|\WP_Error {
		$key  = isset( $args['key'] ) ? self::cleanKey( $args['key'] ) : '';
		$site = isset( $args['site'] ) ? self::cleanSite( $args['site'] ) : '';
		if ( '' !== $key ) {
			$existing = self::findOpenByKey( $key, $site );
			if ( $existing ) {
				$updated = self::update( $existing, $args );
				return true === $updated ? $existing : $updated;
			}
		}

		$title = isset( $args['title'] ) ? trim( sanitize_text_field( (string) $args['title'] ) ) : '';
		if ( '' === $title ) {
			return new \WP_Error( 'todo_hub_no_title', __( 'A task needs a title.', 'todo-hub' ) );
		}

		self::$saving = true;
		$id           = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => self::STATUS_OPEN,
				'post_title'   => $title,
				'post_content' => isset( $args['notes'] ) ? wp_kses_post( (string) $args['notes'] ) : '',
				'post_author'  => get_current_user_id(),
			),
			true
		);
		self::$saving = false;
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$defaults = array(
			'priority' => Priority::NORMAL,
			'due'      => '',
			'source'   => '',
			'key'      => $key,
			'site'     => $site,
			'callback' => '',
			'link'     => '',
			'notify'   => false,
			'repeat'   => null,
			'data'     => array(),
		);
		self::saveFields( $id, array_merge( $defaults, $args, array( 'key' => $key, 'site' => $site ) ) );

		/**
		 * Fires when a task is added.
		 *
		 * @param array<string, mixed> $task The new task.
		 */
		do_action( 'todo_hub_task_added', self::get( $id ) );
		return $id;
	}

	/**
	 * Change a task. Only the fields passed are changed.
	 *
	 * @param array<string, mixed> $args See todo_hub_add().
	 */
	public static function update( int $id, array $args ): bool|\WP_Error {
		if ( ! self::exists( $id ) ) {
			return new \WP_Error( 'todo_hub_not_found', __( 'That task does not exist.', 'todo-hub' ) );
		}
		$post = array();
		if ( array_key_exists( 'title', $args ) ) {
			$title = trim( sanitize_text_field( (string) $args['title'] ) );
			if ( '' === $title ) {
				return new \WP_Error( 'todo_hub_no_title', __( 'A task needs a title.', 'todo-hub' ) );
			}
			$post['post_title'] = $title;
		}
		if ( array_key_exists( 'notes', $args ) ) {
			$post['post_content'] = wp_kses_post( (string) $args['notes'] );
		}
		if ( $post ) {
			self::$saving = true;
			$result       = wp_update_post( array( 'ID' => $id ) + $post, true );
			self::$saving = false;
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( array_key_exists( 'key', $args ) ) {
			$args['key'] = self::cleanKey( $args['key'] );
		}
		self::saveFields( $id, $args );
		return true;
	}

	/**
	 * Store the meta fields and categories present in $args.
	 *
	 * @param array<string, mixed> $args Task fields.
	 */
	private static function saveFields( int $id, array $args ): void {
		if ( array_key_exists( 'priority', $args ) ) {
			update_post_meta( $id, self::META_PRIORITY, Priority::parse( $args['priority'] ) );
		}
		if ( array_key_exists( 'due', $args ) ) {
			// A due value replaces the date and the time; a time inside it ("tomorrow 3pm") is kept.
			$due = DueDate::parseWithTime( $args['due'], wp_timezone(), Settings::today() );
			update_post_meta( $id, self::META_DUE, $due['date'] );
			update_post_meta( $id, self::META_DUE_TIME, '' === $due['date'] ? '' : $due['time'] );
		}
		if ( array_key_exists( 'due_time', $args ) ) {
			$has_date = '' !== (string) get_post_meta( $id, self::META_DUE, true );
			update_post_meta( $id, self::META_DUE_TIME, $has_date ? DueDate::parseTime( $args['due_time'] ) : '' );
		}
		if ( array_key_exists( 'source', $args ) ) {
			update_post_meta( $id, self::META_SOURCE, sanitize_key( (string) $args['source'] ) );
		}
		if ( array_key_exists( 'key', $args ) ) {
			update_post_meta( $id, self::META_KEY, (string) $args['key'] );
		}
		if ( array_key_exists( 'link', $args ) ) {
			update_post_meta( $id, self::META_LINK, esc_url_raw( (string) $args['link'] ) );
		}
		if ( array_key_exists( 'site', $args ) ) {
			update_post_meta( $id, self::META_SITE, self::cleanSite( $args['site'] ) );
		}
		if ( array_key_exists( 'callback', $args ) ) {
			$url = esc_url_raw( (string) $args['callback'], array( 'https', 'http' ) );
			update_post_meta( $id, self::META_CALLBACK, $url );
		}
		if ( array_key_exists( 'notify', $args ) ) {
			update_post_meta( $id, self::META_NOTIFY, $args['notify'] ? 1 : 0 );
		}
		if ( array_key_exists( 'repeat', $args ) ) {
			$rule = Recurrence::parse( $args['repeat'] );
			if ( null === $rule ) {
				delete_post_meta( $id, self::META_REPEAT );
			} else {
				update_post_meta( $id, self::META_REPEAT, $rule );
			}
		}
		if ( array_key_exists( 'data', $args ) ) {
			update_post_meta( $id, self::META_DATA, is_array( $args['data'] ) ? $args['data'] : array() );
		}
		if ( array_key_exists( 'category', $args ) ) {
			wp_set_object_terms( $id, self::categoryIds( $args['category'] ), self::TAXONOMY );
		}
		/**
		 * Fires after a task's fields are saved (added or changed through To-do Hub).
		 *
		 * @param int $id Task id.
		 */
		do_action( 'todo_hub_task_changed', $id );
	}

	/**
	 * Category ids for names, slugs or ids. Names that don't exist yet are created.
	 *
	 * @param mixed $value A name, slug, id, or a list of them.
	 * @return list<int>
	 */
	public static function categoryIds( mixed $value ): array {
		$ids = array();
		foreach ( (array) $value as $item ) {
			if ( is_int( $item ) || ( is_string( $item ) && ctype_digit( $item ) ) ) {
				$term = get_term( (int) $item, self::TAXONOMY );
				if ( $term instanceof \WP_Term ) {
					$ids[] = $term->term_id;
				}
				continue;
			}
			$name = trim( sanitize_text_field( (string) $item ) );
			if ( '' === $name ) {
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( $name ), self::TAXONOMY );
			if ( ! $term instanceof \WP_Term ) {
				$term = get_term_by( 'name', $name, self::TAXONOMY );
			}
			if ( $term instanceof \WP_Term ) {
				$ids[] = $term->term_id;
				continue;
			}
			$made = wp_insert_term( $name, self::TAXONOMY );
			if ( is_array( $made ) ) {
				$ids[] = (int) $made['term_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** Mark a task done. Returns false when it doesn't exist or is already done. */
	public static function complete( int $id ): bool {
		if ( ! self::exists( $id ) || get_post_status( $id ) === self::STATUS_DONE ) {
			return false;
		}
		self::$saving = true;
		$result       = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => self::STATUS_DONE,
			),
			true
		);
		self::$saving = false;
		return ! is_wp_error( $result );
	}

	/** Reopen a done task. */
	public static function reopen( int $id ): bool {
		if ( ! self::exists( $id ) || get_post_status( $id ) !== self::STATUS_DONE ) {
			return false;
		}
		self::$saving = true;
		$result       = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => self::STATUS_OPEN,
			),
			true
		);
		self::$saving = false;
		return ! is_wp_error( $result );
	}

	/** Move a task to the trash. */
	public static function remove( int $id ): bool {
		return self::exists( $id ) && (bool) wp_trash_post( $id );
	}

	/** For a repeating task that was just completed, add the next copy (once). */
	private static function addNextCopy( int $id ): void {
		$rule = Recurrence::parse( get_post_meta( $id, self::META_REPEAT, true ) );
		if ( null === $rule || get_post_meta( $id, self::META_NEXT_ID, true ) ) {
			return;
		}
		$task = self::get( $id );
		if ( null === $task ) {
			return;
		}
		$next = self::add(
			array(
				'title'    => $task['title'],
				'notes'    => get_post_field( 'post_content', $id ),
				'category' => array_column( $task['categories'], 'id' ),
				'priority' => $task['priority'],
				'due'      => Recurrence::next( $task['due'], $rule, Settings::today() ),
				'due_time' => $task['due_time'],
				'source'   => $task['source'],
				'link'     => $task['link'],
				'site'     => $task['site'],
				'callback' => (string) get_post_meta( $id, self::META_CALLBACK, true ),
				'notify'   => $task['notify'],
				'repeat'   => $rule,
				'data'     => $task['data'],
			)
		);
		if ( is_int( $next ) ) {
			update_post_meta( $id, self::META_NEXT_ID, $next );
			// The key moves to the new copy, so the source plugin keeps talking to the open one.
			if ( '' !== $task['key'] ) {
				update_post_meta( $next, self::META_KEY, $task['key'] );
				delete_post_meta( $id, self::META_KEY );
			}
		}
	}

	public static function exists( int $id ): bool {
		return $id > 0 && get_post_type( $id ) === self::POST_TYPE && get_post_status( $id ) !== 'trash';
	}

	/**
	 * An id, or a key (string) for the open task with that key. Keys are per site: tasks
	 * added on the hub itself have site '', tasks from a connected site carry its address.
	 */
	public static function resolve( int|string $id_or_key, string $site = '' ): int {
		if ( is_int( $id_or_key ) || ctype_digit( $id_or_key ) ) {
			$id = (int) $id_or_key;
			return self::exists( $id ) ? $id : 0;
		}
		return self::findOpenByKey( self::cleanKey( $id_or_key ), self::cleanSite( $site ) );
	}

	/** Id of the open task with this key (from this site), or 0. */
	public static function findOpenByKey( string $key, string $site = '' ): int {
		if ( '' === $key ) {
			return 0;
		}
		$meta = array(
			array(
				'key'   => self::META_KEY,
				'value' => $key,
			),
		);
		if ( '' === $site ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_SITE,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => self::META_SITE,
					'value' => '',
				),
			);
		} else {
			$meta[] = array(
				'key'   => self::META_SITE,
				'value' => $site,
			);
		}
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::STATUS_OPEN,
				'meta_query'       => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** A connected site's address without the scheme, e.g. "rosinabignall.com" or "example.com/blog". */
	public static function cleanSite( mixed $site ): string {
		$site = is_scalar( $site ) ? strtolower( trim( (string) $site ) ) : '';
		$site = (string) preg_replace( '#^[a-z]+://#', '', $site );
		return substr( rtrim( (string) preg_replace( '#[^a-z0-9.\-:/_~]#', '', $site ), '/' ), 0, 191 );
	}

	/** Keys are free text from other plugins, e.g. "where-to-buy:approve-queue". */
	public static function cleanKey( mixed $key ): string {
		$key = is_scalar( $key ) ? (string) $key : '';
		return substr( (string) preg_replace( '/[^A-Za-z0-9:_\-.\/]/', '', $key ), 0, 191 );
	}

	/**
	 * A task as an array.
	 *
	 * @return array{id: int, title: string, notes: string, status: string, priority: int, priority_name: string, due: string, due_time: string, categories: list<array{id: int, name: string, slug: string}>, source: string, key: string, link: string, site: string, notify: bool, repeat: array{every: int, unit: string}|null, data: array<mixed>, created: string, completed: string, edit_url: string}|null
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$terms      = get_the_terms( $post, self::TAXONOMY );
		$categories = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$categories[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}
		$data     = get_post_meta( $id, self::META_DATA, true );
		$priority = Priority::parse( get_post_meta( $id, self::META_PRIORITY, true ) );
		return array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'notes'         => $post->post_content,
			'status'        => self::STATUS_DONE === $post->post_status ? 'done' : ( 'trash' === $post->post_status ? 'trash' : 'open' ),
			'priority'      => $priority,
			'priority_name' => Priority::name( $priority ),
			'due'           => (string) get_post_meta( $id, self::META_DUE, true ),
			'due_time'      => (string) get_post_meta( $id, self::META_DUE_TIME, true ),
			'categories'    => $categories,
			'source'        => (string) get_post_meta( $id, self::META_SOURCE, true ),
			'key'           => (string) get_post_meta( $id, self::META_KEY, true ),
			'link'          => (string) get_post_meta( $id, self::META_LINK, true ),
			'site'          => (string) get_post_meta( $id, self::META_SITE, true ),
			'notify'        => (bool) get_post_meta( $id, self::META_NOTIFY, true ),
			'repeat'        => Recurrence::parse( get_post_meta( $id, self::META_REPEAT, true ) ),
			'data'          => is_array( $data ) ? $data : array(),
			'created'       => $post->post_date_gmt,
			'completed'     => (string) get_post_meta( $id, self::META_DONE_AT, true ),
			'edit_url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * Open tasks, first to do first.
	 *
	 * @param array{category?: int, source?: string, site?: string, key?: string} $filters Optional filters.
	 * @return list<array<string, mixed>>
	 */
	public static function open( array $filters = array() ): array {
		$query = array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => self::STATUS_OPEN,
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		);
		if ( ! empty( $filters['category'] ) ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => self::TAXONOMY,
					'terms'    => (int) $filters['category'],
				),
			);
		}
		$meta = self::metaFilters( $filters );
		if ( $meta ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$ids = get_posts( $query );
		update_meta_cache( 'post', $ids );
		update_object_term_cache( $ids, self::POST_TYPE );
		$tasks = array_values( array_filter( array_map( static fn ( $id ) => self::get( (int) $id ), $ids ) ) );
		return Ranking::sort( $tasks, Settings::now(), Settings::soonDays(), Settings::weekDays() );
	}

	/**
	 * Meta query clauses for the source, site and key filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return list<array<string, string>>
	 */
	private static function metaFilters( array $filters ): array {
		$meta = array();
		if ( ! empty( $filters['source'] ) ) {
			$meta[] = array(
				'key'   => self::META_SOURCE,
				'value' => sanitize_key( (string) $filters['source'] ),
			);
		}
		if ( ! empty( $filters['site'] ) ) {
			$meta[] = array(
				'key'   => self::META_SITE,
				'value' => self::cleanSite( $filters['site'] ),
			);
		}
		if ( ! empty( $filters['key'] ) ) {
			$meta[] = array(
				'key'   => self::META_KEY,
				'value' => self::cleanKey( $filters['key'] ),
			);
		}
		return $meta;
	}

	/**
	 * Recently completed tasks, newest first.
	 *
	 * @param array{source?: string, site?: string, key?: string} $filters Optional filters.
	 * @return list<array<string, mixed>>
	 */
	public static function done( int $limit = 50, array $filters = array() ): array {
		$meta   = self::metaFilters( $filters );
		$meta[] = array(
			'key'     => self::META_DONE_AT,
			'compare' => 'EXISTS',
		);
		$ids    = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::STATUS_DONE,
				'posts_per_page'   => $limit,
				'fields'           => 'ids',
				'meta_query'       => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_key'         => self::META_DONE_AT, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'          => 'meta_value',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return array_values( array_filter( array_map( static fn ( $id ) => self::get( (int) $id ), $ids ) ) );
	}

	/** How many open tasks are overdue or due today (for the menu bubble). */
	public static function dueNowCount(): int {
		$today = Settings::today()->format( 'Y-m-d' );
		$query = new \WP_Query(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => self::STATUS_OPEN,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => self::META_DUE,
						'value'   => array( '0000-00-01', $today ),
						'compare' => 'BETWEEN',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Sources that have open tasks, for the filter.
	 *
	 * @return list<string>
	 */
	public static function sources(): array {
		global $wpdb;
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_type = %s AND p.post_status = %s ORDER BY m.meta_value",
				self::META_SOURCE,
				self::POST_TYPE,
				self::STATUS_OPEN
			)
		);
		return array_map( 'strval', $rows );
	}
}
