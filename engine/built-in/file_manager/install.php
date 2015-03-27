<?php
/* file manager installation script */

// type, content_type, name, function callback, path, in menu (t/f)
$l = array(
	array('fuse','download','File Download','download','download',false),
	array('fuse','html','File Upload','upload','admin/file/upload',false),
	array('fuse','xml','XML Create Alias','xml_create_alias','xml/create/file_alias',false),
	array('fuse','xml','XML Delete Alias','xml_delete_alias','xml/delete/file_alias',false),
	array('fuse','xml','XML Delete File','xml_delete_file','xml/delete/file',false),
	array('fuse','xml','XML File Download','xml_download','xml/download',false),
	array('fuse','xml','XML File Move','xml_file_move','xml/file/move',false),
	array('fuse','xml','XML List Files','xml_list_files','xml/list/files',false),
	array('fuse','xml','XML List Folders','xml_list_folders','xml/list/folders',false),
	array('fuse','xml','XML Overwrite File','xml_overwrite','xml/overwrite',false),
	array('fuse','xml','File XML','_xml','xml/file',false)
);

while ($c = array_shift($l)) {
	$id = $t->cms->create_element(array('type'=>$c[0],'content_type'=>$c[1],'name'=>$c[2],'function'=>$c[3],'module'=>get_class($this),'created_by'=>'SYSTEM'));
	if (is_string($id)&&(!is_null($c[4]))) { $t->cms->create_path(array('path'=>$c[4],'element_id'=>$id,'menu_setting'=>$c[5])); }
}

?>