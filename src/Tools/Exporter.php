<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2023 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tools;

use Orkan\Winamp\Application;
use Orkan\Winamp\Factory;

/**
 * Export Winamp ML.
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class Exporter extends \Orkan\Application
{
	const APP_NAME = 'Winamp Export Media Library';
	const APP_VERSION = '6.0.0';
	const APP_DATE = 'Sat, 06 Apr 2024 14:58:53 +02:00';

	/**
	 * @link https://patorjk.com/software/taag/#p=display&v=0&f=Ivrit&t=CLI%20App
	 * @link Utils\usr\php\logo\logo.php
	 */
	const LOGO = ' __      __.__                                       ___________                             __
/  \\    /  \\__| ____ _____    _____ ______           \\_   _____/__  _________   ____________/  |_
\\   \\/\\/   /  |/    \\\\__  \\  /     \\\\____ \\   ______  |    __)_\\  \\/  /\\____ \\ /  _ \\_  __ \\   __\\
 \\        /|  |   |  \\/ __ \\|  Y Y  \\  |_> > /_____/  |        \\>    < |  |_> >  <_> )  | \\/|  |
  \\__/\\  / |__|___|  (____  /__|_|  /   __/          /_______  /__/\\_ \\|   __/ \\____/|__|   |__|
       \\/          \\/     \\/      \\/|__|                     \\/      \\/|__|';

	/**
	 * Copy tracks to cfg[export_dir]?
	 * @var bool
	 */
	protected $isExport;

	/**
	 * Loaded plylists from cfg.
	 * @var Playlist[]
	 */
	protected $playlists = [];

	/**
	 * Special playlist to store all exported tracks.
	 *
	 * Properties:
	 * array PlaylistAll::pl    Default playlist definition
	 * array PlaylistAll::bytes Size of each track Array( [id] => size )
	 *
	 * @see Exporter::__construct()
	 * @see Exporter::playlist()
	 *
	 * @var Playlist
	 */
	protected $PlaylistAll;

	/**
	 * Progress start time.
	 * @var float
	 */
	protected $start;

	/* @formatter:off */

	/**
	 * List of newly copied files.
	 *
	 * Save this list in output folder for Playlists & Music to keep track of modified files in next exporter run.
	 * The [dst] points to exported file and is requred.
	 * The [src] points to source file and is optional eg. for auto generated playlists there's no source file.
	 * The [id] must always match in [old] <-> [new] manifests to help identify the same entry!
	 *
	 * @see Exporter::playlist()
	 * @see Exporter::manifestUnlink()
	 *
	 * @var array
	 */
	protected $manifest = [
		'output' => [], // [id] => Array( [dst] => pl01.m3u ), [id] => Array( [dst] => pl02.m3u ), ...
		'export' => [], // [id] => Array( [dst] => Music/au01.mp3, [src] => D:\Music\au01.mp3 ), [id] => Array( ... )
	];

	/**
	 * Collected track statistics.
	 *
	 * Build:
	 * @see Exporter::playlist()
	 * Reduce:
	 * @see Exporter::manifestUnlink()
	 * Use:
	 * @see Exporter::progress()
	 *
	 * @var array
	 */
	protected $stats = [
		'item'  => 0, // current track
		'items' => 0, // total items
		'bytes' => 0, // total bytes
		'asize' => 0, // average track size
		'dupes' => 0, // same tracks repeated on different playlists
	];

	/* @formatter:on */

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory->merge( self::defaults() );
		parent::__construct( $Factory );

	/**
	 * WARNING:
	 * User config loaded from:
	 * @see \Orkan\Winamp\Factory::__construct()
	 $this->loadUserConfig( 'config' );
	 */
	}

	/**
	 * Get defaults.
	 */
	private function defaults(): array
	{
		/**
		 * [cmd_title]
		 * CMD window title
		 * @see \Orkan\Application::cmdTitle()
		 *
		 * [winamp_xml]
		 * Winamp playlists XML file name (def. playlists.xml)
		 *
		 * [playlist_all]
		 * Playlist file name holding all tracks from all playlists.
		 *
		 * [manifest]
		 * File holding list of all files created by previus run to make diff lists
		 *
		 * [auto_dirs]
		 * Auto create paths: output_dir, export_dir
		 *
		 * [total_size]
		 * Total size limit (eg. 44444, 120M, 3.4G)
		 * Tip: Use 0 to set no limit
		 * Tip: Prepend '?' or use empty string for user prompt
		 *
		 * [total_size_str]
		 * String representation of cfg[total_size] or "no limit" if 0
		 *
		 * [winamp_dir]
		 * Path to Winamp ML dir
		 *
		 * [output_dir]
		 * Output dir for Playlists
		 * Tip: Prepend '?' or use empty string for user prompt
		 *
		 * [export_dir]
		 * Output dir for Music
		 * Tip: Use empty string to disable relocating files
		 * Tip: Prepend '?' for user prompt
		 *
		 * [export_map]
		 * Apply path mapping in playlist entries: [export_dir]/{last_dir/track} --> [export_map]/{last_dir/track}
		 *
		 * [export_sep]
		 * Path mapping directory separator
		 *
		 * [playlists]
		 * Array(
		 *   [
		 *     'file'    => 'plf_FAV.m3u8', // Input playlist name
		 *     'name'    => 'Ulubione',     // Output playlist name
		 *     'save'    => 4,              // How many copies? Eg. Ulubione1.m3u8, Ulubione2.m3u8, etc...
		 *     'limit'   => 100,            // Limit playlist entries?
		 *     'shuffle' => true,           // Shuffle playlist before applying [limit]?
		 *   ],
		 * )
		 *
		 * [extra]
		 * Extra playlists to add AS-IS without limit restrictions (unconditionally)
		 * Array( "Title 1", "Title 2", ... )
		 *
		 * @formatter:off */
		return [
			'cmd_title'       => 'Winamp Export',
			'app_usage'       => 'export.php [options]',
			'app_opts'    => [
				'config' => [ 'short' => 'c:', 'long' => 'config:', 'desc' => 'Configuration file' ],
			],
			'log_reset'       => true,
			'winamp_xml'      => 'playlists.xml',
			'manifest'        => 'export.json',
			'playlist_all'    => 'Export',
			'auto_dirs'       => false,
			'total_bytes'     => '',
			'total_size_str'  => '',
			'winamp_dir'      => '',
			'output_dir'      => '',
			'export_dir'      => '',
			'export_map'      => '',
			'export_sep'      => '/',
			'playlists'       => [],
			'extra'           => [],
			'bar_loading'     => '- loading [%bar%] %current%/%max% %message%',
			'bar_adding'      => '- adding [%bar%] %current%/%max% %message%',
			'bar_analyzing'   => '- analyze [%bar%] %current%/%max% %message%',
			'bar_exporting'   => '- copying [%bar%] %current%/%max% %message%',
		];
		/* @formatter:on */
	}

	/**
	 * Get info about collected tracks that going to be exported.
	 */
	public function stats(): array
	{
		return $this->stats;
	}

	/**
	 * Copy tracks progress info.
	 */
	protected function progress(): array
	{
		/* @formatter:off */
		$out = [
			'byte_done' => 0,   // elapsed: bytes
			'cent_done' => 0,   // progress:  %
			'cent_left' => 100, // remaining: %
			'time_exec' => 0,   // elapsed: seconds
			'time_done' => 0,   // progress:  seconds
			'time_left' => 0,   // remaining: seconds
			'speed_bps' => 0,   // average: bytes / sec
		];
		/* @formatter:on */

		// No tracks loaded yet
		if ( !$this->stats['items'] ) {
			return $out;
		}

		// Dont overflow!
		$this->stats['item'] = min( $this->stats['item'] + 1, $this->stats['items'] );

		$out['byte_done'] = $this->stats['asize'] * $this->stats['item'];
		$out['cent_done'] = 100 / $this->stats['bytes'] * $out['byte_done'];
		$out['cent_left'] = 100 - $out['cent_done'];

		$out['time_exec'] = $this->Utils->exectime( $this->start );
		$out['time_done'] = $out['time_exec'] / $out['cent_done'];
		$out['time_left'] = $out['time_done'] * $out['cent_left'];

		$out['speed_bps'] = $out['byte_done'] / $out['time_exec'];

		return $out;
	}

	/**
	 * Create Playlist object from playlist array.
	 * - use pl[shuffle] and pl[limit]
	 * - validate tracks
	 *
	 * Two separate data counters are initialized and later checked for data integrity:
	 * @see Exporter::$PlaylistAll
	 * @see Exporter::$progress
	 *
	 * @throws \RuntimeException On Missing playlist or track file
	 *
	 * @param  array $pl cfg[playlists] definition
	 */
	protected function playlist( array $pl ): ?Playlist
	{
		$pl = $this->plNormalize( $pl );
		$total = $pl['istotal'] ? $this->Factory->get( 'total_bytes' ) : 0;

		$m3u = $this->Factory->get( 'winamp_dir' ) . '/' . $pl['file'];
		$exp = $this->Factory->get( 'export_dir' );
		$map = $this->Factory->get( 'export_map' );
		$sep = $this->Factory->get( 'export_sep' );

		$this->Factory->info();
		$this->Factory->notice( 'Playlist: [{name}] "{file}"', [ '{name}' => $pl['name'], '{file}' => $m3u ] );
		$this->Logger->debug( $this->Utils->phpMemoryMax() );

		if ( !is_file( $m3u ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unable to locate playlist file: "%s"', $m3u ) );
		}

		// -------------------------------------------------------------------------------------------------------------
		// Total size 1/2:
		// Don't load another Playlist if there's no space left in given total limit
		$bytes = $this->stats['bytes'] + $this->stats['asize'];
		if ( $total && $bytes > $total ) {
			/* @formatter:off */
			$this->Factory->info( '- skipped! Less than {bytes} left (total size limit: {total})', [
				'{bytes}' => $this->Utils->byteString( $this->stats['asize'] ),
				'{total}' => $this->Utils->byteString( $total ),
			]);
			/* @formatter:on */
			return null;
		}

		// -------------------------------------------------------------------------------------------------------------
		// New Playlist
		$Playlist = $this->Factory->Playlist( [ 'file' => $m3u, 'onLoad' => function ( $current, $count, $line, $item ) {
			if ( 1 === $current ) {
				$this->Factory->barNew( $count, 'bar_loading' ); // count == lines, not tracks!
			}
			if ( $item ) {
				$this->Factory->barInc( $item['name'] );
			}
			if ( $current === $count ) {
				$this->Factory->barDel();
			}
		} ] );
		$Playlist->load();
		$Playlist->cfg( 'onLoad', false );

		$Playlist->pl = $pl;
		$Playlist->name = $pl['name'];
		$count = $Playlist->count();
		$this->Logger->notice( "- found {$count} tracks" );

		if ( $pl['shuffle'] ) {
			$this->Logger->notice( '- shuffle...' );
			$Playlist->shuffle();
		}

		if ( $pl['limit'] && $pl['limit'] < $count ) {
			$Playlist->reduce( $pl['limit'] );
			$count = $Playlist->count();
			$this->Logger->notice( "- reduced to {$count} tracks (user limit)" );
		}

		$this->Factory->barNew( $count, 'bar_adding' );

		// -------------------------------------------------------------------------------------------------------------
		// Total size 2/2:
		// Skip remaining tracks if total size has been reached
		$done = 0;
		foreach ( $Playlist->each() as $itemId => $item ) {
			$home = basename( dirname( $item['path'] ) );
			$last = $home . $sep . $item['name']; // {last_dir}/{track.mp3}
			$item['map'] = $map ? $map . $sep . $last : $last;

			$this->Factory->barInc( $item['name'] );
			$Playlist->itemUpdate( $itemId, $item['map'], 'map' );

			// ---------------------------------------------------------------------------------------------------------
			// Is unique track? Same track from different playlists should NOT increase total size!
			if ( isset( $this->PlaylistAll->id2bytes[$itemId] ) ) {
				$this->stats['dupes']++;
				$done++;
				continue;
			}

			// ---------------------------------------------------------------------------------------------------------
			// Is file exists?
			if ( !$stat = @stat( $item['path'] ) ) {
				throw new \RuntimeException( sprintf( 'Missing track "%s". Please "[Rebuild] Winamp ML" first!', $item['orig'] ) );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Total size limit?
			$bytes = $this->stats['bytes'] + $stat['size'];
			if ( $total && $bytes > $total ) {
				$Playlist->reduce( $done );
				$this->Factory->barDel();

				/* @formatter:off */
				$this->Factory->info( '- reduced to {done} tracks (total size limit: {total})', [
					'{done}'  => $done,
					'{total}' => $this->Utils->byteString( $total ),
				]);
				/* @formatter:on */
				break;
			}

			// ---------------------------------------------------------------------------------------------------------
			// PlaylistALL: add new unique track!
			$this->PlaylistAll->insert( $item['path'] );
			$this->PlaylistAll->itemUpdate( $itemId, $item['map'], 'map' );
			$this->PlaylistAll->id2bytes[$itemId] = $stat['size'];

			// ---------------------------------------------------------------------------------------------------------
			// Export:
			if ( $this->isExport ) {
				$this->manifestInsert( 'export', $exp . $sep . $last, $item['path'] );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Summary:
			$this->stats['bytes'] = $bytes;
			$this->stats['items']++;
			$done++;
		}

		$this->Factory->barDel();
		$this->statsRebuild();

		/* @formatter:off */
		$this->Factory->notice( 'Tracks: {items} | {bytes} | ~{asize}', [
			'{items}' => $this->stats['items'],
			'{bytes}' => $this->Utils->byteString( $this->stats['bytes'] ),
			'{asize}' => $this->Utils->byteString( $this->stats['asize'] ),
		]);
		/* @formatter:on */

		$this->Logger->debug( $this->Utils->phpMemoryMax() );
		return $Playlist;
	}

	/**
	 * Get loaded Playlists collection.
	 *
	 * @return array Playlist[] Playlist objects indexed by pl[name]
	 */
	public function playlists(): array
	{
		return $this->playlists;
	}

	/**
	 * Convert config playlist into Playlist objects.
	 */
	protected function playlistsLoad(): void
	{
		if ( $this->playlists ) {
			return;
		}

		/* @formatter:off */
		$pls = array_merge(
			$this->Factory->get( 'playlists', [] ) ,
			$this->plsFromNames( $this->Factory->get( 'extra', [] ) ),
		);
		/* @formatter:on */

		$pls = array_map( [ $this, 'plNormalize' ], $pls );

		// Proccess playlists without total size restrictions first!
		$this->Utils->arraySortMulti( $pls, 'istotal' );

		foreach ( $pls as $pl ) {
			if ( $Playlist = $this->playlist( $pl ) ) {
				$this->playlists[] = $Playlist;
			}
		}

		// Veriffy
		$items = count( $this->PlaylistAll->id2bytes );
		$bytes = array_sum( $this->PlaylistAll->id2bytes );
		if ( $items !== $this->stats['items'] || $bytes !== $this->stats['bytes'] ) {
			/* @formatter:off */
			throw new \RuntimeException( sprintf( "Data integrity check failed!\n" .
				'PlaylistAll->items: %1$s  <->  progress->items: %3$s' . PHP_EOL .
				'PlaylistAll->bytes: %2$s  <->  progress->bytes: %4$s' . PHP_EOL ,
				/*1*/ $items,
				/*2*/ $bytes,
				/*3*/ $this->stats['items'],
				/*4*/ $this->stats['bytes'],
			) );
			/* @formatter:on */
		}
	}

	/**
	 * Convert playlist names to playlist definitions.
	 */
	protected function plsFromNames( array $names ): array
	{
		$out = [];
		$names = array_combine( $names, $names ); // combine + unique

		if ( !count( $names ) ) {
			return $out;
		}

		$pls = Winamp::loadPlaylists( $this->Factory->get( 'winamp_dir' ) . '/' . $this->Factory->get( 'winamp_xml' ) );

		// Keep $names order (for tests)
		foreach ( $names as $name ) {
			foreach ( $pls as $pl ) {
				if ( $name === $pl['title'] ) {
					/* @formatter:off */
					$out[$name] = [
						'name'    => $pl['title'],
						'file'    => $pl['filename'],
						'istotal' => false,
					];
					/* @formatter:on */
				}
			}
		}

		// Check missing
		if ( $miss = array_diff( array_keys( $names ), array_keys( $out ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unable to locate playlist name: [%s]', implode( '], [', $miss ) ) );
		}

		return $out;
	}

	/**
	 * Normalize cfg[playlists] definition.
	 */
	protected static function plNormalize( array $pl ): array
	{
		/* @formatter:off */
		$pl = array_merge([
			'name'    => 'Unknown',
			'shuffle' => false,
			'save'    => 1,
			'limit'   => 0,
			'istotal' => true,
		], $pl );
		/* @formatter:on */

		$pl['limit'] = (int) $pl['limit'];
		$pl['save'] = (int) $pl['save'];
		$pl['shuffle'] = (bool) $pl['shuffle'];

		// Never shuffle cfg[extra] playlists
		// Always shuffle multi generated playlists
		$pl['shuffle'] = $pl['shuffle'] ?: $pl['save'] > 1;

		return $pl;
	}

	/**
	 * Insert manifest entry.
	 *
	 * If only [dst] file is provided then it simply will be deleted in manifestUnlink()
	 * If both [dst] and [src] files are provided then they will be matched before deletion.
	 * @see Exporter::manifestUnlink()
	 *
	 * @param string $type cfg[{type}_dir] holding output dir, eg. output|export
	 * @param string $dst  Destination file
	 * @param string $src  Source file (optional)
	 */
	protected function manifestInsert( string $type, string $dst, string $src = '' ): void
	{
		$out = [];

		if ( !$out['dst'] = $this->Utils->pathFix( $dst ) ) {
			throw new \RuntimeException( sprintf( 'Missing manifest [dst] file: "%s"', $dst ) );
		}

		if ( $src && !$out['src'] = realpath( $src ) ) {
			throw new \RuntimeException( sprintf( 'Missing manifest [src] file: "%s"', $src ) );
		}

		$id = $this->manifestId( $out['dst'] );
		$this->manifest[$type][$id] = $out;
	}

	/**
	 * Compute manifest item id.
	 * @param string $dst Destination file
	 */
	protected function manifestId( string $dst ): int
	{
		return crc32( $dst );
	}

	/**
	 * Remove manifest entries from filesystem.
	 *
	 * Do not delete if the same file is exported again and it's size and
	 * modification time is less than 10s diffrent: [src] <=10s=> [dst].
	 * In all other cases unlink old files and orphans that are not going to be exported again.
	 *
	 * For playlists we dont know new filenames before they are actually created.
	 * So unlink everything from old manifest and then save new filenames in new manifest file.
	 *
	 * @param string $type Config[{type}_dir], eg. output|export
	 */
	protected function manifestUnlink( string $type ): bool
	{
		$dir = $this->Factory->get( "{$type}_dir" );
		$this->Factory->notice( 'Sync: "%s"', $dir );

		// No manifest found? Clear output dir to get rid of all untracked files
		if ( !is_file( $file = $dir . '/' . $this->Factory->get( 'manifest' ) ) ) {
			$get = $this->Utils->prompt( 'Manifest file not found! Clear export dir? y/[n]: ', '', 'N' );
			if ( 'Y' === strtoupper( $get ) ) {
				$this->Logger->notice( '- clearing export dir...' );
				$this->Utils->dirClear( $dir );
			}
			return false;
		}

		// Collect extra data
		if ( DEBUG ) {
			$this->stats[$type]['invalid'] = [];
			$this->stats[$type]['missing'] = [];
			$this->stats[$type]['updated'] = [];
			$this->stats[$type]['deleted'] = [];
		}

		// -------------------------------------------------------------------------------------------------------------
		// Check old manifest:
		$manifest = json_decode( file_get_contents( $file ), true );

		$bytes = $items = $invalid = $missing = $updated = $deleted = 0;
		$this->Factory->barNew( count( $manifest ), 'bar_analyzing' );

		foreach ( $manifest as $id => $old ) {
			$size = 0;
			$newSrc = $this->manifest[$type][$id]['src'] ?? null;
			$newDst = $this->manifest[$type][$id]['dst'] ?? null;
			$oldDst = $old['dst'];

			$this->Factory->barInc( basename( dirname( $oldDst ) ) . '/' . basename( $oldDst ) );

			// Check manifest file integrity
			if ( $newDst && $id !== $this->manifestId( $oldDst ) ) {
				$unlink = true;
				$this->Factory->warning( 'Invalid [dst:{dst}, id:{id}]', [ '{id}' => $id, '{dst}' => $oldDst ] );
				DEBUG && $this->stats[$type]['invalid'][] = $oldDst;
			}
			// Playlists: alwasys delete (no src, auto-generated)
			// Music:     keep only if [src] exists and was not changed
			elseif ( $newSrc && is_file( $newSrc ) && is_file( $newDst ) ) {
				$statSrc = stat( $newSrc );
				$statDst = stat( $newDst );
				$unlink = false;
				$unlink |= $statSrc['size'] !== $statDst['size'];
				$unlink |= abs( $statSrc['mtime'] - $statDst['mtime'] ) > 10; // allow 10s shift @see touch(mtime)
				$unlink && $updated++;
				$size = $statDst['size'];
				/* @formatter:off */
				DEBUG && $unlink && $this->Factory->debug(
					'{action} [dst:{dst}, size:{srcB}/{dstB}, mtime:{srcT}/{dstT}, id:{id}]', [
						'{id}'     => $id,
						'{action}' => $unlink ? 'Update' : 'Keep',
						'{dst}'    => $oldDst,
						'{srcB}'   => $statSrc['size'],
						'{dstB}'   => $statDst['size'],
						'{srcT}'   => $statSrc['mtime'],
						'{dstT}'   => $statDst['mtime'],
				]);
				/* @formatter:off */
				DEBUG && $unlink && $this->stats[$type]['updated'][] = $oldDst;
			}
			// Previously exported file is missing from new manifest
			elseif ( !$newDst && $oldDst ) {
				$unlink = true;
				$missing++;
				DEBUG && $this->Factory->debug( 'Missing [dst:{dst}, id:{id}]', [ '{id}' => $id, '{dst}' => $oldDst ] );
				DEBUG && $this->stats[$type]['missing'][] = $oldDst;
			}
			// Default: always unlink if [dst] exists!
			else {
				$unlink = true;
			}

			// Delete to allow copy new file
			if ( $unlink && @unlink( $oldDst ) ) {
				$deleted++;
				DEBUG && $this->Factory->debug( 'Delete [dst:{dst}, id:{id}]', [ '{id}' => $id, '{dst}' => $oldDst ] );
				DEBUG && $this->stats[$type]['deleted'][] = $oldDst;
			}
			// File won't be copied over. Reduce totals!
			elseif ( $size ) {
				$bytes += $size;
				$items++;
			}
		}

		$this->Factory->barDel();

		// -------------------------------------------------------------------------------------------------------------
		// Summary:
		$invalid && $this->Logger->info( "- invalid {$invalid} files" );
		$missing && $this->Logger->info( "- missing {$missing} files" );
		$deleted && $this->Logger->info( "- deleted {$deleted} files" );
		$updated && $this->Logger->info( "- updated {$updated} files" );

		if ( $items ) {
			$this->stats['items'] -= $items; // might be 0 items!
			$this->stats['bytes'] -= $bytes;
			$this->statsRebuild();

			/* @formatter:off */
			$this->Factory->info( '- saved {bytes} by not exporting {items} matched files.', [
				'{bytes}' => $this->Utils->byteString( $bytes ),
				'{items}' => $items,
			]);
			/* @formatter:on */
		}

		return @unlink( $file );
	}

	/**
	 * Save manifest to file.
	 */
	protected function manifestWrite( string $type ): void
	{
		$file = $this->Factory->get( "{$type}_dir" ) . '/' . $this->Factory->get( 'manifest' );
		file_put_contents( $file, json_encode( $this->manifest[$type], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Rebuild stats.
	 */
	protected function statsRebuild(): void
	{
		$this->stats['asize'] = $this->stats['items'] ? $this->stats['bytes'] / $this->stats['items'] : 0;
	}

	/**
	 * Start export.
	 */
	public function run(): void
	{
		parent::run();

		if ( isset( $this->start ) ) {
			throw new \RuntimeException( 'Already launched!' );
		}

		$this->start = $this->Utils->exectime();
		$this->isExport = (bool) $this->Factory->get( 'export_dir' );

		$this->PlaylistAll = $this->Factory->Playlist(); // dont link with file yet!
		$this->PlaylistAll->pl = $this->plNormalize( [ 'name' => $this->Factory->get( 'playlist_all' ) ] );

		if ( !is_dir( $dir = $this->Factory->get( 'winamp_dir' ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Winamp ML dir "%s" not found at "%s". Check cfg[winamp_dir]',
				/**/ $dir,
				/**/ getcwd() ) );
		}

		// Config
		$this->configure();

		// Load tracks
		$this->playlistsLoad();
		$this->Factory->info();
		$this->plsSummary();

		// Generate playlists
		$this->Factory->info();
		$this->runOutput();

		// Copy tracks
		if ( $this->isExport ) {
			$this->Factory->info();
			$this->runExport();
		}

		$this->cmdTitle();
		$this->Logger->notice( 'Done.' );
	}

	/**
	 * Verify (prompt) user config.
	 */
	protected function configure(): void
	{
		$Prompt = $this->Factory->Prompt();

		$total = $Prompt->importBytes( 'total_bytes', 'Total size (0 - unlimited)' );
		$this->Factory->cfg( 'total_bytes_str', $total ? $this->Utils->byteString( $total ) : 'no limit' );
		$this->Logger->info( 'Total size: ' . $this->Factory->get( 'total_bytes_str' ) );

		$dirs = [];
		$dirs[] = [ 'output_dir', 'Playlists dir' ];
		$this->isExport && $dirs[] = [ 'export_dir', 'Music dir' ];
		foreach ( $dirs as $v ) {
			$Prompt->importPath( $v[0], $v[1], $this->Factory->get( 'auto_dirs' ) );
		}
		foreach ( $dirs as $v ) {
			$this->Logger->info( $v[1] . ': ' . $this->Factory->get( $v[0] ) );
		}
	}

	/**
	 * Show "export.m3u8" summary.
	 *
	 * @see Exporter::$PlaylistAll
	 */
	protected function plsSummary()
	{
		/* @formatter:off */
		$this->Factory->notice([
			'Playlist: [{name}] - all tracks summary',
			'Tracks: {items} ({dupes} dupes) | {bytes} | ~{asize}',
			],[
			'{name}' => $this->PlaylistAll->pl['name'],
			'{items}' => $this->stats['items'],
			'{bytes}' => $this->Utils->byteString( $this->stats['bytes'] ),
			'{asize}' => $this->Utils->byteString( $this->stats['asize'] ),
			'{dupes}' => $this->stats['dupes'],
		]);
		/* @formatter:on */

		// Release memory
		$this->gc( $this->PlaylistAll->id2bytes );
	}

	/**
	 * Save playlists in output dir.
	 */
	protected function runOutput()
	{
		$this->manifestUnlink( 'output' );

		foreach ( $this->playlists as $Playlist ) {
			for ( $i = 0; $i < $Playlist->pl['save']; $i++ ) {
				$name = $Playlist->pl['name'];
				if ( $Playlist->pl['save'] > 1 ) {
					$name .= sprintf( '_%0' . ( floor( $Playlist->pl['save'] / 10 ) + 1 ) . 'd', $i + 1 );
				}
				$file = sprintf( '%s/%s.m3u8', $this->Factory->get( 'output_dir' ), $name );
				$this->playlistWrite( $Playlist, $file );
			}
		}
		$file = sprintf( '%s/%s.m3u8', $this->Factory->get( 'output_dir' ), $this->PlaylistAll->pl['name'] );
		$this->playlistWrite( $this->PlaylistAll, $file );

		$this->manifestWrite( 'output' );

		// Release memory
		$this->gc( $this->playlists );
	}

	/**
	 * Save playlist in output dir.
	 */
	protected function playlistWrite( Playlist $Playlist, string $file )
	{
		/* @formatter:off */
		$header = sprintf(
			'# %1$s' . "\n" .
			'# %6$s v%7$s' . "\n" .
			'# %5$s' . "\n" .
			'# Playlist: %2$s | %3$s | %4$s tracks' . "\n" .
			"\n",
			/*1*/ $this->Factory->get( 'cmd_title' ),
			/*2*/ $Playlist->pl['name'],
			/*3*/ basename( $file ),
			/*4*/ $Playlist->count(),
			/*5*/ date( 'r', time() ),
			/*6*/ Application::APP_NAME,
			/*7*/ Application::APP_VERSION,
		);
		/* @formatter:on */

		$Playlist->pl['shuffle'] && $Playlist->shuffle();
		$tracks = $Playlist->paths( $this->isExport ? 'map' : 'path' );
		file_put_contents( $file, $header . implode( "\n", $tracks ) . "\n" );

		$this->manifestInsert( 'output', $file );
		$this->Factory->notice( '- save [{name}] "{path}"', [ '{name}' => $Playlist->pl['name'], '{path}' => realpath( $file ) ] );
	}

	/**
	 * Save tracks in export dir.
	 */
	protected function runExport()
	{
		if ( !$this->isExport ) {
			return;
		}

		// Unlink invalid files and write new manifest ASAP,
		// so we know what files to delete next time, even if export failed in half way.
		$this->manifestUnlink( 'export' );
		$this->manifestWrite( 'export' );

		/* @formatter:off */
		$this->Factory->notice( 'Copy: {items} tracks | {bytes}', [
			'{items}' => $this->stats['items'],
			'{bytes}' => $this->Utils->byteString( $this->stats['bytes'] ),
		]);
		/* @formatter:on */

		$this->Factory->barNew( $this->stats['items'], 'bar_exporting' );

		foreach ( $this->manifest['export'] as $new ) {
			// Don't replace existing files left (matched) by manifestUnlink()
			if ( is_file( $new['dst'] ) ) {
				continue;
			}

			$new['name'] = basename( $new['dst'] );
			$this->Factory->barInc( $new['name'] );
			$info = $this->progress();

			/* @formatter:off */
			$this->cmdTitle( '[{cent_done}%] {time_left} left at {speed_bps}/s', [
				'{cent_done}' => floor( $info['cent_done'] ),
				'{time_left}' => $this->Utils->timeString( $info['time_left'], 0 ),
				'{speed_bps}' => $this->Utils->byteString( $info['speed_bps'] ),
			]);
			/* @formatter:on */

			// Create "last" subdir, copy to [dst], update [dst:mtime] to match [src:mtime]
			// Warning: The touch(mtime) might be 1s inaccurate! Bug or performance?
			@mkdir( dirname( $new['dst'] ), 0777, true );
			copy( $new['src'], $new['dst'] );
			touch( $new['dst'], filemtime( $new['src'] ) );
		}

		$this->Factory->barDel();
	}
}
