	<div class="form">
		<form name="remove" action="?action=a015&s=3" method="post">
	<?php 
		global $a;
		
		# display record selection form
		$temp = $news->GetNews('*', implode('', $a));
		
		# if an error occured in GetNews pass it to the client
		if ($temp == -1 || $temp == false) { $message = "Error retrieving event list or no entries exist!"; }
				
		# Display any waiting messages
			if (isset($message)) {
				echo "\r\n\t\t\t<span class=\"message\">$message</span>\r\n\t\t\t";
				unset ($message);
				}
					
		?>
			<h1>Delete a News Item</h1>
			<div class="row">
				<span class="label" style="width: 100px;">Item:</span>
				<span class="in" style="width: 300px;">
					<select name="id" style="width: 290px;">
					<?php
						global $a;
						
						foreach ($temp as $temp_item) {
							$item = explode('|', $temp_item);
							$qry_filter1 = $item[4];
							$qry_filter2 = implode('', $a);
							include (ROOT_PATH . '/security/qry_IsAuthorized.php');
							if ($qry_result) {
								# Edit Access Granted
								$year = left($item[0], 4);
								$month = left(right($item[0], 4), 2);
								$day = right($item[0], 2);
								$desc = $item[2];
								if (strlen($desc) > 35) { $desc = left($desc, 31) . '...'; }
								echo "<option value=\"$item[1]\">$month/$day/$year: $desc\r\n\t\t\t\t\t\t";
								} // if qry result
							} // foreach
					?>
					</select>
				</span>
			</div>
			<div class="row">
				<input type="submit" name="submit" class="submit" value="Delete" 
					   onClick="return confirm('Are you sure you want to permanently delete this news bulletin?');" />
			</div>
		
			<div class="spacer">&nbsp;</div>
		</form>
	</div>
	
	<br /><br />