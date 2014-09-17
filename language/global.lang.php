<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

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
$l['download'] = "Download";
$l['redirecting'] = "Redirecting...";
$l['dont_wait'] = "Click to continue, if you do not wish to wait.";
$l['back'] = "Back";
$l['found_error'] = "Error Encountered";
$l['loading_data'] = "Loading data from database...";
$l['done'] = "Done";

// Modules, english names are hardcoded. Uncomment this for your language
// Descriptions are added as "module_{key}_desc, however the current ones doesn't have a description
// Singular versions are added as "module_{key}_singular
/*
$l['module_usergroups'] = 'User Groups';
$l['module_usergroups_singular'] = 'User Group';
$l['module_users'] = 'Users';
$l['module_users_singular'] = 'User';
$l['module_categories'] = 'Categories';
$l['module_forums'] = 'Forums';
$l['module_forums_singular'] = 'Forum';
$l['module_forumperms'] = 'Forum Permissions';
$l['module_forumperms_singular'] = 'Forum Permission';
// Yes, this is used twice as the key used for automatic detection is different sometimes. Will be fixed in a later release
$l['module_forum_permissions'] = 'Forum Permissions';
$l['module_forum_permissions_singular'] = 'Forum Permission';
$l['module_moderators'] = 'Moderators';
$l['module_moderators_singular'] = 'Moderator';
$l['module_threads'] = 'Threads';
$l['module_threads_singular'] = 'Thread';
$l['module_posts'] = 'Posts';
$l['module_posts_singular'] = 'Post';
$l['module_attachments'] = 'Attachments';
$l['module_attachments_singular'] = 'Attachment';
$l['module_polls'] = 'Polls';
$l['module_polls_singular'] = 'Poll';
// Yes, this is used twice as the key used for automatic detection is different sometimes. Will be fixed in a later release
$l['module_poll_votes'] = 'Polls';
$l['module_poll_votes_singular'] = 'Poll';
$l['module_pollvotes'] = 'Poll Votes';
$l['module_pollvotes_singular'] = 'Poll Vote';
$l['module_privatemessages'] = 'Private Messages';
$l['module_privatemessages_singular'] = 'Private Message';
// Yes, this is used twice as the key used for automatic detection is different sometimes. Will be fixed in a later release
$l['module_private_messages'] = 'Private Messages';
$l['module_private_messages_singular'] = 'Private Message';
$l['module_events'] = 'Events';
$l['module_events_singular'] = 'Event';
$l['module_icons'] = 'Icons';
$l['module_icons_singular'] = 'Icon';
$l['module_smilies'] = 'Smilies';
$l['module_smilies_singular'] = 'Smilie';
$l['module_settings'] = 'Settings';
$l['module_settings_singular'] = 'Setting';
$l['module_attachtypes'] = 'Attachment Types';
$l['module_attachtypes_singular'] = 'Attachment Type';
*/
$l['module_categories_singular'] = 'Category';

$l['creating_fields'] = "Creating fields for tracking data during the Merge process (This may take a while)...";
$l['creating_table'] = "Creating {1} table.";
$l['creating_columns'] = "Adding {1} columns {2} to table {3}";

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
$l['database_exit'] = "Exit Configuration";
$l['database_check_success'] = "Checking database details... <span style=\"color: green\">success.</span>";
$l['database_success'] = "Successfully configured and connected to the database.";
$l['database_details'] = "Please enter the database details for your installation of {1} you want to merge from.";

$l['wbb_installationnumber'] = "Installationnumber";
$l['wbb_installationnumber_desc'] = "Which was the installationnumber you selected when installing?";

$l['per_screen_config'] = "Options Configuration";
$l['per_screen'] = "Please select how many {1} to import at a time";
$l['per_screen_label'] = "{1} to import at a time";
$l['per_screen_autorefresh'] = "Do you want to automatically continue to the next step until it's finished?";

$l['stats_in_progress'] = "{1} {2} are importing right now. There are {3} {2} left to import and {4} pages left.";
$l['stats'] = "There are {1} {2} that will be imported.";

$l['progress'] = "Inserting {1} #{2}";
$l['progress_merging_user'] = "Merging user #{1} with user #{2}";
$l['progress_settings'] = "Inserting {1} {2} from your other {3} database";
$l['progress_none_left'] = "There are no {1} to import. Please press next to continue.";
$l['progress_none_left_settings'] = "There are no {1} to update. Please press next to continue.";

$l['import_successfully'] = "Successfully imported {1}.";

$l['module_post_rebuilding'] = "Rebuilding Counters";
$l['module_post_rebuild_counters'] = "<br />\nRebuilding thread, forum, and statistic counters...(This may take a while)<br /><br />\n
<br />\nRebuilding thread counters... ";
$l['module_post_thread_counter'] = "Rebuilding counters for thread #{1}";
$l['module_post_rebuilding_forum'] = "Rebuilding forum counters...";
$l['module_post_forum_counter'] = "Rebuilding counters for forum #{1}";
$l['module_post_rebuilding_user'] = "Rebuilding user counters...";
$l['module_post_user_counter'] = "Rebuilding counters for user #{1}";

