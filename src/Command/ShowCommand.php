<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Command;

use Orkan\Utils;
use Orkan\Winamp\Playlists\PlaylistBuilder;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Render playlists.xml file
 * Retrive playlists array through getPalyslists() public method.
 *
 * @author Orkan <orkans@gmail.com>
 */
class ShowCommand extends Command
{
	protected static $defaultName = 'show';

	/*
	 * Options:
	 * Tip: option[0] works as default
	 */
	private $sort = [ 'lp', 'filename', 'title', 'id', 'songs', 'seconds' ];
	private $dir = [ 'asc', 'desc' ];
	private $format = [ 'raw', 'formated' ];

	protected function configure()
	{
		$this->setDescription( 'Display Winamp playlists' );
		$this->setHelp( 'Display user defined Winamp playlists' );

		$this->addOption( 'infile', 'i', InputOption::VALUE_REQUIRED, 'Winamp playlists file', $this->Factory->cfg( 'winamp_playlists' ) );

		$vals = implode( ' | ', $this->sort );
		$this->addOption( 'sort', 's', InputOption::VALUE_REQUIRED, "Sort playlists by: {$vals}.", $this->sort[0] );

		$vals = implode( ' | ', $this->dir );
		$this->addOption( 'dir', 'd', InputOption::VALUE_REQUIRED, "Sort direction: {$vals}.", $this->dir[0] );

		$vals = implode( ' | ', $this->format );
		$this->addOption( 'format', 'f', InputOption::VALUE_REQUIRED, "Display format: {$vals}.", $this->format[0] );
	}

	protected function execute( InputInterface $input, OutputInterface $output )
	{
		$this->Logger->notice( '=================' );
		$this->Logger->notice( 'Winamp playlists:' );
		$this->Logger->notice( '=================' );

		/* @formatter:off */
		$args = [
			'infile' => $input->getOption( 'infile' ),
			'sort'   => $input->getOption( 'sort' ),
			'dir'    => $input->getOption( 'dir' ),
			'format' => $input->getOption( 'format' ),
		];
		/* @formatter:on */
		$this->Logger->debug( 'Input arguments: ' . Utils::print_r( $args ) );

		$pls = $this->loadPlaylistsXml( $args['infile'] );
		DEBUG && $this->logPlaylistArray( $pls );

		if ( empty( $pls ) ) {
			throw new \Exception( 'Empty playlist!' );
		}

		$isSort = false;
		if ( 'lp' == $args['sort'] ) {
			if ( 'desc' == $args['dir'] ) {
				$pls = array_reverse( $pls, true );
				$isSort = true;
			}
		}
		else {
			PlaylistBuilder::sortPlaylist( $pls, $args['sort'], $args['dir'] );
			$isSort = true;
		}

		$isSort && $this->Logger->info( sprintf( 'Sorting playlists by %s, %s:', $args['sort'], $args['dir'] ) );
		DEBUG && $isSort && $this->logPlaylistArray( $pls );

		$table = new Table( $output );
		switch ( $args['format'] )
		{
			case 'raw':
				$l = strlen( count( $pls ) );
				$table->setHeaders( array_merge( [ 'Lp' ], array_keys( $pls[0] ) ) );

				foreach ( $pls as $key => $val ) {
					// Justify right:
					$lp = sprintf( "%{$l}s", $key + 1 );
					$val['songs'] = sprintf( "%6s", $val['songs'] );
					$val['seconds'] = sprintf( "%8s", $val['seconds'] );

					$table->addRow( array_merge( [ $lp ], $val ) );
				}
				break;

			case 'formated':
				$table->setHeaders( [ 'Playlist [title]', 'Songs [songs]', 'Duration [seconds]' ] );
				foreach ( $pls as $val ) {
					/* @formatter:off */
					$table->addRow( [
						$val['title'],
						sprintf( "%13s", $val['songs'] ),
						sprintf( "%18s", Utils::formatTime( $val['seconds'], false ) ),
					] );
					/* @formatter:on */
				}

				break;

			default:
				throw new \OutOfBoundsException( 'Unsuported --format switch' );
		}
		$table->setStyle( 'box-double' );
		$table->render();

		return Command::SUCCESS;
	}

	/**
	 * List playlists
	 *
	 * @param array $arr
	 */
	protected function logPlaylistArray( array $arr ): void
	{
		array_walk( $arr, function ( $val, $key ) {
			$this->Logger->debug( sprintf( 'Playlist #%d: %s', $key + 1, Utils::print_r( $val ) ) );
		} );
	}
}
