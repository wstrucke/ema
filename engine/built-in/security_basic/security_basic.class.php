<?php
 /* Basic EMA Security Access (Authorization) Module
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.3.0, Apr-30-2011
  * William Strucke, wstrucke@gmail.com
  *
  * Syntax:
  *
  * For the purpose of this class, "client" refers to an accounts object, basic access level,
  * or a registered security_guid.  Which specific one it is will depend on the situation and 
  * will usually be irrelevant to the class, provided all names are unique.
  *
  * Also, "item" refers to a page or object that requires an access level and permissions.
  * The first time a check is performed on an object access will be denied and set to the
  * default level of "NONE" -- no one is granted access.  It is then up to the 
  * administrator and/or implementation to set access levels.  This is the preferred way to add
  * an item to the database.
  *
  * There are three 'basic access levels' which are reserved keywords and *are* case-
  * sensative:
  *   PUBLIC  --  grant access to everyone
  *   AUTHENTICATED  --  grant access to authenticated users only
  *   NONE  --  deny (and revoke) access to everyone
  *
  * If the security_layer object is instructed to apply any of these keywords to an item
  * all other explicit permissions on that item will be revoked. Permissions on sub-items
  * will not be altered, though if a "NONE" directive is applied to a parent, they will be 
  * ignored. (see permissions propogation below)
  *
  * Since an implementation with this class should ONLY ACCESS THE DATABASE *THROUGH*
  * this object, it will be up to the object to ensure permissions are configured 
  * properly.  If a script instructs the security_layer object to grant access to 
  * "book.chapter.page.paragraph3", for example, the object should propogate that 
  * access to "book", "book.chapter", and "book.page".
  *
  *
  * Usage:
  *
  * This object must be instantiated with at least one valid accounts module and a valid 
  * database connection object that is already connected to a database server.
  *
  * At this time there is no way to change the default configuration; in future releases
  * I anticipate making several options available.
  *
  *
  * Database:
  *
  * A table is required named "security_permission" with two fields:
  *   item (string[254])
  *   guid (string[55])
  *
  *
  * Permissions Propagation:
  *
  * If an item contains one or more period characters each string of text to the right of a 
  * period will be treated as a "sub-item".  This allows for grouping and inherited permissions.
  * Access will be checked from the left (the most general) to the right (the most specific)
  * When checking an element with sub-items access is assumed to be granted and is denied 
  * unilaterally upon finding any sub-item along the way that the client does not have
  * access to.  This implies that a client can not be granted access to a second level
  * sub-item if access is denied to a parent or first-level sub-item.
  *
  * For example: checking the item "book.chapter.page" for client "generic" will proceed
  * in the following order:
  *   First book is checked -- if the client is denied access to book, access is denied.
  *   If access is granted to book, book.chapter is checked.  (repeat procedure as above)
  *   Lastly, if and only if access is granted to book AND book.chapter, 
  *   book.chapter.page is checked.
  *
  */
	
class security_basic extends standard_extension
{
	public $accounts;                // account module registry
	public $auth_module;             // static module instance with the authenticated account loaded
	public $auth_module_id;          // the id of the auth module
	public $authenticated;           // true if the loaded account has been authenticated
	public $authentication;          // authentication module registry (a subset of $accounts)
	public $err;                     // internal error code to be shared, if one is encountered
	public $err_message;             // error message text
	public $login_path;              // path for a login request
	public $modules;                 // all connected and configured accounts module instances (objects)
	
	protected $auth_explicit;        // (bool) true -> accounts module_id must be provided with
	                                 //        user_id/passphrase pair. a value of false indicates
	                                 //        authentication should be implicit, and check
	                                 //        all enabled auth modules in a defined order
	                                 //        until one authenticates successfully (less secure)
	protected $cache;                // local permissions cache
	protected $client_field = 'guid';
	                                 // the field name to use for client variables
	protected $default_action = 'superadmin';
	                                 // the default action the extension takes when checking an
	                                 //   unconfigured or new item: either 'superadmin', 'allow', or 'deny'
	protected $enabled;              // true if the security system is enabled
	protected $item_field = 'item';  // the field name to use for items
	protected $item_seperator = '.'; // one or more characters seperating nested items
	protected $modules_data;         // cached data for loaded module instances (by module id)
	protected $permissions_table = 'security_permission';
	                                 // the name of the table storing access permissions
	protected $session_mode;         // the session mode (server|database)
	protected $session_qualifier;    // the session variable name
	protected $session_timeout = 28800;
	                                 // the maximum age in seconds of a session
	protected $superadmin_guid = '61123ca4-f075-11df-bff3-aa46601030c1';
	                                 // the security_guid of the superadmin role, group, or account
	protected $userid;               // the loaded user id; stored here for publishing
	protected $username;             // the loaded user name (or id if none); stored here for publishing
	
	# database version
	public $schema_version='0.2.10.2'; // the schema version to match the registered schema
	
	public $_name = 'Basic Security Access Management System Extension';
	public $_version = '1.3.0';
	protected $_debug_prefix = 'security_basic';
	
	/* code */
	
	protected function _construct()
	/* initialize security_basic class
	 *
	 */
	{
		$this->_tx->_publish('authenticated', $this->authenticated);
		$this->_tx->_publish('explicit_authentication', $this->auth_explicit);
		$this->_tx->_publish('security_error', $this->err_message);
		$this->_tx->_publish('security_error_code', $this->err);
		$this->_tx->_publish('login_path', $this->login_path);
		
		if (is_null($this->authenticated)) $this->authenticated = false;
		
		// legacy
		$this->authenticated ? $mode = 'ON' : $mode = 'OFF';
		$this->_debug('AUTHENTICATED Mode ' . $mode);
		
		# in the future the below values should be loaded from a settings table
		$this->auth_explicit = true;
		$this->session_mode = 'database';
		$this->session_qualifier = 'PHPSESSID';
		
		$this->initialize_cache();
		
		# setup authentication modules
		if ($this->initialize_modules() === false) return false;
		
		# set active mode
		$this->enabled = true;
		
		# set implicit authentication if there is only one module
		if (count($this->authentication) <= 1) $this->auth_explicit = false;
		
		return true;
	}
	
	public function access($item, $client = false)
	/* check access to the requested item
	 *
	 * this is the public "wrapper" function for access_specific
	 *	it is this functions job to manage "grouped" or "inherited" access levels
	 *	and implement the access propogation with sub-items.
	 *
	 * the item is arbitrary and should be unique from the object requesting
	 *  the access check. i.e. the developer should use a unique prefix along
	 *  with their request.
	 *
	 * the client is optional; if none is provided the authenticated client
	 *  will be checked; if no client is authenticated PUBLIC will be
	 *  checked
	 *
	 * required:
	 *   item               (string) the item string to check: the permission
	 *
	 * optional:
	 *   client             (string) the client security guid to check [defaults to the authenticated client or PUBLIC]
	 *   client             (object) a client object with a loaded record to check
	 *
	 * returns:
	 *   true or false (false on both error and access denied)
	 *
	 */
	{
		$this->_debug_start("item=$item,client=$client");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		# check for the sub-item string seperator character
		if (strpos($item, $this->item_seperator)) {
			# this item contains sub-items
			$items = explode($this->item_seperator, $item);
			
			for ($i=0;$i<count($items);$i++) {
				# put items back together to check in order, building from
				# most general to most specific
				if ($i > 0) { $items[$i] = $items[$i - 1] . '.' . $items[$i]; }
				
				# now check access for this item
				if (! $this->access_specific($items[$i], $sguid)) {
					return $this->_return(false, "Access Denied to `$item`");
				}
			}
		}
		
		return $this->_return($this->access_specific($item, $sguid));
	}
	
