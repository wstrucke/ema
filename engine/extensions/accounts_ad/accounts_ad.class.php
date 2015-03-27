<?php
 /* EMA Accounts-AD (Authentication) Module for the Security System
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 2.0.0, May-03-2011
  * William Strucke, wstrucke@gmail.com
  *
  * Implements accounts, groups, and membership using MS Active Directory
  *
  */

require_once dirname(__FILE__) . '/utilities/in_array_partial.php';
require_once dirname(__FILE__) . '/utilities/iso-3166.php';

class accounts_ad extends user_group
{
  #public $schema_version='0.1.2';  // the schema version to match the registered schema
  public $_name = 'Accounts-AD (Authentication) Module';
  public $_version = '2.0.0';
  protected $_debug_prefix = 'accounts_ad';
  
  public $_server;                  // (string) server FQDN
  
  # internal variables
  protected $_base_dn;
  protected $_base_ou;
  protected $_block_size = 500;     // the number of group members to retrieve at a time
  protected $_connector;            // (resource) PHP LDAP connector
  protected $_default_gid_number = 513;
  protected $_default_smb_drive = '';
                                    // (string) should be drive letter plus colon, e.g. "H:"
  protected $_default_smb_script = '';
                                    // (string) should be the script name, e.g. "login.vbs"
  protected $_domain;               //          domain name
  protected $_enable_exchange = false;
                                    // (bool) enables exchange fields and type conversions
  protected $_enable_hooks = true;  // (bool) if enabled, checks for function hooks in the 'hooks' sub-directory
                                    //        hooks should be named "function_name.php", e.g. "add.php"
  protected $_hook_dir = 'hooks';   // (string) the directory to look for hooks in
  protected $_login_id;             // (string) login credentials
  protected $_login_pw;
  protected $_port = 636;           // (integer) ldap server SSL port
  protected $_restricted_accounts = array('Administrator');
                                    // list of accounts that the object should never modify
  protected $_role_policy_dn;       // (string) the exchange default role policy dn for new accounts
  protected $_root_dn;              // (string) the absolute root dn of the directory
  protected $_server_ip;            // (string) server IP
  protected $_x400_prefix;          // (string) the exchange x400 prefix for new accounts
  protected $lockout_duration;      // (string) duration of account locks
  protected $lockout_threshold;     // (string) number of bad login attempts before account lockout
  
  protected $co;                    // (string) country code
  protected $exch_uac;              // (string) exchange user account control field contents
  protected $group_data_protected;  // (array) protected group data for stateful requests
  protected $new_pw_required;       // (bool) internal reference to the set of fields controlling new pw required (at next logon) - bool value
  protected $pw_last_set;           // (string) timestamp of 100ns intervals since 01-01-1601 UTC
  protected $sid;                   // (string) object security id
  protected $uac;                   // (string) user account control field contents
  protected $user_data_protected;   // (array) protected user data for stateful requests
  protected $user_primary_type;     // (string) the loaded or initialized object type; defaults to 'user'
                                    //          valid values are ('user', 'user+', 'user++', 'contact', or 'contact+')
  protected $group_working_ou;      // (string) currently selected ou; reset whenever an account is loaded
  protected $tmp_cache;             // (array) for internal use to pass data in recursive functions
  protected $user_working_ou;       // (string) currently selected ou; reset whenever an account is loaded
  
  /* code */
  
  public function &__get($item)
  /* accounts_ad extends the user_group::__get() function to implement protected variables
   *   and to provide read-only aliases for legacy support
   *
   */
  {
  	# try public / parent fields first
  	$pget =& parent::__get($item); if ($pget != false) return $pget;
  	
  	# implement system
  	if (strtolower($item) == 'system') {
  		$this->_debug('system mode');
  		$this->var_mode = 'system';
  		return $this;
  	}
  	
  	if ($this->mode($mode) === false) return $this->false;
  	
  	# return the protected item if it exists
  	if (array_key_exists($item, $this->{$mode . '_data_protected'})) return $this->{$mode . '_data_protected'}[$item];
  	
  	return $this->false;
  }
  
  public function _construct()
  /* accounts_ad extends the user_group::_construct() function
   *
   */
  {
  	parent::_construct();
  	
  	#$this->group_restricted_fields = array_merge($this->group_restricted_fields, array());
  	$this->user_restricted_fields = array_merge($this->user_restricted_fields, array('dn', 'uid', 'uid_number', 'unique_id', 'ldap_object_class'));
  }
  
  protected function _generate_uuid()
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
  {
  	if ($this->configure() === false) return false;
  	if (strlen($this->_domain) == 0) return false;
  	$this->uuid = md5('msad' . $this->_domain);
  	return true;
  }
  
  public function add()
  /* accounts_ad extends the user_group::add() function
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	
  	if ($mode == 'user') {
  		# adhere to restricted accounts list
  		if ($this->user->check_restricted($this->user->uid)) return false;
  		
 			# validate object primary type
  		if ($this->user_primary_type == '') $this->user_primary_type = 'user';
  	}
  	
  	# make sure all required variables have been set
  	if (!$this->{$mode}->check_required()) {
  		$this->_debug('Error: one or more required variables for this object type were not set or invalid.');
  		return false;
  	}
  	
  	# build the object class based on the working primary type
  	$this->{$mode}->ldap_object_class = $this->{$mode}->get_object_class();
  	
  	# handle dynamic arguments
  	switch (func_num_args()) {
  		case 1: $r = parent::__get($mode)->add(func_get_arg(0)); break;
  		case 2: $r = parent::__get($mode)->add(func_get_arg(0), func_get_arg(1)); break;
  		default: $r = parent::__get($mode)->add(); break;
  	}
  	
  	if ($r) {
  		# execute function hook if successful
			$this->{$mode}->hook('add');
			return true;
  	}
  	
  	return false;
  }
  
  public function add_member($guid, $type = 'user')
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
  {
  	return false;
  }
  
  public function admin($option = false)
	/* module administration interface
	 *
	 */
	{
		return $this->_content('admin');
	}
	
  public function arguments()
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
  {
  	return array(
  		0=>array('id'=>'server','required'=>true,'type'=>'string','label'=>'AD Server Name'),
  		1=>array('id'=>'ip','required'=>false,'type'=>'string','label'=>'AD Server IP Address'),
  		2=>array('id'=>'domain','required'=>false,'type'=>'string','label'=>''),
  		3=>array('id'=>'id','required'=>true,'type'=>'string','label'=>'Login ID'),
  		4=>array('id'=>'pw','required'=>true,'type'=>'string','label'=>'Login Password'),
  		5=>array('id'=>'dn','required'=>false,'type'=>'string','label'=>'Base DN'),
  		6=>array('id'=>'root_dn','required'=>false,'type'=>'string','label'=>'Root DN'),
  		7=>array('id'=>'ou','required'=>false,'type'=>'string','label'=>'Base OU for User Accounts'),
  		8=>array('id'=>'use_ip','required'=>false,'type'=>'bool','label'=>'Prefer the IP Address'),
  		9=>array('id'=>'ssl','required'=>false,'type'=>'bool','label'=>'Use SSL to Connect'),
  		10=>array('id'=>'exchange','required'=>false,'type'=>'bool','label'=>'Enable MS Exchange Functionality'),
  		11=>array('id'=>'x400_prefix','required'=>false,'type'=>'string','label'=>'Exchange X400 Prefix'),
  		12=>array('id'=>'role_policy_dn','required'=>false,'type'=>'string','label'=>'Exchange Role Policy DN'),
  		13=>array('id'=>'debug','required'=>false,'type'=>'integer','label'=>'Set to 0 or higher to enable debug output')
  	);
  }
  
  public function authenticate($pw)
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# ensure an account is loaded
  	if (!$this->user_loaded) return $this->_return(false, 'Error: no account is loaded!');
  	
  	# verify object type
  	if (strpos($this->user_primary_type, 'user') === false) {
  		return $this->_return(false, 'Error: only user objects can authenticate');
  	}
  	
  	# ensure we have some password to check
  	if (strlen(trim($pw)) == 0) return $this->_return(false, 'Error: refusing to check empty password!');
  	
  	# authenticate the user
  	$this->_debug('attempting to authenticate against the directory...', true);
  	if (!@ldap_bind($this->_connector, $this->user->dn, $pw)) {
  		$this->_debug('<strong>failed</strong>');
  		# make sure to rebind as the admin
  		$this->bind();
  		return $this->_return(false);
  	}
  	
  	$this->_debug('success');
  	
  	# make sure to rebind as the admin
  	$this->bind();
  	
  	# execute function hook if successful
  	$this->user->hook('authenticate');
  	
