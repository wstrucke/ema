<?php
 /* OSU Marching & Athletic Bands Module
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Sep-01-2011
  * William Strucke, wstrucke@gmail.com
  *
  */
  
class osu_athleticbands extends standard_extension
{
  # public / directly accessible variables
  #public $var;                     // (type) description
  
  # variables to be shared on demand
  #protected $var2;                 // (type) description
  
  # internal variables
  #protected $var3;                 // (type) description
  
  # database version
  public $schema_version='0.1.0';  // the schema version to match the registered schema
  
  protected $_debug_prefix = 'osu_athleticbands';
                                   // object debug output prefix
  public $_name = 'OSU Marching & Athletic Bands';
                                   // the module name
  public $_version = '1.0.0';      // the loaded object's version string
  
  /* code */
  
  public function __clone()
  /* handle clone processes
   *
   */
  {
  }
  
  public function &__get($item)
  {
  }

  public function __set($field, $value)
  {
  }
  
  public function _construct()
  /* initialize the object
   *
   */
  {
  	# get the accounts_shib module
  	$modules = $this->_tx->security->get_modules();
  	$mid = false;
  	foreach ($modules as $i=>$v) {
  		if ($modules[$i]['name'] == 'accounts_shib') { $mid = $modules[$i]['id']; break; }
  	}
  	if ($mid === false) return $this->_debug('construct::shibboleth module unavailable');
  	
  	# make sure our preset groups exist
  	if(!$this->_tx->security->modules[$mid]->group->exists('marching_band')) {
  		# create all of the groups
  		$list = array(
  			array('athletic_band', 1, 'OSU Athletic Band', 'Athletic Band'),
  			array('marching_band', 2, 'OSU Marching Band', 'Marching Band'),
  			array('staff', 3, 'Student Staff', 'Student Staff'),
  			array('squad_leaders', 4, 'Marching Band Squad Leaders', 'Squad Leaders'),
  			array('staff_leaders', 5, 'Head Student Staff', 'Head Staff'),
  			array('directors', 6, 'Band Directors', 'Directors'),
  			array('admins', 7, 'Web Site Admnistrators', 'Administrators')
  		);
  		for ($i=0;$i<count($list);$i++) {
  			$this->_tx->security->modules[$mid]->group->unload();
  			$this->_tx->security->modules[$mid]->group->gid = $list[$i][0];
  			$this->_tx->security->modules[$mid]->group->gid_number = $list[$i][1];
  			$this->_tx->security->modules[$mid]->group->description = $list[$i][2];
  			$this->_tx->security->modules[$mid]->group->display_name = $list[$i][3];
  			$this->_tx->security->modules[$mid]->group->enabled = true;
  			$this->_tx->security->modules[$mid]->group->add();
  		}	
  	}
  }
  
  public function recent_uploads($num = 5, $category = false)
  /* FILAMENT: return an unordered list of recent $num uploads
   *
   * if category is specified, limit to uploads in that category
   *
   */
  {
  	return "<ul><li>recents</li></ul>";
  }
  
  protected function reply($reply_message)
  /* append log file and send message to browser (application)
   *
   */
  {
  	//append('/appinterface.log', $reply_message);
  	echo $reply_message;
  	
  	return;
  }
  
