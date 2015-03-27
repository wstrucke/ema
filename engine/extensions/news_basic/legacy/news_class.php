<?php
 /***********************************************************************************************
 *	News Class for v2 Site
 *	=============================================================================================
 *	William Strucke [strucke.1@osu.edu], 2005.06.10
 *		- rev. 2005.08.10 : added checks to ensure no \r\n breaks are saved
 *	---------------------------------------------------------------------------------------------
 *	REQUIRES: Standard File I/O Operations
 *	---------------------------------------------------------------------------------------------
 *	DataStore:
 *		store events in a single index file, sorted by date new to old. (filename: news.wb)
 *
 *		"yearmonthday (yyyymmdd) | news_id | description (html) | SEC_VIEW | SEC_EDIT | 
 *				post user | post date (mm/dd/yyyy) | post time (hh/mm/ss) \r\n"
 *
 *		number of fields: 10
 *	---------------------------------------------------------------------------------------------
 *	Functions:
 *		AddItem($month, $day, $year, $desc, $view, $edit)
 *		DeleteItem($id)
 *		GetItem($id)
 *		GetNewID()
 *		GetNews(optional $how_many, optional user $permissions)
 *		PrepareOutput($items, $my_permissions)
 *		UpdateItem($id, $month, $day, $year, $desc, $view, $edit)
 *		OutputAddForm()
 *		OutputUrgent()
 *		AllowEdit($myPermissions, $item_id)
 ***********************************************************************************************/
	
	class news_bulletin {
	
		function AddItem($month, $day, $year, $desc, $view, $edit) {
			/************************************************************************
			* adds a new news posting																								|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			global $_APP;
			
			# Validate Date Values
			if (intval($month) < 10) { $month = '0' . strval(intval($month)); }
			if (intval($day) < 10) { $day = '0' . strval(intval($day)); }
			if (intval($year) < 10) { $year = '200' . strval(intval($year)); }
			if (intval($year) < 100) { $year = '20' . strval(intval($year)); }
			
			# Validate News Text
			$desc = str_replace("\r\n", "<br />", $desc);
			$desc = str_replace("|", "&zwnj;", $desc);
			
			# generate an id for this new record
			$id = $this->GetNewID();
			if ($id < 0) { return false; }
			
			# prepare permissions
			if ($view[0] != "") { $view_permis = implode('', $view); }
			if ($edit[0] != "") { $edit_permis = implode('', $edit); }
			$view_permis .= '67';
			$edit_permis .= '67';
			
			# ensure variables do not contain the bar character as that would severely cripple the entire system
			$desc = str_replace('|', '^', $desc);
			
			$newentry = $year . $month . $day . '|' . $id . '|' . stripslashes($desc) . '|' . implode("", parseaccesslevel($view_permis));
			$newentry .= '|' . implode("", parseaccesslevel($edit_permis)) . '|' . $_SESSION['user'];
			$newentry .= '|' . date("m/d/Y|H:i:s");
			
			# get file
			$file = rdumpfile('news.wb');
			
			# add new entry
			$file[count($file)] = $newentry;
			
			# sort by date
			rsort($file, SORT_NUMERIC);
			
			markSiteAsUpdated();
			
			return write_new_file('news.wb', $file);
			}
			
		function DeleteItem($id) {
			/************************************************************************
			* deletes news item $id																									|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			
			# get file
			$file = rdumpfile('news.wb');
			
			# prepare result
			$result = false;
			
			# find and remove item $id
			for ($i = 0; $i < count($file); $i++) {
				$temp = explode('|', $file[$i]);
				if ($temp[1] == $id) {
					$result = true;
					$file[$i] = '';
					}
				} // for
				
			if ($result == false) { return false; }
			
			# sort by date
			if (count($file) > 0) { rsort($file, SORT_NUMERIC); }
			
			return write_new_file('news.wb', $file);
			}
			
		function GetItem($id) {
			/************************************************************************
			* return a single news record matching $id															|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			
			# get file
			$file = rdumpfile('news.wb');
			
			# find item
			for ($i = 0; $i < count($file); $i++) {
				$temp = explode('|', $file[$i]);
				if ($temp[1] == $id) { return explode('|', $file[$i]); }
				} // for
				
			# did not find item
			return false;
			}
			
		function GetNewID() {
			/************************************************************************
			* returns a new id for a news item																			|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			
			# retrieve file data
			$file = rdumpfile('news.wb');
			
			# create an array of ids
			while ($file[0] != "") {
				$temp = explode('|', array_shift($file));
				$ids[count($ids)] = $temp[1];
				} // while
					
			if (count($ids) == 0) { return 1; }
			
			# sort ids
			sort($ids, SORT_NUMERIC);
			
			return $ids[count($ids) - 1] + 1;
			}
			
		function GetNews($how_many = 3, $permissions = '10000000') {
			/************************************************************************
			* returns array of $how_many articles starting at the newest and going	|
			*	back in time, defaults to three.																			|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			* 2005.08.12 : Added Security Check 																		|
			*																																				|
			************************************************************************/
			
			# retrieve file data
			$file = rdumpfile('news.wb');
			
			# check how_many * option
			if ($how_many == "*") { $how_many = count($file); }
			
			# reset result array just in case
			if (isset($results)) { unset($results); }
			
			# grab number of entries requested
			while (($how_many) > 0 && (count($file) > 0)) {
				# only return this item if we pass the security check
				$line = array_shift($file);
				$tmp = explode('|', $line);
				$qry_filter1 = $tmp[3];
				$qry_filter2 = $permissions;
				include (ROOT_PATH . '/security/qry_IsAuthorized.php');
				if ($qry_result) {
					$results[count($results)] = $line;
					$how_many--;
					} // if qry_result
				} // while
				
			if (count($results) == 0) { return false; }
				
			return $results;
			}
			
		function PrepareOutput($items, $my_permissions) {
			/************************************************************************
			* given an array of news $items and client permissions, return 					|
			* properly formatted html for output.																		|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|		
			* Format is:																														|
			*	<ul class="news">																											|
			*		<span class="head">News for 05-01-2005</span>												|
			*		<li><p>The Cedar Point Itinerary for Spring Athletic Band has been 	|
			*					 posted into the member's only section.</p></li>							|
			*	</ul>																																	|
			*																																				|		
			************************************************************************/
			
			# clear $previous, $results just in case
			if (isset($previous)) { unset($previous); }
			if (isset($results)) { unset($results); }
			
			if (is_array($items)) {
				# this is an array of events
		
				for ($icounter = 0; $icounter < count($items); $icounter++) {
					# split item into component array
					$temp = explode('|', $items[$icounter]);
					
					# check security
					$my_permissions[0] = 1;	// everyone has public access
					$qry_filter1 = $temp[3];
					$qry_filter2 = implode("", $my_permissions);
					
					include (ROOT_PATH . '/security/qry_IsAuthorized.php');
					
					if ($qry_result) {
						# Access Granted		
						# split date into components
						$year = left($temp[0], 4);
						$month = left(right($temp[0], 4), 2);
						$day = right($temp[0], 2);
						
						# Set close tag (edit or no...)
						if (isset($closetag)) { unset($closetag); }
						$qry_filter1 = $temp[4];
						$qry_filter2 = implode("", $my_permissions);
						include (ROOT_PATH . '/security/qry_IsAuthorized.php');
						if ($qry_result) {
							# Edit Permission Granted
							$closetag = '<span>[ <a href="?action=a015&s=2&id=';
							$closetag .= $temp[1] . '">Edit</a> ]</span>';
							}
						
						$closetag .= '</li>';
						
						if ($temp[0] != $previous) {
							# output footer for previous row if necessary
							if ($results != "") { $results .= "\r\n\t\t</ul>"; }
							# output row header
							$results .= "\r\n\t\t<ul class=\"news\">\r\n\t\t\t<li class=\"head\">News for ";
							$results .= $month . '-' . $day . '-' . $year . $closetag;
							} // if 
							
						$results .= "\r\n\t\t\t<li><p>" . $temp[2] . "</p></li>";
						
						# set previous for next iteration
						$previous = $temp[0];
						} else {
							# Access Denied, retrieve next news item
							} // if qry_result (security check)
					} // for
			} else {
				# single item
				$temp = explode('|', $items);
				
				# check security
				$my_permissions[0] = 1;	// everyone has public access
				$qry_filter1 = $temp[3];
				$qry_filter2 = implode("", $my_permissions);
				include (ROOT_PATH . '/security/qry_IsAuthorized.php');
				
				if ($qry_result) {
					# Access Granted				
					# split date into components
					$year = left($temp[0], 4);
					$month = left(right($temp[0], 4), 2);
					$day = right($temp[0], 2);
					
					# output row header and text
					$results .= "\r\n\t\t<ul class=\"news\">\r\n\t\t\t<span class=\"head\">News for ";
					$results .= $month . '-' . $day . '-' . $year . "</span>";			
					$results .= "\r\n\t\t\t<li><p>" . $temp[2] . "</p></li>";
					} // if qry result
				} // if is array
					
			# output footer for previous row if necessary
			if ($results != "") { $results .= "\r\n\t\t</ul>"; }
			
			return $results;
			}
		
		function UpdateItem($id, $month, $day, $year, $desc, $view, $edit) {
			/************************************************************************
			* update news item $id
			* =======================================================================
			* William Strucke, 2005.06.30
			* -----------------------------------------------------------------------
			*	rev. 2006.02.28 - convert ampersands to appropriate markup
			*
			************************************************************************/
			global $_APP;

			if ($id == "") { return false; }
			
			# prepare permissions
			if ($view[0] != "") { $view_permis = implode("", $view); }
			if ($edit[0] != "") { $edit_permis = implode("", $edit); }
			$view_permis .= "67";
			$edit_permis .= "67";
			
			# ensure variables do not contain the bar character or line break as that 
			# would severely cripple the entire system
			$desc = str_replace("\r\n", "<br />", $desc);
			$desc = str_replace("|", "&zwnj;", $desc);
			
			# convert any URL ampersand symbols to the proper markup to validate: "&amp;"
			# - instead of trying to identify each instance of "&" alone and not in the context
			#		of an existing "&amp;", we'll first convert any "&amp;"'s to a basic "&" and then
			#		get all of them at once - this will avoid extending an "&amp;" to "&amp;amp;"
			$desc = str_replace("&amp;", "&", $desc);
			$desc = str_replace("&", "&amp;", $desc);
			
			$newentry = $year . $month . $day . '|' . $id . '|' . $desc . '|' . implode("", parseaccesslevel($view_permis));
			$newentry .= '|' . implode("", parseaccesslevel($edit_permis)) . '|' . $_SESSION['user'];
			$newentry .= '|' . date("m/d/Y|H:i:s") . "\r\n";
			
			#update_line("news.wb", "1", $id, $newentry);
			
			# this could potentially de-sort the file - have to use own function!!
			
			$GetLine = dumpfile('news.wb');
		
			for ($n = 0; $n < count($GetLine); $n++) {
				$data = explode('|', trim($GetLine[$n]));
				if ($data[1] == $id) { /* update the field */ $GetLine[$n] = $newentry; }
				}
				
			# sort by date
			rsort($GetLine, SORT_NUMERIC);
			
			markSiteAsUpdated();
			
			# write the file back
			return write_new_file("news.wb", $GetLine);	
			}
			
		function OutputAddForm() {
			/************************************************************************
			* output form for adding a new news item																|
			* ======================================================================|
			* William Strucke, 2005.06.30																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			global $message;
			?>
			<div class="form">
				<form name="add" action="?action=a015&s=1" method="post">
					<?php 
						# Display any waiting messages
						if (isset($message)) {
							echo "\r\n\t\t\t<span class=\"message\">$message</span>\r\n\t\t\t";
							unset ($message);
							}
							
						# set form to return if specified
						if (isset($_GET['return'])) { 
							echo "\r\n\t\t\t<input name=\"return\" type=\"hidden\" value=\"1\" />"; 
							echo "\r\n\t\t\t<input name=\"d\" type=\"hidden\" value=\"" . $_GET['d'] . "\" />"; 
							echo "\r\n\t\t\t<input name=\"m\" type=\"hidden\" value=\"" . $_GET['m'] . "\" />"; 
							echo "\r\n\t\t\t<input name=\"y\" type=\"hidden\" value=\"" . $_GET['y'] . "\" />"; 
							}
						?>
					<script language="javascript" type="text/javascript">var cal1x = new CalendarPopup("pop_calendar");</script>
					<div ID="pop_calendar" STYLE="position:absolute;margin-top:-262px;margin-left:-30px;visibility:hidden;background-color:white;layer-background-color:white;"></div>
					<h1>Add an Event</h1>
					<div class="row">
						<span class="label">Announcement Date (mm/dd/yyyy):</span>
						<span class="in"><input name="date" size="10" value="<?php echo date("m/d/Y"); ?>" />
														 <A HREF="#" 
																onClick="cal1x.select(document.forms[0].date,'anchor1x','MM/dd/yyyy'); return false;" 
																TITLE="cal1x.select(document.forms[0].date,'anchor1x','MM/dd/yyyy'); return false;" 
																NAME="anchor1x" ID="anchor1x">select</A>
															</span>
					</div>
					<div class="row">
						<span class="label">Description:</span>
						<span class="in"><textarea name="description" rows="6"></textarea></span>
					</div>
					<div class="row">
						<span class="label">View Permissions:</span>
						<span class="in">
							<select name="view[]" multiple="true" style="width: 150px;">
								<option value="0" selected>Public
								<option value="1">Athletic Band
								<option value="2">Marching Band
								<option value="3">Student Staff
								<option value="4">MB Squad Leaders
								<option value="5">Staff Leaders
							</select>
						</span>
					</div>
					<div class="row">
						<span class="label">Edit Permissions:</span>
						<span class="in">
							<select name="edit[]" multiple="true" style="width: 150px;">
								<option value="3" selected>Student Staff
								<option value="4">MB Squad Leaders
								<option value="5">Staff Leaders
							</select>
						</span>
					</div>
					<div class="row">
						<input name="submit" type="submit" value="Submit" class="submit" onClick="return checkFields();" />
					</div>
					<div class="spacer">&nbsp;</div>
				</form>
			</div>
			
			<br /><br />
			<?php
			
			} // OutputAddForm
		
		function OutputUrgent() {
			/************************************************************************
			* check for and output urgent news item (if one exists)									|
			* ======================================================================|
			* William Strucke, 2005.08.01																						|
			* ----------------------------------------------------------------------|
			*																																				|
			************************************************************************/
			# scope application/config variable
			global $_APP;
			
			// Output Urgent News using fxNewsTicker Javascript Class
			if ($_APP['urgent_news'] != '') include('dsp_urgentNewsBulletin.php');
			
			/* Output Urgent News Using Java Applet (SLOW!) *
			if ($_APP['urgent_news'] != '') {
				$tmp = "\r\n\t\t<div align=\"center\">\r\n\t\t";
				$tmp .= "<applet name=\"newsTicker\" code=\"Corf_Scroller.class\" width=\"470\" height=\"22\">";
				$tmp .= "\r\n\t\t\t<param name=\"Corf_Timer\" value=\"15\" />";
				$tmp .= "\r\n\t\t\t<param name=\"Corf_Link\" value=\"" . $_APP['urgent_news_link'] . "\" />";
				$tmp .= "\r\n\t\t\t<param name=\"Corf_Text\" value=\"" . $_APP['urgent_news'] . "\" />";
				$tmp .= "\r\n\t\t\t<param name=\"Corf_Foreground\" value=\"ffffff\" />";
				$tmp .= "\r\n\t\t\t<param name=\"Corf_Background\"  value=\"cc0000\" />";
				$tmp .= "\r\n\t\t\tOSUMB Site v2.0 Beta\r\n\t\t</applet>\r\n\t\t</div><br />";
				echo $tmp;
				} // if app urgent news
			*/
			} // Output Urgent
		
		function AllowEdit($myPermissions, $item_id) {
			/********************************************************************************************
			 *	return true if permission list is allowed to modify specified item											|
			 *	=========================================================================================
			 *	William Strucke [strucke.1@osu.edu], 2005.08.08																					|
			 *	-----------------------------------------------------------------------------------------
			 *	$myPermissions is 8 byte permission list in binary digits																|
			 *	$page_id is selected page's id number																										|
			 *																																													|
			 *	returns true if granted, false if access denied																					|
			 *																																													|
			 *******************************************************************************************/
				# ensure we have valid input
				if (($myPermissions === false) || ($item_id === false)) { return false; }
				
				# retrieve item permissions
				$qry_filter1 = retrieve_var('news.wb', '1', $item_id, '4');
				
				# ensure we have a value
				if ($qry_filter1 === false) { return false; }
				
				# prepare second query value
				$qry_filter2 = $myPermissions;
				
				# execute query
				include (ROOT_PATH . '/security/qry_IsAuthorized.php');
				
				# return result
				return $qry_result;
				
				} // AllowEdit
	
		} // news class
	
	?>