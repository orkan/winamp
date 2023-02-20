<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2023 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Orkan\Utils;
use Orkan\Winamp\Application\Application;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Factory / Dependency Injection
 *
 * Because of complex setup the Logger class is hardcoded here.
 * All other services should be defined in $this->cfg
 * Class name: $this->cfg['Service']
 *
 * @author Orkan <orkans+winamp@gmail.com>
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
	 */
	public function cfg( string $key = '', $val = null )
	{
		if ( isset( $val ) ) {
			$this->cfg[$key] = $val;
		}

		if ( '' === $key ) {
			return $this->cfg;
		}

		return $this->cfg[$key] ?? '';
	}

	/**
	 * Merge defaults with current config
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
		if ( !isset( $this->Logger ) ) {

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

			if ( !$Input->hasParameterOption( '--no-log', true ) ) {
				$Handler = new RotatingFileHandler( $this->cfg['log_file'], $this->cfg['log_keep'], $this->cfg['log_level'] );
				$Handler->setFormatter( new LineFormatter( "[%datetime%] %level_name%: %message%\n", $this->cfg['log_datetime'] ) );
				$this->Logger->pushHandler( $Handler );

				$this->Logger->info( '______________________' . Application::APP_NAME . '______________________' );
				$this->Logger->debug( 'Command line: ' . $Input );
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
	 * Assign CLI output to Console Handler
	 *
	 * When?
	 * If using standalone Logger instance b/c ConsoleOutput is OFF by default.
	 * @see \Symfony\Bridge\Monolog\Handler\ConsoleHandler
	 *
	 * How?
	 * @see \Symfony\Component\Console\Application::run()
	 * @see \Symfony\Component\Console\Application::configureIO()
	 * @see \Symfony\Component\Console\Command\Command::initialize()
	 * $Factory->initConsoleHandler( new ConsoleOutput( OutputInterface::VERBOSITY_VERBOSE ) );
	 */
	public function initConsoleHandler( ConsoleOutput $output = null )
	{
		if ( null === $output ) {
			$output = new ConsoleOutput();
		}

		foreach ( $this->logger()->getHandlers() as $Handler ) {
			if ( $Handler instanceof ConsoleHandler ) {
				$Handler->setOutput( $output );
			}
		}
	}

	/**
	 * Create new object
	 *
	 * @param  string $name    Object name to create
	 * @param  mixed  ...$args Object args passed to constructor
	 * @return mixed           New object
	 */
	public function create( string $name, ...$args )
	{
		$class = $this->cfg( $name );
		return new $class( ...$args );
	}
}
