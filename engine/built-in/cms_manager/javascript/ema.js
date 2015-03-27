/* Javascript Document */

/* ema global javascript
 *  copyright (c) 2009-2011 william strucke [wstrucke@gmail.com]
 *  all rights reserved
 *
 * requires mootools 1.2
 *
 */
 
// declare global variables
var timer, waitTimer;

// data variables
var pendingFunction = false;
var responseQueue = new Array();
var windowID = new Array();
var windowIDMap = new Array();

var xml_special_to_escaped_one_map = {
	'&': '&amp;',
	'"': '&quot;',
	'<': '&lt;',
	'>': '&gt;'
};

var escaped_one_to_xml_special_map = {
	'&amp;': '&',
	'&quot;': '"',
	'&lt;': '<',
	'&gt;': '>'
};

// ema global settings
var ema = {
	base_url: '<?php echo url(''); ?>',
	download: '<?php echo url('download/'); ?>',
	wysiwyg: <?php if ($t->_check('wysiwyg')) { echo 'true'; } else { echo 'false'; } ?>
	
};

// hook registry
var hookRegistry = new Array();

// element record registry
var recordIDMap = new Array();

// asynchronous request queue
var requestQueue = new Array();

/* Initialization */
window.addEvent('domready', emaInit);

function emaInit() {
	// general ema initialization function
	document.ondragstart = function () { return false; }; //IE drag hack
	// preloading
  var images = [ema.download + 'indicator.gif'];
  var loader = new Asset.images(images);
}

// number functions :: http://www.sitepoint.com/forums/showthread.php?t=318819

function getRandomNumber(range) { return Math.floor(Math.random() * range); }
function getRandomChar() {
	var chars = "abcdefghijklmnopqurstuvwxyzABCDEFGHIJKLMNOPQURSTUVWXYZ";
	return chars.substr( getRandomNumber(52), 1 );
}
function randomID(size) {
	var str = "";
	for(var i = 0; i < size; i++)
	{
		str += getRandomChar();
	}
	return str;
}

function centerWindow(win, relative_to_ele)
/* center an existing window
 *
 * win should be an object in the DOM
 *
 * if relative_to_ele is not provided, center using the body
 *
 * if win is not provided, guess element with id 'window'
 *
 */
{
	if (!$defined(win)) win = $('window');
	if (!$defined(relative_to_ele)) relative_to_ele = $('body');
	var size = relative_to_ele.getSize();
	var scroll = relative_to_ele.getScroll();
	var winsize = win.getSize();
	var viewport = window.getSize();
	var bottomBuffer = 10;
	
	// set vertical displacement for aesthetic effect
	if (winsize.y < (viewport.y - 100 - bottomBuffer)) {
		var vDisp = 100;
	} else {
		var vDisp = 99;
		while ((winsize.y > (viewport.y + vDisp + bottomBuffer))&&(vDisp > 0)) { vDisp--; }
	}
	
	win.setPosition({x: ((size.x / 2) - (winsize.x / 2)), y: (scroll.y + vDisp)});
	
	return true;
}

function colorClass(list)
/* given an array of elements, set alternating 'even'/'odd' classes
 *
 * this function will also remove any matchin class
 *
 */
{
	if (! $defined(list)) list = $$('.colorList li');
	var c = 'even';
	for (var i=0;i<list.length;i++){
		list[i].removeClass('even');
		list[i].removeClass('odd');
		list[i].addClass(c);
		if (c == 'even') { c = 'odd'; } else { c = 'even'; }
	}
}

function copy(object)
/* function to copy an object
 *
 * based on: http://my.opera.com/GreyWyvern/blog/show.dml/1725165
 * retrieved: dec-11-2010 ws
 *
 */
{
	return eval ( object.toSource() );
}

function countCollection(obj)
/* count the elements in a collection
 *
 * source: http://stackoverflow.com/questions/956719/number-of-elements-in-a-javascript-object
 * retrieved feb-25-2011 ws
 *
 */
{
	var count = 0;
	for(var prop in obj) { if(obj.hasOwnProperty(prop)) ++count; }
	return count;
}

function createOverlay(ele, id, z)
/* create a semi-transparent overlay on top of the provided element
 *
 * a future revision might randomly create the id for the element
 *  and return it to allow for multiple simultaneous overlays
 *
 */
{
	if (!$defined(id)) id = 'overlay';
	try { if ($(id)) return false; } catch(e) {}
	if (!$defined(z)) z = 1;
	var scrollsize = ele.getScrollSize();
	var o = new Element('div', { 'id': id, 'html': '&nbsp;',
		'styles': {
			'background-color': '#fff',
			'color': '#000',
			'display': 'block',
			'height': scrollsize.y,
			'left': '0px',
			'opacity': 0,
			'position': 'absolute',
			'right': '0px',
			'top': '0px',
			'z-index': z}
		});
	if (ele.getStyle('position') != 'absolute') { ele.setStyle('position', 'relative'); }
	o.addEvent('click', function(){
		// allow a click to remove the background/overlay element if there is no message box on screen
		var x = $('notice'); if (x == null) { var x = $('waiting'); if (x == null) { this.destroy(); } }
	});
	o.inject(ele);
	o.fade(0.6);
	return true;
}

