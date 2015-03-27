<?php
/* cms manager installation script */

# type, content_type, name, function callback, path, in menu (t/f)
$l = array(
	array('fuse','html','CMS Dev Page :: Drop All Tables','dev_drop_tables','dev/droptables',false),
	array('fuse','xml','CMS XML Create Element','xml_create_element','xml/create/element',false),
	array('fuse','xml','CMS XML Create Path','xml_create_path','xml/create/path',false),
	array('fuse','xml','CMS XML Delete Element','xml_delete_element','xml/delete/element',false),
	array('fuse','xml','CMS XML Delete Path','xml_delete_path','xml/delete/path',false),
	array('fuse','xml','CMS XML Get Element Content','xml_get_element_content','xml/get/element/content',false),
	array('fuse','xml','CMS XML Get Element Info','xml_get_element_info','xml/get/element/info',false),
	array('fuse','xml','CMS XML List Elements','xml_list_elements','xml/list/elements',false),
	array('fuse','xml','CMS XML List Elements By Template','xml_list_elements_by_template','xml/list/elements/by/template',false),
	array('fuse','xml','CMS XML List Navigation','xml_list_navigation','xml/list/navigation',false),
	array('fuse','xml','CMS XML Module Disable','xml_module_disable','xml/module/disable',false),
	array('fuse','xml','CMS XML Module Drop Schema','xml_module_drop_schema','xml/module/drop_schema',false),
	array('fuse','xml','CMS XML Module Enable','xml_module_enable','xml/module/enable',false),
	array('fuse','xml','CMS XML Module Refresh','xml_module_refresh','xml/module/refresh',false),
	array('fuse','xml','CMS XML Test Page','xml_test','xml/test',false),
	array('fuse','xml','CMS XML Toggle Path in Menu','xml_toggle_path_menu','xml/toggle/path_in_menu',false),
	array('fuse','xml','CMS XML Update Element Info','xml_update_element_info','xml/update/element/info',false),
	array('fuse','xml','CMS XML Update Element Content','xml_update_element_content','xml/update/element/content',false),
	array('fuse','xml','CMS XML Update Path Parent ID','xml_update_path_parent','xml/update/path/parent',false),
	array('fuse','xml','CMS XML Update Site Settings','xml_update_site_settings','xml/update/site/settings',false),
	array('fuse','html','Site Administration','siteadmin','admin',false)
);

while ($c = array_shift($l)) {
	$args = array('type'=>$c[0],'content_type'=>$c[1],'name'=>$c[2],'function'=>$c[3],'module'=>get_class($this),'created_by'=>'SYSTEM');
	$id = $this->create_element($args);
	if (is_string($id)&&(!is_null($c[4]))) { $this->create_path(array('path'=>$c[4],'element_id'=>$id,'menu_setting'=>$c[5])); }
	# set permissions
	if ($this->_has('security')) { $this->_tx->security->grant_access('61123ca4-f075-11df-bff3-aa46601030c1', "content_id.$id"); }
}

?>