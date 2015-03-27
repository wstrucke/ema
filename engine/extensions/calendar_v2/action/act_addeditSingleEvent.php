<?php
 /*	Calendar Class
 	*
	*	Process a posted add event form.
	*	- either add the requested event OR
	*	- return an error
	*
	*	Version 1.0 : 2006.05.22
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# Get date
	$temp = explode('/', $_REQUEST['date']);
	
	# Validate date
	if ((intval($temp[0]) < 1) || (intval($temp[0]) > 12) ||
			(intval($temp[1]) < 1) || (intval($temp[1]) > 31) ||
			(intval($temp[2]) < 2005) || (intval($temp[2]) > 2050)
			)
	{
		echo 'INVALID DATE';
		exit;
	}

	# set event day from parsed date
	$event['day'] = $temp[1];
	
	# Get caption
	$temp = $_REQUEST['caption'];
	
	# Check added html values
	if (isset($_REQUEST['captionAttributes'])) {
		foreach($_REQUEST['captionAttributes'] as $val) { $temp = "<$val>$temp</$val>"; }
	}
	
	# set event caption
	$event['caption'] = $temp;
	
	# Check Time setting
	if (isset($_REQUEST['sTimeAllDay'])) {
		# All Day Event
		$event['sTime'] = '00:00';
		$event['eTime'] = '23:59';
	} else {
		# normal/limited time event
		$event['sTime'] = (string)$_REQUEST['sTime'];
		$event['eTime'] = (string)$_REQUEST['eTime'];
	}
	
	# finish adding data - the below variables require no checks/modification
	$event['link'] = (string)$_REQUEST['link'];
	$event['desc'] = (string)$_REQUEST['desc'];
	$event['group'] = (string)$_REQUEST['group'];
	$event['location'] = (string)$_REQUEST['location'];
	
	# check if this is an existing event or a new event
	if ( (isset($_REQUEST['id'])) && ($_REQUEST['id'] != '') ) {
		# extract the id
		
		# initial syntax is 'X.mm,dd,yyyy'
		$id = explode('.', $_REQUEST['id'], 2);
		$id = intval($id[0]);
	} else {
		# set to nothing
		$id = '';
	}
	
	if ($t->calendar->add_update_event($event, $_REQUEST['date'], $id)) {
		$success = true;
	} else {
		$success = false;
	}
	
?>