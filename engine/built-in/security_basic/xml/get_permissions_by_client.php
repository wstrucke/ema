<?php
 /* list permissions by client
  *
  * required:
  *   0:guid             the client guid
  *
  * conditionally required:
  *   1:module_id        when explicit mode is enabled, the security module id the account is from
  *
  * optional:
  *   N/A
  *
  * returns:
  *   OK or error
  *
  */
  
  # get the guid
  if (count($_xml_args) > 0) { $guid = $_xml_args[0]; } else { $guid = ''; }
  if (array_key_exists('guid', $_POST)) $guid = @urldecode($_POST['guid']);
  
  # check implicit/explicit mode setting
  if ($this->auth_explicit) {
  	if (count($_xml_args) > 1) { $module_id = $_xml_args[1]; } else { $module_id = ''; }
  	if (array_key_exists('module_id', $_POST)) $module_id = @urldecode($_POST['module_id']);
  	if (strlen($module_id) == 0) return xml_error('A module id is required');
  } else {
  	$module_id = false;
  }
  
	# validate input
	if (strlen($guid)==0) return xml_error('A guid is required');
	
	$list = $this->get_authorized_items($guid, $module_id);
	if ($list === false) return xml_error('Error loading permissions');
	
	$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
	$this->_tx->xml_3->_cc('client')->_sa('guid', $guid);
	foreach($list as $permission=>$access) {
		$this->_tx->xml_3->client->_cc('item', $permission);
	}
	
	echo $this->_tx->xml_3;
	return true;
?>