/* Javascript Document */

/* Copyright 2010 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

var template = new Array(11); // the loaded template

/* defined as:
 *   template['id'] - the template id
 *   template['parent_id'] - optional parent template id
 *   template['name'] - the template name
 *   template['description'] - the template description
 *   template['enabled'] - enabled value
 *   template['resources'] - affiliated resources
 *   template['resources_data'] - affiliated resources data (loaded on demand)
 *   template['content'] - affiliated content
 *   template['children'] - sub/child templates
 *   template['elements'] - affiliated elements
 *   template['element_content'] - affiliated element content
 *
 */

/* XML Paths */
var template_urls = {
	'associate': '<?php echo url('xml/associate/template'); ?>',
	'copy': '<?php echo url('xml/copy/template'); ?>',
	'create': '<?php echo url('xml/create/template'); ?>',
	'createElement': '<?php echo url('xml/create/template/element'); ?>',
	'createResource': '<?php echo url('xml/create/template/resource'); ?>',
	'delete': '<?php echo url('xml/delete/template'); ?>',
	'deleteElement': '<?php echo url('xml/delete/template/element'); ?>',
	'deleteResource': '<?php echo url('xml/delete/template/resource'); ?>',
	'download': '<?php echo url('xml/download'); ?>',
	'list': '<?php echo url('xml/list/templates'); ?>',
	'listChildren': '<?php echo url('xml/list/template/children'); ?>',
	'listContent': '<?php echo url('xml/list/elements/by/template'); ?>',
	'listElements': '<?php echo url('xml/list/template/elements'); ?>',
	'listFiles': '<?php echo url('xml/list/files'); ?>',
	'listResources': '<?php echo url('xml/list/template/resources'); ?>',
	'load': '<?php echo url('xml/get/template'); ?>',
	'overwrite': '<?php echo url('xml/overwrite'); ?>',
	'save': '<?php echo url('xml/update/template'); ?>',
	'saveElement': '<?php echo url('xml/update/template/element'); ?>',
	'saveResource': '<?php echo url('xml/update/template/resource'); ?>',
	'toggleEnabled': '<?php echo url('xml/toggle/template/enabled'); ?>',
	'unassociate': '<?php echo url('xml/unassociate/template'); ?>'
	};

var global_new_template = false;
var files;
var mode;

/* Field Definitions */


/* Initialization */

window.addEvent('domready', addEventHandlers);

/* Functions */

function addEventHandlers()
{
	$('btn_newTemplate').addEvent('click', createNewTemplateWindow);
	
	// disable the wysiwyg editor for templates (for now)
	if (ema.wysiwyg) {
		ema.wysiwyg = false;
		wysiwygSettings.enabled = false;
	}
}

function associateTemplate(id)
/* associate the template specified by id with the
 *  loaded template as a new "child" or subtemplate
 *
 * optionally process affiliated template resource
 *  mappings to remove any (now) inherited mappings
 *
 */
{
	
}

function copyTemplate(url)
/* copy the loaded template into a new template
 *
 */
{
	clearLoadedTemplate();
	// create the request object
	var myRequest = new Request.HTML({
		method: 'post',
		url: url,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove the new template window
				$$('#overlay', '#window').destroy();
				// load the new template
				global_new_template = true;
				var id = e.filter('id')[0].textContent;
				loadTemplate(id, true);
			} catch(er) {}
		}
	});
	// build the post data
	myRequest.send();
	showWaiting('Copying...');
}

function clearLoadedTemplate()
/* clear the loaded template
 *
 */
{
	// reset the global template object
	template = new Array(10);
}

function createCopyTemplateWindow(source_id)
/* construct the copy template window
 *
 * I was going to use the template manager window for this
 *  but all we really need is a name
 *
 * paired with copyTemplate()
 *
 */
{
	var fd = {
		'source_id': { type: 'hidden' },
		'name': { label: 'Name of copy (optional)', width: 300, required: true },
		'copy_files': { label: 'Copy Resource Files', type: 'checkbox' },
		'btn_copy': { label: 'Copy', type: 'button' }
		}
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	createWindow('Copy Template', fd, true);
	if (source_id.length > 0) $('source_id').set('value', source_id);
	$('btn_copy').addEvent('click', function(){
		// collect data
		var name = $('name').get('value');
		var source_id = $('source_id').get('value');
		var url = template_urls.copy + '/' + encodeURIComponent(source_id) + '/';
		if (name.length != 0) { url += encodeURIComponent(name); } else { url += '0'; }
		if ($('copy_files').checked) { url += '/1'; }
		// pass to the new template function
		return copyTemplate(url);
		});
}

