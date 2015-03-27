/* Javascript Document */

/* cms module ui javascript
 *	copyright (c) 2009-2011 william strucke
 * 	wstrucke@gmail.com
 *
 * Requires mootools 1.2
 *
 */

var def_MenuManager = new Array(
	{ id: 'list', element: 'ul', properties: { class: 'list' }, children: menu_list, onLoad: menu_list_dragable, break: true }
);

var def_NewLink = new Array(
	{ id: 'name', element: 'input', label: 'Name', width: 250, required: true },
	{ id: 'link_is_external', element: 'checkbox', label: 'External Link', help: 'Uncheck if this is a local (relative) path', value: true },
	{ id: 'link_uri', element: 'input', label: 'URL / Address', width: 400, required: true, break: true },
	{ id: 'title', element: 'input', label: 'Page Title', width: 400, break: true },
	{ id: 'link_title', element: 'input', label: 'Link Title', width: 400, break: true },
	{ id: 'description', element: 'input', label: 'Description', width: 400, break: true },
	{ id: 'enabled', element: 'checkbox', label: 'Enabled', value: true, break: true },
	{ id: 'locked', element: 'checkbox', label: 'Locked', help: 'Lock edit access to this element' },
	{ id: 'save', element: 'formbutton', label: 'Create', onClick: act_CreateLink, break: true }
);

var def_NewElement = new Array(
	{ id: 'name', element: 'input', label: 'Name', width: 400, required: true },
	{ id: 'title', element: 'input', label: 'Page Title', width: 400, break: true },
	{ id: 'link_title', element: 'input', label: 'Link Title', width: 400, break: true },
	{ id: 'description', element: 'input', label: 'Description', width: 400, break: true },
	{ id: 'template', element: 'select', label: 'Template', width: 400, help: 'Select a template to override the default site template', break: true },
	{ id: 'enabled', element: 'checkbox', label: 'Enabled', break: true },
	{ id: 'locked', element: 'checkbox', label: 'Locked', help: 'Lock edit access to this element' },
	{ id: 'ssl_required', element: 'checkbox', label: 'SSL Required', help: 'Require an SSL connection to access this element' },
	{ id: 'save', element: 'formbutton', label: 'Save and Close', break: true },
	{ id: 'save_edit', element: 'formbutton', label: 'Save and Edit' }
);

var def_PathManager = new Array(
	{ id: 'new_path', element: 'formbutton', label: 'New Path', onclick: function(){showNotice('under development');} },
	{ id: 'list', element: 'ul', properties: { class: 'list', 'style': 'clear: both;' }, children: path_list, break: true }
);