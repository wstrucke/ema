<?php
 /* create a new user
  *
  * required:
  *   0:uid              the user id to create
  *   1:password         the initial password for the account
  *
  * optional:
  *   N/A
  *
  * returns:
  *   OK or error
  *
  */
  
  # get the group id
  if (count($_xml_args) > 1) {
  	$uid = $_xml_args[0];
  	$password = $_xml_args[1];
  } else {
  	$uid = '';
  	$password = '';
  }
	if (array_key_exists('uid', $_POST)) $uid = @urldecode($_POST['uid']);
	if (array_key_exists('password', $_POST)) $password = @urldecode($_POST['password']);
	
	# validate input
	if (strlen($uid)==0) return xml_error('No user id provided');
	
	# clear any loaded user
	$this->user->clear();
	
	# load any posted values
	$fields = $this->user->get_fields();
	for ($i=0;$i<count($fields);$i++) {
		if (@array_key_exists($fields[$i], $_POST)&&(@strlen($_POST[$fields[$i]])>0)) {
			$this->user->$fields[$i] = $_POST[$fields[$i]];
		}
	}
	
	# set the user id
	$this->user->uid = $uid;
	
	# attempt to create the account
	if (!$this->user->add($password)) return xml_error('Error creating user account');
	
	return xml_response();
?>