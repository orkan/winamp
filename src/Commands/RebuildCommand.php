<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Rebuild playlists.xml file.
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class RebuildCommand extends Command
{
	protected static $defaultName = 'rebuild';

	/**
	 * Defaults for input options
	 */
	protected $inputExt;
	protected $inputExtStr;
	protected $outputExt;
	protected $defaultEsc = '[0-9]';

	/**
	 * Stats
	 *
	 * @formatter:off */
	protected $stats = [
		'count'   => 0,
		'items'   => 0,
		'duped'   => 0,
		'moved'   => 0,
		'removed' => 0,
		'skiped'  => 0,
		'updated' => 0,
		'erased'  => 0,
		'before'  => 0,
		'after'   => 0,
	];
	/* @formatter:on */

	/**
	 * Mapped locations
	 *
	 * @var array
	 */
	protected $dirMap = [];

	/**
	 * Regex for new file names
	 *
	 * @var array
	 */
	protected $regMap = [];

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		$Playlist = $this->Factory->Playlist();

		// Extensions:
		$this->inputExt = array_merge( [ 'xml' ], $Playlist::SUPPORTED_TYPES );
		$this->outputExt = $Playlist::SUPPORTED_TYPES;
		$this->inputExtStr = '*.' . implode( ', *.', $this->inputExt );

		$this->setDescription( 'Rebuild playlists' );
		$this->setHelp( <<<EOT
		Scan playlist file ({$this->inputExtStr}) and validate path entries.
		--------------------------------------------------------------------
		
		Every time you change the location of your media files, the playlists
		won't take those changes and you will get the wrong paths. This tool tries
		to find missing entries in all your playlists and update it accordingly.
		
		For best results, place your media files in alphabetical subfolders
		(see Media folder). In the case of a different folder layout,
		semi-automatic methods have been implemented (see Validation).
		
		----------
		Validation
		----------
		Scan all playlists from Winamp Media Library playlists.xml or any
		single playlist file and replace all paths to absolute ones (see Q&A).
		
		There are 5 steps to validate each track:
		
		  a) Check that the playlist entry is pointing to an existing file. If not, then:
		  b) Check that file exists in mapped location (see Relocate). If not, then:
		  c) Check that file exists after renaming (see Rename). If not, then:
		  d) Check that file exists in [Media folder] by testing the first letter. If not, then:
		  e) Ask for an action:
		
		     [1] Update - enter new path for current track
		     [2] Relocate - replace path for current and remaining tracks
		     [3] Rename - rename filenames with regex pattern
		     [4] Remove - remove current playlist entry
		     [5] Skip (default) - leave current track and skip to next one
		     [6] Exit - return to prompt line
		
		------------
		Media folder
		------------
		The [Media folder] structure should be organized into sub folders,
		each named with Regular Expressions notation, describing letters range
		of filenames they are holding, ie. [A-Z] or [0-9].
		
		If there is no folder that match the first letter of a filename, then 
		it will look in Escape folder instead (def. {$this->defaultEsc})
		
		Example dir structure:
		
		[Media folder]
		  |
		  +-- [0-9] For filenames starting with a number (also default Escape folder)
		  +-- [A-D] For filenames starting with letters: a, b, c, d
		  +-- ...
		  +-- [T-T] For filenames starting with letter: t
		
		
		-------------------
		Questions & Answers
		-------------------
		Why it replaces relative paths to absolute?
		Because it verifies location for every track in playlist, it's imposible to
		generate relative path from an old invalid location.
		
		EOT );

		$this->addArgument( 'media-folder', InputArgument::REQUIRED, '[Media folder] - Media files location' );
		$this->addOption( 'infile', 'i', InputOption::VALUE_REQUIRED, "Winamp playlist.xml or single playlist file ($this->inputExtStr)", $this->Factory->get( 'winamp_playlists' ) );
		$this->addOption( 'esc', 'e', InputOption::VALUE_REQUIRED, '[Escape] sub-folder inside [Media folder]', $this->defaultEsc );
		$this->addOption( 'sort', null, InputOption::VALUE_NONE, 'Sort playlist' );
		$this->addOption( 'dupes', null, InputOption::VALUE_NONE, 'Remove duplicates' );
		$this->addOption( 'action', 'a', InputOption::VALUE_REQUIRED, 'Default action for invalid entries: skip|remove|exit|ask (In --quiet or --no-interaction mode default is: "skip")', 'ask' );
		$this->addOption( 'no-backup', null, InputOption::VALUE_NONE, 'Do not backup modified playlists' );
		$this->addOption( 'format', 'f', InputOption::VALUE_REQUIRED, 'Output format: m3u|m3u8 (implicitly enables --force when input format differs)' );
		$this->addOption( 'no-ext', null, InputOption::VALUE_NONE, 'Do not generate #EXTINF lines (will not read Id3 tags from media files)' );
		$this->addOption( 'force', null, InputOption::VALUE_NONE, 'Refresh playlist file even if nothing has been modified, ie. refresh #M3U tags' );
		$this->addOption( 'pretty-xml', null, InputOption::VALUE_NONE, 'Pretty print playlists file' );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Commands\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		$infile = $input->getOption( 'infile' );

		// -------------------------------------------------------------------------------------------------------------
		// Validate:
		if ( !in_array( $this->Utils->fileExt( $infile ), $this->inputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Input file "%s" not in supproted extensions: %s',
				/**/ $infile,
				/**/ $this->inputExtStr ) );
		}

		/* @formatter:off */
		$locations = [
			$infile,
			getcwd() . '/' . basename( $infile ),
			dirname( $this->Factory->get( 'winamp_playlists' ) ) . '/' . basename( $infile ),
		];
		/* @formatter:on */

		foreach ( $locations as $v ) {
			if ( $inputFile = realpath( $v ) ) {
				break;
			}
		}

		if ( !is_file( $inputFile ) ) {
			throw new \InvalidArgumentException( sprintf( "Playlist file not found. Was trying:\n%s",
				/**/ $this->Utils->arrayImplode( $locations, ",\n" ) ) );
		}

		if ( !is_dir( $mediaDir = $this->Utils->pathToAbs( $input->getArgument( 'media-folder' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Media folder "%s" not found in "%s"',
				/**/ $input->getArgument( 'media-folder' ), getcwd() ) );
		}

		if ( !is_dir( $escDir = $this->Utils->pathToAbs( $mediaDir . '/' . $input->getOption( 'esc' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Escape folder "%s" not found in "%s"',
				/**/ $input->getOption( 'esc' ), $mediaDir ) );
		}

		if ( !empty( $format = $input->getOption( 'format' ) ) && !in_array( $format, $this->outputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unsuported output format "%s"',
				/**/ $format ) );
		}

		/* @formatter:off */
		$this->Factory->info([
			'=',
			'{command}',
			'   Input file: "{inputFile}"',
			' Media folder: "{mediaDir}"',
			'=',
		],[
			'{command}' => $this->getDescription(),
			'{inputFile}' => $inputFile,
			'{mediaDir}'  => $mediaDir,
		]);
		/* @formatter:on */

		DEBUG && $this->Factory->debug( 'Escape folder: "%s"', $escDir );
		DEBUG && $this->Logger->debug( 'Args: ' . $this->Utils->print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		// =============================================================================================================
		// Each playlist
		// =============================================================================================================
		foreach ( $this->getPlaylists( $inputFile ) as $playlistName => $playlistPath ) {

			/* @formatter:off */
			$Playlist = $this->Factory->Playlist([
				'file'   => $playlistPath,
				'base'   => $mediaDir,
				'tags'   => !$input->getOption( 'no-ext' ),
				'cp'     => $input->getOption( 'code-page' ),
				'onLoad' => [ $this->Factory, 'onPlaylistLoad' ],
			]);
			/* @formatter:on */
			$this->Logger->debug( $this->Utils->phpMemoryMax() );

			$this->Factory->info();
			$this->Factory->notice( 'Playlist [{name}] "{path}"', [ '{name}' => $playlistName, '{path}' => $playlistPath ] );

			$playlistCount = $Playlist->count();
			$playlistCountSkip = 0;
			$this->Logger->info( '- tracks: ' . $playlistCount );
			$this->Logger->info( '- analyzing...' );

			// =========================================================================================================
			// Each mp3
			// =========================================================================================================
			foreach ( $Playlist->items() as $id => $item ) {
				/*
				 * Try to find propper path to media file with different methods...
				 * We cannot join multiple methods here, like: Relocate + Rename.
				 * A workaround would be to create master method that will join both procedures.
				 *
				 * @formatter:off */
				$itemPath =
					$this->pathAbs( $item, $Playlist->cfg( 'base' ) ) ?:
					$this->pathRelocate( $item ) ?:
					$this->pathRename( $item ) ?:
					$this->pathMedia( $item, $mediaDir, $escDir ) ?:
					$this->pathQuestion( $item, $input, $output ) ?:
					'';
				/* @formatter:on */

				if ( 'Skip' == $itemPath ) {
					$this->Factory->info( '- skip "%s"', $item['orig'] );
					$Playlist->itemUpdate( $id, $item['orig'] ); // replace missing realpath with original entry
					$playlistCountSkip++;
					continue;
				}
				if ( 'Remove' == $itemPath ) {
					$this->Factory->info( '- remove "%s"', $item['orig'] );
					$Playlist->remove( $id );
					continue;
				}
				if ( 'Exit' == $itemPath ) {
					$this->Logger->warning( 'User Exit' );
					return Command::FAILURE;
				}

				if ( !is_file( $itemPath ) ) {
					throw new \UnexpectedValueException( sprintf( 'Computed path "%s" is invalid for playlist entry "%s"',
						/**/ $itemPath,
						/**/ $item['orig'] ) );
				}

				// -----------------------------------------------------------------------------------------------------
				// Update
				$itemPath = realpath( $itemPath );
				if ( $item['orig'] != $itemPath ) {
					$this->Logger->info( '- update:' );
					$this->Factory->info( '  <-- "%s"', $item['orig'] );
					$this->Factory->info( '  --> "%s"', $itemPath );
					$Playlist->itemUpdate( $id, $itemPath );
				}

				// =====================================================================================================
				// Next mp3...
				// =====================================================================================================
			}

			DEBUG && count( $this->dirMap ) && $this->Logger->debug( 'Relocate MAP: ' . $this->Utils->print_r( $this->dirMap ) );
			DEBUG && count( $this->regMap ) && $this->Logger->debug( 'Rename MAP: ' . $this->Utils->print_r( $this->regMap ) );

			// ---------------------------------------------------------------------------------------------------------
			// Duplicates: updated paths may resolve to same location!
			if ( $input->getOption( 'dupes' ) ) {
				$Playlist->duplicates( true );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Sort
			if ( $input->getOption( 'sort' ) ) {
				$this->Logger->info( '- sort: ' . ( $Playlist->sort() ? 'changed' : 'not changed' ) );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Stats
			$stats = $Playlist->stats();
			$stats['movedCount'] = count( $stats['moved'] );
			$stats['removedCount'] = count( $stats['removed'] );
			$stats['dupedCount'] = count( $stats['duped'] );
			$stats['updated'] = $stats['movedCount'] + $stats['removedCount'] + $stats['dupedCount'];

			$playlistCountFinal = $Playlist->count();
			$stats['erased'] = $playlistCount - $playlistCountFinal;

			$this->stats['count']++;
			$this->stats['items'] += $playlistCount;
			$this->stats['moved'] += $stats['movedCount'];
			$this->stats['removed'] += $stats['removedCount'];
			$this->stats['duped'] += $stats['dupedCount'];
			$this->stats['updated'] += $stats['updated'];
			$this->stats['erased'] += $stats['erased'];
			$this->stats['skiped'] += $playlistCountSkip;
			$this->stats['before'] += $playlistCount;
			$this->stats['after'] += $playlistCountFinal;

			if ( $stats['removed'] ) {
				$this->Factory->info( '- removed (%s):', $stats['removedCount'] );
				foreach ( $stats['removed'] as $path ) {
					$this->Factory->info( '  <-- "%s"', $path );
				}
			}

			if ( $stats['duped'] ) {
				$this->Factory->info( '- duplicates (%s):', count( $stats['duped'] ) );
				foreach ( $stats['dupes'] as $path => $ids ) {
					$this->Factory->info( '  x{count} - {path}', [ '{count}' => count( $ids ), '{path}' => $path ] );
				}
			}

			// ---------------------------------------------------------------------------------------------------------
			// Save
			$isDry = $input->getOption( 'dry-run' );
			$outFormat = $input->getOption( 'format' ) ?: $Playlist->cfg( 'type' );
			$isBackup = !$input->getOption( 'no-backup' );
			$isForce = $input->getOption( 'force' );
			$isForce |= $outFormat != $Playlist->cfg( 'type' );

			if ( $Playlist->isDirty() || $isForce ) {
				/* @formatter:off */
				$this->Factory->info( '- save:{force}{backup}', [
					'{force}'  => $isForce  ? ' +force '  : '',
					'{backup}' => $isBackup ? ' +backup ' : '',
				]);
				/* @formatter:on */

				$save = $Playlist->save( !$isDry, $isBackup, $outFormat );

				$this->Factory->info( '- saved: "%s"', $save['file'] );
				$isBackup && $this->Factory->info( '- backup: "%s"', $save['back'] ?: '---' );
			}

			// ---------------------------------------------------------------------------------------------------------
			/* @formatter:off */
			$this->Factory->info( 'Summary: {updated} paths updated:' .
				' {moved} moved, {removed} removed, {dupes} dupes -->' .
				' {before} before, {after} after ({erased} erased, {skiped} skiped)', [
				'{updated}' => $stats['updated'],
				'{moved}'   => $stats['movedCount'],
				'{removed}' => $stats['removedCount'],
				'{dupes}'   => $input->getOption( 'dupes' ) ? $stats['dupedCount'] : '?',
				'{before}'  => $playlistCount,
				'{after}'   => $playlistCountFinal,
				'{erased}'  => $stats['erased'],
				'{skiped}'  => $playlistCountSkip,
			]);
			/* @formatter:on */

			// =========================================================================================================
			// Next playlist...
			// =========================================================================================================
		}

		// Summary
		if ( $this->stats['count'] > 1 ) {

			/* @formatter:off */
			$this->Factory->notice([
				'=',
				'In {count} playlists found {items} entries ({moved} moved, {removed} removed, {duped} dupes)',
				'{before} before, {after} after ({updated} updated, {erased} erased, {skiped} skiped)',
			],[
				'{count}' => $this->stats['count'],
				'{items}' => $this->stats['items'],
				'{moved}' => $this->stats['moved'],
				'{removed}' => $this->stats['removed'],
				'{duped}' => $this->stats['duped'],
				'{before}'  => $this->stats['before'],
				'{after}'   => $this->stats['after'],
				'{updated}' => $this->stats['updated'],
				'{erased}'  => $this->stats['erased'],
				'{skiped}'  => $this->stats['skiped'],
			]);
			/* @formatter:on */
		}

		// Pretty print XML?
		if ( $input->getOption( 'pretty-xml' ) && 'xml' == $this->Utils->fileExt( $inputFile ) ) {
			$Xml = new \DomDocument( '1.0' );
			$Xml->preserveWhiteSpace = false;
			$Xml->formatOutput = true;
			$Xml->load( $inputFile );

			$filename = $input->getOption( 'no-backup' ) ? $inputFile : $Playlist->backupName( $inputFile );
			$this->Factory->info( 'Save +pretty-xml "%s"', $filename );
			file_put_contents( $filename, $Xml->saveXML() );

			$Xml->encoding = 'UTF-8';
			$filename = str_replace( '.xml', '.utf8.xml', $filename );
			$this->Factory->info( 'Save +pretty-xml "%s"', $filename );
			file_put_contents( $filename, $Xml->saveXML() );
		}

		return Command::SUCCESS;
	}

	/**
	 * #1: Find absolute path
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	protected function pathAbs( array $item, string $base )
	{
		if ( $item['path'] ) {
			return $item['path']; // realpath(), see: Playlist->load()
		}

		$this->Factory->debug( 'Not found (#1): "{abs}" at "{base}"', [ '{abs}' => $item['orig'], '{base}' => $base ] );
		return false;
	}

	/**
	 * #2: Find item in relocation map
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	protected function pathRelocate( array $item )
	{
		$base = dirname( $item['orig'] );
		$path = isset( $this->dirMap[$base] ) ? $this->dirMap[$base] . '/' . $item['name'] : '';

		if ( file_exists( $path ) ) {
			return $path;
		}

		$this->Factory->debug( 'Not found (#2): no path mapping for "%s"', $base );
		return false;
	}

	/**
	 * #3: Find path with filename regex pattern
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	protected function pathRename( array $item )
	{
		$base = dirname( $item['orig'] );

		foreach ( $this->regMap as $pat => $sub ) {
			$path = $base . '/' . $this->getRenamedItem( $pat, $sub, $item['name'] );
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		$this->Factory->debug( 'Not found (#3): no pattern mapping for "%s"', $item['orig'] );
		return false;
	}

	/**
	 * #4: Find item in Media Folder
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	protected function pathMedia( array $item, string $mediaDir, string $escDir )
	{
		$dir = $this->matchDir( $mediaDir, $item['name'] );
		$dir = $dir ? "$mediaDir/$dir" : $escDir;
		$path = $dir . '/' . $item['name'];

		if ( file_exists( $path ) ) {
			return $path;
		}

		$this->Factory->debug( 'Not found (#4): not in Media folder "%s"', $path );
		return false;
	}

	/**
	 * #5: Missing in all locations - Ask user!
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	protected function pathQuestion( array $item, InputInterface $input, OutputInterface $output )
	{
		if ( in_array( strtolower( $input->getOption( 'action' ) ), [ 'skip', 'remove', 'exit' ] ) ) {
			return ucfirst( $input->getOption( 'action' ) );
		}

		$this->Factory->notice( 'Invalid "%s"', $item['orig'] );

		/* @formatter:off */
		$question = new ChoiceQuestion( 'Please select an action:', [
			1 => 'Update',
			2 => 'Relocate',
			3 => 'Rename',
			4 => 'Remove',
			5 => 'Skip',
			6 => 'Exit',
		], 5 );
		/* @formatter:on */

		$path = '';
		$base = dirname( $item['orig'] );

		$helper = $this->getHelper( 'question' );
		switch ( $answer = $helper->ask( $input, $output, $question ) )
		{
			case 'Update':
				$question = new Question( 'New path: ' );
				$question->setValidator( function ( $answer ) {
					if ( !empty( $answer ) && !is_file( $answer ) ) {
						throw new \InvalidArgumentException( 'File not found! ' );
					}
					return $answer;
				} );

				$path = $helper->ask( $input, $output, $question );
				if ( empty( $path ) ) {
					return 'Skip';
				}

				$this->Factory->notice( 'Updated to "%s"', $path );
				break;

			case 'Relocate':
				$question = new Question( sprintf( 'Replace all occurences of "%s" to: ', $base ) );
				$question->setValidator( function ( $answer ) use ($item ) {
					if ( !empty( $answer ) && !is_file( $out = $answer . '/' . $item['name'] ) ) {
						throw new \InvalidArgumentException( sprintf( 'Not found "%s"', $out ) );
					}
					return $answer;
				} );

				$path = $helper->ask( $input, $output, $question );
				if ( empty( $path ) ) {
					return 'Skip';
				}

				$this->dirMap[$base] = $path;
				$path = "{$path}/{$item['name']}";
				$this->Factory->debug( 'New path mapping "{dir}" -> "{map}"', [ '{dir}' => $base, '{map}' => $this->dirMap[$base] ] );
				$this->Factory->notice( 'Relocated to "%s"', $path );
				break;

			case 'Rename':
				while ( 1 ) {
					$pat = $helper->ask( $input, $output, new Question( 'Pattern: ' ) );
					$sub = $helper->ask( $input, $output, new Question( 'Substitution: ' ) );

					if ( empty( $pat ) || empty( $sub ) ) {
						return 'Skip';
					}

					$name = $this->getRenamedItem( $pat, $sub, $item['name'] );
					$path = $base . '/' . $name;

					if ( file_exists( $path ) ) {
						break;
					}

					$output->writeln( sprintf( '<error>Not found "%s"</>', $path ) );
				}

				$this->regMap[$pat] = $sub;
				$this->Factory->debug( 'New pattern mapping "{pat}" -> "{sub}"', [ '{pat}' => $pat, '{sub}' => $sub ] );
				$this->Factory->notice( 'Renamed to "%s"', $name );
				break;

			default:
				return $answer;
		}

		return $path;
	}

	/**
	 * Get new file name from pattern
	 * Case sensitive!
	 *
	 * @param string $pat
	 * @param string $sub
	 * @param string $name Old filename
	 * @return string New filename
	 */
	protected function getRenamedItem( string $pat, string $sub, string $name )
	{
		return preg_replace( "~$pat~u", $sub, $name );
	}
}
