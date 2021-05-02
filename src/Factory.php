<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Orkan\Utils;
use Orkan\Winamp\Application\Application;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Factory / Dependency Injection
 *
 * Because of complex setup the Logger class is hardcoded here.
 * All other services should be defined in $this->cfg
 * Class name: $this->cfg['Service']
 *
 * @author Orkan <orkans@gmail.com>
 */
class Factory
{
	protected $cfg = [];
	protected $Logger;

	/**
	 * Pass configuration via file, cmd or both
	 */
	public function __construct( array $cfg = [] )
	{
		$usr = ( new ArgvInput() )->getParameterOption( [ '--user-cfg', '-u' ], false, true );
		$usr = $usr ? require $usr : [];
		$this->cfg = array_merge( $cfg, $usr );
	}

	/**
	 * Set/Get config value
	 *
	 * @param string|null $key
	 * @param string|null $val
	 * @return mixed
	 */
	public function cfg( string $key = null, $val = null )
	{
		if ( ! isset( $key ) ) {
			return $this->cfg;
		}

		if ( isset( $val ) ) {
			$this->cfg[$key] = $val;
		}

		return $this->cfg[$key] ?? 'n/a';
	}

	/**
	 * Merge defaults with current config
	 *
	 * @param array $cfg
	 */
	public function merge( array $defaults )
	{
		$this->cfg = array_merge( $defaults, $this->cfg );
	}

	/**
	 * Get Logger instance
	 *
	 * @return \Monolog\Logger
	 */
	public function logger()
	{
		if ( ! isset( $this->Logger ) ) {

			/* @formatter:off */
			$this->merge([
				'log_level'    => Logger::DEBUG,
				'log_keep'     => 0,
				'log_file'     => dirname( __DIR__ ) . '/Winamp.log',
				'log_timezone' => 'Europe/Berlin',
				'log_datetime' => 'Y-m-d H:i:s',
			]);
			/* @formatter:on */

			$this->Logger = new Logger( 'ch1', [], [], new \DateTimeZone( $this->cfg['log_timezone'] ) );
			$Input = new ArgvInput();

			if ( ! $Input->hasParameterOption( '--no-log', true ) ) {
				$Handler = new RotatingFileHandler( $this->cfg['log_file'], $this->cfg['log_keep'], $this->cfg['log_level'] );
				$Handler->setFormatter( new LineFormatter( "[%datetime%] %level_name%: %message%\n", $this->cfg['log_datetime'] ) );
				$this->Logger->pushHandler( $Handler );

				$this->Logger->notice( '______________________' . Application::APP_NAME . '______________________' );
				$this->Logger->notice( 'Command line: ' . $Input );
				$this->Logger->debug( sprintf( 'CFG: [%s():%d] %s', __CLASS__, __LINE__, Utils::print_r( $this->cfg ) ) );
			}

			/* @formatter:off */
			$Handler = new ConsoleHandler( null, true, [
				OutputInterface::VERBOSITY_QUIET        => Logger::WARNING, // -q
				OutputInterface::VERBOSITY_NORMAL       => Logger::NOTICE,  //
				OutputInterface::VERBOSITY_VERBOSE      => Logger::INFO,    // -v
				OutputInterface::VERBOSITY_VERY_VERBOSE => Logger::DEBUG,   // -vv
				OutputInterface::VERBOSITY_DEBUG        => Logger::DEBUG,   // -vvv
			]);
			/* @formatter:on */

			$Handler->setFormatter( new LineFormatter( "%message%\n" ) );
			$this->Logger->pushHandler( $Handler );
		}

		return $this->Logger;
	}

	/**
	 * Can't find a way to pass $args as function arguments - and not as an array ??? ...
	 *
	 * @param string $key
	 * @param array ...$args
	 * @return mixed
	 public static function create( string $key, ...$args )
	 {
	 $class = self::cfg( $key );
	 return new $class( $args ); <-- $args as array !!!
	 }
	 */

	/**
	 * @return \Orkan\Winamp\Tags\M3UTagger
	 */
	public function createM3UTagger()
	{
		$class = $this->cfg( 'M3UTagger' );
		return new $class();
	}

	/**
	 * @param mixed ...$args
	 * @return \Orkan\Winamp\Playlists\PlaylistBuilder
	 */
	public function createPlaylistBuilder( ...$args )
	{
		$class = $this->cfg( 'PlaylistBuilder' );
		return new $class( $args[0], $args[1], $args[2] );
		//return $this->create( 'PlaylistBuilder', ...$args );
	}
}
