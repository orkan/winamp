<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022 Orkan <orkans+winamp@gmail.com>
 */

/**
 * bin/console
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
use Orkan\Winamp\Factory;
use Orkan\Winamp\Application;
use Symfony\Component\Console\Input\ArgvInput;

if ( !in_array( PHP_SAPI, [ 'cli', 'phpdbg', 'embed' ], true ) ) {
	fwrite( STDERR, sprintf( "Warning:\nThe console should be invoked via the CLI version of PHP, not the %s SAPI\n\n", PHP_SAPI ) );
}

foreach ( [ __DIR__ . '/../../../autoload.php', __DIR__ . '/../../vendor/autoload.php', __DIR__ . '/vendor/autoload.php' ] as $file ) {
	if ( file_exists( $file ) ) {
		define( 'WINAMP_COMPOSER_INSTALL', $file );
		break;
	}
}

if ( !defined( 'WINAMP_COMPOSER_INSTALL' ) ) {
	fwrite( STDERR, "You need to set up the project dependencies using Composer:\n\n\tcomposer install orkan/winamp\n\n" );
	die( 1 );
}

set_time_limit( 0 );
require WINAMP_COMPOSER_INSTALL;

$Input = new ArgvInput();
if ( $Input->hasParameterOption( '--no-debug', true ) ) {
	putenv( 'APP_DEBUG=' . $_ENV['APP_DEBUG'] = '0' );
}
define( 'DEBUG', (bool) getenv( 'APP_DEBUG' ) );

unset( $file );
unset( $Input );

// ---------------------------------------------------------------------------------------------------------------------
// Run
$Factory = new Factory();
$Application = new Application( $Factory );
$Application->setCatchExceptions( false );
$Application->setAutoExit( false );

try {
	$Application->run();
}
catch ( Throwable $e ) {
	$Factory->Logger()->error( $e->getMessage() );
	echo sprintf( "\nIn %s line %d:\n\n  [%s]\n  %s\n\n", basename( $e->getFile() ), $e->getLine(), get_class( $e ), $e->getMessage() );

	// https://symfony.com/doc/current/console/verbosity.html
	if ( (int) getenv( 'SHELL_VERBOSITY' ) > 0 ) {
		echo $e . "\n";
	}

	exit( $e->getCode() ?: 1 );
}