function createNewElementWindow()
/* construct the new element window
 *
 */
{
	var fd = {
		'name': { label: 'Name', width: 300, required: true },
		'css_id': { label: 'CSS Document', type: ele_cssSelect() },
		'scope': { label: 'Scope', width: 300 },
		'order': { label: 'Order', width: 300 },
		'size': { label: 'Size', width: 300 },
		'width': { label: 'Width', width: 300 },
		'height': { label: 'Height', width: 300 },
		'btn_saveElement': { label: 'Save and Close', type: 'button' }
		}
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	createWindow('New Element', fd, true);
	$('btn_cancel').addEvent('click', function(e) { loadTemplate(template.id); });
	$('btn_saveElement').addEvent('click', function(){
		// collect data
		var name = $('name').get('value');
		// validate data
		if (name.length == 0) { return showNotice('A name is required.'); }
		// pass to the new template function
		return newElement(name, $('css_id').get('value'), $('scope').get('value'), $('order').get('value'),
			$('size').get('value'), $('width').get('value'), $('height').get('value'));
		});
}

function createNewResourceWindow()
/* construct the new resource window
 *
 */
{
	var fd = {
		'resource_name': { label: 'Name', width: 300, required: true },
		'resource_type': { label: 'Resource Type', required: true, type: ele_resourceTypeSelect('resource_type') },
		'resource_description': { label: 'Description', width: 300 },
		'resource_url': { label: 'URL', width: 300 },
		'resource_file_id': { label: 'File ID', width: 300, type: ele_fileSelect('resource_file_id') },
		'resource_contents': { label: 'Meta Contents', width: 300 },
		'btn_saveResource': { label: 'Save and Close', type: 'button' }
		}
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	createWindow('New Resource', fd, true);
	$('btn_cancel').addEvent('click', function(e) { loadTemplate(template.id); });
	$('btn_saveResource').addEvent('click', function(){
		// collect data
		var name = $('resource_name').get('value');
		var type = $('resource_type').get('value');
		var url = $('resource_url').get('value');
		var file_id = $('resource_file_id').get('value');
		var contents = $('resource_contents').get('value');
		// validate data
		if (name.length == 0) { return showNotice('A name is required.'); }
		if ((url.length > 0) && (file_id.length > 0)) { return showNotice('Please provide a URL <em>or</em> a File, but not both'); }
		if (type != 'meta') { contents = undefined; }
		// pass to the new template function
		return newResource(name, type, $('resource_description').get('value'), url, file_id, contents);
		});
}

function createNewTemplateWindow(parent_id)
/* construct the new template window
 *
 * I was going to use the template manager window for this
 *  but all we really need is a name and description
 *  to create a new template
 *
 * optionally provide a parent id if this is going to
 *  explicitly be a child template
 *
 * paired with newTemplate()
 *
 */
{
	var fd = {
		'parent_id': { type: 'hidden' },
		'name': { label: 'Name', width: 300, required: true },
		'description': { label: 'Description', width: 300 },
		'enabled': { label: 'Enabled', type: 'checkbox' },
		'btn_save': { label: 'Save and Close', type: 'button' }
		}
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	createWindow('New Template', fd, true);
	if (parent_id.length > 0) $('parent_id').set('value', parent_id);
	$('btn_save').addEvent('click', function(){
		// collect data
		var name = $('name').get('value');
		// validate data
		if (name.length == 0) { return showNotice('A name is required.'); }
		// pass to the new template function
		return newTemplate(name, $('description').get('value'), $('enabled').checked, $('parent_id').get('value'));
		});
}

