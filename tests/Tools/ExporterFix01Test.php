<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
use Orkan\Winamp\Tests\TestCase;
use Orkan\Winamp\Tools\Exporter;
use Orkan\Winamp\Tools\Playlist;

/**
 * Test suite
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class ExporterFix01Test extends TestCase
{
	protected static $fixture = 'Fix01';
	protected static $cfg;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		/* @formatter:off */
		self::$Factory->merge([
			'winamp_dir' => self::$dir['ml'],
			'winamp_xml' => 'playlists-1.xml', // can't use default playlists.xml in Fix01!
		], true );
		/* @formatter:on */

		// Save default config before any test might change it!
		self::$cfg = self::$Factory->cfg();
	}

	protected function setUp(): void
	{
		parent::setUp();

		// Restore default config before each test
		self::$Factory->reset( self::$cfg );
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [playlists-2.xml]: Convert cfg[extra] titles to playlists array.
	 */
	public function testCanLoadExtraPlaylists()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8',
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
			],
		];
		$cfg = [
			'winamp_xml' => 'playlists-2.xml',
			'extra' => [
				'Pl 02',
				'Pl 01',
				'Pl 02', // double name - remove
			],
		];
		$expect = [
			'Pl 02',
			'Pl 01',
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->playlistsLoad();

		$actual = [];
		foreach ( $Exporter->playlists() as $Playlist ) {
			$actual[] = $Playlist->pl['name'];
		}

		$this->assertSame( $expect, $actual );
	}

	/**
	 * [playlists-1.xml]: Import cfg[playlists] to Playlist[].
	 */
	public function testCanBuildCfgPlaylists()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8',
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
				'pl03' => self::$dir['ml'] . '/playlists_03.m3u8',
			],
		];
		$cfg = [
			'total_size' => 0,
			'playlists'  => [
				[ 'name' => 'Pl 01', 'file' => 'playlists_01.m3u8' ],
				[ 'name' => 'Pl 02', 'file' => 'playlists_02.m3u8' ],
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->playlistsLoad();

		$actual = $Exporter->playlists();
		$this->assertSame( count( $cfg['playlists'] ), count( $actual ) );
		$this->assertInstanceOf( Playlist::class, current( $actual ) );
	}

	/**
	 * [playlists-1.xml]: Use total limit.
	 */
	public function testCanUseTotalLimit()
	{
		/* @formatter:off */
		$cfg = [
			'total_size' => 50000,
			'auto_dirs'  => true,
			'output_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'output_dir' ),
			'playlists'  => [
				[ 'name' => 'Pl 01', 'file' => 'playlists_01.m3u8', 'shuffle' => true ],
				[ 'name' => 'Pl 02', 'file' => 'playlists_02.m3u8', 'shuffle' => true ],
			],
		];
		$data = [
			'files' => [
				/*
				 * [0] => #Audio-2sec.mp3 | 22153 bytes | 22153 bytes
				 */
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8',
				/*
				 * [0] => 4udio1-2sec.mp3 | 18754 bytes | 40907 bytes
				 * [1] => Audio2-3sec.mp3 | 20782 bytes | 61689 bytes
				 */
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertLessThanOrEqual( $cfg['total_size'], $stats['bytes'] );
	}

	/**
	 * [playlists-1.xml]: Use playlist limit.
	 */
	public function testCanUsePlaylistLimit()
	{
		/* @formatter:off */
		$cfg = [
			'total_size' => 0,
			'auto_dirs'  => true,
			'output_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'output_dir' ),
			'playlists'  => [
				[ 'name' => 'Pl 01', 'file' => 'playlists_01.m3u8', 'limit' => 1 ],
				[ 'name' => 'Pl 02', 'file' => 'playlists_02.m3u8', 'limit' => 1 ],
			],
		];
		$data = [
			'files' => [
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8', // 1 track
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8', // 2 tracks
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 2, $stats['items'] );
	}

	/**
	 * [playlists-1.xml]: Mark all dupes.
	 */
	public function testCanMarkDupes()
	{
		/* @formatter:off */
		$cfg = [
			'total_size' => 0,
			'auto_dirs'  => true,
			'output_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'output_dir' ),
			'playlists'  => [
				[ 'name' => 'Pl 01', 'file' => 'playlists_01.m3u8' ], // <-- doubled
			],
			'extra' => [
				'Pl 01', // <-- doubled
				'Pl 03',
			],
		];
		$data = [
			'files' => [
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8', // #Audio-2sec.mp3 <-- doubled
				'pl03' => self::$dir['ml'] . '/playlists_03.m3u8', // Foreign-2sec.mp3
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 2, $stats['items'] );
		$this->assertSame( 1, $stats['dupes'] );
	}

	/**
	 * [playlists-1.xml]: Skip exporting same files.
	 */
	public function testCanManifestSkipFiles()
	{
		/* @formatter:off */
		$cfg = [
			'manifest'   => 'manifest.json',
			'total_size' => 0,
			'auto_dirs'  => true,
			'output_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'output_dir' ),
			'export_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'export_dir' ),
			'extra' => [
				'Pl 02',
			],
		];
		$data = [
			'files' => [
				/*
				 * [0] => 4udio1-2sec.mp3 | 18754 bytes
				 * [1] => Audio2-3sec.mp3 | 20782 bytes
				 */
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		// Prepare:
		// Copy all 2 music files, update mtime to match [src] <-> [dst]
		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 2, $stats['items'] );

		// Get the list of [src] <=> [dst] pairs from manifest file
		$manifest = self::$Factory->get( 'export_dir' ) . '/' . self::$Factory->get( 'manifest' );
		$manifest = json_decode( file_get_contents( $manifest ), true );
		$manifest = array_values( $manifest ); // remove custom keys

		// Check 1: Don't change [dst] and check if nothing is marked to copy over
		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 0, $stats['items'], 'Unmodified [src] == [dst]' );

		// Check 2: Remove first [dst] file and see if it was marked to copy again
		unlink( $manifest[0]['dst'] );
		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 1, $stats['items'], 'Remove first [dst] file' );

		// Check 3: Touch second [src] file and see if it was marked to copy again
		touch( $manifest[1]['src'], time() - 3600 );
		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 1, $stats['items'], 'Touch second [src] file -1h' );

		// Check 4: Resize first [src] file and see if it was marked to copy again
		copy( $manifest[1]['src'], $manifest[0]['src'] );
		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
		$stats = $Exporter->stats();
		$this->assertSame( 1, $stats['items'], 'Resize first [src] file' );
	}

	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error:
	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [Error] __construct(): Can't find Winamp ML dir.
	 */
	public function testExceptionThrownOnMissingWinampMlDir()
	{
		$this->expectExceptionMessage( 'winamp_dir' );

		self::$Factory->cfg( 'winamp_dir', '/test/' . __METHOD__ );
		new Exporter( self::$Factory );
	}

	/**
	 * [Error] plsFromNames(): Unable to locate playlist name: "Missing A", "Missing B"
	 */
	public function testExceptionThrownOnMissingPlaylistName()
	{
		$this->expectExceptionMessage( 'Missing A' );
		$this->expectExceptionMessage( 'Missing B' );

		/* @formatter:off */
		$data = [
			'files' => [
				'pl01' => self::$dir['ml'] . '/playlists_01.m3u8',
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
			],
		];
		$cfg = [
			'winamp_xml' => 'playlists-2.xml',
			'extra' => [
				'Pl 01',
				'Pl 02',
				'Missing A', // <-- exception!
				'Missing B', // <-- exception!
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->playlistsLoad();
	}

	/**
	 * [Error] playlist(): Missing track "". Run "[Rebuild] Winamp ML" first!
	 */
	public function testExceptionThrownOnMissingPlaylistTrack()
	{
		$this->expectExceptionMessage( 'Missing track' );

		/* @formatter:off */
		$cfg = [
			'total_size' => 0,
			'auto_dirs'  => true,
			'output_dir' => sprintf( '%s/%s/%s', self::$dir['sandbox'], __FUNCTION__, 'output_dir' ),
			'extra' => [
				'Pl 02',
			],
		];
		$data = [
			'files' => [
				'pl02' => self::$dir['ml'] . '/playlists_02.m3u8',
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		self::$Factory->merge( $cfg, true );

		$Playlist = self::$Factory->Playlist( [ 'file' => $data['files']['pl02'] ] );
		$paths = $Playlist->paths();
		unlink( current( $paths ) );

		$Exporter = new Exporter( self::$Factory );
		$Exporter->run();
	}
}
