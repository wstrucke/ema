<?php
 /*	Calendar Class
 	*
	*	Display a single day
	*
	*	Version 1.0 : 2006.05.03
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	# Load Calendar Data
	$data = $t->get->calendar_data;
?>
	<script language="javascript" type="text/javascript">
	<!--
		function confirmDelete(event_id, day, year)
		{
			// prompt user to confirm deletion of specified event
			if (confirm('Are you sure you want to delete this event?'))
			{
				window.location='<?php echo CAL_INDEX; ?>selection=delete&id=' + event_id;
			}
		}
	-->
	</script>

	<div class="calendar2">
		<h1><?php echo $data['day']; ?></h1>
		<a href="<?php echo $data['link'][0]; ?>" id="monthLink">Switch to Month View</a>
		<div class="dayNavigation">
			<a href="<?php echo $data['link'][1]; ?>" class="left">&lt;- <?php echo $data['link'][2]; ?></a>
			<a href="<?php echo $data['link'][3]; ?>" class="right"><?php echo $data['link'][4]; ?> -&gt;</a>
			<?php echo @$data['link'][5]; ?>
		</div>
		<ul>
			<?php
				if (is_array($data['events'])) {
					foreach ($data['events'] as $item) {	echo "<li>$item</li>\r\n\t\t\t"; }
				} else {
					echo "<li><em>No calendar entries found</em></li>\r\n\t\t\t";
				}
			?>			
		</ul>
	</div>