<?php
 /* Template Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Feb-08-2009/Jul-04-2009/Apr-25-2010
  * William Strucke, wstrucke@gmail.com
  *
  * The template manager's purpose is to store, retrieve, populate, and export
  * page templates.  It should intelligently provide a proper and validated
  * structure to web content based upon a combination of predefined structures,
  * fields, and a loaded CSS file.
  *
  * to do:
  *  - recode get_html() function to support multiple root elements
  *
  */

	class template_manager extends standard_extension
	{
		public $css;                          // combined css document from all loaded documents
		protected $html;                      // html reference file (in xml_document object)
		public $id;                           // id of the loaded template
		protected $loaded = false;            // true when a template has been loaded
		protected $options;                   // array of registered module options for the loaded template
		public $parent_id;                    // optional parent template id
		
		public $css_array;                    // array of css files for the loaded template
		public $meta_tags;                    // array of meta tags for the page head
		public $javascript;                   // array of javascript for the loaded template
		
		# database version
		public $schema_version='0.5.4';       // the schema version to match the registered schema
		
		public $_name = 'Template Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'templ';
		
		/* code */
		
		protected function _construct()
		/* initialize template_manager class
		 *
		 */
		{
			$this->css =& $this->_tx->my_css_document;
			
			$this->css_array = array();
			$this->meta_tags = array();
			$this->javascript = array();
			$this->id = '';
			
			$this->_tx->_publish('css_document', $this->css);
			$this->_tx->_publish('css_array', $this->css_array);
			$this->_tx->_publish('meta_tags', $this->meta_tags);
			$this->_tx->_publish('template_id', $this->id);
			$this->_tx->_publish('javascript', $this->javascript);
			
			# register with the system cache
			if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
				$this->_tx->cache->register_table('templates');
				$this->_tx->cache->register_table('template_elements');
				$this->_tx->cache->register_table('map_template_elements');
				$this->_tx->cache->register_table('template_resource');
				$this->_tx->cache->register_table('map_template_resource');
			}
		}
		
		public function admin($option = false)
		/* module administration interface
		 *
		 */
		{
			return $this->_content('index');
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
  		case 'templates': $match['uri'] = '%'; break;
  		case 'template_elements': $match['uri'] = '%'; break;
  		case 'map_template_elements': $match['uri'] = '%'; break;
  		case 'template_resource': $match['uri'] = '%'; break;
  		case 'map_template_resource': $match['uri'] = '%'; break;
  		default: return false;
  	}
  	return $match;
  }
  
		public function create()
		/* create a new template
		 *
		 */
		{
			
		}
		
		public function get_css()
		/* return an array of css document URLs for the loaded template
		 *
		 */
		{
			# sanity check
			if (! $this->loaded) return false;
			
			return $this->css_array;
		}
		
		public function copy($to = false, $source_id = false, $copy_children = false, $new_parent = false, $copy_files = false)
		/* copy the loaded or specified template to a new template using the provided name
		 *
		 * to should be the new, unique, name
		 * source_id is required if a template is not loaded
		 *
		 * if copy_children is specified, also copies all child templates recursively
		 *
		 * if new_parent is specified, changes the parent_id for the new child element
		 *
		 * if copy_files is true, also copies the CSS and Javascript file data into
		 *  new files eventually we should implement source control so we can diff
		 *  the files, or just store changes to the files for the template and not
		 *  completely seperate files.
		 *
		 *  also add a comment to the head of the file with the copy information:
		 *    COPY of #file_id#
		 *    ema template_manager
		 *
		 * returns true on success or false on failure
		 *
		 */
		{
			# validate a source template is loaded
			if (($source_id === false)&&(! $this->loaded)) return false;
			if ($source_id === false) $source_id = $this->id;
			
			# sanitization
			$source_id = $this->_tx->db->escape($source_id);
			if ($to !== false) $to = $this->_tx->db->escape($to);
			if ($new_parent !== false) $new_parent = $this->_tx->db->escape($new_parent);
			if ($copy_files !== true) $copy_files = false;
			
			# get the source name
			$r = $this->_tx->db->query('templates', array('template_id'), array($source_id), array('name'));
			if (! db_qcheck($r)) return false;
			$from = $r[0]['name'];
			
			# optionally generate a name
			$index = 1;
			while ($to === false) {
				if ($index == 1000) return false;
				$r = $this->_tx->db->query('templates', array('name'), array($from . '_copy' . $index));
				if (db_qcheck($r)) { $index++; continue; }
				$to = $from . '_copy' . $index;
			}
			
			# validate the new name is unique
			if (db_qcheck_exec($this->_tx->db->query('templates', array('name'), array($to)))) return false;
			
			# load the template field list for the primary template table
			$list = $this->field_list('templates');
			
			# generate a new id
			$new_id = $this->next_id();
			
			# remove the fields that are unique for a new template
			$fields = array();
			$exclude = array('template_id', 'name', 'description');
			if ($new_parent !== false) $exclude[] = 'parent_id';
			for ($i=0;$i<count($list);$i++) { if (! in_array($list[$i], $exclude)) { $fields[] = $list[$i]; } }
			
			# build the update string
			$str = "INSERT INTO templates (`template_id`, `name`, `description`, `" . implode('`, `', $fields) .
				"`) SELECT '$new_id' AS 'template_id', '$to' AS 'name', CONCAT(`description`, ' COPY') AS 'description', `" .
				implode('`, `', $fields) . "` FROM `templates` WHERE `template_id`='" . $source_id . "'";
			
			# make the copy
			if ($this->_tx->db->query_raw($str) === false) return false;
			
			if ($new_parent !== false) {
				$this->_tx->db->query_raw("UPDATE templates SET `parent_id`='" . $new_parent . "' WHERE `template_id`='$new_id'");
			}
			
			# duplicate the other tables
			$tables = array('map_template_elements', 'map_template_resource', 'template_option');
			
			for ($i=0;$i<count($tables);$i++){
				$list = $this->field_list($tables[$i]);
				$fields = array();
				for ($j=0;$j<count($list);$j++) { if ($list[$j] != 'template_id') { $fields[] = $list[$j]; } }
				$str = "INSERT INTO `" . $tables[$i] . "` (`template_id`, `" . implode('`, `', $fields) .
					"`) SELECT '$new_id' AS 'template_id', `" . implode('`, `', $fields) . "` FROM `" . $tables[$i] .
					"` WHERE `template_id`='" . $source_id . "'";
				$this->_tx->db->query_raw($str);
			}
			
			# duplicate the actual elements
			$rows = $this->_tx->db->query('map_template_elements', array('template_id'), array($new_id), array('element_id'), true);
			if (db_qcheck($rows, true)) {
				$list = $this->field_list('template_elements');
				$fields = array();
				for ($j=0;$j<count($list);$j++) { if ($list[$j] != 'element_id') { $fields[] = $list[$j]; } }
				foreach($rows as $r) {
					$str = "INSERT INTO `template_elements` (`" . implode('`, `', $fields) .
					"`) SELECT `" . implode('`, `', $fields) . "` FROM `template_elements` WHERE `element_id`='" . $r['element_id'] . "'";
					$this->_tx->db->query_raw($str);
					$row_id = $this->_tx->db->insert_id();
					$this->_tx->db->update(
						'map_template_elements',
						array('template_id', 'element_id'),
						array($new_id, $r['element_id']),
						array('element_id'),
						array($row_id));
				}
			}
			
			# duplicate the actual resources
			$rows = $this->_tx->db->query('map_template_resource', array('template_id'), array($new_id), array('template_resource_id'), true);
			if (db_qcheck($rows, true)) {
				$list = $this->field_list('template_resource');
				$fields = array();
				for ($j=0;$j<count($list);$j++) { if ($list[$j] != 'id') { $fields[] = $list[$j]; } }
				foreach($rows as $r) {
					$str = "INSERT INTO `template_resource` (`" . implode('`, `', $fields) .
					"`) SELECT `" . implode('`, `', $fields) . "` FROM `template_resource` WHERE `id`='" . $r['template_resource_id'] . "'";
					$this->_tx->db->query_raw($str);
					$row_id = $this->_tx->db->insert_id();
					$this->_tx->db->update(
						'map_template_resource',
						array('template_id', 'template_resource_id'),
						array($new_id, $r['template_resource_id']),
						array('template_resource_id'),
						array($row_id));
				}
				# optionally duplicate file data as well
				if ($copy_files) {
					$this->_debug('Copying file data');
					$rows = $this->_tx->db->query('map_template_resource', array('template_id'), array($new_id), array('template_resource_id'), true);
					$tmp = array();
					for ($i=0;$i<count($rows);$i++) { $tmp[] = $rows[$i]['template_resource_id']; }
					$list = implode(',', $tmp);
					$rows = $this->_tx->db->query_raw("SELECT `id`, `file_id` FROM `template_resource` WHERE `id` IN ($list) AND `file_id` IS NOT NULL AND LENGTH(`file_id`) > 0");
					if (db_qcheck($rows, true)) {
						# copy the files and update the file ids in the resource table
						foreach($rows as $r) {
							# set the header text
							$header = "/* COPY of " . $r['file_id'] . " */\r\n/* Duplicated " . date('Y-m-d H:i:s') . " by " . $this->_tx->get->userid .
								" */\r\n/* " . $this->_version . " */\r\n\r\n";
							$file_id = $this->_tx->file->copy($r['file_id'], array('force_database'=>true, 'prepend'=>$header));
							if ($file_id !== false) {
								$this->_tx->db->update(
									'template_resource',
									array('id'),
									array($r['id']),
									array('file_id'),
									array($file_id));
							}
						}
					}
				} else {
					$this->_debug('<strong>NOT</strong> copying file data!');
				}
			}
			
			if ($copy_children) {
				$list = $this->_tx->db->query('templates', array('parent_id'), array($source_id), array('template_id'));
				if (! db_qcheck($list, true)) return $new_id;
				for ($i=0;$i<count($list);$i++) { $this->copy(false, $list[$i]['template_id'], true, $new_id, $copy_files); }
			}
			
			return $new_id;
		}
		
		protected function field_list($table, $values_as_types = false)
		/* return an array of fields for the specified table
		 *
		 * if values as types is true, return in the format of "field"=>"type"
		 *
		 */
		{
			switch($table) {
				case 'map_template_elements':
					$list = array('element_id'=>'integer', 'template_id'=>'string',
						'order'=>'integer');
					break;
				case 'map_template_resource':
					$list = array('template_resource_id'=>'integer', 'template_id'=>'string',
						'order'=>'integer');
					break;
				case 'template_elements':
					$list = array('element_id'=>'integer', 'scope'=>'integer',
					    'order'=>'integer', 'name'=>'string', 'css_id'=>'integer',
					    'size'=>'integer', 'width'=>'integer', 'height'=>'integer',
					    'ingredients'=>'string');
					break;
				case 'template_option':
					$list = array('template_id'=>'string', 'option'=>'string',
							'value'=>'string');
					break;
				case 'template_resource':
					$list = array('id'=>'integer', 'name'=>'string', 'type'=>'string',
					    'description'=>'string', 'url'=>'string', 'file_id'=>'string',
					    'contents'=>'string');
					break;
				case 'templates':
					$list = array('template_id'=>'string', 'parent_id'=>'string',
					    'name'=>'string', 'description'=>'string', 'enabled'=>'boolean');
					break;
				default: return array(); break;
			}
			
			if (! $values_as_types) $list = array_keys($list);
			
			return $list;
		}
		
		protected function field_type($table, $field)
		/* return the field type for the specified table and field
		 *
		 */
		{
			$list = $this->field_list($table, true);
			return $list[$field];
		}
		
		protected function get_element_html($id)
		/* return the element html
		 *
		 */
		{
			# retrieve the element html
			$result = $this->_tx->db->query('template_elements', array('element_id'), array($id), array('ingredients'));
			if (! db_qcheck($result)) return false;
			$html = $result[0]['ingredients'];
			
			# process any php in the code -- this should be highly protected but we need to get the site online ASAP
			ob_start();
			# this is generating a syntax error on at least one server so suppress error notifications for now
			#   we may have to revisit this in the future
			@eval(" ?> $html <?php ");
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
		
		public function get_html($id = false)
		/* return the complete html template
		 *
		 */
		{
			$this->_debug_start();
			
			# sanity check
			if ($id == false) {
				if (! $this->loaded) return $this->_return(false, 'a template must be loaded');
				$id = $this->id;
			}
			
			# list the elements for this and all parent elements
			$list = $this->list_elements();
			
			# sort the returned list by the order
			usort($list, array($this, 'list_elements_sort'));
			
			# build the html
			$html = '';
			for ($i=0;$i<count($list);$i++) {
				$html .= $this->get_element_html($list[$i]['element_id']);
			}
			
			return $this->_return($html);
		}
		
		public function get_options($include_values = true, $return_all_options = false)
		/* retrieve all options for the loaded template
		 *
		 * if include_values is false, only return an array of options
		 *   otherwise, returns an array of options and values
		 *
		 * if return_all_options is set, return all possible options
		 *   include those not set for this template (will null values)
		 *
		 */
		{
			if ($include_values) return $this->options;
			return array_keys($this->options);
		}
		
		public function list_css_elements(&$list, $css_id = '')
		/* return an array of available css element types and names
		 *
		 * possibly include type, id, name (if any)
		 *
		 * possibly return in xml??
		 *
		 * return elements for the specified style sheet. if none
		 *	is specified, use the default/selected style sheet
		 *
		 */
		{
			// tbd...
		}
		
		public function list_elements($template_id = false, $include_inherited_elements = true)
		/* return an array of element ids and order for the requested template
		 *
		 * if no template id is provided assume the loaded template
		 *
		 * returns an array of 0 or more element_ids and order
		 *
		 * format is $arr {
		 *   0 => array('element_id'=>#, 'order'=>#)
		 *   }
		 */
		{
			if ($template_id === false) {
				if (! $this->loaded) return false;
				$template_id = $this->id;
				$parent_id = $this->parent_id;
			} elseif ($include_inherited_elements) {
				# attempt to load the parent template
				$tmp = $this->_tx->db->query('templates', array('template_id'), array($template_id), array('parent_id'));
				if (db_qcheck($tmp)) { $parent_id = $tmp[0]['parent_id']; } else { $parent_id = null; }
			}
			$result = $this->_tx->db->query('map_template_elements', array('template_id'), array($template_id), array('element_id', 'order'), true);
			if (! db_qcheck($result, true)) return array();
			if ($include_inherited_elements) {
				$inherited = $this->list_elements($parent_id);
				if (db_qcheck($inherited, true)) $result = array_merge($result, $inherited);
			}
			return $result;
		}
		
		public function list_elements_sort($a, $b)
		/* sort function for results provided by list_elements
		 *
		 */
		{
			if (intval($a['order']) == intval($b['order'])) return 0;
			return (intval($a['order']) > intval($b['order'])) ? +1 : -1;
		}
		
		public function list_style_sheets(&$list)
		/* return an array of available style sheets and ids
		 *
		 */
		{
			$this->_debug_start();
			
			# sanity check
			if (! $this->loaded) return $this->_return(false, 'a template must be loaded');
			
			# initialize return variable
			$returnArr = array();
			
			$r = $this->_tx->db->query(
				'template_resource',
				array('type'),
				array('css'),
				array('id', 'name', 'description'),
				true,
				array('name'));
			
			if (! db_qcheck($r, true)) return $this->_return(false, 'error retrieving style sheet list');
			
			for ($i=0; $i<count($r); $i++)
			{
				$returnArr[$r[$i]['id']]['name'] = $r[$i]['name'];
				$returnArr[$r[$i]['id']]['description'] = $r[$i]['description'];
			}
			
			if (count($returnArr) == 0) return $this->_return(false, 'error processing results');
			
			return $this->_return($returnArr, 'successfully retrieved ' . count($returnArr) . ' css records');
		}
		
		public function load($id = '')
		/* load the specified template
		 *
		 * if no id is provided... ????
		 *
		 * an id of ampersand (&) indicates the setup/admin console template
		 *
		 */
		{
			$this->_debug_start();
			
			# make sure a valid id was provided
			if ($id == '') return $this->_return(false, 'Error: no id was supplied');
			
			/* for now we are going to terminate if the id is empty */
			
			# attempt to load the specified template
			$tmp = $this->_tx->db->query('templates', array('template_id'), array($id), array('name', 'description', 'parent_id'));
			if (!db_qcheck($tmp)) $this->_return(false, 'error loading specified template');
			
			# initialize template storage
			$this->css_array = array();
			$this->javascript = array();
			$this->meta_tags = array();
			$this->options = array();
			
			# load the template resources
			$r = $this->load_resources($id, $tmp[0]['parent_id']);
			
			# load the site settings
			$link_rewrite = $this->_tx->get->link_rewrite;
			$ps = $this->_tx->get->ps;
			$drc = $this->_tx->get->download_request_code;
			
			# combine css documents into one loaded object for html elements to reference
			foreach ($r['css'] as $arr) {
				if (strlen($arr['file_id']) > 0) {
					if ($link_rewrite) {
						$file = url('download' . $ps . $arr['file_id']);
					} else {
						$file = url('download&' . $drc . '=' . $arr['file_id']);
					}
				} else {
					$file = $arr['url'];
				}
				$this->css_array[] = $file;
				$this->css->load_combine($file);
			}
			
			# restore each tag from encoded html
			for ($i=0;$i<count($r['meta']);$i++) {
				$this->meta_tags[$i] = '<meta name="' . $r['meta'][$i]['name'] . '" content="' . $r['meta'][$i]['contents'] . '" />';
			}
			
			# remove the bottom level array returned by the db array query
			for ($i=0;$i<count($r['javascript']);$i++) {
				if (strlen($r['javascript'][$i]['file_id']) > 0) {
					if ($link_rewrite) {
						$file = url('download' . $ps . $r['javascript'][$i]['file_id']);
					} else {
						$file = url('download&' . $drc . '=' . $r['javascript'][$i]['file_id']);
					}
				} else {
					$file = $r['javascript'][$i]['url'];
				}
				$this->javascript[] = $file;
			}
			
			$this->id = $id;
			$this->loaded = true;
			$this->parent_id = $tmp[0]['parent_id'];
			
			return $this->_return(true);
		}
		
		protected function load_options($template_id)
		/* load all options for the requested template
		 *
		 */
		{
			$list = $this->_tx->db->query('template_option', array('template_id'), array($template_id), '*', true);
			if (! db_qcheck($list, true)) return false;
			$options = array();
			foreach($list as $record) {
				$tmp = unserialize($record['value']);
				$options[$record['option']] = $tmp[0];
			}
			return $options;
		}
		
		protected function load_resources($template_id, $parent_id = null)
		/* recursively load all resources for the requested template
		 *  and its parents and return in an array:
		 *
		 *  $arr['css'] = array()
		 *  $arr['javascript'] = array()
		 *  $arr['meta'] = array()
		 *
		 */
		{
			$this->_debug_start('load_resources');
			
			# init
			$r = false;
			
			if ($parent_id) {
				# attempt to load the parent template
				$tmp = $this->_tx->db->query('templates', array('template_id'), array($parent_id), array('parent_id'));
				if (db_qcheck($tmp)) $r = $this->load_resources($parent_id, $tmp[0]['parent_id']);
			}
			
			if (! is_array($r)) {
				# prepare return array
				$r = array('css'=>array(), 'javascript'=>array(), 'meta'=>array());
			}
			
			$t = $this->_tx->db->query(
				array('map_template_resource'=>'template_resource_id','template_resource'=>'id'),
				array('map_template_resource,template_id'),
				array($template_id),
				array('template_resource,id', 'template_resource,type', 'template_resource,contents',
					'template_resource,name', 'template_resource,url', 'template_resource,file_id',
					'map_template_resource,order', 'template_resource,description'),
				true,
				array('map_template_resource,order', 'template_resource,id'));
			
			# since we will always return results simply error checks by setting an emtpy array in case
			#  of query failure
			if (! is_array($t)) $t = array();
			$this->_debug('found ' . count($t) . ' total resources');
			
			for ($i=0;$i<count($t);$i++){
				switch($t[$i]['type']) {
					case 'meta':
						$r['meta'][] = array('id'=>$t[$i]['id'], 'name'=>$t[$i]['name'], 'type'=>$t[$i]['type'],
							'description'=>$t[$i]['description'], 'file_id'=>$t[$i]['file_id'], 'contents'=>$t[$i]['contents'],
							'order'=>$t[$i]['order'], 'linked_to'=>$template_id);
						break;
					default:
						$r[$t[$i]['type']][] = array('id'=>$t[$i]['id'], 'name'=>$t[$i]['name'], 'type'=>$t[$i]['type'],
							'description'=>$t[$i]['description'], 'file_id'=>$t[$i]['file_id'], 'url'=>$t[$i]['url'],
							'order'=>$t[$i]['order'], 'linked_to'=>$template_id);
						break;
				}
			}
			
			return $this->_retByRef($r, 'returning ' . count($r['css']) . '/' . count($r['javascript']) . '/' .
				count($r['meta']) . ' total resources');
		}
		
		public function lookup_element($scope, $ele)
		/* search the loaded template for the specified element
		 *
		 * return the matching element or false
		 *
		 */
		{
			$this->_debug_start("element = $ele");
			
			$this->_debug('<strong>WARNING:</strong> Element lookups are not implemented!');
			
			$result = false;
			
			return $this->_return($result);
		}
		
		public function match_element($scope, $properties)
		/* return an element that comes closest to matching the requested properties
		 *	in the specified scope
		 *
		 */
		{
			$this->_debug_start();
			
			$this->_debug('<strong>WARNING:</strong> Element matching is not implemented!');
			
			$result = false;
			
			return $this->_return($result);
		}
		
		protected function next_id($table = 'templates', $column = 'template_id')
		/* get the next available id in the requested table
		 *
		 */
		{
			$r = $this->_tx->db->queryFull("SELECT MAX(CAST(`$column` AS UNSIGNED)) AS id FROM `$table`");
			if (is_array($r)) return strval(intval($r[0]['id']) + 1);
			return '1';
		}
		
		public function option($name, $value = null)
		/* get or set a template option for a module
		 *
		 * setting or changing an option will automatically save it in the database
		 *
		 * null values are not allowed
		 *
		 * this function always returns the value of the option
		 *
		 */
		{
			# make sure a template is loaded
			if (! $this->loaded) return null;
			
			if (@is_null($value)&&@array_key_exists($name, $this->options)) {
				# return a value only
				return $this->options[$name];
			}
			
			# put value in an array to serialize the type and value
			$tmp = array($value);
			
			if (@array_key_exists($name, $this->options)) {
				# update the database
				$this->_tx->db->update('template_option', array('template_id', 'option'), array($this->id, $name), array('value'), array(serialize($tmp)));
			} else {
				# insert to database
				$this->_tx->db->insert('template_option', array('template_id', 'option', 'value'), array($this->id, $name, serialize($tmp)));
			}
			
			# set local value
			$this->option[$name] = $value;
			
			# value
			return $value;
		}
		
		public function output_css($css_id = '')
		/* load and output cascading style sheet
		 *
		 * if $css_id is empty, use the default/selected array style sheets (combined)
		 *
		 */
		{
			$this->_debug_start();
			
			if ($css_id == '')
			{
				$this->_debug('using predefined css document(s)');
				
			//} elseif (file_exists($css_id)) {
				//$tmp = new css_document();
				//$tmp->load($css_id)
			} else {
				return $this->_return(false, 'invalid css document id');
			}
			
			return $this->_return(true);
		}
		
		public function save()
		/* save the loaded template
		 *
		 */
		{
			
		}
		
		public function status()
		/* return the status of the template object
		 *
		 * returns 'loaded' if a template is loaded or 'unloaded' if nothing is loaded
		 *
		 */
		{
			if ($this->loaded) return 'loaded';
			return 'unloaded';
		}
		
		public function xml_associate_template($parent_id = null, $child_id = null)
		/* associate the provided templates
		 *
		 * optionally process affiliated template resource
		 *  mappings to remove any (now) inherited mappings
		 *
		 */
		{
			return xml_error('XML FUNCTION INCOMPLETE');
		}
		
		public function xml_copy($source_id = false, $to = false, $copy_files = false)
		/* copy the loaded template to a new template using the provided name
		 *
		 * source_id should be the source template id
		 * to should be the new, unique, name
		 *
		 * automatically duplicates child templates
		 *
		 * if copy_files is true, also copies the CSS and Javascript file data into
		 *  new files eventually we should implement source control so we can diff
		 *  the files, or just store changes to the files for the template and not
		 *  completely seperate files.
		 *
		 *  also add a comment to the head of the file with the copy information:
		 *    COPY of #file_id#
		 *    ema template_manager
		 *
		 * returns true on success or false on failure
		 *
		 */
		{
			# validate source id and to
			if ($source_id === false) return xml_error('A source template is required');
			
			# validate the new name is unique
			if ((@strlen($to) == 0)||(@intval($to) == 0)) $to = false;
			if ($to !== false) {
				if (db_qcheck_exec($this->_tx->db->query('templates', array('name'), array($to)))) return xml_error('The provided template name already exists');
			}
			
			if ((@strlen($copy_files) == 0)||(@intval($copy_files) == 0)) {
				$copy_files = false;
			} else {
				$copy_files = true;
			}
			
			# load the requested template
			if (! $this->load($source_id)) return xml_error('Error loading the source template');
			
			$id = $this->copy($to, false, true, false, $copy_files);
			if (! $id) return xml_error('Error duplicating the template');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			$this->_tx->xml_3->id = $id;
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_create_template()
		/* create a new template
		 *
		 */
		{
			# validate input
			if (! array_key_exists('name', $_POST)) return xml_error('A name is required');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# prepare our master field list
			$f = $this->field_list('templates', true);
			unset($f['template_id']);
			
			# generate an id
			$id = $this->next_id();
			
			# create the primary key
			$f['AUTOGEN_ID'] = 'template_id';
			$f['AUTOGEN_VALUE'] = $id;
			
			db_post_insert('templates', $f);
			
			$this->_tx->xml_3->id = $id;
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_create_template_element($template_id = null)
		/* create a new element and associate with the specified template
		 *
		 */
		{
			# validate input
			if (! array_key_exists('name', $_POST)) return xml_error('A name is required');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# prepare our master field list
			$f = $this->field_list('template_elements', true);
			unset($f['element_id']);
			
			# generate an id
			$id = $this->next_id('template_elements', 'element_id');
			
			# create the primary key
			$f['AUTOGEN_ID'] = 'element_id';
			$f['AUTOGEN_VALUE'] = $id;
			
			$r = db_post_insert('template_elements', $f);
			
			if ((! is_null($template_id)) && ($r !== false)) {
				$this->_tx->db->insert('map_template_elements', array('element_id', 'template_id', 'order'), array($id, $template_id, '0'));
			}
			
			$this->_tx->xml_3->id = $r;
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_create_template_resource($template_id = null)
		/* create a new resource and associate with the specified template
		 *
		 */
		{
			# validate input
			if (! array_key_exists('name', $_POST)) return xml_error('A name is required');
			if (! array_key_exists('type', $_POST)) return xml_error('A type is required');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# prepare our master field list
			$f = $this->field_list('template_resource', true);
			unset($f['id']);
			
			# generate an id
			$id = $this->next_id('template_resource', 'id');
			
			# create the primary key
			$f['AUTOGEN_ID'] = 'id';
			$f['AUTOGEN_VALUE'] = $id;
			
			$r = db_post_insert('template_resource', $f);
			
			if ((! is_null($template_id)) && ($r !== false)) {
				$this->_tx->db->insert('map_template_resource', array('template_resource_id', 'template_id', 'order'), array($id, $template_id, '0'));
			}
			
			$this->_tx->xml_3->id = $id;
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_delete_template($template_id = null)
		/* delete the template specified by id
		 *
		 * this function will also delete any template this is a parent for
		 *  as well as any mappings for this id
		 *  and it will call the content_manager function to clean up the deleted template
		 *  from cms tables.
		 *
		 */
		{
			# sql injection protection
			$template_id = $this->_tx->db->escape($template_id);
			if (strpos($template_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'templates', 'search_keys'=>array('template_id'), 'search_values'=>array($template_id), 'return_columns'=>array('template_id')))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# delete the template
			if ($this->_tx->db->delete('templates', array('template_id'), array($template_id))) {
				# delete sub/child templates
				$this->_tx->db->delete('templates', array('parent_id'), array($template_id));
				# delete element and resource mappings
				$this->_tx->db->delete('map_template_elements', array('template_id'), array($template_id));
				$this->_tx->db->delete('map_template_resource', array('template_id'), array($template_id));
				# call the cms hook
				$this->_tx->cms->clean_up_after_template($template_id);
			} else {
				return xml_error('Error deleting the template');
			}
			
			$this->_tx->xml_3 = "<result>Success</result>";
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_delete_template_element($element_id = null)
		/* delete the element specified by id and deassociate with
		 *  the specified template
		 *
		 * this function will also delete any mappings for this element
		 *
		 */
		{
			# sql injection protection
			$element_id = $this->_tx->db->escape($element_id);
			if (strpos($element_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'template_elements', 'search_keys'=>array('element_id'), 'search_values'=>array($element_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# delete the element
			if ($this->_tx->db->delete('template_elements', array('element_id'), array($element_id))) {
				# delete element mappings
				$this->_tx->db->delete('map_template_elements', array('element_id'), array($element_id));
			} else {
				return xml_error('Error deleting the element');
			}
			
			$this->_tx->xml_3 = "<result>Success</result>";
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_delete_template_resource($resource_id = null)
		/* delete the specified resource id
		 *
		 * this function will also delete any mappings for this resource
		 *
		 */
		{
			# sql injection protection
			$resource_id = $this->_tx->db->escape($resource_id);
			if (strpos($resource_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'template_resource', 'search_keys'=>array('id'), 'search_values'=>array($resource_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# delete the element
			if ($this->_tx->db->delete('template_resource', array('id'), array($resource_id))) {
				# delete element mappings
				$this->_tx->db->delete('map_template_resource', array('template_resource_id'), array($resource_id));
			} else {
				return xml_error('Error deleting the resource');
			}
			
			$this->_tx->xml_3 = "<result>Success</result>";
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_get_template($template_id = null)
		/* get the template information
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			# retrieve the data
			$record = $this->_tx->db->query('templates', array('template_id'), array($template_id), '*');
			
			if (! db_qcheck($record)) return xml_error('Invalid ID Provided');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# build the output
			foreach ($record[0] as $col=>$val) { $this->_tx->xml_3->$col = $val; }
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_template_children($template_id = null)
		/* list sub/child templates affiliated with the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			# retrieve the data
			$record = $this->_tx->db->query('templates', array('parent_id'), array($template_id), '*', true);
			
			if (! $record) $record = array();
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
			
			# add the response count
			$this->_tx->xml_3->_cc('count')->_setValue(count($record));
			
			# build the output
			for ($i=0;$i<count($record);$i++) {
				$tmp =& $this->_tx->xml_3->_cc('template');
				foreach ($record[$i] as $col=>$val) { $tmp->$col = $val; }
			}
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_template_elements($template_id = null)
		/* list elements affiliated with the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			# get the field list
			$f = $this->field_list('template_elements');
			
			# prep list for join
			for ($i=0;$i<count($f);$i++) { $f[$i] = 'template_elements,' . $f[$i]; }
			
			# retrieve the data
			$list = $this->_tx->db->query(
				array('map_template_elements'=>'element_id', 'template_elements'=>'element_id'),
				array('map_template_elements,template_id'),
				array($template_id),
				$f,
				true);
			
			if (! $list) $list = array();
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
			
			# add the response count
			$this->_tx->xml_3->_cc('count')->_setValue(count($list));
			
			# build the output
			for ($i=0;$i<count($list);$i++) {
				$tmp =& $this->_tx->xml_3->_cc('element');
				foreach ($list[$i] as $col=>$val) { $tmp->$col = htmlentities($this->_tx->db->unescape($val)); }
			}
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_template_resources($template_id = null)
		/* list resources affiliated with the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			# check if this template has a parent
			$tmp = $this->_tx->db->query('templates', array('template_id'), array($template_id), array('parent_id'));
			if (db_qcheck($tmp)) { $parent_id = $tmp[0]['parent_id']; } else { $parent_id = null; }
			
			# retrieve the data
			$full_list = $this->load_resources($template_id, $parent_id);
			
			if ($full_list) {
				$list = array_merge($full_list['css'], $full_list['javascript'], $full_list['meta']);
			} else {
				$list = array();
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
			
			# add the response count
			$this->_tx->xml_3->_cc('count')->_setValue(count($list));
			
			# build the output
			for ($i=0;$i<count($list);$i++) {
				$tmp =& $this->_tx->xml_3->_cc('resource');
				$tmp->url = '';
				$tmp->contents = '';
				foreach ($list[$i] as $col=>$val) { $tmp->$col = $val; }
			}
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_templates()
		/* outputs all registered templates as xml
		 *
		 */
		{
			# get the field list
			$f = $this->field_list('templates');
			
			$data = $this->_tx->db->query('templates', '', '', $f, true, array('name'));
			if (! is_array($data)) $data = array();
			
		/* <list>
		      <template id="id" name="name">description</template>
		   </list> */
		  
		  $this->_tx->_preRegister('new_xml', array('_tag'=>'list'));
		  $x = $this->_tx->new_xml;
		  
		  // this should be rewritten using the foreach in most of the xml functions above,
		  //  however in order to do so the javascript that uses this function will need
		  //  to be rewritten (specifically, in the cms.js.php file)
		  foreach($data as $record) {
		  	$this->_tx->_preRegister('new_xml_2', array('_tag'=>'template'));
		  	$y = $this->_tx->new_xml_2;
		  	$y->_setAttribute('id', $record['template_id']);
		  	$y->_setAttribute('parent_id', $record['parent_id']);
		  	$y->_setAttribute('name', $record['name']);
		  	$y->_setAttribute('enabled', b2s($record['enabled']));
		  	$y->_setValue($record['description']);
		  	$x->_addChild($y);
		  }
		  
		  echo $x;
		  
		  return true;
		}
		
		public function xml_toggle_template_enabled($template_id = null)
		/* toggle the enabled value of the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			# retrieve the data
			$record = $this->_tx->db->query('templates', array('template_id'), array($template_id), array('enabled'));
			
			if (! db_qcheck($record)) return xml_error('Invalid ID Provided');
			
			if ($record['enabled']) { $toggle = false; } else { $toggle = true; }
			
			if (! $this->_tx->db->update('templates', array('template_id'), array($template_id), array('enabled'), array($toggle))) {
				return xml_error('Error Updating Database');
			}
			
			echo '<response>Success</response>';
			
			return true;
		}
		
		public function xml_unassociate_template($template_id = null)
		/* unassociate the template specified by id from its' parent
		 *
		 * this will make the template a "parent" template by clearing
		 *   the parent_id field
		 *
		 * optionally copy inherited resource mappings (?)
		 *   the problem with doing this now is that the only way to
		 *   effectively "unmap" a resource is to also delete it.
		 *   that means this will have to duplicate the resources
		 *   and create new mappings... which is doable I suppose.
		 *
		 */
		{
			return xml_error('XML FUNCTION INCOMPLETE');
		}
		
		public function xml_unlink_template_element($template_id = null, $element_id = null)
		/* unlink the provided element from the provided template
		 *
		 */
		{
			# sql injection protection
			$template_id = $this->_tx->db->escape($template_id);
			$element_id = $this->_tx->db->escape($element_id);
			if (strpos($template_id, '*') !== false) return xml_error('Invalid ID Provided');
			if (strpos($element_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'templates', 'search_keys'=>array('id'), 'search_values'=>array($template_id)))) {
				return xml_error('Invalid ID Provided');
			}
			if (! db_qcheck_exec(array('table'=>'template_elements', 'search_keys'=>array('element_id'), 'search_values'=>array($element_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# delete element mappings
			if (! $this->_tx->db->delete('map_template_elements', array('template_id', 'element_id'), array($template_id, $element_id))) {
				return xml_error('Error unlinking the element');
			}
			
			$this->_tx->xml_3 = "<result>Success</result>";
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_unlink_template_resource($template_id = null, $resource_id = null)
		/* unlink the provided resource from the provided template
		 *
		 */
		{
			# sql injection protection
			$template_id = $this->_tx->db->escape($template_id);
			$resource_id = $this->_tx->db->escape($resource_id);
			if (strpos($template_id, '*') !== false) return xml_error('Invalid ID Provided');
			if (strpos($resource_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'templates', 'search_keys'=>array('id'), 'search_values'=>array($template_id)))) {
				return xml_error('Invalid ID Provided');
			}
			if (! db_qcheck_exec(array('table'=>'template_resource', 'search_keys'=>array('id'), 'search_values'=>array($resource_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'template'));
			
			# delete element mappings
			if (! $this->_tx->db->delete('map_template_resource', array('template_id', 'template_resource_id'), array($template_id, $resource_id))) {
				return xml_error('Error unlinking the resource');
			}
			
			$this->_tx->xml_3 = "<result>Success</result>";
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_update_template($template_id = null)
		/* save changes to the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			if (strpos($template_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'templates', 'search_keys'=>array('template_id'), 'search_values'=>array($template_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare our master field list
			$f = $this->field_list('templates', true);
			unset($f['template_id']);
			unset($f['parent_id']);
			
			if (! db_post_update('templates', $f, array('template_id'=>$template_id))) {
				return xml_error('Error Updating Database');
			}
			
			echo "<response>Success</response>";
			
			return true;
		}
		
		public function xml_update_template_element($element_id = null)
		/* save changes to the specified element
		 *
		 */
		{
			# input validation
			if ( is_null($element_id) || (strlen($element_id) == 0)) return xml_error('Invalid ID Provided');
			if (strpos($element_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'template_elements', 'search_keys'=>array('element_id'), 'search_values'=>array($element_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare our master field list
			$f = $this->field_list('template_elements', true);
			unset($f['element_id']);
			
			if (! db_post_update('template_elements', $f, array('element_id'=>$element_id))) {
				return xml_error('Error Updating Database');
			}
			
			echo "<response>Success</response>";
			
			return true;
		}
		
		public function xml_update_template_resource($resource_id = null)
		/* save changes to the specified resource
		 *
		 */
		{
			# input validation
			if ( is_null($resource_id) || (strlen($resource_id) == 0)) return xml_error('Invalid ID Provided');
			if (strpos($resource_id, '*') !== false) return xml_error('Invalid ID Provided');
			
			# validate that this record exists
			if (! db_qcheck_exec(array('table'=>'template_resource', 'search_keys'=>array('id'), 'search_values'=>array($resource_id)))) {
				return xml_error('Invalid ID Provided');
			}
			
			# prepare our master field list
			$f = $this->field_list('template_resource', true);
			unset($f['id']);
			
			if (! db_post_update('template_resource', $f, array('id'=>$resource_id))) {
				return xml_error('Error Updating Database');
			}
			
			echo "<response>Success</response>";
			
			return true;
		}
					
	}

?>