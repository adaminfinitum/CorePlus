<?php
/**
 *
 * @package Theme
 * @since 2011.06
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright 2011, Charlie Powell
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl.html>
 * This system is licensed under the GNU LGPL, feel free to incorporate it into
 * custom applications, but keep all references of the original authors intact,
 * read the full license terms at <http://www.gnu.org/licenses/lgpl-3.0.html>, 
 * and please contribute back to the community :)
 */

namespace Theme;

/**
 * Theme object.
 * 
 * Themes consist just of the template files and corresponding assets.
 */
class Theme{

	/**
	 * Underlying XML Loader object of the component.xml file.
	 * 
	 * Responsible for retrieving most information about this component.
	 * 
	 * @var \XMLLoader
	 */
	private $_xmlloader = null;
	
	/**
	 * The name of the component.
	 * Has to be unique, (because the name is a directory in /components)
	 * 
	 * @var string
	 */
	protected $_name;
	
	/**
	 * Version of the component, (propagates to libraries and modules).
	 *
	 * @var string
	 */
	protected $_version;

	/**
	 * Description of this library.
	 * As set from the XML file.
	 *
	 * @var string
	 */
	protected $_description;
	
	/**
	 * Is this component explictly disabled?
	 * (themes cannot be disabled...)
	 * 
	 * @var boolean
	 */
	protected $_enabled = true;

	/**
	 * Version of the component, as per the database (installed version).
	 * 
	 * @var string
	 */
	private $_versionDB = false;
	
	/**
	 * This object only needs to be loaded once
	 * @var boolean
	 */
	private $_loaded = false;
	
	
	public function __construct($name = null){
		$this->_xmlloader = new \XMLLoader();
		$this->_xmlloader->setRootName('theme');
		
		$filename = ROOT_PDIR . 'themes/' . $name . '/theme.xml';
		
		if(!$this->_xmlloader->loadFromFile($filename)){
			throw new \Exception('Parsing of XML Metafile [' . $filename . '] failed, not valid XML.');
		}
	}
	
	public function load(){
		if($this->_loaded) return;
		
		$this->_name = $this->_xmlloader->getRootDOM()->getAttribute('name');
		$this->_version = $this->_xmlloader->getRootDOM()->getAttribute("version");
		
		// Load the database information, if there is any.
		$dat = \ComponentFactory::_LookupComponentData('theme/' . $this->_name);
		if(!$dat) return;
		
		$this->_versionDB = $dat['version'];
		$this->_enabled = ($dat['enabled']) ? true : false;

		if(DEVELOPMENT_MODE && defined('AUTO_INSTALL_ASSETS') && AUTO_INSTALL_ASSETS && EXEC_MODE == 'WEB'){
			\Core\Utilities\Logger\write_debug('Auto-installing assets for theme [' . $this->getName() . ']');
			$this->_installAssets();
		}

		$this->_loaded = true;
	}
	
	/**
	 * Get all the templates registered for this theme.
	 * Each template can be a different site skin, ie: 2-column, 3-column, etc.
	 * 
	 * @return array 
	 */
	public function getSkins(){
		$out = array();
		$default = null;
		$admindefault = null;
		$currenttheme = false;

		// If this theme is currently selected, check the default template too.
		if($this->getKeyName() == \ConfigHandler::Get('/theme/selected')){
			$default = \ConfigHandler::Get('/theme/default_template');
			$admindefault = \ConfigHandler::Get('/theme/default_admin_template');

			// Defaults to the main public skin.
			if(!$admindefault) $admindefault = $default;

			$currenttheme = true;
		}
		
		foreach($this->_xmlloader->getElements('//skins/file') as $f){
			$basefilename = $f->getAttribute('filename');
			$filename = $this->getBaseDir() . 'skins/' . $basefilename;


			if($basefilename == 'blank.tpl'){
				continue;
			}

			$skin = \Core\Templates\Template::Factory($filename);

			// The return is expecting an array.
			$out[] = array(
				'filename'        => $filename,
				'file'            => $basefilename,
				'title'           => $f->getAttribute('title'),
				'default'         => ($default == $basefilename),
				'admindefault'    => ($admindefault == $basefilename),
				'has_stylesheets' => $skin->hasOptionalStylesheets(),
				'current_theme'   => $currenttheme,
			);
		}

		return $out;
	}

