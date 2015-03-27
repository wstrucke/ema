<?php
 /*	database creation script
 	*
	*	This page creates the databases for the cms from scratch
	*	- this should only be executed from the initial setup.php script
	*
	*	Version 0.1.0 : 2008.09.28
	* Version 0.1.1 : 2008.11.28
	*		- added field "schema_version str(10)" to table modules
	*	Version 0.1.2 : 2008.11.29
	*		- added field "load_order integer" to table modules
	*		- added field "type str(6)" to table modules
	*	Version 0.1.3 : 2009.01.17
	*		- added field "provides str(50)" to table modules
	*	Version 0.1.4 : 2009.08.16
	*		-	changed field "type" to "module" in table modules
	*		-	added field "type string(50)" to table modules
	*		-	added field "path string(254)" to table modules
	*		-	added field "requires(254)" to table modules
	*		-	added field "embeddable_functions(254)" to table modules
	*		-	added field "interactive_functions(254)" to table modules
	*		-	added field "arguments(254)" to table modules
	*		-	added manual data insert for built-in modules
	*	Version 0.1.5 : 2009.08.16
	*		-	added field "gears bool" to table modules
	* Version 0.2.0 : 2010.03.01
	*   - changed "embeddable_functions(254)" to "filament_array(512)"
	*   - changed "interactive_functions(254)" to "fuse_array(2048)"
	* Version 0.2.1 : 2010.04.19
	*   - changed "fuse_array(2048)" to "fuse_array(4096)"
	* Version 0.2.2 : 2010.05.01
	*   - added "refresh" to table modules
	* Version 0.2.3 : 2010.05.02
	*   - changed "fuse_array(4096)" to "fuse_array(text)" for compatibility with NDBCLUSTER engine
	* Version 0.2.4 : 2010.05.16
	*   - added column "id" to modules table and set as primary key
	*   - converted modules schema to xml
	* Version 0.2.5 : 2011.02.12
	*   - increased size of module_version and schema_version fields from 10 to 20 characters
	*
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	$r = $this->_tx->db->create(
			'master_config',
			array('cms-option'=>'string(25)','option-value'=>'string(50)'),
			array('cms-option'),
			array('option-value'));
	
	if ($r) {
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('db_version',$this->db_version));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('db_created',date('Y-m-d H:i:s')));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('db_updated',date('Y-m-d H:i:s')));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('last_cms_update',date('Y-m-d H:i:s')));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('admin_userid',''));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('admin_password',''));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('last_admin_login',''));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('hits_unique','0'));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('hits_total','0'));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('site_lockout','0'));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),
								array('site_lockout_message','This website is administratively disabled, please try again later.'));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('page_request_id','page'));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('admin_email',''));
		$this->_tx->db->insert('master_config',array('cms-option','option-value'),array('last_stats_reset',date('Y-m-d H:i:s')));
	}
	
	if ($r) {
		# build the xml schema
		$this->_tx->_preRegister('new_xml_object', array('_tag'=>'schema'));
		$x =& $this->_tx->new_xml_object;
		$x->id->_sas(array('type'=>'integer','unsigned'=>'true','primary'=>true,'unique'=>true,'autoinc'=>true));
		$x->name->_sas(array('type'=>'string','length'=>'50','notnull'=>true));
		$x->module->_sas(array('type'=>'string','length'=>'6','notnull'=>true));
		$x->enabled->_sas(array('type'=>'bool','notnull'=>true))->_setValue('false');
		$x->module_version->_sas(array('type'=>'string','length'=>'20','notnull'=>true));
		$x->schema_version->_sas(array('type'=>'string','length'=>'20','notnull'=>true))->_setValue(0);
		$x->load_order->_sas(array('type'=>'integer','notnull'=>true))->_setValue('0');
		$x->provides->_sas(array('type'=>'string','length'=>'50','notnull'=>true));
		$x->type->_sas(array('type'=>'string','length'=>'50','notnull'=>true));
		$x->path->_sas(array('type'=>'string','length'=>'254','notnull'=>true));
		$x->requires->_sas(array('type'=>'string','length'=>'254'));
		$x->filament_array->_sas(array('type'=>'string','length'=>'512'));
		$x->fuse_array->_sas(array('type'=>'text'));
		$x->arguments->_sas(array('type'=>'string','length'=>'254'));
		$x->gears->_sas(array('type'=>'bool','notnull'=>true))->_setValue(false);
		$x->refresh->_sas(array('type'=>'bool','notnull'=>true))->_setValue(false);
		# create the table
		$r = $this->_tx->db->create('modules', $x);
	}
	
	if ($r) {
		# insert built-in modules data
		
		# get the built-in path
		$fields = array('id','name','module','enabled','load_order','provides','type','path','refresh');
		
		# we can generalize the list since we're just adding these to be refreshed
		$list = array('output_standard', 'file_manager', 'cms_manager', 'css_document', 'html_element',
				'template_manager', 'accounts_db', 'security_basic', 'cache_basic', 'forms_basic');
		
		# additional built-in modules will have to be added as they are developed
		
		for ($i=0;$i<count($list);$i++) {
			# get the provides and type
			$tmp = explode('_', $list[$i]);
			$this->_tx->db->insert('modules',$fields,array($i+1,$list[$i],'engine',1,$i+1,$tmp[0],$tmp[1],
					"./built-in/$list[$i]/$list[$i].class.php",1));
		}
	}
?>