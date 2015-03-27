<?php
/* template manager installation script */

# workaround for catch-22 - template manager requires cms manager requires template manager
if (@is_object($this->_tx->cms)) {

	// type, content_type, name, function callback, path, in menu (t/f)
	$l = array(
		array('fuse','xml','Associate Template','xml_associate_template','xml/associate/template',false),
		array('fuse','xml','Copy Template','xml_copy','xml/copy/template',false),
		array('fuse','xml','Create Template','xml_create_template','xml/create/template',false),
		array('fuse','xml','Create Template Element','xml_create_template_element','xml/create/template/element',false),
		array('fuse','xml','Create Template Resource','xml_create_template_resource','xml/create/template/resource',false),
		array('fuse','xml','Delete Template','xml_delete_template','xml/delete/template',false),
		array('fuse','xml','Delete Template Element','xml_delete_template_element','xml/delete/template/element',false),
		array('fuse','xml','Delete Template Resource','xml_delete_template_resource','xml/delete/template/resource',false),
		array('fuse','xml','Get Template','xml_get_template','xml/get/template',false),
		array('fuse','xml','List Template Children','xml_list_template_children','xml/list/template/children',false),
		array('fuse','xml','List Template Elements','xml_list_template_elements','xml/list/template/elements',false),
		array('fuse','xml','List Template Resources','xml_list_template_resources','xml/list/template/resources',false),
		array('fuse','xml','List Templates','xml_list_templates','xml/list/templates',false),
		array('fuse','xml','Toggle Template Enabled','xml_toggle_template_enabled','xml/toggle/template/enabled',false),
		array('fuse','xml','Unassociate Template','xml_unassociate_template','xml/unassociate/template',false),
		array('fuse','xml','Unlink Template Element','xml_unlink_template_element','xml/unlink/template/element',false),
		array('fuse','xml','Unlink Template Resource','xml_unlink_template_resource','xml/unlink/template/resource',false),
		array('fuse','xml','Update Template','xml_update_template','xml/update/template',false),
		array('fuse','xml','Update Template Element','xml_update_template_element','xml/update/template/element',false),
		array('fuse','xml','Update Template Resource','xml_update_template_resource','xml/update/template/resource',false)
	);
	
	while ($c = array_shift($l)) {
		$id = $t->cms->create_element(array('type'=>$c[0],'content_type'=>$c[1],'name'=>$c[2],'function'=>$c[3],'module'=>get_class($this),'created_by'=>'SYSTEM'));
		if (is_string($id)&&(!is_null($c[4]))) { $t->cms->create_path(array('path'=>$c[4],'element_id'=>$id,'menu_setting'=>$c[5])); }
	}

}
	
?>