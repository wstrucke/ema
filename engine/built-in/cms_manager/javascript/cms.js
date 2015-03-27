/* Javascript Document */

/* Copyright 2010 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

var disableTimer, globalSearchActive, go, goDisable, lastSearch;
var hideMe = function(){ this.setStyle('display', 'none'); }
var hideMySelect = function() { this.getParent().getElement('select').setStyle('display', 'none'); }
var mode;
var cmsData = {
	menus: false,
	paths: false
	};
var cms_settings = {
	admin_menu: '<?php echo intval($t->cms->administrator_menu); ?>',
	content_code: '<?php echo addslashes($t->get->content_request_code); ?>',
	dev_mode: '<?php echo intval($t->cms->dev_mode); ?>',
	download_code: '<?php echo addslashes($t->get->download_request_code); ?>',
	home: '<?php echo addslashes($t->get->default_home); ?>',
	ps: '<?php echo addslashes($t->get->ps); ?>',
	title: '<?php echo addslashes($t->get->site_title); ?>',
	template: '<?php echo addslashes($t->get->default_template_id); ?>'
	};
var elements = undefined;
var site_base_url = '<?php echo url(''); ?>';
var templates = undefined;

/* XML Paths */
var cms_urls = {
	create: '<?php echo url('xml/create/element'); ?>',
	create_path: '<?php echo url('xml/create/path'); ?>',
	'delete': '<?php echo url('xml/delete/element'); ?>',
	delete_path: '<?php echo url('xml/delete/path'); ?>',
	list: '<?php echo url('xml/list/elements'); ?>',
	list_nav: '<?php echo url('xml/list/navigation'); ?>',
	list_templates: '<?php echo url('xml/list/templates'); ?>',
	load_content: '<?php echo url('xml/get/element/content'); ?>',
	load_meta: '<?php echo url('xml/get/element/info'); ?>',
	save_content: '<?php echo url('xml/update/element/content'); ?>',
	save_meta: '<?php echo url('xml/update/element/info'); ?>',
	save_path_parent: '<?php echo url('xml/update/path/parent'); ?>',
	save_site_settings: '<?php echo url('xml/update/site/settings'); ?>',
	toggle_path_menu: '<?php echo url('xml/toggle/path_in_menu'); ?>'
	};

/* Field Definitions */

var cmsImages = {
	alias: '<?php echo img('download', 'alias.png', '24', '24', 'alias', 'This element is a simple alias for another item'); ?>',
	file: '<?php echo img('download', 'attachment.png', '24', '24', 'attachment', 'This is a downloadable file'); ?>',
	bookmark: '<?php echo img('download', 'bookmark.png', '24', '24', 'bookmark', 'This item appears in one or more site navigation menus'); ?>',
	circuit: '<?php echo img('download', 'circuit.png', '24', '24', 'circuit', 'This element is a circuit, combining one or more threads and/or fuses on a single page'); ?>',
	fuse: '<?php echo img('download', 'fuse.png', '24', '24', 'fuse', 'This element is a fuse provided by an enabled module or component'); ?>',
	link: '<?php echo img('download', 'link.png', '24', '24', 'link', 'This element is a static link'); ?>',
	locked: '<?php echo img('download', 'locked.png', '24', '24', 'locked', 'This element has been locked by the owner or an administrator and can not be modified'); ?>',
	permissions: '<?php echo img('download', 'permissions.png', '24', '24', 'permissions', 'Extended permissions have been set for this element'); ?>',
	thread: '<?php echo img('download', 'thread.png', '24', '24', 'thread', 'This element is a simple thread'); ?>',
	filament: '<?php echo img('download', 'lightbulb.png', '24', '24', 'filament', 'This element is a filament provided by an enabled module or component'); ?>',
	close: '<?php echo img('download', 'close-32x64.png', '64', '32', 'close', ''); ?>',
	target: new Element('img', {'src': '<?php echo url('download/target-48x48.png'); ?>', 'width': 16, 'height': 16, 'alt': 'Move'})
};

var contentMetaFieldsDef = {
	name: { label: 'Name', width: 300, required: true },
	title: { label: 'Page Title', width: 300 },
	link_title: { label: 'Link Title', width: 300 },
	description: { label: 'Description', width: 300 },
	template: { label: 'Template (select to override site template)', width: 300 },
	enabled: { label: 'Enabled', type: 'checkbox' },
	locked: { label: 'Locked', type: 'checkbox' },
	ssl_required: { label: 'SSL Required', type: 'checkbox' },
	btn_save: { label: 'Save and Close', type: 'button' },
	btn_save_edit: { label: 'Save and Edit', type: 'button' }
};

var siteSettings = {
	'home': { label: 'Home Page', required: true },
	'title': { label: 'Site Page Title' },
	'template': { label: 'Default Site Template', required: true },
	'content_code': { label: 'Content Request Code', required: true },
	'download_code': { label: 'Download Request Code', required: true },
	'ps': { label: 'Path Separator', required: true },
	'admin_menu': { label: 'Enable Administrator Menu', type: 'checkbox' },
	'dev_mode': { label: 'Development Mode', type: 'checkbox' },
	'btn_save': { label: 'Save and Close', type: 'button' }
};

var elementOptions = {
	meta: 'Edit Information',
	content: 'Edit Content',
	copy: 'Make a copy',
	path: 'Create a path (alias)',
	permissions: 'Set Permissions',
	lock: 'Lock/Unlock',
	'delete': 'Delete'
};

/* Initialization */

window.addEvent('domready', addEventHandlers);

/* Classes */

