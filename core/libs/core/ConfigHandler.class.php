<?php
/**
 * // enter a good description here
 * 
 * @package Core
 * @since 2011.06
 * @author Charlie Powell <powellc@powelltechs.com>
 * @copyright Copyright 2011, Charlie Powell
 * @license GNU Lesser General Public License v3 <http://www.gnu.org/licenses/lgpl-3.0.html>
 * This system is licensed under the GNU LGPL, feel free to incorporate it into
 * custom applications, but keep all references of the original authors intact,
 * read the full license terms at <http://www.gnu.org/licenses/lgpl-3.0.html>, 
 * and please contribute back to the community :)
 */

/**
 * Core / ConfigHandler class
 * 
 * Core configuration handling class; 
 *  handles getting and setting config values from the database and XML config files.
 * 
 * @author powellc <powellc@powelltechs.com>
 * @package CAE Core
 * @version 1.0.0-dev
 */

/**
 * The class that handles all configuration getting and setting.
 * Can handle calls to XML config files and DB configuration `configs` table.
 * 
 * @package CAE Core
 */
class ConfigHandler implements ISingleton {

	private static $instance = null;
	public static $directory;
	/**
	 * Cache of datamodels of the configuration options from the database.
	 * @var array
	 */
	private static $CacheFromDB = array();

	/**
	 * Private constructor class to prevent outside instantiation.
	 *
	 * @return void
	 */
	private function __construct() {
		// Run through the config directory, looking for XML files.

		ConfigHandler::$directory = ROOT_PDIR . "config/";

		if (!is_readable(ConfigHandler::$directory)) {
			throw new Exception("Could not open config directory [ConfigHandler::$directory] for reading.");
		}
	}

