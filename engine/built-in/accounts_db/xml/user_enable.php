<?php
 /* enable an existing user account
  *
  * required:
  *   0:uid              the user id to enable
  *
  * optional:
  *   N/A
  *
  * returns:
  *   OK or error
  *
  */
  
  # get the user id
  if (count($_xml_args) > 0) { $uid = $_xml_args[0]; } else { $uid = ''; }
	if (array_key_exists('uid', $_POST)) $uid = @urldecode($_POST['uid']);
	
	# validate input
	if (strlen($uid)==0) return xml_error('No user id provided');
	
	# load the account
	if (!$this->user->load($uid)) return xml_error('Error loading user account');
	
	# enable the account
	if (!$this->user->enable()) return xml_error('Error enabling account');
	
	return xml_response();
?>