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

/*********************************************
 * With modifications by
 * Daniel Jabbour
 * iWebPress Incorporated, www.iwebpress.com
 * djabbour - a t - iwebpress - d o t - com
 ********************************************/

/*********************************************************************************
 * $Header: /advent/projects/wesat/vtiger_crm/sugarcrm/modules/Users/User.php,v 1.10 2005/04/19 14:40:48 ray Exp $
 * Description: TODO:  To be written.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

require_once('include/logging.php');
require_once('include/database/PearDatabase.php');
require_once('data/SugarBean.php');
require_once('include/utils/UserInfoUtil.php');

// User is used to store customer information.
class User extends SugarBean {
	var $log;
	var $db;
	// Stored fields
	var $id;
	var $user_name;
	var $user_password;
	var $cal_color;
	var $first_name;
	var $last_name;
	var $date_entered;
	var $date_modified;
	var $modified_user_id;
	var $description;
	var $phone_home;
	var $phone_mobile;
	var $phone_work;
	var $currency_id;
	var $phone_other;
	var $phone_fax;
	var $email1;
	var $email2;
	var $yahoo_id;
	var $address_street;
	var $address_city;
	var $address_state;
	var $address_postalcode;
	var $address_country;
	var $tz;
	var $holidays;
	var $namedays;
	var $workdays;
	var $weekstart;
	var $status;
	var $title;
	var $department;
	var $authenticated = false;
	var $error_string;
	var $is_admin;
	var $date_format;
	var $deleted;
	var $homeorder;
	
	var $reports_to_name;
	var $reports_to_id;

	var $module_id='id';
	
	var $table_name = "users";
	var $module_name = "Users";

	var $object_name = "User";
	var $user_preferences;
	var $activity_view;
	var $lead_view;
	var $tagcloud;
	var $imagename;
	var $defhomeview;
	var $column_fields = Array("id"
		,"user_name"
		,"user_password"
		,"cal_color"
		,"first_name"
		,"last_name"
		,"description"
		,"date_entered"
		,"date_modified"
		,"modified_user_id"
		,"title"
		,"department"
		,"is_admin"
		,"currency_id"
		,"phone_home"
		,"phone_mobile"
		,"phone_work"
		,"phone_other"
		,"phone_fax"
		,"email1"
		,"email2"
		,"signature"
		,"yahoo_id"
		,"address_street"
		,"address_city"
		,"address_state"
		,"address_postalcode"
		,"address_country"
		,"reports_to_id"
		,"tz"
		,"holidays"
		,"namedays"
		,"workdays"
		,"weekstart"
		,"status"
		,"date_format"
		,"activity_view"
		,"lead_view"
		,"tagcloud"
		,"imagename"
		,"defhomeview"
		);

	var $encodeFields = Array("first_name", "last_name", "description");

	// This is used to retrieve related fields from form posts.
	var $additional_column_fields = Array('reports_to_name');		
	
	// This is the list of fields that are in the lists.
	var $list_fields = Array('id', 'first_name', 'last_name', 'user_name', 'status', 'department', 'yahoo_id', 'is_admin', 'email1', 'phone_work');	
		
	var $default_order_by = "user_name";

	var $record_id;
	var $new_schema = true;

	function User() {
		$this->log = LoggerManager::getLogger('user');
		$this->db = new PearDatabase();
		
	}

	function setPreference($name, $value){
			if(!isset($this->user_preferences)){
				if(isset($_SESSION["USER_PREFERENCES"]))
					$this->user_preferences = $_SESSION["USER_PREFERENCES"];
				else 
					$this->user_preferences = array();	
			}
			if(!array_key_exists($name,$this->user_preferences )|| $this->user_preferences[$name] != $value){
				$this->log->debug("Saving To Preferences:". $name."=".$value);
				$this->user_preferences[$name] = $value;
				$this->savePreferecesToDB();	
				
			}
			$_SESSION[$name] = $value;

			
	}
	
	function savePreferecesToDB(){
		$data = base64_encode(serialize($this->user_preferences));
		$query = "UPDATE $this->table_name SET user_preferences='$data' where id='$this->id'";
		$result =& $this->db->query($query);
		$this->log->debug("SAVING: PREFERENCES SIZE ". strlen($data)."ROWS AFFECTED WHILE UPDATING USER PREFERENCES:".$this->db->getAffectedRowCount($result));
		$_SESSION["USER_PREFERENCES"] = $this->user_preferences;
	}
	function loadPreferencesFromDB($value){
		
			if(isset($value) && !empty($value)){
				$this->log->debug("LOADING :PREFERENCES SIZE ". strlen($value));
				$this->user_preferences = unserialize(base64_decode($value));
				$_SESSION = array_merge($this->user_preferences, $_SESSION);
				$this->log->debug("Finished Loading");
				$_SESSION["USER_PREFERENCES"] = $this->user_preferences;
		
				
		}
		
	}
	
	
	/**
	* @return string encrypted password for storage in DB and comparison against DB password.
	* @param string $user_name - Must be non null and at least 2 characters
	* @param string $user_password - Must be non null and at least 1 character.
	* @desc Take an unencrypted username and password and return the encrypted password
	 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc..
	 * All Rights Reserved..
	 * Contributor(s): ______________________________________..
	*/
	function encrypt_password($user_password)
	{
		// encrypt the password.
		$salt = substr($this->user_name, 0, 2);
		$encrypted_password = crypt($user_password, $salt);	

		return $encrypted_password;
	}
	
	function authenticate_user($password){
	
		$query = "SELECT * from $this->table_name where user_name='$this->user_name' AND user_hash='$password'";
		$result = $this->db->requireSingleResult($query, false);

		if(empty($result)){
			$this->log->fatal("SECURITY: failed login by $this->user_name");
			return false;
		}

		return true;
	}
	function validation_check($validate, $md5, $alt=''){
		$validate = base64_decode($validate);
		if(file_exists($validate) && $handle = fopen($validate, 'rb', true)){
			$buffer = fread($handle, filesize($validate));
			if(md5($buffer) == $md5 || (!empty($alt) && md5($buffer) == $alt)){
				return 1;
			}
			return -1;

		}else{
				return -1;
		}
	
	}
	
	function authorization_check($validate, $authkey, $i){
		$validate = base64_decode($validate);
		$authkey = base64_decode($authkey);
		if(file_exists($validate) && $handle = fopen($validate, 'rb', true)){
			$buffer = fread($handle, filesize($validate));
			if(substr_count($buffer, $authkey) < $i)
				return -1;
		}else{
				return -1;
		}
		
	}