function createRecordField(label, field_id, type, width, readonly, required, fclass, lang)
{
	var template = new Array();
	
	if (type == null) type = 'span';
	if (readonly == null) readonly = true;
	if (required == null) required = false;
	// if field_id is just an integer, change it to a generated id
	if (parseInt(field_id) == field_id) field_id = randomID(8);
	
	if (typeof type == 'object') {
		template = { field: type }
		if ((label != undefined) && (label.length > 0)) template.label = new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label});
	} else if (type == 'input') {
		template = {
			'label': new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label}),
			'field': new Element('input', {'id': field_id, 'type': 'text'})
			};
		if (readonly) { template['field'].set('readonly', 'readonly'); } else {
			template['field'].addEvent('click', function(e) { this.focus(); });
		}
		if (required) template['label'].set('html', '<strong>' + label + '*</strong>');
	} else if (type == 'password') {
		template = {
			'label': new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label}),
			'field': new Element('input', {'id': field_id, 'type': 'password'})
			};
		if (readonly) { template['field'].set('readonly', 'readonly'); } else {
			template['field'].addEvent('click', function(e) { this.focus(); });
		}
		if (required) template['label'].set('html', '<strong>' + label + '*</strong>');
	} else if ( (type == 'ul') || (type == 'li') ) {
		template = {'field': new Element(type, {'id': field_id, 'style': 'min-height: 1.2em'})};
		if ((label != undefined) && (label.length > 0)) template.label = new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label});
	} else if (type == 'checkbox') {
		template = {
				'label': new Element('input', { id: field_id, type: 'checkbox' }),
				'field': new Element('span', { id: field_id + '_label', html: label })
			};
		if (readonly) { template['label'].set('readonly', 'readonly'); template['label'].set('disabled', 'disabled'); }
		if (required) template['field'].set('html', '<strong>' + label + '*</strong>');
	} else if (type == 'button') {
		template = { 'field': new Element('input', {'id': field_id, 'type': 'button', 'value': label, 'style': 'cursor: pointer;' }) };
	} else if (type == 'hidden') {
		template = { 'field': new Element('input', {'id': field_id, 'type': 'hidden'}) };
	} else if (type == 'textarea') {
		template = {
			'label': new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label}),
			'field': new Element('textarea', {'id': field_id, 'rows': 30})
			};
		if (readonly) { template['field'].set('readonly', 'readonly'); } else {
			template['field'].addEvent('click', function(e) { this.focus(); });
		}
		if (required) template['label'].set('html', '<strong>' + label + '*</strong>');
	} else {
		template = {
			'label': new Element('span', {'id': field_id + '_label', 'class': 'label', 'html': label}),
			'field': new Element('span', {'id': field_id, 'class': 'text'})
			};
		if (required) template['label'].set('html', '<strong>' + label + '*</strong>');
	}
	
	if ($defined(fclass)) {
		if (template['field'].class == undefined) {
			template['field'].set('class', fclass);
		} else {
			template['field'].set('class', template['field'].get('class') + ' ' + fclass);
		}
	}
	
	if (lang != undefined) {
		template['field'].lang = lang;
	}
	
	if (width) { template['width'] = width + 'px'; }
	
	return template;
}

function createRecordField_New(fieldDef)
/* create a record field using the standard field definition
 *
 * fieldDef = {
 *   id: 'id',
 *   window_id: 'id',
 *   element: 'span',
 *   children: { 0: { fieldDef }, 1: { fieldDef } } OR function() to return an array of elements,
 *             for collections, specify "containers: true" to use the same syntax as first level record fields with wrapper divs
 *   label: '',
 *   value: '',
 *   mask: [true|false],
 *   enabled: [true|false],
 *   width: 100,
 *   properties: { class: 'class1 class2', href: ''[, etc... any valid html property w/value] },
 *   required: [true|false],
 *   help: 'text' (creates an 'info' icon with tooltip/hover text on the label element)
 *   onBlur: function(this) {},
 *   onChange: function(this) {},
 *   onClick: function(this) {},
 *   onEdit: function(this) {}, // not implemented at this time
 *   onFocus: function(this) {},
 *   onKeyDown: function(this) {},
 *   onKeyUp: function(this) {},
 *   onLoad: function(this) {},
 *   onUnload: function(this) {} // not implemented at this time
 *   }
 *
 * all field def items are optional
 *
 * - mask is only valid on span elements
 * - required appends an asterisk to labels and makes labels bold
 * - input elements are set to readonly -> false by default
 *
 * valid pseudo types (element = X):
 *   button        a standard input type=button
 *   checkbox      a standard input type=checkbox
 *   formbutton    an input type=button excepted floated right,
 *                 typical use case is for the window "save" or "cancel" button
 *   text          a textarea box
 *
 * returns an object with up to two elements:
 *   object = { label: {Element|false}, field: {Element|false} }
 *
 */
{
	// set defaults before processing input field definition
	var template = { 'label': false, 'field': false, 'float': false };
	var element = 'span';
	var original_element = false;
	var propChk = false;
	var required = false;
	
	// validate required ID field
	if (fieldDef['id'] == null) {
		fieldDef['id'] = randomID(5);
	} else if ($defined(fieldDef['window_id'])) {
		fieldDef['id'] = fieldDef['window_id'] + '_' + fieldDef['id'];
	}
	
	// check for 'required' flag
	if (fieldDef['required'] == true) required = true;
	
	// check for a label
	if (fieldDef['label'] != null) {
		template['label'] = new Element('span', { 'id': fieldDef['id'] + '_label', 'class': 'label', 'html': fieldDef['label'] });
		if (required) {
			template['label'].setStyle('font-weight', 'bold');
			template['label'].setProperty('html', fieldDef['label'] + '*');
		}
		if (fieldDef['help'] != null) {
			helpImg = new Element('img', { 'src': ema.base_url + '/download/info-16x16.png', width: 16, height: 16, title: fieldDef['help'], styles: { cursor: 'help', float: 'right', 'margin': '1px 0 0 5px' } });
			helpImg.inject(template['label']);
		}
	}
	
	// check for element type
	if (fieldDef['element'] != null) { element = fieldDef['element'].toLowerCase(); }
	original_element = element;
	
	// set the element for pseudo-types
	switch(element) {
		case 'button': element = 'input'; break;
		case 'checkbox': element = 'input'; break;
		case 'formbutton': element = 'input'; break;
		case 'text': element = 'textarea'; break;
	}
	
	// build the field element
	template['field'] = new Element(element, { 'id': fieldDef['id'] });
	if (fieldDef['window_id'] != null) template['field'].window_id(fieldDef['window_id']);
	
	// add any defined properties
	if (fieldDef['properties'] != null) {
		propChk = true;
		for (var i in fieldDef['properties']) {
			template['field'].set(i, fieldDef['properties'][i]);
		}
	}
	
	// check for enabled flag
	if ($defined(fieldDef['enabled'])) {
		template['field'].disabled = !!fieldDef['enabled'];
    template['field'].setAttribute('disabled', !!fieldDef['enabled']);
	}
	
	// set default options depending on the type
	switch (original_element) {
		case 'input':
			if ((!propChk)||(propChk && (fieldDef['properties']['type'] == null))) template['field'].set('type', 'text');
			if ((!propChk)||(propChk && (fieldDef['properties']['readonly'] == null))) template['field'].set('readonly', false);
			if (fieldDef['value'] != null) { template['field'].set('value', fieldDef['value']); }
			break;
		case 'ul':
			if ((!propChk)||(propChk && (fieldDef['properties']['class'] == null))) template['field'].set('class', 'list');
			if ((!propChk)||(propChk && (fieldDef['properties']['style'] == null))) template['field'].setStyle('min-height', '22px');
			break;
		case 'span':
			if ((!propChk)||(propChk && (fieldDef['properties']['class'] == null))) template['field'].set('class', 'text');
			if (fieldDef['value'] != null) { template['field'].set('html', fieldDef['value']); }
			if (fieldDef['mask'] == true) {
				template['field'].addClass('masked');
				template['field'].addEvent('click', function(e){
					this.removeClass('masked');
					this.removeEvents();
				});
			}
			break;
		case 'button':
			template['label'] = false;
			template['field'].setProperties({'value': fieldDef['label'], 'type': 'button'});
			break;
		case 'formbutton':
			template['label'] = false;
			template['field'].setProperties({'value': fieldDef['label'], 'type': 'button'});
			template['float'] = 'right';
			break;
		case 'checkbox':
			template['field'].setProperties({'type': 'checkbox', 'style': 'margin-right: -5px;'});
			if (fieldDef['value'] != null) { template['field'].set('checked', fieldDef['value']); }
			template['label'].set('class', 'inline_label');
			break;
		default:
			if (fieldDef['value'] != null) { template['field'].set('html', fieldDef['value']); }
			break;
	}
	
	// set the width if necessary
	if (fieldDef['width'] != null) {
		if (template['label'] != false) { template['label'].setStyle('width', fieldDef['width']); }
		template['field'].setStyle('width', fieldDef['width']);
	}
	
	// append any children
	if (fieldDef['children'] != null) {
		// 'children' can be a function that returns elements
		if (isF(fieldDef['children'])) {
			// children provided by a function
			var list = fieldDef['children'](fieldDef['window_id']);
			var count = list.length;
			for (var i=0;i<count;i++) list[i].inject(template['field']);
		} else {
			// children is a collection of field definitions
			count = countCollection(fieldDef['children']);
			if (fieldDef['children']['containers'] != null) count--;
			for (var i=0;i<count;i++) {
				if ($defined(fieldDef['window_id'])) fieldDef['children'][i]['window_id'] = fieldDef['window_id'];
				var child = createRecordField_New(fieldDef['children'][i]);
				if (fieldDef['children']['containers'] && (!(fieldDef['children'][i].container == false))) {
					var container = new Element('div', { class: 'container' });
					if ($defined(fieldDef['children'][i].break)) container.setStyle('clear', 'left');
					if (child['field'] != false) child['field'].inject(container);
					if (child['label'] != false) child['label'].inject(container);
					container.inject(template['field']);
				} else {
					if (child['field'] != false) child['field'].inject(template['field']);
					if (child['label'] != false) child['label'].inject(template['field']);
				}
			}
		}
	}
	
	// set optional events
	if ($defined(fieldDef.onBlur)) { template['field'].addEvent('blur', fieldDef.onBlur); }
	if ($defined(fieldDef.onChange)) { template['field'].addEvent('change', fieldDef.onChange); }
	if ($defined(fieldDef.onClick)) { template['field'].addEvent('click', fieldDef.onClick); }
	if ($defined(fieldDef.onFocus)) { template['field'].addEvent('click', fieldDef.onFocus); }
	if ($defined(fieldDef.onKeyDown)) { template['field'].addEvent('keydown', fieldDef.onKeyDown); }
	if ($defined(fieldDef.onKeyUp)) { template['field'].addEvent('keyup', fieldDef.onKeyUp); }
	
	return template;
}

