<?php
	/*	Standard File I/O Operations
	
		read/write/write at location, etc
		2004/11/24 ws
		
		Function Definitions:
		
			function dumpfile($filename) {
			
					# get entire contents of file and put into array by line (with line seperator '\r\n')
					
			function dumpfile_with_seperator($filename, $lineseperator) {
			
					# get entire contents of file and put into array by line (with any line seperator)
					
			function retrieve_file($filename, $path = '/php-data/') {
			
					# returns an entire file into a single string
					
			function retrieve_line($file, $field_number, $criteria, $forceLowercase = false) {
			
					# returns data on success and -1 on failure
					
			function find_line_from_dump($searchvar, $field_number, $criteria) {
			
					# search array of bar ("|") seperated lines, $searchvar for $criteria in field number $field_number
					# returns data (array) on success and -1 on failure			
					
			function find_var_from_dump($searchvar, $source_field_number, $criteria, $return_field_number) {
			
					# essentially find_line_from_dump only returns a single variable from 
					# $return_field_number instead of a line
					# returns data on success and -1 on failure			
					
			function append($filename, $data) {
			
					# add $data to the end of $filename
					# returns result of fwrite on action			
					
			function update_value($filename, $index, $newvalue) {
			
					# change a value in a file
					# find $index (any text) in $filename and replace entire line with $newvalue
					# returns true on success false on failure			
					
			function update_line_number($filename, $line_number, $newvalue) {
			
					# change a line in a file
					# replace $line_number in $filename with $newvalue
					# always returns true (change this?!)			
					
			function clear_line($filename, $line) {
			
					# clear the value of a line, but do not remove it
					# always returns true (change this!?)			
					
			function read_line_number($filename, $line) {
			
					# get a single line from specified file based on line number
					
			function remove_line($filename, $unique_text) {
			
					# given unique_text (something we can use to find which line is to be deleted),
					# delete a line from a file
					
			write_new_file($filename, $newdata, $commit = true, $fullpath = '/usr/local/webs/tbdbitl/php-data/')
			
					# overwrite $filename with $newdata array adding \r\n to end of each element
					# optional commit = false -> only update cache of the file
					# optional full path allows us to access other file paths
					
			sWriteFile($filename, $contents, $commit = true, $path = ROOT_PATH)
			
					# overwrite filename with $contents, serialized
					# optional commit = false -> only update cache of the file
					# optional full path specifies a non-standard path
					
			function array_search_bit($search, $array_in) {
			
					# this function came from a forum, i did not write it (ws)
					# search $array_in array for $search text
					# returns array index or -1			
					
			function fileop_delete_file($filename) {
			
					# delete $filename
					
			function mu_sort ($array, $key_sort) {
			
					# i did not write this function, found online (ws)
					# sort $array by key $key_sort
					
			function retrieve_var ($file, $check_field, $criteria, $return_field) {
			
					# get a single variable from $file
					# searches field $check_field for $criteria and returns $return_field
					# similiar to find_var_from_dump except this actually opens a file for
					# data rather than using a pre-existing array
					
			function change_var ($filename, $check_field, $criteria, $change_field, $newvalue) {
			
					# change a single variable on specified line in specified file
					
			function remove_empty_lines($tmpdata) {
			
					# remove any lines with null data values (whitespace) from array and return the array
					
			function update_line($file, $field_number, $criteria, $newline) {
			
					# update a line in a file based on search criteria in specified field number
					# this function overwrites the ENTIRE line and adds /r/n to the end
					
			function filemodified ($name) {
			
					# returns date when specified file was last modified
					
			function getd () {
			
					# proprietary osumb site function to populate array $d with static mapping info for show files
					
			function clear_line_from_dump($array, $line) {
			
					# given an array and a complete line in said array, locate the line and set the value to null
					# you may want to run remove_empty_lines on the array afterwards
					# returns array with data in $line removed
					
			function qsort_multiarray($array,$num = 0,$order = "ASC",$left = 0,$right = -1) {
			
					# given an array, an integer field number, and an order sort by specified field number
					# returns sorted array
					
			function rdumpfile($name) {
			
				# returns result of remove_empty_lines(dumpfile($name)) call
	*/
	
	if ($_SERVER['SERVER_SOFTWARE'] == 'Apache/1.3.33 (Darwin) PHP/5.2.0 DAV/1.0.3 mod_ssl/2.8.24 OpenSSL/0.9.7l') 
		{
			define('NL', "\n");
		} else {
			define('NL', "\r\n");
		}
	
	function dumpfile($filename) 
	{
		#get entire contents of file and put into array by line (with line seperator '\r\n')
		return dumpfile_with_seperator($filename, NL);
	} // dumpfile
	
	function dumpfile_with_seperator($filename, $lineseperator) 
	{
		# get entire contents of file and put into array by line (with any line seperator)
		global $cache;

		$filename = CONFIG_PATH . $filename;

		# cache files to avoid opening the same file twice in one page load
		if (isset($cache[$filename]) && 1==2) 
		{
			# file is already cached, use that
			return $cache[$filename];
		} else {
			# file has not yet been opened, access and cache it
		
			#$tmp = "Opening $filename at " . date("H:i:s") . "\r\n";
			#append("debug.txt", $tmp);
			
			# only continue if the file exists
			if (file_exists($filename)) {		
				/* found the following method on the internet (ws) */
				ob_start();        					// start output buffering 
				include ($filename);    		// all output goes to buffer 
				$buf = ob_get_contents();   // assign buffer to a variable 
				ob_end_clean();        			// clear buffer and turn off output buffering
				/* (end internet method)                           */
				
				$i = explode($lineseperator, $buf, 5000);		// 5000 represents our maximum number of lines in a given file
				
				# clear array null values and ensure array is valid
				for ($j = 0; $j < 100; $j++) { 
					if ($i[$j] == '') { $i[$j] = ""; }
					}
			} else {
				echo "ERROR: File ($filename) Does Not Exist!<br />";
				# file does not exists, return null
				$i = '';
				} // if file_exists
			
			# cache file
			$cache[$filename] = $i;
			
			return $i;	// 5000 max lines
			} // if isset cache
	} // dumpfile_with_seperator
	
	function retrieve_file($filename, $path = 'configuration/') 
	{
		# returns an entire file into a single string
		$filename = RESOURCE_PATH . $path . $filename;
		
		# only continue if the file exists
		if (file_exists($filename)) 
		{		
			$buf = str_replace("\t", "  ", file_get_contents($filename));	
		} else {
			$buf = '<strong>Error:</strong> Requested file does not exist!';
		} // if file exists
		
		return $buf;
	} // retrieve_file
	
	function retrieve_line($file, $field_number, $criteria, $forceLowercase = false) 
	{
		$GetLine = dumpfile($file);
		$n = 0;
		while (count($GetLine[0]) > 0) 
		{	
			if ($n == 5000) { return -2; }  // protect against endless loop
			$data = explode('|', trim($GetLine[0]));
			if ($forceLowercase) 
			{
				# force lowercase check
				if (strtolower($data[$field_number]) == strtolower($criteria)) { return $data; }
			} else {
				if ($data[$field_number] == $criteria) { return $data; }
			}
			array_shift($GetLine);
			$n++;
		}
		return -1;
	} // retrieve_line
	
	function find_line_from_dump($searchvar, $field_number, $criteria) 
	{
		$GetLine = $searchvar;
		while ((is_array($GetLine)) && (intval(count($GetLine[0])) > 0)) 
		{	
			if ($n == 5000) { return -1; }  // protect against endless loop
			$data = explode('|', trim($GetLine[0]));
			if ($data[$field_number] == $criteria) { return $data; }
			array_shift($GetLine);
			$n++;
		}
		return -1;
	} //find_line_from_dump
	
	function find_var_from_dump($searchvar, $source_field_number, $criteria, $return_field_number) 
	{
		#$fields = intval($return_field_number) + 1; 			// just in case
		$GetLine = $searchvar;
		while (count($GetLine[0]) > 0) 
		{	
			if ($n == 5000) { return -1; }  // protect against loop
			$data = explode("|", trim($GetLine[0]));#, $fields);
			if ($data[$source_field_number] == $criteria) { return $data[$return_field_number]; }
			array_shift($GetLine);
			$n++;
		}
		return -1;
	} // find_var_from_dump
	
	function append($filename, $data) 
	{
		# add $data to the end of $filename
		global $cache;
		
		if (isset($cache[CONFIG_PATH . $filename])) {
			unset ($cache[CONFIG_PATH . $filename]);
			}
		
		$temp = fopen(CONFIG_PATH . $filename, "a");
		$result = fwrite($temp, $data);
		fclose($temp);
		return $result;
	} // append
	
	function update_value($filename, $index, $newvalue) 
	{
		/*
			change a value in a file
			
			read the entire file into a variable,
			replace the value at a specific point then
			overwrite/output the entire file.
		*/
		
		$i = rdumpfile($filename);
		$n = array_search_bit($index, $i);
	
		if ($n != -1) 
		{
			$i[$n] = $newvalue;
			write_new_file($filename, $i);
			return true;					# success
		} else { 
			return false; 
		}
	} // update_value
	
	function update_line_number($filename, $line_number, $newvalue) 
	{
		/*
			change a line in a file
			
			read the entire file into a variable,
			replace $line_number in the array
			overwrite/output the entire file.
		*/
		
		$i = dumpfile($filename);

		# clear array null values
		for ($j = 0; $j < 100; $j++) { 
			if ($i[$j] == '') { $i[$j] = ""; }
			}
		# update requested line
		$i[$line_number] = $newvalue;
		
		# debug
		/*
		for ($j = 0; $j < 100; $j++) { 
			echo "$line_number -- $j : $i[$j]<br />"; 
			}
		*/
		write_new_file($filename, $i);
		return true;					# success
	} // update_line_number
	
	function clear_line($filename, $line) 
	{
		// clear the value of a line, but do not remove it
		update_line_number($filename, $line, "");
		return true;
	} // clear_line
	
	function read_line_number($filename, $line) 
	{
		// get a single line from specified file based on line number
		$data = dumpfile($filename);
		return $data[$line];
	} // read_line_number
	
	function remove_line($filename, $unique_text) 
	{
		/*
			given unique_text (something we can use to find which line is to be deleted),
			delete a line from a file.		
		*/

		$i = dumpfile($filename);
		$n = array_search_bit($unique_text, $i);
		
		if ($n != -1) 
		{
			$a = array_slice($i, 0, $n); 
			$b = array_slice($i, $n + 1); 
			$i = array_merge($a, $b); 
			write_new_file($filename, $i);
			return true;
		} else { 
			#echo "<p>remove_line found nothing!</p>"; 
			return false;
		}
	} // remove_line
	
	function write_new_file($filename, $newdata, $commit = true, $fullpath = '') 
	{
		# 2005.07.12 if commit is false, only update cache
		# 2005.08.13 added full path variable
		
		if ($fullpath == '') { $fullpath = CONFIG_PATH; }
		
		if (! $commit) 
		{
			# update cache only
			$cache[$fullpath . $filename] = $newdata;
			return true;
		} else {
			global $cache;
			
			if (isset($cache[$fullpath . $filename])) 
			{
				unset ($cache[$fullpath . $filename]);
			}
				
			if (strcasecmp(substr($filename, 5), "shows") != 0)
			{ 	
				// do not remove empty lines from shows
				$newdata = remove_empty_lines($newdata);
			}
			
			if (count($newdata) == 0) 
			{
				$temp = "";
			} else {
				$temp = implode(NL, $newdata) . NL;
			}
			
			$file = fopen($fullpath . $filename, 'w');
			$return_value = fwrite($file, $temp);
			fclose($file);
			return $return_value;
		} // if not commit
	} // write_new_file
	
	function array_search_bit($search, $array_in) 
	{
		#this function came from a forum, i did not write it
		foreach ($array_in as $key => $value) 
		{
			if (strpos($value, $search) !== FALSE) return $key;
		}
		return -1;
	} // array_search_bit
	
	function fileop_delete_file($filename) 
	{
		# delete a file
		
		global $cache;
		
		if (isset($cache[RESOURCE_PATH . $filename])) 
		{
			unset ($cache[RESOURCE_PATH . $filename]);
		}
		
		if (file_exists(RESOURCE_PATH . $filename)) 
		{
			unlink(RESOURCE_PATH . $filename);
			return true;
		} else {
			return false;
		}
	} //fileop_delete_file
	
	function mu_sort ($array, $key_sort) 
	{ // start function
		#this is from php.net
	  $key_sorta = explode(",", $key_sort); 
	
	  $keys = array_keys($array[0]);
	
		// sets the $key_sort vars to the first
		for($m=0; $m < count($key_sorta); $m++){ $nkeys[$m] = trim($key_sorta[$m]); }
		
		$n += count($key_sorta);    // counter used inside loop
		
		// this loop is used for gathering the rest of the 
		// key's up and putting them into the $nkeys array
		for($i=0; $i < count($keys); $i++){ // start loop
		
		 // quick check to see if key is already used.
		 if(!in_array($keys[$i], $key_sorta)){
		
			 // set the key into $nkeys array
			 $nkeys[$n] = $keys[$i];
		
			 // add 1 to the internal counter
			 $n += "1"; 
		
			 } // end if check
		
		} // end loop
	
		// this loop is used to group the first array [$array]
		// into it's usual clumps
		for($u=0;$u<count($array); $u++){ // start loop #1
		
		 // set array into var, for easier access.
		 $arr = $array[$u];
		
			 // this loop is used for setting all the new keys 
			 // and values into the new order
			 for($s=0; $s<count($nkeys); $s++){
		
				 // set key from $nkeys into $k to be passed into multidimensional array
				 $k = $nkeys[$s];
		
				 // sets up new multidimensional array with new key ordering
				 $output[$u][$k] = $array[$u][$k]; 
		
			 } // end loop #2
		
		} // end loop #1
		
		// sort
		sort($output);
		
		// return sorted array
		return $output;
	} // end function
	
	function retrieve_var ($file, $check_field, $criteria, $return_field) 
	{
		$temp = retrieve_line($file, $check_field, $criteria);
		if (is_array($temp)) { return $temp[$return_field]; }
		else { return -1; }
	} // retrieve_var
	
	function change_var ($filename, $check_field, $criteria, $change_field, $newvalue) 
	{
		# change a single variable on specified line in specified file
		#update_value($filename, $index, $newvalue)	
		$n = 0;
		$GetLine = dumpfile($filename);
		while (count($GetLine[0]) > 0) 
		{	
			if ($n == 2000) { return -1; }  // protect against loop
			$data = explode('|', trim($GetLine[0]));
			if (strcmp($data[$check_field], $criteria) == 0) 
			{ 
				#found value
				if (right($data[$change_field], 1) == ";") { $data[$change_field] = $newvalue . ";"; }
				else { $data[$change_field] = $newvalue; }
			}
			$newline[$n] = implode('|', $data);
			array_shift($GetLine);
			$n++;
		}	
		write_new_file($filename, $newline);
	} // change_var
	
	function remove_empty_lines($tmpdata) 
	{
		// remove any lines with null data values (whitespace) from array and return
		for ($ci = 0;  $ci <= count($tmpdata); $ci++) 
		{
			if (strcmp($tmpdata[$ci],"") != 0) { $tmpdata2[count($tmpdata2)] = $tmpdata[$ci]; }
			/* another way to do this might be to if("") { unset($newdata[$ci]); } */
		}
		return $tmpdata2;
	} // remove_empty_lines
	
	function update_line($file, $field_number, $criteria, $newline, $commit = true) 
	{
		// update specified line number (by search) with new data and append /r/n to end of line
		# 2005.07.12 - added commit option to specify if file should be written or only cache updated
		$GetLine = rdumpfile($file);
		for ($n = 0; $n < count($GetLine); $n++) 
		{
			$data = explode('|', trim($GetLine[$n]));
			if ($data[$field_number] == $criteria) { /* update the field */ $GetLine[$n] = $newline; }
		}
		/* write the file back */
		return write_new_file($file, $GetLine, $commit);
	} // update_line
	
	function filemodified ($name) 
	{
		//$filename = "/usr/local/webs/tbdbitl/php-data/" . $name;
		$filename = RESOURCE_PATH . '/' . $name;
		if (file_exists($filename)) 
		{
			return date ("F d Y", filemtime($filename));
		}
	} // filemodified
	
	function getd () 
	{
		# descriptions for osumb show config files
		$d[0] = "Pregame Charts";
		$d[1] = "Halftime Charts";
		$d[2] = "Postgame Charts";
		$d[3] = "Baritone BC Music";
		$d[4] = "Baritone TC Music";
		$d[5] = "Bass Drum Music";
		$d[6] = "Cymbal Music";
		$d[7] = "Eb Coronet Music";
		$d[8] = "Eb Horn Music";
		$d[9] = "F Horn Music";
		$d[10] = "Flugel Music";
		$d[11] = "Snare Music";
		$d[12] = "Tenor Music";
		$d[13] = "Trombone 1 Music";
		$d[14] = "Trombone 2 Music";
		$d[15] = "Trumpet 1 Music";
		$d[16] = "Trumpet 2 Music";
		$d[17] = "Sousa Music";
		$d[18] = "Director's Score";
		return $d;
	} // getd
	
	function clear_line_from_dump($array, $line) 
	{
		# given an array and a complete line in said array, locate the line and set the value to null
		# you may want to run remove_empty_lines on the array afterwards
		# returns array with data in $line removed
		
		for ($counter = 0; $counter < count($array); $counter++) 
		{
			if ($array[$counter] == $line) { $array[$counter] = ""; }
		} // for
			
		return $array;
	} // clear_line_from_dump
	
	function qsort_multiarray(&$array,$num = 0,$order = "ASC",$left = 0,$right = -1)
	{
		# from CK1 at wwwtech dot de on http://www.php.net (listed under the sort function)
		if($right == -1)
		{ $right = count($array) - 1; }
		
		$links = $left;
		$rechts = $right;
		$mitte = $array[($left + $right) / 2][$num];
		
		if($rechts > $links)
		{
		do
		{
		if($order == "ASC")
		{
		while($array[$links][$num]<$mitte) $links++;
		while($array[$rechts][$num]>$mitte) $rechts--;
		}
		else
		{
		while($array[$links][$num]>$mitte) $links++;
		while($array[$rechts][$num]<$mitte) $rechts--;
		}
		
		if($links <= $rechts)
		{
		$tmp = $array[$links];
		$array[$links++] = $array[$rechts];
		$array[$rechts--] = $tmp;
		}
		
		} while($links <= $rechts);
		
		$array = qsort_multiarray($array,$num,$order,$left, $rechts);
		$array = qsort_multiarray($array,$num,$order,$links,$right);
		}
		
		
		
		return $array;
	} // qsort_multiarray
		
	function rdumpfile($name) 
	{
		# call dumpfile with remove empty lines
		return remove_empty_lines(dumpfile($name));
	}
		
	# Set static field mappings for file data
	
	$fileidx['index'] = 0;
	$fileidx['sec_read'] = 1;
	#		$fileidx[sec_write] = ;
	$fileidx['path'] = 2;
	$fileidx['name'] = 3;
	$fileidx['classification'] = 4;
	$fileidx['description'] = 5;
	$fileidx['show_id'] = 6;
	$fileidx['uploaded_by'] = 7;
	$fileidx['mod_date'] = 8;
	$fileidx['mod_time'] = 9;
	$fileidx['modified_by'] = 10;
	
	#	------------------------------------
	#	New SQL Compatible File Operations
	# ------------------------------------
