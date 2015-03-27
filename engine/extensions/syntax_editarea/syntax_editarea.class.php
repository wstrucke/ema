<?php
	/* editarea for ema
	 *
	 * Revision 1.0.0, May-11-2010
	 * William Strucke, wstrucke@gmail.com
	 *
	 * to do:
	 *	-	everything
	 *
	 */
	
	class syntax_editarea extends standard_extension
	{
		protected $no_areas = 0;              // internal tracker for the number of edit areas on the page
		
		# standard extension variables
		protected $_copyright = 'Extension Copyright &copy; 2010 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
		protected $_debug_prefix = 'editarea';
		public $_name = 'editarea Version 0.8.2; Editarea Extension';
		public $_version = '1.0.1';
		
		public function preoutput()
		/* register javascript hook
		 *
		 */
		{
			# add the required javascript
			$this->_tx->get->html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript','src'=>url('editarea/edit_area_full.js')));
			# define the javascript
			$js = "registerHook('createWindow', function(){
				// get any text areas in the html output
				var list = $$('textarea');
				// preset settings
				var settings = '';
				// for now extend all text areas until we define settings for the syntax_editarea extension
				for (var i=0;i<list.length;i++) {
					// set the default content type
					var type = 'php';
					// try to determine the text area content type
					try {
						if (list[i].get('lang').length > 0) { type = list[i].get('lang'); }
					} catch(e) {}
					// validate the selected type
					switch (type) {
						case 'css': break;
						case 'javascript': type = 'js'; break;
						default: type = null; break;
					}
					if (type != null) {
						// get the id
						if ((typeof(list[i].getAttribute('id')) != 'string') || (list[i].getAttribute('id').length == 0)) {
							// for now, assume only one text area per page... this will have to be changed as well
							var area_id = 'syntax_1';
							list[i].setAttribute('id', area_id);
						} else {
							var area_id = list[i].getAttribute('id');
						}
						// create the syntax editor
						editAreaLoader.init({id: area_id,start_highlight: true,allow_resize: 'y',allow_toggle: false,word_wrap: true,language: 'en',syntax: type});
					}
				}
				return true;
			});";
			# insert into the document (for now)
			$this->_tx->get->html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript'))->set_value($js);
			return true;
		}
		
		public function postoutput()
		/* check output for a text area with class 'syntax'
		 *  and extend it
		 * 
		 *
		 */
		{
			$this->_debug_start();
			# set a reference to the compiled html output
			$html =& $this->_tx->get->html;
			# get any text areas in the html output
			$list = $html->body->match_children('tag', 'textarea');
			# debug output
			$this->_debug('found ' . count($list) . ' text areas');
			# preset settings
			$settings = '';
			# if the element class contains 'syntax' extend it
			for ($i=0;$i<count($list);$i++) {
				if (in_array('syntax', $list[$i]->get_class())) {
					# found an object to extend
					
					# increment the global textarea counter
					$this->no_areas++;
					
					# get the id
					$id = $list[$i]->get_id();
					
					# make sure the text area has an id (required for javascript)
					if (strlen($id) == 0) {
						$id = 'syntax_' . $this->no_areas;
						$list[$i]->set_id($id);
					}
					
					if ($this->no_areas == 1) {
						# add the required javascript
						$html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript','src'=>url('editarea/edit_area_full.js')));
					}
					
					# compile the settings for the text area
					$settings .= "editAreaLoader.init({id: '$id',start_highlight: true,allow_resize: 'y',allow_toggle: false,word_wrap: true,language: 'en',syntax: 'php'});";
				}
			}
			
			# if there were results, insert the init element
			if (strlen($settings) > 0) {
				$html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript'))->set_value($settings);
			}
			
			return $this->_return(true);
		}
		
		public function editarea($path = '')
		{
		/*
			if ($this->_tx->get->link_rewrite)
			{
				# if link rewrite is enabled, the file id should be after the last slash
				#  or other path seperator (ps) in the URI
				$tmp = explode($this->_tx->get->ps, $_SERVER['REQUEST_URI']);
				$id = $tmp[count($tmp) - 1];
			} else {
				# make sure something was requested
				if (! isset($_REQUEST[$this->download_request_code])) return false;
			
				# get the id
				$id = $_REQUEST[$this->download_request_code];
			}
		*/
			
			# sanity check
			if ( ($path === false) || (strlen($path) == 0) ) return true;
			
			$root = dirname(__FILE__) . '/edit_area/';
			
			# make sure the file exists
			if (! file_exists($root . $path)) {
				header('Content-Type: text/html'); echo "FILE DOES NOT EXIST: "; var_dump($root . $path); exit;
				return false;
			}
			# load the file
			ob_start();
			readfile($root . $path);
			$file = ob_get_contents();
			ob_end_clean();
			$size = strlen($file);
			$file_time = filemtime($root . $path);
			$modified = gmdate('D, d M Y H:i:s', $file_time) .' GMT';
			
			switch (substr($path, strlen($path) - 3))
			{
				case '.js':	$mime = 'text/javascript'; break;
				case 'css': $mime = 'text/css'; break;
				case 'xml': $mime = 'text/xml'; break;
				case 'gif': $mime = 'image/gif'; break;
				case 'png': $mime = 'image/png'; break;
				default: $mime = 'text/html'; break;
			}
			
			$name = $path;
			while (strpos($name, '/') !== false) {
				$p = strpos($name, '/');
				$name = substr($name, $p + 1);
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
				$headers = array();
			}
			
			if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) >= $file_time))
			{
				header('HTTP/1.1 304 Not Modified');
				header('Connection: close');
			} else {
				# required for IE
				if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off'); }
				
				#header('HTTP/1.1 200 OK');
				#header('Pragma: public');
				header('Content-Type: ' . $mime);
				#header('Cache-Control: private',false);
				#header('Content-Transfer-Encoding: binary');
				#header("Content-Description: File Transfer");
				#header("Content-Length: $size");
				#header("Content-Disposition: attachment; filename=" . $name);	// inline to not force download
				header('Last-Modified: ' . $modified);
				#header('Connection: close');
				echo $file;
			}
		}
	}
?>