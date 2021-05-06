<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Tests;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;

/**
 *
 * @author Orkan <orkans@gmail.com>
 */
class FactoryMock extends \Orkan\Winamp\Factory
{
	public $stubs;
	protected $Logger;

	public function logger()
	{
		if ( ! isset( $this->Logger ) ) {

			/* @formatter:off */
			$this->merge([
				'log_level'    => Logger::DEBUG,
				'log_keep'     => 0,
				'log_file'     => __DIR__ . '/_log/' . $this->cfg( 'test_class' ) . '.log',
				'log_timezone' => 'Europe/Warsaw',
				'log_datetime' => 'Y-m-d H:i:s',
			]);
			/* @formatter:on */

			$this->Logger = new Logger( 'ch2', [], [], new \DateTimeZone( $this->cfg['log_timezone'] ) );
			$Handler = new RotatingFileHandler( $this->cfg['log_file'], $this->cfg['log_keep'], $this->cfg['log_level'] );
			$Handler->setFormatter( new LineFormatter( "[%datetime%] %level_name%: %message%\n", $this->cfg['log_datetime'] ) );
			$this->Logger->pushHandler( $Handler );
		}

		return $this->Logger;
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	public function stub( $name )
	{
		return $this->stubs[$name];
	}

	/**
	 * @return \Orkan\Winamp\Tags\M3UTagger
	 */
	public function createM3UTagger()
	{
		if ( isset( $this->stubs['M3UTagger'] ) ) {
			return $this->stubs['M3UTagger'];
		}

		return parent::createM3UTagger();
	}

	/**
	 * @param mixed ...$args
	 * @return \Orkan\Winamp\Playlists\PlaylistBuilder
	 */
	public function createPlaylistBuilder( ...$args )
	{
		if ( isset( $this->stubs['PlaylistBuilder'] ) ) {
			return $this->stubs['PlaylistBuilder'];
		}

		return parent::createPlaylistBuilder( $args[0], $args[1], $args[2] );
	}
}
