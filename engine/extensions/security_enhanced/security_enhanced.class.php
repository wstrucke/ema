<?php
 /* Enhanced Security Access Management System Extension for ema
  *
  * Revision 1.0.0, May-09-2010
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	class security_enhanced extends standard_extension
	{
		protected $pageid;
		protected $default_home;
		
		public $_name = 'Enhanced Security Access Management System Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'security_enhanced';
		
		public $err;                     // internal error code to be shared, if one is encountered
		
		/* code */
		
		protected function _construct()
		/* initialize security_enhanced class
		 *
		 */
		{
			$this->_tx->_publish('security_error', $this->err);
		}
		
		public function control_panel()
		/* output the security manager control panel page
		 *
		 * this should probably be in the database and just use generated data from the engine,
		 *	but we'll get to that later
		 *
		 */
		{
			# enable buffering to process php in the control panel
			ob_start();
			# content management system console
			include(dirname(__FILE__) . '/content/cpanel.php');
			# retrieve the output
			$file = ob_get_contents();
			# stop buffering
			ob_end_clean();
			# return file data
			return $file;
		}
		
		public function validate()
		/* check the access to the requested content
		 *
		 */
		{
			$this->_debug_start();
			
			//if ($this->pageid == '&') { $this->pageid = 'home'; }
			
			$this->_debug('<strong>WARNING:</strong> access validation has not been implemented! There is no security!');
			
			return $this->_return(true);
		}
		
	}
?>