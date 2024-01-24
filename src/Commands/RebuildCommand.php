<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Rebuild playlists.xml file
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
		$this->addOption( 'infile', 'i', InputOption::VALUE_REQUIRED, "Winamp playlist.xml or single playlist file ($this->inputExtStr)", $this->Factory->cfg( 'winamp_playlists' ) );
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

		$this->info( '=========================' );
		$this->info( 'Rebuild Winamp playlists:' );
		$this->info( '=========================' );
		$this->debug( 'Args: ' . $this->Utils->print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		$infile = $input->getOption( 'infile' );

		if ( !in_array( $this->Utils->fileExt( $infile ), $this->inputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Input file "%s" not in supproted extensions: %s',
				/**/ $infile,
				/**/ $this->inputExtStr ) );
		}

		/* @formatter:off */
		$locations = [
			$infile,
			getcwd() . '/' . basename( $infile ),
			dirname( $this->Factory->cfg( 'winamp_playlists' ) ) . '/' . basename( $infile ),
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

		$this->debug( 'Resolved [Media folder] "%s"', $mediaDir );
		$this->debug( 'Resolved [Escape folder] "%s"', $escDir );

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

			$this->info();
			$this->notice( 'Playlist [%s] "%s"', $playlistName, $playlistPath );

			$playlistCount = $Playlist->count();
			$playlistCountSkip = 0;
			$this->debug( $this->Utils->memory() );
			$this->info( '- tracks: %d ', $playlistCount );
			$this->info( '- analyzing...' );

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
					$this->notice( '- skip "%s"', $item['orig'] );
					$Playlist->itemUpdate( $id, $item['orig'] ); // replace missing realpath with original entry
					$playlistCountSkip++;
					continue;
				}
				if ( 'Remove' == $itemPath ) {
					$this->notice( '- remove "%s"', $item['orig'] );
					$Playlist->remove( $id );
					continue;
				}
				if ( 'Exit' == $itemPath ) {
					$this->warning( 'User Exit' );
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
					$this->notice( '- update:' );
					$this->notice( '  <-- "%s"', $item['orig'] );
					$this->notice( '  --> "%s"', $itemPath );
					$Playlist->itemUpdate( $id, $itemPath );
				}

				// =====================================================================================================
				// Next mp3...
				// =====================================================================================================
			}

			count( $this->dirMap ) && $this->debug( 'Relocate MAP: ' . $this->Utils->print_r( $this->dirMap ) );
			count( $this->regMap ) && $this->debug( 'Rename MAP: ' . $this->Utils->print_r( $this->regMap ) );

			// ---------------------------------------------------------------------------------------------------------
			// Duplicates: updated paths may resolve to same location!
			if ( $input->getOption( 'dupes' ) ) {
				$Playlist->duplicates( true );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Sort
			if ( $input->getOption( 'sort' ) ) {
				$this->info( '- sort: %s', $Playlist->sort() ? 'changed' : 'not changed' );
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
				$this->info( '- removed (%d):', $stats['removedCount'] );
				foreach ( $stats['removed'] as $val ) {
					$this->info( '  <-- "%s"', $val );
				}
			}

			if ( $stats['duped'] ) {
				$this->info( '- duplicates (%s):', count( $stats['duped'] ) );
				foreach ( $stats['dupes'] as $path => $ids ) {
					$this->info( '  x%d - %s', count( $ids ), $path );
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

				$this->info( '- save:%s%s', $isForce ? ' +force ' : '', $isBackup ? ' +backup ' : '' );
				$save = $Playlist->save( !$isDry, $isBackup, $outFormat );

				$this->info( '- saved: "%s"', $save['file'] );
				$isBackup && $this->info( '- backup: "%s"', $save['back'] ?: '---' );
			}

			// ---------------------------------------------------------------------------------------------------------
			/* @formatter:off */
			$this->notice(
				'Summary: %2$d paths updated: ' .
				'%3$d moved, %4$d removed, %5$s dupes --> ' .
				'%6$d before, %7$d after (%8$d erased, %9$d skiped)',
				/*1*/ $playlistName,
				/*2*/ $stats['updated'],
				/*3*/ $stats['movedCount'],
				/*4*/ $stats['removedCount'],
				/*5*/ $input->getOption( 'dupes' ) ? $stats['dupedCount'] : '?',
				/*6*/ $playlistCount,
				/*7*/ $playlistCountFinal,
				/*8*/ $stats['erased'],
				/*9*/ $playlistCountSkip,
			);
			/* @formatter:on */

			// =========================================================================================================
			// Next playlist...
			// =========================================================================================================
		}

		// Summary
		if ( $this->stats['count'] > 1 ) {

			$this->notice( '=' );

			/* @formatter:off */
			$this->notice( 'In %1$d playlists found %2$d entries (%3$d moved, %4$d removed, %5$d dupes)',
				/*1*/ $this->stats['count'],
				/*2*/ $this->stats['items'],
				/*3*/ $this->stats['moved'],
				/*4*/ $this->stats['removed'],
				/*5*/ $this->stats['duped'],
			);
			$this->notice( '%1$d before, %2$d after (%3$d updated, %4$d erased, %5$d skiped)',
				/*1*/ $this->stats['before'],
				/*2*/ $this->stats['after'],
				/*3*/ $this->stats['updated'],
				/*4*/ $this->stats['erased'],
				/*5*/ $this->stats['skiped'],
			);
			/* @formatter:on */
		}

		// Pretty print XML?
		if ( $input->getOption( 'pretty-xml' ) && 'xml' == $this->Utils->fileExt( $inputFile ) ) {
			$Xml = new \DomDocument( '1.0' );
			$Xml->preserveWhiteSpace = false;
			$Xml->formatOutput = true;
			$Xml->load( $inputFile );
			$filename = $input->getOption( 'no-backup' ) ? $inputFile : $Playlist->backupName( $inputFile );
			$this->info( 'Save +pretty-xml "%s"', $filename );
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
	protected function pathAbs( array $item, string $home )
	{
		if ( $item['path'] ) {
			return $item['path']; // realpath(), see: Playlist->load()
		}

		$this->debug( 'Not found (#1): "%s" at "%s"', $item['orig'], $home );
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

		$this->debug( 'Not found (#2): no path mapping for "%s"', $base );
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

		$this->debug( 'Not found (#3): no pattern mapping for "%s"', $item['orig'] );
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

		$this->debug( 'Not found (#4): not in Media folder "%s"', $path );
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

		$this->notice( 'Invalid "%s"', $item['orig'] );

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

				$this->notice( 'Updated to "%s"', $path );
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
				$this->debug( 'New path mapping "%s" -> "%s"', $base, $this->dirMap[$base] );
				$this->notice( 'Relocated to "%s"', $path );
				break;

			case 'Rename':
				while ( 1 ) {
					$pat = $helper->ask( $input, $output, new Question( 'Pattern: ' ) );
					$sub = $helper->ask( $input, $output, new Question( 'Substitution: ' ) );

					if ( empty( $pat ) || empty( $sub ) ) {
						return 'Skip';
					}

					$name = $this->getRenamedItem( $pat, $sub, $item['name'] );
					$path = "{$base}/{$name}";

					if ( file_exists( $path ) ) {
						break;
					}

					$output->writeln( sprintf( '<error>Not found "%s"</>', $path ) );
				}

				$this->regMap[$pat] = $sub;
				$this->debug( 'New pattern mapping "%s" -> "%s"', $pat, $sub );
				$this->notice( 'Renamed to "%s"', $name );
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
