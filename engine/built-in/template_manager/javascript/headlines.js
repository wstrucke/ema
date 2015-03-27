// JavaScript Document

/*
 * MooTools implementation for headlines on home page
 *
 * Version 1.0, 2007.02.12
 *
 * William Strucke, wstrucke@gmail.com
 *
 */
	
	var headlineFx = new Array();
	
	window.onload=function()
	{
		var h = $$('#headlines li');
	
		for (i=1;i<h.length;i++)
		{
			headlineFx[h[i].getProperty('id')] = new Fx.Styles(h[i], {duration: 1200, transition: Fx.Transitions.linear});
			h[i].setProperty('initHeight', (h[i].getSize().size.y - 5));
			//h[i].onmouseover=function(){ headlineFx[this.getProperty('id')].start({ 'height': this.getSize().scrollSize.y }); };
			//h[i].onmouseout=function(){ headlineFx[this.getProperty('id')].start({ 'height': this.getProperty('initHeight') }); };
		}
	};
	
	function showHeadline(h)
	{
		headlineFx[$(h).getProperty('id')].start({ 'height': ($(h).getSize().scrollSize.y - 5) });
		$$('#' + h + ' a.fxButton').setHTML('hide');
		$$('#' + h + ' a.fxButton').setProperty('href',"javascript:hideHeadline('" + h + "');");
		//javascript:showHeadline('h1');
	}
	
	function hideHeadline(h)
	{
		headlineFx[$(h).getProperty('id')].start({ 'height': $(h).getProperty('initHeight') });
		$$('#' + h + ' a.fxButton').setHTML('read');
		$$('#' + h + ' a.fxButton').setProperty('href',"javascript:showHeadline('" + h + "');");
	}