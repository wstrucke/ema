<?php
 /*	Calendar Class
 	*
	*	Display a single month, compact version
	*
	*	Version 1.0 : 2006.05.01
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# Preset Hover Array
	$hoverArray = array();
	
	# Load Calendar Data
	$data = $t->get->calendar_data;
?>

	<script language="javascript" type="text/javascript">
	<!--
		function post (obj) {	document.calendar.submit();	}
	-->
	</script>
	
	<ul class="calendar2 compact">
	
		<li class="head"><?php echo $data['month']; ?> <?php echo $data['year']; ?></li>
	
	<?php
		for ($i=0;$i<count($data['dayHeader']);$i++) {
			echo '<li class="dayHeader">' . $data['dayHeader'][$i] . '</li>' . "\r\n\t\t";
		}
		
		# insert a blank line for aesthetics
		echo "\r\n\r\n\t\t";
		
		for ($i=0;$i<count($data['events']);$i++) {
			echo '<li class="' . $data['events'][$i][2] . '"';
			if (@$data['events'][$i][3] != '') { echo ' id="' . $data['events'][$i][3] . '"'; }
			if ($data['events'][$i][1] != '')
			{
				echo ' onclick="loadDay(\'' . $data['events'][$i][1] . '\');"';
			}
			echo '>';
			echo $data['events'][$i][0];
			echo '</li>' . "\r\n\t\t";
			if (@$data['events'][$i][4] != '') { $hoverArray[count($hoverArray)] = '<div class="hoverContents" id="' . $data['events'][$i][3] . 'hoverData' . '">' . $data['events'][$i][4] . "</div>\r\n\t\t"; }
		}
		
		# insert a blank line for aesthetics
		echo "\r\n\r\n\t\t";
	?>
		
		<li class="clear"></li>
		
	</ul>
	
	<?php
	# Output Hover Elements
	if (isset($hoverArray)) foreach ($hoverArray as $item) echo $item . "\r\n";
	
	?>
	
	<div style="clear:left;">&nbsp;</div>