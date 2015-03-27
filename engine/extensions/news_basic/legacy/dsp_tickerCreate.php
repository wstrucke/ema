<?php
 /***************************************
	* Create a news ticker form
	*
	*/
	
?>

	<form name="news" class="newsForm" action="?action=a015&amp;s=7&amp;do=post" method="post">
		<input type="hidden" name="route" value="create" />
		<span>Create a new news ticker</span>
		
		&nbsp;Ticker Effect:
		<select name="mode">
			<option value="0">Typewriter Effect</option>
			<option value="1" selected>Scrolling Effect</option>
			<option value="2">Fade Effect</option>
		</select>
		
		<ol>
		<?php
			for ($i=0;$i<5;$i++)
			{
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
		<?php
			}
		?>
		</ol>
		<input type="button" class="button" name="cancel" value="Cancel" onclick="window.location='?action=a015&amp;s=7';" />
		<input type="submit" class="button" name="submit" value="Save" />
		<br style="clear: both; line-height: 1.8em;" />
	</form>