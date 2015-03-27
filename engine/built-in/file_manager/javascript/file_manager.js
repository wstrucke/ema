/* Javascript Document */

/* Copyright 2010 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

/* XML Paths */
var file_urls = {
	'content': '<?php echo url('xml/file/get_file_content'); ?>',
	'create_alias': '<?php echo url('xml/create/file_alias'); ?>',
	'delete_alias': '<?php echo url('xml/delete/file_alias'); ?>',
	'delete': '<?php echo url('xml/delete/file'); ?>',
	'download': '<?php echo url('download'); ?>',
	'listFiles': '<?php echo url('xml/list/files'); ?>',
	'move': '<?php echo url('xml/file/move'); ?>',
	'save_content': '<?php echo url('xml/file/save_file_content'); ?>'
	};

/* Field Definitions */


/* Initialization */

window.addEvent('domready', addEventHandlers);

/* Functions */

function addEventHandlers()
{
	
}

function createContentEditorWindow(local_mode)
/* create a content editor popup window
 *
 */
{
	var edit = false;
	var fd = {
	  'content_help': { 'type': 'p' },
		'content': { 'type': 'textarea', 'width': 1200, 'rows': 40, 'lang': 'text' },
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
	
	createWindow('Edit File Content', fd, edit);
	
	//if (ema.wysiwyg) {
	//	$('content_help').set('html', 'Update the content in the editor below.  Changes will appear on your site as soon as you save them.');
	//} else {
		$('content_help').set('html', "&nbsp;");
	//}
	
	// add events
	$('btn_save').addEvent('click', function(e) { saveFileContent(false); });
	$('btn_close').addEvent('click', function(e) { saveFileContent(true); });
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
			saveFileContent(false);
		}
	});
	
	$('content').focus();
}

function fileListAction(ele)
{
	var id = ele.getParent().getElement('input').get('value');
	var folder = $('folder').get('html');
	
	switch(ele.get('value'))
	{
		case '': break;
		case '------------------------------------': break;
		case 'alias':
			var fd = {
				'alias_fid': { type: 'hidden' },
				'new_alias': { label: 'Alias' },
				'btn_save': { label: 'Save', type: 'button' }
			};
			createWindow('Create an alias', fd, true);
			$('alias_fid').set('value', id);
			$('btn_save').addEvent('click', function() {
				// post to xml
				var myRequest = new Request.HTML({
					method: 'get',
					url: file_urls.create_alias + '/' + $('alias_fid').get('value') + '/' + encodeURIComponent($('new_alias').get('value')),
					onSuccess: function(t,e,h,j){
						hideWaiting();
						// if error show message and stop here
						try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
						try { $$('#window', '#overlay').destroy(); } catch(er) {}
					}
				});
				myRequest.send();
				showWaiting('Saving...');
			});
			break;
		case 'content':
			var c = $$('#file_' + id + ' td');
			var type = c[4].textContent;
			if (type.indexOf('text') !== 0) {
				showNotice('You can only modify the contents of text files');
				break;
			}
			
			createContentEditorWindow('edit');
			createOverlay($('window'), 'windowOverlay');
			
			var myRequest = new Request.HTML({
				url: file_urls.content + '/' + id,
				noCache: true,
				onSuccess: function(t,e,h,j){
					$('windowOverlay').destroy();
					// if error show message and stop here
					try { if (e.filter("error")[0]) { err = e.filter("error")[0]; return showNotice('Error loading content'); } } catch(er) {}
					var record = e.filter('file')[0];
					var data = base64_decode(record.getElement('data').textContent);
					//if (ema.wysiwyg) {
					//	editor.setData(data);
					//} else {
						$('content').set('value', data);
					//}
					// set id and type as well
					$('id').set('value', record.getElement('unique_id').textContent);
					$('type').set('value', record.getElement('type').textContent);
					$('content').focus();
				},
			onFailure: function() { showNotice('The request failed.'); }
			});
			myRequest.get();
			break;
		case 'download':
			var path = file_urls.download + '/' + id;
			if (folder != '/') {
				var tmp = ele.getParent().getParent().getChildren('td');
				var filename = tmp[1].get('html');
				path = file_urls.download + folder + '/' + filename;
			}
			window.location = path;
			break;
		case 'delete':
			if (confirm('Are you sure?')) {
				var myRequest = new Request.HTML({
					method: 'post',
					url: file_urls['delete'] + '/' + id,
					onSuccess: function(t,e,h,j){
						hideWaiting();
						// if error show message and stop here
						try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
						try {
							var id = e.filter('response')[0].textContent;
							$('file_' + id).destroy();
						} catch(er) {}
					}
				});
				showWaiting('Deleting...');
				myRequest.send();
			}
			break;
		case 'move':
			var f = {
				'path': { 'type': 'input', 'label': 'Path', 'width': 300 },
				'btn_move': { 'type': 'button', 'label': 'Move' }
				};
			createWindow('Move File To...', f, true);
			$('btn_move').addEvent('click', function(){
				var myRequest = new Request.HTML({
					method: 'post',
					url: file_urls['move'] + '/' + id + '/' + $('path').get('value').replace(/\//g, '|'),
					onSuccess: function(t,e,h,j){
						hideWaiting();
						// if error show message and stop here
						try { if (e.filter('error')[0]) { return showNotice(e.filter('error')[0].textContent); } } catch(er) {}
						try {
							var id = e.filter('response')[0].textContent;
							$$('#window', '#overlay', '#file_' + id).destroy();
						} catch(er) {}
					}
				});
				showWaiting('Moving...');
				myRequest.send();
				});
			$('path').focus();
			break;
		default: showNotice('option not implemented, id: ' + id); break;
	}
	ele.set('value', '');
	ele.blur();
}

function saveFileContent(autoclose)
/* save element content from an editor window 
 *
 */
{
	var myRequest = new Request.HTML({
		method: 'post',
		url: file_urls.save_content + '/' + encodeURIComponent($('id').get('value')),
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
	//if (ema.wysiwyg) {
	//	myRequest.send('content=' + encodeURIComponent(CKEDITOR.instances['content'].getData()));
	//} else {
		myRequest.send('content=' + base64_encode($('content').get('value')));
	//}
}