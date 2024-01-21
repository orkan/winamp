<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tools;

/**
 * Describes Id3 Tagger instance
 * and getters for tags used in M3U standard.
 */
interface M3UInterface
{

	public function analyze( string $filename );

	public function artist();

	public function title();

	public function seconds();
}