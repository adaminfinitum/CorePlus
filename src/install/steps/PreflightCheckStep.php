<?php
/**
 * File for class PreflightCheckStep definition in the coreplus project
 * 
 * @author Charlie Powell <charlie@eval.bz>
 * @date 20130320.0640
 * @package Core\Installer
 */

namespace Core\Installer;


/**
 * Class PreflightCheckStep description
 *
 * @package Core\Installer
 */
class PreflightCheckStep extends InstallerStep {
	public function execute() {
		// Some more preflight checks, such as htaccess presence and permissions.
		// See https://rm.eval.bz/issues/29 for more info.

		$tests = array(
			$this->testPHPVersion(),
			$this->testRewrite(),
			$this->testPHPXML(),
			$this->testHTAccessFile(),
			$this->testConfigFile(),
		);

		// Run through all these checks and see if there were any errors.
		$good = true;
		foreach($tests as $test){
			if($test['status'] == 'error') $good = false;
		}

		if($good && $_SERVER['REQUEST_METHOD'] == 'POST'){
			// user clicked "next"
			// Mark this task as passed and proceed to the next.
			$this->setAsPassed();
			reload();
		}

		$tpl = $this->getTemplate();

		$this->title = 'Preflight Checks';
		$tpl->assign('tests', $tests);
		$tpl->assign('good', $good);
	}

	/**
	 * Test that the PHP version is new enough.
	 *
	 * @return array
	 */
	private function testPHPVersion() {
		$version = phpversion();

		// @todo Write a blog post on upgrading php on centos.
		if(version_compare($version, '5.4.0', '<')){
			return [
				'title' => 'php',
				'status' => 'error',
				'message' => 'php is too old',
				'description' => 'Your version of PHP is ' . $version . '.  The bare minimum required is 5.4.0!  Please upgrade PHP before proceeding.'
			];
		}

		if(version_compare($version, '5.4.6', '<')){
			return [
				'title' => 'php',
				'status' => 'warning',
				'message' => 'php might be too old',
				'description' => 'Your version of PHP is ' . $version . '.  It is probably a good idea to upgrade to newest version, ya know, for security and all.',
			];
		}

		return [
			'title' => 'php',
			'status' => 'passed',
			'message' => 'php is new enough!',
			'description' => 'Your version of PHP is ' . $version . '.  That\'ll work.',
		];
	}

