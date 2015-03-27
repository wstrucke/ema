<div>
		<h1>Shibboleth Module Administration<div><?php echo l('close', 'admin'); ?></div></h1>
</div>

<h4>Configurable Settings (Under Development)</h4>

<form name="accounts_shib" action="<?php echo url('admin/accounts_shib'); ?>" method="post">

<?php
	global $t;
	$args = $t->accounts_shib->arguments();
	$t->accounts_shib->configure();
	for($i=0;$i<count($args);$i++){
		if (array_key_exists('options', $args[$i])) {
			printf('%s: <select name="%s">', $args[$i]['label'], $args[$i]['id']);
			foreach($args[$i]['options'] as $k=>$v) {
				printf('<option value="%s">%s</option>', $k, $v);
			}
			echo "</select><br />\r\r";
		} else {
			$v = $t->accounts_shib->{$args[$i]['id']};
			printf('%s: <input type="text" name="%s" value="%s" /><br />', $args[$i]['label'], $args[$i]['id'], $v);
		}
	}
?>
</form>

<br />

<h4>All Groups</h4>
<ul>
<?php
	$list = $t->accounts_shib->group->catalog();
	for($i=0;$i<count($list);$i++){
		printf('<li>%s: %s</li>', $list[$i]['gid'], $list[$i]['display_name']);
	}
?>
</ul>

<br />

<h4>All Accounts</h4>
<ul>
<?php
	$list = $t->accounts_shib->user->catalog();
	for($i=0;$i<count($list);$i++){
		printf('<li>%s: %s</li>', $list[$i]['uid'], $list[$i]['display_name']);
	}
?>
</ul>