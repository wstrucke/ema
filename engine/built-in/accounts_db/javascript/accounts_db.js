/* Javascript Document */

/* ema accounts.db module
 *
 * copyright (c) 2010-2011 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

// vars

var accountsData = {
	'group_columns': Array('gid', 'display_name', 'description', 'enabled'),
	'group_list': false,
	'group_manager': {
		'btn_newUser': { 'caption': 'New Group', 'click': dsp_NewGroupForm }
		},
	'group_options': Array('', 'Enable/Disable Group', 'Delete Group', 'Edit Group Detail',
		'View Membership', 'Permissions'),
	'accounts_manager': {
		'btn_userManager': { 'caption': 'Manage Users', 'click': dsp_UserManager },
		'btn_groupManager': { 'caption': 'Manage Groups', 'click': dsp_GroupManager }
		},
	'user_columns': Array('uid', 'pw_last_set', 'first', 'last', 'display_name', 'email', 'description', 'enabled'),
	'user_list': false,
	'user_manager': {
		'btn_newUser': { 'caption': 'New User', 'click': dsp_NewUserForm }
		},
	'user_options': Array('', 'Reset Password', 'Enable/Disable Account', 'Delete Account', 'Edit Account Detail',
		'Group Membership', 'Permissions')
};

var accountsUrls = {
	'create_group': '<?php echo url('xml/accounts_db/group_create'); ?>',
	'create_user': '<?php echo url('xml/accounts_db/user_create'); ?>',
	'delete_group': '<?php echo url('xml/accounts_db/group_delete'); ?>',
	'delete_user': '<?php echo url('xml/accounts_db/user_delete'); ?>',
	'disable_group': '<?php echo url('xml/accounts_db/group_disable'); ?>',
	'disable_user': '<?php echo url('xml/accounts_db/user_disable'); ?>',
	'enable_group': '<?php echo url('xml/accounts_db/group_enable'); ?>',
	'enable_user': '<?php echo url('xml/accounts_db/user_enable'); ?>',
	'list_groups': '<?php echo url('xml/accounts_db/group_list'); ?>',
	'list_users': '<?php echo url('xml/accounts_db/user_list'); ?>',
	'reset_password': '<?php echo url('xml/accounts_db/user_set_password'); ?>'
};

// window defs

var def_NewGroupForm = {
	gid: { 'label': 'Group ID', required: true },
	display_name: { 'label': 'Display Name' },
	description: { 'label': 'Description' },
	enabled: { 'label': 'Enabled', type: 'checkbox', checked: true },
	btn_save: { label: 'Create Group', type: 'button' }
};

var def_NewUserForm = {
	uid: { 'label': 'User ID', required: true },
	password: { 'label': 'Passphrase', type: 'password', required: true },
	first: { 'label': 'First Name' },
	//middle: { 'label': 'Middle Initial' },
	last: { 'label': 'Last Name' },
	display_name: { 'label': 'Display Name' },
	email: { 'label': 'E-mail Address' },
	//phone: { 'label': 'Contact Number' },
	description: { 'label': 'Description' },
	//mobile: { 'label': 'Mobile Number' },
	//pager: { 'label': 'Pager Number' },
	//office: { 'label': 'Office' },
	//address: { 'label': 'Street Address' },
	//city: { 'label': 'City' },
	//state: { 'label': 'State' },
	//zip: { 'label': 'Postal Code' },
	//country: { 'label': 'Country' },
	//web_url: { 'label': 'Home Page URL' },
	enabled: { 'label': 'Enabled', type: 'checkbox', checked: true },
	btn_save: { label: 'Create Account', type: 'button' }
};

var def_ResetPasswordPrompt = {
	uid: { type: 'hidden' },
	new_password: { label: 'New Password', type: 'password', width: 300, required: true },
	new_password_confirm: { label: 'Confirm Password', type: 'password', width: 300, required: true },
	btn_save: { label: 'Reset Password', type: 'button' }
};

// init

window.addEvent('domready', function() { createCommandMenu(accountsData.accounts_manager, 'cms_cpanel_buttons').inject($('workspace')); });

// code

function createCommandMenu(buttonDef, menu_id)
/* build a command menu form (structurally 'ul') with the provided button definition object
 *
 */
{
	var menu = new Element('ul');
	if (menu_id) menu.set('id', menu_id);
	for (id in buttonDef) {
		var b = new Element('li');
		if (buttonDef[id]['caption']) {
			b.set('html', buttonDef[id]['caption']);
		} else {
			b.set('html', id);
		}
		if (buttonDef[id]['click']) b.addEvent('click', buttonDef[id]['click']);
		b.inject(menu);
	}
	return menu;
}