	/**
	 * Get all the email skins registered for this theme.
	 * Each template can be a different site skin, ie: 2-column, 3-column, etc.
	 *
	 * @return array
	 */
	public function getEmailSkins(){
		$out = array();
		$default = null;
		$currenttheme = false;

		// If this theme is currently selected, check the default template too.
		if($this->getKeyName() == \ConfigHandler::Get('/theme/selected')){
			$default = \ConfigHandler::Get('/theme/default_email_template');

			$currenttheme = true;
		}

		foreach($this->_xmlloader->getElements('//emailskins/file') as $f){
			$basefilename = $f->getAttribute('filename');
			$filename = $this->getBaseDir() . 'emailskins/' . $basefilename;

			$skin = \Core\Templates\Template::Factory($filename);
			$title = $basefilename;

			// The return is expecting an array.
			$out[] = array(
				'filename'        => $filename,
				'file'            => $basefilename,
				'title'           => $title,
				'default'         => ($default == $basefilename),
				'current_theme'   => $currenttheme,
			);
		}

		// Tack on the main default... no skin!
		$out[] = array(
			'filename'        => '',
			'file'            => '',
			'title'           => '-- No Skin --',
			'default'         => ($default == ''),
			'current_theme'   => $currenttheme,
		);

		return $out;
	}

	/**
	 * Alias of getSkins()
	 * @return mixed
	 */
	public function getTemplates(){
		return $this->getSkins();
	}

	/**
	 * Get this component's "key" name.
	 *
	 * This *must* be the name of the directory it's installed in
	 * and *must not* contain spaces or other weird characters.
	 *
	 * @return string
	 */
	public function getKeyName(){
		return str_replace(' ', '-', strtolower($this->_name));
	}
	
	/**
	 * Get this theme's name
	 * 
	 * @return string
	 */
	public function getName(){
		return $this->_name;
	}

	/**
	 * Get the base directory of this component
	 *
	 * Generally /home/foo/public_html/themes/theme-name/
	 *
	 * @param mixed|string $prefix Path to prepend to the string.  Use "" for relative, or ROOT_PDIR for fully resolved.
	 *
	 * @return string
	 */
	public function getBaseDir($prefix = ROOT_PDIR){
		return $prefix . 'themes/' . $this->getKeyName() . '/';
	}
	
	/**
	 * Save this component metadata back to its XML file.
	 * Useful in packager scripts.
	 */
	public function save($minified = false){
		// Ensure there's a required namespace on the root node.
		$this->_xmlloader->getRootDOM()->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");

		// This needs to be the final step... write the XML doc back to the file.
		$XMLFilename = $this->getBaseDir() . 'theme.xml';

		if ($minified) {
			file_put_contents($XMLFilename, $this->_xmlloader->asMinifiedXML());
		}
		else {
			file_put_contents($XMLFilename, $this->_xmlloader->asPrettyXML());
		}
	}

	/**
	 * Save or get the package XML for this component.  This is useful for the
	 * packager
	 *
	 * @param boolean $minified
	 * @param string  $filename
	 */
	public function savePackageXML($minified = true, $filename = false) {

		// Instantiate a new XML Loader object and get it ready to use.
		$dom = new \XMLLoader();
		$dom->setRootName('package');
		$dom->load();

		// Populate the root attributes for this component package.
		$dom->getRootDOM()->setAttribute('type', 'theme');
		$dom->getRootDOM()->setAttribute('name', $this->getName());
		$dom->getRootDOM()->setAttribute('version', $this->getVersion());

		// Declare the packager
		$dom->createElement('packager[version="' . \Core::GetComponent()->getVersion() . '"]');

		/* // Themes don't have any provide directives.
		// Copy over any provide directives.
		foreach ($this->_xmlloader->getRootDOM()->getElementsByTagName('provides') as $u) {
			$newu = $dom->getDOM()->importNode($u);
			$dom->getRootDOM()->appendChild($newu);
		}
		$dom->getElement('/provides[type="component"][name="' . strtolower($this->getName()) . '"][version="' . $this->getVersion() . '"]');
		*/

		/* // Themes don't have any requrie directives.
		// Copy over any requires directives.
		foreach ($this->_xmlloader->getRootDOM()->getElementsByTagName('requires') as $u) {
			$newu = $dom->getDOM()->importNode($u);
			$dom->getRootDOM()->appendChild($newu);
		}
		*/

		// Copy over any upgrade directives.
		// This one can be useful for an existing installation to see if this
		// package can provide a valid upgrade path.
		foreach ($this->_xmlloader->getRootDOM()->getElementsByTagName('upgrade') as $u) {
			$newu = $dom->getDOM()->importNode($u);
			$dom->getRootDOM()->appendChild($newu);
		}

		// Tack on description
		$desc = $this->_xmlloader->getElement('/description', false);
		if ($desc) {
			$newd            = $dom->getDOM()->importNode($desc);
			$newd->nodeValue = $desc->nodeValue;
			$dom->getRootDOM()->appendChild($newd);
		}


		$out = ($minified) ? $dom->asMinifiedXML() : $dom->asPrettyXML();

		if ($filename) {
			file_put_contents($filename, $out);
		}
		else {
			return $out;
		}
	}

