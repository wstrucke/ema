/* Javascript Document */

/* ema modules support javascript
 * revision 1.0.0, nov-22-2010 ws
 *
 * Copyright 2010 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

var module_urls = {
	drop_schema: '<?php echo url('xml/module/drop_schema'); ?>',
	refresh: '<?php echo url('xml/module/refresh'); ?>'
	};

window.addEvent('domready', addModuleEnabledEvents);
		
function addModuleEnabledEvents(){
	$$('input[type=checkbox]').addEvent('click', function(){
		if (this.checked) { var turl='<?php echo url('xml/module/enable'); ?>'; } else { var turl='<?php echo url('xml/module/disable'); ?>'; }
		turl += '/' + this.id.substring(this.id.indexOf('_') + 1);
		var m = new Request.HTML({url: turl, noCache: true, onSuccess: function(){ hideWaiting(); }});
		showWaiting('Saving...');
		m.send();
	});
	$$('select').addEvent('change', function(){ moduleListAction(this); });
}

function moduleListAction(ele) {
	var t = ele.getParent().getParent().getElement('input').get('id');
	var id = t.substring(t.indexOf('_') + 1);
	switch(ele.get('value')) {
		case 'drop_schema':
			if (! confirm('Are you sure you want to drop the schema (tables) and permanently erase all data for this module?')) break;
			var r = new Request.HTML({url: module_urls.drop_schema + '/' + id, onSuccess: function(t,e,h,j){ hideWaiting(); parseXmlResponse(e); }});
			r.get();
			showWaiting('Dropping database schema (tables)...');
			break;
		case 'refresh':
			var r = new Request.HTML({url: module_urls.refresh + '/' + id, onSuccess: function(t,e,h,j){ hideWaiting(); }});
			r.get();
			showWaiting('Setting refresh flag...');
			break;
		default: showNotice('option not implemented'); break;
	}
	ele.set('value', '');
	ele.blur();
}