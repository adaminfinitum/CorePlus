<?php

/**
 * Description of UpdaterHelper
 *
 * @author powellc
 */
class UpdaterHelper {
	
	/**
	 * Perform a lookup on any repository sites installed and get a list of provided pacakges.
	 * 
	 * @return array
	 */
	public static function GetUpdates(){
		// Allow this to be cached for x amount of time.  This will save the number of remote requests.
		if(false && isset($_SESSION['updaterhelper_getupdates']) && $_SESSION['updaterhelper_getupdates']['expire'] <= time()){
			return $_SESSION['updaterhelper_getupdates']['data'];
		}
		
		$corevers = Core::GetComponent()->getVersion();
		
		// Build a list of components currently installed, this will act as a base.
		$components = array();
		foreach(ComponentHandler::GetAllComponents() as $c){
			$n = strtolower($c->getName());
			if(!isset($components[$n])) $components[$n] = array();
			$components[$n][$c->getVersion()] = array(
				'name' => $n,
				'title' => $c->getName(),
				'version' => $c->getVersion(),
				'type' => $c->getType(),
				'source' => 'installed',
				'description' => $c->getDescription(),
				'provides' => $c->getProvides(),
				'requires' => $c->getRequires(),
				'location' => null,
				'status' => 'installed',
			);
		}
		
		// Now, look up components from all the updates sites.
		$updatesites = UpdateSiteModel::Find('enabled = 1');
		foreach($updatesites as $site){
			
			$file = new File_remote_backend($site->get('url'));
			$file->username = $site->get('username');
			$file->password = $site->get('password');
			
			$repoxml = new RepoXML();
			$repoxml->loadFromFile($file);
			$rootpath = dirname($site->get('url')) . '/';
			foreach($repoxml->getPackages() as $pkg){
				// Already installed and is up to date, don't do anything.
				if($pkg->isCurrent()) continue;
				
				$n = strtolower($pkg->getName());
				
				if(!ComponentHandler::GetComponent($n)){
					$status = 'new';
				}
				else{
					$status = 'update';
				}
				
				// Check and see if this version is already listed in the repo.
				if(!isset($components[$n][$pkg->getVersion()])){
					$components[$n][$pkg->getVersion()] = array(
						'name' => $n,
						'title' => $pkg->getName(),
						'version' => $pkg->getVersion(),
						'type' => $c->getType(),
						'source' => 'repo-' . $site->get('id'),
						'description' => $pkg->getDescription(),
						'provides' => $pkg->getProvides(),
						'requires' => $pkg->getRequires(),
						'location' => $rootpath . $pkg->getFileLocation(),
						'status' => $status,
					);
				}		
				
				//var_dump($pkg->asPrettyXML()); die();
			}
		}
		
		// Give me the components in alphabetical order.
		ksort($components);
		
		// And sort the versions.
		foreach($components as $k => $v){
			ksort($components[$k]);
		}
		
		// Cache this for next pass.
		$_SESSION['updaterhelper_getupdates'] = array();
		$_SESSION['updaterhelper_getupdates']['data'] = $components;
		$_SESSION['updaterhelper_getupdates']['expire'] = time() + 60;
		
		return $components;
	}
	
