<?php
 /* People Extension for ema
  *
  * Revision 1.0.0, May-09-2010
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	class people_standard extends standard_extension
	{
		public $_name = 'People Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'people';
		
		public $err;                     // internal error code to be shared, if one is encountered
		
		/* code */
		
		protected function _construct()
		/* initialize people
		 *
		 */
		{
			
		}
		
		public function control_panel()
		/* output the people control panel page
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
		
	}
?>