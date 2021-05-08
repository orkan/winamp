<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Tests;

use Orkan\Utils;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Finder\Finder;

/**
 * Base class for all Winamp tests
 *
 * @author Orkan <orkans@gmail.com>
 */
class TestCase extends BaseTestCase
{
	/**
	 * The Command under test
	 *
	 * @var \Symfony\Component\Console\Command\Command
	 */
	protected static $Command;

	/**
	 * CommandTester - Eases the testing of console commands.
	 *
	 * @var \Symfony\Component\Console\Tester\CommandTester
	 * @link https://github.com/symfony/symfony/blob/5.x/src/Symfony/Component/Console/Tester/CommandTester.php
	 */
	protected static $CommandTester;

	/**
	 * Static properties set in static::setUpBeforeClass()
	 */
	protected static $Factory;
	protected static $Logger;
	protected static $count = 0;
	protected static $series;

	/**
	 * Various locations for this suite
	 */
	protected static $testsDir = __DIR__;
	protected static $dir = [];

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public static function setUpBeforeClass(): void
	{
		if ( !isset( static::$fixture ) ) {
			throw new \RuntimeException( 'Each test suite must set the fixture dirname in $fixture property' );
		}

		/* @formatter:off */
		self::$dir = [
			'fixtures' => self::$testsDir . '/_fixtures',
			'fixture'  => self::$testsDir . '/_fixtures/' . static::$fixture,
			'sandbox'  => self::$testsDir . '/_sandbox',
			'ml'       => self::$testsDir . '/_sandbox/ml',
			'media'    => self::$testsDir . '/_sandbox/media',
		];
		self::$Factory = new FactoryMock([
			'test_class'       => basename( static::class ),
			'winamp_playlists' => self::$dir['ml'] . '/playlists.xml',
		]);
		/* @formatter:on */

		self::clearDirectory( self::$dir['sandbox'] );
		self::copyDirectory( self::$dir['fixture'], self::$dir['sandbox'] );

		/*
		 * Logger
		 * Instead of mocking Logger class, use pre-configured Logger instance for tests purposes.
		 * Each test suite has its own "Command under test" log file saved in tests/_log dir.
		 */
		self::$Logger = self::$Factory->logger();

		date_default_timezone_set( self::$Factory->cfg( 'log_timezone' ) );
		self::$series = date( 'His' );

		self::$Logger->alert( '______________________________[ SERIES ' . self::$series . ' ]______________________________' );
		self::$Logger->alert( 'Fixture: ' . static::$fixture );
	}

	protected function setUp(): void
	{
		self::$Logger->alert( '' );
		self::$Logger->alert( sprintf( '>>> TEST %s.%02d - %s(): ', self::$series, ++self::$count, $this->getName() ) );
	}

	protected function tearDown(): void
	{
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Methods Methods Methods Methods Methods Methods Methods Methods Methods Methods Methods Methods Methods Methods
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	protected static function removeFiles( string $dir, array $files )
	{
		foreach ( ( new Finder() )->name( $files )->in( $dir ) as $file ) {
			unlink( $file );
		}
	}

	protected static function remove( $files )
	{
		if ( !is_array( $files ) ) {
			$files = (array) $files;
		}

		$result = true;

		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				$result &= Utils::removeDirectory( $file );
			}

			if ( file_exists( $file ) ) {
				$result &= unlink( $file );
			}

			if ( false === $result ) {
				throw new \RuntimeException( 'Failed removing: ' . $file );
			}
		}

		return $result;
	}

	protected static function clearDirectory( $directory )
	{
		Utils::removeDirectory( $directory );
		clearstatcache( true );
		mkdir( $directory, 0777, true );
	}

	protected static function copyDirectory( $source, $destination )
	{
		Utils::copyDirectory( $source, $destination );
		clearstatcache( true );
	}

	/**
	 * Parse php scripts with given variables and save parsed results.
	 * For 'files' use file names without *.php ext
	 *
	 * $data[]: Array (
	 *   'files' => [ 'key1' => 'file1', 'key2' => 'file2' ] --> files to parse (without php ext)
	 *   'body'  => [ 'key1' => 'body1', 'key2' => 'body2' ] --> parsed contents of php files
	 * )
	 *
	 * @param array $data
	 */
	protected static function parseFiles( array &$data )
	{
		$data['body'] = [];
		foreach ( $data['files'] as $key => $file ) {
			$data['body'][$key] = self::parseFile( $file . '.php' );
			file_put_contents( $file, $data['body'][$key] );
		}
	}

	/**
	 * Extract $vars in php file and return parsed contents
	 * @see \Orkan\Winamp\Tests\TestCase::$dirs
	 */
	protected static function parseFile( string $file ): string
	{
		// Use extract() to suppress claiming about unused variables
		extract( [ 'dir_sandbox' => realpath( self::$dir['sandbox'] ) ] );

		ob_start();
		require $file;
		return ob_get_clean();
	}

	/**
	 * Reset parsed file to initial state ie. after Console command execution
	 *
	 * @param array $data
	 * @param string $key
	 */
	protected static function revertParsedFile( array &$data, string $key )
	{
		file_put_contents( $data['files'][$key], $data['body'][$key] );
	}
}
