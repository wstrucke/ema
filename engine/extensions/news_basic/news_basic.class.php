<?php
 /* news module for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, dec-05-2010
  * William Strucke, wstrucke@gmail.com
  *
  * Syntax:
  *
  *   TBD
  *
  * Usage:
  *
  *   TBD
  *
  * Database:
  *
  *   TBD
  *
  */
  
class news_basic extends standard_extension
{
  # database version
  public $schema_version='0.0.1';  // the schema version to match the registered schema
  
  public $_name = 'News Module';
  public $_version = '1.0.0';
  protected $_debug_prefix = 'news';
  
  /* code */
  public function breaking_news()
  /* return breaking news item(s)
   *
   */
  {
  	return ''; // disabled;
  	# set optional ID
  	# set optional class
  	# display link true/false
  	# should have an optional expiration date/time
  	/*
  	$tempStr = '<div id="urgent-news-new" class="struckeBox">
           <p class="headline">Skull Session for the OSU vs Michigan game on Saturday, November 27th will begin at 10:00 am in St. John Arena.</p>
           <span>Click <a href="#">here</a> for more details.</span>
           <img id="breaking_news_close" onclick="p=this.parentNode; this.parentNode.parentNode.removeChild(p);" src="' . url('download/1291844899_close.png') . '" width="16" height="16" alt="Close" />
  		</div>';
  	*/
  	$tempStr = '<div id="urgent-news-new" class="struckeBox">
  				<p class="headline">Buckeye Invitational: Saturday, October 15th, 2011. Tickets on sale now or at the gate. Details below.</p>
  				<span><a href="http://www.ticketmaster.com/event/0500474ADEDAC49B" target="_blank">Order Tickets Online</a></span>
  				<img id="breaking_news_close" onclick="p=this.parentNode; this.parentNode.parentNode.removeChild(p);" src="' . url('download/1291844899_close.png') . '" width="16" height="16" alt="Close" />
  				</div>';
  	return $tempStr;
  }
  
  public function cache_expire_interval($method = '')
  /* given a method name return the interval in seconds in which it should be refreshed by the system cache
   *  the cache should always start counting at midnight
   *
   * return an integer; -1 means 'do not cache'
   *
   * for reference:
   *    1 hour        3600 seconds
   *    6 hours       21600 seconds
   *    12 hours      43200 seconds
   *    1 day         86400 seconds
   *    5 days        432000 seconds
   *    1 week        604800 seconds
   *    1 month       2592000 seconds
   *
   * a value of 0 means never automatically refresh until the cache is expired
   *
   */
  {
  	switch($method) {
  		case 'breaking_news': return 0;
  		case 'news': return 0;
  		default: return 0;
  	}
  }
  
  public function news()
  /* return news html
   *
   */
  {
  	# set optional ID
  	# set optional class
  	$tempStr = '<ul class="news">
  								<li>
  	              	<div><em>News</em> for 2012-03-04</div>
  	              	<p>Junior/Senior Night 2012 is Sunday, April 22nd from 3:00 pm to 7:00 pm. <a href="/forms/junior_senior_night">Register now!</a></p>
  	              	<p>The 2012 Buckeye Invitational will be on Saturday, October 13, 2012. <a href="/forms/buckeye_invitational">Register your band now!</a></p>
  	              </li>
                </ul>';
  	return $tempStr;
  	/*
  								<li>
  	                <div><em>News</em> for 10-07-2011</div>
  	                <p>Buckeye Invitational</p>
  	                <p>Featuring 40 of the finest Marching Bands in Ohio...and one from New York!</p>
  	                <p>Saturday, October 15th, 2011<br />Tickets: $10.00 presale through Thursday, October 13<br />Call 614-688-5926 daily from 2:30 pm to 7:00 pm or order online at <a href="http://www.ticketmaster.com/event/0500474ADEDAC49B">http://www.ticketmaster.com/event/0500474ADEDAC49B</a>.<br />All major credit cards, cash and checks accepted.<br /><br />Tickets at the gate on the day of the event:  $12.00</p>
  	                <p><a href="http://tbdbitl.osu.edu/download/pdfs/Buckeye Invitational Final Schedule (10-10-11).pdf">Download the event schedule</a></p>
  	              </li>
  	              <li>
                      <div><em>News</em> for 08-09-2011</div>
                      <p>Tryouts for the Ohio State University Marching Band for the 2011-2012 season will be held Tuesday, August 30th and 
                        Wednesday, August 31st. In order to participate in tryouts, please sign-up <a href="forms/register_for_tryouts">here</a>. All 
                        candidates that have not been members of the marching band in past years are required to attend "Candidate Training Days" on 
                        Sunday, August 28th and Monday, August 29th. These two days are meant to introduce new candidates to what will be expected 
                        during tryouts.</p>
                      <p>For additional practice, feel free to attend the optional rehearsals lead by veteran members of the band known as summer 
                        sessions. The full schedule can be found <a href="/calendar">here</a>, where percussion players start at 6:00pm and brass 
                        players begin at 7:00pm on the rehearsal field in front of Lincoln Tower. For additional information about tryouts or summer 
                        sessions, please contact us via email or by calling the band center via the information on the right.</p>
                    </li>
		*/
  }
}