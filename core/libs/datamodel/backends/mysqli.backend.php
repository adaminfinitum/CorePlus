<?php
/**
 * MySQLi datamodel backend system
 * 
 * @package Core
 * @subpackage Datamodel
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
 * Description of mysql
 *
 * @author powellc
 */
class DMI_mysqli_backend implements DMI_Backend {
	
	/**
	 * Number of reads done from the database from the 'execute' function.
	 * @var int
	 */
	private $_reads = 0;
	
	/**
	 * Number of writes done to the database from the 'execute' function.
	 * @var int
	 */
	private $_writes = 0;
	
	
	/**
	 *
	 * @var mysqli
	 */
	private $_conn = null;
	
	
	/**
	 * Create a new connection to a mysql server.
	 * 
	 * @param type $host
	 * @param type $user
	 * @param type $pass
	 * @param type $database
	 * @return type 
	 */
	public function connect($host, $user, $pass, $database){
		
		// Did the host come in with a port attached?
		if(strpos($host, ':') !== false) list($host, $port) = explode(':', $host);
		else $port = 3306;
		
		if(!class_exists('mysqli', false)){
			throw new DMI_Exception('Unable to locate the PHP MySQLi library.  Please switch to a supported driver or see http://us3.php.net/manual/en/book.mysqli.php for more information.');
		}
		
		$this->_conn = new mysqli();
		
		// Errors, SHHH!  I'll handle them manually!
		@$this->_conn->real_connect($host, $user, $pass, $database, $port);
		
		if($this->_conn->errno){
			throw new DMI_Exception($this->_conn->error, null, null, $this->_conn->errno);
		}
		
		
		return ($this->_conn);
	}
	
	public function execute(Dataset $dataset){
		switch($dataset->_mode){
			case Dataset::MODE_GET:
				++$this->_reads;
				$this->_executeGet($dataset);
				break;
			case Dataset::MODE_INSERT:
				++$this->_writes;
				$this->_executeInsert($dataset);
				break;
			case Dataset::MODE_UPDATE:
				++$this->_writes;
				$this->_executeUpdate($dataset);
				break;
			case Dataset::MODE_DELETE:
				++$this->_writes;
				$this->_executeDelete($dataset);
				break;
			case Dataset::MODE_COUNT:
				++$this->_reads;
				$this->_executeCount($dataset);
				break;
			default:
				throw new Exception('Invalid dataset mode [' . $dataset->_mode . ']');
				break;
		}
	}
	
	
	public function tableExists($tablename){
		$q = "SHOW TABLES LIKE ?";
		$rs = $this->_rawExecute($q, $tablename);
		return ($rs->num_rows);
	}
	
