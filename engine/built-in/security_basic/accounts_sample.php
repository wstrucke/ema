<?php
 /* SAMPLE (Authentication) Module for the Security System
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, M-D-Y
  * Author, e-mail
  *
  * Implements accounts, groups, and membership
  *
  */

class accounts_sample extends user_group
{
  # public / directly accessible variables
  #public $var;                     // (type) description
  
  # variables to be shared on demand
  #protected $var2;                 // (type) description
  
  # internal variables
  #protected $var3;                 // (type) description
  
  # database version
  #public $schema_version='0.0.1';  // the schema version to match the registered schema
  
  protected $_debug_prefix = '';
                                   // object debug output prefix
  public $_name = '';
                                   // the module name
  public $_version = '1.0.0';      // the loaded object's version string
  
  /* code */
  
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
  }
  
  public function admin($option = false)
	/* module administration interface
	 *
	 */
	{
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
  }
  
  public function get_fields()
  /* returns an array of object fields usable for the loaded account type
   *
   * returns an empty array on error (with output to debug)
   *
   */
  {
  }
  
  public function login($option)
  /* contract for login: output the login form or selection text for a non-standard login mechanism
   *
   * required:
   *   option             (string) what to output [ select | login ]
   *
   * optional:
   *   n/a
   *
   * returns:
   *   string, the html to be output by the security module
   *
   * implementation notes:
   *   this function is optional and only used for non-standard authentication mechanisms
   *
   */
  {
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
  }  
}
?>