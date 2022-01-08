<?php
/*
 * This file is part of the orkan/winamp package.
 *
 * Copyright (c) 2022 Orkan <orkans@gmail.com>
 */
use Orkan\Utils;
use Orkan\Tests\Utils as TestsUtils;
use Orkan\Winamp\Application\Application;
use Orkan\Winamp\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;

/**
 * Test suite
 *
 * @author Orkan <orkans@gmail.com>
 */
class RebuildCommandFix01Test extends TestCase
{
	protected static $fixture = 'Fix01';

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
		self::$Command = $Application->find( 'rebuild' );

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
		self::removeFiles( self::$dir['ml'], [ '*.bak', '*.m3u', '*.m3u8' ] );
	}

	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests: Tests:
	// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [pl01.m3u8]: Don't save playlist file if the playlist wasn't modified
	 * @see PlaylistBuilderTest::testCanSkipSavingToFile()
	 */
	public function testCanSkipSavingUnmodifiedPlaylist()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01.m3u8',
			],
		];
		/* @formatter:on */

		// Create "pl01.m3u8" file
		self::parseFiles( $data );

		// Set the modification time 1 hour before, so we can clearly see if the command updated the playlist file
		touch( $data['files']['infile'], $time = time() - 3600 );

		$expTime = filemtime( $data['files']['infile'] );
		$expSum = crc32( file_get_contents( $data['files']['infile'] ) );

		// Make sure we're all set!
		$this->assertEquals( $time, $expTime, 'Test preparation failed!' );

		/* @formatter:off */
		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			//'--force'    => false, // <-- default
			'--no-ext'     => false, // <-- even "turning ON" EXTINF shouldn't re-save playlist if it wasnt changed!
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$actTime = filemtime( $data['files']['infile'] );
		$actSum = crc32( file_get_contents( $data['files']['infile'] ) );

		$this->assertEquals( $expTime, $actTime, 'Command re-saved unmodified playlist without --force switch!' );
		$this->assertEquals( $expSum, $actSum, 'Command changed playlist file!' );
	}

	/**
	 * [pl01.m3u8]: Insert #EXTM3U
	 */
	public function testCanInsertExtinfTags()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01.m3u8',
				'expect' => self::$dir['ml'] . '/pl01_extinf.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--force'      => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl01.m3u8]: Backup original playlist
	 */
	public function testCanCreateBackups()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01.m3u8',
			],
		];
		/* @formatter:on */

		self::parseFiles( $data );
		$backups = 2;

		for ( $i = 0; $i < $backups; $i++ ) {
			/* @formatter:off */
			self::$CommandTester->execute( [
				'--infile'     => $data['files']['infile'],
				'--force'      => true,
				'--no-ext'     => true,
				'media-folder' => self::$dir['media'],
			] );
			/* @formatter:on */
		}

		$result = ( new Finder() )->name( '*.bak' )->in( self::$dir['ml'] )->count();
		$this->assertSame( $backups, $result, 'Backup files mismatch' );
	}

	/**
	 * [pl01.m3u8]: Sort playlist
	 */
	public function testCanSortPlaylist()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl01.m3u8',
				'expect' => self::$dir['ml'] . '/pl01_sorted.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--sort'       => true,
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl02.m3u8]: Fix paths
	 */
	public function testCanFixPaths()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u8',
				'expect' => self::$dir['ml'] . '/pl02_fix_paths.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl02_remove_dupes.m3u8]: Remove duplicates
	 */
	public function testCanRemoveDuplicates()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl02.m3u8',
				'expect' => self::$dir['ml'] . '/pl02_remove_dupes.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--dupes'      => true,
			'--sort'       => true,
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl03.m3u8]: m3u8 -> m3u
	 */
	public function testCanSavePlaylistToAnsi1250()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl03.m3u8',
				'expect' => self::$dir['ml'] . '/pl03_ansi.[Windows-1250].m3u',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--format'     => 'm3u',
			'--code-page'  => 'Windows-1250', // same codepage as in: Fix01/ml/pl03_ansi.[Windows-1250].m3u.php
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$infile = Utils::fileNoExt( $data['files']['infile'] ); // skip extension
		$this->assertSame( $data['body']['expect'], file_get_contents( $infile . '.m3u' ) );
	}

	/**
	 * [pl03_ansi.[Windows-1250].m3u]: m3u -> m3u8
	 */
	public function testCanSavePlaylistToUtf8()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl03_ansi.[Windows-1250].m3u',
				'expect' => self::$dir['ml'] . '/pl03.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--format'     => 'm3u8',
			'--code-page'  => 'Windows-1250', // same codepage as in: Fix01/ml/pl03_ansi.[Windows-1250].m3u.php
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$infile = Utils::fileNoExt( $data['files']['infile'] ); // skip extension
		$this->assertSame( $data['body']['expect'], file_get_contents( $infile . '.m3u8' ) );
	}

	/**
	 * [pl04.m3u8]: esc folder
	 */
	public function testCanFindFileInEscFolder()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl04.m3u8',
				'expect' => self::$dir['ml'] . '/pl04_esc_dir.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--esc'        => '_esc',
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl05.m3u8]: Leave absolute path if outside of media folder
	 */
	public function testCanFindFileOutOfMediaFolder()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl05.m3u8',
				'expect' => self::$dir['ml'] . '/pl05_outside.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--force'      => true,
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [playlists_.m3u8]: playlist.xml
	 */
	public function testCanReadXmlPlaylists()
	{
		/* @formatter:off */
		$data = [
			'files' => [ // In playlists.xml:
				'Pl 1' => self::$dir['ml'] . '/playlists_01.m3u8',
				'Pl 2' => self::$dir['ml'] . '/playlists_02.m3u8',
				'Pl 3' => self::$dir['ml'] . '/playlists_03.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => self::$dir['ml'] . '/playlists.xml',
			'--format'     => 'm3u', // Force save as *.m3u
			'--no-ext'     => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		foreach ( $data['files'] as $file ) {
			$this->assertFileExists( Utils::fileNoExt( $file ) . '.m3u' );
		}
	}

	/**
	 * [empty.m3u]: Can read empty playlist
	 * Expect no exception!
	 */
	public function testCanReadEmptyPlaylistAnsi()
	{
		/* @formatter:off */
		self::$CommandTester->execute( [
			'--infile'     => self::$dir['fixtures'] . '/empty.m3u',
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->expectNotToPerformAssertions();
	}

	/**
	 * [empty.m3u8]: Can read empty playlist (with BOM)
	 * Expect no exception!
	 */
	public function testCanReadEmptyPlaylistUtf8()
	{
		/* @formatter:off */
		self::$CommandTester->execute( [
			'--infile'     => self::$dir['fixtures'] . '/empty.m3u8',
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->expectNotToPerformAssertions();
	}

	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Interaction: Interaction: Interaction: Interaction: Interaction: Interaction: Interaction: Interaction:
	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * [pl06_user.m3u8] Missing path: User input -> [1] Update
	 *
	 * Simulate user input - wont work on Windows !!!
	 *
	 * On Windows systems Symfony uses a special binary to implement hidden questions. This means that
	 * those questions don’t use the default Input console object and therefore you can’t test them on Windows.
	 *
	 * @link https://symfony.com/doc/current/components/console/helpers/questionhelper.html#testing-a-command-that-expects-input
	 */
	public function _testCanAskUserForNewPath()
	{
		$this->markTestIncomplete( 'This test has not been implemented yet' );

		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl06_user.m3u8',
				'expect' => self::$dir['ml'] . '/pl06_user_update.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		self::$CommandTester->setInputs( [ '1', realpath( self::$dir['sandbox'] . '/media/[A-A]/Audio1-2sec.mp3' ) ] );

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl07_user_relocate.m3u8] Missing path: User input -> [2] Relocate
	 */
	public function testCanAskUserForRelocationPath()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl07_user.m3u8',
				'expect' => self::$dir['ml'] . '/pl07_user_relocate.m3u8',
			],
		];

		$abs = realpath( self::$dir['sandbox'] );
		$dirMap = [
			"$abs\\outside 1"  => self::$dir['sandbox'] . '/_outside',
			"..\\to\\outside2" => self::$dir['sandbox'] . '/_outside',
			"to\\media A-A"    => self::$dir['media'] . '/[A-A]',
		];
		/* @formatter:on */

		self::parseFiles( $data );

		/*
		 * Simulate user input - wont work on Windows !!!
		 * Hack: Simulate user answers by replacing internal property
		 */
		TestsUtils::setPrivateProperty( self::$Command, 'dirMap', $dirMap );

		/* @formatter:off */
		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl08_user_rename.m3u8] Missing path: User input -> [3] Rename
	 */
	public function testCanAskUserForRenamePattern()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl08_user.m3u8',
				'expect' => self::$dir['ml'] . '/pl08_user_rename.m3u8',
			],
		];

		$regMap = [
			'Baudio' => 'Audio',
			'8udio'  => '4udio',
			'-Jazn'  => '-Jaźń',
		];
		/* @formatter:on */

		self::parseFiles( $data );

		/*
		 * Simulate user input - wont work on Windows !!!
		 * Hack: Simulate user answers by replacing internal property
		 */
		TestsUtils::setPrivateProperty( self::$Command, 'regMap', $regMap );

		/* @formatter:off */
		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	public function _testCanAskUserToRemove()
	{
	}

	public function _testCanAskUserToSkip()
	{
	}

	/**
	 * [pl09_action_skip.m3u8] Default action to perform on invlaid entries: skip
	 * Actions: exit|ask -- not testable so far :(
	 */
	public function testCanPerformDefaultActionSkip()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl09.m3u8',
				'expect' => self::$dir['ml'] . '/pl09_action_skip.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--action'     => 'skip',
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	/**
	 * [pl09_action_remove.m3u8] Default action to perform on invlaid entries: remove
	 * Actions: exit|ask -- not testable so far :(
	 */
	public function testCanPerformDefaultActionRemove()
	{
		/* @formatter:off */
		$data = [
			'files' => [
				'infile' => self::$dir['ml'] . '/pl09.m3u8',
				'expect' => self::$dir['ml'] . '/pl09_action_remove.m3u8',
			],
		];

		self::parseFiles( $data );

		self::$CommandTester->execute( [
			'--infile'     => $data['files']['infile'],
			'--action'     => 'remove',
			'--no-ext'     => true,
			'--no-backup'  => true,
			'media-folder' => self::$dir['media'],
		] );
		/* @formatter:on */

		$this->assertSame( $data['body']['expect'], file_get_contents( $data['files']['infile'] ) );
	}

	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error: Error:
	// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Cant find input file
	 */
	public function testExceptionThrownIfInputFileNotFound()
	{
		// Factory->cfg( 'winamp_playlists' ) is the default playlist if no --infile is given
		unlink( self::$Factory->cfg( 'winamp_playlists' ) );

		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ 'media-folder' => self::$dir['media'] ] );
	}

	/**
	 * Cant find Media folder
	 */
	public function testExceptionThrownIfMediaFolderNotFound()
	{
		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ 'media-folder' => 'fake [Media folder] path' ] );
	}

	/**
	 * Cant find Escape folder
	 */
	public function testExceptionThrownIfEscapeFolderNotFound()
	{
		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ '--esc' => '[X-X]', 'media-folder' => self::$dir['media'] ] );
	}

	/**
	 * Unsupported output format
	 */
	public function testExceptionThrownOnUnsuportedOutputFormat()
	{
		$this->expectException( Exception::class );
		self::$CommandTester->execute( [ '--format' => 'm4u', 'media-folder' => self::$dir['media'] ] );
	}
}
