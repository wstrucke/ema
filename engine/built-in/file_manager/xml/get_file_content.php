<?php
	# what file was requested?
	$args = $t->get->fuse_args;
	if (array_key_exists(1, $args)) {
		# set the field list we're retrieving from the files_index
		$field_list = array('id', 'unique_id','type','name','path','server_path','size','object','shared','ssl_required','mime','uploaded');
		
		# attempt to get the file metadata
		$info = $t->db->select('files_index', array('unique_id'), array($args[1]), $field_list, true);
		
		# also try the id
		if (!db_qcheck($info)) $info = $t->db->select('files_index', array('id'), array($args[1]), $field_list, true);
		
		# enforce only one result
		if (db_qcheck($info)) {
			# check ssl
			if ( ($info[0]['ssl_required']) && (!$t->get->ssl) ) { xml_error('SSL is required'); die('SSL handling not configured'); }
			
			# set file location for locally uploaded files
			if (! $info[0]['uploaded']) {
				# file was not uploaded which means the root path is the owner object's path
				$root_path = $t->{$info[0]['object']}->_myloc;
			} else {
				# use the configured upload path as the root
				$root_path = $t->get->upload_root;
			}
			
			switch($info[0]['type']) {
				case 'database':
					# get the file
					$data = $this->_tx->db->select('files', array('unique_id'), array($info[0]['unique_id']), array('data'));
					if ($data !== false) $data = $data[0]['data'];
					break;
				default:
					# make sure the file exists
					if (file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) {
						# load the file
						$data = buffer($root_path . $info[0]['server_path'] . $info[0]['name'], false);
					}
					break;
			}
			if (isset($data)) {
				echo "<file>\r\n\t<name>" . $info[0]['name'] . "</name>\r\n\t<unique_id>" . $info[0]['unique_id'] . "</unique_id>\r\n\t<location_type>" . $info[0]['type'] . "</location_type>\r\n\t<type>" . $info[0]['mime'] . "</type>\r\n\t<size>" . $info[0]['size'] . "</size>\r\n\t<data encoding=\"base64\">" . base64_encode($data) . "</data>\r\n</file>";
				// \t<data encoding=\"html\">" . htmlentities($data) . "</data>\r\n
			} else {
				xml_error('Error loading file');
			}
		} else {
			xml_error('Invalid file id');
		}
	} else {
		xml_error('A file id is required');
	}
?>