	/**
	 * Get the raw XML of this component, useful for debugging.
	 *
	 * @return string (XML)
	 */
	public function getRawXML($minified = false) {
		return ($minified) ? $this->_xmlloader->asMinifiedXML() : $this->_xmlloader->asPrettyXML();
	}

	/**
	 * Set all asset files in this component.  Only really usable in the installer.
	 *
	 * @param $files array Array of files to set.
	 */
	public function setAssetFiles($files) {
		// Clear out the array first.
		$this->_xmlloader->removeElements('//theme/assets/file');

		// It would be nice to have them alphabetical.
		$newarray = array();
		foreach ($files as $f) {
			$newarray[$f['file']] = $f;
		}
		ksort($newarray);

		// And recreate them all.
		foreach ($newarray as $f) {
			$this->addAssetFile($f);
		}
	}

	/**
	 * Add a single asset file to this theme.
	 *
	 * @param array $file The array is expected to be an associative array with attributes "file" and "md5".
	 */
	public function addAssetFile($file){
		$this->_xmlloader->createElement('//theme/assets/file[@filename="' . $file['file'] . '"][@md5="' . $file['md5'] . '"]');
	}

	/**
	 * Set all skin files in this component.  Only really usable in the installer.
	 *
	 * @param $files array Array of files to set.
	 */
	public function setSkinFiles($files) {

		// This behaves slightly differently than the other ones, since this can include metadata for the skin.
		// As such, they are not simply deleted to begin with.

		// It would be nice to have them alphabetical.
		$newarray = array();
		foreach ($files as $f) {
			// Make sure that the file does not start with 'skins/'...
			if(strpos($f['file'], 'skins/') === 0) $f['file'] = substr($f['file'], 6);
			$newarray[$f['file']] = $f;
		}
		ksort($newarray);

		$used = array();
		// Instead, I'm checking each existing one.
		foreach($this->_xmlloader->getElements('//theme/skins/file') as $el){
			$att_file = $el->getAttribute('filename');
			if(isset($newarray[$att_file])){
				$used[] = $att_file;
				$el->setAttribute('md5', $newarray[$att_file]['md5']);
			}
			else{
				// Remove it!
				$this->_xmlloader->getElement('//theme/skins', false)->removeChild($el);
			}
		}

		// And make sure that I didn't miss any new ones.
		foreach($newarray as $f){
			if(!in_array($f['file'], $used)){
				// Make the title something generic.
				$title = substr($f['file'], 6, -4);
				$this->_xmlloader->createElement('//theme/skins/file[@filename="' . $f['file'] . '"][@md5="' . $f['md5'] . '"][@title="' . $title . '"]');
			}
		}
	}

	/**
	 * Set all view files in this component.  Only really usable in the installer.
	 *
	 * @param $files array Array of files to set.
	 */
	public function setViewFiles($files) {
		// Clear out the array first.
		$this->_xmlloader->removeElements('//theme/view/file');

		// It would be nice to have them alphabetical.
		$newarray = array();
		foreach ($files as $f) {
			$newarray[$f['file']] = $f;
		}
		ksort($newarray);

		// And recreate them all.
		foreach ($newarray as $f) {
			$this->_xmlloader->createElement('//theme/view/file[@filename="' . $f['file'] . '"][@md5="' . $f['md5'] . '"]');
		}
	}

	/**
	 * Get this component's version
	 *
	 * @return string
	 */
	public function getVersion() {
		return $this->_version;
	}

	/**
	 * Set the version of this component
	 *
	 * This affects the component.xml metafile of the package.
	 *
	 * @param $vers string
	 *
	 * @return void
	 */
	public function setVersion($vers) {
		if ($vers == $this->_version) return;

		// Switch over any unversioned upgrade directives to this version.
		// First, check just a plain <upgrade> directive.
		if (($upg = $this->_xmlloader->getElement('/upgrades/upgrade[@from=""][@to=""]', false))) {
			// Add the current and dest. attribute to it.
			$upg->setAttribute('from', $this->_version);
			$upg->setAttribute('to', $vers);
		}
		elseif (($upg = $this->_xmlloader->getElement('/upgrades/upgrade[@from="' . $this->_version . '"][@to=""]', false))) {
			$upg->setAttribute('to', $vers);
		}
		else {
			// No node found... just create a new one.
			$this->_xmlloader->getElement('/upgrades/upgrade[@from="' . $this->_version . '"][@to="' . $vers . '"]');
		}

		$this->_version = $vers;
		$this->_xmlloader->getRootDOM()->setAttribute('version', $vers);
	}

