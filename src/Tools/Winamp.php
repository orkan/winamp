<?php
/*
 * This file is part of the orkan/winamp package.
 * Copyright (c) 2022-2023 Orkan <orkans+winamp@gmail.com>
 */
namespace Orkan\Winamp\Tools;

/**
 * Winamp utils
 *
 * @author Orkan <orkans+winamp@gmail.com>
 */
class Winamp
{

	/**
	 * Load playlists.xml into array
	 *
	 * @return array [ [ filename => 'TOP.m3u8', title => 'Najlepsze', id => '{F4DA}', songs => '3', seconds => '123' ], [...], [...] ]
	 */
	public static function loadPlaylists( string $file ): array
	{
		$Xml = simplexml_load_file( $file );

		$out = [];
		foreach ( $Xml->playlist as $playlist ) {

			$attr = [];
			foreach ( $playlist->attributes() as $k => $v ) {

				$key = (string) $k;
				$val = (string) $v;

				// The 'title' can be number like, but we need it as !string! later in sort()
				// Other numeric attributes should remain integers
				$val = 'title' == $key || !is_numeric( $val ) ? $val : (int) $val;

				$attr[$key] = $val;
			}
			$out[] = $attr;
		}

		return $out;
	}
}
