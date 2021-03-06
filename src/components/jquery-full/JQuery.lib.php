<?php
/**
 * jQuery library file, just includes the various jquery javascript assets.
 * 
 * @package JQuery
 * @since 0.1
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2013  Charlie Powell
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

abstract class JQuery {
	
	public static function IncludeJQuery(){
		CurrentPage::AddScript ('js/jquery/jquery-1.10.2.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function IncludeJQueryUI(){
		self::IncludeJQuery();
		CurrentPage::AddScript ('js/jquery/jquery-ui-1.10.3.custom.js');
		CurrentPage::AddStylesheet('css/jquery-ui-1.10.3.custom.css');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_nestedSortable(){
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		CurrentPage::AddScript ('js/jquery/jquery.ui.nestedSortable.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_tmpl(){
		// I need jquery ui first.
		self::IncludeJQueryUI();

		CurrentPage::AddScript ('js/jquery/tmpl.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_readonly(){
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		CurrentPage::AddStylesheet('css/jquery.readonly.css');
		CurrentPage::AddScript ('js/jquery/jquery.ui.readonly.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_json(){
		// I need jquery first.
		self::IncludeJQuery();
		
		CurrentPage::AddScript ('js/jquery/jquery.json-2.4.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_cookie(){
		// I need jquery first.
		self::IncludeJQuery();

		CurrentPage::AddScript ('js/jquery/jquery.cookie.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_masonry(){
		// I need jquery first.
		self::IncludeJQuery();
		CurrentPage::AddScript('js/jquery/jquery.masonry.min.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_form(){
		// I need jquery first.
		self::IncludeJQuery();
		CurrentPage::AddScript('js/jquery/jquery.form.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_timepicker(){
		// I need jquery ui first.
		self::IncludeJQueryUI();
		CurrentPage::AddScript('js/jquery/jqueryui.timepicker.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_waypoints(){
		// I need jquery first.
		self::IncludeJQuery();
		CurrentPage::AddScript ('js/jquery/waypoints.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_Smoothscroll(){
		// I need jquery first.
		self::IncludeJQuery();

		CurrentPage::AddScript('js/jquery/jquery.smooth-scroll.min.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
}
