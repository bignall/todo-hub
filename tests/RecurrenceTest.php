<?php
/**
 * Unit tests for Recurrence (plain PHP, no WordPress needed).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TodoHub\Recurrence;

final class RecurrenceTest extends TestCase {

	/** @return array<string, array{mixed, array{every: int, unit: string}|null}> */
	public static function rules(): array {
		return array(
			'weekly'         => array( 'weekly', array( 'every' => 1, 'unit' => 'week' ) ),
			'Monthly'        => array( ' Monthly ', array( 'every' => 1, 'unit' => 'month' ) ),
			'fortnightly'    => array( 'fortnightly', array( 'every' => 2, 'unit' => 'week' ) ),
			'array'          => array( array( 'every' => 3, 'unit' => 'day' ), array( 'every' => 3, 'unit' => 'day' ) ),
			'plural unit'    => array( array( 'every' => '2', 'unit' => 'Weeks' ), array( 'every' => 2, 'unit' => 'week' ) ),
			'every missing'  => array( array( 'unit' => 'year' ), array( 'every' => 1, 'unit' => 'year' ) ),
			'bad unit'       => array( array( 'every' => 1, 'unit' => 'hour' ), null ),
			'zero'           => array( array( 'every' => 0, 'unit' => 'day' ), null ),
			'unknown word'   => array( 'sometimes', null ),
			'empty'          => array( '', null ),
			'null'           => array( null, null ),
		);
	}

	#[DataProvider( 'rules' )]
	public function testParse( mixed $value, ?array $expected ): void {
		$this->assertSame( $expected, Recurrence::parse( $value ) );
	}

	/** @return array<string, array{string, string, string, string}> */
	public static function nextDates(): array {
		return array(
			// due, rule word, today, expected.
			'weekly on time'           => array( '2026-11-18', 'weekly', '2026-11-18', '2026-11-25' ),
			'weekly done early'        => array( '2026-11-20', 'weekly', '2026-11-18', '2026-11-27' ),
			'weekly done 3 weeks late' => array( '2026-10-28', 'weekly', '2026-11-18', '2026-11-25' ),
			'late lands on today'      => array( '2026-11-11', 'weekly', '2026-11-18', '2026-11-25' ),
			'daily'                    => array( '2026-11-18', 'daily', '2026-11-18', '2026-11-19' ),
			'no due date'              => array( '', 'monthly', '2026-11-18', '2026-12-18' ),
			'month end clamps'         => array( '2026-01-31', 'monthly', '2026-01-31', '2026-02-28' ),
			'month end leap year'      => array( '2028-01-31', 'monthly', '2028-01-31', '2028-02-29' ),
			'month end keeps day'      => array( '2026-01-31', 'monthly', '2026-03-01', '2026-03-31' ),
			'december to january'      => array( '2026-12-15', 'monthly', '2026-12-15', '2027-01-15' ),
			'yearly leap day'          => array( '2028-02-29', 'yearly', '2028-02-29', '2029-02-28' ),
		);
	}

	#[DataProvider( 'nextDates' )]
	public function testNext( string $due, string $word, string $today, string $expected ): void {
		$rule = Recurrence::parse( $word );
		$this->assertNotNull( $rule );
		$this->assertSame( $expected, Recurrence::next( $due, $rule, new \DateTimeImmutable( $today ) ) );
	}

	public function testEveryThreeMonths(): void {
		$rule = array(
			'every' => 3,
			'unit'  => 'month',
		);
		$this->assertSame( '2027-02-28', Recurrence::next( '2026-11-30', $rule, new \DateTimeImmutable( '2026-11-30' ) ) );
	}
}
