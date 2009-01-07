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

//5.0.4 to 5.1.0 database changes

//we have to use the current object (stored in PatchApply.php) to execute the queries
$adb = $_SESSION['adodb_current_object'];
$conn = $_SESSION['adodb_current_object'];

$migrationlog->debug("\n\nDB Changes from 5.0.4 to 5.1.0 -------- Starts \n\n");

require_once('include/events/include.inc');
$em = new VTEventsManager($adb);
/* For the event api */
ExecuteQuery("create table vtiger_eventhandlers (eventhandler_id int, event_name varchar(100), handler_path varchar(400), handler_class varchar(100), cond text, is_active boolean, primary key(eventhandler_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

/* Added new column actions to vtiger_relatedlists which tracks the type of actions allowed for that related list */
ExecuteQuery("alter table vtiger_relatedlists add column actions VARCHAR(50) default ''");

require_once("modules/com_vtiger_workflow/include.inc");
require_once("modules/com_vtiger_workflow/tasks/VTEntityMethodTask.inc");
require_once("modules/com_vtiger_workflow/VTEntityMethodManager.inc");
$emm = new VTEntityMethodManager($adb);

/* Update the block id in sequence table, to the current highest value of block id used. */
$tmp = $adb->getUniqueId('vtiger_blocks');
$max_block_id_query = $adb->query("SELECT MAX(blockid) AS max_blockid FROM vtiger_blocks");
$max_block_id = $adb->query_result($max_block_id_query,0,"max_blockid");

ExecuteQuery("UPDATE vtiger_blocks_seq SET id=".$max_block_id);

/* Migration queries to cleanup ui type 15, 16, 111 - 
 * 15 for Standard picklist types,
 * 16 for non-standard picklist types which do not support Role-based picklist */
ExecuteQuery("update vtiger_field set uitype = '15' where uitype='16'");
ExecuteQuery("update vtiger_field set uitype = '15', typeofdata='V~M' where uitype='111'");

ExecuteQuery("update vtiger_field set uitype=16 where fieldname in " .
		"('visibility','duration_minutes','recurringtype','hdnTaxType','recurring_frequency','activity_view','lead_view','date_format','reminder_interval')" .
		" and uitype = '15'");

/* Function to add Field Security for newly added fields */
function addFieldSecurity($tabid, $fieldid, $allow_merge=true) {
	global $adb;
	ExecuteQuery("INSERT INTO vtiger_def_org_field (tabid, fieldid, visible, readonly) VALUES ($tabid, $fieldid, 0, 1)");

	$profile_result = $adb->query("select distinct(profileid) as profileid from vtiger_profile");
	$num_profiles = $adb->num_rows($profile_result);
	for($j=0; $j<$num_profiles; $j++) {
		$profileid = $adb->query_result($profile_result,$j,'profileid');
		ExecuteQuery("INSERT INTO vtiger_profile2field (profileid, tabid, fieldid, visible, readonly) VALUES($profileid, $tabid, $fieldid, 0, 1)");
	}
	
	if ($allow_merge) {
		$user_result = $adb->query("select distinct(id) as userid from vtiger_users");
		$num_users = $adb->num_rows($user_result);
		for($j=0; $j<$num_users; $j++) {
			$userid = $adb->query_result($user_result,$j,'userid');
			ExecuteQuery("INSERT INTO vtiger_user2mergefields VALUES($userid, $tabid, $fieldid, 0)");
		}
	}
}

/* Add Total column in default customview of Purchase Order */
$res = $adb->query("select cvid from vtiger_customview where viewname='All' and entitytype='PurchaseOrder'");
$po_cvid = $adb->query_result($res, 0, 'cvid');
$adb->query("update vtiger_cvcolumnlist set columnindex = 5 where columnindex = 4 and cvid = $po_cvid");
$adb->query("insert into vtiger_cvcolumnlist values ($po_cvid, 4, 'vtiger_purchaseorder:total:hdnGrandTotal:PurchaseOrder_Total:V')");
                        


/* To Provide an option to Create Product from Quick Create */
ExecuteQuery("UPDATE vtiger_field SET quickcreate = 0 WHERE tablename='vtiger_products' and columnname='productname'");
ExecuteQuery("UPDATE vtiger_field SET quickcreate = 0 WHERE tablename='vtiger_products' and columnname='discontinued'");
ExecuteQuery("UPDATE vtiger_field SET quickcreate = 0 WHERE tablename='vtiger_products' and columnname='unit_price'");
ExecuteQuery("UPDATE vtiger_field SET quickcreate = 0 WHERE tablename='vtiger_products' and columnname='qtyinstock'");

/* Necessary DB Changes for Recycle bin feature */
ExecuteQuery("create table vtiger_relatedlists_rb(entityid int(19), action varchar(50), rel_table varchar(200), rel_column varchar(200), ref_column varchar(200), related_crm_ids text)  ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("insert into vtiger_tab values('30', 'Recyclebin', '0', '27', 'Recyclebin', null, null, 0, '1')");

ExecuteQuery("insert into vtiger_parenttabrel values('7', '30', '4')");

// Enable Search icon for all profiles by default for Recyclebin module
$profileresult = $adb->query("select * from vtiger_profile");
$countprofiles = $adb->num_rows($profileresult);
for($i=0;$i<$countprofiles;$i++)
{
	$profileid = $adb->query_result($profileresult,$i,'profileid');
	ExecuteQuery("insert into vtiger_profile2utility values($profileid,30,3,0)");
	ExecuteQuery("insert into vtiger_profile2tab values ($profileid,30,0)");
}

/* For Role based customview support */
ExecuteQuery("alter table vtiger_customview add column status int(1) default '3'");
ExecuteQuery("update vtiger_customview set status=0 where viewname='All'");
ExecuteQuery("alter table vtiger_customview add column userid int(19) default '1'");

/* Reminder Popup support for Calendar Events */
ExecuteQuery("CREATE TABLE vtiger_activity_reminder_popup(reminderid int(19) NOT NULL AUTO_INCREMENT,semodule varchar(100) NOT NULL,recordid varchar(100) NOT NULL,date_start DATE,time_start varchar(100) NOT NULL,status int(2) NOT NULL, PRIMARY KEY(reminderid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_reminder_interval(reminder_intervalid int(19) NOT NULL AUTO_INCREMENT,reminder_interval varchar(200) NOT NULL,sortorderid int(19) NOT NULL,presence int(1) NOT NULL, PRIMARY KEY(reminder_intervalid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("alter table vtiger_users add column reminder_interval varchar(100) NOT NULL");
ExecuteQuery("alter table vtiger_users add column reminder_next_time varchar(100)");

ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'None',0,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'1 Minute',1,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'5 Minutes',2,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'15 Minutes',3,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'30 Minutes',4,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'45 Minutes',5,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'1 Hour',6,1)");
ExecuteQuery("INSERT INTO vtiger_reminder_interval values(".$adb->getUniqueId("vtiger_reminder_interval").",'1 Day',7,1)");
ExecuteQuery("UPDATE vtiger_users SET reminder_interval='5 Minutes', reminder_next_time='".date('Y-m-d H:i')."'");
$user_adv_block_id = $adb->getUniqueID('vtiger_blocks');
ExecuteQuery("insert into vtiger_blocks values (".$user_adv_block_id.",29,'LBL_USER_ADV_OPTIONS',5,0,0,0,0,0)"); //Added a New Block User Image Info in Users Module
ExecuteQuery("insert into vtiger_field values (29,".$adb->getUniqueID("vtiger_field").",'reminder_interval','vtiger_users',1,'16','reminder_interval','Reminder Interval',1,0,0,100,1,$user_adv_block_id,1,'V~O',1,null,'BAS')");

/* For Duplicate Records Merging feature */
ExecuteQuery("INSERT INTO vtiger_actionmapping values(10,'DuplicatesHandling',0)");
ExecuteQuery("CREATE TABLE vtiger_user2mergefields (userid int(11) REFERENCES vtiger_users( id ) , tabid int( 19 ) ,fieldid int( 19 ), visible int(2))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");

function insertUser2mergefields($userid, $tabid)
{
	global $log,$adb;
	$log->debug("Entering insertUser2mergefields(".$userid.") method ...");
        $log->info("in insertUser2mergefields ".$userid);

	foreach($tabid AS $key=>$tab_id) { 
        $fld_result = getFieldsResultForMerge($tab_id);
        if ($fld_result != null) {
    		$num_rows = $adb->num_rows($fld_result);
			for($j=0; $j<$num_rows; $j++) {
				$field_id = $adb->query_result($fld_result,$j,'fieldid'); 
				$data_type = explode("~",$adb->query_result($fld_result,$j,'typeofdata')); 
				if($data_type[1] == 'M') { 
					$visible = 1; 
				} else { 
					$visible = 2; 
				} 
				ExecuteQuery("insert into vtiger_user2mergefields values ($userid, $tab_id, $field_id, $visible)");
	        }
        }
	}
	$log->debug("Exiting insertUser2mergefields method ...");
}

$tabid = Array(); 
$tab_res = $adb->query("SELECT distinct tabid FROM vtiger_tab"); 
$noOfTabs = $adb->num_rows($tab_res); 
for($i=0;$i<$noOfTabs;$i++) { 
	$tabid[] = $adb->query_result($tab_res,$i,'tabid'); 
} 

$usr_sql = $adb->query("select id from vtiger_users"); 
$num_usr = $adb->num_rows($usr_sql); 
for($i=0;$i<$num_usr;$i++) { 
	insertUser2mergefields($adb->query_result($usr_sql,$i,'id'),$tabid); 
} 

$profile_sql = $adb->query("select profileid from vtiger_profile"); 
$num_profile = $adb->num_rows($profile_sql);
/*Duplicate merging is supported for 
 * Accounts, Potentials, Contacts, Leads, Products, Vendors, TroubleTickets
 */ 
$dupSupported = array(6, 2, 4, 7, 14, 18, 13);
for($i=0;$i<$num_profile;$i++) { 
	$profile_id = $adb->query_result($profile_sql,$i,'profileid'); 
	for($j=0;$j<$noOfTabs;$j++) {
		if (in_array($tabid[$j], $dupSupported)) {
			ExecuteQuery("insert into vtiger_profile2utility values($profile_id,".$tabid[$j].",10,0)");
		} 
	} 
} 

/* Local Backup Feature */
ExecuteQuery("alter table vtiger_systems add column server_path varchar(256)");

/* Multi-Currency Support in Products, Pricebooks and Other Inventory Modules */

// To save mapping between products and its price in different currencies.
ExecuteQuery("CREATE TABLE IF NOT EXISTS vtiger_productcurrencyrel (productid int(11) not null, currencyid int(11) not null, converted_price decimal(25,2) default NULL, actual_price decimal(25, 2) default NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// Update Product related tables
ExecuteQuery("alter table vtiger_products drop column currency");
ExecuteQuery("alter table vtiger_products add column currency_id int(19) not null default '1'");

// Update Currency related tables
ExecuteQuery("alter table vtiger_currency_info add column deleted int(1) not null default '0'");

// Update Inventory related tables
ExecuteQuery("alter table vtiger_quotes drop column currency");
ExecuteQuery("alter table vtiger_quotes add column currency_id int(19) not null default '1'");
ExecuteQuery("alter table vtiger_quotes add column conversion_rate decimal(10,3) not null default '1.000'");
$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(20,$field_id,'currency_id','vtiger_quotes','1','117','currency_id','Currency','1','0','1','100','21','51','3','I~O','1',null,'BAS')");
addFieldSecurity(20,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(20,$field_id,'conversion_rate','vtiger_quotes','1','1','conversion_rate','Conversion Rate','1','0','1','100','22','51','3','N~O','1',null,'BAS')");
addFieldSecurity(20,$field_id);

ExecuteQuery("alter table vtiger_purchaseorder add column currency_id int(19) not null default '1'");
ExecuteQuery("alter table vtiger_purchaseorder add column conversion_rate decimal(10,3) not null default '1.000'");
$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(21,$field_id,'currency_id','vtiger_purchaseorder','1','117','currency_id','Currency','1','0','1','100','18','57','3','I~O','1',null,'BAS')");
addFieldSecurity(21,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(21,$field_id,'conversion_rate','vtiger_purchaseorder','1','1','conversion_rate','Conversion Rate','1','0','1','100','19','57','3','N~O','1',null,'BAS')");
addFieldSecurity(21,$field_id);

ExecuteQuery("alter table vtiger_salesorder add column currency_id int(19) not null default '1'");
ExecuteQuery("alter table vtiger_salesorder add column conversion_rate decimal(10,3) not null default '1.000'");
$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(22,$field_id,'currency_id','vtiger_salesorder','1','117','currency_id','Currency','1','0','1','100','19','63','3','I~O','1',null,'BAS')");
addFieldSecurity(22,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(22,$field_id,'conversion_rate','vtiger_salesorder','1','1','conversion_rate','Conversion Rate','1','0','1','100','20','63','3','N~O','1',null,'BAS')");
addFieldSecurity(22,$field_id);

ExecuteQuery("alter table vtiger_invoice add column currency_id int(19) not null default '1'");
ExecuteQuery("alter table vtiger_invoice add column conversion_rate decimal(10,3) not null default '1.000'");
$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(23,$field_id,'currency_id','vtiger_invoice','1','117','currency_id','Currency','1','0','1','100','18','69','3','I~O','1',null,'BAS')");
addFieldSecurity(23,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values(23,$field_id,'conversion_rate','vtiger_invoice','1','1','conversion_rate','Conversion Rate','1','0','1','100','19','69','3','N~O','1',null,'BAS')");
addFieldSecurity(23,$field_id);

// Update Price Book related tables
ExecuteQuery("alter table vtiger_pricebook drop column description");
ExecuteQuery("alter table vtiger_pricebook add column currency_id int(19) not null default '1'");
ExecuteQuery("alter table vtiger_pricebookproductrel add column usedcurrency int(11) not null default '1'");
$pb_currency_field_id = $adb->getUniqueID('vtiger_field');
$pb_tab_id = getTabid('PriceBooks');
$adb->query("insert into vtiger_field values($pb_tab_id,$pb_currency_field_id,'currency_id','vtiger_pricebook','1','117','currency_id','Currency','1','0','0','100','5','48','1','I~M','0','3','BAS')");
$adb->query("insert into vtiger_cvcolumnlist values('23','2','vtiger_pricebook:currency_id:currency_id:PriceBooks_Currency:I')");
addFieldSecurity($pb_tab_id,$pb_currency_field_id);

/* Documents module */
$documents_tab_id = getTabid('Documents');
ExecuteQuery("delete from vtiger_cvcolumnlist where columnname like '%Notes_Contact_Name%'");
ExecuteQuery("delete from vtiger_cvcolumnlist where columnname like '%Notes_Related_to%'");
//ExecuteQuery("create table vtiger_notegrouprelation (notesid int(19) NOT NULL, groupname varchar(100) default NULL)");

ExecuteQuery("insert into vtiger_def_org_share values (13,$documents_tab_id,2,0)");

for($i=0;$i<4;$i++)
{
	ExecuteQuery("insert into vtiger_org_share_action2tab values(".$i.",$documents_tab_id)");
}	

ExecuteQuery("alter table vtiger_customview drop foreign key fk_1_vtiger_customview ");
ExecuteQuery("update vtiger_customview set entitytype='Documents' where entitytype='Notes'");
ExecuteQuery("update vtiger_tab set ownedby=0,name='Documents',tablabel='Documents' where tabid=$documents_tab_id");
ExecuteQuery("update vtiger_entityname set modulename='Documents' where tabid=$documents_tab_id");
ExecuteQuery("alter table vtiger_customview add constraint FOREIGN KEY fk_1_vtiger_customview (entitytype) REFERENCES vtiger_tab (name) ON DELETE CASCADE");

$DocumentsId = getTabid('Documents');
ExecuteQuery("UPDATE vtiger_relatedlists SET actions='add,select', related_tabid=$documents_tab_id WHERE name='get_attachments'");
ExecuteQuery("alter table vtiger_notes add(folderid int(19) NOT NULL,filetype varchar(50) default NULL,filelocationtype varchar(5) default NULL,filedownloadcount int(19) default NULL,filestatus int(19) default NULL,filesize int(19) NOT NULL default '0',fileversion varchar(50) default NULL)");

ExecuteQuery("create table vtiger_attachmentsfolder ( folderid int(19) NOT NULL,foldername varchar(200) NOT NULL default '', description varchar(250) default '', createdby int(19) NOT NULL, sequence int(19) default NULL, PRIMARY KEY  (folderid))");

ExecuteQuery("insert into vtiger_attachmentsfolder values (0,'Existing Notes','Contains all Notes migrated from the earlier version',1,1)");

ExecuteQuery("alter table vtiger_senotesrel drop foreign key fk_2_vtiger_senotesrel ");

$notesQuery = $adb->query("select notesid from vtiger_notes");
$noofnotes = $adb->num_rows($notesQuery);
if($noofnotes > 0)
{
    for($k=0;$k<$noofnotes;$k++)
    {
        $notesid = $adb->query_result($notesQuery,$k,'notesid');
        $attachmentidQuery = 'select attachmentsid from vtiger_seattachmentsrel where crmid = ?';
        $res = $adb->pquery($attachmentidQuery,array($notesid));
        $attachmentid = $adb->query_result($res,0,'attachmentsid');
		if($attachmentid != ''){	
        	 $attachmentInfoQuery = 'select * from vtiger_attachments where attachmentsid = ?';
       		 $attachres = $adb->pquery($attachmentInfoQuery,array($attachmentid));
        	 $filename = $adb->query_result($attachres,0,'name');
        	 $filepath = $adb->query_result($attachres,0,'path');
       	 	 $filetype = $adb->query_result($attachres,0,'type');
       		 $filesize = filesize($filepath.$attachmentid."_".$filename);
      		 ExecuteQuery("update vtiger_notes set folderid = 0,filestatus=1,filelocationtype='I',filedownloadcount=0,fileversion='',filetype='".$filetype."',filesize='".$filesize."',filename='".$filename."' where notesid = ".$notesid);
	}
	else{
		ExecuteQuery("update vtiger_notes set folderid=0,filestatus=0,filelocationtype='',filedownloadcount='',fileversion='',filetype='',filesize='',filename='' where notesid = ".$notesid);
	
	}
	$query ="update vtiger_crmentity set setype='Documents' where crmid = ?";
	$adb->pquery($query,array($notesid));
   }
}

$fieldid = Array();
for($i=0;$i<8;$i++)
{
	$fieldid[$i] = $adb->getUniqueID("vtiger_field");
}
$file_block_id = $adb->getUniqueID('vtiger_blocks');
ExecuteQuery("insert into vtiger_blocks values($file_block_id,$documents_tab_id,'LBL_FILE_INFORMATION',2,0,0,0,0,0)");

$description_block_id_Query = 'select blockid from vtiger_blocks where tabid = '.$documents_tab_id.' and blocklabel = "" ';
$desc_id = $adb->pquery($description_block_id_Query,array());
$desc = $adb->query_result($desc_id,0,'blockid');

$desc_update = 'update vtiger_blocks set blocklabel ="LBL_DESCRIPTION",show_title = 0,sequence = 3 where blockid = ?';
$desc_block_update = $adb->pquery($desc_update,array($desc));

ExecuteQuery("update vtiger_field set sequence=1 where tabid=$documents_tab_id and columnname='title'");
ExecuteQuery("update vtiger_field set sequence=8 where tabid=$documents_tab_id and columnname='createdtime'");
ExecuteQuery("update vtiger_field set sequence=9 where tabid=$documents_tab_id and columnname='modifiedtime'");

ExecuteQuery("update vtiger_field set sequence=1,block=$desc where tabid=$documents_tab_id and columnname='notecontent'");
ExecuteQuery("update vtiger_field set block = $file_block_id,fieldlabel='File Name',displaytype = 2  where tabid = $documents_tab_id and columnname = 'filename'");

ExecuteQuery("insert into vtiger_field values ($documents_tab_id,".$fieldid[0].",'smownerid','vtiger_crmentity',1,53,'assigned_user_id','Assigned To',1,0,0,100,2,17,1,'V~O',0,3,'BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[1].",'filetype','vtiger_notes',1,1,'filetype','File Type',1,0,0,100,3,$file_block_id,2,'V~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[2].",'filesize','vtiger_notes',1,1,'filesize','File Size',1,0,0,100,4,$file_block_id,2,'V~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[3].",'filelocationtype','vtiger_notes',1,122,'filelocationtype','Download Type',1,0,0,100,1,$file_block_id,1,'V~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[4].",'fileversion','vtiger_notes',1,1,'fileversion','Version',1,0,0,100,6,17,1,'V~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[5].",'filestatus','vtiger_notes',1,56,'filestatus','Active',1,0,0,100,2,$file_block_id,1,'V~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[6].",'filedownloadcount','vtiger_notes',1,1,'filedownloadcount','Download Count',1,0,0,100,7,$file_block_id,2,'I~O',1,'','BAS')");
ExecuteQuery("insert into vtiger_field values($documents_tab_id,".$fieldid[7].",'folderid','vtiger_notes',1,121,'folderid','Folder Name',1,0,0,100,4,17,1,'V~M',0,'2','BAS')");

for($i=0;$i<count($fieldid);$i++)
{
	addFieldSecurity($documents_tab_id,$fieldid[$i]);
}
//Rename Attachments to Documents in relatedlist 
ExecuteQuery("update vtiger_relatedlists set label='Documents' where name = 'get_attachments'");

$dbQuery = "select notesid,contact_id from vtiger_notes";
$dbresult = $adb->query($dbQuery);
$noofrecords = $adb->num_rows($dbresult);
if($noofrecords > 0)
{
    for($i=0;$i<$noofrecords;$i++)
    {
        $contactid = $adb->query_result($dbresult,$i,'contact_id');
        $notesid = $adb->query_result($dbresult,$i,'notesid');
        if($contactid != 0)
            ExecuteQuery("insert into vtiger_senotesrel values (".$contactid.",".$notesid.")");
    }
}

ExecuteQuery("delete from vtiger_field where tabid = 8 and fieldname = 'contact_id'");
ExecuteQuery("delete from vtiger_field where tabid = 8 and fieldname = 'parent_id'");

ExecuteQuery("alter table vtiger_notes drop column contact_id");


$em->registerHandler('vtiger.entity.aftersave', 'modules/Documents/AttachFile.php', 'Attachfile');
ExecuteQuery("update vtiger_cvcolumnlist set columnname='vtiger_notes:filename:filename:Documents_Filename:V' where cvid = 22 and columnindex = 3");
custom_addCustomFilterColumn('Documents','All', 'vtiger_crmentity','smownerid','assigned_user_id','Documents_Assigned_To:V',7);

//remove filename column from trouble ticket
ExecuteQuery("alter table vtiger_troubletickets drop column filename");
//End: Database changes regarding Documents module

/* Home Page Customization */
ExecuteQuery("CREATE TABLE vtiger_homestuff (stuffid int(19) NOT NULL default '0', stuffsequence int(19) NOT NULL default '0', stufftype varchar(100) default NULL, userid int(19) NOT NULL, visible int(10) NOT NULL default '0', stufftitle varchar(100) default NULL, PRIMARY KEY  (stuffid), KEY stuff_stuffid_idx (stuffid), KEY fk_1_vtiger_homestuff (userid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_homedashbd (stuffid int(19) NOT NULL default 0, dashbdname varchar(100) default NULL, dashbdtype varchar(100) default NULL, PRIMARY KEY  (stuffid), KEY stuff_stuffid_idx (stuffid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_homedefault (stuffid int(19) NOT NULL default 0, hometype varchar(30) NOT NULL, maxentries int(19) default NULL, setype varchar(30) default NULL, PRIMARY KEY  (stuffid), KEY stuff_stuffid_idx (stuffid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_homemodule (stuffid int(19) NOT NULL, modulename varchar(100) default NULL, maxentries int(19) NOT NULL, customviewid int(19) NOT NULL, setype varchar(30) NOT NULL, PRIMARY KEY  (stuffid), KEY stuff_stuffid_idx (stuffid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_homemoduleflds (stuffid int(19) default NULL, fieldname varchar(255) default NULL, KEY stuff_stuffid_idx (stuffid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("CREATE TABLE vtiger_homerss (stuffid int(19) NOT NULL default 0, url varchar(100) default NULL, maxentries int(19) NOT NULL, PRIMARY KEY  (stuffid), KEY stuff_stuffid_idx (stuffid))  ENGINE=InnoDB DEFAULT CHARSET=utf8;"); 

ExecuteQuery("ALTER TABLE vtiger_homestuff ADD CONSTRAINT fk_1_vtiger_homestuff FOREIGN KEY (userid) REFERENCES vtiger_users (id) ON DELETE CASCADE");
ExecuteQuery("ALTER TABLE vtiger_homedashbd ADD CONSTRAINT fk_1_vtiger_homedashbd FOREIGN KEY (stuffid) REFERENCES vtiger_homestuff (stuffid) ON DELETE CASCADE");
ExecuteQuery("ALTER TABLE vtiger_homedefault ADD CONSTRAINT fk_1_vtiger_homedefault FOREIGN KEY (stuffid) REFERENCES vtiger_homestuff (stuffid) ON DELETE CASCADE");
ExecuteQuery("ALTER TABLE vtiger_homemodule ADD CONSTRAINT fk_1_vtiger_homemodule FOREIGN KEY (stuffid) REFERENCES vtiger_homestuff (stuffid) ON DELETE CASCADE");
ExecuteQuery("ALTER TABLE vtiger_homemoduleflds ADD CONSTRAINT fk_1_vtiger_homemoduleflds FOREIGN KEY (stuffid) REFERENCES vtiger_homemodule (stuffid) ON DELETE CASCADE");
ExecuteQuery("ALTER TABLE vtiger_homerss ADD CONSTRAINT fk_1_vtiger_homerss FOREIGN KEY (stuffid) REFERENCES vtiger_homestuff (stuffid) ON DELETE CASCADE");

//to get the users lists
$query = $adb->pquery('select * from vtiger_users',array());
for($i=0;$i<$adb->num_rows($query);$i++)
{
	$userid = $adb->query_result($query,$i,'id');

	$s1=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s1.",1,'Default',".$userid.",1,'Top Accounts')";
	$res=$adb->pquery($sql,array());

	$s2=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s2.",2,'Default',".$userid.",1,'Home Page Dashboard')";
	$res=$adb->pquery($sql,array());

	$s3=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s3.",3,'Default',".$userid.",1,'Top Potentials')";
	$res=$adb->pquery($sql,array());

	$s4=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s4.",4,'Default',".$userid.",1,'Top Quotes')";
	$res=$adb->pquery($sql,array());

	$s5=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s5.",5,'Default',".$userid.",1,'Key Metrics')";
	$res=$adb->pquery($sql,array());

	$s6=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s6.",6,'Default',".$userid.",1,'Top Trouble Tickets')";
	$res=$adb->pquery($sql,array());

	$s7=$adb->getUniqueID("vtiger_homestuff"); 
	$sql="insert into vtiger_homestuff values(".$s7.",7,'Default',".$userid.",1,'Upcoming Activities')";
	$res=$adb->pquery($sql,array());

	$s8=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s8.",8,'Default',".$userid.",1,'My Group Allocation')";
	$res=$adb->pquery($sql,array());

	$s9=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s9.",9,'Default',".$userid.",1,'Top Sales Orders')";
	$res=$adb->pquery($sql,array());

	$s10=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s10.",10,'Default',".$userid.",1,'Top Invoices')";
	$res=$adb->pquery($sql,array());

	$s11=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s11.",11,'Default',".$userid.",1,'My New Leads')";
	$res=$adb->pquery($sql,array());

	$s12=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s12.",12,'Default',".$userid.",1,'Top Purchase Orders')";
	$res=$adb->pquery($sql,array());

	$s13=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s13.",13,'Default',".$userid.",1,'Pending Activities')";
	$res=$adb->pquery($sql,array());

	$s14=$adb->getUniqueID("vtiger_homestuff");
	$sql="insert into vtiger_homestuff values(".$s14.",14,'Default',".$userid.",1,'My Recent FAQs')";
	$res=$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s1.",'ALVT',5,'Accounts')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s2.",'HDB',5,'Dashboard')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s3.",'PLVT',5,'Potentials')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s4.",'QLTQ',5,'Quotes')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s5.",'CVLVT',5,'NULL')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s6.",'HLT',5,'HelpDesk')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s7.",'UA',5,'Calendar')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s8.",'GRT',5,'NULL')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s9.",'OLTSO',5,'SalesOrder')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s10.",'ILTI',5,'Invoice')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s11.",'MNL',5,'Leads')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s12.",'OLTPO',5,'PurchaseOrder')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s13.",'PA',5,'Calendar')";
	$adb->pquery($sql,array());

	$sql="insert into vtiger_homedefault values(".$s14.",'LTFAQ',5,'Faq')";
	$adb->pquery($sql,array());
}
for($i=0;$i<$adb->num_rows($query);$i++)
{
	$def_homeorder = $adb->query_result($query,$i,'homeorder');
	$user_id = $adb->query_result($query,$i,'id');
	$def_array = explode(",",$def_homeorder);
	$sql = $adb->pquery("SELECT vtiger_homestuff.stuffid FROM vtiger_homestuff INNER JOIN vtiger_homedefault WHERE vtiger_homedefault.hometype in (". generateQuestionMarks($def_array) . ") AND vtiger_homestuff.stuffid = vtiger_homedefault.stuffid AND vtiger_homestuff.userid = ?",array($def_array,$user_id));
	$stuffid_list = array();
	for($j=0;$j<$adb->num_rows($sql);$j++) {
		$stuffid_list[] = $adb->query_result($sql,$j,'stuffid');
	}
	$adb->pquery("UPDATE vtiger_homestuff SET visible = 0 WHERE stuffid in (". generateQuestionMarks($stuffid_list) .")",array($stuffid_list));
}

/* For Layout Editor */
ExecuteQuery("ALTER TABLE vtiger_blocks ADD COLUMN display_status int(1) NOT NULL DEFAULT '1'");

/* For Webservices Support */
function webserviceMigration(){
	global $adb;
	$fieldTypeInfo = array('picklist'=>array(15,16),'text'=>array(19,20,21,24),'autogenerated'=>array(3),'phone'=>array(11),
						'multipicklist'=>array(33),'url'=>array(17),'skype'=>array(85),'boolean'=>array(56,156),
						'owner'=>array(53));
	$referenceMapping = array("50"=>array("Accounts"),"51"=>array("Accounts"),"57"=>array("Contacts"),"58"=>array("Campaigns"),
			"73"=>array("Accounts"),"75"=>array("Vendors"),"76"=>array("Potentials"),"78"=>array("Quotes"),
			"80"=>array("SalesOrder"),"81"=>array("Vendors"),"101"=>array("Users"),"52"=>array("Users"),
			"357"=>array("Contacts","Accounts","Leads","Users","Vendors"),"59"=>array("Products"),
			"66"=>array("Leads","Accounts","Potentials","HelpDesk"),"77"=>array("Users"),"68"=>array("Contacts","Accounts"));
	ExecuteQuery("Create table vtiger_ws_fieldtype(fieldtypeid integer(19) not null auto_increment,uitype varchar(30)not null,fieldtype varchar(200) not null,PRIMARY KEY(fieldtypeid),UNIQUE KEY uitype_idx (uitype))ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	ExecuteQuery("Create table vtiger_ws_referencetype(fieldtypeid integer(19) not null,type varchar(25) not null,PRIMARY KEY(fieldtypeid,type),  CONSTRAINT `fk_1_vtiger_referencetype` FOREIGN KEY (`fieldtypeid`) REFERENCES `vtiger_ws_fieldtype` (`fieldtypeid`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	ExecuteQuery("Create table vtiger_ws_userauthtoken(userid integer(19) not null,token varchar(25) not null,expiretime INTEGER(19),PRIMARY KEY(userid,expiretime),UNIQUE KEY userid_idx (userid))ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	ExecuteQuery("alter table vtiger_users add column accesskey varchar(36);");
	$fieldid = $adb->getUniqueID("vtiger_field");
	$user_adv_block_id = getBlockId(29,'LBL_USER_ADV_OPTIONS');
	ExecuteQuery("insert into vtiger_field values(29,$fieldid,'accesskey','vtiger_users',1,3,'accesskey','Webservice Access Key',1,0,0,100,2,$user_adv_block_id,2,'V~O',1,null,'BAS');");
	
	foreach($referenceMapping as $uitype=>$referenceArray){
		$success = true;
		$result = $adb->pquery("insert into vtiger_ws_fieldtype(uitype,fieldtype) values(?,?)",array($uitype,"reference"));
		if(!is_object($result)){
			$success=false;
		}
		$result = $adb->pquery("select * from vtiger_ws_fieldtype where uitype=?",array($uitype));
		$rowCount = $adb->num_rows($result);
		for($i=0;$i<$rowCount;$i++){
			$fieldTypeId = $adb->query_result($result,$i,"fieldtypeid");
			foreach($referenceArray as $index=>$referenceType){
				$result = $adb->pquery("insert into vtiger_ws_referencetype(fieldtypeid,type) values(?,?)",array($fieldTypeId,$referenceType));
				if(!is_object($result)){
					echo "failed for: $referenceType, uitype: $fieldTypeId";
					$success=false;
				}
			}
		}
		if(!$success){
			echo "Migration Query Failed";
			break;
		}
	}
	
	foreach($fieldTypeInfo as $type=>$uitypes){
		foreach($uitypes as $uitype){
			$result = $adb->pquery("insert into vtiger_ws_fieldtype(uitype,fieldtype) values(?,?)",array($uitype,$type));
			if(!is_object($result)){
				"Query for fieldtype details($uitype:uitype,$type:fieldtype)";
			}
		}
	}
	
	$sql = "select * from vtiger_users";
	$updateQuery = "update vtiger_users set accesskey=? where id=?";
	$result = $adb->pquery($sql,array());
	$rowCount = $adb->num_rows($result);
	for($i=0;$i<$rowCount;$i++){
		$userId = $adb->query_result($result,$i,"id");
		$insertResult = $adb->pquery($updateQuery,array(vtws_generateRandomAccessKey(16),$userId));
		if(!is_object($insertResult)){
			echo "failed for user: ".$adb->query_result($result,$i,"user_name");
			break;
		}
	}
	
}
require_once 'include/Webservices/Utils.php';
webserviceMigration();


/* Adding Custom Events Migration */
ExecuteQuery("UPDATE vtiger_field SET uitype=15,typeofdata='V~M' WHERE tabid=16 and columnname='activitytype'");
ExecuteQuery("alter table vtiger_activitytype drop column sortorderid");
ExecuteQuery("alter table vtiger_activitytype add column picklist_valueid int(19) NOT NULL default '0'");
$picklist_id = $adb->getUniqueId("vtiger_picklist");
ExecuteQuery("INSERT INTO vtiger_picklist VALUES(".$picklist_id.",'activitytype')");

$query = $adb->pquery("SELECT * from vtiger_activitytype",array());
for($i=0;$i<$adb->num_rows($query);$i++){
	$picklist_valueid = $adb->getUniqueID('vtiger_picklistvalues');
	$activitytypeid = $adb->query_result($query,$i,'activitytypeid');
	$adb->pquery("UPDATE vtiger_activitytype SET picklist_valueid=? , presence=0 WHERE activitytypeid = ? ",array($picklist_valueid,$activitytypeid));
}

$role_query = $adb->query("SELECT * FROM vtiger_role");
for($j=0;$j<$adb->num_rows($role_query);$j++){
	$roleid = $adb->query_result($role_query,$j,'roleid');
	$query = $adb->pquery("SELECT * from vtiger_activitytype",array());
	for($i=0;$i<$adb->num_rows($query);$i++){
		$picklist_valueid = $adb->query_result($query,$i,'picklist_valueid');
		ExecuteQuery("INSERT INTO vtiger_role2picklist VALUES('".$roleid."',".$picklist_valueid.",".$picklist_id.",$i)");
	}
}

$uniqueid = $adb->getUniqueID("vtiger_relatedlists");
$faqtabid = getTabid('Faq');
ExecuteQuery("insert into vtiger_relatedlists values($uniqueid,$faqtabid,$documents_tab_id,'get_attachments',1,'Documents',0,'add,select')");
//CustomEvents Migration Ends

/* Important column renaming to support database porting */
$adb->pquery("ALTER TABLE vtiger_profile2standardpermissions CHANGE Operation testoperation INTEGER", array());
$adb->pquery("ALTER TABLE vtiger_profile2standardpermissions CHANGE testoperation operation INTEGER", array());

$renameArray = array(
		"vtiger_sales_stage",
		"vtiger_faqcategories",
		"vtiger_faqstatus",
		"vtiger_rating",
		"vtiger_ticketcategories",
		"vtiger_ticketpriorities",
		"vtiger_ticketseverities",
		"vtiger_ticketstatus"
);
foreach($renameArray as $tablename) {
	$adb->pquery("ALTER TABLE $tablename CHANGE PRESENCE testpresence INTEGER", array());
	$adb->pquery("ALTER TABLE $tablename CHANGE testpresence presence INTEGER", array());	
}
// Renaming completed

/* Important database schema changes to support database porting */
ExecuteQuery("alter table vtiger_attachments drop index attachments_description_type_attachmentsid_idx");
ExecuteQuery("alter table vtiger_attachments modify column description LONGTEXT");
ExecuteQuery("alter table vtiger_emaildetails modify column idlists LONGTEXT");

/* Product Bundles Feature */
$field_id = $adb->getUniqueID("vtiger_field");
ExecuteQuery("insert into vtiger_field values (14,".$field_id.",'parentid','vtiger_products',1,'51','product_id','Member Of',1,0,0,100,21,31,1,'I~O',1,null,'BAS')");
ExecuteQuery("ALTER TABLE vtiger_products ADD COLUMN parentid int(19) DEFAULT '0'");
addFieldSecurity(getTabid("Products"), $field_id);

$users_query = $adb->pquery("SELECT id from vtiger_users",array());
for($i=0; $i<$adb->num_rows($users_query); $i++){
	$userid = $adb->query_result($users_query,$i,'id');
	ExecuteQuery("insert into vtiger_user2mergefields values ($userid,".getTabid("Products").", $field_id, 0)");
}

ExecuteQuery("insert into vtiger_relatedlists values(".$adb->getUniqueID('vtiger_relatedlists').",".getTabid("Products").",".getTabid("Products").",'get_products',13,'Product Bundles',0,'add')");

/* vtmailscanner customization */
ExecuteQuery("CREATE TABLE vtiger_mailscanner(scannerid INT AUTO_INCREMENT NOT NULL PRIMARY KEY,scannername VARCHAR(30),
	server VARCHAR(100),protocol VARCHAR(10),username VARCHAR(30),password VARCHAR(255),ssltype VARCHAR(10),
sslmethod VARCHAR(30),connecturl VARCHAR(255),searchfor VARCHAR(10),markas VARCHAR(10),isvalid INT(1)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_mailscanner_ids(scannerid INT, messageid TEXT,crmid INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_mailscanner_folders(folderid INT AUTO_INCREMENT NOT NULL PRIMARY KEY,scannerid INT,foldername VARCHAR(255),lastscan VARCHAR(30),rescan INT(1), enabled INT(1)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_mailscanner_rules(ruleid INT AUTO_INCREMENT NOT NULL PRIMARY KEY,scannerid INT,fromaddress VARCHAR(255),toaddress VARCHAR(255),subjectop VARCHAR(20),subject VARCHAR(255),bodyop VARCHAR(20),body VARCHAR(255),matchusing VARCHAR(5),sequence INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_mailscanner_actions(actionid INT AUTO_INCREMENT NOT NULL PRIMARY KEY,scannerid INT,actiontype VARCHAR(10),module VARCHAR(30),lookup VARCHAR(30),sequence INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_mailscanner_ruleactions(ruleid INT,actionid INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
// END

/* Recurring Invoice Feature */
$new_block_seq_no = 2;
// Get all the blocks of the same module (SalesOrder), and update their sequence depending on the sequence of the new block added.
$res = $adb->query("SELECT blockid FROM vtiger_blocks WHERE tabid = ". getTabid('SalesOrder') ." AND sequence >= ". $new_block_seq_no);
$no_of_blocks = $adb->num_rows($res);
for ($i=0; $i<$no_of_blocks;$i++) {
	$blockid = $adb->query_result($res, $i, 'blockid');
	ExecuteQuery("UPDATE vtiger_blocks SET sequence = sequence+1 WHERE blockid=$blockid");
}
// Add new block to show recurring invoice information at specified position (sequence of blocks)
$new_block_id = $adb->getUniqueID('vtiger_blocks');
ExecuteQuery("INSERT INTO vtiger_blocks VALUES (".$new_block_id.",".getTabid('SalesOrder').",'Recurring Invoice Information',$new_block_seq_no,0,0,0,0,0,1)");

ExecuteQuery("ALTER TABLE vtiger_salesorder ADD COLUMN enable_recurring INT default 0");
ExecuteQuery("CREATE TABLE vtiger_invoice_recurring_info(salesorderid INT, recurring_frequency VARCHAR(200), start_period DATE, end_period DATE, last_recurring_date DATE default NULL, " .
		"			payment_duration VARCHAR(200), invoice_status VARCHAR(200)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("CREATE TABLE vtiger_recurring_frequency(recurring_frequency_id INT, recurring_frequency VARCHAR(200), sortorderid INT, presence INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
// Add default values for the recurring_frequency picklist
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'--None--',1,1)");
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'Daily',2,1)");
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'Weekly',3,1)");
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'Monthly',4,1)");
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'Quarterly',5,1)");
ExecuteQuery("INSERT INTO vtiger_recurring_frequency values(".$adb->getUniqueID('vtiger_recurring_frequency').",'Yearly',6,1)");

ExecuteQuery("CREATE TABLE vtiger_payment_duration(payment_duration_id INT, payment_duration VARCHAR(200), sortorderid INT, presence INT) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
// Add default values for the vtiger_payment_duration picklist
ExecuteQuery("INSERT INTO vtiger_payment_duration values(".$adb->getUniqueID('vtiger_payment_duration').",'Net 30 days',1,1)");
ExecuteQuery("INSERT INTO vtiger_payment_duration values(".$adb->getUniqueID('vtiger_payment_duration').",'Net 45 days',2,1)");
ExecuteQuery("INSERT INTO vtiger_payment_duration values(".$adb->getUniqueID('vtiger_payment_duration').",'Net 60 days',3,1)");

// Add fields for the Recurring Information block
$salesorder_tabid = getTabid('SalesOrder');
$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'enable_recurring','vtiger_salesorder',1,'56','enable_recurring','Enable Recurring',1,0,0,100,1,$new_block_id,1,'C~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'recurring_frequency','vtiger_invoice_recurring_info',1,'16','recurring_frequency','Frequency',1,0,0,100,2,$new_block_id,1,'V~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'start_period','vtiger_invoice_recurring_info',1,'5','start_period','Start Period',1,0,0,100,3,$new_block_id,1,'D~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'end_period','vtiger_invoice_recurring_info',1,'5','end_period','End Period',1,0,0,100,4,$new_block_id,1,'D~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'payment_duration','vtiger_invoice_recurring_info',1,'16','payment_duration','Payment Duration',1,0,0,100,5,$new_block_id,1,'V~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

$field_id = $adb->getUniqueID('vtiger_field');
ExecuteQuery("insert into vtiger_field values($salesorder_tabid,$field_id,'invoice_status','vtiger_invoice_recurring_info',1,'15','invoicestatus','Invoice Status',1,0,0,100,6,$new_block_id,1,'V~O',1,null,'BAS')");
addFieldSecurity($salesorder_tabid,$field_id);

// Add new picklist value 'AutoCreated' for Invoice Status and add the same for all the existing roles.
$picklistRes = $adb->query("SELECT picklistid FROM vtiger_picklist WHERE name='invoicestatus'");
$picklistid = $adb->query_result($picklistRes,0,'picklistid');

$picklist_valueid = $adb->getUniqueID('vtiger_picklistvalues');
$id = $adb->getUniqueID('vtiger_invoicestatus');

ExecuteQuery("insert into vtiger_invoicestatus values($id, 'AutoCreated', 1, $picklist_valueid)");

//Default entries for role2picklist relation has been inserted..
$sql="select roleid from vtiger_role";
$role_result = $adb->pquery($sql, array());
$numrow = $adb->num_rows($role_result);
for($k=0; $k < $numrow; $k ++)
{
	$roleid = $adb->query_result($role_result,$k,'roleid');
	$params = array($roleid, $picklist_valueid, $picklistid, $id-1);
	$adb->pquery("insert into vtiger_role2picklist values(?,?,?,?)", $params);
}

// Add Event handler for Recurring Invoice
$em->registerHandler('vtiger.entity.aftersave', 'modules/SalesOrder/RecurringInvoiceHandler.php', 'RecurringInvoiceHandler');

/* Workflow Manager - com_vtiger_workflow */
ExecuteQuery("create table com_vtiger_workflows_seq (id int(11)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("insert into com_vtiger_workflows_seq (id) values(1)");
ExecuteQuery("create table com_vtiger_workflows (workflow_id int, module_name varchar(100), summary varchar(100), test varchar(400), task_id int(11), exec_date int, execution_condition varchar(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery('create table com_vtiger_workflow_activatedonce (entity_id int, workflow_id int);');

ExecuteQuery("create table com_vtiger_workflowtasks_seq (id int(11)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("insert into com_vtiger_workflowtasks_seq (id) values(1)");
ExecuteQuery("create table com_vtiger_workflowtasks (task_id int, workflow_id int, summary varchar(100), task text, primary key(task_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("create table com_vtiger_workflowtask_queue (task_id int, entity_id int, do_after int, primary key(task_id, entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

ExecuteQuery("create table com_vtiger_workflowtasks_entitymethod_seq (id int(11)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
ExecuteQuery("insert into  com_vtiger_workflowtasks_entitymethod_seq (id) values(1)");
ExecuteQuery("create table com_vtiger_workflowtasks_entitymethod (workflowtasks_entitymethod_id int, module_name varchar(100), method_name varchar(100), function_path varchar(400), function_name varchar(100), primary key(workflowtasks_entitymethod_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$em->registerHandler('vtiger.entity.aftersave', 'modules/com_vtiger_workflow/VTEventHandler.inc', 'VTWorkflowEventHandler');
// com_vtiger_workflow ends

/* Mass Edit Feature */
ExecuteQuery("ALTER TABLE vtiger_field ADD COLUMN masseditable int(11) NOT NULL DEFAULT '1'");
$tab_field_array = array(
	'Accounts'=>array('accountname'),
	'Contacts'=>array('imagename'),
	'Products'=>array('imagename','product_id'),
	'Invoice'=>array('invoice_no','salesorder_id'),
	'SalesOrder'=>array('quote_id','salesorder_no'),
	'PurchaseOrder'=>array('purchaseorder_no'),
	'Quotes'=>array('quote_no'),
	'HelpDesk'=>array('filename'),
);
foreach($tab_field_array as $index=>$value){
	$tabid = getTabid($index);
	$adb->pquery("UPDATE vtiger_field SET masseditable=0 WHERE tabid=? AND fieldname IN (".generateQuestionMarks($value).")",array($tabid,$value));
}

/* Showing Emails in Vendors related list */
ExecuteQuery("insert into vtiger_relatedlists values(".$adb->getUniqueID('vtiger_relatedlists').",".getTabid("Vendors").",".getTabid("Emails").",'get_emails',4,'Emails',0,'add')");

/* Added for module sequence number customization */
ExecuteQuery("CREATE TABLE vtiger_modentity_num (num_id int(19) NOT NULL, semodule varchar(50) NOT NULL, prefix varchar(50) NOT NULL DEFAULT '', start_id varchar(50) NOT NULL, cur_id varchar(50) NOT NULL, active int(2) NOT NULL, PRIMARY KEY(num_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// Setup module sequence numbering for all modules (except Invoice).
function custom_addInventoryRows($paramArray){
	global $adb;

	$fieldCreateCount = 0;

	for($index = 0; $index < count($paramArray); ++$index) {
		$criteria = $paramArray[$index];

		$semodule = $criteria['semodule'];
		$adb->pquery("INSERT into vtiger_modentity_num values(?,?,?,?,?,?)",array($adb->getUniqueId("vtiger_modentity_num"),$semodule,$criteria['prefix'],$criteria['startid'],$criteria['curid'],1));
	}
}
$modseq = array(
	'Leads'     =>'LEA',
	'Accounts'  =>'ACC',
	'Campaigns' =>'CAM',	
	'Contacts'  =>'CON',
	'Potentials'=>'POT',
	'HelpDesk'  =>'TT',
	'Quotes'    =>'QUO',
	'SalesOrder'=>'SO',
	'PurchaseOrder'=>'PO',
	'Products'  =>'PRO',
	'Vendors'   =>'VEN',
	'PriceBooks'=>'PB',
	'Faq'       =>'FAQ',
	'Documents' =>'DOC'
);
foreach($modseq as $modname => $prefix) {
	custom_addInventoryRows(
		array(
			array('semodule'=>$modname, 'active'=>'1','prefix'=>$prefix,'startid'=>'1','curid'=>'1')
		)
	);
}
// Setup module sequence for Invoice
require_once('user_privileges/CustomInvoiceNo.php'); 
custom_addInventoryRows(
		array(
			array('semodule'=>'Invoice', 'active'=>'1','prefix'=>decode_html($inv_str),'startid'=>'1','curid'=>$inv_no)
		)
	);

// Add Module Number Field to UI.						

$blockid = getBlockId(6,'LBL_ACCOUNT_INFORMATION');
ExecuteQuery("insert into vtiger_field values (6,".$adb->getUniqueID("vtiger_field").",'account_no','vtiger_account',1,'4','account_no','Account No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_account ADD COLUMN account_no varchar(100) not null");

$blockid = getBlockId(7,'LBL_LEAD_INFORMATION');
ExecuteQuery("insert into vtiger_field values (7,".$adb->getUniqueID("vtiger_field").",'lead_no','vtiger_leaddetails',1,'4','lead_no','Lead No',1,0,0,100,3,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_leaddetails ADD COLUMN lead_no varchar(100) not null");

$blockid = getBlockId(4,'LBL_CONTACT_INFORMATION');
ExecuteQuery("insert into vtiger_field values (4,".$adb->getUniqueID("vtiger_field").",'contact_no','vtiger_contactdetails',1,'4','contact_no','Contact No',1,0,0,100,3,$blockid,1,'V~M',1,null,'BAS',0)");		
ExecuteQuery("ALTER TABLE vtiger_contactdetails ADD COLUMN contact_no varchar(100) not null");

$blockid = getBlockId(2,'LBL_OPPORTUNITY_INFORMATION');
ExecuteQuery("insert into vtiger_field values (2,".$adb->getUniqueID("vtiger_field").",'potential_no','vtiger_potential',1,'4','potential_no','Potential No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_potential ADD COLUMN potential_no varchar(100) not null");

$blockid = getBlockId(26,'LBL_CAMPAIGN_INFORMATION');
ExecuteQuery("insert into vtiger_field values (26,".$adb->getUniqueID("vtiger_field").",'campaign_no','vtiger_campaign',1,'4','campaign_no','Campaign No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_campaign ADD COLUMN campaign_no varchar(100) not null");

$blockid = getBlockId(13,'LBL_TICKET_INFORMATION');
ExecuteQuery("insert into vtiger_field values (13,".$adb->getUniqueID("vtiger_field").",'ticket_no','vtiger_troubletickets',1,'4','ticket_no','Ticket No',1,0,0,100,1,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_troubletickets ADD COLUMN ticket_no varchar(100) not null");

$blockid = getBlockId(14,'LBL_PRODUCT_INFORMATION');
ExecuteQuery("insert into vtiger_field values (14,".$adb->getUniqueID("vtiger_field").",'product_no','vtiger_products',1,'4','product_no','Product No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_products ADD COLUMN product_no varchar(100) not null");

$blockid = getBlockId(8,'LBL_NOTE_INFORMATION');
ExecuteQuery("insert into vtiger_field values (8,".$adb->getUniqueID("vtiger_field").",'note_no','vtiger_notes',1,'4','note_no','Note No',1,0,0,100,7,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_notes ADD COLUMN note_no varchar(100) not null");

$blockid = getBlockId(15,'LBL_FAQ_INFORMATION');
ExecuteQuery("insert into vtiger_field values (15,".$adb->getUniqueID("vtiger_field").",'faq_no','vtiger_faq',1,'4','faq_no','Faq No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_faq ADD COLUMN faq_no varchar(100) not null");

$blockid = getBlockId(18,'LBL_VENDOR_INFORMATION');
ExecuteQuery("insert into vtiger_field values (18,".$adb->getUniqueID("vtiger_field").",'vendor_no','vtiger_vendor',1,'4','vendor_no','Vendor No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_vendor ADD COLUMN vendor_no varchar(100) not null");

$blockid = getBlockId(19,'LBL_PRICEBOOK_INFORMATION');
ExecuteQuery("insert into vtiger_field values (19,".$adb->getUniqueID("vtiger_field").",'pricebook_no','vtiger_pricebook',1,'4','pricebook_no','PriceBook No',1,0,0,100,3,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_pricebook ADD COLUMN pricebook_no varchar(100) not null");

$blockid = getBlockId(22,'LBL_SO_INFORMATION');
ExecuteQuery("insert into vtiger_field values (22,".$adb->getUniqueID("vtiger_field").",'salesorder_no','vtiger_salesorder',1,'4','salesorder_no','SalesOrder No',1,0,0,100,3,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_salesorder ADD COLUMN salesorder_no varchar(100) not null");

$blockid = getBlockId(21,'LBL_PO_INFORMATION');
ExecuteQuery("insert into vtiger_field values (21,".$adb->getUniqueID("vtiger_field").",'purchaseorder_no','vtiger_purchaseorder',1,'4','purchaseorder_no','PurchaseOrder No',1,0,0,100,2,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_purchaseorder ADD COLUMN purchaseorder_no varchar(100) not null");

$blockid = getBlockId(20,'LBL_QUOTE_INFORMATION');
ExecuteQuery("insert into vtiger_field values (20,".$adb->getUniqueID("vtiger_field").",'quote_no','vtiger_quotes',1,'4','quote_no','Quote No',1,0,0,100,3,$blockid,1,'V~M',1,null,'BAS',0)");
ExecuteQuery("ALTER TABLE vtiger_quotes ADD COLUMN quote_no varchar(100) not null");

     
$field_result = $adb->query("select tabid, fieldid from vtiger_field where uitype='4'");
$num_fields = $adb->num_rows($field_result);
for($i = 0; $i<$num_fields; $i++)
{
	$tab_id = $adb->query_result($field_result,$i,'tabid');
	$fld_id = $adb->query_result($field_result,$i,'fieldid');
	addFieldSecurity($tab_id, $fld_id, false);
}


// Update Existing Module Record Numbers.
function custom_updateModSeqNumber($module, $tablename, $colname, $reccol) {
	global $adb;

	if($tablename == '' || $colname == '') return;

	$result = $adb->query("select * from $tablename");
	$rowcount = $adb->num_rows($result);
	for($i=0;$i<$rowcount;$i++)
	{
		$modres= $adb->query("select prefix, cur_id from vtiger_modentity_num where semodule='$module' and active=1");
		$prefix=$adb->query_result($modres,0,'prefix');
		$cur_id=$adb->query_result($modres,0,'cur_id');
		$recid = $adb->query_result($result,$i,$reccol);
		ExecuteQuery("UPDATE $tablename set $colname='".$prefix.$cur_id."' where $reccol=".$recid);
		ExecuteQuery("UPDATE vtiger_modentity_num set cur_id='".($cur_id+1)."' where semodule='$module' and active=1");
	}
}

$upmodrecs = array(
	array('Leads',       'vtiger_leaddetails',    'lead_no',      'leadid'),
	array('Accounts',    'vtiger_account',        'account_no',   'accountid'),
	array('Campaigns',   'vtiger_campaign',       'campaign_no',  'campaignid'),
	array('Contacts',    'vtiger_contactdetails', 'contact_no',   'contactid'),
	array('Potentials',  'vtiger_potential',      'potential_no', 'potentialid'),
	array('HelpDesk',    'vtiger_troubletickets', 'ticket_no',    'ticketid'),
	array('Quotes',      'vtiger_quotes',         'quote_no',     'quoteid'),
	array('SalesOrder',  'vtiger_salesorder',     'salesorder_no','salesorderid'),
	array('PurchaseOrder','vtiger_purchaseorder', 'purchaseorder_no','purchaseorderid'),
	array('Products',    'vtiger_products',       'product_no',   'productid'),
	array('Vendors',     'vtiger_vendor',         'vendor_no',    'vendorid'),
	array('PriceBooks',  'vtiger_pricebook',      'pricebook_no', 'pricebookid'),
	array('Faq',         'vtiger_faq',            'faq_no',       'id'),
	array('Documents',   'vtiger_notes',          'note_no',      'notesid')
);
for($index = 0; $index < count($upmodrecs); ++$index) {
	custom_updateModSeqNumber($upmodrecs[$index][0], $upmodrecs[$index][1], $upmodrecs[$index][2], $upmodrecs[$index][3]);
}

// Rearrange Fields on UI.
$seq_array = array(
		6 => array( 
				'phone' => 4, 'website' => 3, 'fax' => 6, 'tickersymbol' => 5, 'otherphone' => 8, 'parentid' => 7, 'email1' => 10,
				'employees' => 9, 'email2' => 11, 'ownership' => 12, 'rating' => 14, 'industry' => 13, 'siccode' => 16,
				'account_type' => 15, 'annualrevenue' => 18
			  ),
		7 => array(
				'phone' => 5, 'lastname' => 4, 'mobile' => 7, 'company' => 6, 'fax' => 9, 'designation' => 8, 'email' => 11, 'leadsource' => 10,
				'website' => 13, 'industry' =>12, 'leadstatus' => 15, 'annualrevenue' => 14, 'rating' => 17, 'noofemployees' => 16, 'smownerid' => 19,
				'yahooid' => 18, 'createdtime' => 21, 'modifiedtime' => 20
			),
		4 => array(
				'phone' => 5, 'lastname' => 4, 'mobile' => 7, 'accountid' => 6, 'homephone' => 9, 'leadsource' => 8, 'otherphone' => 11, 'title' => 10,
				'fax' => 13, 'department' => 12, 'birthday' => 16, 'email' => 15, 'reportsto' => 18,
				'assistant' => 17, 'yahooid' => 20, 'assistantphone' => 19, 'donotcall' => 22, 'emailoptout' => 21, 'smownerid' => 24,
				'reference' => 23, 'notify_owner' => 26, 'createdtime' => 25, 'modifiedtime' => 27
			),
		2 => array(
				'amount' => 4, 'accountid' => 3, 'closingdate' => 7, 'potentialtype' => 6, 'nextstep' => 9,
				'leadsource' => 8, 'sales_stage' => 11, 'smownerid' => 10, 'probability'  => 13,
				'campaignid' => 12, 'createdtime' => 15, 'modifiedtime' => 14
			),
		26 => array(
				'campaigntype' => 5, 'product_id' => 6, 'campaignstatus' => 4, 'closingdate' => 8, 'smownerid' => 3, 'numsent' => 12,
				'sponsor' => 9, 'targetaudience' => 7, 'targetsize' => 10, 'createdtime' => 11, 'modifiedtime' => 13
			),
		13 => array (
				'smownerid' => 4, 'parent_id' => 2, 'priority' => 6, 'product_id' => 5, 'severity' => 8, 'status' => 7,
				'category' => 10, 'update_log' => 10, 'createdtime' => 9, 'modifiedtime' => 12, 'filename' => 11
			),
		14 => array (
				'productcode' => 4, 'discontinued'  => 3, 'manufacturer' => 6, 'productcategory' => 6, 'sales_start_date' => 5,  'sales_end_date' => 8,
				'start_date' => 7, 'expiry_date' => 10, 'website' => 14, 'vendor_id' => 13, 'mfr_part_no' => 16, 'vendor_part_no' => 15, 'serialno' => 18, 
				'productsheet' => 17, 'glacct' => 20, 'createdtime' => 19, 'modifiedtime' => 21
			),
		15 => array (
				'category' => 4
			),
		18 => array (
				'phone' => 4, 'email' => 3, 'website' => 6, 'glacct' => 5, 'category' => 8, 'createdtime' => 7
			),
		19 => array (
				'active' => 2
			),
		20 => array (
				'quotestage' => 4, 'validtill' => 5
			),
		22 => array (
				'customerno' => 4,'quoteid' => 5, 'purchaseorder' => 6, 'contactid' => 7
			)
	);

foreach ( $seq_array as $tabid=> $field_seq) {
	foreach ($field_seq as $field=>$seq) {
		ExecuteQuery("update vtiger_field set sequence = $seq where tabid = $tabid and columnname = '". $field ."'");

	}
}

ExecuteQuery("update vtiger_field set uitype = '4' where tabid = 23 and columnname = 'invoice_no' ");

// ADD COLUMN TO SPECIFIED MODULE CUSTOM VIEW / FILTER.
function custom_addCustomFilterColumn($module, $filtername, $tablename, $columnname, $fieldname, $displayinfo, $columnindex=0) {
	global $adb;

	$result = $adb->query("SELECT * FROM vtiger_customview WHERE entitytype = '".$adb->sql_escape_string($module)."' AND viewname = '".$adb->sql_escape_string($filtername)."'");
	if($adb->num_rows($result) > 0) {
		$cvid = $adb->query_result($result, 0, 'cvid');
	}
	
	if($cvid == null) return;

	// (cvid, columnindex) is combined key so we have to update columnindex suitably
	ExecuteQuery("UPDATE vtiger_cvcolumnlist set columnindex=columnindex+1 WHERE cvid = $cvid AND columnindex >= $columnindex ORDER BY columnindex DESC");

	$cvcolumnname_value = $tablename . ":" . $columnname . ":" . $fieldname . ":" . $displayinfo;
	ExecuteQuery("INSERT INTO vtiger_cvcolumnlist(cvid, columnindex, columnname) VALUES ($cvid, $columnindex, '$cvcolumnname_value')");
}

// REMOVE SPECIFIED COLUMN FROM MODULE FILTER.
function custom_removeCustomFilterColumn($module, $filtername, $tablename, $columnname, $fieldname, $displayinfo) {
	global $adb;

	$result = $adb->query("SELECT * FROM vtiger_customview WHERE entitytype = '".$adb->sql_escape_string($module)."' AND viewname = '".$adb->sql_escape_string($filtername)."'");
	if($adb->num_rows($result) > 0) {
		$cvid = $adb->query_result($result, 0, 'cvid');
	}

	if($cvid == null) return;

	$cvcolumnname_value = $tablename . ":" . $columnname . ":" . $fieldname . ":" . $displayinfo;
	ExecuteQuery("DELETE FROM vtiger_cvcolumnlist where cvid = $cvid and columnname like '$cvcolumnname_value:%' ");
}

custom_addCustomFilterColumn('Leads',      'All', 'vtiger_leaddetails',    'lead_no',      'lead_no',      'Leads_Lead_No:V');
custom_addCustomFilterColumn('Accounts',   'All', 'vtiger_account',        'account_no',   'account_no',   'Accounts_Account_No:V');
custom_addCustomFilterColumn('Campaigns',  'All', 'vtiger_campaign',       'campaign_no',  'campaign_no',  'Campaigns_Campaign_No:V');
custom_addCustomFilterColumn('Contacts',   'All', 'vtiger_contactdetails', 'contact_no',   'contact_no',   'Contacts_Contact_No:V');
custom_addCustomFilterColumn('Potentials', 'All', 'vtiger_potential',      'potential_no', 'potential_no', 'Potentials_Potential_No:V');

custom_removeCustomFilterColumn('HelpDesk', 'All', 'vtiger_crmentity',      'crmid',     '',          'HelpDesk_Ticket_ID');
custom_addCustomFilterColumn('HelpDesk',    'All', 'vtiger_troubletickets', 'ticket_no', 'ticket_no', 'HelpDesk_Ticket_No:V');

custom_removeCustomFilterColumn('Quotes', 'All', 'vtiger_crmentity', 'crmid',    '',         'Quotes_Quote_No');
custom_addCustomFilterColumn('Quotes',    'All', 'vtiger_quotes',    'quote_no', 'quote_no', 'Quotes_Quote_No:V');

custom_removeCustomFilterColumn('SalesOrder', 'All', 'vtiger_crmentity',  'crmid','','SalesOrder_Order_No');
custom_addCustomFilterColumn('SalesOrder',    'All', 'vtiger_salesorder', 'salesorder_no', 'salesorder_no', 'SalesOrder_SalesOrder_No:V');

custom_removeCustomFilterColumn('PurchaseOrder', 'All', 'vtiger_crmentity', 'crmid', '', 'PurchaseOrder_Order_No');
custom_addCustomFilterColumn('PurchaseOrder',    'All', 'vtiger_purchaseorder', 'purchaseorder_no', 'purchaseorder_no', 'PurchaseOrder_PurchaseOrder_No:V');

custom_addCustomFilterColumn('Products',   'All', 'vtiger_products',  'product_no',   'product_no',   'Products_Product_No:V');
custom_addCustomFilterColumn('Vendors',    'All', 'vtiger_vendor',    'vendor_no',    'vendor_no',    'Vendors_Vendor_No:V');
custom_addCustomFilterColumn('PriceBooks', 'All', 'vtiger_pricebook', 'pricebook_no', 'pricebook_no', 'PriceBooks_PriceBook_No:V');

custom_removeCustomFilterColumn('Faq', 'All', 'vtiger_faq', 'id',     '',       'Faq_FAQ_Id');
custom_addCustomFilterColumn('Faq',    'All', 'vtiger_faq', 'faq_no', 'faq_no', 'Faq_Faq_No:V');

custom_addCustomFilterColumn('Documents',  'All', 'vtiger_notes', 'note_no', 'note_no', 'Notes_Note_No:V');
// Sequence number customization ends

/* Asterisk integration starts here*/
$blockid = $adb->getUniqueID('vtiger_blocks');
	
$sql = "insert into vtiger_blocks values ($blockid,".getTabid('Users').",'LBL_USER_ASTERISK_OPTIONS',6,0,0,0,0,0,1)";
ExecuteQuery($sql);
$sql = "insert into vtiger_field values (".getTabid('Users').",".$adb->getUniqueID('vtiger_field').",'asterisk_extension','vtiger_asteriskextensions',1,1,'asterisk_extension','Asterisk Extension',1,0,0,30,1,$blockid,1,'V~O',1,NULL,'BAS',1)";
ExecuteQuery($sql);
$sql = "insert into vtiger_field values (".getTabid('Users').",".$adb->getUniqueID('vtiger_field').",'use_asterisk','vtiger_asteriskextensions',1,56,'use_asterisk','Use Asterisk',1,0,0,30,2,$blockid,1,'C~O',1,NULL,'BAS',1)";
ExecuteQuery($sql);
$sql = "drop table if exists vtiger_asteriskextensions";
ExecuteQuery($sql);
$sql = "create table vtiger_asteriskextensions (userid varchar(30), asterisk_extension varchar(50), use_asterisk varchar(3)) ENGINE=InnoDB DEFAULT CHARSET=utf8";
ExecuteQuery($sql);
$sql = "drop table if exists vtiger_asterisk";
ExecuteQuery($sql);
$sql = "create table vtiger_asterisk (server varchar(30), port varchar(30), username varchar(50), password varchar(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8";
ExecuteQuery($sql);
$sql = "drop table if exists vtiger_asteriskincomingcalls";
ExecuteQuery($sql);
$sql = "create table vtiger_asteriskincomingcalls (from_number varchar(50) not null, from_name varchar(50) not null, to_number varchar(50) not null, callertype varchar(30)) ENGINE=InnoDB DEFAULT CHARSET=utf8";
ExecuteQuery($sql);
$sql = "drop table if exists vtiger_asteriskoutgoingcalls";
ExecuteQuery($sql);
$sql = "create table vtiger_asteriskoutgoingcalls (userid varchar(30) not null, from_number varchar(30) not null, to_number varchar(30) not null) ENGINE=InnoDB DEFAULT CHARSET=utf8";
ExecuteQuery($sql);
$sql = "drop table if exists vtiger_tab_name_index";
ExecuteQuery($sql);
$sql = "create table vtiger_tab_name_index (tabid int(19), tablename varchar(50), primaryKey varchar(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8";
ExecuteQuery($sql);
$tabid = getTabid('Users');
$tab_name_index_users = array('vtiger_asteriskextensions'=>'userid');
foreach($tab_name_index_users as $key=>$value){
	$sql = "insert into vtiger_tab_name_index values ($tabid, '$key', '$value')";
	ExecuteQuery($sql);
}
ExecuteQuery("update vtiger_field set uitype='11' where fieldname='mobile' and tabid=".getTabid('Leads'));
ExecuteQuery("update vtiger_field set uitype='11' where fieldname='mobile' and tabid=".getTabid('Contacts'));
ExecuteQuery("update vtiger_field set uitype='11' where fieldname='fax' and tabid=".getTabid('Leads'));
ExecuteQuery("update vtiger_field set uitype='11' where fieldname='fax' and tabid=".getTabid('Contacts'));
ExecuteQuery("update vtiger_field set uitype='11' where fieldname='fax' and tabid=".getTabid('Accounts'));
// asterisk integration ends

/* Support to Configure the functionality of Updating Inventory Stock for Invoice/SalesOrder */
ExecuteQuery("ALTER TABLE vtiger_inventoryproductrel ADD COLUMN incrementondel int(11) not null default '0'");
$invoiceids = $adb->pquery("SELECT invoiceid from vtiger_invoice",array());
$noOfRows = $adb->num_rows($invoiceids);
for($i=0;$i<$noOfRows;$i++){
	$adb->pquery("UPDATE vtiger_inventoryproductrel SET incrementondel = 1 WHERE id=?",array($adb->query_result($invoiceids,$i,"invoiceid")));
}

$emm->addEntityMethod("SalesOrder","UpdateInventory","include/InventoryHandler.php","handleInventoryProductRel");//Adding EntityMethod for Updating Products data after creating SalesOrder
$emm->addEntityMethod("Invoice","UpdateInventory","include/InventoryHandler.php","handleInventoryProductRel");//Adding EntityMethod for Updating Products data after creating Invoice

$vtWorkFlow = new VTWorkflowManager($adb);
$invWorkFlow = $vtWorkFlow->newWorkFlow("Invoice");
$invWorkFlow->test = '[{"fieldname":"subject","operation":"does not contain","value":"`!`"}]';
$invWorkFlow->description = "UpdateInventoryProducts On Every Save";
$vtWorkFlow->save($invWorkFlow);

$tm = new VTTaskManager($adb);
$task = $tm->createTask('VTEntityMethodTask', $invWorkFlow->id);
$task->active=true;
$task->methodName = "UpdateInventory";
$tm->saveTask($task);

/* Support to track if a module is of CrmEntity type or not */
ExecuteQuery("ALTER TABLE vtiger_tab ADD COLUMN isentitytype INT NOT NULL DEFAULT 1");

/* Support for different languages to be stored in database instead of config file - Vtlib */
ExecuteQuery("create table vtiger_language(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), " .
		"prefix VARCHAR(10), label VARCHAR(30), lastupdated DATETIME, sequence INT, isdefault INT(1), active INT(1)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		
/* Register default language English. This will automatically register all the other langauges from config file */
require_once('vtlib/Vtiger/Language.php');
$vtlanguage = new Vtiger_Language();
$vtlanguage->register('en_us','US English','English',true,true,true);

/* To store relationship between the modules in a common table */
ExecuteQuery("CREATE TABLE vtiger_crmentityrel (crmid int(11) NOT NULL, module varchar(100) NOT NULL, relcrmid int(11) NOT NULL, relmodule varchar(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

/* To store the field to module relationship for uitype 10 */
ExecuteQuery("CREATE TABLE vtiger_fieldmodulerel (fieldid int(11) NOT NULL, module varchar(100) NOT NULL, relmodule varchar(100) NOT NULL,
  					status varchar(10) default NULL, sequence int(11) default NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

/* Making users and groups depends on vtiger_users_seq */

$user_result = $adb->pquery("select max(id) as userid from vtiger_users",array());
$inc_num = $adb->query_result($user_result,0,"userid");
$grp_result = $adb->pquery("select groupid from vtiger_groups",array());
$num_grps = $adb->num_rows($grp_result);
for($i=$num_grps; $i>=0; $i--) {
	$oldId = $adb->query_result($grp_result,$i,"groupid");
	$newId = $inc_num+$oldId;
	
	//Added just to increment users_seq table
	$adb->getUniqueId("vtiger_users");
	
	ExecuteQuery("UPDATE vtiger_groups set groupid = $newId where groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_users2group set groupid = $newId where groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_group2grouprel set groupid = $newId where groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_group2role set groupid = $newId where groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_group2rs set groupid = $newId where groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_grp2grp set share_groupid = $newId where share_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_grp2grp set to_groupid = $newId where to_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_grp2role set share_groupid = $newId where share_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_grp2rs set share_groupid = $newId where share_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_role2group set to_groupid = $newId where to_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_datashare_rs2grp set to_groupid = $newId where to_groupid = $oldId");
	ExecuteQuery("UPDATE vtiger_tmp_read_group_sharing_per set sharedgroupid = $newId where sharedgroupid = $oldId");
	ExecuteQuery("UPDATE vtiger_tmp_write_group_sharing_per set sharedgroupid = $newId where sharedgroupid = $oldId");
}

$sql_result = $adb->query("select crmid,setype from vtiger_crmentity where smownerid=0 order by setype");
$num_rows = $adb->num_rows($sql_result);
$groupTables_array = array (
							'Leads'=>array ('vtiger_leadgrouprelation','leadid'),
							'Accounts'=>array ('vtiger_accountgrouprelation','accountid'),
							'Contacts'=>array ('vtiger_contactgrouprelation','contactid'),
							'Potentials'=>array ('vtiger_potentialgrouprelation','potentialid'),
							'Quotes'=>array ('vtiger_quotegrouprelation','quoteid'),
							'SalesOrder'=>array ('vtiger_sogrouprelation','salesorderid'),
							'Invoice'=>array ('vtiger_invoicegrouprelation','invoiceid'),
							'PurchaseOrder'=>array ('vtiger_pogrouprelation','purchaseorderid'),
							'HelpDesk'=>array ('vtiger_ticketgrouprelation','ticketid'),
							'Campaigns'=>array ('vtiger_campaigngrouprelation','campaignid'),
							'Calendar'=>array ('vtiger_activitygrouprelation','activityid')
                            );
for($i=0; $i<$num_rows; $i++) {
	$setype = $adb->query_result($sql_result, $i, 'setype');
	$crmid = $adb->query_result($sql_result, $i, 'crmid');
	
	if(array_key_exists($setype, $groupTables_array)) {
		$groupid_sql = "select groupid from vtiger_groups where groupname in (select groupname from ".$groupTables_array[$setype][0]." where ".$groupTables_array[$setype][1]. " = ".$crmid.")";
		$groupid_res = $adb->query($groupid_sql);
		$groupid = $adb->query_result($groupid_res, 0, 'groupid');
	}
	else {
		$sql1_res = $adb->query("select crmid as entityid from vtiger_seattachmentsrel where attachmentsid = ".$crmid);
		$se_recordid = $adb->query_result($sql1_res, 0, 'entityid');
		
		$groupid_res = $adb->query("select smownerid from vtiger_crmentity where crmid = ".$se_recordid);
		$groupid = $adb->query_result($groupid_res, 0, 'smownerid');
	}
	if(isset($groupid) && $groupid != '')
	ExecuteQuery("update vtiger_crmentity set smownerid = $groupid where crmid = $crmid");
}

ExecuteQuery("DROP TABLE vtiger_leadgrouprelation");
ExecuteQuery("DROP TABLE vtiger_accountgrouprelation");
ExecuteQuery("DROP TABLE vtiger_contactgrouprelation");
ExecuteQuery("DROP TABLE vtiger_potentialgrouprelation");
ExecuteQuery("DROP TABLE vtiger_quotegrouprelation");
ExecuteQuery("DROP TABLE vtiger_sogrouprelation");
ExecuteQuery("DROP TABLE vtiger_invoicegrouprelation");
ExecuteQuery("DROP TABLE vtiger_pogrouprelation");
ExecuteQuery("DROP TABLE vtiger_ticketgrouprelation");
ExecuteQuery("DROP TABLE vtiger_campaigngrouprelation");
ExecuteQuery("DROP TABLE vtiger_activitygrouprelation");
// user-group ends

/* Product Comment was Missing in Inventory PDF's - Fixed this by eliminating column product_description from vtiger_products
 * and referring to description column of vtiger_crmentity wherever required */
ExecuteQuery("UPDATE vtiger_crmentity, vtiger_products SET vtiger_crmentity.description=vtiger_products.product_description
					WHERE vtiger_products.productid = vtiger_crmentity.crmid");		
ExecuteQuery("ALTER TABLE vtiger_products DROP COLUMN product_description");
ExecuteQuery("UPDATE vtiger_field set fieldname='description', columnname='description', tablename='vtiger_crmentity'
					WHERE tablename='vtiger_products' AND fieldname='product_description'");

/* Remove Products from all the Main tabs except for Inventory */
$productTabId = getTabid('Products');

$inventoryTabRes = $adb->query("SELECT parenttabid FROM vtiger_parenttab WHERE parenttab_label='Inventory'");
$inventoryTabId = $adb->query_result($inventoryTabRes, 0, 'parenttabid');

ExecuteQuery("DELETE FROM vtiger_parenttabrel WHERE tabid=$productTabId AND parenttabid != $inventoryTabId");
$adb->query("ALTER TABLE vtiger_producttaxrel DROP FOREIGN KEY fk_1_vtiger_producttaxrel");

/* Vtlib Changes - Table added to store different types of links */
ExecuteQuery("CREATE TABLE vtiger_links (linkid INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    		tabid INT, linktype VARCHAR(20), linklabel VARCHAR(30), linkurl VARCHAR(255), linkicon VARCHAR(100), sequence INT)");
ExecuteQuery("CREATE INDEX link_tabidtype_idx ON vtiger_links(tabid,linktype)");

/* Column added to vtiger_tab to track the version of the module */
ExecuteQuery("ALTER TABLE vtiger_tab ADD COLUMN version VARCHAR(10)");

/* Move Settings Page Information to Database */
// This function moves the settings page to database
function moveSettingsToDatabase($adb){
	$adb->query("drop table if exists vtiger_settings_blocks");
	$adb->query("drop table if exists vtiger_settings_field");
	$adb->query("create table vtiger_settings_blocks (blockid int(19), label varchar(250), sequence int(19), primary key pk_vtiger_settings_blocks (blockid))");
	$adb->query("create table vtiger_settings_field (fieldid int(19), blockid int(19), name varchar(250), iconpath text, description text, linkto text, sequence int(19), foreign key fk_vtiger_settings_fields (blockid) references vtiger_settings_blocks(blockid) on delete cascade)");
	
	//icons for all fields
	$icons = array("ico-users.gif",
				"ico-roles.gif",
				"ico-profile.gif",
				"ico-groups.gif",
				"shareaccess.gif",
				"orgshar.gif",
				"audit.gif",
				"set-IcoLoginHistory.gif",
				"orgshar.gif",
				"custom.gif",
				"picklist.gif",
				"settingsTrash.gif",
				"vtlib_modmng.gif",
				"ViewTemplate.gif",
				"mailmarge.gif",
				"notification.gif",
				"inventory.gif",
				"company.gif",
				"ogmailserver.gif",
				"backupserver.gif",
				"assign.gif",
				"currency.gif",
				"taxConfiguration.gif",
				"system.gif",
				"proxy.gif",
				"announ.gif",
				"set-IcoTwoTabConfig.gif",
				"migrate.gif",
				"terms.gif",
				"settingsInvNumber.gif",
				"mailScanner.gif",
				"settingsWorkflow.png");

	//labels for blocks
	$blocks = array('LBL_USER_MANAGEMENT',
				'LBL_STUDIO', 
				'LBL_COMMUNICATION_TEMPLATES', 
				'LBL_OTHER_SETTINGS');

	//field names
	$names = array('LBL_USERS',
				'LBL_ROLES',
				'LBL_PROFILES',
				'USERGROUPLIST',
				'LBL_SHARING_ACCESS',
				'LBL_FIELDS_ACCESS',
				'LBL_AUDIT_TRAIL',
				'LBL_LOGIN_HISTORY_DETAILS',
				'LBL_LAYOUT_EDITOR',
				'LBL_CUSTOM_FIELDS',
				'LBL_PICKLIST_EDITOR',
				'LBL_RECYCLEBIN',
				'VTLIB_LBL_MODULE_MANAGER',
				'EMAILTEMPLATES',
				'LBL_MAIL_MERGE',
				'NOTIFICATIONSCHEDULERS',
				'INVENTORYNOTIFICATION',
				'LBL_COMPANY_DETAILS',
				'LBL_MAIL_SERVER_SETTINGS',
				'LBL_BACKUP_SERVER_SETTINGS',
				'LBL_ASSIGN_MODULE_OWNERS',
				'LBL_CURRENCY_SETTINGS',
				'LBL_TAX_SETTINGS',
				'LBL_SYSTEM_INFO',
				'LBL_PROXY_SETTINGS',
				'LBL_ANNOUNCEMENT',
				'LBL_DEFAULT_MODULE_VIEW',
				'LBL_MIGRATION',
				'INVENTORYTERMSANDCONDITIONS',
				'LBL_CUSTOMIZE_MODENT_NUMBER',
				'LBL_MAIL_SCANNER',
				'LBL_LIST_WORKFLOWS',);


	//description for fields
	$description = array('LBL_USER_DESCRIPTION', 
					'LBL_ROLE_DESCRIPTION', 
					'LBL_PROFILE_DESCRIPTION', 
					'LBL_GROUP_DESCRIPTION', 
					'LBL_SHARING_ACCESS_DESCRIPTION', 
					'LBL_SHARING_FIELDS_DESCRIPTION', 
					'LBL_AUDIT_DESCRIPTION', 
					'LBL_LOGIN_HISTORY_DESCRIPTION', 
					'LBL_LAYOUT_EDITOR_DESCRIPTION', 
					'LBL_CUSTOM_FIELDS_DESCRIPTION', 
					'LBL_PICKLIST_DESCRIPTION', 
					'LBL_RECYCLEBIN_DESCRIPTION',
					'VTLIB_LBL_MODULE_MANAGER_DESCRIPTION', 
					'LBL_EMAIL_TEMPLATE_DESCRIPTION', 
					'LBL_MAIL_MERGE_DESCRIPTION', 
					'LBL_NOTIF_SCHED_DESCRIPTION', 
					'LBL_INV_NOTIF_DESCRIPTION', 
					'LBL_COMPANY_DESCRIPTION', 
					'LBL_MAIL_SERVER_DESCRIPTION', 
					'LBL_BACKUP_SERVER_DESCRIPTION', 
					'LBL_MODULE_OWNERS_DESCRIPTION',
					'LBL_CURRENCY_DESCRIPTION', 
					'LBL_TAX_DESCRIPTION', 
					'LBL_SYSTEM_DESCRIPTION', 
					'LBL_PROXY_DESCRIPTION', 
					'LBL_ANNOUNCEMENT_DESCRIPTION', 
					'LBL_DEFAULT_MODULE_VIEW_DESC', 
					'LBL_MIGRATION_INFO', 
					'LBL_INV_TANDC_DESCRIPTION', 
					'LBL_CUSTOMIZE_MODENT_NUMBER_DESCRIPTION', 
					'LBL_MAIL_SCANNER_DESCRIPTION', 
					'LBL_LIST_WORKFLOWS_DESCRIPTION');

	$field_labels = array('LBL_USERS',
					'LBL_ROLES',
					'LBL_PROFILES', 
					'USERGROUPLIST', 
					'LBL_SHARING_ACCESS', 
					'LBL_FIELDS_ACCESS', 
					'LBL_AUDIT_TRAIL', 
					'LBL_LOGIN_HISTORY_DETAILS', 
					'LBL_LAYOUT_EDITOR', 
					'LBL_CUSTOM_FIELDS', 
					'LBL_PICKLIST_EDITOR', 
					'LBL_RECYCLEBIN', 
					'VTLIB_LBL_MODULE_MANAGER', 
					'EMAILTEMPLATES', 
					'WORDINTEGRATION', 
					'NOTIFICATIONSCHEDULERS', 
					'INVENTORYNOTIFICATION', 
					'LBL_COMPANY_DETAILS', 
					'LBL_MAIL_SERVER_SETTINGS', 
					'LBL_BACKUP_SERVER_SETTINGS', 
					'LBL_MODULE_OWNERS', 
					'LBL_CURRENCY_SETTINGS', 
					'LBL_TAX_SETTINGS', 
					'LBL_SYSTEM_INFO', 
					'LBL_PROXY_SETTINGS', 
					'LBL_ANNOUNCEMENT', 
					'LBL_DEFAULT_MODULE_VIEW', 
					'LBL_MIGRATION', 
					'LBL_INVENTORY_TANDC', 
					'LBL_CUSTOMIZE_MODENT_NUMBER', 
					'LBL_MAIL_SCANNER', 
					'LBL_LIST_WORKFLOWS'); 

	$links = array('index.php?module=Administration&action=index&parenttab=Settings',
				'index.php?module=Settings&action=listroles&parenttab=Settings',
				'index.php?module=Settings&action=ListProfiles&parenttab=Settings',
				'index.php?module=Settings&action=listgroups&parenttab=Settings',
				'index.php?module=Settings&action=OrgSharingDetailView&parenttab=Settings',
				'index.php?module=Settings&action=DefaultFieldPermissions&parenttab=Settings',
				'index.php?module=Settings&action=AuditTrailList&parenttab=Settings',
				'index.php?module=Settings&action=ListLoginHistory&parenttab=Settings',
				'index.php?module=Settings&action=LayoutBlockList&parenttab=Settings',
				'index.php?module=Settings&action=CustomFieldList&parenttab=Settings',
				'index.php?module=Settings&action=PickList&parenttab=Settings',
				'index.php?module=Recyclebin&action=index&parenttab=Settings',
				'index.php?module=Settings&action=ModuleManager&parenttab=Settings',
				'index.php?module=Settings&action=listemailtemplates&parenttab=Settings',
				'index.php?module=Settings&action=listwordtemplates&parenttab=Settings',
				'index.php?module=Settings&action=listnotificationschedulers&parenttab=Settings',
				'index.php?module=Settings&action=listinventorynotifications&parenttab=Settings',
				'index.php?module=Settings&action=OrganizationConfig&parenttab=Settings',
				'index.php?module=Settings&action=EmailConfig&parenttab=Settings',
				'index.php?module=Settings&action=BackupServerConfig&parenttab=Settings',
				'index.php?module=Settings&action=ListModuleOwners&parenttab=Settings',
				'index.php?module=Settings&action=CurrencyListView&parenttab=Settings',
				'index.php?module=Settings&action=TaxConfig&parenttab=Settings',
				'index.php?module=System&action=listsysconfig&parenttab=Settings',
				'index.php?module=Settings&action=ProxyServerConfig&parenttab=Settings',
				'index.php?module=Settings&action=Announcements&parenttab=Settings',
				'index.php?module=Settings&action=DefModuleView&parenttab=Settings',
				'index.php?module=Migration&action=index&parenttab=Settings',
				'index.php?module=Settings&action=OrganizationTermsandConditions&parenttab=Settings',
				'index.php?module=Settings&action=CustomModEntityNo&parenttab=Settings',
				'index.php?module=Settings&action=MailScanner&parenttab=Settings',
				'index.php?module=com_vtiger_workflow&action=workflowlist&parenttab=Settings',);

	//insert settings blocks
	$count = count($blocks);
	for($i=0; $i<$count; $i++){
		$adb->query("insert into vtiger_settings_blocks values ($i+1, '$blocks[$i]', $i+1)");
	}
	
	$count = count($icons);
	//insert settings fields
	$block=1;
	for($i=0, $seq=1; $i<$count; $i++, $seq++){
		if($i==8 || $i==13 || $i==17){
			$block++;
			$seq = 1;
		}	
		$adb->query("insert into vtiger_settings_field values ($i+1, $block, '$names[$i]', '$icons[$i]', '$description[$i]', '$links[$i]', $seq)");
	}
}
//move settings page to database starts
moveSettingsToDatabase($adb);
//settings page to database ends
// END


/* Email status tracking*/
ExecuteQuery("CREATE TABLE vtiger_email_access(crmid INT, mailid INT, accessdate DATE, accesstime TIME)");
ExecuteQuery("CREATE TABLE vtiger_email_track(crmid INT, mailid INT, count INT, primary key(crmid, mailid))");

$fieldid = $adb->getUniqueID('vtiger_field');
ExecuteQuery("INSERT INTO vtiger_field VALUES ('10',".$fieldid.", 'count', 'vtiger_email_track', '1', '25', 'count', 'Access Count', '1', '0', '0', '100', '6', '21', '3', 'V~O', '1', NULL, 'BAS', 0)");
addFieldSecurity(10, $fieldid, 'false');
// END

/* Reports Revamped */
ExecuteQuery("ALTER TABLE vtiger_tab ADD COLUMN enablereports int(11) NOT NULL DEFAULT '0'");
ExecuteQuery("UPDATE vtiger_tab SET enablereports =1 WHERE name IN (\"Dashboard\",\"Home\",\"Reports\",\"Rss\",\"Emails\",\"Users\",\"PriceBooks\",\"Portal\",\"Events\",\"Faq\",\"Webmails\",\"Recyclebin\")");
ExecuteQuery("ALTER TABLE vtiger_report ADD COLUMN owner int(11) NOT NULL");
ExecuteQuery("ALTER TABLE vtiger_report ADD COLUMN sharingtype varchar(200) NOT NULL DEFAULT 'Private'");
ExecuteQuery("UPDATE vtiger_report SET sharingtype='Public', owner=1 WHERE state='SAVED'");
ExecuteQuery("Create table vtiger_reportsharing(reportid int(19) not null,shareid int(19) not null,setype varchar(200) NOT NULL)");
ExecuteQuery("Create table vtiger_reportfilters(filterid int(11) not null,name varchar(200) not null)");
ExecuteQuery("INSERT INTO vtiger_reportfilters values(1,'Private')");
ExecuteQuery("INSERT INTO vtiger_reportfilters values(2,'Public')");
ExecuteQuery("INSERT INTO vtiger_reportfilters values(3,'Shared')");

/* Added for tooltip manager */
$sql = "create table vtiger_quickview (fieldid int(19) not null, related_fieldid int(19) not null, sequence int(19) not null, view int(19) not null, foreign key(fieldid) references vtiger_field(fieldid) on delete cascade)";
ExecuteQuery($sql);
//add tooltip manager ends

$migrationlog->debug("\n\nDB Changes from 5.0.4 to 5.1.0 -------- Ends \n\n");

?>