	public static function Install($name, $version, $dryrun = false){
		$components = UpdaterHelper::GetUpdates();
		
		// Make sure the name and version exist in the updates list.
		if(!isset($components[$name])){
			return array('status' => 0, 'message' => 'Component ' . $name . ' does not appear to be valid.');
		}
		if(!isset($components[$name][$version])){
			return array('status' => 0, 'message' => 'Component ' . $name . ' does not appear to have requested version.');
		}
		
		// A queue of components to check.
		$pendingqueue = array($components[$name][$version]);
		// A queue of components that will be installed that have satisfied dependencies.
		$checkedqueue = array();
		$lastsizeofqueue = 99;
		
		do{
			foreach($pendingqueue as $k => $c){
				$good = true;
				foreach($c['requires'] as $r){
					$result = UpdaterHelper::CheckRequirement($r);
					if($result === false){
						return array('status' => 0, 'message' => 'Component ' . $name . ' requires ' . $r['name'] . ' ' . $r['version']);
					}
					elseif($result === true){
						// yay
						continue;
					}
					else{
						die('Yeah... finish this part.');
					}
				}
				
				if($good === true){
					$checkedqueue[] = $c;
					unset($pendingqueue[$k]);
				}
			}
			
			$lastsizeofqueue = sizeof($pendingqueue);
		}
		while(sizeof($pendingqueue) && sizeof($pendingqueue) != $lastsizeofqueue);
		
		
		// If dryrun only was requested, just return the status here.
		if($dryrun){
			return array('status' => 1, 'message' => 'All dependencies are met, ok to install', 'data' => $checkedqueue);
		}
		
		$repos = array();
		
		foreach($checkedqueue as $component){
			if(strpos($component['source'], 'repo-') !== false){
				// Look up that repo's connection information, since username and password may be required.
				if(!isset($repos[$component['source']])){
					$repos[$component['source']] = new UpdateSiteModel(substr($component['source'], 5));
				}
				$remotefile = new File_remote_backend($component['location']);
				$remotefile->username = $repos[$component['source']]->get('username');
				$remotefile->password = $repos[$component['source']]->get('password');
				
				$obj = $remotefile->getContentsObject();
				if(!$obj->verify()){
					return array('status' => 0, 'message' => 'Invalid GPG signature for ' . $component['title']);
				}
				
				// Decrypt the signed file.
				$localfile = $obj->decrypt('tmp/updater/');
				$localobj = $localfile->getContentsObject();
				
				// This tarball will be extracted to a temporary directory, then copied from there.
				$tmpdir = $localobj->extract('tmp/installer-' . Core::RandomHex(4));
				
				// Destination directory it will be installed to.
				switch($component['type']){
					case 'core':
						$destbase = ROOT_PDIR;
						break;
					case 'component':
						$destbase = ROOT_PDIR . 'components/' . $component['name'] . '/';
						break;
					default:
						return array('status' => 0, 'message' => 'Invalid component type [' . $component['type'] . ']' . ' for ' . $component['title']);
						break;
				}
				
				// Now that the data is extracted in a temporary directory, extract every file in the destination.
				$datadir = $tmpdir->get('data/');
				if(!$datadir){
					return array('status' => 0, 'message' => 'Invalid component ' . $component['title'] . ', does not contain a data directory.');
				}
				
				$queue = array($datadir);//$datadir->ls();
				$x = 0;
				
				do{
					++$x;
					$queue = array_values($queue);
					foreach($queue as $k => $q){
						if($q instanceof Directory_local_backend){
							unset($queue[$k]);
							// Just queue directories up to be scanned.
							// (don't do array merge, because I'm inside a foreach loop)
							foreach($q->ls() as $subq) $queue[] = $subq;
						}
						else{
							// It's a file, copy it over.
							// To do so, resolve the directory path inside the temp data dir.
							$dest = $destbase . substr($q->getFilename(), strlen($datadir->getPath()));
							$newfile = $q->copyTo($dest, true);
							
							unset($queue[$k]);
						}
					}
				}
				while(sizeof($queue) > 0 && $x < 15);
				
				// Cleanup the temp directory
				$tmpdir->remove();
				
				// and w00t, the files should be extracted.  Do the actual installation.
				switch($component['type']){
					case 'core':
						$c = ComponentHandler::GetComponent('core');
						$c->upgrade();
						break;
					case 'component':
						$c = new Component($component['name']);
						$c->load();
						// if it's installed, switch to that version and upgrade.
						if($c->isInstalled()){
							$c = ComponentHandler::GetComponent($component['name']);
							// Make sure I get the new XML
							$c->load();
							// And upgrade
							$c->upgrade();
						}
						else{
							// It's a new insatllation.
							$c->install();
						}
				}
			}
		}
		
		// yay...
		return array('status' => 1, 'message' => 'Performed all operations successfully');
	}
	
	/**
	 * Simple function to scan through the components provided for one that
	 * satisfies the requirement.
	 * 
	 * @param array $requirement
	 * @return array | false
	 */
	public static function CheckRequirement($requirement){
		
		// This will check if the requirement is already met.
		switch($requirement['type']){
			case 'library':
				if(ComponentHandler::IsLibraryAvailable($requirement['name'], $requirement['version'], $requirement['operation'])){
					return true;
				}
				break;
			case 'jslibrary':
				if(ComponentHandler::IsJSLibraryAvailable($requirement['name'], $requirement['version'], $requirement['operation'])){
					return true;
				}
				break;
			case 'component':
				if(ComponentHandler::IsComponentAvailable($requirement['name'], $requirement['version'], $requirement['operation'])){
					return true;
				}
				break;
		}
		
		// @todo Run through the components that are available via an update.
		
		// Requirement not met... ok.  This needs to be conveyed to the calling script.
		return false;
	}
}

?>