/* these are currently declared in the calendar class - disable this section for now...	
	function sWriteFile($filename, $contents, $commit = true, $path = RESOURCE_PATH)
	/* write file in serialized data format 
	 * (to simulate a table for future transition to SQL)
	 *		$filename = name of file to write
	 *		$contents = data to write to the file (any data type is valid EXCEPT NULL)
	 *		$commit = optional boolean value indicating whether or not to write
	 *	 						to the file or to the cache (true = directly to file)
	 *		$path = optional value to specify a non-standard root path
	 *
	 * Version 1.0 - 2006.03.28 - William Strucke [strucke.1@osu.edu]
	 *
	{
		global $cache;
		
		if (! $commit)
		/* if $commit is false, only update the cache and exit the function *
		{ $cache[$path . $filename] = $contents; return true; }
			
		if (isset($cache[$path . $filename]))
		/* if a cache value is set for this file, clear it prior to updating *
		{	unset ($cache[$path . $filename]); }
		
		# open a handler to the specified file
		$file = fopen($path . $filename, 'w');
		
		# write the serialized data
		$return_value = fwrite($file, serialize($contents));
		
		# close the file
		fclose($file);
		
		# return the result of the write operation
		return $return_value;
	}
	
	function sGetFile($filename, &$data, $utilizeCache = true, $path = RESOURCE_PATH)
	/* open file $filename in serialized data format and return unserialized data
	 * (to simulate a table for future transition to SQL)
	 *		$filename = name of the file to read
	 *		$data = the variable the file contents will be extracted to
	 *		$utilizeCache = a boolean value indicating whether or not to use the
	 *				cache if it's set for this file (true -> yes)
	 *		$path = optional value to specify a non-standard root path
	 *
	 * Version 1.0 - 2006.03.28 - William Strucke [strucke.1@osu.edu]
	 *
	{
		global $cache;
		
		if ( $utilizeCache && (isset($cache[$path . $filename])) )
		/* if $utilizeCache is true and a cache value is set for this file,
		 * return the cache value
		 *
		{	return $cache[$path . $filename]; }
		
		if (! file_exists($path . $filename))
		/* if the file does not exist return empty string *
		{
			$data = '';
			return false;
		}
		
		# get the file data and unserialize it
		$data = unserialize(file_get_contents($path . $filename));
		
		# write the new cache value
		$cache[$path . $filename] = $data;
		
		# return success
		return true;
	}
	*/
?>