	public function createTable($table, $schema){
		// Check if the table exists to begin with.
		if($this->tableExists($table)){
			throw new DMI_Exception('Cannot create table [' . $table . '] as it already exists');
		}
		// Table doesn't exist, just do a simple create
		$q = 'CREATE TABLE `' . $table . '` ';
		$directives = array();
		foreach($schema['schema'] as $column => $coldef){
			$d = '`' . $column . '`';
			
			if(!isset($coldef['type'])) $coldef['type'] = Model::ATT_TYPE_TEXT; // Default if not present.
			if(!isset($coldef['maxlength'])) $coldef['maxlength'] = false;
			
			// Will provide valid mysql string for the data type.
			$d .= ' ' . $this->_getSchemaFromType($coldef);
			
			if(!isset($coldef['null'])) $coldef['null'] = false;
			$d .= ' ' . (($coldef['null'])? 'NULL' : 'NOT NULL');
			
			if(!isset($coldef['default'])) $coldef['default'] = false;
			if($coldef['default']) $d .= ' DEFAULT ' . "'" . $coldef['default'] . "'";
			
			if(!isset($coldef['comment'])) $coldef['comment'] = false;
			if($coldef['comment']) $d .= ' COMMENT \'' . $coldef['comment'] . '\' ';
			
			if($coldef['type'] == Model::ATT_TYPE_ID) $d .= ' AUTO_INCREMENT';
			
			$directives[] = $d;
		}
		

		foreach($schema['indexes'] as $key => $idxdef){
			$d = '';
			if($key == 'primary'){
				$d .= 'PRIMARY KEY ';
			}
			elseif(strpos($key, 'unique:') !== false){
				// Unique keys should all have "unique:something" as the key to differentiate them from regular indexes.
				$d .= 'UNIQUE KEY `' . substr($key, 7) . '` ';
			}
			else{
				$d .= 'KEY `' . $key . '` ';
			}

			// Tack on the columns that are in this index.
			if(is_array($idxdef)){
				$d .= '(`' . implode('`, `', $idxdef) . '`)';
			}
			else{
				$d .= '(`' . $idxdef . '`)';
			}
			
			$directives[] = $d;
		}
		
		if(!sizeof($directives)){
			throw new DMI_Exception('Cowardly refusing to create a table [ ' . $table . ' ] with no column definitions!');
		}

		$q .= '( ' . implode(', ', $directives) . ' ) ';
		
		//echo $q . '<br/>';
		// and GO!
		return ($this->_rawExecute($q));
		
		
		//$q .= 'ENGINE=' . $tblnode->getAttribute('engine') . ' ';
		//$q .= 'DEFAULT CHARSET=' . $tblnode->getAttribute('charset') . ' ';
		//if($tblnode->getAttribute('comment')) $q .= 'COMMENT=\'' . $tblnode->getAttribute('comment') . '\' ';
		// @todo should AUTO_INCREMENT be available here?
	}
	
