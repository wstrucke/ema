<?php
 /*************************************************************************
 * manage urgent news item																								|
 * =======================================================================|
 * William Strucke, 2005.08.01																						|
 * -----------------------------------------------------------------------|
 * There is only one urgent news item at a time, specified in the site		|
 * configuration file under 'urgent_news'.  via this page, the urgent 		|
 * news item can be replaced (the old item is delagated to a normal news	|
 * item), updated, or completely removed.																	|
 *																																				|
 *************************************************************************/
	global $_APP;
	?>
	<script language="javascript">
		function execute ($val) {
			document.news.update.value = $val;
			if ($val == 'remove') { document.news.update.name = 'what'; }
			document.news.submit();
			} // execute
		function enableHyperlink () {
			if (document.news.link_yes.checked) {
				// enable hyperlink field
				document.news.hyperlink.disabled = false;
				document.news.hyperlink.style.background = '#ffffff';
				document.news.hyperlink.focus();
			} else {
				// disable hyperlink field
				document.news.hyperlink.disabled = true;
				document.news.hyperlink.value = '';
				document.news.hyperlink.style.background = '#eeeeee';
				}
			return true;
			} // enableHyperlink
	</script>
	
	<?php
	
	if (isset($_REQUEST['update'])) { 
		# display update form 
		?>
	<div class="wide_form">
	<form name="news" action="?action=a015&s=7" method="post">
		<input type="hidden" name="what" value="<?php echo $_REQUEST['update']; ?>" />
		<h1>Urgent News Post</h1>
		<div class="row">
			<span class="label">Caption:</span>
			<span class="in"><input name="caption" value="<?php
			if ($_REQUEST['update'] == 'edit') { echo $_APP['urgent_news_caption']; }
			?>" style="width: 200px;" />
			Mode: 
			<select name="mode">
				<option value="0"<?php if ($_APP['urgent_news_mode'] == '0') echo ' selected '; ?>>
					Typewriter Effect</option>
				<option value="1"<?php if ($_APP['urgent_news_mode'] == '1') echo ' selected '; ?>>
					Scrolling Effect</option>
				<option value="2"<?php if ($_APP['urgent_news_mode'] == '2') echo ' selected '; ?>>
					Fade Effect</option>
			</select>
			</span>
		</div>
		<div class="row">
			<span class="label">Text:</span>
			<span class="in"><input name="val" value="<?php
			if ($_REQUEST['update'] == 'edit') { echo $_APP['urgent_news']; }
			?>" style="width: 550px;" /></span>
		</div>
		<div class="row">
			<span class="label">Hyperlink:</span>
			<span class="in">
				<?php
				if ($_REQUEST['update'] == 'edit') { 
					echo '<input type="checkbox" name="link_yes" value="1" onClick="enableHyperlink();" ';
					if ($_APP['urgent_news_link'] != '') { echo 'checked '; }
					echo '/>';
					echo "\r\n\t\t\t\t" . '<input name="hyperlink" value="' . $_APP['urgent_news_link'];
					echo '" style="width: 519px;" ';
					if ($_APP['urgent_news_link'] === false) { echo 'style="background-color: #eeeeee;" disabled '; }
					echo '/>'; 
				} else { ?>
				<input type="checkbox" name="link_yes" value="1" onClick="enableHyperlink();" />
				<input name="hyperlink" value="" style="width: 519px; background-color: #eeeeee;" disabled />				
				<?php	}	?>
			</span>
		</div>
		<div class="row">
			<span class="in">
			<?php
				switch ($_REQUEST['update']) {
					case 'new':
						echo 'Adding a new urgent news post.';
						break;
					case 'edit':
						echo 'Editing the existing urgent news post.';
						break;
					case 'remove':
						echo 'Permanently removing the existing urgent news post:<br /><em>' . $_APP['urgent_news'] . '</em>.';
						break;
					case 'replace':
						echo 'Replacing the existing urgent news post:<br /><em>' . $_APP['urgent_news'] . '</em><br />...and ';
						echo 'transferring the existing post to a standard news item.';
						break;
					default:
						echo 'An error has occurred! <a href="mailto:' . $_APP['contact_email'] . '?subject=OSUMB%20Website';
						echo '%20Error%20Report&body=urgent.php%20update%20-%20' . $_REQUEST['update'] . '">Report This</a>';
					} // switch
			?>
			<span>
		</div>
		<div class="spacer">&nbsp;</div>
		<input type="submit" name="submit" class="submit" value="Save" />
		<div class="spacer"></div>
	</form>
	</div>
	<?php
	} elseif (isset($_REQUEST['what'])) {
		# perform update!!!
		require_once (ROOT_PATH . '/cms/management_class.php');
		$console = new cms;
		if ($_REQUEST['what'] == 'replace') {
			# move the existing entry to a normal news post before overwriting
			$news->AddItem(date('m'), date('d'), date('Y'), $console->GetConfiguration('urgent_news'), array(1,1,1,1,1,1,1,1), array(0,0,0,1,0,1,1,1));
			} // if request what == replace
		$console->UpdateConfiguration('urgent_news', $_REQUEST['val']);
		$console->UpdateConfiguration('urgent_news_caption', $_REQUEST['caption']);
		$console->UpdateConfiguration('urgent_news_mode', $_REQUEST['mode']);
		$console->UpdateConfiguration('urgent_news_link', $_REQUEST['hyperlink']);
		echo '<div align="center">Successfully updated urgent news post.</div>';
	} else {	
		if ($_APP['urgent_news'] == '') {
		?>
	<div class="form">
	<form name="news" action="?action=a015&s=7" method="post">
		<input type="hidden" name="update" value="" />
		There is currently no urgent news item specified.  Do you want to <a href="javascript:execute('new');">
		create one</a>?
	</form>
	</div>
		<?php
		} else {
			# a news item is currently set ?>
	<div class="form">
	<form name="news" action="?action=a015&s=7" method="post">
		<input type="hidden" name="update" value="" />
		<input type="hidden" name="caption" value="" />
		<input type="hidden" name="mode" value="2" />
		<input type="hidden" name="val" value="" />
		<input type="hidden" name="hyperlink" value="" />
		An urgent news post already exists!  Do you want to <a href="javascript:execute('edit');">edit the existing 
		post</a>, <a href="javascript:execute('remove');">remove the post</a>, or 
		<a href="javascript:execute('replace');"> replace the post</a> (delegating it to a normal	news posting)?
	</form>
	</div>
	<?php
			} // if app urgent news == ''
		} // if isset request update
	?>