	public static function Singleton() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
			HookHandler::AttachToHook('db_ready', 'ConfigHandler::_DBReadyHook');
		}
		return self::$instance;
	}

	public static function getInstance() {
		return self::singleton();
	}

	/**
	 * Load the configuration variables from a requested config file, located inside of the config directory.
	 *
	 * @param $config string
	 * @return boolean | array
	 */
	public static function LoadConfigFile($config) {

		// Return array (if the XML provides 'return' elements).
		$return = array();
		
		$file = ConfigHandler::$directory . $config . '.xml';
		
		if(!file_exists($file)){
			trigger_error("Requested config file $config.xml not located within " . ConfigHandler::$directory, E_USER_NOTICE);
			return false;
		}
		if(!is_readable($file)){
			trigger_error("Unable to read $file, please ensure it's permissions are set correctly", E_USER_NOTICE);
			return false;
		}

		// Read in the XML data.
		$xml = new DOMDocument();
		$xml->load($file);

		// Get any 'defines' in the configuration file.
		foreach ($xml->getElementsByTagName("define") as $xmlEl) {
			$name = $xmlEl->getAttribute("name");
			$type = $xmlEl->getAttribute("type");
			$value = $xmlEl->getElementsByTagName("value")->item(0)->nodeValue;
			switch (strtolower($type)) {
				case 'int': $value = (int) $value;
					break;
				case 'boolean': $value = (($value == 'true' || $value == '1' || $value == 'yes') ? true : false);
					break;
			}
			if (!defined($name))
				define($name, $value);
		} // foreach($xml->getElementsByTagName("define") as $xmlEl)
		// Get any 'returns' in the configuration file.
		foreach ($xml->getElementsByTagName("return") as $xmlEl) {
			$name = $xmlEl->getAttribute("name");
			$type = $xmlEl->getAttribute("type");
			$value = $xmlEl->getElementsByTagName("value")->item(0)->nodeValue;
			switch (strtolower($type)) {
				case 'int': $value = (int) $value;
					break;
				case 'boolean': $value = (($value == 'true' || $value == '1' || $value == 'yes') ? true : false);
					break;
			}
			$return[$name] = $value;
		} // foreach($xml->getElementsByTagName("define") as $xmlEl)

		return (!count($return) ? true : $return);
	}

	/**
	 * Retrieve a value for a requested configSet and key.
	 *
	 * <b>**Note, currently ONLY supports DB**</b>
	 *
	 * @param $configSet string
	 * @param $key string
	 * @return string | int | boolean
	 * 
	 * @deprecated 2011.10
	 */
	public static function GetValue($key) {
		//trigger_error('ConfigHandler::GetValue() is deprecated, please use ConfigHandler::Get() instead.', E_USER_DEPRECATED);
		return self::Get($key);
		
		return (isset(ConfigHandler::$CacheFromDB[$key])) ? ConfigHandler::$CacheFromDB[$key] : null;
	}

	public static function SetValue($key, $value) {
		//trigger_error('ConfigHandler::SetValue() is deprecated, please use ConfigHandler::Set() instead.', E_USER_DEPRECATED);
		return self::Set($key, $value);
		
        ConfigHandler::$CacheFromDB[$key] = $value;
		$rs = DB::Execute("UPDATE `" . DB_PREFIX . "config` SET `value` = ? WHERE `key` = ? LIMIT 1", array($value, $key));
	}
	
	/**
	 * Get the config model that is attached to the core configuration system.
	 * 
	 * This is the easiest way to create new config options.
	 * 
	 * @param string $key
	 * @return ConfigModel
	 */
	public static function GetConfig($key){
		if(!isset(ConfigHandler::$CacheFromDB[$key])){
			ConfigHandler::$CacheFromDB[$key] = new ConfigModel($key);
		}
		
		return ConfigHandler::$CacheFromDB[$key];
	}
	
	public static function Get($key){
		if(isset(ConfigHandler::$CacheFromDB[$key])) return ConfigHandler::$CacheFromDB[$key]->getValue();
		else return null;
	}
	
	public static function Set($key, $vlaue){
		if(isset(ConfigHandler::$CacheFromDB[$key])) return ConfigHandler::$CacheFromDB[$key]->getValue();
		else return null;
	}

	public static function _DBReadyHook(){
		// This may be called before the componenthandler is ready.
		require_once(ROOT_PDIR . 'core/models/ConfigModel.class.php');
		// Clear out the cache, (if it has any...)
		ConfigHandler::$CacheFromDB = array();
		$fac = ConfigModel::Find();
		foreach($fac as $model){
			ConfigHandler::$CacheFromDB[$model->get('key')] = $model;
			
			// Also map this value if it's set to do so.
			if($model->get('mapto') && !defined($model->get('mapto'))) define($model->get('mapto'), $model->getValue());
		}
	}
	
	/**
	 * Hook listener for when the database is ready.
	 * Query the database for all configuration elements that may be hiding in there.
	 * Assemble them into a cache of variables internally to prevent having to make repeated DB calls.
	 *
	 * @return unknown_type
	 */
	public static function _DBReadyHookLEGACY() {
		$obj = new Dataset();
		$obj->table('config');
		$obj->select(array('key', 'value', 'type', 'mapto'));
		$rs = $obj->execute();
		
		if(!$rs) return false;
		foreach ($rs as $row) {
			switch ($row['type']) {
				case 'int':
					$row['value'] = (int) $row['value'];
					break;
				case 'boolean':
					$row['value'] = ($row['value'] == '1' || $row['value'] == 'true') ? true : false;
					break;
				case 'set':
					$row['value'] = array_map('trim', explode('|', $row['value']));
				// Default is not needed, already comes through as a string.
			}
			
			ConfigHandler::$cacheFromDB[$row['key']] = $row['value'];
			
			// Also map this value if it's set to do so.
			if($row['mapto'] && !defined($row['mapto'])) define($row['mapto'], $row['value']);
		}
		
	}

	public static function var_dump_cache() {
		var_dump(ConfigHandler::$cacheFromDB);
	}

}
