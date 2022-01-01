<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Command;

use Orkan\Utils;
use Orkan\Winamp\Tools\PlaylistBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Rebuild playlists.xml file
 *
 * @author Orkan <orkans@gmail.com>
 */
class RebuildCommand extends Command
{
	protected static $defaultName = 'rebuild';

	/**
	 * Defaults for input options
	 */
	private $inputExt;
	private $inputExtStr;
	private $outputExt;
	private $defaultEsc = '[0-9]';

	/**
	 * Stats
	 *
	 * @formatter:off */
	private $stats = [
		'count'   => 0,
		'items'   => 0,
		'dupes'   => 0,
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
	private $dirMap = [];

	/**
	 * Regex for new file names
	 *
	 * @var array
	 */
	private $regMap = [];

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		// Extensions:
		$this->inputExt = array_merge( [ 'xml' ], PlaylistBuilder::SUPPORTED_TYPES );
		$this->outputExt = PlaylistBuilder::SUPPORTED_TYPES;
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

There are 5 steps to validate each playlist entry:

  a) Check that the entry is pointing to an existing file. If not, then:
  b) Check that file exists in mapped location (see Relocate). If not, then:
  c) Check that file exists after renaming (see Rename). If not, then:
  d) Check that file exists in [Media folder] by testing the first letter. If not, then:
  e) Ask for an action:

     [1] Update - enter path manualy for current entry
     [2] Relocate - change path for all remaining files from current entry's location
     [3] Rename - use regular expression to rename filename part for current and remaining entries
     [4] Remove - remove current entry
     [5] Skip (default) - leave current entry and skip to next one
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
Because it changes location for every entry in playlist, it's hard to
generate relative path from old location (could be invalid) to new
location in [Media folder]

