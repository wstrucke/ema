<?php
 /*************************************************************************
 * edit an existing news item																							|
 * =======================================================================|
 * William Strucke, 2005.06.30																						|
 * -----------------------------------------------------------------------|
 *																																				|
 *************************************************************************/
		
	# determine the month and year we should be using, adjust if necessary
	if (isset($_REQUEST['month'])) { 
		$month = trim($_REQUEST['month']);
	} else {
		$month = date("m");
		}
		
	if (isset($_REQUEST['m'])) { 
		$month = trim($_REQUEST['m']);
	}
		
	if (isset($_REQUEST['year'])) { 
		$year = trim($_REQUEST['year']);
	} else {
		$year = date("Y");
		}
	
	if (isset($_REQUEST['y'])) { 
		$year = trim($_REQUEST['y']);
	}
		
	# ensure month has trailing zeros if necessary
	if ($month < 10) { $month = "0" . strval(intval($month)); }
	
	if ((isset($_REQUEST['date'])) && ($_REQUEST['submit'] == 'Submit')) {
		# form posted to edit, modify item
		
		# get the id
		$id = $_REQUEST['id'];
		
		# if the month and/or year has changed for this event, remove the old copy and add a new one
		$temp = explode("/", $_REQUEST['date']);
		$month = $temp[0];
		$day = $temp[1];
		$year = $temp[2];
		# update the record
		$result = $news->UpdateItem($_REQUEST['id'], $month, $day, $year, $_REQUEST['description'], $_REQUEST['view'], $_REQUEST['edit']);
		
		if ($result) { 
			$message = "Successfully updated event.";
		} else {
			$message = "Could not update event!";
			}
		}
		
	if (isset($_REQUEST['submit']) && ($_REQUEST['submit'] == 'Delete')) {
		if ($news->DeleteItem($_REQUEST['id'])) {
			$message = 'Successfully deleted event.';
		} else {
			$message = 'Error deleting event! <a href="mailto:' . $_APP['contact_email'] . '?subject=a015%20s-2%20delete%20Error">Report This</a>';
			} // news->DeleteItem
		}
	
	if (isset($_REQUEST['id']) && !isset($_REQUEST['date']) && ($news->AllowEdit(implode('', $a), $_REQUEST['id']))) {
		# we have a record but the form has not been posted
		include ('dsp_edit_editor.php');
	} elseif (isset($_REQUEST['return'])) { 
		# return to day view
		$day = $_REQUEST['d'];
		$month = $_REQUEST['m'];
		$year = $_REQUEST['y'];
		include ('dsp_day_body.php');
	} else {
		# display record selector
		include ('dsp_edit_selector.php');
		} // if isset request['id']
	?>