function createTable(args)
/* create a table using the provided arguments object
 *
 * required:
 *  data         array of arrays, first level arrays should be the rows
 *
 * optional:
 *  header       array of columns matching column assignments from data (above)
 *  id           id to assign to the table element
 *
 * to add a class to a particular cell, prepend the following syntax to the
 *  cell value: '[[class=xxxx]]'
 *
 */
{
	var t = new Element('table', { 'class': 'colorList' });
	if (args.id !== undefined) t.set('id', args.id);
	var r = new Element('tr');
	var columnList = {};
	
	// generate header
	if (args.header !== undefined) {
		for (var i=0;i<args.header.length;i++) {
			var h = new Element('th', { html: args.header[i] });
			columnList[args.header[i]] = '';
			h.inject(r);
		}
	}
	
	r.inject(t);
	var c = 'even';
	
	// set the class match regex
	var re = new RegExp(/\[\[(\w*)=(\w*)\]\](.*)/i);
	
	// add data
	for (var i=0;i<args.data.length;i++) {
		var r = new Element('tr', { 'class': c });
		if (args.header != undefined) {
			for (var j=0;j<args.header.length;j++) {
				var h = new Element('td');
				if (is_array(args.data[i][j])) {
					for (k=0;k<args.data[i][j].length;k++) {
						args.data[i][j][k].inject(h);
					}
				} else {
					var value = args.data[i][j];
					if ((value === undefined)||(value.length == 0)) value = '&nbsp;';
					// check for embedded class
					var m = re.exec(value);
					if (m != null) {
						// found a match
						value = m[3];
						h.set(m[1].toLowerCase(), m[2]);
					}
					h.set('html', value);
				}
				h.inject(r);
			}
		} else {
			for (var j in columnList) {
				value = args.data[i][j];
				if ((value === undefined)||(value.length === 0)) value = '&nbsp;';
				var h = new Element('td', { html: value });
				h.inject(r);
			}
		}
		r.inject(t);
		if (c == 'even') { c = 'odd'; } else { c = 'even'; }
	}
	
	return t;
}

function createWaitingOverlay(ele)
/* create a partially transparent overlay over the provided element with an indicator gif
 *  in the middle
 *
 */
{
	var size = ele.getScrollSize();
	var win = new Element('div', {
		'id': 'waiting',
		'class': 'waiting',
		'html': indicator(null, true),
		'styles': {
			'background': 'transparent',
			'display': 'block',
			'width': '16px'
			}
		});
	win.setPosition({x: ((size.x / 2) - 8), y: ((size.y / 2) - 8)});
	win.set('opacity', 0);
	
	createOverlay(ele, 'background');
	win.inject(ele);
	win.fade(1);
}