	public function modifyTable($table, $newschema){
		$changed = false;
		
		// Check if the table exists to begin with.
		if(!$this->tableExists($table)){
			throw new DMI_Exception('Cannot modify table [' . $table . '] as it does not exist');
		}
		
		// Table does exist... I need to do a merge of the data schemas.
		// Create a temp table to do the operations on.
		$this->_rawExecute('CREATE TEMPORARY TABLE _tmptable LIKE ' . $table);
		$this->_rawExecute('INSERT INTO _tmptable SELECT * FROM ' . $table);

		// My simple counter.  Helps keep track of column order.
		$x = 0;
		// This will contain the current table schema of the tmptable.
		// It will get reloaded after any change.
		$schema = $this->_describeTableSchema('_tmptable');
		
		// To make the indexing logic a little easier...
		foreach($newschema['indexes'] as $k => $v){
			if(!is_array($v)) $newschema['indexes'][$k] = array($v);
		}
		if(!isset($newschema['indexes']['primary'])) $newschema['indexes']['primary'] = false; // No primary key on this table.
		
		if(!sizeof($newschema['schema'])){
			throw new DMI_Exception('No schema provided for table ' . $table);
		}
		
		
		// Check if there's still a column with the primary ID flag set.
		// that will not be the same in the resulting table.
		$oldprimaries = array();
		foreach($schema['def'] as $d => $d2){
			if($d2['key'] == 'PRI'){
				$oldprimaries[] = $d;
			}
		}
		if($oldprimaries != $newschema['indexes']['primary']){
			if(sizeof($oldprimaries) == 1){
				// Check its structure as well, it may be an auto_increment.
				$column = $oldprimaries[0];
				$coldef = $schema['def'][$column];
				
				if($coldef['extra'] == 'auto_increment'){
					$q = "ALTER TABLE `_tmptable` CHANGE `$column` `$column` " . $coldef['type'] . ' ';
					$q .= (($coldef['null'] == 'NO')? 'NOT NULL' : 'NULL') . ' ';
					if($coldef['null'] == 'YES' && $coldef['default'] === null) $default = 'NULL';
					elseif($coldef['default'] !== null) $default = "'" . $this->_conn->escape_string($coldef['default']) . "'";
					else $default = false;
					if($default) $q .= 'DEFAULT ' . $default . ' ';
					$this->_rawExecute($q);
				}
			}
			
			$this->_rawExecute('ALTER TABLE _tmptable DROP PRIMARY KEY');
		}
		
		
		foreach($newschema['schema'] as $column => $coldef){
			if(!isset($coldef['type'])) $coldef['type'] = Model::ATT_TYPE_TEXT; // Default if not present.
			if(!isset($coldef['maxlength'])) $coldef['maxlength'] = false;
			if(!isset($coldef['null'])) $coldef['null'] = false;
			if(!isset($coldef['comment'])) $coldef['comment'] = false;
			if(!isset($coldef['default'])) $coldef['default'] = false;
			
			$type = $this->_getSchemaFromType($coldef);
			$null = ($coldef['null'])? 'NULL' : 'NOT NULL'; // Required for the query.
			$checknull = ($coldef['null'])? 'YES' : 'NO'; // Required for the schema check.
			
			if($coldef['null'] && $coldef['default'] === null) $default = 'NULL';
			elseif($coldef['default'] !== false) $default = "'" . $this->_conn->escape_string($coldef['default']) . "'";
			else $default = false;
			//(($coldef['default'])? "'" . $this->_conn->escape_string($coldef['default']) . "'" : (($coldef['null'])? 'NULL' : "''"));
			$checkdefault = (($coldef['default'])? $coldef['default'] : (($coldef['null'])? 'NULL' : ''));
			
			
			//'type' => string 'string' (length=6)
			//'maxlength' => int 256
			//'required' => boolean true
			//'options' => array()
			//'comment' => string 'The define constant to map the value to on system load.'

			
			// coldef should now contain:
			// array(
			//   'field' => 'name_of_field',
			//   'type' => 'type definition, ie: int(11), varchar(32), etc',
			//   'null' => 'NO|YES',
			//   'key' => 'PRI|MUL|UNI|[blank]'
			//   'default' => default value
			//   'extra' => 'auto_increment|[blank]',
			//   'collation' => 'some collation type',
			//   'comment' => 'some comment',
			// );

			// Check that the current column is in the same location as in the database.
			if(!(isset($schema['ord'][$x]) && $schema['ord'][$x] == $column)){
				// Is it even present?
				if(isset($schema['def'][$column])){
					$changed = true;
					// w00t, move it to this position.
					// ALTER TABLE `test` MODIFY COLUMN `fieldfoo` mediumint AFTER `something`
					$q = 'ALTER TABLE _tmptable MODIFY COLUMN `' . $column . '` ' . $type . ' ';
					$q .= ($x == 0)? 'FIRST' : 'AFTER `' . $schema['ord'][$x-1] . '`';
					$this->_rawExecute($q);

					// Moving the column will change the definition... reload that.
					$schema = $this->_describeTableSchema('_tmptable');
				}
				// No? Ok, create it.
				else{
					$changed = true;
					// ALTER TABLE `test` ADD `newfield` TEXT NOT NULL AFTER `something` 
					$q = 'ALTER TABLE _tmptable ADD `' . $column . '` ' . $type . ' ';
					$q .= $null . ' ';
					$q .= ($x == 0)? 'FIRST' : 'AFTER `' . $schema['ord'][$x-1] . '`';
					$this->_rawExecute($q);

					// Adding the column will change the definition... reload that.
					$schema = $this->_describeTableSchema('_tmptable');
				}
			}
			
			// Now the column should exist and be in the correct location.  Check its structure.
			// ALTER TABLE `test` CHANGE `newfield` `newfield` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL 
			// Check its AI and primary states first.
			if(
				$coldef['type'] == Model::ATT_TYPE_ID && ($newschema['indexes']['primary'] && in_array($column, $newschema['indexes']['primary'])) && 
				(!isset($schema['def'][$column]) || ($schema['def'][$column]['extra'] == '' && $schema['def'][$column]['key'] == ''))
			){
				$changed = true;
				// An AI value was added to the table.  I need to add that column as the primary key first, then
				// tack on the AI property.
				// ALTER TABLE `test` ADD PRIMARY KEY(`id`)
				$q = 'ALTER TABLE _tmptable ADD PRIMARY KEY (`' . $column . '`)';
				$this->_rawExecute($q);
				$q = 'ALTER TABLE _tmptable CHANGE `' . $column . '` `' . $column . '` ' . $type . ' ';
				$q .= $null . ' ';
				$q .= 'AUTO_INCREMENT';
				$this->_rawExecute($q);

				// And reload the schema.
				$schema = $this->_describeTableSchema('_tmptable');
			}

			// Now, check everything else.
			if(
				$type != $schema['def'][$column]['type'] ||
				$checknull != $schema['def'][$column]['null'] ||
				$checkdefault != $schema['def'][$column]['default'] ||
				//$coldef['collation'] != $schema['def'][$coldef['field']]['collation'] || 
				$coldef['comment'] != $schema['def'][$column]['comment']
			){
				$changed = true;
				$q = 'ALTER TABLE _tmptable CHANGE `' . $column . '` `' . $column . '` ';
				$q .= $type . ' ';
				//if($coldef['collation']) $q .= 'COLLATE ' . $coldef['collation'] . ' ';
				$q .= $null . ' ';
				if($default !== false) $q .= 'DEFAULT ' . $default . ' ';
				if($coldef['comment']) $q .= 'COMMENT \'' . $coldef['comment'] . '\' ';
				//echo $q . '<br/>';
				$this->_rawExecute($q);

				// And reload the schema.
				$schema = $this->_describeTableSchema('_tmptable');
			}

			$x++;
		} // foreach($this->getElementFrom('column', $tblnode, false) as $colnode)

//var_dump($column, $type, $coldef, $schema); die();
		// The columns should be done; onto the indexes.
		$indexes = $this->_describeTableIndexes('_tmptable');
		
		//var_dump($newschema['indexes'], $schema); die();
		$keysgood = array(); // Used to know what keys have been validated and conformed.
		foreach($newschema['indexes'] as $idx => $columns){
			
			// Damn negatives for variables.... 1 means that it's NOT unique, ie: a standard key.
			$nonunique = (!(strpos($idx, 'unique:') === 0 || $idx == 'primary'));
			
			// Ensure that idxdef['column'] is an array if it's not.
			if(!is_array($columns)) $columns = array($columns);
			
			// Figure out the names for this so I only have to have the logic executed once.
			if($idx == 'primary'){
				$idxkey = 'PRIMARY';
				$idxname = 'PRIMARY KEY';
				$idxdropname = 'PRIMARY KEY';
			}
			elseif($nonunique){
				$idxkey = $idx;
				$idxname = 'KEY ' . $idxkey;
				$idxdropname = 'INDEX ' . $idxkey;
			}
			else{
				$idxkey = substr($idx, 7);
				$idxname = 'UNIQUE KEY ' . $idxkey; // Create requires the UNIQUE flag, but
				$idxdropname = 'INDEX ' . $idxkey; // Drop does not require it.
			}
			
			
			// These are the index creates/modifies.
			if(!isset($indexes[$idxkey])){
				$changed = true;
				// Doesn't exist, create it.
				$this->_rawExecute('ALTER TABLE `_tmptable` ADD ' . $idxname . ' (`' . implode('`, `', $columns) . '`)');
				$keysgood[] = $idxkey;
				$indexes = $this->_describeTableIndexes('_tmptable');
			}
			elseif(
				$indexes[$idxkey]['columns'] != $columns ||
				$nonunique != ($indexes[$idxkey]['nonunique'])
			){
				$changed = true;
				// Rebuild it.
				$this->_rawExecute('ALTER TABLE `_tmptable` DROP ' . $idxdropname . '');
				$this->_rawExecute('ALTER TABLE `_tmptable` ADD ' . $idxname . ' (`' . implode('`, `', $columns) . '`)');
				$keysgood[] = $idxkey;
				$indexes = $this->_describeTableIndexes('_tmptable');
			}
			else{
				// PK Matches, nothing needs to be done.
				$keysgood[] = $idxkey;
			}
			
			// And the key deletions.
			foreach($indexes as $idx => $val){
				if(!in_array($idx, $keysgood)){
					$changed = true;
					// DROP IT!
					$this->_rawExecute('ALTER TABLE `_tmptable` DROP INDEX ' . $idx . '');
				}
			}
			
		} // foreach($this->getElementFrom('index', $tblnode, false) as $idxnode)

		if(!$changed){
			// Drop the table so it's ready for the next table.
			$this->_rawExecute('DROP TABLE _tmptable');
			
			return false;
		}
		else{
			// All operations should be completed now; move the temp table back to the original one.
			$this->_rawExecute('DROP TABLE `' . $table . '`');
			$this->_rawExecute('CREATE TABLE `' . $table . '` LIKE _tmptable');
			$this->_rawExecute('INSERT INTO `' . $table . '` SELECT * FROM _tmptable');

			// Drop the table so it's ready for the next table.
			$this->_rawExecute('DROP TABLE _tmptable');
			
			return true;
		}
	}
	