	private function access_specific($item, $client = false, $readonly = false)
	/* check if the specified client has access to the specified item
	 *  returns true if access is allowed or false if it is not
	 *  if the item has no access levels, it will be set to PUBLIC.
	 *
	 * read only mode allows access checks without automatically adding new items
	 *
	 * required:
	 *   item               (string) the item string to check: the permission
	 *
	 * optional:
	 *   client             (string) the client guid to check [defaults to the authenticated client or PUBLIC]
	 *   module_id          (integer) the module id corresponding to the client guid
	 *                                * in explicit mode, module_id is required when a client guid is provided
	 *
	 * returns:
	 *   true or false (false on both error and access denied)
	 *
	 */
	{
		$this->_debug_start("item=$item,client=$client");
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		# check the cache
		if ($sguid === $this->cache['lastSGUID']) {
			# the last client was the same; use the cache for permissions and build it if necessary
			$this->_debug('cache enabled');
			if (is_null($this->cache['client'])) $this->build_cache($sguid);
			# preset check to false
			$cacheCheck = false;
			# check public
			if (@array_key_exists($item, $this->cache['public'])) $cacheCheck = true;
			# check authenticated
			if ( ($this->authenticated) && (@array_key_exists($item, $this->cache['authenticated'])) ) $cacheCheck = true;
			# check client
			if (@array_key_exists($item, $this->cache['client'])) $cacheCheck = true;
			# lastly, if access is still denied make sure this item exists
			if ( ($cacheCheck == false) && (! $readonly) && (! $this->get_authorized_clients($item)) ) {
				# this item does not exist, add it and return the result of that operation
				$cacheCheck = $this->add_item($item);
			}
			if ($cacheCheck === true) { $msg = 'Granted'; } else { $msg = 'Denied'; }
			# return result
			return $this->_return($cacheCheck, "Access $msg via Cache");
		} else {
			# the last client was different, so set it to this client and continue using the database
			$this->initialize_cache();
			$this->cache['lastSGUID'] = $sguid;
		}
		
		# check for the public keyword
		if (db_qcheck_exec(array('table'=>$this->permissions_table,'search_keys'=>array($this->item_field,$this->client_field),'search_values'=>array($item,'PUBLIC')))) {
			return $this->_return(true, 'Access Granted');
		} else {
			# ensure that if we are checking for the PUBLIC keyword, exit here
			if (($sguid == 'PUBLIC') && ($this->default_action != 'allow')) return $this->_return(false, "Access Denied to `$item`");
		}
		
		# now check for AUTHENTICATED
		if ( ($this->authenticated) && (db_qcheck_exec(array('table'=>$this->permissions_table,'search_keys'=>array($this->item_field,$this->client_field),'search_values'=>array($item,'AUTHENTICATED')))) ) {
			return $this->_return(true, 'Access Granted');
		} else {
			# ensure that if we are checking for the AUTHENTICATED keyword, exit here
			if (($sguid == 'AUTHENTICATED') && ($this->default_action != 'allow')) return $this->_return(false, "Access Denied to `$item`");
		}
		
		# now check for the client
		if (db_qcheck_exec(array('table'=>$this->permissions_table,'search_keys'=>array($this->item_field,$this->client_field),'search_values'=>array($item,$sguid)))) {
			return $this->_return(true, 'Access Granted');
		}
		
		# now we're going to load each authorized client and check if its' a group or role -- if so,
		# check the specified client for membership
		$list = $this->get_authorized_clients($item, true);
		$cli_data = $this->_tx->db->query('security_guid', array('guid'), array($sguid), array('type', 'accounts_guid', 'module_id'));
		
		if (db_qcheck($cli_data)) {
			for ($i=0;$i<count($list);$i++) {
				if ($list[$i]['type'] == 'role') {
					if ($this->role_member($list[$i]['accounts_guid'], $sguid, true)) return $this->_return(true, 'Access Granted via role membership');
				} elseif (($cli_data[0]['type']!='role')&&($list[$i]['type'] == 'group')&&($list[$i]['module_id']==$cli_data[0]['module_id'])) {
					$mid = intval($list[$i]['module_id']);
					if ((!is_null($mid))&&in_array($mid, $this->accounts)&&($this->modules[$mid]->group->check_member($cli_data[0]['accounts_guid'], $list[$i]['accounts_guid'], true))) {
						return $this->_return(true, 'Access Granted via group membership');
					}
				}
			}
		}
		
		if (is_object($this->auth_module)) {
			foreach($list as $k=>$v) {
				if ($this->auth_module->group->exists($k)) {
					# if our specified client is a member, grant access
					if ($this->auth_module->group->check_member($client, $k)) {
						return $this->_return(true, 'Access Granted');
					}
				}
			}
		}
		
		# lastly this item is not in the database -- add it!
		if (!$readonly) {
			# this is a new item, add it and quit
			return $this->_return($this->add_item($item));
		}
		
		return $this->_return(false, "Access Denied to `$item`");
	}
	
	public function access_string($item, $client = false)
	/* wrapper function for access, returns bool value as string
	 *	representation "true" or "false"
	 *
	 */
	{
		if ($this->access($item, $client)) return 'true';
		return 'false';
	}
	
	protected function add_item($item)
	/* add an item to the database 
	 *
	 * returns true if successfull and the defaultAction is allow
	 * returns false if the default action is deny
	 * returns -1 if the page was not added
	 *
	 */
	{
		$this->_debug_start();
		
		# set permissions based on the configured default action
		if ($this->default_action == 'allow') {
			$this->_debug('Setting default permissions of allow for new item.');
			$this->grant_access('PUBLIC', $item);
			return $this->_return(true);
		} elseif ($this->default_action == 'superadmin') {
			$this->_debug('Setting default permission for superadmin for the new item.');
			$this->grant_access($this->superadmin_guid, $item);
			return $this->_return(true);
		}
		
		$this->_debug('Setting default permissions of deny for new item.');
		$this->grant_access('NONE', $item);
		
		return $this->_return(false);
	}
	
	public function admin($option = false)
	/* module administration interface
	 *
	 */
	{
		if (!$this->enabled) return false;
		return $this->_content('index');
	}
	
