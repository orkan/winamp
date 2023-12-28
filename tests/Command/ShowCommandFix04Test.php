<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2023 Orkan <orkans+winamp@gmail.com>
 */
use Orkan\Winamp\Application\Application;
use Orkan\Winamp\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test suite
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class ShowCommandFix04Test extends TestCase
{
	protected static $fixture = 'Fix04';

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	protected function setUp(): void
	{
		parent::setUp();

		/**
		 * Create Command
		 *
		 * Method 1: Build Application and retrieve command
		 * ------------------------------------------------
		 * $Application = new Application( self::$Factory );
		 * self::$Command = $Application->find( 'rebuild' );
		 *
		 * Method 2: Create Command and assign helpers manually
		 * ----------------------------------------------------
		 * self::$Command = new Command\RebuildCommand( self::$Factory );
		 * self::$Command->setHelperSet( new HelperSet( [ new QuestionHelper() ] ) );
		 *
		 * Helpers:
		 * QuestionHelper: required for testing "User input" - not available on Windows :(
		 */
		$Application = new Application( self::$Factory );
		self::$Command = $Application->find( 'show' );

		/**
		 * Commad + Tester
		 * @link https://symfony.com/doc/current/console.html#testing-commands
		 */
		self::$CommandTester = new CommandTester( self::$Command );

		/*
		 * Stubs aren't used since we'll use mocked playlists files instead
		 self::$Factory->stubs( 'M3UTagger', $this->createStub( self::$Factory->cfg( 'M3UTagger' ) ) );
		 self::$Factory->stubs( 'PlaylistBuilder', $this->createStub( self::$Factory->cfg( 'PlaylistBuilder' ) ) );
		 */
	}

	protected function tearDown(): void
	{
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [playlists01_dummy.xml]: Parse xml file
	 */
	public function testCanShowPlaylistSortedByFilenameDesc()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/playlists01_dummy.xml',
				'expect' => self::$dir['ml'] . '/playlists01_dummy-show_sort_filename_desc.txt',
			],
		];

		self::$CommandTester->execute( [
			'--infile' => $data['files']['infile'],
			'--sort'   => 'filename',
			'--dir'    => 'desc',
		] );
		/* @formatter:on */

		$output = self::$CommandTester->getDisplay();
		$this->assertSame( $output, file_get_contents( $data['files']['expect'] ) );
	}

	/**
	 * [playlists01_dummy.xml]: Parse xml file
	 */
	public function testCanShowPlaylistFormated()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/playlists01_dummy.xml',
				'expect' => self::$dir['ml'] . '/playlists01_dummy-show_formated.txt',
			],
		];

		self::$CommandTester->execute( [
			'--infile' => $data['files']['infile'],
			'--format' => 'formated',
		] );
		/* @formatter:on */

		$output = self::$CommandTester->getDisplay();
		$this->assertSame( $output, file_get_contents( $data['files']['expect'] ) );
	}
}
