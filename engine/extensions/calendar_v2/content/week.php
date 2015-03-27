<?php
 /*	Calendar Class
 	*
	*	Display a single week
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
	
	<ul class="calendar2 week">
	<?php 
	if ($data['showHeader']) 
	{ ?>
		<li class="head">
			Week of <?php echo $data['start']['month']; ?> <?php echo $data['start']['day']; ?><sup><?php echo $data['start']['ending']; ?></sup> 
			- <?php echo $data['end']['month']; ?> <?php echo $data['end']['day']; ?><sup><?php echo $data['end']['ending']; ?></sup> <?php echo $data['end']['year']; ?></li>
	
		<?php if ($t->calendar->weekGroupFilter && $t->get->calendar_group_filter) { ?>
		<li class="groupFilter">
			<form name="calendar" method="post">
				<select name="group_filter" onchange="post(this);">
				<?php
					foreach($t->get->calendar_group as $key=>$val) {
						echo '<option value="' . $key . '"';
						if ( (isset($_REQUEST['group_filter'])) && ($_REQUEST['group_filter'] == $key) ) { echo ' selected '; }
						echo '>' . $val . '</option>' . "\r\n\t\t\t\t\t";
					}
				?>
				</select>
			</form>
		</li>
		<?php } ?>
		
		<li class="calNavigation <?php echo $data['nav'][0]; ?>"><?php echo $data['nav'][1]; ?></li>
		
		<li class="image">
		<?php if ($t->get->calendar_week_header_img) { ?>
			<img 	src="<?php echo $data['headerImg']['src']; ?>" 
						width="<?php echo $data['headerImg']['width']; ?>" 
						height="<?php echo $data['headerImg']['height']; ?>" 
						alt="<?php echo $data['headerImg']['alt']; ?>" />
		<?php } else { ?>
			<div style="width: 400px; height: 50px;">&nbsp;</div>
		<?php } ?>
		</li>
		
	<?php
		if ($t->calendar->weekHeadingMode == 'enabled') {
			for ($i=0;$i<count($data['dayHeader']);$i++) {
				echo '<li class="dayHeader">' . $data['dayHeader'][$i] . '</li>' . "\r\n\t\t";
			}
		}
		
		# insert a blank line for aesthetics
		echo "\r\n\r\n\t\t";
		
	} // if showHeader (line 20)
	
	for ($i=0;$i<count($data['events']);$i++) {
		echo '<li class="' . $data['events'][$i][2] . '" onclick="return loadDay(' . $data['events'][$i][3] . ');"';
		if ( (isset($data['events'][$i][4])) && ($data['events'][$i][4] != '') ) { echo ' id="' . $data['events'][$i][4] . '"'; }
		echo '>';
		if ($t->calendar->weekHeadingMode == 'day') {
			echo '<div class="dayHeader">' . $data['dayHeader'][$i] . "</div>\r\n\t\t";
		}
		// numeric day of the month
		echo '<span>' . $data['events'][$i][0] . "</span>\r\n\t\t";
		
		// events summary
		echo '<ul class="summary">' . "\r\n\t\t\t" . $data['events'][$i][1] . "\r\n\t\t</ul>\r\n\t\t";
		
		// events detail (hover/bubble)
		if ( (isset($data['events'][$i][5])) && ($data['events'][$i][5] != '') ) {
			echo '<ul class="detail" id="' . @$data['events'][$i][4] . '">' . "\r\n\t\t\t" . $data['events'][$i][5] . "\r\n\t\t</ul>\r\n\t\t";
		}
		
		echo '</li>' . "\r\n\t\t";
	}
	
	# insert a blank line for aesthetics
	echo "\r\n\r\n\t\t";
	
	?>
		<li style="clear: left; height: 0.1em;"></li>
	</ul>
	
	<div style="clear:left;">&nbsp;</div>