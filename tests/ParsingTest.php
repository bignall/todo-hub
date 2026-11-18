<?php
/**
 * Unit tests for Priority and DueDate parsing (plain PHP, no WordPress needed).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Tests;

use PHPUnit\Framework\TestCase;
use TodoHub\DueDate;
use TodoHub\Priority;

final class ParsingTest extends TestCase {

	public function testPriorityNamesAndNumbers(): void {
		$this->assertSame( Priority::HIGH, Priority::parse( 'High' ) );
		$this->assertSame( Priority::LOW, Priority::parse( 'low' ) );
		$this->assertSame( Priority::NORMAL, Priority::parse( 'medium' ) );
		$this->assertSame( Priority::HIGH, Priority::parse( 3 ) );
		$this->assertSame( Priority::LOW, Priority::parse( '1' ) );
		$this->assertSame( Priority::NORMAL, Priority::parse( 7 ) );
		$this->assertSame( Priority::NORMAL, Priority::parse( 'urgent' ) );
		$this->assertSame( Priority::NORMAL, Priority::parse( null ) );
		$this->assertSame( 'high', Priority::name( Priority::HIGH ) );
	}

	public function testDueDates(): void {
		$tz    = new \DateTimeZone( 'America/Denver' );
		$today = new \DateTimeImmutable( '2026-11-18', $tz ); // A Wednesday.

		$this->assertSame( '2026-11-30', DueDate::parse( '2026-11-30', $tz, $today ) );
		$this->assertSame( '', DueDate::parse( '2026-02-30', $tz, $today ) );
		$this->assertSame( '', DueDate::parse( '', $tz, $today ) );
		$this->assertSame( '', DueDate::parse( 'not a date at all', $tz, $today ) );
		$this->assertSame( '', DueDate::parse( null, $tz, $today ) );
		$this->assertSame( '2026-11-19', DueDate::parse( 'tomorrow', $tz, $today ) );
		$this->assertSame( '2026-11-20', DueDate::parse( 'next friday', $tz, $today ) );
		$this->assertSame( '2026-12-01', DueDate::parse( new \DateTimeImmutable( '2026-12-01 23:00' ), $tz, $today ) );
		// 2026-11-19 03:00 UTC is still the 18th in Denver.
		$this->assertSame( '2026-11-18', DueDate::parse( 1795057200, $tz, $today ) );
	}

	public function testDueDatesWithTimes(): void {
		$tz    = new \DateTimeZone( 'America/Denver' );
		$today = new \DateTimeImmutable( '2026-11-18 16:45', $tz );
		$both  = static fn ( string $date, string $time ): array => array(
			'date' => $date,
			'time' => $time,
		);

		$this->assertSame( $both( '2026-12-01', '14:30' ), DueDate::parseWithTime( '2026-12-01 14:30', $tz, $today ) );
		$this->assertSame( $both( '2026-11-19', '15:00' ), DueDate::parseWithTime( 'tomorrow 3pm', $tz, $today ) );
		$this->assertSame( $both( '2026-11-19', '' ), DueDate::parseWithTime( 'tomorrow', $tz, $today ) );
		$this->assertSame( $both( '2026-12-01', '' ), DueDate::parseWithTime( '2026-12-01', $tz, $today ) );
		$this->assertSame( $both( '2026-12-01', '' ), DueDate::parseWithTime( new \DateTimeImmutable( '2026-12-01 00:00' ), $tz, $today ) );
		$this->assertSame( $both( '2026-12-01', '09:15' ), DueDate::parseWithTime( new \DateTimeImmutable( '2026-12-01 09:15:40' ), $tz, $today ) );
		$this->assertSame( $both( '', '' ), DueDate::parseWithTime( 'whenever', $tz, $today ) );
	}

	public function testTimes(): void {
		$this->assertSame( '14:30', DueDate::parseTime( '14:30' ) );
		$this->assertSame( '14:30', DueDate::parseTime( '14:30:00' ) );
		$this->assertSame( '09:05', DueDate::parseTime( '9:05' ) );
		$this->assertSame( '14:30', DueDate::parseTime( '2:30 PM' ) );
		$this->assertSame( '09:00', DueDate::parseTime( '9am' ) );
		$this->assertSame( '00:00', DueDate::parseTime( '12am' ) );
		$this->assertSame( '12:00', DueDate::parseTime( '12 p.m.' ) );
		$this->assertSame( '', DueDate::parseTime( '9' ) );
		$this->assertSame( '', DueDate::parseTime( '25:00' ) );
		$this->assertSame( '', DueDate::parseTime( '13pm' ) );
		$this->assertSame( '', DueDate::parseTime( '' ) );
		$this->assertSame( '', DueDate::parseTime( null ) );
	}

	public function testDaysUntil(): void {
		$today = new \DateTimeImmutable( '2026-11-18 22:00', new \DateTimeZone( 'America/Denver' ) );
		$this->assertSame( 0, DueDate::daysUntil( '2026-11-18', $today ) );
		$this->assertSame( -18, DueDate::daysUntil( '2026-10-31', $today ) );
		$this->assertSame( 13, DueDate::daysUntil( '2026-12-01', $today ) );
	}
}