function createTemplateManagerWindow(title)
/* construct the template manager window
 *
 */
{
	if (title == undefined) title = 'Title';
	createWindow(title);
	
	var w = $$('#window h1');
	var container = new Element('div', { 'class': 'content_left' });
	var commands = new Element('div', { 'class': 'buttons_right' });
	
	var p = createRecordField('Parent', 'parent');
	p.label.inject(container);
	p.field.inject(container);
	
	var d = createRecordField('Description', 'description');
	d.label.inject(container);
	d.field.inject(container);
	
	var tabList = new Element('ul', { 'class': 'tabs' });
	var li1 = new Element('li', { id: 'tab_resources', html: 'Resources' });
	li1.addEvent('click', tabResources);
	var li2 = new Element('li', { id: 'tab_content', html: 'Content Library' });
	li2.addEvent('click', tabContentLibrary);
	var li3 = new Element('li', { id: 'tab_templates', html: 'Sub-Templates' });
	li3.addEvent('click', tabSubTemplates);
	var li4 = new Element('li', { id: 'tab_elements',  html: 'Elements' });
	li4.addEvent('click', tabElements);
	$$(li1, li2, li3, li4).inject(tabList);
	tabList.inject(container);
	
	var tab = new Element('div', { 'class': 'tab', id: 'tab' });
	tab.inject(container);
	
	// create buttons
	var buttons = new Element('ul', { 'class': 'vertical buttons' });
	var b1 = new Element('li', { id: 'btn_templateEnable', html: 'Enable' });
	b1.addEvent('click', toggleTemplateEnabled);
	var b2 = new Element('li', { id: 'btn_templateEdit', html: 'Edit' });
	var b3 = new Element('li', { id: 'btn_templateCopy', html: 'Copy' });
	b3.addEvent('click', function(){ createCopyTemplateWindow(template.id); });
	var b4 = new Element('li', { id: 'btn_templateDelete', html: 'Delete' });
	b4.addEvent('click', function(){ deleteTemplate(template.id); });
	$$(b1, b2, b3, b4).inject(buttons);
	$$(b2).addEvent('click', function(){ showNotice('Under Development'); });
	buttons.inject(commands);
	
	commands.inject(w[0], 'after');
	container.inject(w[0], 'after');
	
	centerWindow($('window'));
}

function ele_cssSelect(id, selected)
/* create and return a select element with all of the css documents as options
 *
 */
{
	if (! id) { id = 'css_id'; }
	var ele = new Element('select', { 'id': id });
	var opt = new Element('option', { 'value': '', 'html': '&nbsp;' });
	opt.inject(ele);
/*
	for (var j=0;j<templates.length;j++) {
		if (templates[j]['name'].length > 0) {
			opt = new Element('option', { 'value': encodeURIComponent(templates[j]['id']), 'html': templates[j]['name'] });
			if (selected) { if (templates[j]['id'] == selected) opt.set('selected', 'selected'); }
			opt.inject(ele);
		}
	}
*/
	return ele;
}

function ele_fileSelect(id, selected)
/* create and return a select element with all available files as options
 *
 */
{
	if (! id) { id = 'file_id'; }
	var ele = new Element('select', { 'id': id });
	var opt = new Element('option', { 'value': '', 'html': '&nbsp;' });
	opt.inject(ele);
	for (var j=0;j<files.length;j++) {
		if (files[j]['name'].length > 0) {
			opt = new Element('option', { 'value': encodeURIComponent(files[j]['id']), 'html': files[j]['name'] });
			if (selected) { if (files[j]['id'] == selected) opt.set('selected', 'selected'); }
			opt.inject(ele);
		}
	}
	return ele;
}

function ele_resourceTypeSelect(id, selected)
/* create and return a select element with all of the resource types as options
 *
 */
{
	if (! id) { id = 'resource_type'; }
	var ele = new Element('select', { 'id': id });
	opt = new Element('option', { 'value': 'css', 'html': 'CSS' }); opt.inject(ele);
	opt = new Element('option', { 'value': 'javascript', 'html': 'JavaScript' }); opt.inject(ele);
	opt = new Element('option', { 'value': 'meta', 'html': 'Meta Tag' }); opt.inject(ele);
	return ele;
}

function deleteElement(id)
/* delete the element specified by id and deassociate with
 *  the loaded template
 *
 */
{
	if (! confirm('Are you sure?')) return false;
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.deleteElement + '/' + id,
		onSuccess: function(t,e,h,j){
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				try { $$('#window', '#overlay').destroy(); } catch(e) {}
				loadTemplate(template.id);
			} catch(er) {}
		}
	});
	myRequest.send();
}

function deleteResource(id)
/* delete the specified resource id and deassociate with
 *  the loaded template
 *
 */
{
	if (! confirm('Are you sure?')) return false;
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.deleteResource + '/' + id,
		onSuccess: function(t,e,h,j){
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				try { $$('#window', '#overlay').destroy(); } catch(e) {}
				loadTemplate(template.id);
			} catch(er) {}
		}
	});
	myRequest.send();
}

function deleteTemplate(id)
/* delete the template specified by id
 *
 * if id matches the loaded template, clears
 *   the loaded template and closes the template window
 *
 */
{
	if (! confirm('Are you sure?')) return false;
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls['delete'] + '/' + id,
		onSuccess: function(t,e,h,j){
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				try { $('tr_' + template.id).destroy(); } catch(e) {}
				try { $$('#window', '#overlay').destroy(); } catch(e) {}
				clearLoadedTemplate();
			} catch(er) {}
		}
	});
	myRequest.send();
}

