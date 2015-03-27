<?php
 /* administratively set a users' password
  *
  * required:
  *   0:uid              the user id to manipulate
  *   1:password         the password to set
  *
  * optional:
  *   N/A
  *
  * returns:
  *   OK or error
  *
  */
  
  # get the user id
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
	
	# load the account
	if (!$this->user->load($uid)) return xml_error('Error loading user account');
	
	# enable the account
	if (!$this->user->set_password($password)) return xml_error('Error changing password');
	
	return xml_response();
?>