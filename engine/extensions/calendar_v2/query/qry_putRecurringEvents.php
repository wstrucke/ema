<?php
 /*	Calendar Class
 	*
	*	Write all events for recurring data
	*
	*	Version 1.0 : 2006.05.25
	*	William Strucke [wstrucke@gmail.com]
	* ----------------------------------------
	*	Use:
	*		Pass file name as $query[0]
	*		Pass current data as $query[1]
	*		Returns $query as data result
	*		*MUST* be called within the local 
	*			scope of the calendar.class
	*/
	
	# ensure query is set
	if (! isset($query)) { $query = ''; }
	
	# clear our temporary variable if necessary
	if (isset($qryTemp)) { unset($qryTemp); }
	
	# check required variables
	if ( (! is_array($query)) || (strlen($query[0]) == 0) )
	{
		# invalid data passed to this query - return false
		$query = false;
	} else {
		# data check passed - continue
	
		# set filename
		$qryTemp = $query[0];
		
		# write the file data
		#$query = sWriteFile($qryTemp, $query[1], true, $this->dataPath);
		
	} // if (data check - line 15)
	
?>