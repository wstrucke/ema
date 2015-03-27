<?php
 /* Transmission Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Jul-07-2009/Aug-17-2009/Mar-01-2010
  * William Strucke, wstrucke@gmail.com
  *
  * to do:
  *  - implement multiple concurrent versions of the same object and type
  *  - implement version checking for modules (see function _crossCheckExtensions)
  *  - allow extension arguments to have different names than the shared variables 
  *    they request
  *  - recode "embeddable functions/methods" as "passive hooks"
  *  - transmission serialization (sleep/wake)
  *  - implement variable write locking (security, restricted vars)
  *  - obey extension allow_export/allow_embed/et cetera... rules
  *  - design and implement aggressive hooks
  *
  */
	
	class transmission extends debugger
	{
		# settings
		protected $_defaults;           // default extension types
		protected $_gears;              // gear registry
		protected $_load_queue;         // object load queue
		protected $_mode;               // object internal request mode
		protected $_registry;           // extension registry
		protected $_restricted_vars;    // list of restricted variable names
		protected $_use_default_on_failure = true;
		                                // if true, use the default extension in a group when the requested type DNE
		
		# active data
		protected $_extensions;         // loaded extensions
		protected $_false = false;      // always false
		protected $_filament;           // filaments
		protected $_fuse;               // fuses
		protected $_fuse_data;          // array of fuse information, keys are "title", "content_type", and "value"
		protected $_private_ext;        // private loaded extensions
		protected $_published_vars;     // shared variables from objects
		protected $_unpublished_vars;   // non shared variables used to initialize new object instances
		
		# debugger settings
		protected $_copyright = 'Copyright &copy; 2004-2011 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
		protected $_debug_color;        // selected debug output color for prefix
		protected $_debug_match = '';   // optional comma seperated list of debug_prefixes to match when enabling debugging
		protected $_debug_mode;         // enable debugging mode
		protected $_debug_output = 2;   // debug output mode: 0=disabled, 2=inband/html,4=out of band/file,6=screen+file
		protected $_function_level = 0; // to count debug indentation for formatting
		public $_name = 'Transmission Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'transmission';
		
		/* code */
		
		public function __construct($args = array())
		{
			# set arguments
			foreach($args as $key=>&$value) {
				if (is_object($value) || is_array($value)) {
					$this->{$key} =& $value;
				} else {
					$this->{$key} = $value;
				}
			}
			
			# send initialization headers
			$this->_debug("initializing $this->_debug_prefix @ " . date('m/d/Y H:i:s'));
			$this->_debug($this->_version . ', ' . $this->_copyright);
			$this->_debug('debug level ' . $this->_debug_mode);
			$this->_debug('');
			
			# preset internal varables
			$this->_defaults = array();
			$this->_extensions = array();
			$this->_filament = new unique_pair($this);
			$this->_fuse = new unique_pair($this);
			$this->_fuse_data = array();
			$this->_load_queue = array();
			$this->_private_ext = array();
			$this->_published_vars = array();
			$this->_registry = array();
			$this->_restricted_vars = array();
			$this->_unpublished_vars = array();
			
			# initialize gears
			for ($i=0;$i<=LAST_STATE;$i++) { $this->_gears[$i] = array(); }
			
			# add our internal load queue to the first gear
			$this->_setGear(STATE_BEGIN, 'transmission', '_processLoadQueue');
		}
		
		public function &__get($item)
		/* transmission primary interface function
		 *
		 * extension requests can include options:
		 *	my_new_extensionName_optionA_optionB
		 *
		 *	'my' indicates the extension should be private to the
		 *		requesting object
		 *
		 *	'new' indicates the object should not be cached and will
		 *		cause the transmission to always return a new instance
		 *
		 *	where optionA = object type
		 *		e.g. to request an oracle database object use 'db_oracle'
		 *
		 *	and optionB = object instance, in the form of 1, 2, 3, etc...
		 *		e.g. to request a second instance of the database object use 'db_2'
		 *		'db_1' is the equivilent of requesting 'db'
		 *
		 *	instances above '1' are assumed to have the keyword 'my' and are
		 *		private to the requesting object. likewise if no instance is
		 *		specified or instance 1 is specified without 'my' the instance is
		 *		assumed to be public and shared among all objects.
		 *
		 *	the options can be combined, e.g. 
		 *		'db_mysql_3'
		 *		'my_db'
		 *		'my_db_oracle'
		 *		'db_2'
		 *
		 *	'db_2' is equivilent to 'my_db_2'
		 *	'db' is equivilent to 'db_1'
		 *	'my_db' is not equivilent to 'db' but is equivilent to 'my_db_1'
		 *
		 */
		{
			$this->_debug("limited debugging in magic function __get($item)");
			
			# sanity check
			if (strlen($item) == 0) {
				$this->_debug('Invalid (zero-length) request, terminating.');
				return $this->_false;
			}
			
			if ($this->_mode == 'var') {
				$this->_debug('var retrieval');
				
				# reset the mode
				$this->_mode = '';
				
				# return a variable
				if (isset($this->_published_vars[$item])) {
					return $this->_published_vars[$item]; // this should return a copy and not a reference... need to fix this (see restricted vars)
				} else {
					return $this->_false;
				}
			}
			
			if ($item == 'get') {
				$this->_debug('get var retrieval');
				
				# client is requesting a shared variable
				$this->_mode = 'var';
				
				return $this;
			}
			
			if ($item == 'set') {
				$this->_debug('set var retrieval');
				
				# client is requesting a shared variable
				$this->_mode = 'var';
				
				return $this;
			}
			
			if ($item == 'transmission') return $this;
			
			if (! $this->_decodeObjectString($item, $private, $extension, $type, $instance_no)) {
				$this->_debug('Error decoding the request string.');
				return $this->_false;
			}
			
			# preset storage array reference
			$store =& $this->_extensions;
			
			if ($private && ($instance_no >= 0)) {
				# enable php backtrace to get the name of the caller
				$b = debug_backtrace();
				
				# update storage array to point to the object's private array
				if (isset($b[1]['object'])) {
					$store =& $this->_private_ext[$b[1]['object']->_tx_instance];
				}
				if (! is_array($store)) $store = array();
			} elseif ($instance_no < 0) {
				# a new element was requested; do not cache it
				$tmp = array();
				$store =& $tmp;
			}
			
			# client is requesting an extension
			if (@is_object($store[$extension][$type][$instance_no])) {
				$this->_debug("extension retrieval: store[$extension][$type][$instance_no]");
				return $store[$extension][$type][$instance_no];
			}
			
			$this->_debug("creating new instance $instance_no");
			
			# check prerequisites
			foreach((array) $this->_registry[$extension][$type]['requires'] as $module) {
				if (! $this->_decodeObjectString($module, $p1, $g1, $t1, $i1)) {
					$this->_debug("<strong>Error loading required module $module</strong>");
					return $this->_false;
				}
				if (@file_exists($this->_published_vars['engine_root'] . $this->_registry[$g1][$t1]['path'])) {
					require_once ($this->_published_vars['engine_root'] . $this->_registry[$g1][$t1]['path']);
				} elseif (@file_exists($this->_registry[$g1][$t1]['path'])) {
					require_once ($this->_registry[$g1][$t1]['path']);
				} else {
					die('Error locating a required object at the path ' . $this->_registry[$g1][$t1]['path']);
				}
			}
			
			# this is a new extension
			$obj =& $store[$extension][$type][$instance_no];
			
			# include the object file
			if (@file_exists($this->_published_vars['engine_root'] . $this->_registry[$extension][$type]['path'])) {
				require_once($this->_published_vars['engine_root'] . $this->_registry[$extension][$type]['path']);
			} elseif (@file_exists($this->_registry[$extension][$type]['path'])) {
				require_once($this->_registry[$extension][$type]['path']);
			} else {
				$this->_debug('Error: Path not found [' . $this->_registry[$extension][$type]['path'] . ']');
				$this->db->update('modules',array('name'), array($extension . '_' . $type), array('enabled'), array(false));
				unset($this->_registry[$extension][$type]);
				return $this->_false;
			}
			
			# preset object load array
			$loadArr = array(
				'_debug_match'=>false,
				'_debug_mode'=>false,
				'_function_level'=>false,
				'_debug_output'=>false,
				'_tx_instance'=>true);
			
			$this->_preRegister($item, array('_tx_instance'=>uniqid()));
			
			# build the required and/or optional argument array for the object
			$args = array_merge($this->_registry[$extension][$type]['args'], $loadArr);
			
			# debug output arg list
			$arg_list_dbg = implode(',', array_keys($args));
			$this->_debug("Args are: $arg_list_dbg");
			
			# parse the arguments
			foreach($args as $var=>$val) {
				$arg_type = '';
				$arg_model = '';
				if (strpos($val, ',') !== false) {
					$this->_debug('disambiguating value');
					$temp = explode(',', $val);
					$val = $temp[0];
					$arg_type = $temp[1];
					$arg_model = $temp[2];
					if ($val == 'true') { $val = true; } elseif ($val == 'false') { $val = false; }
				}
				if (isset($this->_unpublished_vars[$item][$var])) {
					# avoid sending passwords to the debug output
					if (strcmp($var, 'password') === 0) { $tmpdebug = '<em>redacted</em>'; } else { $tmpdebug = $this->_unpublished_vars[$item][$var]; }
					$this->_debug("setting variable $var to local value $tmpdebug");
					$args[$var] =& $this->_unpublished_vars[$item][$var];
				} elseif (
						(isset($this->_published_vars[$var])) &&
						(
							( (strlen($arg_model) > 0) && ($this->_published_vars[$var] instanceof $arg_model) ) ||
							(strlen($arg_model) == 0)
							)
						)
				{
					$this->_debug("setting variable $var to global value " . $this->_published_vars[$var]);
					$args[$var] =& $this->_published_vars[$var];
				} else {
					$this->_debug("attempting to dynamically load required variable $var");
					if ( ($arg_type == 'object') && (strlen($arg_model) > 0) && ($this->__get($arg_model)) ) {
						$args[$var] =& $this->$arg_model;
					} elseif ($this->$var) {
						$args[$var] =& $this->$var;
					} else {
						# the requested variable is not available
						if ($val) {
							# the variable is required and not available
							$this->_debug("the variable '$var' is required and not available");
							return $this->_false;
						} else {
							# the variable is not required
							$args[$var] = '';
						}
					}
				}
			}
			
			# reset the unpublished vars array for this object
			$this->_unpublished_vars[$item] = array();
			
			# set the object class name
			$className = $extension . '_' . $type;
			
			$this->_debug("class: $className");
			
			# create the new object
			$obj = new $className($this, $args);
			
			# set the directory path for the object
			if (property_exists($className, '_myloc')) {
				$this->_debug('setting directory path for the new object');
				$dname = dirname($this->_registry[$extension][$type]['path']);
				if (@strpos($dname, $this->_published_vars['engine_root']) === 0) { $dname = substr($dname, strlen($this->_published_vars['engine_root'])); }
				$obj->_myloc = $this->_published_vars['engine_root'] . $dname . '/';
			}
			
			# validate optional schema for the new extension
			if (property_exists($className, 'schema_version')) {
				$this->_debug('validating the object schema');
				if (! $this->_checkSchema($obj, $extension, $type)) return $this->_false;
			}
			
			# validate the registered object version versus the actual version
			if ((strpos($className, 'db_') !== 0) && property_exists($className, '_version')) {
				$this->_debug('validating the object version');
				switch(version_compare($obj->_version, $this->_registry[$extension][$type]['version'])) {
					case -1: $this->_debug('Warning: The running extension is older than the registered version!'); break;
					case 0: $this->_debug('The versions match'); break;
					case 1:
						$this->_debug('The object has been updated; running upgrade scripts');
						# exec the installation script
						$obj->_install();
						# update the object version and flag the module for a refresh
						$this->_registry[$extension][$type]['version'] = $obj->_version;
						$this->db->update(
							'modules',
							array('name'),
							array($extension . '_' . $type),
							array('module_version'), array($obj->_version));
						$this->_registry[$extension][$type]['version'] = $obj->_version;
						break;
					default: $this->_debug('Warning: An error occurred during the object version comparison!'); break;
				}
			}
			
			# register any gears
			$this->_debug('registering object gears (' . $extension . '_' . $type . ')');
			if (method_exists($obj, 'preinitialize')) $this->_setGear(STATE_INITIALIZE-1, $className, 'preinitialize');
			if (method_exists($obj, 'initialize')) $this->_setGear(STATE_INITIALIZE, $className, 'initialize');
			if (method_exists($obj, 'validate')) $this->_setGear(STATE_VALIDATE, $className, 'validate');
			if (method_exists($obj, 'optimize')) $this->_setGear(STATE_OPTIMIZE, $className, 'optimize');
			if (method_exists($obj, 'postoptimize')) $this->_setGear(STATE_OPTIMIZE+1, $className, 'postoptimize');
			if (method_exists($obj, 'preoutput')) $this->_setGear(STATE_PREOUTPUT, $className, 'preoutput');
			if (method_exists($obj, 'head')) $this->_setGear(STATE_HEAD, $className, 'head');
			if (method_exists($obj, 'body')) $this->_setGear(STATE_BODY, $className, 'body');
			if (method_exists($obj, 'postoutput')) $this->_setGear(STATE_POSTOUTPUT, $className, 'postoutput');
			if (method_exists($obj, 'unload')) $this->_setGear(STATE_UNLOAD, $className, 'unload');
			if (method_exists($obj, 'postunload')) $this->_setGear(STATE_UNLOAD+1, $className, 'postunload');
			
			$this->_debug('__get (\'er) done');
			return $obj;
		}
		
		public function __set($item, $value)
		/* change the value of a published variable
		 *
		 */
		{
			$this->_debug("limited debugging in magic function __set($item)");
			
			# reset the mode
			$this->_mode = '';
			
			# check for this variable in the restricted list
			if (array_key_exists($item, $this->_restricted_vars)) {
				# enable php backtrace to get the name of the caller
				$b = debug_backtrace();
				
				# this is a restricted variable
				$this->_debug('this variable is restricted, running security check');
				if ($this->_restricted_vars[$item] != $b[1]['class']) {
					$this->_debug('this provider is not authorized to modify this variable');
					return false;
				}
			} else {
				$this->_debug('unrestricted variable');
			}
			
			# update the value
			$this->_published_vars[$item] = $value;
			
			return true;
		}
		
		public function _check($extension)
		/* check if the extension group or specific extension is installed and enabled
		 *
		 * returns true or false
		 *
		 */
		{
			if (@is_object($this->__get($extension))) return true;
			return false;
		}
		
		protected function _checkSchema(&$obj, $extension_group, $type)
		/* check the schema version for an object against the registered version
		 *
		 */
		{
			$this->_debug_start();
			
			$registered_version = $this->_registry[$extension_group][$type]['schema'];
			$extension_version = $obj->schema_version;
			
			if ($registered_version == $extension_version) {
				$this->_debug($registered_version . ' == ' . $extension_version);
				return $this->_return(true, "schema versions match for extension $extension_group $type");
			}
			
			$this->_debug($registered_version . ' != ' . $extension_version);
			
			$this->db->drop_on_create = false;
			
			if ($registered_version == '0') {
				$this->_debug('creating initial schema');
				
				# attempt to load the extension's xml file
				$xml_path = dirname($this->_registry[$extension_group][$type]['path']) . '/extension.xml';
				if (!file_exists($xml_path)) $xml_path = $this->_published_vars['engine_root'] . $xml_path;
				if (! $this->xml_document->_load($xml_path)) return $this->_return(false, 'Error loading schema at ' . $xml_path);
				
				# retrieve this extension's schema version
				$schema = $this->xml_document->schema->_getChildren();
				$initial_data = $this->xml_document->data->_getChildren();
				
				# preset add data approval
				$add_data_denied = array();
				
				# install the extension's schema if one is set
				if ($schema) {
					foreach ($schema as &$table) {
						# create the table
						$this->_debug('adding table "' . $table->_getName() . '"');
						if (! $this->db->create($table->_getName(), $table)) {
							$add_data_denied[] = $table->_getName();
						}
					}
				} else {
					$this->_debug('this extension does not require a database');
				}
				
				# insert the extention's base data set (if one exists)
				if ($initial_data) {
					foreach($initial_data as &$table) {
						# only add data to a table if it did not fail to be created by this upgrade
						#  e.g. skip tables that already existed
						if (! in_array($table->_getName(), $add_data_denied)) {
							$this->db->insert($table->_getName(), $table);
						}
					}
				}
				
				# exec the installation script
				$this->_debug('calling the installation script');
				$obj->_install();
				$this->_debug('post install');
				
				# update the schema version
				$this->_registry[$extension_group][$type]['schema'] = $extension_version;
				$this->db->update(
					'modules',
					array('name'),
					array($extension_group . '_' . $type),
					array('schema_version'), array($extension_version));
				
				return $this->_return(true, "succesfully installed schema version $extension_version");
			} else {
				$this->_debug('updating schema');
				
				# set the upgrade schema path
				$xml_path = $this->_published_vars['engine_root'] . dirname($this->_registry[$extension_group][$type]['path']) . '/upgrade.xml';
				
				# attempt to load the upgrade file
				if (! $this->xml_document->_load($xml_path)) return $this->_return(false, 'Error loading upgrade schema');
				
				while (version_compare($registered_version, $extension_version, '!=')) {
					$all = $this->xml_document->_getChildren();
					$def = null;
					$new_version = null;
					
					foreach($all as $option) {
						if ($option->_getName() == $extension_group . '_' . $type . ':' . $registered_version) {
							$def = $option->_getChildren();
							$new_version = $option->_getAttribute('next');
							break;
						} else {
							$this->_debug(
								'Ignoring version mismatch: ' . $option->_getName() . 
								', looking for ' . $extension_group . '_' . $type . ':' . $registered_version);
						}
					}
					
					# make sure we found a version to upgrade to
					if (is_null($def)) {
						return $this->_return(false, 'Error: Unable to locate a schema upgrade for this extension version.
						  You may be running an unauthorized revision or there may be a problem with the extension registry.');
					}
					
					foreach($def as $table) {
						if ($table->_getName() == 'create') {
							$table = $table->_getChildren();
							foreach ($table as $schema) {
								# create the table
								$this->_debug('adding table "' . $schema->_getName() . '"');
								$this->db->create($schema->_getName(), $schema);
							}
						} elseif ($table->_getName() == 'drop') {
							$list = $table->_getChildren();
							foreach ($list as $table) {
								# drop the table
								$this->_debug('dropping table "' . $table->_getName() . '"');
								if (! $this->db->drop($table->_getName())) {
									return $this->_return(false, 'Error dropping table ' . $table->_getName());
								}
							}
						} elseif ($table->_getName() == 'insert') {
							$list = $table->_getChildren();
							foreach ($list as $table) {
								$this->_debug('inserting data into table "' . $table->_getName() . '"');
								if (! $this->db->insert($table->_getName(), $table)) {
									return $this->_return(false, 'Error inserting data for table ' . $table->_getName());
								}
							}
						} elseif ($table->_getName() == 'update') {
							$list = $table->_getChildren();
							foreach ($list as $table) {
								$this->_debug('updating data in table "' . $table->_getName() . '"');
								if (! $this->db->update($table->_getName(), $table)) {
									return $this->_return(false, 'Error updating data for table ' . $table->_getName());
								}
							}
						} elseif ($table->_getName() == 'delete') {
							$list = $table->_getChildren();
							foreach ($list as $table) {
								$this->_debug('delete data from table "' . $table->_getName() . '"');
								if (! $this->db->delete($table->_getName(), $table)) {
									return $this->_return(false, 'Error delete data from table ' . $table->_getName());
								}
							}
						} else {
							# standard alter existing table
							if (! $this->db->alter($table->_getName(),$table)) {
								return $this->_return(false, 'Error updating table ' . $table->_getName());
							}
						}
					}
					
					# update the schema version and flag the module for a refresh
					$this->_registry[$extension_group][$type]['schema'] = $new_version;
					$this->db->update(
						'modules',
						array('name'),
						array($extension_group . '_' . $type),
						array('schema_version', 'refresh'), array($new_version, true));
					
					# query the new database to check the version (this will allow for incremental updates)
					$registered_version = $this->db->query(
						'modules',
						array('name'),
						array($extension_group . '_' . $type),
						array('schema_version'));
					
					# make sure there was not an error (to prevent an endless loop)
					if (! db_qcheck($registered_version)) return $this->_return(false, 'Data error during automated database update');
					
					# set registered version from result
					$registered_version = $registered_version[0]['schema_version'];
				}
			}
			return $this->_return(true, "successfully upgraded database schema for $extension_group $type");
		}
		
		protected function _crossCheckExtensions()
		/* ...
		 *
		 */
		{
		/* imported from engine
			# get the list of pre-requisite extensions and versions
			foreach ($this->xmli->requires->_getChildren() as $x)
			{
				if (isset($this->extension_registry['offered'][$x->name->_getValue()]))
				{
					# which object provides this?
					$obj = $this->extension_registry['offered'][$x->name->_getValue()];
					# check versions
					if (version_compare($this->extension_registry['versions'][$obj], $x->version_minimum) >= 0)
					{
						# passed minimum check
						if (	(strlen($x->version_maximum->_getValue()) > 0) &&
									(version_compare($this->extension_registry['versions'][$obj], $x->version_maximum) > 0)
									)
						{
							# the provider object is too new
							$this->_debug('The version of ' . $obj . ' providing ' . $x->name . ' is too new for ' 
														. $this->xmli->object_name);
							$this->_debug($this->xmli->_getName() . ' is disabled.');
							$ext_approved = false;
							$ext_disapproved = true;
							break;
						}
					} else {
						# the provider object is too old
						$this->_debug('The version of ' . $obj . ' providing ' . $x->name . ' is too old for ' 
													. $this->xmli->object_name);
						$this->_debug($this->xmli->_getName() . ' is disabled.');
						$ext_approved = false;
						$ext_disapproved = true;
						break;
					}
				} else {
					$this->_debug('no object is currently available to provide a pre-requisite for this extension');
					$this->_debug('looking for: ' . $x->name->_getValue());
					$ext_approved = false;
					break;
				} // if isset extension_registry...
			} // foreach
		*/
		}
		
		protected function _debug($message, $no_line_break = false)
		/* conditionally output debug message
		 *
		 */
		{
			# set the global file handle to avoid locking issues
			global $_ema_debug_file_handle;
			# validate debug prefix
			if (!is_string($this->_debug_prefix)) $this->_debug_prefix = '';
			# check for debug matching
			if (is_string($this->_debug_match)) {
				$match = explode(',', $this->_debug_match);
				if (!in_array($this->_debug_prefix, $match)) return true;
			}
			# check if colors have been set yet
			if ($this->_debug_color == '') {
				$colors = array(  '#666', '#ff3300', '#cc3300', '#cc0033', '#333300', '#666600', '#0033ff',
				                  '#cc6633', '#330000', '#660000', '#990000', '#cc0000', '#ff0000', '#666633',
				                  '#996600', '#663333', '#993333', '#cc3333', '#ff3333', '#cc3366', '#0033cc',
				                  '#669900', '#996633', '#663300', '#990033', '#cc3399', '#339900', '#cc6600',
				                  '#cc0066', '#990066', '#336600', '#669933', '#cc6699', '#993366', '#660033',
				                  '#cc0099', '#330033', '#996699', '#993399', '#990099', '#663366', '#660066',
				                  '#006600', '#336633', '#009900', '#339933', '#ff00ff', '#cc33cc', '#003300',
				                  '#006633', '#339966', '#3399ff', '#9966cc', '#663399', '#330066', '#9900cc',
				                  '#cc00cc', '#009933', '#0066cc', '#9933ff', '#6600cc', '#660099', '#cc33ff',
				                  '#cc00ff', '#009966', '#003366', '#336699', '#6666ff', '#6666cc', '#0066ff',
				                  '#330099', '#9933cc', '#9900ff', '#339999', '#336666', '#006699', '#003399',
				                  '#3333ff', '#3333cc', '#333399', '#333366', '#6633cc', '#009999', '#006666',
				                  '#003333', '#3366cc', '#0000ff', '#0000cc', '#000099', '#000066', '#000033',
				                  '#6633ff', '#3300ff', '#3366ff', '#3300cc');
				# pick a color
				$this->_debug_color = $colors[array_rand($colors)];
				if (($this->_debug_mode >= 99)&&(($this->_debug_output - 6 >= 0)||($this->_debug_output == 2))) {
					echo "DEBUG MODE ENABLED [$this->_debug_color]<br />\r\n";
					# enable error output (enable during development)
					@error_reporting(E_ALL);
					@ini_set("display_errors", 1);
				}
			}
			if ($this->_debug_mode >= $this->_function_level) {
				if (($this->_debug_output - 6 >= 0)||($this->_debug_output == 2)) {
					echo '<span style="color:' . $this->_debug_color . ';">' . date('Y-m-d H:i:s') . " $this->_debug_prefix";
					if ($this->_debug_mode >= 99) { echo "[$this->_function_level]"; }
					echo '</span>';
					for ($i=0;$i<$this->_function_level;$i++) { echo '&nbsp;&nbsp;'; }
					echo " $message";
					if (!$no_line_break) echo "<br />\r\n";
				}
				if ($this->_debug_output - 4 >= 0) {
					# file output
					if (!is_resource($_ema_debug_file_handle)) $_ema_debug_file_handle = @fopen('ema_debug.txt', 'a');
					if (is_resource($_ema_debug_file_handle)) {
						$m = date('Y-m-d H:i:s') . ' ' . $_SERVER['SERVER_ADDR'] . ' ' . $_SERVER['REMOTE_ADDR'] . ' ' . $_SERVER['REQUEST_URI'] . " $this->_debug_prefix";
						if ($this->_debug_mode >= 99) { $m .= "[$this->_function_level]"; }
						for ($i=0;$i<$this->_function_level;$i++) { $m .= ' '; }
						$m .= " $message";
						if (!$no_line_break) $m .= "\r\n";
						@fwrite($_ema_debug_file_handle, $m);
					}
				}
			}
			unset($colors);
		}
		
		protected function _debug_start($message = '', $increment = 1)
		/* enable debugging for the calling function
		 *
		 * optionally output $message if one is supplied
		 *
		 * standard function level increment is 1, unless one is specified
		 *
		 */
		{
			# enable php backtrace to get the name of the caller
			$dbg = debug_backtrace();
			
			$this->_debug('function ' . $dbg[1]['function'] . "($message) {");
			$this->_function_level = $this->_function_level + $increment;
		}
		
		protected function _decodeObjectString(&$str, &$private, &$group, &$type, &$instance_no)
		/* given an object request string, decode it and set the control variables
		 *
		 */
		{
			# preset options
			$private = false;
			$group = '';
			$type = '';
			$instance_no = 1;
			
			# check for options in the extension request
			if (strpos($str, '_') !== false)
			{
				$arr = explode('_', $str);
				
				if ($arr[0] == 'my')
				{
					# private instance requested
					$private = true;
					array_shift($arr);
				}
				if ($arr[0] == 'new')
				{
					# new instance requested
					$instance_no = -1;
					$private = true;
					array_shift($arr);
				}
				if (intval($arr[count($arr) - 1]) > 0)
				{
					# specific instance requested
					if ($instance_no == -1)
					{
						# ignore specific instance numbers when keyword 'new' is used
						array_pop($arr);
					} else {
						$instance_no = intval(array_pop($arr));
						if ($instance_no > 1) $private = true;
					}
				}
				if (count($arr) > 1)
				{
					# a type of element was requested
					$type = array_pop($arr);
					#$this->_debug("setting array type $type");
				}
				$group = $arr[0];
			} else {
				$group = $str;
			}
			
			# make sure an extension in this group is actually registered
			if (! isset($this->_defaults[$group]))
			{
				$this->_debug("this extension group is unknown ($group)");
				return false;
			}
			
			if ($type == '')
			{
				# set type to the default type
				$type = $this->_defaults[$group];
			}
			
			# make sure an extension of this type is registered
			if (! isset($this->_registry[$group][$type]))
			{
				if ($this->_use_default_on_failure)
				{
					# override to use the default type
					$type = $this->_defaults[$group];
				} else {
					# this extension type does not exist
					$this->_debug("the requested extension does not exist: $extension_$type");
					return false;
				}
			}
			
			return true;
		}
		
		public function _exclude($extension = false)
		/* exclude a specific extension from gear processing
		 *
		 * if an extension is not provided, assume the calling extension
		 *
		 * if extension is '*' exclude all further processing
		 *
		 */
		{
			if ($extension == '*') {
				# reinitialize gears
				for ($i=0;$i<=LAST_STATE;$i++) { $this->_gears[$i] = array(); }
			}
			
			# WARNING: this function is only partially implemented
			return true;
		}
		
		public function _getEmbeddableMethods()
		/* alias for _getFilaments
		 *
		 */
		{
			return $this->_getFilaments();
		}
		
		public function _getExtensionMeta($id)
		/* return the registered metadata for the provided extension from the extension registry
		 *
		 * returns an array or false if the extension does not exist
		 *   array('args'=>$arguments, 'path'=>$path, 'requires'=>$requires, 'version'=>$version, 'schema'=>$schema);
		 *
		 */
		{
			// $this->_registry[$provides][$type] = array('args'=>$arguments, 'path'=>$path, 'requires'=>$requires, 'version'=>$version, 'schema'=>$schema);
			
			// not coded yet
			return false;
		}
		
		public function _getExtensions($include_types = false, $include_groups = false)
		/* return a list of loaded extensions
		 *
		 * returns an array of groups:
		 *  array('group1', 'group2', 'group3')
		 *
		 * if include_types is true, returns all types:
		 *  array('group1_type1', 'group1_type2', 'group2_type', 'group3_type')
		 *
		 * if include_types AND include_groups are true, returns groups AND types:
		 *  array('group1', 'group1_type1', 'group1_type2', 'group2', 'group2_type', etc...)
		 *
		 */
		{
			# create empty return array
			$list = array();
			
			# enumerate the registered extensions
			foreach($this->_registry as $group=>$typeArr) {
				if ($include_types) {
					reset($typeArr);
					while (list($type, $detail) = each($typeArr)) { $list[] = $group . '_' . $type; }
					if ($include_groups) $list[] = $group;
				} else {
					$list[] = $group;
				}
			}
			
			return $list;
		}
		
		public function _getFilaments()
		/* return unique filaments
		 *
		 */
		{
			return $this->_filament->get_pairs('values', '_');
		}
		
		public function _getFuse($providespath, $function = false)
		/* given a navigation path OR an object group and function,
		 *  match a loaded fuse or return false
		 *
		 * if successfully, returns an array with the following keys:
		 *  'path', 'provides', 'type', 'function', 'title', 'content_type'
		 *
		 */
		{
			for ($i=0;$i<count($this->_fuse_data);$i++) {
				if ($function) {
					# match against a function and type
					if ( ($this->_fuse_data[$i]['provides'] == $providespath)
							&& ($this->_fuse_data[$i]['function'] == $function) ) return $this->_fuse_data[$i];
				} else {
					# match against a path
					if ($this->_fuse_data[$i]['path'] == $providespath) return $this->_fuse_data[$i];
				}
			}
			return false;
		}
		
		public function _getFuses()
		/* return unique fuses
		 *
		 */
		{
			return $this->_fuse->get_pairs('values', '_');
		}
		
		public function _getGears($gear)
		/* return the registered function list for the specified gear
		 *
		 */
		{
			return $this->_gears[$gear];
		}
		
		public function _getInteractiveMethods()
		/* alias for _getFuses
		 *
		 */
		{
			return $this->_getFuses();
		}
		
		public function _getSharedData()
		/* return a list of available variables
		 *
		 */
		{
			return array_keys($this->_published_vars);
		}
		
		public function _matchEmbeddableMethod($class, $function)
		/* alias for _matchFilament
		 *
		 */
		{
			return $this->_matchFilament($class, $function);
		}
		
		public function _matchExtensions($match_group, $partial_match = false)
		/* return a subset of extensions matching the specified group
		 *
		 * if there are no matches, returns an empty array
		 *
		 * if partial_match is true, returns all partial matches
		 *
		 */
		{
			# create empty return array
			$list = array();
			
			# enumerate the registered extensions
			foreach($this->_registry as $group=>$typeArr) {
				if (($partial_match)&&(stripos($group, $match_group)===false)) {
					continue;
				} elseif ((!$partial_match)&&(strcasecmp($group, $match_group) != 0)) {
					continue;
				}
				reset($typeArr);
				while (list($type, $detail) = each($typeArr)) { $list[] = $group . '_' . $type; }
			}
			
			return $list;
		}
		
		public function _matchFilament($class, $function)
		/* given a class and function, check if it is registered
		 *
		 * returns true if a matching filament is configured
		 *
		 */
		{
			# sanity check
			if ( (strlen($class) == 0) || (strlen($function) == 0)) return false;
			
			return $this->_filament->is_pair($function, $class);
		}
		
		public function _matchFuse($class, $function)
		/* given a class and function, check if it is registered
		 *
		 * returns true if a matching interactive method is configured
		 *
		 */
		{
			# sanity check
			if ( (strlen($class) == 0) || (strlen($function) == 0)) return false;
			
			return $this->_fuse->is_pair($function, $class);
		}
		
		public function _matchInteractiveMethod($class, $function)
		/* alias for _matchFuse
		 *
		 */
		{
			return $this->_matchFuse($class, $function);
		}
		
		public function _moduleOption($args)
		/* get or set the specified module option
		 *
		 * args must be an array
		 *
		 * requires:
		 *  'module'        the module we are processing
		 *  'option'        a string or array of strings representing options/variables
		 *
		 * optional:
		 *  'value'         a string or array of strings to set corresponding options to
		 *  'scope'         the scope for the setting [ false | 'global' | 'site' | 'template' ]
		 *  'context'       if scope is set to 'site' or 'template', the site_id or template_id
		 * 
		 * value types will be preserved
		 *
		 * if the scope is not specified, the transmission will decide based
		 *  on the context of the request
		 *
		 * this function will always return the current value of the requested
		 *  option/variable (even if changing a value fails)
		 *
		 * if a single (string) option is set/requested a single value
		 *  will be returned
		 *
		 * if an array of options is set/requested, an array is returned
		 *
		 * the reserved option string value asterisk ('*') will match all module options
		 *
		 * if an array of options is provided but a single value is provided to
		 *  set them to, all options will be set to the same value, otherwise
		 *  if an array of corresponding values is provided both arrays must
		 *  have the same number of items.
		 *
		 * on error this function returns NULL
		 *
		 */
		{
			# validate provided data
			if (! is_array($args)) return null;
			if ((! array_key_exists('module', $args))||(strlen($args['module']) == 0)) return null;
			if ((! array_key_exists('option', $args))||((strlen($args['option']) == 0)&&(count($args['option']) == 0))) return null;
			if (is_array($args['option']) && is_array($args['value']) && (count($args['option']) != count($args['value']))) return null;
			# set or get?
			if (array_key_exists('value', $args)) {
				# attempt to update the data
				
				# build the update keys
				//if (is_array($args['option']))
			}
			
			/* code incomplete */
			return null;
		}
		
		public function _preRegister($item, $arrData)
		/* pre register key -> value pairs to be used to instantiate an object
		 *
		 * these are used one time; once an extension is registered the internal 
		 *	array storing this data is reset
		 *
		 * arrData should be in the form of "variable"=>"value"
		 *
		 */
		{
			# sanity check
			if ( (strlen($item) == 0) || (! is_array($arrData)) || (count($arrData) == 0) ) return false;
			
			if ( (! isset($this->_unpublished_vars[$item])) || (! is_array($this->_unpublished_vars[$item])) )
			{
				$this->_unpublished_vars[$item] = array();
			}
			
			$this->_unpublished_vars[$item] = array_merge($this->_unpublished_vars[$item], $arrData);
			
			return true;
		}
		
		public function _processLoadQueue()
		/* process any objects in the load queue
		 *
		 */
		{
			$this->_debug_start();
			
			$maximum_iterations = (count($this->_load_queue) * 3);
			$i = 0;
			
			while (count($this->_load_queue) > 0) {
				$i++; if ($i > $maximum_iterations) break;
				$name = array_shift($this->_load_queue);
				if (! $this->{$name}) array_push($this->_load_queue, $name);
			}
			
			return $this->_return(true);
		}
		
		public function _publish($name, &$value)
		/* publish a shared variable
		 *
		 */
		{
			$this->_debug_start();
			
			$this->_debug('processing variable ' . $name);
			
			# preset pass to true
			$pass = true;
			
			# check for this variable in the restricted list
			if (array_key_exists($name, $this->_restricted_vars)) {
				# now assume pass is false
				$pass = false;
				
				# enable php backtrace to get the name of the caller
				$b = debug_backtrace();
				
				# this is a restricted variable
				$this->_debug('this variable is restricted, running security check');
				if ($this->_restricted_vars[$name] == $b[1]['class']) {
					# okay - approved provider for this variable
					$pass = true;
				} else {
					$this->_debug('this provider is not authorized for this variable');
				}
			}
			
			# make sure this variable is not registered yet
			if ($pass && array_key_exists($name, $this->_published_vars)) {
				$this->_debug('Error: this variable is already published');
				$pass = false;
			}
			
			if ($pass) {
				# add the variable to the published array by reference
				$this->_published_vars[$name] =& $value;
			}
			
			return $this->_return(true);
		}
		
		public function _registerExtension($provides, $type, $version, $path, $requires = array(), $arguments = array(),
				$filament = array(), $fuse = array(), $gears = false, $schema = '0')
		/* add extension to the transmission
		 * 
		 * $provides is what the extension provides, i.e. how it will be accessed; e.g. 'dbx'
		 *	-	this is also known as the "extension group"
		 * 
		 * $type is the type of object, e.g. 'mysql'
		 *
		 * $version is the version of the object, e.g. '1.4'
		 *	-	simultaneous versions of the same object and type are not currently supported!
		 * 
		 * $path is the complete path to the object's class file to be included
		 * 
		 * $requires is an array of other objects that are pre-requisites to this object along with types, versions, and privacy
		 *	e.g. to require a public instance of an xml-object version 1.0, the string would be 'public_xmlobject_1.0'****
		 *	-	**** THIS IS LIKELY TO CHANGE ***
		 * 
		 * $arguments is an array of variables that need to be passed into the object's __CONSTRUCT method to initalize it
		 *	these should be in the form of 'shared_variable_name'=>required (true or false), for example:
		 *		'_debug_mode'=>false		(pass in the variable _debug_mode if it is available)
		 *		'output'=>true					(pass in the variable output or fail if it is not available)
		 * 
		 * $filament is an optional array of functions that can be used to output content to a page
		 *  - filament functions may not require any arguments since none will be passed
		 *    this behavior may be updated in the near future
		 *
		 * $fuse is an optional array of functions that can be used to output an entire page (e.g. 'download')
		 *  - fuse functions may not require any arguments since none will be passed
		 *    this behavior may be updated in the near future
		 *
		 * $gears is an optional boolean value. a value of true indicates the registered extension has gear functions
		 *
		 * $schema is an optional string value representing the version of the object's schema, if it has one
		 * 
		 */
		{
			$this->_debug_start();
			
			# sanity check
			if ( (! is_array($requires)) || (! is_array($arguments)) ) return $this->_return(false, 'Error 0');
			if ( (! is_array($filament)) || (! is_array($fuse)) ) return $this->_return(false, 'Error 1');
			if ( (strlen($provides) == 0) || (strlen($type) == 0) || (strlen($path) == 0) ) return $this->_return(false, 'Error 2');
			
			# isset/is_array functions require string values
			$provides = (string)$provides;
			$type = (string)$type;
			
			# validate provides array
			if ( (! isset($this->_registry[$provides])) || (! is_array($this->_registry[$provides])) ) $this->_registry[$provides] = array();
			
			# register the extension
			$this->_registry[$provides][$type] = array('args'=>$arguments, 'path'=>$path, 'requires'=>$requires, 'version'=>$version, 'schema'=>$schema);
			
			$this->_debug("registered extension group $provides, type $type");
			
			# if there is no default for this extension group, set this one as the default
			if (! isset($this->_defaults[$provides])) $this->_setDefault($provides, $type);
			
			foreach($filament as $fn) {
				$this->_filament->add($fn, $provides);
				$this->_filament->add($fn, $provides . '_' . $type);
			}
			
			foreach($fuse as $f) {
				$this->_fuse->add($f['function'], $provides);
				$this->_fuse->add($f['function'], $provides . '_' . $type);
				$this->_fuse_data[] = array('path'=>$f['path'], 'provides'=>$provides, 'type'=>$type,
						'function'=>$f['function'], 'title'=>$f['title'], 'content_type'=>$f['content_type']);
			}
			
			if ($gears) {
				# add this element to the loading queue
				$this->_load_queue[] = $provides . '_' . $type;
			}
			
			return $this->_return(true);
		}
		
		public function _restrict($arrList, $provider)
		/* restrict one or more published variables to the specified provider
		 *
		 * this prevents modification to shared data, usually to the owner only
		 *
		 * it also forces return of a copy of the variable via $this->_tx->get->var
		 *   so the original can not be altered in any way.  normally a reference
		 *   is returned.
		 *
		 */
		{
			if (!is_array($arrList)) return false;
			if (!is_string($provider)) return false;
			for($i=0;$i<count($arrList);$i++) {
				if (!array_key_exists($arrList[$i], $this->_restricted_vars)) {
					$this->_restricted_vars[$arrList[$i]] = $provider;
				}
			}
			return true;
		}
		
		protected function _retByRef(&$value, $message = '', $increment = 1)
		/* returns a value by reference on behalf of an internal function 
		 *	cleaning up as necessary
		 *
		 */
		{
			if ($message != '') $this->_debug($message);
			$this->_debug('}');
			$this->_function_level = $this->_function_level - $increment;
			return $value;
		}
		
		protected function _return($value, $message = '', $increment = 1)
		/* returns a value on behalf of an internal function 
		 *	cleaning up as necessary
		 *
		 */
		{
			if ($message != '') $this->_debug($message);
			$this->_debug('}');
			$this->_function_level = $this->_function_level - $increment;
			return $value;
		}
		
		public function _setDebug($mode)
		/* sets this object's and all objects debug mode to specified value
		 *
		 */
		{
			$this->_debug_mode = $mode;
			foreach($this->_extensions as $ext) {
				foreach($ext as $type) {
					foreach($type as &$instance) {
						if (is_object($instance)) $instance->_debug_on($mode);
					}
				}
			}
		}
		
		public function _setDefault($provides, $type)
		/* set the specified type of object as the default for the $provides extension group
		 *
		 */
		{
			# sanity check
			if ( (strlen($provides) == 0) || (strlen($type) == 0) ) return false;
			if (! isset($this->_registry[$provides][$type])) return false;
			
			$this->_debug("setting $type as the default $provides module");
			
			$this->_defaults[$provides] = $type;
		}
		
		protected function _setGear($gear, $module, $func)
		/* configure a gear to use the specified function
		 *
		 */
		{
			$this->_debug("registering function $func in gear $gear");
			
			# add this function to the gear registry
			$this->_gears[$gear][] = array('object'=>$module, 'function'=>$func);
		}
					
	}
?>