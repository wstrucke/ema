<?php
 /***************************************
	* Ticker edit form
	*
	*/
	
	# Get the urgent news caption, news, and matching links (if any)
	$arCaption = unserialize($_APP['urgent_news_caption']);
	$arNews = unserialize($_APP['urgent_news']);
	$arLinks = unserialize($_APP['urgent_news_link']);
	
	# Reset Variables
	if (isset($ticker)) { unset($ticker); }
	if (isset($temp)) { unset($temp); }
	
	$scroll = false;	$fade = false;	$type = false;
	
	
	while ( (is_array($arCaption)) && (count($arCaption) > 0) )
	{
		$temp['caption'] = $arCaption[0];
		$temp['news'] = $arNews[0];
		$temp['link'] = $arLinks[0];
		$ticker[] = $temp;
		array_shift($arCaption);
		array_shift($arNews);
		array_shift($arLinks);
	}
	
	if ($_APP['urgent_news_mode'] == '0') { $type = true; }
	if ($_APP['urgent_news_mode'] == '1') { $scroll = true; }
	if ($_APP['urgent_news_mode'] == '2') { $fade = true; }
	
?>

	<form name="news" class="newsForm" action="?action=a015&amp;s=7&amp;do=post" method="post">
		<input type="hidden" name="route" value="edit" />
		<span>Modify the existing news ticker</span>
		
		&nbsp;Ticker Effect:
		<select name="mode">
			<option value="0"<?php if ($type) { echo ' selected'; } ?>>Typewriter Effect</option>
			<option value="1"<?php if ($scroll) { echo ' selected'; } ?>>Scrolling Effect</option>
			<option value="2"<?php if ($fade) { echo ' selected'; } ?>>Fade Effect</option>
		</select>
		
		<ol>
		<?php
			foreach ($ticker as $item)
			{
		?>
			<li>&nbsp;
				<ul>
					<li>
						<span>Caption</span><input type="text" name="caption[]" value="<?php echo $item['caption']; ?>" />
					</li>
					<li>
						<span>Text</span><input type="text" name="news[]" value="<?php echo $item['news']; ?>" />
					</li>
					<li>
						<span>Link URL</span><input type="text" name="link[]" value="<?php echo $item['link']; ?>" />
					</li>
				</ul>
			</li>
		<?php
			}
		?>
			<li>&nbsp;
				<ul>
					<li>
						<span>Caption</span><input type="text" name="caption[]" />
					</li>
					<li>
						<span>Text</span><input type="text" name="news[]" />
					</li>
					<li>
						<span>Link URL</span><input type="text" name="link[]" />
					</li>
				</ul>
			</li>
		</ol>
		<input type="button" class="button" name="cancel" value="Cancel" onclick="window.location='?action=a015&amp;s=7';" />
		<input type="submit" class="button" name="submit" value="Save" />
		<br style="clear: both; line-height: 1.8em;" />
	</form>