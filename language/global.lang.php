<?php

$l['next'] = "Next";
$l['version'] = "Version";
$l['none'] = "None";
$l['please_wait'] = "Please wait...";
$l['welcome'] = "Welcome";
$l['pause'] = "Pause";
$l['error'] = "Error";
$l['warning'] = "Warning";
$l['completed'] = "Completed";
$l['dependencies'] = "Dependencies";
$l['resume'] = "Resume";
$l['run'] = "Run";
$l['cleanup'] = "Cleanup";
$l['yes'] = "Yes";
$l['no'] = "No";

// Modules, english names are hardcoded. Uncomment this for your language
/*
$l['module_usergroups'] = 'User Groups';
$l['module_users'] = 'Users';
$l['module_categories'] = 'Categories';
$l['module_forums'] = 'Forums';
$l['module_forumperms'] = 'Forum Permissions';
$l['module_moderators'] = 'Moderators';
$l['module_threads'] = 'Threads';
$l['module_posts'] = 'Posts';
$l['module_attachments'] = 'Attachments';
$l['module_polls'] = 'Polls';
$l['module_pollvotes'] = 'Poll Votes';
$l['module_privatemessages'] = 'Private Messages';
$l['module_events'] = 'Events';
$l['module_icons'] = 'Icons';
$l['module_smilies'] = 'Smilies';
$l['module_settings'] = 'Settings';
$l['module_attachtypes'] = 'Attachment Types';
*/

$l['indexpage_require'] = "The MyBB Merge System requires MyBB 1.8 to run.";

$l['welcomepage_description'] = "Welcome to the MyBB Merge System. The MyBB Merge system has been designed to allow you to convert a supported forum software to MyBB 1.8. In addition, you may also <i>merge</i> multiple forums into one MyBB Forum.<br /><br />You can find a detailed guide to the MyBB Merge System on our docs site: ";
$l['welcomepage_mergesystem'] = "Merge System";
$l['welcomepage_anonymousstat'] = "Send anonymous statistics about my merge to the MyBB Group";
$l['welcomepage_informations'] = "What information is sent?";
$l['welcomepage_closeboard'] = "Close the board during the merge";
$l['welcomepage_note'] = "The MyBB Merge system is <u><strong>not</strong></u> used for upgrading or linking MyBB forums. In addition, please make sure all modifications or plugins that may interefere with the conversion process are <strong>deactivated</strong> on both forums (your old forum and your new forum), before you run the MyBB Merge System. It is also <strong>strongly</strong> recommended to make a backup of both forums before you continue.";
$l['welcomepage_pleasenote'] = "Please Note";

$l['requirementspage_check'] = "Requirements Check";
$l['requirementspage_req'] = "Requirements";
$l['requirementspage_uptodate'] = "Up to Date";
$l['requirementspage_outofdatedesc'] = "Your MyBB Merge System is out of date! Your MyBB Merge System may not work properly until you update. Latest version: 
";
$l['requirementspage_outofdate'] = "Out of Date";
$l['requirementspage_mergeoutofdate'] = "This version of the merge system is out-of-date";
$l['requirementspage_download'] = "Download";
$l['requirementspage_unabletocheck'] = "Unable to Check";
$l['requirementspage_unabletocheckdesc'] = "Unable to check version status against mybb.com version server";
$l['requirementspage_chmoduploads'] = "The attachments directory (/uploads/) is not writable. Please adjust the ";
$l['requirementspage_chmoduploads2'] = " permissions to allow it to be written to.";
$l['requirementspage_chmod'] = "The attachments directory (/uploads/) is not writable. Please adjust the ";
$l['requirementspage_notwritable'] = "Not Writable";
$l['requirementspage_attnotwritable'] = "Attachments directory not writable";
$l['requirementspage_attwritable'] = "Writable";
$l['requirementspage_attwritabledesc'] = "Attachments directory writable";
$l['requirementspage_reqfailed'] = "The MyBB Merge System Requirements check failed:";
$l['requirementspage_mergeversion'] = "Merge System Version:";
$l['requirementspage_attwritabledesc2'] = "Attachments directory writable:";
$l['requirementspage_checkagain'] = "When you are ready, click \"Check Again\" to check again.";
$l['requirementspage_congrats'] = "Congratulations, you passed all the requirement checks! Click \"Next\" to move right along.
";

$l['boardspage_welcome'] = "Thank you for choosing MyBB. This wizard will guide you through the process of converting from your existing community to MyBB.";
$l['boardspage_boardselection'] = "Board Selection";
$l['boardspage_boardselectiondesc'] = "Please select the board you wish to convert from.";

$l['module_selection'] = "Module Selection";
$l['module_selection_select'] = "Please select a module to run.";
$l['module_selection_import'] = "Import {1} ";
$l['module_selection_cleanup_desc'] = "After you have run the modules you want, continue to the next step in the conversion process.  The cleanup step will remove any temporary data created during the conversion.";

$l['database_configuration'] = "Database Configuration";
$l['database_settings'] = "Database Settings";
$l['database_engine'] = "Database Engine";
$l['database_path'] = "Database Path";
$l['database_host'] = "Database Server Hostname";
$l['database_user'] = "Database Username";
$l['database_pw'] = "Database Password";
$l['database_name'] = "Database Name";
$l['database_table_settings'] = "Table Settings";
$l['database_table_prefix'] = "Table Prefix";
$l['database_table_encoding'] = "Table Encoding";
$l['database_utf8_thead'] = "Encode to UTF-8";
$l['database_utf8_desc'] = "Automatically convert messages to UTF8?:<br /><small>Turn this off if the conversion creates<br />weird characters in your forum's messages.</small>";
$l['database_click_next'] = "Once you have checked these details are correct, click next to continue.";

