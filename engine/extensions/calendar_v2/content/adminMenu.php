<?php
 /*	Calendar Class
 	*
	*	Display the manager/admin menu
	*
	*	Version 1.1 : sep-02-2010
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# build link array
	$links = array('add'=>'Add an Event', 'day'=>'Go to Today', 'week'=>'This Week', 'month'=>'This Month',
		'weekevents'=>'Just This Week\'s Events');
	
	if ($t->calendar->simpleAccessLevel != 'user') {
		$links = array_merge($links, array('admin'=>'Admin Interface'));
	}
	
	// '3months'=>'3 Months', '12months'=>'Year', 'compact'=>'Compact Month', 'compactyear'=>'Compact Year',
	// 'twoweeks'=>'2 Weeks'
?>
<!--
<div class="calendarMgmtMenu">
<?php
	foreach($links as $k=>$v) { echo l($v, "calendar&selection=$k"); }
?>
</div>
-->