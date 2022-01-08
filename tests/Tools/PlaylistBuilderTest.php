<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */
use Orkan\Winamp\Tests\TestCase;
use Orkan\Winamp\Tools\PlaylistBuilder;

/**
 * Test suite
 *
 * @author Orkan <orkans@gmail.com>
 */
class PlaylistBuilderTest extends TestCase
{
	protected static $fixture = 'Fix03';

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [pl02.m3u8]: Compute absolute track paths based on given cfg('base') path, rather than playlist location
	 */
	public function testCanFindAbsPathsFromBaseLocation()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u', // read relative paths
				'expect' => self::$dir['ml'] . '/pl02_01_abs.m3u',
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		// Use 'base' dir - success
		$Playlist = new PlaylistBuilder( $data['files']['infile'], null, [ 'base' => self::$dir['media'] ] );
		$Playlist->save(); // save with abs paths!
		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl02.m3u8]: Compute absolute track paths based on playlist location
	 */
	public function testCanNotFindAbsPathsFromPlaylistLocation()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u', // read relative paths
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		// Use playlist dir as base (default) - fail!
		$Playlist = new PlaylistBuilder( $data['files']['infile'] );
		$Playlist->load();

		/* @formatter:off */
		$this->assertSame(
			file( $data['files']['infile'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ),
			$Playlist->stats( 'missing' )['items']
		);
		/* @formatter:on */

		$Playlist->save(); // save 0 tracks
		$this->assertEmpty( trim( file_get_contents( $data['files']['infile'] ) ) );
	}

	/**
	 * [pl02.m3u8]: Save 'orig' tracks instead of absolute
	 */
	public function testCanSaveOriginalTrackEntries()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u', // read relative paths
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		$Playlist = new PlaylistBuilder( $data['files']['infile'] );
		$Playlist->save( true, false, '', 'orig' ); // save orig paths!

		$this->assertSame( $data['body']['infile'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl02.m3u8]: Do not save
	 * @see RebuildCommandFix01Test::testCanSkipSavingUnmodifiedPlaylist()
	 */
	public function testCanSkipSavingToFile()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u', // read relative paths
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		$Playlist = new PlaylistBuilder( $data['files']['infile'] );
		$resutl = $Playlist->save( false );

		$this->assertSame( 0, $resutl['bytes'] );
	}

	/**
	 * [pl01.m3u8]: Trigger onLoad event for every line in playlist file
	 */
	public function testCanRunOnloadCallbackForEachLineInPlaylistFile()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01_01_out_ext.m3u8', // has 6 lines: 3 EXTINF + 3 paths
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		// Create a Test Stub with an empty method stdClass::onLoadCallback()
		$Stub = $this->getMockBuilder( stdClass::class )->addMethods( [ 'onLoadCallback' ] )->getMock();

		/**
		 * Expect invoking it for each line in playlist file
		 * @see PlaylistBuilder::load()
		 * @formatter:off
		 */
		$Stub->expects( $this->exactly( 6 ) )->method( 'onLoadCallback' )->withConsecutive(
		//   line, lines, text                                , track?
			[   1,     6, $this->stringContains( '#EXTM3U' )  , false],
			[   2,     6, $this->stringContains( '#EXTINF' )  , false],
			[   3,     6, $this->stringContains( 'One.mp3' )  , true ], // track
			[   4,     6, $this->stringContains( '#EXTINF' )  , false],
			[   5,     6, $this->stringContains( 'Three.mp3' ), true ], // track
			[   6,     6, $this->stringContains( 'Main.mp3' ) , true ], // track
		);
		/* @formatter:on */

		// Assign mocked callback to PlaylistBuilder::load()
		$Playlist = new PlaylistBuilder( $data['files']['infile'], null, [ 'onLoad' => [ $Stub, 'onLoadCallback' ] ] );

		// trigger load() -> trigger onLoadCallback()
		$count = $Playlist->count();

		// There are 3 paths in this playlist file
		$this->assertSame( 3, $count );
	}

	/**
	 * [pl02.m3u8]: Do not trigger onLoad event for an empty playlist file
	 */
	public function testDoNotRunOnloadCallbackForAnEmptyPlaylistFile()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01_03_sub.m3u8', // empty file!
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );

		// Create a Test Stub with an empty method stdClass::onLoadCallback()
		$Stub = $this->getMockBuilder( stdClass::class )->addMethods( [ 'onLoadCallback' ] )->getMock();

		// Expect invoking it 0 times
		$Stub->expects( $this->exactly( 0 ) )->method( 'onLoadCallback' );

		// Assign mocked callback to PlaylistBuilder::load()
		$Playlist = new PlaylistBuilder( $data['files']['infile'], null, [ 'onLoad' => [ $Stub, 'onLoadCallback' ] ] );

		// trigger load() -> trigger onLoadCallback()
		$count = $Playlist->count();

		// There are 0 paths in this playlist file
		$this->assertSame( 0, $count );
	}
}
