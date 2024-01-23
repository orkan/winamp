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
	/**
	 * @var ProgressBar
	 */
	protected $Bar;

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
		 * [cmd_title]
		 * Default CMD window title
		 * @see Factory::cmdTitle()
		 *
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
		 * [bar_default]
		 * ProgressBar format
		 * @see ProgressBar::initFormats()
		 *
		 * [bar_char]
		 * ProgressBar character
		 *
		 * [bar_char_empty]
		 * ProgressBar empty character
		 *
		 * [bar_usleep]
		 * ProgressBar slow down
		 *
		 * @formatter:off */
		return [
			'cmd_title'             => 'CMD Factory',
			'app_version'           => '5.2.1',
			'log_header'            => true,
			'log_console'           => true,
			'log_console_verbosity' => OutputInterface::VERBOSITY_VERBOSE,
			'bar_default'           => '[%bar%] %current%/%max% %message%',
			'bar_loading'           => '- loading [%bar%] %current%/%max% %message%',
			'bar_char'              => '|',
			'bar_char_empty'        => '.',
			'bar_usleep'            => getenv( 'APP_BAR_USLEEP' ) ?: 0,
		];
		/* @formatter:on */
	}

	/**
	 * Update CMD window title.
	 *
	 * @param array  $tokens Array( [%token1%] => text1, [%token2%] => text2, ... )
	 * @param string $format Eg. '%token1% - %token2% - %title%'
	 */
	public function cmdTitle( array $tokens = [], string $format = '%cmd_title%' ): void
	{
		$tokens['%cmd_title%'] = $this->get( 'cmd_title' );
		cli_set_process_title( strtr( $format, $tokens ) );
	}

	/**
	 * Slow down.
	 */
	public function sleep( string $key ): void
	{
		$ms = (int) $this->get( $key );
		$ms && usleep( $ms );
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
	 * Callback Playlist->onLoad()
	 */
	public function onPlaylistLoad( int $current, int $count, string $path, ?array $item )
	{
		if ( 1 == $current ) {
			$this->barNew( $count, 'bar_loading' );
		}

		if ( $item ) {
			$this->barInc( $item['name'] );
		}

		if ( $current == $count ) {
			$this->barDel();
		}
	}

	/**
	 * Create progress bar.
	 * @link https://symfony.com/doc/current/components/console/helpers/progressbar.html#bar-settings
	 */
	public function barNew( int $steps = 10, string $format = 'bar_default' ): void
	{
		if ( !$steps || defined( 'TESTING' ) ) {
			return; // Don't display empty bar
		}

		ProgressBar::setFormatDefinition( $format, $this->get( $format ) );

		$this->Bar = new ProgressBar( $this->Output(), $steps );
		$this->Bar->setFormat( $format );
		$this->Bar->setBarCharacter( $this->get( 'bar_char' ) );
		$this->Bar->setProgressCharacter( $this->get( 'bar_char' ) );
		$this->Bar->setEmptyBarCharacter( $this->get( 'bar_char_empty' ) );
		$this->Bar->setMessage( '' ); // Get rid of %message% string displayed in case there are 0 steps performed
		$this->Bar->setRedrawFrequency( 1 ); // redraws the screen every each iteration
		$this->Bar->start();
		$this->sleep( 'bar_usleep' ); // give time to show step [1]
	}

	/**
	 * Distroy progress bar.
	 *
	 * Make sure the ProgressBar properly displays final state.
	 * Sometimes it doesn't render the last step if the redrawFreq is too low.
	 *
	 * @param bool $clear  Clear %message%
	 * @param bool $finish Set Bar to 100%
	 */
	public function barDel( bool $clear = false, bool $finish = false ): void
	{
		if ( !$this->Bar ) {
			return;
		}

		$clear && $this->Bar->setMessage( '' );
		$finish && $this->Bar->finish();

		// force refresh!
		$this->Bar->display();

		// New line after Progress Bar
		$this->Output->writeln( '' );

		$this->Bar = null;
	}

	/**
	 * Increment progress bar.
	 *
	 * @param string $msg     Formatted %message%
	 * @param int    $advance Increment by...
	 */
	public function barInc( string $msg = '', int $advance = 1 ): void
	{
		if ( !$this->Bar ) {
			return;
		}
		$this->Bar->setMessage( $msg );
		$this->Bar->advance( $advance );
		$this->sleep( 'bar_usleep' );
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
