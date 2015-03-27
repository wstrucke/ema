<?php
 /*	Calendar Class
 	*
	*	Process a posted add event form.
	*	- either add the requested event OR
	*	- return an error
	*
	*	Version 1.0 : 2006.04.25
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	/* Posted Fields:
	 *	date
	 *	sTimeAllDay
	 *	recurring
	 *	sTime
	 *	eTime
	 *	caption
	 *	captionAttributes[] -> 'strong','em', &/or 'u'
	 *	location
	 *	group
	 *	desc
	 *	link
	 *	recurrance_pattern -> 'daily', 'weekly', 'monthly', 'yearly'
	 *	daily -> 0 or 1
	 *	daily_pattern -> STRING
	 *	weekly_pattern -> STRING
	 *	weekly_pattern2[] -> 'sunday', 'monday', ... , 'saturday'
	 *	monthly -> 0 or 1
	 *	monthly_pattern -> STRING
	 *	monthly_pattern2 -> STRING
	 *	monthly_pattern3 -> 'first', 'second', 'third', 'fourth', 'last'
	 *	monthly_pattern4 -> 'day', 'weekday', 'sunday', 'monday', ... , 'saturday'
	 *	monthly_pattern5 -> STRING
	 *	yearly -> 0 or 1
	 *	yearly_pattern -> 'january', 'february', 'march', ... , 'december'
	 *	yearly_pattern2 -> STRING
	 *	yearly_pattern3 -> 'first', 'second', third', 'fourth', 'last'
	 *	yearly_pattern4 -> 'day', 'weekday', 'sunday', 'monday', ... , 'saturday'
	 *	yearly_pattern5 -> 'january', 'february', ... , 'december'
	 *	recurrance_range_end -> 0, 1, or 2
	 *	recurrance_range_end_after
	 *	recurrance_range_end_by
	 */
	
	/* End Result: 
	 *	Array (for single entry):
	 *		$ar['day']				the day of the month this event occurs on
	 *		$ar['sTime']			start time for this event (hh/mm)
	 *		$ar['eTime']			end time for this event (hh/mm)
	 *		$ar['caption']		caption/title/brief for this event
	 *		$ar['link']				hyperlink url either this or the description (below) is required (or both)
	 *		$ar['desc']				long description (html) of this event, or link destination if link->true
	 *		$ar['SEC_VIEW']		view permissions
	 *		$ar['SEC_EDIT'] 	edit permissions
	 *		$ar['postUser']		who created this event
	 *		$ar['postDate']		date this was posted (mm/dd/yyyy)
	 *		$ar['postTime']		time this was posted (hh:mm:ss)
	 *		$ar['location']		optional location of this event
	 *		$ar['group']			numerical group association for this event
	 *
	 *	or... Array (for recurring entry):
	 *		$ar['type']				the type of recurrance pattern this follows (daily, weekly, monthly, yearly)
	 *		$ar['pattern']		the recurrance pattern details
	 *		$ar['startDate']	the recurrance start date
	 *		$ar['endDate']		the recurrance end date
	 *		$ar['exceptions']	array of dates that are to be excluded from the range 
	 *											([05/07/2006],[01/20/2006],etc)
	 *		$ar['sTime']			start time for this event (hh/mm)
	 *		$ar['eTime']			end time for this event (hh/mm)
	 *		$ar['caption']		caption/title/brief for this event
	 *		$ar['link']				hyperlink url either this or the description (below) is required (or both)
	 *		$ar['desc']				long description (html) of this event, or link destination if link->true
	 *		$ar['SEC_VIEW']		view permissions
	 *		$ar['SEC_EDIT'] 	edit permissions
	 *		$ar['postUser']		who created this event
	 *		$ar['postDate']		date this was posted (mm/dd/yyyy)
	 *		$ar['postTime']		time this was posted (hh:mm:ss)
	 *		$ar['location']		optional location of this event
	 *		$ar['group']			numerical group association for this event
	 */
	 
	if ((@$_REQUEST['date'] == '') || (@$_REQUEST['caption'] == '') ||
			((@$_REQUEST['sTimeAllDay'] == '') && @($_REQUEST['sTime'] == '')))
	{
		echo "<strong>INVALID POST DATA</strong><br /><br />\r\n\r\n";
		var_dump($_REQUEST);
		exit;
	}
	
	# Initialize Variables
	if (isset($event)) { unset($event); }
	if (isset($temp)) { unset($temp); }
	
	# Determine what type of event this is
	if (@$_REQUEST['recurring'])
	{
		# Recurring Event
		include ('act_addeditRecurringEvent.php');
	} else {
		# Single Event
		include ('act_addeditSingleEvent.php');
	}
	
	# Output the response and return to day* view (this should be a configuration option)
	if ($success) {
		echo $t->calendar->outputMessage('Successfully added/updated event');
	} else {
		echo $t->calendar->outputMessage('Error adding or updating the event');
	}
	
	# Get date
	$temp = explode('/', $_REQUEST['date']);
	
	echo $t->calendar->outputDay($temp[0], $temp[1], $temp[2]);
	
?>