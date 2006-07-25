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

global $theme;
$theme_path="themes/".$theme."/";
$image_path=$theme_path."images/";

$productid = $_REQUEST['productid'];
$rowid = $_REQUEST['curr_row'];
$product_total = $_REQUEST['productTotal'];

$tax_details = getTaxDetailsForProduct($productid,'all');//we should pass available instead of all if we want to display only the available taxes.
$associated_tax_count = count($tax_details);


$tax_div = '
		<table width="100%" border="0" cellpadding="5" cellspacing="0" class="small">
		   <tr>
			<td nowrap align="left" >Set Tax for : '.$product_total.'</td>
			<td>&nbsp;</td>
			<td align="right"><img src="'.$image_path.'close.gif" border="0" onClick="fnHidePopDiv(\'tax_div'.$rowid.'\')" style="cursor:pointer;"></td>
		   </tr>
	   ';

$net_tax_total = 0.00;
for($i=0;$i<count($tax_details);$i++)
{
	$tax_name = $tax_details[$i]['taxname'];
	$tax_percentage = $tax_details[$i]['percentage'];
	$tax_name_percentage = $tax_name."_percentage".$rowid;
	$tax_name_total = "popup_tax_row".$rowid;//$tax_name."_total".$rowid;
	$tax_total = $product_total*$tax_percentage/100.00;

	$net_tax_total += $tax_total;
	$tax_div .= '
		   <tr>
			<td align="left" class="lineOnTop">
				<input type="text" class="small" size="5" name="'.$tax_name_percentage.'" id="'.$tax_name_percentage.'" value="'.$tax_percentage.'" onBlur="calcCurrentTax(\''.$tax_name_percentage.'\','.$rowid.','.$i.')">&nbsp;%
			</td>
			<td align="center" class="lineOnTop">'.$tax_name.'</td>
			<td align="right" class="lineOnTop">
				<input type="text" class="small" size="6" name="'.$tax_name_total.'" id="'.$tax_name_total.'" style="cursor:pointer;" value="'.$tax_total.'" readonly>
			</td>
		   </tr>
	    ';
}

if($associated_tax_count == 0)
{
	$tax_div .= '<tr><td colspan="3" align="left" class="lineOnTop">No taxes associated with this product.</td></tr>';
}

$tax_div .= '</table>';
$tax_div .= '<input type="hidden" id="hdnTaxTotal'.$rowid.'" name="hdnTaxTotal'.$rowid.'" value="'.$net_tax_total.'">';

echo $tax_div;


?>
