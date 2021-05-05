<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Command;

use Orkan\Utils;
use Orkan\Winamp\Factory;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;

/**
 * Shared class for all Winamp commands
 * Part A: Methods to extend Command object
 * Part B: Various methods shared across diferent commands
 *
 * @author Orkan <orkans@gmail.com>
 */
class Command extends BaseCommand
{
	protected static $defaultName = 'common';

	protected $input;
	protected $output;

	/**
	 * List of subdirs in [Media folder]
	 *
	 * @var array
	 */
	private $matchDirs = [];

	/**
	 * @var \Monolog\Logger
	 */
	protected $Logger;

	/**
	 * @var \Orkan\Winamp\Factory
	 */
	protected $Factory;

	// =========================================================================================================
	// Part A: Methods to extend Command object
	// =========================================================================================================

	/**
	 * @param Factory $Factory
	 */
	public function __construct( Factory $Factory )
	{
		$Factory->merge( $this->defaults() );

		$this->Logger = $Factory->logger(); // just a shorthand
		$this->Factory = $Factory;

		parent::__construct();
	}

	/**
	 * Get default config
	 *
	 * @return array Default config
	 */
	private function defaults(): array
	{
		/* @formatter:off */
		return [
			// Services:
			'M3UTagger'       => 'Orkan\\Winamp\\Tags\\M3UTagger',
			'PlaylistBuilder' => 'Orkan\\Winamp\\Playlists\\PlaylistBuilder',
		];
		/* @formatter:on */
	}

	/**
	 * Assign output for Console Handler
	 * @see \Symfony\Bridge\Monolog\Handler\ConsoleHandler
	 *
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::initialize()
	 */
	protected function initialize( InputInterface $input, OutputInterface $output )
	{
		foreach ( $this->Logger->getHandlers() as $Handler ) {
			if ( $Handler instanceof ConsoleHandler ) {
				$Handler->setOutput( $output );
			}
		}
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
		$this->Logger->info( 'Extracting playlists from: ' . $file );

		foreach ( $this->loadPlaylistsXml( $file ) as $val ) {

			if ( is_file( $loc = $base . DIRECTORY_SEPARATOR . $val['filename'] ) ) {
				$pls[$val['title']] = $loc;
			}
			else {
				$this->Logger->warning( 'Failed to locate: ' . $val['filename'] );
			}
		}

		$this->Logger->info( sprintf( 'Found %d playlists', count( $pls ) ) );
		return $pls;
	}

	/**
	 * Load playlists.xml into array
	 *
	 * @param string $xmlFile
	 * @return array
	 */
	protected function loadPlaylistsXml( string $xmlFile ): array
	{
		$Xml = simplexml_load_file( $xmlFile );

		$out = [];
		foreach ( $Xml->playlist as $playlist ) {

			$attr = [];
			foreach ( $playlist->attributes() as $k => $v ) {

				$key = (string) $k;
				$val = (string) $v;

				// The 'title' can be number like, but we need it as !string! later in sort()
				// Other numeric attributes should remain integers
				$val = 'title' == $key || ! is_numeric( $val ) ? $val : (int) $val;

				$attr[$key] = $val;
			}
			$out[] = $attr;
		}

		return $out;
	}
}
