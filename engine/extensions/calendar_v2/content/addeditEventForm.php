<?php
 /*	Calendar Class
 	*
	*	Display add a new event form, if variables (below) are set, pre-load them into 
	* the fields.
	*
	*	Version 1.0 : 2006.04.25, 2006.07.21, 2006.08.20
	*	William Strucke [wstrucke@gmail.com]
	*
	* VALID POST DATA:
	*		event date		$_REQUEST['date'] overrides $data['event']['date'] -> "mm/dd/yyyy"
	*		
	*/
	
	# Load Calendar Data
	$data = $t->get->calendar_data;
	
	# set initial event date based on post/get data
	if (isset($_REQUEST['date'])) {
		$date = str_replace(',', '/', $_REQUEST['date']);
	} elseif (isset($data['event']['startDate'])) {
		$date = $data['event']['startDate'];
	} elseif ( (isset($data['event']['day'])) && (isset($_REQUEST['id'])) ) {
		# get the date from the second part of the posted id
		$id = $_REQUEST['id'];
		if (strlen($data['event']['day'])==1) $data['event']['day'] = '0' . $data['event']['day'];
		$date = substr($data['event']['month'], 4) . '/' . $data['event']['day'] . '/' .substr($data['event']['month'], 0, 4);
	} else {
		$date = date('m/d/Y');
	}
	
	# set default field values
	$everyXdays = '1';
	$everyXweeks = '1';
	$everySunday = '';
	$everyMonday = '';
	$everyTuesday = '';
	$everyWednesday = '';
	$everyThursday = '';
	$everyFriday = '';
	$everySaturday = '';
	$startDateArray = explode('/', $date);
	$noEndDate = '';
	$endAfter = '';
	$endDate = date('m/d/Y', mktime(0, 0, 0, intval($startDateArray[0]) + 1, intval($startDateArray[1]), intval($startDateArray[2])));
	$monthlyOptionA = '';
	$monthlyOptionADayValue = '1';
	$monthlyOptionAMonthValue = '1';
	$monthlyOptionB = '';
	$monthlyOptionBValueA = '';
	$monthlyOptionBValueB = '';
	$monthlyOptionBValueC = '1';
	$yearlyOptionA = '';
	$yearlyOptionAValueA = '';
	$yearlyOptionAValueB = '';
	$yearlyOptionB = '';
	$yearlyOptionBValueA = '';
	$yearlyOptionBValueB = '';
	$yearlyOptionBValueC = '';
	$caption = '';
	$bold = '';
	$italic = '';
	$underline = '';
	$location = '';	// add AJAX feature here!!
	$description = 'Type the event details here.';	// do not change this default value without changing the javascript as well
	$link = '';
	
	# set appropriate post value
	if (isset($data['event'])) {
		$selection = 'postEdit'; 
		$heading = 'Edit an event';
		$caption = $data['event']['caption'];
		
		/* TEMPORARY -- WE NEED TO USE A WHILE LOOP AND RegExp to extract matching tags, 
				then add them to the captionAttributes[] array as ' checked ' */
		if (stripos($caption, '<strong>') !== false) { $bold = 'checked'; }
		if (stripos($caption, '<em>') !== false) { $italic = 'checked'; }
		if (stripos($caption, '<u>') !== false) { $underline = 'checked'; }
		/* END TEMPORARY CODE */
		
		$location = $data['event']['location'];
		$group[intval($data['event']['group'])] = ' selected ';
		$description = $data['event']['description'];
		$link = $data['event']['url'];
	} else {
		$selection = 'postAdd';
		$heading = 'Add a new event';
	}
	
	# if we are editing a recurring event, set the appropriate values to display
	if (isset($data['event']['type']))
	{
		switch ($data['event']['type'])
		{
			case 'daily':
				$patternDaily = ' selected ';
				if ($data['event']['pattern'] == 'weekdays')
				{
					unset($everyXdays);
				} else {
					$everyXdays = $data['event']['pattern'];
				}
				break;
			case 'weekly':
				$patternWeekly = ' selected ';
				$pattern = explode(',', $data['event']['pattern']);
				$everyXweeks = $pattern[0];
				if (strpos($pattern[1], '0') !== false) { $everySunday = 'checked'; }
				if (strpos($pattern[1], '1') !== false) { $everyMonday = 'checked'; }
				if (strpos($pattern[1], '2') !== false) { $everyTuesday = 'checked'; }
				if (strpos($pattern[1], '3') !== false) { $everyWednesday = 'checked'; }
				if (strpos($pattern[1], '4') !== false) { $everyThursday = 'checked'; }
				if (strpos($pattern[1], '5') !== false) { $everyFriday = 'checked'; }
				if (strpos($pattern[1], '6') !== false) { $everySaturday = 'checked'; }
				break;
			case 'monthly':
				$patternMonthly = ' selected ';
				$pattern = explode(',', $data['event']['pattern']);
				if (sizeof($pattern) == 2)
				{
					$monthlyOptionA = 'checked';
					$monthlyOptionADayValue = $pattern[0];
					$monthlyOptionAMonthValue = $pattern[1];
				} else {
					$monthlyOptionB = 'checked';
					$monthlyOptionBValueA[$pattern[1]] = ' selected ';
					$monthlyOptionBValueB[$pattern[2]] = ' selected ';
					$monthlyOptionBValueC = $pattern[0];
				}
				break;
			case 'yearly':
				$patternYearly = ' selected ';
				$pattern = explode(',', $data['event']['pattern']);
				if (sizeof($pattern) == 2)
				{
					$yearlyOptionA = 'checked';
					$yearlyOptionAValueA[intval($pattern[0])] = ' selected ';
					$yearlyOptionAValueB = $pattern[1];
				} else {
					$yearlyOptionB = 'checked';
					$yearlyOptionBValueA[intval($pattern[1])] = ' selected ';
					$yearlyOptionBValueB[intval($pattern[2])] = ' selected ';
					$yearlyOptionBValueC[intval($pattern[0])] = ' selected ';
				}
				break;
		} // switch
		# Set the range
		$endDate = $data['event']['endDate'];
		if ($endDate == '') { $noEndDate = 'checked'; }
	}
	
