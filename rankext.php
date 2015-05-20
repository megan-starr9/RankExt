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

function rankext_info() {
    return array(
        'name' => 'RankExt',
        'description' => 'Adds internal ranks to user groups within MyBB.',
        'website' => 'https://github.com/megan-starr9/RankExt',
        'author' => 'Megan Lyle',
        'authorsite' => 'http://megstarr.com',
        'version' => '1.0.0',
        'compatibility' => '18*',
    );
}

// Load the install/admin functions in ACP.
if (defined("IN_ADMINCP")) {
	require_once MYBB_ROOT."inc/plugins/rankext/rankext_install.php";
	require_once MYBB_ROOT."inc/plugins/rankext/rankext_admincp.php";
} else { // Otherwise load User/Mod/View functionality
	require_once MYBB_ROOT."inc/plugins/rankext/rankext_functionality.php";
}

?>