function editElement(id)
/* edit the specified element
 *
 */
{
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	var fd = {
		'element_id': { 'type': 'hidden' },
		'element_help': { 'type': 'p', 'width': 750 },
		'element_content': { 'type': 'textarea', 'width': 750, 'rows': 30 , 'lang': 'html' },
		'btn_saveElement': { 'type': 'button', 'label': 'Save' },
		'btn_close': { 'type': 'button', 'label': 'Save and Close' },
		'btn_previewElement': { 'type': 'button', 'label': 'Preview' }
		};
	
	if (ema.wysiwyg) {
		// pass wysiwyg settings
		resourceList = [];
		//return showNotice(template['resources'].length);
		for (var i=1;i<(template['resources'].length);i++){
			if (template['resources'][i][2] == 'css') {
				if (template['resources'][i][5].length > 0) {
					resourceList[resourceList.length] = ema.download + template['resources'][i][5];
				} else {
					// ignore external style sheets for now
					//resourceList[resourceList.length] = template['resources'][i][4];
				}
			}
		}
		
		wysiwygSettings.css = resourceList;
	}
	
	createWindow('Edit Element Content', fd, true);
	
	// add events
	$('btn_saveElement').addEvent('click', function(e) { saveElementContent($('element_id').get('value'), false); });
	$('btn_close').addEvent('click', function(e) { saveElementContent($('element_id').get('value'), true); });
	$('btn_previewElement').addEvent('click', function(e) { showNotice('not implemented at this time'); });
	$('btn_cancel').addEvent('click', function(e) { loadTemplate(template.id); });
	
	if (ema.wysiwyg) {
		editor.setData(template.element_content[id]);
	} else {
		$('element_content').addEvent('keydown', function(event) {
			if (event.key == "tab") {
				event.preventDefault();
				if (Browser.Engine.trident) {
					var range = document.selection.createRange();
					range.text = "    "; // "\t"
				} else {
					var start = this.selectionStart;
					var end = this.selectionEnd;
					var scrollTop = this.scrollTop;
					var scrollLeft = this.scrollLeft;
					var value = this.get('value');
					this.set('value', value.substring(0, start) + "    " + value.substring(end, value.length)); // "\t"
					start+=4; // start++
					this.scrollTop = scrollTop;
					this.scrollLeft = scrollLeft;
					this.setSelectionRange(start, start);
				}
			} else if (event.key=='s' && event.control) {
				// save
				event.preventDefault();
				saveElementContent($('element_id').get('value'), false);
			}
		});
		// add content
		$('element_content').set('value', template.element_content[id]);
	}
	$('element_id').set('value', id);
	$('element_help').set('html', 'Use "#__VARIABLE__#" to access application variables. Use "##_FUNCTION_##" to access filaments. Enter content fillable areas using &lt;%#&gt;&lt;/%#&gt;. For example, "&lt;%1&gt;&lt;/%1&gt;".  There must be at least one fillable area in a working template element.');
	
	$('element_content').focus();
}

function editResource(id)
/* edit the specified resource
 *
 */
{
	
}