function deleteGroup(ele)
/* delete selected group
 *
 */
{
	gid = ele.getParent().getSiblings()[0].get('html');
	if (! confirm('Are you sure you want to permanently delete the group `' + gid + '`?')) return false;
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: accountsUrls.delete_group,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				dsp_GroupList(true);
				showNotice('Group deleted successfully.');
			} catch(er) {}
		}
	});
	myRequest.send('gid=' + encodeURIComponent(gid));
	showWaiting('Deleting group...');
}

function deleteUser(ele)
/* delete selected account
 *
 */
{
	uid = ele.getParent().getSiblings()[0].get('html');
	if (! confirm('Are you sure you want to permanently delete the account for `' + uid + '`?')) return false;
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: accountsUrls.delete_user,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				dsp_UserList(true);
				showNotice('Account deleted successfully.');
			} catch(er) {}
		}
	});
	myRequest.send('uid=' + encodeURIComponent(uid));
	showWaiting('Deleting account...');
}

function dsp_GroupList(reload)
/* display the list of group accounts in the workspace
 *
 */
{
	if (reload !== true) reload = false;
	if ((accountsData.group_list === false)||reload) return enqueue({
		url: accountsUrls.list_groups,
		silent: true,
		onSuccess: function(x) {
			try {
				xmlDoc = parseXmlResponse(x);
				list = xmlDoc.getElements('group');
				if (list == undefined) { num = 0; } else { num = list.length; }
				accountsData.group_list = Array();
				for (i=0;i<num;i++) {
					record = Array();
					for (c=0;c<accountsData.group_columns.length;c++) {
						record[c] = list[i].getElement(accountsData.group_columns[c]).textContent;
					}
					accountsData.group_list[i] = record;
				}
				dsp_GroupList();
			} catch(e){ showNotice('Error: ' + e); }
		}
	}, true);
	
	// format the group list data for output
	var list = {data:Array(),header:Array(),id:'accounts_list'};
	list.header = $A(accountsData.group_columns);
	list.header[list.header.length] = '&nbsp;';
	for (i=0;i<accountsData.group_list.length;i++) {
		selectHtml = '';
		for (j=0;j<accountsData.group_options.length;j++) {
			selectHtml += '<option>' + accountsData.group_options[j] + '</option>';
		}
		accountsData.group_list[i].push('<select>' + selectHtml + '</select>');
	}
	list.data = accountsData.group_list;
	
	var table = createTable(list);
	if ($('accounts_list') != null) {
		table.replaces($('accounts_list'));
	} else {
		table.inject($('workspace'));
	}
	
	$$('table.colorList select').addEvent('change', function(){
		switch(this.value){
			case '': break;
			case 'Delete Group': deleteGroup(this); break;
			case 'Enable/Disable Group': toggleGroup(this); break;
			default: showNotice('Option Not Implemented'); break;
		}
		this.value = '';
	});
}

function dsp_GroupManager()
/* display the group management interface in the workspace
 *
 */
{
	menu = createCommandMenu(accountsData.group_manager, 'command_menu');
	if ($('command_menu') != null) {
		menu.replaces($('command_menu'));
	} else {
		menu.inject($('workspace'));
	}
	dsp_GroupList();
}

