<?php
 /*************************************************************************
 * Add a news item																												|
 * =======================================================================|
 * William Strucke, 2005.06.30																						|
 * -----------------------------------------------------------------------|
 *																																				|
 *************************************************************************/
	global $message;
	
	# change to specified date if necessary
	if (isset($_GET['d'])) { $day = $_GET['d']; }
	if (isset($_GET['m'])) { $month = $_GET['m']; }
	if (isset($_GET['y'])) { $year = $_GET['y']; }
	
	if (isset($_POST['date'])) {
		# form posted
		
		# get date
		$temp = explode("/", $_POST['date']);
		$month = $temp[0];
		$day = $temp[1];
		$year = $temp[2];
		
		# add event!
		$result = $news->AddItem($month, $day, $year, $_POST['description'], $_POST['view'], $_POST['edit']);
		if ($result) { 
			$message = "Successfully added event.";
		} else {
			$message = "Could not add event!";
			}
		}
	
	if (isset($_POST['return'])) { 
		# return to day view
		$day = $_REQUEST['d'];
		$month = $_REQUEST['m'];
		$year = $_REQUEST['y'];
		include ('dsp_day_body.php');
	} else {
		# display add form
			$news->OutputAddForm();
		} // if isset
	?>