	/**
	 * Get the description for this component
	 * @return string
	 */
	public function getDescription() {
		if ($this->_description === null) {
			$this->_description = trim($this->_xmlloader->getElement('//description')->nodeValue);
		}

		return $this->_description;
	}

	/**
	 * Set the description for this component
	 * @param $desc string
	 */
	public function setDescription($desc) {
		// Set the cache first.
		$this->_description = $desc;
		// And set the data in the original DOM.
		$this->_xmlloader->getElement('//description')->nodeValue = $desc;
	}
	
	public function getViewSearchDir(){
		$d = $this->getBaseDir() . 'templates/';
		return (is_dir($d)) ? $d : null;
	}
	
	public function getAssetDir(){
		$d = $this->getBaseDir() . 'assets/';
		return (is_dir($d)) ? $d : null;
	}

	public function getSkinDir(){
		$d = $this->getBaseDir() . 'skins/';
		return (is_dir($d)) ? $d : null;
	}
	
	public function isLoadable(){
		return true; // Themes really can't quite be *not* loadable.
	}
	
	
	public function isInstalled(){
		return ($this->_versionDB === false)? false : true;
	}
	
	public function needsUpdated(){
		return ($this->_versionDB != $this->_version);
	}
	
		
	public function hasLibrary(){
		return false; // Themes don't have libraries.
	}
	
	public function hasJSLibrary(){
		return false; // Themes don't have JS libraries, (am I even supporting this anymore???)
	}
	
	public function hasModule(){
		return false; // Themes don't have modules.
	}
	
	public function hasView(){
		return true; // This is the only thing a theme is in fact...
	}
	
	
	/**
	 * Install this theme and its assets.
	 * 
	 * Returns false if nothing changed, else will return an array containing all changes.
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	public function install(){
		// @todo I need actual error checking here.
		//if($this->isInstalled()) return false;
		
		return $this->_performInstall();
	}
	
	/**
	 * "Reinstall" (aka) Install this theme and its assets.
	 * 
	 * Alias of install()
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	public function reinstall(){
		// @todo I need actual error checking here.
		//if(!$this->isInstalled()) return false;
		
		return $this->_performInstall();
	}
	
	/**
	 * "Upgrade" (aka) Install this theme and its assets.
	 * 
	 * Alias of install()
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	public function upgrade(){
		//if(!$this->isInstalled()) return false;
		
		return $this->_performInstall();
	}

	/**
	 * Get if this theme is currently set as the site default.
	 *
	 * @return bool
	 */
	public function isDefault(){
		return \ConfigHandler::Get('/theme/selected') == $this->getKeyName();
	}

	/**
	 * Get the primary, (first), screenshot of this theme.
	 *
	 * @return array
	 */
	public function getScreenshot(){
		$s = $this->_xmlloader->getElement('//screenshots/screenshot', false);

		if(!$s){
			return array(
				'file' => '',
				'title' => $this->getName()
			);
		}
		else{
			return array(
				'file' => $this->getBaseDir() . $s->getAttribute('file'),
				'title' => ($s->getAttribute('title') ? $s->getAttribute('title') : $this->getName()),
			);
		}
	}
	