// editIframe source: http://www.mooforum.net/help12/editable-iframe-t1621.html#p7303
// retrieved nov-15-2009, ws
var editIframe = new Class({
	src_element : null,
	container : null,
	background : null,
	window : null,
	body : null,
	
	initialize : function( ele )
	{
		this.body = $('body');
		this.src_element = $(ele);
		this.container = this.src_element.getParent();
		
		var size = this.body.getSize();
		var scroll = this.body.getScroll();
		var scrollsize = this.body.getScrollSize();
		
		this.background = new Element('div', {
			'id': 'background',
			'html': '&nbsp;',
			'styles': {
				'display':'block',
				'position': 'absolute',
				'top': '0px',
				'left': '0px',
				'width': '100%',
				'height': scrollsize.y,
				'color': '#000',
				'background-color': '#fff',
				'opacity': 0
				}
			});
		
		this.window = new Element('div', {
			'id': 'waiting',
			'class': 'waiting',
			'styles': {
				'display': 'block',
				'width': '500px',
				'border': '1px solid #999',
				'background-color': '#eee'
				}
			});
		
		var btn_cancel = new Element('button', {'html': 'Cancel'});
		btn_cancel.addEvent('click', function(e){
			try
			{
				$$('#waiting', '#background', '#background2').destroy();
			} catch(e){}
			});
		btn_cancel.inject(this.window);
		
		var fld_edit = new Element('textarea', {
			'id': 'fld_edit',
			'html': this.src_element.get('html')
			});
		fld_edit.inject(this.window);
		
		this.window.setPosition({x: ((size.x / 2) - 250), y: (180 + scroll.y)});
		this.window.set('opacity', 0);
		
		this.background.inject(this.body);
		this.window.inject(this.body);
		this.background.fade(0.6);
		this.window.fade(1);
	/*
		this.iframe = new Element('iframe',{
			'styles' : {
				'position' : 'absolute',
				'top' : this.textarea.getPosition(this.container).y,
				'left' : this.textarea.getPosition(this.container).x,
				'height' : this.textarea.getSize().y,
				'width' : this.textarea.getSize().x,
				'display' : 'none'
			}
		});
		this.iframe.inject(this.container);
		this.iframe.contentWindow.document.designMode = 'On';
		this.iframe.contentWindow.document.close();
	*/
	}
});

/* Functions */

function act_CreateLink()
/* create a new link
 *
 * post from the NewLink window (id NewElement)
 *
 */
{
	var wID = this.window_id();
	var data = getFormData(wID, def_NewLink); if (data === false) return false;
	var createStr = '/link?';
	for (var field in data) createStr += field + '=' + encodeURIComponent(data[field]) + '&';
	showWaiting('Saving...');
	return enqueue({'url': cms_urls.create + createStr, silent: true, onSuccess: function(x){
		hideWaiting();
		var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
		loadElements();
		closeWindow('NewElement');
	}}, true);
}

function addEventHandlers()
{
	//installCMSToolbar();
	loadElements();
	loadTemplates();
	try { $('btn_thread').addEvent('click', createContentMetaWindow); } catch(e){}
	try { $('btn_link').addEvent('click', dsp_NewLinkWindow); } catch(e){}
	try { $('btn_paths').addEvent('click', function(){dsp_PathManager(true);}); } catch(e){}
	try { $('btn_menus').addEvent('click', function(){dsp_MenuManager(true);}); } catch(e){}
	try { $('cms_cpanel_type_filter').addEvent('change', function(){$('cms_cpanel_filter').set('value', ''); loadElements();}); } catch(e){}
	try { $('cms_cpanel_search').addEvent('click', loadElements); } catch(e){}
	try { $('btn_sitesettings').addEvent('click', createSiteSettingsWindow); } catch(e){}
	try { $('btn_modules').addEvent('click', function(){ window.location = url('admin/cms/modules'); }); } catch(e){}
	try { $('btn_refresh').addEvent('click', loadElements); } catch(e){}
	
	// add shortcut handlers
	window.addEvent('keydown', function(event) {
		try { if ($('window')) {
			if (event.key=='w' && event.control) {
				// close a window
				event.preventDefault(); $$('#overlay', '#window').destroy();
			}
			return true;
			}
		} catch(e){}
		// only apply if there is no window open
		if (event.key=='r' && event.control) {
			// refresh
			event.preventDefault(); loadElements();
		} else if (event.key=='n' && event.control) {
			// new thread
			event.preventDefault(); createContentMetaWindow();
		} else if (event.key=='l' && event.control) {
			// new link
			event.preventDefault(); dsp_NewLinkWindow();
		} else if (event.key=='a' && event.control) {
			// path manager
			event.preventDefault(); dsp_PathManager();
		} else if (event.key=='m' && event.control) {
			// menu manager
			event.preventDefault(); dsp_MenuManager();
		} else if (event.key=='s' && event.control) {
			// site settings
			event.preventDefault(); createSiteSettingsWindow();
		}
	});
	
	// element search field
	
	// clear the last search value since this is a new instance
	lastSearch = '';
	globalSearchActive = true;
	var search = $('cms_cpanel_filter');
	var searchPosition = search.getCoordinates(search.getParent());
	search_clear = new Element('div', {'id': 'cancelSearch'});
	search_clearLink = new Element('a', {'html': '[ X ]'});
	search_clear.addEvent('mouseenter', function() { this.fade(0.25, 0.99, {'duration': 'short'}); });
	search_clear.addEvent('mouseleave', function() { this.fade(0.99, 0.25, {'duration': 'short'}); });
	search_clearLink.addEvent('click', function(){
		$('cms_cpanel_filter').set('value', '');
		$('cms_cpanel_filter').focus();
		// clear the last search value
		lastSearch = null;
		loadElements(true);
	});
	search_clearLink.inject(search_clear);
	search_clear.inject(search, 'after');
	search_clear.setStyle('top', (searchPosition['top'] + 2));
	search_clear.setStyle('right', (searchPosition['right'] + 90));
	search.addEvent('keydown', function(e) {
			if (e.key == 'enter') { e.stop(); elementSearch(this.value, this.id); }
		});
	search.addEvent('keyup', function(e) { elementSearch(this.value, this.id); });
	search.focus();
	
}