function editResourceContent(id)
/* edit the specified resource content
 *
 */
{
	if (template.resources_data == undefined) template.resources_data = Array();
	if (template.resources_data[id] == undefined) {
		showWaiting('Loading Resource...');
		var r = new Request.HTML({
			url: template_urls.download + '/' + id,
			method: 'get',
			noCache: true,
			onSuccess: function(t,e,h,j){
				hideWaiting();
				// if error show message and stop here
				try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
				try {
					var record = e.filter('file')[0];
					var id = record.getElement('id').textContent;
					template.resources_data[id] = xml_decode(record.getElement('content').textContent);
					editResourceContent(id);
					} catch(er) { showNotice('Error: ' + er); }
				}
			});
		r.send();
		return false;
	}
	try { $$('#window', '#overlay').destroy(); } catch(e) {}
	
	// set content type for textarea
	for (var i=1;i<template.resources.length;i++) {
		if (template.resources[i][5] == id) { var type = template.resources[i][2]; }
	}
	
	var fd = {
		'file_id': { 'type': 'hidden' },
		'resource_help': { 'type': 'p' },
		'resource_content': { 'type': 'textarea', 'width': 750, 'rows': 30, 'lang': type },
		'btn_saveResource': { 'type': 'button', 'label': 'Save' },
		'btn_close': { 'type': 'button', 'label': 'Save and Close' }
		};
	
	createWindow('Edit Resource File Data', fd, true);
	
	// add button events
	$('btn_saveResource').addEvent('click', function(e) { saveResourceContent($('file_id').get('value'), false); });
	$('btn_close').addEvent('click', function(e) { saveResourceContent($('file_id').get('value'), true); });
	$('btn_cancel').addEvent('click', function(e) { loadTemplate(template.id); });
	$('resource_content').addEvent('keydown', function(event) {
		if (event.key == "tab") {
			event.preventDefault();
			if (Browser.Engine.trident) {
				var range = document.selection.createRange();
				range.text = "    "; // "\t"
			} else {
				var start = this.selectionStart;
				var end = this.selectionEnd;
				var scrollTop = this.scrollTop;
				var scrollLeft = this.scrollLeft;
				var value = this.get('value');
				this.set('value', value.substring(0, start) + "    " + value.substring(end, value.length)); // "\t"
				start+=4; // start++
				this.scrollTop = scrollTop;
				this.scrollLeft = scrollLeft;
				this.setSelectionRange(start, start);
			}
		} else if (event.key=='s' && event.control) {
			// save
			event.preventDefault();
			saveResourceContent($('file_id').get('value'), false);
		}
	});
	
	// add content
	$('resource_content').set('value', template.resources_data[id]);
	$('file_id').set('value', id);
	$('resource_help').set('html', '<em>TBD</em>');
	
	$('resource_content').focus();
}

function editTemplate(id)
/* edit the specified template
 *
 * if no id is provided, edit the loaded template
 *
 * if a template is already loaded (not id), closes
 *   the window
 *
 */
{
	
}

function loadChildren()
/* load sub/child templates affiliated with the loaded template
 *
 */
{
	if (!$defined(template.children)) {
		return enqueue({'url': template_urls.listChildren + '/' + template.id, silent: true, onSuccess: function(x){
		try{
			var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
			var list = xmlDoc.getElements('template');
			template.children = {data:Array()};
			template.children.header = Array('ID','Name','Description','Enabled','&nbsp;');
			for (var i=0;i<list.length;i++){
				var tId = list[i].getElement('template_id').textContent;
				template.children.data.push(Array(
					tId,
					list[i].getElement('name').textContent,
					list[i].getElement('description').textContent,
					list[i].getElement('enabled').textContent,
					'<button onclick="loadTemplate(\'' + tId + '\')">Manage</button>'
				));
			}
		}catch(e){showNotice('loadChildren error: ' + e);}
		}}, true);
	}
}

function loadContent()
/* load content affiliated with the loaded template
 *
 */
{
	if (!$defined(template.content)) {
		return enqueue({'url': template_urls.listContent + '/' + template.id, silent: true, onSuccess: function(x){
		try{
			var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
			var list = xmlDoc.getElements('element');
			template.content = {data:Array()};
			template.content.header = Array('ID','Name','Type','Description','Enabled');
			for (var i=0;i<list.length;i++){
				template.content.data.push(Array(
					list[i].getAttribute('id'),
					list[i].getAttribute('name'),
					list[i].getAttribute('type'),
					list[i].getElement('description').textContent,
					list[i].getElement('enabled').textContent
				));
			}
		}catch(e){showNotice('loadContent error: ' + e);}
		}}, true);
	}
}

function loadElements()
/* load elements affiliated with the loaded template
 *
 */
{
	if (!$defined(template.elements)) {
		return enqueue({'url': template_urls.listElements + '/' + template.id, silent: true, onSuccess: function(x){
		try{
			var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
			var list = xmlDoc.getElements('element');
			template.element_content = Array();
			template.elements = {data:Array()};
			template.elements.header = Array('ID','Name','Scope','Order','CSS ID','Size','Width','Height','&nbsp;');
			for (var i=0;i<list.length;i++){
				var eId = list[i].getElement('element_id').textContent;
				template.elements.data.push(Array(
					eId,
					list[i].getElement('name').textContent,
					list[i].getElement('scope').textContent,
					list[i].getElement('order').textContent,
					list[i].getElement('css_id').textContent,
					list[i].getElement('size').textContent,
					list[i].getElement('width').textContent,
					list[i].getElement('height').textContent,
					'<button onclick="editElement(\'' + eId + '\')">Edit</button><button onclick="deleteElement(\'' +
						eId + '\')">Delete</button>'
				));
				template.element_content[eId] = xml_decode(list[i].getElement('ingredients').textContent);
			}
		}catch(e){showNotice('loadElements error: ' + e);}
		}}, true);
	}
}

