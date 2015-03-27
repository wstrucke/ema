<?php
	# add required js link to the header for this page
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/template_manager.js')));
	
	# get the list of templates for output
	$fields = array('template_id','name','description','enabled');
	$list = $t->db->query('templates', array('parent_id'), array('null'), $fields, true, array('name'));
	if (! is_array($list)) $list = array();
?>
	<div>
		<h1>template manager control panel<div><?php echo l('close', 'admin'); ?></div></h1>
	</div>
	
	<ul id="cms_cpanel_buttons">
		<li><?php echo l('List', 'template'); ?></li>
		<li id="btn_newTemplate">New</li>
	</ul>
	
	<table class="colorList" id="template_list">
		<tr>
			<th>ID</th>
			<th>Name</th>
			<th>Enabled</th>
			<th>Description</th>
			<th>&nbsp;</th>
		</tr>
<?php
	$counter = 0;
	
	foreach ($list as $record) {
		# set class (for style) based on loop counter
		if ($counter % 2 == 0) { $class = 'even'; } else { $class = 'odd'; }
		# override class for a selected template
		#if ($record['id'] == $id) $class = 'selected';
?>
    <tr class="<?php echo $class; ?>" id="tr_<?php echo $record['template_id']; ?>">
			<td><?php echo $record['template_id']; ?></td>
			<td><?php echo $record['name']; ?></td>
			<td><?php if ($record['enabled']) { echo 'Y'; } else { echo 'N'; } ?></td>
			<td><?php echo $record['description']; ?></td>
			<td><button onclick="loadTemplate('<?php echo $record['template_id']; ?>')">Manage</button></td>
		</tr>
<?php		
		$counter++;
	}
?>
	</table>