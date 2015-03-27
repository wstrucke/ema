<?php
 /* global ema functions
  *
  */

	function b2s($value)
	{
		if ($value == chr(0x01)) return 'true';
		if ($value == chr(0x00)) return 'false';
		if ($value) return 'true';
		return 'false';
	}
	
	function bit2bool($value)
	{
		if (($value===true)||(ord($value)===1)||($value==='1')) return true;
		return false;
	}
	
	function buffer($path_to_file, $process_php = true)
	/* process and return the contents of the script at path_to_file
	 *
	 * if process_php is false, only returns the file contents
	 *
	 */
	{
		# bring the shared transmission object into scope for the included script
		global $t;
		# correct for relative paths
		$path_to_file = str_replace('/./', '/', $path_to_file);
		# enable buffering to process php
		ob_start();
		# process the file
		if ($process_php) {
			include $path_to_file;
		} else {
			readfile($path_to_file);
		}
		# retrieve the output
		$file = ob_get_contents();
		# stop buffering
		ob_end_clean();
		# return file data
		return $file;
	}
	
	function boolval($in, $strict=false)
	/* Checks a variable to see if it should be considered a boolean true or false.
	 *  Also takes into account some text-based representations of true of false,
	 *  such as 'false','N','yes','on','off', etc.
	 * @author Samuel Levy <sam+nospam@samuellevy.com>
	 * @param mixed $in The variable to check
	 * @param bool $strict If set to false, consider everything that is not false to
	 *                     be true.
	 * @return bool The boolean equivalent or null (if strict, and no exact equivalent)
	 *
	 * source: http://www.samuellevy.com/node/7
	 * retrieved: august 22, 2010 23:37 ws
	 *
	 */
	{
		$out = null;
		$in = (is_string($in)?strtolower($in):$in);
		# if not strict, we only have to check if something is false
		if (in_array($in,array('false','no', 'n','0','off',false,0), true) || !$in) {
			$out = false;
		} else if ($strict) {
			// if strict, check the equivalent true values
			if (in_array($in,array('true','yes','y','1','on',true,1), true)) {
				$out = true;
			}
		} else {
			// not strict? let the regular php bool check figure it out (will
			//     largely default to true)
			$out = ($in?true:false);
		}
		return $out;
	}
	
	function countCollection($obj)
	/* alias for count
	 *
	 * exists to provide cross-compatibility with javascript html element implementations
	 *
	 */
	{
		return count($obj);
	}
	
	function db_post_insert($table, $field_arr)
	/* insert a record in to a database table, matching fields from a form post
	 *
	 * this function should be used with *caution* -- make sure to do all of
	 *  your validation in the calling function
	 *
	 * $field_arr should be an array with columns as keys and the
	 *  corresponding data types as values
	 *
	 * this function will return the id of the new record or false on error
	 *
	 * you can optionally provide two array keys to set a manual ID in the
	 *  situation where the table does not automatically generate a
	 *  primary key:
	 *    'AUTOGEN_ID'=> 'column_name', 'AUTOGEN_VALUE'=>'column_value'
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		# sanity check
		if (strlen($table) == 0) return false;
		if (! is_array($field_arr)) return false;
		
		# create the arrays that will be passed to the mysql object
		$saveFields = array();
		$saveValues = array();
		
		# check for our special AUTOGEN_ID field
		if (array_key_exists('AUTOGEN_ID', $field_arr)) {
			$saveFields[] = $field_arr['AUTOGEN_ID'];
			$saveValues[] = $field_arr['AUTOGEN_VALUE'];
			unset($field_arr['AUTOGEN_ID'], $field_arr['AUTOGEN_VALUE']);
		}
		
		# build the insert arrays
		foreach($field_arr as $col=>$type) {
			if ( (array_key_exists($col, $_POST)) && (strlen($_POST[$col]) > 0) ) {
				# set the value based on the field type
				switch($type) {
					case 'string': $saveValues[] = (string)urldecode($_POST[$col]); break;
					case 'integer': $saveValues[] = intval($_POST[$col]); break;
					case 'boolean': $saveValues[] = (bool)$_POST[$col]; break;
					//case 'timestamp': $saveValues[] = "'" . $t->db->escape($_POST[$col]) . "'"; break;
					default: $saveValues[] = "field type error"; break;
				}
				$saveFields[] = $col;
			}
		}
		
		# insert the new record into the database
		if (! $t->db->insert($table, $saveFields, $saveValues)) return false;
		
		# return the insert (new record) id
		return $t->db->insert_id();
	}
	
	function db_post_update($table, $field_arr, $match_arr)
	/* update a record in a database table, matching fields from a form post
	 *
	 * this function should be used with *caution* -- make sure to do all of
	 *  your validation in the calling function
	 *
	 * $field_arr should be an array with columns as keys and the
	 *  corresponding data types as values
	 *
	 * $match_arr should contain one or more key=>value pairs of
	 *  column names to values to use to match the update record(s)
	 *
	 * this function will return the result of the update operation
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		# sanity check
		if (strlen($table) == 0) return false;
		if (! is_array($field_arr)) return false;
		
		# create the arrays that will be passed to the mysql object
		$saveFields = array();
		$saveValues = array();
		
		# build the update arrays
		foreach($field_arr as $col=>$type) {
			if ( (array_key_exists($col, $_POST)) && (strlen($_POST[$col]) > 0) ) {
				# set the value based on the field type
				switch($type) {
					case 'string': $saveValues[] = (string)urldecode($_POST[$col]); break;
					case 'integer': $saveValues[] = intval($_POST[$col]); break;
					case 'boolean': $saveValues[] = (bool)b2s($_POST[$col]); break;
					//case 'timestamp': $saveValues[] = "'" . $t->db->escape($_POST[$col]) . "'"; break;
					default: $saveValues[] = "field type error"; break;
				}
				$saveFields[] = $col;
			}
		}
		
		# return the result of the update operation
		return $t->db->update($table, array_keys($match_arr), array_values($match_arr), $saveFields, $saveValues);
	}
	
	function db_qcheck($result, $more = false)
	/* given a result from a database query, return true if there was exactly one result
	 *
	 * if $more == true, return true if there was one or more results
	 *
	 * returns false if there was an error or zero results
	 *
	 */
	{
		if ($result === false) return false;
		if ($more && (count($result) > 0)) return true;
		if (count($result) == 1) return true;
		return false;
	}
	
	function db_qcheck_exec($query, $more = false)
	/* given the provided database query, execute it on the current database and return
	 *  true if there was exactly one result
	 *
	 * if $more == true, return true if there was one or more results
	 *
	 * returns false if there was an error or zero results
	 *
	 * $query should be either an XML query or array of query arguments
	 *   required array values are:
	 *     'table' => string
	 *     'search_keys' => array()
	 *     'search_values' => array()
	 *   optional array values are:
	 *     'return_columns' => array()
	 *     'sort_by' => array()
	 *     'multiple' => bool (true or false) to return multiple results
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		if ($query instanceof xml_object) return db_qcheck($t->db->query($query), $more);
		
		if (array_key_exists('table', $query)) { $table = $query['table']; } else { return false; }
		if (array_key_exists('search_keys', $query)) { $search_keys = $query['search_keys']; } else { $search_keys = ''; }
		if (array_key_exists('search_values', $query)) { $search_values = $query['search_values']; } else { $search_values = ''; }
		if (array_key_exists('return_columns', $query)) { $return_columns = $query['return_columns']; } else { $return_columns = ''; }
		if (array_key_exists('multiple', $query)) { $multiple = $query['multiple']; } else { $multiple = false; }
		if (array_key_exists('sort_by', $query)) { $sort_by = $query['sort_by']; } else { $sort_by = ''; }
		
		return db_qcheck($t->db->query($table, $search_keys, $search_values, $return_columns, $multiple, $sort_by), $more);
	}
	
	function ema_decrypt($encrypted_data, $salt)
	/* given an encrypted string, decrypt and unserialize it
	 *
	 * required:
	 *   encrypted_data  encrypted data from ema_encrypt
	 *   salt            the salt used in the encryption algorithm
	 *
	 * optional:
	 *   N/A
	 *
	 * returns:
	 *   the decrypted, original variable (of the original type)
	 *
	 * notes:
	 *   companion to ema_encrypt
	 *   requires mcrypt functions or behavior is undefined
	 *
	 */
	{
		# validate mcrypt support
		if (!function_exists('mcrypt_decrypt')) return @unserialize($encrypted_data);
		
		# WARNING: the mcrypt code has not be tested; abort
		return @unserialize($encrypted_data);
		
		# convert the hex data back to binary
		$e = pack("H*" , $encrypted_data);
		
		# Encryption Algorithm
		$alg = MCRYPT_RIJNDAEL_256;
		
		# Create the initialization vector for increased security.
		$iv = mcrypt_create_iv(mcrypt_get_iv_size($alg, MCRYPT_MODE_ECB), MCRYPT_RAND);
		
		$d = mcrypt_decrypt($alg, $salt, $e, MCRYPT_MODE_CBC, $iv);
		
		# unserialize the decrypted variable
		return @unserialize($d);
	}
	
	function ema_encrypt($var, $salt)
	/* given any variable, serialize and encrypt it
	 *
	 * required:
	 *   var             any variable
	 *   salt            a salt to use in the encryption algorithm
	 *
	 * optional:
	 *   N/A
	 *
	 * returns:
	 *   an encrypted string of abitrary length
	 *
	 * notes:
	 *   companion to ema_decrypt
	 *   requires mcrypt functions or behavior is undefined
	 *
	 */
	{
		# serialize the input variable
		$v = serialize($var);
		
		# validate mcrypt support
		if (!function_exists('mcrypt_create_iv')) return $v;
		
		# WARNING: the mcrypt code has not be tested; abort
		return $v;
		
		# Encryption Algorithm
		$alg = MCRYPT_RIJNDAEL_256;
		# Create the initialization vector for increased security.
		$iv = mcrypt_create_iv(mcrypt_get_iv_size($alg, MCRYPT_MODE_ECB), MCRYPT_RAND);
		
		# Encrypt $string
		$e = mcrypt_encrypt($alg, $salt, $v, MCRYPT_MODE_CBC, $iv);
 
		# Convert to hexadecimal and output to browser
		return bin2hex($e);
		$decrypted_string = mcrypt_decrypt($alg, $key, $encrypted_string, MCRYPT_MODE_CBC, $iv);
	}
	
	function ema_error_handler($er_num, $er_msg, $er_file = false, $er_line = false, $er_context = false)
	{
		// disable for now
		return false;
		
		switch ($er_num) {
    case E_USER_ERROR:
        echo "<b>My ERROR</b> [$er_num] $er_msg<br />\n";
        echo "  Fatal error on line $er_line in file $er_file";
        echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        echo "Aborting...<br />\n";
        exit(1);
        break;
    
    case E_USER_WARNING:
        echo "<b>My WARNING</b> [$er_num] $er_msg<br />\n";
        break;

    case E_USER_NOTICE:
        echo "<b>My NOTICE</b> [$er_num] $er_msg<br />\n";
        break;
    
    default:
        echo "Unknown error type: [$er_num] $er_msg<br />\n";
        echo "  Non-fatal error on line $er_line in file $er_file<br />\n";
        break;
    }
    
    /* Don't execute PHP internal error handler */
    return true;
	}
	
	function ema_exception_handler($ex)
	{
		// disable for now
		return false;
		
		echo "Uncaught exception: " , $ex->getMessage(), "\n";
	}
	
	function ema_urldecode($value)
	/* safely decode a string posted with javascript's encodeURIComponent, including utf-8 characters
	 *
	 * source: http://weierophinney.net/matthew/archives/133-PHP-decoding-of-Javascript-encodeURIComponent-values.html
	 * retrieved: jul-18-2011 ws
	 *
	 * credit where credit is due; thanks
	 *
	 */
	{
		if (is_array($value)) {
			foreach ($value as $key=>$val) $value[$key] = utf8Urldecode($val);
		} else {
			$value = preg_replace('/%([0-9a-f]{2})/ie', 'chr(hexdec($1))', (string) $value);
		}
		return stripslashes($value);
	}
	
	function formspecialchars($var)
	/* source: http://php.net/manual/en/function.htmlspecialchars.php
	 * author: nessthehero at gmail dot com
	 * retrieved: dec-03-2010 ws
	 *
	 */
	{
		$pattern = '/&(#)?[a-zA-Z0-9]{0,};/';
		
		// If variable is an array
		if (is_array($var)) {
			// Set output as an array
			$out = array();
			foreach ($var as $key => $v) {
				$v = mb_convert_encoding($v, 'UTF-8', mb_detect_encoding($v));
				// Run formspecialchars on every element of the array and return the result. Also maintains the keys.
				$out[$key] = formspecialchars($v);
			}
		} else {
			$var = mb_convert_encoding($var, 'UTF-8', mb_detect_encoding($var));
			$out = $var;
			while (preg_match($pattern,$out) > 0) {
				$out = htmlspecialchars_decode($out, ENT_QUOTES | ENT_IGNORE);
			}
			// Trim the variable, strip all slashes, and encode it
			$out = htmlspecialchars(stripslashes(trim($out)), ENT_QUOTES | ENT_IGNORE,'UTF-8',true);
		}
		
		return $out;
	}
	
	function img($path, $id = false, $width = false, $height = false, $alt = false, $title = false)
	/* create an image element and return the string
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		if ($t->get->link_rewrite)
		{
			$str = '<img src="' . $t->get->uri . '/' . $path . $t->get->ps . $id . '" ';
		} else {
			$str = '<img src="' . $_SERVER['PHP_SELF'] . '?' . $t->get->content_request_code . '=' . $path
				. '&' . $t->get->download_request_code . '=' . $id . '" ';
		}
		
		if ($width !== false) $str .= "width=\"$width\" ";
		if ($height !== false) $str .= "height=\"$height\" ";
		if ($alt !== false) $str .= "alt=\"$alt\" ";
		if ($title !== false) $str .= "title=\"$title\" ";
		$str .= '/>';
		
		return $str;
	}
	
	function l($link_text, $path='')
	/* create a link and return the string
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		if ($t->get->link_rewrite)
		{
			$str = '<a href="' . $t->get->uri . "/$path\">$link_text</a>";
		} else {
			$str = '<a href="' . $_SERVER['PHP_SELF'] . '?' . $t->get->content_request_code
				. "=$path\">$link_text</a>";
		}
		
		return $str;
	}
	
	function lb($link_text, $extended_path = '', $extra_args = false)
	/* create a link back to the current page
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		# set the path to the current path
		$path = $t->get->request_string;
		
		# append any extended path provided
		if (is_string($extended_path)&&(strlen($extended_path)>0)) $path .= "/$extended_path";
		
		# get the link
		$link = l($link_text, $path);
		
		# build the optional argument list
		# check if there are existing arguments in the path
		if (strpos($link, '?') !== false) { $extra = '&'; } else { $extra = '?'; }
		foreach ($extra_args as $k=>$v) { $extra .= "$k=$v&"; }
		# remove the last ampersand
		$extra = substr($extra, 0, strlen($extra)-1);
		
		# add the extra args
		$link = preg_replace('/\ href="(.*)">/', ' href="\1' . $extra . '">', $link);
		
		return $link;
	}
	
	function mailto($address, $mask_address = true, $subject = '')
	/* return a mailto link using the specified address
	 *
	 * if mask_address is true, email address "test@example.com" will be converted to
	 *   "test (at) example.com"
	 *
	 * optionally include a message subject
	 *
	 */
	{
		if (strlen($address) == 0) return '';
		$l = '<a href="mailto:' . $address;
		if (strlen($subject) > 0) { $l .= '?subject=' . htmlentities($subject); }
		$l .= '">';
		if ($mask_address) {
			$l .= str_replace('@', ' (at) ', $address);
		} else {
			$l .= $address;
		}
		$l .= '</a>';
		return $l;
	}
	
	function message($string, $type = 'notice')
	/* set a message at the top of the html->body output
	 *
	 * optional type (notice|error)
	 *
	 */
	{
		# bring the transmission into the local scope
		global $t;
		# validate the cms object
		if (!is_object($t->cms)) return false;
		# try to add the message
		return $t->cms->message($string, $type);
	}
	
	function url($path = '', $extra_args = false)
	/* return a url from the provided path
	 *
	 */
	{
		# bring the global transmission link into scope
		global $t;
		
		if ($t->get->link_rewrite) {
			$r = $t->get->uri . '/' . $path;
		} else {
			$r = $_SERVER['PHP_SELF'] . '?' . $t->get->content_request_code . '=' . $path;
		}
		
		# build the optional argument list
		# check if there are existing arguments in the path
		if (!is_array($extra_args)) $extra_args = array();
		$extra = '';
		if (strpos($r, '?') !== false) { $extra = '&'; } else { $extra = '?'; }
		foreach ($extra_args as $k=>$v) { $extra .= "$k=$v&"; }
		# remove the last ampersand
		$extra = substr($extra, 0, strlen($extra)-1);
		
		return $r . $extra;
	}
?>