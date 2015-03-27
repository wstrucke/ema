<?php
 /* EMA Account (Authentication) Interface for the Security System
  * Copyright 2008-2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Oct-26-2008/-/Nov-03-2008/Apr-18-2011/Aug-30-2011
  * William Strucke, wstrucke@gmail.com
  *
  */
  
abstract class user_group extends debugger
{
  # public / directly accessible variables
  public $group_loaded;            // (bool) true if a group is loaded
  public $user_loaded;             // (bool) true if a user account is loaded (long name)
  
  # object uuid for the security system, to be set after the configuration is applied
  protected $uuid;                 // (string) a unique identifier for the configured object
                                   //            such that every instance of the object will
                                   //            automatically generate the same uuid provided
                                   //            it has the same configuration.
                                   //          this should ensure that the same object instantiated
                                   //            twice with different configurations have seperate
                                   //            idenitifiers for the security system.
                                   //          maximum length 255 characters.
  
  # variables to be shared on demand
  protected $group_guid;           // (string) the loaded group globally unique id (string or numeric)
  protected $group_id;             // (string) the loaded group id or false (short name not id number)
  protected $group_name;           // (string) the loaded group name (long name)
  protected $user_guid;            // (string) the loaded user globally unique id (string or numeric)
  protected $user_id;              // (string) the loaded user id or false (short name not id number)
  protected $user_name;            // (string) the loaded user name
  
  # internal variables
  protected $false;                // (bool) false for functions that return by reference
  protected $group_data;           // (array) loaded group data for stateful requests
  protected $group_restricted_fields;
                                   // (array) group fields that should never be modified by the client
  protected $password_entropy = 1000;
                                   // the number of times the sha512 & md5 algorithms are run
                                   //   to generate new passwords, increase for more entropy.
                                   // if this number is changed all passwords will have to
                                   //   be reset immediately since it will invalidate them.
  protected $user_allow_empty_passwords;
                                   // (bool) true if empty passwords are allowed (default false)
  protected $user_data;            // (array) loaded user data for stateful requests
  protected $user_restricted_fields;
                                   // (array) user fields that should never be modified by the client
  protected $var_mode;             // internal variable to track magic variable requests
  
  # attribute cache
  protected $group_cache;          // (array) for internal use to check if attribute values were changed
  protected $user_cache;           // (array) for internal use to check if attribute values were changed
  
  protected $_copyright = 'Copyright &copy; 2004-2011 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
  protected $_debug_color;         // selected debug output color for prefix
  protected $_debug_match = '';   // optional comma seperated list of debug_prefixes to match when enabling debugging
  protected $_debug_mode = -1;     // enable debugging mode
  protected $_debug_output = 2;    // debug output mode: 0=disabled, 2=inband/html,4=out of band/file,6=screen+file
  protected $_debug_prefix = '';   // object debug output prefix
  protected $_function_level = 0;  // to count debug indentation for formatting
  protected $_in_loop = false;     // set to true to disable debug output in a nested loop
  protected $_module_id;           // (string) the module id provided by the security system after instantiation
  public $_myloc;                  // server directory path to the instantiated object set by transmission
  public $_name;                   // the loaded object's name
  protected $_tx;                  // transmission object reference
  public $_tx_instance;            // transmission object's instance number for this element
  public $_version;                // the loaded object's version string
  
  /* code */
  
  public function __clone()
  /* handle clone processes
   *
   */
  {
  	global $t;
  	$this->_tx = $t;
  	return true;
  }
  
  public function __construct(transmission &$transmission_obj, $args = array())
  {
  	# set arguments
  	foreach($args as $key=>&$value) {
  		if (is_object($value) || is_array($value)) {
  			$this->{$key} =& $value;
  		} else {
  			$this->{$key} = $value;
  		}
  	}
  	
  	# check if debugging is disabled
  	if ( (property_exists($this, '_no_debug')) && ($this->_no_debug) ) $this->_debug_mode = -1;
  	
  	# send initialization headers
  	$this->_debug("initializing $this->_debug_prefix @ " . date('m/d/Y H:i:s'));
  	$this->_debug($this->_name . ' version ' . $this->_version . ', ' . $this->_copyright);
  	$this->_debug('debug level ' . $this->_debug_mode);
  	$this->_debug('');
  	
  	# connect to transmission
  	$this->_tx =& $transmission_obj;
  	
  	# optionally execute child object initialization method
  	if (method_exists($this, '_construct')) return $this->_construct();
  	
  	return true;
  }
  
