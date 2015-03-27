<?php
 /*	login and authentication functions
	*
	*	Version 1.0 : 2005.06.19
	* Version 2.0 : 2007.02.11
	*
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	function SiteIsLocked(&$_APP, &$user)
	{
		/* Return false is this user can access the site or true if they can not
		 *
		 */
		
		# lockout check
		if ($_APP['sitelock'] == '1') 
		{
			# site is locked, check if this is an admin user
			$qry_filter = $user;
			include (QUERY_PATH . 'qry_get_user_rights.php');
			if ($qry_result[7] == '1') 
			{
				# administrative user, continue login authentication
				return false;
			} else {
				# non-admin, disable site
				return true;
			} // else
		} else {
			# Site is unlocked, return false
			return false;
		} // if app sitelock
	} // SiteIsLocked

	function checkauthorized($name) 
	{ 
		/* Function checkauthorized returns access level for given user name */ 
		$userdata = retrieve_line("grant.wb", "0", $name, true);
		if ($userdata != "0") { return $userdata[1]; }
		return 0;
	} // end checkauthorized

	function loginagain($errorStatus) 
	{
		# update failed logins
		global $console, $_APP, $redirect;
		
		# Set Error Message from Status Code
		switch ($errorStatus) {
			case 0:
				/* Kerberos Login Verification Failed */
				$message = 'An error occurred during login. Please check your username and 
										password and try again.<br /><br />
										If problems persist, please review the 
										<a title="How to Log In" 
											 href="https://webauth.service.ohio-state.edu/shibboleth/loginhelp.html">
										Login Guide</a> and 
										<a title="Common Problems" 
											 href="https://webauth.service.ohio-state.edu/shibboleth/faq.html">
										FAQ List</a>. If you need further assistance, please contact the Technology 
										Support Center at 614-688-HELP (or <a href="mailto:8help@osu.edu">8help@osu.edu</a>).';
				break;
			case 1:
				/* Unauthorized Account */
				$message = 'Though your identity has been verified, you are not authorized to access this area
										of our website.<br /><br />
										If you believe this is in error, please contact the 
										<a title="Contact Us" href="http://tbdbitl.osu.edu/?action=a021">Band Office</a> 
										at 614-292-2598 (or <a href="mailto:osumb@osu.edu">osumb@osu.edu</a>).';
				break;
			case 2:
				/* Logins are Administratively Disabled */
				$message = $_APP['sitelock_message'];
				break;
			case 3:
				/* Kerberos Login Verification Failed (Pseudo) */
				$message = "Kerberos Authentication Failed For Pseudo-Login";
				break;
			case 4:
				/* Access Denied */
				$message = 'You are not authorized to view the requested content.<br /><br />
										Please <a href="mailto:strucke.1@osu.edu?subject=tbdbitl.osu.edu%20Access%20Dispute">
										contact the webmaster</a> if you think you received this message in error.';
				break;
			case 5:
				/* Hidden Error Message or Just display login page */
				$message = '';
				break;
			default:
				/* Unknown */
				$message = 'An unknown error has occurred during your authentication.<br /><br />
										Please <a href="mailto:strucke.1@osu.edu?subject=tbdbitl.osu.edu%20UNKNOWN%20Authentication%20Error">
										tell us about it</a>.';
				break;
			}
		
		# update auth failed
		$temp = intval($console->GetConfiguration('auth_failed'));
		$console->UpdateConfiguration('auth_failed', $temp + 1);
		
		# set message in session variable
		$_SESSION['message'] = $message;
		
		# set redirect variable to maintain redirect action across multiple login failures
		$redirect = $_POST['redirect'];
		
		# set route
		$action = LOGIN_PAGE;
		
		# output the login to user
		include (DISPLAY_PATH . 'dsp_page_standard.php');
		
		# terminate script if it returns to this point
		exit;
	} // end loginagain

	function addlogentry($user) 
	{
		#add log entry
			
		if (($user != 'strucke.1') && ($user != '')) 
		{
			// add an entry to the site login log
			$newentry = date("Y-m-d|H:i:s") . '|' . $user . '|' . $_SERVER[REMOTE_ADDR] . "|in\r\n";
			$tt = append("loginlog.wb", $newentry);
		}
		
		// add an entry to the last login file
		
		$temp = retrieve_line('lastlogin.wb', '0', $user);
		
		$hits = intval(trim($temp[3])) + 1;
			
		$newentry = $user . '|' . date("Y-m-d|H:i:s") . '|' . $hits;
		
		if (intval($temp) > -1) 
		{
			update_value('lastlogin.wb', implode('|', $temp), $newentry);
		} else {
			append('lastlogin.wb', $newentry . "\r\n");
		}
	} // end addlogentry

	function update_stats() 
	{
		global $a, $pseudo, $console;
		
		# update total logins
		$temp = intval($console->GetConfiguration('total_logins'));
		$console->UpdateConfiguration('total_logins', $temp + 1);
		
		# update stats for specific security groups 
		if (($a[1] == 1) && ($a[2] == 0) && ($a[7] == 0) && ($pseudo == false)) {
			# update athletic band hits
			$temp = intval($console->GetConfiguration('osuab'));
			$console->UpdateConfiguration('osuab', $temp + 1);
			}
			
		if (($a[2] == 1) && ($a[7] == 0) && ($pseudo == false)) {
			# update marching band hits
			$temp = intval($console->GetConfiguration('osumb'));
			$console->UpdateConfiguration('osumb', $temp + 1);
			}
			
		if ((($a[3] == 1) || ($a[6] == 1)) && ($a[2] == 0) && ($a[7] == 0) && ($pseudo == false)) {
			# update staff hits
			$temp = intval($console->GetConfiguration('staff'));
			$console->UpdateConfiguration('staff', $temp + 1);
			}
			
		if ($a[7] == 1) {
			# update admin hits
			$temp = intval($console->GetConfiguration('admin'));
			$console->UpdateConfiguration('admin', $temp + 1);
			$console->UpdateConfiguration('adminlogin', date('Y-m-d') . ' at ' . date('H:i:s'));
			}
		
	} // update_stats

	function set_global_session_vars($user, $access) 
	{
		/*	set session variables that will be retrieved in header.php to decrease
			file paging during normal site access.
		*/
		global $a, $greeting, $console;
		
		# set access permissions
		$a = parseaccesslevel($access);
		
		# clear greeting if it's already in use
		if (isset($greeting)) { unset($greeting); }
		
		# Set User Variables based on security access
		
		if ($a[2] == 1) { 
			# Marching Band
			$data = retrieve_line('osum_band.wb', '0', $user, true);
			if ($data > 0) {
				$fullname = $data[1] . ' ' . $data[2];
				//if (($data[1] == 'Matt') && ($data[2] == 'Wolford')) { $fullname = 'Nancy'; }
				$instrument = $data[4];
				$part = $data[5];
				$row = $data[6];
				/* need to set a new row variable for marching band only
						anyone in both mb and ab will have their row overwritten (below)
						this breaks the phone roster and any other function that utilizes
						the mb specific row.  we'll have to leave the row above as well to
						avoid breaking other things (without recoding the whole site)
				*/
				$mbrow = $data[6];
				$number = $data[7];
				$squadleader = $data[9];
				$greeting = '<p>OSUMB ' . $mbrow . '-' . $number;
				if ($a[4] == 1) { $greeting .= ' <strong>(Squad Leader)</strong>'; }
				//if ($squadleader) { $greeting .= ' <strong>(Squad Leader)</strong>'; }
				$greeting .= '</p>';
				}
			// set row site links and titles
			$rowsite[0] = $console->GetConfiguration(strtolower($row) . 'rowsite');
			$rowsite[1] = $console->GetConfiguration(strtolower($row) . 'rowtext');
			}
			
		if ($a[1] == 1) { 
			# Athletic Band
			$data = retrieve_line('osua_band.wb', '0', $user, true);
			if ($data > 0) {
				$fullname = $data[1] . ' ' . $data[2];
				$section = $data[8];
				if (strlen($section) > 0) { $section = $section . ", "; }
				$row = $data[6];
				$number = $data[7];
				$instrument = $data[4];
				$part = $data[5];
				$greeting .= '<p>OSUAB ' . $row . '-' . $number;
				if ($data[9]) { $greeting .= ' <strong>(Section Leader)</strong>'; }
				$greeting .= '</p>';
				}
			}
			
		if ($a[3] != 1 && $a[5] != 1) { /* do nothing */ }
		else {
			# Staff
			$data = retrieve_line('staff.wb', '0', $user, true);
			if ($data > 0) {
				$fullname = $data[1] . ' ' . $data[2];
				$section = $data[4];
				}
			$greeting .= '<p>Staff ' . $data[4] . '</p>';
			}
			
		if ($a[6] == 1) {
			# Directors
			$data = retrieve_line('staff.wb', '0', $user, true);
			if ($data > 0) {
				$fullname = $data[1] . ' ' . $data[2];
				$section = $data[4];
				}
			$greeting = '<p>' . $data[4] . '</p>';
			}
			
		if ($a[7] == 1) {
			# Website Administrator
			$greeting .= '<p>Website Administrator</p>';
			if ($fullname == '') {
				# not in osuab or osumb, get info from staff file
				$data = retrieve_line('staff.wb', '0', $user, true);
				if ($data > 0) { $fullname = $data[1] . ' ' . $data[2]; $section = $data[4]; }
				} // if
			}
			
		// add link to row site to greeting if applicable
		if ($rowsite[0] != '') { $greeting .= '<p><a href="' . $rowsite[0] . '">' . $rowsite[1] . '</a></p>'; }
		
		// cache file index
		$fileswb = rdumpfile('files.wb');
		$fileswb_count = count($fileswb);
		
		// for pseudo login, clear all previously set session vars
		unset ($_SESSION['section'], $_SESSION['row'], $_SESSION['number'], $_SESSION['instrument']);
		unset ($_SESSION['part'], $_SESSION['rowsite0'], $_SESSION['rowsite1'], $_SESSION['squadleader']);
			
		// write session variables
		for ($i = 0; $i < 8; $i++) { $_SESSION['a' . $i] = $a[$i]; }
		if (isset($section)) { $_SESSION['section'] = $section; }
		if (isset($row)) { $_SESSION['row'] = $row; $_SESSION['sister_row'] = get_sister_row ($row); }
		if (isset($mbrow)) { $_SESSION['mbrow'] = $mbrow; $_SESSION['sister_row'] = get_sister_row ($mbrow); }
		if (isset($number)) { $_SESSION['number'] = $number; }
		if (isset($instrument)) { $_SESSION['instrument'] = $instrument; }
		if (isset($part)) { $_SESSION['part'] = $part; }
		$_SESSION['greeting'] = $greeting;
		$_SESSION['user'] = $user;
		$_SESSION['fullname'] = $fullname;
		$_SESSION['fileswb_count'] = $fileswb_count;
		if (isset($squadleader)) { $_SESSION['squadleader'] = $squadleader; }
		for ($i = 0; $i <= $fileswb_count; $i++) { $_SESSION['fileswb' . $i] = $fileswb[$i]; }	# cache files.wb
	} // set_global_session_vars

	function get_sister_row ($row) 
	{
		switch ($row) {
			case 'A':
				return 'X';
				break;
			case 'B':
				return 'T';
				break;
			case 'C':
				return 'S';
				break;
			case 'D':
				#return '';
				break;
			case 'E':
				return 'R';
				break;
			case 'F':
				return 'Q';
				break;
			case 'H':
				return 'M';
				break;
			case 'I':
				return 'J';
				break;
			case 'J':
				return 'I';
				break;
			case 'K':
				return 'L';
				break;
			case 'L':
				return 'K';
				break;
			case 'M':
				return 'H';
				break;
			case 'Q':
				return 'F';
				break;
			case 'R':
				return 'E';
				break;
			case 'S':
				return 'C';
				break;
			case 'T':
				return 'B';
				break;
			case 'X':
				return 'A';
				break;
			} // switch
		
		return '';
	} // get_sister_row

?>