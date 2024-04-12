<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tools;

use Orkan\Config;
use Orkan\Winamp\Factory;

/**
 * Build M3U playlist.
 *
 * The base idea beghind this class is to make no assumptions about the logic
 * of how the output file will be constructed.
 * It provides shorthand methods for the client to help get various informations
 * about the playlist and then decide what to do with any of the playlist entries.
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class Playlist
{
	use Config;

	/**
	 * Supported playlist extensions.
	 * @var array
	 */
	const SUPPORTED_TYPES = [ 'm3u', 'm3u8' ];

	/**
	 * Main array of all files found in Playlist.
	 * To identify an entry in any of the public methods here - use the (int) key
	 *
	 * $this->main[0] = [ 'orig' => original entry1, 'path' => full path1, 'name' => filename1 ]
	 * $this->main[1] = [ 'orig' => original entry2, 'path' => full path2, 'name' => filename2 ]
	 *
	 * @var array
	 */
	private $main = [];

	/**
	 * Wether the playlist has been modified by the client or not.
	 * @var boolean
	 */
	private $isDirty = false;

	/**
	 * Did file assigned to this instance was already read?
	 * @var boolean
	 */
	private $isLoaded = false;

	/**
	 * Wether the playlist was already shuffled?
	 */
	private $isShuffled = false;

	/* @formatter:off */

	/**
	 * Stats.
	 *
	 * [duped]   => Array( path, path, ... )
	 * [dupes]   => Array( [path] => Array( id, id, ...), [path] => Array( id, id, ...) )
	 * [moved]   => Array( path, path, ... )
	 * [removed] => Array( orig, orig, ... )
	 * [missing] => Array( orig, orig, ... )
	 */
	private $stats = [
		'duped'     => [],
		'dupes'     => [],
		'moved'     => [],
		'removed'   => [],
		'missing'   => [],
	];
	/* @formatter:on */

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;

	public function __construct( Factory $Factory, array $cfg = [] )
	{
		$this->cfg = $cfg;
		$this->merge( self::defaults() );

		$this->Factory = $Factory;
		$this->Utils = $this->Factory->Utils();
	}

	/**
	 * Get defaults.
	 */
	private function defaults(): array
	{
		$file = $this->get( 'file' );

		/**
		 * [file]
		 * Playlist file. Use empty to use memory only.
		 *
		 * [base]
		 * Base dir from which relative track paths are computed
		 *
		 * [type]
		 * Playlist file extension
		 *
		 * [types]
		 * Supported playlist extensions
		 *
		 * [bom]
		 * Write file.m3u8 with this BOM header
		 *
		 * [cp]
		 * Playlist codepage. Output extension: ASCII ==> m3u, UTF-8 ==> m3u8
		 *
		 * [tags]
		 * Generate #EXTINF tags?
		 *
		 * [onLoad]
		 * Callback on loading each track from playlist
		 * onLoad(current, count, line, added)
		 *
		 * @formatter:off */
		return [
			'file'     => '',
			'base'     => $file ? realpath( dirname( $file ) ) : getcwd(),
			'type'     => $file ? pathinfo( $file, PATHINFO_EXTENSION ) : '',
			'types'    => '*.' . implode( ', *.', self::SUPPORTED_TYPES ),
			'bom'      => pack( 'H*', 'EFBBBF' ),
			'cp'       => 'ASCII',
			'tags'     => false,
			'onLoad'   => null,
		];
		/* @formatter:on */
	}

	/**
	 * Get the backup filename string.
	 */
	public static function backupName( string $path ): string
	{
		$i = 1;
		$info = pathinfo( $path );
		$base = dirname( $path );
		do {
			$new = $base . DIRECTORY_SEPARATOR . sprintf( '%s (%d).%s.bak', $info['filename'], $i, $info['extension'] );
			$i++;
		}
		while ( is_file( $new ) );

		return $new;
	}

	/**
	 * Return internal main array.
	 * @see Playlist::$main
	 */
	public function items(): array
	{
		$this->load();
		return $this->main;
	}

	/**
	 * Update item entry.
	 *
	 * @param int    $id   Id of an item
	 * @param string $path Value to add
	 * @param string $kind Type of value. Can be one of supported [orig|path]
	 *                     or custom string, later used in save($kind = '...') to dump into playlist
	 * @return bool True if path was ovevrwriten.
	 */
	public function itemUpdate( int $id, string $path, string $kind = 'path' ): bool
	{
		$last = $this->main[$id][$kind] ?? null;
		$diff = $last !== $path;
		$this->main[$id][$kind] = $path;

		if ( $diff && 'path' === $kind ) {
			$this->stats['moved'][$id] = $this->main[$id]['path'];
		}

		$this->isDirty |= $diff;

		return $diff;
	}

	/**
	 * Merge item.
	 *
	 * @param int    $id   Id of an item
	 * @param array  $item Values to add/overwrite
	 * @return bool True if an item has changed.
	 */
	public function itemMerge( int $id, array $item ): bool
	{
		if ( !$item ) {
			return false;
		}

		$old = json_encode( $this->main[$id] );
		$this->main[$id] = array_merge( $this->main[$id], $item );
		$new = json_encode( $this->main[$id] );
		$diff = $old !== $new;
		$this->isDirty |= $diff;

		return $diff;
	}

	/**
	 * Return playlist sub array of given kind with keys preserved.
	 *
	 * @see Playlist::$main
	 * @see Playlist::insert()
	 *
	 * @param string $kind Key name from $this->main array: orig|path|name|custom
	 * @return array Extracted main[kind] column with keys preserved
	 */
	public function paths( string $kind = 'path' ): array
	{
		$this->load();

		$out = [];
		foreach ( $this->main as $id => $arr ) {
			$out[$id] = $arr[$kind];
		}

		return $out;
	}

	/**
	 * Iterate over each playlist entry. Preserve keys.
	 */
	public function each(): \Generator
	{
		$this->load();
		foreach ( $this->main as $id => $arr ) {
			yield $id => $arr;
		}
	}

	/**
	 * Add new entry(s) to playlist.
	 *
	 * @param mixed $lines String or array of strings
	 * @param bool  $abs   Compute absolute path (from current working dir!)
	 * @return array Inserted ids Array([id] => line, ...)
	 */
	public function insert( $lines, bool $abs = false ): array
	{
		$ids = $dupes = [];

		foreach ( (array) $lines as $line ) {

			if ( empty( $line ) ) {
				continue;
			}

			$id = $this->id( $line );
			if ( isset( $this->main[$id] ) ) {
				$dupes[$line][] = $id;
				continue;
			}

			$ids[$id] = $line;
			$path = $abs ? realpath( $line ) : $line;
			!$path && $this->stats['missing'][] = $line;

			/* @formatter:off */
			$this->main[$id] = [
				'orig' => $line, // original entry
				'path' => $path, // new path to save
				'name' => basename( $line ), // track name (eg. sort by filename)
			];
			/* @formatter:on */

			$this->isDirty = true;
		}

		$this->statsDupes( $dupes );

		return $ids;
	}

	/**
	 * Get item id.
	 *
	 * @param string $line The original playlist line.
	 */
	public function id( string $line ): int
	{
		return crc32( $line );
	}

	/**
	 * Get an item from main array.
	 *
	 * @param string $id Id generated with Playlist::id(line)
	 */
	public function item( int $id ): array
	{
		return $this->main[$id] ?? [];
	}

	/**
	 * Remove entry(s) form main array.
	 *
	 * @param mixed $keys A key returned by items() or an array of keys (for bulk operations)
	 */
	public function remove( $keys, string $stat = 'removed' ): void
	{
		$this->load();

		foreach ( (array) $keys as $id ) {
			if ( isset( $this->main[$id] ) ) {
				$this->stats[$stat][$id] = $this->main[$id]['orig'];
				unset( $this->main[$id] );
				$this->isDirty = true;
			}
		}
	}

	/**
	 * Count (and remove) duplicates.
	 *
	 * Save results in stats.
	 * @see Playlist::$stats
	 *
	 * @param bool $remove If false - only count dupes. If true - remove items from main array
	 */
	public function duplicates( bool $remove = false ): void
	{
		$this->load();
		$unique = $dupes = [];

		foreach ( $this->main as $id => $item ) {

			// Skip missing tracks
			if ( !$path = $item['path'] ) {
				continue;
			}

			// Unique?
			if ( !isset( $unique[$path] ) ) {
				$unique[$path] = $id;
				continue;
			}

			// Duplicated!
			$dupes[$path][] = $id;
		}

		if ( $remove ) {
			foreach ( $dupes as $ids ) {
				$this->remove( $ids );
			}
		}

		$this->statsDupes( $dupes );
	}

	/**
	 * Add more dupes to stats.
	 * @see Playlist::$stats
	 */
	protected function statsDupes( array $dupes )
	{
		if ( $dupes ) {
			$this->stats['duped'] = array_merge( $this->stats['duped'], array_keys( $dupes ) );
			foreach ( $dupes as $path => $ids ) {
				$this->stats['dupes'][$path] = array_merge( $this->stats['dupes'][$path] ?? [], $ids );
			}
		}
	}

	/**
	 * Get playlist stats.
	 */
	public function stats( string $key = '' )
	{
		return $this->stats[$key] ?? $this->stats;
	}

	/**
	 * Whether the main array has been modified or not?
	 */
	public function isDirty(): bool
	{
		return $this->isDirty;
	}

	/**
	 * Whether the playlist file was already imported into $main[]?
	 */
	public function isLoaded(): bool
	{
		return $this->isLoaded;
	}

	/**
	 * Count all tracks.
	 */
	public function count(): int
	{
		$this->load();
		return count( $this->main );
	}

	/**
	 * Initialize main array from entries found in playlist.
	 * It is called internally from number of methods which needs playlist to be loaded first.
	 * This method shoud NOT change the "dirty" flag!
	 *
	 * M3U format
	 * @link https://en.wikipedia.org/wiki/M3U
	 *
	 * @throws \Exception On invalid file type (ext)
	 * @return bool Did reading the file actually happened?
	 */
	public function load(): bool
	{
		// Object created without any file assigned have nothing to load - use add() directly!
		if ( $this->isLoaded || !is_file( $this->get( 'file' ) ) ) {
			return false;
		}

		if ( !in_array( $type = $this->get( 'type' ), self::SUPPORTED_TYPES ) ) {
			throw new \InvalidArgumentException( sprintf( 'File type "%s" not in supproted extensions: %s',
				/**/ $this->get( 'type' ),
				/**/ $this->get( 'types' ) ) );
		}

		$oldDirty = $this->isDirty;
		$toUtf = 'm3u' === $type;
		$lines = file( $this->get( 'file' ), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$count = count( $lines );

		$count && $lines[0] = str_replace( $this->get( 'bom' ), '', $lines[0] );

		// Compute abolute paths from playlist location
		$cwd = getcwd();
		chdir( $this->get( 'base' ) );

		$i = 0;
		foreach ( $lines as $line ) {

			if ( empty( $line ) ) {
				continue;
			}

			$toUtf && $line = iconv( $this->get( 'cp' ), 'UTF-8', $line );

			$item = null;
			if ( $line[0] !== '#' ) {
				$ids = $this->insert( $line, true );
				$id = key( $ids );
				$id && $item = $this->main[$id];
			}

			// Callback (current, count, line, ?item)
			$this->get( 'onLoad' ) && call_user_func( $this->get( 'onLoad' ), ++$i, $count, $line, $item );
		}

		// Recover current working dir
		chdir( $cwd );

		$this->isDirty = $oldDirty;
		$this->isLoaded = true;

		return true;
	}

	/**
	 * Reduce tracks to [0...$length].
	 */
	public function reduce( int $length )
	{
		if ( $length !== $this->count() ) {
			$this->main = array_slice( $this->main, 0, $length, true );
			$this->isDirty = true;
		}
	}

	/**
	 * Save tracks to file.
	 * + generate M3U tags
	 * + create backup of replaced playlist
	 *
	 * Note, it's up to the client to decide whether to save the playlist or not. Use isDirty()
	 * method to find out if any changes has been made to the playlist.
	 *
	 * @param bool   $write  Set to false for dry-run
	 * @param bool   $backup Make a backup of original playlist?
	 * @param string $format Force output format. Default is to use input format
	 * @param string $kind   Type of path to be saved in resulting playlist file: orig|path
	 * @return array File paths of saved files
	 */
	public function save( bool $write = true, bool $backup = true, string $format = '', string $kind = 'path' ): array
	{
		$this->load();

		// -------------------------------------------------------------------------------------------------------------
		// Generate
		$Tagger = $this->get( 'tags' ) ? $this->Factory->M3UTagger() : null;
		$lines = $Tagger ? '#EXTM3U' . PHP_EOL : '';

		foreach ( $this->main as $item ) {

			// START: Id3Tag -------------------------------------------------------------------------------------------
			if ( $Tagger && is_file( $item['path'] ) ) {
				$Tagger->analyze( $item['path'] );

				/* @formatter:off */
				$lines .= sprintf('#EXTINF:%d,%s - %s' . PHP_EOL,
					$Tagger->seconds(),
					$Tagger->artist(),
					$Tagger->title()
				);
				/* @formatter:on */
			}
			// END : Id3Tag --------------------------------------------------------------------------------------------

			$lines .= $item[$kind] . PHP_EOL;
		}

		// -------------------------------------------------------------------------------------------------------------
		// Save
		$ext = $format ?: $this->get( 'type' );
		$toAscii = 'm3u' == $ext;

		/* @formatter:off */
		$result = [
			'file'  => sprintf( '%s.%s', $this->Utils->fileNoExt( $this->get( 'file' ) ), $ext ),
			'back'  => '',
			'bytes' => 0,
		];
		/* @formatter:on */

		if ( $toAscii ) {
			$lines = iconv( 'UTF-8', $this->get( 'cp' ), $lines ); // to *.m3u
		}
		else {
			$lines = $this->get( 'bom' ) . $lines; // to *.m3u8
		}

		// Don't create backup if we are not overwriting any files
		if ( $write && $backup && is_file( $result['file'] ) ) {
			rename( $result['file'], $result['back'] = self::backupName( $result['file'] ) );
			$result['back'] = realpath( $result['back'] );
		}

		if ( $write ) {
			$result['bytes'] = file_put_contents( $result['file'], $lines );
			$result['file'] = realpath( $result['file'] );
		}

		$this->isDirty = false;

		return $result;
	}

	/**
	 * Sort tracks.
	 *
	 * @param  string $sort Field name to sort by
	 * @param  bool   $asc  Sort ASC?
	 * @return bool Did playlist changed?
	 */
	public function sort( string $sort = 'name', bool $asc = true ): bool
	{
		$this->load();

		$keys = array_keys( $this->main );
		$this->Utils->arraySortMulti( $this->main, $sort, $asc );
		$changed = $keys !== array_keys( $this->main );
		$this->isDirty |= $changed;

		return $changed;
	}

	/**
	 * Randomize tracks.
	 *
	 * @return bool Did playlist changed?
	 */
	public function shuffle(): bool
	{
		$this->load();

		$changed = $this->Utils->arrayShuffle( $this->main );
		$this->isShuffled = true;
		$this->isDirty |= $changed;

		return $changed;
	}

	/**
	 * Did playlist was shuffled?
	 */
	public function isShuffled(): bool
	{
		return $this->isShuffled;
	}
}
