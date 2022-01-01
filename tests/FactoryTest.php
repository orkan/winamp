<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */

/**
 * Test suite
 *
 * @author Orkan <orkans@gmail.com>
 */
use Orkan\Winamp\Factory;
use Orkan\Winamp\Tests\TestCase;

class FactoryTest extends TestCase
{
	protected static $fixture = 'Fix02';
	protected static $argv;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$argv = $_SERVER['argv'];
	}

	protected function tearDown(): void
	{
		$_SERVER['argv'] = self::$argv;
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Options: -u
	 */
	public function testCanLoadUserCfgViaShortOption()
	{
		$_SERVER['argv'][] = '-u';
		$_SERVER['argv'][] = $usr_file = self::$dir['sandbox'] . '/config.php';

		$Factory = new Factory();

		$usr = require $usr_file;
		foreach ( $usr as $key => $val ) {
			$this->assertSame( $val, $Factory->cfg( $key ) );
		}
	}

	/**
	 * Options: --user-cfg
	 */
	public function testCanLoadUserCfgViaLongOption()
	{
		$_SERVER['argv'][] = '--user-cfg';
		$_SERVER['argv'][] = $usr_file = self::$dir['sandbox'] . '/config.php';

		$Factory = new Factory();

		$usr = require $usr_file;
		foreach ( $usr as $key => $val ) {
			$this->assertSame( $val, $Factory->cfg( $key ) );
		}
	}

	/**
	 */
	public function testCanLoadCfgViaConstructor()
	{
		$Factory = new Factory( $cfg = [ 'val1' => 'Val 1', 'val2' => 2 ] );
		$this->assertSame( $cfg, $Factory->cfg() );
	}

	/**
	 */
	public function testCanLoadCfgMerged()
	{
		$_SERVER['argv'][] = '--user-cfg';
		$_SERVER['argv'][] = $usr_file = self::$dir['sandbox'] . '/config.php';

		$cfg = [ 'val1' => 'Val 1', 'val2' => 2 ];
		$usr = require $usr_file;
		$expected = array_merge( $cfg, $usr );

		$Factory = new Factory( $cfg );
		$this->assertSame( $expected, $Factory->cfg() );
	}

	/**
	 */
	public function testCanLoadMissingCfgValue()
	{
		$Factory = new Factory();
		$this->assertSame( '', $Factory->cfg( 'not exist' ) );
	}
}