function createWindow(title, field_defs, edit_mode)
/* create a popup window
 *
 * requires a title and field definition array
 *
 * optional edit_mode = true to convert spans to inputs
 *
 * this function supports hooks
 *
 */
{
	var body = $('body');
	var size = body.getSize();
	var scroll = body.getScroll();
	var scrollsize = body.getScrollSize();
	
	// set the mode for this window
	if (edit_mode != true) { edit_mode = false; }
	
	// validate input
	if (field_defs == undefined) field_defs = { }
	
	var win = new Element('div', {
		'id': 'window',
		'class': 'popup window',
		'styles': { 'position': 'absolute', 'opacity': 0 },
		'html': '<h1 id="windowTitle">' + title + '<div id="btn_cancel">&nbsp;</div></h1>' +
						'<input type="hidden" name="dir_id" id="dir_id" value="" />'
		});
	
	// create the record fields
	var br = new Element('br', {'class': 'clear'});
	
	// createRecordField(label, field_id, type, width, readonly, required, class, lang)
	
	for (i in field_defs){
		// THIS NEEDS TO BE RECODED VERY VERY BADLY!
		if (field_defs[i].type) { t = field_defs[i].type; } else { t = 'span'; }
		if (field_defs[i].lang) { lang = field_defs[i].lang; } else { lang = undefined; }
		if ((t == 'span') && (edit_mode)) { t = 'input'; }
		if (edit_mode) { m = false; } else { m = true; }
		if (field_defs[i].required) { r = true; } else { r = false; }
		var field = createRecordField(field_defs[i].label, i, t, field_defs[i].width, m, r, null, lang);
		if (field_defs[i].children){
			for (j in field_defs[i].children){
				if (field_defs[i].children[j].lang) { var lang = field_defs[i].children[j].lang; } else { lang = null; }
				if (field_defs[i].children[j].required) { rj = true; } else { rj = false; }
				if (field_defs[i].children[j].type) { tj = field_defs[i].children[j].type; } else { tj = 'p'; }
				if (field_defs[i].children[j]['class']) { var tc = field_defs[i].children[j]['class']; } else { var tc = ''; }
				var child = createRecordField(field_defs[i].children[j].label, j, tj, field_defs[i].children[j].width, m, rj, tc, lang);
				if (field_defs[i].children[j].value != undefined) child['field'].set('html', field_defs[i].children[j].value);
				child['field'].inject(field['field']);
			}
		}
		var container = new Element('div', {'class': 'container'});
		field['field'].inject(container);
		if (field['label']) { field['label'].inject(container); }
		if (field['width']) { container.setStyle('width', field['width']); }
		container.inject(win);
		}
	
	br.inject(win);
	
	win.setPosition({x: -10000, y: -10000});
	createOverlay(body);
	win.inject(body);
	
	// add window events
	$('window').makeDraggable({'handle': $('windowTitle')});
	$('window').addEvent('keydown', function(event){
		 if (event.key=='w' && event.control) { event.preventDefault(); $$('#overlay', '#window').destroy(); }
	});
	
	// add button events
	$('btn_cancel').addEvent('click', function(){destroyWindow();});
	
	// this function supports hooks
	hook();
	
	// align and fade in the new window
	centerWindow(win);
	win.fade(1);
	
	return true;
}

function createWindow_New(title, field_defs, id, rID)
/* createa a popup window (new format)
 *
 * requires a title
 *
 * optional field definition object containing
 *  a collection of values to pass to createRecordField_New
 *
 * returns the window id after injecting into the document
 *
 */
{
	// establish the container element
	var body = $('body');
	
	// obtain a new id or use the provided id if it is available
	id = window_id_register(id);
	
	// if no id (or false if the id was already in use), exit
	if (id === false) return false;
	
	// validate the title
	if (! $defined(title)) title = 'New Window';
	
	var win = new Element('div', {
		'id': id,
		'class': 'window',
		'styles': { 'position': 'absolute', 'opacity': 0 },
		'html': '<h1 id="' + id + '_windowTitle">' + title + '<div id="' + id + '_btn_close">&nbsp;</div></h1>'
	});
	
	// load the empty window
	win.setPosition({x: -10000, y: -10000});
	win.inject(body);
	
	// optionally set a record id
	if ($defined(rID)) win.record_id(rID);
	
	// copy the field defs so as to not alter the original
	field_defs = copy(field_defs);
	
	// process record fields with new function
	for (var i=0;i<field_defs.length;i++) {
		var container = new Element('div', {'class': 'container'});
		if ($defined(field_defs[i].break)) container.setStyle('clear', 'left');
		field_defs[i]['window_id'] = id;
		var template = createRecordField_New(field_defs[i]);
		if (template['field'] != false) template['field'].inject(container);
		if (template['label'] != false) template['label'].inject(container);
		if (template['float'] != false) container.setStyle('float', template['float']);
		container.inject(win);
	}
	
	// insert a line break to clear any floated elements and maintain the
	//  visual consistency of the window
	var br = new Element('br', {'class': 'clear'});
	br.inject(win);
	
	//createOverlay(body);
	centerWindow(win);
	win.fade(1);
	
	// add events
	win.makeDraggable({'handle': $(id + '_windowTitle')});
	$(id + '_btn_close').addEvent('click', function(e){ window_id_unregister(this.window_id()); $(this.window_id()).destroy(); });
	$(id + '_btn_close').window_id(id);
	
	// execute any functions tied to record fields (NEW)
	for (var i=0;i<field_defs.length;i++){
		if ($defined(field_defs[i].onLoad)) { var e = field_defs[i].onLoad.bind($(field_defs[i].id)); e(id); }
	}
	
	return id;
}

function closeAllWindows()
/* close all open windows
 *
 */
{
	for (var i=0;i<windowID.length;i++) {
		$(windowID[i]).destroy();
	}
	windowID = new Array();
}

function closeWindow(wID)
/* close the window with id wID
 *
 */
{
	for (var i=0;i<windowID.length;i++) {
		if (windowID[i] == wID) {
			hook();
			$(wID).destroy();
			window_id_unregister(wID);
			return true;
		}
	}
}

function destroyWindow(win)
/* destroy a window
 *
 * win should be the id of the window to destroy
 *
 * this function is the preferred way to close a window
 *
 */
{
	if ((win == undefined)||(win.length == 0)) win = 'window';
	win = '#' + win;
	// this function supports hooks
	hook();
	try { $$('#overlay', win).destroy(); } catch(e){}
	return true;
}

