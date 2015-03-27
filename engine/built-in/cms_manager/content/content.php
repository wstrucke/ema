<?php
	# add required js link to the header for this page
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/cms.js')));
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/cms_ui.js')));
?>

	<div>
		<h1>content management system<div><?php echo l('close', 'admin'); ?></div></h1>
		<h2>Content Library</h2>
	</div>
	
	<!-- <div id="workspace">This is a test text area</div> -->
	
	<ul id="cms_cpanel_buttons">
		<li id="btn_thread">New Thread</li>
		<li id="btn_link">New Link</li>
		<li id="btn_paths">Paths</li>
		<li id="btn_menus">Menu System</li>
		<li id="btn_sitesettings">Site Settings</li>
		<li id="btn_modules">System Modules</li>
		<li id="btn_refresh">Refresh List</li>
	</ul>
	
	<input type="text" name="cms_cpanel_filter" id="cms_cpanel_filter" autocomplete="off" />
	<button id="cms_cpanel_search" style="cursor: pointer;">search</button>
	<select name="cms_cpanel_type_filter" id="cms_cpanel_type_filter">
		<option value="thread,circuit,link" selected="selected">Threads, Circuits, &amp; Links</option>
		<option value="thread,circuit,link,alias">Threads, Circuits, Links, &amp; Aliases</option>
		<option value="thread,circuit,filament,link">Threads, Circuits, Filaments, &amp; Links</option>
		<option value="thread,circuit,filament,fuse,link,alias,file">Everything</option>
		<option value="alias">Aliases</option>
		<option value="circuit">Circuits</option>
		<option value="file">Files</option>
		<option value="fuse">Fuses</option>
		<option value="filament">Filaments</option>
		<option value="link">Links</option>
		<option value="thread">Threads</option>
	</select>
	
	<ul id="cms_cpanel_list"></ul>