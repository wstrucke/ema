<?php
# process a posted permissions change

# check for an update directive
if (isset($_POST['update'])) {
	
	# get the account settings
	$sguid = $_POST['sguid'];
	$settings = $sec->get_client($sguid);
	
	$non_inherited_items = array_keys($sec->get_authorized_items($sguid));
	
	if (array_key_exists('item', $_REQUEST)&&is_array($_REQUEST['item'])) {
		$list = $_REQUEST['item'];
	} else {
		$list = array();
	}
	
	# revoke all items that are direct permissions, but are not checked
	foreach( array_diff($non_inherited_items, $list) as $item ) $sec->revoke_access($sguid, $item);
	
	# grant all items that are checked and not in the database
	foreach( array_diff($list, $non_inherited_items) as $item ) $sec->grant_access($sguid, $item);
	
	if ($settings['type'] == 'role') {
		$name = $sec->role_name($_REQUEST['sguid']);
	} elseif (is_object($sec->modules[$settings['module_id']]) && $sec->modules[$settings['module_id']]->{$settings['type']}->load($settings['guid'], 'unique_id')) {
		$name = $sec->modules[$settings['module_id']]->{$settings['type']}->name;
	} else {
		$name = 'unknown';
	}
	
	$message = 'Permissions updated for client ' . $name;
}

?>

<p><em>Inherited permissions appear greyed out.  You cannot grant someone explicit permissions 
if he or she already has access through inheritance.</em></p>
<?php
# Output any queued messages
if (!isset($message_type)) $message_type = 'note';
if (isset($message)) echo "<div class=\"$message_type box\">$message</div>";

# initialize variables
$sguid = false;

if (array_key_exists('submit', $_POST)&&($_POST['submit']=='Go')) {
	# special case to handle new accounts
	$module_id = intval($_POST['module_id']);
	$type = $_POST['type'];
	$uid = $_POST['uid'];
	if ($sec->modules[$module_id]->{$type}->load($uid)===false) die('Unable to find or load account');
	$sguid = $sec->get_sguid($sec->modules[$module_id]->{$type});
	$name = $sec->modules[$module_id]->{$type}->name . " ($uid)";
} elseif (array_key_exists('permissions', $_REQUEST)&&array_key_exists('type', $_REQUEST)&&array_key_exists('module_id', $_REQUEST)) {
	# user/group permissions
	$module_id = $_REQUEST['module_id'];
	$type = $_REQUEST['type'];
	$uid = $_REQUEST['permissions'];
	if ($sec->modules[$module_id]->{$type}->load($uid)) {
		$sguid = $sec->get_sguid($sec->modules[$module_id]->{$type});
	} else {
		die('Unable to load the requested account');
	}
	$name = $sec->modules[$module_id]->{$type}->name . " ($uid)";
} elseif (array_key_exists('permissions', $_REQUEST)&&(strlen($_REQUEST['permissions']) > 0)) {
	# role or sguid provided
	$sguid = $_REQUEST['permissions'];
	$name = $sec->role_name($sguid);
	$type = 'role';
	$detail = $sec->get_client($sguid);
	$role_id = intval($detail['guid']);
} else {
	die('Invalid authentication object provided');
}

echo l('Return', 'admin/security') . "<br /><br />";

if ((strcasecmp($sguid, 'PUBLIC')===0)||(strcasecmp($sguid, 'AUTHENTICATED')===0)||(strcasecmp($sguid, 'NONE')===0)) {
	echo "<em><strong>WARNING: Setting an item to the special role '" . strtoupper($sguid) . "' will automatically revoke access for *all other accounts*. Please use extreme caution.  You have been warned.</strong></em><br /><br />";
}

echo "Object inherits from:<br />";
foreach ($sec->role_membership($sguid) as $parent_sguid=>$v) printf("&nbsp;&nbsp;%s<br />", $sec->role_name($sec->role_sguid($parent_sguid)));
echo "<br />";

if ($type === 'role') {
	echo "Role members:<br />";
	foreach ($sec->role_members($role_id) as $child_sguid) {
		$child_detail = $sec->get_client($child_sguid);
		$ctype = $child_detail['type'];
		$cmid = intval($child_detail['module_id']);
		if ($ctype == 'role') {
			$cname = $sec->role_name($child_sguid);
		} else {
			if ($sec->modules[$cmid]->{$ctype}->load($child_detail['guid'], 'unique_id') === false) { echo "Error loading $cmid $ctype " . $child_detail['guid'] . "<br />"; }
			$cname = $sec->modules[$cmid]->{$ctype}->name . ' (' . $sec->modules[$cmid]->{$ctype}->uid . ')';
		}
		printf("&nbsp;&nbsp;%s: %s<br />", mb_convert_case($ctype, MB_CASE_TITLE), $cname);
	}
	echo "<br />";
}

# build the return link
$link = url("admin/security?permissions=");
if ($type != 'role') { $link.= "$uid&type=$type&module_id=$module_id"; } else { $link.=$sguid; }
?>

Permissions for <strong><?php echo $name; ?></strong>:<br /><br />

<form id="form" action="<?php echo $link; ?>" method="post" style="font-size: 0.79em;">

	<input type="submit" name="update" value="Update Permissions" />
	
	<input type="hidden" name="route" value="securityCenter.toolbox.permissions" />
	<input type="hidden" name="sguid" value="<?php echo $sguid; ?>" />
	
	<br /><br />
	
<?php
	$authd_items = $sec->get_authorized_items($sguid);
	if (!is_array($authd_items)) $authd_items = array();
	
	foreach ( $sec->get_items() as $item ) {
		$attributes = "";
		if (array_key_exists($item, $authd_items)) {
			$attributes .= "checked='checked'";
		} elseif ( ($sguid != 'NONE') && ($sguid != 'AUTHENTICATED') && $sec->access($item, $sguid) ) {
			//check for inheritance
			$attributes .= "checked='checked' disabled='disabled'";
		}
		printf("<input type=\"checkbox\" name=\"item[]\" value=\"%s\" %s />&nbsp;%s<br />\r\n", $item, $attributes, $item);
	}
?>
	
	<br />
	<input type="submit" name="update" value="Update Permissions" />
</form>