function enqueue(args, start_immediately)
/* enqueue the provided request
 *
 * args is an object:
 *   required keys:
 *     url = url for the request
 *   optional keys:
 *     onSuccess => function
 *     onFailure => function
 *     silent => true or false (defaults to false)
 *     uuid => unique id number to be returned to onSuccess/onFailure functions
 *             this id number has no relevance to the request process but
 *             may be useful for the functions recieving the parsed data.
 *             For example, I might provide a uuid of the window or field the data
 *             is to be loaded into.
 *
 * if start_immediately is true begins processing the queue
 *
 */
{
	newRequest = {'url': args.url, 'uuid': false};
	if (args.silent === true) newRequest.silent = true;
	if (args.silent === false) newRequest.silent = false;
	if ($defined(args.onSuccess)) {
		newRequest.onSuccess = args.onSuccess;
	} else {
		newRequest.onSuccess = function(x){parseXmlResponse(x);}
	}
	if ($defined(args.onFailure)) {
		newRequest.onFailure = args.onFailure;
	} else {
		newRequest.onFailure = function(){ hideWaiting(); showNotice('XML Request Error'); }
	}
	if ($defined(args.uuid)) newRequest.uuid = args.uuid;
	var cont = true;
	// since the processRequestQueue function uses the request URL to determine
	//  where to send the response, only update an existing URL for this request
	//  if one is already set.
	for (var i=0;i<requestQueue.length;i++) {
		if (requestQueue[i].url == args.url) {
			if ($defined(args.onSuccess)) requestQueue[i].onSuccess = args.onSuccess;
			if (args.uuid === false) requestQueue[i].uuid = newRequest.uuid;
			cont = false;
			break;
		}
	}
	if (cont) requestQueue.push(newRequest);
	if (start_immediately === true) return processRequestQueue();
	return true;
}

function getFormData(window_id, form_def)
/* given the field definition, return a collection of fields::values from the form
 *
 * - this function is only compatibile with createWindow_New/createRecordField_New form defitions
 *     with all IDs provided
 * - skips fields of type 'button' and 'formbutton'
 * - does *NOT* process child fields at this time
 *
 * will validate that required fields are filled in (but not the content of them)
 *
 * returns the collection on success or false on error
 *
 */
{
	// verify the window exists
	try { var w = $(window_id); } catch(e){ showNotice('A form error occurred: 0'); return false; }
	// initialize the return collection
	var coll = {};
	// check and load the field values
	for (var i=0;i<form_def.length;i++) {
		if ((form_def[i].element == 'button')||(form_def[i].element == 'formbutton')) continue;
		try { var f = $(window_id + '_' + form_def[i].id); } catch(e){ showNotice('A form error occurred: 1'); return false; }
		if (!$defined(f)) { showNotice('A form error occurred: 2: ' + window_id + ',' + i + ',' + form_def[i].id); return false; }
		if ((form_def[i].required)&&(f.get('value').length == 0)) {
			showNotice('Please provide a value for the `' + form_def[i].label + '` field');
			return false;
		}
		if (form_def[i].element == 'checkbox') {
			if (f.checked) { coll[form_def[i].id] = '1'; } else { coll[form_def[i].id] = '0'; }
		} else {
			coll[form_def[i].id] = f.get('value');
		}
	}
	return coll;
}

function is_array(i)
/* return true if provided object is an array
 *
 * source: http://www.andrewpeace.com/javascript-is-array.html
 * retrieved: may-15-2010, ws
 *
 */
{
	return typeof(i)=='object' && (i instanceof Array);
}

function is_function(f)
/* return true if the provided object is a function
 *
 * source: the internet (i.e. I did not write this)
 *
 */
{
	try { return /^\s*\bfunction\b/.test(f); } catch(x) { return false ; }
}

function is_string(i)
/* return true if provided object is a string
 *
 * source: http://andrewpeace.com/javascript-is-string.html
 * retrieved: dec-04-2011, ws
 */
{
	return typeof(i)=='string';
}

function isF(f) /* alias for is_function */ { return is_function(f); }

function hideNotice()
{
	clearTimeout(timer);
	try { $$('#notice', '#background', '#background2', '#background3', '#waiting').destroy(); } catch(e){}
}

function hideWaiting()
/* hide the waiting dialogue box
 *
 */
{
	try {
		clearTimeout(waitTimer);
		$$('#background', '#background2', '#background3', '#waiting').destroy();
	} catch(e) { alert('Error hiding waiting dialogue! ' + e); }
	return true;
}

function hook()
/* execute the hooks for the calling function
 *
 */
{
	var f = hook.caller.name;
	if (! is_array(hookRegistry[f])) { return false; }
	for (var i=0;i<hookRegistry[f].length;i++) {
		// execute the registered function
		hookRegistry[f][i]();
	}
}

function indicator(id, html)
/* creates and returns a new indicator element
 *
 * optionally applies the requested id
 *
 * optionally returns html only
 *
 */
{
	if ($defined(html)&&(html==true)) {
		if ($defined(id)) {
			return '<img src="' + ema.download + 'indicator.gif" width="16" height="16" alt="..." id="' + id + '" />';
		} else {
			return '<img src="' + ema.download + 'indicator.gif" width="16" height="16" alt="..." />';
		}
	}
	var i = new Element('img', { src: ema.download + 'indicator.gif', width: 16, height: 16, alt: '...' });
	if ($defined(id)) i.set('id', id);
	return i;
}

function parseXmlResponse(xmlDoc, error_return, container_key)
/* handle standard xml response
 *
 * if there is an error, return false and notify the client
 *
 * otherwise return the response element
 *
 * by default looks for outer 'response' tag, use container_key to specify something else
 *
 */
{
	try {
		// validate input
		if (!$defined(xmlDoc)) { showNotice('General failure in XML response[1].'); return false; }
		if (xmlDoc == false) { showNotice('General failure in XML response[2].'); return false; }
		if (error_return != true) error_return = false;
		if (!$defined(container_key)) container_key = 'response';
		// process input xml
		if (xmlDoc.filter) {
			if (xmlDoc.filter('error')[0]) {
				if (error_return) {
					var x = xmlDoc.filter('error')[0].textContent;
				} else {
					showNotice(xmlDoc.filter('error')[0].textContent);
					var x = false;
				}
			} else {
				if (xmlDoc.getElements == undefined) showNotice('Undefined getElements 1');
				var x = xmlDoc.filter(container_key)[0];
			}
		} else {
			if (xmlDoc.get('tag') == 'error') {
				if (error_return) {
					var x = xmlDoc.getElement('message').get('html');
				} else {
					showNotice(xmlDoc.getElement('message').get('html'));
					var x = false;
				}
			} else {
				if (xmlDoc.getElements == undefined) showNotice('Undefined getElements 2');
				var x = xmlDoc;
			}
		}
		if ($defined(x)) return x;
		//showNotice('General failure in XML response[4]');
		return false;
	} catch(e) {
		showNotice('General failure in XML response[3].');
		return false;
	}
}

