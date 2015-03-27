<?php
 /***********************************************************************************************
 *	Elegant, Modular Applications Engine
 *	-- formerly Content Management System (CMS) Class
 *	=============================================================================================
 *	Version 1.0.0 : 2005.06.30
 *	Version 2.0.0-alpha-1 : 2007.02.14,2008.09.27,2008.10.13,2008.10.18,2009.01.17,2010.02.17
 *	William Strucke [wstrucke@gmail.com]
 *	---------------------------------------------------------------------------------------------
 *	Internal Functions:
 *		__construct()           initialize the object with default settings
 *		checkDatabaseVersion()  checks the table version in the established database connection
 *		_debug($message)        outputs debugging information if debugging is enabled
 *		listExtensions($path, &$result)
 *		                        returns a list of extensions at the specified path
 *		validateExtension($ext) checks the specified extension to make sure it is valid
 *		retrieveTemplate($filename)
 *		                        opens template ($filename) and returns contents
 *	---------------------------------------------------------------------------------------------
 *	If this file appears distored or incorrectly spaced, change your tab size to 2 characters
 *	---------------------------------------------------------------------------------------------
 *	COPYRIGHT/FAIR USE NOTICE:
 *		Copyright (c) 2004-2010, William Strucke.
 *		All Rights Reserved.
 *
 *		You are granted one license to this software.  That entitles you to use this on ONE (1)
 *		website on one domain (i.e. www.yoursite.com).  For additional licensing information or 
 *		to obtain a new license, please contact William Strucke, wstrucke@gmail.com.
 *
 *	---------------------------------------------------------------------------------------------
 *	to do:
 *		- allow debug output to ajax monitor ('debug window') and restrict to specific session
 *		- rewrite debug levels to use 'notice', 'warning', 'error', etc...
 *
 *  timing example:
 *    $timer = new timer();
 *    $this->_debug('<strong>timing process: </strong>'); $timer->start();
 *    $timer->stop(); $this->_debug('<strong>total exec time <em>' . $timer->retrieve() . '</em></strong>'); $timer->reset();
 *
 ***********************************************************************************************/
	
	require_once (dirname(__FILE__) . '/abstract/debugger/debugger.class.php');
	require_once (dirname(__FILE__) . '/abstract/user_group/user_group.class.php');
	require_once (dirname(__FILE__) . '/base/0_timer/timer.class.php');
	
	define('NL', "\r\n");
	
	define('STATE_BEGIN', 0);
	
	define('STATE_INITIALIZE', 2);
	
	define('STATE_VALIDATE', 4);
	
	define('STATE_OPTIMIZE', 6);
	
	define('STATE_PREOUTPUT', 8);
	
	define('STATE_HEAD', 10);
	
	define('STATE_BODY', 12);
	
	define('STATE_POSTOUTPUT', 14);
	
	define('STATE_UNLOAD', 16);
	
	define('LAST_STATE', 17);
	
	# prepare global transmission link
	$t = null;
	
	class engine extends debugger
	{
		# Define Tier 1 Internal Variables
		var $absRootPath;             // the absolute root path to the web site on the server
		var $rootPath;                // the base directory on the server for all server-side only files
		var $displayPath;             // the path to display/output files on the server
		var $objectPath;              // the path to object/class files on the server
		var $extensionPath;           // the path to the extensions directory on the server
		var $queryPath;               // the path to query/data access files on the server
		var $resourcePath;            // the path to resource/data files on the server
		var $uri;                     // the server URI
		
		# Define Tier 2 Internal Variables
		var $configPath;              // the path to cms configuration file(s)
		var $functionPath;            // the path to shared libraries/functions
		
		# public variables
		public $_tx_instance;         // transmission object's instance number for this element
		public $client_extensions = array();
		                              // list of all client extensions
		public $upload_root;          // absolute path to the server writable upload folder
		
		# private global variables
		public $_version = 'elegant, modular applications engine 2.0.0-alpha-1';
		protected $_copyright = 'Copyright &copy; 2010 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
		protected $db_version = '0.2.6';
		protected $obj_version = '2.0.0-alpha-1';
		protected $built_in, $extensions;
		protected $first_load_queue;  // used to initialize extension schemas on their first install
		protected $time;              // the total execution time for the site
		
		# transmission
		protected $_tx;
		
		# state management
		private $gear = -1;             // global running state, incremented throughout execution
		
		protected $_debug_gear;         // optional array of gears to debug
		protected $_debug_match = '';   // optional comma seperated list of debug_prefixes to match when enabling debugging
		protected $_debug_mode;         // enable debugging mode
		protected $_debug_output = 2;   // debug output mode: 0=disabled, 2=inband/html,4=out of band/file,6=screen+file
		protected $_function_level = 0; // to count debug indentation for formatting
		protected $_debug_prefix = 'engine';
		protected $_debug_color;
		
		function __construct($debug = -1)
		/*	ladies and gentlemen, start your engines...
		 *	
		 */
		{
			# bring the global transmission link into scope
			global $t;
			
			# set object debugging mode 
			if (array_key_exists('debugoverride', $_GET)) {
				$this->_debug_mode = $_GET['debugoverride'];
				if (array_key_exists('debugmatch', $_GET)) { $this->_debug_match = $_GET['debugmatch']; } else { $this->_debug_match = false; }
				if (array_key_exists('debugoutput', $_GET)) { $this->_debug_output = intval($_GET['debugoutput']); }
			} else {
				$this->_debug_match = false;
				$this->_debug_mode = $debug;
			}
			
			$this->_debug_gear = array();
			if (array_key_exists('debuggear', $_GET)) {
					$tmplist = explode(',', $_GET['debuggear']);
					for($i=0;$i<count($tmplist);$i++) $this->_debug_gear[] = intval($tmplist[$i]);
			}
			
			# the timezone must be set before any processing (specifically, the time object)
			$tz = (ini_get('date.timezone') == '' ? 'America/New_York' : ini_get('date.timezone'));
			date_default_timezone_set($tz);
			
			$timer = new timer();
			$this->_debug('<strong>TIMER:</strong> Starting Initialization');
			$timer->start();
			
			# send initialization headers
			$this->_debug('Initializing elegant, modular applications engine @ ' . date('m/d/Y H:i:s'));
			$this->_debug('Version ' . $this->obj_version . ', ' . $this->_copyright);
			$this->_debug('Debug Level ' . $this->_debug_mode);
			$this->_debug('');
			
			# set default variables and initialize data access and security layers
			$this->init();
			
			set_error_handler('ema_error_handler');
			set_exception_handler('ema_exception_handler');
			
			$this->_tx_instance = 'ENGINE';
			
			$this->first_load_queue = false;
			
			# load base objects
			require_once($this->rootPath . '/base/0_standard_extension/standard_extension.class.php');
			require_once($this->rootPath . '/base/0_timer/timer.class.php');
			require_once($this->rootPath . '/base/0_unique_pair/unique_pair.class.php');
			require_once($this->rootPath . '/base/1_xml_object/xml_object.class.php');
			require_once($this->rootPath . '/base/2_xml_document/xml_document.class.php');
			require_once($this->rootPath . '/base/3_transmission/transmission.class.php');
			
			# set child object debug level
			#  - fyi this is where our "full" debug level of '104' comes from: 99 + 5
			$debug_mode = ($this->_debug_mode - 5);
			
			# connect to the transmission
			$this->_tx = new transmission(array('_debug_mode' => $debug_mode, '_function_level' => &$this->_function_level, '_debug_output' => $this->_debug_output, '_debug_match'=> $this->_debug_match));
			
			# load the transmission into the global scope
			$GLOBALS['t'] =& $this->_tx;
			
			# publish engine variables
			$this->_tx->_publish('_debug_match', $this->_debug_match);
			$this->_tx->_publish('_debug_mode', $debug_mode);
			$this->_tx->_publish('_debug_output', $this->_debug_output);
			$this->_tx->_publish('_function_level', $this->_function_level);
			$this->_tx->_publish('engine_root', $this->rootPath);
			$this->_tx->_publish('engine_version', $this->obj_version);
			$this->_tx->_publish('exec_time', $this->time);
			$this->_tx->_publish('gear', $this->gear); // read only
			$this->_tx->_publish('site_root', $this->absRootPath);
			$this->_tx->_publish('upload_root', $this->upload_root);
			$this->_tx->_publish('uri', $this->uri);
			
			# set restricted variables
			$this->_tx->_restrict(array('engine_root', 'engine_version', 'exec_time', 'gear', 'site_root', 'upload_root', 'uri'), 'engine');
			#$this->restricted_vars['userid'] = 'users';
			#$this->restricted_vars['password'] = '';              // password should never be published
			
			# manually register required base objects
			$this->_tx->_registerExtension('unique', 'pair', '1.2.0', './base/0_unique_pair/unique_pair.class.php');
			$this->_tx->_registerExtension('xml', 'object', '1.0.0', './base/1_xml_object/xml_object.class.php',
			                                array(), array('_tag'=>true,'_value'=>false));
			$this->_tx->_registerExtension('xml', 'document', '1.0.2', './base/2_xml_document/xml_document.class.php',
			                                array('xml_object'), array('_tag'=>false,'_value'=>false));
			$this->_tx->_setDefault('xml', 'document');
			
			# preload all built-in database extensions
			$this->_debug('Registering Database Extensions');
			#$this->register_extension($this->rootPath . '/built-in/', 'db_file');
			#$this->register_extension($this->rootPath . '/built-in/', 'db_mssql');
			$this->register_extension('./built-in/', 'db_mysql');
			#$this->register_extension($this->rootPath . '/built-in/', 'db_oracle');
			$this->_debug('Done.');
			
			# check for the configuration file
			$this->_debug('locating configuration file');
			if (! file_exists($this->configPath . 'config.xml')) {
				# configuration does not exist; run setup
				require ($this->displayPath . 'setup.php');
				exit;
			}
			
			$this->_debug('loading configuration');
			$this->_tx->xml_document->_load($this->configPath . 'config.xml');
			
			# now retrieve a list of installed extensions
			#$this->listExtensions($this->objectPath, $this->built_in);
			$this->listExtensions($this->extensionPath, $this->extensions);
			
			#$this->_debug('Built-in Raw List: ' . implode(',', $this->built_in));
			$this->built_in = array();
			$this->_debug('Extended Raw List: ' . implode(',', $this->extensions));
			
			# pre-validate extensions
		/*
			foreach ($this->built_in as $ext) {
				if (! $this->preValidateExtension($this->objectPath . $ext)) unset($this->built_in[$ext]);
			}
		*/
			foreach ($this->extensions as $ext) {
				if (! $this->preValidateExtension($this->extensionPath . $ext)) unset($this->extensions[$ext]);
			}
			
			#$this->_debug('Built-in Pre-validation: ' . implode(',', $this->built_in));
			$this->_debug('Extended Pre-validation: ' . implode(',', $this->extensions));
			
			# establish database connection
			switch ($this->_tx->xml_document->data_interface) {
				case 'file_db':
					$db =& $this->_tx->xml_document->file_db;
					die ('Error: support for the flat-file database is incomplete, terminating execution.');
					break;
				case 'mysql_db':
					$db =& $this->_tx->xml_document->mysql_db;
					$this->_tx->_preRegister('db', array('server'=>$db->address->_getValue(), 'database'=>$db->database->_getValue(),
																								'user'=>$db->username->_getValue(), 'password'=>$db->password->_getValue(),
																								'engine'=>$db->engine->_getValue(), 'charset'=>$db->charset->_getValue()));
					$this->_tx->_setDefault('db', 'mysql');
					break;
				default:
					die ('Error: unknown database source object, terminating execution.');
			}
			
			# check database version against object version
			if (! $this->checkDatabaseVersion()) {
				die ('<strong>Critical Error</strong>: database incompatible with this version of the Content Management System');
			}
			
			$this->_debug('registering extensions from database');
			
			# register extensions from the database
			$this->register_db_extensions();
			
			# load disabled extensions from the database
			$disabled_extensions = $this->_tx->db->query('modules', array('enabled'), array(false), array('name'), true);
			
			# remove all disabled extensions from the list(s)
			if (db_qcheck($disabled_extensions, true)) {
				foreach($disabled_extensions as $ext) {
					unset($this->extensions[$ext['name']]);
					unset($this->built_in[$ext['name']]);
				}
			}
			
			$this->_debug('registering built-in extensions');
			
			# add any remaining built-in extensions
			foreach ($this->built_in as $ext) { $this->register_extension($this->objectPath, $ext, false); }
			foreach ($this->built_in as $ext) { $this->register_extension($this->objectPath, $ext, true); }
			
			# preset extension load order
			$next_extension = 0;
			
			# preset database version variable
			$db_ver = false;
			
			# preset the maximum loop count
			$maximum_loops = (count($this->extensions) * 4);
			
			# preset iteration count to zero
			$iteration = 0;
			
			# preset pass to 0
			$pass = 0;
			
			# process database queue
			while ($ext = array_shift($this->extensions)) {
				# to prevent an endless loop, make sure we have not surpassed the maximum number of loops
				if ($iteration > $maximum_loops) break;
				if ($iteration > count($this->extensions)) $pass = 1;
				
				# increment the loop counter
				$iteration++;
				
				# add user extensions directly to the database as disabled
				#$this->_tx->db->insert('modules', array('name','module','enabled'), array($ext,'user',false));
				if ($pass == 0) { $commit = true; } else { $commit = false; }
				$this->register_extension($this->extensionPath, $ext, $commit);
			}
			
			if (is_array($this->first_load_queue)) {
				$this->_debug('FIRST_LOAD_QUEUE ORDER: ' . implode(', ', $this->first_load_queue));
				# since this is the first initialization, load this object to ensure its' schema is created
				foreach($this->first_load_queue as $n) {
					$this->_debug('FIRST_LOAD_QUEUE::' . $n);
					$this->_tx->$n;
				}
			}
			
			$this->_debug('<strong>TIMER:</strong> Finished Initialization');
			$timer->stop();
			$this->_debug('<strong>TIMER:</strong> Total INIT Time: ' . $timer->retrieve());
			$this->time = $timer->retrieve();
			$timer->reset();
			$timerTracker = array();
			
			# process gears
			for ($i=0;$i<=LAST_STATE;$i++) {
				if (in_array($i, $this->_debug_gear)) {
					$this->_debug_mode = 104;
					$this->_tx->_setDebug(104);
				}
				$this->_debug("<strong>TIMER:</strong> Processing GEAR $i...");
				$timer->start();
				$this->_gear_change_handler();
				$this->_debug("<strong>TIMER:</strong> Finished GEAR $i");
				$timer->stop();
				$this->_debug("<strong>TIMER:</strong> Total GEAR $i Time: " . $timer->retrieve());
				$timerTracker[$i] = $timer->retrieve();
				$this->time += $timer->retrieve();
				$timer->reset();
				if (in_array($i, $this->_debug_gear)) {
					$this->_debug_mode = -1;
					$this->_tx->_setDebug(-1);
				}
			}
			
			$this->_debug('<strong>TIMER:: TOTAL EXECUTION TIME:</strong> ' . $this->time);
			
			if ($this->_debug_mode >= 99) {
				# do some time analysis
				$shortest = $timerTracker[0];
				# protect against divide by zero (line 389 or thereabouts)
				if ($shortest == 0) $shortest = 1;
				$shortestGear = 0;
				$longest = $timerTracker[0];
				$longestGear = 0;
				$next_longest = $timerTracker[0];
				# protect against divide by zero (line 389 or thereabouts)
				if ($next_longest == 0) $next_longest = 1;
				$next_longestGear = 0;
				for ($i=1;$i<=LAST_STATE;$i++) {
					if ($timerTracker[$i] < 1) continue;
					if ($timerTracker[$i] < $shortest) {
						$shortest = $timerTracker[$i];
						$shortestGear = $i;
					}
					if ($timerTracker[$i] > $longest) {
						$next_longest = $longest;
						$next_longestGear = $longestGear;
						$longest = $timerTracker[$i];
						$longestGear = $i;
					}
				}
				# output results
				$this->_debug('<strong>TIME ANALYSIS:</strong>');
				$this->_debug("&nbsp;&nbsp;The fastest gear was #$shortestGear with an execution time of $shortest ms");
				$this->_debug("&nbsp;&nbsp;The slowest gear was #$longestGear with an execution time of $longest ms");
				$this->_debug("&nbsp;&nbsp;The next slowest gear was #$next_longestGear with an execution time of $next_longest ms");
				$this->_debug('&nbsp;&nbsp;---------------------------------------------------------------------------------------');
				$this->_debug("&nbsp;&nbsp;The slowest gear took " . number_format($longest/$next_longest, 2) . " times as long to run than the next slowest");
				$this->_debug("&nbsp;&nbsp;The fastest gear ran " . number_format($longest/$shortest, 2) . " times faster than the slowest");
			}
		}
		
		function __destruct() {
			global $_ema_debug_file_handle;
			if (@is_resource($_ema_debug_file_handle)) @fclose($_ema_debug_file_handle);
		}
		
		
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		// 	BASE FUNCTIONALITY
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		
		private function _debug_start($message = '', $increment = 1)
		/* enable debugging for the calling function
		 *
		 * optionally output $message if one is supplied
		 *
		 * standard function level increment is 1, unless one is specified
		 *
		 */
		{
			# enable php backtrace to get the name of the caller
			$dbg = debug_backtrace(false);
			
			$this->_debug('function ' . $dbg[1]['function'] . "($message) {");
			$this->_function_level = $this->_function_level + $increment;
		}
		
		private function _retByRef(&$value, $message = '', $increment = 1)
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
		
		private function _return($value, $message = '', $increment = 1)
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
		
		private function _gear_change_handler()
		/* Change the gear of the engine --
		 *	This function should be called by this object ONLY
		 *
		 */
		{
			$this->_debug('function _gear_change_handler() {');
			$this->_function_level++;
			
			# Change the gear (state)
			$this->gear++;
			
			$this->_debug('moving to gear ' . $this->gear);
			
			$functionList = $this->_tx->_getGears($this->gear);
			
			$this->_debug('loaded ' . count($functionList) . ' callbacks');
			
			# prevent duplicates (need to track down where those are being added...)
			$dupes = array();
			
			# Run the registered functions for this gear
			foreach($functionList as $item) {
				if (in_array($item['object'] . '::' . $item['function'], $dupes)) continue;
				$dupes[] = $item['object'] . '::' . $item['function'];
				$this->_debug('Calling gear function ' . $item['object'] . '::' . $item['function']);
				$o =& $this->_tx->$item['object'];
				if (! is_object($o)) {
					$this->_debug('Error loading dynamic object `' . $item['object'] . '`, bypassing it.');
					die('Error loading dynamic object `' . $item['object'] . '`; Please contact the object developer.');
					continue;
				}
				if (in_array($this->gear, $this->_debug_gear)) {
					$geartimer = new timer();
					$geartimer->start();
					$this->_debug('<strong>timing function execution</strong>');
				}
				call_user_func(array($o, $item['function']));
				if (in_array($this->gear, $this->_debug_gear)) {
					$geartimer->stop();
					$this->_debug('<strong>total exec time <em>' . $geartimer->retrieve() . '</em></strong>');
				}
			}
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		private function checkDatabaseVersion()
		/* compare the database version with the engine version
		 * 	if a mis-match is found, upgrade the database
		 *
		 */
		{
			$this->_debug('function checkDatabaseVersion() {');
			$this->_function_level++;
			
			# make sure the database is connected
			if (! $this->_tx->db) {
				$this->_debug('Error: no database is loaded');
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			$module = 'engine'; // this is used during the upgrade (below)
			$db_detail = $this->_tx->db->query('master_config', array('cms-option'), array('db_version'), array('option-value'));
			$dbkey = 'option-value';
			
			# check results
			if (! db_qcheck($db_detail)) {
				$this->_debug('Error: unable to retrieve version from database');
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			# set the version to compare to
			$counter_check =& $this->db_version;
			
			# compare versions
			switch (version_compare($counter_check, $db_detail[0][$dbkey])) {
				case -1:
					$this->_debug('Error: the database is newer than the object!');
					$this->_debug('}');
					$this->_function_level--;
					return false;
					break;
				case 0:
					$this->_debug('The versions match');
					$this->_debug('}');
					$this->_function_level--;
					return true;
					break;
				case 1:
					$this->_debug('The database is out of date and needs to be updated (' . $db_detail[0][$dbkey] . ')');
					break;
			}
			
			# if script execution gets to this point in the function, the database needs to be updated
			while (version_compare($counter_check, $db_detail[0][$dbkey], '!=')) {
				# run an update script, perhaps based on the source db version #...
				
				/* run script here */
				
				# set the path to the database upgrade schema file
				$upgrade_schema_path = $this->queryPath . 'setup/upgrade.xml';
				
				if (! $this->_tx->xml_document->_load($upgrade_schema_path)) {
					$this->_debug('Error: unable to load database upgrade definition.');
					$this->_debug('}');
					$this->_function_level--;
					return false;
				}
				
				$all = $this->_tx->xml_document->_getChildren();
				$def = null;
				$new_version = null;
				
				foreach($all as $option) {
					if ($option->_getName() == $module . ':' . $db_detail[0][$dbkey]) {
						$def = $option->_getChildren();
						$new_version = $option->_getAttribute('next');
						break;
					} else {
						$this->_debug('Ignoring version mismatch: ' . $option->_getName() . ', looking for ' . $module . ':' . $db_detail[0][$dbkey]);
					}
				}
				
				# make sure we found a version to upgrade to
				if (is_null($def)) {
					return $this->_return(false, 'Error: Unable to locate a schema upgrade for this extension version.
					  You may be running an unauthorized revision or there may be a problem with the extension registry.');
				}
				
				foreach($def as $table) {
					if ($table->_getName() == 'create') {
						$list = $table->_getChildren();
						foreach ($list as $table) {
							# create the table
							$this->_debug('adding table "' . $table->_getName() . '"');
							$this->_tx->db->create($table->_getName(), $table);
						}
					} elseif ($table->_getName() == 'drop') {
						$list = $table->_getChildren();
						foreach ($list as $table) {
							# drop the table
							$this->_debug('dropping table "' . $table->_getName() . '"');
							if (! $this->_tx->db->drop($table->_getName())) {
								//return $this->_return(false, 'Error dropping table ' . $table->_getName());
							}
						}
					} elseif ($table->_getName() == 'insert') {
						$list = $table->_getChildren();
						foreach ($list as $table) {
							$this->_debug('inserting data into table "' . $table->_getName() . '"');
							if (! $this->_tx->db->insert($table->_getName(), $table)) {
								return $this->_return(false, 'Error inserting data for table ' . $table->_getName());
							}
						}
					} elseif ($table->_getName() == 'update') {
							$list = $table->_getChildren();
							foreach ($list as $table) {
								$this->_debug('updating data in table "' . $table->_getName() . '"');
								if (! $this->_tx->db->update($table->_getName(), $table)) {
									return $this->_return(false, 'Error updating data for table ' . $table->_getName());
								}
							}
					} elseif ($table->_getName() == 'delete') {
						$list = $table->_getChildren();
						foreach ($list as $table) {
							$this->_debug('delete data from table "' . $table->_getName() . '"');
							if (! $this->_tx->db->delete($table->_getName(), $table)) {
								return $this->_return(false, 'Error delete data from table ' . $table->_getName());
							}
						}
					} else {
						# standard alter existing table
						if (! $this->_tx->db->alter($table->_getName(),$table)) {
							die('Error updating table ' . $table->_getName());
						}
					}
				}
				
				# update the database version && check the new version for the next loop
				$this->_tx->db->update('master_config', array('cms-option'), array('db_version'), array('option-value'), array($new_version));
				$this->_tx->db->update('master_config', array('cms-option'), array('db_updated'), array('option-value'), array(date('Y-m-d H:i:s')));
				# query the new database to check the version (this will allow for incremental updates)
				$db_detail = $this->_tx->db->query('master_config', array('cms-option'), array('db_version'), array('option-value'));
				$dbkey = 'option-value';
				
				# make sure there was not an error (to prevent an endless loop)
				if (! db_qcheck($db_detail)) die('Critical error during automated database update');
			}
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		private function init()
		/* initialize the engine
		 *
		 */
		{
			$this->_debug_start();
			
			# system settings
			@date_default_timezone_set('America/New_York');
			@set_magic_quotes_runtime(0);
			
			# set path constants
			$this->_debug('Setting tier 1 constants...');
		
			# set application data root path
			$this->_debug('Discovering document root path... ');
			$tmpArr = explode('/', dirname(__FILE__));
			for ($i = 0; $i < (count($tmpArr) - 1); $i++) {
				$this->absRootPath .= $tmpArr[$i] . '/';
			}
			
			$this->_debug('$absRootPath = ' . $this->absRootPath);
			
			$this->rootPath = $this->absRootPath . $tmpArr[$i] . '/';
			$this->_debug('$rootPath = ' . $this->rootPath);
			
			$this->displayPath = $this->rootPath . 'display/';
			$this->_debug('$displayPath = ' . $this->displayPath);
			
			$this->objectPath = './built-in/';
			$this->_debug('$objectPath = ' . $this->objectPath);
			
			$this->extensionPath = './extensions/';
			$this->_debug('$extensionPath = ' . $this->extensionPath);
			
			$this->queryPath = $this->rootPath . 'query/';
			$this->_debug('$queryPath = ' . $this->queryPath);
			
			$this->resourcePath = $this->rootPath . 'resources/';
			$this->_debug('$resourcePath = ' . $this->resourcePath);
			
			# set tier 2 path constants (these depend on tier 1 constants)
			$this->_debug('Setting tier 2 constants...');
			
			$this->configPath = $this->resourcePath . 'configuration/';
			$this->_debug('$configPath = ' . $this->configPath);
			
			$this->functionPath = $this->resourcePath . 'shared/';
			$this->_debug('$functionPath = ' . $this->functionPath);
			
			$this->upload_root = $this->resourcePath . 'data/';
			$this->_debug('$upload_root = ' . $this->upload_root);
			
			# set tier 3 configurable variables
			$this->_debug('Setting tier 3 variables...');
			
			$this->applicationFile = $this->configPath . 'application.data';
			$this->_debug('$applicationFile = ' . $this->applicationFile);
			
			# set URI
			$request_tmp = explode('/', $_SERVER['REQUEST_URI']);
			$server_tmp = explode('/', $_SERVER['PHP_SELF']);
			$uri = '/';
			$p = 0;
			unset($server_tmp[count($server_tmp) - 1]);
			for ($i=0;$i<count($server_tmp);$i++) {
				for ($j=$p;$j<count($request_tmp);$j++) {
					if ($server_tmp[$i] == '') continue;
					if ($server_tmp[$i] == $request_tmp[$j]) {
						$uri .= $server_tmp[$i] . "/";
						$p = $j;
					}
				}
			}
			if ($uri != '//') $uri = substr($uri, 0, strlen($uri) - 1);
			$this->uri = $uri;
			
			# initialize gear registry
			$this->gear_registry = array();
			$this->gear_registry[STATE_INITIALIZE] = array();
			$this->gear_registry[STATE_PREOUTPUT] = array();
			$this->gear_registry[STATE_HEAD] = array();
			$this->gear_registry[STATE_BODY] = array();
			$this->gear_registry[STATE_POSTOUTPUT] = array();
			$this->gear_registry[STATE_UNLOAD] = array();	
			
			# load shared libraries
			require_once ($this->functionPath . 'lib_array.php');
			require_once ($this->functionPath . 'lib_ema.php');
			require_once ($this->functionPath . 'lib_xml.php');
			require_once ($this->functionPath . 'rfc822.php');
			
			return $this->_return(true);
		}
		
		private function listExtensions ($path, &$result)
		/* return an array of extensions at $path
		 *
		 *	code based on sample at http://www.php.net/readdir
		 *
		 */
		{
			$this->_debug('function listExtensions ($path=' . $path . ') {');
			$this->_function_level++;
			
			$result = array();
			
			$path = $this->rootPath . $path;
			
			if ($handle = @opendir($path)) {
				while (false !== ($file = readdir($handle))) {
					$this->_debug('checking file ' . $file);
					# ignore hidden files
					if ( (substr($file, 0, 1) != '.') && (is_dir($path . $file)) ) {
						$result[$file] = $file;
					}
				}
				closedir($handle);
			} else {
				$this->_debug('Error: unable to open path');
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			ksort($result);
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		private function refresh_extension($path, $name)
		/* refresh an extension's module table entry (except db schema info)
		 *
		 */
		{
			$this->_debug('function refresh_extension() {');
			$this->_function_level++;
			
			$xmli =& $this->_tx->xml_document;
			
			# for the refresh function the object name is already included in the path
			#  but we need to strip it out to maintain code compatibility with the register_extension function
			$tmp = explode('/', $path);
			$c = count($tmp);
			unset($tmp[$c - 1], $tmp[$c - 2]);
			$path = implode('/', $tmp) . '/';
			
			# load the extension data file
			if (! $xmli->_load($path . $name . '/extension.xml')) {
				$this->_debug("Error: unable to load extension '$name' at $path$name/extension.xml");
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			$this->_debug($xmli->object_name . ' was loaded (provides: ' .
			              $xmli->provides . ', version: ' . $xmli->version . ')');
			
			# update complete path to object file
			$path .= $xmli->object_file . '/' . $xmli->object_file . '.class.php';
			
			# preset requires
			$requires = array();
			if ($xmli->requires->_countChildren() > 0) {
				$temp = $xmli->requires->_getChildren();
				foreach($temp as $child) { $requires[] = $child->_getTag(); }
			}
			
			# preset embeddable functions
			$filament = array();
			if ( $xmli->_getChild('filament') && ($xmli->filament->_countChildren() > 0) ) {
				$temp = $xmli->filament->_getChildren();
				foreach($temp as $child) { $filament[] = $child->_getTag(); }
			}
			
			# preset interactive functions
			$fuse = array();
			if ( $xmli->_getChild('fuse') && ($xmli->fuse->_countChildren() > 0) ) {
				$temp = $xmli->fuse->_getChildren();
				foreach($temp as $child) {
					if ($child->_getAttribute('content_type')) { $ct = $child->_getAttribute('content_type'); } else { $ct = 'html'; }
					$fuse[] = array('path'=>$child->_getValue(), 'provides'=>$xmli->provides->_getValue(), 'type'=>$xmli->type->_getValue(),
						'function'=>$child->_getTag(), 'title'=>$child->_getAttribute('title'), 'content_type'=>$ct);
				}
			}
			
			# preset arguments
			$arguments = array();
			if ( $xmli->_getChild('arguments') && ($xmli->arguments->_countChildren() > 0) ) {
				$temp = $xmli->arguments->_getChildren();
				foreach($temp as $child) {
					$arguments[$child->_getTag()] = 
						$child->_getValue() . ',' . 
						$child->_getAttribute('type') . ',' . 
						$child->_getAttribute('model');
				}
			}
			
			# preset gears
			$gears = false;
			if ($xmli->_getChild('gears')) {
				if ($xmli->gears->_getValue()) $gears = true;
			}
			
			# add this extension to the registry
			$this->_tx->_registerExtension(
				$xmli->provides,
				$xmli->type,
				$xmli->version,
				$path,
				$requires,
				$arguments,
				$filament,
				$fuse,
				$gears);
			
			# update the modules table
			$module_fields = array('provides','type','requires',
				'filament_array','fuse_array','arguments','gears','refresh');
			
			$updateArr = array(
				$xmli->provides,          // provides
				$xmli->type,              // type
				serialize($requires),     // requires
				serialize($filament),     // filaments
				serialize($fuse),         // fuses
				serialize($arguments),    // arguments
				$gears,                   // gears
				false                     // refresh
				);
			
			$this->_tx->db->update('modules', array('name'), array($xmli->object_name), $module_fields, $updateArr);
			
			if (! is_array($this->first_load_queue)) $this->first_load_queue = array();
			
			# make sure any objects we require are in the queue ahead of us
			$max = 0;
			for ($i=0;$i<count($requires);$i++) {
				$p = array_search($requires[$i], $this->first_load_queue);
				if ($p === false) {
					array_unshift($this->first_load_queue, $requires[$i]);
				} elseif ($p > $max) {
					$max = $p;
				}
			}
				
			$me = array_search($xmli->provides . '_' . $xmli->type, $this->first_load_queue);
			$my_name = $xmli->provides . '_' . $xmli->type;
			if ($me === false) {
				array_push($this->first_load_queue, $my_name);
			} else {
				if ($me < $max) {
					unset($this->first_load_queue[$me]);
					$new = array();
					foreach($this->first_load_queue as $k=>$v) {
						$new[] = $v;
						if ($k == $max) $new[] = $my_name;
					}
					$this->first_load_queue = $new;
				}
			}
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		private function register_db_extensions()
		/* register all modules loaded in the database
		 *
		 */
		{
			$module_fields = array('name','module','enabled','module_version','schema_version',
				'provides','type','path','requires','filament_array','fuse_array','arguments','gears','refresh');
			
			$list = $this->_tx->db->query('modules', array('enabled'), array(true), $module_fields, true, array('load_order'));
			
			# stop here if there were no results
			if (! db_qcheck($list, true)) return true;
			
			# first load queue has to be prepped here since we are no longer processing built-in modules
			#  during each page view
			if (! is_array($this->first_load_queue)) $this->first_load_queue = array();
			
			foreach($list as $module) {
				# set the working extension list array
				if ($module['module'] == 'engine') {
					$arr =& $this->built_in;
					# add to first load queue now to ensure proper ordering of pre-requisites
					$this->first_load_queue[] = $module['name'];
					$this->first_load_queue[] = $module['provides'];
				} else {
					$arr =& $this->extensions;
				}
				
				# remove the database registered module from the extension list
				$this->_debug('removing module ' . $module['name'] . ' from the extension list');
				unset($arr[$module['name']]);
				
				# check if a refresh was requested on this module
				if ($module['refresh']) {
					$this->refresh_extension($this->rootPath . $module['path'], $module['name']);
				} else {
					# restore the serialized arrays
					$arguments = unserialize($module['arguments']);
					$filament = unserialize($module['filament_array']);
					$fuse = unserialize($module['fuse_array']);
					$requires = unserialize($module['requires']);
					
					# register the module with the transmission
					$this->_tx->_registerExtension(
						$module['provides'],
						$module['type'],
						$module['module_version'],
						$module['path'],
						$requires,
						$arguments,
						$filament,
						$fuse,
						$module['gears'],
						$module['schema_version']
						);
				}
			}
			
			return true;
		}
		
		private function register_extension($path, $name, $add_to_database = false)
		/* register an approved extension
		 *
		 */
		{
			$this->_debug('function register_extension() {');
			$this->_function_level++;
			
			$xmli =& $this->_tx->xml_document;
			
			# load the extension data file
			if (! $xmli->_load($this->rootPath . $path . $name . '/extension.xml')) {
				$this->_debug("Error: unable to load extension '$name' at $path$name/extension.xml");
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			$this->_debug($xmli->object_name . ' was registered (provides: ' .
			              $xmli->provides . ', version: ' . $xmli->version . ')');
			
			# update complete path to object file
			$path .= $xmli->object_file . '/' . $xmli->object_file . '.class.php';
			
			# preset schema version
			$schema_version = '0';
			
			# preset requires
			$requires = array();
			if ($xmli->requires->_countChildren() > 0) {
				$temp = $xmli->requires->_getChildren();
				foreach($temp as $child) { $requires[] = $child->_getTag(); }
			}
			
			# preset embeddable functions
			$filament = array();
			if ( $xmli->_getChild('filament') && ($xmli->filament->_countChildren() > 0) ) {
				$temp = $xmli->filament->_getChildren();
				foreach($temp as $child) { $filament[] = $child->_getTag(); }
			}
			
			# preset interactive functions
			$fuse = array();
			if ( $xmli->_getChild('fuse') && ($xmli->fuse->_countChildren() > 0) ) {
				$temp = $xmli->fuse->_getChildren();
				foreach($temp as $child) {
					if ($child->_getAttribute('content_type')) { $ct = $child->_getAttribute('content_type'); } else { $ct = 'html'; }
					$fuse[] = array('path'=>$child->_getValue(), 'provides'=>$xmli->provides->_getValue(), 'type'=>$xmli->type->_getValue(),
						'function'=>$child->_getTag(), 'title'=>$child->_getAttribute('title'), 'content_type'=>$ct);
				}
			}
			
			# preset arguments
			$arguments = array();
			if ( $xmli->_getChild('arguments') && ($xmli->arguments->_countChildren() > 0) ) {
				$temp = $xmli->arguments->_getChildren();
				foreach($temp as $child) {
					$arguments[$child->_getTag()] = 
						$child->_getValue() . ',' . 
						$child->_getAttribute('type') . ',' . 
						$child->_getAttribute('model');
				}
			}
			
			# preset gears
			$gears = false;
			if ($xmli->_getChild('gears')) {
				if ($xmli->gears->_getValue() == "true") $gears = true;
			}
			
			if (strpos($path, 'built-in') === false) {
				$module = 'user';
				$enabled = false;
			} else {
				$module = 'engine';
				$enabled = true;
			}
			
			if ($enabled) {
				# add this extension to the registry
				$this->_tx->_registerExtension(
					$xmli->provides,
					$xmli->type,
					'0',
					$path,
					$requires,
					$arguments,
					$filament,
					$fuse,
					$gears,
					$schema_version);
			}
			
			# optionally insert into the modules table too
			if ($add_to_database) {
				$module_fields = array(
				    'name','module','enabled','module_version','schema_version','provides','type','path','requires',
				    'filament_array','fuse_array','arguments','gears','load_order');
				
				# try to find this object in the load queue to determine the load order
				$pos = array_search($xmli->object_name, $this->first_load_queue);
				if ($pos === false) {
					# if the exact name was not found, check on the extension group instead
					$pos = array_search($xmli->provides, $this->first_load_queue);
				}
				# if there is still no position, set to last
				if ($pos === false) { $pos = count($this->first_load_queue); }
				
				# user extensions always come after engine (built-in) extensions
				if ($module == 'user') { $pos *= 10; }
				
				$loadArr = array(
					$xmli->object_name,       // name
					$module,                  // module
					$enabled,                 // enabled
					'0',                      // module_version
					'0',                      // schema_version
					$xmli->provides,          // provides
					$xmli->type,              // type
					$path,                    // path
					serialize($requires),     // requires
					serialize($filament),     // filaments
					serialize($fuse),         // fuses
					serialize($arguments),    // arguments
					$gears,                   // gears
					$pos                      // load order
					);
				
				$this->_tx->db->delete('modules', array('name'), array($xmli->object_name));
				$this->_tx->db->insert('modules', $module_fields, $loadArr);
			}
			
			if (! is_array($this->first_load_queue)) $this->first_load_queue = array();
			
			# make sure any objects we require are in the load queue ahead of us
			$my_name = $xmli->provides . '_' . $xmli->type;
			$me = array_search($my_name, $this->first_load_queue);
			if ($me === false) {
				for ($i=0;$i<count($requires);$i++) {
					$p = array_search($requires[$i], $this->first_load_queue);
					if ($p === false) { array_push($this->first_load_queue, $requires[$i]); }
				}
				array_push($this->first_load_queue, $my_name);
			} else {
				$max = 0;
				for ($i=0;$i<count($requires);$i++) {
					$p = array_search($requires[$i], $this->first_load_queue);
					if ($p === false) {
						array_push($this->first_load_queue, $requires[$i]);
						$max = count($this->first_load_queue) - 1;
					} elseif ($p > $max) {
						$max = $p;
					}
				}
				if ($me < $max) {
					unset($this->first_load_queue[$me]);
					$new = array();
					foreach($this->first_load_queue as $k=>$v) {
						$new[] = $v;
						if ($k == $max) $new[] = $my_name;
					}
					$this->first_load_queue = $new;
				}
			}
			
			$this->_debug('<strong>AFTER ' . $xmli->provides . '_' . $xmli->type . '::FIRST_LOAD_QUEUE ORDER</strong>: ' .
				implode(', ', $this->first_load_queue));
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		private function preValidateExtension($ext)
		/* Given the path to an extension, check if it is valid
		 *	Returns TRUE if the extension is valid, FALSE if it is not
		 *
		 */
		{
			$this->_debug('function preValidateExtension($ext) {');
			$this->_function_level++;
			
			# make sure extension configuration file exists
			if (! file_exists($this->rootPath . $ext . '/extension.xml')) {
				$this->_debug('Error: extension definition not found');
				$this->_debug('}');
				$this->_function_level--;
				return false;
			}
			
			$this->_debug('}');
			$this->_function_level--;
			return true;
		}
		
		
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		//  INTERNAL SUPPORT FUNCTIONS
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		
		
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
			if ( ($this->_debug_mode >= $this->_function_level)) {
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
		
		private function selfURL() 
		/* get the current browser URL
		 *
		 * this function copyright the author of dev.kanngard.net
		 * retrieved from http://dev.kanngard.net/Permalinks/ID_20050507183447.html on apr-14-2007
		 *
		 */
		{ 
			$s = empty($_SERVER["HTTPS"]) ? '' 
						: ($_SERVER["HTTPS"] == "on") ? "s" 
						: "";
			$protocol = $this->strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
			$port = ($_SERVER["SERVER_PORT"] == "80") ? "" 
						: (":".$_SERVER["SERVER_PORT"]); 
			return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI']; 
		}
		
		private function strleft($s1, $s2) 
		/* return the left portion of string s1
		 *
		 * this function copyright the author of dev.kanngard.net
		 * retrieved from http://dev.kanngard.net/Permalinks/ID_20050507183447.html on apr-14-2007
		 *
		 */
		{ 
			return substr($s1, 0, strpos($s1, $s2));
		}
		
	} // engine
	
?>