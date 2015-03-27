<?php
/**
 *	TBDBITL Secure Area :: /_functions/file_idx_interface.php
 *	---------------------------------------------------------------------------------------------------------
 *	 SUMMARY:	centralized functions providing access and modifications to the uploaded files index (files.wb)
 *	  AUTHOR:	William Strucke, 2005.04.14
 *	REQUIRES:	fileoperations.php in scope; any security checks require $a (access array) to be set
 *	 RETURNS:	various - > see individual functions
 *	SECURITY:	00000000 (PUBLIC)
 *	---------------------------------------------------------------------------------------------------------
 */
 
 # given a file index and access type return true if the logged in user 
 # 	(or public user) is authorized for that access
 	function file_Authorized($idx, $modify = false) {
		/**
		 *	$idx is the file index
		 *	optional $modify, true -> return permissions to update/modify file, false -> return read permissions
		 */
		
		include(QUERY_PATH . 'qry_get_session_data.php');
		 
	} // file_Authorized
	
 # given users permission array, return authorized file index (with optional file type filter, start, end points)
	function retrieve_index($permissions, $filetype = 'all', $start = 0, $end = 0, $showall = false) {
		/**
		 *	$permissions is an 8 bit array with users access permissions (i.e. [0]->1,[1]->0,etc)
		 *	optional $start point and $end point
		 *	optional $filetype with filetype filter value
		 *	do not return files marked as hidden (field 12)
		 */
		
		# retrieve base file
		$data = rdumpfile('files.wb');
		
		# check and/or set end point
		if ($end == 0) { $end = count($data); }
		if ($start > $end) { /* error */ return false; }
		if ($start >= count($data)) { /* ensure start isn't past file count */ $start = count($data) - 1; }
		if ($end > count($data)) { /* ensure end isn't past file count */ $end = count($data); }
		
		# initialize return array
		if (isset($return_array)) { unset($return_array); }
		
		# build file list
		for ($ci = $start; $ci < $end; $ci++) {
			$myData = explode('|', $data[$ci]);
			
			# check security on this item
			$qry_filter1 = implode('', parseaccesslevel($myData[1]));
			$qry_filter2 = implode('', $permissions);
			include (QUERY_PATH . 'qry_check_authorized.php');
			
			if ((($qry_result) && (($myData[12] == '0') || ($showall == true))) || ($permissions[7] == 1)) { 
				# Access Granted
				if ($filetype == 'all') {
					$return_array[count($return_array)] = $data[$ci];
				} else {
					if ($myData[4] == $filetype) { $return_array[count($return_array)] = $data[$ci]; }
					} // if filetype == all
				} // if qry_result
			} // for
			
		return $return_array;
	} // retrieve_index
	
 # given a file index, remove it from the file index and the server (full delete)
 	function delete_file($idx) {
		/**
		 *	$idx is the file index
		 */
		 
		global $fileidx;
		 
		include(QUERY_PATH . 'qry_get_session_data.php');
		  
		# get the line in the file index for this file
		$temp = retrieve_line('files.wb', $fileidx[index], $idx);
		
		# check error condition
		if ($temp == -1) { return false; }
		
		# remove said line from the index
		if (! remove_line('files.wb', implode('|', $temp))) { return false;	}
		
		# set $file to the complete relative path for the file to be deleted
		$file = $temp[$fileidx[path]] . $temp[$fileidx[name]];
		
		# delete the file
		if (fileop_delete_file($file)) { return true; } else { return false; }
		
	} // delete_file

 
 # given a file index return an array with all information about that file supplied
 	function load_FileDetails($idx) {
		/**
		 *	$idx is the file index
		 */
		 
		include(QUERY_PATH . 'qry_get_session_data.php');
		
	} // getFileDetails
 
 # given a file index and an array of information either append or update the index
	function save_FileDetails($idx, $data) {
		/**
		 *	$idx is the file index
		 *	$data is a structure containing the file data (keys matching what's returned in get_FileDetails above)
		 */
		
		include(QUERY_PATH . 'qry_get_session_data.php');
		 
	} // save_FileDetails
	
 # given a file index and an integer value greater than zero, return specified # of most recently updated files
	function extract_lastUpdated($idx, $number) {
		/**
		 *	$idx is the file index
		 *	$number is the number of last updated files requested to return
		 */
		# ensure we have valid input
		if ((count($idx) == 0) || ($number <= 0)) { return false; }
		# initialize temp array
		if (isset($tmp_array)) { unset($tmp_array); }
		# prepare array to sort
		for ($i = 0; $i < count($idx); $i++) {
			$myData = explode('|', $idx[$i]);
			$date = explode('/', $myData[8]);
			$time = str_replace(':', '', $myData[9]);
			$tmp_array[$i] = intval($date[2]) . $date[0] . $date[1] . $time . '|' . $idx[$i];
			} // for
		# sort the index by date/time last updated in descending order
		rsort($tmp_array);
		if ($number > count($tmp_array)) { $number = count($tmp_array); }
		# clear $idx
		unset($idx);
		# remove timestamp from front of array and return remainder
		for ($i = 0; $i < $number; $i++) {
			$myData = explode('|', $tmp_array[$i]);
			$tmp = array_shift($myData);
			$idx[$i] = implode('|', $myData);
			} // for
		return $idx;
	} // extract_lastUpdated
	
 # map type values to array (if you modify this, also modify values in upload.php)
	$type['database'] = "Database";
	$type['audio_practice'] = "Practice Recordings";
	$type['audio_other'] = "Other Audio";
	$type['image'] = "Image";
	$type['video'] = "Video Clip";
	$type['music_pdf'] = "Music (PDF)";
	$type['music_jpeg'] = "Music (JPEG)";
	$type['chart_pdf'] = "Drill Chart";
	$type['document'] = "Document";
	$type['program'] = "Program";
	$type['archive'] = "Archive";
	$type['other'] = "Other File Type";
	
 # map icon files to array
	$icon['database'] = "/images/icons/database.gif";
	$icon['audio_practice'] = "/images/icons/practice_recording.gif";
	$icon['audio_other'] = "/images/icons/other_audio.gif";
	$icon['image'] = "/images/icons/picture.gif";
	$icon['video'] = "/images/icons/video.gif";
	$icon['music_pdf'] = "/images/icons/sheet_music_pdf.gif";
	$icon['music_jpeg'] = "/images/icons/sheet_music_jpeg.gif";
	$icon['chart_pdf'] = "/images/icons/drillchart.gif";
	$icon['document'] = "/images/icons/document.gif";
	$icon['program'] = "/images/icons/program.gif";
	$icon['archive'] = "/images/icons/archive.gif";
	$icon['other'] = "/images/icons/other.gif";
  
 ?>