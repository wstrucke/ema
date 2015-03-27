<?php
 /* create a new group
  *
  * required:
  *   0:gid              the group id to create
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
	
	# clear any loaded group
	$this->group->clear();
	
	# load any posted values
	$fields = $this->group->get_fields();
	for ($i=0;$i<count($fields);$i++) {
		if (@array_key_exists($fields[$i], $_POST)&&(@strlen($_POST[$fields[$i]])>0)) {
			$this->group->$fields[$i] = $_POST[$fields[$i]];
		}
	}
	
	# set the group id
	$this->group->gid = $gid;
	
	# attempt to create the group
	if (! $this->group->add()) return xml_error('Error creating group');
	
	return xml_response();
?>