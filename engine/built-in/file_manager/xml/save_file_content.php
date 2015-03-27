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
			
			# set the data to update
			$data = @base64_decode($_POST['content']);
			
			switch($info[0]['type']) {
				case 'database':
					# save the file
					$result = $this->_tx->db->update('files', array('unique_id'), array($info[0]['unique_id']), array('data'), array($data));
					break;
				default:
					# file name
					$filename = $root_path . $info[0]['server_path'] . $info[0]['name'];
					# make sure the file exists
					if (file_exists($filename)&&is_writable($filename)) {
						# save the file
						$fp = @fopen($filename, 'w');
						if ($fp !== false) {
							$result = @fwrite($fp, $data);
						} else {
							xml_error('Unable to open file!');
						}
						@fclose($fp);
					} else {
						xml_error('File does not exist or is not writable');
					}
					break;
			}
			if (isset($result)&&($result !== false)) {
				xml_response();
			} else {
				xml_error('Error saving file');
			}
		} else {
			xml_error('Invalid file id: ' . $args[1]);
		}
	} else {
		xml_error('A file id is required');
	}
?>