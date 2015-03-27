<?php
/*	TBDBITL Secure Area :: _functions/functions.php

	SUMMARY:	misc global functions to supplement PHP
	AUTHOR:		William Strucke, 2005.03.01
	REQUIRES:	n/a
	RETURNS:	n/a
	SECURITY:	00000000 (PUBLIC)
*/

function right ($string, $length) {
	if (strlen($string) < $length) { return $string; }
	if (strlen($string) > 0) {
		$string = strrev($string);
		$temp = chunk_split($string, $length, "|");
		$string = explode("|", $temp);
		$temp = strrev($string[0]);
		return $temp;
		}
	} //right
	
function left ($string, $length) {
	if (strlen($string) < $length) { return $string; }
	$temp = chunk_split($string, $length, "|");
	$string = explode("|", $temp);
	return $string[0];
	} //left
	
function get_mbYear() {
	# return the current marching band year
	$this_year = date('Y');
	$this_month = date('m');
	$this_day = date('d');
	if ($this_month < 9) 
	{
		if ( ($this_month == 8) && ($this_day > 29) )
		{
			# account for early tryouts in 2006
			return $this_year;
		} else {
			# normal jan - august response
			return $this_year - 1;
		}
	} else {
		# normal sept - dec
		return $this_year;
		}
	}
	
function displayTitle ($text) {
	# output a page title
	echo "\r\n<title>$text</title>\r\n";
	}
	
function sortby_field (&$arr, $no, $sort_type = SORT_NUMERIC) {
	# sort an array by specified field number
	if (isset($tmp)) { unset($tmp); }
	foreach ($arr as $value) {
		# move sorted field to front by adding extra field
		$row = explode('|', $value);
		$tmp[count($tmp)] = $row[$no] . '|' . implode('|', $row);
		} // foreach
	# sort new array
	sort($tmp, $sort_type);
	# restore sorted array to original
	for ($sortby_field_counter = 0; $sortby_field_counter < count($arr); $sortby_field_counter++) {
		$value = explode('|', $tmp[$sortby_field_counter], 2);
		$arr[$sortby_field_counter] = $value[1];
		} // for
	}
		
function displayError ($text) {
	# output a formatted error the screen
	echo "\r\n<div align=\"center\"><h4>$text</h4></div>\r\n";
	}
	
function parseaccesslevel($a) {
	// given a string ($a) with one to eight numbers, return an array of those numbers
	
	for ($i = 0; $i < 8; $i++) { $temp[$i] = 0; }	// reset vars to zero
	for ($i = 0; $i < strlen($a); $i++) { $temp[substr($a, $i, 1)] = 1; }
	
	return $temp;
	
} // end parseaccesslevel

function alert($text) {
	# output a javascript alert command to the browser with text in the msg box
	echo "<script language=\"javascript\" type=\"text/javascript\"> alert('$text'); </script>\r\n";
} // alert

function findObjects (&$haystack, $pos, $needle) {
	# locate needle in haystack at array position $pos.  return all results as array
	while (count($haystack) > 0) {
		$real_haystack = explode("|", $haystack[0]);
		if ($real_haystack[$pos] == $needle) { $result[count($result)] = $haystack[0]; }
		else { $remaining_haystack[count($remaining_haystack)] = $haystack[0]; }
		array_shift($haystack);
		}
	# return vars not removed from haystack to haystack
	$haystack = $remaining_haystack;
	return $result;
	} // findObjects
	
function getFileName ($name) {
	# given a file name with path, extract the name only
	$tmp = strrpos('/', $name);
	if ($tmp === false) { return $name; }
	return right($name, strlen($name) - ($tmp + 1));
	} // getFileName
	
function updateStats() 
{	
	# update site statistics
	$console = new cms;
	
	# update total hits
	$temp = intval($console->GetConfiguration('pagehits'));
	$console->UpdateConfiguration('pagehits', $temp + 1);
} // updateStats
	
function round_up($Number) {
    // Copyright Josh Acecool M http://www.acecoolco.com
    if ($Number > number_format($Number, "0")) {
        $Number = round($Number);
        $Number++;
    }
    else
    {
        $Number = number_format($Number, "0");
    }
    return $Number;
}

