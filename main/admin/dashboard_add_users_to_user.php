<?php
/* For licensing terms, see /license.txt */

/**
==============================================================================
*	Interface for assigning users to Human Resources Manager
*	@package chamilo.admin
==============================================================================
*/

// name of the language file that needs to be included
$language_file='admin';
// resetting the course id
$cidReset=true;

// including some necessary dokeos files
require_once '../inc/global.inc.php';
require_once '../inc/lib/xajax/xajax.inc.php';
require_once api_get_path(LIBRARY_PATH).'usermanager.lib.php';

// create an ajax object
$xajax = new xajax();
$xajax -> registerFunction ('search_users');

// setting the section (for the tabs)
$this_section = SECTION_PLATFORM_ADMIN;

// Access restrictions
api_protect_admin_script(true);

// setting breadcrumbs
$interbreadcrumb[] = array('url' => 'index.php', 'name' => get_lang('PlatformAdmin'));
$interbreadcrumb[] = array('url' => 'user_list.php','name' => get_lang('UserList'));

// Database Table Definitions
$tbl_user 			= 	Database::get_main_table(TABLE_MAIN_USER);

// setting the name of the tool
$tool_name= get_lang('AssignUsersToHumanResourcesManager');

// initializing variables
$id_session=intval($_GET['id_session']);
$hrm_id = intval($_GET['user']);
$hrm_info = api_get_user_info($hrm_id);
$user_anonymous  = api_get_anonymous_id();
$current_user_id = api_get_user_id();

$add_type = 'multiple';
if(isset($_GET['add_type']) && $_GET['add_type']!=''){
	$add_type = Security::remove_XSS($_REQUEST['add_type']);
}

if (!api_is_platform_admin()) {
	api_not_allowed(true);
}

function search_users($needle,$type) {
	global $tbl_user, $user_anonymous, $current_user_id, $hrm_id;

	$xajax_response = new XajaxResponse();
	$return = '';
	if(!empty($needle) && !empty($type)) {
		// xajax send utf8 datas... datas in db can be non-utf8 datas
		$charset = api_get_setting('platform_charset');
		$needle = api_convert_encoding($needle, $charset, 'utf-8');

		$assigned_users_to_hrm = UserManager::get_users_followed_by_drh($hrm_id);
		$assigned_users_id = array_keys($assigned_users_to_hrm);
		$without_assigned_users = '';
		if (count($assigned_users_id) > 0) {
			$without_assigned_users = " AND user_id NOT IN(".implode(',',$assigned_users_id).")";
		}

		$sql = "SELECT user_id, username, lastname, firstname FROM $tbl_user user
				WHERE  ".(api_sort_by_first_name() ? 'firstname' : 'lastname')." LIKE '$needle%'  AND user_id NOT IN ($user_anonymous, $current_user_id, $hrm_id) $without_assigned_users";
		$rs	= Database::query($sql);

		$user_list = array();
		$return .= '<select id="origin" name="NoAssignedUsersList[]" multiple="multiple" size="20" style="width:340px;">';
		while($user = Database :: fetch_array($rs)) {
			$user_list[] = $user['id'];
			$person_name = api_get_person_name($user['firstname'], $user['lastname']);
			$return .= '<option value="'.$user['user_id'].'" title="'.htmlspecialchars($person_name,ENT_QUOTES).'">'.$person_name.' ('.$user['username'].')</option>';
		}
		$return .= '</select>';
		$xajax_response -> addAssign('ajax_list_users_multiple','innerHTML',api_utf8_encode($return));
	}
	$_SESSION['user_list'] = $user_list;
	return $xajax_response;
}

$xajax -> processRequests();
$htmlHeadXtra[] = $xajax->getJavascript('../inc/lib/xajax/');
$htmlHeadXtra[] = '
<script type="text/javascript">
<!--
function moveItem(origin , destination) {
	for(var i = 0 ; i<origin.options.length ; i++) {
		if(origin.options[i].selected) {
			destination.options[destination.length] = new Option(origin.options[i].text,origin.options[i].value);
			origin.options[i]=null;
			i = i-1;
		}
	}
	destination.selectedIndex = -1;
	sortOptions(destination.options);
}
function sortOptions(options) {
	var newOptions = new Array();
	for (i = 0 ; i<options.length ; i++) {
		newOptions[i] = options[i];
	}
	newOptions = newOptions.sort(mysort);
	options.length = 0;
	for(i = 0 ; i < newOptions.length ; i++){
		options[i] = newOptions[i];
	}
}
function mysort(a, b) {
	if (a.text.toLowerCase() > b.text.toLowerCase()) {
		return 1;
	}
	if (a.text.toLowerCase() < b.text.toLowerCase()) {
		return -1;
	}
	return 0;
}

function valide() {
	var options = document.getElementById("destination").options;
	for (i = 0 ; i<options.length ; i++) {
		options[i].selected = true;
	}
	document.forms.formulaire.submit();
}
function remove_item(origin) {
	for(var i = 0 ; i<origin.options.length ; i++) {
		if(origin.options[i].selected) {
			origin.options[i]=null;
			i = i-1;
		}
	}
}
-->
</script>';

$formSent=0;
$errorMsg = $firstLetterUser = '';
$UserList = array();

$msg = '';
if (intval($_POST['formSent']) == 1) {
	$user_list = $_POST['UsersList'];
	$affected_rows = UserManager::suscribe_users_to_hr_manager($hrm_id,$user_list);
	if ($affected_rows)	{
		$msg = get_lang('AssignedUsersHaveBeenUpdatedSuccessfully');
	}
}