function loadFiles()
/* load all files
 *
 */
{
	// load data if necessary
	if (files == undefined) {
		// create the request object
		var myRequest = new Request.HTML({
			method: 'post',
			url: template_urls.listFiles,
			onSuccess: function(t,e,h,j){
				// if error show message and stop here
				try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
				try {
					var list = e.filter('file');
					// assign the resources array
					files = new Array(list.length);
					// build the next level
					for (i=0;i<(list.length);i++) { files[i] = Array(12); }
					// load the data
					for (i=0;i<list.length;i++) {
						files[i]['id'] = list[i].getElement('id').textContent;
						files[i]['unique_id'] = list[i].getElement('unique_id').textContent;
						files[i]['type'] = list[i].getElement('type').textContent;
						files[i]['name'] = list[i].getElement('name').textContent;
						files[i]['description'] = list[i].getElement('description').textContent;
						files[i]['path'] = list[i].getElement('path').textContent;
						files[i]['size'] = list[i].getElement('size').textContent;
						files[i]['object'] = list[i].getElement('object').textContent;
						files[i]['shared'] = list[i].getElement('shared').textContent;
						files[i]['ssl_required'] = list[i].getElement('ssl_required').textContent;
						files[i]['updated'] = list[i].getElement('updated').textContent;
						files[i]['mime'] = list[i].getElement('mime').textContent;
						};
					} catch(e) {}
				}
			});
		myRequest.send();
	}
}

function loadResources()
/* load resources affiliated with the loaded template
 *
 */
{
	if (!$defined(template.resources)) {
		return enqueue({'url': template_urls.listResources + '/' + template.id, silent: true, onSuccess: function(x){
		try{
			var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
			var list = xmlDoc.getElements('resource');
			template.resources = {data:Array()};
			template.resources.header = Array('ID','Name','Type','Description','URL','File ID','&nbsp;');
			for (var i=0;i<list.length;i++){
				var linked_template = list[i].getElement('linked_to').textContent;
				var rId = list[i].getElement('id').textContent;
				var fId = list[i].getElement('file_id').textContent;
				if (list[i].getElement('url').textContent.length > 0){
					var url = '<a href="' + list[i].getElement('url').textContent + '" target="_blank">Link</a>';
				} else {
					var url = '&nbsp;';
				}
				if (linked_template == template.id) {
					var link = '<button onclick="editResourceContent(\'' +
						fId + '\')">Edit</button><button onclick="deleteResource(\'' +
						rId + '\')">Delete</button>';
				} else {
					var link = '<span style="color: #ccc">Inherited</span>';
				}
				template.resources.data.push(Array(rId,list[i].getElement('name').textContent,list[i].getElement('type').textContent,
					list[i].getElement('description').textContent,url,fId,link));
			}
		}catch(e){showNotice('loadResources error: ' + e);}
		}}, true);
	}
}

function loadTemplate(id)
/* load the requested template
 *
 */
{
	clearLoadedTemplate();
	// load template
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.load + '/' + id,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				var record = e.filter('template')[0];
				template.id = record.getElement('template_id').textContent;
				template.parent_id = record.getElement('parent_id').textContent;
				template.name = record.getElement('name').textContent;
				template.description = record.getElement('description').textContent;
				template.enabled = record.getElement('enabled').textContent;
				// check if this was a new template
				if (global_new_template && (! template.parent_id)) {
					var tr = new Element('tr', { 'class': 'selected', id: 'tr_' + template.id });
					var col1 = new Element('td', { html: template.id });
					var col2 = new Element('td', { html: template.name });
					var col3 = new Element('td', { html: template.enabled });
					var col4 = new Element('td', { html: template.description });
					var col5 = new Element('td', { html: '<button onclick="loadTemplate(\'' + template.id + '\')">Manage</button>' });
					$$(col1, col2, col3, col4, col5).inject(tr);
					tr.inject($('template_list'));
				}
				global_new_template = false;
				try { $$('#window', '#overlay').destroy(); } catch(e) {}
				// build the template manager window
				createTemplateManagerWindow(template.name);
				// optionally create the parent link
				if (template.parent_id.length > 0) {
					var parent_link = new Element('div', { html: 'PL', class: 'arrow_up', title: 'Load Parent Template' });
					parent_link.addEvent('click', function(){ loadTemplate(template.parent_id); });
					parent_link.inject($('btn_cancel'), 'after');
					enqueue({url: template_urls.load + '/' + template.parent_id, silent: true, onSuccess: function(x){
						try {
							xmlDoc = parseXmlResponse(x);
							if (xmlDoc === false) return true;
							text = xmlDoc.getElement('name').textContent;
							desc = xmlDoc.getElement('description').textContent;
							if (desc.length > 0) text += ' (' + desc + ')';
							$('parent').set('html', text);
						} catch(e){ showNotice(text + ': ' + e); }
					}}, true);
				} else {
					$('parent').set('html', '(None)');
				}
				// set the description value
				$('description').set('html', template.description);
				// set the enabled value
				if (template.enabled == '1') {
					$('btn_templateEnable').set('html', 'Disable');
				}
				// load affiliated data
				loadTemplateExtras(true);
			} catch(er) {}
		}
	});
	myRequest.send();
	showWaiting('Loading...');
}

