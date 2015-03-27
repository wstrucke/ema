<?php
 /*	News Class
 	*
	*	Display urgent news bulletin using fxNewsTicker javascript class
	*
	*	Version 1.0 : 2006.04.23
	*	William Strucke [strucke.1@osu.edu]
	*
	*/

	global $_APP;
	
	# Get the urgent news caption, news, and matching links (if any)
	$arCaption = unserialize($_APP['urgent_news_caption']);
	$arNews = unserialize($_APP['urgent_news']);
	$arLinks = unserialize($_APP['urgent_news_link']);
	
	$caption = '';
	$text = '';
	$links = '';
	
	while ( (is_array($arCaption)) && (count($arCaption) > 0) )
	{
		if ($caption != '')
		{
			# add commas first
			$caption .= ',';
			$text .= ',';
			$links .= ',';
		}
		$caption .= "'$arCaption[0]'";
		$text .= "'$arNews[0]'";
		$links .= "'$arLinks[0]'";
		array_shift($arCaption);
		array_shift($arNews);
		array_shift($arLinks);
	}

?>

		<script type="text/javascript" src="https://tbdbitl.osu.edu/javascript/fxNewsTicker.js"></script>
		<script type="text/javascript">
		<!--
			var ticker = new fxNewsTicker('fxNewsTicker');
			
			ticker.fxSetting = <?php echo $_APP['urgent_news_mode']; ?>;
			ticker.caption = Array(<?php echo $caption; ?>);
			ticker.news = Array(<?php echo $text; ?>);
			ticker.href = Array(<?php echo $links; ?>);
			
			setTimeout("addEvent(window, 'load', ticker.init(), false);", 1500);
		-->
		</script>
		
		<div id="fxNewsTicker"></div>