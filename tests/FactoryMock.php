<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tests;

use Orkan\Noop;

/**
 * Mock Factory objects.
 *
 * -------------------
 * PHPUnit\Framework\MockObject\ClassIsFinalException:
 * Class "Symfony\Component\Console\Helper\ProgressBar" is declared "final" and cannot be doubled
 * -------------------
 * Static methods cannot be mocked, eg. Console::writeln()
 * -------------------
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class FactoryMock extends \Orkan\Winamp\Factory
{

	/**
	 * ProgressBar require this mocked Output to prevent printing empty line if no format given.
	 *
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Factory::Output()
	 */
	public function Output()
	{
		return new Noop();
	}

	/**
	 * {@inheritDoc}
	 * @see \Orkan\Winamp\Factory::ProgressBar()
	 */
	public function ProgressBar( int $steps = 10, string $format = '' )
	{
		return new Noop();
	}
}
