<?php
 /*	Calendar Class
 	*
	*	(development) root/index page
	*
	*	Version 1.0 : 2006.04.25
	* Version 2.0.0 : 2010.05.04
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# add required js link to the header for this page
	//$t->get->html->head->cc('link')->sas(array('type'=>'text/css','rel'=>'stylesheet','src'=>url('download/calendar.css')));
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/calendar.compat.js')));
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/calendar2a2.js')));
	
	$t->get->html->head->cc('script')->set_value("
<!--
	function loadDay (m,d,y)
	{
		// load specified day into browser
		try {
			if (d != null) { tmp = m + ',' + d + ',' + y; } else { tmp = m; }
			window.location = '" . $t->calendar->calendarURL . "selection=day&date=' + tmp;
		} catch(e) { showNotice('Your browser does not support this feature, please click the \"Full Schedule\" link to view the calendar.'); }
	}
-->");

	if ($t->calendar->simpleAccessLevel != 'user') {
		# build link array
		$links = array('add'=>'Add an Event', 'day'=>'Go to Today', 'week'=>'This Week', 'month'=>'This Month',
			'weekevents'=>'Just This Week\'s Events');
		
		if ($t->calendar->simpleAccessLevel == 'admin') {
			$links = array_merge($links, array('admin'=>'Admin Interface'));
		}
		
		// '3months'=>'3 Months', '12months'=>'Year', 'compact'=>'Compact Month', 'compactyear'=>'Compact Year',
		// 'twoweeks'=>'2 Weeks'
?>
<div class="calendarMgmtMenu">
<?php
	foreach($links as $k=>$v) { echo l($v, "calendar&selection=$k"); }
?>
</div>
<?php
	}
	
	# Choose what to display based on input
	switch (@$_REQUEST['selection']) {
		case '12months':
			$m = intval(date('m'));
			$y = intval(date('Y'));
			for ($i=0;$i<12;$i++) { echo $t->calendar->outputMonth($m+$i,$y); }
			break;
		case '3months':
			$m = intval(date('m'));
			$y = intval(date('Y'));
			echo $t->calendar->outputMonth($m, $y);
			echo $t->calendar->outputMonth($m + 1, $y);
			echo $t->calendar->outputMonth($m + 2, $y);
			break;
		case 'add':
			if ($t->calendar->simpleAccessLevel != 'user') {
				echo $t->calendar->_content('addeditEventForm');
			}
			break;
		case 'admin':
			if ($t->calendar->simpleAccessLevel == 'admin') {
				echo $t->calendar->_content('admin');
			}
			break;
		case 'compact':
			echo $t->calendar->outputCompact();
			break;
		case 'compactyear':
			for ($i=1;$i<=12;$i++)
			{
				echo '<div style="float: left; margin-right: 10px; height: 200px; ">';
				echo $t->calendar->outputCompact($i, date('Y'));
				echo '</div>';
			}
			echo '<div style="clear: left;">&nbsp;</div>';
			break;
		case 'day':
			# retrieve date if one is passed, otherwise use today
			if (isset($_REQUEST['date'])) { $temp = $_REQUEST['date']; } else { $temp = date('m,d,Y'); }
			$date = explode(',', $temp);
			echo '<table border=0 padding=0 width="800px"><tr><td valign="top">';
			# output mini calendar 
			echo $t->calendar->outputCompact($date[0], $date[2], $date[1]);
			echo '</td><td width="100%" valign="top">';
			# output day view
			echo $t->calendar->outputDay($date[0], $date[1], $date[2]);
			echo '</td></tr></table>';
			break;
		case 'delete':
			# delete an existing event
			if ($t->calendar->simpleAccessLevel != 'user') {
				include ($t->calendar->actionPath . 'act_deleteEvent.php');
			}
			break;
		case 'edit':
			# edit an existing event
			if ($t->calendar->simpleAccessLevel != 'user') {
				$t->calendar->get_event($event, $_REQUEST['id']);
				$t->calendar->data = array('event'=>$event);
				echo $t->calendar->_content('addeditEventForm');
			}
			break;
		case 'month':
			# retrieve date if one is passed, otherwise use today
			if (isset($_REQUEST['date'])) { $temp = $_REQUEST['date']; } else { $temp = date('m,d,Y'); }
			$date = explode(',', $temp);
			echo $t->calendar->outputMonth($date[0], $date[2]);
			break;
		case 'postAdd':
			if ($t->calendar->simpleAccessLevel != 'user') {
				include ($t->calendar->actionPath . 'act_addeditEventForm.php');
			}
			break;
		case 'postAdmin':
			if ($t->calendar->simpleAccessLevel == 'admin') {
			}
			break;
		case 'postEdit':
			if ($t->calendar->simpleAccessLevel != 'user') {
				include ($t->calendar->actionPath . 'act_addeditEventForm.php');
			}
			break;
		case 'twoweeks':
			# retrieve date if one is passed, otherwise use today
			if (isset($_REQUEST['date'])) { $temp = $_REQUEST['date']; } else { $temp = date('m,d,Y'); }
			$date = explode(',', $temp);
			echo $t->calendar->outputTwoWeeks($date[0], $date[1], $date[2]);
			break;
		case 'week':
			# retrieve date if one is passed, otherwise use today
			if (isset($_REQUEST['date'])) { $temp = $_REQUEST['date']; } else { $temp = date('m,d,Y'); }
			$date = explode(',', $temp);
			echo $t->calendar->outputWeek($date[0], $date[1], $date[2]);
			break;
		case 'weekevents':
			# retrieve date if one is passed, otherwise use today
			if (isset($_REQUEST['date'])) { $temp = $_REQUEST['date']; } else { $temp = date('m,d,Y'); }
			$date = explode(',', $temp);
			echo '<table border=0 padding=0 width="800px"><tr><td valign="top">';
			# output mini calendar 
			echo $t->calendar->outputCompact($date[0], $date[2]);
			echo '</td><td width="100%" valign="top">';
			# output day view
			echo $t->calendar->outputSevenDays($date[0], $date[1], $date[2]);
			echo '</td></tr></table>';
			break;
		default:
			# retrieve date if one is passed, otherwise use today
			echo $t->calendar->outputMonth();
			break;
	}
?>