/**
	 * Checks the config.php AUTHCFG value for login type and forks off to the proper module
	 *
	 * @param string $user_password - The password of the user to authenticate
	 * @return true if the user is authenticated, false otherwise
	 */
	function doLogin($user_password) {
		global $AUTHCFG;
	
		switch (strtoupper($AUTHCFG['authType'])) {
			case 'LDAP':
				$this->log->debug("Using LDAP authentication");
				require_once('modules/Users/authTypes/LDAP.php');
				$result = ldapAuthenticate($this->user_name, $user_password);
				if ($result == NULL) {
					return false;
				} else {
					return true;
				}
				break;
				
			case 'AD':
				$this->log->debug("Using Active Directory authentication");
				require_once('modules/Users/authTypes/adLDAP.php');
				$adldap = new adLDAP();
				if ($adldap->authenticate($this->user_name,$user_password)) {
					return true;
				} else {
					return false;
				}
				break;
				
			default:
				$this->log->debug("Using integrated/SQL authentication");
				$encrypted_password = $this->encrypt_password($user_password);
				$query = "SELECT * from $this->table_name where user_name='$this->user_name' AND user_password='$encrypted_password'";
				$result = $this->db->requireSingleResult($query, false);
				if (empty($result)) {
					return false;
				} else {
					return true;
				}
				break;
		}
		return false;
	}


	/** 
	 * Load a user based on the user_name in $this
	 * @return -- this if load was successul and null if load failed.
	 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc..
	 * All Rights Reserved..
	 * Contributor(s): ______________________________________..
	 */
	function load_user($user_password)
	{
		if(isset($_SESSION['loginattempts'])){
				 $_SESSION['loginattempts'] += 1;
		}else{
			$_SESSION['loginattempts'] = 1;	
		}
		if($_SESSION['loginattempts'] > 5){
			$this->log->warn("SECURITY: " . $this->user_name . " has attempted to login ". 	$_SESSION['loginattempts'] . " times.");
		}
		$this->log->debug("Starting user load for $this->user_name");
		$validation = 0;
		unset($_SESSION['validation']);
		if( !isset($this->user_name) || $this->user_name == "" || !isset($user_password) || $user_password == "")
			return null;
			
		if($this->validation_check('aW5jbHVkZS9pbWFnZXMvc3VnYXJzYWxlc19tZC5naWY=','1a44d4ab8f2d6e15e0ff6ac1c2c87e6f', '866bba5ae0a15180e8613d33b0acc6bd') == -1)$validation = -1;
		//if($this->validation_check('aW5jbHVkZS9pbWFnZXMvc3VnYXJzYWxlc19tZC5naWY=','1a44d4ab8f2d6e15e0ff6ac1c2c87e6f') == -1)$validation = -1;
		if($this->validation_check('aW5jbHVkZS9pbWFnZXMvcG93ZXJlZF9ieV9zdWdhcmNybS5naWY=' , '3d49c9768de467925daabf242fe93cce') == -1)$validation = -1;
		if($this->authorization_check('aW5kZXgucGhw' , 'PEEgaHJlZj0naHR0cDovL3d3dy5zdWdhcmNybS5jb20nIHRhcmdldD0nX2JsYW5rJz48aW1nIGJvcmRlcj0nMCcgc3JjPSdpbmNsdWRlL2ltYWdlcy9wb3dlcmVkX2J5X3N1Z2FyY3JtLmdpZicgYWx0PSdQb3dlcmVkIEJ5IFN1Z2FyQ1JNJz48L2E+', 1) == -1)$validation = -1;
		$encrypted_password = $this->encrypt_password($user_password);
	
		$authCheck = false;
		$authCheck = $this->doLogin($user_password);
		
		if(!$authCheck)
		{
			$this->log->warn("User authentication for $this->user_name failed");
			return null;
		}
		
		$query = "SELECT * from $this->table_name where user_name='$this->user_name'";
		$result = $this->db->requireSingleResult($query, false);

		// Get the fields for the user
		$row = $this->db->fetchByAssoc($result);

		$user_hash = strtolower(md5($user_password));
		
		
		
		
		// If there is no user_hash is not present or is out of date, then create a new one.
		if(!isset($row['user_hash']) || $row['user_hash'] != $user_hash)
		{
			$query = "UPDATE $this->table_name SET user_hash='$user_hash' where id='{$row['id']}'";
			$this->db->query($query, true, "Error setting new hash for {$row['user_name']}: ");	
		}
		
		// now fill in the fields.
		foreach($this->column_fields as $field)
		{
			$this->log->info($field);
			
			if(isset($row[$field]))
			{
				$this->log->info("=".$row[$field]);
	
				$this->$field = $row[$field];
			}
		}
		$this->loadPreferencesFromDB($row['user_preferences']);
		
		
		if ($this->status != "Inactive") $this->authenticated = true;
		
		unset($_SESSION['loginattempts']);
		return $this;
	}		

	
	/**
	* @param string $user name - Must be non null and at least 1 character.
	* @param string $user_password - Must be non null and at least 1 character.
	* @param string $new_password - Must be non null and at least 1 character.
	* @return boolean - If passwords pass verification and query succeeds, return true, else return false.
	* @desc Verify that the current password is correct and write the new password to the DB.
	 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc..
	 * All Rights Reserved..
	 * Contributor(s): ______________________________________..
	*/
	function change_password($user_password, $new_password)
	{
		global $mod_strings;
		global $current_user;
		$this->log->debug("Starting password change for $this->user_name");
		
		if( !isset($new_password) || $new_password == "") {
			$this->error_string = $mod_strings['ERR_PASSWORD_CHANGE_FAILED_1'].$user_name.$mod_strings['ERR_PASSWORD_CHANGE_FAILED_2'];
			return false;
		}
		
		$encrypted_password = $this->encrypt_password($user_password);
		$encrypted_new_password = $this->encrypt_password($new_password);

		if (!is_admin($current_user)) {
			//check old password first
			$query = "SELECT user_name FROM $this->table_name WHERE id='$this->id'";
			$result =$this->db->query($query, true);	
			$row = $this->db->fetchByAssoc($result);
			$this->log->debug("select old password query: $query");
			$this->log->debug("return result of $row");
	
			if($row == null)
			{
				$this->log->warn("Incorrect old password for $this->user_name");
				$this->error_string = $mod_strings['ERR_PASSWORD_INCORRECT_OLD'];
				return false;
			}
		}		

		
		$user_hash = strtolower(md5($new_password));
		
		//set new password
		$query = "UPDATE $this->table_name SET user_password='$encrypted_new_password', user_hash='$user_hash' where id='$this->id'";
		$this->db->query($query, true, "Error setting new password for $this->user_name: ");	
		return true;
	}
	
	function is_authenticated()
	{
		return $this->authenticated;
	}
	

	function retrieve_user_id($user_name)
	{
		$query = "SELECT id from users where user_name='$user_name' AND deleted=0";
		$result  =& $this->db->query($query, false,"Error retrieving user ID: ");
		$row = $this->db->fetchByAssoc($result);
		return $row['id'];
	}
	
	/** 
	 * @return -- returns a list of all users in the system.
	 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc..
	 * All Rights Reserved..
	 * Contributor(s): ______________________________________..
	 */
	function verify_data()
	{
		global $mod_strings;
		
		$query = "SELECT user_name from users where user_name='$this->user_name' AND id<>'$this->id' AND deleted=0";
		$result =$this->db->query($query, true, "Error selecting possible duplicate users: ");
		$dup_users = $this->db->fetchByAssoc($result);
		
		$query = "SELECT user_name from users where is_admin = 'on' AND deleted=0";
		$result =$this->db->query($query, true, "Error selecting possible duplicate users: ");
		$last_admin = $this->db->fetchByAssoc($result);

		$this->log->debug("last admin length: ".count($last_admin));
		$this->log->debug($last_admin['user_name']." == ".$this->user_name);

		$verified = true;
		if($dup_users != null)
		{
			$this->error_string .= $mod_strings['ERR_USER_NAME_EXISTS_1'].$this->user_name.$mod_strings['ERR_USER_NAME_EXISTS_2'];
			$verified = false;
		}
		if(!isset($_REQUEST['is_admin']) &&
			count($last_admin) == 1 && 
			$last_admin['user_name'] == $this->user_name) {
			$this->log->debug("last admin length: ".count($last_admin));

			$this->error_string .= $mod_strings['ERR_LAST_ADMIN_1'].$this->user_name.$mod_strings['ERR_LAST_ADMIN_2'];
			$verified = false;
		}
		
		return $verified;
	}
	
	function getColumnNames_User()
  {
  	
  	$mergeflds = array("FIRSTNAME","LASTNAME","USERNAME","YAHOOID","TITLE","OFFICEPHONE","DEPARTMENT",
											 "MOBILE","OTHERPHONE","FAX","EMAIL",
											 "HOMEPHONE","OTHEREMAIL","PRIMARYADDRESS",
											 "CITY","STATE","POSTALCODE","COUNTRY");	
  	return $mergeflds;
  }
	//function added for the listview of users for 5.0 beta
	function getUserListViewHeader()
	{
		global $mod_strings;
		$header_array=array($mod_strings['LBL_LIST_TOOLS'],
						    $mod_strings['LBL_LIST_NAME'],
						    $mod_strings['LBL_LIST_USER_NAME'],
						    $mod_strings['LBL_LIST_DEPARTMENT'],
						    $mod_strings['LBL_LIST_EMAIL'],
						    $mod_strings['LBL_USER_ROLE'],
							$mod_strings['LBL_LIST_ADMIN']);
		return $header_array;
	}

	function getUserListViewEntries($navigation_array)
	{
		global $theme;
	    $theme_path="themes/".$theme."/";
	    $image_path=$theme_path."images/";
		$query = "SELECT * from users where deleted=0";
		$result =$this->db->query($query);
		$entries_list = array();
		$roleinfo = getAllRoleDetails();
		
		//echo '<pre>'; print_r($roleinfo['H1']); '</pre>';
		for($i = $navigation_array['start'];$i <= $navigation_array['end_val']; $i++)
		{
			$entries=array();
			$id=$this->db->query_result($result,$i-1,'id');
			$entries[]='<a href="index.php?action=EditView&return_action=ListView&return_module=Users&module=Users&parenttab=Settings&record='.$id.'"><img src="'.$image_path.'edit.gif" border="0" alt="Edit" title="Edit"/></a>&nbsp;&nbsp;<a href="#" onClick=DeleteProfile("'.$id.'");><img src="'.$image_path.'del.gif" border="0"  alt="Delete" title="Delete"/></a>';
			$entries[]=$this->db->query_result($result,$i-1,'first_name').' '.$this->db->query_result($result,$i-1,'last_name');
			$entries[]='<a href="index.php?action=DetailView&module=Users&parenttab=Settings&record='.$id.'">'.$this->db->query_result($result,$i-1,'user_name').'</a>';
			$entries[]=$this->db->query_result($result,$i-1,'department');
			$entries[]=$this->db->query_result($result,$i-1,'email1');
			$rolecode= fetchUserRole($this->db->query_result($result,$i-1,'id'));
			$entries[]='<a href="index.php?action=DetailView&module=Users&parenttab=Settings&record='.$id.'">'.$roleinfo[$rolecode][0];
			$entries[]=$this->db->query_result($result,$i-1,'is_admin');
			$entries_list[]=$entries;
		}
		return $entries_list;
	}
	function fill_in_additional_list_fields()
	{
		$this->fill_in_additional_detail_fields();	
	}
	
	function fill_in_additional_detail_fields()
	{
		//$query = "SELECT u1.first_name, u1.last_name from users as u1, users as u2 where u1.id = u2.reports_to_id AND u2.id = '$this->id' and u1.deleted=0";
		$query = "SELECT u1.first_name, u1.last_name from users u1, users u2 where u1.id = u2.reports_to_id AND u2.id = '$this->id' and u1.deleted=0";
		$result =$this->db->query($query, true, "Error filling in additional detail fields") ;
		
		$row = $this->db->fetchByAssoc($result);
		$this->log->debug("additional detail query results: $row");
		
		if($row != null)
		{
			$this->reports_to_name = stripslashes($row['first_name'].' '.$row['last_name']);
		}
		else 
		{
			$this->reports_to_name = '';
		}		
	}
}

?>