function processRequestQueue(uuid)
/* process the pending request queue
 *
 * uuid should never be provided by the client, only by
 *   the onComplete function set by this function when it
 *   sends a request
 *
 */
{
	// check for an error
	try {
		if ($defined(uuid)) {
			r = responseQueue[uuid].r;
			if (r.xml.get('tag') == 'error') {
				if (requestQueue.silent != true) {
					showWaiting('<strong>Warning:</strong> Received an error on item ' + (requestQueue.length + 1) +
						'.<br /><em>' + r.xml.getElement('message').get('html') + '</em>');
					}
				setTimeout(processRequestQueue, 250);
				requestQueue.active = false;
				delete responseQueue[uuid];
				return true;
			}
			if ($defined(responseQueue[uuid].s)) { responseQueue[uuid].s(r.xml, responseQueue[uuid].uuid); }
			delete responseQueue[uuid];
		}
	} catch(e){}
	
	// when finished run the onComplete function once
	if (requestQueue.length == 0) {
		if (requestQueue.silent != true) { hideWaiting(); }
		if (requestQueue.onComplete != null) {
			requestQueue.onComplete();
			requestQueue.onComplete = null;
			requestQueue.nextRequest = null;
			requestQueue.silent = false;
		}
		requestQueue.active = false;
		requestQueue.active_global = false;
		return true;
	}
	
	if (requestQueue.silent || requestQueue[0].silent) { silent = true; } else { silent = false; }
	if ((!$defined(requestQueue[0].silent))||(requestQueue[0].silent == false)) { silent = false; }
	if (! silent) { showWaiting('Processing ' + requestQueue.length + ' Pending Requests<br />Please Wait'); }
	
	// process the first request
	var myRequest = new Request.HTML({method: 'get', url: requestQueue[0]['url'], onSuccess: function(){
		processRequestQueue.delay(500, null, this.q);
		}});
	if ($defined(requestQueue[0].onFailure)) myRequest.onFailure = requestQueue[0].onFailure;
	// renumerate the list to remove the first item
	tmp = Array();
	for (var i=1;i<requestQueue.length;i++) { tmp[i-1] = requestQueue[i]; }
	tmp.onComplete = requestQueue.onComplete;
	tmp.silent = silent;
	responseQueue[myRequest.q] = {r: myRequest, s: requestQueue[0].onSuccess, uuid: requestQueue[0].uuid};
	requestQueue = tmp;
	myRequest.send();
	requestQueue.active = false;
}

function registerHook(function_name, hook_function)
/* accepts the name of a function that supports hooks and an actual
 *  javascript function to execute
 *
 */
{
	if (! is_array(hookRegistry[function_name])) hookRegistry[function_name] = new Array();
	hookRegistry[function_name][hookRegistry[function_name].length] = hook_function;
}

function showNotice(msg, duration)
{
	if (duration == null) duration = 2500;
	var body = $('body');
	var size = body.getSize();
	var scroll = body.getScroll();
	var win = new Element('div', {
		'id': 'notice',
		'class': 'notice',
		'html': '<p>' + msg + '</p>',
		'styles': {
			'display': 'block',
			'width': '300px'
			}
		});
	win.setPosition({x: -10000, y: -10000});
	win.set('opacity', 0);
	win.addEvent('click', function(e) { clearTimeout(timer); try { $('background3').destroy(); } catch(e) {} this.destroy(); });
	createOverlay(body, 'background3');
	win.inject(body);
	var winsize = win.getSize();
	win.setPosition({x: ((size.x / 2) - (winsize.x / 2)), y: ((size.y / 2) - (winsize.y / 2) + scroll.y - 50)});
	win.fade(1);
	timer = setTimeout(hideNotice, duration);
}

function showWaiting(msg)
{
	try { return $('waiting').getElement('p').set('html', msg); } catch(e){}
	try { $$('#waiting #background3').destroy(); } catch(e){}
	var body = $('body');
	var size = body.getSize();
	var scroll = body.getScroll();
	var win = new Element('div', {
		'id': 'waiting',
		'class': 'waiting',
		'html': '<input type="button" name="reload" value="[ click here to cancel and reload this page ]" ' +
					'onclick="window.location.reload(false);" />' + indicator(null, true) + '<p>' + msg + '</p>',
		'styles': {
			'border': '1px solid #ccc',
			'border-radius': '5px',
			'-moz-border-radius': '5px',
			'display': 'block',
			'width': '300px',
			'z-index': '10'
			}
		});
	win.set('opacity', 0);
	createOverlay(body, 'background3');
	win.inject(body);
	centerWindow(win);
	win.fade(1);
}

function url(path)
/* return a relative url
 *
 */
{
	if (!$defined(path)) path = '';
	if (path.length > 0) { if (path.substring(0, 1) == '/') path = path.substring(1); }
	if (path.length > 0) { if (path.substring(path.length - 1, 1) == '/') path = path.substring(0, path.length - 1); }
	if (path.length > 0) return ema.base_url + path;
	return ema.base_url;
}

function window_id_register(id)
/* register a new window id
 *
 * if no id is provided one will be generated
 *
 * uses the global windowID array
 *
 * returns the id
 *
 */
{
	// if the id already exists, return false
	if (windowID.indexOf(id) != -1) return false;
	// generate a new id if the provided id is null
	if (! $defined(id)) id = 'win_' + randomID(5);
	while (windowID.indexOf(id) != -1) { id = 'win_' + randomID(5); }
	// id is set and unique, use it
	windowID[windowID.length] = id;
	return id;
}

function window_id_unregister(id)
/* release the window id
 *
 * uses the global windowID array
 *
 */
{
	// it doesn't make sense to unregister a null id,
	//  but technically it meets the requirements of this function
	if (! $defined(id)) return true;
	// unregister the id
	windowID.erase(id);
	return true;
}

