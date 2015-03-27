// Calendar 2 Javascript Support File
// --------------------------------------------------------
// Copyright (c) 2006, William Strucke, wstrucke@gmail.com
// 
// Some code contained in this file was modified from
// other authors.
//

<!--

	
	
	
/*	
	function addEvent(obj, evType, fn, useCapture)
	{
		// source: http://www.barclaey.com/experiments/custom.js
		if (obj.addEventListener)
		{
			obj.addEventListener(evType, fn, useCapture);
			return true;
		} else if (obj.attachEvent) {
			var r = obj.attachEvent("on"+evType, fn);
			return r;
		} else {
			alert("Handler could not be attached");
		}
	}
*/
	function getElementTop(Elem) {
		//if (ns4) {
		//	var elem = getObjNN4(document, Elem);
		//	return elem.pageY;
		//} else {
			if(document.getElementById) {	
				var elem = document.getElementById(Elem);
			} else if (document.all) {
				var elem = document.all[Elem];
			}
			yPos = elem.offsetTop;
			tempEl = elem.offsetParent;
			while (tempEl != null) {
					yPos += tempEl.offsetTop;
					tempEl = tempEl.offsetParent;
				}
			return yPos;
		//}
	}
	
	function getHeight() 
	{
		// http://www.thescripts.com/forum/thread91876.html
		var docHeight;
		if (typeof document.height != 'undefined') {
		docHeight = document.height;
		}
		else if (document.compatMode && document.compatMode != 'BackCompat') {
		docHeight = document.documentElement.scrollHeight;
		}
		else if (document.body && typeof document.body.scrollHeight != 'undefined') {
		docHeight = document.body.scrollHeight;
		}
		
		return docHeight;
	}
	
	function loadDay (m,d,y)
	{
		// load specified day into browser
		try 
		{ 
			if (d != null) { tmp = m + ',' + d + ',' + y; } else { tmp = m; }
			window.location = window.calendarRootPath + 'selection=day&date=' + tmp; 
		} catch (e) { alert('Your browser does not support this feature, please click the "Full Schedule" link to view the calendar.'); }
	}

-->