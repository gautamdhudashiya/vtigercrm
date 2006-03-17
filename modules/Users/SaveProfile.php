<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
*
 ********************************************************************************/

require_once('include/database/PearDatabase.php');
require_once('include/utils/UserInfoUtil.php');
require_once('include/utils/utils.php');
global $adb;
$profilename = $_REQUEST['profile_name'];
$description= $_REQUEST['profile_description'];
//Inserting values into Profile Table
$sql1 = "insert into profile values('','".$profilename."','".$description."')";
$adb->query($sql1);

        //Retreiving the profileid
        $sql2 = "select max(profileid) as current_id from profile";
        $result2 = $adb->query($sql2);
        $profileid = $adb->query_result($result2,0,'current_id');


//Retreiving the tabs permission array
$tab_perr_result = $adb->query("select * from profile2tab where profileid=1");
$act_perr_result = $adb->query("select * from profile2standardpermissions where profileid=1");
$act_utility_result = $adb->query("select * from profile2utility where profileid=1");
$num_tab_per = $adb->num_rows($tab_perr_result);
$num_act_per = $adb->num_rows($act_perr_result);
$num_act_util_per = $adb->num_rows($act_utility_result);

	//Updating profile2global permissons table
	$view_all_req=$_REQUEST['view_all'];
	$view_all = getPermissionValue($view_all_req);

	$edit_all_req=$_REQUEST['edit_all'];
	$edit_all = getPermissionValue($edit_all_req);


	$sql4="insert into profile2globalpermissions values(".$profileid.",1, ".$view_all.")";
        $adb->query($sql4);

	$sql4="insert into profile2globalpermissions values(".$profileid.",2, ".$edit_all.")";
        $adb->query($sql4);

	
	//profile2tab permissions
	for($i=0; $i<$num_tab_per; $i++)
	{
		$tab_id = $adb->query_result($tab_perr_result,$i,"tabid");
		$request_var = $tab_id.'_tab';
		if($tab_id != 3 && $tab_id != 16 && $tab_id != 15)
		{
			$permission = $_REQUEST[$request_var];
			if($permission == 'on')
			{
				$permission_value = 0;
			}
			else
			{
				$permission_value = 1;
			}
			$sql4="insert into profile2tab values(".$profileid.", ".$tab_id.", ".$permission_value.")";
                	$adb->query($sql4);

			if($tab_id ==9)
			{
				$sql4="insert into profile2tab values(".$profileid.",16, ".$permission_value.")";
                        	$adb->query($sql4);	
			}
		}
		elseif($tab_id == 13)
		{
			$sql4="insert into profile2tab values(".$profileid.",13,0)";
                        $adb->query($sql4);
		}
		elseif($tab_id == 15)
		{
			$sql4="insert into profile2tab values(".$profileid.",15,0)";
                        $adb->query($sql4);
		}
	}
	
	//profile2standard permissions	
	for($i=0; $i<$num_act_per; $i++)
	{
		$tab_id = $adb->query_result($act_perr_result,$i,"tabid");
		$action_id = $adb->query_result($act_perr_result,$i,"operation");
		if($tab_id != 16 && $tab_id != 15)
		{
			$action_name = getActionname($action_id);
			if($action_name == 'EditView' || $action_name == 'Delete' || $action_name == 'DetailView')
			{
				$request_var = $tab_id.'_'.$action_name;
			}
			elseif($action_name == 'Save')
			{
				$request_var = $tab_id.'_EditView';
			}
			elseif($action_name == 'index')
			{
				$request_var = $tab_id.'_DetailView';
			}

			$permission = $_REQUEST[$request_var];
			if($permission == 'on')
			{
				$permission_value = 0;
			}
			else
			{
				$permission_value = 1;
			}

			$sql7="insert into profile2standardpermissions values(".$profileid.", ".$tab_id.", ".$action_id.", ".$permission_value.")";
                	$adb->query($sql7);

			if($tab_id ==9)
			{
				$sql7="insert into profile2standardpermissions values(".$profileid.", 16, ".$action_id.", ".$permission_value.")";
                        	$adb->query($sql7);
			}



		}
		elseif($tab_id == 15)
		{
			
                          $sql7="insert into profile2standardpermissions values(".$profileid.", 15, ".$action_id.",0)";
                          $adb->query($sql7);
		}
	}

	//Update Profile 2 utility
	for($i=0; $i<$num_act_util_per; $i++)
	{
		$tab_id = $adb->query_result($act_utility_result,$i,"tabid");

		$action_id = $adb->query_result($act_utility_result,$i,"activityid");
		$action_name = getActionname($action_id);
		$request_var = $tab_id.'_'.$action_name;


		$permission = $_REQUEST[$request_var];
		if($permission == 'on')
		{
			$permission_value = 0;
		}
		else
		{
			$permission_value = 1;
		}

		$sql9="insert into profile2utility values(".$profileid.", ".$tab_id.", ".$action_id.", ".$permission_value.")";
                $adb->query($sql9);

	}



	$modArr= Array('Leads'=>'LBL_LEAD_FIELD_ACCESS',
                'Accounts'=>'LBL_ACCOUNT_FIELD_ACCESS',
                'Contacts'=>'LBL_CONTACT_FIELD_ACCESS',
                'Potentials'=>'LBL_OPPORTUNITY_FIELD_ACCESS',
                'HelpDesk'=>'LBL_HELPDESK_FIELD_ACCESS',
                'Products'=>'LBL_PRODUCT_FIELD_ACCESS',
                'Notes'=>'LBL_NOTE_FIELD_ACCESS',
                'Emails'=>'LBL_EMAIL_FIELD_ACCESS',
                'Activities'=>'LBL_TASK_FIELD_ACCESS',
                'Events'=>'LBL_EVENT_FIELD_ACCESS',
                'Vendors'=>'LBL_VENDOR_FIELD_ACCESS',
                'PriceBooks'=>'LBL_PB_FIELD_ACCESS',
                'Quotes'=>'LBL_QUOTE_FIELD_ACCESS',
                'PurchaseOrder'=>'LBL_PO_FIELD_ACCESS',
                'SalesOrder'=>'LBL_SO_FIELD_ACCESS',
                'Invoice'=>'LBL_INVOICE_FIELD_ACCESS'
              );

