<?php

function xml_get_permissions()
	/* return all registered items
	 *
	 */
	{
		if (!$this->enabled) return false;
		# validate input
		if (! $list = $this->get_items()) return xml_error('Error loading permissions');
		$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
		for($i=0;$i<count($list);$i++) {
			$this->_tx->xml_3->_cc('item', $list[$i]);
		}
		echo $this->_tx->xml_3; return true;
	}

?>