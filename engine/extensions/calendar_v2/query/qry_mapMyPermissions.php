<?php
 /*	Calendar Class
 	*
	*	Connect my site's permissions to the
	*	simple calendar permissions v2.0 Alpha 1
	*
	*	Version 1.0 : 2006.08.27
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	/* There are three permissions levels for the
	 * 2.0 Alpha release of the calendar:
	 *	user, manager, and admin
	 *
	 * all you have to do is check what the user
	 * in the current session should have access to
	 * and assign the appropriate security level
	 *
	 * the default level is user.  if you do not provide
	 * a level, the client will be in the user group.
	 * if you specifiy an invalid value, the client will
	 * be in the user group.
	 *
	 * user has read only access to the entire calendar
	 * manager has read/write/delete access to the entire calendar
	 * admin has manager access plus access to the admin menu, 
	 * 	currently non-functional... but you can see the settings
	 *
	 */
	 
	$securityLevel = '';
	
	if ($this->_tx->security->access('calendar.admin')) {
		$securityLevel = 'admin';
	} elseif ($this->_tx->security->access('calendar.manager')) {
		$securityLevel = 'manager';
	}
	
?>