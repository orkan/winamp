<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */
use Orkan\Winamp\Application\Application;
use Orkan\Winamp\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test suite
 *
 * @author Orkan <orkans@gmail.com>
 */
class MathCommandFix03Test extends TestCase
{
	protected static $fixture = 'Fix03';

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	protected function setUp(): void
	{
		parent::setUp();

		/**
		 * Create Command
		 *
		 * Method 1: Build Application and retrieve command
		 * ------------------------------------------------
		 * $Application = new Application( self::$Factory );
		 * self::$Command = $Application->find( 'rebuild' );
		 *
		 * Method 2: Create Command and assign helpers manually
		 * ----------------------------------------------------
		 * self::$Command = new Command\RebuildCommand( self::$Factory );
		 * self::$Command->setHelperSet( new HelperSet( [ new QuestionHelper() ] ) );
		 *
		 * Helpers:
		 * QuestionHelper: required for testing "User input" - not available on Windows :(
		 */
		$Application = new Application( self::$Factory );
		self::$Command = $Application->find( 'math' );

		/**
		 * Commad + Tester
		 * @link https://symfony.com/doc/current/console.html#testing-commands
		 */
		self::$CommandTester = new CommandTester( self::$Command );

		/*
		 * Stubs aren't used since we'll use mocked playlists files instead
		 self::$Factory->stubs( 'M3UTagger', $this->createStub( self::$Factory->cfg( 'M3UTagger' ) ) );
		 self::$Factory->stubs( 'PlaylistBuilder', $this->createStub( self::$Factory->cfg( 'PlaylistBuilder' ) ) );
		 */
	}

	protected function tearDown(): void
	{
		self::removeFiles( self::getDir( 'ml' ), [ '*.bak', '*.m3u', '*.m3u8' ] );
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [pl01.m3u8][01]: Substract 2 songs (save diff with #EXTINF)
	 */
	public function testCanSubstractTwoSongs()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_01_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_01_out_ext.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['out'],

			// --------------def
			'--method' => 'sub',
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
		$this->assertFileDoesNotExist( self::$dir['ml'] . '/pl01 (1).m3u8.bak' );
	}

	/**
	 * [pl01.m3u8][01]: Substract 2 songs (do not save diff!)
	 */
	public function testCanSubstractTwoSongsDryRun()
	{
		$out = self::$dir['ml'] . '/pl01_01_out.m3u8';

		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_01_sub.m3u8',
				//'out' => self::$dir['ml'] . '/pl01_01_out.m3u8', --> do not parse!
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a'         => $data['files']['pla'],
			'b'         => $data['files']['plb'],
			'o'         => $out,
			'--dry-run' => true,

			// --------------def
			'-m'        => 'sub',
			'--no-ext'  => true,
		] );
		/* @formatter:on */

		$this->assertFileDoesNotExist( $out );
	}

	/**
	 * [pl01.m3u8][01]: Substract 2 songs
	 */
	public function testCanSubstractTwoSongsNoExt()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_01_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_01_out.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['out'],

			// --------------def
			//'--method' => 'sub', // <-- This is default method. Can also be omited
			'--no-ext' => true,
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
	}

	/**
	 * [pl01.m3u8][01]: Substract 2 songs. Overwrite Playlist A.
	 */
	public function testCanSubstractTwoSongsAndOverwriteMainPlaylist()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_01_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_01_out.m3u8', // <-- expected results
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['pla'], // <-- overwrite --pla file

			// --------------def
			'--method' => 'sub',
			'--no-ext' => true,
			//'--no-backup' => true, // <-- auto create backup!
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['pla'] ) );
		$this->assertFileExists( self::$dir['ml'] . '/pl01 (1).m3u8.bak' );
	}

	/**
	 * [pl01.m3u8][02]: Substract missing song
	 */
	public function testCanSubstractNotFound()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_02_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_02_out.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['out'],

			// --------------def
			'--method' => 'sub',
			'--no-ext' => true,
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
	}

	/**
	 * [pl01.m3u8][03]: Substract 0 song
	 */
	public function testCanSubstractZeroSong()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_03_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_03_out.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['out'],

			// --------------def
			'--no-ext' => true,
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
	}

	/**
	 * [pl01.m3u8][04]: Substract all songs
	 */
	public function testCanSubstractAllSongs()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_04_sub.m3u8',
				'out' => self::$dir['ml'] . '/pl01_04_out.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a' => $data['files']['pla'],
			'b' => $data['files']['plb'],
			'o' => $data['files']['out'],

			// --------------def
			'--no-ext' => true,
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
	}

	/**
	 * [pl01.m3u8][05]: Add 1 song. Skip 2 songs already in pla.
	 */
	public function testCanAddOneNewSongTwoOldSkip()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pla' => self::$dir['ml'] . '/pl01.m3u8',
				'plb' => self::$dir['ml'] . '/pl01_05_add.m3u8',
				'out' => self::$dir['ml'] . '/pl01_05_out.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'a'        => $data['files']['pla'],
			'b'        => $data['files']['plb'],
			'o'        => $data['files']['out'],
			'--method' => 'add',
			// --------------def
			'--no-ext' => true,
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['out'], file_get_contents( $data['files']['out'] ) );
	}

	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error:
	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Unsupported file format
	 */
	public function testExceptionThrownOnUnsuportedFormat()
	{
		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ 'a' => 'file1.m4u', 'b' => 'file2.m4u', 'o' => 'file3.m4u' ] );
	}

	/**
	 * Input file not found
	 */
	public function testExceptionThrownOnInputFileNotFound()
	{
		// Create file1. Make file2 not found.
		$file1 = self::$dir['ml'] . '/file1.m3u8';
		touch( $file1 );

		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ 'a' => $file1, 'b' => 'file2.m3u', 'o' => 'file3.m3u' ] );
	}

	/**
	 * Unsupported Math method
	 */
	public function testExceptionThrownOnUnsuportedMethod()
	{
		$file = self::$dir['ml'] . '/file.m3u8';
		touch( $file );

		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ '--method' => 'xyz', 'a' => $file, 'b' => $file, 'o' => 'file3.m3u' ] );
	}

	/**
	 * No arguments given
	 */
	public function testExceptionThrownIfNoArgs()
	{
		$this->expectException( Exception::class );
		self::$CommandTester->execute( [] );
	}
}
