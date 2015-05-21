<?php
/**
* RankExt - Rank extension for MyBB
* Written by Megan Starr
* Credit for some plugin structure to AccountSwitcher extension
*      -> http://mybbplugins.de.vu
*/

if(!defined("IN_MYBB"))
{
	die("You Cannot Access This File Directly. Please Make Sure IN_MYBB Is Defined.");
}

/**
 * Add custom settings to plugin page
 * For Tier and Rank addition/update/deletion
 */
// Add Hooks
$plugins->add_hook("admin_config_settings_change", "rankext_custom_settings");
$plugins->add_hook("admin_config_settings_change_commit", "rankext_custom_settings_commit");

// Determine if we are in the correct setting group
function rankext_custom_settings() {
	global $db, $mybb, $plugins;

	$query = $db->simple_select('settinggroups', 'gid', "name='rankext'");
	$result = $query->fetch_assoc();

	if((int)$mybb->input['gid'] == (int)$result['gid']) {
		// We are in the right settings, now do some magic!
		$plugins->add_hook("admin_page_output_header", "rankext_scripts");
		$plugins->add_hook("admin_formcontainer_end", "rankext_add_ranks_and_tiers");
	}
}

// Add script that allows adding of new ranks/tiers to the header
function rankext_scripts($args) {
	$args[this]->extra_header .= '<script src="../inc/plugins/rankext/rankext_scripts_admin.js" type="text/javascript"></script>';
}

/**
 * Add form to plugin settings
 */
function rankext_add_ranks_and_tiers() {
	global $db, $mybb, $form, $form_container;
	$ranks = array();
	$tiers = array();
	$ranktieropts = array(
		'0' => 'None'
	);

	// Print tier edit rows
	$query = $db->simple_select('rankext_tiers', '*', 'true', array(
		"order_by" => 'seq',
    "order_dir" => 'ASC'));
	$form_container->output_row("", "", "<h2 style='display:inline;'>Tier Management</h2>");
	while($tier = $query->fetch_assoc()) {
		$tiers[] = $tier;
		$ranktieropts[$tier['id']] = $tier['label'];
	}
	$tierform = '<table><tr><td>Delete?</td><td>Order</td><td>Label</td></tr>';
	foreach($tiers as $tier) {
		$tierform .= "<tr><td>".$form->generate_check_box("delete_tier[]", $tier['id'], '', array('checked' => false))."</td>"
				."<td>".$form->generate_text_box("tier_seq".$tier['id'], $tier['seq'])."</td>"
				."<td>".$form->generate_text_box("tier_label".$tier['id'], $tier['label'])." </td></tr>";
	}
	$tierform .= "</table><button type='button' id='create_tier'>Create New Tier</button> <i>(Save first!)</i>";
	$form_container->output_row("", "", $tierform);

	// Print rank edit rows
	$query = $db->simple_select('rankext_ranks', '*', 'true', array(
		"order_by" => 'seq',
    "order_dir" => 'ASC'));
	$form_container->output_row("", "", "<h2 style='display:inline;'>Rank Management</h2>");
	while($rank = $query->fetch_assoc()) {
		$ranks[] = $rank;
	}
	$rankform = '<table><tr><td>Delete?</td><td>Order</td><td>Tier</td><td>Label</td><td>Always Visible?</td></tr>';
	foreach($ranks as $rank) {
		$rankform .= "<tr><td>".$form->generate_check_box("delete_rank[]", $rank['id'], '', array('checked' => false))."</td>"
				."<td>".$form->generate_text_box("rank_seq".$rank['id'], $rank['seq'])."</td>"
				."<td>".$form->generate_select_box("rank_tier".$rank['id'], $ranktieropts, $rank['tierid'])."</td>"
				."<td>".$form->generate_text_box("rank_label".$rank['id'], $rank['label'])." </td>"
				."<td>".$form->generate_check_box("rank_visible".$rank['id'], 1, '', array('checked' => $rank['visible']))."</td></tr>";
	}
	$rankform .= "</table><button type='button' id='create_rank'>Create New Rank</button> <i>(Save first!)</i>";
	$form_container->output_row("", "", $rankform);
}

/**
 * Save custom settings on submit
 */
