<?php
 /* Standard Extension Object for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Oct-06-2008/Jul-04-2009/Jul-09-2009/May-08-2010/Aug-15-2010
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	class standard_extension extends debugger
	{
		protected $_copyright = 'Copyright &copy; 2004-2011 <a href="mailto:wstrucke@gmail.com">William Strucke</a>';
		protected $_debug_color;        // selected debug output color for prefix
		protected $_debug_match = '';   // optional comma seperated list of debug_prefixes to match when enabling debugging
		protected $_debug_mode = -1;    // enable debugging mode
		protected $_debug_output = 2;   // debug output mode: 0=disabled, 2=inband/html,4=out of band/file,6=screen+file
		protected $_debug_prefix = '';  // object debug output prefix
		protected $_function_level = 0; // to count debug indentation for formatting
		protected $_in_loop = false;    // set to true to disable debug output in a nested loop
		public $_myloc;                 // server directory path to the instantiated object set by transmission
		public $_name;                  // the loaded object's name
		protected $_tx;                 // transmission object reference
		public $_tx_instance;           // transmission object's instance number for this element
		public $_version;               // the loaded object's version string
		
		public function __clone()
		/* handle clone processes
		 *
		 */
		{
			global $t;
			$this->_tx = $t;
			return true;
		}
		
		public function __construct(transmission &$transmission_obj, $args = array())
		{
			# set arguments
			foreach($args as $key=>&$value) {
				if (is_object($value) || is_array($value)) {
					$this->{$key} =& $value;
				} else {
					$this->{$key} = $value;
				}
			}
			
			# check if debugging is disabled
			if ( (property_exists($this, '_no_debug')) && ($this->_no_debug) ) $this->_debug_mode = -1;
			
			# send initialization headers
			$this->_debug("initializing $this->_debug_prefix @ " . date('m/d/Y H:i:s'));
			$this->_debug($this->_name . ' version ' . $this->_version . ', ' . $this->_copyright);
			$this->_debug('debug level ' . $this->_debug_mode);
			$this->_debug('');
			
			# connect to transmission
			$this->_tx =& $transmission_obj;
			
			# optionally execute child object initialization method
			if (method_exists($this, '_construct')) return $this->_construct();
			
			return true;
		}
		
		public function _content($name='')
		/* includes specified content file (if it exists) in the scope of this object
		 *
		 * returns the content or false on an error
		 *
		 */
		{
			# bring global transmission link into scope to allow for multifunction fuse
			#  and automatically linked content
			global $t;
			if (strlen($name)==0) $name='index';
			if (@file_exists($this->_myloc . "content/$name.php")) {
				return buffer($this->_myloc . "content/$name.php");
			}
			return false;
		}
		
		protected function _debug($message, $no_line_break = false)
		/* conditionally output debug message
		 *
		 */
		{
			# set the global file handle to avoid locking issues
			global $_ema_debug_file_handle;
			# validate debug prefix
			if (!is_string($this->_debug_prefix)) $this->_debug_prefix = '';
			# check for debug matching
			if (is_string($this->_debug_match)) {
				$match = explode(',', $this->_debug_match);
				if (!in_array($this->_debug_prefix, $match)) return true;
			}
			# check if colors have been set yet
			if ($this->_debug_color == '') {
				$colors = array(  '#666', '#ff3300', '#cc3300', '#cc0033', '#333300', '#666600', '#0033ff',
				                  '#cc6633', '#330000', '#660000', '#990000', '#cc0000', '#ff0000', '#666633',
				                  '#996600', '#663333', '#993333', '#cc3333', '#ff3333', '#cc3366', '#0033cc',
				                  '#669900', '#996633', '#663300', '#990033', '#cc3399', '#339900', '#cc6600',
				                  '#cc0066', '#990066', '#336600', '#669933', '#cc6699', '#993366', '#660033',
				                  '#cc0099', '#330033', '#996699', '#993399', '#990099', '#663366', '#660066',
				                  '#006600', '#336633', '#009900', '#339933', '#ff00ff', '#cc33cc', '#003300',
				                  '#006633', '#339966', '#3399ff', '#9966cc', '#663399', '#330066', '#9900cc',
				                  '#cc00cc', '#009933', '#0066cc', '#9933ff', '#6600cc', '#660099', '#cc33ff',
				                  '#cc00ff', '#009966', '#003366', '#336699', '#6666ff', '#6666cc', '#0066ff',
				                  '#330099', '#9933cc', '#9900ff', '#339999', '#336666', '#006699', '#003399',
				                  '#3333ff', '#3333cc', '#333399', '#333366', '#6633cc', '#009999', '#006666',
				                  '#003333', '#3366cc', '#0000ff', '#0000cc', '#000099', '#000066', '#000033',
				                  '#6633ff', '#3300ff', '#3366ff', '#3300cc');
				# pick a color
				$this->_debug_color = $colors[array_rand($colors)];
				if (($this->_debug_mode >= 99)&&(($this->_debug_output - 6 >= 0)||($this->_debug_output == 2))) {
					echo "DEBUG MODE ENABLED [$this->_debug_color]<br />\r\n";
					# enable error output (enable during development)
					@error_reporting(E_ALL);
					@ini_set("display_errors", 1);
				}
			}
			if ( ($this->_debug_mode >= $this->_function_level) && ($this->_in_loop == false) ) {
				if (($this->_debug_output - 6 >= 0)||($this->_debug_output == 2)) {
					echo '<span style="color:' . $this->_debug_color . ';">' . date('Y-m-d H:i:s') . " $this->_debug_prefix";
					if ($this->_debug_mode >= 99) { echo "[$this->_function_level]"; }
					echo '</span>';
					for ($i=0;$i<$this->_function_level;$i++) { echo '&nbsp;&nbsp;'; }
					echo " $message";
					if (!$no_line_break) echo "<br />\r\n";
				}
				if ($this->_debug_output - 4 >= 0) {
					# file output
					if (!is_resource($_ema_debug_file_handle)) $_ema_debug_file_handle = @fopen('ema_debug.txt', 'a');
					if (is_resource($_ema_debug_file_handle)) {
						$m = date('Y-m-d H:i:s') . ' ' . $_SERVER['SERVER_ADDR'] . ' ' . $_SERVER['REMOTE_ADDR'] . ' ' . $_SERVER['REQUEST_URI'] . " $this->_debug_prefix";
						if ($this->_debug_mode >= 99) { $m .= "[$this->_function_level]"; }
						for ($i=0;$i<$this->_function_level;$i++) { $m .= ' '; }
						$m .= " $message";
						if (!$no_line_break) $m .= "\r\n";
						@fwrite($_ema_debug_file_handle, $m);
					}
				}
			}
			unset($colors);
		}
		
		public function _debug_off()
		/* temporarily disable debugging for this object
		 *
		 */
		{
			$this->_debug_mode = -1;
			return true;
		}
		
		public function _debug_on($mode = 99, $device = 2)
		/* temporarily enable debugging for this object
		 *
		 */
		{
			if ($mode < 0) $mode = 99;
			$this->_debug_match = false;
			$this->_debug_mode = $mode;
			$this->_debug_output = $device;
			return true;
		}
		
		protected function _debug_start($message = '', $increment = 1)
		/* enable debugging for the calling function
		 *
		 * optionally output $message if one is supplied
		 *
		 * standard function level increment is 1, unless one is specified
		 *
		 */
		{
			# enable php backtrace to get the name of the caller
			$dbg = debug_backtrace(false);
			
			$this->_debug('function ' . $dbg[1]['function'] . "($message) {");
			$this->_function_level = $this->_function_level + $increment;
		}
		
		protected function _has($extension)
		/* alias for transmission _check function in the scope of this object
		 *
		 */
		{
			return $this->_tx->_check($extension);
		}
		
		public function _install()
		/* run any code to install the class
		 *
		 * this function will be executed during version changes
		 *  so it shouldn't do any one time actions without
		 *  checking the validity of those actions first
		 *
		 */
		{
			$this->_debug('Installing');
			# bring global transmission link into scope to allow for multifunction fuse
			#  and automatically linked content
			global $t;
			if (@file_exists($this->_myloc . "install.php")) {
				# validate cms link
				if ((get_class($this)!='cms_manager')&&(!is_object($t->__get('cms')))&&(!is_object($t->cms))) { die('CMS Module is inaccessible in ' . get_class($this)); }
				$this->_debug('Running installation script for this module');
				include_once($this->_myloc . "install.php");
			}
			return true;
		}
		
		protected function _option($name)
		/* get or set the specified local option
		 *
		 * this function supports a variable number of arguments
		 * 
		 * $name is required; it can be either a string to retrieve (or set)
		 *  one value or an array of strings for the same purpose
		 *
		 * argument 2 is an optional value to set the variable to
		 * argument 3 allows the scope to be specified
		 *
		 * if the scope is not specified, the transmission will decide based
		 *  on the context of the request
		 *
		 * this function will always return the current value (even if
		 *  setting a value fails)
		 *
		 * if a single (string) option is set/requested a single value
		 *  will be returned
		 *
		 * if an array of options is set/requested, an array is returned
		 *
		 * if an array of options is provided but a single value is provided to
		 *  set them to, all options will be set to the same value, otherwise
		 *  if an array of corresponding values is provided both arrays must
		 *  have the same number of items.
		 *
		 * on error this function returns NULL
		 *
		 */
		{
			$args = array('module'=>get_class($this), 'option'=>$name);
			$num = func_num_args();
			if ($num > 1) $args['value'] = func_get_arg(1);
			if ($num > 2) $args['scope'] = func_get_arg(2);
			return $this->_tx->_moduleOption($args);
		}
		
		protected function &_retByRef(&$value, $message = '', $increment = 1)
		/* returns a value by reference on behalf of an internal function 
		 *	cleaning up as necessary
		 *
		 */
		{
			if ($message != '') $this->_debug($message);
			$this->_debug('}');
			$this->_function_level = $this->_function_level - $increment;
			return $value;
		}
		
		protected function _return($value, $message = '', $increment = 1)
		/* returns a value on behalf of an internal function 
		 *	cleaning up as necessary
		 *
		 */
		{
			if ($message != '') $this->_debug($message);
			$this->_debug('}');
			$this->_function_level = $this->_function_level - $increment;
			return $value;
		}
		
		public function _xml($_xml_file='')
		/* includes specified xml file (if it exists) in the scope of this object
		 *
		 * returns true if the content was included successfully, false on an error
		 *
		 */
		{
			# bring global transmission link into scope to allow for multifunction fuse
			#  and automatically linked content
			global $t;
			if (strlen($_xml_file)==0) return false;
			if (func_num_args() > 1) {
	  		$_xml_args = func_get_args();
	  		array_shift($_xml_args);
	  	} else {
	  		$_xml_args = array();
	  	}
			if (@file_exists($this->_myloc . "xml/$_xml_file.php")) {
				# process args
				foreach($_xml_args as $_arg=>$_val) { ${$_arg}=$_val; }
				include($this->_myloc . "xml/$_xml_file.php");
				return true;
			}
			return false;
		}
			
	}
?>