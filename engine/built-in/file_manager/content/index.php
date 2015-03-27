<?php
	# add required js link to the header for this page
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/file_manager.js')));
	
	# temporary navigation until such time as cms is capable of adding/removing files/paths on demand
	# - basically this will be here throughout development - sorry! I need to focus on the UI now!
	
	# get the path from the cms
	$path = $t->cms->get_args();
	if ((strlen($path) == 0)||($path == 'file_manager')||($path == 'file')) {
		$path = false;
	} elseif (@substr($path, 0, 12) == 'file_manager') {
		$path = @substr($path, 13);
	} elseif (@substr($path, 0, 4) == 'file') {
		$path = @substr($path, 5);
	}
	
	if (@substr($path, 0, 6) == 'browse') $path = @substr($path, 7);
	
	# set the current folder
	if ($path === false) {
		$folder = '/';
		$path = '';
	} else {
		$folder = '/' . $path;
		if (substr($path, strlen($path) - 1) == '/') $path = substr($path, 0, strlen($path) - 1);
	}
	
	# get the list of files and folders for output
	$folders = $t->file->list_folders($path);
	$list = $t->file->list_files($path);
?>
	
	<div>
		<h1>file manager control panel<div><?php echo l('close', 'admin'); ?></div></h1>
	</div>
	
	<ul id="cms_cpanel_buttons">
		<li><?php echo l('List', 'admin/file'); ?></li>
		<li><?php echo l('Upload', 'admin/file/upload'); ?></li>
	</ul>
	
	<h2>Browsing Folder <strong id="folder"><?php echo $folder; ?></strong></h2>
	<table class="colorList">
<?php
	$counter = 0;
	if ($path != '') {
		$p = strpos($path, '/');
		if ($p === false) { 
			$tmp = 'admin/file';
		} else {
			$tmp = explode('/', $path);
			unset($tmp[count($tmp)-1]);
			$tmp = 'admin/file/browse/' . implode('/', $tmp);
		}
		echo "\t\t<tr class=\"even\"><td>" . l('/..', $tmp) . "</td></tr>\r\n";
		$counter++;
		$path .= '/';
	}
	for ($i=0;$i<count($folders);$i++) {
		# set class (for style) based on loop counter
		if ($counter % 2 == 0) { $class = 'even'; } else { $class = 'odd'; }
		echo "\t\t<tr class=\"$class\"><td>" . l('/' . $folders[$i], "admin/file/browse/$path" . $folders[$i]) . "</td></tr>\r\n";
		$counter++;
	}
?>
	</table>
	
	<table class="colorList">
		<tr>
			<th>ID</th>
			<th>Name</th>
			<th>Description</th>
			<th>Size (bytes)</th>
			<th>Type</th>
			<th>Shared</th>
			<th>SSL Required</th>
			<th>Updated</th>
			<th>&nbsp;</th>
		</tr>
<?php
		$counter = 0;
		
		foreach ($list as $record) {
			# set class (for style) based on loop counter
			if ($counter % 2 == 0) { $class = 'even'; } else { $class = 'odd'; }
			# override class for a selected file
			#if ($record['id'] == $id) $class = 'selected';
?>
    <tr class="<?php echo $class; ?>" id="file_<?php echo $record['id']; ?>">
			<td><?php echo $record['id']; ?></td>
			<td><?php echo $record['name']; ?></td>
			<td><?php echo $record['description']; ?></td>
			<td><?php echo $record['size']; ?></td>
			<td><?php echo $record['mime']; ?></td>
			<td><?php if ($record['shared'] == '1') { echo 'Y'; } else { echo 'N'; } ?></td>
			<td><?php if ($record['ssl_required'] == '1') { echo 'Y'; } else { echo 'N'; } ?></td>
			<td><?php echo $record['updated']; ?></td>
			<td>
				<input type="hidden" value="<?php echo $record['id']; ?>" />
				<select name="action" onchange="fileListAction(this);">
					<option value="" selected>Choose action...</option>
					<option value="download">Download</option>
					<option value="info">Edit Information</option>
					<option value="content">Edit Content</option>
					<option value="alias">Create Alias</option>
					<option value="delete">Delete</option>
					<option value="move">Move</option>
				</select>
			</td>
		</tr>
<?php		
			$counter++;
		}
?>
	</table>
