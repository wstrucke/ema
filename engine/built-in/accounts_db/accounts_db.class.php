<?php
 /* EMA Accounts-DB (Authentication) Module for the Security System
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Apr-21-2011
  * William Strucke, wstrucke@gmail.com
  *
  * Implements accounts, groups, and membership using the local database
  *
  */

class accounts_db extends user_group
{
  public $schema_version='0.1.2';  // the schema version to match the registered schema
  public $_name = 'Accounts-DB (Authentication) Module';
  public $_version = '1.0.0';
  protected $_debug_prefix = 'accounts_db';
  
  /* code */
  
  public function _construct()
  /* initialize the object
   *
   */
  {
  	parent::_construct();
  	# register with the system cache
		if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
			$this->_tx->cache->register_table('accounts_map');
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
  	# since this module uses a static database configuration, there can be only one possible configuration
  	$this->uuid = '1';
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
  		'accounts_map',
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
		return $this->_content('index');
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
  	return array();
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
  	
  	# validate state
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->user_loaded) return $this->_return(false, 'Error: no account is loaded!');
  	
  	# build the password string
  	$checkPw = $this->user->encode_password($pw);
  	
		# authenticate the user
  	$this->_debug('attempting to authenticate the account...', true);
  	if (!db_qcheck_exec(array('table'=>'accounts_user', 'search_keys'=>array('uid', 'password', 'enabled'), 'search_values'=>array($this->user->uid,   $checkPw, true)))) {
  		$this->_debug('<strong>failed</strong>');
  		return $this->_return(false);
  	}
  	
  	# authentication succeeded
  	$this->authenticated = true;
  	
  	return $this->_return(true, 'success');
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
  		case 'accounts_map': $match['authenticated'] = true; break;
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
  	if ($mode === 'group') { $table = 'accounts_group'; } else { $table = 'accounts_user'; }
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
			$result = $this->_tx->db->update('accounts_group', array('gid'), array($this->group->gid), array('gid'), array($new_id));
		} else {
			$result = $this->_tx->db->update('accounts_user', array('uid'), array($this->user->uid), array('uid'), array($new_id));
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
  	
  	if (db_qcheck_exec(array('table'=>'accounts_map','search_keys'=>array('group_guid', 'member_guid'),'search_values'=>array($group_guid, $guid)))) {
  		return $this->_return(true, 'membership validated');
  	}
  	
  	# conditionally check nested groups as well
  	if ($nested) {
  		# retrieve a list of member groups
  		$groups = $this->_tx->db->query('accounts_map',array('group_guid', 'member_is_group'),array($group_guid, true),array('member_guid'),true);
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
  	
  	# validate this isn't a system group
  	if (($mode == 'group')&&($this->group_data['system'] == true)) return $this->_return(false, 'Error: Unable to delete system groups');
  	
  	# attempt to delete account
  	$this->_debug("attempting to delete $mode... ", true);
  	
  	if (!$this->_tx->db->delete("accounts_$mode", array($id), array($this->{$mode}->{$id}))) {
 			return $this->_return(false, '<strong>failed</strong>');
  	}
  	
  	# unregister the guid
  	$this->_tx->db->delete('accounts_guid', array('guid'), array($guid));
  	
  	# delete any mappings
  	$this->_tx->db->delete('accounts_map', array('member_guid'), array($guid));
  	if ($mode == 'group') { $this->_tx->db->delete('accounts_map', array('group_guid'), array($guid)); }
  	
  	# unload this account
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
  	if ($mode === 'user') { $default = 'uid'; $table = 'accounts_user'; } else { $default = 'gid'; $table = 'accounts_group'; }
  	
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
  			$arr = array('system');
  			break;
  		case 'user':
  			$arr = array();
  			break;
  	}
  	
  	return array_merge($list, $arr);
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
  	$check = $this->_tx->db->query('accounts_map', array('member_guid'), array($guid), array('group_guid'), true);
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
  	$check = $this->_tx->db->query('accounts_map', array('group_guid', 'member_guid'), array($this->group->unique_id, $guid), array('locked'));
  	if (!db_qcheck($check)) return $this->_return(true, 'Membership not found');
  	if (bit2bool($check[0]['locked'])) return $this->_return(false, 'Unable to remove restricted member');
  	
  	# remove the member
  	if (!$this->_tx->db->delete('accounts_map', array('group_guid', 'member_guid'), array($this->group->unique_id, $guid))) {
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
  	if ($mode == 'group') { $table = 'accounts_group'; } else { $table = 'accounts_user'; }
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
  		$table = 'accounts_group';
  		$fields = array('gid', 'gid_number', 'unique_id', 'display_name', 'enabled');
  	} else {
  		$table = 'accounts_user';
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
  	$this->_debug_start();
  	if ($this->mode($mode, 'user') === false) return $this->_return(false, 'Invalid mode');
  	if (!$this->user_loaded) return $this->_return(false, 'Error: no account is loaded!');
  	if (!is_string($pw)) return $this->_return(false, ' Error: invalid password');
  	
  	# check the password to make sure it isn't blank
  	if ((!$this->user_allow_empty_passwords)&&(strlen($pw) == 0)) {
  		return $this->_return(false, 'Error: blank passwords are not allowed');
  	}
  	
  	# attempt to change the password
  	if (!$this->_tx->db->update(
      	'accounts_user',
      	array('uid'),
      	array($this->user_data['uid']),
      	array('password','pw_last_set'),
      	array($this->user->encode_password($pw),'NOW()')))
  	{
  		return $this->_return(false, 'failed to change password');
  	}
  	
  	# set password policies to ensure user can login
  	$this->_debug('setting account policies');
  	$this->{$mode}->enable();
  	
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
  	if (!in_array($operation, array('add', 'save'))) return $this->_return(false, 'Invalid operation');
  	if (!is_array($data)) return $this->_return(false, 'Invalid data');
  	if (($operation == 'save')&&($this->{$mode.'_loaded'} === false)) return $this->_return(false, 'No account is loaded');
  	if (($operation == 'save')&&(count($data) == 0)) return true;
  	if ($operation == 'add') {
  		$result = $this->_tx->db->insert("accounts_$mode", array_keys($data), array_values($data));
  		# register the guid
  		$this->_tx->db->insert('accounts_guid', array('guid', 'type'), array($data['unique_id'], $mode));
  	} else {
  		if ($mode == 'group') {
  			$result = $this->_tx->db->update('accounts_group', array('gid'), array($this->group->gid), array_keys($data), array_values($data));
  		} else {
  			$result = $this->_tx->db->update('accounts_user', array('uid'), array($this->user->uid), array_keys($data), array_values($data));
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
}
?>