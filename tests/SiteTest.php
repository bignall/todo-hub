<?php
/**
 * Unit tests for site addresses and keys (plain PHP, no WordPress needed).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Tests;

use PHPUnit\Framework\TestCase;
use TodoHub\Tasks;

final class SiteTest extends TestCase {

	public function testSiteAddresses(): void {
		$this->assertSame( 'rosinabignall.com', Tasks::cleanSite( 'https://rosinabignall.com/' ) );
		$this->assertSame( 'example.com/blog', Tasks::cleanSite( 'http://Example.com/blog/' ) );
		$this->assertSame( 'localhost:8891', Tasks::cleanSite( 'http://localhost:8891' ) );
		$this->assertSame( 'evil.comscriptx', Tasks::cleanSite( 'evil.com<script>x' ) );
		$this->assertSame( '', Tasks::cleanSite( array( 'x' ) ) );
	}

	public function testKeys(): void {
		$this->assertSame( 'where-to-buy:approve-queue', Tasks::cleanKey( 'where-to-buy:approve-queue' ) );
		$this->assertSame( 'a/b.c_dx', Tasks::cleanKey( 'a/b.c_d <x>' ) );
		$this->assertSame( 191, strlen( Tasks::cleanKey( str_repeat( 'k', 300 ) ) ) );
	}
}
