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

/**
 *
 * @author Orkan <orkans@gmail.com>
 */
class Application extends BaseApplication
{
	const APP_NAME = 'Winamp Media Library CLI tools by Orkan';
	const APP_VERSION = 'v2.0.1';
	const RELEASE_DATE = 'Sat, 01 May 2021 12:34:11 +02:00';

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
