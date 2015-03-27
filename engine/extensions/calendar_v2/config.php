<?php
 /**********************************
	* Calendar EU Configuration File
	* --------------------------------
	* Version 1.0, 2006.11.22
	*
	*/

	# Configure variables here: uncomment each variable to customize its' value
	
	
	/* IMPORTANT :: PATH SETTINGS */
	
	// the root path is the SERVER SIDE absolute path to the calendar directory
	//	- you must have a trailing slash
	$this->rootPath = dirname(__FILE__) . '/';
	// the calendar URL is the path EVERY PAGE IN THE CALENDAR will link to the included calendar_index.php file
	// 	- the calendar url MUST have trailing GET character for first passed variable (either a '?' or '&')
	//	- a leading slash is not required.  below are three examples:
	$this->calendarURL = url('calendar');
	if (strpos($this->calendarURL, '?')===false) { $this->calendarURL .= '?'; } else { $this->calendarURL .= '&'; }
	#$this->calendarURL = 'index.php?action=a139&';
	#$this->calendarURL = '/calendar/index.php?';
	
	
	/* Month Header Pic Settings */
	
	$this->monthHeaderPic['enabled'] = true;
	$this->monthHeaderPic['width'] = '600';
	$this->monthHeaderPic['height'] = '100';
	$this->monthHeaderPic['alt'] = 'Calendar Header Picture';
	$this->monthHeaderPic['src'] = 'calendar2_demo.png';
	
	
	/* Week Header Pic Settings */
	
	$this->weekHeaderPic['enabled'] = false;
	$this->weekHeaderPic['width'] = '400';
	$this->weekHeaderPic['height'] = '50';
	$this->weekHeaderPic['alt'] = 'Calendar Week';
	$this->weekHeaderPic['src'] = 'images/mini_01.jpg';		
	
	
	/* Group Display Settings */

	// enable the group filter option
	$this->groupFilterEnabled = true;
	// set the group options - the key numbers here should correspond to the group display numbers
	$this->group = array('0'=>'None','1'=>'Athletic Band','2'=>'Marching Band');
	// set the group display options -- 0 displays all events no matter what you make the text for item 0.
	// the numbers here should correspond to the values in the group array
	$this->groupDisplay = array('0'=>'All Events','1'=>'Non-grouped','2'=>'Athletic Band','3'=>'Marching Band');
	// group filter sets the default setting when the month and week is output (0 = show all events)
	$this->groupFilter = 0;	
	
	
	/* Other Display Settings */
	
	// weekGroupFilter:
	//  true                - output the group filter (if it is enabled on the site) in the week view
	$this->weekGroupFilter = false;
	// valid monthNavType values are:
	//	'mNsimple'					- show one month ahead and behind in the upper right corner
	//	'mNdeuce'						- show two months ahead and behind in the upper right corner
	//	'mNfullyearTop'			- show twelve months across the top of the calendar
	//	'mNfullyearBottom'	- show twelve months across the bottom of the calendar
	$this->monthNavType = 'mNfullyearTop';
	// valid oneweekMode values are: 
	//	'startToday'				- the first day of the week view should be the given date
	//	'startSunday'				- the first day of the week view should be the last sunday before the given date
	$this->oneweekMode = 'startToday';
	// valid weekHeadingMode values are:
	//  'enabled'           - display the weekday headings in a single row of list items
	//  'disabled'          - do not display the weekday headings
	//  'day'               - display each weekday heading in each day list item
	$this->weekHeadingMode = 'day';
	// useCalendarCSS:
	//  true                - output the calendar css style sheet
	$this->useCalendarCSS = false;
	
?>