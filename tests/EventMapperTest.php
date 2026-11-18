<?php
/**
 * Unit tests for EventMapper (plain PHP, no WordPress needed).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Tests;

use PHPUnit\Framework\TestCase;
use TodoHub\Google\EventMapper;

final class EventMapperTest extends TestCase {

	/** @return array{id: int, title: string, notes: string, status: string, due: string, due_time: string, link: string, edit_url: string} */
	private function task( array $over = array() ): array {
		return $over + array(
			'id'       => 7,
			'title'    => 'Renew SSL',
			'notes'    => '<ul><li>Amazon: wrong domain</li><li>Chewy: not a full address</li></ul>',
			'status'   => 'open',
			'due'      => '2026-11-30',
			'due_time' => '',
			'link'     => 'https://example.com/wp-admin/post.php?post=3',
			'edit_url' => 'https://todo.example.com/wp-admin/post.php?post=7&action=edit',
		);
	}

	public function testNoDueDateNoEvent(): void {
		$this->assertNull( EventMapper::event( $this->task( array( 'due' => '' ) ), 'America/Denver' ) );
	}

	public function testAllDay(): void {
		$e = EventMapper::event( $this->task(), 'America/Denver' );
		$this->assertSame( array( 'date' => '2026-11-30' ), $e['start'] );
		$this->assertSame( array( 'date' => '2026-12-01' ), $e['end'] );
		$this->assertSame( 'Renew SSL', $e['summary'] );
		$this->assertSame( 'opaque', $e['transparency'] );
		$this->assertSame( "Amazon: wrong domain\nChewy: not a full address\n\nGo to it: https://example.com/wp-admin/post.php?post=3\n\nTask: https://todo.example.com/wp-admin/post.php?post=7&action=edit", $e['description'] );
	}

	public function testTimedInNamedZone(): void {
		$e = EventMapper::event( $this->task( array( 'due_time' => '14:30' ) ), 'America/Denver', 45 );
		$this->assertSame( array( 'dateTime' => '2026-11-30T14:30:00-07:00', 'timeZone' => 'America/Denver' ), $e['start'] );
		$this->assertSame( array( 'dateTime' => '2026-11-30T15:15:00-07:00', 'timeZone' => 'America/Denver' ), $e['end'] );
	}

	public function testTimedWithOffsetZone(): void {
		$e = EventMapper::event( $this->task( array( 'due_time' => '23:45' ) ), '+02:00', 30 );
		$this->assertSame( array( 'dateTime' => '2026-11-30T23:45:00+02:00' ), $e['start'] );
		$this->assertSame( array( 'dateTime' => '2026-12-01T00:15:00+02:00' ), $e['end'] );
	}

	public function testDoneIsMarkedAndFree(): void {
		$e = EventMapper::event( $this->task( array( 'status' => 'done' ) ), 'UTC' );
		$this->assertSame( '✓ Renew SSL', $e['summary'] );
		$this->assertSame( 'transparent', $e['transparency'] );
	}

	public function testEventIdsAreValidAndStable(): void {
		$id = EventMapper::eventId( 'todo.example.com', 42 );
		$this->assertMatchesRegularExpression( '/^[a-v0-9]{5,1024}$/', $id );
		$this->assertSame( $id, EventMapper::eventId( 'todo.example.com', 42 ) );
		$this->assertNotSame( $id, EventMapper::eventId( 'other.example.com', 42 ) );
		$this->assertNotSame( $id, EventMapper::eventId( 'todo.example.com', 43 ) );
	}
}
