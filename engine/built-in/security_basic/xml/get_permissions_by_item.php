<?php

function xml_get_permissions_by_item($item = '')
	/* get permissions assigned for an item
	 *
	 */
	{
		if (!$this->enabled) return false;
		# validate input
		if (strlen($item)==0) return xml_error('No item ID provided');
		$list = $this->get_authorized_clients($item);
		if ($list === false) return xml_error('Error loading permissions');
		$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
		$this->_tx->xml_3->_cc('item')->_sa('id', $item);
		foreach($list as $permission=>$access) {
			$this->_tx->xml_3->item->_cc('client', $permission);
		}
		echo $this->_tx->xml_3; return true;
	}


?>