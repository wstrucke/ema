<?php
	/* CKEditor for ema
	 *
	 * Revision 1.0.0, Apr-17-2010
	 * William Strucke, wstrucke@gmail.com
	 *
	 * to do:
	 *	-	everything
	 *
	 */
	
	class wysiwyg_ckeditor extends standard_extension
	{
		# database version
		public $schema_version='0.0.2';       // the schema version to match the registered schema
		
		# standard extension variables
		protected $_copyright = 'Extension Copyright &copy; 2010 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
		protected $_debug_prefix = 'ckeditor';
		public $_name = 'CKEditor Version 3.2.1; CKEditor Extension';
		public $_version = '1.0.1';
		
		public function ckeditor()
		{
			$t = func_get_args();
			$path = @implode('/', $t);
			if ( ($path === false) || (strlen($path) == 0) ) return true;
			
			$root = dirname(__FILE__) . '/';
			
			# make sure the file exists
			if (! file_exists($root . $path) || is_dir($root . $path)) {
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
			
			# required for IE
			if(ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off'); }
			header('Content-Type: ' . $mime);
			header('Last-Modified: ' . $modified);
			echo $file;
			exit;
		}
		
		public function preoutput()
		/* register javascript hook
		 *
		 */
		{
			# add the required javascript
			$this->_tx->get->html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript','src'=>url('ckeditor/ckeditor.js')));
			# insert into the document (for now)
			$this->_tx->get->html->head->cc('script')->sas(array('language'=>'javascript','type'=>'text/javascript','src'=>url('download/wysiwyg_ckeditor.js')));
			return true;
		}
	}
		