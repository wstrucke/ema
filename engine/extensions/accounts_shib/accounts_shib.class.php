<?php
 /* Shibboleth (Authentication) Module for the Security System
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Aug-02-2011
  * William Strucke, wstrucke@gmail.com
  *
  * Implements accounts, groups, and membership using Shibboleth
  *
  * Server support and configuration is required and outside
  *  the scope of this module.
  *
  */

class accounts_shib extends user_group
{
  # public / directly accessible variables
  #public $var;                     // (type) description
  
  # variables to be shared on demand
  #protected $var2;                 // (type) description
  
  # internal variables
  public $known_users_only;        // (bool) limit authentication to existing, pre-defined users only
  public $shib_app_id;             // (string) the shibboleth application id
  public $shib_auth_ssl;           // (bool) authenticate over ssl (*strongly recommended*)
  public $shib_auth_url;           // (string) the complete url (without http/https) to redirect shibboleth
                                   //          authentication requests to.
  public $shib_provider;           // (string) shibboleth identity provider
  public $shib_referrer;           // (string) the referral URL for validation
  protected $validate_called = false;
                                   // (bool) whether or not validate has been called (temporary variable
                                   //        until double call bug is fixed)
  
  # database version
  public $schema_version='0.2.0';  // the schema version to match the registered schema
  
  protected $_debug_prefix = 'shibboleth';
                                   // object debug output prefix
  public $_name = 'Shibboleth Authentication Module';
                                   // the module name
  public $_version = '1.0.0';      // the loaded object's version string
  
  /* code */
  
  public function _construct()
  /* initialize the object
   *
   */
  {
  	parent::_construct();
  	# register with the system cache
  	if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
  		$this->_tx->cache->register_table('shib_map');
  	}
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
  	# the shib auth mechanism is based on the provider
  	$this->uuid = md5($this->shib_auth_url);
  	return true;
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
  	$this->_debug_start();
  	
  	# validate state
  	if ($this->mode($mode, 'group') === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->group_loaded) return $this->_return(false, 'Error: no group is loaded!');
  	
  	# validate input
  	if (strlen((string)$guid) == 0) return $this->_return(false, 'Error: Invalid GUID provided');
  	if (strtolower((string)$type) !== 'user') {
  		$type = 'group';
  		if (!$this->group->exists($guid, 'unique_id')) return $this->_return(false, 'Error: Group does not exist');
  	} else {
  		if (!$this->user->exists($guid, 'unique_id')) return $this->_return(false, 'Error: User does not exist');
  	}
  	
  	# set type qualifier
  	if (strtolower((string)$type) == 'user') { $type = false; } else { $type = true; }
  	
  	# verify this group is not already a member
  	if ($this->check_member($guid, null, true)) return $this->_return(false, 'Error: Group is already a member');
  	
  	# add the member
  	if (! $this->_tx->db->insert(
  		'shib_map',
  		array('group_guid', 'member_guid', 'member_is_group'),
  		array($this->group->unique_id, $guid, $type)))
  	{
  		return $this->_return(false, 'Error adding member to the group');
  	}
  	
