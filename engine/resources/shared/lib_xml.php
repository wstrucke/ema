<?php
 /* global xml functions
  *
  */
	
	function xml_error($error_message = '')
	/* output a standard xml error message
	 *
	 * this function always returns false to allow calling procedures to simply
	 *  use the syntax "return xml_error();", simultaneously outputting the
	 *  xml_error message and telling the calling function that it failed
	 *
	 */
	{
		if (strlen($error_message) == 0) $error_message = 'An error has occurred';
		echo "<error>$error_message</error>";
		return true;
	}
	
	function xml_response($message = '')
	/* output a basic xml response message
	 *
	 * this function always returns true to allow calling procedures to simply
	 *  use the syntax "return xml_response();", simultaneously outputting the
	 *  xml_response message and telling the calling function that it succeeded
	 *
	 */
	{
		if (strlen($message) == 0) $message = 'ok';
		echo "<response>$message</response>";
		return true;
	}

?>