<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Command;

use Orkan\Utils;
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
	private $outputExt = [ 'm3u', 'm3u8' ];
	private $inputExt = [ 'xml', 'm3u', 'm3u8' ];
	private $inputExtStr;
	private $defaultEsc = '[0-9]';

	protected function configure()
	{
		$this->inputExtStr = '*.' . implode( ', *.', $this->inputExt );

		$this->setDescription( 'Rebuild playlists' );
		$this->setHelp( <<<EOT
Scan playlist file ({$this->inputExtStr}) and validate path entries.
--------------------------------------------------------------------

Each time you change location of your media files, your playlists won't
match the changes and you'll end up with invalid paths. This tool tries 
to help you arrange the files alphabetically into subdirectories so that
it can easly find the path to any file in your playlists and modify it
accordingly.

Note:
You should move all your media files to propper locations before running
this command (see Media folder).

Although it is possible to put the files in other locations than default
[Media folder], but then you will have to enter the mapping manually
every time you run this tool (see Relocate).

---------
Playlists
---------
Will scan all playlists from Winamp Media Library playlists.xml or any
provided playlist file. Will replace all paths to absolute ones (see Q&A).

There are 4 stages of validating each playlist entry:

  a) Check that the entry is pointing to an existing file. If not, then:
  b) Check that file exists in [Media folder] by testing the first letter. If not, then:
  c) Check that file exists in mapped location (see Relocate). If not, then:
  d) Ask for an action:

     [1] Update - enter path manualy for current entry
     [2] Relocate - mass change path for all files from current location
     [3] Remove - remove current entry
     [4] Skip (default) - leave current entry and skip to next one
     [5] Exit - return to prompt line

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
		$this->addOption( 'remove', null, InputOption::VALUE_NONE, 'Remove missing paths' );
		$this->addOption( 'no-backup', null, InputOption::VALUE_NONE, 'Do not backup modified playlists' );
		$this->addOption( 'format', 'f', InputOption::VALUE_REQUIRED, 'Output format: m3u | m3u8 (implicitly enables --force when input format differs)' );
		$this->addOption( 'no-ext', null, InputOption::VALUE_NONE, 'Skip all #EXTINF lines (will not read Id3 tags from media files)' );
		$this->addOption( 'force', null, InputOption::VALUE_NONE, 'Refresh playlist file even if nothing has been modified, ie. refreshes #M3U tags' );

		parent::moreOptions();
	}

	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		$this->Logger->notice( '=========================' );
		$this->Logger->notice( 'Rebuild Winamp playlists:' );
		$this->Logger->notice( '=========================' );
		$this->Logger->debug( 'Args: ' . Utils::print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		$infile = $input->getOption( 'infile' );

		if ( ! in_array( Utils::fileExt( $infile ), $this->inputExt ) ) {
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

		if ( ! is_file( $inputFile ) ) {
			throw new \InvalidArgumentException( sprintf( "Playlist file not found. Was trying:\n%s", Utils::implode( $locations, ",\n" ) ) );
		}

		if ( ! is_dir( $mediaDir = Utils::pathToAbs( $input->getArgument( 'media-folder' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Media folder "%s" not found in "%s"', $input->getArgument( 'media-folder' ), getcwd() ) );
		}

		if ( ! is_dir( $escapeDir = Utils::pathToAbs( $mediaDir . '/' . $input->getOption( 'esc' ), getcwd() ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Escape folder "%s" not found in "%s"', $input->getOption( 'esc' ), $mediaDir ) );
		}

		if ( ! empty( $format = $input->getOption( 'format' ) ) && ! in_array( $format, $this->outputExt ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unsuported output format "%s"', $format ) );
		}

		$this->Logger->debug( 'Resolved [Media folder] : ' . $mediaDir );
		$this->Logger->debug( 'Resolved [Escape folder]: ' . $escapeDir );

		$Tagger = $input->getOption( 'no-ext' ) ? null : $this->Factory->createM3UTagger();
		$codePage = $input->getOption( 'code-page' ); // For *.m3u files only
		$dirMap = []; // Mass replace paths

		// =============================================================================================================
		// Each playlist
		// =============================================================================================================
		foreach ( $this->getPlaylists( $inputFile ) as $playlistName => $playlistPath ) {

			$this->Logger->notice( sprintf( 'Loading [%s]: %s', $playlistName, $playlistPath ) );

			//$Playlist = $this->Factory->create( 'PlaylistBuilder', $playlistPath, $codePage, $Tagger );
			$Playlist = $this->Factory->createPlaylistBuilder( $playlistPath, $codePage, $Tagger );
			$items = $Playlist->items();

			// ---------------------------------------------------------------------------------------------------------
			// PRE Duplicates
			if ( $input->getOption( 'dupes' ) ) {
				$this->Logger->notice( 'Removing duplicates...' );
				$dupes = $Playlist->duplicates( true );

				if ( $i = count( $dupes ) ) {
					$this->Logger->info( sprintf( 'Dupes removed (%d):', $i ) );
					foreach ( $dupes as $entry => $ids ) {
						$this->Logger->info( sprintf( 'x%d - %s', count( $ids ), $entry ) );
					}
				}
			}

			// =========================================================================================================
			// Each mp3 (without dupes!)
			// =========================================================================================================
			foreach ( $Playlist->items() as $key => $item ) {

				// -------------------------------------------------------------------------------------------------
				// #1: File is missing in original location?
				// Wath out for realpath() - also returns dir paths or current dir for empty arg!
				if ( ! $itemPath = Utils::pathToAbs( $item['line'], $Playlist->home() ) ) {

					$this->Logger->debug( sprintf( 'Not found (#1): "%s" (at %s)', $item['line'], $Playlist->home() ) );

					// -------------------------------------------------------------------------------------------------
					// #2: File is missing in $pathMap?
					$basePath = dirname( $item['line'] );

					if ( ! isset( $dirMap[$basePath] ) || ! $itemPath = realpath( "{$dirMap[$basePath]}/{$item['name']}" ) ) {

						$this->Logger->debug( sprintf( 'Not found (#2): no path mapping for "%s"', $basePath ) );

						// ---------------------------------------------------------------------------------------------
						// #3: File is missing in Media dir?
						$dirDes = $this->matchDir( $mediaDir, $item['name'] ) ?: $input->getOption( 'esc' );
						$dirDesPath = "{$mediaDir}/{$dirDes}/{$item['name']}";

						if ( ! $itemPath = realpath( $dirDesPath ) ) {

							$this->Logger->debug( sprintf( 'Not found (#3): not in match dir "%s"', $dirDesPath ) );

							// -----------------------------------------------------------------------------------------
							// Missing in all locations - Ask user!
							if ( ! $removeMissing = $input->getOption( 'remove' ) ) {

								$this->Logger->notice( 'Invalid path: ' . $item['line'] );

								/* @formatter:off */
								$question = new ChoiceQuestion( 'Please select an action:', [
									1 => 'Update',
									2 => 'Relocate',
									3 => 'Remove',
									4 => 'Skip',
									5 => 'Exit',
								], 4 );
								/* @formatter:on */

								$helper = $this->getHelper( 'question' );
								switch ( $helper->ask( $input, $output, $question ) )
								{
									case 'Update':
										$question = new Question( 'New path: ' );
										$question->setValidator( function ( $answer ) {
											if ( ! is_file( $answer ) ) {
												throw new \InvalidArgumentException( 'File not found! ' );
											}
											return $answer;
										} );
										$itemPath = $helper->ask( $input, $output, $question );
										$this->Logger->debug( sprintf( 'New path "%s"', $itemPath ) );
										break;
									case 'Relocate':
										$question = new Question( sprintf( 'Replace all occurences of "%s" to: ', $basePath ) );
										$question->setValidator( function ( $answer ) use ($item ) {
											if ( ! is_file( $path = "{$answer}/{$item['name']}" ) ) {
												throw new \InvalidArgumentException( sprintf( 'Not found "%s"', $path ) );
											}
											return $answer;
										} );
										$dirMap[$basePath] = $helper->ask( $input, $output, $question );
										$itemPath = realpath( "{$dirMap[$basePath]}/{$item['name']}" );
										$this->Logger->debug( sprintf( 'Map path "%s" to "%s"', $basePath, $dirMap[$basePath] ) );
										$this->Logger->debug( sprintf( 'Rename "%s" to "%s"', $item['line'], $itemPath ) );
										break;
									case 'Remove':
										$removeMissing = true;
										break;
									case 'Exit':
										$this->Logger->warning( 'User Exit' );
										return Command::FAILURE;
									default:
										$this->Logger->notice( 'Skipping...' );
										continue 2; // break & continue ... foreach mp3
								}
							}

							// -----------------------------------------------------------------------------------------
							// Remove
							if ( $removeMissing ) {
								$this->Logger->debug( sprintf( 'Remove "%s" from [%s] (%s)', $item['line'], $playlistName, $playlistPath ) );
								$Playlist->remove( $key );
								continue;
							}
						}
					}
				}

				if ( ! is_file( $itemPath ) ) {
					/* @formatter:off */
					throw new \UnexpectedValueException( sprintf(
						'Computed path "%s" is invalid for playlist entry "%s"',
						$itemPath,
						$item['line']
					));
					/* @formatter:on */
				}

				// -----------------------------------------------------------------------------------------------------
				// Update
				if ( $itemPath != $item['line'] ) {
					$this->Logger->info( 'Update path:' );
					$this->Logger->info( sprintf( '<-- %s', $item['line'] ) );
					$this->Logger->info( sprintf( '--> %s', $itemPath ) );
					$Playlist->path( $key, $itemPath );
				}

				// =====================================================================================================
				// Next mp3...
				// =====================================================================================================
			}

			count( $dirMap ) && $this->Logger->debug( 'Relocation MAP: ' . Utils::print_r( $dirMap ) );

			// ---------------------------------------------------------------------------------------------------------
			// POST Duplicates
			// Some paths might be resolved to same location!
			if ( $input->getOption( 'dupes' ) ) {
				$this->Logger->notice( 'Removing duplicates...' );
				$dupes = $Playlist->duplicates( true );

				if ( $i = count( $dupes ) ) {
					$this->Logger->info( sprintf( 'Dupes removed (%d):', $i ) );
					foreach ( $dupes as $entry => $ids ) {
						$this->Logger->info( sprintf( 'x%d - %s', count( $ids ), $entry ) );
					}
				}
			}

			// ---------------------------------------------------------------------------------------------------------
			// Sort
			if ( $input->getOption( 'sort' ) ) {
				$this->Logger->notice( 'Sorting...' );
				$Playlist->sort();
			}

			// ---------------------------------------------------------------------------------------------------------
			// Playlist stats
			$stats = $Playlist->stats();

			if ( $stats['removed']['count'] ) {
				$this->Logger->info( sprintf( 'Removed (%d):', $stats['removed']['count'] ) );
				foreach ( $stats['removed']['items'] as $val ) {
					$this->Logger->info( sprintf( '--- %s', $val ) );
				}
			}

			/* @formatter:off */
			$this->Logger->notice( sprintf(
				'Updated %2$d paths in [%1$s]: %3$d moved, %4$d removed, %5$s dupes --> %6$d before, %7$d after (%8$d erased)',
				$playlistName,
				$stats['moved']['count'] + $stats['removed']['count'] + $stats['dupes']['count'],
				$stats['moved']['count'],
				$stats['removed']['count'],
				$input->getOption( 'dupes' ) ? $stats['dupes']['count'] : '?',
				$before = count( $items ),
				$after = count( $Playlist->items() ),
				$before - $after
				));
			/* @formatter:on */

			// ---------------------------------------------------------------------------------------------------------
			// Logic...
			$isDry = $input->getOption( 'dry-run' );
			$isBackup = ! $input->getOption( 'no-backup' );
			$outFormat = $input->getOption( 'format' ) ?: $Playlist->type();

			$isForce = $input->getOption( 'force' );
			$isForce |= $outFormat != $Playlist->type();

			// ---------------------------------------------------------------------------------------------------------
			// Save
			if ( $Playlist->isDirty() || $isForce ) {

				$force_str = ! $Playlist->isDirty() && $isForce ? ' +force' : '';
				$backup_str = $isBackup ? ' +backup' : '';

				$save = $Playlist->save( ! $isDry, $isBackup, $outFormat );

				$this->Logger->notice( sprintf( "Saved [%s]%s%s", basename( $save['file'] ), $force_str, $backup_str ) );
				$this->Logger->info( 'File: ' . $save['file'] );
				$isBackup && $this->Logger->info( 'Back: ' . ( $save['back'] ?: '---' ) );
			}
			else {
				$this->Logger->notice( sprintf( 'No changes in [%s]', $playlistName ) );
			}

			// =========================================================================================================
			// Next playlist...
			// =========================================================================================================
		}

		return Command::SUCCESS;
	}
}
