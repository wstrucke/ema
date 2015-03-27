<?php
 /*************************************************************************
 * Remove an existing news item																						|
 * =======================================================================|
 * William Strucke, 2005.06.30																						|
 * -----------------------------------------------------------------------|
 *																																				|
 *************************************************************************/
	global $a;
	
	# determine the month and year we should be using, adjust if necessary
	if (isset($_POST['month'])) { 
		$month = trim($_POST['month']);
	} else {
		$month = date("m");
		}
		
	if (isset($_GET['m'])) { 
		$month = trim($_GET['m']);
	}
		
	if (isset($_POST['year'])) { 
		$year = trim($_POST['year']);
	} else {
		$year = date("Y");
		}
	
	if (isset($_GET['y'])) { 
		$year = trim($_GET['y']);
	}
		
	# ensure month has trailing zeros if necessary
	if ($month < 10) { $month = "0" . strval(intval($month)); }
	
	if (isset($_REQUEST['id'])) {
		# form posted, delete event!
		
		# get the id
		$id = $_REQUEST['id'];
		
		# retrieve the news item
		$item = $news->GetItem($id);
				
		# check security
		$qry_filter1 = $item[4];
		$qry_filter2 = implode('', $a);
		include (ROOT_PATH . '/security/qry_IsAuthorized.php');
		if ($qry_result) {
			# Edit Access Granted: Delete!			
			$result = $news->DeleteItem($id);
			
			if ($result) { 
				$message = 'Successfully deleted event record.';
			} else {
				$message = 'Could not delete event!';
				}
		} else {
			# Access Denied
			$message = 'Access Denied';
			} // if qry_result
		} // if isset request id
	
	if (isset($_GET['return'])) { 
		$day = $_GET['d'];
		$month = $_GET['m'];
		$year = $_GET['y'];
		include ('dsp_day_body.php');
	} else {
		include ('dsp_remove_body.php');
		}
	?>