<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp;

use Orkan\Winamp\Tools\M3UTagger;
use Orkan\Winamp\Tools\Playlist;
use Orkan\Winamp\Tools\Winamp;
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

	/* @formatter:off */

	/**
	 * Map verbosity levels: Symfony Console <==> Orkan\Logger console.
	 * @see ConsoleOutput
	 * @see \Orkan\Logger::addRecord() > cfg[log_verbose]
	 */
	const VERBOSITY = [
		OutputInterface::VERBOSITY_QUIET        => 'ERROR',  //  16 => 400 -q
		OutputInterface::VERBOSITY_NORMAL       => 'NOTICE', //  32 => 250
		OutputInterface::VERBOSITY_VERBOSE      => 'INFO',   //  64 => 200 -v
		OutputInterface::VERBOSITY_VERY_VERBOSE => 'DEBUG',  // 128 => 100 -vv
		OutputInterface::VERBOSITY_DEBUG        => 'DEBUG',  // 256 => 100 -vvv
	];
	/* @formatter:on */

	/*
	 * Services:
	 */
	protected $Output;

	/**
	 * Pass configuration via file, cmd or both.
	 */
	public function __construct( array $cfg = [] )
	{
		parent::__construct();
		$this->merge( self::defaults(), true );
		$this->merge( $cfg, true );

		/**
		 * User config.
		 *
		 * CAUTION:
		 * The [-c arg] is used to satisfy both apps: Winamp\Application (Symfony) and Tools\Exporter (Orkan)
		 * @see Tools\Exporter::defaults(app_opts)       <-- Orkan\Application
		 * @see Application::getDefaultInputDefinition() <-- Symfony\Application
		 */
		$Args = new ArgvInput();
		if ( $cfg = $Args->getParameterOption( [ '--user-cfg', '-c' ], false, true ) ) {
			$this->merge( require $cfg, true );
			$this->cfg( 'cfg_user', realpath( $cfg ) );
		}
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
		 * [log_verbose]
		 * Default Logger verbosity level
		 * @see \Orkan\Logger::addRecord() > echo
		 * The exact value of --verbose is aciqured in
		 * @see \Orkan\Application::setVerbosity()
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
		 * [bar_debug]
		 * Progress bar slow down with [Enter]
		 *
		 * @formatter:off */
		return [
			'log_header'      => true,
			'log_verbose'     => 'NOTICE',
			'bar_verbose'     => OutputInterface::VERBOSITY_VERBOSE, // -v, INFO
			'bar_default'     => '[%bar%] %current%/%max% %message%',
			'bar_loading'     => '- loading [%bar%] %current%/%max% %message%',
			'bar_char'        => '|',
			'bar_char_empty'  => '.',
			'bar_usleep'      => getenv( 'BAR_USLEEP' ) ?: 0,
			'bar_debug'       => getenv( 'BAR_DEBUG' ) ?: false,
		];
		/* @formatter:on */
	}

	/*
	 * -----------------------------------------------------------------------------------------------------------------
	 * SERVICES
	 * -----------------------------------------------------------------------------------------------------------------
	 */

	/**
	 * ConsoleOutput is used for displaying Symfony components only, ie. ProgressBar, Table, etc...
	 * @return OutputInterface
	 */
	public function Output()
	{
		if ( !isset( $this->Output ) ) {
			// Match ConsoleOutput verbosity to Orkan\Logger
			$verbosity = array_search( $this->get( 'log_verbose' ), static::VERBOSITY );
			$this->cfg( 'out_verbose', $verbosity );

			$this->Output = new ConsoleOutput( $verbosity );
		}

		return $this->Output;
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
		// Don't display empty Bar
		if ( !$steps || defined( 'TESTING' ) ) {
			return;
		}

		/**
		 * Don't display Bar in less verbose modes
		 * Output verbosity is mapped from cfg[log_verbosity]
		 * @see Factory::Output()
		 */
		if ( $this->Output()->getVerbosity() < $this->get( 'bar_verbose' ) ) {
			return;
		}

		$this->Bar = new ProgressBar( $this->Output(), $steps );
		$this->Bar->setFormat( $this->get( $format ) );
		$this->Bar->setBarCharacter( $this->get( 'bar_char' ) );
		$this->Bar->setProgressCharacter( $this->get( 'bar_char' ) );
		$this->Bar->setEmptyBarCharacter( $this->get( 'bar_char_empty' ) );
		$this->Bar->setMessage( '' ); // Get rid of %message% string displayed in case there are 0 steps performed
		$this->Bar->setRedrawFrequency( 1 ); // redraws the screen every each iteration
		$this->Bar->start();
		DEBUG && $this->sleep( 'bar_usleep' ); // give time to show step [1]
		DEBUG && $this->get( 'bar_debug' ) && $this->Utils->stdin(); // Hit [Enter] to continue...
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

	/**
	 * @return Winamp
	 */
	public function Winamp()
	{
		return new Winamp();
	}
}
