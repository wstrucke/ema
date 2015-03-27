<?php
	# add required js link to the header for this page
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/modules.js')));
	$dev_mode = $t->get->dev_mode;
?>

<?php
	$list = $t->db->query(
		'modules',
		'',
		'',
		array('id','name','module','enabled','module_version','schema_version','load_order','provides','type','gears'),
		true,
		array('load_order','name'));
	
	$counter = 0;
?>

	<div>
		<h1>modules<div><?php echo l('close', 'admin/cms'); ?></div></h1>
		
		<form name="modules" action="<?php echo url('admin/cms/modules'); ?>" method="post">
		<table class="colorList">
			<tr>
				<th>Enabled</th>
				<th>Name</th>
				<th>Module</th>
				<th>Version</th>
				<th>DB</th>
				<th>Provides</th>
				<th>Type</th>
				<th>Gears</th>
				<th>Order</th>
				<th>&nbsp;</th>
			</tr>
<?php
	
	if ($list !== false) {
		foreach ($list as $record) {
			# set class (for style) based on loop counter
			if ($counter % 2 == 0) { $class = 'even'; } else { $class = 'odd'; }
			
			# set output values
			if ($record['gears']) { $gears = 'Yes'; } else { $gears = 'No'; }
			if ($record['load_order'] == 0) { $load_order = '-'; } else { $load_order = $record['load_order']; }
			if ($record['schema_version'] == 0) { $schema = '-'; } else { $schema = $record['schema_version']; }
			
?>
      <tr class="<?php echo $class; ?>">
      	<td style="text-align: center;"><input type="checkbox" id="module_<?php echo $record['id']; ?>" <?php if ($record['enabled']) { echo 'checked="checked" '; } if (($record['module'] == 'engine')&&($dev_mode == false)) { echo 'disabled="disabled" '; } ?>/></td>
				<td><?php echo $record['name']; ?></td>
				<td><?php echo $record['module']; ?></td>
				<td style="text-align: right;"><?php echo $record['module_version']; ?></td>
				<td style="text-align: center;"><?php echo $schema; ?></td>
				<td><?php echo $record['provides']; ?></td>
				<td><?php echo $record['type']; ?></td>
				<td style="text-align: center;"><?php echo $gears; ?></td>
				<td style="text-align: center;"><?php echo $load_order; ?></td>
				<td>
					<select name="action">
						<option value="" selected><em>Choose action...</em></option>
						<option value="reorder">Change Load Order</option>
						<option value="drop_schema">Drop Schema</option>
						<option value="details">View Module Details</option>
						<option value="refresh">Refresh Configuration</option>
					</select>
				</td>
			</tr>
<?php		
			$counter++;
		}
		# set class (for style) based on loop counter
		if ($counter % 2 == 0) { $class = 'even'; } else { $class = 'odd'; }
	}
?>
		</table>
		</form>
	</div>