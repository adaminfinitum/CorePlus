<?php
/**
 * Admin controller, handles all /Admin requests
 *
 * @package Core Plus\Core
 * @author Charlie Powell <powellc@powelltechs.com>
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

class AdminController extends Controller_2_1 {

	public function __construct() {
		$this->accessstring = 'g:admin';
	}

	public function index() {

		$view     = $this->getView();
		$pages    = PageModel::Find(array('admin' => '1'));
		$viewable = array();

		foreach ($pages as $p) {
			if (!\Core\user()->checkAccess($p->get('access'))) continue;

			$viewable[] = $p;
		}

		$this->setTemplate('/pages/admin/index.tpl');
		$view->assignVariable('links', $viewable);
	}

	public function reinstallAll() {
		// Just run through every component currently installed and reinstall it.
		// This will just ensure that the component is up to date and correct as per the component.xml metafile.
		$view = $this->getView();

		$changes = array();

		foreach (ThemeHandler::GetAllThemes() as $t) {

			if (!$t->isInstalled()) continue;

			if (($change = $t->reinstall()) !== false) {
				$changes[] = '<b>Changes to theme [' . $t->getName() . ']:</b><br/>' . "\n" . implode("<br/>\n", $change) . "<br/>\n<br/>\n";
			}
		}

		foreach (Core::GetComponents() as $c) {
			if (!$c->isInstalled()) continue;

			// Request the reinstallation
			$change = $c->reinstall();

			// 1.0 version components don't support verbose changes :(
			if ($change === true) {
				$changes[] = '<b>Changes to component [' . $c->getName() . ']:</b><br/>' . "\n(list of changes not supported with this component!)<br/>\n<br/>\n";
			}
			// 2.1 components support an array of changes, yay!
			elseif ($change !== false) {
				$changes[] = '<b>Changes to component [' . $c->getName() . ']:</b><br/>' . "\n" . implode("<br/>\n", $change) . "<br/>\n<br/>\n";
			}
			// I don't care about "else", nothing changed if it was false.

		}

		// Flush the system cache, just in case
		Core::Cache()->flush();

		//$page->title = 'Reinstall All Components';
		$this->setTemplate('/pages/admin/reinstallall.tpl');
		$view->assign('changes', $changes);
	}

	public function config() {
		$view = $this->getView();

		$configs = ConfigModel::Find(array(), null, 'key');
		$groups  = array();
		foreach ($configs as $c) {
			// Export out the group for this config option.
			$gname = substr($c->get('key'), 1);
			$gname = ucwords(substr($gname, 0, strpos($gname, '/')));

			if (!isset($groups[$gname])) $groups[$gname] = new FormGroup(array('title' => $gname,
			                                                                   'class' => 'collapsible collapsed'));

			// /user/displayname/displayoptions
			$title = substr($c->get('key'), strlen($gname) + 2);
			$val   = $c->get('value') ? $c->get('value') : $c->get('default_value');
			$name  = 'config[' . $c->get('key') . ']';

			switch ($c->get('type')) {
				case 'string':
					$el = FormElement::Factory('text');
					break;
				case 'enum':
					$el = FormElement::Factory('select');
					$el->set('options', array_map('trim', explode('|', $c->get('options'))));
					break;
				case 'boolean':
					$el = FormElement::Factory('radio');
					$el->set(
						'options', array('false' => 'No/False',
						                 'true'  => 'Yes/True')
					);
					if ($val == '1' || $val == 'true' || $val == 'yes') $val = 'true';
					else $val = 'false';
					break;
				case 'int':
					$el                    = FormElement::Factory('text');
					$el->validation        = '/^[0-9]*$/';
					$el->validationmessage = $gname . ' - ' . $title . ' expects only whole numbers with no puncuation.';
					break;
				case 'set':
					$el = FormElement::Factory('checkboxes');
					$el->set('options', array_map('trim', explode('|', $c->get('options'))));
					$val  = array_map('trim', explode('|', $val));
					$name = 'config[' . $c->get('key') . '][]';
					break;
				default:
					throw new Exception('Supported configuration type for ' . $c->get('key') . ', [' . $c->get('type') . ']');
					break;
			}

			$el->set('title', $title);
			$el->set('name', $name);
			$el->set('value', $val);

			$desc = $c->get('description');
			if ($c->get('default_value') && $desc) $desc .= ' (default value is ' . $c->get('default_value') . ')';
			elseif ($c->get('default_value')) $desc = 'Default value is ' . $c->get('default_value');
			$el->set('description', $desc);

			$groups[$gname]->addElement($el);
		}


		$form = new Form();
		$form->set('callsmethod', 'AdminController::_ConfigSubmit');
		foreach ($groups as $g) {
			$form->addElement($g);
		}

		$form->addElement('submit', array('value' => 'Save'));

		$this->setTemplate('/pages/admin/config.tpl');
		$view->assign('form', $form);
	}


	public static function _ConfigSubmit(Form $form) {
		$elements = $form->getElements();

		$updatedcount = 0;

		foreach ($elements as $e) {
			// I'm only interested in config options.
			if (strpos($e->get('name'), 'config[') === false) continue;

			// Make the name usable a little.
			$n = $e->get('name');
			if (($pos = strpos($n, '[]')) !== false) $n = substr($n, 0, $pos);
			$n = substr($n, 7, -1);

			// And get the config object
			$c = new ConfigModel($n);

			switch ($c->get('type')) {
				case 'string':
				case 'enum':
				case 'boolean':
				case 'int':
					$c->set('value', $e->get('value'));
					break;
				case 'set':
					$c->set('value', implode('|', $e->get('value')));
					break;
				default:
					throw new Exception('Supported configuration type for ' . $c->get('key') . ', [' . $c->get('type') . ']');
					break;
			}

			if ($c->save()) {
				$updatedcount++;
			}
		}

		if ($updatedcount == 0) {
			Core::SetMessage('No configuration options changed', 'info');
		}
		elseif ($updatedcount == 1) {
			Core::SetMessage('Updated ' . $updatedcount . ' configuration option', 'success');
		}
		else {
			Core::SetMessage('Updated ' . $updatedcount . ' configuration options', 'success');
		}

		return '/';
	}
}
