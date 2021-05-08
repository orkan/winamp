<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2021 Orkan <orkans@gmail.com>
 */
namespace Orkan\Winamp\Application;

use Orkan\Winamp\Command;
use Orkan\Winamp\Factory;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputOption;

/**
 *
 * @author Orkan <orkans@gmail.com>
 */
class Application extends BaseApplication
{
	const APP_NAME = 'Winamp Media Library CLI tools by Orkan';
	const APP_VERSION = 'v2.2.1';
	const RELEASE_DATE = 'Sat, 08 May 2021 17:42:59 +02:00';

	/**
	 * @link https://patorjk.com/software/taag/#p=display&v=0&f=Graffiti&t=Winamp
	 * Tip: Replace all \\ to \\\
	 */
	private static $logo = ' __      __.__
/  \    /  \__| ____ _____    _____ ______
\   \/\/   /  |/    \\\__  \  /     \\\___  \
 \        /|  |   |  \/ __ \|  Y Y  \ |_\  \
  \__/\  / |__|___|__(____  /__|_|__/   ___/
       \/                 \/        |__|
';

	/**
	 * @var \Orkan\Winamp\Factory
	 */
	private $Factory;

	public function __construct( Factory $Factory )
	{
		parent::__construct( self::APP_NAME, self::APP_VERSION );

		$this->Factory = $Factory;
		$this->Factory->merge( $this->defaults() );
	}

	private function defaults(): array
	{
		/* @formatter:off */
		return [
			'winamp_playlists' => getenv( 'APPDATA' ) . '\\Winamp\\Plugins\\ml\\playlists.xml',
			'code_page'        => function_exists( 'sapi_windows_cp_get' ) ? 'Windows-' . sapi_windows_cp_get( 'ansi' ) : 'ASCII',
		];
		/* @formatter:on */
	}

	/**
	 * Insert common options here, shared by all Commands
	 *
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Application::getDefaultInputDefinition()
	 */
	protected function getDefaultInputDefinition()
	{
		$Definition = parent::getDefaultInputDefinition();

		/* @formatter:off */
		$Definition->addOptions( [
			new InputOption( 'user-cfg' ,  'u', InputOption::VALUE_REQUIRED, 'User config (ie. logger settings)', false ),
			new InputOption( 'code-page',  'c', InputOption::VALUE_REQUIRED, 'M3U files encoding', $this->Factory->cfg( 'code_page' ) ),
			new InputOption( 'dry-run'  , null, InputOption::VALUE_NONE    , 'Outputs the operations but will not save any files (implicitly enables --verbose)' ),
			new InputOption( 'no-log'   , null, InputOption::VALUE_NONE    , 'Turns off logging to file' ),
			new InputOption( 'no-debug' , null, InputOption::VALUE_NONE    , 'Turns off debug info. Also resets APP_DEBUG environment variable' ),
		]);
		/* @formatter:on */

		return $Definition;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDefaultCommands()
	{
		/* @formatter:off */
		$commands = array_merge( parent::getDefaultCommands(), [
			new Command\ShowCommand( $this->Factory ),
			new Command\RebuildCommand( $this->Factory ),
		]);
		/* @formatter:on */

		return $commands;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHelp()
	{
		return self::$logo . parent::getHelp();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLongVersion()
	{
		return sprintf( '%s (%s)', parent::getLongVersion(), self::RELEASE_DATE );
	}
}
