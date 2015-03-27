<?php
 /* enable a group
  *
  * required:
  *   0:gid              the group id to enable
  *
  * optional:
  *   N/A
  *
  * returns:
  *   OK or error
  *
  */
  
  # get the group id
  if (count($_xml_args) > 0) { $gid = $_xml_args[0]; } else { $gid = ''; }
	if (array_key_exists('gid', $_POST)) $gid = @urldecode($_POST['gid']);
	
	# validate input
	if (strlen($gid)==0) return xml_error('No group id provided');
	
	# load the group
	if (!$this->group->load($gid)) return xml_error('Error loading group');
	
	# enable the group
	if (!$this->group->enable()) return xml_error('Error enabling group');
	
	return xml_response();
?>