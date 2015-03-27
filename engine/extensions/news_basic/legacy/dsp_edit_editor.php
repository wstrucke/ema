	<div class="form">
		<form name="edit" action="?action=a015&s=2" method="post">
	<?php 
		# Display any waiting messages
			if (isset($message)) {
				echo "\r\n\t\t\t<span class=\"message\">$message</span>\r\n\t\t\t";
				unset ($message);
				}
				
		# set id
		$id = $_REQUEST['id'];
		
		# load record and display record edit form
		$temp = $news->GetItem($id);
		
		# pass on via form value if we need to return
		if (isset($_GET['return'])) { 
			echo "\r\n\t\t\t<input name=\"return\" type=\"hidden\" value=\"1\" />"; 
			echo "\r\n\t\t\t<input name=\"d\" type=\"hidden\" value=\"" . $_GET['d'] . "\" />"; 
			echo "\r\n\t\t\t<input name=\"m\" type=\"hidden\" value=\"" . $_GET['m'] . "\" />"; 
			echo "\r\n\t\t\t<input name=\"y\" type=\"hidden\" value=\"" . $_GET['y'] . "\" />"; 
			}
		
		# if GetItem returned an error, display an error message
		if ($temp == -1 || $temp == false) { $message = "Error loading event record!"; }
		
		# set field variables to existing record data
		$combined_date = $temp[0];
		# split date into components
		$year = left($combined_date, 4);
		$month = left(right($combined_date, 4), 2);
		$day = right($combined_date, 2);
		$desc = $temp[2];
		$view = preg_split('//', $temp[3], -1, PREG_SPLIT_NO_EMPTY);
		$edit = preg_split('//', $temp[4], -1, PREG_SPLIT_NO_EMPTY);
		
		# display record edit form
		?>
			<input name="id" type="hidden" value="<?php echo $_REQUEST['id']; ?>" />
			<h1>Modify a News Posting</h1>
			<script language="javascript" type="text/javascript"> var cal1x = new CalendarPopup("pop_calendar"); </script>
			<div class="row">
				<span class="label" style="width: 150px;">Announcement Date (mm/dd/yyyy):</span>
				<span class="in" style="width: 250px;">
					<input name="date" size="10" value="<?php echo $month . "/" . $day . "/" . $year; ?>" />
					<a href="#" onClick="cal1x.select(document.edit.date,'anchor1x','MM/dd/yyyy'); return false;" title="Select a date" name="anchor1x" id="anchor1x">select</a>
				</span>
			</div>
			<div class="row">
				<span class="label" style="width: 150px;">Description:</span>
				<span class="in" style="width: 250px;"><textarea name="description" rows="6"><?php echo $desc; ?></textarea></span>
			</div>
			<div class="row">
				<span class="label" style="width: 150px;">View Permissions:</span>
				<span class="in" style="width: 250px;">
					<select name="view[]" multiple="true" style="width: 150px;">
						<option value="0" <?php if ($view[0]) { echo "selected"; } ?>>Public
						<option value="1" <?php if ($view[1]) { echo "selected"; } ?>>Athletic Band
						<option value="2" <?php if ($view[2]) { echo "selected"; } ?>>Marching Band
						<option value="3" <?php if ($view[3]) { echo "selected"; } ?>>Student Staff
						<option value="4" <?php if ($view[4]) { echo "selected"; } ?>>MB Squad Leaders
						<option value="5" <?php if ($view[5]) { echo "selected"; } ?>>Staff Leaders
					</select>
				</span>
			</div>
			<div class="row">
				<span class="label" style="width: 150px;">Edit Permissions:</span>
				<span class="in" style="width: 250px;">
					<select name="edit[]" multiple="true" style="width: 150px;">
						<option value="3" <?php if ($edit[3]) { echo "selected"; } ?>>Student Staff
						<option value="4" <?php if ($edit[4]) { echo "selected"; } ?>>MB Squad Leaders
						<option value="5" <?php if ($edit[5]) { echo "selected"; } ?>>Staff Leaders
					</select>
				</span>
			</div>
			<div class="row">
				<input name="submit" type="submit" value="Submit" class="submit" onClick="return checkFields();" />
				<input name="submit" type="submit" value="Delete" class="submit" onClick="return confirm('Are you sure you want to delete this news item?');" />
			</div>
			<div class="spacer">&nbsp;</div>
		</form>
	</div>
	<div id="pop_calendar" style="position:absolute;visibility:hidden;background-color:white;layer-background-color:white;"></div>