	public function readCount() {
		return $this->_reads;
	}
	
	public function writeCount() {
		return $this->_writes;
	}
	
	
	/**
	 * Translate the database-independent types into mysql-specific ones.
	 * 
	 * Used internally for some schema operations.
	 * 
	 * @param array $coldef
	 * @return string 
	 */
	public function _getSchemaFromType($coldef){
		
		switch($coldef['type']){
			case Model::ATT_TYPE_BOOL:
				$type = " enum('0','1')";
				break;
			case Model::ATT_TYPE_ENUM:
				if(!(isset($coldef['options']) && is_array($coldef['options']) && sizeof($coldef['options']))){
					throw new DMI_Exception('Invalid column definition for ' . $table . '.' . $column . ', type ENUM must include at least one option.');
				}
				foreach($coldef['options'] as $k => $opt){
					// Ensure that any single quotes are escaped out.
					$coldef['options'][$k] = str_replace("'", "\\'", $opt);
				}
				$type = "enum('" . implode("','", $coldef['options']) . "')";
				break;
			case Model::ATT_TYPE_FLOAT:
				$type = "float";
				break;
			case Model::ATT_TYPE_ID:
				$type = "int(15)";
				break;
			case Model::ATT_TYPE_INT:
				$type = "int(15)";
				break;
			case Model::ATT_TYPE_STRING:
				$maxlength = ($coldef['maxlength'])? $coldef['maxlength'] : 255; // It needs something...
				$type = "varchar($maxlength)";
				break;
			case Model::ATT_TYPE_TEXT:
				$type = "text";
				break;
			case Model::ATT_TYPE_CREATED:
			case Model::ATT_TYPE_UPDATED:
				$type = 'int(11)';
				break;
			default:
				throw new DMI_Exception('Unsupported model type for ' . $table . '.' . $column . ' [' . $coldef['type'] . ']');
				break;
		}
		
		return $type;
	}
	