function createContentEditorWindow(local_mode)
/* create a content editor popup window
 *
 */
{
	var edit = false;
	var fd = {
	  'content_help': { 'type': 'p' },
		'content': { 'type': 'textarea', 'width': 750, 'rows': 30, 'lang': 'html' },
		'btn_save': { 'type': 'button', 'label': 'Save' },
		'btn_close': { 'type': 'button', 'label': 'Save and Close' },
		'btn_preview': { 'type': 'button', 'label': 'Preview' }
		};
	
	if (local_mode == 'edit') {
		edit = true;
		// add the hidden fields
		fd['id'] = { 'type': 'hidden' };
		fd['type'] = { 'type': 'hidden' };
	}
	
	createWindow('Edit Element Content', fd, edit);
	
	if (ema.wysiwyg) {
		$('content_help').set('html', 'Update the content in the editor below.  Changes will appear on your site as soon as you save them.');
	} else {
		$('content_help').set('html', "Use HTML.  Do not include html, head, or body tags.  Anything written here will go inside the template editable area, so global site tags should be in your template <strong>not</strong> in each page!  To insert links within this site, use php functions: <br /><br />&lt;?php echo l('LINK TEXT', 'LINK_PATH'); ?&gt;.  Images should be inserted using either the img function or the url function for the image path, for example:<br /><br /><strong>&lt;img src=\"&lt;?php echo url('download/file_id'); ?&gt;\" alt=\"something\" width=\"100\" height=\"100\" /&gt;.");
	}
	
	// add events
	$('btn_save').addEvent('click', function(e) { saveElementContent(false); });
	$('btn_close').addEvent('click', function(e) { saveElementContent(true); });
	$('btn_preview').addEvent('click', function(e) { showNotice('not implemented at this time'); });
	$('content').addEvent('keydown', function(event) {
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
			saveElementContent(false);
		}
	});
	
	$('content').focus();
}

function createContentMetaWindow(local_mode)
/* create a content meta data popup window
 *
 */
{
	// generate the template select element
	contentMetaFieldsDef.template.type = ele_templateSelect();
	
	var edit = false;
	var fd = contentMetaFieldsDef;
	
	// set the global mode for this window
	if (local_mode == 'edit') { mode = 'meta_edit'; edit = true; } else { mode = 'meta_new'; edit = true; }
	
	if (mode == 'meta_edit') {
		// add the hidden fields
		fd['id'] = { 'type': 'hidden' };
		fd['type'] = { 'type': 'hidden' };
	}
	
	createWindow('Edit Element Information', fd, edit);
	$('window').addClass('record');
	$('window').setStyles({'width': 310});
	
	// add button events
	$('btn_save').addEvent('click', function(e) { createElement(); });
	$('btn_save_edit').addEvent('click', function(e) { createElement(true); });
	
	// conditionally set focus on the name field
	if (mode == 'meta_edit') $('name').focus();
}

function createElement(auto_edit)
/* create an element using xml from the create content meta window
 *
 * if auto_edit is true then a window to edit the element will be
 *  opened automatically
 *
 */
{
	// verify auto_edit setting
	if (auto_edit == null) auto_edit = false;
	
	// create an empty post array
	var post_args = '';
	
	// verify required fields
	if ($('name').get('value') == ''){ showNotice('Name is required'); return false; }
	
	// collect form data
	for (i in contentMetaFieldsDef){
		var f = $(i);
		switch(i){
			case 'template':
				post_args += 'template_id=' + f.get('value') + '&';
				break;
			case 'enabled':
				post_args += i;
				if (f.checked) { post_args += '=1&'; } else { post_args += '=0&'; }
				break;
			case 'locked':
				post_args += i;
				if (f.checked) { post_args += '=1&'; } else { post_args += '=0&'; }
				break;
			case 'ssl_required':
				post_args += i;
				if (f.checked) { post_args += '=1&'; } else { post_args += '=0&'; }
				break;
			default:
				post_args += i + '=' + encodeURIComponent(f.get('value')) + '&';
				break;
		}
	}
	
	// remove the last ampersand
	post_args = post_args.substring(0, (post_args.length - 1));
	
	// check the global mode
	if (mode == 'meta_edit') {
		var local_url = cms_urls.save_meta + '/' + $('id').get('value') + '/' + $('type').get('value');
	} else if (mode == 'meta_new') {
		var local_url = cms_urls.create;
	} else {
		return showNotice('Invalid Meta Mode');
	}
	
	if (auto_edit) { mode = 'element_edit'; } else { mode = false; }
	
	// post to xml
	var myRequest = new Request.HTML({
		method: 'post',
		url: local_url,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove meta window
				$$('#overlay', '#window').destroy();
				
				// refresh the content list
				loadElements();
				
				// if auto_edit show content (thread) editor window
				if (mode == 'element_edit') {
					createContentEditorWindow('edit');
					var record = e.filter('cms_element')[0];
					if (ema.wysiwyg) {
						editor.setData(record.getElement('content').textContent);
					} else {
						$('content').set('value', record.getElement('content').textContent);
					}
					$('id').set('value', record.getElement('id').textContent);
					$('type').set('value', record.getElement('type').textContent);
					$('content').focus();
				}
				
			} catch(er) {}
		}
	});
	
	myRequest.send(post_args);
	
	showWaiting('Saving...');
}

