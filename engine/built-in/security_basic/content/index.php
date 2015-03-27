<?php
	# add security javascript to header
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/security.js')));
	$sec =& $t->security;
?>
	
	<div>
		<h1>security manager control panel<div><?php echo l('close', 'admin'); ?></div></h1>
	</div>
	
	<!-- <div id="workspace">&nbsp;</div> -->
	
	<br style="clear: both;" />
	
<?php
	if (array_key_exists('permissions', $_REQUEST)||array_key_exists('uid', $_REQUEST)) { include('permissions.php');	} else {
	# Output any queued messages
  if (!isset($message_type)) $message_type = 'note';
  if (isset($message)) echo "<div class=\"$message_type box\">$message</div>";
?>
	
  <form id="form" action="<?php echo url('admin/security'); ?>" method="post">
  	To manage access permissions, please select a user, group, or role from the list below <em>or</em> use the manual 
  	entry box to set explicit permissions for an unlisted, existing user or group account.
  	<div style="display: block; margin: 10px 0 10px 10px; border: 1px solid #ccc; padding: 10px;">
	  	<input type="text" name="uid" value="Type name in here..." onclick="this.value='';" style="width: 300px;" />
	  	Type:
	  	<select name="type" onchange="if (this.get('value') == 'role') { $('security_module_id').set('value',''); }">
	  		<option value="user" selected="selected">User</option>
	  		<option value="group">Group</option>
	  		<!-- <option value="role">Role</option> -->
	  	</select>
	  	Module:
	  	<select name="module_id" id="security_module_id">
	  	<?php foreach($sec->get_modules() as $m) {
	  		if (is_string($m['instance_name'])&&(strlen($m['instance_name'])>0)) {
	  			$instance_name = $m['instance_name'];
	  		} else {
	  			$instance_name = $m['name'];
	  		}
	  		printf('<option value="%s">%s</option>', $m['id'], $instance_name);
	  	} ?>
	  	</select>
	  	<input type="submit" name="submit" value="Go" />
  	</div>
  	
  	<ul>
  		<li class="head">Roles:</li>
  		<li><a href="?permissions=PUBLIC">PUBLIC</a></li>
  		<li><a href="?permissions=AUTHENTICATED">AUTHENTICATED</a></li>
  		<li><a href="?permissions=NONE">NONE</a></li>
  	<?php foreach($sec->role_catalog() as $role): ?>
        <li><a href="?permissions=<?php echo $sec->role_sguid($role['id']); ?>"><?php echo $role['name']; ?></a></li>
    <?php endforeach; ?>
  	</ul>
    <br /><br />
    <ul>
  		<li class="head">Users and groups with explicit permissions:</li>
        <?php
            foreach($sec->get_clients() as $client)
            {
                $info = $sec->get_client($client);
                # get_client can return an empty record, especially due to schema changes in the alpha release
                if ($info['guid'] === '') continue;
                if ( in_array($info['type'], array('user','group') ) )
                {
                    if ( $sec->modules[ $info['module_id'] ]->{$info['type']}->load( $info['guid'] , 'unique_id')!==false ) 
                    {
                        $uid = $sec->modules[ $info['module_id'] ]->{$info['type']}->uid;
                        echo sprintf("<li><a href=\"?permissions=%s&module_id=%s&type=%s\">%s</a></li>\r\n", $uid, $info['module_id'], $info['type'], $uid);
                    }
                }
            }
        ?>
  	</ul>
  </form>

<?php } ?>