<?php
/**
 * File for class Factory definition in the coreplus project
 * 
 * @package Core\Filestore
 * @author Charlie Powell <charlie@eval.bz>
 * @date 20130604.2130
 * @copyright Copyright (C) 2009-2013  Author
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */

namespace Core\Filestore;

use Core\Filestore\Backends;


/**
 * A short teaser of what Factory does.
 *
 * More lengthy description of what Factory does and why it's fantastic.
 *
 * <h3>Usage Examples</h3>
 *
 *
 * @todo Write documentation for Factory
 * <h4>Example 1</h4>
 * <p>Description 1</p>
 * <code>
 * // Some code for example 1
 * $a = $b;
 * </code>
 *
 *
 * <h4>Example 2</h4>
 * <p>Description 2</p>
 * <code>
 * // Some code for example 2
 * $b = $a;
 * </code>
 *
 * 
 * @package Core\Filestore
 * @author Charlie Powell <charlie@eval.bz>
 *
 */
abstract class Factory {
	/**
	 * Array of file objects that have been instantiated to act as a cache.
	 *
	 * @var array
	 */
	protected static $_Files = array();

	/**
	 * Array of file objects that have been instantiated to act as a cache.
	 *
	 * @var array
	 */
	protected static $_Directories = array();

	/**
	 * Cache of incoming URIs to the fully resolved version.
	 *
	 * @var array
	 */
	protected static $_ResolveCache = array();

	/**
	 * Static function to act as Factory for the underlying Filestore system.
	 * This will parse the incoming URI and return the appropriate type based on Core settings and filetype.
	 *
	 * @param $uri
	 *
	 * @return File
	 */
	public static function File($uri) {

		// GOGO caching ;)
		if(isset(self::$_ResolveCache[$uri])){
			$resolved = self::$_ResolveCache[$uri];
			return self::$_Files[$resolved];
		}

		// self::$_Files[$originaluri]

		//var_dump($uri);

		// base64 comes first.  If the filename is encoded in that, decode it first.
		if (strpos($uri, 'base64:') === 0){
			$uri = base64_decode(substr($uri, 7));
		}

		// Allow FTP files to be requested here!
		// This needs to be before the :// check, because technically FTP can be a remote file,
		// but it has extra functionality, (namely being able to write or perform other operations through FTP)
		if(strpos($uri, 'ftp://') === 0){
			// Don't cache remote files.
			return new Backends\FileFTP($uri);
		}

		// If this is a local file, just the URL version.... allow that remap too!
		if(strpos($uri, ROOT_URL_NOSSL) === 0){
			$uri = ROOT_PDIR . substr($uri, strlen(ROOT_URL_NOSSL));
		}
		elseif(strpos($uri, ROOT_URL_SSL) === 0){
			$uri = ROOT_PDIR . substr($uri, strlen(ROOT_URL_SSL));
		}

		// Allow remote files to be requested here too!
		if(strpos($uri, '://') !== false){
			// Don't cache remote files.
			return new Backends\FileRemote($uri);
		}




		if(
			strpos($uri, 'asset/') === 0 ||
			strpos($uri, 'assets/') === 0 ||
			strpos($uri, get_asset_path()) === 0
		){
			// Is this an asset request?
			$file = self::ResolveAssetFile($uri);
		}
		elseif(
			strpos($uri, 'public/') === 0 ||
			strpos($uri, get_public_path()) === 0
		){
			// Is this a public request?
			$file = resolve_public_file($uri);
		}
		elseif(
			strpos($uri, 'private/') === 0 ||
			strpos($uri, get_private_path()) === 0
		){
			// Is this a private request?
			// @todo
			return new CDN\FileAsset($uri);
		}
		elseif(
			strpos($uri, 'tmp/') === 0
		){
			// Is this a tmp request?
			$file = new Backends\FileLocal(get_tmp_path() . substr($uri, 4));
		}
		elseif(
			strpos($uri, get_tmp_path()) === 0 ||
			strpos($uri, '/tmp/') === 0
		){
			// tmp fully resolved?
			$file = new Backends\FileLocal($uri);
		}
		elseif(\Core\FTP() && EXEC_MODE == 'WEB'){
			// Umm.... ok
			// Still, try to use the FTP proxy files if it's enabled.
			$file = new Backends\FileFTP($uri);
		}
		else{
			// Screw it... regular file it is!
			$file = new Backends\FileLocal($uri);
		}

		// Cache this for future calls on this page load.
		self::$_Files[$file->getFilename()] = $file;
		return $file;
	}

