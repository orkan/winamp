<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Playlists;

use Orkan\Utils;
use Orkan\Winamp\Tags\M3UInterface;

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
	 * Home dir of playlist file
	 *
	 * @var string
	 */
	private $home;

	/**
	 * Playlist type (extension)
	 *
	 * @var string
	 */
	private $type;

	/**
	 * BOM sequuence
	 *
	 * @var string
	 */
	private $bom;

	/**
	 * Code page to read/write *.m3u files
	 *
	 * @var string
	 */
	private $codePage;

	/**
	 * Main array of all files found in Playlist.
	 * To identify an entry in any of the public methods here - use the (int) key
	 *
	 * $this->main[0] = [ 'line' => original entry1, 'name' => filename1, 'path' => full path1 ]
	 * $this->main[1] = [ 'line' => original entry2, 'name' => filename2, 'path' => full path2 ]
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
	];
	/* @formatter:on */

	/**
	 * @param string $file Path to Playlist file or just filename in current working dir
	 * @param string $codePage *.m3u files encoding
	 * @param M3UInterface $Tagger (optional) Id3 tagger to generate #EXTM3U entries in final playlist file
	 */
	public function __construct( string $file, string $codePage = 'ASCII', M3UInterface $Tagger = null )
	{
		$this->file = Utils::pathToAbs( $file, getcwd() ) ?: $file;
		$this->home = dirname( $this->file );
		$this->type = pathinfo( $this->file, PATHINFO_EXTENSION );
		$this->bom = pack( 'H*', 'EFBBBF' );
		$this->codePage = $codePage;
		$this->Tagger = $Tagger;
	}

	public static function supportedTypes(): string
	{
		return '*.' . implode( ', *.', self::SUPPORTED_TYPES );
	}

	/**
	 * Get Paylists home dir
	 *
	 * @return string
	 */
	public function home(): string
	{
		return $this->home;
	}

	/**
	 * Get Paylists extension
	 *
	 * @return string
	 */
	public function type(): string
	{
		return $this->type;
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
	 * @see \Orkan\Winamp\Playlists\PlaylistBuilder::$main
	 *
	 * @return array Main array
	 */
	public function items(): array
	{
		$this->load();
		return $this->main;
	}

	/**
	 * Return all path lines found in the original Playlist
	 * @see \Orkan\Winamp\Playlists\PlaylistBuilder::$main['line']
	 *
	 * @return array Original path entries
	 */
	public function lines(): array
	{
		$this->load();

		$out = [];
		foreach ( $this->main as $val ) {
			$out[] = $val['line'];
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
				$this->stats[$stat]['items'][] = $this->main[$id]['line'];
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
		$unique = $dupes = $all = [];
		$this->load();

		foreach ( $this->main as $id => $item ) {

			$key = $item['path'];

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
			$this->stats['moved']['items'][] = $this->main[$id]['line'];
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
	 *
	 * @return boolean
	 */
	public function isDirty()
	{
		return $this->isDirty;
	}

	/**
	 * Initialize main array from entries found in playlist.
	 */
	public function load()
	{
		if ( !is_file( $this->file ) ) {
			return;
		}

		if ( !empty( $this->main ) ) {
			return;
		}

		if ( !in_array( $this->type, self::SUPPORTED_TYPES ) ) {
			throw new \Exception( sprintf( 'File type "%s" not in supproted extensions: %s', $this->type, implode( ', ', self::SUPPORTED_TYPES ) ) );
		}

		$toUtf = 'm3u' == $this->type;
		$lines = file( $this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		count( $lines ) && $lines[0] = str_replace( $this->bom, '', $lines[0] );

		foreach ( $lines as $line ) {

			if ( empty( $line ) || 0 === strpos( $line, '#EXT' ) ) {
				continue;
			}

			$toUtf && $line = iconv( $this->codePage, 'UTF-8', $line );

			$this->add( $line );
		}
	}

	/**
	 * Add new entry(s) to playlist.
	 */
	public function add( $lines )
	{
		$lines = (array) $lines;

		foreach ( $lines as $line ) {

			/* @formatter:off */
			$this->main[] = [
				'line' => $line, // original entry
				'path' => $line, // new path to save
				'name' => basename( $line ), // sort by filename
			];
			/* @formatter:on */

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
	 * @param bool $write Set to false for dry-run
	 * @param bool $backup Make a backup of original playlist?
	 * @param string $format Force output format. Default is to use input format
	 * @return array File paths of saved files
	 */
	public function save( bool $write = true, bool $backup = true, string $format = '' ): array
	{

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

			$lines .= $item['path'] . PHP_EOL;
		}

		// -------------------------------------------------------------------------------------------------------------
		// Save
		$back = '';
		$ext = $format ?: $this->type();
		$toAscii = 'm3u' == $ext;
		$file = sprintf( '%s.%s', Utils::fileNoExt( $this->file ), $ext );

		if ( $toAscii ) {
			$lines = iconv( 'UTF-8', $this->codePage, $lines ); // to *.m3u
		}
		else {
			$lines = $this->bom . $lines; // to *.m3u8
		}

		// Don't create backup if we are not overwriting any files
		if ( $backup && is_file( $file ) ) {
			$back = self::backupName( $file );
			$write && rename( $file, $back );
		}

		$write && file_put_contents( $file, $lines );
		$this->isDirty = false;

		return [ 'file' => $file, 'back' => $back ];
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
		self::sortPlaylist( $this->main, $sort, $dir );

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
	 * Sorts multi-dimensional array by sub-array key
	 * Maintains key assigments!
	 *
	 * @param array $playlists
	 * @param string $sort
	 * @param string $dir
	 * @return bool
	 */
	public static function sortPlaylist( array &$playlists, string $sort = '', string $dir = 'asc' ): bool
	{
		if ( empty( $playlists ) || empty( $sort ) || !isset( $playlists[0][$sort] ) ) {
			return false;
		}

		uasort( $playlists, function ( $a, $b ) use ($sort, $dir ) {

			if ( is_int( $a[$sort] ) ) {
				$cmp = $a[$sort] < $b[$sort] ? -1 : 1;
			}
			else {
				$cmp = strcasecmp( $a[$sort], $b[$sort] );
			}

			// Keep ASC sorting for unknown [dir]
			return 'desc' == $dir ? -$cmp : $cmp;
		} );

		return true;
	}
}