  public function sync()
  /* sync function to accept authentication and data from the band database
   *
   * should be linked to the path /backdoor.php
   *
   * legacy roles:
   *  public          0
   *  athletic band   1
   *  marching band   2
   *  student staff		3
   *  squad leaders   4
   *  staff leaders   5
   *  directors       6
   *  site admins     7
   *
   * legacy files to new mappings:
   *  grant.wb        shib_user table via accounts_shib module
   *  banddata.wb     osu_people table
   *  osum_band.wb    osu_mband table
   *  osua_band.wb    osu_aband table
   *  staff.wb        osu_staff table
   *
   * get variables:
   *  authname        user id to authenticate
   *  authcode        user password
   *  file            file id to update
   *  idx             number of records
   *  rN              encoded records where N in {0..(idx-1)}
   *
   */
  {
  	# authenticate the user
  	$authname = $_REQUEST['authname'];
  	$authcode = $_REQUEST['authcode'];
  	
  	$modules = $this->_tx->security->get_modules(true);
  	$mid = false;
  	
  	# find the accounts_db module
  	foreach ($modules as $i=>$v) {
  		if ($modules[$i]['name'] == 'accounts_db') { $mid = $modules[$i]['id']; break; }
  	}
  	if ($mid === false) die('Accounts Module Not Found. Please contact the Site Administrator');
  	if (!$this->_tx->security->modules[$mid]->user->load($authname)) die('Unknown User');
  	if (!$this->_tx->security->modules[$mid]->user->authenticate($authcode)) die('Bad Password');
  	
  	# successfully authenticated
  	$this->reply("200 OK: AUTH SUCCESS " . $authname . "@" . $_SERVER['REMOTE_ADDR'] . " " . date("m/d/Y H:i:s") . "\r\n");
  	
  	# get the file information
  	$file = $_REQUEST['file'];
  	if (array_key_exists('idx', $_REQUEST)) {
  		$idx = intval($_REQUEST['idx']);
  	} else {
  		$idx = 0;
  	}
  	
  	for ($i = 0; $i < $idx; $i++) {
  		if ($file == 1) {
  			# remove ssns from posted data
  			$tmp = explode('|', $_REQUEST['r' . $i]);
  			$tmp[2] = '';
  			$tmp = implode('|', $tmp);
  		} else {
  			$tmp = $_REQUEST['r' . $i];
  		}
  		$data[$i] = $tmp;
  	}
  	
  	switch ($file) {
  		case 5: { $file_name = "grant.wb"; $this->wb_grant($data); break; }
  		case 1: { $file_name = "banddata.wb"; $this->wb_people($data); break; }
  		case 2: { $file_name = "osum_band.wb"; $this->wb_mband($data); break; }
  		case 3: { $file_name = "osua_band.wb"; $this->wb_aband($data); break; }
  		case 4: { $file_name = "staff.wb"; $this->wb_staff($data); break; }
  		default: { /* invalid value */ $this->reply("400 ERROR: FILE $file NOT INDEXED\r\n"); exit; }
  	} //switch
  	
  	$this->reply("202 FILE_ID: $file_name\r\n202 ACK: RECVD $idx RECORDS\r\n");
  	
  	#$config->UpdateConfiguration("last_synch", date('Y-m-d'));
  	
  	$this->reply("201 DONE: FILE UPDATED\r\n");
  }
  
  protected function wb_grant($data)
  /* legacy grant.wb
   *
   * grant.wb handled the list of authorized users
   * since that is now handled by the shibboleth module, this function will
   * interface with it
   *
   * expected data format:
   *   name.#|legacy_permissions
   *
   */
  {
  	# validate input
  	if (!is_array($data)) return $this->reply("406 Missing File Data\r\n");
  	
  	# get the accounts_shib module
  	$modules = $this->_tx->security->get_modules();
  	$mid = false;
  	foreach ($modules as $i=>$v) {
  		if ($modules[$i]['name'] == 'accounts_shib') { $mid = $modules[$i]['id']; break; }
  	}
  	if ($mid === false) return $this->reply("404 Shibboleth Module Unavailable, Unable to set authorized users\r\n");
  	
  	# clear all existing user accounts
  	$this->_tx->security->modules[$mid]->user->delete_all();
  	
  	for($i=0;$i<count($data);$i++){
  		$record = explode('|', $data[$i]);
  		# create the user account
  		$this->_tx->security->modules[$mid]->user->unload();
  		$this->_tx->security->modules[$mid]->user->uid = $record[0];
  		$this->_tx->security->modules[$mid]->user->enabled = true;
  		$this->_tx->security->modules[$mid]->user->add();
  		while(strlen($record[1]) > 0) {
  			$gid_number = substr($record[1], 0, 1);
  			$record[1] = substr($record[1], 1);
  			if ($this->_tx->security->modules[$mid]->group->load($gid_number, 'gid_number')) {
  				$guid = $this->_tx->security->modules[$mid]->user->unique_id;
  				$this->_tx->security->modules[$mid]->group->add_member($guid);
  			}
  		}
  	}
  	
  	$this->reply("200 Success\r\n");
  }
  
