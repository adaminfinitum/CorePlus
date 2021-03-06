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
 * Manually add a widget onto a template.
 * 
 * @param array $params
 * @param Object $template
 * @return mixed 
 */
function smarty_function_widget($params, $template){
	
	
	$assign= (isset($params['assign']))? $params['assign'] : false;
	
	// Version 2.0 uses baseurl as the defining call.
	if(isset($params['baseurl'])){
		$api = 2.0;
		$parts = WidgetModel::SplitBaseURL($params['baseurl']);
		$name = $parts['controller'];
		$method = $parts['method'];
		$parameters = $parts['parameters'];
	}
	// Version 1.0 uses name.
	elseif(isset($params['name'])){
		$api = 1.0;
		$name = $params['name'];
		// Try to look up this requested widget.
		$name .= 'Widget';
		$parameters = null;
	}


	if(!class_exists($name)){
		throw new SmartyException('Unable to locate class [' . $name . '] for requested widget', null, null);
	}
	// @todo Add support for requiring instancing.
	
	/** @var $w Widget_2_1 */
	$w = new $name();
	// Version 1.0 API
	if($api == 1.0){
		$dat = $w->execute()->fetch();
	}
	// Version 2.0 API
	elseif($api == 2.0){
		$w->_params = $parameters;

		// Populate the request with the inbound data too.
		$request = $w->getRequest();
		if(isset($params['baseurl'])) unset($params['baseurl']);

		if($parameters) $request->parameters = array_merge($params, $parameters);
		else $request->parameters = $params;

		$return = call_user_func(array($w, $method));
		$dat = null;

		if(is_int($return)){
			throw new SmartyException("widget $name/$method returned error code $return.", null, null);
		}
		elseif($return === null){
			// Hopefully it's setup!
			$return = $w->getView();
		}
		// If it's just a string, return that.
		elseif(is_string($return)) {
			$dat = $return;
		}


		// If dat is still null, (it probably is still null btw), then render the template!
		if($dat === null){
			// Try to guess the templatename if it wasn't set.
			if($return->error == View::ERROR_NOERROR && $return->contenttype == View::CTYPE_HTML && $return->templatename === null){
				$cnameshort = (strpos($name, 'Widget') == strlen($name) - 6) ? substr($name, 0, -6) : $name;
				$return->templatename = strtolower('/widgets/' . $cnameshort . '/' . $method . '.tpl');
			}

			$dat = $return->fetch();
		}
	}
	
	
	return $assign ? $template->assign($assign, $dat) : $dat;
}
