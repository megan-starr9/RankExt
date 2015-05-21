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
* Build Ranklist View
* (Replaces forum list on index page)
*
*/
$plugins->add_hook('index_end', 'build_ranklist');

function build_ranklist() {
	global $mybb, $db, $templates, $header, $footer, $headerinclude, $title, $forums;

	if($mybb->input['action'] == "showranks") {
		$forums = '';
		$title = $group['title']." Ranks";

		// Get group id
		if($mybb->input['gid'] != '') {
			$gid = intval($mybb->input['gid']);
		} else {
			$gid = $mybb->user['displaygroup'];
		}
		$groupfields = 'gid, title, rankext_hasranks, rankext_primarycolor, rankext_secondarycolor';
		$userfields = 'uid, username, displaygroup, rankext_rank';
		$tierfields = '*';
		$rankfields = '*';
		$query = $db->simple_select('usergroups', $groupfields, 'gid = '.$gid);
		$group = $query->fetch_assoc();

		$groupswithoutranks = explode(",", $mybb->settings['rankext_groupswithoutranks']);
		if(isset($group['rankext_hasranks']) && ($group['rankext_hasranks'] && !in_array($gid, $groupswithoutranks))) {
			// Now build out group object
			$query = $db->simple_select('rankext_tiers', $tierfields, 'true', array(
				"order_by" => 'seq',
		    "order_dir" => 'ASC'));
			$tiers = array();
			while ($tier = $query->fetch_assoc()) {
				$query2 = $db->simple_select('rankext_ranks', $rankfields, 'tierid = '.$tier['id'], array(
					"order_by" => 'seq',
			    "order_dir" => 'ASC'));
				$ranks = array();
				while ($rank = $query2->fetch_assoc()) {
					$query3 = $db->simple_select('users', $userfields, 'rankext_rank = "'.$rank['id'].'" AND (displaygroup = "'.$group['gid'].'"
										OR (usergroup = "'.$group['gid'].'" AND displaygroup = "0"))');
					$users = array();
					while ($member = $query3->fetch_assoc()) {
						$users[] = $member;
					}
					$rank['users'] = $users;
					$ranks[] = $rank;
				}
				$tier['ranks'] = $ranks;
				$tiers[] = $tier;
			}
			$group['tiers'] = $tiers;

			// Now get ungrouped Members
			$query = $db->simple_select('users', $userfields, 'rankext_rank = "0" AND (displaygroup = "'.$group['gid'].'"
								OR (usergroup = "'.$group['gid'].'" AND displaygroup = "0"))');
			while($member = $query->fetch_assoc()) {
				$group['unrankedmembers'][] = $member;
			}

			return display_group($group);
		}

		return eval("\$forums = \"".$templates->get('rankext_rankpage_noranks')."\";");
	}
}

// If group has ranks, build the display with templates
function display_group($group) {
	global $mybb, $templates, $header, $footer, $headerinclude, $title, $forums,
			$ranklist_fullview, $ranklist, $tierlist, $userlist, $unrankedlist, $unrankeduserlist;

	// Build out the templates!
	foreach($group['tiers'] as $tier) {
		foreach($tier['ranks'] as $rank) {
			if(sizeof($rank['users']) > 0 || $rank['visible']) {
				if(sizeof($rank['users']) == 0) {
					eval("\$userlist .= \"".$mybb->settings['rankext_placeholder']."\";");
				} else {
					foreach($rank['users'] as $user) {
						if(!empty($userlist)) {
							eval("\$userlist .= \"".$mybb->settings['rankext_delimiter']."\";");
						}
						eval("\$userlist .= \"".$templates->get('rankext_userview')."\";");
					}
				}
				eval("\$ranklist .= \"".$templates->get('rankext_rankview')."\";");
				// reset userlist
				eval("\$userlist = \"\";");
			}
		}
		eval("\$tierlist .= \"".$templates->get('rankext_tierview')."\";");
		// reset ranklist
		eval("\$ranklist = \"\";");
	}

	// Append unranked members
	if(sizeof($group['unrankedmembers']) > 0) {
		$unranked_title = $mybb->settings['rankext_overflowrank'];
		foreach($group['unrankedmembers'] as $user) {
			eval("\$unrankedmemberlist .= \"".$templates->get('rankext_unrankeduser')."\";");
		}
		eval("\$unrankedlist = \"".$templates->get('rankext_unrankedtitle')."\";");
	}

	return eval("\$forums = \"".$templates->get('rankext_rankpage_full')."\";");
}

/**
 * Add option to ModCP so mods can update rank
 *
 */
$plugins->add_hook("modcp_editprofile_end", "rankext_mod_editform");
$plugins->add_hook("modcp_do_editprofile_update", "rankext_mod_saveinfo");

function rankext_mod_editform() {
	global $mybb, $db, $requiredfields, $user, $form;
		$rankopts = array(
			0 => 'None'
			);
		$query = $db->simple_select('rankext_ranks', '*', 'true', array(
			"order_by" => 'seq',
			"order_dir" => 'ASC'));
		$options = '<option value="0">None</option>';
		while($rank = $query->fetch_assoc()) {
			if($user['rankext_rank'] == $rank['id']) {
				$options .= '<option value="'.$rank['id'].'" selected>'.$rank['label'].'</option>';
			} else {
				$options .= '<option value="'.$rank['id'].'">'.$rank['label'].'</option>';
			}
		}
		$requiredfields .= "<div class=\"forum_settings_bit\"><b>User Rank: </b><select name='rankext_rank'>".$options."</select></div><br>";
}

/**
* Save extra ModCP user info on submit
*
*/
function rankext_mod_saveinfo() {
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

/**
 * Handle XMLHTTP requests
 * (Right now these are only triggered from the AdminCP when creating tiers/ranks)
 */
$plugins->add_hook('xmlhttp', 'handle_rankext_ajax_request');

function handle_rankext_ajax_request() {
	global $mybb;
	if($mybb->input['action'] == "rankext_addtier") {
		// User is submitting thread for EXP consideration
		create_tier();
	} else if($mybb->input['action'] == "rankext_addrank") {
		// Moderator is approving thread as valid
		create_rank();
	}
}

// Create a stub Tier
function create_tier() {
	global $db;
	$newtier = array(
		'label' => 'New Tier'
	);
	$db->insert_query('rankext_tiers', $newtier);
}

// Create a stub Rank
function create_rank() {
	global $db;
	$newrank = array(
		'label' => 'New Rank',
	);
	$db->insert_query('rankext_ranks', $newrank);
}
