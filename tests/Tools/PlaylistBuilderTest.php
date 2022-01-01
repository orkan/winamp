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
}
