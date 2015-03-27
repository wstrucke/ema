<?php
 /* File Manager Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.3, Oct-30-2011
  * William Strucke, wstrucke@gmail.com
  *
  * To Do:
  * - add code to detect and possibly alter the php upload_max_filesize setting
  * - make sure forms that upload data use enctype="multipart/form-data"
  * - change files_index ID to integer value, auto_increment -- MYSQL object *MUST*
  *    be updated to support this type of change
  *
  */
	
	class file_manager extends standard_extension
	{
		# shared variables
		public $max_upload_size;            // the configured maximum upload size on the server
		
		# internal variables
		protected $db;                      // database connection
		protected $download_request_code;   // published download request code from file manager
		protected $upload_root;             // upload directory
		
		# debug variables
		public $_name = 'File Manager Extension';
		public $_version = '1.0.3';
		protected $_debug_prefix = 'file';
		
		# database version
		public $schema_version='0.2.5';     // the schema version to match the registered schema
		
		public function admin($option = false)
		/* administration interface
		 *
		 */
		{
			return $this->_content('index');
		}
  
  public function _construct()
  /* initialize the object
   *
   */
  {
  	# register with the system cache
  	if ($this->_has('cache')&&(@is_object($this->_tx->cache))) $this->_tx->cache->register_table('files_index');
  	
  	# publish variables
  	$this->_tx->_publish('max_upload_size', $this->max_upload_size);
  	$this->_tx->_publish('upload_root', $this->upload_root);
  	
  	# retrieve the system upload settings
  	$post_max_size = ini_get('post_max_size');
  	$upload_max_filesize = ini_get('upload_max_filesize');
  	
  	# set the max upload size
  	if (intval($upload_max_filesize) < intval($post_max_size)) {
  		$this->max_upload_size = $upload_max_filesize;
  	} else {
  		$this->max_upload_size = $post_max_size;
  	}
  }
  
  public function cache_expire_table($t)
  /* given a table (t) that has been modified, return an array of match arguments to be expired
   *   from the cache
   *
   * this function should only be called from the cache module
   *
   * returns an array of one or more key->value pairs or false to decline the request
   *
   */
  {
  	$match = array();
  	switch($t) {
  		case 'files_index':
  			$match['uri'] = 'admin';
  			$match['uri_args'] = 'file';
  			break;
  		default: return false;
  	}
  	return $match;
  }
  
		public function copy($source_id, $args = null)
		/* copy a file
		 *
		 * requires the source file id or name
		 *
		 * an optional argument array is allowed:
		 *   'prepend': STRING : DEFAULT NULL : prepended to the file if type text
		 *   'append':  STRING : DEFAULT NULL : appended to the file if type text
		 *   'force_disk': BOOL : DEFAULT FALSE : force the file to the disk
		 *   'force_database': BOOL : DEFAULT FALUSE : force the file to the server
		 *   'name': STRING : DEFAULT Original + CopyX : the new file name
		 *   'path': STRING : DEFAULT Original : the new file path
		 *
		 * returns the new file id number or false one error
		 *
		 */
		{
			# make the sure file id exists
			if (@strlen($source_id) == 0) return false;
			if (! db_qcheck_exec(array('table'=>'files_index', 'search_keys'=>array('id'), 'search_values'=>array($source_id)))) {
				$tmp = $this->_tx->db->query('files_index', array('name'), array($source_id), array('id'));
				if (! db_qcheck($tmp)) return false;
				$source_id = $tmp[0]['id'];
			}
			if (! @is_array($args)) $args = array();
			$tmp = $this->field_list('files_index');
			$fields = array();
			$ignore_fields = array('id', 'unique_id');
			for($i=0;$i<count($tmp);$i++) { if (!in_array($tmp[$i], $ignore_fields)) { $fields[] = $tmp[$i]; }}
			# make the copy
			$this->_tx->db->query_raw("INSERT INTO `files_index` (`" . implode('`, `', $fields) .
					"`,`unique_id`) SELECT `" . implode('`, `', $fields) . "`,'NEW_COPY' FROM `files_index` WHERE `id`='" . $source_id . "'");
			$new_id = $this->_tx->db->insert_id();
			# build the change list from any provided arguments
			$update = array();
			if (array_key_exists('name', $args)) $update['name'] = $args['name'];
			if (array_key_exists('path', $args)) $update['path'] = $args['path'];
			if (count($update) > 0) $this->_tx->db->update('files_index', array('id'), array($new_id), array_keys($update), array_values($update));
			
			# get the file info
			$fields[] = 'unique_id';
			$info = $this->_tx->db->query('files_index', array('id'), array($new_id), $fields);
			
			# load the file data
			if ($info[0]['type'] == 'database') {
				# file data stored in our files table
				$old = $this->_tx->db->query('files_index', array('id'), array($source_id), array('unique_id'));
				$tmp = $this->_tx->db->query('files', array('unique_id'), array($old[0]['unique_id']), array('data'));
				if (db_qcheck($tmp)) { $data = $tmp[0]['data']; } else { $data = ''; }
			} else {
				# file data stored on the server
				# set file location for locally uploaded files
				if (! $info[0]['uploaded']) {
					# file was not uploaded which means the root path is the owner object's path
					$root_path = $this->_tx->{$info[0]['object']}->_myloc;
				} else {
					# use the configured upload path as the root
					$root_path = $this->upload_root;
				}
				if (@file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) {
					$data = @file_get_contents($root_path . $info[0]['server_path'] . $info[0]['name']);
				} else {
					$data = '';
				}
			}
			
			$dest_type = $info[0]['type'];
			
			# set the destination type
			if (array_key_exists('force_database', $args)&&$args['force_database']) {
				$dest_type = 'database';
			} elseif (array_key_exists('force_disk', $args)&&$args['force_disk']&&($dest_type=='database')) {
				$dest_type = 'server';
			}
			
			# check for prepended/appended text
			if (@substr($info[0]['mime'], 0, 4) == 'text') {
				if (array_key_exists('prepend', $args)) $data = $args['prepend'] . $data;
				if (array_key_exists('append', $args)) $data .= $args['append'];
			}
			
			# re-initialize the file index update array
			$uuid = uniqid(md5($info[0]['name']), true);
			$update = array('unique_id'=>$uuid, 'type'=>$dest_type);
			
			# write the new file
			if ($dest_type == 'database') {
				$this->_tx->db->insert('files', array('unique_id', 'data'), array($uuid, $data));
			} else {
				$index = 0;
				while (@file_exists($root_path . $info[0]['server_path'] . $info[0]['name'] . "_copy$index")) { $index++; }
				$handle = @fopen($root_path . $info[0]['server_path'] . $info[0]['name'] . "_copy$index", 'w');
				@fwrite($handle, $data);
				@fclose($handle);
				$update['name'] = $info[0]['name'] . "_copy$index";
			}
			
			# update the database
			$this->_tx->db->update('files_index', array('id'), array($new_id), array_keys($update), array_values($update));
			
			return $new_id;
		}
		
		public function create_alias($id, $alias)
		/* create a new alias for a file
		 *
		 */
		{
			if ((strlen($id)==0)||(strlen($alias)==0)) return false;
			
			# verify the file id exists
			$check = $this->_tx->db->query('files_index', array('id'), array($id));
			if (!db_qcheck($check)) return false;
			
			# insert the new record into the database
			$result = $this->_tx->db->insert('file_alias', array('file_id', 'alias'), array($id, $alias));
			if (db_qcheck($result)) return true;
			return false;
		}
		
		public function delete_alias($id)
		/* delete the alias with the specified id
		 *
		 */
		{
			if (strlen($id)==0) return false;
			
			# verify the alias exists
			$check = $this->_tx->db->query('file_alias', array('id'), array($id));
			if (!db_qcheck($check)) return false;
			
			# delete the record from the database
			$result = $this->_tx->db->delete('file_alias', array('id'), array($id));
			if (db_qcheck($result)) return true;
			return false;
		}
		
		public function download()
		/* download a file 
		 *
		 */
		{
			$this->_debug_start();
			
			@session_cache_limiter(false);
			
			# protect against the case where the drc is not supplied
			if (strlen($this->download_request_code) == 0) {
				if (strlen($this->_tx->get->download_request_code) > 0) {
					$this->download_request_code = $this->_tx->get->download_request_code;
				} else {
					$this->download_request_code = 'id';
				}
			}
			
			$this->_debug('Download Request Code: ' . $this->download_request_code);
			
			# set the search path for the file
			$path = '';
			
			# pre-load the function arguments
			$args = func_get_args();
			$from_drc = false;
			
			if ((!$this->_tx->get->link_rewrite)||(count($args)==0)||(strlen($args[0])==0)) {
				# make sure something was requested
				if (!array_key_exists($this->download_request_code, $_REQUEST)) return $this->_return(false, 'No file requested');
				# load the complete request
				$args = array($_REQUEST[$this->download_request_code]);
				$from_drc = true;
			}
			
			# load arguments
			$n = count($args);
			if (($n == 0)&&(!$this->_tx->get->link_rewrite)) return $this->_return(false, 'No file requested [2]');
			
			if (!$from_drc) {
				# the last argument ($n) is the file id, all others are part of the path
				for ($i=0;$i<($n-1);$i++) {
					if ($i>0) $path .= '/';
					$path .= $args[$i];
				}
				
				# get the id
				$id = $args[$n-1];
			} else {
				$id = $args[0];
			}
			
			# sanity check
			if ( ($id === false) || (strlen($id) == 0) ) return $this->_return(true, 'No file requested [3]');
			
			# prevent some attacks
			if ($id != stripslashes($id)) return $this->_return(false, 'File ID did not validate');
			
			# if there is no path the database value is defined as null, not 0 length string
			if (strlen($path) == 0) $path = null;
			
			# set the field list we're retrieving from the files_index
			$field_list = array('id', 'unique_id','type','name','path','server_path','size','object','shared','ssl_required','mime','uploaded');
			
			# attempt to get the file metadata
			$info = $this->_tx->db->query('files_index',array('name', 'path'),array($id, $path),$field_list,true);
			
			# enforce only one result in case of ambiguity in file name
			if (!db_qcheck($info)) {
				# alias lookup
				$check = $this->_tx->db->query('file_alias', array('alias'), array($id), array('file_id'));
				if (db_qcheck($check)){
					$info = $this->_tx->db->query('files_index',array('id'),array($check[0]['file_id']),$field_list);
				}
			}
			
			if ((!db_qcheck($info)) && ($path == null)) {
				$info = $this->_tx->db->query('files_index',array('id'),array($id),$field_list);
			}
			
			if ((! db_qcheck($info)) && ($path == null)) {
				$info = $this->_tx->db->query('files_index',array('unique_id'),array($id),$field_list);
			}
			
			if (!db_qcheck($info)) return $this->_return(false, 'File Not Found');
			
			# check permissions
			if (! $info[0]['shared']) {
				# trace the calling object to validate permissions
				return $this->_return(false, 'retrieval of restricted objects is not implemented at this time');
			}
			
			if ($this->_has('security')) {
				if ($this->_tx->security->access('download.' . $info[0]['id']) === false) return $this->_return(false, 'Access Denied');
			}
			
			# check ssl
			if ( ($info[0]['ssl_required']) && (!$this->_tx->get->ssl) ) return $this->_return(false, 'SSL is required');
			
			# set file location for locally uploaded files
			if (! $info[0]['uploaded']) {
				# file was not uploaded which means the root path is the owner object's path
				$root_path = $this->_tx->{$info[0]['object']}->_myloc;
			} else {
				# use the configured upload path as the root
				$root_path = $this->upload_root;
			}
			$this->_debug("Set server root path for this file: $root_path");
			$this->_debug('File is of type: ' . $info[0]['type']);
			
			switch($info[0]['type']) {
				case 'database':
					# get the file
					$data = $this->_tx->db->query('files', array('unique_id'), array($info[0]['unique_id']), array('data', 'modified'));
					if (!db_qcheck($data)) return $this->_return(false, 'Database File DNE');
					$file =& $data[0]['data'];
					$size = strlen($data[0]['data']);
					$file_time = strtotime($data[0]['modified']);
					$modified = gmdate('D, d M Y H:i:s', $file_time).' GMT';
					break;
				case 'server':
					# make sure the file exists
					if (! file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) return $this->_return(false, 'File DNE');
					# load the file
					$file = buffer($root_path . $info[0]['server_path'] . $info[0]['name'], false);
					$size = strlen($file);
					$file_time = filemtime($root_path . $info[0]['server_path'] . $info[0]['name']);
					$modified = gmdate('D, d M Y H:i:s', $file_time).' GMT';
					break;
				case 'sscript':
					# make sure the file exists
					if (! file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) return $this->_return(false, 'File DNE');
					# load the file
					$file = buffer($root_path . $info[0]['server_path'] . $info[0]['name']);
					# process any commands in the stream
					$this->_tx->cms->parse_ema_commands($file);
					$size = strlen($file);
					$file_time = filemtime($root_path . $info[0]['server_path'] . $info[0]['name']);
					$modified = gmdate('D, d M Y H:i:s', $file_time).' GMT';
					break;
				default:
					return $this->_return(false, 'Invalid File Type');
					break;
			}
			
			/* optional:
			 *
			 *	header('Expires: 0');			// disable caching
			 *	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');			// use for ssl
			 *
			 */
			
			if (function_exists('apache_request_headers')) {
				$headers = apache_request_headers();
			} else {
				$this->_debug('Error: apache_request_headers is unavailable.');
				$headers = array();
			}
			
			# http://stackoverflow.com/questions/1038638/caching-image-requests-through-php-if-modified-since-not-being-sent
			if (($info[0]['type'] != 'sscript') && array_key_exists('If-Modified-Since', $headers) && (strtotime($headers['If-Modified-Since']) == $file_time)) {
				# Client's cache IS current, so we just respond '304 Not Modified'.
				$this->_debug('HTTP/1.1 304 Not Modified');
				header('Last-Modified: '.$modified, true, 304);
				header('Connection: close');
			} else {
				# Image not cached or cache outdated, we respond '200 OK' and output the image.
				
				# required for IE
				if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off'); }
				
				# disable caching for server parsed scripts
				if ($info[0]['type'] == 'sscript') {
					header("Cache-Control: no-store, no-cache, must-revalidate");
			    header("Pragma: no-cache");
					header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
				} else {
					header('Cache-Control: private');
				}
				
				header('Last-Modified: '.$modified, true, 200);
				header("Content-Length: $size");
				header('Content-Type: ' . $info[0]['mime']);
				#header('Pragma: public');
				#header('Content-Transfer-Encoding: binary');
				#header("Content-Description: File Transfer");
				header("Content-Disposition: inline; filename=" . $info[0]['name']);	// inline to not force download, attachment to force
				#header('Connection: close');
				$this->_debug('Sending file starting with "' . substr($file, 0, 25) . '"');
				# DO NOT OUTPUT THE FILE IN DEBUG MODE
				if ($this->_debug_mode < 0) echo $file;
			}
			
			return $this->_return(true);
		}
		
		protected function field_list($table, $values_as_types = false)
		/* return an array of fields for the specified table
		 *
		 * if values as types is true, return in the format of "field"=>"type"
		 *
		 */
		{
			switch($table) {
				case 'files_index':
					$list = array('id'=>'string', 'unique_id'=>'string',
					    'type'=>'string', 'name'=>'string', 'description'=>'string',
					    'path'=>'string', 'size'=>'string', 'object'=>'string',
					    'shared'=>'boolean', 'ssl_required'=>'boolean',
					    'updated'=>'datetime', 'mime'=>'string', 'server_path'=>'string');
					break;
				case 'files':
					$list = array('unique_id'=>'string', 'data'=>'string', 'modified'=>'timestamp');
					break;
				default: return array(); break;
			}
			
			if (! $values_as_types) $list = array_keys($list);
			
			return $list;
		}
		
		public function list_module_files($path = false)
		/* return a list of available files for the requesting module
		 *
		 */
		{
			# enable php backtrace to get the name of the caller
			$b = debug_backtrace();
			$module = $b[1]['object'];
			
			# get the field list
			$f = $this->field_list('files_index');
			
			if ($path === false) {
				$data = $this->_tx->db->query('files_index', array('object'), array($module), $f, true, array('name'));
			} else {
				if (strlen($path) == 0) $path = null;
				$data = $this->_tx->db->query('files_index', array('object', 'path'), array($module, $path), $f, true, array('name'));
			}
			
			if (! is_array($data)) $data = array();
			
			return $data;
		}
		
		public function list_files($path = false)
		/* return a list of available files
		 *
		 */
		{
			# get the field list
			$f = $this->field_list('files_index');
			
			if ($path === false) {
				$data = $this->_tx->db->query('files_index', '', '', $f, true, array('name'));
			} else {
				if (strlen($path) == 0) $path = null;
				$data = $this->_tx->db->query('files_index', array('path'), array($path), $f, true, array('name'));
			}
			
			if (! is_array($data)) $data = array();
			
			return $data;
		}
		
		public function list_folders($path = false)
		/* return a list of folders from the provided path, if no path is specified list all folders
		 *
		 * returns an array
		 *
		 */
		{
			if ($path === false) {
				$data = $this->_tx->db->query('files_index', '', '', array('distinct path'), true, array('path'));
			} elseif (strlen($path) == 0) {
				$data = $this->_tx->db->query('files_index', array('path'), array('%'), array('distinct path'), true, array('path'));
			} else {
				if (substr($path, strlen($path) - 1) == '/') { $path = substr($path, 0, strlen($path) -1); }
				$data = $this->_tx->db->query('files_index', array('path'), array($path . '/%'), array('distinct path'), true, array('path'));
			}
			
			if (! is_array($data)) $data = array();
			$unique = array();
			
			# only return one level of folders
			for ($i=0;$i<count($data);$i++) {
				$unique[$i] = substr($data[$i]['path'], strlen($path));
				$pos = strpos($unique[$i], '/');
				if ($pos === 0) {
					$unique[$i] = substr($unique[$i], 1);
					$pos = strpos($unique[$i], '/');
				}
				if ($pos !== false) $unique[$i] = substr($unique[$i], 0, $pos);
			}
			
			# ensure unique results
			$unique = array_flip($unique);
			$data = array_keys($unique);
			
			return $data;
		}
		
		protected function newid()
		/* generate a unique file id
		 *
		 */
		{
			$this->_tx->db->query();
		}
		
		public function next_id()
		/* get the next available id in the index table
		 *
		 * this function currently uses a hard coded MySQL statement
		 *  which will need to be changed to use a function in the
		 *  MySQLi object
		 *
		 */
		{
			$r = $this->_tx->db->queryFull('SELECT MAX(CAST(`id` AS UNSIGNED)) AS id FROM `files_index`');
			if (is_array($r)) return strval(intval($r[0]['id']) + 1);
			return '1';
		}
		
		public function new_file()
		/* create a new file
		 *
		 */
		{
			
		}
		
		public function upload()
		/* upload a file
		 *
		 */
		{
			return buffer(dirname(__FILE__) . '/content/upload.php');
		}
		
		public function xml_create_alias($id = false, $alias = false)
		/* create a file alias for the provided file
		 *
		 */
		{
			if (!$this->create_alias($id, $alias)) return xml_error('Unable to create the alias');
			return xml_response();
		}
		
		public function xml_delete_alias($id = false)
		/* delete a file alias
		 *
		 */
		{
			if (!$this->delete_alias($id)) return xml_error('Unable to delete the alias');
			return xml_response();
		}
		
		public function xml_delete_file($id = false)
		/* delete a file via xml request
		 *
		 */
		{
			# sanity check
			if ( ($id === false) || (strlen($id) == 0) ) return xml_error('A file ID is required');
			
			# prevent some attacks
			if ($id != stripslashes($id)) return xml_error('A file ID is required');
			
			# attempt to get the file metadata
			$info = $this->_tx->db->query(
				'files_index',
				array('id'),
				array($id),
				array('unique_id','type','name','path','size','object','shared','ssl_required','mime'));
			
			if (! db_qcheck($info)) {
				$info = $this->_tx->db->query(
					'files_index',
					array('unique_id'),
					array($id),
					array('unique_id','type','name','path','size','object','shared','ssl_required','mime'));
			}
			
			if (($info === false)||(count($info)==0)) return xml_error('Invalid file ID');
			
			switch($info[0]['type']) {
				case 'database':
					$r = $this->_tx->db->delete('files', array('unique_id'), array($info[0]['unique_id']));
					break;
				case 'server':
					# make sure the file exists
					if (! file_exists($this->upload_root . $info[0]['path'] . $info[0]['name'])) {
						return xml_error('File does not exist');
					}
					$r = unlink($this->upload_root . $info[0]['path'] . $info[0]['name']);
					break;
				case 'sscript':
					# make sure the file exists
					if (! file_exists($this->upload_root . $info[0]['path'] . $info[0]['name'])) {
						return xml_error('File does not exist');
					}
					$r = unlink($this->upload_root . $info[0]['path'] . $info[0]['name']);
					break;
				default:
					return xml_error('There is a problem with the database for the requested file');
					break;
			}
			
			if ($r !== false) {
				# delete the index
				$r = $this->_tx->db->delete('files_index', array('unique_id'), array($info[0]['unique_id']));
				if ($r === false) {
					return xml_error('WARNING: Deleted file contents successfully but there was an error deleting the index entry!');
				}
			}
			
			if ($r === false) return xml_error('Unable to delete the file');
			
			echo "<response>$id</response>"; return true;
		}
		
		public function xml_download($id = false)
		/* download a text file contents encoded in xml
		 *
		 */
		{
			# sanity check
			if ( ($id === false) || (strlen($id) == 0) ) return xml_error('A file ID is required');
			
			# prevent some attacks
			if ($id != stripslashes($id)) return xml_error('A file ID is required');
			
			# attempt to get the file metadata
			$info = $this->_tx->db->query(
				'files_index',
				array('id'),
				array($id),
				array('unique_id','type','name','path','server_path','size','object','shared','ssl_required','mime'));
			
			if (! db_qcheck($info)) {
				$info = $this->_tx->db->query(
					'files_index',
					array('unique_id'),
					array($id),
					array('unique_id','type','name','path','server_path','size','object','shared','ssl_required','mime'));
			}
			
			if (! db_qcheck($info)) return xml_error('Invalid file ID');
			
			# check permissions
			if (! $info[0]['shared']) {
				# trace the calling object to validate permissions
				$this->_debug('retrieval of restricted objects is not implemented at this time');
				return xml_error('Module Restricted File');
			}
			
			# check ssl
			if ( ($info[0]['ssl_required']) && (! $this->_tx->get->ssl) ) {
				return xml_error('Transmission of this file requires an encrypted channel');
			}
			
			# set file location for locally uploaded files
			if (! $info[0]['uploaded']) {
				# file was not uploaded which means the root path is the owner object's path
				$root_path = $this->_tx->{$info[0]['object']}->_myloc;
			} else {
				# use the configured upload path as the root
				$root_path = $this->upload_root;
			}
			$this->_debug("Set server root path for this file: $root_path");
			
			# verify this is text
			if (strpos($info[0]['mime'], 'text') === false) {
				return xml_error('XML file transmission is only available for text files');
			}
			
			switch($info[0]['type']) {
				case 'database':
					# get the file
					$data = $this->_tx->db->query('files', array('unique_id'), array($info[0]['unique_id']), array('data', 'modified'));
					$file = $data[0]['data'];
					$size = $info[0]['size'];
					$file_time = strtotime($data[0]['modified']);
					$modified = gmdate('D, d M Y H:i:s', $file_time) .' GMT';
					break;
				case 'server':
					# make sure the file exists
					if (! file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) return xml_error('File Not Found');
					# load the file
					ob_start();
					readfile($root_path . $info[0]['server_path'] . $info[0]['name']);
					$file = ob_get_contents();
					ob_end_clean();
					$size = strlen($file);
					$file_time = filemtime($root_path . $info[0]['server_path'] . $info[0]['name']);
					$modified = gmdate('D, d M Y H:i:s', $file_time) .' GMT';
					break;
				case 'sscript':
					# make sure the file exists
					if (! file_exists($root_path . $info[0]['server_path'] . $info[0]['name'])) return xml_error('File Not Found');
					# load the file
					ob_start();
					readfile($root_path . $info[0]['server_path'] . $info[0]['name']);
					$file = ob_get_contents();
					ob_end_clean();
					$size = strlen($file);
					$file_time = filemtime($root_path . $info[0]['server_path'] . $info[0]['name']);
					$modified = gmdate('D, d M Y H:i:s', $file_time) .' GMT';
					break;
				default:
					return xml_error('There is a problem with the database for the requested file');
					break;
			}
			
			$this->_tx->_preRegister('new_xml', array('_tag'=>'file'));
			$x = $this->_tx->new_xml;
			
			$x->id = $id;
			$x->name = htmlentities($info[0]['name']);
			$x->type = $info[0]['type'];
			$x->size = $size;
			$x->modified = htmlentities($modified);
			$x->content = htmlentities($file);
			
			echo $x;
			return true;
		}
		
		public function xml_file_move($id = false, $path = false)
		/* move a file to a different virtual folder
		 *
		 * the path should have '/' replaced with '|'
		 * 
		 * leading and trailing '/' will be ignored
		 *
		 */
		{
			# make sure both an id and path were provided
			if (($id === false) || ($path === false)) return xml_error('Both an ID and path are required');
			
			# convert the bar character to forward slash
			$path = str_replace('|', '/', $path);
			# make sure neither the first nor last characters are slashes
			while (substr($path, 0, 1) == '/') { $path = substr($path, 1); }
			while (substr($path, strlen($path)-1) == '/') { $path = substr($path, 0, strlen($path)-1); }
			# make sure the file exists
			$r = $this->_tx->db->query('files_index', array('id'), array($id), array('name', 'path'));
			if (! db_qcheck($r)) return xml_error('Invalid File ID');
			
			# do nothing if the paths are the same
			if ($path == $r[0]['path']) {
				echo "<response>$id</response>"; return true;
			}
			# special case for root
			if (strlen($path) == 0) $path = 'null';
			# make sure a file with the same name does not exist at the destination path
			$q = $this->_tx->db->query('files_index', array('name', 'path'), array($r['name'], $path));
			if (db_qcheck($q)) return xml_error('A file with the same name exists at the destination path');
			
			# update the path
			if (! $this->_tx->db->update('files_index', array('id'), array($id), array('path'), array($path))) return xml_error('Error Saving Changes');
			
			# success
			echo "<response>$id</response>"; return true;
		}
		
		public function xml_list_files($path = false)
		/* outputs all registered files as xml
		 *
		 */
		{
			# get the field list
			$f = $this->field_list('files_index');
			
			$data = $this->list_files($path);
			
		/* <list>
		      <file>
		      	<id></id>
		      	<name></name>
		      	<object></object>
		      	( etc... )
		      </file>
		   </list> */
		  
		  $this->_tx->_preRegister('new_xml', array('_tag'=>'list'));
		  $x = $this->_tx->new_xml;
		  
		  foreach($data as $record) {
		  	$this->_tx->_preRegister('new_xml_2', array('_tag'=>'file'));
		  	$y =& $this->_tx->new_xml_2;
		  	foreach($f as $col) {
		  		if (bit2bool($record[$col]) === 0) $record[$col] = '0';
		  		if (bit2bool($record[$col]) === 1) $record[$col] = '1';
		  		$y->$col = $record[$col];
		  	}
		  	$x->_addChild($y);
		  }
		  
		  echo $x;
		  
		  return true;
		}
		
		public function xml_list_folders($path = false)
		/* outputs all available folders as xml
		 *
		 */
		{
			$data = $this->list_folders($path);
			
		/* <list>
		      <folder></folder>
		   </list> */
		  
		  $this->_tx->_preRegister('new_xml', array('_tag'=>'list'));
		  $x = $this->_tx->new_xml;
		  
		  foreach($data as $record) {
		  	$x->_cc('file')->_setValue($record);
		  }
		  
		  echo $x;
		  
		  return true;
		}
		
		public function xml_overwrite($id = false)
		/* overwrite the contents of the file at id
		 *
		 * using post value 'content'
		 *
		 * will also update modified date and file size (if database)
		 *
		 */
		{
			# sanity check
			if ( ($id === false) || (strlen($id) == 0) ) return xml_error('A file ID is required');
			
			# prevent some attacks
			if ($id != stripslashes($id)) return xml_error('A file ID is required');
			
			# verify post data exists
			if (! array_key_exists('content', $_POST)) return xml_error('The data field was not set');
			
			# attempt to get the file metadata
			$info = $this->_tx->db->query(
				'files_index',
				array('id'),
				array($id),
				array('unique_id','type','name','path','size','object','shared','ssl_required','mime'));
			
			if (! db_qcheck($info)) {
				$info = $this->_tx->db->query(
					'files_index',
					array('unique_id'),
					array($id),
					array('unique_id','type','name','path','size','object','shared','ssl_required','mime'));
			}
			
			if (! db_qcheck($info)) return xml_error('Invalid file ID');
			
			# check permissions
			if (! $info[0]['shared']) {
				# trace the calling object to validate permissions
				$this->_debug('Updating of restricted objects is not implemented at this time');
				return xml_error('Restricted File');
			}
			
			# check ssl
			if ( ($info[0]['ssl_required']) && (! $this->_tx->get->ssl) ) {
				return xml_error('Transmission of this file requires an encrypted channel');
			}
			
			# verify this is text
			if (strpos($info[0]['mime'], 'text') === false) {
				return xml_error('XML file updates are only available for text files');
			}
			
			# validate content
			if (get_magic_quotes_gpc()) {
				$content = stripslashes($_POST['content']);
			} else {
				$content =& $_POST['content'];
			}
			
			switch($info[0]['type']) {
				case 'database':
					# update
					$result = $this->_tx->db->update(
						'files',
						array('unique_id'),
						array($info[0]['unique_id']),
						array('data', 'modified'),
						array($content, 'NOW()'));
					$new_size = strlen($content);
					break;
				case 'server':
					# make sure the file exists
					if (! file_exists($this->upload_root . $info[0]['path'] . $info[0]['name'])) {
						return xml_error('The file does not exist');
					}
					$file = fopen($this->upload_root . $info[0]['path'] . $info[0]['name'], 'w');
					$result = fwrite($file, $content);
					fclose($file);
					$new_size = filesize($this->upload_root . $info[0]['path'] . $info[0]['name']);
					break;
				case 'sscript':
					# make sure the file exists
					if (! file_exists($this->upload_root . $info[0]['path'] . $info[0]['name'])) {
						return xml_error('The file does not exist');
					}
					$file = fopen($this->upload_root . $info[0]['path'] . $info[0]['name'], 'w');
					$result = fwrite($file, $content);
					fclose($file);
					$new_size = filesize($this->upload_root . $info[0]['path'] . $info[0]['name']);
					break;
				default:
					return xml_error('There is a problem with the database for the requested file');
					break;
			}
			
			if (! $result) return xml_error('Unable to save changes');
			
			# update the size
			$this->_tx->db->update('files_index', array('unique_id'), array($info['unique_id']), array('size'), array($new_size));
			
			echo '<result>ok</result>';
			return true;
		}
		
	} // class file_manager

?>