  public function &__get($item)
  {
  	$this->_debug("GET:$item");
  	# for dynamic function calling, disallow variables named user and group
  	if (($item === 'user')||($item==='group')) $this->var_mode = false;
  	# check if this is a nested variable request
		switch ($this->var_mode) {
			case 'group':
				$this->var_mode = false;
				if (array_key_exists($item, $this->group_data)) { return $this->group_data[$item]; } else { return $this->false; }
				break;
			case 'user':
				$this->var_mode = false;
				if (array_key_exists($item, $this->user_data)) { return $this->user_data[$item]; } else { return $this->false; }
				break;
			default:
				# set the request
				if (strtolower($item) == 'group') {
					$this->var_mode = 'group';
					return $this;
				} elseif (strtolower($item) == 'user') {
					$this->var_mode = 'user';
					return $this;
				} else {
					$this->_debug('INVALID Var Mode `' . $item . '`');
					return $this->false;
				}
				break;
		}
  }

  public function __set($field, $value)
  {
  	# check if this is a nested variable request
  	if ($this->var_mode !== false) {
  		switch ($this->var_mode) {
  			case 'group':
  				if (! is_array($this->group_data)) $this->group_data = array();
  				$this->group_data[$field] = $value;
  				break;
  			case 'user':
  				if (! is_array($this->user_data)) $this->user_data = array();
  				$this->user_data[$field] = $value;
  				break;
  			default: $this->_debug('Error: invalid nested type request'); break;
  		}
  		# reset var mode
  		$this->var_mode = false;
  	}
  }
  
