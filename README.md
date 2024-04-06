# Winamp Media Library CLI tools `v6.0.0`
Fixes invalid entries in M3U playlists. 

Every time you change the location of your media files, the playlists
won't take those changes and you will get the wrong paths. This tool tries
to find missing entries in all your playlists and update it accordingly.

For best results, place your media files in alphabetical subfolders
(see Media folder). In the case of a different folder layout,
semi-automatic methods have been implemented (see Validation).

## Installation
`$ composer require orkan/winamp`

## Commands
### > vendor\bin\winamp show
Displays Winamp playlists

### > vendor\bin\winamp math
Add or substract playlists

### > vendor\bin\winamp rebuild
Scan and fix all entries from Winamp playlists.xml or any provided playlist file (*.m3u). 

## Validation
There are 5 steps to validate each track:

```
  a) Check that the playlist entry is pointing to an existing file. If not, then:
  b) Check that file exists in mapped location (see Relocate). If not, then:
  c) Check that file exists after renaming (see Rename). If not, then:
  d) Check that file exists in [Media folder] by testing the first letter. If not, then:
  e) Ask for an action:
     [1] Update - enter new path for current track
     [2] Relocate - replace path for current and remaining tracks
     [3] Rename - rename filenames with regex pattern
     [4] Remove - remove current playlist entry
     [5] Skip (default) - leave current track and skip to next one
     [6] Exit - return to prompt line
```

For more information and options type: `vendor\bin\winamp rebuild --help`

## Media folder
The user [Media folder] structure should be organized into sub folders each named with Regular Expressions notation, describing letters range of filenames they are holding, ie. [A-Z] or [0-9].

Example:

```
[Media folder]
  |
  +-- [0-9] For filenames starting with a number (also default Escape folder)
  +-- [A-D] For filenames starting with letters: a, b, c, d
  +-- ...
  +-- [T-T] For filenames starting with letter: t
```

## Third Party Packages
* [Symfony Console](https://symfony.com/doc/current/components/console.html) The Console Component
* [Monolog](https://github.com/Seldaek/monolog) for extended logging
* [getID3](https://www.getid3.org/) for ID3tag support

## About
### Requirements
PHP  ^7.4

### Author
[Orkan](https://github.com/orkan)

### License
MIT

### Updated
Sat, 06 Apr 2024 14:58:53 +02:00