$l['cleanup_header'] = "MyBB Merge System - Final Step: Cleanup";
$l['cleanup_notice'] = "Performing final cleanup and maintenance (This may take a while)...";

$l['error_invalid_board'] = "The board module you have selected does not exist.";
$l['error_js_off'] = 'It appears that you have javascript turned off. The MyBB Merge System requires that javascript be turned on in order to operate properly. Once you have turned javascript on, please refresh this page.';

$l['loginconvert_title'] = "MyBB Merge System - Setup Password Conversion";
$l['loginconvert_message'] = "			<div class=\"error\">\n
				<h3>Error</h3>
				The MyBB Merge System cannot continue until you upload loginconvert.php (found in this directory via a file transfer application) to your MyBB Forums' inc/plugins folder.\n
		</div>

		<p>More Information can be found <a href=\"http://docs.mybb.com/1.8/merge/running/#loginconvert.php-plugin\" target=\"_blank\">here</a>.</p>
		<p>Once you have uploaded the file, click next to continue.</p>";


$l['report_txt'] = 'MyBB Merge System - Merge Report
--------------------------------------------------------
Welcome to the MyBB Merge System Generated Report. This
report shows a small overview of this merge session.

General
-------
	Board merged:    {1}
	Import began:    {2}
	Import finished: {3}

Database Query Statistics
-------------------------
	Queries on MyBB database: {4}
	Queries on old database:  {5}
	Total query time:         {6}

Modules
-------
The following modules from this converter were completed:
{7}

Import Statistics
-----------------
The MyBB import system imported the following from your copy of {8}:
{9}

Errors
------
The following errors were logged during the process of the Merge System:
{10}

Problems?
---------
The "mybb_debuglogs" table located in your database contains
debug information about this merge. If you find problems
please file a support inquery at http://community.mybb.com/.

--------------------------------------------------------
Generated: {11}';

$l['report_html'] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>MyBB Merge System &gt; Generated Report</title>
	<style type="text/css">
		body {
			font-family: Verdana, Arial, sans-serif;
			font-size: 12px;
			background: #efefef;
			color: #000000;
			margin: 0;
		}

		#container {
			margin: auto auto;
			width: 780px;
			background: #fff;
			border: 1px solid #ccc;
			padding: 20px;
		}

		h1 {
			font-size: 25px;
			margin: 0;
			background: #ddd;
			padding: 10px;
		}

		h2 {
			font-size: 18px;
			margin: 0;
			padding: 10px;
			background: #efefef;
		}

		h3 {
			font-size: 14px;
			clear: left;
			border-bottom: 1px dotted #aaa;
			padding-bottom: 4px;
		}

		ul, li {
			padding: 0;
		}

		#general p, #modules p, #import p, ul, li, dl {
			margin-left: 30px;
		}

		dl dt {
			float: left;
			width: 300px;
			padding-bottom: 10px;
			font-weight: bold;
		}

		dl dd {
			padding-bottom: 10px;
		}

		#footer {
			border-top: 1px dotted #aaa;
			padding-top: 10px;
			font-style: italic;
		}

		.float_right {
			float: right;
		}
	</style>
</head>
<body>
<div id="container">
	<h1>MyBB Merge System</h1>
	<h2>Merge Report</h2>
	<p>Welcome to the MyBB Merge System Generated Report. This report shows a small overview of this merge session.</p>
	<div id="general">
		<h3>General Statistics</h3>
		<p>You merged {1} to your forum.</p>
		<dl>
			<dt>Import began</dt>
			<dd>{2}</dd>

			<dt>Import finished</dt>
			<dd>{3}</dd>
		</dl>
	</div>
	<div id="database">
		<h3>Database Query Statistics</h3>
		<dl>
			<dt>Queries on the MyBB database</dt>
			<dd>{4}</dd>

			<dt>Queries on the {$board->plain_bbname} database</dt>
			<dd>{5}</dd>

			<dt>Total query time</dt>
			<dd>{6}</dd>
		</dl>
	</div>
	<div id="modules">
		<h3>Modules</h3>
		<p>The following modules from this converter were completed:</p>
		<ul>
		{7}
		</ul>
	</div>
	<div id="import">
		<h3>Import Statistics</h3>
		<p>The MyBB import system imported the following from your copy of {8}:</p>
		<dl>
		{9}
		</dl>
	</div>
	<div id="errors">
		<h3>Errors</h3>
		<p>The following errors were logged during the process of the Merge System:</p>
		<ul>
		{10}
		</ul>
	</div>
	<div id="problems">
		<h3>Problems?</h3>
		<p>The "mybb_debuglogs" table located in your database contains debug information about this merge. If you find problems please file a support inquiry at the <a href="http://community.mybb.com/">MyBB Community Forums</a>.</p>
	</div>
	<div id="footer">
		<div class="float_right">MyBB &copy; 2002-{12} MyBB Group</div>
		<div>Generated {11}</div>
	</div>
</div>
</body>
</html>';