  public function _content($name='')
  /* includes specified content file (if it exists) in the scope of this object
   *
   * returns the content or false on an error
   *
   */
  {
  	# bring global transmission link into scope to allow for multifunction fuse
  	#  and automatically linked content
  	global $t;
  	if (strlen($name)==0) $name='index';
  	if (@file_exists($this->_myloc . "content/$name.php")) {
  		return buffer($this->_myloc . "content/$name.php");
  	}
  	return false;
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
		if ( ($this->_debug_mode >= $this->_function_level) && ($this->_in_loop == false) ) {
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
  
  public function _debug_off()
  /* temporarily disable debugging for this object
   *
   */
  {
  	$this->_debug_mode = -1;
  	return true;
  }
  
  public function _debug_on($mode = 99, $device = 2)
	/* temporarily enable debugging for this object
	 *
	 */
	{
		if ($mode < 0) $mode = 99;
		$this->_debug_mode = $mode;
		$this->_debug_output = $device;
		return true;
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
  	$dbg = debug_backtrace(false);
  	
  	$this->_debug('function ' . $dbg[1]['function'] . "($message) {");
  	$this->_function_level = $this->_function_level + $increment;
  }
  
  protected function _has($extension)
  /* alias for transmission _check function in the scope of this object
   *
   */
  {
  	return $this->_tx->_check($extension);
  }
  
  public function _install()
  /* run any code to install the class
   *
   * this function will be executed during version changes
   *  so it shouldn't do any one time actions without
   *  checking the validity of those actions first
   *
   */
  {
  	$this->_debug('Installing');
  	# bring global transmission link into scope to allow for multifunction fuse
  	#  and automatically linked content
  	global $t;
  	if (@file_exists($this->_myloc . "install.php")) {
  		# validate cms link
  		if ((get_class($this)!='cms_manager')&&(!is_object($t->__get('cms')))&&(!is_object($t->cms))) { die('CMS Module is inaccessible in ' . get_class($this)); }
  		$this->_debug('Running installation script for this module');
  		include_once($this->_myloc . "install.php");
  	}
  	return true;
  }
  
  protected function _option($name)
  /* get or set the specified local option
   *
   * this function supports a variable number of arguments
   * 
   * $name is required; it can be either a string to retrieve (or set)
   *  one value or an array of strings for the same purpose
   *
   * argument 2 is an optional value to set the variable to
   * argument 3 allows the scope to be specified
   *
   * if the scope is not specified, the transmission will decide based
   *  on the context of the request
   *
   * this function will always return the current value (even if
   *  setting a value fails)
   *
   * if a single (string) option is set/requested a single value
   *  will be returned
   *
   * if an array of options is set/requested, an array is returned
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
  	$args = array('module'=>get_class($this), 'option'=>$name);
  	$num = func_num_args();
  	if ($num > 1) $args['value'] = func_get_arg(1);
  	if ($num > 2) $args['scope'] = func_get_arg(2);
  	return $this->_tx->_moduleOption($args);
  }
  
  protected function &_retByRef(&$value, $message = '', $increment = 1)
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
  
  public function _xml($_xml_file='')
  /* includes specified xml file (if it exists) in the scope of this object
   *
   * returns true if the content was included successfully, false on an error
   *
   */
  {
  	# bring global transmission link into scope to allow for multifunction fuse
  	#  and automatically linked content
  	global $t;
  	if (strlen($_xml_file)==0) return false;
  	if (func_num_args() > 1) {
  		$_xml_args = func_get_args();
  		array_shift($_xml_args);
  	} else {
  		$_xml_args = array();
  	}
  	if (@file_exists($this->_myloc . "xml/$_xml_file.php")) {
  		# process args
  		foreach($_xml_args as $_arg=>$_val) { ${$_arg}=$_val; }
  		include($this->_myloc . "xml/$_xml_file.php");
  		return true;
  	}
  	return false;
  }
  
  protected function _build_cache(string $account_type)
  /* build the cache of local variables and values
   *  to enable error checking during updates
   *
   * the cache is used to verify that certain attributes are not altered improperly
   *
   */
  {
  	$this->_debug_start();
  	
  	# validate account type
  	if (!in_array($account_type, array('user','group'))) return $this->_return(false, 'invalid account type');
  	
  	# enumerate the valid fields for this account type
  	$list = $this->{$account_type}->get_fields();
  	
  	# load the data in to the cache
  	foreach($list as $var) { $this->{$account_type . '_cache'}[$var] = $this->{$account_type . '_data'}[$var]; }
  	
  	return $this->_return(true, 'cache successfully updated');
  }
  
  protected function _check_load_state(string $account_type)
  /* this function is used internally to make sure something is loaded
   *
   */
  {
  	$this->_debug_start();
  	
  	# validate account type
  	if (!in_array($account_type, array('user','group'))) return $this->_return(false, 'invalid account type');
  	
  	# check if the account is loaded
  	if (!$this->{$account_type . '_loaded'}) return $this->_return(false, 'error: no account loaded.');
  	
  	return $this->_return(true);
  }
  
  public function _construct()
  /* init the object
   *
   */
  {
  	$this->group_restricted_fields = array();
  	$this->user_allow_empty_passwords = false;
  	$this->user_restricted_fields = array();
  	$this->var_mode = false;
  	
  	# clear both sides of the object
  	$this->group->clear();
  	$this->user->clear();
  	
  	# generate the object uuid
  	$this->_generate_uuid();
  }
  
  abstract protected function _generate_uuid();
  /* contract for _generate_uuid: generate the unique identifier for this instance of this object
   *
   * required:
   *   N/A
   *
   * optional:
   *   N/A
   *
   * returns:
   *   sets a string of length 1 to 255 characters
   *
   * implementation notes:
   *   see the notes under the uuid variable declaration at the top of
   *   this object
   *
   */
  
  public function _uuid()
  /* return the generated uuid or false on error
   *
   */
  {
  	if (is_null($this->uuid)||($this->uuid === false)) $this->_generate_uuid();
  	if (is_string($this->uuid)&&(strlen($this->uuid)>0)&&(strlen($this->uuid)<256)) return $this->uuid;
  	return false;
  }
  
  protected function _verify_cache($account_type)
  /* verify fixed object attributes with the cached attributes
   *  to ensure they are not adjusted improperly
   *
   * this function will return true if all fixed attributes have *not* changed or
   *  false if they have (meaning running an update could potentially corrupt the directory)
   *
   */
  {
  	$this->_debug_start();
  	
  	# validate account type
  	if (!in_array($account_type, array('user','group'))) return $this->_return(false, 'invalid account type');
  	
  	# preset the data integrity variable to true
  	$integrity_checks_out = true;
  	$this->_debug('preset integrity check... true');
  	
  	foreach($this->{$account_type . '_restricted_fields'} as $var) {
  		if ($this->{$account_type . '_data'}[$var] != $this->{$account_type . '_cache'}[$var]) {
  			$this->_debug("<strong>Warning:</strong> restricted variable $var was modified. Canceling updates!");
  			$integrity_checks_out = false;
  		}
  	}
  	
  	if (!$integrity_checks_out) return $this->_return(false, 'integrity check failed!');
  	
  	return $this->_return(true);
  }
  
  public function add()
  /* add a new account
   *
   * required:
   *   N/A
   *
   * user optional:
   *   password           (string) the user password: default 'password'
   *   debug_mode         (bool) true to enable debugging: default false
   *
   * group optional:
   *   debug_mode         (bool) true to enable debugging: default false
   *
   * returns:
   *   true or false
   *
   */
  {
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (($mode === 'user')&&(func_num_args() == 2)) {
  		$debug_me = func_get_arg(1);
  	} elseif (func_num_args() == 1) {
  		$debug_me = func_get_arg(0);
  	} else {
  		$debug_me = false;
  	}
  	if ($debug_me) { $this->_debug_mode = 99; } $this->_debug_start();
  	
  	# set variables
  	if (($mode === 'user')&&(func_num_args() > 0)) { $pw = func_get_arg(0); } else { $pw = 'password'; }
  	
  	# first check to make sure that no record is loaded
  	if ($this->{$mode . '_loaded'}) return $this->_return(false, "Error: can not add a new $mode when one is already loaded");
  	
  	# retrieve a complete list of fields
  	$fields = $this->{$mode}->get_fields();
  	
  	# validate all primary type fields
  	for ($i=0;$i<count($fields);$i++) { $this->{$mode}->validate_data($fields[$i]); }
  	
  	# make sure all required variables have been set
  	if (!$this->{$mode}->check_required()) {
  		return $this->_return(false, 'Error: one or more required variables for this object type were not set or invalid.');
  	}
  	
  	# now verify that this object is not already registered (last chance to check!!)
  	$this->_debug('validating that this object is not already registered');
  	if ($mode === 'user') { $id = 'uid'; } else { $id = 'gid'; }
  	if ($this->exists($this->{$mode}->{$id}, $id, '')) return $this->_return(false, 'Error: this object already exists!');
  	
  	# set variables
  	$this->_debug('creating new object array...', true);
  	$data = array();
  	
  	# add set variables to the array
  	foreach($fields as $var) {
  		if (is_array($this->{$mode . '_data'}[$var])&&(count($this->{$mode . '_data'}[$var])>0)) {
  			$data[$var] = $this->{$mode . '_data'}[$var];
  		} elseif (strlen(trim((string)$this->{$mode . '_data'}[$var]))>0) {
  			$data[$var] = trim($this->{$mode . '_data'}[$var]);
  		}
  	}
  	
  	# add the unique id
  	if (!array_key_exists('unique_id', $data)) { $data['unique_id'] = $this->_tx->db->uuid(); }
  	
  	if ($mode === 'user') {
  		$data['password'] = $this->user->encode_password($pw);
  		$data['pw_last_set'] = date('Y-m-d H:i:s');
  	}
  	
  	$this->_debug('done');
  	
  	# attempt to add new object
  	$this->_debug('adding object...');
  	$result = $this->{$mode}->store('add', $data);
  	
  	# cache the id to reload
  	$object_id = $this->{$mode}->{$id};
  	
  	# clear values
  	$this->{$mode}->clear();
  	
  	# if the new object was added successfully, attempt to load it
  	if ($result) $result = $this->{$mode}->load($object_id);
  	
  	return $this->_return($result);
  }
  
  abstract public function add_member($guid, $type = 'user');
  /* contract for add_member: add an existing user or group (as a nested group) to the loaded group
   *
   * required:
   *   guid               (string) the user or group object globally unique identifier
   *
   * optional:
   *   type               (string) [ user | group ]: default user
   *
   * returns:
   *   true or false based on operation success and/or input validity
   *
   * implementation notes:
   *   implements the membership/map store (i.e. database, ldap, etc...)
   *   $this->var_mode must equal 'group'
   *   do not ever allow a group to be added if it is already a nested or direct member
   *
   */
  
  abstract public function arguments();
  /* contract for arguments: return an array detailing the arguments that can be passed to configure()
   *
   * required:
   *   N/A
   *
   * optional:
   *   N/A
   *
   * returns:
   *   (array) of zero or more arrays detailing the arguments in the following format:
   *
   *   [0]=>array(
   *              'id'=>'variable name'         the exact variable to be passed to configure (the key)
   *              'required'=>(bool)            [ true | false ]: true if the variable is required
   *              'type'=>[string|int|bool]*    (optional) the type to be passed for the value
   *              'length'=>(integer)*          (optional) the maximum length for the value
   *              'label'=>(string)*            (optional) the label to be used in any client facing form
   *              'description'=>(string)*      (optional) an extended description to be used in any client facing form
   *              'options'=>(array)*           (optional) an array of options the variable must be selected from
   *                                                       if keys are non-integer, the key will be returned not the value
   *             )
   *
   * implementation notes:
   *   N/A
   *
   */
  
  abstract public function authenticate($pw);
  /* contract for authenticate: verify the provided password is valid for the loaded user account
   *
   * required:
   *   pw                 (string) the password to check
   *
   * optional:
   *   N/A
   *
   * returns:
   *   true if the password is valid, false otherwise
   *
   * implementation notes:
   *   $this->var_mode must equal 'user'
   *
   */
  
  protected function cache_vars()
  /* load all account attributes into cache array
   *
   * the cache is used to verify that certain attributes are not altered improperly
   *	(i.e. changing the user name)
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	$list = $this->{$mode}->get_fields();
  	foreach($list as $var) { $this->{$mode . '_cache'}[$var] = @$this->{$mode . '_data'}[$var]; }
  	return $this->_return(true, 'cache successfully updated');
  }
  
  abstract public function catalog($fields = false);
  /* contract for catalog: return a list of all accounts
   *
   * required:
   *   N/A
   *
   * optional:
   *   fields             (array) local field names to return: default false -> all
   *
   * returns:
   *   array of rows or false based on operation success and/or input validity
   *
   * implementation notes:
   *   use $this->get_fields() to load the default field list
   *
   */
  
  public function change_password($old_password, $new_password)
  /* change the password for the loaded account
   *
   * returns true or false
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# ensure an account is loaded
  	if (!$this->user_loaded) return $this->_return(false, 'Error: no account is loaded!');
  	
  	# verify the old password and authenticate as the user so he or she changes his or her own password
  	$this->_debug('verifying the current password');
  	if (!$this->authenticate($old_password)) return $this->_return(false, 'Error: incorrect password');
  	
  	# change the password
  	return $this->_return($this->set_password($new_password));
  }
  
  abstract public function change_id($new_id);
  /* contract for change_id: change the user or group id of the loaded account
   *
   * required:
   *   new_id             (string) the new unique id for the account
   *
   * optional:
   *   N/A
   *
   * returns:
   *   true or false based on success
   *
   * implementation notes:
   *   N/A
   *
   */
  
  abstract public function check_member($guid, $group_guid = null, $nested = false);
  /* contract for check_member: report if a item is a member of a group
   *
   * required:
   *   guid               (string) the user or group object globally unique identifier
   *
   * optional:
   *   group_guid         (string) if null use the loaded group, otherwise check against
   *                               this target group: default null
   *   nested             (bool) if true, check nested groups to N levels, otherwise
   *                             only check the direct membership: default false
   *
   * returns:
   *   true or false based on group membership and/or input validity
   *
   * implementation notes:
   *   $this->var_mode must equal 'group'
   *
   */
  
  protected function check_required()
  /* verify that all required variables for this object type have been set
   *
   * returns true if the conditions have been met, false if they have not
   *
   */
  {
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode === 'user') { $id = 'uid'; } else { $id = 'gid'; }
  	if (strlen($this->{$mode}->{$id}) == 0) return false;
  	return true;
  }
  
  protected function clear($mode_override = false)
  /* clear all variables and reset the object
   *
   */
  {
  	$this->_debug_start();
  	if ($mode_override !== false) {
  		$mode = $mode_override;
  	} else {
  		if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	}
  	
  	# configure object to unloaded state
  	switch ($mode) {
  		case 'group':
  			$this->group_loaded = false;
  			$this->group_data = array();
  			$this->group_cache = array();
  			break;
  		case 'user':
  			$this->authenticated = false;
  			$this->user_loaded = false;
  			$this->user_data = array();
  			$this->user_cache = array();
  	}
  	
  	$list = $this->{$mode}->get_fields();
  	foreach($list as $var) {
  		$this->{$mode . '_cache'}[$var] = '';
  		$this->{$mode . '_data'}[$var] = '';
  	}
  	
  	$this->_debug("$mode attributes initialized");
  	
  	return $this->_return(true, 'reset to unloaded state');
  }
  
  abstract public function configure($args = false);
  /* contract for configure: configure the module and/or verify the module configuration
   *
   * required:
   *   N/A
   *
   * optional:
   *   args               (array) key value pairs of arguments::value to be set
   *
   * returns:
   *   true if all required settings are set and the module is operational
   *   false if the module is not configured
   *
   */
  
  abstract public function delete();
  /* contract for delete: delete an account
   *
   * required:
   *   N/A
   *
   * optional:
   *   N/A
   *
   * returns:
   *   true or false based on operation success
   *
   * implementation notes:
   *   make sure to clear any membership/mapping table as well for groups
   *
   */
  
  public function disable()
  /* administratively disable the loaded account
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode . '_loaded'}) return $this->_return(false, "Error: no $mode is loaded!");
  	$this->{$mode . '_data'}['enabled'] = false;
  	return $this->_return($this->{$mode}->save());
  }
  
  public function enable()
  /* administratively enable the loaded account
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode . '_loaded'}) return $this->_return(false, "Error: no $mode is loaded!");
  	$this->{$mode . '_data'}['enabled'] = true;
  	return $this->_return($this->{$mode}->save());
  }
  
  protected function encode_password($password)
  /* encodes a user password for storage in the database
   *
   * returns the encoded password
   *
   * encryption loop based on code provided by gigatop100@hotmail.com at:
   *  http://www.php.net/md5
   *  retrieved may-09-2010, ws
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	$salt = '%^(W~S#`*';
  	for ($i=0;$i<$this->password_entropy;$i++) {
  		$password = hash('sha512', $salt . $password);
  		$password = md5($password . $salt);
  	}
  	return $password;
  }
  
  abstract public function exists($match_string, $local_field = false);
  /* contract for exists: report whether or not an account exists
   *
   * required:
   *   match_string       (string) a non-empty string value to match against
   *
   * optional:
   *   local_field        (string) a local field name to search: default false -> uid/gid
   *
   * returns:
   *   true or false based on input validity and whether the account exists
   *
   * implementation notes:
   *   N/A
   *
   */
  
  public function get_fields($mode_override = false)
  /* returns an array of object fields usable for the loaded account type
   *
   * returns an empty array on error (with output to debug)
   *
   */
  {
  	if ($this->mode($mode) === false) {
  		if (is_string($mode_override)&&(in_array($mode_override, array('user', 'group')))) {
  			$mode = $mode_override;
  		} else {
  			return false;
  		}
  	}
  	
  	switch($mode) {
  		case 'group':
  			$arr = array('gid', 'gid_number', 'unique_id', 'description', 'display_name', 'enabled');
  			break;
  		case 'user':
  			$arr = array('email', 'first', 'middle', 'last', 'uid', 'uid_number', 'unique_id',
  			             'phone', 'description', 'mobile', 'pager', 'country', 'city', 'state', 'zip', 
  			             'office', 'address', 'web_url', 'pw_last_set', 'display_name', 'enabled');
  			break;
  	}
  	
  	return $arr;
  }
  
  public function load($match_string, $local_field = false, $debug_me = false)
  /* load account matching the specified criteria
   *
   * the default search field is uid/gid (depending on the mode)
   *
   */
  {
  	if ($debug_me) { $this->_debug_mode = 99; }
  	$this->_debug_start();
  	
  	# get the mode
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($local_field === false) {
	  	if ($mode === 'group') { $local_field = 'gid'; } else { $local_field = 'uid'; }
	  }
  	
  	# ensure an account is not loaded
  	if ($this->{$mode . '_loaded'}) $this->{$mode}->unload();
  	
  	# ensure the search criteria is valid
  	if (!is_string($match_string)) return $this->_return(false, '<strong>Warning</strong>: Invalid search criteria provided. Match value was not a string.');
  	if (strlen(trim($match_string)) == 0) return $this->_return(false, '<strong>Warning</strong>: You did not provide any data to search for!');
  	
  	# search for this account
  	$this->_debug('searching for the account matching "' . $match_string . '"');
  	$search = $this->{$mode}->search(array($local_field=>$match_string));
  	if ($search === false) return $this->_return(false, "<strong>Error</strong>: $mode not found");
  	
  	# load the account data
  	$data = $this->{$mode}->retrieve($search[0]['unique_id']);
  	if ($data === false) return $this->_return(false, "<strong>Error</strong>: Found a $mode but I was unable to load it");
  	
  	# parse results into object variables
  	$this->_debug('loading object data... ', true);
  	$this->{$mode . '_data'} = array();
  	foreach($data as $field=>$value) { $this->{$mode . '_data'}[$field] = $value; }
  	$this->_debug('done');
  	
  	# set object to record loaded mode
  	$this->{$mode . '_loaded'} = true;
  	
  	# update cache
  	$this->_debug('preparing record cache');
  	$this->{$mode}->cache_vars();
  	
  	return $this->_return(true, 'successfully loaded object');
  }
  
  abstract public function members($guid, $include_descendants = false, $recursive = false);
  /* contract for members: retrieve the complete group membership for the provided guid
   *
   * required:
   *   guid               (string) the group object globally unique identifier
   *
   * optional:
   *   include_descendants (bool) if true, include all descendant members as well: default false
   *   recursive          (bool) if true, function was called by itself
   *
   * returns:
   *   an array of zero or more rows with user or group guids as keys and membership type as values
   *     0 => direct membership
   *     1 => descendant membership
   *
   * implementation notes:
   *   this function can be called without regard to mode
   *   this function returns users or groups that are members of the provide guid
   *
   */
  
  abstract public function membership($guid, $include_inherited = false, $recursive = false);
  /* contract for membership: retrieve the complete group membership for provided guid
   *
   * required:
   *   guid               (string) the user or group object globally unique identifier
   *
   * optional:
   *   include_inherited  (bool) if true, included all inherited groups as well: default false
   *   recursive          (bool) if true, function was called by itself
   *
   * returns:
   *   an array of zero or more rows with group guids as keys and membership type as values
   *     0 => direct membership
   *     1 => indirect (inherited) membership
   *
   * implementation notes:
   *   this function can be called without regard to the mode
   *   this function returns groups the provided guid is a member of
   *
   */
  
  protected function mode(&$mode, $target_mode = false)
  /* return the current var mode
   *
   * if target_mode is provided only return true if the actual mode is the target mode
   *   - target_mode can be an array of valid modes
   *
   * returns true if a mode is set or false if no mode is set
   * clears the set mode
   *
   */
  {
  	if ($this->var_mode === false) return false;
  	if (
  			($target_mode === false)
  			||
  			(is_string($target_mode)&&($this->var_mode === $target_mode))
  			||
  			(is_array($target_mode)&&(in_array($this->var_mode, $target_mode)))
  		 )
  	{
  		$mode = $this->var_mode;
  		$this->var_mode = false;
  		return true;
  	}
  	return false;
  }
  
  public function module_id($id = false)
  /* set or get the module id for this instantiated object
   *
   * used by the security system and by the site to reference this object
   *
   * if no id is provided, the currently set id will be returned or false if none is set
   *
   * returns the module_id of this object or false
   *
   */
  {
  	if ($id === false) {
  		if (is_null($this->_module_id)) return false;
  		return $this->_module_id;
  	}
  	$this->_module_id = $id;
  	return $id;
  }
  
  public function publish()
  /* publish variables for the loaded account
   *
   */
  {
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode == 'group') {
  		$this->_tx->publish('group_guid', $this->group_guid);
  		$this->_tx->publish('group_id', $this->group_id);
  		$this->_tx->publish('group_name', $this->group_name);
  	} else {
  		$this->_tx->publish('user_guid', $this->user_guid);
  		$this->_tx->publish('user_id', $this->user_id);
  		$this->_tx->publish('user_name', $this->user_name);
  	}
  }
  
