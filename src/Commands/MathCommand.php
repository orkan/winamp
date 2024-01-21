<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Orkan\Utils;
use Orkan\Winamp\Tools\Playlist;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add, substract playlists
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class MathCommand extends Command
{
	protected static $defaultName = 'math';

	/* @formatter:off */
	private $methods = [
		'sub' => 'Substract',
		'add' => 'Add',
	];
	/* @formatter:on */

	/**
	 * Supported file extensions (*.m3u, *.pls, ...).
	 * @var string
	 */
	private $types;

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		$this->setDescription( 'Add or substract two playlists' );
		$this->setHelp( 'Add: a + b = o. Substract: a - b = o.' );

		$Playlist = new Playlist( $this->Factory );
		$this->types = $Playlist->get( 'types' );

		$this->addArgument( 'a', InputArgument::REQUIRED, "Playlist A ($this->types)" );
		$this->addArgument( 'b', InputArgument::REQUIRED, "Playlist B ($this->types)" );
		$this->addArgument( 'o', InputArgument::REQUIRED, "Output playlist ($this->types)" );
		$this->addOption( 'method', 'm', InputOption::VALUE_REQUIRED, 'Math method: add|sub', 'sub' );
		$this->addOption( 'sort', null, InputOption::VALUE_NONE, 'Sort output playlist' );
		$this->addOption( 'no-ext', null, InputOption::VALUE_NONE, 'Do not create #EXTINF lines in Output playlist' );
		$this->addOption( 'no-backup', null, InputOption::VALUE_NONE, 'Do not backup overwriten playlist' );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Commands\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		$method = $input->getOption( 'method' );
		$isDry = $input->getOption( 'dry-run' );

		/* @formatter:off */
		$pls = [
			'pla' => [ 'path' => $input->getArgument( 'a' ), 'label' => 'Playlist A'    ],
			'plb' => [ 'path' => $input->getArgument( 'b' ), 'label' => 'Playlist B' ],
			'out' => [ 'path' => $input->getArgument( 'o' ), 'label' => 'Output playlist' ],
		];
		/* @formatter:on */

		// -------------------------------------------------------------------------------------------------------------
		// Validate:
		foreach ( $pls as $opt => $pl ) {
			if ( !in_array( Utils::fileExt( $pl['path'] ), Playlist::SUPPORTED_TYPES ) ) {
				throw new \InvalidArgumentException( sprintf( 'Playlist "%s" not in supproted extensions: %s',
					/**/ $pl['path'],
					/**/ $this->types ) );
			}
			if ( in_array( $opt, [ 'pla', 'plb' ] ) && !is_file( $pl['path'] ) ) {
				throw new \InvalidArgumentException( sprintf( '%s not found in: --%s "%s"',
					/**/ $pl['label'],
					/**/ $opt,
					/**/ $pl['path'] ) );
			}
		}

		if ( !in_array( $method, [ 'add', 'sub' ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Method "%s" is not supported yet :(', $method ) );
		}

		$this->Logger->info( '=========================' );
		$this->Logger->info( $this->methods[$method] . ' playlists:' );
		$this->Logger->info( '=========================' );
		$this->Logger->debug( 'Args: ' . Utils::print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		// =============================================================================================================
		// Run:
		// =============================================================================================================
		$pla = $this->Factory->Playlist( [ 'file' => $pls['pla']['path'] ] )->paths( 'orig' );
		$plb = $this->Factory->Playlist( [ 'file' => $pls['plb']['path'] ] )->paths( 'orig' );

		$out = array_diff( $pla, $plb );

		if ( 'add' == $method ) {
			$out = array_merge( $out, $plb );
		}
		// -------------------------------------------------------------------------------------------------------------

		// Output playlist
		// Clear output file if it exists
		if ( !$isDry && is_file( $pls['out']['path'] ) ) {
			unlink( $pls['out']['path'] );
			touch( $pls['out']['path'] );
		}

		/* @formatter:off */
		$Playlist = $this->Factory->Playlist([
			'file' => $pls['out']['path'],
			'tags' => !$input->getOption( 'no-ext' ),
			'cp'   => $input->getOption( 'code-page' ),
		]);
		/* @formatter:on */

		$Playlist->insert( $out );

		// Stats
		$cPla = count( $pla );
		$cPlb = count( $plb );
		$cOut = count( $out );

		$this->Logger->info( 'Results:' );

		switch ( $method )
		{
			case 'add':
				$this->Logger->notice( sprintf( '%d + %d = %d', $cPla, $cPlb, $cOut ) );
				break;

			case 'sub':
				$this->Logger->notice( sprintf( '%d - %d (of %d) = %d', $cPla, ( $cPla - $cOut ), $cPlb, $cOut ) );
				break;
		}

		// ---------------------------------------------------------------------------------------------------------
		// Sort
		if ( $input->getOption( 'sort' ) ) {
			if ( $Playlist->sort() ) {
				$this->Logger->info( sprintf( 'Sort' ) );
			}
		}

		// ---------------------------------------------------------------------------------------------------------
		// Save
		$isBackup = !$input->getOption( 'no-backup' );

		$strBackup = $isBackup ? ' +backup' : '';
		$save = $Playlist->save( !$isDry, $isBackup, '', 'orig' ); // save original path entries

		$this->Logger->info( sprintf( "Save [%s]%s", basename( $pls['out']['path'] ), $strBackup ) );
		$this->Logger->info( sprintf( 'Saved "%s"', $save['file'] ) );
		$isBackup && $this->Logger->info( sprintf( 'Back "%s"', $save['back'] ?: '---' ) );

		return Command::SUCCESS;
	}
}
