<?php
 /* return a list of all users
  *
  * required:
  *   N/A
  *
  * optional:
  *   N/A
  *
  * returns:
  *   a list of users or error
  *
  */
  
	# retrieve the list of users
	if (($list = $this->user->catalog())===false) return xml_error('Error enumerating users');
	
	$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
	for($i=0;$i<count($list);$i++) {
		$this->_tx->xml_3->_cc('user')->_ccc($list[$i]);
	}
	echo $this->_tx->xml_3;
	
	return true;
?>