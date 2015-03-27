<h3>Active Directory Module Administration</h3>

<h4>Configurable Settings (Under Development)</h4>

<form name="accounts_ad" action="<?php echo url('admin/accounts_ad'); ?>" method="post">

<?php
	global $t;
	$args = $t->accounts_ad->arguments();
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