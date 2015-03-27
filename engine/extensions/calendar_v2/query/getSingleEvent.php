<?php
 /*	Calendar Class
 	*
	*	Retrieve all events for specified month
	*
	*	Version 1.0 : Sep-02-2010
	*	William Strucke [wstrucke@gmail.com]
	* ----------------------------------------
	*	Use:
	*		Pass event id as $query
	*		Returns $query as data from database
	*		*MUST* be called within the local 
	*			scope of the calendar.class
	*/
	
	# ensure query is set
	if (! isset($query)) { $query = ''; }
	
	# check required variables
	if ( (strlen($query) == 0) || (intval($query) == 0) ) {
		# invalid data passed to this query - return false
		$query = false;
	} else {
		# Need to use queryFull until complex joins are better defined using new XML query format (in development)
		$query = $this->_tx->db->queryFull("SELECT calendar_events.*, calendar_locations.name 'location',
				calendar_locations.address, calendar_locations.city, calendar_locations.state, calendar_locations.zip,
				calendar_locations.map_url, calendar_locations.phone, calendar_groups.name 'group',
				calendar_groups.description group_description, calendar_groups.url group_url FROM
				calendar_events LEFT JOIN calendar_locations ON calendar_events.location_id = calendar_locations.id
				LEFT JOIN calendar_groups ON calendar_events.group_id = calendar_groups.id WHERE
				calendar_events.id = $query");
	} // if (data check - line 15)
	
?>