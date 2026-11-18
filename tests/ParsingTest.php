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

	public function testDaysUntil(): void {
		$today = new \DateTimeImmutable( '2026-11-18 22:00', new \DateTimeZone( 'America/Denver' ) );
		$this->assertSame( 0, DueDate::daysUntil( '2026-11-18', $today ) );
		$this->assertSame( -18, DueDate::daysUntil( '2026-10-31', $today ) );
		$this->assertSame( 13, DueDate::daysUntil( '2026-12-01', $today ) );
	}
}
