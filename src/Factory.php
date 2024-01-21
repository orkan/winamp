<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp;

use Monolog\Formatter\LineFormatter;
use Orkan\Logger;
use Orkan\Utils;
use Orkan\Winamp\Tools\Exporter;
use Orkan\Winamp\Tools\M3UTagger;
use Orkan\Winamp\Tools\Playlist;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Winamp Factory
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class Factory extends \Orkan\Factory
{
	/*
	 * Services:
	 */
	protected $Exporter;
	protected $Utils;
	protected $Logger;
	protected $Output;

	/**
	 * Pass configuration via file, cmd or both.
	 */
	public function __construct( array $cfg = [] )
	{
		parent::__construct( $cfg );
		$this->merge( $this->defaults() );

		// High priority CMD args!
		$usr = ( new ArgvInput() )->getParameterOption( [ '--user-cfg', '-u' ], false, true );
		$usr = $usr ? require $usr : [];
		$this->merge( $usr, true );
	}

	/**
	 * Get defaults.
	 */
	protected function defaults()
	{
		/**
		 * [log_header]
		 * Add extra header after Logger init
		 *
		 * [log_console]
		 * Log to Console
		 *
		 * [log_console_verbosity]
		 * Log to Console verbosity level
		 * @see OutputInterface
		 *
		 * [bar_format]
		 * ProgressBar format
		 * @see ProgressBar::initFormats()
		 *
		 * [bar_char]
		 * ProgressBar character
		 *
		 * [bar_char_empty]
		 * ProgressBar empty character
		 *
		 * @formatter:off */
		return [
			'log_header'            => true,
			'log_console'           => true,
			'log_console_verbosity' => OutputInterface::VERBOSITY_VERBOSE,
			'bar_format'            => '[%bar%] %current%/%max% %message%',
			'bar_char'              => '|',
			'bar_char_empty'        => '.',
		];
		/* @formatter:on */
	}

	/*
	 * -----------------------------------------------------------------------------------------------------------------
	 * SERVICES
	 * -----------------------------------------------------------------------------------------------------------------
	 */

	/**
	 * @return Exporter
	 */
	public function Exporter()
	{
		return $this->Exporter ?? $this->Exporter = new Exporter( $this );
	}

	/**
	 * @return Utils
	 */
	public function Utils()
	{
		return $this->Utils ?? $this->Utils = new Utils();
	}

	/**
	 * @return Logger
	 */
	public function Logger()
	{
		if ( !isset( $this->Logger ) ) {

			// ---------------------------------------------------------------------------------------------------------
			// File:
			$Input = new ArgvInput();
			if ( $Input->hasParameterOption( '--no-log', true ) ) {
				$this->cfg( 'log_file', '' );
			}

			$this->Logger = new Logger( $this );

			// ---------------------------------------------------------------------------------------------------------
			if ( $this->get( 'log_console' ) ) {
				/* @formatter:off */
				$Handler = new ConsoleHandler( null, true, [
					OutputInterface::VERBOSITY_QUIET        => Logger::ERROR,   // -q
					OutputInterface::VERBOSITY_NORMAL       => Logger::NOTICE,  //
					OutputInterface::VERBOSITY_VERBOSE      => Logger::INFO,    // -v
					OutputInterface::VERBOSITY_VERY_VERBOSE => Logger::DEBUG,   // -vv
					OutputInterface::VERBOSITY_DEBUG        => Logger::DEBUG,   // -vvv
				]);
				/* @formatter:on */
				$Handler->setOutput( $this->Output() );
				$Handler->setFormatter( new LineFormatter( "%message%\n" ) );
				$this->Logger->Monolog()->pushHandler( $Handler );
			}

			// ---------------------------------------------------------------------------------------------------------
			// Header:
			if ( $this->get( 'log_header' ) ) {
				$this->Logger->debug( '______________________' . Application::APP_NAME . '______________________' );
				$this->Logger->debug( 'Command line: ' . $Input );
				$this->Logger->debug( sprintf( 'CFG: [%s:%d] %s', __CLASS__, __LINE__, Utils::print_r( $this->cfg ) ) );
			}
		}

		return $this->Logger;
	}

	/**
	 * @return OutputInterface
	 */
	public function Output()
	{
		return $this->Output ?? $this->Output = new ConsoleOutput( $this->get( 'log_console_verbosity' ) );
	}

	/**
	 * @return ProgressBar
	 */
	public function ProgressBar( int $steps = 10, string $format = '' )
	{
		$format = $format ?: 'bar_format';
		ProgressBar::setFormatDefinition( $format, $this->get( $format ) );

		$ProgressBar = new ProgressBar( $this->Output(), $steps );
		$ProgressBar->setFormat( $format );
		$ProgressBar->setBarCharacter( $this->get( 'bar_char' ) );
		$ProgressBar->setProgressCharacter( $this->get( 'bar_char' ) );
		$ProgressBar->setEmptyBarCharacter( $this->get( 'bar_char_empty' ) );

		return $ProgressBar;
	}

	/**
	 * @return M3UTagger
	 */
	public function M3UTagger()
	{
		return new M3UTagger();
	}

	/**
	 * @return Playlist
	 */
	public function Playlist( array $cfg = [] )
	{
		return new Playlist( $this, $cfg );
	}
}
