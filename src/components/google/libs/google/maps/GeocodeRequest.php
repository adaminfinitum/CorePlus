<?php
/**
 * File for class GeocodeRequest definition in the Alliance One project
 * 
 * @package Google\Maps
 * @author Charlie Powell <charlie@eval.bz>
 * @date 20130521.1504
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

namespace Google\Maps;


/**
 * A short teaser of what GeocodeRequest does.
 *
 * More lengthy description of what GeocodeRequest does and why it's fantastic.
 *
 * <h3>Usage Examples</h3>
 *
 *
 * @todo Write documentation for GeocodeRequest
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
 * @package Google\Maps
 * @author Charlie Powell <charlie@eval.bz>
 *
 */
class GeocodeRequest {

	public $address1;
	public $address2;
	public $city;
	public $state;
	public $country = 'US';

	public $lat;
	public $lng;

	public $sensor = false;

	public function lookup(){
		if($this->lat && $this->lng){
			// @todo reverse lookup
		}
		else{
			return $this->_lookup();
		}
	}

	private function _lookup() {

		// At least address or city are required.
		if(!($this->address1 || $this->city)){
			throw new \Exception('At least the address or city are required for geocode lookups.');
		}

		$params = [
			'address' => $this->address1 . ' ' . $this->address2 . ($this->city ? ',' . $this->city : '') . ($this->state ? ',' . $this->state : ''),
			'sensor' => ($this->sensor ? 'true' : 'false')
		];
		$address = trim($this->address1 . ' ' . $this->address2 . ' ' . $this->city . ' ' . $this->state);
		if($this->country) $params['region'] = $this->country;

		// Make a request to google and update the record.
		// http://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&sensor=true_or_false
		$ps = [];
		foreach($params as $k => $v){
			$ps[] = $k . '=' . urlencode(trim($v));
		}
		$url = 'http://maps.googleapis.com/maps/api/geocode/json?' . implode('&', $ps);

		// Make the request
		// Since this is making use of Core's native File system, caching of remote files is builtin for free :)
		$request = \Core\Filestore\Factory::File($url);
		$json = $request->getContents();
		$contents = json_decode($json, true);

		// If contents aren't good, just continue to the next.
		if(!$contents){
			throw new \Exception('Bad response for: ' . $address);
		}
		// If it's not an array, just continue.
		if(!is_array($contents)){
			throw new \Exception('Bad response for: ' . $address);
		}
		// If the status isn't good, just continue.
		if($contents['status'] != 'OK'){
			throw new \Exception('Bad response for: ' . $address);
		}

		// Yay, it's a valid location!
		$result = new GeocodeResponse($contents);
		return $result;
	}
}