foreach($modArr as $fld_module => $fld_label)
{
	$fieldListResult = getProfile2FieldList($fld_module, 1);
	$noofrows = $adb->num_rows($fieldListResult);
	$tab_id = getTabid($fld_module);
	for($i=0; $i<$noofrows; $i++)
	{
		$fieldid =  $adb->query_result($fieldListResult,$i,"fieldid");
		$visible = $_REQUEST[$fieldid];
		if($visible == 'on')
		{
			$visible_value = 0;
		}
		else
		{
			$visible_value = 1;
		}
		//Updating the Mandatory fields
		$uitype = $adb->query_result($fieldListResult,$i,"uitype");
		if($uitype == 2 || $uitype == 51 || $uitype == 6 || $uitype == 22 || $uitype == 73 || $uitype				== 24 || $uitype == 81 || $uitype == 50 || $uitype == 23 || $uitype == 16)
		{
			$visible_value = 0;
		}
		//Updating the database
		$sql11="insert into profile2field values(".$profileid.", ".$tab_id.", ".$fieldid.", ".$visible_value.",1)";
                $adb->query($sql11);
	}
}
	$loc = "Location: index.php?action=ListProfiles&module=Users&mode=view&profileid=".$profileid;
	header($loc);

function getPermissionValue($req_per)
{
	if($req_per == 'on')
	{
		$permission_value = 0;
	}
	else
	{
		$permission_value = 1;
	}
	return $permission_value;
}

?>