function dsp_NewGroupForm()
/* pop up a window to create a new group account
 *
 */
{
	createWindow('New Group', def_NewGroupForm, true);
	$('gid').focus();
	// add button events
	$('btn_save').addEvent('click', function(e) {
		// validate required fields
		gid = $('gid').get('value');
		if (gid.length == 0) return showNotice('Error: A group id is required.');
		// load data
		createStr = '';
		for (i in def_NewGroupForm) {
			if (i == 'gid') continue;
			if (def_NewGroupForm[i]['type'] == 'checkbox') {
				if ($(i).get('checked')) { value = 1; } else { value = 0; }
			} else {
				value = encodeURIComponent($(i).get('value'));
			}
			createStr += '&' + i + '=' + value;
		}
		// post to xml
		var myRequest = new Request.HTML({
			method: 'post',
			url: accountsUrls.create_group,
			onSuccess: function(t,e,h,j){
				hideWaiting();
				// if error show message and stop here
				try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
				try {
					// on success remove meta window
					$$('#overlay', '#window').destroy();
					dsp_GroupList(true);
					showNotice('Group created successfully.');
				} catch(er) {}
			}
		});
		myRequest.send('gid=' + encodeURIComponent(gid) + createStr);
		showWaiting('Creating group...');
	});
}

function dsp_NewUserForm()
/* pop up a window to create a new user account
 *
 */
{
	createWindow('New User Account', def_NewUserForm, true);
	$('uid').focus();
	// add button events
	$('btn_save').addEvent('click', function(e) {
		// validate required fields
		uid = $('uid').get('value');
		password = $('password').get('value');
		if (uid.length == 0) return showNotice('Error: A user id is required.');
		//if (password != $('new_password_confirm').get('value')) { return showNotice('Error: Passwords do not match'); }
		if (password.length < 1) { return showNotice('Error: Empty passwords are not allowed'); }
		if (password.length < 8) { if (! confirm('Are you sure you want to set a password fewer than 8 characters?')) return false; }
		// load data
		createStr = '';
		for (i in def_NewUserForm) {
			if ((i == 'uid')||(i == 'password')) continue;
			if (def_NewUserForm[i]['type'] == 'checkbox') {
				if ($(i).get('checked')) { value = 1; } else { value = 0; }
			} else {
				value = encodeURIComponent($(i).get('value'));
			}
			createStr += '&' + i + '=' + value;
		}
		// post to xml
		var myRequest = new Request.HTML({
			method: 'post',
			url: accountsUrls.create_user,
			onSuccess: function(t,e,h,j){
				hideWaiting();
				// if error show message and stop here
				try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
				try {
					// on success remove meta window
					$$('#overlay', '#window').destroy();
					dsp_UserList(true);
					showNotice('User created successfully.');
				} catch(er) {}
			}
		});
		myRequest.send('uid=' + encodeURIComponent(uid) + '&password=' + encodeURIComponent(password) + createStr);
		showWaiting('Creating user...');
	});
}

function dsp_ResetPasswordPrompt(ele)
/* pop up a window to reset the password for the provided account
 *
 */
{
	uid = ele.getParent().getSiblings()[0].get('html');
	createWindow('Reset Password for ' + uid, def_ResetPasswordPrompt, true);
	
	// set uid value
	$('uid').set('value', uid);
	
	// focus
	$('new_password').focus();
	
	// add button events
	$('btn_save').addEvent('click', function(e) {
		// get the new password
		new_password = $('new_password').get('value');
		// validate the new password
		if (new_password != $('new_password_confirm').get('value')) { return showNotice('Error: Passwords do not match'); }
		if (new_password.length < 1) { return showNotice('Error: Empty passwords are not allowed'); }
		if (new_password.length < 8) { if (! confirm('Are you sure you want to set a password fewer than 8 characters?')) return false; }
		// post to xml
		var myRequest = new Request.HTML({
			method: 'post',
			url: accountsUrls.reset_password,
			onSuccess: function(t,e,h,j){
				hideWaiting();
				// if error show message and stop here
				try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
				try {
					// on success remove meta window
					$$('#overlay', '#window').destroy();
					dsp_UserList(true);
					showNotice('Password changed successfully.');
				} catch(er) {}
			}
		});
		myRequest.send('uid=' + encodeURIComponent($('uid').get('value')) + '&password=' + encodeURIComponent(new_password));
		showWaiting('Changing password...');
	});
}

