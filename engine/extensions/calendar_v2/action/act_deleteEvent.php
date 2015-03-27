<?php
 /*	Calendar Class
 	*
	*	Permanently remove an event from the calendar
	*	- either delete the specified event OR
	*	- return an error
	*
	*	Version 1.0 : 2006.08.27
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# ensure we have a valid event id
	if ( (! isset($_REQUEST['id'])) || ($_REQUEST['id'] === '') ) {
		echo 'INVALID POST DATA';
		exit;
	}
	
	# execute delete command and output an appropriate response
	if ($t->calendar->delete_event($e, $_REQUEST['id'])) {
		echo $t->calendar->outputMessage('Successfully deleted event!');
	} else {
		echo $t->calendar->outputMessage('Error deleting the event!');
	}
	
	# Get date
	$temp = explode('/', $_REQUEST['date']);
	
	echo $t->calendar->outputDay($temp[0], $temp[1], $temp[2]);
	
?>