<?php
/**
 * File for class Parser definition in the coreplus project
 * 
 * @author Charlie Powell <charlie@eval.bz>
 * @date 20130409.1056
 * @package Core\Utilities\Changelog
 */

namespace Core\Utilities\Changelog;


/**
 * Class Parser description
 * 
 * @package Core\Utilities\Changelog
 */
class Parser {

	private $_file;
	private $_name;
	private $_sections;

	public function __construct($name, $file){
		$this->_name = $name;
		$this->_file = $file;
	}

	public function exists(){
		return file_exists($this->_file);
	}

	/**
	 * Parse the given changelog file.
	 *
	 * @throws Exception
	 */
	public function parse(){
		// Blank out the existing sections, if any.
		$this->_sections = array();

		if($this->exists()){

			// Start reading the file contents until I find the header, (probably on line 1, but you never know).
			$fh = fopen($this->_file, 'r');
			if(!$fh){
				throw new Exception('Unable to open file ' . $this->_file . ' for reading');
			}

			$currentsection = null;
			$inchange = false;

			while(!feof($fh)){
				// Get the line, (up to 512 characters), trimming the right side, (newline character).
				$line = rtrim(fgets($fh, 512));

				// Just outright skip blank lines.
				if(trim($line) == '') continue;

				// Does this line look like a new header?
				if(stripos($line, $this->_name) === 0){
					// Headers are lines that MUST start with the name of the package.
					// Everything else has a space or something before it.
					// Since it's a new section, I can simply change the pointer to this new section.
					$currentsection = new Section();
					$currentsection->parseHeader($line);
					if($currentsection->getVersion()){
						$this->_sections[ $currentsection->getVersion() ] = $currentsection;
					}
				}
				elseif($currentsection){
					$currentsection->parseLine($line);
				}
			}
			fclose($fh);
		}
	}

	/**
	 * Get a section by a particular version number.
	 * Will create a section if it doesn't exist.
	 *
	 * @param $version
	 * @return Section
	 */
	public function getSection($version){
		if(!isset($this->_sections[$version])){
			$s = new Section();
			$s->parseHeader($this->_name . ' ' . $version);
			$this->_sections[$version] = $s;

			// Make sure that the sections remain sorted by version.
		}

		return $this->_sections[$version];
	}

	/**
	 * Sort the sections.
	 * This is called internally, so you shouldn't need to worry about it.
	 */
	public function sort(){

		// I'd like to sort the sections by version number.
		$versioned = array();
		foreach($this->_sections as $s){
			$versioned[ $s->getVersion() ] = $s;
		}

		krsort($versioned);

		$this->_sections = array_values($versioned);
	}

	/**
	 * Get the filename for this changelog.
	 */
	public function getFilename(){
		return $this->_file;
	}

	/**
	 * Save this CHANGELOG back out as the standard format
	 *
	 * @param null|string $filename Set to a string to save as another file instead of the original
	 */
	public function save($filename = null){

		// Default to the original filename.
		if($filename === null){
			$filename = $this->getFilename();
		}

		// Make sure they're sorted.
		$this->sort();

		$out = '';
		foreach($this->_sections as $s){
			$out .= $s->fetchFormatted() . "\n";
		}

		// make sure the directory exists.
		if(!is_dir(dirname($filename))){
			mkdir(dirname($filename));
		}
		file_put_contents($filename, $out);
	}

	public function saveHTML($filename){
		// Make sure they're sorted.
		$this->sort();

		$out = '<h1>' . $this->_name . ' Change Log</h1>' . "\n";
		foreach($this->_sections as $s){
			$out .= $s->fetchAsHTML() . "\n<hr/>\n";
		}

		// make sure the directory exists.
		if(!is_dir(dirname($filename))){
			mkdir(dirname($filename));
		}
		file_put_contents($filename, $out);
	}
}