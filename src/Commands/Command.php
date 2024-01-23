<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Orkan\Logging;
use Orkan\Utils;
use Orkan\Winamp\Factory;
use Orkan\Winamp\Tools\Winamp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;

/**
 * Shared class for all Winamp commands
 * Part A: Methods to extend Command object
 * Part B: Various methods shared across diferent commands
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class Command extends \Symfony\Component\Console\Command\Command
{
	use Logging;

	protected static $defaultName = 'common';
	protected $input;
	protected $output;

	/**
	 * Console Progress Bar
	 * @var \Symfony\Component\Console\Helper\ProgressBar
	 */
	protected $Bar;

	/**
	 * List of subdirs in [Media folder]
	 *
	 * @var array
	 */
	private $matchDirs = [];

	/*
	 * Services:
	 */
	protected $Factory;
	protected $Utils;
	protected $Logger;

	// =========================================================================================================
	// Part A: Methods to extend Command object
	// =========================================================================================================
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory;
		$this->Utils = $this->Factory->Utils();
		$this->Logger = $this->Factory->Logger();
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		if ( $input->getOption( 'dry-run' ) ) {
			$output->setVerbosity( max( OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity() ) );
		}

		$this->input = $input;
		$this->output = $output;

		return Command::FAILURE;
	}

	protected function confirm( $question )
	{
		$Helper = $this->getHelper( 'question' );
		$Question = new ConfirmationQuestion( "$question [y/N]: ", false );
		return $Helper->ask( $this->input, $this->output, $Question );
	}

	// =========================================================================================================
	// Part B: Various methods shared across diferent commands
	// =========================================================================================================

	/**
	 * Find correct sub-dir in [Media folder] for a given [filename]
	 *
	 * @param string $mediaDir [Media folder]
	 * @param string $filename
	 * @return string Correct sub-dir name
	 */
	protected function matchDir( string $mediaDir, string $filename ): string
	{
		if ( empty( $this->matchDirs ) ) {
			$Finder = Finder::create()->directories( '[*]' )->depth( '== 0' )->in( $mediaDir );
			foreach ( $Finder as $dir ) {
				$this->matchDirs[] = basename( $dir );
			}
		}

		$dest = '';
		foreach ( $this->matchDirs as $dir ) {
			if ( preg_match( "~$dir~i", $filename[0] ) ) {
				$dest = $dir;
				break;
			}
		}
		return $dest;
	}

	/**
	 * Validate & return playlist path(s)
	 *
	 * @param string $file Single playlist file or playlists.xml
	 * @return array Playlists paths Array( 'Pl Name' => 'path' )
	 */
	protected function getPlaylists( string $file ): array
	{
		if ( 'xml' != Utils::fileExt( $file ) ) {
			return [ basename( $file ) => $file ];
		}

		$pls = [];
		$base = dirname( $file );
		$this->info( 'Load "%s"', $file );

		foreach ( Winamp::loadPlaylists( $file ) as $val ) {

			if ( is_file( $loc = $base . DIRECTORY_SEPARATOR . $val['filename'] ) ) {
				$pls[$val['title']] = $loc;
			}
			else {
				$this->warning( 'Failed to locate "%s"', $val['filename'] );
			}
		}

		$this->info( '- playlists: %d', count( $pls ) );
		return $pls;
	}
}
