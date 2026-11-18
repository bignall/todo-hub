<?php
/**
 * Unit tests for Ranking (plain PHP, no WordPress needed).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TodoHub\Priority;
use TodoHub\Ranking;

final class RankingTest extends TestCase {

	private \DateTimeImmutable $today;

	protected function setUp(): void {
		$this->today = new \DateTimeImmutable( '2026-11-18' );
	}

	/** @return array<string, array{string, string}> */
	public static function states(): array {
		return array(
			'no date'       => array( '', Ranking::STATE_NONE ),
			'yesterday'     => array( '2026-11-17', Ranking::STATE_OVERDUE ),
			'last year'     => array( '2025-11-18', Ranking::STATE_OVERDUE ),
			'today'         => array( '2026-11-18', Ranking::STATE_TODAY ),
			'tomorrow'      => array( '2026-11-19', Ranking::STATE_SOON ),
			'in 3 days'     => array( '2026-11-21', Ranking::STATE_SOON ),
			'in 4 days'     => array( '2026-11-22', Ranking::STATE_WEEK ),
			'in 7 days'     => array( '2026-11-25', Ranking::STATE_WEEK ),
			'in 8 days'     => array( '2026-11-26', Ranking::STATE_LATER ),
			'across a year' => array( '2027-01-02', Ranking::STATE_LATER ),
		);
	}

	#[DataProvider( 'states' )]
	public function testState( string $due, string $expected ): void {
		$this->assertSame( $expected, Ranking::state( $due, $this->today ) );
	}

	public function testTodayIgnoresTimeOfDay(): void {
		$late = new \DateTimeImmutable( '2026-11-18 23:59:59' );
		$this->assertSame( Ranking::STATE_TODAY, Ranking::state( '2026-11-18', $late ) );
		$this->assertSame( Ranking::STATE_SOON, Ranking::state( '2026-11-19', $late ) );
	}

	public function testSortOrder(): void {
		$tasks = array(
			$this->task( 1, Priority::HIGH, '' ),
			$this->task( 2, Priority::LOW, '2026-11-10' ),          // Overdue, low.
			$this->task( 3, Priority::LOW, '' ),
			$this->task( 4, Priority::NORMAL, '2026-11-18' ),       // Today.
			$this->task( 5, Priority::HIGH, '2026-11-16' ),         // Overdue, high.
			$this->task( 6, Priority::LOW, '2026-11-19' ),          // Low but tomorrow: like high.
			$this->task( 7, Priority::NORMAL, '2026-12-25' ),       // Far off: normal.
			$this->task( 8, Priority::NORMAL, '' ),
			$this->task( 9, Priority::NORMAL, '2026-11-23' ),       // This week: like high.
			$this->task( 10, Priority::HIGH, '2026-11-20' ),        // Soon and high: above everything not overdue/today.
		);

		$order = array_column( Ranking::sort( $tasks, $this->today ), 'id' );

		// Overdue (high first), today, then by raised level, earlier date first, undated last.
		$this->assertSame( array( 5, 2, 4, 10, 6, 9, 1, 7, 8, 3 ), $order );
	}

	public function testTiesGoToTheOlderTask(): void {
		$tasks = array(
			$this->task( 1, Priority::NORMAL, '', '2026-11-02 10:00:00' ),
			$this->task( 2, Priority::NORMAL, '', '2026-11-01 10:00:00' ),
			$this->task( 3, Priority::NORMAL, '', '2026-11-01 10:00:00' ),
		);
		$this->assertSame( array( 2, 3, 1 ), array_column( Ranking::sort( $tasks, $this->today ), 'id' ) );
	}

	public function testTimedTaskIsOverdueOnceItsTimePasses(): void {
		$afternoon = new \DateTimeImmutable( '2026-11-18 14:30' );
		$this->assertSame( Ranking::STATE_OVERDUE, Ranking::state( '2026-11-18', $afternoon, time: '09:00' ) );
		$this->assertSame( Ranking::STATE_TODAY, Ranking::state( '2026-11-18', $afternoon, time: '14:30' ) );
		$this->assertSame( Ranking::STATE_TODAY, Ranking::state( '2026-11-18', $afternoon, time: '17:00' ) );
		$this->assertSame( Ranking::STATE_TODAY, Ranking::state( '2026-11-18', $afternoon ) );
		$this->assertSame( Ranking::STATE_SOON, Ranking::state( '2026-11-19', $afternoon, time: '08:00' ) );
	}

	public function testSameDayTimedTasksComeFirstInTimeOrder(): void {
		$now   = new \DateTimeImmutable( '2026-11-18 08:00' );
		$tasks = array(
			$this->task( 1, Priority::NORMAL, '2026-11-20' ),
			$this->task( 2, Priority::NORMAL, '2026-11-20', time: '15:00' ),
			$this->task( 3, Priority::NORMAL, '2026-11-20', time: '09:30' ),
			$this->task( 4, Priority::NORMAL, '2026-11-18', time: '07:00' ), // Passed: overdue.
			$this->task( 5, Priority::HIGH, '2026-11-18', time: '17:00' ),   // Later today.
		);
		$this->assertSame( array( 4, 5, 3, 2, 1 ), array_column( Ranking::sort( $tasks, $now ), 'id' ) );
	}

	public function testThresholdsCanChange(): void {
		$this->assertSame( Ranking::STATE_SOON, Ranking::state( '2026-11-23', $this->today, 5, 10 ) );
		$this->assertSame( Ranking::STATE_WEEK, Ranking::state( '2026-11-28', $this->today, 5, 10 ) );
	}

	/** @return array{id: int, priority: int, due: string, created: string, due_time: string} */
	private function task( int $id, int $priority, string $due, string $created = '2026-11-01 00:00:00', string $time = '' ): array {
		$due_time = $time;
		return compact( 'id', 'priority', 'due', 'created', 'due_time' );
	}
}
