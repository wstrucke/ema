<?php
 /* disable an existing user account
  *
  * required:
  *   0:uid              the user id to disable
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
	if (($uid == $this->_tx->security->auth_module->uid)&&($this->_uuid() == $this->_tx->security->auth_module->_uuid())) {
		return xml_error('Disabling your own account is not allowed');
	}
	
	# load the account
	if (!$this->user->load($uid)) return xml_error('Error loading user account');
	
	# disable the account
	if (!$this->user->disable()) return xml_error('Error disabling account');
	
	return xml_response();
?>