	<div class="form">
		<form name="edit" action="?action=a015&s=2" method="post">
	<?php 
		global $a;
		
		# display record selection form
		$temp = $news->GetNews('*', implode('', $a));
		
		# if an error occured in GetEventList pass it to the client
		if ($temp == -1 || $temp == false) { $message = "Error retrieving event list or no entries exist!"; }

		# Display any waiting messages
			if (isset($message)) {
				echo "\r\n\t\t\t<span class=\"message\">$message</span>\r\n\t\t\t";
				unset ($message);
				}
					
		?>
			<h1>Select a News Item to Edit</h1>
			<div class="row">
				<span class="label" style="width: 100px;">Item:</span>
				<span class="in" style="width: 300px;">
					<select name="id" style="width: 290px;">
					<?php
						foreach ($temp as $temp_item) {
							$item = explode("|", $temp_item);
							if ($news->AllowEdit(implode('', $a), $item[1])) {
								$year = left($item[0], 4);
								$month = left(right($item[0], 4), 2);
								$day = right($item[0], 2);
								$desc = $item[2];
								if (strlen($desc) > 35) { $desc = left($desc, 31) . '...'; }
								if (strpos($desc, '"') > 0) {
									// ensure there is a closing quote
									if (strpos(desc, '"', strpos($desc, '"')) == 0) { $desc .= '"'; }
									}
								echo "<option value=\"$item[1]\">$month/$day/$year: $desc\r\n\t\t\t\t\t\t";
								} // if news->allowedit
							} // foreach
					?>
					</select>
				</span>
			</div>
			<div class="row">
				<input type="submit" name="submit" class="submit" value="Submit" />
			</div>
			<div class="spacer">&nbsp;</div>
		</form>
	</div>
	
	<br /><br />