	/**
	 * Reverse of getSchemaFromType.  Will return valid code and values for a database schema.
	 * @param type $colschema 
	 */
	/*public function _getTypeFromSchema($colschema){
		if(isset($colschema['Type'])) $t = $colschema['Type'];
		elseif(isset($colschema['type'])) $t = $colschema['type'];
		else return false;
		
		
	}*/
	
	
	public function _getTables(){
		$rs = $this->_rawExecute('SHOW TABLES');
		$ret = array();
		while($row = $rs->fetch_row()){
			$ret[] = $row[0];
		}
		return $ret;
	}
	
	public function _describeTableSchema($table){
		$rs = $this->_rawExecute('SHOW FULL COLUMNS FROM `' . $table . '`');
		$tabledef = array();
		$tableord = array();
		
		while($row = $rs->fetch_assoc()){
			$tabledef[$row['Field']] = array();
			foreach($row as $k => $v){
				$tabledef[$row['Field']][strtolower($k)] = $v;
			}
			$tableord[] = $row['Field'];
		}
		return array('def' => $tabledef, 'ord' => $tableord);
	}
	
	public function _describeTableIndexes($table){
		$rs = $this->_rawExecute('SHOW INDEXES FROM `' . $table . '`');
		$def = array();
		
		while($row = $rs->fetch_assoc()){
			// Non_unique | Key_name | Column_name | Comment
			if(isset($def[$row['Key_name']])){
				// Add a column.
				$def[$row['Key_name']]['columns'][] = $row['Column_name'];
			}
			else{
				$def[$row['Key_name']] = array(
					'name' => $row['Key_name'],
					'nonunique' => $row['Non_unique'],
					'comment' => $row['Comment'],
					'columns' => array($row['Column_name']),
				);
			}
		}
		return $def;
	}
	
	
	private function _executeGet(Dataset $dataset){
		// Generate a query to run.
		$q = 'SELECT';
		$ss = array();
		foreach($dataset->_selects as $s){
			// Check the escaping for this column.
			if(strpos($s, '.')) $s = '`' . str_replace('.', '`.`', trim($s)) . '`';
			// `*` is not a valid column.
			elseif($s == '*') $s = $s; // (just don't change it)
			else $s = '`' . $s . '`';
			
			$ss[] = $s;
		}
		$q .= ' ' . implode(', ', $ss);
		
		$q .= ' FROM `' . $dataset->_table . '`';
		
		if(sizeof($dataset->_where)){
			$wsg = array();
			$ws = array();
			foreach($dataset->_where as $w){
				$w['value'] = $this->_conn->real_escape_string($w['value']);
				if(!isset($wsg[$w['group']])) $wsg[$w['group']] = array();
				
				$wsg[$w['group']][] = "`{$w['field']}` {$w['op']} '{$w['value']}'";
			}
			
			// Combine each group member with its own seperator.
			foreach($wsg as $k => $v){
				$ws[] = ' ( ' . implode(' ' . $dataset->_wheregroups[$k] . ' ', $v) . ' ) ';
			}
			
			$q .= ' WHERE ' . implode(' AND ', $ws);
		}
		
		if($dataset->_limit) $q .= ' LIMIT ' . $dataset->_limit;
		
		if($dataset->_order){
			// Support keys as complex as "key DESC, value ASC"
			// and as simple as "sortnum"
			$orders = explode(',', $dataset->_order);
			$os = array();
			foreach($orders as $o){
				$o = trim($o);
				
				// Allow for mycolumn DESC or order ASC
				if(strpos($o, ' ') !== false) $os[] = '`' . substr($o, 0, strpos($o, ' ')) . '` ' . substr($o, strpos($o, ' ') + 1);
				// Allow for RAND() or other functions.
				elseif(strpos($o, '()') !== false) $os[] = $o;
				// Everything else just gets escaped normally.
				else $os[] = '`' . $o . '`';
			}
			$q .= ' ORDER BY ' . implode(', ', $os);
		}
		
		// Execute this and populate the dataset appropriately.
		$res = $this->_rawExecute($q);
		
		$dataset->num_rows = $res->num_rows;
		$dataset->_data = array();
		while($row = $res->fetch_assoc()){
			$dataset->_data[] = $row;
		}
	}
	
