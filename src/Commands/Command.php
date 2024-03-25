<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

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
	protected function execute( InputInterface $Input, OutputInterface $Output )
	{
		$this->input = $Input;
		$this->output = $Output;

		// Only PHPUnit script args are available during tests!
		if ( defined( 'TESTING' ) ) {
			return Command::FAILURE;
		}

		if ( $Input->getOption( 'dry-run' ) ) {
			$Output->setVerbosity( max( OutputInterface::VERBOSITY_NORMAL, $Output->getVerbosity() ) );
		}

		// Match Orkan\Logger verbosity to CMD --verbose
		$this->Factory->cfg( 'log_verbose', $this->Factory::VERBOSITY[$Output->getVerbosity()] );

		// This is a generic command (abstract?)
		return Command::FAILURE;
	}

	/**
	 * Get user input: Yes|No.
	 */
	protected function confirm( $question )
	{
		$Helper = $this->getHelper( 'question' );
		$Question = new ConfirmationQuestion( "$question y/[n]: ", false );
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
		$this->Factory->info( 'Load "%s"', $file );

		foreach ( Winamp::loadPlaylists( $file ) as $val ) {

			if ( is_file( $loc = $base . DIRECTORY_SEPARATOR . $val['filename'] ) ) {
				$pls[$val['title']] = $loc;
			}
			else {
				$this->Factory->warning( 'Failed to locate "%s"', $val['filename'] );
			}
		}

		$this->Logger->info( '- playlists: ' . count( $pls ) );
		return $pls;
	}
}