  	return $this->_return(true, 'Successfully added member to the group');
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
  		0=>array('id'=>'shib_app_id','required'=>false,'type'=>'string','label'=>'Shibboleth Application ID'),
  		1=>array('id'=>'shib_auth_url','required'=>false,'type'=>'string','label'=>'SP Authentication URL'),
  		2=>array('id'=>'shib_auth_ssl','required'=>true,'type'=>'string','label'=>'Authenticate over SSL (Recommended)','options'=>array('true'=>'True','false'=>'False')),
  		3=>array('id'=>'shib_provider','required'=>false,'type'=>'string','label'=>'SP Provider ID'),
  		4=>array('id'=>'shib_referrer','required'=>false,'type'=>'string','label'=>'SP Referral URL'),
  		5=>array('id'=>'known_users_only','required'=>false,'type'=>'string','label'=>'Limit to Known Accounts','options'=>array('true'=>'True','false'=>'False'))
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
  	# shibboleth uses non-standard authentication mechanism
  	return false;
  }
  
  protected function auth_url()
  /* set the auth url
   *
   */
  {
  	# check for required argument
  	if (is_null($this->shib_auth_url)) {
  		if ($this->shib_auth_ssl) { $prefix = 'https://'; } else { $prefix = 'http://'; }
  		$this->shib_auth_url = $prefix . $this->_tx->get->domain . url('Shibboleth.sso/Login');
  	}
  	$this->_debug('using auth url ' . $this->shib_auth_url);
  }
  
  public function cache_expire_table($t)
  /* given a table (t) that has been modified, return an array of match arguments to be expired
   *   from the cache
   *
   * this function should only be called from the cache module
   *
   * returns an array of one or more key->value pairs or false to decline the request
   *
   */
  {
  	$match = array();
  	switch($t) {
  		case 'shib_map': $match['authenticated'] = true; break;
  		default: return false;
  	}
  	return $match;
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
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode === 'group') { $table = 'shib_group'; } else { $table = 'shib_user'; }
  	if (($fields === false)||(!is_array($fields))) $fields = $this->{$mode}->get_fields();
  	$list = $this->_tx->db->query($table, '', '', $fields, true);
  	if (!db_qcheck($list, true)) return false;
  	return $list;
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
			$result = $this->_tx->db->update('shib_group', array('gid'), array($this->group->gid), array('gid'), array($new_id));
		} else {
			$result = $this->_tx->db->update('shib_user', array('uid'), array($this->user->uid), array('uid'), array($new_id));
		}
		return $this->_return($result);
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
  	$this->_debug_start();
  	
  	# input validation
  	if ($this->mode($mode, 'group') === false) return $this->_return(false, 'Invalid mode');
  	if (is_null($group_guid)&&(!$this->group_loaded)) return $this->_return(false, 'Error: A group is required');
  	if (strlen((string)$guid)==0) return $this->_return(false, 'Error: Invalid GUID Provided');
  	
  	# set the group to search
  	if (is_null($group_guid)) $group_guid = $this->group->unique_id;
  	
  	# handle recursive history processing via nested variable
  	if (is_array($nested)) {
  		$nest_history = $nested;
  		$nested = true;
  	} else {
  		$nest_history = array($group_guid);
  	}
  	
  	if (db_qcheck_exec(array('table'=>'shib_map','search_keys'=>array('group_guid', 'member_guid'),'search_values'=>array($group_guid, $guid)))) {
  		return $this->_return(true, 'membership validated');
  	}
  	
  	# conditionally check nested groups as well
  	if ($nested) {
  		# retrieve a list of member groups
  		$groups = $this->_tx->db->query('shib_map',array('group_guid', 'member_is_group'),array($group_guid, true),array('member_guid'),true);
  		if (!db_qcheck($groups, true)) return $this->_return(false, 'no nested groups and guid is not a member');
  		
  		for ($i=0;$i<count($groups);$i++) {
  			if (in_array($groups[$i]['member_guid'], $nest_history)) continue;
  			$nest_history[] = $groups[$i]['member_guid'];
  			if ($this->group->check_member($guid, $groups[$i]['member_guid'], $nest_history)) return $this->_return(true, 'nested membership validated');
  		}
  	}
  	
  	return $this->_return(false, 'guid is not a member');
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
  	if (!is_array($args)) $args = array();
  	# check for debug mode
  	if (isset($args['debug'])) { $this->_debug_mode = $args['debug']; } else { $this->_debug_mode = -1; }
  	$this->_debug('configure');
  	
  	# set defaults
  	$this->known_users_only = true;
  	$this->shib_auth_ssl = true;
  	
  	# check for arguments
  	if (array_key_exists('known_users_only', $args)) {
  		if ($args['known_users_only'] === 'false') $this->known_users_only = false;
  		$this->_debug('configured to allow unknown users');
  	}
  	
  	if (array_key_exists('shib_auth_url', $args)) {
  		$this->shib_auth_url = $args['shib_auth_url'];
  		$this->_debug('using auth url ' . $this->shib_auth_url);
  	}
  	
  	if (array_key_exists('shib_auth_ssl', $args)) {
  		if ($args['shib_auth_ssl'] === 'false') $this->shib_auth_ssl = false;
  	}
  	
  	if (array_key_exists('shib_app_id', $args)) {
  		$this->shib_app_id = $args['shib_app_id'];
  		$this->_debug('using app id ' . $this->shib_app_id);
  	} else {
  		# temporary until we can configure via the gui
  		$this->shib_app_id = 'tbdbitl';
  	}
  	
  	if (array_key_exists('shib_provider', $args)) {
  		$this->shib_provider = $args['shib_provider'];
  		$this->_debug('using SP identity ' . $this->shib_provider);
  	} else {
  		# temporary until we can configure via the gui
  		$this->shib_provider = 'urn:mace:incommon:osu.edu';
  	}
  	
  	if (array_key_exists('shib_referrer', $args)) {
  		$this->shib_referrer = $args['shib_referrer'];
  		$this->_debug('using referral url ' . $this->shib_referrer);
  	} else {
  		# temporary until we can configure via the gui
  		#$this->shib_referrer = 'https://webauth.service.ohio-state.edu/idp/profile/Shibboleth/SSO';
  	}
  	
  	return true;
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
  	
  	# ensure an account is loaded
  	if (!$this->{$mode.'_loaded'}) return $this->_return(false, "Error: no $mode is loaded");
  	
  	# set the ids
  	if ($mode == 'group') { $id = 'gid'; } else { $id = 'uid'; }
  	$guid = $this->{$mode}->unique_id;
  	
  	# attempt to delete account
  	$this->_debug("attempting to delete $mode... ", true);
  	
  	if (!$this->_tx->db->delete("shib_$mode", array($id), array($this->{$mode}->{$id}))) {
 			return $this->_return(false, '<strong>failed</strong>');
  	}
  	
  	# unregister the guid
  	$this->_tx->db->delete('shib_guid', array('guid'), array($guid));
  	
  	# delete any mappings
  	$this->_tx->db->delete('shib_map', array('member_guid'), array($guid));
  	if ($mode == 'group') { $this->_tx->db->delete('shib_map', array('group_guid'), array($guid)); }
  	
  	# unload this account
  	$this->{$mode}->unload();
  	
  	return $this->_return(true, 'success');
  }
  
  public function delete_all()
  /* special function for accounts_shib
   *
   * delete all authorized users
   *
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	# attempt to delete account
  	$this->_debug("attempting to delete all ${mode}... ", true);
  	
  	if (!$this->_tx->db->delete("shib_$mode", array(1), array(1))) {
 			return $this->_return(false, '<strong>failed</strong>');
  	}
  	
  	# unregister the guids
  	$this->_tx->db->delete('shib_guid', array('type'), array($mode));
  	
  	# delete all mappings
  	$this->_tx->db->delete('shib_map', array(1), array(1));
  	
  	# unload any loaded account
  	$this->{$mode}->unload();
  	
  	return $this->_return(true, 'success');
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
   */
  {
  	$this->_debug_start();
  	
  	# validate state
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ($mode === 'user') { $default = 'uid'; $table = 'shib_user'; } else { $default = 'gid'; $table = 'shib_group'; }
  	
  	# load field list
		$list = $this->{$mode}->get_fields();
  	
  	# ensure the search criteria is valid
  	if (strlen($match_string) == 0) return $this->_return(false, '<strong>Warning</strong>: You did not provide any data to search for!');
  	if ($local_field === false) { $local_field = $default; } else { $local_field = strtolower((string)$local_field); }
  	if (!in_array($local_field, $list)) return $this->_return(false, 'Invalid search field');
  	
  	# ids should always be lowercase to ensure proper matching
  	if ($local_field == $default) $match_string = strtolower((string)$match_string);
  	
  	# search for this account
  	$search = $this->_tx->db->query($table, array($local_field), array($match_string));
  	if (!db_qcheck($search, true)) return $this->_return(false, 'no results were found');
  	
  	# if there was a result, return true
  	return $this->_return(true, 'specified account exists');
  }
  
  public function get_fields()
  /* returns an array of object fields usable for the loaded account type
   *
   * returns an empty array on error (with output to debug)
   *
   */
  {
  	if ($this->mode($mode) === false) return false;
  	$list = parent::get_fields($mode);
  	
  	switch($mode) {
  		case 'group':
  			$arr = array();
  			break;
  		case 'user':
  			$arr = array('kerberos_id');
  			break;
  	}
  	
  	return array_merge($list, $arr);
  }
  
  public function login($option, $path = '')
  /* contract for login: output the login form or selection text for a non-standard login mechanism
   *
   * required:
   *   option             (string) what to output [ select | login ]
   *
   * optional:
   *   path               (string) optional post-login redirect path
   *
   * returns:
   *   string, the html to be output by the security module
   *
   * implementation notes:
   *   this function is optional and only used for non-standard authentication mechanisms
   *
   */
  {
  	$this->auth_url();
  	if (!is_string($path)) $path = '';
  	switch($option) {
  		case 'select':
  			return img('download', 'osu_logo_red.png', 128, 128) . '<br />OSU Internet Login';
  			break;
  		case 'login':
	  		if (strpos($this->shib_auth_url, '?') !== false) { $c = '&'; } else { $c = '?'; }
	  		if ($this->shib_auth_ssl) { $prefix = 'https://'; } else { $prefix = 'http://'; }
	  		$targetpath = urlencode($prefix . $this->_tx->get->domain . url($this->_tx->get->request_string));
  			return '<meta http-equiv="refresh" content="0;' . $this->shib_auth_url . $c . 'target=' . $targetpath . '" />';
  			break;
  		default:
  			return 'Error';
  			break;
  	}
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
  	# to be coded
  	return false;
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
  	$this->_debug_start();
  	if ((!is_string($guid))||(strlen($guid)==0)) return $this->_return(false, 'Invalid GUID');
  	if ($include_inherited !== true) $include_inherited = false;
  	
  	# initialize the return array
  	$list = array();
  	
  	# get a list of groups this account is a member of
  	$check = $this->_tx->db->query('shib_map', array('member_guid'), array($guid), array('group_guid'), true);
  	if (!db_qcheck($check, true)) $check = array();
  	
  	# preset inheritance value
  	if ($recursive === true) { $flag = 1; } else { $flag = 0; }
  	
  	# process the result list
  	for ($i=0;$i<count($check);$i++){
  		$list[$check[$i]['group_guid']] = $flag;
  		if ($include_inherited) $list = array_merge($list, $this->membership($check[$i]['group_guid'], true, true));
  	}
  	
  	return $this->_return($list);
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
  	$this->_debug_start();
  	if ($this->mode($mode, 'group') === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->group_loaded) return $this->_return(false, 'Error: no group is loaded!');
  	
  	# validate input
  	if ((!is_string($guid))||(strlen($guid) == 0)) return $this->_return(false, 'Error: Invalid GUID provided');
  	if (!in_array($type, array('group', 'user'))) return $this->_return(false, 'Error: Invalid Type provided');
  	
  	# check the membership
  	$check = $this->_tx->db->query('shib_map', array('group_guid', 'member_guid'), array($this->group->unique_id, $guid), array('locked'));
  	if (!db_qcheck($check)) return $this->_return(true, 'Membership not found');
  	if (bit2bool($check[0]['locked'])) return $this->_return(false, 'Unable to remove restricted member');
  	
  	# remove the member
  	if (!$this->_tx->db->delete('shib_map', array('group_guid', 'member_guid'), array($this->group->unique_id, $guid))) {
  		return $this->_return(false, 'Error removing member from the group');
  	}
  	
  	return $this->_return(true, 'Successfully removed member from the group');
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
  	# validate state and input
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	if ((!is_string($guid))||(strlen($guid)==0)) return false;
  	if ($mode == 'group') { $table = 'shib_group'; } else { $table = 'shib_user'; }
  	$data = $this->_tx->db->query($table, array('unique_id'), array($guid), $this->{$mode}->get_fields());
  	if (!db_qcheck($data)) return false;
  	return $data[0];
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
   */
  {
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	
  	# validate input
  	if ((!is_array($criteria))||(count($criteria) < 1)) return $this->_return(false, 'Error: invalid search criteria');
  	if ($multiple_results !== true) $multiple_results = false;
  	if ($partial_match !== true) $partial_match = false;
  	if ((!is_string($comparison_type))||(!in_array($comparison_type, array('AND')))) {
  		return $this->_return(false, 'Comparison type unsupported');
  	}
  	
  	# set the search variables
  	if ($mode == 'group') {
  		$table = 'shib_group';
  		$fields = array('gid', 'gid_number', 'unique_id', 'display_name', 'enabled');
  	} else {
  		$table = 'shib_user';
  		$fields = array('uid', 'uid_number', 'unique_id', 'display_name', 'enabled', 'email');
  	}
  	
  	# perform the search
  	$search = $this->_tx->db->query($table, array_keys($criteria), array_values($criteria), $fields, $multiple_results);
  	if (!db_qcheck($search, true)) return $this->_return(false, 'No Results');
  	
  	# prepare return array
  	if ($multiple_results) {
  		# sort the results
  		ksort($search);
  	}
  	
  	return $this->_return($search);
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
  	# shibboleth uses non standard authentication mechanism
  	return false;
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
  	if (!in_array($operation, array('add', 'save'))) return $this->_return(false, 'Invalid operation');
  	if (!is_array($data)) return $this->_return(false, 'Invalid data');
  	if (($operation == 'save')&&($this->{$mode.'_loaded'} === false)) return $this->_return(false, 'No account is loaded');
  	if (($operation == 'save')&&(count($data) == 0)) return true;
  	if ($operation == 'add') {
  		$result = $this->_tx->db->insert("shib_$mode", array_keys($data), array_values($data));
  		# register the guid
  		$this->_tx->db->insert('shib_guid', array('guid', 'type'), array($data['unique_id'], $mode));
  	} else {
  		if ($mode == 'group') {
  			$result = $this->_tx->db->update('shib_group', array('gid'), array($this->group->gid), array_keys($data), array_values($data));
  		} else {
  			$result = $this->_tx->db->update('shib_user', array('uid'), array($this->user->uid), array_keys($data), array_values($data));
  		}
  	}
  	return $result;
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
  	$this->_debug_start();
  	if ($this->mode($mode) === false) return $this->_return(false, 'Invalid mode');
  	return $mode;
  }
  
  public function validate()
  /* gear function validate
   *
   */
  {
  	# only continue if we aren't already authenticated
  	if ($this->_tx->security->authenticated) return true;
  	if ($this->validate_called) return false;
  	$this->validate_called = true;
  	# make sure this module is configured
  	if (is_null($this->known_users_only)) {
  		# this copy of accounts_shib was created by the transmission solely to access the gear (this function)
  		# call the security basic module which is properly configured
  		$module_id = $this->_tx->security->get_module_id($this);
  		if ($module_id === false) return false;
  		return $this->_tx->security->modules[$module_id]->validate();
  	}
  	# check for a shibboleth session
  	if (array_key_exists('Shib-Session-ID', $_SERVER)||array_key_exists('REDIRECT_Shib-Session-ID', $_SERVER)) {
  		# set defaults
  		$sp_app_id = '';
  		$sp_id = '';
  		$eppn = '';
  		$sp_given = '';
  		$sp_last = '';
  		$sp_mail = '';
  		$sp_kerb = '';
  		
  		# load standard shib vars
  		if (array_key_exists('Shib-Application-ID', $_SERVER)) $sp_app_id = $_SERVER['Shib-Application-ID'];
  		if (array_key_exists('Shib-Identity-Provider', $_SERVER)) $sp_id = $_SERVER['Shib-Identity-Provider'];
  		if (array_key_exists('eppn', $_SERVER)) $eppn = $_SERVER['eppn'];
  		if (array_key_exists('givenName', $_SERVER)) $sp_given = $_SERVER['givenName'];
  		if (array_key_exists('sn', $_SERVER)) $sp_last = $_SERVER['sn'];
  		if (array_key_exists('mail', $_SERVER)) $sp_mail = $_SERVER['mail'];
  		if (array_key_exists('KERBEROS-ID', $_SERVER)) $sp_kerb = $_SERVER['KERBEROS-ID'];
  		
  		# load redirect shib vars
  		if (array_key_exists('REDIRECT_Shib-Application-ID', $_SERVER)) $sp_app_id = $_SERVER['REDIRECT_Shib-Application-ID'];
  		if (array_key_exists('REDIRECT_Shib-Identity-Provider', $_SERVER)) $sp_id = $_SERVER['REDIRECT_Shib-Identity-Provider'];
  		if (array_key_exists('REDIRECT_eppn', $_SERVER)) $eppn = $_SERVER['REDIRECT_eppn'];
  		if (array_key_exists('REDIRECT_givenName', $_SERVER)) $sp_given = $_SERVER['REDIRECT_givenName'];
  		if (array_key_exists('REDIRECT_sn', $_SERVER)) $sp_last = $_SERVER['REDIRECT_sn'];
  		if (array_key_exists('REDIRECT_mail', $_SERVER)) $sp_mail = $_SERVER['REDIRECT_mail'];
  		if (array_key_exists('REDIRECT_KERBEROS-ID', $_SERVER)) $sp_kerb = $_SERVER['REDIRECT_KERBEROS-ID'];
  		
  	} else {
  		return true;
  	}
		# preset validated to true
		$validated = true;
		# based on the configured settings, validate the app id, SP id, and SP referral url
		if ((@strlen($this->shib_app_id) > 0)&&($sp_app_id != $this->shib_app_id)) $validated = false;
		if ((@strlen($this->shib_provider) > 0)&&($sp_id != $this->shib_provider)) $validated = false;
		if ((@strlen($this->shib_referrer) > 0)&&(@$_SERVER['HTTP_REFERER'] != $this->shib_referrer)) $validated = false;
		if (strpos($eppn, '@') !== false) $eppn = substr($eppn, 0, strpos($eppn, '@'));
		# if not validated, end here
		if (!$validated) return true;
		# attempt to lookup this account
		$check = $this->user->exists($eppn);
		# if the account does not exist and we only accept existing accounts exit here
		if (($check === false) && $this->known_users_only) {
			message('You were successfully authenticated but your account does not exist in our system.<br />Please contact the web site administrator.', 'error');
			return true;
		}
		# if the account does not exist and we haven't exited, create it
		if ($check === false) {
			# create the account
			$this->user->clear();
			$this->user->first = $sp_given;
			$this->user->last = $sp_last;
			$this->user->mail = $sp_mail;
			$this->user->kerberos_id = $sp_kerb;
			$this->user->uid = $eppn;
			if (!$this->user->add()) return true;
		} else {
			# load the account
			if (!$this->user->load($eppn)) return true;
			# verify shibboleth provided fields are set, if they are set do not change them
			$map = array('first'=>$sp_given, 'last'=>$sp_last, 'mail'=>$sp_mail, 'kerberos_id'=>$sp_kerb);
			$change = false;
			foreach ($map as $k=>$token) {
				if (strlen($this->user->{$k}) === 0) { $this->user->{$k} = $token; $change = true; }
			}
			if ($change) { if (!$this->user->save()) return true; }
		}
		# session validated, account loaded, activate the session
		$_SESSION['active']	= true;
		$_SESSION['auth_module_id'] = $this->_module_id;
		$_SESSION['auth_uid'] = $eppn;
		# set security manager stuff
		$this->_tx->security->authenticated = true;
		$this->_tx->security->auth_module = new accounts_shib($this->_tx, array('_debug_mode' => $this->_debug_mode, '_function_level' => &$this->_function_level, '_debug_output' => $this->_debug_output));
		if ($this->known_users_only) { $kn = 'true'; } else { $kn = 'false'; }
		$this->_tx->security->auth_module->configure(array('shib_app_id'=>$this->shib_app_id, 'shib_auth_url'=>$this->shib_auth_url, 'shib_provider'=>$this->shib_provider, 'shib_referrer'=>$this->shib_referrer, 'known_users_only'=>$kn));
		$this->_tx->security->auth_module->user->load($eppn);
		# set the target path
		$this->_tx->set->pageid = $this->_tx->get->default_home;
		$this->_tx->set->content_type = 'html';
		@session_write_close();
  }
}
?>