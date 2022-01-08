<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Tools;

use Orkan\Utils;

/**
 * Build M3U playlist
 *
 * The base idea beghind this class is to make no assumptions about the logic
 * of how the output file will be constructed.
 * It provides shorthand methods for the client to help get various informations
 * about the playlist and then decide what to do with any of the playlist entries.
 *
 * @author Orkan <orkans@gmail.com>
 */
class PlaylistBuilder
{
	const SUPPORTED_TYPES = [ 'm3u', 'm3u8' ];

	/**
	 * Absolute path to Playlist file
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Config passed to constructor
	 *
	 * @var array
	 */
	private $cfg;

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
	 * Wether the playlist has been modified by the client or not
	 *
	 * @var boolean
	 */
	private $isDirty = false;

	/**
	 * Did file assigned to this instance was already read?
	 *
	 * @var boolean
	 */
	private $isLoaded = false;

	/**
	 * Id3 tagger used to generate #EXTINF tags in final playlist file
	 *
	 * @var M3UInterface
	 */
	private $Tagger;

	/**
	 * Some stats...
	 *
	 * @formatter:off */
	private $stats = [
		'dupes'   => [ 'count' => 0, 'items' => [], 'all' => 0 ],
		'moved'   => [ 'count' => 0, 'items' => [] ],
		'removed' => [ 'count' => 0, 'items' => [] ],
		'missing' => [ 'count' => 0, 'items' => [] ],
	];
	/* @formatter:on */

	/**
	 * @param string $file Path to Playlist file or just filename in current working dir
	 * @param string $codePage *.m3u files encoding
	 * @param M3UInterface $Tagger (optional) Id3 tagger to generate #EXTM3U entries in final playlist file
	 */
	public function __construct( string $file, M3UInterface $Tagger = null, array $cfg = [] )
	{

		/* @formatter:off */
		$this->cfg = array_merge( [
			'base' => realpath( dirname( $file ) ) ?: getcwd(), // base dir from which relative track paths are computed
			'type' => pathinfo( $file, PATHINFO_EXTENSION ),
			'bom'  => pack( 'H*', 'EFBBBF' ),
			'cp'   => 'ASCII',
		], $cfg );
		/* @formatter:on */

		$this->file = $file;
		$this->Tagger = $Tagger;
	}

	public static function supportedTypes(): string
	{
		return '*.' . implode( ', *.', self::SUPPORTED_TYPES );
	}

	/**
	 * Set/Get config value
	 */
	public function cfg( string $key = '', $val = null )
	{
		if ( isset( $val ) ) {
			$this->cfg[$key] = $val;
		}

		if ( '' === $key ) {
			return $this->cfg;
		}

		return $this->cfg[$key] ?? '';
	}

	/**
	 * Get the backup filename string
	 *
	 * @return string
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
	 * Return all the entries found in input Playlist
	 * @see \Orkan\Winamp\Tools\PlaylistBuilder::$main
	 *
	 * @return array Main array
	 */
	public function items(): array
	{
		$this->load();
		return $this->main;
	}

	/**
	 * Return playlist paths. Preserve keys
	 *
	 * @see \Orkan\Winamp\Tools\PlaylistBuilder::$main
	 * @see \Orkan\Winamp\Tools\PlaylistBuilder::add()
	 *
	 * @param string $key Key name from $this->main array: orig|path|name
	 */
	public function paths( string $key = 'path' ): array
	{
		$this->load();

		$out = [];
		foreach ( $this->main as $k => $v ) {
			$out[$k] = $v[$key];
		}

		return $out;
	}

	/**
	 * Remove entry(s) form main array
	 *
	 * @param mixed $keys A key returned by items() or an array of keys (for bulk operations)
	 */
	public function remove( $keys, string $stat = 'removed' )
	{
		foreach ( (array) $keys as $id ) {
			if ( isset( $this->main[$id] ) ) {
				$this->stats[$stat]['count']++;
				$this->stats[$stat]['items'][$id] = $this->main[$id]['orig'];
				unset( $this->main[$id] );
				$this->isDirty = true;
			}
		}
	}

