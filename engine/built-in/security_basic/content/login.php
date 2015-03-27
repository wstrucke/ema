<?php
	# load the security css
	$t->get->html->head->cc('link')->sas(array('rel'=>'stylesheet','type'=>'text/css','href'=>url('download/security.css')));
	
	# load data from security object
	$message = $t->get->security_error;
	$path = $t->get->login_path;
	$explicit = $t->get->explicit_authentication;
	
	# auth type considerations
	$all_auth_standard = true;
	$standard = array();
	$nonstandard = array();
	
	# process modules
	if ($explicit) {
		$list = $t->security->get_modules(true);
		$domains = array();
		foreach($list as $k=>$v) {
			if (strlen($v['instance_name'])>0) {
				$domains[$k] = $v['instance_name'];
			} else {
				$domains[$k] = $v['name'];
			}
			if ($v['auth_standard'] == '0') {
				$all_auth_standard = false;
				$nonstandard[] = $k;
			} else {
				$standard[] = $k;
			}
		}
	}
	
	# load the url here to allow the login form to work when debugging is enabled
	if (array_key_exists('domain', $_GET)) {
		$url = url('', array('domain'=>$_GET['domain']));
	} else {
		$url = url();
	}
	
	if (!array_key_exists('domain', $_GET)&&(!$all_auth_standard)) {
		# allow for non standard auth types; if any authentication modules use non standard (user id/password) authentication
		#  display a dialogue to select the auth type
?>
	<div>This site uses multiple authentication mechanisms, please select the method you would like to use.</div>
	<ul class="security_auth_list">
		<li><?php echo lb(img('download', 'login_manager.png', 128, 128) . '<br />Site Manager', '', array('domain'=>'standard')); ?></li>
<?php
	for ($i=0;$i<count($nonstandard);$i++) {
		$j = $nonstandard[$i];
		printf("<li>%s</li>", lb($t->security->modules[$j]->login('select'), '', array('domain'=>$j)));
	}
?>
	</ul>
<?php
	} elseif ($all_auth_standard || (array_key_exists('domain', $_GET)&&($_GET['domain']=='standard'))) {
?>
	<form id="security_login" action="<?php echo $url; ?>" method="post">
	<?php if ($message !== false) { ?>
		<div id="security_login_message"><?php echo $message; ?></div>
	<?php } ?>
	<?php if ($path !== false) { ?>
		<input type="hidden" name="path" value="<?php echo $path; ?>" />
	<?php } ?>
		<span>User ID:</span><input type="text" name="userid" id="security_userid" value="" /><br />
	<?php if ($explicit && (count($standard) > 1)) { ?>
		<span>Domain:</span>
		<select name="domain" id="security_domain">
		<?php foreach($standard as $v) { ?>
			<option value="<?php echo $v; ?>"><?php echo $domains[$v]; ?></option>
		<?php } ?>
		</select>
		<br />
	<?php } elseif ($explicit) { ?>
		<input name="domain" type="hidden" value="<?php echo $standard[0]; ?>" />
	<?php } ?>
		<span>Password:</span><input type="password" name="password" id="security_password" value="" />
		<input type="submit" name="submit" id="security_submit" value="Login" />
	</form>

<?php
	} elseif (array_key_exists($_REQUEST['domain'], $domains)&&array_key_exists(intval($_REQUEST['domain']), $t->security->modules)&&method_exists($t->security->modules[intval($_REQUEST['domain'])], 'login')) {
		# use a custom authentication method
		$j = intval($_REQUEST['domain']);
		echo $t->security->modules[$j]->login('login');
		exit;
	} else {
		message('Invalid Authentication Domain', 'error');
	}
?>