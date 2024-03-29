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
	const APP_VERSION = '5.4.0';
	const APP_DATE = 'Mon, 25 Mar 2024 16:42:00 +01:00';

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
	protected $progress = [
		'track' => 0, // current track
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
		 * Load separate user config via [-- arg].
		 *
		 * CAUTION:
		 * Winamp\Factory is loading config via [-u arg] but Winamp\Exporter doesn't define -u switch,
		 * @see \Orkan\Application::ARGUMENTS
		 * leading PHP::getopt() to stop parsing args when an unknown switch is found...
		 * @see \Orkan\Application::getArg()
		 */
		$this->loadUserConfig();
	}

	/**
	 * Get defaults.
	 */
	protected function defaults(): array
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
		 * [garbage_collect]
		 * Garbage collect: Free up memory once is no longer used
		 *
		 * @formatter:off */
		return [
			'cmd_title'       => 'Winamp Export',
			'app_usage'       => 'export.php [OPTIONS] [--] [config.php]',
			'log_reset'       => true,
			'winamp_xml'      => 'playlists.xml',
			'manifest'        => 'export.json',
			'playlist_all'    => 'Export',
			'auto_dirs'       => false,
			'total_size'      => '',
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
			'bar_analyzing'   => '- analyzing [%bar%] %current%/%max% %message%',
			'bar_exporting'   => '- copying [%bar%] %current%/%max% %message%',
			'garbage_collect' => getenv( 'APP_GC' ) ?: false,
		];
		/* @formatter:on */
	}

	/**
	 * Import and create config path.
	 *
	 * @param string $type cfg[{type}_dir] holding initial path
	 * @param string $name Path description eg. Playlists, Music, etc...
	 */
	protected function dirPrepare( string $type, string $name ): void
	{
		$key = "{$type}_dir";
		$msg = "{$name} output";
		$loop = 0;

		do {
			$dir = $this->importPath( $key, "{$msg} dir" );

			if ( !is_dir( $dir ) ) {
				// Path must exists
				if ( !$this->Factory->get( 'auto_dirs' ) ) {
					echo "Error: $msg dir not found!\n";
					if ( !$loop ) {
						echo "$dir\n";
					}
					$dir = '';
				}
				// Create full directory path if not exist
				elseif ( !$this->Utils->dirClear( $dir ) ) {
					throw new \RuntimeException( sprintf( 'Error creating cfg[%s] path: "%s"', $key, $dir ) );
				}
			}
			$loop++;
		}
		while ( !$dir );

		$this->Factory->cfg( $key, $dir );
		$this->Factory->notice( '{name} set: "{path}"', [ '{name}' => $msg, '{path}' => $dir ] );
	}

	/**
	 * Help: Import path string.
	 *
	 * @param string $key    Config[key] holding initial path
	 * @param string $prompt Prompt message
	 * @return int Fixed path string
	 */
	protected function importPath( string $key, string $prompt = 'Import' ): string
	{
		// Initial path. Prefix with '?' for user prompt
		$path = $this->Factory->get( $key );

		if ( empty( $path ) || '?' === $path[0] ) {

			if ( defined( 'TESTING' ) ) {
				throw new \InvalidArgumentException( "Cannot prompt user input in tests! Use cfg[$key]" );
			}

			$path = ltrim( $path, '?' );
			do {
				$get = $this->Utils->prompt( sprintf( '%s: %s', $prompt, $path ), false );
				$get = $get ?: $path;
				if ( !$get ) {
					echo "Empty string not allowed!\n";
				}
			}
			while ( !$get );
			$path = $get;
		}

		return $this->Utils->pathFix( $path );
	}

	/**
	 * Help: Import bytes as int.
	 *
	 * @param string $key    Config[key] holding bytes to update
	 * @param string $prompt Prompt message
	 * @return int Bytes number
	 */
	protected function importBytes( $key, string $prompt = 'Import' ): int
	{
		// Initial bytes. Prefix with '?' for user prompt
		$bytes = $this->Factory->get( $key );

		if ( is_numeric( $bytes ) ) {
			return $bytes;
		}

		if ( empty( $bytes ) || '?' === $bytes[0] ) {

			if ( defined( 'TESTING' ) ) {
				throw new \InvalidArgumentException( "Cannot prompt user input in tests! Use cfg[$key]" );
			}

			$bytes = ltrim( $bytes, '?' );
			do {
				$get = $this->Utils->prompt( sprintf( '%s: %s', $prompt, $bytes ), false );
				$get = '' === $get ? $bytes : $get;
				if ( !preg_match( '/^([\d.]+)(\s)?([BKMGTPE]?)(B)?$/i', $get ) ) {
					echo "Invalid entry! Use integer or size string: 100M, 3.4G, etc...\n";
					$get = '';
				}
			}
			while ( '' === $get );
			$bytes = $get;
		}

		return $this->Utils->byteNumber( $bytes );
	}

	/**
	 * Get info about collected tracks that going to be exported.
	 */
	public function stats(): array
	{
		return $this->progress;
	}

	/**
	 * Copy tracks progress info.
	 */
	protected function progress(): array
	{
		static $start;

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
		if ( !$this->progress['items'] ) {
			return $out;
		}

		// Start?
		if ( 0 === $this->progress['track'] ) {
			$start = $this->Utils->exectime();
			$this->progress['asize'] = $this->progress['bytes'] / $this->progress['items'];
		}

		$this->progress['track']++;

		$out['byte_done'] = $this->progress['asize'] * $this->progress['track'];
		$out['cent_done'] = 100 / $this->progress['bytes'] * $out['byte_done'];
		$out['cent_left'] = 100 - $out['cent_done'];

		$out['time_exec'] = $this->Utils->exectime( $start );
		$out['time_done'] = $out['time_exec'] / $out['cent_done'];
		$out['time_left'] = $out['time_done'] * $out['cent_left'];

		$out['speed_bps'] = $out['byte_done'] / $out['time_exec'];

		DEBUG && $this->Logger->debug( '$out: ' . $this->Utils->print_r( $out ) );
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
		$total = $pl['istotal'] ? $this->Factory->get( 'total_size' ) : 0;

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
		$bytes = $this->progress['bytes'] + $this->progress['asize'];
		if ( $total && $bytes > $total ) {
			/* @formatter:off */
			$this->Factory->info( '- skipped! Less than {bytes} left (total size limit: {total})', [
				'{bytes}' => $this->Utils->byteString( $this->progress['asize'] ),
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
				$this->progress['dupes']++;
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
			$bytes = $this->progress['bytes'] + $stat['size'];
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
			$this->progress['bytes'] = $bytes;
			$this->progress['items']++;
			$done++;
		}

		$this->Factory->barDel();
		$this->progress['asize'] = $this->progress['bytes'] / $this->progress['items'];

		/* @formatter:off */
		$this->Factory->notice( 'Tracks: {items} | {bytes} | ~{asize}', [
			'{items}' => $this->progress['items'],
			'{bytes}' => $this->Utils->byteString( $this->progress['bytes'] ),
			'{asize}' => $this->Utils->byteString( $this->progress['asize'] ),
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
		if ( $items !== $this->progress['items'] || $bytes !== $this->progress['bytes'] ) {
			/* @formatter:off */
			throw new \RuntimeException( sprintf( "Data integrity check failed!\n" .
				'PlaylistAll->items: %1$s  <->  progress->items: %3$s' . PHP_EOL .
				'PlaylistAll->bytes: %2$s  <->  progress->bytes: %4$s' . PHP_EOL ,
				/*1*/ $items,
				/*2*/ $bytes,
				/*3*/ $this->progress['items'],
				/*4*/ $this->progress['bytes'],
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

		$this->manifest[$type][crc32( $dst )] = $out;
	}

	/**
	 * Remove manifest entries from filesystem.
	 *
	 * Do not delete if the same file is exported again and it's size and
	 * modification time is less than 10s diffrent: [src] <=10s=> [dst].
	 * In all other cases unlink old files and orphans that are not going to be exported again.
	 *
	 * @param string $type Config[{type}_dir], eg. output|export
	 */
	protected function manifestUnlink( string $type ): bool
	{
		$dir = $this->Factory->get( "{$type}_dir" );

		$this->Factory->notice( 'Sync: "%s"', $dir );

		if ( !is_file( $file = $dir . '/' . $this->Factory->get( 'manifest' ) ) ) {
			$get = $this->Utils->prompt( 'Manifest file not found! Clear export dir? y/[n]: ', false );
			if ( 'Y' === strtoupper( $get ) ) {
				$this->Logger->notice( '- clearing export dir...' );
				$this->Utils->dirClear( $dir );
			}
			return false;
		}

		$manifest = json_decode( file_get_contents( $file ), true );

		// For playlists we dont know new filenames before they are actually saved.
		// So unlink everything now from old manifest [dst] and later save new filenames in new manifest file.
		$new = &$this->manifest[$type];

		$bytes = $items = $orphaned = $deleted = 0;
		$this->Factory->barNew( count( $manifest ), 'bar_analyzing' );

		foreach ( $manifest as $id => $old ) {
			$old['name'] = basename( $old['dst'] );
			$this->Factory->barInc( $old['name'] );

			$size = 0;
			$unlink = true;
			$newSrc = $new[$id]['src'] ?? '';
			$newDst = $new[$id]['dst'] ?? '';
			$oldDst = basename( dirname( $old['dst'] ) ) . '/' . $old['name'];

			// Shouldn't happen, but...
			if ( $newDst && $newDst !== $old['dst'] ) {
				/* @formatter:off */
				$this->Factory->warning( 'Manifest [Id:{id}] mismatch! old [dst:"{old}"] !== new [dst:"{new}"]', [
					'{id}'  => $id,
					'{old}' => $old['dst'],
					'{new}' => $newDst,
				]);
				/* @formatter:on */
			}
			// Do we have a src file? Playlists: no (auto-generated), Music: yes
			elseif ( $newSrc && is_file( $newSrc ) && is_file( $newDst ) ) {
				$statSrc = stat( $newSrc );
				$statDst = stat( $newDst );
				$unlink = false;
				$unlink |= $statSrc['size'] !== $statDst['size'];
				$unlink |= abs( $statSrc['mtime'] - $statDst['mtime'] ) > 10; // allow 10s shift @see touch(mtime)
				$size = $statDst['size'];

				/* @formatter:off */
				$this->Factory->debug( '{action} [Id:{id}] "{dst}" [size:{srcB}/{dstB}, mtime:{srcT}/{dstT}]', [
					'{action}' => $unlink ? 'Replace' : 'Keep',
					'{id}'     => $id,
					'{dst}'    => $oldDst,
					'{srcB}'   => $statSrc['size'],
					'{dstB}'   => $statDst['size'],
					'{srcT}'   => $statSrc['mtime'],
					'{dstT}'   => $statDst['mtime'],
				]);
				/* @formatter:on */
			}
			elseif ( $old['dst'] && !$newDst ) {
				$orphaned++;
				$this->Factory->debug( 'Orphaned [Id:{id}] "{path}"', [ '{id}' => $id, '{path}' => $oldDst ] );
			}

			// Delete to allow copy new file
			if ( $unlink && @unlink( $old['dst'] ) ) {
				$deleted++;
			}
			// File won't be copied over. Reduce totals!
			elseif ( $size ) {
				$bytes += $size;
				$items++;
			}
		}

		$this->Factory->barDel();

		// Summary:
		$deleted && $this->Logger->notice( "- deleted {$deleted} previously exported files ({$orphaned} orphaned)" );

		if ( $items ) {
			$this->progress['bytes'] -= $bytes;
			$this->progress['items'] -= $items; // might be 0 items!
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
	 * Import, save byte size.
	 *
	 * @param string $name  Config[name] holding bytes to update
	 * @param string $label Type of Config[name]
	 */
	protected function setBytes( string $name, string $label ): void
	{
		$config = "{$name}_str";
		$prompt = sprintf( '%s size', $label );
		$total = $this->importBytes( $name, "Set {$prompt} (0 - unlimited)" );

		$this->Factory->cfg( $name, $total );
		$this->Factory->cfg( $config, $total ? $this->Utils->byteString( $total ) : 'no limit' );
		$this->Factory->notice( '{name} set: "{path}"', [ '{name}' => $prompt, '{path}' => $this->Factory->get( $config ) ] );
	}

	/**
	 * Start export.
	 */
	public function run(): void
	{
		parent::run();

		$this->isExport = (bool) $this->Factory->get( 'export_dir' );

		$this->PlaylistAll = $this->Factory->Playlist(); // dont link with file yet!
		$this->PlaylistAll->pl = $this->plNormalize( [ 'name' => $this->Factory->get( 'playlist_all' ) ] );

		if ( !is_dir( $dir = $this->Factory->get( 'winamp_dir' ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Winamp ML dir "%s" not found at "%s". Check cfg[winamp_dir]',
				/**/ $dir,
				/**/ getcwd() ) );
		}

		// Config
		$this->setBytes( 'total_size', 'Total' );
		$this->dirPrepare( 'output', 'Playlists' );
		$this->isExport && $this->dirPrepare( 'export', 'Music' );

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
			'{items}' => $this->progress['items'],
			'{bytes}' => $this->Utils->byteString( $this->progress['bytes'] ),
			'{asize}' => $this->Utils->byteString( $this->progress['asize'] ),
			'{dupes}' => $this->progress['dupes'],
		]);
		/* @formatter:on */

		// Release memory
		$this->garbage( $this->PlaylistAll->id2bytes );
	}

	/**
	 * Memory saver.
	 */
	protected function garbage( &$item ): void
	{
		if ( $this->Factory->get( 'garbage_collect' ) ) {
			$this->Factory->debug( $this->Utils->phpMemoryMax(), 1 );
			$item = null;
			$this->Factory->debug( $this->Utils->phpMemoryMax(), 1 );
		}
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
		$this->garbage( $this->playlists );
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
		$this->Factory->notice( 'Sync: {items} tracks | {bytes}', [
			'{items}' => $this->progress['items'],
			'{bytes}' => $this->Utils->byteString( $this->progress['bytes'] ),
		]);
		/* @formatter:on */

		$this->Factory->barNew( $this->progress['items'], 'bar_exporting' );

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