EOT );

		$this->addArgument( 'media-folder', InputArgument::REQUIRED, '[Media folder] - Media files location' );
		$this->addOption( 'infile', 'i', InputOption::VALUE_REQUIRED, "Winamp playlist.xml or single playlist file ($this->inputExtStr)", $this->Factory->cfg( 'winamp_playlists' ) );
		$this->addOption( 'esc', 'e', InputOption::VALUE_REQUIRED, '[Escape] sub-folder inside [Media folder]', $this->defaultEsc );
		$this->addOption( 'sort', null, InputOption::VALUE_NONE, 'Sort playlist' );
		$this->addOption( 'dupes', null, InputOption::VALUE_NONE, 'Remove duplicates' );
		$this->addOption( 'action', 'a', InputOption::VALUE_REQUIRED, 'Default action for invalid entries: skip|remove|exit|ask (In --quiet or --no-interaction mode default is: "skip")', 'ask' );
		$this->addOption( 'no-backup', null, InputOption::VALUE_NONE, 'Do not backup modified playlists' );
		$this->addOption( 'format', 'f', InputOption::VALUE_REQUIRED, 'Output format: m3u|m3u8 (implicitly enables --force when input format differs)' );
		$this->addOption( 'no-ext', null, InputOption::VALUE_NONE, 'Skip all #EXTINF lines (will not read Id3 tags from media files)' );
		$this->addOption( 'force', null, InputOption::VALUE_NONE, 'Refresh playlist file even if nothing has been modified, ie. refreshes #M3U tags' );
		$this->addOption( 'pretty-xml', null, InputOption::VALUE_NONE, 'Pretty print playlists file' );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Command\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		$this->Logger->info( '=========================' );
		$this->Logger->info( 'Rebuild Winamp playlists:' );
		$this->Logger->info( '=========================' );
		$this->Logger->debug( 'Args: ' . Utils::print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		$infile = $input->getOption( 'infile' );

		if ( !in_array( Utils::fileExt( $infile ), $this->inputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Input file "%s" not in supproted extensions: %s', $infile, $this->inputExtStr ) );
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
			throw new \InvalidArgumentException( sprintf( "Playlist file not found. Was trying:\n%s", Utils::implode( $locations, ",\n" ) ) );
		}

		if ( !is_dir( $mediaDir = Utils::pathToAbs( $input->getArgument( 'media-folder' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Media folder "%s" not found in "%s"', $input->getArgument( 'media-folder' ), getcwd() ) );
		}

		if ( !is_dir( $escDir = Utils::pathToAbs( $mediaDir . '/' . $input->getOption( 'esc' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Escape folder "%s" not found in "%s"', $input->getOption( 'esc' ), $mediaDir ) );
		}

		if ( !empty( $format = $input->getOption( 'format' ) ) && !in_array( $format, $this->outputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unsuported output format "%s"', $format ) );
		}

		$this->Logger->debug( sprintf( 'Resolved [Media folder] "%s"', $mediaDir ) );
		$this->Logger->debug( sprintf( 'Resolved [Escape folder] "%s"', $escDir ) );

		$Tagger = $input->getOption( 'no-ext' ) ? null : $this->Factory->create( 'M3UTagger' );
		$codePage = $input->getOption( 'code-page' ); // For *.m3u files only

		// =============================================================================================================
		// Each playlist
		// =============================================================================================================
		foreach ( $this->getPlaylists( $inputFile ) as $playlistName => $playlistPath ) {

			$Playlist = $this->Factory->create( 'PlaylistBuilder', $playlistPath, $Tagger, [ 'base' => $mediaDir, 'cp' => $codePage ] );
			$playlistCount = $Playlist->count();
			$playlistCountSkip = 0;

			$this->Logger->notice( sprintf( 'Load [%s] "%s"', $playlistName, $playlistPath ) );
			$this->Logger->info( sprintf( 'Found %d tracks', $playlistCount ) );

			// ---------------------------------------------------------------------------------------------------------
			// PRE Duplicates
			if ( $input->getOption( 'dupes' ) ) {
				$this->logDupes( 'PRE check', $Playlist->duplicates( true ) );
			}

			// =========================================================================================================
			// Each mp3 (without dupes!)
			// =========================================================================================================
			foreach ( $Playlist->items() as $key => $item ) {

				/*
				 * Try to find propper path to media file with different methods...
				 * Note, we cannot join multiple methods here, like: Relocate + Rename
				 * A workaround would be to create master method that would join both procedures
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
					$this->Logger->notice( sprintf( '- skip "%s"', $item['orig'] ) );
					$Playlist->path( $key, $item['orig'] ); // replace missing realpath with original entry
					$playlistCountSkip++;
					continue;
				}
				if ( 'Remove' == $itemPath ) {
					$this->Logger->notice( sprintf( '- remove "%s"', $item['orig'] ) );
					$Playlist->remove( $key );
					continue;
				}
				if ( 'Exit' == $itemPath ) {
					$this->Logger->warning( 'User Exit' );
					return Command::FAILURE;
				}

				if ( !is_file( $itemPath ) ) {
					/* @formatter:off */
					throw new \UnexpectedValueException( sprintf(
						'Computed path "%s" is invalid for playlist entry "%s"',
						$itemPath,
						$item['orig']
					));
					/* @formatter:on */
				}

				// -----------------------------------------------------------------------------------------------------
				// Update
				$itemPath = realpath( $itemPath );
				if ( $item['orig'] != $itemPath ) {
					$this->Logger->notice( '- update:' );
					$this->Logger->notice( sprintf( '  <-- "%s"', $item['orig'] ) );
					$this->Logger->notice( sprintf( '  --> "%s"', $itemPath ) );
					$Playlist->path( $key, $itemPath );
				}

				// =====================================================================================================
				// Next mp3...
				// =====================================================================================================
			}

			count( $this->dirMap ) && $this->Logger->debug( 'Relocate MAP: ' . Utils::print_r( $this->dirMap ) );
			count( $this->regMap ) && $this->Logger->debug( 'Rename MAP: ' . Utils::print_r( $this->regMap ) );

			// ---------------------------------------------------------------------------------------------------------
			// POST Duplicates
			// Some paths might be resolved to same location!
			if ( $input->getOption( 'dupes' ) && $Playlist->stats( 'moved' )['count'] ) {
				$this->logDupes( 'POST check', $Playlist->duplicates( true ) );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Sort
			if ( $input->getOption( 'sort' ) ) {
				if ( $Playlist->sort() ) {
					$this->Logger->info( sprintf( 'Sort' ) );
				}
			}

			// ---------------------------------------------------------------------------------------------------------
			// Logic...
			$isDry = $input->getOption( 'dry-run' );
			$isBackup = !$input->getOption( 'no-backup' );
			$outFormat = $input->getOption( 'format' ) ?: $Playlist->cfg( 'type' );

			$isForce = $input->getOption( 'force' );
			$isForce |= $outFormat != $Playlist->cfg( 'type' );

			// ---------------------------------------------------------------------------------------------------------
			// Save
			if ( $Playlist->isDirty() || $isForce ) {

				$strForce = !$Playlist->isDirty() && $isForce ? ' +force' : '';
				$strBackup = $isBackup ? ' +backup' : '';

				$save = $Playlist->save( !$isDry, $isBackup, $outFormat );

				$this->Logger->info( sprintf( "Save [%s]%s%s", $playlistName, $strForce, $strBackup ) );
				$this->Logger->info( sprintf( 'Saved "%s"', $save['file'] ) );
				$isBackup && $this->Logger->info( sprintf( 'Back "%s"', $save['back'] ?: '---' ) );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Stats
			$stats = $Playlist->stats();

			$playlistCountFinal = count( $Playlist->items() );
			$stats['updated'] = $stats['moved']['count'] + $stats['removed']['count'] + $stats['dupes']['count'];
			$stats['erased'] = $playlistCount - $playlistCountFinal;

			if ( $stats['removed']['count'] ) {
				$this->Logger->info( sprintf( 'Removed (%d):', $stats['removed']['count'] ) );
				foreach ( $stats['removed']['items'] as $val ) {
					$this->Logger->info( sprintf( '--- %s', $val ) );
				}
			}

			if ( $stats['updated'] ) {
				/* @formatter:off */
				$this->Logger->notice( sprintf(
					'- %2$d paths updated: ' .
					'%3$d moved, %4$d removed, %5$s dupes --> ' .
					'%6$d before, %7$d after (%8$d erased, %9$d skiped)',
					$playlistName, // 1
					$stats['updated'], // 2
					$stats['moved']['count'], // 3
					$stats['removed']['count'], // 4
					$input->getOption( 'dupes' ) ? $stats['dupes']['count'] : '?', // 5
					$playlistCount, // 6
					$playlistCountFinal, // 7
					$stats['erased'], // 8
					$playlistCountSkip // 9
				));
				/* @formatter:on */
			}
			else {
				$this->Logger->info( sprintf( 'Skip [%s]', $playlistName ) );
			}

			$this->stats['count']++;
			$this->stats['items'] += $playlistCount;
			$this->stats['dupes'] += $stats['dupes']['count'];
			$this->stats['moved'] += $stats['moved']['count'];
			$this->stats['removed'] += $stats['removed']['count'];
			$this->stats['updated'] += $stats['updated'];
			$this->stats['erased'] += $stats['erased'];
			$this->stats['skiped'] += $playlistCountSkip;
			$this->stats['before'] += $playlistCount;
			$this->stats['after'] += $playlistCountFinal;

			// =========================================================================================================
			// Next playlist...
			// =========================================================================================================
		}

		// Summary
		if ( $this->stats['count'] > 1 ) {

			$this->Logger->notice( '------------------' );

			/* @formatter:off */
			$this->Logger->notice( sprintf( 'In %1$d playlists found %2$d entries (%3$d moved, %4$d removed, %5$d dupes)',
				$this->stats['count'], // 1
				$this->stats['items'], // 2
				$this->stats['moved'], // 3
				$this->stats['removed'], // 4
				$this->stats['dupes'] // 5
			));
			$this->Logger->notice( sprintf( '%1$d before, %2$d after (%3$d updated, %4$d erased, %5$d skiped)',
				$this->stats['before'], // 1
				$this->stats['after'], // 2
				$this->stats['updated'], // 3
				$this->stats['erased'], // 4
				$this->stats['skiped'], // 5
			));
			/* @formatter:on */
		}

		// Pretty print XML?
		if ( $input->getOption( 'pretty-xml' ) && 'xml' == Utils::fileExt( $inputFile ) ) {
			$Xml = new \DomDocument( '1.0' );
			$Xml->preserveWhiteSpace = false;
			$Xml->formatOutput = true;
			$Xml->load( $inputFile );
			$filename = $input->getOption( 'no-backup' ) ? $inputFile : PlaylistBuilder::backupName( $inputFile );
			$this->Logger->info( sprintf( 'Pretty print "%s"', $filename ) );
			file_put_contents( $filename, $Xml->saveXML() );
		}

		return Command::SUCCESS;
	}

	/**
	 * A shorthand for double dupes check
	 */
	private function logDupes( string $label, array $dupes )
	{
		$this->Logger->info( sprintf( 'Duplicates (%s): %s', $label, count( $dupes ) ?: 'none' ) );
		foreach ( $dupes as $entry => $ids ) {
			$this->Logger->info( sprintf( 'x%d - %s', count( $ids ), $entry ) );
		}
	}

	/**
	 * #1: Find absolute path
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	private function pathAbs( array $item, string $home )
	{
		if ( $item['path'] ) {
			return $item['path']; // realpath(), see: PlaylistBuilder->load()
		}

		$this->Logger->debug( sprintf( 'Not found (#1): "%s" (at %s)', $item['orig'], $home ) );
		return false;
	}

	/**
	 * #2: Find item in relocation map
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	private function pathRelocate( array $item )
	{
		$base = dirname( $item['orig'] );
		$path = isset( $this->dirMap[$base] ) ? $this->dirMap[$base] . '/' . $item['name'] : '';

		if ( file_exists( $path ) ) {
			return $path;
		}

		$this->Logger->debug( sprintf( 'Not found (#2): no path mapping for "%s"', $base ) );
		return false;
	}

	/**
	 * #3: Find path with filename regex pattern
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	private function pathRename( array $item )
	{
		$base = dirname( $item['orig'] );

		foreach ( $this->regMap as $pat => $sub ) {
			$path = $base . '/' . $this->getRenamedItem( $pat, $sub, $item['name'] );
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		$this->Logger->debug( sprintf( 'Not found (#3): no pattern mapping for "%s"', $item['orig'] ) );
		return false;
	}

	/**
	 * #4: Find item in Media Folder
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	private function pathMedia( array $item, string $mediaDir, string $escDir )
	{
		$dir = $this->matchDir( $mediaDir, $item['name'] );
		$dir = $dir ? "$mediaDir/$dir" : $escDir;
		$path = $dir . '/' . $item['name'];

		if ( file_exists( $path ) ) {
			return $path;
		}

		$this->Logger->debug( sprintf( 'Not found (#4): not in Media folder "%s"', $path ) );
		return false;
	}

	/**
	 * #5: Missing in all locations - Ask user!
	 *
	 * @param array $item Playlist item
	 * @return string|boolean
	 */
	private function pathQuestion( array $item, InputInterface $input, OutputInterface $output )
	{
		if ( in_array( strtolower( $input->getOption( 'action' ) ), [ 'skip', 'remove', 'exit' ] ) ) {
			return ucfirst( $input->getOption( 'action' ) );
		}

		$this->Logger->notice( sprintf( 'Invalid "%s"', $item['orig'] ) );

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

				$this->Logger->notice( sprintf( 'Updated to "%s"', $path ) );
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
				$this->Logger->debug( sprintf( 'New path mapping "%s" -> "%s"', $base, $this->dirMap[$base] ) );
				$this->Logger->notice( sprintf( 'Relocated to "%s"', $path ) );
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
				$this->Logger->debug( sprintf( 'New pattern mapping "%s" -> "%s"', $pat, $sub ) );
				$this->Logger->notice( sprintf( 'Renamed to "%s"', $name ) );
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
	private function getRenamedItem( string $pat, string $sub, string $name )
	{
		return preg_replace( "~$pat~u", $sub, $name );
	}
}