	/**
	 * Remove duplicates
	 *
	 * @param bool $remove
	 * @return array Duplicates Array( 'playlist line' => [ 'id1', 'id2' ... ] )
	 */
	public function duplicates( bool $remove = false ): array
	{
		$this->load();

		$unique = $dupes = $all = [];

		foreach ( $this->main as $id => $item ) {

			$key = $item['path'];

			// Dont check missing tracks
			if ( !$key ) {
				continue;
			}

			if ( isset( $unique[$key] ) ) {
				$dupes[$key][] = $id;
				$this->stats['dupes']['all']++;
				$all[] = $id;
				continue;
			}

			$unique[$key] = $id;
		}

		$remove && $this->remove( $all, 'dupes' );

		return $dupes;
	}

	/**
	 * Set path of an entry
	 *
	 * @param int $id A key returned by items()
	 * @param string $path
	 */
	public function path( int $id, string $path )
	{
		if ( $this->main[$id]['path'] != $path ) {
			$this->stats['moved']['count']++;
			$this->stats['moved']['items'][$id] = $this->main[$id]['path'];
			$this->main[$id]['path'] = $path;
			$this->isDirty = true;
		}
	}

	public function stats( string $key = '' )
	{
		return $this->stats[$key] ?? $this->stats;
	}

	/**
	 * Tells whether the main array has been modified or not
	 */
	public function isDirty(): bool
	{
		return $this->isDirty;
	}

	/**
	 * Tells whether the playlist file was already imported into $main[]
	 */
	public function isLoaded(): bool
	{
		return $this->isLoaded;
	}