  abstract public function remove_member($guid, $type = 'user');
  /* contract for remove_member: remove a member from the loaded group
   *
   * required:
   *   guid               (string) the user or group object globally unique identifier
   *
   * optional:
   *   type               (string) [ user | group ]: default user
   *
   * returns:
   *   true or false based on operation success and/or input validity
   *
   * implementation notes:
   *   implements the membership/map store (i.e. database, ldap, etc...)
   *   verify $this->var_mode == 'group'
   *   do not allow 'locked' members to be removed
   *
   */
  
  abstract protected function retrieve($guid);
  /* contract for retrieve: retrieve an account record
   *
   * required:
   *   guid               (string) the user or group object globally unique identifier
   *
   * optional:
   *   N/A
   *
   * returns:
   *   record array or false based on operation success and/or input validity
   *
   * implementation notes:
   *   N/A
   *
   */
  
  public function save($debug_me = false)
  /* save the loaded account
   *
   */
  {
  	if ($debug_me) $this->_debug_mode = 99;	
  	$this->_debug_start();
  	
  	# get the mode
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	
  	# ensure an account is loaded
  	if (!$this->{$mode . '_loaded'}) return $this->_return(false, 'Error: no account is loaded!');
  	
  	# check to make sure that no fixed values were altered
  	if (!$this->{$mode}->verify_vars()) return $this->_return(false, 'Error: one or more fixed variables were altered. Can not save record');
  	
  	# retrieve the field list
  	$fields = $this->{$mode}->get_fields();
  	
  	# validate input data
  	for ($i=0;$i<count($fields);$i++) { $this->{$mode}->validate_data($fields[$i]); }
  	
  	# make sure all required variables have been set
  	if (! $this->{$mode}->check_required()) {
  		return $this->_return(false, 'Error: one or more required variables for this object type were not set or invalid.');
  	}
  	
  	# declare variables
  	$update = array();
  	
  	# set variables
  	$this->_debug('creating account update array...', true);
  	
  	foreach($fields as $field) {
  		if (is_array($this->{$mode.'_data'}[$field])&&(count($this->{$mode.'_data'}[$field]) > 0)) {
  			$update[$field] = $this->{$mode.'_data'}[$field];
  		} elseif (strlen(trim((string)$this->{$mode.'_data'}[$field])) > 0) {
  			$update[$field] = $this->{$mode.'_data'}[$field];
  		} else {
  			$update[$field] = '';
  		}
  	}
  	
  	$this->_debug('done');
  	
  	# attempt to update the account
  	$this->_debug('saving object... ', true);
  	if ((count($update) > 0)&&($this->{$mode}->store('save', $update))) {
  		$this->_debug('success');
  		$result = true;
  	} elseif (count($update) == 0) {
  		$this->_debug('nothing to save');
  		$result = true;
  	} else {
  		$this->_debug('failed');
  		$this->_debug('<strong>Could not save object.</strong>');
  		if ($debug_me) { echo "<br /><br />"; var_dump($update); echo "<br /><br />"; }
  		$result = false;
  	}
  	
  	# cache the expected object class and name to reload
  	$object_id = $this->{$mode.'_data'}[$id];
  	
  	# clear values
  	$this->{$mode}->clear();
  	
  	# reload the object to refresh the data after save
  	$this->{$mode}->load($object_id);
  	
  	return $this->_return($result);
  }
  
