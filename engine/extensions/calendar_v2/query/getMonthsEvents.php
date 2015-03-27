<?php
 /*	Calendar Class
 	*
	*	Retrieve all events for specified month
	*
	*	Version 1.0 : 2006.05.01
	*	William Strucke [wstrucke@gmail.com]
	* ----------------------------------------
	*	Use:
	*		Pass month to look up as $query[0]
	*		Pass year to look up as $query[1]
	*		Returns $query as data from file
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
		$tmp_month = trim($query[1]) . trim($query[0]);
		
		# Need to use queryFull until complex joins are better defined using new XML query format (in development)
		$query = $this->_tx->db->queryFull("SELECT calendar_events.*, calendar_locations.name 'location',
				calendar_locations.address, calendar_locations.city, calendar_locations.state, calendar_locations.zip,
				calendar_locations.map_url, calendar_locations.phone, calendar_groups.name 'group',
				calendar_groups.description group_description, calendar_groups.url group_url FROM
				calendar_events LEFT JOIN calendar_locations ON calendar_events.location_id = calendar_locations.id
				LEFT JOIN calendar_groups ON calendar_events.group_id = calendar_groups.id WHERE
				calendar_events.month = '$tmp_month'");
		
	} // if (data check - line 15)
	
	if (! is_array($query)) $query = array();
	
?>