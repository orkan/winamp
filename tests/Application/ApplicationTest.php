<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2023 Orkan <orkans+winamp@gmail.com>
 */
use Orkan\Winamp\Factory;
use Orkan\Winamp\Application\Application;
use PHPUnit\Framework\TestCase;

/**
 * Test suite
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class ApplicationTest extends TestCase
{

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers Helpers
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 */
	public function testCommonOptionsExists()
	{
		$Application = new Application( new Factory() );
		$Definition = $Application->getDefinition();

		$this->assertTrue( $Definition->hasOption( 'code-page' ) );
		$this->assertTrue( $Definition->hasShortcut( 'c' ) );
		$this->assertTrue( $Definition->hasOption( 'user-cfg' ) );
		$this->assertTrue( $Definition->hasShortcut( 'u' ) );
		$this->assertTrue( $Definition->hasOption( 'dry-run' ) );
		$this->assertTrue( $Definition->hasOption( 'no-log' ) );
		$this->assertTrue( $Definition->hasOption( 'no-debug' ) );
	}

	/**
	 */
	public function testDefaultConfigExists()
	{
		new Application( $Factory = new Factory() );

		$this->assertNotEquals( 'n/a', $Factory->cfg( 'winamp_playlists' ), 'Missing cfg[winamp_playlists]' );
		$this->assertNotEquals( 'n/a', $Factory->cfg( 'code_page' ), 'Missing cfg[code_page]' );
	}
}