  abstract public function search($criteria, $multiple_results = false, $partial_match = false, $comparison_type = 'AND');
  /* contract for search: find one or more accounts
   *
   * required:
   *   criteria           (array) one or more pairs of fields=>values to search for
   *
   * optional:
   *   multiple_results   (bool) if true return data IFF there is exactly one record, otherwise return false: default false
   *   partial_match      (bool) if true, use partial matching on all provided field::value pairs: default false
   *   comparison_type    (string) type of simple comparison to use for all field::value pairs [ AND | OR | etc... ]
   *
   * returns:
   *   an array of rows, if multiple results still return data in $array[0] = array() or false on error OR no results
   *
   * implementation notes:
   *   N/A
   *
   */
  
  abstract public function set_password($pw);
  /* contract for set_password: administratively reset or set the password for the loaded account
   *
   * required:
   *   pw                 (string) the password to set for the loaded account
   *
   * optional:
   *   N/A
   *
   * returns:
   *   true or false based on result of operation
   *
   * implementation notes:
   *   verify $this->var_mode == 'user'
   *
   */
  
  abstract protected function store($operation, $data);
  /* contract for store: store an account
   *
   * required:
   *   operation          (string) [ add | save ]
   *   data               (array) fields::values to save
   *
   * optional:
   *   N/A
   *
   * returns:
   *   true or false based on operation success and/or input validity
   *
   * implementation notes:
   *   implements the account store (i.e. database, ldap, etc...)
   *
   */
  
