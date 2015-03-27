<?php
 /***************************************
	* Remove all values for the ticker
	*
	*/
	
	# Get our content management system object
	require_once (ROOT_PATH . '/cms/management_class.php');
	$console = new cms;
	
	# Clear application values
	$_APP['urgent_news_caption'] = '';
	$_APP['urgent_news'] = '';
	$_APP['urgent_news_link'] = '';
	
	# Clear configuration file values
	$console->UpdateConfiguration('urgent_news', '');
	$console->UpdateConfiguration('urgent_news_caption', '');
	$console->UpdateConfiguration('urgent_news_link', '');
	
?>
	Successfully cleared the news post.<br />