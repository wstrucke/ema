<?php
 /* return a list of all groups
  *
  * required:
  *   N/A
  *
  * optional:
  *   N/A
  *
  * returns:
  *   a list of groups or error
  *
  */
  
	# retrieve the list of groups
	if (($list = $this->group->catalog())===false) return xml_error('Error enumerating groups');
	
	$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
	for($i=0;$i<count($list);$i++) {
		$this->_tx->xml_3->_cc('group')->_ccc($list[$i]);
	}
	echo $this->_tx->xml_3;
	
	return true;
?>