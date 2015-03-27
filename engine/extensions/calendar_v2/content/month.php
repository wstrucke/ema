<?php
 /*	Calendar Class
 	*
	*	Display a single month
	*
	*	Version 1.0 : 2006.05.01
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# Preset Hover Array
	if (isset($hoverArray)) unset($hoverArray);
	
	# Load Calendar Data
	$data = $t->get->calendar_data;
?>

	<script language="javascript" type="text/javascript">
	<!--
		function post (obj) {	document.calendar.submit();	}
	-->
	</script>
	
	<ul class="calendar2 month">
		
		<!-- calendar title -->
		<li class="head"><?php echo $data['start']['month']; ?> <?php echo $data['start']['year']; ?></li>
	
		<!-- group filter option -->
		<?php if ($t->get->calendar_group_filter) { ?>
		<li class="groupFilter">
			<form name="calendar" id="calendar_group_form" method="post">
				Current View: 
				<select name="group_filter" id="calendar_group_filter">
				<?php
					foreach($t->get->calendar_group as $key=>$val) {
						echo '<option value="' . $key . '"';
						if(@$_REQUEST['group_filter'] == $key) { echo ' selected '; }
						echo '>' . $val . '</option>' . "\r\n\t\t\t\t\t";
					}
				?>
				</select>
			</form>
		</li>
		<?php } ?>
		
		<!-- graphic -->
		<li class="image">
		<?php if($t->get->calendar_month_header_img) { ?>
			<img src="<?php echo $data['headerImg']['src']; ?>" width="<?php echo $data['headerImg']['width']; ?>" height="<?php echo $data['headerImg']['height']; ?>" alt="<?php echo $data['headerImg']['alt']; ?>" />
		<?php } ?>
		</li>

		<!-- inter-month navigation -->
		<li class="calNavigation <?php echo $data['nav'][0]; ?>"><?php echo $data['nav'][1]; ?></li>
		
	<?php
		for ($i=0;$i<count($data['dayHeader']);$i++) {
			echo '<li class="dayHeader">' . $data['dayHeader'][$i] . '</li>' . "\r\n\t\t";
		}
		
		# insert a blank line for aesthetics
		echo "\r\n\r\n\t\t";
		
		for ($i=0;$i<count($data['events']);$i++) {
			echo '<li class="' . $data['events'][$i][2] . '" onclick="return loadDay(' . $data['events'][$i][3] . ');"';
			if ( (isset($data['events'][$i][4])) && ($data['events'][$i][4] != '') ) { echo ' id="' . $data['events'][$i][4] . '"'; }
			echo '>';
			#if ($t->calendar->weekHeadingMode == 'day') {
			#	echo '<div class="dayHeader">' . $data['dayHeader'][$i] . "</div>\r\n\t\t";
			#}
			// numeric day of the month
			echo '<span>' . $data['events'][$i][0] . "</span>\r\n\t\t";
			
			// events summary
			echo '<ul class="summary">' . "\r\n\t\t\t" . $data['events'][$i][1] . "\r\n\t\t</ul>\r\n\t\t";
			
			// events detail (hover/bubble)
			if ( (isset($data['events'][$i][5])) && ($data['events'][$i][5] != '') ) {
				echo '<ul class="detail" id="' . @$data['events'][$i][4] . '">' . "\r\n\t\t\t" . @$data['events'][$i][5] . "\r\n\t\t</ul>\r\n\t\t";
			}
			
			echo '</li>' . "\r\n\t\t";
		}
	?>
		
		<li class="calNavigation <?php echo $data['nav'][2]; ?>"><?php echo @$data['nav'][3]; ?></li>
		
	</ul>
	
	<div style="clear:left;">&nbsp;</div>