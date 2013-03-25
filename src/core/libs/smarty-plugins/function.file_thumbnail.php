<?php
/**
 * @package Core Plus\Core
 * @since 1.9
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2012  Charlie Powell
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

/**
 * @param $params
 * @param $template
 *
 * @return string
 * @throws SmartyException
 */
function smarty_function_file_thumbnail($params, $template){
	
	// Key/value array of attributes for the resulting HTML.
	$attributes = array();
	
	if(!isset($params['file'])){
		throw new SmartyException('Required parameter [file] not provided for file_thumbnail!');
	}
	
	// Some optional parameters, (and their defaults)
	$size = $d = $assign = $width = $height = false;
	
	
	$file = $params['file'];
	unset($params['file']);
	
	// $file should be a File...
	if(!$file instanceof File_Backend){
		throw new SmartyException('Invalid parameter [file] for file_thumbnail, must be a File_Backend!');
	}
	
	if(isset($params['assign'])){
		$assign = $params['assign'];
		unset($params['assign']);
	}
	
	if(isset($params['width'])){
		$width = $params['width'];
		unset($params['width']);
	}
	
	if(isset($params['height'])){
		$height = $params['height'];
		unset($params['height']);
	}
	
	if(isset($params['dimensions'])){
		$d = $params['dimensions'];
		
		if(is_numeric($d)){
			$width = $d;
			$height = $d;
		}
		else{
			// New method. Split on the "x" and that should give me the width/height.
			$vals = explode('x', strtolower($d));
			$width = (int)$vals[0];
			$height = (int)$vals[1];
		}
		// Translate this dimension set to a "sm/med/lg" size.
		$size = Core::TranslateDimensionToPreviewSize($width, $height);
		unset($params['dimensions']);
	}
	
	if(isset($params['size'])){
		$size = $params['size'];
		// Let size override width and height.
		$width = $height = ConfigHandler::Get('/theme/filestore/preview-size-' . $size);
		$d = $width . 'x' . $height;
		unset($params['size']);
	}
	
	
	// If one is provided but not the other, just make them the same.
	if(!$d){
		if($width && !$height) $height = $width;
		if($height && !$width) $width = $height;

		$d = ($width && $height) ? $width . 'x' . $height : false;
		$size = Core::TranslateDimensionToPreviewSize($width, $height);
	}
	
	
	if(!$file->exists()){
		$icon = Core::File('assets/mimetype_icons/notfound-' . $size . '.png');
		$attributes['src'] = $icon->getURL();
	}
	elseif(ConfigHandler::Get('/core/filestore/previews') && $file->isPreviewable()){
		if($file->getFilesize() < (1024*1024*4)){
			// Files that are smaller than a certain size can probably be safely rendered on this pageload.
			$attributes['src'] = $file->getPreviewURL($d);
		}
		else{
			// Larger files should be rendered independently.
			// This causes each image to be longer, but should not cause a script timeout.
			$attributes['src'] = Core::ResolveLink('/File/Preview/' . $file->getFilenameHash() . '?size=' . $d);
		}
	}
	else{
		$icon = Core::File('assets/mimetype_icons/' . str_replace('/', '-', strtolower($file->getMimetype()) ) . '-' . $size . '.png');
		if(!$icon->isReadable()) $icon = Core::File('assets/mimetype_icons/unknown-' . $size . '.png');
		$attributes['src'] = $icon->getURL();
	}
	
	// Do the rest of the attributes that the user sent in (if there are any)
	foreach($params as $k => $v){
		$attributes[$k] = $v;
	}
	
	// Merge them back together in one string.
	$html = '<img';
	foreach($attributes as $k => $v) $html .= " $k=\"$v\"";
	$html .= '/>';
	
	return $assign ? $template->assign($assign, $html) : $html;
}