	private function build_cache($sguid)
	/* build the cache for specified client
	 *
	 */
	{
		$this->_debug_start("sguid=$sguid");
		
		# check/build public
		if (is_null($this->cache['public'])) {
			$this->_debug('building public cache');
			$this->cache['public'] = $this->get_authorized_items('PUBLIC');
		}
		
		# check/build authenticated
		if ( ($this->authenticated) && is_null($this->cache['authenticated']) ) {
			$this->_debug('building authenticated cache');
			$this->cache['authenticated'] = $this->get_authorized_items('AUTHENTICATED');
		}
		
		# build client cache
		if ($sguid == 'PUBLIC') {
			$this->cache['client'] = $this->cache['public'];
		} elseif ($sguid == 'AUTHENTICATED') {
			$this->cache['client'] = $this->cache['authenticated'];
		} else {
			$this->cache['client'] = $this->get_authorized_items($sguid);
		}
		
		# ensure the client cache is valid
		if (!is_array($this->cache['client'])) $this->cache['client'] = array();
		
		$r = $this->_tx->db->query('security_guid', array('guid'), array($sguid), array('type', 'accounts_guid', 'module_id'));
		if (db_qcheck($r)) {
			# init local vars
			$groups = array();
			$roles = array();
			$module_id = intval($r[0]['module_id']);
			
			# retrieve a list of groups this client is a member of
			if (($r[0]['type'] != 'role')&&(array_key_exists($module_id, $this->modules))) {
				$tmp = $this->modules[$module_id]->group->membership($r[0]['accounts_guid'], true);
				if ($tmp !== false) $groups = array_keys($tmp);
			}
			
			# retrieve a list of roles this client is a member of
			$tmp = $this->role_membership($sguid, true);
			$list = array_keys($tmp);
			
			# append group permissions to the client permissions
			for($i=0;$i<count($groups);$i++) {
				# load the group
				if (!$this->modules[$module_id]->group->load($groups[$i], 'unique_id')) {
					$this->_debug('Error loading group!'); continue;
				}
				# get the group sguid
				$gsguid = $this->get_sguid($this->modules[$module_id]->group);
				# attempt to retrieve items for this group
				$tmp = $this->get_authorized_items($gsguid, $module_id);
				$this->_debug('found ' . count($tmp) . ' authorized items for the group to merge');
				# merge the items
				if (is_array($tmp))	$this->cache['client'] = array_merge($this->cache['client'], $tmp);
				# get the group's roles
				$tmp = $this->role_membership($gsguid, true);
				$tmplist = array_keys($tmp);
				$list = array_merge($list, $tmplist);
			}
			
			$this->_debug('processing ' . count($list) . ' roles for the requested client');
			for ($i=0;$i<count($list);$i++) {
				# get the role sguid
				$rsguid = $this->role_sguid($list[$i]);
				# attempt to retrieve items for this role
				$tmp = $this->get_authorized_items($rsguid);
				# merge the items
				if (is_array($tmp))	$this->cache['client'] = array_merge($this->cache['client'], $tmp);
			}
		}
		
		return $this->_return(true);
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
  		case 'security_permission': $match['uri'] = '%'; break;
  		case 'security_role_member': $match['uri'] = '%'; break;
  		default: return false;
  	}
  	return $match;
  }
  
	public function clear_authorized_clients($item)
	/* wrapper function for clear_authorized_clients_internal to mask the ignore permissions option
	 *
	 */
	{
		$this->_debug_start("item=$item");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		return $this->_return($this->clear_authorized_clients_internal($item));
	}
	
	protected function clear_authorized_clients_internal($item, $ignore_empty_permissions=false)
	/* revokes access for all clients and resets item access levels to PUBLIC
	 *	returns an array of 0 or more removed clients
	 *
	 */
	{
		$this->_debug_start("item=$item");
		
		# preset return array
		$result = array();
		
		# retrieve the client list for this item
		$clients = $this->_tx->db->query(
				$this->permissions_table,
				array($this->item_field),
				array($item),
				array($this->client_field),
				true);
		
		if (! is_array($clients)) $clients = array();
		
		# remove each client
		while( ($chk = array_shift($clients)) && ($chk[$this->client_field] != 'PUBLIC')) {
			$this->revoke_access_internal($chk[$this->client_field], $item, $ignore_empty_permissions);
			$result[] = $chk[$this->client_field];
		}
		
		return $this->_return($result);
	}
	
	public function clear_authorized_items($client)
	/* revokes access to all items for the specified client
	 *	if this is the only client authorized for an item, that items'
	 *	access permissions will be reset to PUBLIC
	 *	returns an array of items reset to PUBLIC, true, or false
	 *
	 */
	{
		$this->_debug_start("client=$client");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		# this function should never be run on the keyword 'PUBLIC'
		if ($client == 'PUBLIC') {
			return $this->_return(false, 'Error: illegal call to clear_authorized_clients using PUBLIC keyword');
		}
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		# preset return array
		$result = array();
		
		# retrieve item list
		$items = $this->_tx->db->query(
				$this->permissions_table,
				array($this->client_field),
				array($sguid),
				array($this->item_field),
				true);
		
		# remove the client from every item
		while($chk = array_shift($items)) {
			$this->revoke_access($sguid, $chk[$this->item_field]);
			$result[] = $chk[$this->item_field];
		}
		
		return $this->_return($result);
	}
	
	protected function client2sguid(&$client)
	/* handle the various client input conditions for all functions and provide the interpreted sguid value
	 *
	 */
	{
		# handle no client provided
		if ($client===false) {
			if ( is_object($this->auth_module) && ($this->auth_module->user_loaded) && ($this->authenticated) ) {
				$sguid = $this->get_sguid($this->auth_module->user);
			} else {
				if (!is_object($this->auth_module)) $this->_debug('public due to auth module not being set (gear ' . $this->_tx->get->gear . ')');
				#if (!$this->auth_module->user_loaded) $this->_debug('public due to user not being loaded in auth module');
				#if (!$this->authenticated) $this->_debug('public due to no user being authenticated');
				$sguid = 'PUBLIC';
			}
		} elseif (is_object($client)) {
			$sguid = $this->get_sguid($client);
		} elseif (($client === '')&&($this->authenticated)) {
			$sguid = $this->get_sguid($this->auth_module->user);
		} else {
			$sguid = $client;
		}
		$this->_debug("client set: $sguid");
		return $sguid;
	}
	
	protected function generate_guid()
	/* generate a new guid for the security_guid table
	 *
	 */
	{
		return uniqid($this->_tx->db->uuid(), true);
	}
	
	public function get_authorized_clients($item, $more = false)
	/* return an array of authorized clients for the specified item
	 *	returns an array of one or more clients or an empty array
	 *
	 * if more, return data from security_guid table instead of just the guids
	 *
	 */
	{
		if (!$this->enabled) return false;
		
		if ($more) {
			$r = $this->_tx->db->query(
				array($this->permissions_table=>$this->client_field,'security_guid'=>'guid'),
				array($this->permissions_table.','.$this->item_field),
				array($item),
				array($this->permissions_table.','.$this->client_field, 'security_guid,type', 'security_guid,accounts_guid','security_guid,module_id'),
				true
			);
			if (!db_qcheck($r, true)) return array();
			return $r;
		}
		
		# retrieve data
		$r = $this->_tx->db->query($this->permissions_table, array($this->item_field), array($item), array($this->client_field), true);
		if (!db_qcheck($r, true)) return array();
		
		# now parse out access strings into a new array
		$list = array_multi_reduce($r, $this->client_field);
		
		return $list;
	}
	
	public function get_authorized_items($client = '', $module_id = null, $prefix = '', $limit = false)
	/* return an array of authorized items for the specified client
	 *	returns an array of items or false
	 *
	 * if a prefix is provided, match items starting with it and followed by a seperator
	 *  character
	 *
	 * if limit is true, only return items matching the level for prefix, i.e.
	 *  if a prefix is not specified and limit is true, return the top level only
	 *
	 */
	{
		$this->_debug_start("client=$client");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		# preset the type
		$type = null;
		
		# get the client info
		if (($sguid != 'PUBLIC')&&($sguid != 'AUTHENTICATED')&&($sguid != 'NONE')) {
			$tmp = $this->get_client($sguid);
			$module_id = $tmp['module_id'];
			$client = $tmp['guid'];
			$type = $tmp['type'];
		} else {
			$module_id = null;
			$type = 'user';
		}
		
		# verify the module id
		if (($client != 'PUBLIC') && ($client != 'AUTHENTICATED') && ($client != 'NONE') && ($type != 'role') && $this->auth_explicit && (is_null($module_id))) return $this->_return(false, 'A module id is required');
		if ((!is_null($module_id)) && (!in_array(intval($module_id), $this->accounts))) return $this->_return(false, 'Invalid module id');
		
		# validate input
		if ($limit !== true) $limit = false;
		if (!is_string($prefix)) $prefix = '';
		
		# preset result array
		$result = array();
		
		# build the search pattern
		$search_keys = array($this->client_field);
		$search_vals = array($sguid);
		
		if ($limit) {
			$search_keys[] = $this->item_field;
			$search_vals[] = $prefix . $this->item_seperator . "%";
			$search_keys[] = $this->item_field;
			$serach_vals[] = "!= $prefix" . $this->item_seperator . "%" . $this->item_seperator . "%";
		}
		
		if ($prefix != '') {
			$search_keys[] = $this->item_field;
			$search_vals[] = $prefix . $this->item_seperator . "%";
		}
		
		# retrieve data
		$tmp = $this->_tx->db->query($this->permissions_table, $search_keys, $search_vals, array($this->item_field), true);
		
		# now parse out access strings into a new array
		if (is_array($tmp)) {
			foreach($tmp as $v) {
				$result[$v[$this->item_field]] = true;
			}
		}
		
		return $this->_retByRef($result);
	}
	
	public function get_client($sguid)
	/* given an sguid, return client data or false
	 *
	 * returns an array:
	 *   $a['guid']        the client module guid
	 *   $a['module_id']   the module id
	 *   $a['type']        the client type ( role | group | user )
	 *
	 */
	{
		# validate input
		if ((!is_string($sguid))||(strlen($sguid)==0)) return false;
		
		# lookup the provided sguid
		$r = $this->_tx->db->query('security_guid', array('guid'), array($sguid), array('accounts_guid', 'module_id', 'type'));
		if (! db_qcheck($r)) return false;
		
		return array('guid'=>$r[0]['accounts_guid'], 'module_id'=>$r[0]['module_id'], 'type'=>$r[0]['type']);
	}
	
	public function get_clients()
	/* get all unique clients in the database
	 *	returns array of clients or false
	 *
	 */
	{
		$this->_debug_start();
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		$qryStr = 'SELECT DISTINCT `' . $this->client_field . '` FROM `' . $this->permissions_table .
			'` ORDER BY `' . $this->client_field . '` ASC';
		
		$list = $this->_tx->db->queryFull($qryStr);
		if (is_array($list)) {
			foreach ($list as $k=>&$v) {
				$v = $v[$this->client_field];
			}
		}
		
		return $this->_return($list);
	}
	
	public function get_sguid(&$client)
	/* provided accounts object $client, return the assigned security guid
	 *
	 */
	{
		$this->_debug_start();
		if (is_string($client)&&(in_array(strtoupper($client), array('PUBLIC', 'AUTHENTICATED', 'NONE')))) return $this->_return(strtoupper($client), 'Built-in client');
		if ((!is_object($client))||(!($client instanceof user_group))) return $this->_return(false, 'Invalid object provided');
		
		$module_id = $this->get_module_id($client);
		if ($module_id === false) return $this->_return(false, 'Failed to obtain the module id from the provided object');
		
		# get the account guid
		$type = $client->type();
		$guid = $client->{$type}->unique_id;
		
		if ((!is_string($type))||(!is_string($guid))) return $this->_return(false, 'Error loading account information from the provided object');
		
		# lookup the client security guid
		$r = $this->_tx->db->query('security_guid', array('type', 'accounts_guid', 'module_id'), array($type, $guid, $module_id), array('guid'));
		if (db_qcheck($r)) {
			$security_guid = $r[0]['guid'];
		} else {
			$this->_debug('Generating a new Security GUID');
			# generate a new security guid
			$security_guid = $this->generate_guid();
			# add this client
			$this->_tx->db->insert('security_guid', array('guid', 'type', 'accounts_guid', 'module_id'), array($security_guid, $type, $guid, $module_id));
		}
		
		return $this->_return($security_guid);
	}
	
	public function get_sguid_by_name($guid, $module_id = false)
	/* given a guid and module id, return the assigned security id or false on error
	 *
	 * this function will automatically register a security guid if one does not exist
	 *
	 * this function does not alter 'last' values
	 *
	 */
	{
		$this->_debug_start();
		
		# verify the guid
		if ((!is_string($guid))||(strlen($guid)==0)) return $this->_return(false, 'Invalid GUID provided');
		
		# return static guids for reserved names
		if (in_array(strtoupper($guid), array('PUBLIC', 'AUTHENTICATED', 'NONE'))) return strtoupper($guid);
		
		# verify the module id
		if ($this->auth_explicit && ($module_id === false)) return $this->_return(false, 'A module id is required');
		if (($module_id !== false) && (!in_array(intval($module_id), $this->accounts))) return $this->_return(false, 'Invalid module id');
		
		# handle explicit mappings
		if ($module_id !== false) {
			$module_id = intval($module_id);
			$r = $this->_tx->db->query('security_guid', array('accounts_guid', 'module_id'), array($guid, $module_id), array('guid'));
			if (db_qcheck($r)) return $this->_return($r[0]['guid'], 'Found an existing SGUID');
			# generate a new security guid
			$security_guid = $this->generate_guid();
			# verify the account and add the record
			if ($this->modules[$module_id]->user->exists($guid, 'unique_id')) {
				$this->_tx->db->insert('security_guid', array('guid', 'type', 'accounts_guid', 'module_id'), array($security_guid, 'user', $guid, $module_id));
			} elseif ($this->modules[$module_id]->group->exists($guid, 'unique_id')) {
				$this->_tx->db->insert('security_guid', array('guid', 'type', 'accounts_guid', 'module_id'), array($security_guid, 'group', $guid, $module_id));
			} else {
				return $this->_return(false, 'The provided GUID does not exist');
			}
			return $this->_return($security_guid, 'Successfully added new sguid');
		}
		
		# handle implicit mappings
		for ($i=0;$i<count($this->accounts);$i++) {
			if ($this->modules[$this->accounts[$i]]->user->exists($guid, 'unique_id')) {
				$module_id = $this->accounts[$i];
				$type = 'user';
				break;
			} elseif ($this->modules[$this->accounts[$i]]->group->exists($guid, 'unique_id')) {
				$module_id = $this->accounts[$i];
				$type = 'group';
				break;
			}
		}
		
		# if module id is still false after searching then no account was found
		if ($module_id === false) return $this->_return(false, 'The provided GUID does not exist');
		
		# check if this account is registered
		$r = $this->_tx->db->query('security_guid', array('accounts_guid', 'module_id', 'type'), array($guid, $module_id, $type), array('guid'));
		if (db_qcheck($r)) return $this->_return($r[0]['guid'], 'Found an existing SGUID');
		
		# generate a new security guid
		$security_guid = $this->generate_guid();
		
		# add the record
		$this->_tx->db->insert('security_guid', array('guid', 'type', 'accounts_guid', 'module_id'), array($security_guid, $type, $guid, $module_id));
		
		return $this->_return($security_guid, 'Successfully added new sguid');
	}
	
	public function get_items()
	/* get all unique items in the database
	 *	returns array of items or false
	 *
	 */
	{
		$this->_debug_start();
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		$qryStr = 'SELECT DISTINCT `' . $this->item_field . '` FROM `' . $this->permissions_table .
			'` ORDER BY `' . $this->item_field . '` ASC';
		
		$list = $this->_tx->db->queryFull($qryStr);
		if (is_array($list)) {
			foreach ($list as $k=>&$v) {
				$v = $v[$this->item_field];
			}
		}
		
		return $this->_return($list);
	}
  
  public function get_module_id(&$client)
  /* given an accounts object, return the module id
   *
   * if client is not registered, register it
   *
   * returns an integer or false on error
   *
   */
  {
  	$this->_debug_start();
  	
  	# get the provided module data
  	$module = @get_class($client);
  	$uuid = $client->_uuid();
  	if ((!is_string($uuid))||(!is_string($module))) return $this->_return(false, "Invalid accounts object provided ($module, $uuid)");
  	
  	# lookup the module id
  	$r = $this->_tx->db->query('security_module', array('name', 'uuid'), array($module, $uuid), array('id'));
  	if (db_qcheck($r)) {
  		$module_id = $r[0]['id'];
  	} else {
  		# add this module
  		$this->_tx->db->insert('security_module', array('name', 'uuid'), array($module, $uuid));
  		$module_id = $this->_tx->db->insert_id();
  		# refresh modules since the list has changed
  		$this->initialize_modules();
  	}
  	
  	return $this->_return(intval($module_id));
  }
  
  public function get_modules($limit_to_auth_modules = false)
  /* return meta data associated with the loaded module instances
   *
   * if limit_to_auth_modules is true, only return data for authentication modules
   *
   */
  {
  	if ($limit_to_auth_modules) {
  		$return = array();
  		for($i=0;$i<count($this->authentication);$i++) { $return[$this->authentication[$i]] = $this->modules_data[$this->authentication[$i]]; }
  		return $return;
  	} else {
  		return $this->modules_data;
  	}
  }
  
	public function grant_access($client, $item)
	/* this is the public "wrapper" function for grant_access_specific
	 *	it is this functions job to manage "grouped" or "inherited" access levels
	 *	and implement the access propogation with sub-items.
	 *
	 */
	{
		$this->_debug_start("client=$client,item=$item");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		$result = false;
		
		# check for the sub-item string seperator character
		if (strpos($item, $this->item_seperator)) {
			# this item contains sub-items
			$items = explode($this->item_seperator, $item);
			
			for ($i=0;$i<count($items);$i++) {
				# put items back together to grant in order, building from
				# most general to most specific
				if ($i > 0) { $items[$i] = $items[$i - 1] . '.' . $items[$i]; }
				
				# now check access for this item -- ALWAYS apply the request to the exact item!
				if ( (! $this->access_specific($items[$i], $sguid, true)) || ($items[$i] == $item) ) {
					# if this is a keyword other than public, do not apply permissions to anything
					# except for the final goal
					if ( ($sguid == 'AUTHENTICATED') || ($sguid == 'NONE') ) {
						$this->_debug('The client is either AUTHENTICATED or NONE: ' . $sguid);
						# do nothing if the client is auth or none AND this isn't the final item
						if ($items[$i] == $item) {
							$this->_debug('This is the final item: ' . $item);
							# grant this user access to this item
							$result = $this->grant_access_specific($sguid, $items[$i]);
						}
					} else {
						$this->_debug('Granting access to item');
						# grant this user access to this item
						$result = $this->grant_access_specific($sguid, $items[$i]);
					}
				}	
			}
		} else {
			return $this->_return($this->grant_access_specific($sguid, $item));
		}
		
		return $this->_return($result);
	}
	
	protected function grant_access_specific($client, $item)
	/* grant the specified client access to the specified item
	 *	returns true if action succeeded
	 *
	 */
	{
		$this->_debug_start("client=$client,item=$item");
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		if ( ($sguid == 'PUBLIC') || ($sguid == 'RESTRICTED') || ($sguid == 'NONE') ) {
			# if overwriting access with a reserved keyword, first clear the existing permissions
			$this->clear_authorized_clients_internal($item, true);
		}
		
		$this->_debug('granting access to specified client');
		$result = $this->_tx->db->insert(
			$this->permissions_table,
			array($this->client_field, $this->item_field),
			array($sguid, $item));
		
		# now since we have added an explicit permission, make sure no entry for PUBLIC,
		# AUTHENTICATED, or NONE exist
		if ($client != 'PUBLIC') {
			$this->_debug('ensuring PUBLIC does not exist');
			$chk = $this->_tx->db->query($this->permissions_table,array($this->item_field,$this->client_field),array($item,'PUBLIC'));
			if (db_qcheck($chk))	{ /* REMOVE PUBLIC */ $this->revoke_access('PUBLIC', $item); }
		}
		
		if ($client != 'AUTHENTICATED') {
			$this->_debug('ensuring AUTHENTICATED does not exist');
			$chk = $this->_tx->db->query($this->permissions_table,array($this->item_field,$this->client_field),array($item,'AUTHENTICATED'));
			if (db_qcheck($chk))	{ /* REMOVE AUTHENTICATED */ $this->revoke_access('AUTHENTICATED', $item); }
		}
		
		if ($client != 'NONE') {
			$this->_debug('ensuring NONE does not exist');
			$chk = $this->_tx->db->query($this->permissions_table,array($this->item_field,$this->client_field),array($item,'NONE'));
			if (db_qcheck($chk))	{ /* REMOVE NONE */ $this->revoke_access('NONE', $item); }
		}
		
		# update the cache if necessary
		if ($this->cache['lastClient'] == $client) {
			$this->cache['client'][$item] = true;
			if ($client == 'PUBLIC') $this->cache['public'][$item] = true;
			if ($client == 'AUTHENTICATED') $this->cache['authenticated'][$item] = true;
		}
		
		return $this->_return($result);
	}
	
	protected function initialize_cache()
	/* clear and initialize the client cache
	 *
	 */
	{
		$this->_debug_start();
		
		if (! is_array($this->cache)) {
			$this->cache = array('lastClient'=>null, 'lastSGUID'=>null, 'client'=>null, 'public'=>null, 'authenticated'=>null, 'last'=>array('module_id'=>null,'guid'=>null));
		} else {
			$this->cache['client'] = null;
			$this->cache['lastClient'] = null;
			$this->cache['lastSGUID'] = null;
			$this->cache['last'] = array('module_id'=>null,'guid'=>null);
		}
		
		return $this->_return(true);
	}
	
	protected function initialize_modules()
	/* re-initialize the internal module list
	 *
	 * this should only be executed during object initialization or when absolutely necessary
	 *  since it could destroy cached module ids or references
	 *
	 */
	{
		# load account objects
		$this->accounts = array();
		$this->authentication = array();
		$this->auth_module = false;
		$this->auth_module_id = false;
		$this->modules = array();
		$this->modules_data = array();
		$list = $this->_tx->_matchExtensions('accounts');
		if (count($list) == 0) {
			$this->_debug('<strong>Critical Error</strong>: There are no registered account objects.  The security system will be disabled for this session.');
			return false;
		}
		
		# get all registered instances with settings
		$all = $this->_tx->db->query('security_module', '', '', '*', true, array('order'));
		
		# the following check should only fail the very first time the object is called by the transmission
		if (!is_array($all)) return false;
		
		# process all registered instances
		while (count($all) > 0) {
			$name = $all[0]['name'];
			$module_id = intval($all[0]['id']);
			if (array_remove($list, $name, true)) {
				# the current module is enabled; set this instance
				if ($all[0]['enabled']) {
					$this->modules[$module_id] =& $this->_tx->{'my_' . $name . '_' . $module_id};
					if (!is_object($this->modules[$module_id])) { array_shift($all); continue; }
					if (@strlen($all[0]['instance_settings']) > 0) $this->modules[$module_id]->configure(ema_decrypt($all[0]['instance_settings'], ''));
					# if the module is configured and operational, register it
					if ($this->modules[$module_id]->configure()) {
						$this->accounts[] = $module_id;
						$this->modules[$module_id]->module_id($module_id);
						if ($all[0]['authentication']) $this->authentication[] = $module_id;
					}
					$this->modules_data[$module_id] = $all[0];
				}
				array_shift($all);
				# finally, if there are additional instances of this module re-add this module to the list so they can be processed
				if (array_multi_search(array('name'=>$name), $all)!==false) $list[] = $name; 
			} else {
				array_shift($all);
			}
		}
		
		# process all unregistered modules
		while (count($list) > 0) {
			$id = $this->get_module_id($this->_tx->{$list[0]});
			if ($id !== false) {
				# register this module with the default settings
				$this->modules[$id] =& $this->_tx->{'my_' . $list[0] . '_' . $id};
				if ($this->modules[$id]->configure()) {
					$this->accounts[] = $id;
					$this->authentication[] = $id;
					$this->modules[$id]->module_id($id);
				}
			}
			array_shift($list);
		}
		
		return true;
	}
	
	public function last_guid()
	/* return the client security_guid of the last checked account
	 *
	 * neither get_guid() nor generate_guid() affect the last guid
	 *
	 */
	{
		return $this->cache['lastSGUID'];
	}
	
	public function ll($path = '', $format = 0)
	/* login link function
	 *
	 * optionally provide a path to link to, otherwise
	 *   just link to the site home
	 *
	 * optionally specify the output format
	 *   0   display the user id followed by the logout link in brackets
	 *   1   just display the logout link
	 *
	 */
	{
		if (!$this->enabled) return false;
		$pre_link = '';
		$post_link = '';
		if ($this->authenticated) {
			$pre_link = $this->username . ' [ ';
			$post_link = ' ]';
			$text = 'Logout';
			$path = 'logout';
		} else {
			$text = 'Login';
		}
		switch($format) {
			case 1:	return l($text, $path);
		}
		return $pre_link . l($text, $path) . $post_link;
	}
	
	public function login($message = false, $path = false)
	/* display the login window
	 *
	 */
	{
		if (!$this->enabled) return false;
		$this->err_message = $message;
		if (array_key_exists('path', $_POST)) {
			$this->login_path = $_POST['path'];
		} elseif (is_array($path)) {
			$this->login_path = base64_encode(serialize($path));
		} else {
			$this->login_path = $path;
		}
		# return file data
		return $this->_content('login');
	}
	
	public function revoke_access($client, $item)
	/* wrapper function for revoke access to mask the ignore empty permissions option
	 *
	 */
	{
		$this->_debug_start("client=$client,item=$item");
		if (!$this->enabled) return $this->_return(false, 'Security System Disabled');
		return $this->_return($this->revoke_access_internal($client, $item));
	}
	
	protected function revoke_access_internal($client, $item, $ignore_empty_permissions=false)
	/* revoke access to the specified item from the specified client
	 *	returns true if action succeeded
	 *	if there are no access permissions left after this action the
	 *	item will be reset to PUBLIC
	 *
	 */
	{
		$this->_debug_start("client=$client,item=$item");
		
		# get the security guid
		$sguid = $this->client2sguid($client);
		
		$result = $this->_tx->db->delete(
			$this->permissions_table,
			array($this->client_field, $this->item_field),
			array($sguid, $item));
		
		# now check to make sure there is still at least one for this client
		$chk = $this->_tx->db->query(
			$this->permissions_table,
			array($this->item_field),
			array($item));
		
		if ( (! db_qcheck($chk, true)) && (! $ignore_empty_permissions) ) {
			# if no entry was found, re-add the default
			if ($this->default_action == 'allow') {
				$this->_debug('Setting default permissions of allow for new item.');
				$this->grant_access('PUBLIC', $item);
			} elseif ($this->default_action == 'superadmin') {
				$this->_debug('Setting default permissions for superadmin for the new item.');
				$this->grant_access($this->superadmin_guid, $item);
			} else {
				$this->_debug('Setting default permissions of deny for new item.');
				$this->grant_access('NONE', $item);
			}
		}
		
		return $this->_return($result);
	}
	
	public function role_add($name, $description = '')
	/* add a new role
	 *
	 * returns the role id or false
	 *
	 */
	{
		if ((!is_string($name))||(strlen($name)==0)) return false;
		if ($this->role_exists($name)) return false;
		if (!$this->_tx->db->insert('security_role', array('name', 'description'), array($name, $description))) return false;
		$id = $this->_tx->db->insert_id();
		$sguid = $this->generate_guid();
		$this->_tx->db->insert('security_guid', array('guid', 'type', 'accounts_guid'), array($sguid, 'role', $id));
		return $id;
	}
	
	public function role_add_member($id_or_name, $sguid)
	/* add a member to a role
	 *
	 */
	{
		if ((!is_string($id_or_name))&&(!is_integer($id_or_name))) return false;
		# check if it's an id or name
		if (intval($id_or_name) == 0) {
			$id = $this->role_get_id($id_or_name);
			if ($id === false) return false;
		} else {
			$id = $id_or_name;
		}
		if ($this->role_member($id, $sguid, true)) return false;
		return $this->_tx->db->insert('security_role_member', array('role_id', 'guid'), array($id, $sguid));
	}
	
	public function role_catalog()
	/* return a list of all roles
	 *
	 */
	{
		return $this->_tx->db->query('security_role', '', '', '*', true, 'name asc');
	}
	
	public function role_delete($id)
	/* delete a role
	 *
	 */
	{
		$id = @intval($id);
		if (!$this->role_exists($id, 'id')) return false;
		if (!$this->_tx->db->delete('security_role', array('id'), array($id))) return false;
		$this->_tx->db->delete('security_role_member', array('role_id'), array($id));
		$r = $this->_tx->db->query('security_guid', array('type', 'accounts_guid'), array('role', $id), array('guid'));
		if (db_qcheck($r)) {
			$this->_tx->db->delete('security_guid', array('type', 'accounts_guid'), array('role', $id));
			$this->_tx->db->delete('security_role_member', array('guid'), array($r[0]['guid']));
		}
		return true;
	}
	
	public function role_exists($identifier, $field='name')
	/* check if the specified role exists
	 *
	 */
	{
		if ((!is_string($identifier))&&(!is_integer($identifier))) return false;
		if (!in_array($field, array('id', 'name'))) return false;
		return db_qcheck_exec(array('table'=>'security_role','search_keys'=>array($field),'search_values'=>array($identifier)));
	}
	
	public function role_get_id($name)
	/* get the role id for the specified role name
	 *
	 */
	{
		if ((!is_string($name))||(strlen($name)==0)) return false;
		$r = $this->_tx->db->query('security_role', array('name'), array($name), array('id'));
		if (!db_qcheck($r)) return false;
		return $r[0]['id'];
	}
	
	public function role_member($id, $sguid, $nested = false)
	/* check of the provided sguid is a member of the specified role
	 *
	 * if nested is true, check inherited membership as well
	 *
	 */
	{
		if ((!is_string($id))&&(!is_integer($id))) return false;
		if (!is_string($sguid)) return false;
		
		# handle recursive history processing via nested variable
  	if (is_array($nested)) {
  		$nest_history = $nested;
  		$nested = true;
  	} else {
  		$nest_history = array($id);
  	}
  	
  	if (db_qcheck_exec(array('table'=>'security_role_member','search_keys'=>array('role_id', 'guid'),'search_values'=>array($id, $sguid)))) {
  		return true;
  	}
  	
  	# conditionally check nested groups as well
  	if ($nested) {
  		# retrieve a list of member groups
 			$list = $this->_tx->db->query('security_role_member',array('role_id'),array($id),array('guid'),true);
  		if (!db_qcheck($list, true)) return false;
  		for ($i=0;$i<count($list);$i++) {
  			if (in_array($list[$i]['guid'], $nest_history)) continue;
  			$nest_history[] = $list[$i]['guid'];
  			$role_id = $this->_tx->db->query('security_guid', array('type', 'guid'), array('role', $list[$i]['guid']), array('accounts_guid'));
  			if ((db_qcheck($role_id))&&($this->role_member($role_id[0]['accounts_guid'], $sguid, $nest_history))) return true;
  		}
  	}
  	
  	return false;
	}
	
	public function role_members($id_or_name)
	/* return a list of members in the specified role
	 *
	 */
	{
		if ((!is_string($id_or_name))&&(!is_integer($id_or_name))) return false;
		# check if it's an id or name
		if (intval($id_or_name) == 0) {
			$id = $this->role_get_id($id_or_name);
			if ($id === false) return false;
		} else {
			$id = $id_or_name;
		}
		$r = $this->_tx->db->query('security_role_member', array('role_id'), array($id), array('guid'), true);
		if (!db_qcheck($r, true)) return array();
		return array_multi_reduce($r, 'guid');
	}
	
	public function role_membership($sguid, $include_inherited = false, $recursive = false)
	/* return a list of roles the specified security guid is a member of
	 *
	 */
	{
  	if ((!is_string($sguid))||(strlen($sguid)==0)) return $this->_return(false, 'Invalid Security GUID');
  	if ($include_inherited !== true) $include_inherited = false;
  	
  	# initialize the return array
  	$list = array();
  	
  	# get a list of roles this account is a member of
  	$check = $this->_tx->db->query('security_role_member', array('guid'), array($sguid), array('role_id'), true);
  	if (!db_qcheck($check, true)) $check = array();
  	
  	# preset inheritance value
  	if ($recursive === true) { $flag = 1; } else { $flag = 0; }
  	
  	# process the result list
  	for ($i=0;$i<count($check);$i++){
  		$list[$check[$i]['role_id']] = $flag;
  		$rsguid = $this->role_sguid($check[$i]['role_id']);
  		if ($include_inherited) {
  			$inherited = $this->role_membership($rsguid, true, true);
  			if (count($inherited) > 0) array_merge_keys($list, $inherited);
  		}
  	}
  	
  	return $list;
	}
	
	public function role_name($sguid)
	/* given a role sguid, return its name
	 *
	 */
	{
		if (!is_string($sguid)) return false;
		if ((strcasecmp($sguid, 'PUBLIC')==0)||(strcasecmp($sguid, 'AUTHENTICATED')==0)||(strcasecmp($sguid, 'NONE')==0)) return strtoupper($sguid);
		
		$r = $this->_tx->db->query('security_guid', array('type', 'guid'), array('role', $sguid), array('accounts_guid'));
		if (!db_qcheck($r)) return false;
		$id = $r[0]['accounts_guid'];
		
		$r = $this->_tx->db->query('security_role', array('id'), array($id), array('name'));
		if (!db_qcheck($r)) return false;
		return $r[0]['name'];
	}
	
	public function role_remove_member($id_or_name, $sguid)
	/* remove a member from a role
	 *
	 */
	{
		if ((!is_string($id_or_name))&&(!is_integer($id_or_name))) return false;
		# check if it's an id or name
		if (intval($id_or_name) == 0) {
			$id = $this->role_get_id($id_or_name);
			if ($id === false) return false;
		} else {
			$id = $id_or_name;
		}
		if (!$this->role_member($id, $sguid)) return true;
		return $this->_tx->db->delete('security_role_member', array('role_id', 'guid'), array($id, $sguid));
	}
	
	public function role_search($criteria, $multiple_results = false, $partial_match = false, $comparison_type = 'AND')
	/* find a role
	 *
	 */
	{
		return false;
	}
	
	public function role_sguid($id_or_name)
	/* given a role id or role name, return the role sguid
	 *
	 */
	{
		if ((!is_string($id_or_name))&&(!is_integer($id_or_name))) return false;
		# check if it's an id or name
		if (intval($id_or_name) == 0) {
			$id = $this->role_get_id($id_or_name);
			if ($id === false) return false;
		} else {
			$id = $id_or_name;
		}
		$r = $this->_tx->db->query('security_guid', array('type', 'accounts_guid'), array('role', $id), array('guid'));
		if (!db_qcheck($r)) return false;
		return $r[0]['guid'];
	}
	
	public function session_close()
	/* close session callback (for database storage)
	 *
	 */
	{
		return true;
	}
	
	public function session_destroy($id)
	/* destroy session callback (for database storage)
	 *
	 */
	{
		$this->_tx->db->delete('security_session', array('session_id'), array($id));
		return true;
	}
	
	public function session_gc()
	/* gc session callback (for database storage)
	 *
	 */
	{
		$time = time();
		$this->_tx->db->delete('security_session', array('expires'), array("< $time"));
		return true;
	}
	
	public function session_open()
	/* open session callback (for database storage)
	 *
	 */
	{
		return true;
	}
	
	public function session_read($id)
	/* read session callback (for database storage)
	 *
	 */
	{
		# initialize result
		$data = '';
		
		# fetch session data from the selected database
		$time = time();
		
		$result = $this->_tx->db->query('security_session', array('session_id', 'expires'), array($id, "> $time"), array('session_data'));
		if (db_qcheck($result)) {
			# get the session data
			$data = $result[0]['session_data'];
			# automatically update the expiration
			$this->_tx->db->update('security_session', array('session_id'), array($id), array('expires'), array(time() + $this->session_timeout));
		}
		
		return $data;
	}
	
	protected function session_start()
	/* start a session
	 *
	 */
	{
		# Generating cookies must take place before any HTML.
		# Check for existing "SessionId" cookie
		if (array_key_exists($this->session_qualifier, $_COOKIE)) {
			$id = $_COOKIE[$this->session_qualifier];
		} else {
			$id = '';
		}
		
		if ($id == '') {
			# generate a random number to randomize the session_ids
			srand ((double) microtime( )*100000000);
			$rand1 = (rand()%1000);
			for ($counter=0;$counter<100;$counter++) { $random[$counter]=$rand1 * $counter; }
			$rand1 = (rand()%100);
			# Use user's IP address to make more unique.
			$random_string = (intval(md5('lets go bucks!')) * $random[12]) . $random[44] . 'f' . $random[0] . 'u';
			$random_string .= $random[10] . 'd' . $random[13] . 'ge' . $random[20] . 'm' . $random[33];
			$random_string .= bin2hex('*') . $random[12] . 'c' . $random[18] . 'h' . $random[86] . bin2hex('*');
			$random_string .= $random[88] . 'g' . $random[99] . 'a' . $random[10] . 'n' . $random[7];
			$random_string .= (intval(bin2hex($_SERVER['REMOTE_ADDR'])) * $random[37]) . ($random[76] % 5);
			$random_string .= $random[69] . ($random[24] * $random[56]);
			if (strlen($random_string) > 110) { $random_string = left($random_string, 110); }
			$id = uniqid($random_string);
		}
		
		# temporarily define static values
		$httponly = false;
		
		# initialize the session parameters
		session_id($id);
		session_name($this->session_qualifier);
		
		# set site expiration time
		@ini_set('session.gc_maxlifetime', (string)$this->session_timeout);
		session_cache_limiter('private'); // set to private_no_expire for non dynamic content
		session_cache_expire($this->session_timeout / 60);
		#@session_set_cookie_params($this->session_timeout , url(), $this->_tx->get->domain, $this->_tx->get->ssl, $httponly);
		
		# disallow php version header
		if (function_exists('header_remove')) @header_remove('X-Powered-By');
		
		# start the session
		if (session_start() === false) { $this->_debug('UNABLE TO INIT SESSION!'); }
		@header("Cache-control: private");
	}
	
	public function session_terminate()
	/* terminate a session (e.g. for logout)
	 *
	 */
	{
		# grab session id before removing the cookie
		$session = $_COOKIE[$this->session_qualifier];
		# Kill the Cookie by setting its expiration in the past
		@header('Set-Cookie: ' . $this->session_qualifier . '=; Max-Age=-' . $this->session_timeout . '; Domain=' . $this->_tx->get->domain . '; Path=/; secure;');
		unset($_COOKIE[$this->session_qualifier]);
		# now we have to start the session in order to terminate it on the server
		session_id($session);
		@header("Cache-control: private");
		$this->session_destroy($session);
		if (is_object($this->_tx->get->html)) {
			# redirect the client home
			$this->_tx->get->html->head->cc('meta')->sas(array('http-equiv'=>'refresh', 'content'=>'0;url=' . url()));
			# display notice
			$this->_tx->get->html->body->cc('h1', 'You have been logged out');
		}
		$this->authenticated = false;
	}
	
	public function session_write($id, $data)
	/* write session callback (for database storage)
	 *
	 */
	{
		# conditionally update the session data
		if (strlen($data) > 0) {
			$this->_tx->db->lock('security_session', 'write');
			$this->_tx->db->delete('security_session', array('session_id'), array($id));
			$this->_tx->db->insert('security_session', array('session_id', 'session_data', 'expires'), array($id, $data, time() + $this->session_timeout));
			$this->_tx->db->unlock();
		}
		
		return true;
	}
	
	/******************************************************************************
	 * GEAR FUNCTIONS
	 *
	 */
	
	public function initialize()
	/* gear function initialize
	 *
	 */
	{
		if (!$this->enabled) return false;
		$this->_tx->_publish('userid', $this->userid);
		$this->_tx->_publish('username', $this->username);
		
		if ($this->session_mode == 'database') {
			# register this object as the session handler
			session_set_save_handler( 
				array( &$this, "session_open" ), 
				array( &$this, "session_close" ),
				array( &$this, "session_read" ),
				array( &$this, "session_write"),
				array( &$this, "session_destroy"),
				array( &$this, "session_gc" )
			);
		}
		
		# register with the system cache
		if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
			$this->_tx->cache->register_table('security_permission');
			$this->_tx->cache->register_table('security_role_member');
		}
		
		$this->session_start();
		
		# check for login
		if (array_key_exists('active', $_SESSION) && ($_SESSION['active'])) {
			$module_id = @intval($_SESSION['auth_module_id']);
			if (array_key_exists($module_id, $this->modules_data)) {
				$this->auth_module =& $this->_tx->{'new_' . $this->modules_data[$module_id]['name']};
				if (@strlen($this->modules_data[$module_id]['instance_settings']) > 0) {
					$this->auth_module->configure(ema_decrypt($this->modules_data[$module_id]['instance_settings'], ''));
				}
				if ($this->auth_module->user->load($_SESSION['auth_uid'])) {
					$this->authenticated = true;
				} else {
					$this->_debug("unable to load auth module for " . $_SESSION['auth_uid']);
				}
				$this->auth_module_id = $module_id;
				$this->userid = $this->auth_module->user->uid;
		  	$this->username = $this->auth_module->user->display_name;
			} else {
				$this->_debug("module `$module_id` was not found, unable to restore session");
			}
		}
	}
	
	public function validate()
	/* gear function validate
	 *
	 */
	{
		if (!$this->enabled) return false;
		
		if ($this->authenticated) session_write_close();
		
		# process any pending login request
		if (($this->auth_explicit && array_key_exists('domain', $_POST) && array_key_exists('userid', $_POST) && array_key_exists('password', $_POST))  ||
		    ((!$this->auth_explicit) && array_key_exists('userid', $_POST) && array_key_exists('password', $_POST))) {
		  $authed = false;
		  if ($this->auth_explicit) {
		  	$module_id = @intval($_POST['domain']);
		  	if (
		  		array_key_exists($module_id, $this->modules) &&
		  		$this->modules[$module_id]->user->load($_POST['userid']) && 
		  		$this->modules[$module_id]->user->authenticate($_POST['password'])) { $authed = true; }
		  } else {
		  	for($i=0;$i<count($this->authentication);$i++) {
		  		$module_id = $this->authentication[$i];
		  		if ($this->modules[$module_id]->user->load($_POST['userid']) && $this->modules[$module_id]->user->authenticate($_POST['password'])) {
		  			$authed = true;
		  			break;
		  		}
		  	}
		  }
			if ($authed) {
				# successfully authenticated
				# load the auth module
				$this->authenticated = true;
				$this->auth_module =& $this->_tx->{'new_' . $this->modules_data[$module_id]['name']};
				if (@strlen($this->modules_data[$module_id]['instance_settings']) > 0) $this->auth_module->configure(ema_decrypt($this->modules_data[$module_id]['instance_settings'], ''));
				$this->auth_module->user->load($_POST['userid']);
				$this->auth_module_id = $module_id;
				$this->userid = $this->auth_module->user->uid;
		  	$this->username = $this->auth_module->user->display_name;
				if (array_key_exists('path', $_POST)&&(strlen($_POST['path']) > 0)) {
					if (@unserialize(base64_decode($_POST['path']))) {
						$this->_tx->set->pageid = unserialize(base64_decode($_POST['path']));
					} else {
						$this->_tx->set->pageid = $_POST['path'];
					}
				} else {
					$this->_tx->set->pageid = $this->_tx->get->default_home;
				}
				$this->_tx->set->content_type = 'html';
				$_SESSION['active']	= true;
				$_SESSION['auth_module_id'] = $module_id;
				$_SESSION['auth_uid'] = $_POST['userid'];
				@session_write_close();
			} else {
				$this->_tx->set->pageid = array('provides'=>'security', 'function'=>'login');
				$this->_tx->set->fuse_args = array('Authentication Failed');
			}
		}
		
		# validate access for this request
		$fuse = $this->_tx->get->fuse;
		$pageid = $this->_tx->get->pageid;
		if (is_array($pageid)) {
			# special case for LEGACY built-in dynamic content requests
			if ($fuse['function'] == '_content') {
				$check = $fuse['provides'] . $this->item_seperator . $this->_tx->get->fuse_args[0];
			} elseif ($fuse['function'] == '_xml') {
				$check = $fuse['provides'] . $this->item_seperator . 'xml' . $this->item_seperator . $this->_tx->get->fuse_args[0];
			} else {
				$check = $fuse['provides'] . $this->item_seperator . $fuse['function'];
			}
		} else {
			$check = 'content_id' . $this->item_seperator . $pageid;
		}
		
		if (! $this->access($check)) {
			$this->_tx->set->title = $this->_tx->get->site_title;
			$this->_tx->set->content_type = 'html';
			$this->_tx->set->type = 'fuse';
			$this->_tx->set->mech_override = false;
			# Access Denied
			if ($this->authenticated) {
				# an account is logged in; show the access denied page
				$this->_tx->set->pageid = $this->_tx->get->page_401;
			} else {
				# show the login page
				$this->_tx->set->fuse = array('provides'=>'security', 'function'=>'login');
				$this->_tx->set->fuse_args = array(false, $this->_tx->get->pageid);
				$this->_tx->set->pageid = array();
			}
		}
	}
	
	public function optimize()
	/* close session write
	 *
	 */
	{
		# do not end the session prematurely if we are logging out
		$fuse = $this->_tx->get->fuse;
		if (is_array($fuse)&&array_key_exists('provides', $fuse)&&array_key_exists('function', $fuse)&&($fuse['provides']=='security_basic')&&($fuse['function']=='session_terminate')) {
			return true;
		}
		session_write_close();
	}
	
}
?>