	private function _executeInsert(Dataset $dataset){
		// Generate a query to run.
		$q = "INSERT INTO `" . $dataset->_table . "`";
		
		$keys = array();
		$vals = array();
		foreach($dataset->_sets as $k => $v){
			$keys[] = "`$k`";
			$vals[] = "'" . $this->_conn->real_escape_string($v) . "'";
		}
		
		$q .= " ( " . implode(', ', $keys) . " )";
		$q .= " VALUES ";
		$q .= " ( " . implode(', ', $vals) . " )";
		
		
		// Execute this and populate the dataset appropriately.
		$res = $this->_rawExecute($q);
		
		$dataset->num_rows = $this->_conn->affected_rows;
		$dataset->_data = array();
		// Inserts don't have any data, but do have an ID, (which mysql handles internally)
		if($dataset->_idcol) $dataset->_idval = $this->_conn->insert_id;
	}
	
	private function _executeUpdate(Dataset $dataset){
		// Generate a query to run.
		$q = "UPDATE `" . $dataset->_table . "`";
		
		$sets = array();
		foreach($dataset->_sets as $k => $v){
			$sets[] = "`$k` = '" . $this->_conn->real_escape_string($v) . "'";
		}
		
		$q .= ' SET ' . implode(', ', $sets);
		
		if(sizeof($dataset->_where)){
			$ws = array();
			foreach($dataset->_where as $w){
				$w['value'] = $this->_conn->real_escape_string($w['value']);
				$ws[] = "`{$w['field']}` {$w['op']} '{$w['value']}'";
			}
			$q .= ' WHERE ' . implode(' AND ', $ws);
		}
		
		if($dataset->_limit) $q .= ' LIMIT ' . $dataset->_limit;
		
		// Execute this and populate the dataset appropriately.
		$res = $this->_rawExecute($q);
		
		$dataset->num_rows = $this->_conn->affected_rows;
		$dataset->_data = array();
		// Inserts don't have any data, but do have an ID, (which mysql handles internally)
		//if($dataset->_idcol) $dataset->_idval = $this->_conn->insert_id;
	}
	