  protected function wb_people($data)
  /* legacy banddata.wb
   *
   * expected data format:
   *   name.#|mobile|-discard-|home_address|home_address_2|home_city|home_state|home_zip|home_county|home_phone|campus_address|
   *     campus_address_2|campus_city|campus_state|campus_zip|campus_phone|allergies|vegeterian|gender|high_school|
   *     hs_graduation_year|year_in_band|im_name|im_service
   *
   */
  {
  	# validate input
  	if (!is_array($data)) return $this->reply("406 Missing File Data\r\n");
  	
  	# delete all records
  	$this->_tx->db->delete('osu_people', array(1), array(1));
  	
  	# set the array keys
  	$keys = array('namen', 'mobile', 'home_address', 'home_address_2', 'home_city', 'home_state', 'home_zip', 'home_county',
  	  'home_phone', 'campus_address', 'campus_address_2', 'campus_city', 'campus_state', 'campus_zip', 'campus_phone',
  	  'allergies', 'vegeterian', 'gender', 'high_school', 'hs_graduation_year', 'year_in_band', 'im_name', 'im_service');
  	
  	# be sure to discard the third field (position 2 starting from zero) provided to osu_people
  	for($i=0;$i<count($data);$i++){
  		$record = explode('|', $data[$i]);
  		# remove key 2
  		array_splice($record, 2, 1);
  		# insert data
  		$this->_tx->db->insert('osu_people', $keys, $record);
  	}
  }
  
  protected function wb_mband($data)
  /* legacy osum_band.wb
   *
   * expected data format:
   *   name.#|first|last|email|instrument|part|row|number|year_in_band|squad_leader
   *
   */
  {
  	# validate input
  	if (!is_array($data)) return $this->reply("406 Missing File Data\r\n");
  	
  	# delete all records
  	$this->_tx->db->delete('osu_mband', array(1), array(1));
  	
  	# set the array keys
  	$keys = array('namen', 'first', 'last', 'email', 'instrument', 'part', 'row', 'number', 'year_in_band', 'squad_leader');
  	
  	for($i=0;$i<count($data);$i++){
  		$record = explode('|', $data[$i]);
  		# insert data
  		$this->_tx->db->insert('osu_mband', $keys, $record);
  	}
  }
  
  protected function wb_aband($data)
  /* legacy osua_band.wb
   *
   * expected data format:
   *   name.#|first|last|email|instrument|part|row|number|grouping|section_leader
   *
   */
  {
  	# validate input
  	if (!is_array($data)) return $this->reply("406 Missing File Data\r\n");
  	
  	# delete all records
  	$this->_tx->db->delete('osu_aband', array(1), array(1));
  	
  	# set the array keys
  	$keys = array('namen', 'first', 'last', 'email', 'instrument', 'part', 'row', 'number', 'grouping', 'section_leader');
  	
  	for($i=0;$i<count($data);$i++){
  		$record = explode('|', $data[$i]);
  		# insert data
  		$this->_tx->db->insert('osu_aband', $keys, $record);
  	}
  }
  
  protected function wb_staff($data)
  /* legacy staff.wb
   *
   * expected data format:
   *   name.#|first|last|email|position|year_on_staff
   *
   */
  {
  	# validate input
  	if (!is_array($data)) return $this->reply("406 Missing File Data\r\n");
  	
  	# delete all records
  	$this->_tx->db->delete('osu_staff', array(1), array(1));
  	
  	# set the array keys
  	$keys = array('namen', 'first', 'last', 'email', 'position', 'year_on_staff');
  	
  	for($i=0;$i<count($data);$i++){
  		$record = explode('|', $data[$i]);
  		# insert data
  		$this->_tx->db->insert('osu_staff', $keys, $record);
  	}
  }
  
}
?>