/* Javascript Document */

/* WYSIWYG_CKEditor JS Support File for ema
 *
 * Version 1.0.0, 2010-12-02 ws
 *
 */

// Global Variables for passing input to and from wysiwyg
var wysiwygSettings = {
	css: [],
	enabled: true
};

var CKEDITOR_BASEPATH = '<?php echo url('ckeditor/'); ?>';
var editor;

// Initialization

registerHook('createWindow', function(){
	// only continue if enabled for the current page
	if (wysiwygSettings.enabled == false) return true;
	// make sure there is only one editor at a time to avoid errors
	try { if (editor) editor.destroy(); } catch(e){}
	// get any text areas in the html output
	var list = $$('textarea');
	// preset settings
	var settings = '';
	// for now extend all text areas until we define settings for the wysiwyg_ckeditor extension
	for (var i=0;i<list.length;i++) {
		// set the default content type
		var type;
		// try to determine the text area content type
		try {
			if (list[i].get('lang').length > 0) { type = list[i].get('lang'); }
		} catch(e) {}
		// validate the selected type
		switch (type) {
			case 'html': break;
			default: type = null; break;
		}
		if (type != null) {
			// get the id
			if ((typeof(list[i].getAttribute('id')) != 'string') || (list[i].getAttribute('id').length == 0)) {
				// for now, assume only one text area per page... this will have to be changed as well
				var area_id = 'syntax_1';
				list[i].setAttribute('id', area_id);
			} else {
				var area_id = list[i].getAttribute('id');
			}
			// define the editor configuration
			var config = {
				contentsCss: wysiwygSettings.css,
				disableObjectResizing: true,
				fullPage: false,
				height: 450,
				htmlEncodeOutput: false,
				resize_maxWidth: 750,
				toolbar : [
					['Source', 'Preview'],
					['Cut','Copy','Paste','PasteText','PasteFromWord','-','Scayt'],
					['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
					['Image','Flash','Table','HorizontalRule','Smiley','SpecialChar','PageBreak'],
					'/',
					['Styles', 'Format'],
					['Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Unlink','Anchor', '-', 'About']],
				width: 750
				};
			// create the wysiwyg editor
			editor = CKEDITOR.replace(area_id, config);
		}
	}
	return true;
});

registerHook('destroyWindow', function(){
	// only continue if enabled for the current page
	if (wysiwygSettings.enabled == false) return true;
	try { if (editor) editor.destroy(); } catch(e){}
});