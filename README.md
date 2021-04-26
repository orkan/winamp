# Winamp Media Library CLI tools
Fixes invalid entries in M3U playlists. 

Each time you change location of your media files, your playlists won't match the changes and you'll end up with invalid paths. This tool tries to help you arrange the files alphabetically into subdirectories so that it can easly find the path to any file in your playlists and modify it accordingly.

This tool was originally designed to work with Winamp playlists only (hence the name) but most of the commands will work with regular M3U playlists as well.

## Installation
`$ composer require orkan/winamp`

## Commands
### > vendor\bin\winamp show
Displays Winamp playlists

### > vendor\bin\winamp rebuild
Will scan and fix all entries from Winamp playlists.xml or any provided playlist file (*.m3u). There are 4 stages of validating each entry:

```
a) Check that the entry points to an existing file. If not, then:
b) Check that file exists in [Media folder] by testing the first letter. If not, then:
c) Check that file exists in mapped location (see Relocate). If not, then:
d) Ask for an action:
   [1] Update - enter path manualy for current entry
   [2] Relocate - mass change path for all files from current location
   [3] Remove - remove current entry
   [4] Skip (default) - don't change anything and skip to next entry
   [5] Exit - return to prompt line
```
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
PHP  ^7.3

### Author
Orkan - orkans@gmail.com - https://github.com/orkan

### License
GNU GPLv3