function rankext_custom_settings_commit() {
	global $db, $mybb;
	$ranks = array();
	$tiers = array();

	$query = $db->simple_select('settinggroups', 'gid', "name='rankext'");
	$result = $query->fetch_assoc();

	if((int)$mybb->input['gid'] == (int)$result['gid']) {
		// We are in the right settings, now do some magic!

		//First we delete so that we don't do unnecessary saving
		if(isset($mybb->input['delete_tier']) && is_array($mybb->input['delete_tier'])) {
			$delete_string = '';
			$update_string = '';
			foreach($mybb->input['delete_tier'] as $tier) {
				$to_delete = (int)$tier;
				if(!empty($delete_string)) {
					$delete_string .= " OR ";
					$update_string .= " OR ";
				}
				$delete_string .= "id = ".$to_delete;
				$update_string .= "tierid = ".$to_delete;
			}
			if(!empty($delete_string)) {
				$db->delete_query('rankext_tiers', $delete_string);
				$db->update_query('rankext_ranks', 'tierid=NULL', $update_string);
			}
		}

		if(isset($mybb->input['delete_rank']) && is_array($mybb->input['delete_rank'])) {
			$delete_string = '';
			$update_string = '';
			foreach($mybb->input['delete_rank'] as $rank) {
				$to_delete = (int)$rank;
				if(!empty($delete_string)) {
					$delete_string .= " OR ";
					$update_string .= " OR ";
				}
				$delete_string .= "id = ".$to_delete;
				$update_string .= "rankext_rank = ".$to_delete;
			}
			if(!empty($delete_string)) {
				$db->delete_query('rankext_ranks', $delete_string);
				$db->update_query('users', 'rankext_rank=NULL', $update_string);
			}
		}

		// Save Tiers
		$query = $db->simple_select('rankext_tiers');
		while($tier = $query->fetch_assoc()) {
			$tiers[] = $tier;
		}
		foreach($tiers as $tier) {
			$tier_array = array(
					'label' => $db->escape_string($mybb->input['tier_label'.$tier['id']]),
					'seq' => (int)$mybb->input['tier_seq'.$tier['id']]
			);
			$db->update_query("rankext_tiers", $tier_array, 'id='.$tier['id']);
		}

		// Save Ranks
		$query = $db->simple_select('rankext_ranks');
		while($rank = $query->fetch_assoc()) {
			$ranks[] = $rank;
		}
		foreach($ranks as $rank) {
			$rank_array = array(
					'label' => $db->escape_string($mybb->input['rank_label'.$rank['id']]),
					'seq' => (int)$mybb->input['rank_seq'.$rank['id']],
					'tierid' => (int)$mybb->input['rank_tier'.$rank['id']],
					'visible' => (int)$mybb->input['rank_visible'.$rank['id']]
			);
			$db->update_query("rankext_ranks", $rank_array, 'id='.$rank['id']);
		}
	}
}

/**
 * Adds a setting in group options in ACP.
 * (Colors & whether group has internal ranks)
 *
 */
// Add Hooks
$plugins->add_hook("admin_user_groups_edit", "rankext_group_edit");
$plugins->add_hook("admin_user_groups_edit_commit", "rankext_group_commit");

function rankext_group_edit() {
	global $plugins;

	// Add new hook
	$plugins->add_hook("admin_formcontainer_end", "rankext_group_editform");
}

/**
 * Add additional inputs to group update form
 * (Miscellaneous tab)
 */
function rankext_group_editform() {
	global $mybb, $lang, $form, $form_container, $usergroup;
	// Create the input fields
	if ($form_container->_title == $lang->misc)
	{
		$rankext_groupsettings= array(
				$form->generate_check_box("rankext_hasranks", 1, "Group has internal rank system", array("checked" => $usergroup['rankext_hasranks'])),
				"<b>Primary Color:</b> ".$form->generate_text_box("rankext_primarycolor", $usergroup['rankext_primarycolor'], "Primary color for group rank display"),
				"<b>Secondary Color:</b> ".$form->generate_text_box("rankext_secondarycolor", $usergroup['rankext_secondarycolor'], "Secondary color for group rank display")
		);
		$form_container->output_row("RankExt Settings", "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $rankext_groupsettings)."</div>");
	}
}

/**
 * Sets the group options values in ACP on submit
 *
 */
function rankext_group_commit()
{
	global $mybb, $updated_group, $db;

			$updated_group['rankext_hasranks'] = (int)$mybb->input['rankext_hasranks'];
			$updated_group['rankext_primarycolor'] = $db->escape_string($mybb->input['rankext_primarycolor']);
			$updated_group['rankext_secondarycolor'] = $db->escape_string($mybb->input['rankext_secondarycolor']);
}

/**
 * Adds a setting in user profile in ACP so admins can update ranks.
 *
 */
// Add Hooks
$plugins->add_hook("admin_user_users_edit", "rankext_user_edit");
$plugins->add_hook("admin_user_users_edit_commit_start", "rankext_user_commit");

function rankext_user_edit() {
	global $plugins;

	// Add new hook
	$plugins->add_hook("admin_formcontainer_end", "rankext_user_editform");
}

/**
 * Add additional inputs to user form
 */
function rankext_user_editform() {
	global $mybb, $lang, $form, $form_container, $user, $db;

	// Create the input fields
	if (strpos($form_container->_title, $lang->required_profile_info) !== false)	{
		$rankopts = array(
			0 => 'None'
			);
		$query = $db->simple_select('rankext_ranks', '*', 'true', array(
			"order_by" => 'seq',
	    "order_dir" => 'ASC'));
		while($rank = $query->fetch_assoc()) {
			$rankopts[$rank['id']] = $rank['label'];
		}
		$rankext_usersettings= array(
				$form->generate_select_box("rankext_rank", $rankopts, $user['rankext_rank'])
		);
		$form_container->output_row("User Rank", "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $rankext_usersettings)."</div>");
	}
}

/**
 * Sets the user options values in ACP on submit
 */
function rankext_user_commit()
{
	global $mybb, $extra_user_updates, $db;
			// Get rank text also for display purposes
			$rankid = (int)$mybb->input['rankext_rank'];
			$query = $db->simple_select('rankext_ranks', 'label', 'id = "'.$rankid.'"');
			$rankinfo = $query->fetch_assoc();

			$extra_user_updates['rankext_rank'] = $rankid;
			if(isset($rankinfo['label'])) {
				$extra_user_updates['rankext_ranktext'] = $rankinfo['label'];
			} else {
				$extra_user_updates['rankext_ranktext'] = '';
			}
}
