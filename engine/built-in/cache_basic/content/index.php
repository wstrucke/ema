<?php
	
	if (isset($_GET)&&array_key_exists('expire', $_GET)&&array_key_exists('request', $_GET)) {
		$tmp = $t->db->update('cache_content', array('request'), array($_GET['request']), array('approved'), array(false));
		if ($tmp === false) {
			message('Error expiring request', 'error');
		} else {
			message('Request expired successfully', 'notice');
		}
	}
	
	$count = $t->db->count('cache_content'); if ($count === false) $count = 0;
	$ccount = $t->db->count('cache_content_map'); if ($ccount === false) $ccount = 0;
	$dcount = $t->db->count('cache_output'); if ($dcount === false) $dcount = 0;
	if ($count > 0) {
		$list = $t->db->select('cache_content', '', '', '*', 500, 'returned DESC');
	} else {
		$list = array();
	}
?>

<style type="text/css">
<!--
	div#page {
		left: auto !important;
		margin: 0 auto 0 !important;
		right: auto !important;
		width: 1200px !important;
	}
	table.cachelist {
		font-size: 0.79em;
		margin: 15px 0 0;
		width: 1200px;
	}
	table.cachelist td {
		border-bottom: 1px solid #ccc;
		border-left: 1px solid #ccc;
		text-align: center;
	}
-->
</style>
	
	<div>
		<h1>cache control panel<div><?php echo l('refresh', 'admin/cache_basic') . ' ' . l('close', 'admin'); ?></div></h1>
	</div>
<!--
	<ul id="cms_cpanel_buttons">
		<li><?php echo l('List', 'admin/file'); ?></li>
		<li><?php echo l('Upload', 'admin/file/upload'); ?></li>
	</ul>
-->
	<h2>Summary</h2>
	<h3>There are <?php echo $count; ?> cached requests in <?php echo $ccount; ?> pieces de-duplicated to <?php echo $dcount; ?> unique data components.</h3>
	<table class="cachelist">
		<tr>
			<th>Generation</th><!-- time -->
			<th>Last Verification</th><!-- last_verify -->
			<th>Verify Count</th><!-- count -->
			<th>Compile Time</th><!-- duration -->
			<th>Approval</th><!-- approved -->
			<th>Return Count</th><!-- returned -->
			<th>Cache Time</th><!-- cache_duration -->
			<th>Time Saved</th>
			<th>Maximum Age</th><!-- max_age -->
			<th>Page ID</th><!-- pageid -->
			<th>Request URI</th><!-- uri -->
			<th>Arguments</th><!-- uri_args -->
			<th>&nbsp;</th>
		</tr>
<?php foreach($list as $row): ?>
		<tr>
		<?php
			# set color for approval
			(bit2bool($row['approved'])) ? $color = 'bg-green' : $color = 'bg-red';
			printf("<td>%s</td>\r\n", date('Y-M-d H:i:s', $row['time']));
			printf("<td>%s</td>\r\n", date('Y-M-d H:i:s', $row['last_verify']));
			printf("<td>%s</td>\r\n", $row['count']);
			printf("<td>%s ms</td>\r\n", $row['duration']);
			printf("<td class=\"%s\">%s</td>\r\n", $color, b2s($row['approved']));
			printf("<td>%s</td>\r\n", $row['returned']);
			printf("<td>%s ms</td>\r\n", $row['cache_duration']);
			printf("<td>%s ms</td>\r\n", (intval($row['duration'])-intval($row['cache_duration']))*intval($row['returned']));
			printf("<td>%s</td>\r\n", $row['max_age']);
			printf("<td>%s</td>\r\n", $row['pageid']);
			printf("<td>%s</td>\r\n", $row['uri']);
			printf("<td>%s</td>\r\n", $row['uri_args']);
		?>
			<td><?php echo l('Expire', 'admin/cache_basic?expire=1&request=' . $row['request']); ?></td>
		</tr>
<?php endforeach; ?>
	</table>