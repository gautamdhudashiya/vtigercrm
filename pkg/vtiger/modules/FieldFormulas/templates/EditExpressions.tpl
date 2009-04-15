{*<!--
/*********************************************************************************
  ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
   * ("License"); You may not use this file except in compliance with the License
   * The Original Code is:  vtiger CRM Open Source
   * The Initial Developer of the Original Code is vtiger.
   * Portions created by vtiger are Copyright (C) vtiger.
   * All Rights Reserved.
  *
 ********************************************************************************/
-->*}

<script src="modules/FieldFormulas/resources/jquery-1.2.6.js" type="text/javascript" charset="utf-8"></script>
<script src="modules/FieldFormulas/resources/functional.js" type="text/javascript" charset="utf-8"></script>
<script src="modules/FieldFormulas/resources/json2.js" type="text/javascript" charset="utf-8"></script>
<script type="text/javascript" charset="utf-8">
	var strings = {$JS_STRINGS};
</script>
<script src="modules/FieldFormulas/resources/editexpressionscript.js" type="text/javascript" charset="utf-8"></script>


<table align="center" border="0" cellpadding="0" cellspacing="0" width="98%">
<tbody><tr>
        <td valign="top"><img src="{'showPanelTopLeft.gif'|@vtiger_imageurl:$THEME}"></td>
        <td class="showPanelBg" style="padding: 10px;" valign="top" width="100%">
<br>
<div align=center>
		{include file='SetMenu.tpl'}
		
<!-- DISPLAY -->
<div id="view">
	{include file='modules/FieldFormulas/ModuleTitle.tpl'}
	<input type="hidden" id="pick_module" name="pick_module" value={$FORMODULE} />
	<table class="tableHeading" width="100%" border="0" cellspacing="0" cellpadding="5" align="center">
		<tr>
			<td class="big" nowrap="">
				<strong><span id="module_info">{$MOD.LBL_MODULE_INFO} "{$FORMODULE|@getTranslatedString:$MOD}"</span></strong>
			</td>
		</tr>
	</table>
	<table class="listTableTopButtons" width="100%" border="0" cellspacing="0" cellpadding="5">
		<tr>
			<td class="small"> <span id="status_message"></span> </td>
			<td class="small" align="right">
				<input type="button" class="crmButton create small" 
					value="{'LBL_NEW_FIELD_EXPRESSION_BUTTON'|@getTranslatedString:$MOD}" id='new_field_expression'/>
			</td>
		</tr>
	</table>
	<div id='editpopup' class='editpopup' style='display:none;' >
		<table width="100%" cellspacing="0" cellpadding="5" border="0" class="layerHeadingULine">
			<tr>
				<td width="60%" align="left" class="layerPopupHeading">
					{'LBL_EDIT_EXPRESSION'|@getTranslatedString:$MOD}
					</td>
				<td width="40%" align="right">
					<a href="javascript:void(0);" id="editpopup_close">
						<img border="0" align="absmiddle" src="{'close.gif'|@vtiger_imageurl:$THEME}"/>
					</a>
				</td>
			</tr>
		</table>
		<table width="100%" bgcolor="white" align="center" border="0" cellspacing="0" cellpadding="5">
			<tr>
				<td>
					<p>
						{'LBL_FIELD'|@getTranslatedString:$MOD}: 
						<select id='editpopup_field' class='small'>
	
							<option></option>
	
						</select>
					</p>
					<p>{'LBL_EXPRESSION'|@getTranslatedString:$MOD}:</p>
					<textarea name="Name" rows="8" cols="40" id='editpopup_expression'></textarea>
				</td>
				<td width="50%">
					<table width="50%" border="0" cellspacing="0" cellpadding="5" align="center">
						<tr>
							<td class="datalabel" nowrap="nowrap" align="right">
								<b>{'LBL_FIELDS'|@getTranslatedString:$MOD}: </b>
							</td>
							<td align="left">
								<select id='editpopup_fieldnames' class='small'></select>
							</td>
						</tr>
						<tr>
							<td class="datalabel" nowrap="nowrap" align="right">
								<b>{'LBL_FUNCTIONS'|@getTranslatedString:$MOD}: </b>
							</td>
							<td align="left">
								<select id='editpopup_functions' class='small'></select>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<table width="100%" cellspacing="0" cellpadding="5" border="0" class="layerPopupTransport">
			<tr><td align="center">
				<input type="button" class="crmButton small save" value="{$APP.LBL_SAVE_BUTTON_LABEL}" name="save" id='editpopup_save'/> 
				<input type="button" class="crmButton small cancel" value="{$APP.LBL_CANCEL_BUTTON_LABEL}" name="cancel" id='editpopup_cancel'/>
			</td></tr>
		</table>
	</div>
	<table class="listTable" width="100%" border="0" cellspacing="0" cellpadding="5" id='expressionlist'>
		<tr>
			<td class="colHeader small" width="20%">
				{'LBL_FIELD'|@getTranslatedString:$MOD}				
			</td>
			<td class="colHeader small" width="65">
				{'LBL_EXPRESSION'|@getTranslatedString:$MOD}
			</td>
			<td class="colHeader small" width="15%">
				{'LBL_SETTINGS'|@getTranslatedString:$MOD}
			</td>
		</tr>
	</table>
</div>

</td>
        </tr>
        </table>
        </td>
        </tr>
        </table>
        </div>

        </td>
        <td valign="top"><img src="{'showPanelTopRight.gif'|@vtiger_imageurl:$THEME}"></td>
        </tr>
</tbody>
</table>
<br>