// display the dokeos header
Display::display_header($tool_name);

echo '<div class="actions" style="height:22px;">
<span style="float: right;margin:0px;padding:0px;">
<a href="dashboard_add_courses_to_user.php?user='.$hrm_id.'">'.Display::return_icon('course_add.gif', get_lang('AssignCourses'), array('style'=>'vertical-align:middle')).' '.get_lang('AssignCourses').'</a>
<a href="dashboard_add_sessions_to_user.php?user='.$hrm_id.'">'.Display::return_icon('view_more_stats.gif', get_lang('AssignSessions'), array('style'=>'vertical-align:middle')).' '.get_lang('AssignSessions').'</a></span>
<span style="vertical-align:middle">'.get_lang('AssignUsersTo').'&nbsp;'.api_get_person_name($hrm_info['firstname'], $hrm_info['lastname']).'</span></div>';	

// *******************

$assigned_users_to_hrm = UserManager::get_users_followed_by_drh($hrm_id);
$assigned_users_id = array_keys($assigned_users_to_hrm);
$without_assigned_users = '';
if (count($assigned_users_id) > 0) {
	$without_assigned_users = " user_id NOT IN(".implode(',',$assigned_users_id).") AND ";
}

$search_user = '';
if (isset($_POST['firstLetterUser'])) {
	$needle = Database::escape_string($_POST['firstLetterUser']);
	$search_user ="AND ".(api_sort_by_first_name() ? 'firstname' : 'lastname')." LIKE '$needle%'";
}

$sql = "SELECT user_id, username, lastname, firstname FROM $tbl_user user
		WHERE  $without_assigned_users user_id NOT IN ($user_anonymous, $current_user_id, $hrm_id) $search_user ";
$result	= Database::query($sql);

?>
<form name="formulaire" method="post" action="<?php echo api_get_self(); ?>?user=<?php echo $hrm_id ?>" style="margin:0px;" <?php if($ajax_search){echo ' onsubmit="valide();"';}?>>
<input type="hidden" name="formSent" value="1" />
<?php
if(!empty($msg)) {
	Display::display_normal_message($msg); //main API
}
?>
<table border="0" cellpadding="5" cellspacing="0" width="100%" align="center">
<tr>
	<td align="left"></td>
	<td align="left"></td>
	<td width="" align="center"> &nbsp;	</td>
</tr>
<tr>
  <td width="45%" align="center"><b><?php echo get_lang('UserListInPlatform') ?> :</b></td>
  <td width="10%">&nbsp;</td>
  <td align="center" width="45%"><b><?php echo get_lang('AssignedUsersListToHumanResourcesManager') ?> :</b></td>
</tr>

<?php if($add_type == 'multiple') { ?>
<tr><td width="45%" align="center">
 <?php echo get_lang('FirstLetterUser');?> :
     <select name="firstLetterUser" onchange = "xajax_search_users(this.value,'multiple')">
      <option value="%">--</option>
      <?php
      echo Display :: get_alphabet_options($_POST['firstLetterUser']);
      ?>
     </select>
</td>
<td>&nbsp;</td></tr>
<?php } ?>
<tr>
  <td width="45%" align="center">
	<div id="ajax_list_users_multiple">
	<select id="origin" name="NoAssignedUsersList[]" multiple="multiple" size="20" style="width:340px;">
	<?php
	while ($enreg = Database::fetch_array($result)) {
		$person_name = api_get_person_name($enreg['firstname'], $enreg['lastname']);
	?>
		<option value="<?php echo $enreg['user_id']; ?>" <?php echo 'title="'.htmlspecialchars($person_name,ENT_QUOTES).'"';?>><?php echo $person_name.' ('.$enreg['username'].')'; ?></option>
	<?php } ?>
	</select></div>
  </td>

  <td width="10%" valign="middle" align="center">
  <?php
  if ($ajax_search) {
  ?>
  	<button class="arrowl" type="button" onclick="remove_item(document.getElementById('destination'))"></button>
  <?php
  }
  else
  {
  ?>
  	<button class="arrowr" type="button" onclick="moveItem(document.getElementById('origin'), document.getElementById('destination'))" onclick="moveItem(document.getElementById('origin'), document.getElementById('destination'))"></button>
	<br /><br />
	<button class="arrowl" type="button" onclick="moveItem(document.getElementById('destination'), document.getElementById('origin'))" onclick="moveItem(document.getElementById('destination'), document.getElementById('origin'))"></button>
  <?php
  }
  ?>
	<br /><br /><br /><br /><br /><br />
	<?php
		echo '<button class="save" type="button" value="" onclick="valide()" >'.get_lang('AssignUsersToHumanResourcesManager').'</button>';
	?>
  </td>
  <td width="45%" align="center">
  <select id='destination' name="UsersList[]" multiple="multiple" size="20" style="width:320px;">
	<?php
	if (is_array($assigned_users_to_hrm)) {
		foreach($assigned_users_to_hrm as $enreg) {
			$person_name = api_get_person_name($enreg['firstname'], $enreg['lastname']);
	?>
		<option value="<?php echo $enreg['user_id']; ?>" <?php echo 'title="'.htmlspecialchars($person_name,ENT_QUOTES).'"'; ?>><?php echo $person_name.' ('.$enreg['username'].')'; ?></option>
	<?php }
	}?>
  </select></td>
</tr>
</table>

</form>

<?php
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display::display_footer();
?>
