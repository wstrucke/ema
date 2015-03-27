<?php
 /* Standard Output Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Nov-23-2008
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	class output_standard extends standard_extension
	{	
		protected $_cache;
		protected $_live = false;
		
		public $_name = 'Standard Output Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'output';
		protected $_no_debug = true;
		
		public function __toString()
		{
			#return htmlspecialchars($this->_cache);
			return $this->_cache;
		}
		
		public function _construct()
		/* init
		 *
		 */
		{
			$this->_cache = '';
		}
		
		public function _send($str, $tab = 0)
		/* output or cache data
		 *
		 */
		{
			$this->_debug_start('received ' . strlen($str) . ' bytes for output');
			
			$tabs = '';
			for ($i=0;$i<$tab;$i++) { $tabs .= "\t"; }
			$this->_cache .= "$tabs$str\r\n";
			if ($this->_live) {
				echo "$tabs$str\r\n";
			} else {
				$this->_debug('running mode: silent');
			}
			
			return $this->_return(true, 'New output size: ' . strlen($this->_cache));
		}
		
		public function _toggle($mode = -1)
		/* toggle live mode
		 *
		 */
		{
			if (is_bool($mode)) { $this->_live = $mode; } else { $this->_live = !($this->_live); }
			return true;
		}
		
		public function get()
		/* get the cached output
		 *
		 */
		{
			return $this->_cache;
		}
				
	}
?>