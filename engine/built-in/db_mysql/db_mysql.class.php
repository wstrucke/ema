<?php
 /* MySQL Database Extension for ema
  * Copyright 2010-2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.4.5, Apr-12-2011
  * William Strucke, wstrucke@gmail.com
  *   - WARNING: upgrading to 1.4.4.2 or higher of the MySQLi extension *REQUIRES*
  *     a complete code review for any script calling "query", "queryFull", or
  *     "query_raw" functions since the return behavior has changed!!
  *   - added support for dropping indices and keys in tables to the alter function
  *   - changed the return value of the query_raw function to return an empty array
  *     if the query executed successfully but did not return results
  *   - added attribute continue_on_error to alterations
  *
  * The current version of the mysql extension supports the following data types:
  * - string
  * - integer
  * - boolean
  * - clob
  * - date
  * - time
  * - datetime
  * - timestamp
  * - text
  * - blob (via xml only)
  * - double (via xml only)
  * - float (via xml only)
  * - real (via xml only)
  *
  * Future versions should support additional types:
  * - decimal     :   Numerical values with an optional fraction, fixed fraction size.
  *
  * The current version does not validate field sizes, unique data values, etc... 
  *   during inserts, instead it leaves it up to the back end database.
  *
  * To retrieve the size of a blob column, use OCTET_LENGTH(col_name);
  *
  * Beginning in version 1.4 all I/O for this object is gradually being converted to use
  *   the more advanced xml_object. Scripts should be converted as soon as possible.
  *
  * Also slated for a future version is active database updates through this object
  *
  * to do:
  *   - $searchValues[$key] = mysqli_real_escape_string($this->connector, $searchValues[$key]);
  *   - code xml queries to allow table joins on more than one field
  *
  */
	
	class db_mysql extends standard_extension
	{
		public $connected = false;
		public $drop_on_create = false;
		public $error;
		public $errno;
		
		protected $charset;
		protected $connector;
		protected $database;
		protected $engine;
		protected $last_insert_id;
		protected $password;
		protected $server;
		protected $tlock;
		protected $user;
		
		public $_name = 'MySQL Database Extension';
		public $_version = '1.4.5';
		protected $_debug_prefix = 'db_mysql';
		
		/* code */
		
		protected function _construct()
		/* initialize the mysql db class
		 *
		 */
		{
			$this->_debug('checking variables');
			
			# check required variables
			if (strlen(trim($this->server)) == 0) {
				$this->_debug('no server was provided');
				return false;
			}
			
			if (strlen(trim($this->database)) == 0) {
				$this->_debug('no database was provided');
				return false;
			}
			
			if (strlen(trim($this->user)) == 0) {
				$this->_debug('no user name was provided');
				return false;
			}
			
			# set the php socket timeout
			@ini_set('mysql.connect_timeout', '5');
			
			$this->_debug('connecting...');
			
			$this->connector = @mysqli_init();
			
			if (! @mysqli_options($this->connector, MYSQLI_OPT_CONNECT_TIMEOUT, 5)) {
		   $this->_debug('Setting MYSQLI_OPT_CONNECT_TIMEOUT failed');
		   return false;
			}
			
			if (@mysqli_real_connect($this->connector, $this->server, $this->user, $this->password, null, null, null, 2)) {
				$this->_debug('connected');
				# connection successful
				$this->connected = true;
				$tmp = mysqli_select_db($this->connector, $this->database);
			} else {
				$this->_debug('connection failed!');
				return false;
			}
			
			# check for an engine and validate
			if (! is_null($this->engine)) {
				switch(strtolower('' . $this->engine)) {
					case 'myisam': $this->engine = 'MYISAM'; break;
					case 'innodb': $this->engine = 'INNODB'; break;
					case 'ndb': $this->engine = 'NDBCLUSTER'; break;
					case 'ndbcluster': $this->engine = 'NDBCLUSTER'; break;
					default: $this->engine = null; break;
				}
			}
			
			# check for a character set
			if (strlen(trim($this->charset)) == 0) {
				$this->_debug('no charset was provided');
				$this->charset = 'latin1';
			}
			
			# clear the password from the object to prevent caching
			$this->password = '';
			
			# set the connection character set
			if (! mysqli_set_charset($this->connector, $this->charset)) {
				$this->_debug('failed to change character set!');
			} else {
				$this->_debug('charset: ' . $this->charset);
			}
			
			# init lock vars
			$this->tlock = null;
			
			return true;
		}
		
		public function alter($table, xml_object $alterations)
		/* alter a table's configuration
		 *
		 * $table :: the name of the table to be changed
		 * 
		 * $alterations :: an xml object with alter commands in the following format:
		 *	-	$alterations->add->
		 *	-	$alterations->alter->
		 *	-	$alterations->change->
		 *	-	$alterations->modify->
		 *	-	$alterations->drop->
		 *	- $alterations->disable_keys->
		 *	-	$alterations->enable_keys->
		 *	-	$alterations->rename->
		 *	-	$alterations->order_by->
		 *	-	$alterations->convert_to_character_set->
		 *	-	$alterations->default_character_set->
		 *	-	$alterations->discard_table_space->
		 *	-	$alterations->import_table_space->
		 *	
		 *
		 * Full MySQL 5.0 Spec:
		 *
		 *		ALTER [IGNORE] TABLE tbl_name
		 *		alter_specification [, alter_specification] ...
		 *		
		 *		alter_specification:
		 *		table_option ...
		 *		| ADD [COLUMN] col_name column_definition
		 *					[FIRST | AFTER col_name ]
		 *		| ADD [COLUMN] (col_name column_definition,...)
		 *		| ADD {INDEX|KEY} [index_name]
		 *					[index_type] (index_col_name,...) [index_type]
		 *		| ADD [CONSTRAINT [symbol]] PRIMARY KEY
		 *					[index_type] (index_col_name,...) [index_type]
		 *		| ADD [CONSTRAINT [symbol]]
		 *					UNIQUE [INDEX|KEY] [index_name]
		 *					[index_type] (index_col_name,...) [index_type]
		 *		| ADD [FULLTEXT|SPATIAL] [INDEX|KEY] [index_name]
		 *					(index_col_name,...) [index_type]
		 *		| ADD [CONSTRAINT [symbol]]
		 *					FOREIGN KEY [index_name] (index_col_name,...)
		 *					reference_definition
		 *		| ALTER [COLUMN] col_name {SET DEFAULT literal | DROP DEFAULT}
		 *		| CHANGE [COLUMN] old_col_name new_col_name column_definition
		 *					[FIRST|AFTER col_name]
		 *		| MODIFY [COLUMN] col_name column_definition
		 *					[FIRST | AFTER col_name]
		 *		| DROP [COLUMN] col_name
		 *		| DROP PRIMARY KEY
		 *		| DROP {INDEX|KEY} index_name
		 *		| DROP FOREIGN KEY fk_symbol
		 *		| DISABLE KEYS
		 *		| ENABLE KEYS
		 *		| RENAME [TO] new_tbl_name
		 *		| ORDER BY col_name [, col_name] ...
		 *		| CONVERT TO CHARACTER SET charset_name [COLLATE collation_name]
		 *		| [DEFAULT] CHARACTER SET [=] charset_name [COLLATE [=] collation_name]
		 *		| DISCARD TABLESPACE
		 *		| IMPORT TABLESPACE
		 *
		 */
		{
			$this->_debug_start();
			
			# create sql array
			$sql = array();
			$stopOnError = array();
			
			# start building the sql statement for this table
			$boilerplate = "ALTER TABLE `$table`";
			
			# load the alterations
			$list = $alterations->_getChildren();
			$this->_debug('found ' . count($list) . ' alterations');
			
			if (count($list) == 0) { echo $alterations; exit; }
			
			# process each alteration in order
			foreach($list as $item) {
				# set failure mode
				if (($item->_getAttribute('continue_on_error'))&&(strtolower($item->_getAttribute('continue_on_error')) == 'true')) {
					$stopOnError[] = false;
				} else {
					$stopOnError[] = true;
				}
				switch(strtolower($item->_getTag())) {
					case 'add':
						$this->_debug('found an add value');
						$result_sql = $this->convert_add_to_sql($item);
						$this->remove_trailing_comma($result_sql);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'alter':
						$this->_debug('found an alter value');
						$result_sql = $this->convert_alter_to_sql($item);
						$this->remove_trailing_comma($result_sql);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'modify':
						$this->_debug('found a modify value');
						$result_sql = $this->convert_modify_to_sql($item);
						//$this->remove_trailing_comma($result_sql);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'change':
						$this->_debug('found a change value');
						$result_sql = $this->convert_change_to_sql($item);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'drop':
						$this->_debug('found a drop value');
						$result_sql = $this->convert_drop_column_to_sql($item);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'disable_keys':
						$this->_debug('found a disable-keys value'); return false;
						break;
					case 'enable_keys':
						$this->_debug('found a enable-keys value'); return false;
						break;
					case 'rename':
						$this->_debug('found a rename value');
						$result_sql = $this->convert_rename_to_sql($item);
						if ($result_sql) { $sql[] .= $boilerplate . $result_sql; } else { return false; }
						break;
					case 'order_by':
						$this->_debug('found a order-by value'); return false;
						break;
					case 'convert_to_character_set':
						$this->_debug('found a convert-to-character-set value'); return false;
						break;
					case 'default_character_set':
						$this->_debug('found a default-character-set value'); return false;
						break;
					case 'discard_table_space':
						$this->_debug('found a discard-table-space value'); return false;
						break;
					case 'import_table_space':
						$this->_debug('found a import-table-space value'); return false;
						break;
					default:
						$this->_debug('unsupported alteration'); break;
				}
			}
			
			# preset result to return true if there are no alterations
			$result = true;
			
			# execute each sql statement
			for ($i=0;$i<count($sql);$i++) {
				$this->_debug('Built statement:<br /><br />' . $sql[$i] . '<br /><br />');
				# execute alter statement
				$result = @mysqli_query($this->connector, $sql[$i]);
				if ((! $result)&&($stopOnError[$i])) {
					$this->_debug('Error executing this SQL statement! Terminating execution of further commands!');
					break;
				} elseif (! $result) {
					$this->_debug('Statement failed but option to continue on failure was set');
					$result = true;
				}
			}
			
			if ($result !== false) $result = true;
			
			return $this->_return($result);
		}
		
		private function convert_add_to_sql(xml_object &$addXml)
		/* using the supplied xml object with columns to add, return sql
		 *
		 */
		{
			$this->_debug_start();
			
			$items = $addXml->_getChildren();
			
			$number_of_columns = 0;
			$return_sql = '';
			
			foreach($items as $item) {
				$column_name = $item->_getTag();
				
				# preset validation to true
				$column_validated = true;
				
				# check if this is a new primary key
				if ($item->_getAttribute('primary')) {
					$loop_sql = ' DROP PRIMARY KEY, ADD COLUMN';
				} else {
					$loop_sql = ' ADD COLUMN';
				}
				
				
				# validate field name
				if ($column_name != stripslashes(stripcslashes($column_name))) {
					$this->_debug('error: field contained invalid characters');
					$column_validated = false;
				}
				
				$loop_sql .= " `$column_name`";
				
				# set type
				$this->_debug('checking the supplied type for this field');
				
				# first check the data types that are explicit
				$loop_sql .= $this->get_type_from_xml($item, $this_data_type);
				
				# add sql for optional column attributes
				$loop_sql .= $this->get_attributes_from_xml($item, $this_data_type);
				
				$default_value = $item->_getValue();
				
				if (strlen($default_value) > 0) {
					# there is a default value
					$loop_sql .= " DEFAULT";
					# set the default value
					switch ($item->_getAttribute('type')) {
						case 'string':
							# strings require extra quotes
							$loop_sql .= " '$default_value'";
							break;
						case 'bool':
							# bool must be converted to string representation
							if (strtolower($default_value) == 'true') {
								$loop_sql .= " true";
							} else {
								$loop_sql .= " false";
							}
							break;
						default:
							# all others just use the set default value
							$loop_sql .= " $default_value";
							break;
					}
				}
				
				if ($item->_getAttribute('first')) {
					$loop_sql .= ' FIRST';
				} else {
					if ($item->_getAttribute('after')) {
						$loop_sql .= ' AFTER `' . $item->_getAttribute('after') . '`';
					}
				}
				
				if ($item->_getAttribute('primary')) {
					$loop_sql .= ", ADD PRIMARY KEY (`$column_name`),";
				} else {
					$loop_sql .= ',';
				}
				
				# finished preparing column
				if ($column_validated) {
					$return_sql .= $loop_sql;
					$number_of_columns++;
				}
			} // foreach
			
			# make sure at least one column was added
			if ($number_of_columns == 0) return $this->_return(false, 'Error: no columns were validated!');
			
			return $this->_retByRef($return_sql);
		}
		
		private function convert_alter_to_sql(xml_object &$alterXml)
		/* using the supplied xml object with columns to alter, return sql
		 *
		 * | ALTER [COLUMN] col_name {SET DEFAULT literal | DROP DEFAULT}
		 *
		 */
		{
			$this->_debug_start();
			
			$items = $alterXml->_getChildren();
			
			$number_of_columns = 0;
			$return_sql = '';
			
			foreach($items as $item) {
				$column_name = $item->_getName();
				
				# validate field name
				if ($column_name != stripslashes(stripcslashes($column_name))) {
					return $this->_return(false, 'error: field contained invalid characters');
				}
				
				$return_sql .= " ALTER COLUMN `$column_name`";
				
				$default_value = $item->_getValue();
				
				if (strlen($default_value) > 0) {
					# there is a default value
					$return_sql .= " SET DEFAULT";
					# set the default value
					switch ($item->_getAttribute('type')) {
						case 'string':
							# strings require extra quotes
							$return_sql .= " '$default_value'";
							break;
						case 'bool':
							# bool must be converted to string representation
							if (strtolower($default_value) == 'true') {
								$return_sql .= " true";
							} else {
								$return_sql .= " false";
							}
							break;
						default:
							# all others just use the set default value
							$return_sql .= " $default_value";
							break;
					}
				} else {
					# default values can not be applied to text columns in cluster databases
					if (! ((strtolower($item->_getAttribute('type')) == 'clob') && ($this->engine == 'NDBCLUSTER'))) {
						# there is no default value
						$return_sql .= " DROP DEFAULT";
					}
				}
				
				$return_sql .= ',';
			}
			
			return $this->_retByRef($return_sql);
		}
		
		protected function convert_change_to_sql(xml_object &$xml)
		/* using the supplied xml object with columns to change, return sql
		 *
		 * | CHANGE [COLUMN] old_col_name new_col_name column_definition
		 *          [FIRST|AFTER col_name]
		 *
		 */
		{
			$this->_debug_start();
			
			$items = $xml->_getChildren();
			
			$number_of_columns = 0;
			$return_sql = '';
			
			foreach($items as $item) {
				$column_name = $item->_getTag();
				
				# preset validation to true
				$column_validated = true;
				
				# build the add statement
				$loop_sql = ' CHANGE COLUMN';
				
				# validate field name
				if ($column_name != stripslashes(stripcslashes($column_name))) {
					$this->_debug('error: field contained invalid characters');
					$column_validated = false;
				}
				
				$loop_sql .= " `$column_name`";
				
				# check for new column name
				if ($item->_getAttribute('rename')) { $rename = $item->_getAttribute('rename'); } else { $rename = $column_name; }
				
				# validate mew field name
				if ($rename != stripslashes(stripcslashes($rename))) {
					$this->_debug('error: new column name contained invalid characters');
					$column_validated = false;
				}
				
				$loop_sql .= " `$rename`";
				
				# set type
				$this->_debug('checking the supplied type for this field');
				
				# first check the data types that are explicit
				$loop_sql .= $this->get_type_from_xml($item, $this_data_type);
				
				# add sql for optional column attributes
				$loop_sql .= $this->get_attributes_from_xml($item, $this_data_type);
				
				$default_value = $item->_getValue();
				
				if (strlen($default_value) > 0) {
					# there is a default value
					$loop_sql .= " DEFAULT";
					# set the default value
					switch ($item->_getAttribute('type')) {
						case 'string':
							# strings require extra quotes
							$loop_sql .= " '$default_value'";
							break;
						case 'bool':
							# bool must be converted to string representation
							if (strtolower($default_value) == 'true') {
								$loop_sql .= " true";
							} else {
								$loop_sql .= " false";
							}
							break;
						default:
							# all others just use the set default value
							$loop_sql .= " $default_value";
							break;
					}
				}
				
				if ($item->_getAttribute('first')) {
					$loop_sql .= ' FIRST';
				} else {
					if ($item->_getAttribute('after')) {
						$loop_sql .= ' AFTER `' . $item->_getAttribute('after') . '`';
					}
				}
				
				# finished preparing column
				if ($column_validated) {
					$return_sql .= $loop_sql . ',';
					$number_of_columns++;
				}
			} // foreach
			
			# make sure at least one column was added
			if ($number_of_columns == 0) return $this->_return(false, 'Error: no columns were validated!');
			
			# remove the final comma from the column list
			$this->strip_last_character($return_sql);
			
			return $this->_retByRef($return_sql);
		}
		
		private function convert_column_to_sql(xml_object &$column, &$key_list)
		/* process the supplied column in xml format and return properly formatted sql
		 *
		 */
		{
			$this->_debug_start();
			
			$this_data_type = '';
							
			# validate field name
			if ($column->_getName() != stripslashes(stripcslashes($column->_getName()))) {
				return $this->_return(false, 'error: field ' . $column->_getName() . ' contained invalid characters');
			}
			
			# add field name to query
			$return_sql = '`' . $column->_getName() . '`';
			
			# set type
			$this->_debug('checking the supplied type for this field');
			
			# first check the data types that are explicit
			$return_sql .= $this->get_type_from_xml($column, $this_data_type);
			
			# check if this is a primary key
			if ($column->_getAttribute('primary')) {
				$this->_debug('adding primary key ' . $column->_getName());
				# append query string
				$key_list .= '`' . $column->_getName() . '`,';
			}
			
			# add sql for optional column attributes
			$return_sql .= $this->get_attributes_from_xml($column, $this_data_type);
			
			# default values can not be applied to text columns in cluster databases
			if (! ((strtolower($this_data_type) == 'clob') && ($this->engine == 'NDBCLUSTER'))) {
				# check for a default value
				if (strlen($column->_getValue()) > 0) {
					$this->_debug('adding specified default value for this column');
					# set the default value
					switch ($this_data_type) {
						case 'string':
							# strings require extra quotes
							$return_sql .= ' DEFAULT \'' . $column->_getValue() . '\'';
							break;
						case 'bool':
							# bool must be converted to string representation
							if (strtolower($column->_getValue()) == 'true') {
								$return_sql .= " DEFAULT true";
							} else {
								$return_sql .= " DEFAULT false";
							}
							break;
						default:
							# all others just use the set default value
							$return_sql .= ' DEFAULT ' . $column->_getValue();
							break;
					}
				}
			}
			
			return $this->_retByRef($return_sql);
		}
		
		private function convert_drop_column_to_sql(xml_object &$dropColXml)
		/* using the supplied xml object with columns or keys to drop, return sql
		 *
		 */
		{
			$this->_debug_start();
			
			$items = $dropColXml->_getChildren();
			
			$number_of_columns = 0;
			$return_sql = '';
			
			foreach($items as $item) {
				$column_name = $item->_getTag();
				
				# preset validation to true
				$column_validated = true;
				
				# set drop type
				if (($item->_getAttribute('type'))&&(strtolower($item->_getAttribute('type')) == 'key')) {
					$type = 'KEY';
				} elseif (($item->_getAttribute('type'))&&(strtolower($item->_getAttribute('type')) == 'index')) {
					$type = 'INDEX';
				} else {
					$type = 'COLUMN';
				}
				
				# build the drop statement
				$loop_sql = " DROP $type";
				
				# validate field name
				if ($column_name != stripslashes(stripcslashes($column_name))) {
					$this->_debug('error: field contained invalid characters');
					$column_validated = false;
				}
				
				$loop_sql .= " `$column_name`";
				
				# finished preparing column
				if ($column_validated) {
					$return_sql .= $loop_sql . ',';
					$number_of_columns++;
				}
			} // foreach
			
			# make sure at least one column was added
			if ($number_of_columns == 0) return $this->_return(false, 'Error: no columns were validated!');
			
			# remove the final comma from the column list
			$this->strip_last_character($return_sql);
			
			return $this->_retByRef($return_sql);
		}
		
		protected function convert_modify_to_sql(xml_object &$xml)
		/* using the supplied xml object with columns to modify, return sql
		 *
		 * | MODIFY [COLUMN] col_name column_definition [FIRST | AFTER col_name]
		 *
		 */
		{
			$this->_debug_start();
			
			$items = $xml->_getChildren();
			
			$number_of_columns = 0;
			$return_sql = '';
			
			foreach($items as $item) {
				$column_name = $item->_getName();
				
				# validate field name
				if ($column_name != stripslashes(stripcslashes($column_name))) {
					return $this->_return(false, 'error: field contained invalid characters');
				}
				
				$return_sql .= " MODIFY COLUMN `$column_name`";
				
				# set type
				$this->_debug('checking the supplied type for this field');
				
				# get the data type and length
				$return_sql .= $this->get_type_from_xml($item, $this_data_type);
				
				# add sql for optional column attributes
				$return_sql .= $this->get_attributes_from_xml($item, $this_data_type) . ',';
			}
			
			# remove the final comma from the column list
			$this->strip_last_character($return_sql);
			
			return $this->_retByRef($return_sql);
		}
		
		protected function convert_rename_to_sql(xml_object &$xml)
		/* using the supplied xml object with columns to modify, return sql
		 *
		 * | RENAME [TO] new_tbl_name
		 *
		 */
		{
			$this->_debug_start();
			
			$new_name = $xml->_getAttribute('to');
			
			# validate new table name
			if (strlen($new_name) == 0) return $this->_return(false, 'error: invalid table name');
			if ($new_name != stripslashes(stripcslashes($new_name))) {
				return $this->_return(false, 'error: new table name contained invalid characters');
			}
			
			$return_sql = " RENAME TO `$new_name`";
			
			return $this->_retByRef($return_sql);
		}
		
		protected function comp_where($key, $value)
		/* given a key and value, return a mysql where component
		 *
		 * i.e. for ('column', 'test') returns "(`column`='test')"
		 *
		 */
		{
			# initialize the return string
			$table = null;
			
			# check for a join
			if (strpos($key, ',') !== false) {
				$arr = explode(",", $key);
				$table = '`' . $this->escape($arr[0]) . '`';
				$key = $arr[1];
			}
			
			# validate the key value
			if (!is_int($key)) $key = '`' . $this->escape($key) . '`';
			
			# prepend the table name as needed
			if (!is_null($table)) $key = $table . '.' . $key;
			
			# validate the value and apply special where clause syntax
			if (is_int($value)) {
				$value = " = $value";
			} elseif (is_bool($value)) {
				$value = ' IS ' . strtoupper(b2s($value));
			} elseif (is_null($value)) {
				$value = ' IS NULL';
			} else {
				# protect against sql injection
				$value = $this->escape($value);
				# parse any special syntax or values
				if (($value == 'null')||(strlen($value)==0)) {
					$value = ' IS NULL';
				} elseif (($value[0] == '%')||($value[strlen($value)-1] == '%')) {
					$value = " LIKE '$value'";
				} elseif ($value[0] == '<') {
					if (preg_match('/^<=?(?: *)[0-9]+$/', $value) !== 1) {
						# treat as a string
						$value = " = '$value'";
					}
				} elseif ($value[0] == '>') {
					if (preg_match('/^>=?(?: *)[0-9]+$/', $value) !== 1) {
						# treat as a string
						$value = " = '$value'";
					}
				} elseif ((strlen($value) > 3)&&(substr($value, 0, 3) == 'IN ')) {
					$value = " $value";
				} elseif ((strlen($value) > 2)&&(substr($value, 0, 2) == '!=')) {
					$value = " $value";
				} else {
					$value = " = '$value'";
				}
			}
			
			return '(' . $key . $value . ')';
		}
		
		public function count($table, $search = null)
		/* get a total number of records from the specified table
		 *
		 * optional search array in the form of { column => value }
		 *
		 * - joins are not supported at this time
		 * - xml is not supported at this time
		 * - no debugging
		 *
		 * returns an integer of 0 or more on results or false on error
		 *
		 */
		{
			# input validation
			$table = trim((string)$table);
			if (strlen($table) == 0) return false;
			$table = '`' . $this->escape($table) . '`';
			$qryStr = "SELECT COUNT(*) FROM $table";
			if (is_array($search)) {
				$qryStr .= " WHERE ";
				foreach ($search as $k=>$v) $qryStr .= $this->comp_where($k, $v) . ' AND ';
				$this->strip_last_character($qryStr, 5);
			}
			@mysqli_query($this->connector, "LOCK TABLES $table READ");
			$r = @mysqli_query($this->connector, $qryStr);
			@mysqli_query($this->connector, 'UNLOCK TABLES');
			if (@mysqli_num_rows($r) == 0) return false;
			$tmp = @mysqli_fetch_array($r, MYSQL_NUM);
			if ($tmp === false) return false;
			@mysqli_free_result($r);
			return $tmp[0];
		}
		
		public function create($table, $fields_arr_or_xml, $opt_prim_keys = '', $opt_nulls = '', $opt_defaults = '')
		/* wrapper create function to utilize either array (v1.0) or xml (v1.3) table input
		 * 	implemented for compatibility
		 *
		 * no debugging is implemented in this function -- instead each internal function will
		 *	masquerade as the create function
		 *
		 */
		{
			# logically determine which type of input was provided
			if (! is_array($opt_prim_keys)) {
				# input provided uses the new xml format
				return $this->create_from_xml($table, $fields_arr_or_xml);
			} else {
				# input provided uses the old array format
				return $this->create_from_array($table, $fields_arr_or_xml, $opt_prim_keys, $opt_nulls, $opt_defaults);
			}
		}
		
		protected function create_from_xml($table, xml_object &$schema)
		/* create a new table based on the provided xml schema
		 *
		 * $table :: the name of the table to be created
		 * 
		 * $schema :: an xml object with fields in the following format:
		 *	-	$schema->field1						// where 'field1' is the name of the field
		 *	-	$schema->field1->_type		// where 'type' is an xml attribute :: the field type
		 *	-	$schema->field1->_length	// where 'length' is an xml attribute :: the field length
		 *	-	$schema->field1->_primary	// where 'primary' is an xml attribute :: true => primary key
		 *	-	$schema->field1->_nulls 	// where 'nulls' is an xml attribute :: true => nulls allowed
		 *	-	$schema->field1->_autoinc	// where 'autoinc' is an xml attribute :: true => auto increment (int only)
		 *	-	$schema->field1->_unsigned	// where 'unsigned' is an xml attribute :: true => unsigned (int only)
		 *	-	$schema->field1->_unique 	// where 'unique' is an xml attribute :: true => unique value
		 *
		 *	The value of $schema->field1, if one is set, is the default field value
		 *	The field type is required, all other attributes are optional and default as definied here:
		 *		length = depends on the type
		 *		primary = no
		 *		nulls = no
		 *	
		 */
		{
			$this->_debug_start();
			
			# ensure a table name was provided
			if (strlen(trim($table)) == 0) {
				return $this->_return(false, 'Error: no table name was supplied');
			}
			
			# validate table name
			$this->_debug('validating table name');
			if ($table != stripslashes(stripcslashes($table))) {
				return $this->_return(false, 'error: table name contained invalid characters');
			}
			
			# begin create string
			$this->_debug('building create query');
			$qryStr = "CREATE TABLE IF NOT EXISTS `$table` ( ";
			
			$column_list = $schema->_getChildren();
			$key_list = '';
			
			# process each column
			foreach ($column_list as &$column) {
				$result_sql = $this->convert_column_to_sql($column, $key_list);
				if ($result_sql) { $qryStr .= $result_sql . ','; } else { return $this->_return(false); }
			}
			
			# make sure there is at least one primary key
			if (strlen($key_list) == 0) return $this->_return(false, 'NO PRIMARY KEYS, ABORTING');
			
			# remove the final comma from the key list
			$this->strip_last_character($key_list);
			
			# add the primary key statement and the final parenthesis
			$qryStr .= "PRIMARY KEY ($key_list) )";
			
			# check for an explicit engine
			if (! is_null($this->engine)) {
				$qryStr .= ' ENGINE=' . $this->engine;
			}
			
			# check for an explicit charset
			if ($schema->_getAttribute('charset')) {
				$charset = $schema->_getAttribute('charset');
				if (strlen($charset) > 0) $qryStr .= " CHARSET $charset";
			} elseif ((! is_null($this->charset))&&(strlen($this->charset) > 0)) {
				$qryStr .= ' CHARSET ' . $this->charset;
			/* utf8_general_ci, collation not implemented at this time
				if (! is_null($this->collation)) {
					$qryStr .= ' COLLATE ' . $this->collation;
				}
			*/
			}
			
			$this->_debug('final create statement: ' . $qryStr);
			$this->_debug('');
			
			# drop the table first in case it already exists
			if ($this->drop_on_create) {
				$this->_debug('dropping existing table just in case');
				$result = mysqli_query ($this->connector, "DROP TABLE IF EXISTS $table");
			}
			
			if (mysqli_query($this->connector, $qryStr) === false) { $result = false; } else { $result = true; }
			
			return $this->_return($result);
		} // function create_from_xml
				
		protected function create_from_array(&$table, &$fields, &$primary_keys, &$nulls = '', &$default_values = '')
		/* create a single table
		 *
		 *	the table name must be unique and not exist
		 *		not all ascii characters are allowed
		 *		if you execute this on an existing table all of your data will be lost
		 *
		 *	fields is an array of field names and matching types:
		 *		array('key1'=>'string(255)', 'key2'=>'bool', etc...)
		 *
		 *		data types are case sensitive and should be all lowercase
		 *
		 *		supported types are:
		 *			string(x)	:		a string of ascii characters where 'x' is the maximum field length, 
		 *										up to 255 characters
		 *			int(x)		:		a number with up to 'x' digits (there is an implicit maximum # of digits)
		 *			bool			:		a true or false value
		 *			blob			:		binary data (e.g. for file uploads)
		 *			clob(x)		:		large character data (types include TINYTEXT, TEXT, MEDIUMTEXT, LONGTEXT)
		 *										where 'x' is a value that allows us to choose the correct mysql data type
		 *			date			:		a date/time value
		 *			timestamp	:		a field containing a timestamp
		 *
		 *	primary_keys is an array of at least one primary key for the new table
		 *
		 *	nulls is an optional array of fields with allowed null values
		 *
		 *	default values is an optional array of field names and default values:
		 *
		 *		if the data type for the field is int(eger), you can add
		 *		up to four options in the default_values array:
		 *			(int_field_name=>'START_NUMBER,AUTO-INCREMENT,UNSIGNED,UNIQUE')
		 *
		 *		if the data type for the field is string, you can add up 
		 *		to two options in the default_values array:
		 *			(str_field_name=>'DEFAULT_VALUE,UNIQUE')
		 *		
		 *		one or both options may be included, deliminated by a comma with no 
		 *		spaces
		 *		
		 */
		{
			$this->_debug_start("table = $table");
			
			# preset final check variables
			$primary_key_set = false;
			
			# ensure all required data exists
			if (strlen(trim($table)) == 0) return $this->_return(false, 'error: no table name was supplied');
			
			if ( (count($fields) == 0) || (count($primary_keys) == 0) || (! is_array($fields)) || (! is_array($primary_keys)) ) {
				return $this->_return(false, 'error: no value or non-array provided for either fields or primary_keys');
			}
			
			# validate table name
			$this->_debug('validating table name');
			$test = stripslashes(stripcslashes($table));
			
			if ($table != $test) return $this->_return(false, 'error: table name contained invalid characters');
			
			# begin create string
			$this->_debug('building create query');
			$qryStr = "CREATE TABLE IF NOT EXISTS `$table` ( ";
			
			$loop_counter = 0;
			
			# process each field
			foreach ($fields as $k=>$v) {
				$loop_counter++;
				$this_data_type = '';
				
				# validate field name
				$this->_debug('validating field ' . $loop_counter);
				$test = stripslashes(stripcslashes($k));
				if ($k != $test) return $this->_return(false, 'error: field ' . $loop_counter . ' contained invalid characters');
				
				# add field name to query
				$qryStr .= "`$k`";
				
				# set type
				$this->_debug('checking the supplied type for this field');
				
				# first check the data types that are explicit
				switch($v) {
					case 'bool':
						$this->_debug('boolean data type');
						$this_data_type = 'bool';
						$qryStr .= ' BOOL';
						break;
					case 'boolean':
						$this->_debug('boolean data type');
						$this_data_type = 'bool';
						$qryStr .= ' BOOL';
						break;
					case 'blob':
						return $this->_return(false, '<strong>incomplete fn error:</strong> the data type "blob" is not implemented yet.');
						break;
					case 'date':
						return $this->_return(false, '<strong>incomplete fn error:</strong> the data type "date" is not implemented yet.');
						break;
					case 'timestamp':
						return $this->_return(false, '<strong>incomplete fn error:</strong> the data type "timestamp" is not implemented yet.');
						break;
					default:
						# the type is implicit or incorrect
						if (substr($v, 0, 6) == 'string') {
							# string
							$this->_debug('string data type');
							# get the field length
							$data_length = intval(substr($v, 7, (strlen($v) - 1)));
							# update the query
							$qryStr.= " VARCHAR($data_length)";
							$this_data_type = 'string';
						} elseif (substr($v, 0, 3) == 'int') {
							# integer
							$this->_debug('integer data type');
							# get the field length
							$data_length = intval(substr($v, 4, (strlen($v) - 1)));
							# update the query
							$qryStr.= " INT($data_length)";
							$this_data_type = 'int';
						} elseif (substr($v, 0, 4) == 'clob') {
							# clob
							$this->_debug('clob data type');
							# get the field length
							$length = intval(substr($v, 5, strlen($v) - 1));
							# update the query
							if ($length <= 255) {
								$qryStr.= " TINYTEXT";
							} elseif ($length <= 65535) {
								$qryStr.= " TEXT";
							} elseif ($length <= 16777215) {
								$qryStr.= " MEDIUMTEXT";
							} elseif ($length <= 4294967295) {
								$qryStr.= " LONGTEXT";
							} else {
								$qryStr.= " TINYTEXT";
							}
							$this_data_type = 'string';
						} else {
							# bad data type
							return $this->_return(false, 'bad data type supplied');
						}
						break;
				} // switch
					
				# now check if this is a primary key
				foreach($primary_keys as $pk) {
					if ($k == $pk) {
						# matched one
						$this->_debug("adding primary key $k");
						# append query string
						$qryStr .= " PRIMARY KEY";
						# mark pk set value
						$primary_key_set = true;
						# exit loop
						break;
					}
				}
				
				# preset null check
				$allowed_null = false;
				
				# check for this field in allowed nulls list
				if (is_array($nulls)) {
					$this->_debug('checking allowed nulls');
					foreach($nulls as $nl=>$n) {
						if ($n == $k) {
							$this->_debug('this field can be null');
							$allowed_null = true;
							break;
						}
					}
				}
				
				# if allowed_null is still false, add not null to query string
				if (! $allowed_null) {
					$this->_debug('this field must have a value');
					$qryStr .= " NOT NULL";
				}
				
				# check default values, including the auto-increment option for integers
				if (is_array($default_values)) {
					$this->_debug('default values are set, processing');
					foreach($default_values as $dv=>$dvv) {
						# preset values
						$autoincrement = false;
						$unsigned = false;
						$unique = false;
						$defaultvalue = '';
						$zerofill = false;
					
						if ($dv == $k) {
							# found a value for this field
							$comma_pos = strpos($dvv, ',');
							if ($comma_pos !== false) {
								# located multiple options for this item
								$value_arr = explode(',', $dvv);
								foreach($dvv as $checkval) {
									if (strtoupper($checkval)=='AUTO-INCREMENT') {
										$autoincrement = true;
									} elseif (strtoupper($checkval)=='UNSIGNED') {
										$unsigned = true;
									} elseif (strtoupper($checkval)=='UNIQUE') {
										$unique = true;
									} elseif (strtoupper($checkval)=='ZEROFILL') {
										$zerofill = true;
									} else {
										$defaultvalue = $checkval;
									}
								}
							} else {
								# just set the default value
								$defaultvalue = $dvv;
							}
							
							# optionally set the unsigned field
							if ( ($unsigned) && ($this_data_type == 'int') ) {
								$this->_debug('setting this field to unsigned');
								$qryStr .= " UNSIGNED";
							}
							
							# optionally set the unique field
							if ($unique) {
								$this->_debug('setting uqique flag');
								$qryStr .= " UNIQUE";
							}
							
							# optionally set the zerofill field
							if ( ($zerofill) && ($this_data_type == 'int') ) {
								$this->_debug('setting zerofill flag');
								$qryStr .= " ZEROFILL";
							}
							
							# optionally set the autoincrement field
							if ( ($autoincrement) && ($this_data_type == 'int') ) {
								$this->_debug('enabling auto-increment for this field');
								$qryStr .= " AUTO_INCREMENT";
							}
							
							# set the default value
							switch ($this_data_type) {
								case 'string':
									# strings require extra quotes
									$qryStr .= " DEFAULT '$defaultvalue'";
									break;
								case 'bool':
									# bool must be converted to string representation
									if ($defaultvalue) {
										$qryStr .= " DEFAULT true";
									} else {
										$qryStr .= " DEFAULT false";
									}
									break;
								default:
									# all others just use the set default value
									$qryStr .= " DEFAULT $defaultvalue";
									break;
							}							
							
						}
					} // foreach loop (default values)
				} // if (is_array default values)
				
				# add final comma to terminate this field entry
				$qryStr .= ',';
			} // foreach loop (fields)
	
			# remove the final comma from the loop
			$this->strip_last_character($qryStr);
			
			# add the final parenthesis
			$qryStr .= ' )';
			
			# check for an explicit engine
			if (! is_null($this->engine)) {
				$qryStr .= ' ENGINE=' . $this->engine;
			}
			
			$this->_debug('final create statement: ' . $qryStr);
			$this->_debug('');
			
			# drop the table first in case it already exists
			if ($this->drop_on_create) {
				$this->_debug('dropping existing table just in case');
				$result = mysqli_query ($this->connector, "DROP TABLE IF EXISTS $table");
			}
			
			if (mysqli_query($this->connector, $qryStr) === false) { $result = false; } else { $result = true; }
			
			$this->errno = mysqli_errno($this->connector);
			$this->error = mysqli_error($this->connector);
			
			return $this->_return($result);
		} // function create_from_array
		
		public function database($name, $charset = false)
		/* set active database to $name
		 *
		 */
		{
			if (strlen($name) == 0) return $this->database;
			$this->database = $name;
			@mysqli_select_db($this->connector, $this->database);
			if ($charset) {
				# set the connection character set
				if (! mysqli_set_charset($this->connector, $charset)) {
					$this->_debug('failed to change character set!');
				} else {
					$this->charset = $charset;
					$this->_debug('charset: ' . $this->charset);
				}
			}
			return true;
		}
		
		public function delete($table, $search_keys_or_xml, $opt_search_values = false, $debug_me = false)
		/* delete wrapper function for array versus xml methods
		 *
		 */
		{
			if ($debug_me) $this->_debug_mode = 99;
			# logically determine which type of input was provided
			if (is_object($opt_search_values) && (get_class($opt_search_values) == 'xml_object')) {
				# input provided uses the new xml format
				return $this->delete_from_xml($table, $search_keys_or_xml);
			} else {
				# input provided uses the old array format
				return $this->delete_from_array($table, $search_keys_or_xml, $opt_search_values);
			}
		}
		
		public function delete_from_xml($table, &$xml)
		/* delete a record via xml definition
		 *
		 */
		{
			$this->_debug_start();
			
			# ensure a table name was provided
			if (strlen(trim($table)) == 0) {
				return $this->_return(false, 'Error: no table name was supplied');
			}
			
			# validate table name
			$this->_debug('validating table name');
			if ($table != stripslashes(stripcslashes($table))) {
				return $this->_return(false, 'error: table name contained invalid characters');
			}
			
			# begin query string
			$qryStr = "DELETE FROM `$table` WHERE";
			
			$list = $xml->_getChildren();
			
			$first = true;
			
			foreach ($list as &$child) {
				if (! $first) $qryStr .= ' AND ';
				$first = false;
				$qryStr .= " `" . $child->_getName() . "` = ";
				$type = $child->_getAttribute('type');
				switch($type) {
					case 'integer': $qryStr .= intval($child->_getValue()); break;
					case 'double': $qryStr .= floatval($child->_getValue()); break;
					case 'float': $qryStr .= floatval($child->_getValue()); break;
					case 'real': $qryStr .= floatval($child->_getValue()); break;
					case 'bool': if ($child->_getValue() == 'true') { $qryStr .= 'true'; } else { $qryStr .= 'false'; } break;
					default: $qryStr .= "'" . $child->_getValue() . "'"; break;
				}
			}
			
			$this->_debug("Final Query: $qryStr");
			$result = mysqli_query($this->connector, $qryStr);
			$num_rows = mysqli_affected_rows($this->connector);
			
			if ($result !== false) $result = true;
			if ($num_rows == 0) $result = false;
			
			# cache hook
			if ($result&&$this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return($result, b2s($result));
		}
		
		public function delete_from_array($table, &$searchKeys, &$searchValues)
		/* locate a record by the specified search keys and values, and 
		 * delete the matching record from the table
		 *
		 */
		{
			$this->_debug_start();
			
			# ensure all required data exists
			if (strlen(trim($table)) == 0) return $this->_return(-1);
			if ( (! is_array($searchKeys)) || (! is_array($searchValues)) ) return $this->_return(-2);
			if ( (count($searchKeys) == 0) || (count($searchValues) == 0) || (count($searchKeys) != count($searchValues)) ) return $this->_return(-3);
			
			# begin query string
			$qryStr = "DELETE FROM `$table` WHERE ";
			
			# add the where clauses
			foreach ($searchKeys as $k=>$v) {
				$qryStr .= $this->comp_where($searchKeys[$k], $searchValues[$k]) . ' AND ';
			}
				
			# remove the final comma from the loop
			$this->strip_last_character($qryStr, 5);
			
			if (is_null($this->tlock)) {
				$this->_debug('acquiring write lock');
				@mysqli_query($this->connector, "LOCK TABLES $table WRITE");
			}
			
			$this->_debug("Final Query: $qryStr");
			$result = mysqli_query($this->connector, $qryStr);
			$num_rows = mysqli_affected_rows($this->connector);
			
			if (is_null($this->tlock)) {
				$this->_debug('releasing locks');
				@mysqli_query($this->connector, 'UNLOCK TABLES');
			}
			
			if ($result !== false) $result = true;
			if ($num_rows == 0) $result = false;
			
			# cache hook
			if ($result&&$this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return($result, b2s($result));
		}
		
		public function drop($table)
		/* drop the requested table from the database
		 *
		 * for obvious reasons this should be used with caution
		 *
		 */
		{
			# sanity check
			if ($table !== $this->escape($table)) return false;
			return mysqli_query($this->connector, "DROP TABLE `$table`");
		}
		
		public function escape(&$string)
		/* return clean string to the client 
		 *
		 */
		{
			return mysqli_real_escape_string($this->connector, $string);
		}
		
		protected function get_attributes_from_xml(&$xml, &$data_type)
		/* given an xml object, extract any optional column attributes
		 *
		 * does not alter xml or data type
		 *
		 * returns sql
		 *
		 */
		{
			$this->_debug_start();
			
			$return_sql = '';
			
			# optionally set the unsigned field
			if ( ($xml->_getAttribute('unsigned')) && ($data_type == 'int') ) {
				$this->_debug('setting this field to unsigned');
				$return_sql .= " UNSIGNED";
			}
			
			# optionally set the unique field
			if ($xml->_getAttribute('unique')) {
				$this->_debug('setting unique flag');
				$return_sql .= " UNIQUE";
			}
			
			# if nulls are allowed add not null to query string
			if ( ($xml->_getAttribute('nulls')) || ($xml->_getAttribute('notnull')) ) {
				$this->_debug('this field must have a value');
				$return_sql .= ' NOT NULL';
			}
			
			# optionally set the autoincrement field
			if ( ($xml->_getAttribute('autoinc')) && ($data_type == 'int') ) {
				$this->_debug('enabling auto-increment for this field');
				$return_sql .= " AUTO_INCREMENT";
			}
			
			# optionally set the zerofill value (for integers only)
			if ( ($xml->_getAttribute('zerofill')) && ($data_type == 'int') ) {
				$this->_debug('setting zerofill flag');
				$return_sql .= " ZEROFILL";
			}
			
			# optionally add key
			if ($xml->_getAttribute('key')) {
				$this->_debug('creating key');
				$return_sql .= " KEY";
			}
			
			return $this->_return($return_sql);
		}
		
		protected function get_type_from_xml(&$xml, &$data_type)
		/* given an xml object, extract any specified type and return it
		 *
		 * does not alter xml
		 *
		 * sets value of the variable $data_type to the type of data:
		 *  bool, blob, string, date, double, float, real, timestamp,
		 *  datetime, int
		 *
		 * returns sql
		 *
		 */
		{
			$this->_debug_start();
			
			# initialize return sql
			$return_sql = '';
			
			switch(@strtolower($xml->_getAttribute('type'))) {
				case 'bool':
					$this->_debug('boolean data type');
					$data_type = 'bool';
					$return_sql .= ' BOOL';
					break;
				case 'boolean':
					$this->_debug('boolean data type');
					$data_type = 'bool';
					$return_sql .= ' BOOL';
					break;
				case 'blob':
					$this->_debug('blob data type');
					# get the field length
					$length = intval($xml->_getAttribute('length'));
					# update the query
					if ($length <= 255) {
						$return_sql .= " TINYBLOB";
					} elseif ($length <= 65535) {
						$return_sql .= " BLOB";
					} elseif ($length <= 16777215) {
						$return_sql .= " MEDIUMBLOB";
					} elseif ($length <= 4294967295) {
						$return_sql .= " LONGBLOB";
					} else {
						$return_sql .= " BLOB";
					}
					$data_type = 'blob';
					break;
				case 'clob':
					$this->_debug('clob data type');
					# get the field length
					$length = intval($xml->_getAttribute('length'));
					# update the query
					if ($length <= 255) {
						$return_sql .= " TINYTEXT";
					} elseif ($length <= 65535) {
						$return_sql .= " TEXT";
					} elseif ($length <= 16777215) {
						$return_sql .= " MEDIUMTEXT";
					} elseif ($length <= 4294967295) {
						$return_sql .= " LONGTEXT";
					} else {
						$return_sql .= " TINYTEXT";
					}
					$data_type = 'string';
					break;
				case 'date':
					$this->_debug('data type: date');
					$return_sql .= ' DATE';
					$data_type = 'date';
					break;
				case 'datetime':
					$this->_debug('data type: datetime');
					$return_sql .= ' DATETIME';
					$data_type = 'datetime';
					break;
				case 'double':	
					$this->_debug('data type: double');
					$return_sql .= ' DOUBLE';
					if (intval($xml->_getAttribute('length')) > 0) {
						$return_sql .= '(' . intval($xml->_getAttribute('length'));
						if (intval($xml->_getAttribute('precision')) > 0) {
							$return_sql .= ',' . intval($xml->_getAttribute('precision'));
						}
						$return_sql .= ')';
					}
					$data_type = 'double';
					break;
				case 'float':
					$this->_debug('data type: float');
					$return_sql .= ' FLOAT';
					if (intval($xml->_getAttribute('length')) > 0) {
						$return_sql .= '(' . intval($xml->_getAttribute('length'));
						if (intval($xml->_getAttribute('precision')) > 0) {
							$return_sql .= ',' . intval($xml->_getAttribute('precision'));
						}
						$return_sql .= ')';
					}
					$data_type = 'float';
					break;
				case 'int':
					$this->_debug('integer data type');
					# update the query
					if (intval($xml->_getAttribute('length')) == 0) {
						$return_sql .= ' INT';
					} else {
						$return_sql .= ' INT(' . intval($xml->_getAttribute('length')) . ')';
					}
					$data_type = 'int';
					break;
				case 'integer':
					$this->_debug('integer data type');
					# update the query
					if (intval($xml->_getAttribute('length')) == 0) {
						$return_sql .= ' INT';
					} else {
						$return_sql .= ' INT(' . intval($xml->_getAttribute('length')) . ')';
					}
					$data_type = 'int';
					break;
				case 'real':
					$this->_debug('data type: real');
					$return_sql .= ' REAL';
					if (intval($xml->_getAttribute('length')) > 0) {
						$return_sql .= '(' . intval($xml->_getAttribute('length'));
						if (intval($xml->_getAttribute('precision')) > 0) {
							$return_sql .= ',' . intval($xml->_getAttribute('precision'));
						}
						$return_sql .= ')';
					}
					$data_type = 'real';
					break;
				case 'text':
					$this->_debug('data type: text');
					$return_sql .= ' TEXT';
					$data_type = 'string';
					break;
				case 'time':
					$this->_debug('data type: time');
					$return_sql .= ' TIME';
					$data_type = 'time';
					break;
				case 'timestamp':
					$this->_debug('data type: timestamp');
					$return_sql .= ' TIMESTAMP';
					$data_type = 'timestamp';
					break;
				case 'string':
					$this->_debug('string data type');
					# update the query
					$return_sql .= ' VARCHAR(' . intval($xml->_getAttribute('length')) . ')';
					$data_type = 'string';
					break;
				default:
					# bad data type
					return $this->_return(false, 'bad data type supplied for column ' . $xml->_getName());
					break;
			} // switch
			
			return $this->_retByRef($return_sql);
		}
		
		public function get_primary_keys($table)
		/* retrieve and return the primary keys for the specified table in the active database
		 *
		 */
		{
			$this->_debug_start("table = $table");
			
			$s = mysqli_prepare($this->connector, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_KEY='PRI'");
			if ($s === false) return $this->_return(false, 'Error preparing statement');
			
			$tmp = null;
			$r = Array();
			
			mysqli_stmt_bind_param($s, "s", $table);
			mysqli_stmt_execute($s);
			mysqli_stmt_bind_result($s, $tmp);
			while (mysqli_stmt_fetch($s)) {
				$r[] = array('column_name'=>$tmp);
			}
	    mysqli_stmt_close($s);
	    
	    return $this->_return($r, 'Done');
		}
		
		public function insert($table, $keys_or_xml, $values = '', $debug_me = false)
		/* insert wrapper function to provide a gradual upgrade path towards complete xml I/O
		 *
		 */
		{
			if ($debug_me) $this->_debug_mode = 99;
			$this->last_insert_id = 0;
			if (is_array($keys_or_xml)) {
				return $this->insert_from_array($table, $keys_or_xml, $values);
			} else {
				return $this->insert_from_xml($table, $keys_or_xml);
			}
		}
		
		protected function insert_from_xml($table, xml_object &$xml)
		/* insert data into a single table
		 *
		 */
		{
			$this->_debug_start("table = $table");
						
			# ensure all required data exists
			if (strlen(trim($table)) == 0) {
				return $this->_return(false, 'error: no table was supplied');
			}
			
			$this->_debug('building insert by row');
			
			$data = $xml->_getChildren();
			
			# for the time being, we have to run a separate query for every single row since
			# we can not ensure that every xml representation of every row will have the same exact fields
			# in the same exact order; and since at this point in time this object knows nothing about
			# the physical structure of the database and/or tables.  in the future, the object should pull
			# the layout of a table before modifying it to make sure that the modifications are proper.
			# this will also allow the query to be restructured so that rows can have whatever data they like
			# (mismatched and all) and the function will still properly apply it.
			#
			# once that type of validation is coded, the required for the "type" attribute in fields can be
			# removed, since this object will already know what type of data each field contains.
			
			foreach($data as &$row) {
				# begin query string
				$qryStr = "INSERT INTO `$table` ";
				
				$fields = $row->_getChildren();
				$fieldList = '';
				$valueList = '';
				
				foreach($fields as $field) {
					$fieldList .= '`' . $field->_getName() . '`,';
					switch ($field->_getAttribute('type')) {
						case 'integer': $valueList .= $field->_getValue() . ','; break;
						case 'double': $valueList .= $field->_getValue() . ','; break;
						case 'float': $valueList .= $field->_getValue() . ','; break;
						case 'real': $valueList .= $field->_getValue() . ','; break;
						case 'bool': $valueList .= $field->_getValue() . ','; break;
						default:
							$valueList .= "'";
							if ($field->_countChildren() > 0) {
								$c = $field->_getChildren();
								foreach ($c as $child) { $valueList .= $this->escape($child->_getValue()); }
							} else {
								$valueList .= $this->escape($field->_getValue());
							}
							$valueList .= "',";
							break;
					}
					$valueList = substr($valueList, 0, strlen($valueList) - 1) . ',';
				}
						
				# remove the final comma from each list
				$this->strip_last_character($fieldList);
				$this->strip_last_character($valueList);
				
				$qryStr .= "($fieldList) VALUES ($valueList)";
				
				$this->_debug('final insert statement: ' . $qryStr);
				
				if (! mysqli_query($this->connector, $qryStr)) {
					$this->errno = mysqli_errno($this->connector);
					$this->error = mysqli_error($this->connector);
					return $this->_return(false, 'Error [' . $this->errno . '] inserting data set: ' . $this->error . '!');
				}
				
				# store the insert id
				$this->last_insert_id = @mysqli_insert_id($this->connector);
			}
			
			# cache hook
			if ($this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return(true);
		}
		
		protected function insert_from_array($table, &$keys, &$values)
		/* insert data into a single table
		 *
		 *	keys and values should be arrays and have equal, matching elements
		 *
		 */
		{
			$this->_debug_start("table = $table");
						
			# ensure all required data exists
			if (strlen(trim($table)) == 0) {
				return $this->_return(false, 'error: no table was supplied');
			}
			
			if ( (count($keys) == 0) || (count($values) == 0) || (count($keys) != count($values)) ) {
				return $this->_return(false, 'error: keys and values are empty or mismatched');
			}
			
			# begin query string
			$this->_debug('building insert query');
			$qryStr = "INSERT INTO `$table` SET ";
			
			# add each update key/value pair
			foreach ($keys as $k=>$v) {
				$qryStr .= "`$v`=";
				if (is_null($values[$k]) || (strtolower('' . $values[$k]) == 'null')) {
					$qryStr .= 'NULL';
				} elseif (@strtoupper(substr($values[$k], 0, 5)) == 'NOW()') {
					$qryStr .= $values[$k];
				} else {
					if (is_int($values[$k])) {
						$qryStr .= $values[$k];
					} elseif (is_bool($values[$k])) {
						if ($values[$k]) { $qryStr .= 'true'; } else { $qryStr .= 'false'; }
					} else {
						$values[$k] = @mysqli_real_escape_string($this->connector, $values[$k]);
						$qryStr .= '\'' . $values[$k] . '\'';
					}
				}
				$qryStr .= ',';
			}
			
			# remove the final comma from the loop
			$this->strip_last_character($qryStr);
			
			if (strlen($qryStr) > 8198) {
				$this->_debug('final insert statement is over 8k');
			} else {
				$this->_debug('final insert statement: ' . $qryStr);
			}
			$this->_debug('');
			
			if (is_null($this->tlock)) {
				$this->_debug('acquiring write lock');
				@mysqli_query($this->connector, "LOCK TABLES $table WRITE");
			}
			
			$result = mysqli_query($this->connector, $qryStr);
			
			# store the insert id
			$this->last_insert_id = @mysqli_insert_id($this->connector);
			
			if (is_null($this->tlock)) {
				$this->_debug('releasing locks');
				@mysqli_query($this->connector, 'UNLOCK TABLES');
			}
			
			$this->errno = mysqli_errno($this->connector);
			$this->error = mysqli_error($this->connector);
			
			if ($result !== false) $result = true;
			
			# cache hook
			if ($result&&$this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return($result, b2s($result) . " ($this->errno) $this->error");
		}
		
		public function insert_id()
		/* return the insert id from the last insert
		 *
		 */
		{
			return $this->last_insert_id;
		}
		
		public function lock($table, $mode)
		/* lock a table for reading or writing
		 *
		 * modes: ( read | r | write | w )
		 *
		 */
		{
			if (!is_null($this->tlock)) return false;
			$table = trim((string)$table);
			if (strlen($table) == 0) return false;
			$table = '`' . $this->escape($table) . '`';
			switch(strtolower($mode)) {
				case 'read': $mode = 'READ'; break;
				case 'r': $mode = 'READ'; break;
				case 'write': $mode = 'WRITE'; break;
				case 'w': $mode = 'WRITE'; break;
				default: return false; break;
			}
			$this->_debug('acquiring ' . strtolower($mode) . ' lock');
			@mysqli_query($this->connector, "LOCK TABLES $table $mode");
			if (@mysqli_errno($this->connector) === 0) {
				$this->tlock = $table;
				return true;
			}
			return false;
		}
		
		public function query($table_or_xml, $searchKeys = '', $searchValues = '', $returnKeys = '', $returnMultipleResults = false, $sortOrder = '', $local_debug = false)
		/* legacy query function during transition
		 *
		 * please update all code to use db_mysql::select as soon as possible as query_raw is scheduled to be renamed to query
		 *
		 */
		{
			return $this->select($table_or_xml, $searchKeys, $searchValues, $returnKeys, $returnMultipleResults, $sortOrder, $local_debug);
		}
		
		protected function query_from_xml(xml_object &$xml)
		/* execute a query on a database using xml query notation
		 *
		 * $xml :: the query in xml format
		 *	<name>
		 *		<select>(comma seperated list of fields)</select>
		 *		<from>(root table)</from>
		 *		<where>
		 *			<column_name type="type">(value to match)</column_name>
		 *		</where>
		 *	</name>
		 *
		 *	where "name" is the name of the query,
		 *	 column_name is the name of the column (all sql characters are valid, except "<>")
		 *
		 * returns 0 or more rows as an array or false if there is an error
		 *
		 */
		{
			$this->_debug_start("building query using root table: '$table'");
			
			# initialize variables
			$qryStr = '';
			$returnArr = array();
			$join = false;
			
			# retrieve the query information
			$query_name = $xml->_getTag();
			$root_table = $xml->from->_getValue();
			
			# determine if the query is complex (join)
			if ($xml->from->_countChildren() > 0)
			{
			
			} else {
				# simple query
				$root_table = $xml->from->_getValue();
			}
			
			
			
			$select = $xml->select->_getValue();
			$where = $xml->where->_getChildren();
			
			# validate provided values
			
			# not implemented yet
			return $this->_return(false, 'function not implemented at this time');
		}
		
		protected function query_from_array($table, $searchKeys, $searchValues, $returnKeys = '', $returnMultipleResults = false, $sortOrder = '')
		/* Execute a query on a database
		 *
		 * use an array for search keys, search values, and return keys
		 *
		 * return keys, return multiple results, sort order, and debug me are optional arguments
		 *
		 * returnMultipleResults can be TRUE, FALSE, or an integer value greater than 1 to specify the max returned rows
		 *
		 * to query one table, use the following syntax:
		 *	query('tablename', array('searchfield-1', 'searchfield-2'), array('searchvalue-1', 'searchvalue-2'), array('returnfield'));
		 *
		 * to query multiple tables with a join, use the following syntax:
		 *	query(array('table-A'=>'join-fieldA', 'table-B'=>'join-fieldB'),
		 *				array('table-A,searchfield-1', 'table-A,searchfield-2'),
		 *				array('searchvalue-1', 'searchvalue-2'),
		 *				array('table-A,returnfield-1', 'table-B,returnfield-2');
		 *
		 * to issue a wildcard search (return all rows), set searchkeys and searchvalues to an empty string (not an array)
		 *	and either list returnkeys in an array or provide '*' to return all fields
		 *
		 * to perform partial matches, use search values starting and/or ending with '%'
		 *
		 * to retrieve a unique/distinct value for a field, prepend 'distinct' to the field name in the search key (*case insensitive*)
		 *
		 */
		{
			$this->_debug_start("table = $table");
			
			if (is_array($table)) {
				$join = true; 
			} else {
				$join = false;
				# ensure all required data exists
				$table = trim((string)$table);
				if (strlen($table) == 0) {
					return $this->_return(false, 'error: no table was specified');
				}
			}
			
			if ( (count($searchKeys) == 0) || (count($searchValues) == 0) || (count($searchKeys) != count($searchValues)) ) {
				return $this->_return(false, 'error: searchKeys and searchValues are empty or mismatched');
			}
			
			# validate multiple results value
			if ((!is_bool($returnMultipleResults)&&(!is_int($returnMultipleResults)))
			|| (is_int($returnMultipleResults)&&($returnMultipleResults <= 1))) {
				$returnMultipleResults = false;
			}
			
			# begin query string
			$this->_debug('building query');
			$qryStr = 'SELECT ';
			$table_list = array();
			
			# if a return array was specified, use that
			if (is_array($returnKeys)) {
				$this->_debug('returnKeys is an array');
				# insert each return key to the query string
				foreach($returnKeys as $item) {
					if ($join) {
						$arr = explode(",", $item);
						$qryStr .= '`' . $arr[0] . '`.`' . $arr[1] . '`,';
					} elseif ((strlen($item) > 9) && (strtolower(substr($item, 0, 8)) == 'distinct')) {
						$item = substr($item, 9);
						$qryStr .= "DISTINCT `$item`,";
					} else {
						$qryStr .= "`$item`,";
					}
				}
				# remove the final comma from the loop
				$this->strip_last_character($qryStr);
			} elseif ($returnKeys == '*') {
				$this->_debug('all columns requested');
				$qryStr .= '*';
			} else {
				$this->_debug('no returnKeys given or returnKeys was not an array, ignoring returnKeys and using searchKeys instead');
				# no return array was specified, use the searchKeys
				foreach($searchKeys as $item)	{	$qryStr .= "`$item`" . ',';	}
				# remove the final comma from the loop
				$this->strip_last_character($qryStr);
			}
			
			if (is_array($searchKeys) && is_array($searchValues)) {
				$this->_debug('searchKeys and searchValues are arrays');
				# add from clause
				$qryStr .= " FROM ";
				
				# add table(s)
				if ($join) {
					$this->_debug('join enabled');
					foreach($table as $tbl=>$join_field) {
						$table_list[] = $tbl;
						if (! isset($table_prime)) {
							$table_prime = $tbl;
							$table_prime_join_field = $join_field;
							$qryStr .= "$tbl";
						} else {
							$qryStr .= " LEFT JOIN $tbl ON `$table_prime`.`$table_prime_join_field` = `$tbl`.`$join_field`";
						}
					}
				} else {
					$this->_debug('no join');
					$qryStr .= "`$table`";
					$table_list[] = $table;
				}
				
				$qryStr .= " WHERE ";
				
				# add the where clauses
				foreach ($searchKeys as $k=>$v) {
					$qryStr .= $this->comp_where($searchKeys[$k], $searchValues[$k]) . ' AND ';
				}
				
				# remove the final comma from the loop
				$this->strip_last_character($qryStr, 5);
			} else {
				# No Restrictions on the data set
				if ($join) {
					$this->_debug('join enabled');
					foreach($table as $tbl=>$join_field) {
						$table_list[] = $tbl;
						if (! isset($table_prime)) {
							$table_prime = $tbl;
							$table_prime_join_field = $join_field;
							$qryStr .= " FROM `$tbl`";
						} else {
							$qryStr .= " LEFT JOIN `$tbl` ON `$table_prime`.`$table_prime_join_field` = `$tbl`.`$join_field`";
						}
					}
				} else {
					$this->_debug('no join');
					$qryStr .= " FROM `$table`";
					$table_list[] = $table;
				}
			}
			
			# Set Optional Sort Order
			if ( (is_array($sortOrder)) || (strlen((string)$sortOrder) > 0) ) {
				$this->_debug('optional sortOrder enabled, processing...');
				# add order by command
				$qryStr .= ' ORDER BY ';
				if (! is_array($sortOrder)) {
					# only one field to sort by
					$qryStr .= $sortOrder;
				} else {
					# multiple fields to sort by in an array
					foreach ($sortOrder as $tbl=>$field) {
						if ($join) {
							$arr = explode(",", $field);
							$qryStr .= '`' . $arr[0] . '`.`' . $arr[1] . '`,';
						} else {
							$qryStr .= "`$field`,";
						}
					}
					# remove the final comma from the loop
					$this->strip_last_character($qryStr);
				}
			}
			
			if (is_int($returnMultipleResults)) {
				$qryStr .= " LIMIT $returnMultipleResults";
				$returnMultipleResults = true;
			}
			
			$this->_debug('completed query string: ' . $qryStr);
			$this->_debug('');
			
			if (is_null($this->tlock)) {
				$this->_debug('acquiring read lock');
				@mysqli_query($this->connector, 'LOCK TABLES ' . implode(',', $table_list) . ' READ');
			}
			
			$this->_debug('executing query');
			$result = @mysqli_query($this->connector, $qryStr);
			$arr = array();
			
			if (is_null($this->tlock)) {
				$this->_debug('releasing locks');
				@mysqli_query($this->connector, 'UNLOCK TABLES');
			}
			
			# get the returned results into a variable
			if (@mysqli_num_rows($result) > 0) {
				if ($returnMultipleResults === true) {
					$this->_debug('multiple results enabled');
					# return all results
					$tmp = @mysqli_fetch_array($result, MYSQL_ASSOC);
					while ($tmp != false) { $arr[] = $tmp; $tmp = @mysqli_fetch_array($result, MYSQL_ASSOC); }
				} else { 
					$this->_debug('single result only');
					# return first result only
					$arr[0] = @mysqli_fetch_array($result, MYSQL_ASSOC);
				}
			} elseif(@mysqli_errno($this->connector) === 0) {
				return $this->_return(array(), 'mysql returned no results');
			} else {
				$result = false;
				$this->_debug("mysql returned error code #" . @mysqli_errno($this->connector) . ': ' . @mysqli_error($this->connector));
				return $this->_retByRef($result, 'An error occurred during the search');
			}
			
			$this->_debug('mysql found ' . @mysqli_num_rows($result) . ' rows');
			
			# release the search results
			@mysqli_free_result($result);
				
			return $this->_retByRef($arr);
		} // query
		
		public function queryFull($str, $local_debug = false)
		/* legacy alais for query_raw
		 *
		 */
		{
			return $this->query_raw($str, $local_debug);
		}
		
		public function query_raw($qryStr, $local_debug = false)
		/* Given a string or full query, return the result from the database
	 	 *
	 	 * try to avoid using this function since it is slated to be removed in version 2.0
	 	 *	if you absolutely have to use it then the other functions need to be modified
	 	 *
	 	 * this function is scheduled to be renamed to query once legacy code has been updated and moved from query -> select
	 	 *
		 */
		{
			if ($local_debug) $this->_debug_mode = 99;
			
			$this->_debug_start();
			
			$this->_debug('<em><strong>WARNING:</strong> This function is scheduled to be removed in version 2.0!
											Please update your code to use the standard database interface functions!</em>');
			
			$this->_debug('provided query string: ' . $qryStr);
			$this->_debug('');
			
			$this->_debug('executing query');
			#$result = @mysqli_query($this->connector, "SET NAMES 'utf8'");
			$result = @mysqli_query($this->connector, $qryStr);
			$arr = array();
			
			# get the returned results into a variable
			if (@mysqli_num_rows($result) > 0) {
				$this->_debug('multiple results enabled');
				# return all results
				$tmp = @mysqli_fetch_array($result, MYSQL_ASSOC);
				while ($tmp != false) { $arr[] = $tmp; $tmp = @mysqli_fetch_array($result, MYSQL_ASSOC); }
			} elseif (@mysqli_errno($this->connector) == 0) {
				$this->_debug('mysql returned zero results without an error');
			} else {
				$result = false;
				$this->_debug("mysql returned error code #" . @mysqli_errno($this->connector) . ': ' . @mysqli_error($this->connector));
				return $this->_retByRef($result, 'An error occurred during the search');
			}
			
			$this->_debug('mysql found ' . @mysqli_num_rows($result) . ' rows');
			
			# release the search results
			@mysqli_free_result($result);
			
			if ($local_debug) $this->_debug_mode = -1;
				
			return $this->_retByRef($arr);
		}
		
		private function remove_trailing_comma(&$string)
		/* Remote any trailing commas from the supplied string, altering the supplied string
		 *
		 */
		{
			# remove a trailing comma if one exists
			if (substr($string, (strlen($string) - 1), 1) == ',') $string = substr($string, 0, (strlen($string) - 1));
			return true;
		}
		
		public function set_debug_mode($level)
		/* set debug mode to specified value 
		 *
		 */
		{
			$this->_debug_mode = $level;
			return true;
		}
		
		public function select($table_or_xml, $searchKeys = '', $searchValues = '', $returnKeys = '', $returnMultipleResults = false, $sortOrder = '', $local_debug = false)
		/* wrapper function for select to concurrently support new xml queries and legacy array queries
		 *
		 * this is the old query function
		 *
		 */
		{
			if ($local_debug) $this->_debug_mode = 99;
			if (is_array($searchKeys) || (strlen($searchKeys) == 0)) {
				return $this->query_from_array(
						$table_or_xml,
						$searchKeys,
						$searchValues,
						$returnKeys,
						$returnMultipleResults,
						$sortOrder);
			}
			
			$this->_debug('select from xml');
			return $this->query_from_xml($table_or_xml);
		}
		
		protected function strip_last_character(&$str, $reduction = 1)
		/* given a string, remove the last character (typically a comma for this object)
		 *
		 * alters provided string, returns true
		 *
		 */
		{
			if (strlen($str) > $reduction) {
				$str = substr($str, 0, (strlen($str) - $reduction));
			} else {
				$str = '';
			}
			
			return true;
		}
		
		public function unescape($str)
		/* Undo changes mysqli_real_escape_string does to line breaks
		 *
		 */
		{
			$arr = array('\r\n', '\n', '\r');
			return str_replace($arr, "\r\n", $str);
		}
		
		public function unlock()
		/* unlock tables
		 *
		 */
		{
			$this->_debug('releasing locks');
			@mysqli_query($this->connector, 'UNLOCK TABLES');
			if (@mysqli_errno($this->connector) === 0) {
				$this->tlock = null;
				return true;
			}
			return false;
		}
		
		public function update($table, $keys_or_xml, $values = '', $updateKeys = '', $updateValues = '', $debugMe = false)
		/* update wrapper function to provide a gradual upgrade path towards complete xml I/O
		 *
		 */
		{
			if (is_array($keys_or_xml)) {
				return $this->update_from_array($table, $keys_or_xml, $values, $updateKeys, $updateValues, $debugMe);
			} else {
				return $this->update_from_xml($table, $keys_or_xml);
			}
		}
		
		protected function update_from_xml($table, xml_object &$xml)
		/* insert data into a single table
		 *
		 */
		{
			$this->_debug_start("table: `$table`");
			
			# ensure all required data exists
			if (strlen(trim($table)) == 0) return $this->_return(false, 'error: no table was supplied');
			
			$this->_debug('building update sql');
			
			$match = $xml->match->_getChildren();
			$set = $xml->set->_getChildren();
			
			if (($match === false)&&($xml->match->_getValue() !== '*')) return $this->_return(false, 'error: nothing to match against');
			if ($set === false) return $this->_return(false, 'error: nothing to set');
			
			# build the match string
			if ($xml->match->_getValue() == '*') {
				# special case for wildcard match
				$matchStr = '1=1';
			} else {
				$matchStr = '';
				foreach($match as &$field) {
					$matchStr .= '`' . $field->_getTag() . '`=';
					switch ($field->_getAttribute('type')) {
						case 'integer': $matchStr .= $field->_getValue(); break;
						case 'int': $matchStr .= $field->_getValue(); break;
						case 'double': $matchStr .= $field->_getValue(); break;
						case 'float': $matchStr .= $field->_getValue(); break;
						case 'real': $matchStr .= $field->_getValue(); break;
						case 'bool': $matchStr .= $field->_getValue(); break;
						case 'boolean': $matchStr .= $field->_getValue(); break;
						default:
							$matchStr .= "'" . $this->escape($field->_getValue()) . "'";
							break;
					}
					$matchStr .= ' AND ';
				}
				
				# remove the final and
				$this->strip_last_character($matchStr, 5);
			}
			
			$qryStr = "UPDATE `$table` SET ";
			
			foreach($set as &$field) {
				$qryStr .= '`' . $field->_getTag() . '`=';
				switch ($field->_getAttribute('type')) {
					case 'integer': $qryStr .= $field->_getValue(); break;
					case 'int': $qryStr .= $field->_getValue(); break;
					case 'double': $qryStr .= $field->_getValue(); break;
					case 'float': $qryStr .= $field->_getValue(); break;
					case 'real': $qryStr .= $field->_getValue(); break;
					case 'bool': $qryStr .= $field->_getValue(); break;
					case 'boolean': $qryStr .= $field->_getValue(); break;
					case 'null': $qryStr .= 'NULL'; break;
					case 'sql':
						# SPECIAL UPDATE CASE
						if ($xml->match->_getValue() == '*') {
							# get all values for the specified field
							$list = $this->query($table, '', '', array($field->_getTag()), true);
							if (!is_array($list)) $list = array();
							for ($i=0;$i<count($list);$i++){
								# get the value to update to
								$this->_debug('Field: `' . $field->_getTag() . '`, Value: `' . $list[$i][$field->_getTag()] . '`');
								$newQuery = str_replace('#SELF#', $list[$i][$field->_getTag()], $field->_getValue());
								$result = @mysqli_query($this->connector, $newQuery);
								/* DEBUG */ $this->_debug("VALUE QUERY: $newQuery");
								if (@mysqli_num_rows($result) > 0) { $newValue = @mysqli_fetch_array($result); } else { $newValue = array(0 => null); }
								$sql_sql = str_replace('#SELF#', $list[$i][$field->_getTag()], "UPDATE `$table` SET `" . $field->_getTag() . "` = '" . $newValue[0] . "' WHERE `" . $field->_getTag() . "`='#SELF#'");
								@mysqli_query($this->connector, $sql_sql);
								/* DEBUG */ $this->_debug("UPDATE QUERY: $sql_sql");
							}
						} else {
							return $this->_return(false, 'Only Wildcard matches are supported for xml-SQL column updates');
						}
						return $this->_return(true);
						break;
					case 'column':
						# SPECIAL UPDATE CASE
						# - update this column from another column in the same table
						
						# get the primary key(s) for this table
						$tmp = $this->get_primary_keys($table);
						if ($tmp === false) return $this->_return(false, 'Unable to load the primary keys');
						$keys = Array();
						for ($i=0;$i<count($tmp);$i++) $keys[] = $tmp[$i]['column_name'];
						
						# get all matching rows in this table
						$this->_debug('Match query: ' . "SELECT " . implode(',', $keys) . ',`' . $field->_getValue() . "` FROM $table WHERE $matchStr");
						$result = @mysqli_query($this->connector, "SELECT " . implode(',', $keys) . ',`' . $field->_getValue() . "` FROM $table WHERE $matchStr");
						$list = array();
						$tmp = @mysqli_fetch_array($result, MYSQL_ASSOC);
						while ($tmp != false) { $list[] = $tmp; $tmp = @mysqli_fetch_array($result, MYSQL_ASSOC); }
						
						$this->_debug('Matched ' . count($list) . ' columns');
						
						# update columns
						for($i=0;$i<count($list);$i++){
							# build local match statement
							$matchLocalStr = '';
							foreach ($list[$i] as $k=>$v) $matchLocalStr .= "`$k`='$v' AND ";
							$this->strip_last_character($matchLocalStr, 5);
							$qryStr = "UPDATE `$table` SET `" . $field->_getTag() . "` = '" . $list[$i][$field->_getValue()] . "' WHERE $matchLocalStr";
							$this->_debug('Running update query: ' . $qryStr);
							@mysqli_query($this->connector, $qryStr);
						}
						return $this->_return(true);
						break;
					default:
						$qryStr .= "'" . $this->escape($field->_getValue()) . "'";
						break;
				}
				$qryStr .= ', ';
			}
			
			# remove the final comma
			$this->strip_last_character($qryStr, 2);
			
			# add the pre-built where clause
			$qryStr .= " WHERE $matchStr";
			
			$this->_debug('final update statement: ' . $qryStr);
			
			if (! mysqli_query($this->connector, $qryStr)) {
				$this->errno = mysqli_errno($this->connector);
				$this->error = mysqli_error($this->connector);
				return $this->_return(false, 'Error [' . $this->errno . '] updating data set: ' . $this->error . '!');
			}
			
			# cache hook
			if ($this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return(true);
		}
		
		protected function update_from_array($table, $searchKeys, $searchValues, $updateKeys, $updateValues, $debugMe = false)
		/* Run update query
		 *
		 */
		{
			if ($debugMe) { $this->_debug_mode = 99; }
			
			$this->_debug_start();
			
			# ensure all required data exists
			if (strlen(trim($table)) == 0) return $this->_return(false);
			
			if ( (count($searchKeys) == 0) || (count($searchValues) == 0) || (count($searchKeys) != count($searchValues)) ) {
				$this->_debug('Error: Invalid search criteria');
				return $this->_return(false);
			}
			
			if ( (count($updateKeys) == 0) || (count($updateValues) == 0) || (count($updateKeys) != count($updateValues)) ) {
				$this->_debug('Error: Invalid update criteria');
				return $this->_return(false);
			}
			
			# begin query string
			$qryStr = "UPDATE `$table` SET ";
			
			# add each update key/value pair
			foreach ($updateKeys as $k=>$v) {
				$qryStr .= "`$v`=";
				if (is_null($updateValues[$k]) || (strtolower('' . $updateValues[$k]) == 'null')) {
					$qryStr .= 'NULL';
				} elseif (strtoupper('' . $updateValues[$k]) == 'NOW()') {
					$qryStr .= 'NOW()';
				} else {
					if (is_int($updateValues[$k])) {
						$qryStr .= $updateValues[$k];
					} elseif (is_bool($updateValues[$k])) {
						if ($updateValues[$k]) { $qryStr .= 'true'; } else { $qryStr .= 'false'; }
					} else {
						$updateValues[$k] = mysqli_real_escape_string($this->connector, $updateValues[$k]);
						$qryStr .= '\'' . $updateValues[$k] . '\'';
					}
				}
				$qryStr .= ', ';
			}
			
			# remove the final comma from the loop
			$this->strip_last_character($qryStr, 2);
			
			$qryStr .= ' WHERE ';
			
			# add the where clauses
			foreach ($searchKeys as $k=>$v) {
				$qryStr .= $this->comp_where($searchKeys[$k], $searchValues[$k]) . ' AND ';
			}
			
			# remove the final and from the loop
			$qryStr = substr($qryStr, 0, strlen($qryStr) - 5);
			
			if (is_null($this->tlock)) {
				$this->_debug('acquiring write lock');
				@mysqli_query($this->connector, "LOCK TABLES $table WRITE");
			}
			
			$this->_debug("Final Query: $qryStr");
			$result = mysqli_query($this->connector, $qryStr);
			$num_rows = mysqli_affected_rows($this->connector);
			
			if (is_null($this->tlock)) {
				$this->_debug('releasing locks');
				@mysqli_query($this->connector, 'UNLOCK TABLES');
			}
			
			if ($result !== false) $result = true;
			if ($num_rows == 0) $result = false;
			
			# cache hook
			if ($result&&$this->_has('cache')&&(@is_object($this->_tx->cache))) { $this->_tx->cache->expire(array('table'=>$table)); }
			
			return $this->_return($result);
		} // update
		
		public function uuid()
		/* return a MySQL UUID
		 *
		 */
		{
			$result = @mysqli_fetch_row(mysqli_query($this->connector, 'Select UUID()'));
			if (is_array($result)) {
				return $result[0];
			} else {
				return false;
			}
		}
	
	}
?>