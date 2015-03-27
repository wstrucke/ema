<?php
/* Calendar Class
 *
 * Process a posted add/edit event form.
 *  - either add/update the requested event OR
 *  - return an error
 *
 * Version 1.0 : 2006.05.22 - 2006.05.25, 2006.08.27
 * Version 1.1, 2010-11-23 ws
 * William Strucke [wstrucke@gmail.com]
 *
 */
	
 /*  Array (for recurring entry):
  *    $ar['type']                 the type of recurrance pattern this follows (daily, weekly, monthly, yearly)
  *    $ar['pattern']              the recurrance pattern details
  *    $ar['start_date']           the recurrance start date
  *    $ar['end_date']             the recurrance end date
  *    $ar['exceptions']           comma seperated list of dates that are to be excluded from the range
  *                                example to be determined...
  *    $ar['start_time']           start time for this event (hh/mm)
  *    $ar['end_time']             end time for this event (hh/mm)
  *    $ar['caption']              caption/title/brief for this event
  *    $ar['description']          long description (html) of this event, or link destination if link->true
  *    $ar['url']                  related url
  *    $ar['location_id']          optional location id of this event
  *    $ar['group_id']             group id associated with this event
  */
	
	# initialize the array
	$event = array();
	
	# Get the recurrance pattern
	$event['type'] = (string)$_REQUEST['recurrance_pattern'];
	
	# The selected recurrance pattern will determine how we process the posted data
	switch ($event['type']) {
		case 'daily': 
			if ($_REQUEST['daily'] == 1) {
				# occur every weekday
				$event['pattern'] = 'weekdays';
			} else {
				# occur every X days
				$event['pattern'] = $_REQUEST['daily_pattern'];
			}
			break;
		case 'weekly':
			/*	Write the pattern as: "(every X weeks), (days of the week)"
			 *		where sunday = 0, monday = 1, ... saturday = 6
			 *		e.g. if pattern is every two weeks on monday and wednesday: "2,13"
			 */
			$event['pattern'] = $_REQUEST['weekly_pattern'] . ',';
			$temp = $_REQUEST['weekly_pattern2'];
			foreach ($temp as $val) {
				if ($val == 'sunday')     { $event['pattern'] .= '0'; }
				if ($val == 'monday')     { $event['pattern'] .= '1'; }
				if ($val == 'tuesday')    { $event['pattern'] .= '2'; }
				if ($val == 'wednesday')  { $event['pattern'] .= '3'; }
				if ($val == 'thursday')   { $event['pattern'] .= '4'; }
				if ($val == 'friday')     { $event['pattern'] .= '5'; }
				if ($val == 'saturday')   { $event['pattern'] .= '6'; }
			}
			break;
		case 'monthly':
			/*	monthly -> 0 or 1 (0 indicates part 1 (the next two) 1 indicates rest)
			 *	monthly_pattern -> STRING "day of the month"
			 *	monthly_pattern2 -> STRING "every X months"
			 *	monthly_pattern3 -> 'first', 'second', 'third', 'fourth', 'last'
			 *	monthly_pattern4 -> 'day', 'weekday', 'sunday', 'monday', ... , 'saturday'
			 *	monthly_pattern5 -> STRING "every X months"
			 *	
			 *	Write the pattern as: "(every X months), (integer day value OR [1-5],[0-8])"
			 *		where [1-5] equals (first = 1, second = 2, ... , last = 5) AND
			 *		[0-8] equals (sunday = 0, monday = 1, ... , saturday = 6, day = 7, weekday = 8)
			 *		e.g. if pattern is every second sunday every 3 months: "3,2,0" OR
			 *				 if pattern is every 2 months on the 20th: "2,20"
			 */
			if ($_REQUEST['monthly'] == '0') {
				# use monthly_pattern and monthly_pattern2
				$event['pattern'] = $_REQUEST['monthly_pattern2'] . ',' . $_REQUEST['monthly_pattern'];
			} else {
				# use monthly_pattern3, monthly_pattern4, and monthly_pattern5
				$event['pattern'] = $_REQUEST['monthly_pattern5'] . ',';
				switch ($_REQUEST['monthly_pattern3']) {
					case 'first':     $event['pattern'] .= '1,'; break;
					case 'second':    $event['pattern'] .= '2,'; break;
					case 'third':     $event['pattern'] .= '3,'; break;
					case 'fourth':    $event['pattern'] .= '4,'; break;
					case 'last':      $event['pattern'] .= '5,'; break;
					default:
						# Error Condition
						echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
								 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
						exit;
						break;
				}
				switch ($_REQUEST['monthly_pattern4']) {
					case 'sunday':    $event['pattern'] .= '0'; break;
					case 'monday':    $event['pattern'] .= '1'; break;
					case 'tuesday':   $event['pattern'] .= '2'; break;
					case 'wednesday': $event['pattern'] .= '3'; break;
					case 'thursday':  $event['pattern'] .= '4'; break;
					case 'friday':    $event['pattern'] .= '5'; break;
					case 'saturday':  $event['pattern'] .= '6'; break;
					case 'day':       $event['pattern'] .= '7'; break;
					case 'weekday':   $event['pattern'] .= '8'; break;
					default:
						# Error Condition
						echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
								 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
						exit;
						break;
				}
			}
			break;
		case 'yearly':
			/*	yearly -> 0 or 1 (0 indicates part 1 (the next two) 1 indicates rest)
			 *	yearly_pattern -> 'january', 'february', 'march', ... , 'december'
			 *	yearly_pattern2 -> STRING (integer as string -> day of the month)
			 *	yearly_pattern3 -> 'first', 'second', third', 'fourth', 'last'
			 *	yearly_pattern4 -> 'day', 'weekday', 'sunday', 'monday', ... , 'saturday'
			 *	yearly_pattern5 -> 'january', 'february', ... , 'december'
			 *
			 *	Write the pattern as: "(month of the year), (day of the month OR [1-5],[0-8])"
			 *		where [1-5] equals (first = 1, second = 2, ... , last = 5) AND
			 *		[0-8] equals (sunday = 0, monday = 1, ... , saturday = 6, day = 7, weekday = 8)
			 *		i.e. if pattern is every february 2nd: "2,2" OR
			 *				 if pattern is every second weekday of march: "3,2,7"
			 */
			if ($_REQUEST['yearly'] == '0') {
				# use yearly_pattern and yearly_pattern2
				$event['pattern'] = $t->calendar->monthNametoInteger($_REQUEST['yearly_pattern']) . ','
				                    . $_REQUEST['yearly_pattern2'];
			} else {
				# use yearly_pattern3, yearly_pattern4, and yearly_pattern5
				$event['pattern'] = $t->calendar->monthNametoInteger($_REQUEST['yearly_pattern5']) . ',';
				switch ($_REQUEST['yearly_pattern3']) {
					case 'first':     $event['pattern'] .= '1,'; break;
					case 'second':    $event['pattern'] .= '2,'; break;
					case 'third':     $event['pattern'] .= '3,'; break;
					case 'fourth':    $event['pattern'] .= '4,'; break;
					case 'last':      $event['pattern'] .= '5,'; break;
					default:
						# Error Condition
						echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
								 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
						exit;
						break;
				}
				switch ($_REQUEST['yearly_pattern4']) {
					case 'sunday':    $event['pattern'] .= '0'; break;
					case 'monday':    $event['pattern'] .= '1'; break;
					case 'tuesday':   $event['pattern'] .= '2'; break;
					case 'wednesday': $event['pattern'] .= '3'; break;
					case 'thursday':  $event['pattern'] .= '4'; break;
					case 'friday':    $event['pattern'] .= '5'; break;
					case 'saturday':  $event['pattern'] .= '6'; break;
					case 'day':       $event['pattern'] .= '7'; break;
					case 'weekday':   $event['pattern'] .= '8'; break;
					default:
						# Error Condition
						echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
								 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
						exit;
						break;
				}
			}
			break;
		default:
			# Error Condition
			echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
					 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
			exit;
			break;
	}
	
	# Get the start date
	$event['start_date'] = @date('Y-m-d', strtotime($_REQUEST['date']));
	
	# recurrance end type:
	/* recurrance_range_end, recurrance_range_end_after, recurrance_range_end_by */
	switch ($_REQUEST['recurrance_range_end']) {
		case '0':
			# No end date
			$event['end_date'] = null;
			break;
		case '1':
			# End after X occurances
			$event['end_date'] = null; //$_REQUEST['recurrance_range_end_after'];
			/* DETERMINE THE EXACT END DATE HERE */
			break;
		case '2':
			# End by specified date
			$event['end_date'] = @date('Y-m-d', strtotime($_REQUEST['recurrance_range_end_by']));
			break;
		default:
			# Error Condition
			echo '<strong>Error at line ' . __LINE__ . ' in act_addRecurringEvent:' .
					 ' invalid pattern specified!</strong><em>' . $event['pattern'] . '</em>';
			//exit;
			break;
	}
	
	# no exceptions initially
	$event['exceptions'] = '';
	
	# Get caption
	$temp = $_REQUEST['caption'];
	
	# Check Time setting
	if (isset($_REQUEST['sTimeAllDay'])) {
		# All Day Event
		$event['start_time'] = '00:00';
		$event['end_time'] = '23:59';
	} else {
		# normal/limited time event
		$event['start_time'] = (string)$_REQUEST['sTime'];
		$event['end_time'] = (string)$_REQUEST['eTime'];
	}
	
	if (strlen($event['end_time'])==0) $event['end_time'] = null;
	
	# Check added html values
	if (isset($_REQUEST['captionAttributes'])) {
		foreach($_REQUEST['captionAttributes'] as $val) { $temp = "<$val>$temp</$val>"; }
	}
	
	# set event caption
	$event['caption'] = $temp;
	
	# finish adding data - the below variables require no checks/modification
	$event['url'] = (string)$_REQUEST['link'];
	$event['description'] = (string)$_REQUEST['desc'];
	$event['group_id'] = (string)$_REQUEST['group'];
	$event['location_id'] = (string)$_REQUEST['location'];
	
	# check if this is an existing event or a new event
	if ( (isset($_REQUEST['id'])) && ($_REQUEST['id'] != '') ) {
		# extract the id
		# initial syntax is 'rX'
		$id = $_REQUEST['id'];
		# return the portion of the string after the 'r'
		$id = intval(substr($id, 1));
	} else {
		# set to nothing
		$id = '';
	}
	
/* DEBUG *
	echo '<strong>Type:</strong> ' . $event['type'] . '<br />';
	echo '<strong>Pattern:</strong> ' . $event['pattern'] . '<br />';
	echo '<br /><br />';
	var_dump($event);
	echo '<br /><br />';
	var_dump($_REQUEST);
	exit;*/
	
	if ($t->calendar->add_update_recurring_event($event, $id)) {
		$success = true;
	} else {
		$success = false;
	}
	
?>