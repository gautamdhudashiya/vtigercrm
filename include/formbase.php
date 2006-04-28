<?PHP
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
 * $Header$
 * Description:  is a form helper
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/
 
 
function checkRequired($prefix, $required){
	global $log;
	$log->debug("Entering checkRequired(".$prefix.",". $required.") method ...");
	foreach($required as $key){
		if(!isset($_POST[$prefix.$key]) ||empty($_POST[$prefix.$key])){
			$log->debug("Exiting checkRequired method ...");
			return false;	
		}	
	}
	$log->debug("Exiting checkRequired method ...");
	return true;
}
function populateFromPost($prefix, $focus){
	global $log;
	$log->debug("Entering populateFromPost(".$prefix.",". $focus.") method ...");
	$focus->retrieve($_POST[$prefix.'record']);
	 foreach($focus->column_fields as $field)
		{
			if(isset($_POST[$prefix.$field]))
			{
				$focus->$field = $_POST[$prefix.$field];		
			}
		}
		foreach($focus->additional_column_fields as $field)
		{
			if(isset($_POST[$prefix.$field]))
			{
				$value = $_POST[$prefix.$field];
				$focus->$field = $value;
			}
		}
		$log->debug("Exiting populateFromPost method ...");
		return $focus;

}

function getPostToForm(){
	global $log;
	$log->debug("Entering getPostToForm() method ...");
	$fields = '';
	foreach ($_POST as $key=>$value){
		$fields.= "<input type='hidden' name='$key' value='$value'>";
	}
	$log->debug("Exiting getPostToForm method ...");
	return $fields;
	
}

function handleRedirect($return_id, $return_module){
	global $log;
	$log->debug("Entering handleRedirect(".$return_id.",". $return_module.") method ...");
	if(isset($_POST['return_module']) && $_POST['return_module'] != "") $return_module = $_POST['return_module'];
	else $return_module = $return_module;
	if(isset($_POST['return_action']) && $_POST['return_action'] != "") $return_action = $_POST['return_action'];
	else $return_action = "DetailView";
	if(isset($_POST['return_id']) && $_POST['return_id'] != "") $return_id = $_POST['return_id'];
	$log->debug("Exiting handleRedirect method ...");
	header("Location: index.php?action=$return_action&module=$return_module&record=$return_id");
}
	

