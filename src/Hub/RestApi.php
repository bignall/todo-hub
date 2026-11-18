<?php
/**
 * The hub's REST API, used by connected sites (and anything else that can make HTTP requests).
 *
 * Base: /wp-json/todo-hub/v1. Authenticate with a WordPress application password for a user
 * who can manage tasks (the "To-do Connector" role is made for this).
 *
 *     GET    /ping                    Check the connection.
 *     GET    /tasks                   ?status=open|done &site= &key= &source= &category=
 *     POST   /tasks                   Add (or update the open task with the same site + key).
 *     GET    /tasks/{id}
 *     PATCH  /tasks/{id}              Change the fields sent.
 *     DELETE /tasks/{id}              Move to the trash (it no longer needs doing).
 *     POST   /tasks/{id}/complete
 *     POST   /tasks/{id}/reopen
 *
 * Task fields are those of todo_hub_add(), plus site (the connected site's address) and
 * callback (a URL the hub POSTs to when a task with notify set is completed or reopened).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Hub;

use TodoHub\App;
use TodoHub\Tasks;

final class RestApi {

	public const NAMESPACE = 'todo-hub/v1';

	/** Fields a client may send when adding or changing a task. */
	private const FIELDS = array( 'title', 'notes', 'category', 'priority', 'due', 'due_time', 'source', 'key', 'link', 'notify', 'repeat', 'data', 'site', 'callback' );

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		$can = array( self::class, 'permission' );
		$id  = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'ping' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/tasks',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => $can,
					'args'                => array(
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'open', 'done' ),
							'default' => 'open',
						),
						'site'     => array( 'type' => 'string' ),
						'key'      => array( 'type' => 'string' ),
						'source'   => array( 'type' => 'string' ),
						'category' => array( 'type' => 'integer' ),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 100,
							'minimum' => 1,
							'maximum' => 500,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => $can,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'show' ),
					'permission_callback' => $can,
					'args'                => $id,
				),
				array(
					'methods'             => 'PATCH, PUT',
					'callback'            => array( self::class, 'change' ),
					'permission_callback' => $can,
					'args'                => $id,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'destroy' ),
					'permission_callback' => $can,
					'args'                => $id,
				),
			)
		);
		foreach ( array( 'complete', 'reopen' ) as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/tasks/(?P<id>\d+)/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, $action ),
					'permission_callback' => $can,
					'args'                => $id,
				)
			);
		}
	}

	public static function permission(): bool|\WP_Error {
		if ( current_user_can( Tasks::CAPABILITY ) ) {
			return true;
		}
		return new \WP_Error(
			'todo_hub_forbidden',
			__( 'Log in as a user who can manage tasks. Connected sites use an application password.', 'todo-hub' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public static function ping(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'plugin'  => 'todo-hub',
				'version' => App::version(),
				'mode'    => 'hub',
				'user'    => wp_get_current_user()->user_login,
			)
		);
	}

	public static function index( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = array_filter(
			array(
				'site'     => (string) $request->get_param( 'site' ),
				'key'      => (string) $request->get_param( 'key' ),
				'source'   => (string) $request->get_param( 'source' ),
				'category' => (int) $request->get_param( 'category' ),
			)
		);
		$limit   = (int) $request->get_param( 'per_page' );
		$tasks   = 'done' === $request->get_param( 'status' )
			? Tasks::done( $limit, $filters )
			: array_slice( Tasks::open( $filters ), 0, $limit );
		return new \WP_REST_Response( $tasks );
	}

	public static function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$args = self::fields( $request );
		$site = isset( $args['site'] ) ? Tasks::cleanSite( $args['site'] ) : '';
		$key  = isset( $args['key'] ) ? Tasks::cleanKey( $args['key'] ) : '';
		$had  = '' !== $key ? Tasks::findOpenByKey( $key, $site ) : 0;
		$id   = Tasks::add( $args );
		if ( is_wp_error( $id ) ) {
			return self::error( $id, 400 );
		}
		$response = new \WP_REST_Response( Tasks::get( $id ), $had ? 200 : 201 );
		return $response;
	}

	public static function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$task = self::task( $request );
		return is_wp_error( $task ) ? $task : new \WP_REST_Response( $task );
	}

	public static function change( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$task = self::task( $request );
		if ( is_wp_error( $task ) ) {
			return $task;
		}
		$result = Tasks::update( $task['id'], self::fields( $request ) );
		if ( is_wp_error( $result ) ) {
			return self::error( $result, 400 );
		}
		return new \WP_REST_Response( Tasks::get( $task['id'] ) );
	}

	public static function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$task = self::task( $request );
		if ( is_wp_error( $task ) ) {
			return $task;
		}
		Tasks::remove( $task['id'] );
		return new \WP_REST_Response(
			array(
				'removed' => true,
				'id'      => $task['id'],
			)
		);
	}

	public static function complete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$task = self::task( $request );
		if ( is_wp_error( $task ) ) {
			return $task;
		}
		Tasks::complete( $task['id'] );
		return new \WP_REST_Response( Tasks::get( $task['id'] ) );
	}

	public static function reopen( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$task = self::task( $request );
		if ( is_wp_error( $task ) ) {
			return $task;
		}
		Tasks::reopen( $task['id'] );
		return new \WP_REST_Response( Tasks::get( $task['id'] ) );
	}

	/** @return array<string, mixed>|\WP_Error */
	private static function task( \WP_REST_Request $request ): array|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$task = Tasks::exists( $id ) ? Tasks::get( $id ) : null;
		if ( null === $task ) {
			return new \WP_Error( 'todo_hub_not_found', __( 'That task does not exist.', 'todo-hub' ), array( 'status' => 404 ) );
		}
		return $task;
	}

	/**
	 * The task fields present in the request body (JSON or form).
	 *
	 * @return array<string, mixed>
	 */
	private static function fields( \WP_REST_Request $request ): array {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : $request->get_body_params();
		$args = array();
		foreach ( self::FIELDS as $field ) {
			if ( is_array( $body ) && array_key_exists( $field, $body ) ) {
				$args[ $field ] = $body[ $field ];
			}
		}
		if ( isset( $args['notify'] ) ) {
			$args['notify'] = rest_sanitize_boolean( $args['notify'] );
		}
		return $args;
	}

	private static function error( \WP_Error $error, int $status ): \WP_Error {
		$error->add_data( array( 'status' => $status ) );
		return $error;
	}
}
