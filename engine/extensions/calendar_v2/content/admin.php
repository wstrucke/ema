<?php
 /*	Calendar Class
 	*
	*	Display administrative interface page
	*
	*	Version 1.0 : 2006.04.25
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
?>

	<form name="calendarMgmt" id="calendarMgmt" class="calendarForm" action="<?php echo CAL_INDEX; ?>" method="post">
		<input type="hidden" name="selection" value="postAdmin" />
		<div class="heading">Calendar Administrative Interface</div>

		<span class="footerInfo"><?php echo $t->calendar->version; ?></span>
		
		<h1>Primary Configuration</h1>
		
		<div class="row">
			<span>Calendar Root Path:</span>
			<input type="text" name="rootPath" class="long" value="<?php echo $t->calendar->rootPath; ?>" />
		</div>
		
		<div class="row">
			<span>Calendar (index) URL:</span>
			<input type="text" name="calendarURL" class="long" value="<?php echo $t->calendar->calendarURL; ?>" />
		</div>
		
		<h1>Display Settings</h1>
		
		<div class="row">
			<span>Day Border Style:</span>
			<select name="weekdayBorderStyle">
				<option 
					<?php if ($t->calendar->weekdayBorderStyle=='solid') { echo 'selected '; } ?>
					value="solid">Solid Line</option>
				<option 
					<?php if ($t->calendar->weekdayBorderStyle=='dropshadow') { echo 'selected '; } ?>
					value="dropshadow">Drop Shadow</option>
				<option 
					<?php if ($t->calendar->weekdayBorderStyle=='dotted') { echo 'selected '; } ?>
					value="dotted">Dotted Line</option>
				<option 
					<?php if ($t->calendar->weekdayBorderStyle=='dashed') { echo 'selected '; } ?>
					value="dashed">Dashed Line</option>
				<option 
					<?php if ($t->calendar->weekdayBorderStyle=='none') { echo 'selected '; } ?>
					value="none">No Border/None</option>
			</select>
		</div>
		
		<div class="row">
			<span>Weekday Position of Date:</span>
			<select name="weekdayDatePosition">
				<option 
					<?php if ($t->calendar->weekdayDatePosition == 'top-left') { echo 'selected '; } ?>
					value="top-left">Top Left</option>
				<option 
					<?php if ($t->calendar->weekdayDatePosition == 'top-right') { echo 'selected '; } ?>
					value="top-right">Top Right</option>
				<option 
					<?php if ($t->calendar->weekdayDatePosition == 'bottom-left') { echo 'selected '; } ?>
					value="bottom-left">Bottom Left</option>
				<option 
					<?php if ($t->calendar->weekdayDatePosition == 'bottom-right') { echo 'selected '; } ?>
					value="bottom-right">Bottom Right</option>
			</select>
		</div>
		
		<div class="row">
			<span>Day of Week Heading:</span>
			<input type="text" name="weekHeadingFgColor" title="Foreground Color" value="<?php echo $t->calendar->weekHeadingFgColor; ?>" />
			<input type="text" name="weekHeadingBgColor" title="Background Color" value="<?php echo $t->calendar->weekHeadingBgColor; ?>" />
			<div class="example" id="weekHeading">Sunday</div>
		</div>
		
		<div class="row">
			<span>Normal Weekday:</span>
			<input type="text" name="weekdayFgColor" title="Foreground Color" value="<?php echo $t->calendar->weekdayFgColor; ?>" />
			<input type="text" name="weekdayBgColor" title="Background Color" value="<?php echo $t->calendar->weekdayBgColor; ?>" />
			<input type="text" name="weekdayBorderColor" title="Border Color" value="<?php echo $t->calendar->weekdayBorderColor; ?>" />
			<div class="example" id="weekday">15</div>
		</div>
		
		<div class="row">
			<span>Current day:</span>
			<input type="text" name="todayFgColor" title="Foreground Color" value="<?php echo $t->calendar->todayFgColor; ?>" />
			<input type="text" name="todayBgColor" title="Background Color" value="<?php echo $t->calendar->todayBgColor; ?>" />
			<input type="text" name="todayBorderColor" title="Border Color" value="<?php echo $t->calendar->todayBorderColor; ?>" />
			<div class="example" id="weekHeading">15</div>
		</div>
		
		<div class="row">
			<span>Month Navigation Display:</span>
			<select name="monthNavType">
				<option value="mNsimple"<?php if($t->calendar->monthNavType=='mNsimple') { echo ' selected '; } ?>>One Before/After (Default)</option>
				<option value="mNdeuce"<?php if($t->calendar->monthNavType=='mNdeuce') { echo ' selected '; } ?>>Two Before/After</option>
				<option value="mNfullyearTop"<?php if($t->calendar->monthNavType=='mNfullyearTop') { echo ' selected '; } ?>>Full Year Display, Top</option>
				<option value="mNfullyearBottom"<?php if($t->calendar->monthNavType=='mNfullyearBottom') { echo ' selected '; } ?>>Full Year Display, Bottom</option>
			</select>
		</div>
		
		<div class="row">
			<span>Single Week Display Mode:</span>
			<select name="oneweekMode">
				<option value="startSaturday"<?php if($t->calendar->oneweekMode=='startSaturday') { echo ' selected '; } ?>>Start on the last Saturday</option>
				<option value="startSunday"<?php if($t->calendar->oneweekMode=='startSunday') { echo ' selected '; } ?>>Start on the last Sunday</option>
				<option value="startToday"<?php if($t->calendar->oneweekMode=='startToday') { echo ' selected '; } ?>>Start on the current date</option>
			</select>
		</div>
		
		<h1>Group Configuration</h1>
		
		<div class="split_row_left">
			Enable Group Selection:
			<input type="checkbox" name="groupFilterEnabled" class="check" value="1" 
				<?php if($t->calendar->groupFilterEnabled) { echo 'checked '; } ?> />
		</div>
		<div class="split_row_right">
			Configure Groups:
			<select name="groups" class="medium">
				<option value="">&nbsp;</option>
				<option value="new">Add a new group...</option>
			<?php
				foreach ($t->calendar->group as $key=>$val) {
					echo "\r\n\t\t\t\t" . '<option value="' . $key . '">Edit "' . $val . '"</option>';
				}
			?>
			</select>
		</div>
		
		<h1>Security Settings</h1>
		
		<div class="row">
			<em>to be implemented...</em>
			<a href="javascript:" onclick="" id="">open security window</a>
		</div>
		
		<div style="clear:both;height:0.1em;">&nbsp;</div>
	</form>