$l['module_settings_updating'] = "Updating settings {1}";

$l['module_attachment_link'] = "Please type in the link to your {1} forum attachment directory";
$l['module_attachment_label'] = "Link (URL) to your forum attachment directory";
$l['module_attachment_error'] = "Error transfering the attachment (ID: {1})";
$l['module_attachment_not_found'] = "Error could not find the attachment (ID: {1})";
$l['module_attachment_thumbnail_error'] = "Error transfering the attachment thumbnail (ID: {1})";
$l['module_attachment_thumbnail_not_found'] = "Error could not find the attachment thumbnail (ID: {1})";

$l['attmodule_notwritable'] = "The uploads directory (uploads/) is not writable. Please adjust the ";
$l['attmodule_chmod'] = "chmod";
$l['attmodule_notwritable2'] = " permissions to allow it to be written to.";
$l['attmodule_ipadress'] = "You may not use \"localhost\" in the URL. Please use your Internet IP Address (Please make sure Port 80 is open on your firewall and router).";
$l['attmodule_ipadress2'] = "You may not use \"127.0.0.1\" in the URL. Please use your Internet IP Address (Please make sure Port 80 is open on your firewall and router).";
$l['attmodule_notread'] = "The attachments could not be read. Please adjust the ";
$l['attmodule_notread2'] = " permissions to allow it to be read from and ensure the URL is correct. If you are still experiencing issues, please try the full system path instead of a URL (ex: /var/www/htdocs/path/to/your/old/forum/uploads/ or C:/path/to/your/old/forum/upload/). Also ensure access isn\'t being blocked by a htaccess file.";

$l['removing_table'] = "Removing {1} table.";
$l['removing_columns'] = "Removing columns {1} from table {2}";

$l['cleanup_header'] = "MyBB Merge System - Final Step: Cleanup";
$l['cleanup_notice'] = "Performing final cleanup and maintenance (This may take a while)...";

$l['finish_completion'] = "Completion";
$l['finish_head'] = '<p>The current conversion session has been finished.  You may now go to your copy of <a href="../">MyBB</a> or your <a href="../{1}/index.php">Admin Control Panel</a>.</p>
	<p>Please remove this directory if you are not planning on converting any other forums.</p>';
$l['finish_whats_next_head'] = "What's next?";
$l['finish_whats_next'] = 'As it\'s impossible to merge all permissions, settings and counters you need to do a few things now to make sure everything works as expected:
		<ul>
			<li>Rebuild the <a href="../{1}/index.php?module=tools-cache">caches</a></li>
			<li>Run all <a href="../{1}/index.php?module=tools-recount_rebuild">Recount & Rebuild</a> tools</li>
			<li>Check all <a href="../{1}/index.php?module=config">settings</a></li>
			<li>Check the <a href="../{1}/index.php?module=forum">forum</a> and  <a href="../{1}/index.php?module=user-groups">group</a> permissions</li>
		</ul>';
$l['finish_report1'] = "The following will allow you to download a detailed report generated by the converter in several styles.";
$l['finish_report2'] = "Report Generation";
$l['finish_report_type'] = "Please select the report style you wish to generate.";
$l['finish_report_type_txt'] = "Plain Text File";
$l['finish_report_type_html'] = "HTML (Browser Viewable) File";

$l['warning_innodb'] = "The table \"{1}\" is currently in InnoDB format. We strongly recommend converting these tables to MyISAM otherwise you may experience major slow-downs while running the merge system.";

$l['error_invalid_board'] = "The board module you have selected does not exist.";
$l['error_js_off'] = 'It appears that you have javascript turned off. The MyBB Merge System requires that javascript be turned on in order to operate properly. Once you have turned javascript on, please refresh this page.';
$l['error_list'] = "The MyBB Merge System encountered the following problems";
$l['error_click_next'] = "Once you have resolved the mentioned issues, you may continue by pressing \"Next\" below.";

$l['error_database_relative'] = "You may not use relative URLs for SQLite databases. Please use a file system path (ex: /home/user/database.db) for your SQLite database.";
$l['error_database_invalid_engine'] = "You have selected an invalid database engine. Please make your selection from the list below.";
$l['error_database_cant_connect'] = "Could not connect to the database server at '{1}' with the supplied username and password. Are you sure the hostname and user details are correct?";
$l['error_database_wrong_table'] = "The {1} database could not be found in '{2}'.  Please ensure {1} exists at this database and with this table prefix.";
$l['error_database_list'] = "There seems to be one or more errors with the database configuration information that you supplied";
$l['error_database_continue'] = "Once the above are corrected, continue with the conversion.";

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

			<dt>Queries on the {8} database</dt>
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