	private function _executeDelete(Dataset $dataset){
		$q = 'DELETE FROM `' . $dataset->_table . '`';
		
		if(sizeof($dataset->_where)){
			$ws = array();
			foreach($dataset->_where as $w){
				$w['value'] = $this->_conn->real_escape_string($w['value']);
				$ws[] = "`{$w['field']}` {$w['op']} '{$w['value']}'";
			}
			$q .= ' WHERE ' . implode(' AND ', $ws);
		}
		
		if($dataset->_limit) $q .= ' LIMIT ' . $dataset->_limit;
		
		// Execute this and populate the dataset appropriately.
		$res = $this->_rawExecute($q);
		
		$dataset->num_rows = $this->_conn->affected_rows;
	}
	
	private function _executeCount(Dataset $dataset){
		// Generate a query to run.
		$q = 'SELECT';
		// Count clauses only need a COUNT(*) for the select.
		$q .= ' COUNT(*) c';
		$q .= ' FROM `' . $dataset->_table . '`';
		
		if(sizeof($dataset->_where)){
			$ws = array();
			foreach($dataset->_where as $w){
				$w['value'] = $this->_conn->real_escape_string($w['value']);
				$ws[] = "`{$w['field']}` {$w['op']} '{$w['value']}'";
			}
			$q .= ' WHERE ' . implode(' AND ', $ws);
		}
		
		// Execute this and populate the dataset appropriately.
		$res = $this->_rawExecute($q);
		
		// Instead of using the traditional num_rows offered by mysql, I'll 
		// return the 1 "record" returned, which contains just 'c'.
		$row = $res->fetch_row();
		$dataset->num_rows = $row[0];
	}
	
	
	/**
	 * Execute a raw query
	 * 
	 * Returns FALSE on failure. For successful SELECT, SHOW, DESCRIBE or 
	 * EXPLAIN queries mysqli_query() will return a result object. For other 
	 * successful queries mysqli_query() will return TRUE. 
	 * 
	 * @param string $string 
	 * @return mixed
	 */
	private function _rawExecute($string){
		// If there are additional arguments and a placeholder in the query.... sanitize and parse it.
		if(func_num_args() > 1 && strpos($string, '?') !== false){
			$argv = func_get_args();
			// Drop the first argument, that's the string...
			array_shift($argv);
			$offset = 0;
			foreach($argv as $k){
				// Find the next recurrence, (after the last offset if applicable)
				$pos = strpos($string, '?', $offset);
				
				if($k === null) $sanitizedk = 'NULL';
				else $sanitizedk = "'" . $this->_conn->escape_string($k) . "'";
				
				// Replace it with the sanitized version.
				$string = substr($string, 0, $pos) . $sanitizedk . substr($string, $pos+1);
				// NEXT
				$offset = $pos + strlen($sanitizedk);
			}
		}
		//echo $string . '<br/>'; // DEBUGGING //
		$res = $this->_conn->query($string);
		if($this->_conn->errno){
			// @todo Should this be implemented in the DMI_Exception?
			if(DEVELOPMENT_MODE) echo '<pre class="cae2_debug">' . $string . '</pre>';
			throw new DMI_Exception($this->_conn->error, $this->_conn->errno);
		}
		return $res;
	}
}

?>
