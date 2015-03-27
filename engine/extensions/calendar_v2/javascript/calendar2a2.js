/* Javascript Document */

/* Calendar Extension Version 2.1 ALPHA 1
 *
 * Copyright 2010 William Strucke [wstrucke@gmail.com]
 * All Rights Reserved
 *
 */

	var calendarCache = Array();
	
	/* Initialization */

	window.addEvent('domready', loadCalendarFX);
	
	function confirmDelete(id, date) {
		if (confirm('Are you sure you want to delete this event?')) {
			window.location = window.calendarRootPath + '&selection=delete&id=' + id + '&date=' + date;
		}
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
	
	function loadCalendarFX() {
		try {
			if ($('timeCheckBox').checked) {
				calendarCache['time'] = $('time').getSize();
				$('time').setStyle('height', '0');
			}
		} catch(e){}
		try {
			if (! $('recurringCheckBox').checked) {
				calendarCache['recurring'] = $('recurring').getSize();
				$('recurring').setStyle('height', '0');
			}
		} catch(e){}
		try { if ($('recurrance_pattern')) {
			// update recurrance pattern fields in case of edit form
			changePatternDetails($('recurrance_pattern'));
		}} catch(e){}
		try {
			myCal1 = new Calendar({ date: 'm/d/Y' }, { direction: 1, tweak: { x: 6, y: 0 }});
		} catch(e){}
		
		// attach events
		try {
			$('timeCheckBox').addEvent('click', function(){
				if (this.checked) {
					calendarCache['time'] = $('time').getSize();
					$('time').morph({'height': '0', 'opacity': 0});
				} else {
					$('time').morph({'height': calendarCache['time']['y'], 'opacity': 1});
				}
			});
		} catch(e){}
		try {
			$('recurringCheckBox').addEvent('click', function(){
				if (this.checked) {
					$('recurring').morph({'height': calendarCache['recurring']['y'], 'opacity': 1});
				} else {
					calendarCache['recurring'] = $('recurring').getSize();
					$('recurring').morph({'height': '0', 'opacity': 0});
				}
			});
		} catch(e){}
		try {
			$('recurrance_pattern').addEvent('change', function(){ changePatternDetails(this); });
		} catch(e){}
		try {
			$('calendar_group_filter').addEvent('change', function(){ $('calendar_group_form').submit(); });
		} catch(e){}
		try {
			$('btn_saveEvent').addEvent('click', function(){ $('calendarAddEvent').submit(); });
		} catch(e){}
		
		
		// hover for calendar compact view
		try {
			var hh = new HelpHover();
			hh.init();
		} catch(e){}
	}
	
	
// ****************************************************
//                 LEGACY FUNCTIONS
// ****************************************************
	
	function changePatternDetails(src)
	{
		// change recurrance pattern details based on selection
		
		// get objects
		d = $('recurringDayContainer');
		w = $('recurringWeekContainer');
		m = $('recurringMonthContainer');
		y = $('recurringYearContainer');
		
		// apply changes
		switch (src.value) {
			case 'daily':
				w.setStyle('display', 'none'); m.setStyle('display', 'none');	y.setStyle('display', 'none');
				d.setStyle('display', 'block');
				break;
			case 'weekly':
				d.setStyle('display', 'none'); m.setStyle('display', 'none');	y.setStyle('display', 'none');
				w.setStyle('display', 'block');
				break;
			case 'monthly':
				d.setStyle('display', 'none'); w.setStyle('display', 'none');	y.setStyle('display', 'none');
				m.setStyle('display', 'block');
				break;
			case 'yearly':
				d.setStyle('display', 'none');	w.setStyle('display', 'none');	m.setStyle('display', 'none');
				y.setStyle('display', 'block');
				break;
		}
	}
	
	function HelpHover()
	{
		this._mousePosX = 0;
		this._mousePosY = 0;
		this._hoverItem = null;
		this._hoverContents = null;
	}
	
	HelpHover.prototype.init = function()
	{
		var hh = this;
		var helpItems = $$('.hover');
		for (var i=0; i<helpItems.length; i++) {
			helpItems[i].onmousemove = function(e) {
				if (!e) var e = window.event;
				if (e.pageX || e.pageY) {
					hh.mousePosX = e.pageX;
					hh.mousePosY = e.pageY;
				} else if (e.clientX || e.clientY) {
					hh.mousePosX = e.clientX + document.documentElement.scrollLeft;
					hh.mousePosY = e.clientY + document.documentElement.scrollTop;
				}
				hh._hoverItem = this;
				hh._hoverContents = document.getElementById(this.id+'hoverData');
				hh.move();
			}
			helpItems[i].onmouseout = function(e) { hh.out(); }
		}
	}
	
	HelpHover.prototype.out = function()
	{
		this._hoverContents.style.top = -10000+'px';
		this._hoverContents.style.left = -10000+'px';
		this._hoverItem = null;
		this._hoverContents = null;
	}
	
	HelpHover.prototype.move = function()
	{
		//this._hoverContents.style.top = this.mousePosY+10+'px';
		//this._hoverContents.style.left = this.mousePosX-180+'px';
		/* need to adjust for relative window position */
		
		var useThisHeight = this.mousePosY;
		var useThisWidth = this.mousePosX;
		
		this._hoverContents.style.top = this.mousePosY+'px';
		//this._hoverContents.style.top = useThisHeight+'px';
		this._hoverContents.style.left = this.mousePosX+12+'px';
		//this._hoverContents.style.left = useThisWidth+'px';
	}
	