function createSiteSettingsWindow()
/* create the site settings window
 *
 */
{
	if (!$defined(elements)) {
		showWaiting('Retrieving elements from the server');
		return enqueue({'url': cms_urls.list + '/circuit,thread,fuse', silent: true, onSuccess: function(x){
			try {
			hideWaiting();
			var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
			var list = xmlDoc.getElements('element');
			elements = Array();
			for (var i=0;i<list.length;i++){
				elements[i] = {'id': list[i].getAttribute('id'), 'name': list[i].getAttribute('name'), 'type': list[i].getAttribute('type')};
			}
			}catch(e){showNotice(e);}
			return createSiteSettingsWindow();
		}}, true);
	}
	
	siteSettings.home['type'] = ele_eleSelect('home', cms_settings.home);
	siteSettings.template['type'] = ele_templateSelect();
	try { $$('#window', '#overlay').destroy(); } catch(e){}
	createWindow('Site Settings', siteSettings, true);
	$('window').addClass('record');
	$('window').setStyles({'width': 310});
	/* load settings */
	try { for (i in cms_settings) {
		if ((siteSettings[i].type == 'checkbox')&&(cms_settings[i] == '1')) {
			$(i).set('checked', 'checked');
		} else {
			$(i).set('value', cms_settings[i]);
		}
	} } catch(e) { showNotice('Error processing ' + i + ': ' + e); }
	/* add button events */
	$('btn_save').addEvent('click', function(){
		// collect and validate settings
		var d = Array();
		for (i in cms_settings) {
			if ((siteSettings[i].type == 'checkbox')&&($(i).get('checked'))) {
				d[i] = '1';
			} else if (siteSettings[i].type == 'checkbox') {
				d[i] = '0';
			} else {
				d[i] = $(i).get('value');
			}
			if (d[i].length == 0) return showNotice('A value for ' + i + ' is required.');
		}
		// pass to the save settings function
		return saveSiteSettings(d);
	});
}

function deletePath(id)
/* delete the path specified by id
 *
 */
{
	if (!confirm('Are you sure?')) return false;
	showWaiting('Please wait...');
	return enqueue({'url': cms_urls.delete_path + '/' + id, silent: true, onSuccess: function(x){
		hideWaiting();
		var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
		closeWindow('PathManager');
		dsp_PathManager(true);
	}}, true);
}

function deleteMenu(id)
/* delete the path specified by id
 *
 */
{
	if (!confirm('Are you sure?')) return false;
	showWaiting('Please wait...');
	return enqueue({'url': cms_urls.toggle_path_menu + '/' + id, silent: true, onSuccess: function(x){
		hideWaiting();
		var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
		closeWindow('MenuManager');
		dsp_MenuManager(true);
	}}, true);
}

function dsp_MenuManager(refresh)
/* display the menu manager window
 *
 * if refresh is true, force a refresh of the list
 *
 */
{
	if ($defined(refresh)&&(refresh === true)) cmsData.menus = false;
	if (cmsData.menus === false) { showWaiting('Loading Menus...'); return enqueue({'url': cms_urls.list_nav + '/true', silent: true, onSuccess: function(x){
		hideWaiting();
		var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
		var list = xmlDoc.getElements('record');
		var ul = $('list');
		cmsData.menus = Array();
		for (i=0;i<list.length;i++) {
			var path = {
				'id': list[i].getAttribute('id'),
				'element': list[i].getAttribute('element'),
				'template': list[i].getAttribute('template'),
				'menu': list[i].getAttribute('menu'),
				'level': list[i].getAttribute('level'),
				'parent_id': list[i].getAttribute('parent_id'),
				'parent_order': list[i].getAttribute('parent_order'),
				'path': list[i].textContent,
				'name': list[i].getAttribute('name'),
				'link_title': list[i].getAttribute('link_title'),
				'type': list[i].getAttribute('type')
			};
			cmsData.menus.push(path);
		}
		dsp_MenuManager();
	}}, true); }
	
	var wID = createWindow_New('Site Navigation - Menus', def_MenuManager, 'MenuManager');
}

function dsp_NewLinkWindow()
/* create a prompt to create a link
 *
 */
{
	var wID = createWindow_New('Define a New Link', def_NewLink, 'NewElement');
	if (wID) { $(wID).addClass('record'); }
}

function dsp_PathManager(refresh)
/* display the path manager window
 *
 * if refresh is true, force a refresh of the list
 *
 */
{
	if ($defined(refresh)&&(refresh === true)) cmsData.paths = false;
	if (cmsData.paths === false) { showWaiting('Loading Paths...'); return enqueue({'url': cms_urls.list_nav, silent: true, onSuccess: function(x){
		hideWaiting();
		var xmlDoc = parseXmlResponse(x); if (xmlDoc === false) return false;
		var list = xmlDoc.getElements('record');
		var ul = $('list');
		cmsData.paths = Array();
		for (i=0;i<list.length;i++) {
			var path = {
				'id': list[i].getAttribute('id'),
				'element': list[i].getAttribute('element'),
				'template': list[i].getAttribute('template'),
				'menu': list[i].getAttribute('menu'),
				'level': list[i].getAttribute('level'),
				'parent_id': list[i].getAttribute('parent_id'),
				'parent_order': list[i].getAttribute('parent_order'),
				'path': list[i].textContent,
				'name': list[i].getAttribute('name'),
				'link_title': list[i].getAttribute('link_title'),
				'type': list[i].getAttribute('type')
			};
			cmsData.paths.push(path);
		}
		dsp_PathManager();
	}}, true); }
	
	var wID = createWindow_New('Site Navigation - Paths', def_PathManager, 'PathManager');
}

function ele_eleSelect(id, selected, types)
/* create and return a select element with all available cms elements as options
 *
 * optionally limit to a comma separated list of types (e.g. 'circuit,thread')
 *  - types is not implemented yet, rather the createSiteSettingsWindow is limiting
 *    which element types are returned during the query
 *
 */
{
	if (! id) { id = 'ele_id'; }
	var ele = new Element('select', { 'id': id });
	var opt = new Element('option', { 'value': '', 'html': '&nbsp;' });
	opt.inject(ele);
	for (var i=0;i<elements.length;i++) {
		opt = new Element('option', { 'value': encodeURIComponent(elements[i]['id']), 'html': elements[i]['name'] });
		if (selected) { if (elements[i]['id'] == selected) opt.set('selected', 'selected'); }
		opt.inject(ele);
	}
	return ele;
}