function dsp_UserForm()
/* pop up a window to add or modify a user account
 *
 */
{
	// to do ...
	//createWindow('New User Account', def_NewUserForm, true);
	//$('uid').focus();
}

function dsp_UserList(reload)
/* display the list of user accounts in the workspace
 *
 */
{
	if (reload !== true) reload = false;
	if ((accountsData.user_list === false)||reload) return enqueue({
		url: accountsUrls.list_users,
		silent: true,
		onSuccess: function(x) {
			try {
				xmlDoc = parseXmlResponse(x);
				list = xmlDoc.getElements('user');
				if (list == undefined) { num = 0; } else { num = list.length; }
				accountsData.user_list = Array();
				for (i=0;i<num;i++) {
					record = Array();
					for (c=0;c<accountsData.user_columns.length;c++) {
						record[c] = list[i].getElement(accountsData.user_columns[c]).textContent;
					}
					accountsData.user_list[i] = record;
				}
				dsp_UserList();
			} catch(e){showNotice('dsp_UserList error: ' + e);}
		}
	}, true);
	
	// format the user list data for output
	var list = {data:Array(),header:Array(),id:'accounts_list'};
	list.header = $A(accountsData.user_columns);
	list.header[list.header.length] = '&nbsp;';
	for (i=0;i<accountsData.user_list.length;i++) {
		selectHtml = '';
		for (j=0;j<accountsData.user_options.length;j++) {
			selectHtml += '<option>' + accountsData.user_options[j] + '</option>';
		}
		accountsData.user_list[i].push('<select>' + selectHtml + '</select>');
	}
	list.data = accountsData.user_list;
	
	var table = createTable(list);
	if ($('accounts_list') != null) {
		table.replaces($('accounts_list'));
	} else {
		table.inject($('workspace'));
	}
	
	$$('table.colorList select').addEvent('change', function(){
		switch(this.value){
			case '': break;
			case 'Reset Password': dsp_ResetPasswordPrompt(this); break;
			case 'Enable/Disable Account': toggleUser(this); break;
			case 'Delete Account': deleteUser(this); break;
			default: showNotice('Option Not Implemented'); break;
		}
		this.value = '';
	});
}

function dsp_UserManager()
/* display the user management interface in the workspace
 *
 */
{
	menu = createCommandMenu(accountsData.user_manager, 'command_menu');
	if ($('command_menu') != null) {
		menu.replaces($('command_menu'));
	} else {
		menu.inject($('workspace'));
	}
	dsp_UserList();
}

function toggleGroup(ele)
/* enable or disable account depending on current setting
 *
 */
{
	gid = ele.getParent().getSiblings()[0].get('html');
	current = ele.getParent().getSiblings()[3].get('html');
	if (current == '1') {
		cmd = accountsUrls.disable_group;
	} else {
		cmd = accountsUrls.enable_group;
	}
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: cmd,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				dsp_GroupList(true);
				showNotice('Group updated successfully.');
			} catch(er){}
		}
	});
	myRequest.send('gid=' + encodeURIComponent(gid));
	showWaiting('Updating group...');
}

function toggleUser(ele)
/* enable or disable account depending on current setting
 *
 */
{
	uid = ele.getParent().getSiblings()[0].get('html');
	current = ele.getParent().getSiblings()[7].get('html');
	if (current == '1') {
		cmd = accountsUrls.disable_user;
	} else {
		cmd = accountsUrls.enable_user;
	}
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: cmd,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				dsp_UserList(true);
				showNotice('Account updated successfully.');
			} catch(er) {}
		}
	});
	myRequest.send('uid=' + encodeURIComponent(uid));
	showWaiting('Updating account...');
}