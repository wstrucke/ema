<?php
 /***************************************
	* Create the news ticker from post
	*
	*/
	
	# set up content management system object
	require_once (ROOT_PATH . '/cms/management_class.php');
	$console = new cms;

	# clear variables
	if (isset($caption)) { unset($caption); }
	if (isset($text)) { unset($text); }
	if (isset($link)) { unset($link); }
	
	$result = false;

	for ($i=0;$i<count($_POST['news']);$i++)
	{
		if ($_POST['news'][$i] != '')
		{
			$caption[] = $_POST['caption'][$i];
			$text[] = $_POST['news'][$i];
			$link[] = $_POST['link'][$i];
			$result = true;
		}
	}
	
	if ($result)
	{
		# perform update!!!
		$console->UpdateConfiguration('urgent_news', serialize($text));
		$console->UpdateConfiguration('urgent_news_caption', serialize($caption));
		$console->UpdateConfiguration('urgent_news_mode', $_POST['mode']);
		$console->UpdateConfiguration('urgent_news_link', serialize($link));
		echo 'Successfully updated urgent news post.<br />';
	} else {
		echo '<strong>Error:</strong> You must specify at least one text item.<br />';
	}
?>