	/**
	 * Because install, upgrade and remove all are actually the exact same logic for themes.
	 * 
	 * Returns false if nothing changed, else will return an array containing all changes.
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	private function _performInstall(){
		$changed = array();
		
		$change = $this->_installAssets();
		if($change !== false) $changed = array_merge($changed, $change);
		
		$change = $this->_parseConfigs();
		if($change !== false) $changed = array_merge($changed, $change);
		
		// Make sure the version is correct in the database.
		$c = new \ComponentModel('theme/' . $this->_name);
		$c->set('version', $this->_version);
		$c->save();
		
		return (sizeof($changed)) ? $changed : false;
	}
	
	/**
	 * Internal function to parse and handle the configs in the component.xml file.
	 * This is used for installations and upgrades.
	 * 
	 * Returns false if nothing changed, else will return the configuration options changed.
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	private function _parseConfigs(){
		$changes = array();
		
		// I need to get the schema definitions first.
		$node = $this->_xmlloader->getElement('configs');
		//$prefix = $node->getAttribute('prefix');
		
		// Now, get every table under this node.
		foreach($node->getElementsByTagName('config') as $confignode){
			$m = new \ConfigModel($confignode->getAttribute('key'));
			$m->set('type', $confignode->getAttribute('type'));
			$m->set('default_value', $confignode->getAttribute('default'));
			// Themes overwrite the settings regardless.
			$m->set('value', $confignode->getAttribute('default'));
			$m->set('description', $confignode->getAttribute('description'));
			if($m->save()) $changes[] = 'Set configuration [' . $m->get('key') . '] to [' . $m->get('value') . ']';
		}
		
		// Are there changes?
		return (sizeof($changes)) ? $changes : false;
	} // private function _parseConfigs
	
	/**
	 * Copy in all the assets for this component into the assets location.
	 * 
	 * Returns false if nothing changed, else will return an array of all the changes that occured.
	 * 
	 * @return false | array
	 * @throws \InstallerException
	 */
	private function _installAssets(){
		$assetbase = \Core\Filestore\get_asset_path();

		$coretheme = \ConfigHandler::Get('/theme/selected');
		// WHY is core theme set to blank?!?
		// Damn installer...
		// this happens in the installer.
		if($coretheme === null) $coretheme = 'default';
		$theme = $this->getKeyName();
		$changes = array();

		foreach($this->_xmlloader->getElements('/assets/file') as $node){
			// Cannot install assets if the directory is not setup!
			if(!$this->getAssetDir()){
				continue;
			}
			$b = $this->getBaseDir();
			// The base filename with the directory.
			$filename = $node->getAttribute('filename');

			// The new theme asset will be installed into the same directory as its theme.
			// This differs from usual components because they just follow whatever theme is currently running.
			//$nf = Core::File($assetbase . $theme . '/' . $filename);
			$trimmedfilename = substr($b . $node->getAttribute('filename'), strlen($this->getAssetDir()));
			$themespecificfilename = $assetbase . $theme . '/' . $trimmedfilename;
			$newfilename = 'assets/' . $trimmedfilename;


			// Before anything, check and see if this file has a custom override file present.
			if(file_exists(ROOT_PDIR . 'themes/custom/' . $newfilename)){
				// If so, then copy that asset to the custom directory too!
				$f = \Core\Filestore\Factory::File(ROOT_PDIR . 'themes/custom/' . $newfilename);
			}
			else{
				// Otherwise, the local file is guaranteed to be a local file.
				$f = new \Core\Filestore\Backends\FileLocal($b . $filename);
			}

			$nf = \Core\Filestore\Factory::File($newfilename);

			// The various replacement possibilities for this file.
			// The new destination must be in the theme-specific directory, this is a
			// bit of a hack from the usual behaviour of the filestore system.
			// Since that's designed to return the default if the theme-specific doesn't exist.
			$replacements = array(
				// The theme is not default, but the system translated the path to the default directory.
				// This is because the file doesn't exist in any theme.
				// This is actually expected behaviour, except unwanted here.
				'default/' . $trimmedfilename => $theme . '/' . $trimmedfilename,
				// The theme is not the currently installed, but the system translated the path to the that directory.
				// This is because the filename is the same as the installed theme, so the system just translated there.
				// We don't want that.
				$coretheme . '/' . $trimmedfilename => $theme . '/' . $trimmedfilename,
			);


			foreach($replacements as $k => $v){
				if($k == $v) continue;
				if(strpos($nf->getFilename(), $k) !== false){
					$nf->setFilename( str_replace($k, $v, $nf->getFilename()) );
				}
			}
			
			// Check if this file even needs updated. (this is primarily used for reporting reasons)
			if($nf->exists() && $nf->identicalTo($f)){
				//echo "Skipping file, it's identical.<br/>";
				continue;
			}
			// Otherwise if it exists, I want to be able to inform the user that it was replaced and not just installed.
			elseif($nf->exists()){
				$action = 'Replaced';
			}
			// Otherwise otherwise, it's a new file.
			else{
				$action = 'Installed';
			}
			
			try{
				$f->copyTo($nf, true);
			}
			catch(\Exception $e){
				throw new \InstallerException('Unable to copy [' . $f->getFilename() . '] to [' . $nf->getFilename() . ']');
			}
			
			$changes[] = $action . ' ' . $nf->getFilename();
		}
		
		if(!sizeof($changes)) return false;
		
		// Make sure the asset cache is purged!
		\Core::Cache()->delete('asset-resolveurl');
		
		return $changes;
	}
}
