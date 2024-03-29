<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Commands;

use Orkan\Winamp\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Get varius App info
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class AppCommand extends Command
{
	protected static $defaultName = 'app';

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
	{
		$headerText = 'Get varius info about "' . Application::APP_NAME . '" and display it in command window:';
		$headerLine = str_repeat( '-', strlen( $headerText ) );

		$this->setDescription( 'Get varius App info' );
		$this->setHelp( <<<EOT
{$headerText}
{$headerLine}
name    - Display App name
version - Display App version
logfile - Display current log file path
EOT );
		$this->addArgument( 'type', InputArgument::OPTIONAL, 'Type of information. See --help for options.', 'name' );
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Commands\Command::execute()
	 */
	protected function execute( InputInterface $input, OutputInterface $output )
	{
		parent::execute( $input, $output );

		switch ( $input->getArgument( 'type' ) )
		{
			case 'name':
				$this->Logger->notice( Application::APP_NAME );
				break;

			case 'version':
				$this->Logger->notice( Application::APP_VERSION );
				break;

			case 'logfile':
				$this->Logger->notice( $this->Utils->pathFix( $this->Logger->getFilename() ) );
				break;
		}

		return Command::SUCCESS;
	}
}
