<?php
 /*	Calendar Class
 	*
	*	Display a message
	*
	*	Version 1.0 : 2006.08.27
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# Load Calendar Data
	$data = $t->get->calendar_data;
?>
	<div class="calendarMessage"><p><?php echo $data['text']; ?></p></div>