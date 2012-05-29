<?php
/**
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * Copyright (C) 2010  Charlie Powell <charlie@eval.bz>
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
 *
 * @package [packagename]
 * @author Charlie Powell <charlie@eval.bz>
 * @date [date]
 */

function smarty_block_a($params, $innercontent, $template, &$repeat){
	
	$assign= false;
	
	// Start the A tag
	$content = '<a';
	
	// Allow "confirm" text to override the href and onClick functions.
	if(isset($params['confirm'])){
		$params['onClick'] = "if(confirm('" . str_replace("'", "\\'", $params['confirm']) . "')){ Core.PostURL('" . str_replace("'", "\\'", Core::ResolveLink($params['href'])) . "'); } return false;";
		$params['href'] = '#';
	}
	
	// Add in any attributes.
	foreach($params as $k => $v){
		$k = strtolower($k);
		switch($k){
			case 'href':
				$content .= ' href="' . Core::ResolveLink ($v) . '"';
				break;
			case 'assign':
				$assign = $v;
				break;
			default:
				$content .= " $k=\"$v\"";
		}
	}
	// Close the starting tag.
	$content .= '>';
	
	// Add any content inside.
	$content .= $innercontent;
	
	// Close the set.
	$content .= '</a>';

    return $assign ? $template->assign($assign, $content) : $content;
}