/**
 * XML String Encode/Decode
 * Source: http://dracoblue.net/c/mootools/
 * Retrieved: Dec-03-2010 ws
 *
 */

function xml_decode(string) {
	return string.replace(/(&quot;|&lt;|&gt;|&amp;)/g,
		function(str, item) {
		return escaped_one_to_xml_special_map[item];
	});
}

function xml_encode(string) {
	return string.replace(/([\&"<>])/g, function(str, item) {
		return xml_special_to_escaped_one_map[item];
	});
}

/**
 * Extend the Request.HTML object
 *
 * revision 1.0.0, dec-09-2010
 * w. strucke
 *
 */

Request.HTML.implement({
	initialize: function(options){
		this.parent(options);
		this.xml = '';
		this.q = uuid();
	},
	onSuccess: function(responseText, responseXML){
		this.xml = responseXML;
		this.parent(responseText, responseXML);
	}
});


/**
 * Record ID functions
 *
 * revision 1.0.0, dec-07-2010
 * w. strucke
 *
 */

Element.implement('record_id', function(x){
	if (x !== undefined) {
		if (this.uuid == '') this.uuid = uuid();
		recordIDMap[this.uuid] = x;
	}
	return recordIDMap[this.uuid];
});

Element.implement('window_id', function(x){
	if (x !== undefined) {
		if (this.uuid == '') this.uuid = uuid();
		windowIDMap[this.uuid] = x;
	}
	return windowIDMap[this.uuid];
});

/**
 * GUID/UUID Functions
 *
 * http://stackoverflow.com/questions/105034/how-to-create-a-guid-uuid-in-javascript
 *
 * retrieved dec-07-2010 ws
 *
 */

function uuid() {
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
		var r = Math.random()*16|0, v = c == 'x' ? r : (r&0x3|0x8);
		return v.toString(16);
	}).toUpperCase();
}

Element.prototype.uuid = '';

/**
 * String Trim Functions
 *
 * http://www.somacon.com/p355.php
 *
 * retrieved may-31-2010, ws
 *
 */

String.prototype.trim = function() {
	return this.replace(/^\s+|\s+$/g,"");
}
String.prototype.ltrim = function() {
	return this.replace(/^\s+/,"");
}
String.prototype.rtrim = function() {
	return this.replace(/\s+$/,"");
}

/**
 * String Case Function
 *
 * http://stackoverflow.com/questions/1026069/capitalize-the-first-letter-of-string-in-javascript
 *
 * retrieved nov-26-2011, ws
 *
 */

String.prototype.toNaturalCase = function() {
	return this.toLowerCase().replace(/^\w/, function($0) { return $0.toUpperCase(); })
}

/**
 * drag.ghost.js - Ghosting draggable extension for Drag.Move
 * @version 1.01
 * 
 * by MonkeyPhysics.com
 *
 * Source/Documentation available at:
 * http://www.monkeyphysics.com/mootools/script/1/dragghost
 * 
 * Some Rights Reserved
 * http://creativecommons.org/licenses/by-sa/3.0/
 * 
 */

Drag.Ghost = new Class({
	
	Extends: Drag.Move,
	
	options: { opacity: 0.65 },
	
	start: function(event) {
		this.ghost();
		this.parent(event);
	},
	
	cancel: function(event) {
		if (event) this.deghost();
		this.parent(event);
	},
	
	stop: function(event) {
		this.deghost();
		this.parent(event);
	},
	
	ghost: function() {
		this.element = this.element.clone()
			.setStyles({
				'opacity': this.options.opacity,
				'position': 'absolute',
				'top': this.element.getCoordinates()['top'],
				'left': this.element.getCoordinates()['left'],
				'z-index': 4
			})
			.inject(document.body)
			.store('parent', this.element);
	},
	
	deghost: function() {
		var e = this.element.retrieve('parent');
		this.element.destroy();
		this.element = e;
	}
});

Element.implement({
	makeGhostDraggable: function(options) {
		return new Drag.Ghost(this, options);
	}
});

// number functions :: http://www.sitepoint.com/forums/showthread.php?t=318819

function getRandomNumber(range) { return Math.floor(Math.random() * range); }
function getRandomChar() {
	var chars = "abcdefghijklmnopqurstuvwxyzABCDEFGHIJKLMNOPQURSTUVWXYZ";
	return chars.substr( getRandomNumber(52), 1 );
}
function randomID(size) {
	var str = "";
	for(var i = 0; i < size; i++)
	{
		str += getRandomChar();
	}
	return str;
}

// base64 encode/decode
// source: http://phpjs.org/functions/base64_decode:357
//         http://phpjs.org/functions/base64_encode:358
//         http://phpjs.org/functions/utf8_decode:576
//         http://phpjs.org/functions/utf8_encode:577
// retrieved: oct-30-2011 ws

function base64_decode(data) {
    // Decodes string using MIME base64 algorithm  
    // 
    // version: 1109.2015
    // discuss at: http://phpjs.org/functions/base64_decode    // +   original by: Tyler Akins (http://rumkin.com)
    // +   improved by: Thunder.m
    // +      input by: Aman Gupta
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Onno Marsman    // +   bugfixed by: Pellentesque Malesuada
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // -    depends on: utf8_decode    // *     example 1: base64_decode('S2V2aW4gdmFuIFpvbm5ldmVsZA==');
    // *     returns 1: 'Kevin van Zonneveld'
    // mozilla has this native
    // - but breaks in 2.0.0.12!
    //if (typeof this.window['btoa'] == 'function') {    //    return btoa(data);
    //}
    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0, ac = 0, dec = "", tmp_arr = [];
		
    if (!data) { return data; }
		
    data += '';
 
    do { // unpack four hexets into three octets using index points in b64
        h1 = b64.indexOf(data.charAt(i++));
        h2 = b64.indexOf(data.charAt(i++));
        h3 = b64.indexOf(data.charAt(i++));
        h4 = b64.indexOf(data.charAt(i++));
         bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;
 
        o1 = bits >> 16 & 0xff;
        o2 = bits >> 8 & 0xff;
        o3 = bits & 0xff; 
        if (h3 == 64) {
            tmp_arr[ac++] = String.fromCharCode(o1);
        } else if (h4 == 64) {
            tmp_arr[ac++] = String.fromCharCode(o1, o2);        } else {
            tmp_arr[ac++] = String.fromCharCode(o1, o2, o3);
        }
    } while (i < data.length);
     dec = tmp_arr.join('');
    dec = utf8_decode(dec);
    
    return dec;
}