function ele_templateSelect(id, selected)
/* create and return a select element with all of the templates as options
 *
 */
{
	if (! id) { id = 'template'; }
	var ele = new Element('select', { 'id': id });
	var opt = new Element('option', { 'value': '', 'html': '&nbsp;' });
	opt.inject(ele);
	for (var j=0;j<templates.length;j++) {
		if (templates[j]['name'].length > 0) {
			opt = new Element('option', { 'value': encodeURIComponent(templates[j]['id']), 'html': templates[j]['name'] });
			if (selected) { if (templates[j]['id'] == selected) opt.set('selected', 'selected'); }
			opt.inject(ele);
		}
	}
	return ele;
}

function elementListAction()
{
	var recordID = this.record_id().split('/');
	var id = recordID[0];
	var type = recordID[1];
	
	switch(this.get('value'))
	{
		case '------------------------------------': break;
		case 'content':
			createContentEditorWindow('edit');
			createOverlay($('window'), 'windowOverlay');
			
			var myRequest = new Request.HTML({
				url: cms_urls.load_content + '/' + id,
				noCache: true,
				onSuccess: function(t,e,h,j){
					$('windowOverlay').destroy();
					// if error show message and stop here
					try { if (e.filter("error")[0]) { err = e.filter("error")[0]; return showNotice('Error loading content'); } } catch(er) {}
					var record = e.filter('cms_element')[0];
					if (ema.wysiwyg) {
						editor.setData(record.getElement('content').textContent);
					} else {
						$('content').set('value', record.getElement('content').textContent);
					}
					// set id and type as well
					$('id').set('value', record.getElement('id').textContent);
					$('type').set('value', record.getElement('type').textContent);
					$('content').focus();
				},
			onFailure: function() { showNotice('The request failed.'); }
			});
			myRequest.get();
			break;
		case 'delete':
			if (confirm('Are you sure?')) {
				var myRequest = new Request.HTML({
					url: cms_urls['delete'] + '/' + id,
					noCache: true,
					onSuccess: function(t,e,h,j){
						hideWaiting();
						// if error show message and stop here
						try { if (e.filter("error")[0]) { err = e.filter("error")[0]; return showNotice('Error deleting item'); } } catch(er) {}
						// refresh the content list
						return loadElements();
					},
				onFailure: function() { hideWaiting(); showNotice('The request failed.'); }
				});
				showWaiting('Deleting...');
				myRequest.get();
			}
			break;
		case 'meta':
			createContentMetaWindow('edit');
			createOverlay($('window'), 'windowOverlay');
			var myRequest = new Request.HTML({
				url: cms_urls.load_meta + '/' + this.record_id(),
				noCache: true,
				onSuccess: function(t,e,h,j){
					$('windowOverlay').destroy();
					// if error show message and stop here
					try { if (e.filter("error")[0]) { err = e.filter("error")[0]; return showNotice('Error loading meta data'); } } catch(er) {}
					var record = e.filter('cms_element')[0];
					for (i in contentMetaFieldsDef) {
						// need to account for template versus template_id here
						try { $(i).set('value', record.getElement(i).textContent); } catch(e){}
					}
					// set non-text field values
					$('template').set('value', record.getElement('template_id').textContent);
					if (record.getElement('enabled').textContent == '1') { $('enabled').set('checked', 'checked'); }
					if (record.getElement('ssl_required').textContent == '1') { $('ssl_required').set('checked', 'checked'); }
					if (record.getElement('locked').textContent == '1') { $('locked').set('checked', 'checked'); }
					// set id and type as well
					$('id').set('value', record.getElement('id').textContent);
					$('type').set('value', record.getElement('type').textContent);
				},
			onFailure: function() { showNotice('The request failed.'); }
			});
			myRequest.get();
			break;
		case 'path':
			var fd = {
				'path_eid': { type: 'hidden' },
				'new_path': { label: 'Path' },
				'path_is_outdated': { label: 'Mark as Outdated', type: 'checkbox' },
				'btn_save': { label: 'Save', type: 'button' }
			};
			pid = id.split('/');
			createWindow('Create a path', fd, true);
			$('path_eid').set('value', pid[0]);
			$('btn_save').addEvent('click', function() {
				// post to xml
				var myRequest = new Request.HTML({
					method: 'post',
					url: cms_urls.create_path,
					onSuccess: function(t,e,h,j){
						hideWaiting();
						// if error show message and stop here
						try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
						try { $$('#window', '#overlay').destroy(); } catch(er) {}
					}
				});
				if ($('path_is_outdated').checked) { var legacy_value = 'true'; } else { var legacy_value = 'false'; }
				myRequest.send('path=' + encodeURIComponent($('new_path').get('value')) + '&element_id=' + encodeURIComponent($('path_eid').get('value')) + '&path_is_outdated=' + legacy_value);
				showWaiting('Saving...');
			});
			break;
		default: showNotice('option not implemented'); break;
	}
	this.set('value', '0');
	this.blur();
}