function loadTemplateExtras(initial)
/* load the template affiliated data
 *
 */
{
	clearTimeout(timer);
	if (initial) {
		createOverlay($('window'), 'overlay2');
		loadChildren();
		loadContent();
		loadElements();
		loadFiles();
		loadResources();
	} else if ( (template.resources != undefined) && (template.content != undefined) &&
			(template.children != undefined) && (template.elements != undefined) ) {
		$('overlay2').destroy();
		// load the resources tab
		return tabResources();
	}
	timer = setTimeout('loadTemplateExtras()', 500);
}

function newElement(name, css_id, scope, order, size, width, height)
/* create a new element and associate with the loaded template
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.createElement + '/' + template.id,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove the new template window
				$$('#overlay', '#window').destroy();
				loadTemplate(template.id);
			} catch(er) {}
		}
	});
	// build the post data
	var p = 'name=' + encodeURIComponent(name);
	if (css_id.length > 0) p += '&css_id=' + encodeURIComponent(css_id);
	if (scope.length > 0) p += '&scope=' + encodeURIComponent(scope);
	if (order.length > 0) p += '&order=' + encodeURIComponent(order);
	if (size.length > 0) p += '&size=' + encodeURIComponent(size);
	if (width.length > 0) p += '&width=' + encodeURIComponent(width);
	if (height.length > 0) p += '&height=' + encodeURIComponent(height);
	myRequest.send(p);
	showWaiting('Saving...');
}

function newResource(name, type, description, url, file_id, contents)
/* add (and associate) a new resource with the loaded template
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.createResource + '/' + template.id,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove the new template window
				$$('#overlay', '#window').destroy();
				loadTemplate(template.id);
			} catch(er) {}
		}
	});
	// build the post data
	var p = 'name=' + encodeURIComponent(name) + '&type=' + encodeURIComponent(type);
	if (description.length > 0) p += '&description=' + encodeURIComponent(description);
	if (url.length > 0) p += '&url=' + encodeURIComponent(url);
	if (file_id.length > 0) p += '&file_id=' + encodeURIComponent(file_id);
	if ((contents != undefined) && (contents.length > 0)) p += '&contents=' + encodeURIComponent(contents);
	myRequest.send(p);
	showWaiting('Saving...');
}


function newTemplate(name, description, enabled, parent_id)
/* create a new template
 *
 * optionally provide a parent_id if this is going to be
 *  a sub/child template
 *
 * this function does not validate the input data
 *
 * paired with createNewTemplateWindow()
 *
 */
{
	clearLoadedTemplate();
	// create the request object
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.create,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove the new template window
				$$('#overlay', '#window').destroy();
				// load the new template
				global_new_template = true;
				var id = e.filter('id')[0].textContent;
				loadTemplate(id, true);
			} catch(er) {}
		}
	});
	// build the post data
	var p = 'name=' + encodeURIComponent(name) + '&enabled=';
	if (enabled) { p += '1'; } else { p += '0'; }
	if (description.length > 0) p += '&description=' + encodeURIComponent(description);
	if (parent_id.length > 0) p += '&parent_id=' + encodeURIComponent(parent_id);
	myRequest.send(p);
	showWaiting('Saving...');
}

function saveElement(id)
/* save changes to the specified element (from edit mode)
 *
 */
{
	
}

