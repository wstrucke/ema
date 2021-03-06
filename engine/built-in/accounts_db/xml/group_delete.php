<?php
 /* delete an existing group
  *
  * required:
  *   0:gid              the group id to delete
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
	
	# delete the group
	if (!$this->group->delete()) return xml_error('Error deleting group');
	
	return xml_response();
?>