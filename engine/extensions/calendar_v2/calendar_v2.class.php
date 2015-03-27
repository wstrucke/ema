<?php
	/* calendar_v2 extension for ema
	 *
	 * Revision 2.1.0, Sep-29-2009, Sep-02-2010
	 * William Strucke, wstrucke@gmail.com
	 *
	 *	---------------------------------------------------------------------------------------------
	 *	Internal Functions:
	 *		calendar2()						initialize the calendar with default settings
	 *		arrFindObjects (&$haystack, $pos, $needle) 
	 *													locate needle in haystack at array position $pos. return all 
	 *													results as array.  alters $haystack!
	 *		arrRemoveNonMatching (&$haystack, $pos, $needle)
	 *													locate needle in haystack at array position $pos, remove all 
	 *													non matching results. alters $haystack!
	 *		convertEntryToString (&$entry, $id = false)
	 *													given a single calendar entry, combine it into a legible string value
	 *		convertRecurringToStandard($rEventID,$rEvent,&$EventsArray,$date)
	 *													given a single recurring event record ($rEvent) and its record ID 
	 *													($rEventID), add it as a standard event record into $EventsArray for 
	 *													the specified $date
	 *		dateIsAWeekday($date)
	 *													return true if specified date (m/d/yyyy) is between monday and friday
	 *		dateIsWithinRange($date, $start, $finish)
	 *													if $start <= $date <= $finish return true, otherwise return false
	 *													all three values are in the form of 'mm/dd/yyyy'
	 *		DecodeMonthlyPattern($arrPattern)
	 *													given a 'monthly' encoded pattern as an array, return the day of the 
	 *													month it occurs on
	 *		DecrementDate(&$month, &$day, &$year, $subtraction)
	 *													reduce the supplied date variables by $subtraction days, by reference
	 *		GetDateString(&$month, &$day, &$year)
	 *													given a date, return a string in the form of "Monday, April 15th, 2006"
	 *		GetDayOfWeek(&$month, &$day, &$year)
	 *													returns the textual day of the week name for the specified date
	 *		GetDateSuffix($day)		returns 'st', 'nd', 'rd', or 'th' based on day value
	 *		GetFirst($dayName, &$month, &$year)
	 *													Given a month and year (by reference), find the first $dayName 
	 *													(e.g. 'Sunday')
	 *		GetFirstSunday(&$month, &$day, &$year) 
	 *													alters the specified date until it matches the first sunday prior
	 *													to the initial date
	 *		GetLast($dayName, &$month, &$year)
	 *													Given a month and year (by reference), find the last occurance of 
	 *													$dayName WITHIN the month (e.g. $dayName = 'Monday')
	 *		GetLastSaturday(&$month, &$day, &$year)
	 *													alters the specified date until it matches the last saturday
	 *													following the initial date
	 *		GetMonthData ($month, $year, [ $ignoreRecurringEvents ], [ $ignoreGroupSetting ] )
	 *													returns the data for specified month
	 *													if $ignoreRecurringEvents is true, function will not return any
	 *													recurring events
	 *		GetMonthName($month)	returns the name of the specified integer $month value
	 *		GetMonthsBetween($start, $end)
	 *													return how many months (0+) are between the start and end dates
	 *													(both input vars are arrays)
	 *		GetRecurringData( [ $ignoreGroupSetting ] )		
	 *													returns data from get recurring events query
	 *													* optional ignoreGroupSetting forces all events returned
	 *		GetStandardOutput(&$start, &$end, &$dayHeader, &$day, $startPoint, [ $number_of_days ] )
	 *													using passed variables (by reference), put together standard 
	 *													week/month/whatever output
	 *													* optional arguments $number_of_days (to output)
	 *		IncrementDate(&$month, &$day, &$year, $addition)
	 *													increases the supplied date by $addition days, by reference
	 *		processRecurringEvents(&$data, $month, $year)
	 *													Given array $data, convert recurring events into standard month 
	 *													data format for specified $month/$year
	 *		retrieveTemplate($filename)
	 *													opens template ($filename) and returns contents
	 *	---------------------------------------------------------------------------------------------
	 *	External Functions:
	 *		add_update_event($event, $date, $eventID = '')
	 *													add event to the calendar, input array $event (use format above, no id)
	 *		add_update_recurring_event($event, &$event_id)
	 *													Add a new recurring event to the calendar, using array $event
	 *													... or update an existing one
	 *		delete_event(&$event, $id)
	 *													permanently delete the specified event and return it
	 *		get_event(&$event, $id)
	 *													retrieve an existing event by id
	 *		monthNametoInteger ($name)
	 *													returns the integer value of specified month name
	 *		outputCompact( [ $month ], [ $year ], [ $selected ] )
	 *													output specified month to the screen in compact form
	 *													* optional $selected is a date to display in bold 
	 *														$selected = integer day value
	 *		outputDay( [ $month ], [ $day ], [ $year ] )
	 *													output specified day to the screen, all values are optional
	 *		outputMessage($text)	output the specified message to the screen
	 *		outputMonth( [ $month ], [ $year ] )
	 *													output a month to the screen, all arguments are optional
	 *		outputSevenDays( [ $start_month ], [ $start_day ], [ $start_year ] )
	 *													output seven days (in day form) starting on specified date
	 *													* all arguments are optional
	 *		outputTwoWeeks( [ $start_month ], [ $start_day ], [ $start_year ] )
	 *													output two weeks, all arguments are optional
	 *		outputWeek( [ $start_month ], [ $start_day ], [ $start_year ], [ $showHeader ] )
	 *													output a week (7 days) to the screen beginning at specified date
	 *													if no date is specified, automatically starts with today
	 *													showHeader is a boolean value and is optional - if false does not output
	 *													a picture, title, or sunday - saturday day heading
	 *													all arguments are optional
	 *	---------------------------------------------------------------------------------------------
	 *	If this file appears distored or incorrectly spaced, change your tab size to 2 characters
	 *	---------------------------------------------------------------------------------------------
	 *	COPYRIGHT/FAIR USE NOTICE:
	 *		Copyright (c) 2004-2010, William Strucke.
	 *		All Rights Reserved.
	 *
	 *		You are granted one license to this software.  That entitles you to use this on ONE (1)
	 *		website on one domain (i.e. www.yoursite.com).  For additional licensing information or 
	 *		to obtain a new license, please contact William Strucke, wstrucke@gmail.com.
	 *
	 *  CALENDAR JAVASCRIPT INPUT POPUP Copyright (c) Aeron Glemann, ALL RIGHTS RESERVED
	 *    -- USED WITHOUT PERMISSION --
	 *    http://www.electricprism.com/aeron/calendar/
	 *
	 * to do:
	 *	-	complete transition to ema
	 *	- create interactive function
	 *	- create gear function(s) to output calendar css and javascript
	 *
	 */
	
	# enable error output (enable during development)
	@error_reporting(E_ALL);
	@ini_set("display_errors", 1);
	
	class calendar_v2 extends standard_extension
	{
		#public $css;                          // combined css document from all loaded documents
		#protected $html;                      // html reference file (in xml_document object)
		#public $id;                           // id of the loaded template
		#protected $loaded = false;            // true when a template has been loaded
		
		#public $css_array;                    // array of css files for the loaded template
		#public $meta_tags;                    // array of meta tags for the page head
		#public $javascript;                   // array of javascript for the loaded template
		
		# USER CONFIGURABLE VARIABLES
		public $rootPath = 'calendar2/';
		
		// calendar url should have trailing character for first passed variable (i.e. either a '?' or '&')
		public $calendarURL = 'calendar_index.php?';
		
		# Site ID (not implemented at all)
		public $site_id = 1;
		
		# Color/Display Settings
		public $weekGroupFilter = true;
		public $weekHeadingFgColor = '#000';
		public $weekHeadingBgColor = '#dddddd';
		public $weekHeadingMode = 'enabled';
		public $weekdayFgColor = '#000';
		public $weekdayBgColor = '#fff';
		public $weekdayBorderColor = '#000';
		public $todayFgColor = '#000';
		public $todayBgColor = 'rgb(255,234,234)';
		public $todayBorderColor = 'rgb(230,0,0),rgb(204,0,0)';
		public $weekdayBorderStyle = 'dropshadow';
		public $weekdayDatePosition = 'top-right';
		public $monthNavType = 'mNfullyearTop';
		public $oneweekMode = 'startToday';
		public $useCalendarCSS = true;
		
		/* valid monthNavType values are: 'mNsimple', 'mNdeuce', 'mNfullyearTop', 'mNfullyearBottom' */
		/* valid oneweekMode values are: 'startToday', 'startSunday' */
		/* valid weekHeadingMode values are: 'enabled', 'disabled', 'day' */
		
		# Graphics Settings and Defaults
		public $monthHeaderPic;
		public $weekHeaderPic;
		
		# Group Settings
		public $groupFilterEnabled = true;
		public $group;
		var $groupDisplay;
		var $groupFilter = 0;	// 0 = automatically shows all events, a greater value hides non-matching events
		
		# Simple Security Settings for version 2.0 Alpha
		var $simpleAccessLevel = 'user';
		
		# private global variables
		var $actionPath, $dataPath, $queryPath, $rDataFile, $templatePath;
		protected $configured = false;
		protected $header_output = false;
		
		# loaded calendar data for output
		public $data;
		
		# database version
		public $schema_version='0.1.8';       // the schema version to match the registered schema
		public $version;
		
		public $_name = 'Calendar Extension';
		public $_version = '2.1.0-alpha-1';
		protected $_debug_prefix = 'calendar';
		
		/* code */
		
		public function _construct()
		/* initialize calendar_v2 class
		 *
		 * calendar_v2($permissions = 'user', $debug = false)
		 *
		 * Valid permissions are:
		 *		user		Read Only Access
		 *		manager	Write (add/edit/delete) access
		 *		admin		Full Control of all options and settings
		 * An invalid value will automatically assign 'user'
		 *
		 */
		{
			#$this->css =& $this->_tx->my_css_document;
			
			#$this->css_array = array();
			#$this->meta_tags = array();
			#$this->javascript = array();
			#$this->id = '';
			
			$this->_tx->_publish('calendar_data', $this->data);
			$this->_tx->_publish('calendar_group_filter', $this->groupFilterEnabled);
			$this->_tx->_publish('calendar_group', $this->group);
			$this->_tx->_publish('calendar_month_header_img', $this->monthHeaderPic['enabled']);
			$this->_tx->_publish('calendar_week_header_img', $this->weekHeaderPic['enabled']);
			
			if (isset($_GET['debugoverride'])) { $this->_debug_mode = $_GET['debugoverride']; }
			
			$this->version = $this->_version;
			
			# set week/month header pictures default values
			$this->_debug('setting default values for week/month header pictures');
			$this->monthHeaderPic['enabled'] =  true;
			$this->monthHeaderPic['height'] =   '100';
			$this->monthHeaderPic['width'] =    '600';
			$this->monthHeaderPic['alt'] =      'Calendar Header Picture';
			$this->monthHeaderPic['src'] =      'calendar2_demo.png';
			$this->weekHeaderPic['enabled'] =   true;
			$this->weekHeaderPic['height'] =    '50';
			$this->weekHeaderPic['width'] =     '400';
			$this->weekHeaderPic['alt'] =       'Calendar v2 Header';
			$this->weekHeaderPic['src'] =       'mini_01.jpg';		
			
			# set default group values
			$this->group = array('1'=>'None','2'=>'Athletic Band','3'=>'Marching Band');
			$this->_debug('&nbsp;&nbsp;$this->group[]=' . implode(',', $this->group));
			$this->groupDisplay = array('0'=>'All Events','1'=>'Non-grouped','2'=>'Athletic Band','3'=>'Marching Band');
			$this->_debug('&nbsp;&nbsp;$this->groupDisplay[]=' . implode(',',$this->groupDisplay));
			
			# register with the system cache
			if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
				$this->_tx->cache->register_table('calendar_settings');
				$this->_tx->cache->register_table('calendar_groups');
				$this->_tx->cache->register_table('calendar_locations');
				$this->_tx->cache->register_table('calendar_events');
				$this->_tx->cache->register_table('calendar_recurring_events');
			}
			
			return true;
		}
  
  public function configure($args = false)
  /* contract for configure: configure the module and/or verify the module configuration
   *
   * required:
   *   N/A
   *
   * optional:
   *   args               (array) key value pairs of arguments::value to be set
   *
   * returns:
   *   true if all required settings are set and the module is operational
   *   false if the module is not configured
   *
   */
  {
  	# this should be the new model for the calendar v2.2; for now just use to ensure compatibility
  	# with ema gears
  	
    # preset user permissions
    $permissions = 'user';
    
    # if a security module is registered, check the clients access level for the calendar
    if ($this->_has('security')) {
      if ($this->_tx->security->access('calendar.admin')) {
        $permissions = 'admin';
      } elseif ($this->_tx->security->access('calendar.manager')) {
        $permissions = 'manager';
      }
    }
  	
    # include the custom configuration file here to override all of the default settings
    $this->_debug('<strong>opening configuration override file</strong>');
    require ('config.php');
    
    # initialize variables
    $this->_debug('initializing variables:');
    $this->actionPath = $this->rootPath . 'action/';
    $this->_debug('&nbsp;&nbsp;$this->actionPath=' . $this->actionPath);
    $this->dataPath = $this->rootPath . 'data/';
    $this->_debug('&nbsp;&nbsp;$this->dataPath=' . $this->dataPath);
    $this->queryPath = $this->rootPath . 'query/';
    $this->_debug('&nbsp;&nbsp;$this->queryPath=' . $this->queryPath);
    $this->rDataFile = 'recurring.wb2';
    $this->_debug('&nbsp;&nbsp;$this->rDataFile=' . $this->rDataFile);
    $this->templatePath = $this->rootPath . 'templates/';
    $this->_debug('&nbsp;&nbsp;$this->templatePath=' . $this->templatePath);
    
    $this->_debug('&nbsp;&nbsp;$this->calendarURL=' . $this->calendarURL);
    
    # set permissions
    $this->_debug('set permissions...', true);
    switch (@strtolower($permissions))
    {
      case 'user':    $this->simpleAccessLevel = 'user';    break;
      case 'manager': $this->simpleAccessLevel = 'manager'; break;
      case 'admin':   $this->simpleAccessLevel = 'admin';   break;
      default:        $this->simpleAccessLevel = 'user';    break;
    }
    $this->_debug($this->simpleAccessLevel);
    # maintain group filter across links if one is set
    if ( ($this->groupFilterEnabled) && (isset($_REQUEST['group_filter'])) )
    {
      $this->_debug('groupFilterEnabled');
      $this->groupFilter = $_REQUEST['group_filter'];
      $this->_debug('&nbsp;&nbsp;$this->groupFilter=' . $this->groupFilter);
      $this->calendarURL .= 'group_filter=' . $this->groupFilter . '&';
      $this->_debug('&nbsp;&nbsp;$this->calendarURL=' . $this->calendarURL);
    } else {
      $this->_debug('<strong>groupFilterDisabled</strong>');
    }
    
    $this->configured = true;
  }
		
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		// 	BASE FUNCTIONALITY
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		
		public function add_update_event($event, $date, $eventID = '')
		/* Add a new event to the calendar, using array $event
		 * 	- $event array in format specified at the top of the class, with no $id level
		 *		i.e. instead of $arr['id']['date'], all values are $arr['date'], $arr['time'], etc.
		 *	- $date is a string in the format of "mm/dd/yyyy"
		 *
		 *	To do here:
		 *		- add validation check (regExp?) to ensure all html tags in all data have appropriate
		 *			closing tags (to avoid intentional 'hack' attemps)
		 *		- add permissions check with security subsystem
		 */
		{
			if ($this->configured === false) $this->configure();
			
			/* VARIABLES: $temp, $month, $day, $year, $filename, $data */
			$this->_debug_start($event . ',' . $date . ',' . $eventID);
			
			if ($this->simpleAccessLevel == 'user') return $this->_return(false, '<strong>access denied to add a recurring event</strong>');
			
			if (isset($date)) {
			// Validate Date
				$this->_debug('validating date');
				# split the date into components
				$temp = explode('/', $date);
				# set the date values from split array
				$month = $temp[0]; $day = $temp[1];	$year = $temp[2];
				$this->_debug('initial month=' . $month . ',day=' . $day . ',year=' . $year);
				# Validate Date Values
				if (intval($month) < 10) 	{ $month = '0' . strval(intval($month)); }
				if (intval($day) < 10) 		{ $day = '0' . strval(intval($day)); }
				if (intval($year) < 10) 	{ $year = '200' . strval(intval($year)); }
				if (intval($year) < 100) 	{ $year = '20' . strval(intval($year)); }
			} else {
				$this->_debug('<strong>no initial date set</strong>');
				# no date set, use today's date
				$date = date('m/d/Y');
				$temp = explode('/', $date);
				$month = $temp[0]; $day = $temp[1];	$year = $temp[2];
			}
			$this->_debug('final month=' . $month . ',day=' . $day . ',year=' . $year);
			
			# retrieve the current month's data
			#$data = $this->GetMonthData($month, $year, true, true);
			
			# get the location_id from the provided location (or generate a new one)
			if (strlen($event['location']) > 0) {
				$check = $this->_tx->db->query(
					'calendar_locations',
					array('site_id', 'name'),
					array($this->site_id, '%' . $event['location'] . '%'),
					array('id'),
					true);
				if (db_qcheck($check, true)) {
					# location exists
					$location_id = $check[0]['id'];
				} else {
					# location does not exist, create it
					$this->_tx->db->insert('calendar_locations', array('site_id', 'name'), array($this->site_id, $event['location']));
					$location_id = $this->_tx->db->insert_id();
				}
			} else {
				$location_id = 0;
			}
			
			# get the group_id from the specified group
			$group_id = 0;
			if (strlen($event['group']) > 0) {
				$check = $this->_tx->db->query(
					'calendar_groups',
					array('site_id', 'name'),
					array($this->site_id, $event['group']),
					array('id'),
					true);
				if (db_qcheck($check, true)) { $group_id = $check[0]['id']; }
			}
			
			# if an ID was specified, overwrite that event id
			if ($eventID === '') {
				$this->_debug('no event id was specified, appending new event');
				# add the new entry
				$r = $this->_tx->db->insert(
					'calendar_events',
					array('site_id', 'month', 'day', 'start_time', 'end_time', 'caption', 'url',
						'description', 'location_id', 'group_id', 'post_user', 'post_date'),
					array($this->site_id, $year . $month, $day, $event['sTime'], $event['eTime'], $event['caption'],
						$event['link'], $event['desc'], $location_id, $group_id, $this->_tx->get->userid, date('Y-m-d H:i:s')));
			} else {
				$this->_debug('updating an existing event');
				# update an existing entry
				$r = $this->_tx->db->update(
					'calendar_events',
					array('site_id', 'id'),
					array($this->site_id, $eventID),
					array('month', 'day', 'start_time', 'end_time', 'caption', 'url',
						'description', 'location_id', 'group_id', 'post_user', 'post_date'),
					array($year . $month, $day, $event['sTime'], $event['eTime'], $event['caption'],
						$event['link'], $event['desc'], $location_id, $group_id, $this->_tx->get->userid, date('Y-m-d H:i:s')));
			}
			
			# debug any error
			if (! $r) {
				$this->_debug('Received SQL Error [' . $this->_tx->db->errno . ']: ' . $this->_tx->db->error);
			}
			
			# return the result
			return $this->_return($r);
		} // add_update_event
		
		public function add_update_recurring_event($event, &$event_id)
		/* Add a new recurring event to the calendar, using array $event (or update an existing one)
		 * 	- $event array in format specified at the top of the class, with no $id level
		 *		i.e. instead of $arr['id']['date'], all values are $arr['date'], $arr['time'], etc.
		 *	- $event_id will be replaced by a new, generated event ID
		 *
		 *	To do here:
		 *		- add validation check (regExp?) to ensure all html tags in all data have appropriate
		 *			closing tags (to avoid intentional 'hack' attempts)
		 *		- add permissions check with security subsystem
		 */
		{
			if ($this->configured === false) $this->configure();
			
			/* VARIABLES: $event_id, $event, $data, $query */
			$this->_debug_start($event . ',' . $event_id);
			
			if ($this->simpleAccessLevel == 'user') return $this->_return(false, '<strong>access denied to add a recurring event</strong>');
			
			# prepare permissions
			
			# add date/time stamp
			$event['post_user'] = $this->_tx->get->userid;
			$event['post_date'] = date('Y-m-d H:i:s');
			$this->_debug('adding timestamp ' . $event['post_date']);
			
			# add site id (not implemented at this time)
			$event['site_id'] = $this->site_id;
			
			# get the location_id from the provided location (or generate a new one)
			if (strlen($event['location_id']) > 0) {
				$check = $this->_tx->db->query(
					'calendar_locations',
					array('site_id', 'name'),
					array($this->site_id, '%' . $event['location_id'] . '%'),
					array('id'),
					true);
				if (db_qcheck($check, true)) {
					# location exists
					$event['location_id'] = $check[0]['id'];
				} else {
					# location does not exist, create it
					$this->_tx->db->insert('calendar_locations', array('site_id', 'name'), array($this->site_id, $event['location']));
					$event['location_id'] = $this->_tx->db->insert_id();
				}
			} else {
				$event['location_id'] = 0;
			}
			
			# add or update the event
			if ($event_id == '') {
				# new event
				$r = $this->_tx->db->insert('calendar_recurring_events', array_keys($event), array_values($event));
			} else {
				# update existing
				$r = $this->_tx->db->update('calendar_recurring_events', array('id'), array($event_id), array_keys($event), array_values($event));
			}
			
			# debug any error
			if (! $r) {
				$this->_debug('Received SQL Error [' . $this->_tx->db->errno . ']: ' . $this->_tx->db->error);
			}
			
			# return the result
			return $this->_return($r);
		} // add_update_recurring_event
  
  public function cache_expire_table($t)
  /* given a table (t) that has been modified, return an array of match arguments to be expired
   *   from the cache
   *
   * this function should only be called from the cache module
   *
   * returns an array of one or more key->value pairs or false to decline the request
   *
   */
  {
  	$match = array();
  	switch($t) {
  		case 'calendar_settings': $match['pageid'] = $this->cache_last_modified; break;
  		case 'calendar_groups': return false;
  		case 'calendar_locations': return false;
  		case 'calendar_events': return false;
  		case 'calendar_recurring_events': return false;
  		default: return false;
  	}
  	return $match;
  }
  
  public function cache_expire_interval($method = '')
  /* given a method name return the interval in seconds in which it should be refreshed by the system cache
   *  the cache should always start counting at midnight
   *
   * return an integer; -1 means 'do not cache'
   *
   * for reference:
   *    1 hour        3600 seconds
   *    6 hours       21600 seconds
   *    12 hours      43200 seconds
   *    1 day         86400 seconds
   *    5 days        432000 seconds
   *    1 week        604800 seconds
   *    1 month       2592000 seconds
   *
   * a value of 0 means never automatically refresh until the cache is expired
   *
   */
  {
  	switch($method) {
  		case 'outputCompact': return 86400;
  		case 'outputDay': return 86400;
  		case 'outputMessage': return 0;
  		case 'outputSevenDays': return 86400;
  		case 'outputTwoWeeks': return 86400;
  		case 'outputWeek': return 86400;
  		case 'index': return 0;
  		default: return 0;
  	}
  }
  
		protected function calendar_header()
		/* output calendar header items
		 *
		 */
		{
			# only output the header elements once
			if ($this->header_output) return true;
			$this->header_output = true;
			$html =& $this->_tx->get->html;
			if (!is_object($html)) {
				$this->_debug('<strong>ERROR</strong>: calendar_header() html was not an object!');
				return false;
			}
			
			# include style sheet
			if ($this->useCalendarCSS) {
				$this->_debug('opening calendar css support file');
				$html->head->cc('link')->sas(array('rel'=>'stylesheet','type'=>'text/css',
					'href'=>url('download/calendar.css')));
			}
			
			# set up calendar root path for javascript
			$this->_debug('setting js calendar root path: ' . $this->calendarURL);
			$html->head->cc('script')->sas(array('type'=>'text/javascript'))->set_value('window.calendarRootPath="' . url('calendar?') . '";');
			
			# include javascript file
			#$this->_debug('opening calendar javascript support files');
			#$this->_tx->get->html->head->cc('script')->sas(array('type'=>'text/javascript', 'src'=>url('download/calendar2a2.js')));
		}
		
		public function delete_event(&$event, $eventID)
		/* permanently delete the specified event and return it */
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($event . ',' . $eventID);
			
			if ($this->simpleAccessLevel == 'user') {
				$this->_debug('<strong>access denied to delete an event</strong>');
				return $this->_return(false);
			}
			
			# determine if the specified event is standard or recurring
			if (substr($eventID, 0, 1) == 'r') {
				$this->_debug('event determined to be a recurring event');
				# recurring event
				# set non-standard variable to true
				$nonStd = true;	
				# remove the 'r' and ':' from the event
				$tmp = explode(':', $eventID);
				$eventID = $tmp[0];
				$eventID = substr($eventID, 1);
				$this->_debug('new event id: ' . $eventID);
			} else {
				$this->_debug('event determined to be a standard event');
				# standard event
				# set non-standard variable to false
				$nonStd = false;
			}
			
			# save the new event list
			if ($nonStd) {
				# delete recurring event
				$result = $this->_tx->db->delete('calendar_recurring_events', array('id'), array($eventID));
			} else {
				# delete standard event
				$result = $this->_tx->db->delete('calendar_events', array('id'), array($eventID));
			}
			
			return $this->_return($result);
		} // delete_event
		
		public function get_event(&$event, $id)
		/* retrieve an existing event by id
		 *	return $event = standard event array (as defined above)
		 *
		 */
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($event . ',' . $id);
			
			# if the event id starts with 'r' then it's a recurring event
			if (strpos($id, 'r') === 0) {
				$this->_debug('event determined to be a recurring event');
				# this is a recurring event
				# split the id and date values
				$id = explode('.', $id, 2);
				# if the id contains a colon, remove the date value
				if (strpos($id[0], ':') !== false) {
					$id[0] = explode(':', $id[0], 2);
					$id[0] = $id[0][0];
				}
				# remove the letter 'r' from the id
				$id[0] = substr($id[0], 1, strlen($id[0] - 1));
				$this->_debug('final event id: ' . $id[0]);
				$this->_debug('retrieving recurring event data');
				# get recurring data
				$data = $this->GetRecurringData(false, $id[0]);
				# return the specified recurring event (by id)
				$event = $data[0];
			} else {
				$this->_debug('event determined to be a standard event');
				# this is a single entry event
				$query = $id;
				include ($this->queryPath . 'getSingleEvent.php');
				if ($query !== false) { $event = $query[0]; } else { $event = false; }
			} // if strpos
			
			return $this->_return(true);
		} // get_event
		
		public function monthNametoInteger ($name)
		# returns the integer value of specified month name
		{
			$this->_debug_start($name);
			switch (strtolower($name))
			{
				case 'january':			return $this->_return(1);
				case 'february':		return $this->_return(2);
				case 'march':				return $this->_return(3);
				case 'april':				return $this->_return(4);
				case 'may':					return $this->_return(5);
				case 'june':				return $this->_return(6);
				case 'july':				return $this->_return(7);
				case 'august':			return $this->_return(8);
				case 'september':		return $this->_return(9);
				case 'october':			return $this->_return(10);
				case 'november':		return $this->_return(11);
				case 'december':		return $this->_return(12);
				default: 						return $this->_return(0);
			}
		} // monthNametoInteger
		
		public function outputCompact($month = 0, $year = 0, $selected = 0)
		// output specified month to the screen in compact form
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($month . ',' . $year . ',' . $selected);
			
			$this->calendar_header();
			
			# ensure month/year are set
			if ($month == 0) { $month = date('m'); }
			$this->_debug('&nbsp;&nbsp;$month=' . $month);
			
			if ($year == 0) { $year = date('Y'); }
			$this->_debug('&nbsp;&nbsp;$year=' . $year);
			
			$this->_debug('setting temporary variables');
			# set date variables to pass to month display file
			$start['month'] = $this->GetMonthName($month);
			$start['year'] = strval(intval($year));
			
			$this->_debug('setting column headers');
			# set column headers for output
			$dayHeader = array('Su','Mo','Tu','We','Th','Fr','Sa');
			
			$this->_debug('figuring out how many days we are outputting');
			# determine how many days we need to output
			$sMonth = $month; $sDay = '1'; $sYear = $year;
			$this->GetFirstSunday($sMonth, $sDay, $sYear);
			$end[0] = $month; $end[1] = '1'; $end[2] = $year;
			$this->GetLastSaturday($end[0], $end[1], $end[2]);
			$number_of_days = $this->GetDaysBetween( array($sMonth, $sDay, $sYear), array($end[0], $end[1], $end[2]) );
			$this->_debug('outputting ' . $number_of_days . ' days');
			
			# for some reason the number of days value is wrong when working with multiple months??
			while (($number_of_days % 7) != 0)
			{
				$this->_debug('adding one...');
				$number_of_days++;
			}
			
			// $day[x][0] => date , $day[x][1] => URL or '' , $day[x][2] => class name(s)
			
			$data = null;
			
			$this->_debug('loop start');
			# loop through enough days and set output data for each
			for ($i = 0; $i < $number_of_days; $i++)
			{
				# set the date
				$day[$i][0] = strval(intval($sDay));
				
				# set the link
				$day[$i][1] = $sMonth . ',' . $sDay . ',' . $sYear;
				
				# ensure we have the proper data for this month
				if ( (count($data) == 0) || ($dataIsFor != ($sMonth . $sYear)) )
				# retrieve month's data
				{
					# retrieve the current month's data
					$data = $this->GetMonthData($sMonth, $sYear);
					$dataIsFor = $sMonth . $sYear;
				}
				
				# get events for this day
				$result = $this->arrFindObjects($data, 'day', $sDay);
				
				# preset class for all items
				$day[$i][2] = 'day';
				
				# if result is an array, prepare it for use
				if (is_array($result))
				{
					# move to the beginning of the array so we can just display the first element
					reset($result);
					# set a pointer for the first element in the array
					$bear = current($result);
				} else {
					$bear = '';
				}
				
				if (sizeof($result) > 0)
				{
					# make the date bold
					$day[$i][0] = '<strong>' . $day[$i][0] . '</strong>';
					# add hover element
					$day[$i][2] .= ' hover';
					# assign an id
					$day[$i][3] = $sYear . $sMonth . $sDay;
					# add title to hover box
					$day[$i][4] = $this->GetDateString($sMonth,$sDay,$sYear) . ':<br /><br /><ul>';
					# assign hover box data
					while (count($result) > 0)
					{
						$day[$i][4] .= '<li>' . $bear['caption'] . '</li>';
						array_shift($result);
						$bear = current($result);
					}
					$day[$i][4] .= '</ul>';
				}
				
				# set the class for this day
				if ($sMonth != $month)
				{
					# this is not the current month
					$day[$i][2] .= ' off';
				} else {
					# this is the current month, check $selected
					if (intval($selected) == intval($sDay))
					{
						$day[$i][2] .= ' selected';
					}
				}
				
				# if this is today, add the today stamp to the class
				if (($sMonth . '-' . $sDay . '-' . $sYear) == date('m-d-Y'))	{ $day[$i][2] .= ' today'; }
				
				# move to the next day
				$this->IncrementDate($sMonth, $sDay, $sYear, 1);
			}
			$this->_debug('loop end');
			
			# set data for output
			$this->data = array('month'=>$start['month'], 'year'=>$start['year'], 'dayHeader'=>$dayHeader, 'events'=>&$day);
			
			return $this->_return($this->_content('compact'), 'including dsp_compact.php...');
		} // outputCompact
		
		public function outputDay($month = 0, $day = 0, $year = 0)
		// output specified day with any calendar entries to the screen
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($month . ',' . $day . ',' . $year);
			
			$this->calendar_header();
			
			# ensure date is set
			if ($month == 0) { $month = date('m'); }
			$this->_debug('&nbsp;&nbsp;$month=' . $month);
			
			if ($day == 0) { $day = date('d'); }
			$this->_debug('&nbsp;&nbsp;$day=' . $day);
			
			if ($year == 0) { $year = date('Y'); }
			$this->_debug('&nbsp;&nbsp;$year=' . $year);
			
			# set link to month view
			$link[0] = $this->calendarURL . 'selection=month&date=' . $month . ',1,' . $year;
			$this->_debug('month link set to ' . $link[0]);
			
			# set link to previous/next days (subtract 1 for prev, add 2 for next, subtract 1 to restore)
			$this->DecrementDate($month, $day, $year, 1);
			$link[1] = $this->calendarURL . 'selection=day&date=' . $month . ',' . $day . ',' . $year;
			$this->_debug('previous link: ' . $link[1]);
			$link[2] = $this->GetMonthName($month) . ' ' . strval(intval($day)) . $this->GetDateSuffix($day);
			$this->_debug('previous link text: ' . $link[2]);
			$this->IncrementDate($month, $day, $year, 2);
			$link[3] = $this->calendarURL . 'selection=day&date=' . $month . ',' . $day . ',' . $year;
			$this->_debug('next link: ' . $link[3]);
			$link[4] = $this->GetMonthName($month) . ' ' . strval(intval($day)) . $this->GetDateSuffix($day);
			$this->_debug('next link text: ' . $link[4]);
			$this->DecrementDate($month, $day, $year, 1);
			
			/* SECURITY CHECK HERE */
			
			if ($this->simpleAccessLevel != 'user')
			{
				$this->_debug('granted edit permissions');
				# set management links (if authorized)
				$link[5] = '<a href="' . $this->calendarURL . 'selection=add&date=' . $month . ',' . $day . ',' . $year . '">New Event</a>';
				//$link[5] .= '<a href="#">Management Interface</a>';
			} else {
				$this->_debug('<strong>edit permission denied</strong>');
			}
			
			$this->_debug('retrieving calendar data');
			# get the days calender entries (if any)
			$data = $this->GetMonthData($month, $year);
			$this->_debug('locating data for the selected day');
			$entry = $this->arrFindObjects($data, 'day', $day);
			
			/* SECURITY CHECK HERE */
			if ($this->simpleAccessLevel != 'user')
			{
				$this->_debug('granted edit permission (2)');
				# Client Granted Edit Permissions
				if (is_array($entry)) {
					foreach ($entry as $key=>&$value) { $this->convertEntryToString($value, true); }
				} else {
					$this->_debug('entry was not an array');
					# NOT an array - call function once manually
					$this->convertEntryToString($entry, true);
				}
			} else {
				# Not Authorized for Editing
				$this->_debug('edit access denied');
				if (is_array($entry)) {
					# entry is an array, act accordingly
					$this->_debug('entry is an array');
					$tmp = sizeof($entry);	// cache size before running the loop
					$this->_debug('found ' . sizeof($entry) . ' entries for today');
					$this->_debug('entering foreach loop...', true);
					foreach ($entry as $key=>$value) {
						# have to use a foreach since the key values are unique id numbers and non incremental
						$this->_debug('[' . $key . '] ', true);
						$this->convertEntryToString($value);
						$entry[$key] = $value;
					}
						$this->_debug('done');
						$this->_debug('after convertEntryToString(...) there are ' . sizeof($entry) . ' entries');
				} else {
					# not an array, process as a single string
					$this->_debug('entry is <strong>NOT</strong> an array!');
					$this->convertEntryToString($entry);
				} // if is_array($entry))
				
				/* php 5 only */ 
				//foreach ($entry as $key->&$value) { $this->convertEntryToString($value, $key); }
				
			}
			
			# set the title for the day
			$day = $this->GetDateString($month,$day,$year);
			$this->_debug('day title set to ' . $day);
			
			# set data for output
			$this->data = array('day'=>$day, 'month'=>$month, 'year'=>$year, 'link'=>$link, 'events'=>&$entry);
			
			# initialize menu cache
			$menu_cache = '';
			
			# output the manager menu if we have > user rights
			if ($this->simpleAccessLevel != 'user') {
				$this->_debug('open manager/admin menu output file');
				$menu_cache = $this->_content('adminMenu');
			}
			
			return $this->_return($menu_cache . $this->_content('day'), 'including dsp_day.php...');
		} // outputDay
		
		public function outputMessage($text)
		// Output a message to the screen
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($text);
			
			$this->calendar_header();
			
			# if there is no message, silently return
			if ($text === '') {
				$this->_debug('<strong>Nothing to output!</strong>');
				return $this->_return(false);
			}
			
			# set data for output
			$this->data = array('text'=>$text);
			
			return $this->_return($this->_content('message'));
		}
			
		public function outputMonth($month = 0, $year = 0)
		// output specified month to the screen with optional group filter = value
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start($month . ',' . $year);
			
			$this->calendar_header();
			
			# ensure month/year are set
			if (($month == 0) && ($year == 0)) { $month = date('m'); $year = date('Y'); }
			$this->_debug('<strong>Pre Validation:</strong>');
			$this->_debug('&nbsp;&nbsp;$month=' . $month);
			$this->_debug('&nbsp;&nbsp;$year=' . $year);
			
			# ensure month is valid
			if ($month > 12) { $month = $month - 12; $year++; }
			if ($month < 1) { $month = $month + 12; $year--; }
			$this->_debug('<strong>Post Validation:</strong>');
			$this->_debug('&nbsp;&nbsp;$month=' . $month);
			$this->_debug('&nbsp;&nbsp;$year=' . $year);
			
			# determine how many days we need to output
			$sMonth = $month; $sDay = '1'; $sYear = $year;
			$this->GetFirstSunday($sMonth, $sDay, $sYear);
			$eMonth = $month; $eDay = '1'; $eYear = $year;
			$this->GetLastSaturday($eMonth, $eDay, $eYear);
			$temp = $this->GetDaysBetween( array($sMonth, $sDay, $sYear), array($eMonth, $eDay, $eYear) );
			$this->_debug('Processing ' . $temp . ' days');
			
			# for some reason the calculated days between for multiple months isn't accurage
			while (($temp % 7) != 0) { $this->_debug('adjusting +1'); $temp++; }
			
			# reset variables to pass to standard output prep function
			$start = array($month, '1', $year);
			$end = '';
			$day = '';
			$dayHeader = '';
			
			$this->_debug('Calling GetStandardOutput function...');
			# get standard output
			$this->GetStandardOutput($start, $end, $dayHeader, $day, 'startSunday', $temp);
			
			# set how many months forward/backward to display in navigation
			switch ($this->monthNavType) {
				case 'mNsimple':	$temp = 1; break;
				case 'mNdeuce':		$temp = 2; break;
				default:					$temp = 5; break;
			}
			$this->_debug('Outputting ' . $temp . ' month links in each direction');
			
			/* THIS NEEDS TO BE CONFIGURED */
			
			# select a graphic to display
			if ($this->monthHeaderPic['enabled']) {
				$this->_debug('monthHeaderPicEnabled = true');
				# since there is no dynamic graphic system implemented yet, use the defaults
				$headerImg['src'] = $this->monthHeaderPic['src'];
				$headerImg['width'] = $this->monthHeaderPic['width'];
				$headerImg['height'] = $this->monthHeaderPic['height'];
				$headerImg['alt'] = $this->monthHeaderPic['alt'];
			} else {
				$this->_debug('monthHeaderPicEnabled = false');
				$headerImg = false;
			}
			
			# set navigation links
			$nav[0] = $this->monthNavType;
			$this->_debug('&nbsp;&nbsp;$nav[0]=' . $nav[0]);
						
			# hide bottom navigation box pending check (below)
			$nav[2] = 'hidden';
			
			# if we're displaying more than two links, add the current month to the list
			if ($temp > 2) {
				$this->_debug('adding this month to the month nav list');
				$nav[1] = '<span title="Currently selected month">' . $this->GetMonthName($month) . '</span> | ';
			} else {
				$this->_debug('<strong>not</strong> adding this month to the month nav list');
				$nav[1] = '';
			}
			
			$this->_debug('adding months to the nav list...');
			# add months before/after
			for ($i=1;$i<$temp + 1;$i++) {
				# add a month before
				$nav[1] = '<a href="' . $this->calendarURL . 'selection=month&date=' . ($month - $i)
								. ',1,' . $year . '">' . $this->GetMonthName($month - $i) . '</a> | ' . $nav[1];
				# add a month after
				if ( ($temp > 1) && ($i > 1) ) { $nav[1] .= ' | '; }
				$nav[1] .= '<a href="' . $this->calendarURL . 'selection=month&date=' . ($month + $i)
								. ',1,' . $year . '">' . $this->GetMonthName($month + $i) . '</a>';
			}
			
			# if we're showing 11 months, add one more to the end for an even year
			if ($i == 6) {
				$nav[1] .= ' | <a href="' . $this->calendarURL . 'selection=month&date='
								. ($month + $i) . ',1,' . $year . '">' . $this->GetMonthName($month + $i)
								. '</a>'; 
			}
			
			# if this is the full year display (bottom), switch nav boxes
			if ($this->monthNavType == 'mNfullyearBottom') {
				$this->_debug('switching nav boxes to display the full year on the bottom');
				$nav[2] = $nav[0];
				$nav[3] = $nav[1];
				$nav[0] = 'hidden';
				$nav[1] = '';
			}
			
			# set date variables to pass to month display file
			$start['month'] = $this->GetMonthName($start[0]);
			$start['year'] = strval(intval($start[2]));
			$this->_debug('date values to pass to the month display file set:');
			$this->_debug('&nbsp;&nbsp;$start[\'month\']=' . $start['month']);
			$this->_debug('&nbsp;&nbsp;$start[\'year\']=' . $start['year']);
			
			# pass group filter display values
			$group = $this->groupDisplay;
			$this->_debug('group filer display value is ' . $group);
			
			# set data for output
			$this->data = array('start'=>$start, 'month'=>$month, 'year'=>$year, 'headerImg'=>$headerImg,
				'nav'=>$nav, 'dayHeader'=>$dayHeader, 'events'=>&$day);
			
			# initialize menu cache
			$menu_cache = '';
			
			# output the manager menu if we have > user rights
			if ($this->simpleAccessLevel != 'user') {
				$this->_debug('open manager/admin menu output file');
				$menu_cache = $this->_content('adminMenu');
			}
			
			return $this->_return($menu_cache . $this->_content('month'), 'including dsp_month.php...');
		} // outputMonth
		
		public function outputSevenDays($start_month = 0, $start_day = 0, $start_year = 0)
		// output seven days (in day form) starting on specified date
		{
			if ($this->configured === false) $this->configure();
			
			$this->calendar_header();
			$output = '';
			for ($i=1;$i<8;$i++) {
				$output .= $this->outputDay($start_month, $start_day, $start_year);
				$this->IncrementDate($start_month, $start_day, $start_year, 1);
			}
			return $output;
		} // outputSevenDays
		
		public function outputTwoWeeks($start_month = 0, $start_day = 0, $start_year = 0)
		// output two weeks
		{
			if ($this->configured === false) $this->configure();
			
			$this->calendar_header();
			$output = '';
			$output .= $this->outputWeek($start_month, $start_day, $start_year, true);
			$this->IncrementDate($start_month, $start_day, $start_year, 7);
			$output .= $this->outputWeek($start_month, $start_day, $start_year, false);
			return $output;
		} // outputTwoWeeks
		
		public function outputWeek($start_month = null, $start_day = null, $start_year = null, $showHeader = true)
		// output a week (7 days) to the screen starting at specified date
		{
			if ($this->configured === false) $this->configure();
			
			$this->_debug_start("start_month = $start_month, start_day = $start_day, start_year = $start_year");
			
			$this->calendar_header();
			
			# ensure we have a date set
			if (is_null($start_month)) $start_month = date('m');
			if (is_null($start_day)) $start_day = date('d');
			if (is_null($start_year)) $start_year = date('Y');
			
			$this->_debug("building week output starting on $start_year-$start_month-$start_day");
			
			# reset variables to pass to standard output prep function
			$start = array($start_month, $start_day, $start_year);
			$end = '';
			$day = '';
			$dayHeader = '';
			
			# get standard output
			$this->GetStandardOutput($start, $end, $dayHeader, $day, $this->oneweekMode);
			
			# set variables to pass to week display file
			$start['month'] = $this->GetMonthName($start[0]);
			$start['day'] = strval(intval($start[1]));
			$start['ending'] = $this->GetDateSuffix($start[1]);
			$end['month'] = $this->GetMonthName($end[0]);
			$end['day'] = strval(intval($end[1]));
			$end['ending'] = $this->GetDateSuffix($end[1]);
			$end['year'] = $end[2];
			
			# set link to month view
			$nav[0] = 'mNsimple';
			$nav[1] = '<a href="' . $this->calendarURL . 'selection=month&date=' .
				$start_month . ',1,' . $start_year . '" title="Open the full calendar">Full Schedule</a>';
			
			/* THIS NEEDS TO BE CONFIGURED */
			
			# select a graphic to display
			if ($this->weekHeaderPic['enabled']) {
				$this->_debug('weekHeaderPicEnabled = true');
				# since there is no dynamic graphic system implemented yet, use the defaults
				$headerImg['src'] = $this->weekHeaderPic['src'];
				$headerImg['width'] = $this->weekHeaderPic['width'];
				$headerImg['height'] = $this->weekHeaderPic['height'];
				$headerImg['alt'] = $this->weekHeaderPic['alt'];
			} else {
				$this->_debug('weekHeaderPicEnabled = false');
				$headerImg = false;
			}
			
			# set data for output
			$this->data = array('start'=>$start, 'end'=>$end, 'showHeader'=>$showHeader, 'headerImg'=>$headerImg,
				'nav'=>$nav, 'dayHeader'=>$dayHeader, 'events'=>&$day);
			
			return $this->_return($this->_content('week'), 'including dsp_week.php...');
		} // outputWeek
		
		
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		//  DATA RETRIEVAL FUNCTIONS
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		
		protected function GetMonthData ($month, $year, $ignoreRecurringEvents = false, $ignoreGroupSetting = false)
		# returns data from get month's events query
		{
			if (strlen($month) == 1) { $month = '0' . $month; }
			$query[0] = $month;
			$query[1] = $year;
			include ($this->queryPath . 'getMonthsEvents.php');
			
			if (! $ignoreRecurringEvents) {
				# include any recurring events for this month/year
				$data = $this->GetRecurringData();
				$this->processRecurringEvents($data, $month, $year);
			}
			
			//echo "prior to group check: " . count($query) . "<br />";
			
			# if a group ID is specified, remove non grouped events
			if ( (! $ignoreGroupSetting) && ($this->groupFilter > 0) ) {
				if (is_array($query)) { $this->arrRemoveNonMatching($query, 'group_id', trim($this->groupFilter)); }
			}
			
			//echo "following group check: " . count($query) . "<br />"; exit;
			
			if (@is_array($data)) {
				if (is_array($query)) {
					//return array_merge($query, $data);
					return $query + $data;
				} else {
					return $data;
				}
			} else {
				return $query;
			}
		} // GetMonthData
		
		protected function GetRecurringData($ignoreGroupSetting = false, $id = false)
		# returns data from get recurring events query
		{
			# if a group ID is specified, remove non grouped events
			if ( (! $ignoreGroupSetting) && ($this->groupFilter > 0) ) {
				$sKey = array('group_id');
				$sVal = array($this->groupFilter);
			} else {
				$sKey = '';
				$sVal = '';
			}
			
			if ($id !== false) {
				if (is_array($sKey)) {
					$sKey[] = 'id';
					$sVal[] = $id;
				} else {
					$sKey = array('id');
					$sVal = array($id);
				}
			}
			
			$r = $this->_tx->db->query('calendar_recurring_events', $sKey, $sVal, '*', true);
			
			return $r;
		} // GetRecurringData
		
		protected function retrieveTemplate($filename)
		# get template ($filename) and return contents
		{
			# expand file name to absolute path
			$filename = $this->templatePath . $filename;
			
			# ensure template file exists before continuing
			if (file_exists($filename))
			{	
				return file_get_contents($filename);
			} else {
				return false;
			}
		} // retrieveTemplate
		
		
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		//  INTERNAL SUPPORT FUNCTIONS
		//	--------------------------------------------------------------------------------------------
		//	--------------------------------------------------------------------------------------------
		
		/*	most support functions moved to a new class, calendar_supplement
		 *
		 */
		
		protected function arrFindObjects (&$haystack, $pos, $needle)
		# locate needle in haystack at array position $pos. return all results as array
		{
			# declare variables
			$remaining_haystack = array();
			$result = array();
			
			if (is_array($haystack))
			{
				foreach ($haystack as $key=>$searchArray)
				{
					if ($searchArray[$pos] == $needle) { $result[$key] = $searchArray; }
					else { $remaining_haystack[$key] = $searchArray; }
				}
			}
			
			# return vars not removed from haystack to haystack
			$haystack = $remaining_haystack;
			return $result;
		} // arrFindObjects
		
		protected function arrRemoveNonMatching (&$haystack, $pos, $needle)
		# locate needle in haystack at array position $pos, remove all non matching results
		{
			$result = array();
			if (is_array($haystack))
			{
				foreach ($haystack as $key=>$searchArray)
				{
					if ($searchArray[$pos] == $needle) { $result[$key] = $searchArray; }
					else { $remaining_haystack[$key] = $searchArray; }
				}
			}
			# overwrite haystack with the matching items
			$haystack = $result;
			return true;
		} // arrRemoveNonMatching
		
		protected function convertEntryToString (&$entry, $edit_access = false)
		# convert a single calendar entry to a string format to output
		{
			# get day template
			#$template = $this->retrieveTemplate('tmp_daySingleEntry.php');
			$template = '<h2>#EV_CAPTION#</h2><span class="details">#EV_DETAILS#</span><span class="description">#EV_DESCRIPTION#</span><span class="group" title="Event Grouping">#EV_GROUP#</span><span class="#EDIT_CLASS#">#EDIT_LINK#</span>';
			
			# ensure we have valid data
			if (! $template) { $entry = 'ERROR RETRIEVING TEMPLATE'; return false; }
			
			# prepare conditional variables
			$details = '';
			/* need to add security check here */
			#	$details .= '<a href="javascript:void;" onclick="confirmDelete(-1);">Delete</a>';
			#	$details .= '<a href="#">Edit</a>';
			/* end conditional for security check */
			if ($entry['location_id'] != '') {
				$tmp = $this->_tx->db->query('calendar_locations', array('id'), array($entry['location_id']), array('name'));
				if (db_qcheck($tmp)) $details .= $tmp[0]['name'] . ', ';
			}
			$details .= $entry['start_time'];
			if ($entry['end_time'] != '') { $details .= ' - ' . $entry['end_time']; }
			
			if ($entry['url'] != '') {
				$description = $entry['description'] . '<br /><a href="' . $entry['url'] . '">Link</a>';
			} else {
				$description = $entry['description'];
			}
			
			# replace template variables with actual data
			$template = str_replace('#EV_CAPTION#', $entry['caption'], $template);
			$template = str_replace('#EV_DETAILS#', $details, $template);
			$template = str_replace('#EV_DESCRIPTION#', $description, $template);
			
			if ($this->groupFilterEnabled) {
				$template = str_replace('#EV_GROUP#', $this->group[$entry['group_id']], $template);
			} else {
				$template = str_replace('#EV_GROUP#', '', $template);
			}
			
			# check if we have access to edit this item
			if ($edit_access !== false) {
				# Get the Date
				$date = substr($entry['month'], 4) . '/' . $entry['day'] . '/' . substr($entry['month'], 0, 4);
				# Permission Granted
				$linkText = '<a href="' . $this->calendarURL . 'selection=edit&amp;id=' 
															  . $entry['id'] . '">Edit</a> | <a href="javascript:confirmDelete(\'' 
															  . $entry['id'] . '\', \'' . $date . '\');">Delete</a>';
				$template = str_replace('#EDIT_CLASS#', 'editLink', $template);
				$template = str_replace('#EDIT_LINK#', $linkText, $template);
			} else {
				# No Management Access
				$template = str_replace('#EDIT_CLASS#', 'none', $template);
				$template = str_replace('#EDIT_LINK#', '', $template);
			}
			
			# return template with data
			$entry = $template;
		} // convertEntryToString
		
		protected function convertRecurringToStandard($rEventID,$rEvent,&$EventsArray,$date)
		/* given a single recurring event record ($rEvent) and its record ID ($rEventID) - add it as
		 * a standard event record into $EventsArray for the specified $date
		 */
		{
			# Check Exception List here
			if (strlen($rEvent['exceptions']) != 0)
			{
				# exceptions exist - check
				$exceptionList = explode(',', $rEvent['exceptions']);
				if (is_array($exceptionList)) {
					# multiple entries
					foreach ($exceptionList as $exception) {
						if ($exception == $date) { return false; }
					}
				} else {
					# one entry only
					if ($rEvent['exceptions'] == $date) { return false; }
				}
			} // if: exception check
					
			# get the date
			$date = explode('/', $date);
			
			# if the day value is only one character, add a leading 0
			if (strlen($date[1]) == 1) { $date[1] = '0' . $date[1]; }
			
			# transfer fields to a new event
			$newEvent['id'] = 'r' . $rEventID;
			$newEvent['day'] = $date[1];
			$newEvent['start_time'] = $rEvent['start_time'];
			$newEvent['end_time'] = $rEvent['end_time'];
			$newEvent['caption'] = $rEvent['caption'];
			$newEvent['link'] = @$rEvent['link'];
			$newEvent['description'] = $rEvent['description'];
			$newEvent['post_user'] = $rEvent['post_user'];
			$newEvent['post_date'] = $rEvent['post_date'];
			$newEvent['location_id'] = $rEvent['location_id'];
			$newEvent['group_id'] = $rEvent['group_id'];
			
			# add to the list
			$EventsArray['r' . $rEventID . ':' . $date[1]] = $newEvent;
			
			return true;
		} // convertRecurringToStandard
		
		protected function dateIsAWeekday($date)
		// return true if specified date (m/d/yyyy) is between monday and friday
		{
			# convert date into components
			$check = explode('/', $date);
			# get numerical representation of the day of the week
			$num = date( 'w', mktime(0,0,0,intval($check[0]),intval($check[1]),intval($check[2])) );
			if ( ($num != 0) && ($num != 6) ) { return true; } else { return false; }
		} // dateIsAWeekday
		
		protected function dateIsWithinRange($date, $start, $finish)
		/* if $start <= $date <= $finish return true, otherwise return false
		 * if the day value for $date equals '00' or evaluates to 0, check entire month
		 *
		 * VALUES IN THE FORM OF 'MM/DD/YYYY'
		 *
		 */
		{
			$this->_debug_start("Date: $date, Start: $start, Finish: $finish");
			
			# split each string into array components
			$date = explode('/', $date);
			$start = explode('/', $start);
			if (strlen($finish) > 0) $finish = explode('/', $finish);
			
			# set the absolute integer value for the check
			if (intval($date[1]) == 0) {
				# check entire month
				$checkMonthStart = date("U", mktime(0, 0, 0, $date[0], 1, $date[2]));
				$checkMonthEnd = date("U", mktime(0, 0, 0, ($date[0] + 1), 0, $date[2]));
			} else {
				# check specified date only
				$checkMonthStart = date("U", mktime(0, 0, 0, $date[0], $date[1], $date[2]));
				$checkMonthEnd = $checkMonthStart;
			}
						
			# set the absolute integer value for start and finish
			$checkStart = date("U", mktime(0, 0, 0, $start[0], $start[1], $start[2]));
			
			# if there is no end date specified, return true automatically
			if (! is_array($finish)) {
				$checkFinish = $checkMonthEnd + 1;
			} else {
				$checkFinish = date("U", mktime(0, 0, 0, $finish[0], $finish[1], $finish[2]));
			}
			
			# check condition -> is either the end of the range OR the beginning of the range between monthstart & end?
			if ( ( ($checkStart <= $checkMonthStart) && ($checkMonthStart <= $checkFinish) ) ||
					 ( ($checkStart <= $checkMonthEnd) && ($checkMonthEnd <= $checkFinish) ) )
			{
				return $this->_return(true);
			} else {
				return $this->_return(false);
			}
		} // dateIsWithinRange
		
		protected function DecodeMonthlyPattern(&$arrPattern, &$key, &$value, &$tempArr, &$month, &$year)
		/* given a 'monthly' encoded pattern as an array, return the day of the month
		 * it occurs on
		 */
		{			
			# ensure input is valid
			if (! is_array($arrPattern)) { return false; }
			# check how many elements are in the pattern array
			if (count($arrPattern) == 2)
			{
				# 2 elements indicates this event only occurs once/month on the specified date
				# this is a patterened month, add the event
				$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/" . $arrPattern[1] . "/$year");
			} else {
				# 3 elements indicates this event occurs on a specific type of day every X months
				if ($arrPattern[2] == '7') 
				{
					# occur on the [first/second/third/fourth/last] day of the month
					# pick the day
					if ($arrPattern[1] == '5')
					{
						# last day of the month
						$day = date('d', mktime(0, 0, 0, ($month + 1), 0, $year));
					} else {
						# occur on whatever day of the month specified
						$day = date('d', mktime(0, 0, 0, $month, intval($arrPattern[1]), $year));
					}
					$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$day/$year");
				} elseif ($arrPattern[2] == '8') {
					# occur on the [first/second/third/fourth/last] weekday of the month
					if ($arrPattern[1] == '5') {
						# occur on the last weekday of the month
						$day = date('d', mktime(0, 0, 0, ($month + 1), 0, $year));
						while (! ($this->dateIsAWeekday("$month/$day/$year"))) {
							$this->DecrementDate($month, $day, $year, 1);
						}
					} else {
						# occur on the specified weekday of the month
						$day = 1;
						# locate the first weekday of the month
						while (! ($this->dateIsAWeekday("$month/$day/$year")))
						{
							$this->IncrementDate($month, $day, $year, 1);
						}
						# now add days to get our desired date
						$i = intval($arrPattern[1]);
						while($i > 1) {
							$this->IncrementDate($month, $day, $year, 1);
							if ($this->dateIsAWeekday("$month/$day/$year")) $i--;
						}
					}
					$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$day/$year");
				} else {
					# occur on specified weekday name of the month (0 = sunday, 6 = saturday)
					switch ($arrPattern[2]) {
						case '0':	$dayName = 'Sunday'; break;
						case '1':	$dayName = 'Monday'; break;
						case '2':	$dayName = 'Tuesday'; break;
						case '3':	$dayName = 'Wednesday'; break;
						case '4':	$dayName = 'Thursday'; break;
						case '5':	$dayName = 'Friday'; break;
						default:	$dayName = 'Saturday'; break;
					}
					if ($arrPattern[1] == '5') {
						# occur on the last occurance of the month
						$day = $this->GetLast($dayName, $month, $year);
					} else {
						# occur on the specified weekday of the month (1 - 4)
						$day = $this->GetFirst($dayName, $month, $year);
						# now add 7 for every ($arrPattern[1] - 1) occurance
						$day += (7 * (intval($arrPattern[1]) - 1));
					}
					$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$day/$year");
				}
			}
		} // DecodeMonthlyPattern
		
		protected function DecrementDate(&$month, &$day, &$year, $subtraction)
		# decrements date (passed by reference) by $subtraction days
		{
			for ($i = 0; $i < $subtraction; $i++)
			{
				# decrement day
				$day--;
				# ensure day is valid and move to previous month/year if necessary
				if (!checkdate($month, $day, $year)) 
				{
					if ($month == 1) 
					{
						# if we're in January, move to December and decrement year
						$month = 12;
						$year--;
					} else {
						# otherwise just decrement the month
						$month--;
					}
					$day = date('d', mktime(0, 0, 0, intval($month) + 1, 0, intval($year)));
				} // if checkdate
			} // for
				
			if ($day < 10) { $day = '0' . strval(intval($day)); }
			if ($month < 10) { $month = '0' . strval(intval($month)); }
				
			return true;
		} // DecrementDate
		
		protected function GetDateString(&$month, &$day, &$year)
		// given a date, return a string in the form of "Monday, April 15th, 2006"
		{
			return $this->GetDayOfWeek($month,$day,$year) . ', ' . $this->GetMonthName($month) . ' '
						 . strval(intval($day)) . '<sup>' . $this->GetDateSuffix($day) . '</sup>, ' . $year;
		} // GetDateString
		
		protected function GetDayOfWeek(&$month, &$day, &$year)
		# returns the day of the week for specified date
		{
			return date('l', mktime(0, 0, 0, $month, $day, $year));
		} // GetDayOfWeek
		
		protected function GetDaysBetween($start, $end)
		# return the number of days between the start and end dates, inclusive
		{
			$start[0] = $this->GetMonthName($start[0]);
			$end[0] = $this->GetMonthName($end[0]);
			$temp = floor((strtotime("$end[1]-$end[0]-$end[2]") - strtotime("$start[1]-$start[0]-$start[2]"))/86400);
			return $temp;
		} // GetDaysBetween
		
		protected function GetDateSuffix($day) 
		# returns 'st', 'nd', 'rd', or 'th' based on day value
		{
			if (intval($day) == 1 || intval($day) == 21 || intval($day) == 31) { return 'st'; }
			if (intval($day) == 2 || intval($day) == 22) { return 'nd'; }
			if (intval($day) == 3 || intval($day) == 23) { return 'rd'; }
			return 'th';
		} // GetDateSuffix
		
		protected function GetFirst($dayName, &$month, &$year)
		# Given a month and year (by reference), find the first $dayName (e.g. 'Sunday')
		{
			# very basic error check
			if ($dayName == '') return false;
			# set the day name to the appropriate case
			$dayName = ucfirst(strtolower($dayName));
			# set the start day
			$day = 1;
			# find out what day we're starting on
			$temp = $this->GetDayOfWeek($month, $day, $year);
			# increment this day until we find $dayName
			while ($temp != $dayName)
			{
				$this->IncrementDate($month, $day, $year, 1);
				$temp = $this->GetDayOfWeek($month, $day, $year);
			}
			# return the day of the month
			return $day;
		} // GetFirst
		
		protected function GetFirstSunday(&$month, &$day, &$year)
		# Given a start date locate the first sunday before it (unless it is on a Sunday)
		{
			$temp = $this->GetDayOfWeek($month, $day, $year);
			
			while ($temp != "Sunday")
			{
				$this->DecrementDate($month, $day, $year, 1);
				$temp = $this->GetDayOfWeek($month, $day, $year);
			}
		} // GetFirstSunday
		
		protected function GetLast($dayName, &$month, &$year)
		# Given a month and year (by reference), find the last occurance of $dayName WITHIN the month
		{
			# very basic error check
			if ($dayName == '') return false;
			# set the day name to the appropriate case
			$dayName = ucfirst(strtolower($dayName));
			# set the last day of the month
			$day = intval(date('j', mktime(0, 0, 0, ($month + 1), 0, $year)));
			# find out what day we're starting on
			$temp = $this->GetDayOfWeek($month, $day, $year);
			# decrement this day until we find $dayName
			while ($temp != $dayName)
			{
				$this->DecrementDate($month, $day, $year, 1);
				$temp = $this->GetDayOfWeek($month, $day, $year);
			}
			# return the day of the month
			return $day;
		} // GetLast
		
		protected function GetLastSaturday(&$month, &$day, &$year)
		# Given a month and year, locate the first saturday after the end of the month
		{
			# get the last day of this month
			$day = date('d', mktime(0, 0, 0, intval($month) + 1, 0, intval($year)));
			
			# find out what day of the week it is
			$temp = $this->GetDayOfWeek($month, $day, $year);
			
			while ($temp != "Saturday")
			{
				$this->IncrementDate($month, $day, $year, 1);
				$temp = $this->GetDayOfWeek($month, $day, $year);
			}
		} // GetLastSaturday
		
		protected function GetMonthName($month)
		# returns the name of the specified integer $month value
		{
			return date("F", mktime(0, 0, 0, $month, 1, 2006));
		} // GetMonthname
		
		protected function GetMonthsBetween($start, $end)
		// return how many months (0+) are between the start and end dates (both input vars are arrays)
		{
			# preset result variable
			$result = 0;
			$negify = false;
			# convert input month and year to integer values
			$start[0] = intval($start[0]); $start[2] = intval($start[2]);
			$end[0] = intval($end[0]); $end[2] = intval($end[2]);
			# if start month/year and end month/year are equal, return 0 now
			if ( ($start[0] == $end[0]) && ($start[2] == $end[2]) ) { return 0; }
			# ensure we're always counting up
			if ( ( ($start[0] > $end[0]) && ($start[2] >= $end[2]) ) || ($start[2] > $end[2]) )
			{
				for ($i=0;$i<3;$i++)
				{
					array_push($end, array_shift($start));
					array_push($start, array_shift($end));
				}
				$negify = true;
			}
			# increment month by one until we get to the destination month
			while ( ($start[0] != $end[0]) || ($start[2] != $end[2]) )
			{
				# increment the month
				$start[0]++;
				$result++;
				# ensure month and year are valid
				if ($start[0] == 13) { /* increment year */ $start[0] = 1; $start[2]++; }
			}
			# now that we have the approximate difference in months, check exact difference by day value
			if (intval($start[1]) > intval($end[1])) { $result--; }
			if ($negify) { return ($result * -1); }
			return $result;
		} // GetMonthsBetween
		
		protected function GetStandardOutput(&$start, &$end, &$dayHeader, &$day, $startPoint, $number_of_days = 7)
		# using passed variables (by reference), put together standard week/month/whatever output
		{
			# initialize the end dates for our loops [ 0 => month , 1 => day , 2 => year ]
			$end[0] = $start[0];
			$end[1] = $start[1];
			$end[2] = $start[2];
			
			# determine start point, either 'startSunday' or 'startToday'
			if ($startPoint == 'startSunday') {
				# need to set our starting point backwards in time to the last sunday
				$this->GetFirstSunday($end[0], $end[1], $end[2]);
			}
			
			# declare variables
			$data = array();
			
			# loop through number of specified days and set output data for each
			for ($i = 0; $i < $number_of_days; $i++) {
				if ($i < 7)	{ /* set the day's header name */	$dayHeader[$i]= $this->GetDayOfWeek($end[0], $end[1], $end[2]);	}

				# set the date
				$day[$i][0] = strval(intval($end[1]));
				
				# ensure we have the proper data for this month
				if ( (count($data) == 0) || ($dataIsFor != ($end[0] . $end[2])) )
				# retrieve month's data
				{
					# retrieve the current month's data
					$data = $this->GetMonthData($end[0], $end[2]);
					$dataIsFor = $end[0] . $end[2];
				}
				
				# get events for this day
				$result = $this->arrFindObjects($data, 'day', $end[1]);
				
				# preset class for all items
				$day[$i][2] = 'day';
				
				# if result is an array, prepare it for use
				if (is_array($result)) {
					# move to the beginning of the array so we can just display the first element
					reset($result);
					# set a pointer for the first element in the array
					$bear = current($result);
				} else {
					$bear = '';
				}
				
				# if this is an array with more than one element, add "more" and js hover
				if ( (is_array($result)) && (sizeof($result) > 1) )
				{
					# multiple results found
					if ( ($bear['start_time'] == '00:00') && ($bear['end_time'] == '23:59') ) {
						$day[$i][1] = '<li>' . $bear['caption'] . '</li><li><em>(more)</em></li>';
					} else {
						$day[$i][1] = '<li>' . $bear['caption'] . '<span class="time">' . $bear['start_time'];
						if ($bear['end_time'] != '') {
							$day[$i][1] .= ' - ' . $bear['end_time'];
						}
						$day[$i][1] .= '</span></li><li><em>(more)</em></li>';
					}
					# assign an id
					$day[$i][4] = $end[2] . $end[0] . $end[1];
					# detail counter
					$detail_counter = 0;
					
					# assign detail data
					while (count($result) > 0) {
						if ( ($bear['start_time'] == '00:00') && ($bear['end_time'] == '23:59') ) {
							$day[$i][5] .= '<li';
							if ($detail_counter == 0) $day[$i][5] .= ' class="first"';
							if (count($result) == 1) $day[$i][5] .= ' class="last"';
							$day[$i][5] .= '>' . $bear['caption'] . '</li>';
						} else {
							@$day[$i][5] .= '<li';
							if ($detail_counter == 0) $day[$i][5] .= ' class="first"';
							if (count($result) == 1) $day[$i][5] .= ' class="last"';
							$day[$i][5] .= '>' . $bear['caption'] . ' <span class="time">' . $bear['start_time'];
							if ($bear['end_time'] != '') {
								$day[$i][5] .= ' - ' . $bear['end_time'];
							}
							$day[$i][5] .= '</span></li>';
						}
						array_shift($result);
						$bear = current($result);
						$detail_counter++;
					}
				} elseif (isset($bear['caption'])) {
					# one result found
					if ( ($bear['start_time'] == '00:00') && ($bear['end_time'] == '23:59') ) {
						$day[$i][1] = '<li class="first">' . $bear['caption'] . '</li><li class="last empty">&nbsp;</li>';
					} else {
						$day[$i][1] = '<li class="first">' . $bear['caption'] . '<span class="time">' . $bear['start_time'];
						if ($bear['end_time'] != '') {
							$day[$i][1] .= ' - ' . $bear['end_time'];
						}
						$day[$i][1] .= '</span></li><li class="last empty">&nbsp;</li>';
					}
					$day[$i][5] = $day[$i][1];
				} else {
					# no results found
					$day[$i][1] = '<li>&nbsp;</li>';
				}
				
				# set the class for this day
				if ($end[0] != $start[0]) {
					# this is not the current month
					$day[$i][2] .= ' off';
					# if this is the first/last day of a month insert month name
					if ((intval($end[1]) == 1) || ($i == 0))
					{
						$day[$i][1] .= '<span class="offMonth">' . $this->GetMonthName($end[0]) . '</span>';
					}
				}
				
				# add a class for items with events since CSS doesn't have parent selectors
				if (@strlen($day[$i][5]) > 0) $day[$i][2] .= ' events';
				
				# if this is today, add the today stamp to the class
				if (($end[0] . '-' . $end[1] . '-' . $end[2]) == date('m-d-Y'))	{ $day[$i][2] .= ' today'; }
				
				# add the date to the class
				$day[$i][2] .= ' date' . $end[1];

				# set the javascript value to pull for LoadDay function
				$day[$i][3] = "$end[0],$end[1],$end[2]";
				
				# move to the next day
				$this->IncrementDate($end[0], $end[1], $end[2], 1);
			}
			
			# now reduce end date by one so supplied output values (below) are accurate
			$this->DecrementDate($end[0], $end[1], $end[2], 1);
			
			//$temp = $this->PrepareOutput($this->GetEvents($end_day, $end_month, $end_year, $_REQUEST['group_filter']), $a);
			
			return true;
		} // GetStandardOutput
		
		protected function IncrementDate(&$month, &$day, &$year, $addition)
		# increments date (passed by reference) by $addition days
		{
			for ($i = 0; $i < $addition; $i++) 
			{
				# increment day
				$day++;
				# ensure day is valid and move to next month/year if necessary
				if (!checkdate($month, $day, $year)) 
				{
					if ($month == 12) 
					{
						# if were in december, move to Janurary and increment year
						$month = 1;
						$year++;
					} else {
						# otherwise just increment the month
						$month++;
					} // if month = 12
					$day = 1;
				} // if checkdate
			} // for
				
			if ($day < 10) { $day = '0' . strval(intval($day)); }
			if ($month < 10) { $month = '0' . strval(intval($month)); }
				
			return true;
		} // IncrementDate
		
		public function index($selection = false)
		/* output the calendar index page
		 *
		 */
		{
			if ($this->configured === false) $this->configure();
			
			require_once('query/qry_mapMyPermissions.php');
			
			if ($selection === false) {
				$selection = $this->_tx->cms->get_args();
			}
			
			# configure url reference to this page
			define('CAL_INDEX', url('calendar'));
			
			return $this->_content('index');
		}
		
		protected function processRecurringEvents(&$data, $month, $year)
		// Given array $data, convert recurring events into standard month data format for $month/$year
		{
			/* VARIABLES: $tempArr, $data, $value, $month, $year */
			
			# initialize variables
			$checkNumberOfOccurances = false;
			$tempArr = '';
			
			if (! is_array($data)) { return false; }
			
			# process and check each recurring event
			foreach($data as $key=>$value) {
				# determine if this event is valid for the specified month/year before doing anything else
				$start_date = date('m/d/Y', strtotime($value['start_date']));
				if (! is_null($value['end_date'])) {
					$end_date = @date('m/d/Y', strtotime($value['end_date']));
				} else {
					$end_date = null;
				}
				
				if ( $this->dateIsWithinRange($month . '/00/' . $year, $start_date, $end_date) ) {
					switch ($value['type']) {
						case 'daily':
							# pattern will either be 'weekdays' or integer as string -> every X days
							# get the number of days in the month (-> number of iterations to check)
							$iterations = date('t', mktime(0,0,0,$month,1,$year));
							for ($i=1;$i<=$iterations;$i++) {
								# for each day in the month, check if the pattern occurs on this day
								if ($value['pattern'] == 'weekdays') {
									# occur every weekday
									if ( ($this->dateIsAWeekday("$month/$i/$year")) &&
											 ($this->dateIsWithinRange("$month/$i/$year", $start_date, $end_date)) )
									{
										# add event
										$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$i/$year");
									}
								} else {
									# pattern is every X days
									# find how many days have elapsed since the beginning of the pattern
									$position = $this->GetDaysBetween(explode('/', $start_date), array($month, $i, $year));
									# if today is a patterned day and we're within the range, output this event
									if ( (($position % intval($value['pattern'])) == 0) &&
											 ($this->dateIsWithinRange("$month/$i/$year", $start_date, $end_date)) )
									{
										# add event
										$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$i/$year");
									}
								}
							} // for
							break;
						case 'weekly':
							# pattern is "(every X weeks), (numerical representation of the days of the week)"
							# for example, if pattern is every two weeks on monday and wednesday: "2,13"
							$pattern = explode(',', $value['pattern']);
							# get the number of days in the month (-> number of iterations to check)
							$iterations = date('t', mktime(0,0,0,$month,1,$year));
							for ($i=1;$i<=$iterations;$i++) {
								# find how many days have elapsed since the beginning of the pattern
								$position = $this->GetDaysBetween(explode('/', $start_date), array($month, $i, $year));
								# convert this value to weeks
								$position = intval($position / 7);
								# check if this is a patterned week and we're within the range
								if ( (($position % intval($pattern[0])) == 0) &&
										 ($this->dateIsWithinRange("$month/$i/$year", $start_date, $end_date)) )
								{
									# YES - now is today one of the specified days?
									if ( strpos($pattern[1], strval(date('w', mktime(0,0,0,$month,$i,$year)))) !== false ) {
										# add event
										$this->convertRecurringToStandard($value['id'],$value,$tempArr,"$month/$i/$year");
									}
								}
							}
							break;
						case 'monthly':
							/* pattern is "(every X months), (integer day value OR [1-5],[0-8])"
							 *		where [1-5] equals (first = 1, second = 2, ... , last = 5) AND
							 *		[0-8] equals (sunday = 0, monday = 1, ... , saturday = 6, day = 7, weekday = 8)
							 *		e.g. if pattern is every second sunday every 3 months: "3,2,0" OR
							 *				 if pattern is every 2 months on the 20th: "2,20"
							 */
							$pattern = explode(',', $value['pattern']);
							# find how many months have elapsed since the beginning of the pattern
							$position = $this->GetMonthsBetween(explode('/', $start_date), array($month, '31', $year));
							# check if this is a patterened month
							if ( ($position % intval($pattern[0])) == 0) {
								$this->DecodeMonthlyPattern($pattern, $key, $value, $tempArr, $month, $year);
							}
							break;
						case 'yearly':
							/*	pattern as: "(month of the year), (day of the month OR [1-5],[0-8])"
							 *		where [1-5] equals (first = 1, second = 2, ... , last = 5) AND
							 *		[0-8] equals (sunday = 0, monday = 1, ... , saturday = 6, day = 7, weekday = 8)
							 *		i.e. if pattern is every february 2nd: "2,2" OR
							 *				 if pattern is every second weekday of march: "3,2,7"
							 */
							$pattern = explode(',', $value['pattern']);
							# check if this month is the specified month
							if ($pattern[0] == $month) {
								$this->DecodeMonthlyPattern($pattern, $key, $value, $tempArr, $month, $year);
							} // if pattern 0 is month
							break;
					} // switch
				} // if dateIsWithinRange
			} // foreach
			
			# set new value for data
			$data = $tempArr;
			
			# return success
			return true;
		} // processRecurringEvents
	}
	
	#	------------------------------------
	#	New SQL Compatible File Operations
	# ------------------------------------
	
	function sWriteFile($filename, $contents, $commit = true, $path = ROOT_PATH)
	/* write file in serialized data format 
	 * (to simulate a table for future transition to SQL)
	 *		$filename = name of file to write
	 *		$contents = data to write to the file (any data type is valid EXCEPT NULL)
	 *		$commit = optional boolean value indicating whether or not to write
	 *	 						to the file or to the cache (true = directly to file)
	 *		$path = optional value to specify a non-standard root path
	 *
	 * Version 1.0 - 2006.03.28 - William Strucke [wstrucke@gmail.com]
	 */
	{
		global $cache;
		
		if (! $commit)
		/* if $commit is false, only update the cache and exit the function */
		{ $cache[$path . $filename] = $contents; return true; }
			
		if (isset($cache[$path . $filename]))
		/* if a cache value is set for this file, clear it prior to updating */
		{	unset ($cache[$path . $filename]); }
		
		# open a handle to the specified file
		$file = fopen($path . $filename, 'w');
		
		# write the serialized data
		$return_value = fwrite($file, serialize($contents));
		
		# close the file
		fclose($file);
		
		# return the result of the write operation
		return $return_value;
	}
	
	function sGetFile($filename, &$data, $utilizeCache = true, $path = ROOT_PATH)
	/* open file $filename in serialized data format and return unserialized data
	 * (to simulate a table for future transition to SQL)
	 *		$filename = name of the file to read
	 *		$data = the variable the file contents will be extracted to
	 *		$utilizeCache = a boolean value indicating whether or not to use the
	 *				cache if it's set for this file (true -> yes)
	 *		$path = optional value to specify a non-standard root path
	 *
	 * Version 1.0 - 2006.03.28 - William Strucke [wstrucke@gmail.com]
	 */
	{
		global $cache;
		
		if ( $utilizeCache && (isset($cache[$path . $filename])) )
		/* if $utilizeCache is true and a cache value is set for this file,
		 * return the cache value
		 */
		{	return $cache[$path . $filename]; }
		
		if (! file_exists($path . $filename))
		/* if the file does not exist return empty string */
		{
			$data = '';
			return false;
		}
		
		# get the file data and unserialize it
		$data = unserialize(file_get_contents($path . $filename));
		
		# write the new cache value
		$cache[$path . $filename] = $data;
		
		# return success
		return true;
	}
 
?>