function elementSearch(txt, search_field_id)
/* element search function for filtering the element list
 *
 * this function uses two timers:
 *  - "timer" to perform a search
 *  - "disableTimer" to disable the search for a period of time after one search
 *    this prevents multiple searchs executing repeatedly
 *
 * txt is the search string
 * search_field_id is the id of the search box, ideally provided by the box
 *
 */
{
	// the global search flag must be active and there must be at least two characters
	if (! globalSearchActive) return false;
	if (txt.length < 1) return true;
	
	// check and start counter if necessary
	if (go == false) {
		// set timer
		// NOTE: this is the line that causes the strange search behavior due to
		// it sending a 1.5 second delay to search for the text that was entered
		// WHEN the timer was set (as opposed to the current text 1.5 secs later...)
		timer = setTimeout(function() { go = true; try { elementSearch($(search_field_id).value, search_field_id); } catch(e){} }, 750);
		return true;
	}
	
	// goDisable overrides the default behavior whilst a timer is running
	if (goDisable == true) { return true; }
	
	// don't search if nothing changed
	if (txt == lastSearch) { return true; }
	
	clearTimeout(timer);
	
	go = false;
	goDisable = true;
	disableTimer = setTimeout('goDisableTimeout()', 750);
	lastSearch = txt;
	
	try { $('search_indicator').destroy(); } catch(e){}
	var i = indicator('search_indicator');
	try { i.inject($(search_field_id), 'after'); } catch(e){}
	
	return loadElements($(search_field_id).value);
}

function goDisableTimeout()
{
	clearTimeout(timer);
	clearTimeout(disableTimer);
	goDisable = false;
	return true;
}

function initElementList(){
	var list = $$('ul#cms_cpanel_list li.cms_cpanel_element');
	list.each(function(li){
		var s = li.getElement('div');
		var sel = li.getElement('select');
		$$(s, sel).setStyle('cursor', 'pointer');
		s.addEvent('mouseover', function(){
			$$('ul#cms_cpanel_list select').each(function(el){el.setStyle('display', 'none');});
			this.getParent().getElement('select').setStyle('display', 'block');
			this.getParent().getElement('select').addEvent('mouseout', hideMe);
		});
		sel.addEvents({
			'click': function() {
				this.removeEvent('mouseout', hideMe);
				this.getParent().getElement('div').removeEvent('mouseout', hideMySelect);
				},
			'mouseout': hideMe,
			'blur': hideMe
		});
	});
}

function installCMSToolbar()
{
	var toolbar = new Element('div', {
		'id': 'toolbar',
		'html': '<h1>CMS Toolbar</h1>'
		});
	
	var btn_edit = new Element('a', {
		'id': 'btn_edit',
		'title': 'Click to edit an element',
		'style': 'cursor: pointer; border: 1px solid #000; padding: 1px; z-index: 1;'
		});
	
	var btn_edit_img = new Element('img', {
		'src': '#',
		'alt': 'E',
		'width': 16,
		'height': 16
		});
	
	btn_edit_img.inject(btn_edit);
	
	btn_edit.addEvent('click', function(e){
		e.stop();
		clickable = $('workspace');
		clickable.highlight();
		clickable.addEvent('mouseenter', function(e){
			e.stop();
			this.set({'styles': {
				'cursor': 'pointer'
				}});
			this.tween('background-color', '#d8edff');
			});
		clickable.addEvent('mouseleave', function(e){
			e.stop();
			this.set({'styles': {
				'cursor': 'default'
				}});
			this.tween('background-color', '#fff');
			});
		clickable.addEvent('click', function(e){
			//this.set({'styles': {'cursor': 'text'}});
			//this.tween('background-color', '#fcffc2');
			//this.removeEvents();
			//showWaiting('This is a test');
			//timer = setTimeout('hideWaiting()', 5000);
			var MyEditFrame = new editIframe('workspace');
			});
		});
	
	btn_edit.inject(toolbar);
	
	try
	{
		toolbar.inject($$('body')[0], 'bottom');
	} catch(e) { alert('Error: ' + e); }
	
	//$('query').addEvent('click', clearQuery);
	//$('query').addEvent('keydown', function(e){
	//					if (e.key=='enter') { e.stop(); groupSearch($('query').value); } 
	//					});
	//$('query').addEvent('keyup', function(e) { groupSearch(this.value); });
	
	// remove any search box
	//try { $('popup').destroy(); } catch(e){}
}

function loadElements(filter){
	var myURL = cms_urls.list + '/' + $('cms_cpanel_type_filter').get('value');
	if ($defined(filter)&&(filter.length > 0)) myURL += '/' + encodeURIComponent(filter);
	var myRequest = new Request.HTML({
		method: 'get',
		url: myURL,
		onSuccess: function(t,e,h,j){loadElementsExecute(e);}
		});
	
	myRequest.send();
	
	if (!$defined(filter)) showWaiting('Retrieving elements from the server');
}

function loadElementsExecute(xmlDoc){
	try { $('search_indicator').destroy(); } catch(e){}
	try {
		if (xmlDoc.filter("error")) {
			err = xmlDoc.filter("error")[0];
			hideWaiting();
			//clean_up();
			//showSearch();
			return showNotice("Error loading elements: " + err.get('html'));
		}
	} catch(e){}
	try
	{
		list = xmlDoc.filter('element');
		
		ul = $('cms_cpanel_list');
		ul.set('html', '');
		
		for(i=0;i<list.length;i++){
		
			// first build the image list for this element depending on the settings
			var images = cmsImages[list[i].getAttribute('type')];
			
			try { if (list[i].filter('locked')[0].get('html') == '1') { images += cmsImages['locked']; } } catch(e){}
			try { if (list[i].filter('in_menu')[0].get('html') == '1') { images += cmsImages['bookmark']; } } catch(e){}
			try { if (list[i].filter('extended_permissions')[0].get('html') == '1') { images += cmsImages['permissions']; } } catch(e){}
			
			var l = new Element('li', { 'class': 'cms_cpanel_element', 'html': images });
			var d = new Element('div', { 'html': list[i].getAttribute('name') });
			var d1 = new Element('div');
			try { d1.set('html', list[i].filter('last_modified')[0].get('html')); } catch(e){}
			var d2 = new Element('input', { 'type': 'hidden', 'value': list[i].getAttribute('id') + '/' + list[i].getAttribute('type') });
			var s = new Element('select');
			s.record_id(list[i].getAttribute('id') + '/' + list[i].getAttribute('type'));
			var o = new Element('option', { 'html': list[i].getAttribute('name'), 'selected': 'selected', 'value': '0' });
			o.inject(s);
			var o = new Element('option', { 'html': '------------------------------------' });
			o.inject(s);
			
			for (j in elementOptions) {
				var o = new Element('option', { 'html': elementOptions[j], 'value': j });
				o.inject(s);
			}
			
			// set select action
			s.addEvent('change', elementListAction);
			
			$$(d, d1, d2, s).inject(l);
			l.inject(ul);
		}
		
		initElementList();
		
		hideWaiting();
		
	} catch(e){}
}

