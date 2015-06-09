<?php
if(!defined("IN_MYBB")) {
    die("You Cannot Access This File Directly. Please Make Sure IN_MYBB Is Defined.");
}

/**
 * Install Plugin
 */
function rankext_install() {
	global $db;

	// Make DB Changes
	// Avoid database errors
	if ($db->field_exists("rankext_rank", "users")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP COLUMN `rankext_rank`");
	}
  if ($db->field_exists("rankext_ranktext", "users")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP COLUMN `rankext_ranktext`");
	}
  if ($db->field_exists("rankext_hasranks", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_hasranks`");
	}
  if ($db->field_exists("rankext_primarycolor", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_primarycolor`");
	}
  if ($db->field_exists("rankext_secondarycolor", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_secondarycolor`");
	}
  if ($db->field_exists("rankext_bannerurl", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_bannerurl`");
	}
	if ($db->table_exists("rankext_ranks")) {
		$db->write_query("DROP TABLE `".TABLE_PREFIX."rankext_ranks`");
	}
	if ($db->table_exists("rankext_tiers")) {
		$db->write_query("DROP TABLE `".TABLE_PREFIX."rankext_tiers`");
	}
	$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD COLUMN `rankext_rank` INT(11) NOT NULL DEFAULT '0'");
  $db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD COLUMN `rankext_ranktext` VARCHAR(100) NOT NULL DEFAULT ''");
  $db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` ADD COLUMN `rankext_hasranks` INT(1) NOT NULL DEFAULT '1'");
  $db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` ADD COLUMN `rankext_primarycolor` VARCHAR(7) NOT NULL DEFAULT '#a0a0a0'");
  $db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` ADD COLUMN `rankext_secondarycolor` VARCHAR(7) NOT NULL DEFAULT '#808080'");
  $db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` ADD COLUMN `rankext_bannerurl` VARCHAR(100) NOT NULL DEFAULT ''");
	$db->write_query("CREATE TABLE `".TABLE_PREFIX."rankext_ranks` (id int(11) NOT NULL AUTO_INCREMENT, seq int(11) NOT NULL DEFAULT '0', tierid int(11) NOT NULL DEFAULT '0', label varchar(200), visible int(1) NOT NULL DEFAULT '1', split_dups int(1) NOT NULL DEFAULT '1', dups int(11) NOT NULL DEFAULT '1', PRIMARY KEY(id))");
	$db->write_query("CREATE TABLE `".TABLE_PREFIX."rankext_tiers` (id int(11) NOT NULL AUTO_INCREMENT, seq int(11) NOT NULL DEFAULT '0', label varchar(200), PRIMARY KEY(id))");

	// Create Settings
	$rankext_group = array(
			'gid'    => 'NULL',
			'name'  => 'rankext',
			'title'      => 'RankExt Group Ranks',
			'description'    => 'Settings For Group Internal Ranks',
			'disporder'    => "1",
			'isdefault'  => "0",
	);

	$db->insert_query('settinggroups', $rankext_group);
	$gid = $db->insert_id();

	$rankext_settings[0] = array(
					'sid'            => 'NULL',
					'name'        => 'rankext_overflowrank',
					'title'            => 'Default Rank',
					'description'    => 'What you want those without a rank to be termed as.',
					'optionscode'    => 'text',
					'value'        => 'Unranked Members',
					'disporder'        => 1,
					'gid'            => intval($gid),
			);
  $rankext_settings[1] = array(
    			'sid'            => 'NULL',
    			'name'        => 'rankext_delimiter',
					'title'            => 'Delimiter',
					'description'    => 'The delimiter you wish to use for multi-user ranks',
					'optionscode'    => 'text',
					'value'        => ', ',
					'disporder'        => 1,
					'gid'            => intval($gid),
			);
  $rankext_settings[2] = array(
          'sid'            => 'NULL',
          'name'        => 'rankext_placeholder',
          'title'            => 'Placeholder',
          'description'    => 'The placeholder for empty but visible ranks',
          'optionscode'    => 'text',
          'value'        => '&mdash;',
          'disporder'        => 1,
          'gid'            => intval($gid),
      );
  $rankext_settings[2] = array(
          'sid'            => 'NULL',
          'name'        => 'rankext_groupswithoutranks',
          'title'            => 'Groups without Ranks',
          'description'    => 'Ids of groups without ranks, overrides yes on group settings (comma separated).',
          'optionscode'    => 'text',
          'value'        => '',
          'disporder'        => 1,
          'gid'            => intval($gid),
      );

	foreach($rankext_settings as $setting) {
		$db->insert_query('settings', $setting);
	}
	rebuild_settings();

	// Create any Templates
  //First add the group
  $templategroup = array(
		'prefix' => 'rankext',
		'title'  => 'RankExt',
		'isdefault' => 1
	);
	$db->insert_query("templategroups", $templategroup);

	// Add the new templates
	$rankext_templates[0] = array(
			"title" 	=> "rankext_rankpage_full",
			"template"	=> $db->escape_string('<style>
              .bannerdiv {
                width: 90%;
                margin:auto;
                text-align:center;
                border:0px;
                }
              .ranktable {
                width: 90%;
                margin:auto;
                }
              .tier {
                color: #FFF;
                font-weight: bold;
                background-color: {$group[\'rankext_primarycolor\']};
                text-align: center;
                font-size: 20px;
                padding: 5px 0px;
              }
              .rank {
                background-color: {$group[\'rankext_secondarycolor\']};
                width: 40%;
                text-align: center;
                font-weight: bold;
                padding: 10px 0px;
              }
              .users {
                width: 55%;
                padding-left: 5%;
              }
              .users a:link {
                color:{$group[\'rankext_primarycolor\']};
                font-weight:bold;
              }
              .unrankedrow {
                  text-align: center;
                }
              .unrankedrow a:link {
                color:{$group[\'rankext_primarycolor\']};
                font-weight:bold;
              }
            </style>
            <h2>{$group[\'title\']} Ranks</h2>
              <div class="bannerdiv">{$bannerimg}</div>
							<table class="ranktable">
								{$tierlist}
                {$unrankedlist}
							</table>
              <br><br>'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
	$rankext_templates[1] = array(
			"title" 	=> "rankext_tierview",
			"template"	=> $db->escape_string('<tr>
                    <th class="tier" colspan=2>{$tier[\'label\']}</th></tr>
                    {$ranklist}'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
	$rankext_templates[2] = array(
			"title" 	=> "rankext_rankview",
      "template"	=> $db->escape_string('<tr>
                <td class="rank">{$rank[\'label\']}</td>
                <td class="users">{$userlist}</td>
                </tr>'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
  $rankext_templates[3] = array(
			"title" 	=> "rankext_userview",
			"template"	=> $db->escape_string('<a href="member.php?action=profile&amp;uid={$user[\'uid\']}">{$user[\'username\']}</a>'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
  $rankext_templates[4] = array(
			"title" 	=> "rankext_unrankedtitle",
			"template"	=> $db->escape_string('<tr>
                    <th class="tier" colspan=2>{$unranked_title}</th></tr>
                    {$unrankedmemberlist}'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
  $rankext_templates[5] = array(
			"title" 	=> "rankext_unrankeduser",
			"template"	=> $db->escape_string('<tr>
        <td colspan="2" class="unrankedrow"><a href="member.php?action=profile&amp;uid={$user[\'uid\']}">{$user[\'username\']}</a></td>
      </tr>'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);
  $rankext_templates[6] = array(
			"title" 	=> "rankext_rankpage_noranks",
			"template"	=> $db->escape_string('
            <h2>{$group[\'title\']} Ranks</h2>
							<p>This group has no ranks.</p>
              <br><br>'),
			"sid"		=> -2,
			"version"	=> 1.0,
			"dateline"	=> TIME_NOW
	);

	foreach ($rankext_templates as $row) {
		$db->insert_query('templates', $row);
	}
}

/**
 * Determine if plugin is installed based on changes made on install
 * @return boolean
 */
function rankext_is_installed() {
	global $db;

	if ($db->field_exists("rankext_rank", "users")
        && $db->field_exists("rankext_hasranks", "usergroups")
        && $db->table_exists('rankext_tiers') && $db->table_exists('rankext_ranks')
        ) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * Activate Plugin
 */
function rankext_activate() {
	// Nothing Currently
}

/**
 * Deactivate Plugin
 */
function rankext_deactivate() {
	// Nothing Currently
}

/**
 * Uninstall Plugin
 */
function rankext_uninstall() {
	global $db;

	// Delete any templates
	$db->delete_query("templates", "`title` LIKE 'rankext_%'");
	$db->delete_query("templategroups", "`prefix` = 'rankext'");

	// Delete any table columns
	if ($db->field_exists("rankext_rank", "users")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP COLUMN `rankext_rank`");
	}
  if ($db->field_exists("rankext_ranktext", "users")) {
    $db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP COLUMN `rankext_ranktext`");
  }
  if ($db->field_exists("rankext_hasranks", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_hasranks`");
	}
  if ($db->field_exists("rankext_primarycolor", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_primarycolor`");
	}
  if ($db->field_exists("rankext_secondarycolor", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_secondarycolor`");
	}
  if ($db->field_exists("rankext_bannerurl", "usergroups")) {
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `rankext_bannerurl`");
	}
	if ($db->table_exists("rankext_tiers")) {
		$db->write_query("DROP TABLE `".TABLE_PREFIX."rankext_tiers`");
	}
	if ($db->table_exists("rankext_ranks")) {
		$db->write_query("DROP TABLE `".TABLE_PREFIX."rankext_ranks`");
	}

	// Delete settings
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name LIKE 'rankext_%'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='rankext'");
	rebuild_settings();
}

?>
