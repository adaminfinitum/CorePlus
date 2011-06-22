<?php
/**
 * jQuery library file, just includes the various jquery javascript assets.
 * 
 * @package JQuery
 */

/**
 * Description of JQuery
 *
 * @author powellc
 */
abstract class JQuery {
	
	public static function IncludeJQuery(){
		if(ConfigHandler::GetValue('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/jquery-1.5.1.min.js');
		else CurrentPage::AddScript ('js/jquery/jquery-1.5.1.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function IncludeJQueryUI(){
		self::IncludeJQuery();
		CurrentPage::AddScript ('js/jquery/jquery-ui-1.8.11.min.js');
		CurrentPage::AddStylesheet('css/jquery-ui.css');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_nestedSortable(){
		$base = 'jquery.ui.nestedSortable';
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		if(ConfigHandler::GetValue('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_readonly(){
		$base = 'jquery.ui.readonly';
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		CurrentPage::AddStylesheet('css/jquery.readonly.css');
		
		if(ConfigHandler::GetValue('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_json(){
		$base = 'jquery.json-2.2';
		
		// I need jquery first.
		self::IncludeJQuery();
		
		if(ConfigHandler::GetValue('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
}
