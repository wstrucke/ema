<?php
	# add accounts javascript to header
	$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>url('download/accounts_db.js')));
?>
	
	<div>
		<h1>accounts db control panel<div><?php echo l('close', 'admin'); ?></div></h1>
	</div>
	
	<div id="workspace">&nbsp;</div>
	
	
<h3>Accounts DB Module Administration</h3>

<h4>Configurable Settings (Under Development)</h4>

<form name="accounts_db" action="<?php echo url('admin/accounts_db'); ?>" method="post">

<?php
	global $t;
	$args = $t->accounts_db->arguments();
	for($i=0;$i<count($args);$i++){
		if (array_key_exists('options', $args[$i])) {
			printf('%s: <select name="%s">', $args[$i]['label'], $args[$i]['id']);
			foreach($args[$i]['options'] as $k=>$v) {
				printf('<option value="%s">%s</option>', $k, $v);
			}
			echo "</select><br />\r\r";
		} else {
			printf('%s: <input type="text" name="%s" /><br />', $args[$i]['label'], $args[$i]['id']);
		}
	}
?>
</form>