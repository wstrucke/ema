<?php
 /*	Calendar Class
 	*
	*	Write all events for specified month
	*
	*	Version 1.0 : 2006.05.01
	*	William Strucke [wstrucke@gmail.com]
	* ----------------------------------------
	*	Use:
	*		Pass month to write as $query[0]
	*		Pass year to write as $query[1]
	*		Pass current data as $query[2]
	*		Returns $query as data result
	*		*MUST* be called within the local 
	*			scope of the calendar.class
	*/
	
	# ensure query is set
	if (! isset($query)) { $query = ''; }
	
	# clear our temporary variable if necessary
	if (isset($qryTemp)) { unset($qryTemp); }
	
	# check required variables
	if ( (! is_array($query)) || (intval($query[0]) == 0) || (intval($query[1]) == 0) )
	{
		# invalid data passed to this query - return false
		$query = false;
	} else {
		# data check passed - continue
	
		# set filename
		$qryTemp = trim($query[1]) . trim($query[0]) . '.wb2';
		
		# write the file data
		$query = sWriteFile($qryTemp, $query[2], true, $this->dataPath);
		
	} // if (data check - line 15)
	
?>