  abstract public function type($set_type = false);
  /* contract for type: get the configured internal primary object type or the successfully set type
   *
   * required:
   *   N/A
   *
   * optional:
   *   set_type           (string) [ user | group (| object_defined) ]
   *
   * returns:
   *   the object type at the end of this prodedure or false if there was an error
   *
   * implementation notes:
   *   if set_type is specified and is a valid value, attempt to change the object type to the provided value
   *
   */
  
  public function unload()
  /* unload the loaded account
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(true);
  	$this->{$mode}->clear();
  	$this->{$mode.'_loaded'} = false;
  	return $this->_return(true, 'account unloaded');
  }
  
  protected function validate_data($field)
  /* given an internal field name, validate the current value
   *
   * this function will alter the value such that it can be saved to the directory
   *
   * returns true on success or false on an error
   *
   */
  {
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	switch($mode . '_' . strtolower($field)) {
  		case 'group_gid':
  			$this->_debug('setting group id to lowercase');
  			$this->group->gid = strtolower($this->group->gid);
  			break;
  		case 'user_email':
  			if (! is_null($this->user->email)) {
  				# force e-mail to all lowercase
  				$this->_debug('setting e-mail address to lowercase');
  				$this->user->email = strtolower('' . $this->user->email);
  			}
  			break;
  		case 'user_middle':
  			if ((! is_null($this->user->middle))&&(strlen($this->user->middle) == 1)) {
  				$this->user->middle = strtoupper($this->user->middle);
  			}
  			break;
  		case 'user_display_name':
  			$this->_debug('checking display name value...', true);
  			if ($this->user->display_name == '') {
  				if (strlen($this->user->middle) == 0) {
  					$this->user->display_name = $this->user->first . ' ' . $this->user->last;
  				} elseif (strlen($this->user->middle) > 1) {
  					$this->user->display_name = $this->user->first . ' ' . $this->user->middle . ' ' . $this->user->last;
  				} else {
  					$this->user->display_name = $this->user->first . ' ' . $this->user->middle . '. ' . $this->user->last;
  				}
  			}
  			$this->_debug('set');
  			break;
  		case 'user_uid':
  			$this->_debug('setting user id to lowercase');
  			$this->user->uid = strtolower($this->user->uid);
  			break;
  	}
  	return true;
  }
  
  protected function verify_vars()
  /* verify fixed object attributes with the cached attributes
   *
   * this function will return true if all fixed attributes have *not* changed or
   * false if they have (meaning running an update could potentially corrupt the directory)
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	# preset the data integrity variable to true
  	$integrity_checks_out = true;
  	$this->_debug('preset integrity check... true');
  	
  	foreach($this->{$mode.'_restricted_fields'} as $var) {
  		if ($this->{$mode.'_data'}[$var] != $this->{$mode.'_cache'}[$var]) {
  			$this->_debug("<strong>Warning:</strong> restricted variable $var was modified. Canceling updates!");
  			$integrity_checks_out = false;
  		}
  	}
  	
  	if (! $integrity_checks_out) { $this->_debug('integrity check failed!'); }
  	
  	return $this->_return($integrity_checks_out);
  }
  	
}
?>