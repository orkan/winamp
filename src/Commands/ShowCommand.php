<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Render playlists.xml file
 * Retrive playlists array through getPalyslists() public method.
 *
 * @author Orkan <orkans+winamp@gmail.com>
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

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		$this->setDescription( 'Display Winamp playlists' );
		$this->setHelp( 'Display user defined Winamp playlists' );

		$this->addOption( 'infile', 'i', InputOption::VALUE_REQUIRED, 'Winamp playlists file', $this->Factory->get( 'winamp_playlists' ) );

		$vals = implode( ' | ', $this->sort );
		$this->addOption( 'sort', 's', InputOption::VALUE_REQUIRED, "Sort playlists by: {$vals}.", $this->sort[0] );

		$vals = implode( ' | ', $this->dir );
		$this->addOption( 'dir', 'd', InputOption::VALUE_REQUIRED, "Sort direction: {$vals}.", $this->dir[0] );

		$vals = implode( ' | ', $this->format );
		$this->addOption( 'format', 'f', InputOption::VALUE_REQUIRED, "Display format: {$vals}.", $this->format[0] );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Commands\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		/* @formatter:off */
		$args = [
			'infile' => realpath( $input->getOption( 'infile' ) ),
			'sort'   => $input->getOption( 'sort' ),
			'dir'    => $input->getOption( 'dir' ),
			'format' => $input->getOption( 'format' ),
		];
		/* @formatter:on */

		/* @formatter:off */
		$this->Factory->info([
			'=',
			'{command}',
			'Input file: "{inputFile}"',
			'=',
		],[
			'{command}'   => $this->getDescription(),
			'{inputFile}' => $args['infile'],
		]);
		/* @formatter:on */

		DEBUG && $this->Logger->debug( 'Args: ' . $this->Utils->print_r( array_merge( $input->getOptions(), $input->getArguments() ) ) );

		$pls = $this->Factory->Winamp()->loadPlaylists( $args['infile'] );
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
			$this->Utils->arraySortMulti( $pls, $args['sort'], 'asc' === $args['dir'] );
			$isSort = true;
		}

		/* @formatter:off */
		$isSort && $this->Factory->info( 'Sort playlists by: {sort} | {dir}', [
			'{sort}' => $args['sort'],
			'{dir}'  => $args['dir'],
		]);
		/* @formatter:on */

		DEBUG && $isSort && $this->logPlaylistArray( $pls );

		$Table = new Table( $output );
		switch ( $args['format'] )
		{
			case 'formated':
				$Table->setHeaders( [ 'Playlist [title]', 'Songs [songs]', 'Duration [seconds]' ] );
				foreach ( $pls as $pl ) {
					/* @formatter:off */
					$Table->addRow( [
						$pl['title'],
						sprintf( "%13s", $pl['songs'] ),
						sprintf( "%18s", $this->Utils->timeString( $pl['seconds'], false ) ),
					] );
					/* @formatter:on */
				}
				break;

			case 'raw':
			default:
				$l = strlen( count( $pls ) );
				$baseDir = dirname( $args['infile'] );
				$Table->setHeaders( array_merge( [ 'Lp', 'On' ], array_keys( current( $pls ) ) ) );

				$i = 0;
				foreach ( $pls as $pl ) {
					// Justify right:
					$lp = sprintf( "%{$l}s", ++$i );
					$on = file_exists( $baseDir . '/' . $pl['filename'] ) ? '+' : ' ';
					$pl['songs'] = sprintf( "%6s", $pl['songs'] );
					$pl['seconds'] = sprintf( "%8s", $pl['seconds'] );

					$Table->addRow( array_merge( [ $lp, $on ], $pl ) );
				}
				break;
		}
		$Table->setStyle( 'box-double' );
		$Table->render();

		// -------------------------------------------------------------------------------------------------------------
		// Summary
		if ( $this->Logger->is( 'INFO' ) ) {
			$stats = $this->stats( $pls );
			/* @formatter:off */
			$this->Factory->notice( 'Playlists: {count} | Songs: {songs} | Duration: {seconds}', [
				'{count}'   => $stats['count'],
				'{songs}'   => $stats['songs'],
				'{seconds}' => $this->Utils->timeString( $stats['seconds'], false )
			]);
			/* @formatter:on */
		}

		return Command::SUCCESS;
	}

	/**
	 * Playlists statistics
	 */
	protected function stats( array $pls ): array
	{
		$stats = [ 'count' => 0, 'songs' => 0, 'seconds' => 0 ];
		foreach ( $pls as $val ) {
			$stats['count']++;
			$stats['songs'] += $val['songs'];
			$stats['seconds'] += $val['seconds'];
		}
		return $stats;
	}

	/**
	 * List playlists.
	 */
	protected function logPlaylistArray( array $arr ): void
	{
		$i = 0;
		foreach ( $arr as $pl ) {
			$this->Logger->debug( sprintf( 'Playlist #%d: %s', ++$i, $this->Utils->print_r( $pl ) ) );
		}
	}
}
