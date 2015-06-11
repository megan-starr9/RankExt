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
* Add special location to online list
*
*/
$plugins->add_hook('build_friendly_wol_location_end', 'update_location');

function update_location(&$plugin_array) {
	global $db;
	parse_str(parse_url($plugin_array['user_activity']['location'],  PHP_URL_QUERY), $urlarr);

	$query = $db->simple_select('usergroups', 'title', 'gid="'.$urlarr['amp;gid'].'"');
	$groupname = $query->fetch_assoc()['title'];

	if($urlarr['action'] == 'showranks') {
		$plugin_array['location_name'] = "Viewing <a href='".$plugin_array['user_activity']['location']."'>".$groupname." Ranks</a>"	;
	}
}

/**
* Build Ranklist View
* (Replaces forum list on index page)
*
*/
$plugins->add_hook('index_end', 'build_ranklist');

function build_ranklist() {
	global $mybb, $db, $templates, $header, $footer, $headerinclude, $title;

	if($mybb->input['action'] == "showranks") {

		// Get group id
		if($mybb->input['gid'] != '') {
			$gid = intval($mybb->input['gid']);
		} else {
			$gid = $mybb->user['displaygroup'];
		}

		$groupfields = 'gid, title, rankext_hasranks, rankext_primarycolor, rankext_secondarycolor, rankext_bannerurl, rankext_groupfid';
		$userfields = '*';
		$tierfields = '*';
		$rankfields = '*';
		$query = $db->simple_select('usergroups', $groupfields, 'gid = '.$gid);
		$group = $query->fetch_assoc();

		$title = $group['title']." Ranks";

		$leaderids = array();
		// Just in case someone wants to do something with leaders vs non-leaders?
		$lquery = $db->simple_select("groupleaders", "*", 'gid='.$gid);
		while ($leader = $db->fetch_array($lquery))
		{
			$leaderids[] = $leader['uid'];
		}

		// Check if group is set to use ranks
		$groupswithoutranks = explode(",", $mybb->settings['rankext_groupswithoutranks']);
		if(isset($group['rankext_hasranks']) && ($group['rankext_hasranks'] && !in_array($gid, $groupswithoutranks))) {

			if($group['rankext_groupfid']) {
				add_breadcrumb($group['title'].' Forum', 'forumdisplay.php?fid='.(int)$group['rankext_groupfid']);
			}
			add_breadcrumb($group['title']." Ranks");

			// Get all the users, put them in an array with rank as key
			$rankusers = array();
			$countedposttable = 'SELECT p.uid, MAX(p.dateline) AS recentpost FROM '.TABLE_PREFIX.'posts p
								INNER JOIN '.TABLE_PREFIX.'forums f ON p.fid = f.fid
								WHERE f.usepostcounts = 1
								GROUP BY p.uid';
			$userquery = $db->query('SELECT '.$userfields.', d.recentpost AS lasticpost FROM '.TABLE_PREFIX.'users u
								INNER JOIN '.TABLE_PREFIX.'userfields ON uid = ufid
								LEFT JOIN ('.$countedposttable.') d ON d.uid = u.uid
								WHERE displaygroup = "'.$group['gid'].'"
											OR (usergroup = "'.$group['gid'].'" AND displaygroup = "0")');
			while ($member = $userquery->fetch_assoc()) {
				$member['isleader'] = in_array($member['uid'], $leaderids);
				if(is_array($rankusers[$member['rankext_rank']])) {
					$rankusers[$member['rankext_rank']][] = $member;
				} else {
					$rankusers[$member['rankext_rank']] = array($member);
				}
			}

			// Get all ranks, put them in array with tier as key
			$tierranks = array();
			$rankquery = $db->simple_select('rankext_ranks', $rankfields, true, array(
				"order_by" => 'seq',
				"order_dir" => 'ASC'));
			while ($rank = $rankquery->fetch_assoc()) {
				if(is_array($tierranks[$rank['tierid']])) {
					$tierranks[$rank['tierid']][] = $rank;
				} else {
					$tierranks[$rank['tierid']] = array($rank);
				}
			}

			// Now build out group object
			$tierquery = $db->simple_select('rankext_tiers', $tierfields, 'true', array(
				"order_by" => 'seq',
		    "order_dir" => 'ASC'));
			$tiers = array();
			while ($tier = $tierquery->fetch_assoc()) {
				$ranks = array();
				if(is_array($tierranks[$tier['id']])) {
					$ranks = $tierranks[$tier['id']];
				}

				$rankaccum = array();
				foreach ($ranks as $rank) {
					// Set users for rank
					$users = array();
					if(is_array($rankusers[$rank['id']])) {
						$users = $rankusers[$rank['id']];
					}
					// Split out duplicates if desired
					if($rank['split_dups']) {
						foreach($users as $user) {
							$rank['users'] = array($user);
							$rankaccum[] = $rank;
						}
						// Fill in all wanted empty duplicates
						for($i = 0; $i<$rank['dups'] - sizeof($users); $i++) {
							$rank['users'] = array();
							$rankaccum[] = $rank;
						}
					} else {
						$rank['users'] = $users;
						$rankaccum[] = $rank;
					}
				}
				$tier['ranks'] = $rankaccum;
				$tiers[] = $tier;
			}
			$group['tiers'] = $tiers;

			// Now get ungrouped Members
			$group['unrankedmembers'] = $rankusers[0];

			display_group($group);
			exit;
		}
		eval("\$rankpage = \"".$templates->get('rankext_rankpage_noranks')."\";");
		output_page($rankpage);
		exit;
	}
}

// If group has ranks, build the display with templates
function display_group($group) {
	global $mybb, $templates, $header, $footer, $headerinclude, $title, $forums,
			$ranklist_fullview, $ranklist, $tierlist, $userlist, $unrankedlist, $unrankeduserlist, $bannerimg;

		$bannerimg = (empty($group['rankext_bannerurl']) || !$mybb->user['showimages']) ? '' : '<img src="'.$group['rankext_bannerurl'].'">';

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

	eval("\$rankpage = \"".$templates->get('rankext_rankpage_full')."\";");
	return output_page($rankpage);
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