function loadTemplates()
/* retrieve the master template list
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'get',
		url: cms_urls.list_templates,
		onSuccess: function(t,e,h,j){
			// if error show message and stop here
			try { if (e.filter("error")[0]) { err = e.filter("error")[0]; return showNotice('Error loading templates'); } } catch(er) {}
			try {
				var list = e.filter('template');
				templates = new Array(list.length);
				for (i=0;i<list.length;i++) {
					templates[i] = { id: list[i].getAttribute('id'), name: list[i].getAttribute('name'), description: list[i].get('text') };
				}
			} catch(er) {}
		}
	});
	myRequest.send();
}

function menu_list()
/* return an array of li elements for the menumanager
 *
 */
{
	// initialize return array
	var r = Array();
	
	// create header
	var li = new Element('li', {'class': 'header', 'html': '<span class="medium">Name</span><span class="medium">Link Title</span><span>Element ID</span>'});
	r.push(li);
	
	if (cmsData.menus === false) return false;
	
	// 	cmsData.menus: id element template menu level parent_id parent_order name link_title type
	
	// create the spacer element
	var spacer = new Element('li', { 'class': 'spacer', 'value': '&nbsp;' });
	// add the top spacer
	r.push(spacer.clone());
	
	// create a temporary array to store elements by parent_level and a corresponding index
	var elements_by_level = Array();
	var level_index = Array();
	
	for (var i=0;i<cmsData.menus.length;i++){
		var li = new Element('li', { class: 'dragable' }); li.record_id(i);
		
		var name = new Element('span', { html: cmsData.menus[i].name, class: 'medium', title: cmsData.menus[i].id });
		
		if (cmsData.menus[i].link_title.length > 0) {
			var link = new Element('span', { html: cmsData.menus[i].link_title, class: 'medium' });
		} else {
			var link = new Element('span', { html: '&nbsp;', class: 'medium' });
		}
		
		if (cmsData.menus[i].element.length > 0) {
			var ele = new Element('span', { html: cmsData.menus[i].element });
		} else {
			var ele = new Element('span', { html: '&nbsp;' });
		}
		
		var del = new Element('button', { html: 'X', 'title': 'Remove from the menu' });
		del.addEvent('click', function() {
			var i = this.getParent().record_id();
			deleteMenu(cmsData.menus[i].id);
		});
		
		$$(name, link, ele, del, cmsImages.target.clone()).inject(li);
		
		level = parseInt(cmsData.menus[i].level);
		if (!$defined(elements_by_level[level])) elements_by_level[level] = Array();
		elements_by_level[level].push(li);
		level_index[parseInt(cmsData.menus[i].id)] = (elements_by_level[level].length-1);
	}
	
	// create an empty list to use for parents
	var container = new Element('li', { 'class': 'children' });
	var ul = new Element('ul');
	ul.grab(spacer.clone());
	
	// process the elements_by_level in reverse, removing items and placing them in their parent elements
	for (var j=(elements_by_level.length-1);j>=0;j--) {
		while (li = elements_by_level[j].shift()) {
			var id = li.record_id();
			if (cmsData.menus[id].parent_id.length > 0) {
				var parent_level_index = level_index[parseInt(cmsData.menus[id].parent_id)];
				if (!elements_by_level[j-1][parent_level_index].getElement('ul')) {
					ul.clone().inject(elements_by_level[j-1][parent_level_index]);
				}
				// extract any child ul and append it to a new container after this one
				if (li.getElement('ul')) {
					var ul2 = li.getElement('ul').dispose();
					var li2 = container.clone();
					li2.grab(ul2);
				} else {
					var li2 = false;
				}
				elements_by_level[j-1][parent_level_index].getElement('ul').adopt(li, spacer.clone());
				if (li2) li2.inject(elements_by_level[j-1][parent_level_index].getElement('ul'));
			} else {
				// extract any child ul and append it to a new container after this one
				if (li.getElement('ul')) {
					var ul2 = li.getElement('ul').dispose();
					var li2 = container.clone();
					li2.grab(ul2);
				} else {
					var li2 = false;
				}
				r.push(spacer.clone());
				r.push(li);
				if (li2) r.push(li2);
			}
		}
	}
	
	// add the bottom spacer
	if (r.length > 1) r.push(spacer.clone());
	
	return r;
}