	/**
	 * Count all items
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
	 * @throws \Exception On invalid file type (ext)
	 * @return bool       Did reading the file actually happened?
	 */
	public function load(): bool
	{
		// Object created without any file assigned have nothing to load - use add() directly!
		if ( $this->isLoaded || !is_file( $this->file ) ) {
			return false;
		}

		if ( !in_array( $this->cfg['type'], self::SUPPORTED_TYPES ) ) {
			throw new \Exception( sprintf( 'File type "%s" not in supproted extensions: %s', $this->cfg['type'], implode( ', ', self::SUPPORTED_TYPES ) ) );
		}

		$oldDirty = $this->isDirty;
		$toUtf = 'm3u' == $this->cfg['type'];
		$lines = file( $this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$count = count( $lines );

		$count && $lines[0] = str_replace( $this->cfg['bom'], '', $lines[0] );

		// Compute abolute paths from playlist location
		$cwd = getcwd();
		chdir( $this->cfg['base'] );

		$i = 0;
		foreach ( $lines as $line ) {

			if ( empty( $line ) ) {
				continue;
			}

			$toUtf && $line = iconv( $this->cfg['cp'], 'UTF-8', $line );
			$isTrack = 0 !== strpos( $line, '#EXT' );

			$isTrack && $this->add( $line, true );

			// Callback (current, count, line, added)
			isset( $this->cfg['onLoad'] ) && call_user_func( $this->cfg['onLoad'], ++$i, $count, $line, $isTrack );
		}

		// Recover current working dir
		chdir( $cwd );

		$this->isDirty = $oldDirty;
		$this->isLoaded = true;

		return true;
	}

	/**
	 * Add new entry(s) to playlist.
	 *
	 * @param mixed $lines String or array of strings
	 * @param bool  $abs   Compute absolute path (from current working dir!)
	 */
	public function add( $lines, bool $abs = false )
	{
		$lines = (array) $lines;

		foreach ( $lines as $line ) {

			if ( empty( $line ) ) {
				continue;
			}

			$path = $abs ? realpath( $line ) : $line;

			/* @formatter:off */
			$this->main[] = [
				'orig' => $line, // original entry
				'path' => $path, // new path to save
				'name' => basename( $line ), // sort by filename
			];
			/* @formatter:on */

			if ( !$path ) {
				$id = Utils::lastKey( $this->main );
				$this->stats['missing']['count']++;
				$this->stats['missing']['items'][$id] = $line;
			}

			$this->isDirty = true;
		}
	}

	/**
	 * Save playlist to file.
	 * + create backup of replaced playlist
	 * + generate M3U comments if M3UInterface Tagger was provided in the constructor
	 *
	 * Note, it's up to the client to decide whether to save the playlist or not. Use isDirty()
	 * method to find out if any changes has been made to the playlist.
	 *
	 * @param bool   $write  Set to false for dry-run
	 * @param bool   $backup Make a backup of original playlist?
	 * @param string $format Force output format. Default is to use input format
	 * @param string $kind   Type of path to be saved in resulting playlist file: orig|path
	 * @return array         File paths of saved files
	 */
	public function save( bool $write = true, bool $backup = true, string $format = '', string $kind = 'path' ): array
	{
		$this->load();

		// -------------------------------------------------------------------------------------------------------------
		// Generate
		$lines = $this->Tagger ? '#EXTM3U' . PHP_EOL : '';

		foreach ( $this->main as $item ) {

			// START: Id3Tag -------------------------------------------------------------------------------------------
			if ( $this->Tagger && is_file( $item['path'] ) ) {
				$this->Tagger->analyze( $item['path'] );

				/* @formatter:off */
				$lines .= sprintf('#EXTINF:%d,%s - %s' . PHP_EOL,
					$this->Tagger->seconds(),
					$this->Tagger->artist(),
					$this->Tagger->title()
				);
				/* @formatter:on */
			}
			// END : Id3Tag --------------------------------------------------------------------------------------------

			$lines .= $item[$kind] . PHP_EOL;
		}

		// -------------------------------------------------------------------------------------------------------------
		// Save
		$ext = $format ?: $this->cfg['type'];
		$toAscii = 'm3u' == $ext;

		/* @formatter:off */
		$result = [
			'file'  => sprintf( '%s.%s', Utils::fileNoExt( $this->file ), $ext ),
			'back'  => '',
			'bytes' => 0,
		];
		/* @formatter:on */

		if ( $toAscii ) {
			$lines = iconv( 'UTF-8', $this->cfg['cp'], $lines ); // to *.m3u
		}
		else {
			$lines = $this->cfg['bom'] . $lines; // to *.m3u8
		}

		// Don't create backup if we are not overwriting any files
		if ( $write && $backup && is_file( $result['file'] ) ) {
			rename( $result['file'], $result['back'] = self::backupName( $result['file'] ) );
		}

		if ( $write ) {
			$result['bytes'] = file_put_contents( $result['file'], $lines );
		}

		$this->isDirty = false;

		return $result;
	}

	/**
	 * Sort main array
	 *
	 * @param string $sort Field name to sort by
	 * @param string $dir Sort direction (asc|desc)
	 *
	 * @return bool Did sorting changed the playlist?
	 */
	public function sort( string $sort = 'name', string $dir = 'asc' ): bool
	{
		$this->load();

		Utils::sortMultiArray( $this->main, $sort, $dir );

		// Check for changes
		$keys = array_keys( $this->main );
		$last = 0;
		$isDirty = false;
		foreach ( $keys as $key ) {
			if ( $key != $last++ ) {
				$isDirty = true;
				break;
			}
		}

		$this->isDirty |= $isDirty;

		return $isDirty;
	}

	/**
	 * Randomize main array
	 * Maintain key assigments!
	 *
	 * @return bool Did the playlist changed?
	 */
	public function shuffle(): bool
	{
		$this->load();

		Utils::shuffleArray( $this->main );
		$this->isDirty = true;

		return true;
	}
}
