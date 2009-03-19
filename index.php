<?php
/*********************************************************************************
 * The contents of this file are subject to the SugarCRM Public License Version 1.1.2
 * ("License"); You may not use this file except in compliance with the 
 * License. You may obtain a copy of the License at http://www.sugarcrm.com/SPL
 * Software distributed under the License is distributed on an  "AS IS"  basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is:  SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 ********************************************************************************/
/*********************************************************************************
 * $Header: /advent/projects/wesat/vtiger_crm/sugarcrm/index.php,v 1.93 2005/04/21 16:17:25 ray Exp $
 * Description: Main file and starting point for the application.  Calls the 
 * theme header and footer files defined for the user as well as the module as 
 * defined by the input parameters.
 ********************************************************************************/

global $entityDel;
global $display;
global $category;

if(version_compare(phpversion(), '5.0') < 0) {
        insert_charset_header();
        require_once('phpversionfail.php');
        die();
}

require_once('include/utils/utils.php');

if (version_compare(phpversion(), '5.0') < 0) {
    eval('
    function clone($object) {
      return $object;
    }
    ');
  }

global $currentModule;

 /** Function to  return a string with backslashes stripped off
   * @param $value -- value:: Type string
   * @returns $value -- value:: Type string array
 */
	  
 function stripslashes_checkstrings($value){
        if(is_string($value)){
                return stripslashes($value);
        }
        return $value;

 }
 if(get_magic_quotes_gpc() == 1){
        $_REQUEST = array_map("stripslashes_checkstrings", $_REQUEST);
        $_POST = array_map("stripslashes_checkstrings", $_POST);
        $_GET = array_map("stripslashes_checkstrings", $_GET);

}

// Allow for the session information to be passed via the URL for printing.
if(isset($_REQUEST['PHPSESSID']))
{
	session_id($_REQUEST['PHPSESSID']);
	//Setting the same session id to Forums as in CRM
        $sid=$_REQUEST['PHPSESSID'];
}	

/** Function to set, character set in the header, as given in include/language/*_lang.php
 */
	 
function insert_charset_header()
{
 	global $app_strings, $default_charset;
 	$charset = $default_charset;
 	
 	if(isset($app_strings['LBL_CHARSET']))
 	{
 	        $charset = $app_strings['LBL_CHARSET'];
 	}
		header('Content-Type: text/html; charset='. $charset);
}
 	
insert_charset_header();
// Create or reestablish the current session
session_start();

if (!is_file('config.inc.php')) {
	header("Location: install.php");
	exit();
}

require_once('config.inc.php');
if (!isset($dbconfig['db_hostname']) || $dbconfig['db_status']=='_DB_STAT_') {
		header("Location: install.php");
		exit();
}
			
// load up the config_override.php file.  This is used to provide default user settings
if (is_file('config_override.php')) 
{
	require_once('config_override.php');
}

/**
 * Check for vtiger installed version and codebase
 */
require_once('vtigerversion.php');
global $adb, $vtiger_current_version;
if(isset($_SESSION['VTIGER_DB_VERSION']) && isset($_SESSION['authenticated_user_id'])) {
    if(version_compare($_SESSION['VTIGER_DB_VERSION'], $vtiger_current_version, '!=')) {
        unset($_SESSION['VTIGER_DB_VERSION']);
        header("Location: install.php");
        exit();
    }
} else {
    $exists = $adb->query("SHOW CREATE TABLE vtiger_version");
    if($exists)
    {
        $result = $adb->query("SELECT * FROM vtiger_version");
        $dbversion = $adb->query_result($result, 0, 'current_version');
        if(version_compare($dbversion, $vtiger_current_version, '=')) {
            $_SESSION['VTIGER_DB_VERSION']= $dbversion;
        } else {
            header("Location: install.php");
            exit();
        }   
    }
}
// END

$default_config_values = Array( "allow_exports"=>"all","upload_maxsize"=>"3000000", "listview_max_textlength" => "40", "php_max_execution_time" => "0");

set_default_config($default_config_values);

// Set the default timezone preferred by user
global $default_timezone;
if(isset($default_timezone) && function_exists('date_default_timezone_set')) {
	@date_default_timezone_set($default_timezone);
} 

require_once('include/logging.php');
require_once('modules/Users/Users.php');

global $currentModule;

//if($calculate_response_time) $startTime = microtime();

$log =& LoggerManager::getLogger('index');

global $seclog;
$seclog =& LoggerManager::getLogger('SECURITY');

if (isset($_REQUEST['PHPSESSID'])) $log->debug("****Starting for session ".$_REQUEST['PHPSESSID']);
else $log->debug("****Starting for new session");

// We use the REQUEST_URI later to construct dynamic URLs.  IIS does not pass this field
// to prevent an error, if it is not set, we will assign it to ''
if(!isset($_SERVER['REQUEST_URI']))
{
	$_SERVER['REQUEST_URI'] = '';
}

$action = '';
if(isset($_REQUEST['action']))
{
	$action = $_REQUEST['action'];
}
if($action == 'Export')
{
        include ('include/utils/export.php');
}
if($action == 'ExportAjax')
{
        include ('include/utils/ExportAjax.php');
}
// vtlib customization: Module manager export
if($action == 'ModuleManagerExport') {
	include('modules/Settings/ModuleManager/Export.php');
}
// END

//Code added for 'Path Traversal/File Disclosure' security fix - Philip
$is_module = false;
$is_action = false;
if(isset($_REQUEST['module']))
{
	$module = $_REQUEST['module'];	
	$dir = @scandir($root_directory."modules");
	$temp_arr = Array("CVS","Attic");
	$res_arr = @array_intersect($dir,$temp_arr);
	if(count($res_arr) == 0  && !ereg("[/.]",$module)) {
		if(@in_array($module,$dir))
			$is_module = true;
	}
	$in_dir = @scandir($root_directory."modules/".$module);
	$res_arr = @array_intersect($in_dir,$temp_arr);
	if(count($res_arr) == 0 && !ereg("[/.]",$module)) {
		if(@in_array($action.".php",$in_dir))
			$is_action = true;
	}	
	
	if(!$is_module)
	{
		die("Module name is missing. Please check the module name.");
	}
	if(!$is_action)
	{
		die("Action name is missing. Please check the action name.");
	}
}


//Code added for 'Multiple SQL Injection Vulnerabilities & XSS issue' fixes - Philip
if(isset($_REQUEST['record']) && !is_numeric($_REQUEST['record']) && $_REQUEST['record']!='')
{
        die("An invalid record number specified to view details.");
}

// Check to see if there is an authenticated user in the session.
$use_current_login = false;
if(isset($_SESSION["authenticated_user_id"]) && (isset($_SESSION["app_unique_key"]) && $_SESSION["app_unique_key"] == $application_unique_key))
{
        $use_current_login = true;
}

// Prevent loading Login again if there is an authenticated user in the session.
if (isset($_SESSION["authenticated_user_id"]) && $module == 'Users' && $action == 'Login') {

    header("Location: index.php?action=$default_action&module=$default_module");

} 

if($use_current_login){
	/*&Added to prevent fatal error before starting migration(5.0.4. patch ).
	//Start
	$arr=$adb->getColumnNames("vtiger_users");
	if(!in_array("internal_mailer", $arr))
	{
		$adb->pquery("alter table vtiger_users add column internal_mailer int(3) NOT NULL default '1'", array());
		$adb->pquery("alter table vtiger_users add column tagcloud_view int(1) default 1", array());
	}
	//End*/

	//getting the internal_mailer flag
	if(!isset($_SESSION['internal_mailer'])){
		$qry_res = $adb->pquery("select internal_mailer from vtiger_users where id=?", array($_SESSION["authenticated_user_id"]));
		$_SESSION['internal_mailer'] = $adb->query_result($qry_res,0,"internal_mailer");
	}
	$log->debug("We have an authenticated user id: ".$_SESSION["authenticated_user_id"]);
}else if(isset($action) && isset($module) && $action=="Authenticate" && $module=="Users"){
	$log->debug("We are authenticating user now");
}else{
	if($_REQUEST['action'] != 'Logout' && $_REQUEST['action'] != 'Login'){
		$_SESSION['lastpage'] = $_SERVER['argv'];
	}
	$log->debug("The current user does not have a session.  Going to the login page");	
	$action = "Login";
	$module = "Users";
}


$log->debug($_REQUEST);
$skipHeaders=false;
$skipFooters=false;
$viewAttachment = false;
$skipSecurityCheck= false;
//echo $module;
// echo $action;

if(isset($action) && isset($module))
{
	$log->info("About to take action ".$action);
	$log->debug("in $action");
	if(ereg("^Save", $action) ||
		ereg("^Delete", $action) ||
		ereg("^Choose", $action) ||
		ereg("^Popup", $action) ||
		ereg("^ChangePassword", $action) ||
		ereg("^Authenticate", $action) ||
		ereg("^Logout", $action) ||
		//ereg("^Export",$action) ||
		ereg("^add2db", $action) ||
		ereg("^result", $action) ||
		ereg("^LeadConvertToEntities", $action) ||
		ereg("^downloadfile", $action) ||
		ereg("^massdelete", $action) ||
		ereg("^updateLeadDBStatus",$action) ||
		ereg("^AddCustomFieldToDB", $action) ||
		ereg("^updateRole",$action) ||
		ereg("^UserInfoUtil",$action) ||
		ereg("^deleteRole",$action) ||
		ereg("^UpdateComboValues",$action) ||
		ereg("^fieldtypes",$action) ||
		ereg("^app_ins",$action) ||
		ereg("^minical",$action) ||
		ereg("^minitimer",$action) ||
		ereg("^app_del",$action) ||
		ereg("^send_mail",$action) ||
		ereg("^populatetemplate",$action) ||
		ereg("^TemplateMerge",$action) ||
		ereg("^testemailtemplateusage",$action) ||
		ereg("^saveemailtemplate",$action) ||
		ereg("^ProcessDuplicates", $action ) ||
		ereg("^lastImport", $action ) ||
		ereg("^lookupemailtemplate",$action) ||
		ereg("^deletewordtemplate",$action) ||
		ereg("^deleteemailtemplate",$action) ||
		ereg("^CurrencyDelete",$action) ||
		ereg("^deleteattachments",$action) ||
		ereg("^MassDeleteUsers",$action) ||
		ereg("^UpdateFieldLevelAccess",$action) ||
		ereg("^UpdateDefaultFieldLevelAccess",$action) ||
		ereg("^UpdateProfile",$action)  ||
		ereg("^updateRelations",$action) ||
		ereg("^updateNotificationSchedulers",$action) ||
		ereg("^Star",$action) ||
		ereg("^addPbProductRelToDB",$action) ||
		ereg("^UpdateListPrice",$action) ||
		ereg("^PriceListPopup",$action) ||
		ereg("^SalesOrderPopup",$action) ||
		ereg("^CreatePDF",$action) ||
		ereg("^CreateSOPDF",$action) ||
		ereg("^redirect",$action) ||
		ereg("^webmail",$action) ||
		ereg("^left_main",$action) ||
		ereg("^delete_message",$action) ||
		ereg("^mime",$action) ||
		ereg("^move_messages",$action) ||
		ereg("^folders_create",$action) ||
		ereg("^imap_general",$action) ||
		ereg("^mime",$action) ||
		ereg("^download",$action) ||
		ereg("^about_us",$action) ||
		ereg("^SendMailAction",$action) ||
		ereg("^CreateXL",$action) ||
		ereg("^savetermsandconditions",$action) ||
		ereg("^home_rss",$action) ||
		ereg("^ConvertAsFAQ",$action) ||
		ereg("^Tickerdetail",$action) ||
		ereg("^".$module."Ajax",$action) ||
		ereg("^ActivityAjax",$action) ||
		ereg("^chat",$action) ||
		ereg("^vtchat",$action) ||
		ereg("^updateCalendarSharing",$action) ||
		ereg("^disable_sharing",$action) ||
		ereg("^HeadLines",$action) ||
		ereg("^TodoSave",$action) ||
		ereg("^RecalculateSharingRules",$action) ||
		(ereg("^body",$action) && ereg("^Webmails",$module)) ||
		(ereg("^dlAttachments",$action) && ereg("^Webmails",$module)) ||
		(ereg("^DetailView",$action) &&	ereg("^Webmails",$module) ) ||
		ereg("^savewordtemplate",$action) ||
		ereg("^mailmergedownloadfile",$action) || ereg("^Webmails",$module) && ereg("^get_img",$action) || ereg("^download",$action) || 
		ereg("^getListOfRecords", $action) ||
		ereg("^MoveBlockFieldToDB", $action) ||
		ereg("^AddBlockFieldToDB", $action) ||
		ereg("^AddBlockToDB", $action)  ||
		ereg("^MassEditSave", $action)
		)
	{
		$skipHeaders=true;
		//skip headers for all these invocations as they are mostly popups
		if(ereg("^Popup", $action) ||
			ereg("^ChangePassword", $action) ||
			//ereg("^Export", $action) ||
			ereg("^downloadfile", $action) ||
			ereg("^fieldtypes",$action) ||
			ereg("^lookupemailtemplate",$action) ||
			ereg("^about_us",$action) ||
			ereg("^home_rss",$action) ||
			ereg("^".$module."Ajax",$action) ||
			ereg("^chat",$action) ||
			ereg("^vtchat",$action) ||
			ereg("^massdelete", $action) ||
			ereg("^mailmergedownloadfile",$action) || 	ereg("^get_img",$action) ||
			ereg("^download",$action) ||
			ereg("^ProcessDuplicates", $action ) ||
			ereg("^lastImport", $action ) ||
			ereg("^massdelete", $action ) ||
			ereg("^getListOfRecords", $action) ||
			ereg("^MassEditSave", $action))
			$skipFooters=true;
		//skip footers for all these invocations as they are mostly popups
		if(ereg("^downloadfile", $action) || ereg("^fieldtypes",$action) || ereg("^mailmergedownloadfile",$action)|| ereg("^get_img",$action) || ereg("^MergeFieldLeads", $action) || ereg("^MergeFieldContacts", $action ) || ereg("^MergeFieldAccounts", $action ) || ereg("^MergeFieldProducts", $action ) || ereg("^MergeFieldHelpDesk", $action ) || ereg("^MergeFieldPotentials", $action ) || ereg("^MergeFieldVendors", $action ))
		{
			$viewAttachment = true;
		}
		if(($action == ' Delete ') && (!$entityDel))
		{
			$skipHeaders=false;
		}
	}
	
	if($action == 'Save')
	{
 	         header( "Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
 	         header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" );
 	         header( "Cache-Control: no-cache, must-revalidate" );
 	         header( "Pragma: no-cache" );        
 	}

        if($module == 'Users' || $module == 'Home' || $module == 'uploads')
        {
          $skipSecurityCheck=true;
        }

    if($action == 'UnifiedSearch') {
    	$currentModuleFile = 'modules/Home/'.$action.'.php';
    } else {
		$currentModuleFile = 'modules/'.$module.'/'.$action.'.php';
	}
	$currentModule = $module;
	
      	
}
elseif(isset($module))
{
	
	$currentModule = $module;
	$currentModuleFile = $moduleDefaultFile[$currentModule];
}
else {
    // use $default_module and $default_action as set in config.php
    // Redirect to the correct module with the correct action.  We need the URI to include these fields.
	  

        header("Location: index.php?action=$default_action&module=$default_module");
    exit();
}

$log->info("current page is $currentModuleFile");	
$log->info("current module is $currentModule ");	


// for printing
$module = (isset($_REQUEST['module'])) ? $_REQUEST['module'] : "";
$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : "";
$record = (isset($_REQUEST['record'])) ? $_REQUEST['record'] : "";
$lang_crm = (isset($_SESSION['authenticated_user_language'])) ? $_SESSION['authenticated_user_language'] : "";
$GLOBALS['request_string'] = "&module=$module&action=$action&record=$record&lang_crm=$lang_crm";

$current_user = new Users();

if($use_current_login)
{
	//$result = $current_user->retrieve($_SESSION['authenticated_user_id']);
	//getting the current user info from flat file
	$result = $current_user->retrieveCurrentUserInfoFromFile($_SESSION['authenticated_user_id']);

	if($result == null)
	{
		session_destroy();
	    header("Location: index.php?action=Login&module=Users");
	}

	$moduleList = getPermittedModuleNames();

        foreach ($moduleList as $mod) {
                $moduleDefaultFile[$mod] = "modules/".$currentModule."/index.php";
        }

	//auditing

	require_once('user_privileges/audit_trail.php');
	
	if($audit_trail == 'true')
	{
		if($record == '')
			$auditrecord = '';						
		else
			$auditrecord = $record;	

		$date_var = $adb->formatDate(date('Y-m-d H:i:s'), true);
		if ($action != 'chat')
		{	
			$query = "insert into vtiger_audit_trial values(?,?,?,?,?,?)";
			$qparams = array($adb->getUniqueID('vtiger_audit_trial'), $current_user->id, $module, $action, $auditrecord, $date_var);
			$adb->pquery($query, $qparams);
		}	
	}	

	$log->debug('Current user is: '.$current_user->user_name);
}

if(isset($_SESSION['vtiger_authenticated_user_theme']) && $_SESSION['vtiger_authenticated_user_theme'] != '')
{
	$theme = $_SESSION['vtiger_authenticated_user_theme'];
}
else 
{
	$theme = $default_theme;
}
$log->debug('Current theme is: '.$theme);

//Used for current record focus
$focus = "";

// if the language is not set yet, then set it to the default language.
if(isset($_SESSION['authenticated_user_language']) && $_SESSION['authenticated_user_language'] != '')
{
	$current_language = $_SESSION['authenticated_user_language'];
}
else 
{
	$current_language = $default_language;
}
$log->debug('current_language is: '.$current_language);

//set module and application string arrays based upon selected language
$app_strings = return_application_language($current_language);
$app_list_strings = return_app_list_strings_language($current_language);
$mod_strings = return_module_language($current_language, $currentModule);

//If DetailView, set focus to record passed in
if($action == "DetailView")
{
	if(!isset($_REQUEST['record']))
		die("A record number must be specified to view details.");

	// If we are going to a detail form, load up the record now.
	// Use the record to track the viewing.
	// todo - Have a record of modules and thier primary object names.
	//Getting the actual module
	switch($currentModule)
	{
		case 'Calendar':
			require_once("modules/$currentModule/Activity.php");
			$focus = new Activity();
			break;
		case 'Webmails':
			//No need to create a webmail object here
			break;
		default:
			require_once("modules/$currentModule/$currentModule.php");
			$focus = new $currentModule();
			break;
		}
	
	if(isset($_REQUEST['record']) && $_REQUEST['record']!='' && $_REQUEST["module"] != "Webmails" && $current_user->id != '')
        {
                // Only track a viewing if the record was retrieved.
                $focus->track_view($current_user->id, $currentModule,$_REQUEST['record']);
        }

}	

// set user, theme and language cookies so that login screen defaults to last values
if (isset($_SESSION['authenticated_user_id'])) {
        $log->debug("setting cookie ck_login_id_vtiger to ".$_SESSION['authenticated_user_id']);
        setcookie('ck_login_id_vtiger', $_SESSION['authenticated_user_id']);
}
if (isset($_SESSION['vtiger_authenticated_user_theme'])) {
        $log->debug("setting cookie ck_login_theme_vtiger to ".$_SESSION['vtiger_authenticated_user_theme']);
        setcookie('ck_login_theme_vtiger', $_SESSION['vtiger_authenticated_user_theme']);
}
if (isset($_SESSION['authenticated_user_language'])) {
        $log->debug("setting cookie ck_login_language_vtiger to ".$_SESSION['authenticated_user_language']);
        setcookie('ck_login_language_vtiger', $_SESSION['authenticated_user_language']);
}

if($_REQUEST['module'] == 'Documents' && $action == 'DownloadFile')
{
	include('modules/Documents/DownloadFile.php');
	exit;
}

//skip headers for popups, deleting, saving, importing and other actions
if(!$skipHeaders) {
	$log->debug("including headers");
	if($use_current_login)
	{
		if(isset($_REQUEST['category']) && $_REQUEST['category'] !='')
		{
			$category = $_REQUEST['category'];
		}
		else
		{
			$category = getParentTabFromModule($currentModule);
		}
		include('themes/'.$theme.'/header.php');
	}
	else 
		include('themes/'.$theme.'/loginheader.php');
	
	if(isset($_SESSION['administrator_error']))
	{
		// only print DB errors once otherwise they will still look broken after they are fixed.
		// Only print the errors for admin users.
		if(is_admin($current_user)) 
			echo $_SESSION['administrator_error'];
		unset($_SESSION['administrator_error']);
	}
	
	echo "<!-- startscrmprint -->";
}
else {
		$log->debug("skipping headers");
}



//fetch the permission set from session and search it for the requisite data

if(isset($_SESSION['vtiger_authenticated_user_theme']) && $_SESSION['vtiger_authenticated_user_theme'] != '')
{
	$theme = $_SESSION['vtiger_authenticated_user_theme'];
}
else 
{
	$theme = $default_theme;
}


//logging the security Information
$seclog->debug('########  Module -->  '.$module.'  :: Action --> '.$action.' ::  UserID --> '.$current_user->id.' :: RecordID --> '.$record.' #######');

if(!$skipSecurityCheck)
{


	require_once('include/utils/UserInfoUtil.php');
	if(ereg('Ajax',$action))
        {
                $now_action=$_REQUEST['file'];
        }
        else
        {
                $now_action=$action;
        }
        

        if(isset($_REQUEST['record']) && $_REQUEST['record'] != '')
        {
                $display = isPermitted($module,$now_action,$_REQUEST['record']);
        }
        else
        {
                $display = isPermitted($module,$now_action);
        }	
	$seclog->debug('########### Pemitted ---> '.$display.'  ##############');

}
else
{
	$seclog->debug('########### Pemitted ---> yes  ##############');
}


if($display == "no")
{
	echo "<link rel='stylesheet' type='text/css' href='themes/$theme/style.css'>";	
	echo "<table border='0' cellpadding='5' cellspacing='0' width='100%' height='450px'><tr><td align='center'>";
	echo "<div style='border: 3px solid rgb(153, 153, 153); background-color: rgb(255, 255, 255); width: 55%; position: relative; z-index: 10000000;'>

		<table border='0' cellpadding='5' cellspacing='0' width='98%'>
		<tbody><tr>
		<td rowspan='2' width='11%'><img src='". vtiger_imageurl('denied.gif', $theme) . "' ></td>
		<td style='border-bottom: 1px solid rgb(204, 204, 204);' nowrap='nowrap' width='70%'><span class='genHeaderSmall'>$app_strings[LBL_PERMISSION]</span></td>
		</tr>
		<tr>
		<td class='small' align='right' nowrap='nowrap'>			   	
		<a href='javascript:window.history.back();'>$app_strings[LBL_GO_BACK]</a><br>								   						     </td>
		</tr>
		</tbody></table> 
		</div>";
	echo "</td></tr></table>";
} 
// vtlib customization: Check if module has been de-activated
else if(!vtlib_isModuleActive($currentModule)) {
	echo "<link rel='stylesheet' type='text/css' href='themes/$theme/style.css'>";	
	echo "<table border='0' cellpadding='5' cellspacing='0' width='100%' height='450px'><tr><td align='center'>";
	echo "<div style='border: 3px solid rgb(153, 153, 153); background-color: rgb(255, 255, 255); width: 55%; position: relative; z-index: 10000000;'>

		<table border='0' cellpadding='5' cellspacing='0' width='98%'>
		<tbody><tr>
		<td rowspan='2' width='11%'><img src='". vtiger_imageurl('denied.gif', $theme) . "' ></td>
		<td style='border-bottom: 1px solid rgb(204, 204, 204);' nowrap='nowrap' width='70%'><span class='genHeaderSmall'>$currentModule $app_strings[VTLIB_MOD_NOT_ACTIVE]</span></td>
		</tr>
		<tr>
		<td class='small' align='right' nowrap='nowrap'>			   	
		<a href='javascript:window.history.back();'>$app_strings[LBL_GO_BACK]</a><br>								   						     </td>
		</tr>
		</tbody></table> 
		</div>";
	echo "</td></tr></table>";
}
// END
else
{
	include($currentModuleFile);
}

	if((!$viewAttachment) && (!$viewAttachment && $action != 'home_rss' && $action != $module."Ajax" && $action != "chat" && $action != 'massdelete' && $action != "body") )
	{
		echo "<!-- stopscrmprint -->";
	}

//added to get the theme . This is a bad fix as we need to know where the problem lies yet
if(isset($_SESSION['vtiger_authenticated_user_theme']) && $_SESSION['vtiger_authenticated_user_theme'] != '')
{
        $theme = $_SESSION['vtiger_authenticated_user_theme'];
}
else
{
        $theme = $default_theme;
}
$Ajx_module= $module;
if($module == 'Events')
	$Ajx_module = 'Calendar';
if((!$viewAttachment) && (!$viewAttachment && $action != 'home_rss') && $action != 'Tickerdetail' && $action != $Ajx_module."Ajax" && $action != "chat" && $action != "HeadLines" && $action != 'massdelete'  &&  $action != "DashboardAjax" && $action != "ActivityAjax")
{
	// Under the SPL you do not have the right to remove this copyright statement.	
	$copyrightstatement="<style>
		.bggray
		{
			background-color: #dfdfdf;
		}
	.bgwhite
	{
		background-color: #FFFFFF;
	}
	.copy
	{
		font-size:9px;
		font-family: Verdana, Arial, Helvetica, Sans-serif;
	}
	</style>
		<script language=javascript>
		function LogOut(e)
		{
			var nav4 = window.Event ? true : false;
			var iX,iY;
			if (nav4)
			{
				iX = e.pageX;
				iY = e.pageY;
			}
			else
			{
				iX = event.clientX + document.body.scrollLeft;
				iY = event.clientY + document.body.scrollTop;

			}
			if (iX <= 30 && iY < 0 )
			{
				w=window.open(\"index.php?action=Logout&module=Users\");
				w.close();
			}
		}
	//window.onunload=LogOut
	</script>
		";

	if((!$skipFooters) && $action != "about_us" && $action != "vtchat" && $action != "ChangePassword" && $action != "body" && $action != $module."Ajax" && $action!='Popup' && $action != 'ImportStep3' && $action != 'ActivityAjax' && $action != 'getListOfRecords')
	
	{
		echo $copyrightstatement;
		// Status tracking
		$statimage = '';
		if($currentModule == 'Users' && empty($current_user->id)) {
			$statimage = "<img src='http://stats.vtiger.com/stats.php?uid=$application_unique_key&v=$vtiger_current_version&type=U' 
				alt='|' title='' border=0 width='1px' height='1px'>";
		}
		// END
		echo "<script language = 'JavaScript' type='text/javascript' src = 'include/js/popup.js'></script>";
		echo "<br><br><br><table border=0 cellspacing=0 cellpadding=5 width=100% class=settingsSelectedUI >";
		echo "<tr><td class=small align=left><span style='color: rgb(153, 153, 153);'>vtiger CRM $vtiger_current_version</span></td>";
		echo "<td class=small align=right><span style='color: rgb(153, 153, 153);'>&copy; 2004-".date('Y')." <a href='http://www.vtiger.com' target='_blank'>vtiger.com</a> | <a href='javascript:mypopup()'>".$app_strings['LNK_READ_LICENSE']."</a> | <a href='http://www.vtiger.com/products/crm/privacy_policy.html' target='_blank'>".getTranslatedString('LNK_PRIVACY_POLICY')."</a></span> $statimage</td></tr></table>";
			
	//	echo "<table align='center'><tr><td align='center'>";
		// Under the Sugar Public License referenced above, you are required to leave in all copyright statements
		// in both the code and end-user application.
	//	if($calculate_response_time)
	//	{
	//		$endTime = microtime();

	//		$deltaTime = microtime_diff($startTime, $endTime);
	//		echo('&nbsp;Server response time: '.$deltaTime.' seconds.');
	//	}
	//	echo "</td></tr></table>\n";
	}
	if(($action != 'mytkt_rss') && ($action != 'home_rss') && ($action != $module."Ajax") && ($action != "body") && ($action != 'ActivityAjax'))
	{
	?>
		<script>
			var userDateFormat = "<?php echo $current_user->date_format ?>";
			var default_charset = "<?php echo $default_charset; ?>";
		</script>
<?php
	}
	// ActivityReminder Customization for callback
	if($current_user->id != ''){
		$test = $adb->pquery("select * from vtiger_users where id=?",array($current_user->id));
		$reminder_next=$adb->query_result($test,0,"reminder_next_time");
		$ar_temp=strtotime($reminder_next.":00");
		$reminder_next1=$adb->query_result($test,0,"date_entered");
		$ar_temp1 = strtotime($reminder_next1);	
		
		if($ar_temp1 > $ar_temp)
		{
			$adb->pquery("UPDATE vtiger_users set reminder_next_time=? where date_entered=?",array(date('Y-m-d H:i',$ar_temp1), $reminder_next1));
		}
	}
	if(!$skipFooters) {
		if($current_user->id!=NULL && isPermitted('Calendar','index') == 'yes')
			echo "<script type='text/javascript'>if(typeof(ActivityReminderCallback) != 'undefined') ActivityReminderCallback();</script>";
	}
	// End
	
	if((!$skipFooters) && ($action != "body") && ($action != $module."Ajax") && ($action != "ActivityAjax"))
		include('themes/'.$theme.'/footer.php');
}
?>