?>

	<form name="calendarAddEvent" id="calendarAddEvent" class="calendarForm" action="<?php echo CAL_INDEX; ?>" method="post">
	<div><!-- required for FX -->	
		<input type="hidden" name="selection" value="<?php echo $selection; ?>" />
		
		<?php
		# if we are editing an existing event, add the internal id to a hidden field here
		if (isset($data['event'])) {
			echo '<input type="hidden" name="id" value="' . $_REQUEST['id'] . '" />' . "\r\n";
		}
		?>

		<div class="heading"><?php echo $heading; ?></div>
		
		<span class="footerInfo"><em><sup>*</sup> fields are required to save an event.</em></span>
		
		<a href="#" id="btn_saveEvent">Save This Event</a>
		<a href="<?php echo url('calendar'); ?>&amp;selection=day&date=<?php echo str_replace('/', ',', $date); ?>" id="cancel">Cancel</a>
		
		<h1 class="heading">Details</h1>
		
		<div class="split_row_left">
			Event Date (mm/dd/yyyy)<sup>*</sup>: <input type="text" name="date" id="date" class="small" value="<?php echo $date; ?>" />
		</div>
		<div class="split_row_right">
			Options:
			<input type="checkbox" id="timeCheckBox" name="sTimeAllDay" class="check" accesskey="a"
<?php
			if ( (@$data['event']['start_time'] == '00:00') && ($data['event']['end_time'] == '23:59') ) { echo 'checked="checked"' . "\r\n"; }
?>
				value="1" /><span style="text-decoration: underline;">A</span>ll Day Event
			
			<input type="checkbox" id="recurringCheckBox" name="recurring" class="check" accesskey="r"
<?php
			if (isset($data['event']['type'])) { echo 'checked="checked"' . "\r\n"; }