function menu_list_dragable()
/* enable drag and drop on the menu list
 *
 */
{
	try {
	// make elements dragable
	$$('ul.list .dragable').each(function(drag) {
		// set the drop elements
		var dropElements = $$('.spacer', '.dragable');
		var dropContainer = $('MenuManager');
		new Drag.Ghost(drag, {
			container: dropContainer,
			handle: drag.getChildren('img')[0],
			droppables: dropElements,
			onDrop: function(el,dr) {
				if (! dr) return false;
				dr.removeClass('spacer_mo');
				dr.tween('height', 7);
				if ((el == dr)||(dr == el.nextSibling)||(el.nextSibling.hasChild(dr))) return false;
				// create the spacer element
				var spacer = new Element('li', { 'class': 'spacer', 'value': '&nbsp;' });
				if (el.nextSibling.get('class').trim() == 'children') {
					el.nextSibling.inject(dr, 'after');
					var doSpacer = false;
				} else {
					var doSpacer = true;
					el.nextSibling.destroy();
				}
				if (dr.get('class').trim() == 'dragable') {
					if (dr.nextSibling.get('class').trim() != 'children') {
						// create children ul
						var container = new Element('li', { 'class': 'children' });
						var ul = new Element('ul');
						spacer.clone().inject(ul);
						ul.inject(container);
						container.inject(dr, 'after');
					}
					spacer.clone().inject(dr.nextSibling.firstChild);
					el.inject(dr.nextSibling.firstChild);
					spacer.inject(dr.nextSibling.firstChild);
				} else {
					if (doSpacer) spacer.inject(dr, 'after');
					el.inject(dr, 'after');
				}
				el.highlight('#667c4a');
				
				// cleanup
				/* tbd -- remove empty children containers */
				
				// get this element path
				var id = cmsData.menus[el.record_id()].id;
				// get the new parent_id
				if (el.getParent().get('class') && (el.getParent().get('class').trim() == 'list')) {
					// this is a top level element
					var parent_id = false;
				} else {
					// this is a child element
					var parent_id = cmsData.menus[el.getParent().getParent().getPrevious('li').record_id()].id;
				}
				// get the predecessor (if any)
				if (el.getPrevious('li.dragable') == null) {
					var after = false;
				} else {
					var after = cmsData.menus[el.getPrevious('li.dragable').record_id()].id;
				}
				// set the complete command to pass to our xml script
				var cmd = id;
				if (parent_id != false) cmd += '/' + parent_id;
				if ((parent_id == false) && (after != false)) cmd += '/0';
				if (after != false) cmd += '/' + after;
				
				// save changes
				enqueue({'url':cms_urls.save_path_parent + '/' + cmd, silent: true}, true);
			},
			onEnter: function(el,dr) { if (dr.get('class').trim() == 'spacer') { dr.tween('height', 25); } dr.addClass('spacer_mo'); },
			onLeave: function(el,dr) { dr.removeClass('spacer_mo'); dr.tween('height', 7); }
		});
	});
	} catch(e) { showNotice('menu_list_dragable error: ' + e);}
}

function path_list()
/* return an array of li elements with all valid paths
 *
 */
{
	// initialize return array
	var r = Array();
	
	// create header
	var li = new Element('li', {'class': 'header', 'html': '<span class="wide">Path</span><span class="wide">Element</span><span>Template ID</span><span class="small" title="Display in Site Menu">Menu</span><span class="small">Preview</span>'});
	r.push(li);
	
	if (cmsData.paths === false) return false;
	
	// 	cmsData.paths: id element template menu level parent_id parent_order name link_title type
	
	for (var i=0;i<cmsData.paths.length;i++){
		var li = new Element('li'); li.record_id(i);
		
		if (cmsData.paths[i].path.length > 0) {
			var path = new Element('span', { html: cmsData.paths[i].path, class: 'wide' });
		} else {
			var path = new Element('span', { html: 'Link', class: 'wide' });
		}
		
		var eleID = new Element('span', { html: cmsData.paths[i].name, class: 'wide', title: cmsData.paths[i].element });
		
		if (cmsData.paths[i].template.length > 0) {
			var template = new Element('span', { html: cmsData.paths[i].template });
		} else {
			var template = new Element('span', { html: '&nbsp;' });
		}
		
		var menu = new Element('span', { class: 'small' });
		var menu_box = new Element('input', { type: 'checkbox', class: 'checkbox' });
		if (cmsData.paths[i].menu=='true') menu_box.checked = true;
		menu_box.addEvent('click', function(){
			var i = this.getParent().getParent().record_id();
			var enabled = this.get('checked');
			return enqueue({'url': cms_urls.toggle_path_menu + '/' + cmsData.paths[i].id, silent: true}, true);
		});
		menu_box.inject(menu);
		
		var preview = new Element('span', { html: '<a href="' + '" target="new">Open</a>', class: 'small'});
		
		var del = new Element('button', { html: 'X', 'title': 'Delete this Path' });
		del.addEvent('click', function() {
			var i = this.getParent().record_id();
			deletePath(cmsData.paths[i].id);
		});
		
		$$(path, eleID, template, menu, preview, del).inject(li);
		r.push(li);
	}
	
	return r;
}

function saveElementContent(autoclose)
/* save element content from an editor window 
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: cms_urls.save_content + '/' + encodeURIComponent($('id').get('value')),
		onSuccess: function(t,e,h,j){
			hideWaiting();
			xmlDoc = parseXmlResponse(e);
			if (xmlDoc === false) return false;
			// on success remove meta window
			if (mode) {
				destroyWindow();
				showNotice('Content Updated Successfully');
			}
		}
	});
	showWaiting('Saving...');
	// set autoclose value
	if (autoclose) { mode = true; } else { mode = false; }
	if (ema.wysiwyg) {
		myRequest.send('content=' + encodeURIComponent(CKEDITOR.instances['content'].getData()));
	} else {
		myRequest.send('content=' + encodeURIComponent($('content').get('value')));
	}
}

function saveSiteSettings(d)
/* save site settings
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: cms_urls.save_site_settings,
		onSuccess: function(t,e,h,j){
			hideWaiting();
			// if error show message and stop here
			try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
			try {
				// on success remove meta window
				$$('#overlay', '#window').destroy();
				showNotice('Settings Updated Successfully');
			} catch(er) {}
		}
	});
	var str = '';
	for (i in cms_settings) {
		str += i + '=' + encodeURIComponent(d[i]) + '&';
		cms_settings[i] = d[i]; // this should be done on the response eventually since at this point
		// we can not be sure that the settings were successfully saved
	}
	if (str.length == 0) return showNotice('Error: nothing to save');
	showWaiting('Saving...');
	myRequest.send(str);
}