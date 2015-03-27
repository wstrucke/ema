<?php
 /* Debugger Abstract Object for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Oct-06-2008
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	abstract class debugger
	{
		private $_version;
		
		private $_debug_mode;       // enable debugging mode
		private $_function_level;   // to count debug indentation for formatting
		private $_debug_prefix;
		private $_debug_color;
		private $_colors;
		
		abstract protected function _debug($message, $no_line_break = false);
	}
?>