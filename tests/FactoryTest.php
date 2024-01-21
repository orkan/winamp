<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
use Orkan\Winamp\Factory;
use Orkan\Winamp\Tests\TestCase;

/**
 * Test Orkan\Winamp\Factory.
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
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
		/* @formatter:off */
		$cfg = [
			'val1' => 'Val 1',
			'val2' => 2,
		];
		/* @formatter:on */

		$Factory = new Factory( $cfg );

		$this->assertSame( $cfg['val1'], $Factory->get( 'val1' ) );
		$this->assertSame( $cfg['val2'], $Factory->get( 'val2' ) );
	}

	/**
	 */
	public function testCanLoadCfgMerged()
	{
		/* @formatter:off */
		$cfg = [
			'val1' => 'Val 1',
			'val2' => 2,
		];
		/* @formatter:on */

		$_SERVER['argv'][] = '--user-cfg';
		$_SERVER['argv'][] = $usr_file = self::$dir['sandbox'] . '/config.php';

		$Factory = new Factory( $cfg );

		$usr = require $usr_file;
		$expect = array_merge( $cfg, $usr );
		foreach ( $expect as $k => $v ) {
			$this->assertSame( $v, $Factory->get( $k ) );
			$this->assertSame( $v, $Factory->cfg( $k ) );
		}
	}

	/**
	 */
	public function testCanLoadMissingCfgValue()
	{
		$Factory = new Factory();
		$this->assertNull( $Factory->cfg( 'not exist' ) );
	}
}
