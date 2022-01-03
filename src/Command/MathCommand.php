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

/**
 * Add, substract playlists
 *
 * @author Orkan <orkans@gmail.com>
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
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		$this->setDescription( 'Add or substract two playlists' );
		$this->setHelp( 'Add: a + b = o. Substract: a - b = o.' );

		$extensions = PlaylistBuilder::supportedTypes();
		$this->addArgument( 'a', InputArgument::REQUIRED, "Playlist A ($extensions)" );
		$this->addArgument( 'b', InputArgument::REQUIRED, "Playlist B ($extensions)" );
		$this->addArgument( 'o', InputArgument::REQUIRED, "Output playlist ($extensions)" );
		$this->addOption( 'method', 'm', InputOption::VALUE_REQUIRED, 'Math method: add|sub', 'sub' );
		$this->addOption( 'sort', null, InputOption::VALUE_NONE, 'Sort output playlist' );
		$this->addOption( 'no-ext', null, InputOption::VALUE_NONE, 'Do not create #EXTINF lines in Output playlist' );
		$this->addOption( 'no-backup', null, InputOption::VALUE_NONE, 'Do not backup overwriten playlist' );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Command\Command::execute()
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
			if ( !in_array( Utils::fileExt( $pl['path'] ), PlaylistBuilder::SUPPORTED_TYPES ) ) {
				throw new \InvalidArgumentException( sprintf( 'Playlist "%s" not in supproted extensions: %s', $pl['path'], PlaylistBuilder::supportedTypes() ) );
			}
			if ( in_array( $opt, [ 'pla', 'plb' ] ) && !is_file( $pl['path'] ) ) {
				throw new \InvalidArgumentException( sprintf( '%s not found in: --%s "%s"', $pl['label'], $opt, $pl['path'] ) );
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
		$pla = $this->Factory->create( 'PlaylistBuilder', $pls['pla']['path'] )->paths( 'orig' );
		$plb = $this->Factory->create( 'PlaylistBuilder', $pls['plb']['path'] )->paths( 'orig' );

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

		$Tagger = $input->getOption( 'no-ext' ) ? null : $this->Factory->create( 'M3UTagger' );
		$codePage = $input->getOption( 'code-page' );
		$Playlist = $this->Factory->create( 'PlaylistBuilder', $pls['out']['path'], $Tagger, [ 'cp' => $codePage ] );
		$Playlist->add( $out );

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