function saveElementContent(id, autoclose)
/* save changes to the specified element contents (from edit mode)
 *
 */
{
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.saveElement + '/' + id,
		noCache: true,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove meta window
				if (mode) {
					$$('#overlay', '#window').destroy();
					loadTemplate(template.id);
				}
			} catch(er) {}
		}
	});
	showWaiting('Saving...');
	if (autoclose) { mode = true; } else { mode = false; }
	//showNotice($('element_content').get('type')); return false;
	if (ema.wysiwyg && ($('element_content').get('lang') == 'html')) {
		myRequest.send('ingredients=' + encodeURIComponent(CKEDITOR.instances['element_content'].getData()));
	} else {
		myRequest.send('ingredients=' + encodeURIComponent($('element_content').get('value')));
	}
}

function saveResource(id)
/* save changes to the specified resource (from edit mode)
 *
 */
{
	
}

function saveResourceContent(id, autoclose)
/* save changes to the specified resource (from edit mode)
 *
 */
{
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.overwrite + '/' + id,
		noCache: true,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove meta window
				if (mode) {
					$$('#overlay', '#window').destroy();
					loadTemplate(template.id);
				}
			} catch(er) {}
		}
	});
	showWaiting('Saving...');
	if (autoclose) { mode = true; } else { mode = false; }
	myRequest.send('content=' + encodeURIComponent($('resource_content').get('value')));
}

function saveTemplate()
/* save the loaded template (from edit mode)
 *
 */
{
	
}

function tabContentLibrary()
/* load the content library tab
 *
 */
{
	// clear the tab container
	$('tab').set('html', '');
	// unselect all tabs
	$$('#tab_resources', '#tab_content', '#tab_templates', '#tab_elements').set('class', '');
	// select the resources tab
	$('tab_content').setAttribute('class', 'selected');
	// build the table
	t = createTable(template.content);
	t.inject($('tab'));
}

function tabElements()
/* load the elements tab
 *
 */
{
	// clear the tab container
	$('tab').set('html', '');
	// unselect all tabs
	$$('#tab_resources', '#tab_content', '#tab_templates', '#tab_elements').set('class', '');
	// select the resources tab
	$('tab_elements').setAttribute('class', 'selected');
	// build the table
	t = createTable(template.elements);
	t.inject($('tab'));
	// add the new element button
	b = new Element('button', { id: 'btn_newElement', html: 'New Element' });
	b.addEvent('click', createNewElementWindow);
	b.inject($('tab'));
}

function tabResources()
/* load the resources tab
 *
 */
{
	// clear the tab container
	$('tab').set('html', '');
	// unselect all tabs
	$$('#tab_resources', '#tab_content', '#tab_templates', '#tab_elements').set('class', '');
	// select the resources tab
	$('tab_resources').setAttribute('class', 'selected');
	// build the table
	t = createTable(template.resources);
	t.inject($('tab'));
	// add the new resource button
	b = new Element('button', { id: 'btn_newResource', html: 'New Resource' });
	b.addEvent('click', createNewResourceWindow);
	b.inject($('tab'));
}

function tabSubTemplates()
/* load the sub templates tab
 *
 */
{
	// clear the tab container
	$('tab').set('html', '');
	// unselect all tabs
	$$('#tab_resources', '#tab_content', '#tab_templates', '#tab_elements').set('class', '');
	// select the resources tab
	$('tab_templates').setAttribute('class', 'selected');
	// build the table
	t = createTable(template.children);
	t.inject($('tab'));
	// add the new child template button
	b = new Element('button', { id: 'btn_newChildTemplate', html: 'New Sub-Template' });
	b.addEvent('click', function(){
		createNewTemplateWindow(template.id);
		$('btn_cancel').addEvent('click', function(){ loadTemplate(template.id); });
		});
	b.inject($('tab'));
}

function toggleTemplateEnabled()
/* toggle enabled value for the loaded template
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: template_urls.toggleEnabled + '/' + template.id,
		noCache: true,
		onSuccess: function(t,e,h,j){
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				if (template.enabled == '0') {
					template.enabled = '1';
					$('btn_templateEnable').set('html', 'Disable');
					showNotice('Template Enabled');
				} else {
					template.enabled = '0';
					$('btn_templateEnable').set('html', 'Enable');
					showNotice('Template Disabled');
				}
			} catch(er) {}
		}
	});
	myRequest.send();
}

function unassociateTemplate(id)
/* unassociate the template specified by id from the loaded template
 *
 * this will make the template a "parent" template by clearing
 *   the parent_id field
 *
 * optionally copy inherited resource mappings (?)
 *   the problem with doing this now is that the only way to
 *   effectively "unmap" a resource is to also delete it.
 *   that means this will have to duplicate the resources
 *   and create new mappings... which is doable I suppose.
 *
 */
{
	
}