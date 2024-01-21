<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2024 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tools;

/**
 * Encapsulates james-heinrich/getid3 under M3UInterface
 */
class M3UTagger implements M3UInterface
{
	private $id3;

	public function analyze( string $filename )
	{
		$this->id3 = ( new \getID3() )->analyze( $filename );
	}

	public function artist(): string
	{
		return $this->id3['tags']['id3v2']['artist'][0] ?? $this->id3['tags']['id3v1']['artist'][0] ?? 'n/a';
	}

	public function title(): string
	{
		return $this->id3['tags']['id3v2']['title'][0] ?? $this->id3['tags']['id3v1']['title'][0] ?? 'n/a';
	}

	public function seconds(): int
	{
		return $this->id3['playtime_seconds'] ?? -1;
	}
}