function markSiteAsUpdated() {
	global $_APP;
	
	# mark site as modified/updated
	if (update_value('config.wb', 'last_update=', 'last_update=' . date("Y-m-d") . ' at ' . date("H:i"))) {
		$_APP['last_update'] = date("Y-m-d") . ' at ' . date("H:i");
		return true;
	} else {
		return false;
		}
	
	return true;
	}

function update_active_users ($user) 
{
	/*	open active.wb (list of active users in the last hour)
		remove anyone listed as active more than 60 minutes ago
		check for this user in the list, if entry exists update, otherwise add new entry.
		write active.wb
		
		line (record) definition for active.wb:
			"user.#|YY/mm/dd/HH/mm/ss|Full Name|pseudo|permissions\r\n" ( 5 fields total )
	*/
	
	#echo '<BR />LIBRARY: /resources/shared/lib_common.php<BR /><BR /><STRONG>WARNING:</STRONG> Func. update_active_users needs to be updated!!<br />';
	#exit;
	
	if ($user === false) { return false; }
	if ($user == '') { return false; }
	
	$filedata = rdumpfile('active.wb');
	
	$t1 = explode('/', date("Y/m/d/H/i/s"));	# current date & time
	
	for ($i = 0; $i < count($filedata); $i++) 
	{
		$data = explode('|', $filedata[$i]);	# get record into data array
		$t2 = explode('/', $data[1]);			# record's date & time
		/*	condition:
				if yymmdd == yymmdd ::
						if hh == hh :: GO
						else convert hours & minutes to minutes
							if mm1 - mm2 < 60 :: GO
		*/
		$d1 = $t1[0] . $t1[1] . $t1[2];			# current date in YYmmdd
		$d2 = $t2[0] . $t2[1] . $t2[2];			# record's date in YYmmdd
		if ($d1 == $d2) 
		{
			if ($t1[3] == $t2[3]) { /* GO */ }
			else 
			{ 
				$d1 = ($t1[3] * 60) + $t1[4];	# current time in minutes
				$d2 = ($t2[3] * 60) + $t2[4];	# record's time in minutes
				if (($d1 - $d2) < 60) { /* GO */ } else { $filedata[$i] = ""; }
			} // else :: line 130
		} else { $filedata[$i] = ""; }
	} // for
		
	// clear empty lines from the array
	$data = remove_empty_lines($filedata);
	
	$f = false;
	$pseudo = $_SESSION['pseudo'];
	
	// check the array for the current user and update if there, else append
	for ($i = 0; $i < count($data); $i++) 
	{
		$tmp = explode('|', $data[$i]);		# get record into tmp array
		if ($tmp[0] == $user) 
		{
			$tmp[1] = date("Y/m/d/H/i/s");
			if ($pseudo == true) { $tmp[3] = "1"; } else { $tmp[3] = "0"; }
			$data[$i] = implode('|', $tmp);
			$f = true;
		}
	}
		
	// set pseudo login value to add
	if ($pseudo == true) { $ps = "|1"; } else { $ps = "|0"; }
	
	# rescope session permissions
	include(QUERY_PATH . 'qry_get_session_permissions.php');
		
	// if we did not find a record, append
	if ($f == false) { $data[count($data)] = $user . '|' . date("Y/m/d/H/i/s") . '|' . $_SESSION['fullname'] . $ps . '|' . implode('', $a); }
	
	// update the file
	write_new_file('active.wb', $data);
	
	return true;
} // update_active_users

function escapeHTML (&$string)
/* remove xml/xhtml offensive characters
 */
{
	$string = str_replace('<', '&lt;', $string);
	$string = str_replace('>', '&gt;', $string);
	$string = str_replace(chr(150), '-', $string);
	$string = str_replace(chr(146), '\'', $string);
	$string = str_replace('&', '&amp;', $string);
	//$string = str_replace(chr(13) . chr(10), '&#xD;', $string);
}
	
?>