	/**
	 * Check that mod_rewrite is available.
	 *
	 * @return array
	 */
	private function testRewrite() {
		// Check if mod_rewrite is available
		// This will only be the case if php is running in native mode.
		if(function_exists('apache_get_modules')){
			if(!in_array('mod_rewrite', apache_get_modules())){
				// @todo Write blog post about mod_rewrite and how to enable it on different servers.
				return [
					'title' => 'mod_rewrite',
					'status' => 'error',
					'message' => 'mod_rewrite is not available',
					'description' => 'In order to use Core Plus, the apache module mod_rewrite must be installed and enabled!'
				];
			}
			else{
				return [
					'title' => 'mod_rewrite',
					'status' => 'passed',
					'message' => 'mod_rewrite is available!',
					'description' => '',
				];
			}
		}
		else{
			// This is not working again.... gah
			// PHP is running as CGI.... guess I have to do this the long way :/
			$fp = fsockopen((isset($_SERVER['HTTPS']) ? 'ssl://' : '') . $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT']);
			if($fp) {
				fwrite($fp, "GET " . ROOT_WDIR . "install/test_rewrite/ HTTP/1.0\r\n\r\n");
				stream_set_timeout($fp, 2);
				$line = trim(fgets($fp, 512));
				if(strpos($line, '300 Multiple Choices') === false){
					return [
						'title' => 'mod_rewrite',
						'status' => 'warning',
						'message' => 'mod_rewrite may not available',
						'description' => 'Preliminary tests show that url rewriting may not be available.  If this is the case, you will not be able to fully use Core Plus.  Proceed with caution.'
					];
				}
				else{
					return [
						'title' => 'mod_rewrite',
						'status' => 'passed',
						'message' => 'mod_rewrite is available!',
						'description' => '',
					];
				}
			}
		}
	}

	/**
	 * Check and make sure that php-xml (or php5-xml), is available.
	 * This is needed for use of DOMDocument.
	 *
	 * @return array
	 */
	private function testPHPXML(){
		// Test the presence of DOMDocument, this is provided by php-xml
		if(!class_exists('DOMDocument')){
			return [
				'title' => 'php-xml',
				'status' => 'error',
				'message' => 'php-xml is not available',
				'description' => 'Core Plus relies heavily on XML files and requires php-xml to be installed.  On Debian distributions, this is called php5-xml.'
			];
		}
		else{
			return [
				'title' => 'php-xml',
				'status' => 'passed',
				'message' => 'php-xml is available!',
				'description' => '',
			];
		}
	}

	/**
	 * Configuration.xml file checks.
	 * This includes being able to write to the file and making sure that it's not world readable.
	 *
	 * @return array
	 */
	private function testConfigFile() {
		// The configuration file needs to be modified!
		if(!file_exists(ROOT_PDIR . 'config/configuration.xml') && !is_writable(ROOT_PDIR. 'config')){
			return [
				'title' => 'configuration.xml',
				'status' => 'warning',
				'message' => 'config/ is not writable',
				'description' => 'There is no configuration.xml file and config/ is not writable.  This means that you will have to manually create and update this file.'
			];
		}

		// The configuration file needs to be modified!
		if(file_exists(ROOT_PDIR . 'config/configuration.xml') && !is_writable(ROOT_PDIR. 'config/configuration.xml')){
			return [
				'title' => 'configuration.xml',
				'status' => 'warning',
				'message' => 'config/configuration.xml is not writable',
				'description' => 'config/configuration.xml is not writable.  This means that you will have to manually update this file.'
			];
		}

		/*
		// The configuration file should absolutely not be accessable from the outside world, this includes php fopen'ing the file!
		$fp = fsockopen((isset($_SERVER['HTTPS']) ? 'ssl://' : '') . $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT']);
		if($fp) {
			fwrite($fp, "GET " . ROOT_WDIR . "config/configuration.xml HTTP/1.0\r\n\r\n");
			stream_set_timeout($fp, 2);
			$line = trim(fgets($fp, 512));
			if(strpos($line, '200 OK') !== false){
				// OH NOES!
				return [
					'title' => 'configuration.xml',
					'status' => 'error',
					'message' => 'config/configuration.xml is not writable',
					'description' => 'config/configuration.xml is publicly accessible!  This is a huge security hole and must be fixed before installation can continue.  Please ensure that there is a .htaccess file in that directory and it denies all access to all files.'
				];
				$page = new InstallPage();
				$page->assign('error', '');
				$page->template = 'templates/preflight_requirements.tpl';
				$page->render();
			}
			else{
				// Because otherwise the admin will get "Access to blah blah was denied, OH NOEZ"
				error_log('Access to config/configuration.xml was denied, (that is a GOOD thing!)');
			}
		}
		*/

		return [
			'title' => 'configuration.xml',
			'status' => 'passed',
			'message' => 'config/configuration.xml can be written',
			'description' => ''
		];
	}

	/**
	 * Check that the core .htaccess file can be written.
	 *
	 * @return array
	 */
	private function testHTAccessFile(){
		if(!is_writable(ROOT_PDIR)){
			return [
				'title' => '.htaccess',
				'status' => 'warning',
				'message' => ROOT_PDIR . ' is not writable',
				'description' => 'The root directory is not writable.  This is not a critical issue, but you will need to create the .htaccess file manually.'
			];
		}

		return [
			'title' => '.htaccess',
			'status' => 'passed',
			'message' => ROOT_PDIR . ' is writable',
			'description' => ''
		];
	}
}