  	return $this->_return(true);
  }
  
  protected function bind()
  /* Bind to the LDAP server to enable read/write access
   *
   */
  {	
  	# bind to server
  	$this->_debug_start();
  	$this->_debug('Binding to the directory server...', true);
  	if (!(@ldap_bind($this->_connector, $this->_login_id, $this->_login_pw))) {
  		$this->_debug('error');
  		die("Unable to bind to server. <em>(Error " . ldap_errno($this->_connector) . ') ' .
  				ldap_error($this->_connector) . "</em>");
  	}
  	return $this->_return(true, 'success');
  }
  
  protected function cache_vars()
  /* accounts_ad extends the user_group::cache_vars() function
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	$this->var_mode = $mode;
  	parent::cache_vars();
  	
  	if ($mode === 'user') {
  		$this->user_cache['dn'] = $this->{$mode}->dn;
  		$this->user_cache['_primary_type'] = $this->user_primary_type;
  	} else {
  		$this->group_cache['dn'] = $this->{$mode}->dn;
  	}
  }
  
  public function catalog($fields = false)
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
  {
  	return false;
  }
  
  public function change_id($new_id)
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, 'An account must be loaded');
  	if ($this->{$mode}->exists($new_id)) return $this->_return(false, 'The account already exists');
  	if ($mode == 'group') {
  		$field = 'gid';
  		$update = array();
		} else {
			$field = 'uid';
			$update = array($this->user->local_2_ldap('principal')=>"$new_id@$this->_domain");
		}
		
		# add the user id to update
		$update[$this->{$mode}->local_2_ldap($field)] = $new_id;
		
		# attempt to update the account
		$this->_debug('saving object... ', true);
		if (@ldap_modify($this->_connector, $this->{$mode}->dn, $update)) {
			$this->_debug('success');
			$this->{$mode}->{$field} = $new_id;
			$this->{$mode.'_cache'}[$field] = $new_id;
			if ($mode == 'user') {
				$this->{$mode.'_cache'}['principal'] = "$new_id@$this->_domain";
			}
			# execute function hook if successful
			$message = $this->hook('change_id');
			return $this->_return(true, $message);
		}
		
		$this->_debug('<strong>Could not save object.</strong> 
			<em>(Error ' . ldap_errno($this->_connector) . ') ' . ldap_error($this->_connector) . '</em>'
			);
		if ($debug_local) { echo "<br /><br />"; var_dump($update); echo "<br /><br />"; }
		
		return $this->_return(false, '<strong>failed</strong>');
  }
  
  public function change_password($old_password, $new_password)
  /* accounts_ad extends the user_group::change_password() function
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# verify object type
  	if (strpos($this->user_primary_type, 'user') === false) {
  		return $this->_return(false, 'Error: only user objects have passwords');
  	}
  	
  	# absolute security, can not alter these accounts
  	if ($this->check_restricted($this->user->uid)) return $this->_return(false, 'Error: the account is restricted!');
  	
  	# if the user is not allowed to change his or her password exit here
  	if ($this->user->pw_locked) return $this->_return(false, 'Error: loaded account does not have permissions to change the password');
  	
  	$r = parent::__get('user')->change_password($old_password, $new_password);
  	
  	# execute function hook if successful
  	if ($r === true) $this->user->hook('change_password');
  	
  	return $this->_return($r);
  }
  
  protected function change_type($previous, &$deleteArr)
  /* handle account type transitions cleanly
   *
   * called from the save function only
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# build the transition string
  	$t = $previous . '::' . $this->user_primary_type;
  	
  	# check for illegal transitions
  	if ( ($t == 'contact::user+') || ($t == 'contact::user++') || ($t == 'contact+::user') ||
  		($t == 'user::contact+') || ($t == 'user+::contact') || ($t == 'user++::contact') )
  	{
  		return $this->_return(false, 'Illegal type transition');
  	}
  	
  	$old_fields = $this->user->get_fields($previous);
  	$new_fields = $this->user->get_fields($this->user_primary_type);
  	
  	for ($i=0;$i<count($old_fields);$i++) {
  		if (in_array($old_fields[$i], $new_fields) === false) $deleteArr[] = $this->local_2_ldap($old_fields[$i]);
  	}
  	
  	# alert the developer in case there is a problem
  	if (((strpos($previous, 'contact') !== false)&&(strpos($this->user_primary_type, 'user') !== false)) ||
  		((strpos($previous, 'user') !== false)&&(strpos($this->user_primary_type, 'contact') !== false)))
  	{
  		$this->_debug('<strong><em>WARNING: SAVING THIS OBJECT MAY FAIL.</em></strong> Many versions of active directory do not ' .
  			'allow changes to the object class. If your directory server refuses to make the change <em><code>(Error 53) Server is ' .
  			'unwilling to perform</code></em> you should cache the account data, delete the contact, and restore the data to a new ' .
  			'account with the desired destination type');
  	}
  	
  	return $this->_return(true, "Approved transition $t");
  }
  
  public function check_member($guid, $group_guid = null, $nested = false)
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
  {
  	# enable debugging for this function
  	$this->_debug_start();
  	
  	# input validation
  	if ($this->mode($mode, 'group') === false) return $this->_return(false, 'Invalid mode');
  	if ((!is_string($guid))||(strlen($guid)==0)) return $this->_return(false, 'Invalid Search GUID');
  	if ((!$this->group_loaded)&&((!is_string($group_guid))||(strlen($group_guid)==0))) return $this->_return(false, 'Invalid Group GUID');
  	if ((!$this->group_loaded)&&(!$this->group->exists($group_guid, 'unique_id'))) return $this->_return(false, 'Search group does not exist');
  	if ($nested !== true) $nested = false;
  	
  	# i am implementing this function with the assumption that i can not query ldap directly for
  	# a group id with a specific member id to match the members array.  it is entirely possible
  	# that is incorrect.  obviously using one ldap query to execute this check would be substantially
  	# faster than the method I'm going to write, which involves enumerating the entire group membership
  	# and then checking for the requested guid.
  	#
  	# this function should be a prime candidate for review and optimization in the future. 2011-06-19/ws
  	
  	# get the group guid
  	if (is_null($group_guid)) $group_guid = $this->group->unique_id;
  	
  	# get the member list
  	$list = $this->group->members($group_guid, $nested);
  	$this->_debug('Group has ' . count($list) . ' members');
  	if ($list === false) return $this->_return(false, 'Unable to obtain group membership');
  	
  	# check for the requested id
  	if (array_key_exists($guid, $list)) return $this->_return(true, 'Check Member: True');
  	return $this->_return(false, 'Check Member: false');
  }
  
  protected function check_restricted($uid)
  /* check if the provided uid is in the restricted account list
   *
   * returns true if the account is in the list
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	if (! is_array($this->_restricted_accounts)) return false;
  	foreach($this->_restricted_accounts as $str) {
  		if (strtolower($str) == strtolower($uid)) return true;
  	}
  	return false;
  }
  
  protected function check_required()
  /* verify that all required variables for this object type have been set
   *
   * returns true if the conditions have been met, false if they have not
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	
  	# preset check passed to true
  	$passed = true;
  	
  	# check required vars based upon object type
  	switch ($this->user_primary_type) {
  		case 'contact':
  			if ( (strlen($this->user->name) == 0) || (strlen($this->user->email) == 0) ) {
  				$passed = false;
  			}
  			break;
  		case 'contact+':
  			if ( (strlen($this->user->last) == 0) || (strlen($this->user->email) == 0) || (strlen($this->user->exch_alias) == 0) ||
  				(strlen($this->user->exch_email) == 0) || (strlen($this->user->display_name) == 0) )
  			{
  				$passed = false;
  				$this->_debug('Last: ' . $this->user->last);
  				$this->_debug('E-mail: ' . $this->user->email);
  				$this->_debug('Exchange Alias: ' . $this->user->exch_alias);
  				$this->_debug('Exchange E-mail: ' . $this->user->exch_email);
  				$this->_debug('Display Name: ' . $this->user->display_name);
  			}
  			break;
  		case 'user':
  			if (strlen($this->user->last) == 0) {
  				$passed = false;
  			}
  			break;
  		case 'user+':
  			if ( (strlen($this->user->last) == 0) || (strlen($this->user->email) == 0) || (strlen($this->user->exch_alias) == 0) ||
  				(strlen($this->user->exch_countrycode) == 0) || (strlen($this->user->exch_email) == 0) ||
  				(strlen($this->user->display_name) == 0) )
  			{
  				$passed = false;
  				$this->_debug('Last: ' . $this->user->last);
  				$this->_debug('E-mail: ' . $this->user->email);
  				$this->_debug('Exchange Alias: ' . $this->user->exch_alias);
  				$this->_debug('Exchange Country Code: ' . $this->user->exch_countrycode);
  				$this->_debug('Exchange E-mail: ' . $this->user->exch_email);
  				$this->_debug('Display Name: ' . $this->user->display_name);
  			}
  			break;
  		case 'user++':
  			if ( (strlen($this->user->last) == 0) || (strlen($this->user->email) == 0) ||
  				(strlen($this->user->exch_alias) == 0) || (strlen($this->user->exch_countrycode) == 0) ||
  				(strlen($this->user->exch_uac) == 0) || (strlen($this->user->display_name) == 0) ||
  				(strlen($this->user->exch_mdb) == 0) || (strlen($this->user->exch_x400) == 0) ||
  				(! is_array($this->user->exch_proxy)) || (count($this->user->exch_proxy) == 0) )
  			{
  				$passed = false;
  				die("<BR /><BR />\r\n\r\nINVALID DATA PROVIDED: <strong>SYSTEM HALT</strong><br /><br /><br />\r\n\r\n\r\n");
  			}
  			break;
  		default:
  			# invalid object type -- an error has occurred
  			$this->_debug('Error: check_required found an invalid object type (' . $this->user_primary_type . ')');
  			$passed = false;
  			break;
  	}
  	
  	return $passed;
  }
  
  protected function clear()
  /* accounts_ad extends the user_group::clear() function to implement protected variables
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	parent::clear($mode);
  	
  	if ($mode == 'group') {
  		$this->group_data_protected = array();
  	} else {
  		$this->new_pw_required = false;
  		$this->user_data_protected = array();
  		$this->user_primary_type = '';
  		$this->reset_exch_uac_fields();
  		$this->reset_uac_fields();
  	}
  	
  	# clear/reset internal attributes
  	$this->dn = '';
  	$this->{$mode.'_working_ou'} = $this->_base_ou;
  	
  	return true;
  }
  
  public function close()
  /* close the LDAP connection
   *
   */
  {
  	$this->_debug_start();
  	return $this->_return(@ldap_close($this->_connector), 'terminating the ldap connection');
  }
  
  public function configure($args = false)
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
  {
  	if ($this->_connector) return true;
  	
  	# check for debug mode
  	if (isset($args['debug'])) { $this->_debug_mode = $args['debug']; } else { $this->_debug_mode = -1; }
  	$this->_debug('configure');
  	
  	# check for required argument
  	if (isset($args['server'])) {
  		$this->_server = $args['server'];
  		$this->_debug('using server ' . $this->_server);
  	} else {
  		return false;
  	}
  	
  	# set the domain
  	if (isset($args['domain'])) {
  		$this->_domain = $args['domain'];
  		$this->_debug('using provided domain: ' . $this->_domain);
  	} else {
  		$this->_domain = substr($this->_server, (strpos($this->_server, '.') + 1));
  		$this->_debug('domain set: ' . $this->_domain);
  	}
  	
  	# check for credentials
  	if (isset($args['id']) && isset($args['pw'])) {
  		$this->_login_id = $args['id'] . '@' . $this->_domain;
  		$this->_login_pw = $args['pw'];
  		$this->_debug('set login id: ' . $this->_login_id);
  	} else {
  		$this->_debug('using anonymous credentials');
  		$this->_login_id = '';
  		$this->_login_pw = '';
  	}
  	
  	# set server ip
  	if (isset($args['ip'])) {
  		$this->_server_ip = $args['ip'];
  		$this->_debug('server ip provided: ' . $this->_server_ip);
  	} else {
  		$this->_debug('retrieving server ip...', true);
  		$this->_server_ip = exec("host $this->_server | awk '{print $4}'");
  		$this->_debug($this->_server_ip);
  	}
  	
  	# set the DN
  	if (isset($args['dn'])) {
  		$this->_base_dn = $args['dn'];
  		$this->_debug('set dn: ' . $this->_base_dn);
  	} else {
  		# no DN was provided; split the FQDN into the DN
  		$this->_base_dn = 'dc=' . implode(',dc=', explode('.', $this->_domain));
  		$this->_debug('computed dn: ' . $this->_base_dn);
  	}
  	
  	# set the root DN
  	if (isset($args['root_dn'])) {
  		$this->_root_dn = $args['root_dn'];
  		$this->_debug('set root dn: ' . $this->_root_dn);
  	} else {
  		# no root DN was provided; extract it from the base DN
  		$this->_root_dn = @substr($this->_base_dn, stripos($this->_base_dn, 'dc'));
  		$this->_debug('computed root dn: ' . $this->_root_dn);
  	}
  	
  	# set the OU
  	if (isset($args['ou'])) {
  		$this->_base_ou = $args['ou'];
  		$this->_debug('set ou: ' . $this->_base_ou);
  	} else {
  		$this->_base_ou = 'cn=Users';
  		$this->_debug('set default ou');
  	}
  	
  	# set the working OU
  	$this->group_working_ou = $this->_base_ou;
  	$this->user_working_ou = $this->_base_ou;
  	
  	# set the connection protocol
  	if (isset($args['ssl']) && ($args['ssl'] == false)) {
  		$this->_debug('<em><strong>WARNING:</strong> Connecting over non-secure channel! All password functionality will be disabled.</em>');
  		$protocol = 'ldap://';
  	} else {
  		$protocol = 'ldaps://';
  	}
  	
  	# set the exchange options
  	if (isset($args['exchange']) && ($args['exchange']) &&
  		isset($args['x400_prefix']) && (strlen($args['x400_prefix']) > 0))
  	{
  		$this->_debug('enabling microsoft exchange support');
  		$this->_enable_exchange = true;
  		$this->_x400_prefix = $args['x400_prefix'];
  		$this->_role_policy_dn = $args['role_policy_dn'];
  	}
  	
  	# attempt to establish the server connection
  	$this->_debug('establishing server connection...', true);
  	if (isset($args['use_ip']) && $args['use_ip']) {
  		$this->_debug('connecting via ip override');
  		$this->_connector = ldap_connect($protocol . $this->_server_ip, $this->_port);
  	} else {
  		$this->_connector = ldap_connect($protocol . $this->_server, $this->_port);
  	}
  	
  	if ($this->_connector) {
  		$this->_debug('success');
  	} else {
  		$this->_debug('<strong>failed</strong>');
  		return false;
  	}
  	
  	# set ldap protocol version
  	$this->_debug('setting ldap protocol options');
  	if (! ldap_set_option($this->_connector, LDAP_OPT_PROTOCOL_VERSION, 3)) {
  		$this->_debug('Failed to set LDAP protocol version 3');
  	}
  	
  	ldap_set_option($this->_connector, LDAP_OPT_REFERRALS, 0);
  	
  	# bind to enable read/write access in AD
  	$this->bind();
  	
  	# load running information for the connected server
  	#$this->identify_server();
  	
  	# load system settings
  	$fields = $this->system->get_fields(true);
  	$results = @ldap_read($this->_connector, $this->_root_dn, '(objectclass=domain)', $fields);
  	$entry = @ldap_get_entries($this->_connector, $results);
  	if (is_array($entry)) {
  		for($i=0;$i<count($fields);$i++) {
  			$var = $this->ldap_2_local($fields[$i]); $this->{$var} = @$entry[0][$fields[$i]][0];
  		}
  		# convert system values to seconds
  		$this->lockout_duration /= 10000000;
  	}
  	
  	return true;
  }
  
  protected function convert_to_unicode($str)
  /* convert the provided string to unicode
   *
   */
  {
  	$len = strlen($str);
  	$newStr = '';
  	for ($i = 0; $i < $len; $i++) { $newStr .= "{$str{$i}}\000"; }
  	return $newStr;
  }
  
  public function delete()
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, '<strong>Warning:</strong> No account loaded');
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	if ($this->{$mode}->check_restricted($this->{$mode}->{$id})) return $this->_return(false, 'Error: the account is restricted!');
  		
  	# attempt to delete account
  	$this->_debug('attempting to delete account... ', true);
  	if (!(@ldap_delete($this->_connector, $this->{$mode}->dn))) {
  		$this->_debug('<strong>failed.</strong>');
  		$result = false;
  	} else {
  		$this->_debug('success');
  		$result = true;
  	}
  	
  	# unload this account
  	$this->{$mode}->unload();
  	
  	# execute function hook if successful
  	if ($result) $this->{$mode}->hook('delete');
  	
  	return $this->_return($result);
  }
  
  protected function delete_attribute($arr)
  /* delete one or more attributes from the loaded account
   *
   * arr can be a single string value or an array of strings
   *
   * in order to delete an attribute (from OpenLDAP) we need the 
   * current value(s) to remove, stored in our cachevars function
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, '<strong>Warning:</strong> No account loaded');
  	if (!is_array($arr)) return $this->_return(false, 'Warning: called delete_attribute without anything to delete!');
  	
  	# get id
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	
  	# absolute security, can not delete anything from these accounts
  	if ($this->{$mode}->check_restricted($this->{$mode}->{$id})) return $this->_return(false, 'Error: the account is restricted!');
  	
  	# preset return value in case there are no attributes to delete
  	$result = true;
  	
  	# attempt to delete attributes
  	$this->_debug('deleting ' . count($arr) . ' attributes from account "' . $this->{$mode}->{$id} . '"');
  	
  	foreach($arr as $key=>$item) {
  		# make attribute an array from our cache
  		$attribute[$item] = $this->{$mode.'_cache'}[$this->{$mode}->ldap_2_local($item)];
  		# ensure there is something to delete (e.g. there is no need to delete something that's empty!)
  		if (strlen($attribute[$item]) < 1) {
  			$this->_debug("ignoring attribute $item");
  			unset($attribute);
  			continue;
  		}
  		# ensure there is no 'count' value; this will cause the update/delete to fail
  		if ( (is_array($attribute[$item])) && (isset($attribute[$item]['count'])) ) {
  			$this->_debug('found count key');
  			unset($attribute[$item]['count']);
  		}
  		if (!(@ldap_mod_del($this->_connector, $this->{$mode}->dn, $attribute))) {
  			$this->_debug('<strong>failed to delete item ' . $key . ':' . $item . '</strong>');
  			$result = false;
  		} else {
  			$this->_debug('successfully deleted item ' . $key . ':' . $item);
  			$result = true;
  		}
  		unset($attribute);
  	}
  	
  	return $this->_return($result);
  }
  
  public function disable()
  /* accounts_ad extends the user_group::disable() function
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, '<strong>Warning:</strong> No account loaded');
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	if ($this->{$mode}->check_restricted($this->{$mode}->{$id})) return $this->_return(false, 'Error: the account is restricted!');
  	
  	$result = parent::__get($mode)->disable();
  	
  	# execute function hook if successful
		if ($result) $this->{$mode}->hook('disable');
		
		return $this->_return($result);
  }
  
  public function dn2guid($dn)
  /* given an object dn return the object_guid or false
   *
   */
  {
  	if (is_array($dn)) {
  		$list = array();
  		for($i=0;$i<count($dn);$i++) $list[$i] = $this->dn2guid($dn[$i]);
  		return $list;
  	}
  	
  	# execute ldap query
  	$tmp = @ldap_read($this->_connector, $dn, '(objectclass=*)', array('objectguid'));
  	
  	if (@ldap_count_entries($this->_connector, $tmp) == 1) {
  		$entry = @ldap_first_entry($this->_connector, $tmp);
			$v = ldap_get_values_len($this->_connector, $entry, 'objectguid');
			if (is_array($v) && is_string($v[0]) && (strlen($v[0]) > 0)) return $v[0];
  	}
  	
  	return false;
  }
  
  public function enable()
  /* accounts_ad extends the user_group::enable() function
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, '<strong>Warning:</strong> No account loaded');
  	
  	# get id
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	
  	# absolute security, can not delete anything from these accounts
  	if ($this->{$mode}->check_restricted($this->{$mode}->{$id})) return $this->_return(false, 'Error: the account is restricted!');
  	
  	$result = parent::__get($mode)->enable();
  	
  	# execute function hook if successful
		if ($result) $this->{$mode}->hook('enable');
		
		return $this->_return($result);
  }
  
  protected function encode_exch_uac()
  /* sets the exchange uac value based on the configured local variables
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# preset uac to decimal zero
  	$uac = 0;
  	
  	# get the fields
  	$this->user->map_exch_uac($list);
  	
  	foreach($list as $hex=>$field) {
  		if ($field) { $uac += hexdec($hex); }
  	}
  	
  	$this->user->exch_uac = strval($uac);
  	
  	$this->_debug('encoded exchange uac: ' . $uac);
  	
  	return $this->_return(true);
  }
  
  protected function encode_password($password)
  /* accounts_ad extends the user_group::encode_password() function
   *
   */
  {
  	return $this->convert_to_unicode("\"$password\"");
  }
  
  protected function encode_uac()
  /* sets the uac value based on the configured local variables
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# preset uac to decimal zero
   	$uac = 0;
  	
  	# get the fields
  	$this->user->map_uac($list);
  	
  	foreach($list as $hex=>$field) {
  		if ($field) { $uac += hexdec($hex); }
  	}
  	
  	$this->user->uac = strval($uac);
  	
  	$this->_debug('encoded uac: ' . $uac);
  	
  	return $this->_return(true);
  }
  
  public function encode_x400()
  /* build the x400 string for this account
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	# sanity check
  	if (strpos($this->user_primary_type, '+') === false) return false;
  	$this->exch_x400 = $this->_x400_prefix . 'S=' . $this->ldap_escape($this->last) . ';G=' .
  			$this->ldap_escape($this->first) . ';';
  	if (strlen($this->middle) > 0) $this->exch_x400 .= 'I=' . $this->ldap_escape($this->middle) . ';';
  	return $this->exch_x400;
  }
  
  public function exists($match_string, $local_field = false)
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
   * this function adds a third parameter, object_class to allow searching for contacts
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	# ensure the search criteria is valid
  	if ((!is_string($match_string))||(strlen($match_string) == 0)) return $this->_return(false, '<strong>Warning</strong>: You did not provide any data to search for!');
  	
  	if ($local_field === false) {
  		if ($mode === 'group') { $local_field = 'gid'; } else { $local_field = 'uid'; }
  	}
  	
  	if (($mode === 'user')&&(func_num_args() > 2)) {
  		# get the requested object class
  		$object_class = func_get_arg(2);
  		# match a unique object of a specific type
  		$this->_debug("searching for object type $object_class on $local_field");
  		# build the filter
  		$filter = "(&(" . $this->{$mode}->local_2_ldap($local_field) . "=$match_string)(" . $this->{$mode}->local_2_ldap('class') .
  			'=' . str_replace('+', '', $object_class) . '))';
  	} else {
  		# match any unique object (no class filter)
  		$this->_debug("searching for object type * on $local_field");
  		# build the filter
  		$filter = "(&(" . $this->{$mode}->local_2_ldap($local_field) . "=$match_string)(" . $this->{$mode}->local_2_ldap('class') .
  			"=$mode))";
  	}
  	
  	# make sure we are still connected
  	if (!$this->_connector) return $this->_return(false, '<strong>Error:</strong> Lost connection to the directory server');
  	
  	# search for this account
  	$this->_debug('searching directory for the account matching "' . $match_string . '"');
  	$this->_debug("using search filter: $filter");
  	$search = @ldap_search($this->_connector, $this->_base_dn, $filter);
  	
  	if ($search === false) return $this->_return(false, 'no results were found');
  	
  	# if there was a result, return true
  	if (ldap_count_entries($this->_connector, $search) > 0) {
  		# execute function hook
  		$this->{$mode}->hook('exists');
  		return $this->_return(true, 'specified account exists');
  	} else {
  		return $this->_return(false, 'account does not exist');
  	}
  }
  
  protected function extract_cn($dn)
  /* extract and return a CN from a DN
   *
   * e.g. given "CN=Enterprise Admins,CN=Users,DC=test,DC=local"
   *			return "Enterprise Admins"
   *
   */
  {
  	preg_match('/^cn\=(.*?)(,(cn|dc|ou)\=.*).*/i', $dn, $match);
  	if (($match === false)||(count($match)<2)) return false;
  	return $match[1];
  }
  
  public function get_base_dn()
  /* return the current base dn for this object
   *
   */
  {
  	return $this->_base_dn;
  }
  
  public function get_domain()
  /* return the configured domain name
   *
   * use this wrapper function to prevent unauthorized modification of the protected variable
   *
   */
  {
  	return $this->_domain;
  }
  
 public function get_fields($mode_override = false)
  /* accounts_ad extends the user_group::get_fields() function
   *
   * adds optional ldap argument :: call get_fields(true) to get the ldap field list
   *   - ldap fields will not necessarily be returned in the same order as object fields
   *
   * adds optional check_type argument :: call get_fields('type') to get the field list
   *   for a specific sub-type
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	$list = parent::get_fields($mode);
  	$ldap = false;
  	$check_type = $mode;
  	
  	if (!is_array($list)) $list = array();
  	
  	# check for optional ldap mode qualifier
  	if (func_num_args() > 0) {
  		$arg = func_get_arg(0);
  		if (is_bool($arg)) {
  			$ldap = $arg;
  		} elseif (is_string($arg)) {
  			$check_type = $arg;
  		}
  	}
  	
  	# set the relative object type to return fields for
  	if (($mode === 'user')&&($this->user_loaded)) {
  		$mode = $this->user_primary_type;
  	}
  	
  	# return the field list
  	switch($check_type) {
  		case 'contact':
  			if ($ldap) {
  				$ad = array();
  			} else {
  				$ad = array('email', 'first', 'middle', 'last', 'name', 'unique_id',
  			              'ldap_object_class', 'title', 'department', 'organization',
  			              'room', 'building', 'phone', 'description', 'note', 'mobile',
  			              'pager', 'country', 'city', 'state', 'zip', 'office', 'address',
  			              'web_url', 'display_name', 'employee_id', 'co', 'custom1',
  			              'custom2', 'custom3', 'custom4', 'custom5', 'dn');
  			}
  			break;
  		case 'contact+':
  			if ($ldap) {
  				$ad = array();
  			} else {
  				$ad = array('email', 'first', 'middle', 'last', 'name', 'unique_id',
  			              'ldap_object_class', 'title', 'department', 'organization',
  			              'room', 'building', 'phone', 'description', 'note', 'mobile',
  			              'pager', 'country', 'city', 'state', 'zip', 'office', 'address',
  			              'web_url', 'display_name', 'employee_id', 'co', 'exch_addressbook',
  			              'exch_alias', 'exch_countrycode', 'exch_displaytype', 'exch_dtmfmap',
  			              'exch_email', 'exch_internetencoding', 'exch_moderationflags',
  			              'exch_provisioningflags', 'exch_transportflags', 'exch_version',
  			              'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'exch_legacydn',
  			              'exch_proxy', 'exch_policy_incl', 'exch_policy_excl', 'dn');
  			}
  			break;
  		case 'group':
  			if ($ldap) {
  				# note -- append ";range=0-1499" to member to retrieve the range of
  				#         0 - 1499 records (total 1500, which is the default max in 2003+)
  				$ad = array('cn', 'sAMAccountName', 'primaryGroupToken', 'objectGUID',
  				            'description', 'mail', 'info', 'groupType');
  				$ad[] = 'member;range=0-' . ($this->_block_size - 1);
  			} else {
  				$ad = array('name', 'uid', 'gid_number', 'unique_id', 'description', 'email',
  				            'member_field', 'note', 'type_field');
  			}
  			break;
  		case 'system':
  			if ($ldap) {
  				$ad = array('lockoutduration', 'lockoutthreshold');
  			} else {
  				$ad = array('lockout_duration', 'lockout_threshold');
  			}
  			break;
  		case 'user':
  			if ($ldap) {
  				$ad = array();
  			} else {
  				$ad = array('email', 'first', 'middle', 'last', 'name', 'uid', 'gid_number',
  			              'smb_drive', 'smb_script', 'principal', 'uac', 'uid_number', 'unique_id',
  			              'ldap_object_class', 'title', 'department', 'organization', 'room',
  			              'building', 'phone', 'description', 'note', 'home_dir', 'mobile', 'pager',
  			              'country', 'city', 'state', 'zip', 'office', 'address', 'web_url',
  			              'pw_last_set', 'display_name', 'employee_id', 'co', 'sid', 'sid_history',
  			              'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'lockout_time',
  			              'last_logon', 'bad_pw_time', 'dn');
  			}
  			break;
  		case 'user+':
  			if ($ldap) {
  				$ad = array();
  			} else {
  				$ad = array('email', 'first', 'middle', 'last', 'name', 'uid', 'gid_number',
  			              'smb_drive', 'smb_script', 'principal', 'uac', 'uid_number', 'unique_id',
  			              'ldap_object_class', 'title', 'department', 'organization', 'room',
  			              'building', 'phone', 'description', 'note', 'home_dir', 'mobile', 'pager',
  			              'country', 'city', 'state', 'zip', 'office', 'address', 'web_url',
  			              'pw_last_set', 'display_name', 'employee_id', 'co', 'sid', 'sid_history',
  			              'exch_addressbook', 'exch_alias', 'exch_countrycode', 'exch_displaytype',
  			              'exch_dtmfmap', 'exch_email', 'exch_internetencoding',
  			              'exch_moderationflags', 'exch_provisioningflags', 'exch_transportflags',
  			              'exch_version', 'exch_enabledflags2', 'exch_uac', 'custom1', 'custom2',
  			              'custom3', 'custom4', 'custom5', 'lockout_time', 'last_logon', 'bad_pw_time',
  			              'exch_legacydn', 'exch_proxy', 'exch_policy_incl', 'exch_policy_excl', 'dn');
  			}
  			break;
  		case 'user++':
  			if ($ldap) {
  				$ad = array();
  			} else {
  				# IMPORTANT: 'exch_proxy' must come after 'exch_400'
  				$ad = array('email', 'first', 'middle', 'last', 'name', 'uid', 'gid_number',
  			              'smb_drive', 'smb_script', 'principal', 'uac', 'uid_number', 'unique_id',
  			              'ldap_object_class', 'title', 'department', 'organization', 'room',
  			              'building', 'phone', 'description', 'note', 'home_dir', 'mobile', 'pager',
  			              'country', 'city', 'state', 'zip', 'office', 'address', 'web_url',
  			              'pw_last_set', 'display_name', 'employee_id', 'co', 'sid', 'sid_history',
  			              'exch_addressbook', 'exch_alias', 'exch_countrycode', 'exch_delegates',
  			              'exch_displaytype', 'exch_dtmfmap', 'exch_hidefromlists', 'exch_legacydn',
  			              'exch_mailquota', 'exch_mdb', 'exch_mdb_usedefaults', 'exch_mobileflags',
  			              'exch_mta', 'exch_overhardquotalimit', 'exch_overquotalimit',
  			              'exch_policy_incl', 'exch_policy_excl', 'exch_recipienttype',
  			              'exch_rolepolicy', 'exch_server', 'exch_textmessaging',
  			              'exch_userculture', 'exch_version', 'exch_uac', 'exch_x400', 'exch_proxy',
  			              'exch_enabledflags2', 'exch_moderationflags', 'exch_provisioningflags',
  			              'exch_transportflags', 'exch_mdb_rulesquota', 'exch_internetencoding',
  			              'exch_obj_version', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5',
  			              'lockout_time', 'last_logon', 'bad_pw_time', 'dn');
  			}
  			break;
  		default:
  			# error
  			$ad = array();
  			break;
  	}
  	
  	return array_merge($list, $ad);
  }
  
  public function get_new_pw_required()
  /* returns the value of the private new password required field
   *
   * since this value in AD is controlled by a combination of other variables,
   * 	accessing it through this function ensures clients have read-only access
   *	to the value
   *
   * use the toggle_new_pw_required() function to alter the value
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	return $this->user->new_pw_required;
  }
  
  protected function get_object_class($class = '')
  /* return the complete object class array for the specified class
   *
   * if no class is provided, return it for the loaded class, if none
   *  is loaded, return an empty array
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	if ( ($class == '') && ($mode == 'user') && ($this->user_primary_type != '') ) $class = $this->user_primary_type;
  	
  	switch(strtolower($class)) {
  		case 'contact':    $arr = array(0=>'top', 1=>'person', 2=>'organizationalPerson', 3=>'contact'); break;
  		case 'contact+':   $arr = array(0=>'top', 1=>'person', 2=>'organizationalPerson', 3=>'contact'); break;
  		case 'group':      die('get_object_class()'); break;
  		case 'user':       $arr = array(0=>'top', 1=>'person', 2=>'organizationalPerson', 3=>'user'); break;
  		case 'user+':      $arr = array(0=>'top', 1=>'person', 2=>'organizationalPerson', 3=>'user'); break;
  		case 'user++':     $arr = array(0=>'top', 1=>'person', 2=>'organizationalPerson', 3=>'user'); break;
  		default:           $arr = array(); break;
  	}
  	
  	return $this->_return($arr);
  }
  
  public function get_primary_group($guid)
  /* return the primary group guid for the provided object_guid
   *
   */
  {
  	# validate input
  	if ((!is_string($guid))||(strlen($guid) == 0)) return false;
  	
  	# set dn to search
  	$search_dn = $this->_base_dn;
  	
  	# set search filter
  	$filter = '(' . $this->local_2_ldap('object_guid') . "=$guid)";
  	
  	# get the primary group
  	$tmp = @ldap_search($this->_connector, $search_dn, $filter, array('primarygroupid'));
  	
  	# parse results
  	$results = @ldap_get_entries($this->_connector, $tmp);
  	
  	# make sure exactly one dn was returned
  	if ( ($results == false) || ($results['count'] != 1) ) return false;
  	
  	# set the dn
  	$primary_group_token = $results[0]['primarygroupid'][0];
  	
  	# find the group
  	//$tmp = $this->search(array('gid_number'=>$p));
  	/* the only way to do this is to search for all groups, then
  			iterate through each group loading them one by one and retrieving the
  			primaryGroupToken. the primaryGroupToken that matches $p is the users'
  			primary group.
  			
  			this seems like a huge waste of resources to do this without some sort
  			of caching, so i am not going to implement it yet
  			
  			instead i'm going to just add the number and indicate the group
  			should be looked up if necessary
  	*/
  	
  	# for now, assume 512 = Domain Admins && 513 = Domain Users (built-in)
  	#  see: http://en.wikipedia.org/wiki/Security_Identifier (Aug-12-2010 ws)
  	
  	# obviously this code is broken; we need to figure out if there are predefined, fixed
  	# guids for domain admins/users/etc (not likely) or define a better way to obtain
  	# the object_guid from the primaryGroupToken without enumerating all groups in the directory
  	
  	#return $this->dn2guid($object_dn);
  	return false;
  }
  
  public function get_sid()
  /* return the loaded object SID
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	return $this->{$mode}->sid;
  }
  
  public function guid2dn($guid)
  /* given an object_guid return the object dn or false
   *
   */
  {
  	$this->_debug_start();
  	if (((!is_string($guid))||(strlen($guid)==0))&&(!is_array($guid))) return $this->_return(false);
  	
  	if (is_array($guid)) {
  		$list = array();
  		$originals = array_values($guid);
  		if (is_integer($originals[0])) $originals = array_keys($guid);
  		for($i=0;$i<count($originals);$i++) $list[$i] = $this->guid2dn($originals[$i]);
  		return $this->_return($list);
  	}
  	
  	# build the ldap search filter
  	$guid = $this->ldap_escape($guid);
  	$filter = "(" . $this->user->local_2_ldap('unique_id') . "=$guid)";
  	$this->_debug("filter set: $filter");
  	
  	# search for this account
  	$this->_debug('searching directory for the account matching "' . $guid . '"');
  	$search = @ldap_search($this->_connector, $this->_base_dn, $filter);
  	
  	if ($search === false) return $this->_return(false, 'no results were found');
  	
  	# make sure there is one and only one result
  	if (@ldap_count_entries($this->_connector, $search) != 1) {
  		$this->_debug('Count: ' . @ldap_count_entries($this->_connector, $search));
  		return $this->_return(false, 'Error: result count was greater or less than one for the provided criteria');
  	}
  	
  	# get the search result
  	if (( $entry_id = ldap_first_entry($this->_connector, $search)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the object record!');
  	}
  	
  	# retrieve the object distinguished name
  	if (( $object_dn = ldap_get_dn($this->_connector, $entry_id)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the DN');
  	}
  	
  	return $this->_return($object_dn);
  }
  
  public function hook($fn_name, $debug_me = false)
  /* conditionally implements hooks for the specified function
   *
   */
  {
  	if ($debug_me) { $this->_debug_mode = 99; }
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	if (strlen($fn_name) == 0) return false;
  	$file = dirname(__FILE__) . '/' . $this->_hook_dir . '/' . $mode . '_' . $fn_name . '.php';
  	$this->_debug("attemping to execute hook for $fn_name");
  	if ( ($this->_enable_hooks) && (file_exists($file)) ) {
  		$this->_debug("executing hook: $file");
  		include $file;
  	}
  	
  	if (isset($message)) return $this->_return($message);
  	
  	return $this->_return(true);
  }
  
  public function identify_server()
  /* identify the connected server
   *
   * under development (not accurate at this point in time)
   *
   */
  {
  	# first identify whether this is openldap or microsoft AD
  	$res = @ldap_search($this->_connector, $this->_base_dn, '(cn=*)');
  	if ($res === false) return false;
  	if (@ldap_count_entries($this->_connector, $res) > 0) {
  		# ms ad
  		echo "Microsoft Active Directory<br />";
  	} else {
  		# openldap
  		echo "OpenLDAP<br />";
  	}
  	
  	
  	// msDS-Behavior-Version attribute in root dn; 1 = 2000, 2 = 2003, 3 = 2008, 4 = 2008 R2
  }
  
  protected function ldap_2_local($str)
  /* given an ldap variable, return the local equivilent
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	
  	# handle group member field variants
  	if (stripos($str, 'member;') !== false) return 'member_field';
  	
  	# group fields that are different from user
  	if ($mode === 'group') {
  		switch(strtolower($str)) {
  			case 'samaccountname':                        return 'gid'; break;
  		}
  	}
  	
  	switch (strtolower($str)) {
  		case 'badpasswordtime':                         return 'bad_pw_time'; break;
  		case 'buildingname':                            return 'building'; break;
  		case 'c':                                       return 'country'; break;
  		case 'cn':                                      return 'name'; break;
  		case 'co':                                      return 'co'; break;
  		case 'company':                                 return 'organization'; break;
  		case 'countrycode':                             return 'exch_countrycode'; break;
  		case 'department':                              return 'department'; break;
  		case 'description':                             return 'description'; break;
  		case 'displayname':                             return 'display_name'; break;
  		case 'dn':                                      return 'dn'; break;
  		case 'employeeid':                              return 'employee_id'; break;
  		case 'extensionattribute1':                     return 'custom1'; break;
  		case 'extensionattribute2':                     return 'custom2'; break;
  		case 'extensionattribute3':                     return 'custom3'; break;
  		case 'extensionattribute4':                     return 'custom4'; break;
  		case 'extensionattribute5':                     return 'custom5'; break;
  		case 'givenname':                               return 'first'; break;
  		case 'grouptype':                               return 'type_field'; break;
  		case 'homedirectory':                           return 'home_dir'; break;
  		case 'homedrive':                               return 'smb_drive'; break;
  		case 'homemdb':                                 return 'exch_mdb'; break;
  		case 'homemta':                                 return 'exch_mta'; break;
  		case 'info':                                    return 'note'; break;
  		case 'initials':                                return 'middle'; break;
  		case 'internetencoding':                        return 'exch_internetencoding'; break;
  		case 'l':                                       return 'city'; break;
  		case 'lastlogon':                               return 'last_logon'; break;
  		case 'legacyexchangedn':                        return 'exch_legacydn'; break;
  		case 'lockoutduration':                         return 'lockout_duration'; break;
  		case 'lockoutthreshold':                        return 'lockout_threshold'; break;
  		case 'lockouttime':                             return 'lockout_time'; break;
  		case 'mail':                                    return 'email'; break;
  		case 'mailnickname':                            return 'exch_alias'; break;
  		case 'mdboverhardquotalimit':                   return 'exch_overhardquotalimit'; break;
  		case 'mdboverquotalimit':                       return 'exch_overquotalimit'; break;
  		case 'mdbstoragequota':                         return 'exch_mailquota'; break;
  		case 'mdbusedefaults':                          return 'exch_mdb_usedefaults'; break;
  		case 'member':                                  return 'member_field'; break;
  		case 'mobile':                                  return 'mobile'; break;
  		case 'msexchalobjectversion':                   return 'exch_obj_version'; break;
  		case 'msexchhidefromaddresslists':              return 'exch_hidefromlists'; break;
  		case 'msexchhomeservername':                    return 'exch_server'; break;
  		case 'msexchmdbrulesquota':                     return 'exch_mdb_rulesquota'; break;
  		case 'msexchmobilemailboxflags':                return 'exch_mobileflags'; break;
  		case 'msexchmoderationflags':                   return 'exch_moderationflags'; break;
  		case 'msexchpoliciesincluded':                  return 'exch_policy_incl'; break;
  		case 'msexchpoliciesexcluded':                  return 'exch_policy_excl'; break;
  		case 'msexchprovisioningflags':                 return 'exch_provisioningflags'; break;
  		case 'msexchrbacpolicylink':                    return 'exch_rolepolicy'; break;
  		case 'msexchrecipientdisplaytype':              return 'exch_displaytype'; break;
  		case 'msexchrecipienttypedetails':              return 'exch_recipienttype'; break;
  		case 'msexchtextmessagingstate':                return 'exch_textmessaging'; break;
  		case 'msexchtransportrecipientsettingsflags':   return 'exch_transportflags'; break;
  		case 'msexchumenabledflags2':                   return 'exch_enabledflags2'; break;
  		case 'msexchumdtmfmap':                         return 'exch_dtmfmap'; break;
  		case 'msexchuseraccountcontrol':                return 'exch_uac'; break;
  		case 'msexchuserculture':                       return 'exch_userculture'; break;
  		case 'msexchversion':                           return 'exch_version'; break;
  		case 'objectclass':                             return 'ldap_object_class'; break;
  		case 'objectguid':                              return 'unique_id'; break;
  		case 'objectsid':                               return 'sid'; break;
  		case 'pager':                                   return 'pager'; break;
  		case 'physicaldeliveryofficename':              return 'office'; break;
  		case 'postalcode':                              return 'zip'; break;
  		case 'primarygroupid':                          return 'gid_number'; break;
  		case 'primarygrouptoken':                       return 'gid_number'; break;
  		case 'proxyaddresses':                          return 'exch_proxy'; break;
  		case 'publicdelegatesbl':                       return 'exch_delegates'; break;
  		case 'pwdlastset':                              return 'pw_last_set'; break;
  		case 'roomnumber':                              return 'room'; break;
  		case 'samaccountname':                          return 'uid'; break;
  		case 'scriptpath':                              return 'smb_script'; break;
  		case 'showinaddressbook':                       return 'exch_addressbook'; break;
  		case 'sidhistory':                              return 'sid_history'; break;
  		case 'sn':                                      return 'last'; break;
  		case 'st':                                      return 'state'; break;
  		case 'streetaddress':                           return 'address'; break;
  		case 'targetaddress':                           return 'exch_email'; break;
  		case 'telephonenumber':                         return 'phone'; break;
  		case 'textencodedoraddress':                    return 'exch_x400'; break;
  		case 'title':                                   return 'title'; break;
  		case 'uidnumber':                               return 'uid_number'; break;
  		case 'useraccountcontrol':                      return 'uac'; break;
  		case 'userprincipalname':                       return 'principal'; break;
  		case 'wwwhomepage':                             return 'web_url'; break;
  		default:												            		return false;	break;
  	}
  }
  
  protected function ldap_escape($str)
  /* return an escaped ldap string
   *
   * see: RFC2254
   *
   * source: http://php.net/manual/en/function.ldap-search.php
   *
   */
  {
  	$metaChars = array('\\', '(', ')', '#', '*');
  	$quotedMetaChars = array();
  	foreach ($metaChars as $value) $quotedMetaChars[] = '\\'.dechex(ord($value));
  	$metaChars[] = "\0"; $quotedMetaChars[] = "\\00";
  	$str=str_replace($metaChars,$quotedMetaChars,$str); //replace them
  	return ($str);
  }
  
  public function load($match_string, $local_field = false, $debug_me = false)
  /* accounts_ad extends the user_group::load() function
   *
   */
  {
  	if ($debug_me) $this->_debug_mode = $debug_me;
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	# ensure the search criteria is valid
  	if (!is_string($match_string)) return $this->_return(false, '<strong>Warning</strong>: Invalid search criteria provided. Match value was not a string.');
  	if (strlen(trim($match_string)) == 0) return $this->_return(false, '<strong>Warning</strong>: You did not provide any data to search for!');
  	
  	# remove any white space
  	if ($local_field !== 'unique_id') $match_string = $this->ldap_escape(trim($match_string));
  	
  	# make sure the connection to the ldap server is still active
  	if (! $this->_connector) return $this->_return(false, 'Error: lost connection to the directory server');
  	
  	$this->var_mode = $mode;
  	if (parent::load($match_string, $local_field, $debug_me) === false) return $this->_return(false, 'Load error');
  	
  	# attempt to extract the working organizational unit for this object
  	$p1 = strpos($this->{$mode}->dn, ',') + 1;
  	$p2 = stripos($this->{$mode}->dn, $this->_base_dn);
  	while ( ($p1 !== false) && ($p2 !== false) && ($p1 < $p2) ) {
  		$tmp = substr($this->{$mode}->dn, $p1, ($p2 - $p1 - 1));
  		if (substr($tmp, 2, 1) == '=') {
  			$this->{$mode.'_working_ou'} = $tmp;
  			$this->_debug('set working ou: ' . $this->{$mode.'_working_ou'});
  			break;
  		} else {
  			# found a comma in the CN, try the next comma
  			$p1 = strpos(substr($this->{$mode}->dn, $p1), ',') + $p1 + 1;
  		}
  	}
  	
  	# verify results
  	if (strlen($this->{$mode.'_working_ou'}) == 0) {
  		$this->_debug('error extracting working ou, the base dn was not found in the loaded dn');
  		$this->{$mode.'_working_ou'} = $this->_base_ou;
  	}
  	
  	# set the internal primary object type and load class specific data
  	$tmp = array_flip($this->{$mode}->ldap_object_class);
  	if (isset($tmp['user'])) {
  		# check for exchange fields
  		if ($this->_enable_exchange && isset($this->exch_alias) && (strlen($this->exch_alias) > 0)) {
  			if (isset($this->exch_mdb) && (strlen($this->exch_mdb) > 0)) {
  				$this->{$mode}->type('user++');
  			} else {
  				$this->{$mode}->type('user+');
  			}
  			# decode exchange uac value
  			$this->{$mode}->parse_exch_uac();
  		} else {
  			$this->{$mode}->type('user');
  		}
  		# decode uac value
  		$this->{$mode}->parse_uac();
  		# get the new_pw_required value
  		if ( ($this->{$mode}->pw_last_set == 0) && ($this->{$mode}->pw_never_expires == false) ) $this->{$mode}->new_pw_required = true;
  	} elseif (isset($tmp['contact'])) {
  		# check for exchange fields
  		if ($this->_enable_exchange && isset($this->{$mode}->exch_email) && (strlen($this->{$mode}->exch_email) > 0)) {
  			$this->{$mode}->type('contact+');
  		} else {
  			$this->{$mode}->type('contact');
  		}
  	} elseif (isset($tmp['group'])) {
  		# group specific fields
  		$this->{$mode}->type();
  		
  	} else {
  		$this->{$mode}->type();
  	}
  	
  	# if exchange account, set the mask field values
  	if (($mode === 'user')&&(strpos($this->user_primary_type, '+') !== false)) {
  		if ($this->{$mode}->exch_mdb_usedefaults == 'TRUE') { $this->{$mode}->exch_inheritquotas = true; } else { $this->{$mode}->exch_inheritquotas = false; }
  		if ($this->{$mode}->exch_hidefromlists == 'TRUE') { $this->{$mode}->exch_hidden = true; } else { $this->{$mode}->exch_hidden = false; }
  	}
  	
  	if ($mode === 'user') $this->_debug('set primary object class: ' . $this->user_primary_type);
  	
  	# execute the hook
  	$this->{$mode}->hook('load');
  	
  	return $this->_return(true, 'successfully loaded object');
  }
  
  protected function local_2_ldap($str)
  /* given a local variable, return the ldap equivilent
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	
  	# group fields that are different from user
  	if ($mode === 'group') {
  		switch(strtolower($str)) {
  			case 'dn':                    return 'distinguishedname'; break;
  			case 'gid_number':            return 'primarygrouptoken'; break;
  		}
  	}
  	
  	switch (strtolower($str))
  	{
  		# group
  		case 'gid':                     return 'samaccountname'; break;
  		case 'member_field':            return 'member'; break;
  		case 'type_field':              return 'grouptype'; break;
  		
  		# contact, group, & user
  		case 'address':                 return 'streetaddress'; break;
  		case 'bad_pw_time':             return 'badpasswordtime'; break;
  		case 'city':                    return 'l'; break;
  		case 'co':                      return 'co'; break;
  		case 'country':                 return 'c'; break;
  		case 'custom1':                 return 'extensionattribute1'; break;
  		case 'custom2':                 return 'extensionattribute2'; break;
  		case 'custom3':                 return 'extensionattribute3'; break;
  		case 'custom4':                 return 'extensionattribute4'; break;
  		case 'custom5':                 return 'extensionattribute5'; break;
  		case 'department':              return 'department'; break;
  		case 'description':             return 'description'; break;
  		case 'display_name':            return 'displayname'; break;
  		case 'email':                   return 'mail'; break;
  		case 'employee_id':             return 'employeeid'; break;
  		case 'first':                   return 'givenname'; break;
  		case 'last':                    return 'sn'; break;
  		case 'last_logon':              return 'lastlogon'; break;
  		case 'ldap_object_class':       return 'objectclass'; break;
  		case 'lockout_duration':        return 'lockoutduration'; break;
  		case 'lockout_threshold':       return 'lockoutthreshold'; break;
  		case 'lockout_time':            return 'lockouttime'; break;
  		case 'middle':                  return 'initials'; break;
  		case 'mobile':                  return 'mobile'; break;
  		case 'name':                    return 'cn'; break;
  		case 'note':                    return 'info'; break;
  		case 'office':                  return 'physicaldeliveryofficename'; break;
  		case 'organization':            return 'company'; break;
  		case 'pager':                   return 'pager'; break;
  		case 'phone':                   return 'telephonenumber'; break;
  		case 'pwd_last_set':            return 'pwdlastset'; break;
  		case 'state':                   return 'st'; break;
  		case 'title':                   return 'title'; break;
  		case 'unique_id':               return 'objectguid'; break;
  		case 'web_url':                 return 'wwwhomepage'; break;
  		case 'zip':                     return 'postalcode'; break;
  		
  		# user specific
  		case 'gid_number':              return 'primarygroupid'; break;
  		case 'home_dir':                return 'homedirectory'; break; // alias for smb_home
  		case 'password':                return 'unicodepwd'; break;
  		case 'principal':               return 'userprincipalname'; break;
  		case 'pw_last_set':             return 'pwdlastset'; break;
  		case 'sid':                     return 'objectsid'; break;
  		case 'sid_history':             return 'sidhistory'; break;
  		case 'smb_drive':               return 'homedrive'; break;
  		case 'smb_home':                return 'homedirectory'; break;
  		case 'smb_script':              return 'scriptpath'; break;
  		case 'uac':                     return 'useraccountcontrol'; break;
  		case 'uid':                     return 'samaccountname'; break;
  		case 'uid_number':              return 'uidnumber'; break;
  		
  		# contact specific
  		
  		# exchange user specific
  		case 'exch_addressbook':        return 'showinaddressbook'; break;
  		case 'exch_alias':              return 'mailnickname'; break;
  		case 'exch_countrycode':        return 'countrycode'; break;
  		case 'exch_delegates':          return 'publicdelegatesbl'; break;
  		case 'exch_displaytype':        return 'msexchrecipientdisplaytype'; break;
  		case 'exch_dtmfmap':            return 'msexchumdtmfmap'; break;
  		case 'exch_email':              return 'targetaddress'; break;
  		case 'exch_enabledflags2':      return 'msexchumenabledflags2'; break;
  		case 'exch_hidefromlists':      return 'msexchhidefromaddresslists'; break;
  		case 'exch_internetencoding':   return 'internetencoding'; break;
  		case 'exch_legacydn':           return 'legacyexchangedn'; break;
  		case 'exch_mailquota':          return 'mdbstoragequota'; break;
  		case 'exch_mdb':                return 'homemdb'; break;
  		case 'exch_mdb_rulesquota':     return 'msexchmdbrulesquota'; break;
  		case 'exch_mdb_usedefaults':    return 'mdbusedefaults'; break;
  		case 'exch_mobileflags':        return 'msexchmobilemailboxflags'; break;
  		case 'exch_moderationflags':    return 'msexchmoderationflags'; break;
  		case 'exch_mta':                return 'homemta'; break;
  		case 'exch_obj_version':        return 'msexchalobjectversion'; break;
  		case 'exch_overhardquotalimit': return 'mdboverhardquotalimit'; break;
  		case 'exch_overquotalimit':     return 'mdboverquotalimit'; break;
  		case 'exch_policy_incl':        return 'msexchpoliciesincluded'; break;
  		case 'exch_policy_excl':        return 'msexchpoliciesexcluded'; break;
  		case 'exch_provisioningflags':  return 'msexchprovisioningflags'; break;
  		case 'exch_proxy':              return 'proxyaddresses'; break;
  		case 'exch_recipienttype':      return 'msexchrecipienttypedetails'; break;
  		case 'exch_rolepolicy':         return 'msexchrbacpolicylink'; break;
  		case 'exch_server':             return 'msexchhomeservername'; break;
  		case 'exch_textmessaging':      return 'msexchtextmessagingstate'; break;
  		case 'exch_transportflags':     return 'msexchtransportrecipientsettingsflags'; break;
  		case 'exch_uac':                return 'msexchuseraccountcontrol'; break;
  		case 'exch_userculture':        return 'msexchuserculture'; break;
  		case 'exch_version':            return 'msexchversion'; break;
  		case 'exch_x400':               return 'textencodedoraddress'; break;
  		
  		# exchange contact specific
  		
  		# internal only fields
  		case 'class':                   return 'objectclass'; break;
  		case 'dn':                      return 'dn'; break;
  		case 'building':                return 'buildingname'; break; // building is not used in AD; use office instead
  		case 'room':                    return 'roomnumber'; break; // room is not used in AD; use office instead
  		
  		default:                        return $str; break;
  	}
  }
  
  protected function map_exch_uac(&$arr)
  /* return an array connecting the AD EXCHANGE UAC HEX values (keys) to this
   *  object's corresponding variables (values)
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	$arr = array(
  		'0x00000002'=>&$this->user_data['exch_disabled'],
  		);
  	return true;
  }
  
  protected function map_uac(&$arr)
  /* return an array connecting the AD UAC HEX values (keys) to this object's 
   *	corresponding varibles (values)
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	$arr = array(
  		'0x01000000'=>&$this->user_data['delegation_auth_trust'],
  		'0x00800000'=>&$this->user_data['password_expired'],
  		'0x00400000'=>&$this->user_data['preauth_not_required'],
  		'0x00200000'=>&$this->user_data['des_key_only'],
  		'0x00100000'=>&$this->user_data['disallow_delegation'],
  		'0x00080000'=>&$this->user_data['delegation_trust'],
  		'0x00040000'=>&$this->user_data['smartcard_required'],
  		'0x00020000'=>&$this->user_data['mns_logon'],
  		'0x00010000'=>&$this->user_data['pw_never_expires'],
  		'0x00008000'=>&$this->user_data['hex_8000'],
  		'0x00004000'=>&$this->user_data['hex_4000'],
  		'0x00002000'=>&$this->user_data['server_trust'],
  		'0x00001000'=>&$this->user_data['workstation_trust'],
  		'0x00000800'=>&$this->user_data['interdomain_trust'],
  		'0x00000200'=>&$this->user_data['normal'],
  		'0x00000100'=>&$this->user_data['duplicate'],
  		'0x00000080'=>&$this->user_data['pw_encryption'],
  		'0x00000040'=>&$this->user_data['pw_locked'],
  		'0x00000020'=>&$this->user_data['pw_not_required'],
  		'0x00000010'=>&$this->user_data['locked'],
  		'0x00000008'=>&$this->user_data['home_required'],
  		'0x00000002'=>&$this->user_data['disabled'],
  		'0x00000001'=>&$this->user_data['script_enabled']
  		);
  	return true;
  }
  
  public function members($guid, $include_descendants = false, $recursive = false)
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
  {
  	$this->_debug_start();
  	if ((!is_string($guid))||(strlen($guid)==0)) return $this->_return(false, 'invalid guid');
  	if ($recursive === false) $this->tmp_cache = array();
  	
  	# make sure the group exists
  	if (!$this->group->exists($guid, 'unique_id')) return $this->_return(false, 'group does not exist');
  	
  	# build the ldap search filter
  	$filter = "(" . $this->group->local_2_ldap('unique_id') . "=$guid)";
  	$this->_debug("filter set: $filter");
  	
  	# search for this account
  	$this->_debug('searching directory for the group matching "' . $guid . '"');
  	$search = @ldap_search($this->_connector, $this->_base_dn, $filter);
  	
  	if ($search === false) return $this->_return(false, 'no results were found');
  	
  	# make sure there is one and only one result
  	if (@ldap_count_entries($this->_connector, $search) != 1) {
  		$this->_debug('Count: ' . @ldap_count_entries($this->_connector, $search));
  		return $this->_return(false, 'Error: result count was greater or less than one for the provided criteria');
  	}
  	
  	# get the search result
  	if (( $entry_id = ldap_first_entry($this->_connector, $search)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the object record!');
  	}
  	
  	# retrieve the object distinguished name
  	if (( $object_dn = ldap_get_dn($this->_connector, $entry_id)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the DN');
  	}
  	$this->_debug("group dn: $object_dn");
  	
  	# set the search filter and return values
  	$filter = '(objectclass=*)';
  	$fields = array('member;range=0-' . ($this->_block_size - 1), $this->group->local_2_ldap('gid_number'));
  	
  	# run ldap query
  	$this->_debug('reading object record from the directory');
  	$results = @ldap_read($this->_connector, $object_dn, $filter, $fields);
  	
  	# extract results into entry array
  	$this->_debug('extracting record into an array');
  	$entry = @ldap_get_entries($this->_connector, $results);
  	$mfield = $this->group->local_2_ldap('member_field');
  	if (array_key_exists($mfield, $entry[0])) {
  		$list = $entry[0][$mfield];
  	} elseif (array_key_exists($mfield . ';range=0-' . ($this->_block_size - 1), $entry[0])) {
  		$list = $entry[0][$mfield . ';range=0-' . ($this->_block_size - 1)];
  	} elseif (array_key_exists($mfield . ';range=0-*', $entry[0])) {
  		$list = $entry[0][$mfield . ';range=0-*'];
  	} else {
  		$list = array();
  	}
  	
  	# remove the count key
  	unset($list['count']);
  	
  	# make sure all group members are loaded
  	$this->_debug('validating initial member count (' . count($list) . ')... ', true);
  	if (count($list) == $this->_block_size) {
  		# load more members
  		$next = 0;
  		while ((count($list)%$this->_block_size)==0) {
  			$next += $this->_block_size;
  			# run ldap query
  			$results = @ldap_read($this->_connector, $object_dn, $filter, array("member;range=$next-" . ($next + $this->_block_size - 1)));
  			# verify results
  			if ($results === false) {
  				$this->_debug('Unable to read group members');
  				break;
  			}
  			# extract results into entry array
  			$entry = @ldap_get_entries($this->_connector, $results);
  			# append results to member variable
  			$this->_debug("loading additional members... $next");
  			if (!is_array($entry[0])) {
  				$this->_debug('ERROR: LDAP result was not an array');
  				break;
  			}
  			if (array_key_exists("member;range=$next-*", $entry[0])) {
  				# this is the last result set
  				$add = $entry[0]["member;range=$next-*"];
  			} else {
  				$add = $entry[0]["member;range=$next-" . ($next + $this->_block_size - 1)];
  			}
  			# remove the count key
  			unset($add['count']);
  			# append the new members
  			$list = @array_merge($list, $add);
  		}
  	} else {
  		$this->_debug('done');
  	}
  	
  	# retrieve members from primary group id (distinct from the member field in ms AD)
  	#
  	# for now we'll include these members in the normal, non-descendant look up.
  	# it would be prudent to review this process in the future and possibly only include
  	# these members in a descedant lookup since they are not technically the same type of
  	# group member and depending on the application design, including them here could
  	# result in unexpected behavior.
  	#
  	# a compromise might be to include a system variable on which method to use to
  	# include these types of group members.
  	#
  	# ** COMMENTING THIS CODE OUT since it does not work **
  	#$this->_debug('locating any members with this as their primary group');
  	#$filter = '(primarygroupid=' . $entry[0][$this->group->local_2_ldap('gid_number')] . ')';
  	#$results = @ldap_search($this->_connector, $this->_base_dn, $filter, array('dn'));
  	#$add = @ldap_get_entries($this->_connector, $results);
  	#if (array_key_exists('count', $add)) unset($add['count']);
  	# append the new members
  	#if (is_array($add)) $list = array_merge($list, $add);
  	
  	# take the membership list and create the return array
  	$r = array();
  	if ($recursive) { $flag = 1; } else { $flag = 0; $this->tmp_cache = $list; }
  	 
  	# check inheritance flag
  	if ($include_descendants) {
  		for ($i=0;$i<count($list);$i++) {
  			$mguid = $this->dn2guid($list[$i]);
  			if ($mguid === false) continue;
  			$r[$mguid] = $flag;
  			if (($mguid!=$guid)&&$this->group->exists($mguid, 'unique_id')) {
  				# avoid recursive loops
  				if ($recursive&&(in_array($mguid, $this->tmp_cache))) continue;
  				$this->_debug('calling recursive members');
  				$desclist = $this->members($mguid, true, true);
  				$r = array_merge($r, $desclist);
  				$list = array_merge($list, array_keys($desclist));
  			}
  		}
  	} else {
  		for ($i=0;$i<count($list);$i++) {
  			$mguid = $this->dn2guid($list[$i]);
  			if ($mguid === false) continue;
  			$r[$mguid] = $flag;
  		}
  	}
  	
  	# conditionally update the cache results
  	if (!$recursive) { $this->tmp_cache = $list; }
  	
  	return $this->_return($r, 'Returning array with ' . count($r) . ' members');
  }
  
  public function membership($guid, $include_inherited = false, $recursive = false)
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
  {
  	# enable debugging for this function
  	$this->_debug_start();
  	
  	# input validation
  	if ((!is_string($guid))||(strlen($guid)==0)) return $this->_return(false, 'Invalid Search GUID');
  	if ((!$this->group->exists($guid, 'unique_id'))&&(!$this->user->exists($guid, 'unique_id'))) return $this->_return(false, 'Invalid GUID');
  	if ($include_inherited !== true) $include_inherited = false;
  	if ($recursive !== true) $recursive = false;
  	
  	# get the object_dn
  	$object_dn = $this->guid2dn($guid);
  	if ($object_dn === false) return $this->_return(false, 'Unable to retrieve object dn (despite object exists): PC LOAD LETTER');
  	
  	# set the search base
  	$search_dn = $this->_base_dn;
  	$this->_debug("set search_dn: $search_dn");
  	
  	# set the search filter
  	$filter = '(&(' . $this->group->local_2_ldap('member_field') . '=' . $this->ldap_escape($object_dn) . ')(' . $this->group->local_2_ldap('class') . '=group))';
  	$this->_debug("set search filter: $filter");
  	
  	# execute ldap query
  	$tmp = @ldap_search($this->_connector, $search_dn, $filter, array($this->user->local_2_ldap('unique_id')));
  	
  	# parse results
  	$results = @ldap_get_entries($this->_connector, $tmp);
  	$this->_debug('found ' . count($results) . ' results');
  	
  	# prepare result list
  	$list = array();
  	if ($recursive) { $flag = 1; } else { $flag = 0; }
  	
  	if ($results !== false) {
  		# parse results to extract group names
  		for ($i=0;$i<$results['count'];$i++) {
  			$list[$results[$i][$this->user->local_2_ldap('unique_id')][0]] = $flag;
  		}
  	}
  	
  	# now do the same for the users' primary group (if any)
  	if ($p = $this->get_primary_group($guid)) $list[$p] = $flag;
  	
  	# take the group list and create the return array
  	$r = array();
  	if (!$recursive) $this->tmp_cache =& $list;
  	
  	# check inheritance flag
  	if ($include_inherited) {
  		foreach($list as $mguid=>$mflag) {
  			$r[$mguid] = $flag;
  			if ($mguid!=$guid) {
  				# avoid recursive loops
  				if ($recursive&&(array_key_exists($mguid, $this->tmp_cache))) continue;
  				$this->_debug('calling recursive parents');
  				$plist = $this->membership($mguid, true, true);
  				if (is_array($plist)) $r = array_merge($r, $plist);
  			}
  		}
  	}
  	
  	return $this->_return($r);
  }
  
  protected function parse_exch_uac()
  /* sets internal variables based on the value of the Exchange UAC from the
   *  directory
   *
   * returns false on error
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# get the configured uac value; if none is set this will evaulate to 0 which should return the correct result
  	$uac = intval($this->user->exch_uac);
  	
  	# initially set all uac fields to false
  	$this->user->reset_exch_uac_fields();
  	
  	$this->user->map_exch_uac($list);
  	
  	foreach($list as $hex=>&$field)
  	{
  		if ($uac >= hexdec($hex))
  		{
  			$this->_debug("setting $hex = true");
  			$field = true;
  			$uac -= hexdec($hex);
  		}
  	}
  	
  	# check for error; this should never happen if the above section is coded properly
  	if ($uac > 0) return $this->_return(false, 'Undefined error in function parse_exch_uac()');
  	
  	return $this->_return(true);
  }
  
  protected function parse_uac()
  /* sets internal variables based on the value of the UAC from the directory 
   *
   * returns false on error
   *
   */
  {	
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# get the configured uac value; if none is set this will evaulate to 0 which should return the correct result
  	$uac = intval($this->user->uac);
  	
  	# initially set all uac fields to false
  	$this->user->reset_uac_fields();
  	
  	$this->user->map_uac($list);
  	
  	foreach($list as $hex=>&$field)
  	{
  		if ($uac >= hexdec($hex))
  		{
  			$this->_debug("setting $hex = true");
  			$field = true;
  			$uac -= hexdec($hex);
  		}
  	}
  	
  	# check for error; this should never happen if the above section is coded properly
  	if ($uac > 0) return $this->_return(false, 'Undefined error in function parse_uac()');
  	
  	return $this->_return(true);
  }
  
  protected function reset_exch_uac_fields()
  /* reset all of the internal exchange uac fields to false
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	$this->user->exch_disabled = false;
  	return true;
  }
  
  protected function reset_uac_fields()
  /* reset all of the internal uac fields to false
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	
  	$this->user->delegation_auth_trust = false;
  	$this->user->password_expired = false;
  	$this->user->preauth_not_required = false;
  	$this->user->des_key_only = false;
  	$this->user->disallow_delegation = false;
  	$this->user->delegation_trust = false;
  	$this->user->smartcard_required = false;
  	$this->user->mns_logon = false;
  	$this->user->pw_never_expires = false;
  	$this->user->hex_8000 = false;
  	$this->user->hex_4000 = false;
  	$this->user->server_trust = false;
  	$this->user->workstation_trust = false;
  	$this->user->interdomain_trust = false;
  	$this->user->normal = false;
  	$this->user->duplicate = false;
  	$this->user->pw_encryption = false;
  	$this->user->pw_locked = false;
  	$this->user->pw_not_required = false;
  	$this->user->locked = false;
  	$this->user->home_required = false;
  	$this->user->disabled = false;
  	$this->user->script_enabled = false;
  	
  	return true;
  }
  
  public function remove_member($guid, $type = 'user')
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
  {
  	return false;
  }
  
  protected function retrieve($guid)
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ((!is_string($guid))||(strlen($guid)==0)) return false;
  	$guid = $this->ldap_escape($guid);
  	
  	# load the field list
  	$fields = $this->{$mode}->get_fields();
  	
  	# build the ldap search filter
  	$filter = "(" . $this->{$mode}->local_2_ldap('unique_id') . "=$guid)";
  	$this->_debug("filter set: $filter");
  	
  	# search for this account
  	$this->_debug('searching directory for the account matching "' . $guid . '"');
  	$search = @ldap_search($this->_connector, $this->_base_dn, $filter);
  	
  	if ($search === false) return $this->_return(false, 'no results were found');
  	
  	# make sure there is one and only one result
  	if (@ldap_count_entries($this->_connector, $search) != 1) {
  		$this->_debug('Count: ' . @ldap_count_entries($this->_connector, $search));
  		return $this->_return(false, 'Error: result count was greater or less than one for the provided criteria');
  	}
  	
  	# get the search result
  	if (( $entry_id = ldap_first_entry($this->_connector, $search)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the object record!');
  	}
  	
  	# retrieve the object distinguished name
  	if (( $object_dn = ldap_get_dn($this->_connector, $entry_id)) === false) {
  		return $this->_return(false, 'Error: unable to retrieve the DN');
  	}
  	
  	# set internal object dn variable to fetched dn
  	$this->{$mode}->dn = $object_dn;
  	$this->_debug('set dn: ' . $this->{$mode}->dn);
  	
  	# run ldap query
  	$this->_debug('reading object record from the directory');
  	$results = @ldap_read($this->_connector, $this->{$mode}->dn, '(objectclass=*)', array('*'));
  	
  	# extract results into entry array
  	$this->_debug('extracting record into an array');
  	$entry = @ldap_get_entries($this->_connector, $results);
  	
  	# parse results into object variables
  	$this->_debug('loading object data... ', true);
  	$data = array();
  	
  	# load record
  	foreach($entry[0] as $key=>&$value) {
  		$this->var_mode = $mode;
  		$tmp = $this->ldap_2_local($key);
  		if ($tmp) {
  			if (is_array($value) && isset($value['count'])) unset($value['count']);
  			if (is_array($value) && (count($value) == 1)) {
  				$data[$tmp] = $value[0];
  			} else {
  				$data[$tmp] = $value;
  			}
  		}
  	}
  	
  	# handle group membership
  	if ($mode === 'group') {
  		$data['member_field'] = $this->members($data['unique_id']);
  		$this->_debug('loaded ' . count($data['member_field']) . ' group members');
  	}
  	
  	$this->_debug('done');
  	
  	return $this->_return($data);
  }
  
  public function search($criteria, $multiple_results = false, $partial_match = false, $comparison_type = 'AND')
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
   * this function adds a fifth parameter, object_class to allow searching for contacts
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode === 'group') { $id = 'gid'; $oppmode = 'user'; } else { $id = 'uid'; $oppmode = 'group'; }
  	
  	# check for the optional object_class parameter
  	if (func_num_args() > 4) {
  		$object_class = func_get_arg(4);
  	} else {
  		$object_class = $mode;
  	}
  	
  	# ensure we have criteria to check
  	if ( (!is_array($criteria)) || (count($criteria) < 1) ) return $this->_return(false, 'Error: invalid search criteria!');
  	
  	if (is_array($object_class)) {
  		$tmp = "(!(objectclass=computer))(!(objectclass=$oppmode))";
  		for($i=0;$i<count($object_class);$i++) $tmp .= '(objectclass=' . $object_class[$i] . ')';
  		$criteria[$tmp] = null;
  	} elseif (strlen($object_class) == 0) {
  		$criteria["(!(objectclass=computer))(!(objectclass=$oppmode))"] = null;
  	} else {
  		$criteria["(!(objectclass=computer))(!(objectclass=$oppmode))(objectclass=$object_class)"] = null;
  	}
  	
  	if (isset($criteria['unique_id'])) $criteria['unique_id'] = $this->ldap_escape($criteria['unique_id']);
  	
  	# set dn to search
  	$search_dn = $this->_base_dn;
  	$this->_debug("search dn set: $search_dn");
  	
  	$filter = array();
  	
  	# set search filter
  	foreach($criteria as $c=>$v) {
  		# replace invalid characters
  		$v = str_replace(')', '\)', $v);
  		$v = str_replace('(', '\(', $v);
  		
  		if ( ($partial_match) && ($v != '*') && (strpos($c, 'class') === false ) )
  		{
  			$filter[] = "(" . $this->{$mode}->local_2_ldap($c) . "=*$v*)";
  		} elseif(strpos($c, '(') !== false) {
  			$filter[] = $c . $v;
  		} elseif(strpos($v, '=') !== false) {
  			$filter[] = "(" . $this->{$mode}->local_2_ldap($c) . "$v)";
  		} elseif(strtolower('' . $v) == 'null') {
  			$filter[] = "(!" . $this->{$mode}->local_2_ldap($c) . "=*)";
  		} else {
  			$filter[] = "(" . $this->{$mode}->local_2_ldap($c) . "=$v)";
  		}
  	}
  	
  	# validate filter
  	if (count($filter) == 1) {
  		# one item to search for
  		$filter = $filter[0];
  	} elseif (count($filter) > 1) {
  		# prepend open parenthesis and operator for multiple criteria
  		switch (strtoupper($comparison_type)) {
  			case 'OR':		$filter = '(|' . implode('', $filter) . ')'; break;
  			case 'NOT':		$filter = '(!' . implode('', $filter) . ')'; break;
  			default:			$filter = '(&' . implode('', $filter) . ')'; break;
  		}
  	} else {
  		# this should never happen due to the "default" in the case and criteria check
  		return $this->_return(false, 'A severe error has occurred. Invalid logic in function. Please contact the developer.');
  	}
  	$this->_debug("filter set: $filter");
  	
  	# define the return fields
  	if ($mode === 'group') {
  		$return_fields = array($this->{$mode}->local_2_ldap('gid'), $this->{$mode}->local_2_ldap('gid_number'),
  		                       $this->{$mode}->local_2_ldap('unique_id'), $this->{$mode}->local_2_ldap('name'),
  		                       $this->{$mode}->local_2_ldap('dn'), $this->{$mode}->local_2_ldap('ldap_object_class'));
  	} else {
  		$return_fields = array($this->{$mode}->local_2_ldap('uid'), $this->{$mode}->local_2_ldap('uid_number'),
  		                       $this->{$mode}->local_2_ldap('unique_id'), $this->{$mode}->local_2_ldap('name'),
  		                       $this->{$mode}->local_2_ldap('dn'), $this->{$mode}->local_2_ldap('ldap_object_class'),
  		                       $this->{$mode}->local_2_ldap('email'));
  	}
  	
  	
  	
  	# execute ldap query
  	$tmp = @ldap_search($this->_connector, $search_dn, $filter, $return_fields, 0, 0);
  	
  	# prepare return array
  	$arr = array();
  	
  	if (@ldap_count_entries($this->_connector, $tmp) > 0) {
  		$entry = @ldap_first_entry($this->_connector, $tmp);
  		while ($entry !== false) {
  			$attrs = array_change_key_case(ldap_get_attributes($this->_connector, $entry), CASE_LOWER);
  			$a = array();
  			for ($i=0;$i<count($return_fields);$i++){
  				if ($this->{$mode}->ldap_2_local($return_fields[$i]) === 'dn') {
  					$a['dn'] = ldap_get_dn($this->_connector, $entry);
  					continue;
  				}
  				if ($this->{$mode}->ldap_2_local($return_fields[$i]) === 'unique_id') {
  					$v = ldap_get_values_len($this->_connector, $entry, $return_fields[$i]);
  				}
  				if (! array_key_exists($return_fields[$i], $attrs)) {
  					$a[$this->{$mode}->ldap_2_local($return_fields[$i])] = null;
  					continue;
  				}
	  			$v = $attrs[$return_fields[$i]];
  				if (is_array($v)) {
  					if (array_key_exists('count', $v)) unset($v['count']);
  					if (count($v) === 1) $v = $v[0];
  				}
	  			$a[$this->{$mode}->ldap_2_local($return_fields[$i])] = $v;
  			}
  			$arr[] = $a;
  			$entry = @ldap_next_entry($this->_connector, $tmp);
  		}
  	}
  	
  	if (count($arr) == 0) return $this->_return(false);
  	
  	# sort the array
  	ksort($arr);
  	
  	# execute function hook
  	$this->{$mode}->hook('search');
  	
  	return $this->_return(&$arr);
  }
  
  public function set_password($pw)
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->user_loaded) return $this->_return(false, 'Error: no account loaded');
  	
  	# verify object type
  	if (strpos($this->user_primary_type, 'user') === false) {
  		return $this->_return(false, 'Error: only user objects have passwords');
  	}
  	
  	# absolute security, can not alter these accounts
  	if ($this->user->check_restricted($this->user->uid)) return $this->_return(false, 'Error: the account is restricted!');
  	
  	# check the password to make sure it isn't blank
  	if ( (!$this->user_allow_empty_passwords) && (strlen($pw) == 0) ) {
  		return $this->_return(false, 'Error: blank passwords are not allowed');
  	}
  	
  	# create the update array
  	$update = array($this->user->local_2_ldap('password') => $this->convert_to_unicode("\"$pw\""));
  	
  	# attempt to change the password
  	if (! @ldap_mod_replace($this->_connector, $this->user->dn, $update)) {
  		return $this->_return(false, 'failed to change password: ' . ldap_error($this->_connector) . ' (' . ldap_errno($this->_connector) .')');
  	}
  	
  	# for some reason this command has to be executed twice to take effect
  	@ldap_mod_replace($this->_connector, $this->user->dn, $update);
  	
  	# set password policies to ensure user can login
  	$this->_debug('setting account policies');
  	
  	$this->user->disabled = false;
  	$this->user->locked = false;
  	$this->user->password_expired = false;
  	$this->user->save();
  	
  	# execute function hook
  	$this->user->hook('set_password');
  	
  	return $this->_return(true, 'password set successfully');
  }
  
  protected function store($operation, $data)
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
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	if ($operation == 'add') {
  		# cache the DN components first
  		$dn = array(0 => $this->local_2_ldap('name') . "=" . str_replace(",", "\,", $this->name));
  		if (strlen($this->{$mode.'_working_ou'}) > 0) {
  			$dn[count($dn)] = $this->{$mode.'_working_ou'};
  		}
  		$dn[count($dn)] = $this->_base_dn;
  		
  		# set the dn for the new object
  		$account_dn = implode(",", $dn);
  		
  		# attempt to add new object
  		$this->_debug('adding object... ', true);
  		if (@ldap_add($this->_connector, $account_dn, $obj_array)) {
  			$this->_debug('success');
  			$result = true;
  		} else {
  			$this->_debug('failed');
  			$this->_debug('Unable to create object. <em>(Error ' . ldap_errno($this->_connector) . ') ' . ldap_error($this->_connector) . '</em>');
  			$this->_debug('base_dn: <em>' . $account_dn . '</em>');
  			$str = '';
  			foreach($obj_array as $k=>$v) {
  				$str .= "<br />$k = ";
  				if (is_array($v)) {
  					$str .= "ARRAY(";
  					foreach($v as $k1=>$v1) { $str .= "<br />$k1 = '$v1'"; }
  					$str .= ')';
  				} else {
  					$str .= "'$v'";
  				}
  			}
  			$this->_debug("$obj_array: <em>$str</em>");
  			$result = false;
  		}
  	}
  }
  
  public function sys($var)
  /* return a directory system setting (read-only)
   *
   */
  {
  	switch(@strtolower($var)) {
  		case 'lockout_duration': return $this->lockout_duration; break;
  		case 'lockout_threshold': return $this->lockout_threshold; break;
  		default: return false; break;
  	}
  }
  
  public function time2unix($value)
  /* convert an active directory timestamp to a standard unix timestamp
   *
   * source: http://php.net/manual/en/ref.ldap.php
   * retrieved: aug-03-2010 ws
   * author: brudinie at yahoo dot co dot uk, 26-Jan-2006 04:43
   *
   * accepts as input an AD timestamp or local field name
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	$days = 365.241192;
  	switch($value) {
  		case 'account_expires': $ad_time = $this->user->account_expires; break;
  		case 'lockout_time': $ad_time = $this->user->lockout_time; break;
  		case 'bad_pw_time': $ad_time = $this->user->bad_pw_time; break;
  		case 'pw_last_set': $ad_time = $this->user->pw_last_set; $days = 366.230352; break;
  		default: $ad_time = $value; break;
  	}
  	# if 0 or null, return that value
  	if ((is_null($ad_time))||($ad_time == 0)) return $ad_time;
    # ad_time is nano seconds (yes, nano seconds) since jan 1st 1601
    $secsAfterADEpoch = $ad_time / (10000000); // seconds since jan 1st 1601
    $ADToUnixConvertor=((1970-1601) * $days) * 86400; // unix epoch - AD epoch * number of tropical days * seconds in a day
    return intval($secsAfterADEpoch-$ADToUnixConvertor); // unix Timestamp version of AD timestamp
  }
  
  public function toggle_new_pw_required($save = true)
  /* toggle the new_password_required fields for an account
   *
   * a.k.a. "User must change password at next logon"
   *
   * if $save is true the function will automatically commit the change
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	
  	# verify object type
  	if (strpos($this->user_primary_type, 'user') === false) {
  		return $this->_return(false, 'Error: only user objects have passwords');
  	}
  	
  	# absolute security, can not alter these accounts
  	if ($this->user->check_restricted($this->user->uid)) return $this->_return(false, 'Error: the account is restricted!');
  	
  	# by definition the user must be allowed to change his or her password
  	$this->user->pw_locked = false;
  	$this->user->pw_never_expires = false;
  	
  	if ($this->user->new_pw_required) {
  		# set value to false (new password not required)
  		$this->user->pw_last_set = -1;
  		$this->user->new_pw_required = false;
  		$this->_debug('user will not be required to change his or her password');
  	} else {
  		# set value to true (new password required)
  		$this->user->pw_last_set = 0;
  		$this->user->new_pw_required = true;
  		$this->_debug('user will be required to change her or her password');
  	}
  	
  	$result = true;
  	
  	if ($save) { $result = $this->user->save(); }
  	
  	# execute function hook if successful
  	if ($result) $this->user->hook('toggle_new_pw_required');
  	
  	return $this->_return($result);
  }
  
  public function type($set_type = false)
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
  {
  	$this->_debug_start($set_type);
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, '<strong>Warning:</strong> No account loaded');
  	if ($mode === 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	
  	# disallow modification of restricted accounts
  	if ($this->{$mode}->check_restricted($this->{$mode}->{$id})) $set_type = '';
  	
  	# sanitize input
  	if (!is_string($set_type)) $set_type = '';
  	
  	# for user accounts, if the primary type is not set set it and return the value
  	if ($mode === 'user') {
  		if ($this->user_primary_type == '') {
  			if ($this->_enable_exchange) {
  				# exchange support enabled
  				switch(strtolower($set_type)) {
  					case 'contact': $this->user_primary_type = 'contact'; break;
  					case 'contact+': $this->user_primary_type = 'contact+'; break;
  					case 'user+': $this->user_primary_type = 'user+'; break;
  					case 'user++': $this->user_primary_type = 'user++'; break;
  					default: $this->user_primary_type = 'user'; break;
  				}
  			} else {
  				# exchange support disabled
  				switch(strtolower($set_type)) {
  					case 'contact': $this->user_primary_type = 'contact'; break;
  					case 'contact+': $this->user_primary_type = 'contact'; break;
  					default: $this->user_primary_type = 'user'; break;
  				}
  			}
  			return $this->_return($this->user_primary_type);
  		}
  		
  		if ($this->_enable_exchange) {
  			# exchange support enabled
  			switch(strtolower($set_type)) {
  				case '': return $this->_return($this->user_primary_type); break;
  				case 'contact': $this->user_primary_type = 'contact'; break;
  				case 'contact+': $this->user_primary_type = 'contact+'; break;
  				case 'user+': $this->user_primary_type = 'user+'; break;
  				case 'user++': $this->user_primary_type = 'user++'; break;
  				default: $this->user_primary_type = 'user'; break;
  			}
  		} else {
  			# exchange support disabled
  			switch(strtolower($set_type)) {
  				case '': return $this->_return($this->user_primary_type); break;
  				case 'contact': $this->user_primary_type = 'contact'; break;
  				case 'contact+': $this->user_primary_type = 'contact'; break;
  				default: $this->user_primary_type = 'user'; break;
  			}
  		}
  		return $this->_return($this->user_primary_type);
  	}
  	
  	return $this->_return($mode);
  }
  
  protected function unbind()
  /* unbind from the directory server to restore read-only access
   *
   */
  {
  	$this->_debug('function unbind() <strong>disabled</strong>');
  	return true;
  	
  	$this->_debug_start('unbinding from the directory server');
  	@ldap_unbind($this->_connector);
  	return $this->_return(true);
  }
  
  public function unix2time($value)
  /* convert a unix timestamp to an active directory timestamp
   *
   * accepts as input a unix timestamp or local field name
   *
   */
  {
  	if ($this->mode($mode, 'user') === false) return false;
  	$days = 365.241192;
  	switch($value) {
  		case 'account_expires': $unix_time = $this->user->account_expires; break;
  		case 'lockout_time': $unix_time = $this->user->lockout_time; break;
  		case 'bad_pw_time': $unix_time = $this->user->bad_pw_time; break;
  		case 'pw_last_set': $unix_time = $this->user->pw_last_set; $days = 366.230352; break;
  		default: $unix_time = $value; break;
  	}
  	# if 0 or null, return that value
  	if ((is_null($unix_time))||($unix_time == 0)) return $unix_time;
  	$ADToUnixConvertor=((1970-1601) * $days) * 86400; // unix epoch - AD epoch * number of tropical days * seconds in a day
  	return sprintf("%01.0f", ($unix_time + $ADToUnixConvertor)*(10000000));
  }
}
?>