?>
				value="1" /><span style="text-decoration: underline;">R</span>ecurring Event
		</div>
		
		<div id="time">
			<div class="split_row_left">
				Start Time<sup>*</sup>: <input type="text" name="sTime" class="small" value="<?php echo @$data['event']['start_time']; ?>" />
			</div>
			<div class="split_row_right">
				End Time (optional): <input type="text" name="eTime" class="small" value="<?php echo @$data['event']['end_time']; ?>" />
			</div>
		</div>
		
		<div id="recurring">
			<hr />
			<div class="split_row_left">
				Recurrance Pattern:
				<select name="recurrance_pattern" id="recurrance_pattern">
					<option value="daily"<?php echo @$patternDaily; ?>>Daily</option>
					<option value="weekly"<?php echo @$patternWeekly; ?>>Weekly</option>
					<option value="monthly"<?php echo @$patternMonthly; ?>>Monthly</option>
					<option value="yearly"<?php echo @$patternYearly; ?>>Yearly</option>
				</select>
			</div>
			
			<div id="recurringDayContainer">
				<div class="split_row_right"> <!-- id="recurrance_pattern_details" -->
					<input type="radio" class="radio" name="daily" value="0" <?php if (isset($everyXdays)) { echo 'checked'; } ?> />Every 
					<input type="text" class="superSmall" name="daily_pattern" value="<?php echo $everyXdays; ?>" /> day(s).
				</div>
				<div class="split_row_right">
					<input type="radio" class="radio" name="daily" value="1" <?php if (! isset($everyXdays)) { echo 'checked'; } ?> />Every weekday.
				</div>
			</div>
			
			<div id="recurringWeekContainer">
				<div class="split_row_right">
					Recur every <input type="text" class="superSmall" name="weekly_pattern" value="<?php echo $everyXweeks; ?>" /> week(s) on:
				</div>
				<div class="split_row_right">
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="sunday" <?php echo $everySunday; ?> />Sunday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="monday" <?php echo $everyMonday; ?>  />Monday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="tuesday" <?php echo $everyTuesday; ?>/>Tuesday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="wednesday" <?php echo $everyWednesday; ?> />Wednesday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="thursday" <?php echo $everyThursday; ?> />Thursday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="friday" <?php echo $everyFriday; ?> />Friday
					<input type="checkbox" class="check" name="weekly_pattern2[]" value="saturday" <?php echo $everySaturday; ?> />Saturday
				</div>
			</div>
			
			<div id="recurringMonthContainer">
				<div class="split_row_right">
					<input type="radio" class="radio" name="monthly" value="0" <?php echo $monthlyOptionA; ?> />Day 
					<input type="text" class="superSmall" name="monthly_pattern" value="<?php echo $monthlyOptionADayValue; ?>" /> of every
					<input type="text" class="superSmall" name="monthly_pattern2" value="<?php echo $monthlyOptionAMonthValue; ?>" /> month(s).
				</div>
				<div class="split_row_right">
					<input type="radio" class="radio" name="monthly" value="1" <?php echo $monthlyOptionB; ?> />The
					<select name="monthly_pattern3">
						<option value="first"<?php echo @$monthlyOptionBValueA[1]; ?>>first</option>
						<option value="second"<?php echo @$monthlyOptionBValueA[2]; ?>>second</option>
						<option value="third"<?php echo @$monthlyOptionBValueA[3]; ?>>third</option>
						<option value="fourth"<?php echo @$monthlyOptionBValueA[4]; ?>>fourth</option>
						<option value="last"<?php echo @$monthlyOptionBValueA[5]; ?>>last</option>
					</select>
					<select name="monthly_pattern4">
						<option value="day"<?php echo @$monthlyOptionBValueB[7]; ?>>day</option>
						<option value="weekday"<?php echo @$monthlyOptionBValueB[8]; ?>>weekday</option>
						<option value="sunday"<?php echo @$monthlyOptionBValueB[0]; ?>>Sunday</option>
						<option value="monday"<?php echo @$monthlyOptionBValueB[1]; ?>>Monday</option>
						<option value="tuesday"<?php echo @$monthlyOptionBValueB[2]; ?>>Tuesday</option>
						<option value="wednesday"<?php echo @$monthlyOptionBValueB[3]; ?>>Wednesday</option>
						<option value="thursday"<?php echo @$monthlyOptionBValueB[4]; ?>>Thursday</option>
						<option value="friday"<?php echo @$monthlyOptionBValueB[5]; ?>>Friday</option>
						<option value="saturday"<?php echo @$monthlyOptionBValueB[6]; ?>>Saturday</option>
					</select>
					of every <input type="text" class="superSmall" name="monthly_pattern5" value="<?php echo @$monthlyOptionBValueC; ?>" /> month(s).
				</div>
			</div>
			
			<div id="recurringYearContainer">
				<div class="split_row_right">
					<input type="radio" class="radio" name="yearly" value="0" <?php echo $yearlyOptionA; ?> />Every
					<select name="yearly_pattern">
						<option value="january"<?php echo @$yearlyOptionAValueA[1]; ?>>January</option>
						<option value="february"<?php echo @$yearlyOptionAValueA[2]; ?>>February</option>
						<option value="march"<?php echo @$yearlyOptionAValueA[3]; ?>>March</option>
						<option value="april"<?php echo @$yearlyOptionAValueA[4]; ?>>April</option>
						<option value="may"<?php echo @$yearlyOptionAValueA[5]; ?>>May</option>
						<option value="june"<?php echo @$yearlyOptionAValueA[6]; ?>>June</option>
						<option value="july"<?php echo @$yearlyOptionAValueA[7]; ?>>July</option>
						<option value="august"<?php echo @$yearlyOptionAValueA[8]; ?>>August</option>
						<option value="september"<?php echo @$yearlyOptionAValueA[9]; ?>>September</option>
						<option value="october"<?php echo @$yearlyOptionAValueA[10]; ?>>October</option>
						<option value="november"<?php echo @$yearlyOptionAValueA[11]; ?>>November</option>
						<option value="december"<?php echo @$yearlyOptionAValueA[12]; ?>>December</option>
					</select>
					<input type="text" class="superSmall" name="yearly_pattern2" value="<?php echo @$yearlyOptionAValueB; ?>" />
				</div>
				<div class="split_row_right">
					<input type="radio" class="radio" name="yearly" value="1" <?php echo @$yearlyOptionB; ?> />The
					<select name="yearly_pattern3">
						<option value="first"<?php echo @$yearlyOptionBValueA[1]; ?>>first</option>
						<option value="second"<?php echo @$yearlyOptionBValueA[2]; ?>>second</option>
						<option value="third"<?php echo @$yearlyOptionBValueA[3]; ?>>third</option>
						<option value="fourth"<?php echo @$yearlyOptionBValueA[4]; ?>>fourth</option>
						<option value="last"<?php echo @$yearlyOptionBValueA[5]; ?>>last</option>
					</select>
					<select name="yearly_pattern4">
						<option value="day"<?php echo @$yearlyOptionBValueB[7]; ?>>day</option>
						<option value="weekday"<?php echo @$yearlyOptionBValueB[8]; ?>>weekday</option>
						<option value="sunday"<?php echo @$yearlyOptionBValueB[0]; ?>>Sunday</option>
						<option value="monday"<?php echo @$yearlyOptionBValueB[1]; ?>>Monday</option>
						<option value="tuesday"<?php echo @$yearlyOptionBValueB[2]; ?>>Tuesday</option>
						<option value="wednesday"<?php echo @$yearlyOptionBValueB[3]; ?>>Wednesday</option>
						<option value="thursday"<?php echo @$yearlyOptionBValueB[4]; ?>>Thursday</option>
						<option value="friday"<?php echo @$yearlyOptionBValueB[5]; ?>>Friday</option>
						<option value="saturday"<?php echo @$yearlyOptionBValueB[6]; ?>>Saturday</option>
					</select>
					of
					<select name="yearly_pattern5">
						<option value="january"<?php echo @$yearlyOptionBValueC[1]; ?>>January</option>
						<option value="february"<?php echo @$yearlyOptionBValueC[2]; ?>>February</option>
						<option value="march"<?php echo @$yearlyOptionBValueC[3]; ?>>March</option>
						<option value="april"<?php echo @$yearlyOptionBValueC[4]; ?>>April</option>
						<option value="may"<?php echo @$yearlyOptionBValueC[5]; ?>>May</option>
						<option value="june"<?php echo @$yearlyOptionBValueC[6]; ?>>June</option>
						<option value="july"<?php echo @$yearlyOptionBValueC[7]; ?>>July</option>
						<option value="august"<?php echo @$yearlyOptionBValueC[8]; ?>>August</option>
						<option value="september"<?php echo @$yearlyOptionBValueC[9]; ?>>September</option>
						<option value="october"<?php echo @$yearlyOptionBValueC[10]; ?>>October</option>
						<option value="november"<?php echo @$yearlyOptionBValueC[11]; ?>>November</option>
						<option value="december"<?php echo @$yearlyOptionBValueC[12]; ?>>December</option>
					</select>
				</div>
			</div>
			
			<hr />
			<div class="split_row_left">
				Range of recurrance:
			</div>
			<div class="split_row_right">
				<input type="radio" class="radio" name="recurrance_range_end" value="0" <?php echo $noEndDate; ?> />No end date
			</div>
			<div class="split_row_right">
				<input disabled type="radio" class="radio" name="recurrance_range_end" value="1" <?php if (strlen($endAfter) > 0) { echo 'checked'; } ?> />End after 
				<input disabled type="text" class="superSmall" name="recurrance_range_end_after" value="<?php echo $endAfter; ?>" /> occurrences
			</div>
			<div class="split_row_right">
				<input type="radio" class="radio" name="recurrance_range_end" value="2" <?php if (strlen($endDate) > 0) { echo 'checked'; } ?> />End by:
				<input type="text" class="small" name="recurrance_range_end_by" value="<?php echo $endDate; ?>" /> 
				<a href="javascript:emT();" 
					 onclick="cal1x.select(document.forms[0].recurrance_range_end_by,'anchor3x','MM/dd/yyyy'); return false;" 
					 name="anchor3x" id="anchor3x">select</a>
			</div>
			<hr />
			<br />
		</div>
		
		<div class="split_row_left">
			Caption<sup>*</sup>: <input type="text" name="caption" class="medium" value="<?php echo @$caption; ?>" />
		</div>
		<div class="split_row_right">
			Caption Attributes:
			<input type="checkbox" name="captionAttributes[]" class="check" value="strong" <?php echo @$captionAttributes['strong']; ?> /> <strong>Bold</strong>
			<input type="checkbox" name="captionAttributes[]" class="check" value="em" <?php echo @$captionAttributes['em']; ?> /> <em>Italic</em>
			<input type="checkbox" name="captionAttributes[]" class="check" value="u" <?php echo @$captionAttributes['u']; ?> /> <u>Underline</u>
		</div>
		
		<div class="split_row_left">
			Location: <input type="text" name="location" class="medium" onclick="" value="<?php echo $location; ?>" />
		</div>
		<div class="split_row_right">
			Grouping:
			<select name="group">
			<?php
				foreach ($t->calendar->group as $key=>$val) {
					echo "\t" . '<option value="' . $key . '"' . @$group[$key] . '>' . $val . '</option>';
				}
			?>
			
			</select>
		</div>
		
		<div class="row">
			<span>Event Description:</span>
			<textarea name="desc" rows="6" onclick="if (this.value=='Type the event details here.') { this.value=''; }"><?php echo $description; ?></textarea>
		</div>
		
		<div class="row">
			<span>Related/Link URL:</span>
			<input type="text" name="link" class="long" value="<?php echo $link; ?>" />
		</div>
	<?php /*
		<h1>Security Restrictions</h1>
		
		<div class="split_row_left">
			<em>To be implemented...</em>
		</div>
		<div class="split_row_right">
		</div>
	*/ ?>
		
		<div style="clear:both;height:0.1em;">&nbsp;</div>
	</div>
	</form>