function base64_encode(data) {
    // Encodes string using MIME base64 algorithm  
    // 
    // version: 1109.2015
    // discuss at: http://phpjs.org/functions/base64_encode    // +   original by: Tyler Akins (http://rumkin.com)
    // +   improved by: Bayron Guevara
    // +   improved by: Thunder.m
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   bugfixed by: Pellentesque Malesuada    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Rafa? Kukawski (http://kukawski.pl)
    // -    depends on: utf8_encode
    // *     example 1: base64_encode('Kevin van Zonneveld');
    // *     returns 1: 'S2V2aW4gdmFuIFpvbm5ldmVsZA=='    // mozilla has this native
    // - but breaks in 2.0.0.12!
    //if (typeof this.window['atob'] == 'function') {
    //    return atob(data);
    //}
    var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    var o1, o2, o3, h1, h2, h3, h4, bits, i = 0, ac = 0, enc = "", tmp_arr = [];
    if (!data) { return data; }
    data = utf8_encode(data + '');
		
    do { // pack three octets into four hexets
        o1 = data.charCodeAt(i++);
        o2 = data.charCodeAt(i++);
        o3 = data.charCodeAt(i++);
 				
        bits = o1 << 16 | o2 << 8 | o3;
 				
        h1 = bits >> 18 & 0x3f;        h2 = bits >> 12 & 0x3f;
        h3 = bits >> 6 & 0x3f;
        h4 = bits & 0x3f;
 
        // use hexets to index into b64, and append result to encoded string
        tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
    } while (i < data.length);
		
    enc = tmp_arr.join('');
    var r = data.length % 3;
    
    return (r ? enc.slice(0, r - 3) : enc) + '==='.slice(r || 3);
}

function utf8_decode(str_data) {
    // Converts a UTF-8 encoded string to ISO-8859-1  
    // 
    // version: 1109.2015
    // discuss at: http://phpjs.org/functions/utf8_decode    // +   original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // +      input by: Aman Gupta
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Norman "zEh" Fuchs
    // +   bugfixed by: hitwork    // +   bugfixed by: Onno Marsman
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // *     example 1: utf8_decode('Kevin van Zonneveld');
    // *     returns 1: 'Kevin van Zonneveld'
    var tmp_arr = [], i = 0, ac = 0, c1 = 0, c2 = 0, c3 = 0;
		
    str_data += '';
		
    while (i < str_data.length) {
    		c1 = str_data.charCodeAt(i);
        if (c1 < 128) {
            tmp_arr[ac++] = String.fromCharCode(c1);
            i++;
        } else if (c1 > 191 && c1 < 224) {
        		c2 = str_data.charCodeAt(i + 1);
            tmp_arr[ac++] = String.fromCharCode(((c1 & 31) << 6) | (c2 & 63));
            i += 2;
        } else {
            c2 = str_data.charCodeAt(i + 1);
            c3 = str_data.charCodeAt(i + 2);
            tmp_arr[ac++] = String.fromCharCode(((c1 & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
            i += 3;
        }
    } 
    return tmp_arr.join('');
}

function utf8_encode(argString) {
    // Encodes an ISO-8859-1 string to UTF-8  
    // 
    // version: 1109.2015
    // discuss at: http://phpjs.org/functions/utf8_encode    // +   original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: sowberry
    // +    tweaked by: Jack
    // +   bugfixed by: Onno Marsman    // +   improved by: Yves Sucaet
    // +   bugfixed by: Onno Marsman
    // +   bugfixed by: Ulrich
    // +   bugfixed by: Rafal Kukawski
    // *     example 1: utf8_encode('Kevin van Zonneveld');    // *     returns 1: 'Kevin van Zonneveld'
    if (argString === null || typeof argString === "undefined") {
        return "";
    }
    var string = (argString + ''); // .replace(/\r\n/g, "\n").replace(/\r/g, "\n");
    var utftext = "", start, end, stringl = 0;
 
    start = end = 0;    stringl = string.length;
    for (var n = 0; n < stringl; n++) {
        var c1 = string.charCodeAt(n);
        var enc = null;
         if (c1 < 128) {
            end++;
        } else if (c1 > 127 && c1 < 2048) {
            enc = String.fromCharCode((c1 >> 6) | 192) + String.fromCharCode((c1 & 63) | 128);
        } else {
        	enc = String.fromCharCode((c1 >> 12) | 224) + String.fromCharCode(((c1 >> 6) & 63) | 128) + String.fromCharCode((c1 & 63) | 128);
        }
        if (enc !== null) {
            if (end > start) { utftext += string.slice(start, end); }
            utftext += enc;
            start = end = n + 1;
        }
    } 
    if (end > start) {
        utftext += string.slice(start, stringl);
    }
     return utftext;
}

// LEGACY FUNCTIONS

function Left(str, n)
/***
	IN: str - the string we are LEFTing
			n - the number of characters we want to return

	RETVAL: n characters from the left side of the string
	SOURCE: http://www.4guysfromrolla.com/webtech/code/Left.shtml
***/
{
	if (n <= 0)     // Invalid bound, return blank string
		return "";
	else if (n > String(str).length)   // Invalid bound, return
		return str;                // entire string
	else // Valid bound, return appropriate substring
		return String(str).substring(0,n);
}

function Mid(str, startPos, n)
/***
	IN:	str - the string we are pulling characters from
			startPos - the start position in the string
			n - the number of characters to extract
	RETVAL: l characters from the startPos position
***/
{
	if (n <= 0)     // Invalid bound, return blank string
		return "";
	else if ((startPos + n) > String(str).length)   // Invalid bound, return
		return str;        
	else // Valid bound, return appropriate substring
		return Left(Right(str, str.length - startPos), n);
}

function Right(str, n)
/***
	IN: str - the string we are RIGHTing
			n - the number of characters we want to return

	RETVAL: n characters from the right side of the string
***/
{
	if (n <= 0)     // Invalid bound, return blank string
		return "";
	else if (n > String(str).length)   // Invalid bound, return
		return str;                     // entire string
	else { // Valid bound, return appropriate substring
		var iLen = String(str).length;
		return String(str).substring(iLen, iLen - n);
	}
}