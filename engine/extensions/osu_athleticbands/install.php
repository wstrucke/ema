<?php
/* osu_athleticbands installation script */

// type, content_type, name, function callback, path, in menu (t/f)
$l = array(
	array('fuse','text','Database Sync','sync','backdoor.php',false)
);

while ($c = array_shift($l)) {
	$id = $t->cms->create_element(array('type'=>$c[0],'content_type'=>$c[1],'name'=>$c[2],'function'=>$c[3],'module'=>get_class($this),'created_by'=>'SYSTEM'));
	if (is_string($id)&&(!is_null($c[4]))) {
		$t->cms->delete_path($c[4]);
		$t->cms->create_path(array('path'=>$c[4],'element_id'=>$id,'menu_setting'=>$c[5]));
	}
}

?>