<?php
	$message = false;
	
	# check for a post value
	if (is_array($_FILES) && array_key_exists('upload', $_FILES) && array_key_exists('tmp_name', $_FILES['upload']) && (strlen($_FILES['upload']['tmp_name']) > 0)) {
		# validate there is no path conflict
		$path = $_POST['path'];
		# make sure neither the first nor last characters are slashes
		while (substr($path, 0, 1) == '/') { $path = substr($path, 1); }
		while (substr($path, strlen($path)-1) == '/') { $path = substr($path, 0, strlen($path)-1); }
		if (strlen($path) == 0) {
			$path = 'null';
		} else {
			$r = $t->db->query('files_index', array('name', 'path'), array($_FILES['upload']['name'], $path));
			if (db_qcheck($r, true)) {
				$message = 'Error: A file with this name already exists at the selected path';
			}
		}
		
		if (! $message) {
			# save to the database
			$uuid = uniqid(md5($_FILES['upload']['name']), true);
			$data = file_get_contents($_FILES['upload']['tmp_name']);
			if ($t->db->insert('files', array('unique_id', 'data'), array($uuid, $data))) {
				$id = $t->file->next_id();
				
				# get the mime type [ http://httpd.apache.org/docs/mod/mod_mime_magic.html ]
				$mime = $_FILES['upload']['type'];
				
				switch ($mime) {
					case 'application/x-javascript': $mime = 'text/javascript'; break;
				}
				
				# update the index
				$t->db->insert(
					'files_index',
					array('id', 'unique_id', 'type', 'name', 'description', 'size', 'object', 'shared', 'updated', 'mime', 'path'),
					array($id, $uuid, 'database', $_FILES['upload']['name'], $_POST['description'],
						$_FILES['upload']['size'], 'file_manager', true, 'NOW()', $mime, $path)
					);
				
				$message = 'Received file ' . $_FILES['upload']['name'] . ' (' . $_FILES['upload']['size'] . ' bytes) with assigned ID ' . $id;
				
				if (@strlen($_POST['alias']) > 0) {
					# add the alias as well
					$t->file->create_alias($id, $_POST['alias']);
					$message .= ' and alias ' . $_POST['alias'];
				}
			} else {
				$message = 'Error saving to database.';
			}
		}
	} elseif (is_array($_FILES) && @array_key_exists('error', $_FILES['upload'])) {
		$message = "An error occurred during the upload";
	}
?>

	<div>
		<h1>file manager control panel<div><?php echo l('close', 'admin'); ?></div></h1>
		
		<ul id="navigation">
			<li><?php echo l('List', 'admin/file'); ?></li>
			<li><?php echo l('Upload', 'admin/file/upload'); ?></li>
			<li class="clear">&nbsp;</li>
		</ul>
		
<?php if (isset($message)) { ?>
		<div><?php echo $message; ?></div>
<?php } ?>
		
		<form name="file" enctype="multipart/form-data" action="<?php echo url('admin/file/upload'); ?>" method="POST">
			<input type="hidden" name="submitted" value="true" />
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (ini_get('post_max_size') * 1024 * 1024); ?>" />
			<h2>File Upload</h2>
			<div style="padding: 10px 0px; border-bottom: 1px solid #cccccc;">
				Due to restrictions on our server we can not accept uploads greater than <?php echo $t->get->max_upload_size; ?> each.<br />
				Please direct any questions to the 
				<a href="mailto:<?php echo $t->get->admin_email; ?>?subject=tbdbitl.osu.edu%20upload%20question">Webmaster</a>.</div>
			<div>
				<strong>Select File:</strong>
				<input name="upload" type="file" />
				<br /><br />
				<strong>Alias<sup>*</sup>:</strong>
				<input name="alias" />
				<br /><br />
				<strong>Folder:</strong>
				<select name="folder">
					<option value="">&nbsp;</option>
				</select>
				<br /><br />
				<strong>Path:</strong>
				<input name="path" type="text" />
				<br /><br />
				<strong>Description:</strong>
				<input name="description" type="text" />
			</div>
			<input type="submit" value="Upload File" class="submit" />
		</form>
	
	</div>