	/**
	 * Static function to act as Factory for the underlying Filestore system.
	 * This will parse the incoming URI and return the appropriate type based on Core settings and filetype.
	 *
	 * @param $uri
	 *
	 * @return Directory
	 */
	static function Directory($uri){
		//var_dump($uri);

		// base64 comes first.  If the filename is encoded in that, decode it first.
		if (strpos($uri, 'base64:') === 0){
			$uri = base64_decode(substr($uri, 7));
		}

		// Allow FTP files to be requested here!
		// This needs to be before the :// check, because technically FTP can be a remote file,
		// but it has extra functionality, (namely being able to write or perform other operations through FTP)
		if(strpos($uri, 'ftp://') === 0){
			return new Backends\DirectoryFTP($uri);
		}

		// Allow remote files to be requested here too!
		//if(strpos($uri, '://') !== false){
		//	return new Backends\FileRemote($uri);
		//}

		// Is this an asset request?
		if(
			strpos($uri, 'asset/') === 0 ||
			strpos($uri, 'assets/') === 0 ||
			strpos($uri, get_asset_path()) === 0
		){
			return resolve_asset_directory($uri);
		}

		// Is this a public request?
		if(
			strpos($uri, 'public/') === 0 ||
			strpos($uri, get_public_path()) === 0
		){
			return resolve_public_directory($uri);
		}

		// Is this a private request?
		if(
			strpos($uri, 'private/') === 0 ||
			strpos($uri, get_private_path()) === 0
		){
			return new CDN\FileAsset($uri);
		}

		// Is this a tmp request?
		if(strpos($uri, 'tmp/') === 0){
			return new Backends\DirectoryLocal(get_tmp_path() . substr($uri, 4));
		}
		elseif(strpos($uri, get_tmp_path()) === 0){
			return new Backends\DirectoryLocal($uri);
		}

		// Umm.... ok
		return new Backends\DirectoryLocal($uri);
	}

	/**
	 * Resolve a name for an asset to an actual file.
	 *
	 * @param $filename
	 *
	 * @return \Core\Filestore\File
	 *
	 * @throws \Exception
	 */
	public static function ResolveAssetFile($filename){
		$originaluri = $filename;

		if(isset(self::$_ResolveCache[$originaluri])){
			return self::$_ResolveCache[$originaluri]->getFilename();
		}

		$resolved = get_asset_path();

		if (strpos($filename, 'assets/') === 0) {
			// Allow "assets/blah" to be passed in
			$filename = substr($filename, 7);
		}
		elseif(strpos($filename, 'asset/') === 0){
			// Allow "asset/blah" to be passed in.
			$filename = substr($filename, 6);
		}
		elseif(strpos($filename, $resolved) === 0){
			// Allow the fully resolved name to be passed in
			$filename = substr($filename, strlen($resolved));
		}

		//var_dump($filename);

		// I need to check the custom, current theme, and finally default locations for the file.
		$theme = \ConfigHandler::Get('/theme/selected');
		switch(CDN_TYPE){
			case 'local':
				if(\Core\ftp()){
					// FTP has its own sub-type.
					$custom  = new Backends\FileFTP($resolved  . 'custom/' . $filename);
					$themed  = new Backends\FileFTP($resolved  . $theme . '/' . $filename);
					$default = new Backends\FileFTP($resolved  . 'default/' . $filename);
				}
				else{
					$custom  = new Backends\FileLocal($resolved  . 'custom/' . $filename);
					$themed  = new Backends\FileLocal($resolved  . $theme . '/' . $filename);
					$default = new Backends\FileLocal($resolved  . 'default/' . $filename);
				}

				break;
			default:
				throw new \Exception('Unsupported CDN type: ' . CDN_TYPE);
				break;
		}

		if($custom->exists()){
			// If there is a custom asset installed, USE THAT FIRST!
			self::$_ResolveCache[$originaluri] = $custom->getFilename();
			return $custom;
		}
		elseif($themed->exists()){
			// Otherwise, the themes can override component assets too.
			self::$_ResolveCache[$originaluri] = $themed->getFilename();
			return $themed;
		}
		else{
			self::$_ResolveCache[$originaluri] = $default->getFilename();
			return $default;
		}
	}

	/**
	 * If a file needs to be removed from cache, (ie it was renamed, deleted, etc)
	 * this method should be called to ensure that a future call doesn't use a corrupt/incorrect file!
	 *
	 * @param $file string|File
	 */
	public static function RemoveFromCache($file) {
		if($file instanceof File){
			$filename = $file->getFilename();
		}
		else{
			$filename = $file;
		}

		// Is this file resolved already?
		if(isset(self::$_Files[$filename])){
			// Note, unsetting the object will not purge it from memory!
			// If another method is using that memory space, then it'll remain as a valid object.
			unset(self::$_Files[$filename]);
		}

		// And lookup the lookup cache.
		$keys = array_keys(self::$_ResolveCache, $filename);
		foreach($keys as $k){
			unset(self::$_ResolveCache[$k]);
		}
	}
}