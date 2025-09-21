<?php
/* USER_ACCOUNTS */


/* USER_ACCOUNTS */
/* INSTALL_VARIABLES */
$page_name = "";
$guest_access_allowed = "true";
$CFG['admin_name'] = "";
$CFG['admin_email'] = "";
$CFG['admin_notes'] = "";
$CFG['email_users'] = "false";
$CFG['server_name'] = "";
$page_title = "";
$RSS['title'] = "";
$RSS['description'] = "";
$RSS['title_diff'] = "";
$RSS['description_diff'] = "";
/* INSTALL_VARIABLES */
/* INSTALLER */

// set the debug level
// should be false except during development
$DEBUG = false;

if ($DEBUG) {
    ini_set('display_errors',1);
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

// get file variables
$file = pathinfo($_SERVER['PHP_SELF']);

// this removes the extension from the basename of the script.
$file['name'] = substr($file['basename'], 0, strlen($file['basename']) - strlen($file['extension']) - 1);

$install_file_name = $file['basename'];

$file['rss'] = $file['name'] . '.xml';
$file['rss_diff'] = $file['name'] . '_diff.xml';

// begin installer

$t =& getDataLinesFromFile($file['basename'], 'USER_ACCOUNTS');
$user_accounts = implode("\n", $t);

$t =& getDataLinesFromFile($file['basename'], 'INSTALL_VARIABLES');
$install_variables = implode("\n", $t);

$t =& getDataLinesFromFile($file['basename'], 'INSTALLER');
$installer = implode("\n", $t);


// these must be in the default order
$install_labels = array(
    'Test Setup',
    'Set Hostname',
    'Guest Access',
    'Create Accounts',
    'Email Notifications',
    'Page Name & Title',
    'RSS Setup',
    'Plugins',
    'Confirm Settings',
    'Finished'
);

// this array is used to control which install step is
// run an displayed in the two switches, below.
$steps = array(
    'test'                  => 0,
    'hostname'              => 1,
    'guest_access'          => 2,
    'create_accounts'       => 3,
    'email_notifications'   => 4,
    'page_name'             => 5,
    'rss_setup'             => 6,
    'plugins'               => 7,
    'confirm'               => 8,
    'install'               => 9,
);

// which step follows?
// values must match the value in $steps, above
$next_steps = array(
    'test'                  => 1,
    'hostname'              => 2,
    'guest_access'          => 3,
    'create_accounts'       => 4,
    'email_notifications'   => 5,
    'page_name'             => 6,
    'rss_setup'             => 7,
    'plugins'               => 8,
    'confirm'               => 9,
);

// which is the previous step?
// values must match the value in $steps, above
$prev_steps = array(
    'hostname'              => 0,
    'guest_access'          => 1,
    'create_accounts'       => 2,
    'email_notifications'   => 3,
    'page_name'             => 4,
    'rss_setup'             => 5,
    'plugins'               => 6,
    'confirm'               => 7,
    'install'               => 8,
);

if (empty($_REQUEST['install_step'])) {
    $install_step = $steps['test'];
} else {
    $install_step = $_REQUEST['install_step'];
}

// this is handled after the main switch, below
$finalize = isset($_POST['finish'])
    ? true
    : false;

// was there a form/link submission to be processed on this stage?
// $process will be set to true if so, false if we're hitting
// the stage for the first time (next or back button, menu link)

$process = isset($_REQUEST['process'])
    ? true
    : false;

// flags to control display in the switch controlling the view, below
$msgs = array();

// define plugin functions

// plugin_config generates configuration forms based on an array
// called $PLUGIN_CONFIG which can be set in plugin loaders.
function plugin_config($plugname, $config) {
    echo ("<ul>");
    echo ("<input name='plugs[]' value='$plugname' type='hidden'/>\n");
    foreach ($config as $c) {
        list($type, $name, $default, $injection, $description) = $c;
        echo ("<input name='plug".$plugname."cfg[]' value='$name' type='hidden'/>\n" .
              "<input name='".$plugname.$name."_injection' value='" . str_replace("'", "&apos;", $injection) . "' type='hidden'/>\n");
        echo ("<li>$description<br/>");
        switch ($type) {
        case "pass":
        case "password":
        case "pw":
            echo("<input name='".$plugname.$name."_value' value='". str_replace("'", "&apos;", $default) . "' type='password'/>\n");
            break;
        default:
            echo("<input name='".$plugname.$name."_value' value='" . str_replace("'", "%27", $default) . "' type='text'/>\n");
            break;
        }
        echo ("</li>");
    }
    echo ("</ul>");
}

// plugin_apply_config takes the form-field data from the plugin
// configuration and applies the settings to the individual plugin
// files. returns null on success; error message on failure.
function plugin_apply_config() {
    /*
    echo ("<input name='plugs[]' value='$plugname' type='hidden'/>\n");
    echo ("<input name='plug".$plugname."cfg[]' value='$name' type='hidden'/>\n" .
          "<input name='".$plugname.$name."_injection' value='" . str_replace("'", "%27", $injection) . "' type='hidden'/>\n");
    echo("<input name='".$plugname.$name."_value' value='" . str_replace("'", "%27", $default) . "' type='text'/>\n");
    */
    $plugs = $_POST["plugs"];
    if (!is_array($plugs)) return null; // success, since no plugs exist at all
    foreach ($plugs as $p) {
        $pdata = "";
        $ntv = array();
        $cfg = $_POST["plug" . $p . "cfg"];
        if (is_array($cfg)) {
            foreach ($cfg as $d) {
                $pdata .= str_replace("\\\"", "\"", $_POST[$p.$d."_injection"]) . "\n";
                $ntv[$d] = str_replace("\\\"", "\"", $_POST[$p.$d."_value"]);
            }
        }
        if ($pdata) {
            reset($ntv);
            while (list($n, $v) = each($ntv)) {
                $pdata = str_replace("%".$n, $v, $pdata);
            }
            $plugin = file_get_contents("plugin/" . $p . ".php");
            $start = strpos($plugin, "/* PLUGIN::" . $p . "::CONFIG { */");
            $end = strpos($plugin, "/* } PLUGIN::" . $p . "::CONFIG */");
            if (!($start === false || $end === false)) {
                $plugin = substr($plugin, 0, $start) . "/* PLUGIN::" . $p . "::CONFIG { */\n" . $pdata . "\n/* } PLUGIN::" . $p . "::CONFIG */" . substr($plugin, $end+24 + strlen($p));
            } else if (!($start === false) && $end === false) {
                $plugin = substr($plugin, 0, $start) . "/* PLUGIN::" . $p . "::CONFIG { */\n" . $pdata . "\n/* } PLUGIN::" . $p . "::CONFIG */" . substr($plugin, $start+24 + strlen($p));
            } else if (!($end === false) && $start === false) {
                $plugin = substr($plugin, 0, $end) . "/* PLUGIN::" . $p . "::CONFIG { */\n" . $pdata . "\n/* } PLUGIN::" . $p . "::CONFIG */" . substr($plugin, $end+24 + strlen($p));
            } else {
                $plugin = "<?php\n" . "/* PLUGIN::" . $p . "::CONFIG { */\n" . $pdata . "\n/* } PLUGIN::" . $p . "::CONFIG */\n?>" . $plugin;
            }
            
            file_put_contents("plugin/" . $p . ".php", $plugin);
        }
    }
}

// plugin_attach "adds-on" code to an existing plugin hook.
function plugin_attach($hook, $code) {
    $GLOBALS["PLUGDATA"][$hook] .= $code;
}
// plugin_handle replaces default code for a hook. it supports
// the special keyword [SUPER] which is replaced with the
// prior-to-handle-call content of the hook
function plugin_handle($hook, $code) {
    $GLOBALS["PLUGDATA"][$hook] = str_replace("[SUPER]", $GLOBALS["PLUGDATA"][$hook], $code);
}
// plugin_attach_token is a shortcut for adding a, well, token.
function plugin_attach_token($id, $token, $access, $html, $hook = "tokens") {
    if (!is_array($access)) $access = array($access);
    $id = "'$id'";
    $token = "'$token'";
    $access = "array('" . implode("', '", $access) . "')";
    plugin_attach($hook,
                  '
$TOKENS['.$id.'][\'token\']  = '.$token.';
$TOKENS['.$id.'][\'access\'] = '.$access.';
$TOKENS['.$id.'][\'html\']   = '.$html.';
');
}
// apply_plugins applies the plugins to the $script
function apply_plugins(&$script)
{
    GLOBAL $dir, $LOADING_PLUGIN, $plugs, $PLUGDATA;

    $plugs = array(// main hooks //
                   "admin",
                   "auth",
                   "cfg",
                   "control_logic",
                   "tokens",
                   "functionality",
                   // redefined hooks //
                   "admin::css",
                   "auth::after_auth",
                   "auth::before_auth",
                   "auth::after_fields",
                   "auth::before_fields",
                   "control_logic::after_add_comment",
                   "control_logic::after_cancel",
                   "control_logic::after_catch_ping",
                   "control_logic::after_continue_and_save",
                   "control_logic::after_create_page",
                   "control_logic::after_delete_comments",
                   "control_logic::after_delete_history",
                   "control_logic::after_delete_images",
                   "control_logic::after_delete_trackbacks",
                   "control_logic::after_edit",
                   "control_logic::after_edit_messages",
                   "control_logic::after_logout",
                   "control_logic::after_moderate",
                   "control_logic::after_override_lock",
                   "control_logic::after_preview",
                   "control_logic::after_rename_file",
                   "control_logic::after_save",
                   "control_logic::after_save_messages",
                   "control_logic::after_send_notification_pings",
                   "control_logic::after_send_ping",
                   "control_logic::after_show_login",
                   "control_logic::after_tb_moderate",
                   "control_logic::after_upload_images",
                   "control_logic::after_view_diffs",
                   "control_logic::after_view_page",
                   "control_logic::after_view_trackbacks_comments",
                   "control_logic::after_view_history",
                   "control_logic::before_add_comment",
                   "control_logic::before_cancel",
                   "control_logic::before_catch_ping",
                   "control_logic::before_continue_and_save",
                   "control_logic::before_create_page",
                   "control_logic::before_delete_comments",
                   "control_logic::before_delete_history",
                   "control_logic::before_delete_images",
                   "control_logic::before_delete_trackbacks",
                   "control_logic::before_edit",
                   "control_logic::before_edit_messages",
                   "control_logic::before_logout",
                   "control_logic::before_moderate",
                   "control_logic::before_override_lock",
                   "control_logic::before_preview",
                   "control_logic::before_rename_file",
                   "control_logic::before_save",
                   "control_logic::before_save_messages",
                   "control_logic::before_send_ping",
                   "control_logic::before_send_notification_pings",
                   "control_logic::before_show_login",
                   "control_logic::before_tb_moderate",
                   "control_logic::before_upload_images",
                   "control_logic::before_view_diffs",
                   "control_logic::before_view_history",
                   "control_logic::before_view_page",
                   "control_logic::before_view_trackbacks_comments",
                   "tokens::after_lock",
                   // code-logic-expanded hooks
                   "admin::edit::init",
                   "admin::edit::print_field",
                   "control_logic::edit::is_locked",
                   "control_logic::add_comment::validate",
                   "control_logic::moderate::about_to_moderate");

    // default plugin handler statements
    $PLUGDATA["control_logic::edit::is_locked"] = '
            $err = true;
            $display[\'msg_nonav\'] = true;
            $display[\'override_lock\'] = true;
';
    
    $PLUGDATA["admin::edit::print_field"] = '
            echo ("<fieldset>" .
                  "<legend>" . $data_field_labels[$chunk_name] . ":" .
                  "&nbsp;&nbsp;" .
                  "Size: " . $page_info[$chunk_name][\'size\'] . " characters" .
                  "&nbsp;&nbsp;" .
                  "Lines: " . $page_info[$chunk_name][\'lines\'] . 
                  "&nbsp;&nbsp;" .
                  "Word Count: " . $page_info[$chunk_name][\'words\'] .
                  "</legend>" .

                  "<textarea name=\\"edtpag[" . $chunk_name . "]\\" rows=\\"15\\" cols=\\"20\\" onkeypress=\\"buttonOn(\'submit_button\', \'action_save\');\\">" . $data . "</textarea>" .
                  "</fieldset>");
';

    // load individual plugins
    // currently loading all php scripts found; this could potentially be a security issue, but let's face it -- it's not hard to trigger a malicious
    // php script that is available on a server from the www, if you've managed to make it available, on a server, from the www.
    $LOADING_PLUGIN = true;
    $d = dir("plugin/");
    while ($d && (false !== ($entry = $d->read()))) {
        if (substr($entry, -4) == ".php") {
            include("plugin/" . $entry);
        }
    }

    // apply results to the $script
    foreach ($plugs as $plug) {
        $script = preg_replace('/\/\*\sPLUGIN::' . $plug . '\s\*\//', $PLUGDATA[$plug], $script);
    }
}

// error checking and business logic
// $install_step may be changed during this stage
// if error checks weren't passed

switch ($install_step) {
    // test setup
    case $steps['test']:

        // default page
        $display_step = $steps['test'];

        $msgs['count_tests'] = 1;
        $msgs['count_failed'] = 0;
        $fh = @fopen($file['basename'], 'a');
        if ($fh) {
            fclose($fh);
            $msgs['failed_write'] = false;
        } else {
            $msgs['failed_write'] = true;
            $msgs['count_failed']++;
        }

        $msgs['count_tests']++;
        $fh = @fopen('etpinstalltest.txt', 'w');
        $result = -1;
        if ($fh) {
            $result = @fwrite($fh,'test');
        }
        if ($fh && $result > -1) {
            fclose($fh);
            unlink('etpinstalltest.txt');
            $msgs['failed_makefile'] = false;
        } else {
            $msgs['failed_makefile'] = true;
            $msgs['count_failed']++;
        }

        $msgs['count_tests']++;
        $result = @mkdir('etpinstalltestdir', 0777);
        $msgs['failed_makedir'] = $result ? false : true;
        $result ? false : $msgs['count_failed']++ ;

        if (!$msgs['failed_makedir']) {
            $msgs['count_tests']++;
            $fh = @fopen('etpinstalltestdir/etpinstalltest.txt', 'w');
            $result = -1;
            if ($fh) {
                $result = @fwrite($fh,'test');
            }
            if ($result > -1) {
                fclose($fh);
                unlink('etpinstalltestdir/etpinstalltest.txt');
                rmdir('etpinstalltestdir');
                $msgs['failed_makenewdirfile'] = false;
            } else {
                $msgs['failed_makenewdirfile'] = true;
                $msgs['count_failed']++;
            }
        }
        if (!$msgs['count_failed']) {
            // have we checked if this is an upgrade already?
            if (!$upgrade_checked) {
                // nope! check if this is an upgrade
                if (file_exists("editthispage_index.php")) {
                    // we found the standard file, this is an upgrade
                    $upsource = "editthispage_index.php";
                } else {
                    $d = dir("./");
                    while ($d && (false !== ($entry = $d->read()))) {
                        if (substr($entry, -4) == ".php" &&
                            substr($entry, 0, 13) == "editthispage_") {
                            unset($d);
                            // we found an upgradable file
                            $upsource = $entry;
                        }
                    }
                }
            }
            if ($upsource) {
                $page_name = substr($upsource, 13);
                setScalar($install_variables, 'page_name', '"' . $page_name . '"');
                $fh = fopen($upsource, "r");
                
                // now we have to rip-and-tear at this thing for a little bit...
                $deepc = false;
                $newdeepc = false;
                while (!feof($fh) && ($s = fgets($fh)) && strpos($s, "End Main Configuration") === false) {
                    $deepc = $newdeepc;
                    $s = trim($s);
                    if ($deepc) {
                        if (($dce = strpos($s, "*/")) !== false) {
                            $s = substr($s, $dce+2);
                            $deepc = false;
                            $newdeepc = false;
                        }
                    }
                    if (!$deepc) {
                        if (($sc = strpos($s, "//")) !== false) {
                            $s = substr($s, 0, $sc);
                        }
                        if (($dcb = strpos($s, "/*")) !== false) {
                            $dcbuf = substr($s, $dcb);
                            $s = substr($s, 0, $dcb);
                            $newdeepc = true;
                        }
                    }
                    /// guest access (will be completed, if enabled, in the appropriate installer
                    /// section + in the users section
                    if (substr($s, 0, 28) == "\$guest_access_allowed = true") {
                        setScalar($install_variables, 'guest_access_allowed', '"true"');
                    }
                    /// users
                    /// $users[0]["name"] = "guest";
                    if (preg_match('\\$users\\[(.+)\\]\\["(.+)"\\] = "(.+)"', $s, $regs)) {
                        // $regs => (count, key, value)
                        addScalar($user_accounts, 'users', $regs[1], '"' . $regs[2] . '"', '"' . $regs[3] . '"');
                    }
                    /// CFG
                    /// $CFG['email_users'] = false;
                    if (preg_match('\\$CFG\\[\'(.+)\'\\] = (.+);', $s, $regs)) {
                        // $regs => (key, value)
                        $regs[2] = trim($regs[2]);
                        // we don't want e.g. $foo = $bar;
                        if ($regs[2][0] != '$') {
                            if ($regs[2][0] == '"' || $regs[2][0] == "'") {
                                $regs[2] = substr($regs[2], 1, -1);
                            }
                            setScalar($install_variables, 'CFG', $regs[1], '"' . $regs[2] . '"');
                        }
                    }
                    /// RSS
                    /// $RSS['title'] = "N-Row-J RSS Title";
                    if (preg_match('\\$RSS\\[\'(.+)\'\\]\\[\'(.+)\'\\] = (.+);', $s, $regs) ||
                        preg_match('\\$RSS\\[\'(.+)\'\\] = (.+);', $s, $regs)) {
                        // $regs => (key, value)
                        //   -or-
                        // $regs => (key, subkey, value)
                        $regc = count($regs);
                        $rv = $regc-1;
                        $regs[$rv] = trim($regs[$rv]);
                        // we don't want e.g. $foo = $bar;
                        if ($regs[$rv][0] != '$') {
                            if ($regs[$rv][0] == '"' || $regs[$rv][0] == "'") {
                                $regs[$rv] = substr($regs[$rv], 1, -1);
                            }
                            if ($regc > 3) {
                                setScalar($install_variables, 'RSS', '"' . $regs[1], '"' . $regs[2] . '"', '"' . $regs[3] . '"');
                            } else {
                                setScalar($install_variables, 'RSS', $regs[1], '"' . $regs[2] . '"');
                            }
                        }
                    }
                }
                // we've gathered and such, now it's time to set the upgrade_checked variable
                addScalar($install_variables, 'upgrade_checked', '"true"');
                addScalar($install_variables, 'upgrade_performed', ($upsource ? '"true"' : '"false"'));
            }
        }

        break;

    // hostname
    case $steps['hostname']:
        // default page
        $display_step = $steps['hostname'];

        if ($process) {
            // next page
            $display_step = $next_steps['hostname'];

            // in case we're going straight to finalize, make sure
            // CFG['server_name'] is set in this instance, or we'll
            // get an erroneous "no hostname set" error
            $CFG['server_name'] = $_POST['server_name'];
            
            setScalar($install_variables, 'CFG', 'server_name', '"' . $_POST['server_name'] . '"');
        }

        break;

    // guest access
    case $steps['guest_access']:

        if ($process) {
            // next page
            $display_step = $next_steps['guest_access'];

            if (empty($_POST['guest_access_allowed'])) {
                setScalar($install_variables, 'guest_access_allowed', '"false"');

            } else {

                setScalar($install_variables, 'guest_access_allowed', '"true"');

                // create required guest access account
                // if we haven't already
                if (isset($users)) {
                    $guest_created = userExists($users, 'guest');
                } else {
                    $guest_created = false;
                }

                if ($guest_created === false) {
                    $user_count = isset($users)
                        ?count($users)
                        : 0;
                    addScalar($user_accounts, 'users', $user_count, '"name"', '"guest"');
                    addScalar($user_accounts, 'users', $user_count, '"password"', '"guest"');
                    addScalar($user_accounts, 'users', $user_count, '"email"', '""');
                    addScalar($user_accounts, 'users', $user_count, '"group"', '"guest"');

                    $users[$user_count]['name']      = 'guest';
                    $users[$user_count]['password']  = 'guest';
                    $users[$user_count]['email']     = '';
                    $users[$user_count]['group']     = 'guest';
                }
            }
        } else {
            // default page
            $display_step = $steps['guest_access'];
        }

        break;

    // create accounts
    case $steps['create_accounts']:
        // default page
        $display_step = $steps['create_accounts'];

        $msgs['failed_add_user'] = false;

        if ($process) {
            // deleting an account
            if (isset($_GET['deluser'])) {
                delScalar($user_accounts, 'users', $_GET['deluser'], '"name"');
                delScalar($user_accounts, 'users', $_GET['deluser'], '"password"');
                delScalar($user_accounts, 'users', $_GET['deluser'], '"email"');
                delScalar($user_accounts, 'users', $_GET['deluser'], '"group"');

                unset($users[$_GET['deluser']]);

            // adding an account
            } elseif (isset($_POST['username']) && $_POST['username']) {

                // if there are already user accounts set, make sure this user doesn't exist
                if (isset($users)) {
                    $user_exists = userExists($users, $_POST['username']);
                } else {
                    $user_exists = false;
                }

                if ($user_exists !== false) {
                    $msgs['failed_add_user'] = 'A user with that name already exists.';
                }

                // make sure passwords match
                if ($_POST['password'] != $_POST['confirm_password']) {
                    $msgs['failed_add_user'] = 'Passwords did not match.';
                }

                if (!$msgs['failed_add_user']) {
                    $user_count = isset($users)
                        ?count($users)
                        : 0;
                    addScalar($user_accounts, 'users', $user_count, '"name"', '"' . $_POST['username'] . '"');
                    addScalar($user_accounts, 'users', $user_count, '"password"', '"' . $_POST['password'] . '"');
                    addScalar($user_accounts, 'users', $user_count, '"email"', '"' . $_POST['email'] . '"');
                    addScalar($user_accounts, 'users', $user_count, '"group"', '"' . $_POST['group'] . '"');

                    $users[$user_count]['name']      = $_POST['username'];
                    $users[$user_count]['password']  = $_POST['password'];
                    $users[$user_count]['email']     = $_POST['email'];
                    $users[$user_count]['group']     = $_POST['group'];
                }
            }
        }

        break;

    // email notifications
    case $steps['email_notifications']:
        // default page
        $display_step = $steps['email_notifications'];

        if ($process) {

            // next page
            $display_step = $next_steps['email_notifications'];

            if (isset($_POST['email_users']) && $_POST['email_users']) {
                setScalar($install_variables, 'CFG', 'email_users', '"true"');
            } else {
                setScalar($install_variables, 'CFG', 'email_users', '"false"');
            }
            if (isset($_POST['admin_name']) && $_POST['admin_name']) {
                setScalar($install_variables, 'CFG', 'admin_name', '"' . $_POST['admin_name'] . '"');
            }
            if (isset($_POST['admin_email']) && $_POST['admin_email']) {
                setScalar($install_variables, 'CFG', 'admin_email', '"' . $_POST['admin_email'] . '"');
            }
            if (isset($_POST['admin_notes']) && $_POST['admin_notes']) {
                setScalar($install_variables, 'CFG', 'admin_notes', '"' . $_POST['admin_notes'] . '"');
            }
        }

        break;

    // set page name
    case $steps['page_name']:
        // default page
        $display_step = $steps['page_name'];

        if ($process) {

            if ($upgrade_performed == true || $upgrade_performed == "true") {
                // this is an upgrade; we won't even CREATE a main page
                $display_step = $next_steps['page_name'];
            } else {
                $msgs['failed_page_name'] = false;
                $msgs['failed_page_name_empty'] = false;
                $msgs['failed_page_name_exists'] = false;
                $msgs['failed_page_name_no_extension'] = false;

                if (isset($_POST['pagename']) && $_POST['pagename']){
                    $page_name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $_POST['pagename']);
                }

                if (empty($_POST['pagename']) || !$_POST['pagename']) {
                    $msgs['failed_page_name_empty'] = true;
                    $msgs['failed_page_name'] = true;

                } elseif (file_exists($page_name)) {
                    $msgs['failed_page_name_exists'] = true;
                    $msgs['failed_page_name'] = true;
                    
                    // begins with a dot, or has no dot
                } elseif (!strpos($page_name, '.')) {
                    $msgs['failed_page_name_no_extension'] = true;
                    $msgs['failed_page_name'] = true;
                } else {
                    setScalar($install_variables, 'page_name', '"' . $page_name . '"');
                    $display_step = $next_steps['page_name'];
                }

                if (!$_POST['page_title']) $_POST['page_title'] = "ETP";
                setScalar($install_variables, 'page_title', '"' . $_POST['page_title'] . '"');
                $page_title = $_POST['page_title'];
            }
        }

        break;

    // entering rss setup
    case $steps['rss_setup']:
        // default page
        $display_step = $steps['rss_setup'];

        if ($process) {
            // next page
            // fall through to next step so final tests can be performed
            // XXX: the above is invalid logic, as a new page is now in-between. I believe by false-ing $process, we get around this prob.
            $install_step = $next_steps['rss_setup'];
            $display_step = $next_steps['rss_setup'];
            setScalar($install_variables, 'RSS', 'title', '"' . $_POST['feed_title'] . '"');
            setScalar($install_variables, 'RSS', 'description', '"' . $_POST['feed_description'] . '"');
            setScalar($install_variables, 'RSS', 'title_diff', '"' . $_POST['feed_title_diff'] . '"');
            setScalar($install_variables, 'RSS', 'description_diff', '"' . $_POST['feed_description_diff'] . '"');
            $process = false;
        } else {
            break;
        }

    // entering plugins setup
    case $steps['plugins']:
        $display_step = $steps['plugins'];
        
        if ($process) {
            $display_err = plugin_apply_config();
            if (!$display_err) {
                $display_step = $next_steps['plugins'];
                $install_step = $next_steps['plugins'];
            }
        } else {
            break;
        }

    // entering install
    case $steps['install']:
 
        $GLOBALS["dir"] = dirname($_SERVER['PHP_SELF']);
       
        // installation finished, cleanup
        if (isset($_GET['install'])) {
            unlink($install_file_name);
            // $dir = dirname($_SERVER['PHP_SELF']);

            $dir .= $dir == '/' ? '' : '/';

            header('Location: http://' . $CFG['server_name'] . $dir . $page_name);
            exit;
        }

        // default page
        $display_step = $steps['install'];

        // set configuration and perform installation
        preg_match('/([^\.]*)\.(.*)/', $page_name, $matches);
        $code_filename = 'editthispage_' . $matches[1] . '.' . $matches[2];

        $t =&  getDataLinesFromFile($install_file_name, 'CONFIGURATION');
        $config = implode("\n", $t);

        setScalar($config, 'guest_access_allowed', $guest_access_allowed);
        setScalar($config, 'CFG', 'admin_name', "'" . $CFG['admin_name'] . "'");
        setScalar($config, 'CFG', 'admin_email', "'" . $CFG['admin_email'] . "'");
        setScalar($config, 'CFG', 'admin_notes', "'" . $CFG['admin_notes'] . "'");
        setScalar($config, 'CFG', 'email_users', "'" . $CFG['email_users'] . "'");
        setScalar($config, 'CFG', 'server_name', "'" . $CFG['server_name'] . "'");
        setScalar($config, 'RSS', 'title', '"' . $RSS['title'] . '"');
        setScalar($config, 'RSS', 'description', "'" . $RSS['description'] . "'");
        setScalar($config, 'RSS', 'title_diff', '"' . $RSS['title_diff'] . '"');
        setScalar($config, 'RSS', 'description_diff', "'" . $RSS['description_diff'] . "'");
        setScalar($config, 'page_title', '"' . $page_title . '"');

        $config = preg_replace('/\/\*\sCFG_MARKER_USERS\s\*\//', $user_accounts, $config);

        $t =&  getDataLinesFromFile($install_file_name, 'ETP_HEAD');
        $script_head = implode("\n", $t);

        $t =&  getDataLinesFromFile($install_file_name, 'ETP_FOOT');
        $script_foot = implode("\n", $t);

        $script = "<?php\n"
            . "/* ETP_HEAD */\n"
            . $script_head . "\n"
            . "/* ETP_HEAD */\n"
            . "/* CONFIGURATION */\n"
            . $config . "\n"
            . "/* CONFIGURATION */\n"
            . "/* ETP_FOOT */\n"
            . $script_foot . "\n"
            . "/* ETP_FOOT */\n"
            . "?>\n";

        /* we now have the actual editthispage_XXX.(php) file content,
           and before we save it, we apply all plugins to it */
        apply_plugins($script);
        
        $fh = fopen($code_filename, 'w');

        fwrite($fh,$script);
        fclose($fh);
        unset($script_head);
        unset($script_config);
        unset($script_foot);
        unset($script);

        if ($upgrade_performed == true || $upgrade_performed == "true") {
            // we are upgrading, which means we don't create a storage file
        } else {
            $t =& getDataLinesFromFile($install_file_name, 'DATA');
            $data_file = implode("\n", $t);

            $data_file = str_replace('%%TITLE%%', $page_title, $data_file);

            $data = "<?php\n"
                . "require_once('" . $code_filename . "');\n"
                . "/* DATA */\n"
                . $data_file . "\n"
                . "/* DATA */\n"
                . "?>\n";

            $fh = fopen($page_name, 'w');
            fwrite($fh,$data);
            fclose($fh);
            unset($data);
            unset($data_file);
        }

    break;

}

if ($finalize || $install_step == $steps['confirm']) {
    // entering confirm settings
    // default page
    $display_step = $steps['confirm'];

    $msgs['failed_required_settings'] = false;
    $msgs['no_page_name'] = false;
    $msgs['no_user_accounts'] = false;
    $msgs['no_hostname'] = false;

    // default is the error state. checked later
    $msgs['no_super_editor'] = true;

    // make sure accounts were configured
    if (empty($users) || !count($users)) {
        $msgs['failed_required_settings'] = true;
        $msgs['no_user_accounts'] = true;

    // make sure there's at least one super-editor
    } else {
        foreach ($users as $k => $v) {
            if ($v['group'] == 'super-editor') {
                $msgs['no_super_editor'] = false;
            }
        }
    }

    if (!$CFG['server_name']) {
        $msgs['failed_required_settings'] = true;
        $msgs['no_hostname'] = true;
    }

    if ($upgrade_performed == true || $upgrade_performed == "true") {
        // we're upgrading; again, we don't care about page_name.
    } else if (!$page_name) {
        $msgs['failed_required_settings'] = true;
        $msgs['no_page_name'] = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EditThisPage Install v0.8</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
<style type="text/css">
/* Custom styles to complement Bootstrap */
.ok {
    color: #198754;
    font-weight: 600;
}
.notok {
    color: #dc3545;
    font-weight: 600;
}

.install-steps .nav-link {
    color: #495057;
    border-radius: 0;
    border: none;
    padding: 0.5rem 0.75rem;
}

.install-steps .nav-link:hover {
    color: #007bff;
    background-color: #e9ecef;
}

.install-steps .nav-link.active {
    color: #198754;
    font-weight: 600;
    background-color: #e7f3e7;
}

.install-header {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.install-card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
</style>


<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</head>
<body>
<div class="install-header">
    <div class="container">
        <div class="row">
            <div class="col">
                <h1 class="h2 mb-0">EditThisPagePHP Install v0.8</h1>
                <p class="mb-0 opacity-75">Modern wiki/CMS installation wizard</p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <div class="col-md-3">
            <div class="card install-card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Installation Steps</h5>
                </div>
                <div class="card-body p-0">
                    <nav class="nav nav-pills flex-column install-steps">
                        <?php
                        foreach ($install_labels as $k => $v):
                            $activeClass = ($display_step == $k) ? ' active' : '';
                            $href = ($k == $steps['install']) ? 
                                $_SERVER['PHP_SELF'] . '?install_step=' . $steps['confirm'] :
                                $_SERVER['PHP_SELF'] . '?install_step=' . $k;
                            ?>
                            <a class="nav-link<?= $activeClass ?>" href="<?= $href ?>"><?= $v ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card install-card">
                <div class="card-body"><?php
// display dialog
switch ($display_step):
    // initial tests
    case $steps['test']:
        ?>
        <h2 class="card-title">Testing Your Setup</h2>
        <table class="table table-sm table-hover">
        <tbody>
        <tr><td>
        Can we write to the install script?
        </td><td>

        <?= $msgs['failed_write'] ? '<span class="notok">FAIL</span>' : '<span class="ok">OK</span>' ?>

        </td></tr>

        <tr><td>
        Can we create new files in the install directory?
        </td><td>
        <?= $msgs['failed_makefile'] ? '<span class="notok">FAIL</span>' : '<span class="ok">OK</span>' ?>
        </td></tr>

        <tr><td>
        Can we create new directories?
        </td><td>
        <?= $msgs['failed_makedir'] ? '<span class="notok">FAIL</span>' : '<span class="ok">OK</span>' ?>
        </td></tr>

        <?php
        if (isset($msgs['failed_makenewdirfile'])) :
            ?>
            <tr><td>
            Can we create new files in this directory?
            </td><td>
            <?= $msgs['failed_makenewdirfile'] ? '<span class="notok">FAIL</span>' : '<span class="ok">OK</span>' ?>
            </td></tr>
            <?php
        endif;
        ?>

        </tbody>
        </table>

        <?php
        if ($msgs['count_failed']):
            ?>
            <p>
            Failed <?= $msgs['count_failed'] ?> out of <?= $msgs['count_tests'] ?> tests. Installation will not continue.
            </p><p>

            <p>
            This is likely due to the permission settings on the directory or the <?= $install_file_name ?>
            script. The web server must be able to write to <?= $install_file_name ?>  and the directory where
            it is located.
            </p>

            <p>
            Try the following to correct this:
            <ul>
            <li>Change to the directory to which you uploaded <?= $install_file_name ?>
            <li>Make the file world writeable:<br />
            $ chmod a+w <?=  $install_file_name?></li>
            <li>Make the directory accessible to all:<br />
            $ chgrp chmod a+w,a+x ./</li>

            <li>Click "Test Again" to check your setup again.</li>
            </ul>
            </p>

            <form action="<?= $PHP_SELF ?>" method="post">
            <input type="hidden" name="install_step" value="<?= $steps['test'] ?>">

            <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-warning">Test Again</button>
            </div>

            </form>

            <?php
        else:
            ?>
            <p>
            All tests passed. Click "Next" to continue. You can move to any step in the installation
            using the menu at the left.
            </p><p>
            You may abort the installation at any time.
            </p>
            <em>Note: if you abort the installation, be sure to remove the install script <?= $file['basename'] ?>
            from the server, or others will be able to access it.</em>
            </p>

            <form action="<?= $PHP_SELF ?>" method="post">
            <input type="hidden" name="install_step" value="<?= $next_steps['test'] ?>">

            <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Next &gt;&gt;</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
            </div>

            </form>

            <?php

        endif;
    break;

    case $steps['hostname']:
        ?>
        <h2 class="card-title">Set Hostname</h2>
        <p>
        Set the hostname of the server. This will be used for creating links to RSS feeds, administration
        functions and images.
        </p>
        <div class="alert alert-info">
        The hostname appears to be <strong><?= $_SERVER['SERVER_NAME'] ?></strong>
        <?= isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != $_SERVER['SERVER_NAME'] ? ' or <strong>' . $_SERVER['HTTP_HOST'] . '</strong>' :'' ?>
        </div>

        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['hostname'] ?>" />
        <input type="hidden" name="process" value="1" />
        <div class="mb-3">
            <label for="server_name" class="form-label">Server Name:</label>
            <input type="text" name="server_name" id="server_name" class="form-control" value="<?= $CFG['server_name'] ? $CFG['server_name'] : $_SERVER['SERVER_NAME'] ?>">
        </div>

        <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['hostname'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Next »</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
        </div>
        </div>

        </form>


        <?php
    break;

    case $steps['guest_access']:
        ?>
        <h2 class="card-title">Guest Access</h2>
        <p>
        Do you want to make this page publicly accessible?
        </p>
        <div class="alert alert-warning">
        If "yes", a default guest account will be created in the next step. This does not give guests
        editing permissions. Do not delete this account, or anonymous visitors will not be able to view the page. 
        </div>
        
        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="process" value="1" />
        <input type="hidden" name="install_step" value="<?= $steps['guest_access'] ?>" />
        <div class="form-check mb-4">
            <input type="checkbox" name="guest_access_allowed" value="true" id="guest_access" class="form-check-input"<?= $guest_access_allowed  == "true" ? ' checked' : '' ?> />
            <label for="guest_access" class="form-check-label">Yes, allow public access</label>
        </div>

        <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['guest_access'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Next »</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
        </div>
        </div>

        </form>

        <?php
    break;

    case $steps['create_accounts']:
        ?>
        <h2 class="card-title">Create Accounts</h2>
        <div class="alert alert-info">
        <strong>Create accounts for users:</strong> guest accounts can view the page. Editors have access to
        image and history functions, and can edit the main body content of the page. Super-editors
        can access all functions. You should have at least one super-editor account.
        </div>
        <p>Setting an email address will send notifications to that user of changes to the page, if
        this is enabled in the next step.</p>

        <?php
        if (empty($users)):
            ?>
            <div class="alert alert-warning">No accounts have been created.</div>
            <?php
        else:
            ?>
            <table class="table table-striped table-hover">
            <thead class="table-light">
            <tr>
            <th>Username</th>
            <th>Group</th>
            <th>Email</th>
            <th>Actions</th>
            </tr>
            </thead>
            <tbody>

            <?php
            foreach ($users as $k => $v):
                ?>
                <tr>
                <td><?= htmlClean($v['name']) ?></td>
                <td>
                    <?php
                    $badgeClass = $v['group'] == 'super-editor' ? 'bg-success' : 
                                  ($v['group'] == 'editor' ? 'bg-primary' : 'bg-secondary');
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlClean($v['group']) ?></span>
                </td>
                <td><?= htmlClean($v['email']) ?></td>
                <td>
                <?php
                if ($v['name'] == 'guest' && $v['password'] == 'guest' && $v['group'] == 'guest') {
                    $title = 'Default account for guest access. Required if anonymous access is allowed.';
                } else {
                    $title = 'Delete user account';
                }
                ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?install_step=<?= $steps['create_accounts'] ?>&process=1&deluser=<?= $k ?>" 
                   class="btn btn-sm btn-outline-danger" title="<?= $title ?>">Delete</a>
                </td>
                </tr>

                <?php
            endforeach;
            ?>
            </tbody>
            </table>
            <?php
        endif;
        ?>

        <h3 class="mt-4">Add User</h3>
        <?php
        if (isset($msgs['failed_add_user']) && $msgs['failed_add_user']):
            ?>
            <div class="alert alert-danger"><?= $msgs['failed_add_user'] ?></div>
            <?php
        endif;
        ?>
        <form action="<?= $PHP_SELF ?>" method="post" name="user_form">
        <input type="hidden" name="install_step" value="<?= $steps['create_accounts'] ?>" />
        <input type="hidden" name="process" value="1" />

        <div class="row g-3">
            <div class="col-md-6">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="group" class="form-label">Group</label>
                <select name="group" id="group" class="form-select">
                    <option value="guest">Guest</option>
                    <option value="editor">Editor</option>
                    <option value="super-editor">Super-editor</option>
                </select>
            </div>
            <div class="col-12">
                <label for="email" class="form-label">Email <span class="text-muted">(optional)</span></label>
                <input type="email" name="email" id="email" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control">
            </div>
        </div>

        <script>
        function checkAccountFields()
        {
            if (document.user_form.username.value == ''
                || document.user_form.password.value == ''
                || document.user_form.confirm_password.value == '') {

                alert('"Username", "Password" and "Confirm" are required fields');
                return false;
            }
            return true;
        }
        </script>
        
        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-success" onclick="return checkAccountFields()">Add User</button>
        </div>
        </form>

        <script>
        function accountFieldsEmpty()
        {
            if (document.user_form.username.value != ''
                || document.user_form.email.value != ''
                || document.user_form.password.value != ''
                || document.user_form.confirm_password.value != '') {

                return confirm("This user account has not yet been saved:"
                    + "\n\"Cancel\" will return to the form so you can save this account, "
                    + "\n\"Ok\" will proceed.");
            }
        }
        </script>

        <form action="<?= $PHP_SELF ?>" method="post" class="mt-4">
        <input type="hidden" name="install_step" value="<?= $next_steps['create_accounts'] ?>" />
        <div class="d-flex justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['create_accounts'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" onclick="return accountFieldsEmpty()">Next »</button>
            <button type="submit" name="finish" class="btn btn-success" onclick="return accountFieldsEmpty()">Finish</button>
        </div>
        </div>

        </form>
        <?php
    break;

    case $steps['email_notifications']:
        ?>
        <h2>Notify Users on Page Changes</h2>
        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['email_notifications'] ?>" />
        <input type="hidden" name="process" value="1" />
        <?php
        reset($users);
        for ($i = 0; $users[$i]; $i++) {
            if ($users[$i]["email"] && $users[$i]["group"] != "guest") {
                $ulist .= ($ulist ? ", " : "") . $users[$i]["name"];
            }
        }
        if ($ulist):
        ?>
        <p>Should the users (<?= $ulist ?>) be emailed when a page has been modified?
        <br />
        <input type="checkbox" name="email_users"<?= $CFG['email_users'] == "true" ? ' checked' : ''?>> Yes
        </p>

        <h2>Set Administrator Email</h2>
        <p>If Email Notifications is enabled, this address will be the address notifications will be appear
        to be from, and the address used for replies to that email.
        </p>

        <table style="width:100%;">
        <tr><td>
        Name<br /><input type="text" name="admin_name" value="<?= htmlClean($CFG['admin_name']) ?>">
        </td><td>
        Email<br /><input type="text" name="admin_email" value="<?= htmlClean($CFG['admin_email']) ?>">
        </td></tr>
        <tr><td colspan="2">
        <p>
        Optionally, add a note regarding the administrator. This is not used in the script,
        it is only saved in the configuration section for later reference.
        </p>
        Notes<br /><textarea name="admin_notes" cols="40" rows="2"><?= htmlClean($CFG['admin_notes']) ?></textarea>
        </td></tr>
        </table>
        <?php
        else:
        ?>
        <p>No email addresses were entered. The Notify Users feature is redundant.</p>
        <br/><br/>
        <?php
        endif;
        ?>

        <div class="buttoncontainer">
        <input class="formbutton" type="button" style="float:left" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['email_notifications'] ?>'" value="&lt;&lt; Back" />
        <input class="formbutton"  type="submit" value="Next &gt;&gt;" /><br /><input class="formbutton" type="submit" value="Finish" name="finish">
        </div>

        </form>

        <?php
    break;

    case $steps['page_name'] :
        ?>
        <h2>Page Name</h2>

        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['page_name'] ?>" />
        <input type="hidden" name="process" value="1" />

        <?php

        if ($upgrade_performed == true || $upgrade_performed == "true") {
            echo "<b>Upgrading</b><p>You are performing an upgrade, and thus the individual pages will not be affected. There is currently <b>no way</b> to upgrade the actual pages themselves (what is being upgraded is the 'editthispage_XYZ.php' file, which contains the EditThisPage code). If you would rather make a fresh install, move aside or delete the 'editthispage_*' files out of the installation directory AND the install-etp.php file, put a fresh copy of install-etp.php in the directory and rerun the installation.";
        } else {
        if (isset($msgs['failed_page_name']) && $msgs['failed_page_name']) :
            ?>
            <?php
            if ($msgs['failed_page_name_empty']) :
                ?>

                <p class="error">You need to set a name for the new page.</p>

                <?php
            endif;
            if ($msgs['failed_page_name_exists']) :
                ?>

                <p class="error">A file with that name already exists.</p>

                <?php
            endif;
            if ($msgs['failed_page_name_no_extension']) :
                ?>

                <p class="error">Please include an extension for the name your page will be saved under (e.g. 'index.php').</p>

                <?php
            endif;
        endif;
        if (!$page_name) $page_name = "index.php";
        ?>

        <p>
        The name your page will be saved under.
        This should include the extension, but not the path to the file, e.g. "index.php".
        <br />
        <input type="text" name="pagename" value="<?= @$page_name ?>" />
        </p>

        <h2>Page Title</h2>
        <p>The title for your page. This will be displayed in the title bar of the browser and RSS feeds.
        <br />
        <input type="text" name="page_title" value="<?= @$page_title ?>" />
        </p>
        <?php } ?>

        <div class="buttoncontainer">
        <input class="formbutton" type="button" style="float:left" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['page_name'] ?>'" value="&lt;&lt; Back" />
        <input class="formbutton" type="submit" value="Next &gt;&gt;" /><br /><input class="formbutton" type="submit" value="Finish" name="finish">
        </div>

        </form>

        <?php
    break;

    case $steps['rss_setup'] :
        ?>

        <h2>RSS Options</h2>
        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['rss_setup'] ?>" />
        <input type="hidden" name="process" value="1" />
        <p>The RSS shows recent versions of your page.</p>
        <p>Feed title
        <br />
        <input type="text" name="feed_title" value="<?= !$RSS['title'] ? $page_title . ' RSS Title' : htmlClean($RSS['title']) ?>">
        </p>

        <p>Feed description
        <br />
        <input type="text" name="feed_description" value="<?= !$RSS['description'] ? $page_title . ' RSS Feed' : htmlClean($RSS['description']) ?>" style="width:30em">
        </p>

        <h2>RSS Diff Feed Options</h2>
        <p>The RSS diff feed shows recent additions and deletions to your page. Set the feed title and description here.</p>

        <p>Diff feed title
        <br />
        <input type="text" name="feed_title_diff" value="<?= !$RSS['title_diff'] ? $page_title . ' Changes' : htmlClean($RSS['title_diff']) ?>">
        </p>

        <p>Diff feed description
        <br />
        <input type="text" name="feed_description_diff" value="<?= !$RSS['description_diff'] ? $page_title . ' Changes Feed' : htmlClean($RSS['description']) ?>" style="width:30em">
        </p>

        <div class="buttoncontainer">
        <input class="formbutton" type="button" style="float:left" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['rss_setup'] ?>'" value="&lt;&lt; Back" />
        <input class="formbutton" type="submit" value="Next &gt;&gt;" /><br /><input class="formbutton" type="submit" value="Finish" name="finish">
        </div>

        </form>

        <?php
    break;

case $steps['plugins']:
?>
<form action="<?= $PHP_SELF ?>" method="post">
<input type="hidden" name="install_step" value="<?= $steps['plugins'] ?>" />
<input type="hidden" name="process" value="1" />
<h2>Plugins</h2>
<p>The plugins listed below will be installed, as they are located in the "plugin" directory. Plugins may report errors here, as well, in case they discover that they will not function properly for some reason.</p>
<ul>
<?php
$got_plugs = false;
$got_error = false;
$AFFIRMING_PLUGIN = true;
$d = dir("plugin/");
while ($d && (false !== ($entry = $d->read()))) {
    if (substr($entry, -4) == ".php") {
        $pfname = substr($entry, 0, -4);
        $PLUGIN_ERROR = null;
        $IS_PLUGIN = false;
        include("plugin/" . $entry);
        if ($IS_PLUGIN) {
            $got_plugs = true;
            if (!$PLUGIN_NAME) $PLUGIN_NAME = $entry;
            if (!$PLUGIN_VER) $PLUGIN_VER = "?";
            echo ("<li> <b>" . $PLUGIN_NAME . "</b> (v" . $PLUGIN_VER . ") [" . $entry . "] &rarr; " . ($PLUGIN_ERROR ? "<font color=red>" . $PLUGIN_ERROR . "</font>" : "<font color=green>OK</font>") . "</li>");
            $got_error |= $PLUGIN_ERROR;
            if ($PLUGIN_CONFIG) {
                $fh = @fopen("plugin/" . $entry, 'a');
                if ($fh) {
                    fclose($fh);
                    plugin_config($pfname, $PLUGIN_CONFIG);
                } else {
                    $unwritable.= ($unwritable ? ", " : "") . $entry;
                }
            }
        } else $notaplug .= ($notaplug ? ", " : "") . $entry;
    }
}
if ($notaplug) { echo "<li><font color=grey>The following files do not claim that they are plugins, which may mean they are support files for one: <b>$notaplug</b></font></li>"; }
if ($unwritable) { echo "<li><font color=red>The following plugins require that the install script can modify the plugin files directly, but the files are currently readonly -- the installer is unable to provide configuration for these plugins: <b>$unwritable</b></font> (in linux, this is addressed by doing <b>chmod a+w plugin/*</b>)</li>"; }
if (!$got_plugs) { echo "<li><font color=grey>No plugins were found.</font></li>"; }
?>  
</ul>
<p><?= $got_error ? "<font color=red>There were errors in at least one of the plugins listed above. ETP will still attempt to install the plugin in question, but there's of course the chance that things crash and burn if you don't fix the above.</font>" : "If a plugin is not listed above, you need to copy its .php file to the plugin directory and then reload this page. If a plugin is listed that you do not want, delete the file from the \"plugin\" directory before continuing." ?></p>
<div class="buttoncontainer">
<input class="formbutton" type="button" style="float:left" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['plugins'] ?>'" value="&lt;&lt; Back" />
<input class="formbutton" type="submit" value="Next &gt;&gt;" /><br /><input class="formbutton" type="submit" value="Finish" name="finish">
</div>

</form>
<?php
break;

    case $steps['confirm']:
        ?>


        <?php
        if ($msgs['failed_required_settings']) :
            ?>
            <h2>Configuration Error</h2>
            <p class="error">Not all required settings have been configured:</p>

            <?php
            if ($msgs['no_hostname']) :
                ?>

                <p>You need to configure your hostname.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['hostname'] ?>">Set Hostname</a>
                </p>

                <?php
            endif;
            if ($msgs['no_user_accounts']) :
                ?>

                <p>No user accounts have been created; your page will not be accessible.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['create_accounts'] ?>">Create Accounts</a>
                </p>

                <?php
            endif;
            if ($msgs['no_page_name']) :
                ?>

                <p>You need to set a file name for your page.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['page_name'] ?>">Set Page Name</a>
                </p>

                <?php
            endif;
            ?>
            <br /><br />
            <?php
        else:
            ?>

            <h2>Confirm Settings</h2>

            <p>These user accounts have been created:</p>

            <table style="width:100%;">
            <tr>
            <th>Username</th>
            <th>Group</th>
            <th>Email</th>
            <th>&nbsp;</th>
            </tr>

            <?php
            foreach ($users as $k => $v):
                ?>
                <tr>
                <td><?= $v['name'] ?></td>
                <td><?= $v['group'] ?></td>
                <td><?= $v['email'] ?></td>
                </tr>

                <?php
            endforeach;
            ?>
            </table>

            <?php
            if ($msgs['no_super_editor']) :
                ?>

                <p><em>No super-editor account was found.</em> You can add one in
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['create_accounts'] ?>">Create Accounts</a>.
                If no super-editor account is created, some functions will not be available.
                </p>

                <?php
            endif;
            ?>

            <p>
            Your page will be saved as <em><?= $page_name ?></em>
            </p>

            <p>
            If you are satisfied with these settings, click Install to complete your installation.
            </p>

            <form action="<?= $PHP_SELF ?>" method="post">
            <input type="hidden" name="install_step" value="<?= $next_steps['confirm'] ?>" />
            <input type="hidden" name="process" value="1" />
            <div class="buttoncontainer">
            <input class="formbutton" type="button" style="float:left" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['confirm'] ?>'" value="&lt;&lt; Back" />
            <input class="formbutton"  type="submit" value="Install &gt;&gt;" />
            </div>

            </form>

            <?php
        endif;
    break;

    case $steps['install'] :
        ?>
        <h2>Finished!</h2>
        <p><em>Installation is complete.</em></p>
        <p>A file with your page's configuration information has been saved under the name
        <?= $code_filename ?>. You can add users or change configuration options by editing
        this file. Please see the INSTALL file that came with this distribution for details.
        </p>
        <p>
        Would you like to:
        <ul>
        <li><a href="<?= $_SERVER['PHP_SELF'] ?>?install_step=<?= $steps['test'] ?>">create another page</a>, or</li>
        <li><a href="<?= $_SERVER['PHP_SELF'] ?>?install=1&install_step=<?= $steps['install'] ?>">remove the install
        script and view your new page?</a></li>
        </ul>
        </p>

        <?php
    break;

endswitch;
?>
                </div>
            </div>
            <div class="mt-3 text-muted small text-center">
                Step <?= (1+$display_step) ?> / <?= count($steps) ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
$t =&  getDataLinesFromFile($install_file_name, 'CONFIGURATION');
$config = implode("\n", $t);

$t =&  getDataLinesFromFile($install_file_name, 'ETP_HEAD');
$script_head = implode("\n", $t);

$t =&  getDataLinesFromFile($install_file_name, 'ETP_FOOT');
$script_foot = implode("\n", $t);

$script = "<?php\n"
    . "/* USER_ACCOUNTS */\n"
    . $user_accounts . "\n"
    . "/* USER_ACCOUNTS */\n"
    . "/* INSTALL_VARIABLES */\n"
    . $install_variables . "\n"
    . "/* INSTALL_VARIABLES */\n"
    . "/* INSTALLER */\n"
    . $installer . "\n"
    . "/* INSTALLER */\n"
    . "?>\n\n<?php\n"
    . "/* ETP_HEAD */\n"
    . $script_head . "\n"
    . "/* ETP_HEAD */\n"
    . "/* CONFIGURATION */\n"
    . $config . "\n"
    . "/* CONFIGURATION */\n"
    . "/* ETP_FOOT */\n"
    . $script_foot . "\n"
    . "/* ETP_FOOT */\n"
    . "?>\n";

$fh = @fopen($file['basename'], 'w');
@fwrite($fh,$script);
@fclose($fh);
exit;

/* INSTALLER */
?>

<?php
/* ETP_HEAD */
/**
 * EditThisPage.php
 *
 * Edit This Page PHP - a single-file, completely self-contained PHP
 * script that can be uploaded to any webhost that supports PHP, which
 * allows for the HTML content of that page to be self-edited by a link
 * on that page.
 *
 * Currently works with PHP 4 and 5, and does not require SQL. Every
 * revision makes a backup of itself, so no data can be lost. PHP is not
 * allowed by default in the HTML text itself, to minimize security risks.
 *
 * Note that this documentation is generated from the installer file,
 * install-etp.php, which carries the actual editthispagephp script as
 * a payload. Line numbers reflect where elements are found in the installer.
 *
 * See INSTALL for configuration information.
 * See README for license information.
 *
 * Maintained by:
 *      Christopher Allen <ChristopherA@AlacrityManagement.com>
 *
 * Contributors:
 *      - Phil Tilden <phil@filebottle.com>
 *      - Jesse Hodges <jesse@cedsn.com>
 *      - Shannon Appelcline <shannona@skotos.net>
 *      - Dwayne Holmberg <dwayne@iconys.com>
 *      - Kalle Alm <kalle@enrogue.com>
 *
 * Thanks to:
 *      - Urs Gehrig <urs@circle.ch>
 *
 * @package EditThisPage
 * @version 0.8
 * @copyright Alacrity Management Corp. 2003, 2004
 */

/**
 * Developer Note
 *
 * This script is split into several parts:
 * Main Configuration - what users will usually need to configure.
 * Extended Configuration - extended configuration for advanced users.
 * Initialization - various actions that need to be taken every time
 *      the script is invoked.
 * Functions - where all the real work is done.
 * Control Logic - call necessary functions, set flags that control
 *      later page display, etc.
 * Administration Page Generation - all the HTML generation for the
 *      admin pages
 */

/**
 * version of EditThisPagePHP
 *
 * @global type string
 */
$VERSION = '0.8';

/**
 * script name
 *
 * @global type string
 */
$NAME= 'EditThisPagePHP';

/**
 * Begin Main Configuration
 *
 * This section contains variables which should be
 * set to customize EditThisPage for your own use
 */

// do not remove this comment!
/* ETP_HEAD */
/* CONFIGURATION */

/**
 * can anonymous users view the page?
 *
 * Set to false if you want to restrict access to view the page.
 * @global boolean $guest_access_allowed
 */

$guest_access_allowed = false;

/**
 * user accounts
 *
 * Each user account should have four keys set:
 * <ul>
 * <li>name, the users login name</li>
 * <li>password</li>
 * <li>email, if they should be notified of updates to this page.
 *   If not, set this to an empty string, eg. $users['$i]['email] = "";</li>
 * <li>group, the group that they belong to (for authentication)</li>
 * </ul>
 *
 * If $guest_access_allowed is true, you must set up a guest
 * account. Name, password and group must be 'guest':
 * <code>
 * $users[$i]['name']      = "guest";
 * $users[$i]['password']  = "guest";
 * $users[$i]['email']     = "";
 * $users[$i]['group']     = 'guest';
 * </code>
 * @global array $users
 * @see $guest_access_allowed, $auth_actions, $auth_edit_fields
 */
$users = array();

/* CFG_MARKER_USERS */

/* PLUGIN::auth::before_auth */

/**
 * actions each group is allowed to perform
 *
 * Valid settings are:
 * <ul>
 * <li>all</li>
 * <li>view_page</li>
 * <li>edit</li>
 * <li>override_lock</li>
 * <li>continue_and_save</li>
 * <li>upload_images</li>
 * <li>delete_images</li>
 * <li>rename_file</li>
 * <li>create_page</li>
 * <li>view_history</li>
 * <li>delete_history</li>
 * <li>view_diffs</li>
 * <li>add_comment</li>
 * <li>send_ping</li>
 * <li>view_trackbacks_comments</li>
 * <li>delete_trackbacks_comments</li>
 * </ul>
 * Note: the guest account may ONLY be set to 'view_page', 'add_comment'
 * or 'all'. Setting it to anything else will make inaccessible any
 * content blocks that guests are not allowed to edit
 * eg.
 * <code>
 * $auth_actions['editor'] = array(
 *     'view_page',
 *     'edit',
 *     'preview',
 * );
 * </code>
 * @global array $auth_actions
 * @see $users, $auth_edit_fields
 */

/* PLUGIN::auth */

$auth_actions['guest'] = array(
    'view_page',
    'add_comment',
);

$auth_actions['editor'] = array(
    'view_page',
    'edit',
    'override_lock',
    'continue_and_save',
    'upload_images',
    'delete_images',
    'view_history',
    'delete_history',
    'view_diffs',
    'add_comment',
    'view_trackbacks_comments',
    'delete_trackbacks_comments',
    'send_notification_pings',
);

$auth_actions['super-editor'] = array(
    'all',
);

/* PLUGIN::auth::after_auth */

/**
 * areas of the page each group is allowed to edit
 *
 * Valid settings are:
 * <ul>
 * <li>'PAGE_HEADER', the page header</li>
 * <li>'PAGE_MAIN_CONTENT', the body of the page</li>
 * <li>'PAGE_FOOTER', the page footer</li>
 * <li>'PAGE_HIDDEN_FOOTER', the hidden area of the page footer</li>
 * <li>'META_EDIT_COMMENT', editor comment, appears on history page</li>
 * <li>'MSG_AUTH_FAILED', message for failed authorization</li>
 * </ul>
 * eg.
 * <code>
 * $auth_edit_fields['editor'] = array(
 *     'PAGE_MAIN_CONTENT'
 * );
 *
 * $auth_edit_fields['super-editor'] = array(
 *     'PAGE_HEADER',
 *     'PAGE_MAIN_CONTENT',
 *     'PAGE_FOOTER',
 *     'PAGE_HIDDEN_FOOTER',
 *     'META_EDIT_COMMENT',
 * );
 * </code>
 * @global array $auth_edit_fields
 * @see $users, $auth_actions
 */

/* PLUGIN::auth::before_fields */

$auth_edit_fields['guest'] = array(
);

$auth_edit_fields['editor'] = array(
    'PAGE_MAIN_CONTENT',
    'META_EDIT_COMMENT',
);

$auth_edit_fields['super-editor'] = array(
    'PAGE_HEADER',
    'PAGE_MAIN_CONTENT',
    'PAGE_HIDDEN_FOOTER',
    'PAGE_FOOTER',
    'META_EDIT_COMMENT',
    'MSG_AUTH_FAILED',
);

/* PLUGIN::auth::after_fields */

/**
 * groups with no limits on image uploads
 *
 * These groups won't have restrictions on image size
 * or number for uploads.
 * These must be valid groups, eg.
 *
 * $no_image_limit = array(
 *     'super-editor',
 * );
 * </code>
 * @global array $no_image_limit
 * @see $users
 */

$no_image_limit = array(
    'super-editor',
);

/* PLUGIN::cfg */

/**
 * email users when page is updated?
 *
 * Should users be notified by email when the page is updated?
 * valid values are true and false, eg.
 * <code>
 * $CFG['email_users'] = false;
 * </code>
 * This is the global level configuration. Email notifications can
 * be set on a user-by-user basis according to whether or not an
 * email address is set for a given user.
 * Possible values are true and false.
 * @global boolean $CFG['email_users']
 * @see $users
 */
$CFG['email_users'] = false;


/**
 * moderate all comments
 *
 * If enabled, any comments made to the site will be moderated,
 * meaning a person with at least editor-access will have to
 * approve the comment.
 */
$CFG['moderate_comments'] = false;

/**
 * moderate all trackbacks
 *
 * If enabled, any incoming trackbacks will be invisible until
 * an editor/super-editor approves/deletes the entry.
 */
$CFG['moderate_trackbacks'] = false;

/**
 * allow includes in content (but not comments and the like)
 *
 * Enabling this is A SECURITY RISK, but if you wish to be able to
 * use __PAGE_INCLUDE(pagename)__ in your page content, e.g.
 * to include sidebars or similar, this must be set to true.
 */
$CFG['allow_includes'] = false;

/**
 * notes on administrator
 *
 * Notes regarding the administrator (eg, to help identify them if you
 * forget who foo@example.com is
 *
 * @global string $CFG['admin_notes']
 */
$CFG['admin_notes'] = "";

/**
 * administrator name
 *
 * The name of the owner of the admin_email address
 *
 * @global string $CFG['admin_name']
 */
$CFG['admin_name'] = "";

/**
 * administrative email
 *
 * The email address notification emails will appear to be from.
 * This is also the address the Reply-To header will be set to.
 *
 * @global string $CFG['admin_email']
 */
$CFG['admin_email'] = "adminemail@example.com";

/**
 * lockfile extension
 *
 * set the extension to use for the lockfile
 * (the default 'lock' extension should work in most cases)
 * @global string $CFG['lockext']
 */
$CFG['lockext'] = "lock";

/**
 * page lock time
 *
 * Set the time, in seconds, that the editing session will be valid for.
 * @global int $CFG['locktime']
 */
$CFG['locktime'] = 60*30; // 30 minutes

/**
 * set the date/time format
 *
 * Sets the date/time format used on most admin pages
 * and in notification emails.
 * @global string $CFG['dateformat']
 * @link http://www.php.net/date
 */
$CFG['dateformat'] = "Y-m-d H:i:s / T";

/**
 * set the date/time format
 *
 * Sets the date/time format used on history page
 * @global string $CFG['secondarydateformat']
 * @link http://www.php.net/date
 */
$CFG['secondarydateformat'] = "m/d/y g:ia";

/**
 * set the date/time format for RSS feeds
 *
 * "D, j M Y G:i:s T" = Mon, 30 Sep 2002 11:00:00 GMT
 * gives the standard RSS format
 * @global string $CFG['rss_date_format']
 * @link http://www.php.net/date
 */
$CFG['rss_date_format'] = "D, j M Y H:i:s T";

/**
 * number of images that can be uploaded
 *
 * Sets the maximum number of images which can
 * be uploaded for a given page.
 * @global int $CFG['image_upload_limit']
 * @see $CFG['image_upload_limit']
 */
$CFG['image_upload_limit'] = 10;

/**
 * maximum size of images that can be uploaded
 *
 * Sets the maximum size, in bytes, of images which can
 * be uploaded.
 * @global int $CFG['max_image_size']
 * @see $CFG['image_upload_limit']
 */
$CFG['max_image_size'] = 45000;

/**
 * session file path
 *
 * Sets the directory to which session files will be saved
 * If false, the default set in php.ini will be used.
 * You will not usually need to modify this, except in
 * certain shared server environments.
 * @link http://us2.php.net/manual/en/function.session-save-path.php
 * @global int $CFG['session_save_path']
 */
$CFG['session_save_path'] = false;

/**
 * maximum number of items for the RSS feed
 *
 * @global int $CFG['max_rss_items']
 */
$CFG['max_rss_items'] = 10;

/**
 * font style for RSS diff items for the most recent file
 *
 * @global string $CFG['rss_diff_font_a']
 */
$CFG['rss_diff_font_a'] = 'color="#000000" font-family="Courier, Monospace"';

/**
 * background style for RSS diff items for the most recent file
 *
 * @global string $CFG['rss_diff_bg_a']
 */
$CFG['rss_diff_bg_a'] = '#efefaa';

/**
 * font style for RSS diff items for the older file
 *
 * @global string $CFG['rss_diff_font_b']
 */
$CFG['rss_diff_font_b'] = 'color="#ffffff" font-family="Courier, Monospace"';

/**
 * background style for RSS diff items for the older file
 *
 * @global string $CFG['rss_diff_bg_b']
 */
$CFG['rss_diff_bg_b'] = '#5577bb';

/**
 * font style for RSS diff items for both files
 *
 * @global string $CFG['rss_diff_font_both']
 */
$CFG['rss_diff_font_both'] = 'color:="#000000" font-family="Courier, Monospace"';

/**
 * background style for RSS diff items for both files
 *
 * @global string $CFG['rss_diff_bg_both']
 */
$CFG['rss_diff_bg_both'] = '#ffffff';

/**
 * server protocol
 *
 * This should configure itself correctly
 *
 * @global string $CFG['protocol']
 */
$CFG['protocol'] = strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, strpos($_SERVER['SERVER_PROTOCOL'],'/')));

/**
 * server name
 *
 * This should configure itself correctly
 *
 * @global string $CFG['server_name']
 */
$CFG['server_name'] = "";

/**
 * server IP address
 *
 * This should configure itself correctly
 *
 * @global string $CFG['server_address']
 */
$CFG['server_address'] = $_SERVER['SERVER_ADDR'];

/**
 * rules for vetting comments
 *
 * Each rule is a regular expression in the array. Comments
 * which match one or more of these rules (as specified in
 * $comments_rules_threshold) will be discarded.
 *
 * Note that $comment_rules are fed sequentially to preg_match_all().
 * This means that multiple occurrences will all count towards the threshold
 * @global array $comment_rules
 * @see $comment_rules_threshold
 */
$comment_rules = array(
    '/<a[^>]+href/',
    '/http:/',
    '/https:/',
);

/**
 * number of matches to $comment_rules needed to trigger comment rejection
 *
 * If the number of matches is equal to or greater than the threshold,
 * the comment will be rejected.
 * @global int $comment_rules_threshold
 * @see $comment_rules
 */
$comment_rules_threshold = 4;

/**
 * HTML tags allowed in comments
 *
 * This should be a string of HTML tags allowed in comment bodies.
 * cf. strip_tags()
 * @global string $CFG['allowed_html']
 */
$CFG['allowed_html'] = '<a><p><br><ul><ol><li><em><b><i>';


/**
 * Begin RSS2.0 Configuration
 */

/**
 * RSS configuration
 *
 * See the {@link http://blogs.law.harvard.edu/tech/rss RSS 2.0 specification}
 * for details.
 * @global type var
 */

/**
 * Title for this page's RSS feed
 * and trackback RDF
 *
 * @global string $RSS['title']
 */

$RSS['title'] = 'EditThisPagePHP';

/**
 * Title for this page's RSS diff feed
 *
 * @global string $RSS['title_diff']
 */

$RSS['title_diff'] = 'EditThisPagePHP Changes';

/**
 * RSS description
 *
 * @global string $RSS['description']
 */
$RSS['description'] = 'Latest changes on EditThisPagePHP';

/**
 * RSS diff description
 *
 * @global string $RSS['description_diff']
 */
$RSS['description_diff'] = 'Diff feed for EditThisPagePHP.';

/**
 * RSS feed language
 *
 * @global string $RSS['language']
 */
$RSS['language'] = 'en-us';

/**
 * RSS copyright
 *
 * @global string $RSS['copyright']
 */
$RSS['copyright'] = '';

/**
 * RSS managing editor
 *
 * If a user of the 'super-editor' group exists
 * the first super-editor defined will be used.
 * Leave this as an empty string unless you want
 * to explicitly set the managing editor.
 * @global string $RSS['managingEditor']
 */
$RSS['managingEditor'] = '';

/**
 * RSS web master
 *
 * If a user of the 'super-editor' group exists
 * the first super-editor defined will be used.
 * Leave this as an empty string unless you want
 * to explicitly set the web master.
 * @global string $RSS['webMaster']
 */
$RSS['webMaster'] = '';

/**
 * RSS category, optional
 *
 * @global string $RSS['category']
 */
$RSS['category'] = '';

/**
 * RSS generator
 *
 * @global string $RSS['generator']
 */
$RSS['generator'] = 'EditThisPagePHP ' . $VERSION;

/**
 * RSS ttl
 *
 * Time to live for this channel, in minutes
 * @global string $RSS['ttl']
 */
$RSS['ttl'] = '60';

/**
 * RSS image, optional
 *
 * Image to use for this channel. If you wish to use an image
 * uncomment the lines below. Set $RSS['image'] = false if
 * you are not using an image (default configuration).
 * Keys: 'url', 'title', 'link', 'height', 'width', 'description'
 * Note: 'link' is automatically set during initialization. Do not
 * specify that here.
 * width must not be more than 144, height must not be more than 400;
 * @link http://blogs.law.harvard.edu/tech/rss#ltimagegtSubelementOfLtchannelgt
 * @global boolean,array $RSS['image']
 */
$RSS['image'] = false;

/*
$RSS['image']['url'] = '';
$RSS['image']['title'] = $RSS['title'];
$RSS['image']['width'] = '88';
$RSS['image']['height'] = '31';
$RSS['image']['description'] = '';
*/

/**
 * End RSS2.0 Configuration
 */

/**
 * End Main Configuration
 * You do not need to modify anything below this line
 * for normal operation.
 */

/**
 * Begin Extended Configuration
 *
 * This section contains variables you can set to further
 * customize EditThisPage for your use. These do not need to
 * be set for ordinary use.
 */

/**
 * Start Extended Data HTML Configuration
 */

/**
 * HTML for extended data
 *
 * These templates will be used to format comments
 * and trackbacks. Entries with an 'open' or 'close'
 * suffix will be added to the beginning or end of the
 * full set of records. The entry with just the name
 * of the data field will be applied to each record
 * (comment or trackback).
 *
 * Tokens included in these templates will be expanded.
 * Templates for each individual record may also include
 * lowercase tokens that correspond to the data entry for
 * each record.
 *
 * Valid tokens for trackbacks ('EXT_TRACKBACK') are:
 * <ul>
 * <li>__url__</li>
 * <li>__title__</li>
 * <li>__blog_name__</li>
 * <li>__excerpt__</li>
 * <li>__time__</li>
 * </ul>
 *
 * Valid tokens for comments ('EXT_COMMENTS') are:
 * <ul>
 * <li>__body__</li>
 * <li>__name__</li>
 * <li>__time__</li>
 * </ul>
 * 
 * @global array $EXT_HTML
 */
$EXT_HTML = array();

$EXT_HTML['EXT_TRACKBACK.open']  = <<<EOF
    <h2 style="font-weight: 700;border-bottom: #000 solid 1px">TrackBack</h2>
    <p>
    Trackback URL for this page:<br />
    __TRACKBACK_URL__
    </p>
    <br />
    <div id="trackback">
EOF;

$EXT_HTML['EXT_TRACKBACK'] = <<<EOF
    <p>__excerpt__</p>
    <p style="color:#999; font-weight:700; border-top: #999 solid 1px; padding-top:3px;">
    From <a href="__url__">__title__</a> at __blog_name__,
    tracked at: __time__
    </p>
    <br />
EOF;

$EXT_HTML['EXT_TRACKBACK.close'] = <<<EOF
    </div>
    <br />
EOF;


$EXT_HTML['EXT_COMMENTS.open']  = <<<EOF
    <div id="comments">
    <h2 style="font-weight:700;border-bottom: #000 solid 1px">Comments</h2>
EOF;


$EXT_HTML['EXT_COMMENTS'] = <<<EOF
    <p>
    __body__
    </p>
    <p style="color:#999; font-weight:700; border-top: #999 solid 1px; padding-top:3px;">
    Posted by <a href="__url__" rel="nofollow">__name__</a> at __time__
    </p>
    <br />
EOF;

$EXT_HTML['EXT_COMMENTS.close'] = <<<EOF
    </div>
EOF;

/**
 * End Extended Data HTML Configuration
 */

/**
 * tokens which will be expanded to full HTML on the page
 *
 * Any number of tokens can be added to this array. The key
 * can be anything, but a nested array with the keys 'token'
 * and either 'html' or 'callback' must be present.
 * If 'callback' is used, 'html' will be set to the result
 * of the callback function.
 * <code>
 * $TOKENS['the_token']['token'] = '__EXPAND_THIS__';
 * $TOKENS['the_token']['html'] = 'any valid HTML';
 * </code>
 *
 * The optional key 'callback' can be used to dynamically
 * generate the HTML the token will expand to from a callback
 * function. This should be just the name of the function as
 * a string, eg.,
 * <code>
 * $TOKENS['the_token']['callback'] = 'formatExtHtml';
 * </code>
 *
 * The optional key 'args' is an associative array of arguments
 * to pass in to the function. These can be strings, or any
 * variable available in the global namespace. Variables and strings must
 * be quoted. If a static string is being passed in, it must
 * be in double quotes, eg.
 * <code>
 * $TOKENS['the_token']['args'] = array ('"comments"', '"EXT_COMMENTS"', '$chunks['EXT_COMMENTS']');
 * </code>
 *
 * The above example will be transformed to:
 * <code>
 * formatExtHtml("EXT_COMMENTS", $chunks['EXT_COMMENTS']);
 * </code>
 * and evaled immediately before the page is output.
 *
 * The optional key 'access' can be used to restrict which user
 * groups have access to this token. If this key is present,
 * only those groups specified in the array will be able to save
 * a field with this token. For all others, it will simply be
 * stripped out, eg.
 * <code>
 * $TOKENS['the_token']['access'] = array (
 *     'super-editor'
 * );
 * </code>
 * The optional keys 'auto_open' and 'auto_close' can be used
 * to automatically include the token at the beginning or end
 * of the specified field(s). These fields must be specified
 * as an array, eg.
 * <code>
 * $TOKENS['the_token']['auto_open'] = array(
 *     'PAGE_MAIN_CONTENT'
 * );
 * $TOKENS['other_token']['auto_close'] = array(
 *     'PAGE_MAIN_CONTENT'
 * );
 * </code>
 * <em>Note:</em> tokens that use 'auto_open' and 'auto_close' will
 * not be accessible to an editor. They can only be added by the
 * script itself.
 *
 * The optional key
 * @global array $TOKENS
 */
$TOKENS = array();

$self = $_SERVER['PHP_SELF'];

$TOKENS['edit_button']['token'] = "__EDIT_BUTTON__";
$TOKENS['edit_button']['access'] = array('super-editor');
$TOKENS['edit_button']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <span style="
        background-color: #a7adc7;
        border:2px solid;
        border-color:#d7dde7 #676d77 #676d77 #d7dde7;
    ">
    <a href="$self?action_edit=1" title="Edit This Page"
        style="font-family:fixed;
            font-weight:700;
            font-size:14px;
            color:#000;
            text-decoration:none;
    ">&nbsp;&nbsp;&gt;&nbsp;</a>
    </span>
    <!-- etp end nofeed -->
EOF;

$TOKENS['edit_button_bluebutton']['token'] = "__EDIT_BUTTON_STYLE=BLUEBUTTON__";
$TOKENS['edit_button_bluebutton']['access'] = array('super-editor');
$TOKENS['edit_button_bluebutton']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <form action="$self" method="post">
    <input type="submit" name="action_edit" value="Edit Page"
    style="width:100px; 
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#000000;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;" />
    </form>
    <!-- etp end nofeed -->
EOF;

// these open and close the comments

// this adds the comment field to the page
// note that the 'add_comment' permission must be set
// for the appropriate groups

// this is just a temp variable for use in the below heredoc
$comment_action = $_SERVER['PHP_SELF'];

$TOKENS['comment_form']['token'] = '__COMMENT_FORM__';

$TOKENS['comment_form']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <form action="$comment_action" method="post">
    Name:<br />
    <input type="text" name="comment[name]" size="40" /><br /><br />
    Email (will not be displayed):<br />
    <input type="text" name="comment[email]" size="40" /><br /><br />
    URL:<br />
    <input type="text" name="comment[url]" size="40" /><br /><br />
    Comment:<br />
    <textarea name="comment[body]" rows="5" cols="40"></textarea><br />
    <input type="submit" name="action_add_comment" value="Add Comment"
    style="width:100px; 
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#000000;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;" />
    </form>
    <!-- etp end nofeed -->
EOF;

// this expands to the comments for the page
$TOKENS['comments']['token'] = '__COMMENTS__';
$TOKENS['comments']['callback'] = 'formatExtHtml';
$TOKENS['comments']['args'] = array('"comments"', '"EXT_COMMENTS"', '$chunks["EXT_COMMENTS"]');

// create a hidden area with a button for displaying it
$state = (isset($_COOKIE['etp_state']) && $_COOKIE['etp_state'] == 'open') ? 'display:inline' : 'display:none';
$TOKENS['hidden_area_start']['token'] = '__HIDDEN_AREA_START__';
$TOKENS['hidden_area_start']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <div id="hidden_area" style="$state">
    <!-- etp end nofeed -->
EOF;

$TOKENS['hidden_area_end']['token'] = '__HIDDEN_AREA_END__';
$TOKENS['hidden_area_end']['html'] = <<<EOF
    <!-- etp start nofeed -->
    </div>
    <!-- etp end nofeed -->
EOF;

$self = $_SERVER['PHP_SELF'];
$open_button_state = empty($_COOKIE['etp_state']) || $_COOKIE['etp_state'] == 'close' ? 'display:inline' : 'display:none';
$close_button_state = (isset($_COOKIE['etp_state']) && $_COOKIE['etp_state'] == 'open') ? 'display:inline' : 'display:none';
$TOKENS['hidden_area_button']['token'] = '__HIDDEN_AREA_BUTTON__';
$TOKENS['hidden_area_button']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <script type="text/javascript">
    <!--
    function setCookie(name, value, expires, path, domain, secure)
    {
        var curCookie = name + "=" + escape(value) +
            ((expires) ? "; expires=" + expires.toGMTString() : "") +
            ((path) ? "; path=" + path : "") +
            ((domain) ? "; domain=" + domain : "") +
            ((secure) ? "; secure" : "");
        document.cookie = curCookie;
    }
    function toggleHiddenArea()
    {
        if (document.getElementById('hidden_area')) {
            var el = document.getElementById('hidden_area');
            var on_button = document.getElementById('toggle_button_on');
            var off_button = document.getElementById('toggle_button_off');

            // open
            if (el.style.display == "none") {
                el.style.display = "inline";
                on_button.style.display = 'none';
                off_button.style.display = 'inline';
                setCookie('etp_state', 'open');
            // close
            } else {
                el.style.display = "none";
                on_button.style.display = 'inline';
                off_button.style.display = 'none';
                setCookie('etp_state', 'close');
            }
        }
        
    }
    // -->
    </script>
    <span style="
        background-color: #a7adc7;
        border:2px solid;
        border-color:#d7dde7 #676d77 #676d77 #d7dde7;
    ">
    <a href="javascript:toggleHiddenArea();"
        title="Display Hidden Area"
        id="toggle_button_on"
        style="font-family:fixed;
            font-weight: 700;
            font-size: 14px;
            color:#000;
            text-decoration:none;$open_button_state;">&nbsp;&nbsp;+</a>
    <a href="javascript:toggleHiddenArea();"
        title="Hide Hidden Area"
        id="toggle_button_off"
        style="font-family:fixed;
            font-weight:700;
            font-size:14px;
            color:#000;
            text-decoration:none;$close_button_state;">&nbsp;&mdash;</a>
    </span>
    <!-- etp end nofeed -->
EOF;


$TOKENS['hidden_area_button_bluebutton']['token'] = '__HIDDEN_AREA_BUTTON_STYLE=BLUEBUTTON__';
$TOKENS['hidden_area_button_bluebutton']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <script type="text/javascript">
    <!--
    function setCookie(name, value, expires, path, domain, secure)
    {
        var curCookie = name + "=" + escape(value) +
            ((expires) ? "; expires=" + expires.toGMTString() : "") +
            ((path) ? "; path=" + path : "") +
            ((domain) ? "; domain=" + domain : "") +
            ((secure) ? "; secure" : "");
        document.cookie = curCookie;
    }
    function toggleHiddenArea()
    {
        if (document.getElementById('hidden_area')) {
            var el = document.getElementById('hidden_area');
            var on_button = document.getElementById('toggle_button_on');
            var off_button = document.getElementById('toggle_button_off');

            // open
            if (el.style.display == "none") {
                el.style.display = "inline";
                on_button.style.display = 'none';
                off_button.style.display = 'inline';
                setCookie('etp_state', 'open');
            // close
            } else {
                el.style.display = "none";
                on_button.style.display = 'inline';
                off_button.style.display = 'none';
                setCookie('etp_state', 'close');
            }
        }
        
    }
    // -->
    </script>

    <form>
    <input type="button" id="toggle_button_on" onclick="toggleHiddenArea();return false;" value="Show"
    style="width:100px; 
    $open_button_state;
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#000000;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;" />
    </form>

    <form>
    <input type="button" id="toggle_button_off" onclick="toggleHiddenArea();return false;" value="Hide"
    style="width:100px; 
    $close_button_state;
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#000000;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;" />
    </form>
    <!-- etp end nofeed -->
EOF;



// temp variables to get the name of the rss feed.
// do not change
$t = pathinfo($_SERVER['PHP_SELF']);
$fname = $CFG['server_name'] . $t['dirname'] . '/' . substr($t['basename'], 0, strlen($t['basename']) - strlen($t['extension']) - 1);

$rss_diff = $fname . '_diff.xml';
$rss = $fname . '.xml';

// RSS Feed token
$rss_title = $RSS['title_diff'];
$TOKENS['rss_diff_feed']['token'] = '__RSS_DIFF_FEED__';
$TOKENS['rss_diff_feed']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <a href="http://$rss_diff" id="rss_diff_feed"
    style="font-family: arial, helvetica, sans-serif;
    height:12px;
    font-size: 8px;
    font-weight: bold;
    text-decoration: none;
    border: 1px solid #000;
    color: #fff;
    background-color: #f60;
    padding: 2px;
    margin: 0px;">&nbsp;RSS&nbsp;</a> $rss_title
    <!-- etp end nofeed -->
EOF;

// RSS Feed token
$rss_title = $RSS['title'];
$TOKENS['rss_feed']['token'] = '__RSS_FEED__';
$TOKENS['rss_feed']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <a href="http://$rss" id="rss_feed"
    style="font-family: arial, helvetica sans-serif;
    height:12px;
    font-size: 8px;
    font-weight: bold;
    text-decoration: none;
    border: 1px solid #000;
    color: #fff;
    background-color: #f60;
    padding: 2px;
    margin: 0px;">&nbsp;RSS&nbsp;</a> $rss_title
    <!-- etp end nofeed -->
EOF;

// RSS autodiscovery token
$TOKENS['rss_autodiscovery']['token'] = '__META_RSS_AUTODISCOVERY__';
$TOKENS['rss_autodiscovery']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <link rel="alternate" type="application/rss+xml" title="RSS" href="http://$rss" />
    <!-- etp end nofeed -->
EOF;


// these are just temp variables for use in the next heredoc
$otherself = $CFG['server_name'] . $t['dirname'] . '/' . $_SERVER['PHP_SELF'];
$title = $RSS['title'];

// RDF token for trackback auto-discovery
$TOKENS['tb_rdf']['token'] = '__TRACKBACK_RDF__';
$TOKENS['tb_rdf']['html'] = <<<EOF
    <!-- etp start nofeed -->
    <!--
    <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">
    <rdf:Description
        rdf:about="$otherself"
        dc:title="$title"
        dc:identifier="$otherself"
        trackback:ping="$otherself" />
    </rdf:RDF>
    -->
    <!-- etp end nofeed -->
EOF;

// The trackback URL
$TOKENS['tb_url']['token'] = '__TRACKBACK_URL__';
$TOKENS['tb_url']['html'] =  $CFG['protocol'] . '://' . $CFG['server_name'] . $_SERVER['PHP_SELF'];

// this expands to trackbacks received for the page
$TOKENS['trackbacks']['token'] = '__TRACKBACKS__';
$TOKENS['trackbacks']['callback'] = 'formatExtHtml';
$TOKENS['trackbacks']['args'] = array('"trackbacks"', '"EXT_TRACKBACK"', '$chunks["EXT_TRACKBACK"]');

/* PLUGIN::tokens */


/**
 * data fields which store information for this page
 *
 * These are the valid fields that can be stored in the data section
 * at the end of the script. If a field is not present here, it will
 * not be saved or retrieved.
 * @global array $VALID_DATA_FIELDS
 * @see $data_fields_labels, $html_data_fields, $meta_data_fields
 */
$VALID_DATA_FIELDS = array(
    'VERSION',
    'PAGE_HEADER',
    'PAGE_MAIN_CONTENT',
    'PAGE_FOOTER',
    'META_EDITOR',
    'META_EDITOR_EMAIL',
    'META_EDIT_COMMENT',
    'META_PAGE_TITLE',
    'MSG_AUTH_FAILED',
    'EXT_TRACKBACK',
    'EXT_TRACKBACK_SENT',
);

array_push($VALID_DATA_FIELDS, 'EXT_COMMENTS');
array_push($VALID_DATA_FIELDS, 'EXT_UNMODERATED');
array_push($VALID_DATA_FIELDS, 'EXT_UNMOD_TB');

/**
 * labels for data fields
 *
 * Text labels that will be used in the admin pages to identify
 * which field is being edited, and in the RSS feed to indicate
 * where a change was made
 *
 * @global array $data_field_labels
 * @see $VALID_DATA_FIELDS
 */
$data_field_labels['PAGE_HEADER']           = 'Page Header';
$data_field_labels['PAGE_MAIN_CONTENT']     = 'Body Content';

$data_field_labels['EXT_COMMENTS']          = 'Comments';

$data_field_labels['PAGE_FOOTER']           = 'Page Footer';
$data_field_labels['META_EDITOR']           = 'Editor';
$data_field_labels['META_EDITOR_EMAIL']     = 'Editor Email';
$data_field_labels['META_EDIT_COMMENT']     = 'Editor Comment';
$data_field_labels['META_PAGE_TITLE']       = 'Page Title';
$data_field_labels['MSG_AUTH_FAILED']       = 'Message for failed logins';
$data_field_labels['EXT_TRACKBACK']         = 'Trackbacks';
$data_field_labels['EXT_TRACKBACK_SENT']    = 'Trackbacks Sent';

/**
 * shortcut for referencing page html data fields
 *
 * This array must contain the names of all data fields
 * which should be displayed on the page.
 * <em>These must be in the order you want them to appear on the page.</em>
 * @global array $html_data_fields
 * @see $VALID_DATA_FIELDS, $meta_data_fields
 */
$html_data_fields = array(
    'PAGE_HEADER',
    'PAGE_MAIN_CONTENT',
);

array_push($html_data_fields, 'EXT_COMMENTS');

// array_push($html_data_fields, 'EXT_UNMODERATED');

array_push($html_data_fields, 'EXT_TRACKBACK');

array_push($html_data_fields, 'PAGE_FOOTER');

/**
 * shortcut for referencing page meta-data fields
 *
 * This array must contain the names of all page meta-data fields.
 * @global array $meta_data_fields
 * @see $VALID_DATA_FIELDS, $html_data_fields
 */
$meta_data_fields = array(
    'META_EDITOR',
    'META_EDITOR_EMAIL',
    'META_EDIT_COMMENT',
    'META_PAGE_TITLE',
);

/**
 * shortcut for referencing trackback data fields
 *
 * This array must contain the names of all data fields
 * @global array $trackback_data_fields
 * @see $VALID_DATA_FIELDS
 */
$trackback_data_fields = array(
    'EXT_TRACKBACK',
    'EXT_TRACKBACK_SENT',
);

/**
 * shortcut for referencing which data fields to diff.
 *
 * This is used to create the diffs for the view history page
 * and for creating the RSS feed.
 * <em>These must be in the order you want them to appear in the feed.</em>
 * @global array $diff_data_fields
 * @see $VALID_DATA_FIELDS
 */
$diff_data_fields = array(
    'PAGE_HEADER',
    'PAGE_MAIN_CONTENT',
    'PAGE_FOOTER',
    'EXT_COMMENTS',
    'EXT_TRACKBACK',
);

/**
 * which lines should be included in diffs
 *
 * Diffs can include lines that occur in file A, file B, or in all files.
 * 'a' will show lines that are only in the first file
 * 'b' will show lines that are only in the second file
 * 'all' will show lines that are in both files (that is, unchanged files)
 */
$diff_files = array('a', 'b');

/**
 * shortcut for referencing message data fields
 *
 * This array must contain the names of all message data fields
 * which should be displayed on the page.
 * <em>These must be in the order you want them to appear on the page.</em>
 * @global array $message_data_fields
 * @see $VALID_DATA_FIELDS
 */
$message_data_fields = array(
    'MSG_AUTH_FAILED',
);

// do not remove this comment!
/* CONFIGURATION */
/* ETP_FOOT */
/**
 * Begin Functions
 */

/**
 * Check if a user exists
 *
 * Used in installer
 * @param array $users User accounts
 * @param string $username Username to check
 * @return mixed username or false
 */
function userExists($users, $username)
{
    foreach ($users as $k => $user) {
        if ($user['name'] == $username) {
            return $k;
        }
    }
    return false;
}

/**
 * Modify the value of a scalar assignement in a string of code
 *
 * Takes a reference to the string of code to modify, and a variable
 * number of arguments. The last of these is the value to assign. Quotes
 * must be included if desired in the resulting code.
 * If three parameters are passed in, the assignment is treated as a
 * scalar. If more are passed in, it's treated as an assignment to
 * an array element, eg.
 * <code>
 * setScalar($install_variables, 'guest_access_allowed', 'true');
 * </code>
 * will result in:
 * <code>
 * $guest_access_allowed = true;
 * </code>
 * and
 * <code>
 * setScalar($install_variables, 'CFG', 'admin_email', '"foo@example.com"');
 * </code>
 * will result in:
 * <code>
 * $CFG['admin_email'] = "foo@example.com";
 * </code>
 * 
 * Note that the variable must exist in the code fragment being
 * modified, it will not be created. Only the first such assignement
 * will be modified.
 * 
 * Used in installer.
 *
 * @param reference $mod_section Reference to the string to modify
 * @param string Name of the variable to assign to
 * @param [string,string...] Array keys
 * @param string Value to assign
 * @return void
 * @see addScalar,delScalar
 */
function setScalar(&$mod_section)
{
    $args = func_get_args();

    $value = array_pop($args);

    // if there's more than one left, this is an
    // assignment to a value in an array
    $var_name = $args[1];
    if (count($args) > 2) {
        for ($i = 2; $i < count($args); $i++) {
            $var_name .= '\\[\'' . $args[$i] . '\'\\]';
        }
    }
    
    $pattern = '/(\$' . $var_name . ')\s?=\s?(.*);/';
    $replace = "$1 = $value;";
    $mod_section = preg_replace($pattern, $replace, $mod_section, 1);

}

/**
 * Create a scalar assignement in a string of code
 *
 * Takes a reference to the string of code to modify, and a variable
 * number of arguments. The last of these is the value to assign. Quotes
 * must be included if desired in the resulting code.
 * If three parameters are passed in, the assignment is treated as a
 * scalar. If more are passed in, it's treated as an assignment to
 * an array element, eg.
 * <code>
 * setScalar($install_variables, 'guest_access_allowed', 'true');
 * </code>
 * will result in:
 * <code>
 * $guest_access_allowed = true;
 * </code>
 * and
 * <code>
 * setScalar($install_variables, 'CFG', 'admin_email', '"foo@example.com"');
 * </code>
 * will result in:
 * <code>
 * $CFG['admin_email'] = "foo@example.com";
 * </code>
 * 
 * The assignement will be added to the end of the code string. 
 *
 * Used in installer.
 * @param reference $mod_section Reference to the string to modify
 * @param string Name of the variable to assign to
 * @param [string,string...] Array keys
 * @param string Value to assign
 * @return void
 * @see setScalar,delScalar
 */
function addScalar(&$mod_section)
{
    $args = func_get_args();

    $value = array_pop($args);

    // if there's more than two left, this is an
    // assignment to a value in an array
    $var_name = $args[1];
    if (count($args) > 2) {
        for ($i = 2; $i < count($args); $i++) {
            $var_name .= '[' . $args[$i] . ']';
        }
    }
    
    $new_var = '$' . $var_name . ' = ' . $value . ';';
    $mod_section .= "\n" . $new_var;
}

/**
 * Remove a scalar assignement from a string of code
 *
 * Removes a line of code with a scalar assignment from a string of code.
 * Eg., To remove the line:
 * <code>
 * $users["username"]["name"] = "JohnSmith";
 * </code>
 * use this call
 * <code>
 * delScalar($user_accounts, 'users', 'username', '"name"');
 * </code>
 * delScalar() can be used to remove a simple scalar asssignement, or
 * an assignment to a specific array element.
 *
 * Used in installer
 * @param reference $mod_section Reference to the string to modify
 * @param string Name of the variable to delete
 * @param [string,string...] Array keys
 * @return void
 * @see addScalar,setScalar
 */
function delScalar(&$mod_section)
{
    $args = func_get_args();
    $var_name = $args[1];
    if (count($args) > 2) {
        for ($i = 2; $i < count($args); $i++) {
            $var_name .= '\\[' . $args[$i] . '\\]';
        }
    }
    
    $pattern = '/\$' . $var_name . '\s?=\s?.*?;/';
    $mod_section = preg_replace($pattern, '', $mod_section);
}


/**
 * check username, password
 *
 * Sets session variable for authenticated user on success,
 * clears session on failure.
 * @param array $users User accounts
 * @param string $auth_user Claimed username
 * @param string $auth_pass Password
 * @return boolean
*/

function doLogin($users, $auth_user, $auth_pass)
{
    foreach($users as $user) {
        // check if user is authorized
        if ($user['name'] == $auth_user
            && $user['password'] ==  $auth_pass) {

            // we have a match!
            return true;
        }
    }
    doLogout();
    return false;
}

/**
 * clear user session
*/
function doLogout() {
    session_unset();
    session_destroy();
}

/**
 * return authorized user
 *
 * Checks username and password, returns user information
 * if user exists.
 * @param array $users User accounts
 * @param string $auth_user Claimed username
 * @return false,array false on fail, or this user's information on success
*/

function getUser($users, $auth_user)
{
    foreach($users as $user) {
        if ($user['name'] == $auth_user) {
            return $user;
        }
    }
    return false;
}

/**
 * is this user authorized for this action?
 *
 * @param array $auth_actions User groups and their allowed actions
 * @param array $users User accounts
 * @param string $action Request action
 * @return boolean
 */
function isAuth($auth_actions, $users, $action)
{
    $is_auth = false;
    if (in_array('all', $auth_actions[$users['group']])
        || in_array($action, $auth_actions[$users['group']])) {

        $is_auth = true;
    }
    return $is_auth;
}

/**
 * is this a history file?
 *
 * @param string $file_name File name to check
 * @return boolean,string false if this isn't a history file,
 * name of the file if it is
 */
function isHistoryFile($file_name)
{
    // check to make sure we are we aren't editing the history file.. 
    $pattern = "/\.\d\d\d\d\d\b/";
    preg_match($pattern, $file_name, $matches);
    if (isset($matches[0])) {
        $original_filename = preg_replace($pattern, "", $file_name);
        return $original_filename;
    }
    return false;
}

/**
 * is this a preview file?
 *
 * @param string $file_name File name to check
 * @param string $username Name of current user
 * @return boolean,string false if this isn't a preview file,
 * name of the file if it is
 */
function isPreviewFile($file_name, $username)
{
    // check to make sure we are we aren't editing a preview file
    $pattern = "/\.preview\.$username/";
    preg_match($pattern, $file_name, $matches);
    if (isset($matches[0])) {
        $original_filename = preg_replace($pattern, "", $file_name);
        return $original_filename;
    }
    return false;
}

/**
 * check for a current lock
 *
 * @param array Current file information array
 * @return boolean,array false if no lock found, lock data
 * if there is one.
 * lock data is an assoc. array with the keys:
 * <pre>
 * user:       who locked the file
 * created:    UNIX timestamp of creation date
 * </pre>
 */
function getLockFile($file)
{
    global $CFG, $LOCK_VARS;

    $time = time();
    // get the name of the lockfile
    $lockfile = $file['name'] . '.' . $CFG['lockext'];

    $lock_data = false;
    if (file_exists($lockfile)) {

        // there is a lock file in place.. let's see if it's current
        $contents = implode('', file($lockfile));
        list($user, $created, $lock_vars) = explode('|', $contents, 3);

        if ($created > ($time - $CFG['locktime'])) {
            $lock_data['user'] = $user;
            $lock_data['created'] = $created;
            $lock_data['raw_lock_vars'] = $lock_vars;
            // if ($lock_vars) {
                $pairs = explode('&', $lock_vars);
                foreach ($pairs as $entry) {
                    list($key, $value) = explode("=", $entry, 2);
                    if (!array_key_exists($key, $lock_data)) {
                        $lock_data[$key] = $value;
                        $LOCK_VARS[$key] = $value;
                    }
                }
                // }
        }
    }

    return $lock_data;
}

/**
 * create lock file
 *
 * @param array Current file information array
 * @param string $user Current user name
 * @return int,false Creation date as UNIX timestamp, false on fail
 */
function lockFile($file, $user)
{
    global $CFG, $LOCK_VARS;
    $time = time();

    // get the name of the lockfile
    $lockfile = $file['name'] . '.' . $CFG['lockext'];

    // figure out lock vars
    $extra = "";
    if ($LOCK_VARS) {
        foreach ($LOCK_VARS as $var => $val) {
            $extra .= ($extra ? "&" : "") .
                $var . "=" . $val;
        }
    }

    $filecontents = $user . '|' . $time . '|' . $extra;

    // write a new lockfile
    $fh = fopen($lockfile, 'w');
    if (!$fh) {
        return false;
    }
    fwrite($fh, $filecontents, strlen($filecontents));
    fclose($fh);

    return $time;
}

/**
 * remove lock file
 *
 * Removes the lock file, if it still belongs to this user
 *
 * @param array Current file information array
 * @param string $user Current user name
 */
function removeLock($file, $user)
{
    global $CFG;

    $lock_data = getLockFile($file);
    // if it's our lock file remove it, otherwise do nothing
    if ($lock_data['user'] == $user) {
        // get the name of the lockfile
        $lockfile = $file['name'] . '.' . $CFG['lockext'];
        unlink($lockfile);
    }
}

/**
 * scans chunks for un-authorized tokens, auto-adds tokens
 *
 * Searches the passed in chunks for any tokens the current
 * user is not authorized to insert and removes them. Also
 * automatically adds tokens to the beginning or end of a
 * chunk as required.
 * @param array $chunks Data chunks to be processed
 * @param array $user The current user
 * @return array
 */
function processTokens($chunks, $user)
{
    global $TOKENS;

    foreach ($TOKENS as $token) {

        foreach ($chunks as $name => $text) {

            // do they have access to this token?
            // if not, we'll strip it. Tokens with auto_open
            // or auto_close set are also removed.
            // note that the user does NOT need access for
            // auto_open and auto_close. This allows us to
            // enable automatically adding these, while
            // preventing users from inserting them into the
            // submitted chunk
            if ( (isset($token['access']) && !in_array($user['group'], $token['access']))
                || (isset($token['auto_open']) || isset($token['auto_close'])) ) {

                $chunks[$name] = str_replace($token['token'], '', $text);
            }

            // is there an auto_open for this chunk?
            // only add it if there's something in this chunk to wrap it around
            if (isset($token['auto_open'])
                && $token['auto_open'] == $name
                && $chunks[$name] !== '') {
                $chunks[$name] = $token['token'] . $chunks[$name];
            }

            // is there an auto_close for this chunk?
            // only add it if there's something in this chunk to wrap it around
            if (isset($token['auto_close'])
                && $token['auto_close'] == $name
                && $chunks[$name] !== '') {
                $chunks[$name] .= $token['token'];
            }
        }
    }
    return $chunks;
}

/* *
 * remove auto tokens
 *
 * Removes defined auto tokens from page data for editing.
 * @param array $chunks Data chunks to process
 * @return array
 */
function clearAutoTokens($chunks)
{
    global $TOKENS;

    foreach ($TOKENS as $token) {
        if (isset($token['auto_open']) || isset($token['auto_close'])) {
            foreach ($chunks as $name => $text) {
                $chunks[$name] = str_replace($token['token'], '', $text);
            }
        }
    }
    return $chunks;
}

/**
 * expands tokens
 *
 * Expands any tokens found in the string to HTML.
 * @param string $string String to expand
 * @return string
 */
function expandTokens($string)
{
    global $TOKENS;

    foreach($TOKENS as $token) {
        if (isset($token['html'])) {
            $string = str_replace($token['token'], $token['html'], $string);
        } else {
            continue;
        }
    }
    return $string;
}

/**
 * given a token with a callback, returns code appropriate for eval
 *
 * @param $token A token from the $TOKENS array
 * @return $string
 */
function makeCallback($token)
{
    if (isset($token['args'])) {
        $args = implode(', ', $token['args']);
    } else {
        $args = '';
    }
    return 'return ' . $token['callback'] . '(' . $args . ');';
}

/**
* formats extended HTML chunks according to a template, creates HTML for the token
*
* @param $name Name of the chunk we're formatting.
* @param $chunk Chunk with records to format
* @return HTML to ouptut.
*/
function formatExtHtml($token, $name, $chunk)
{
    global $EXT_HTML;
    global $TOKENS;

    $out = expandTokens($EXT_HTML[$name . '.open']);

    foreach ($chunk as $label => $record) {
        $out_record = $EXT_HTML[$name];
        foreach ($record as $tag => $data) {
            $out_record = str_replace('__' . $tag . '__', $data, $out_record);
        }

        $out .= expandTokens($out_record);
    }
    $out .= expandTokens($EXT_HTML[$name . '.close']);
    return $out;
}

/**
 * finds start and end of data markers
 *
 * Returns line offset of beginning data mark
 * and offset from start of the end of the data section.
 * This does not include the marker itself! This will be the
 * beginning and end of the data.
 * @param array $file_lines Lines to examine for data markers
 * @param string $marker The marker we're looking for
 * @return array
 */
function findMarkers($file_lines, $marker)
{
    // find first occurence of data marker
    $length = count($file_lines) - 1;
    for($start = 0; $start < $length; $start++) {
        $line = trim($file_lines[$start]);
        if (preg_match('/^\/\*\s' . $marker . '\s\*\/$/', $line)) {
            break;
        }
    }

    // last occurence of data marker
    for($end = $length; $end > 0; $end--) {
        $line = trim($file_lines[$end]);
        if (preg_match('/^\/\*\s' . $marker . '\s\*\/$/', $line)) {
            break;
        }
    }

    // we don't want the data markers included
    $start++;
    return array($start, $end - $start);
}

/**
 * get line count, word count, size in bytes
 *
 * Gets the line count, word count and size of html chunks
 * passed in. Returns counts for each chunk, as well as the total
 *
 * @param string $html String to count
 * @return array
 */
function getPageInfo($html)
{

    $info = array();
    $info['total']['size'] = 0;

    $info['total']['lines'] = 0;
    $info['total']['words'] = 0;
    foreach ($html as $k => $chunk) {
        $info[$k]['size'] = strlen($chunk);
        $info['total']['size'] += strlen($chunk);

        // the last line will not have a newline. add it here.
        $info[$k]['lines'] = preg_match_all('/\n/', $chunk, $matches) + 1;
        $info['total']['lines'] += preg_match_all('/\n/', $chunk, $matches) + 1;

        // don't count HTML tags
        $chunk = strip_tags($chunk);
        $info[$k]['words'] = preg_match_all('/\b\w+?/', $chunk, $matches);
        $info['total']['words'] += preg_match_all('/\b\w+?/', $chunk, $matches);
    }
    return $info;
}

/**
 * get data block from a given file as an array of lines
 *
 * @param string $filename Name of file to retrieve data from
 * @param string $data_block The marker for the data chunk to retrieve
 * @return array Data chunk as an array of lines
 */
function &getDataLinesFromFile($filename, $data_block)
{
    $file = array();
    $file = file($filename);

    list($start, $end) = findMarkers($file, $data_block);

    // this will be all the data, including markers within the data section
    $all_data = array_slice($file, $start, $end);

    // remove carriage returns, newlines
    foreach($all_data as $k => $v) {
        $all_data[$k] = preg_replace("/[\r\n]/", '', $v);
    }
    return $all_data;
}

/**
 * extract lines of data from a full data block
 *
 * @param $all_data array Full data block
 * @param $chunk string Name of the chunk we're extracting
 * @return array Data chunk as array of lines
 */
function getChunkLines($all_data, $chunk)
{
    list($start, $end) = findMarkers($all_data, $chunk);
    $chunk_lines = array_slice($all_data, $start, $end);

    // strip protective comment markers from each line
    for($i = 0; $i < count($chunk_lines); $i++) {

        // only strip protective comments from data lines,
        // not data markers
        // the space is optional because if it was a blank line,
        // trim will have removed it
        $chunk_lines[$i] = preg_replace('/^\/\/\s?/', '', $chunk_lines[$i]);
    }
    return $chunk_lines;
}

/**
 * get data from the data block in a file
 *
 * Returns requested page data chunks, or all chunks by default.
 * Optionally html encode data before returning.
 * @param string $filename Name of file
 * @param array $chunks Optional, chunks to retrieve
 * @param boolean $encode Optional, HTML encoded
 * @return array Assoc. array keyed on chunk name
 */
function &getData($filename, $chunks = false, $encode = false)
{
    global $VALID_DATA_FIELDS;

    // we're returning all the data
    if (!$chunks) {
        $chunks = $VALID_DATA_FIELDS;
    }

    $all_data = array();
    $data = array();
    $all_data =& getDataLinesFromFile($filename, 'DATA');

    // parse out each chunk and put them into an assoc. array
    foreach ($chunks as $chunk) {
        $chunk_lines = getChunkLines($all_data, $chunk);

        // is this an extended data chunk?
        // if so, it goes into a nested array
        if (strstr($chunk, 'EXT_') === false) {
            // it's not. encode if necessary
            if ($encode) {
                $data[$chunk] = htmlspecialchars($data[$chunk]);
            }
            $data[$chunk] = implode("\n", $chunk_lines);
        } else {
            // it is an extended data chunk
            $data[$chunk] = expandChunks($chunk_lines, $encode);
        }
    }
    return $data;
}

/**
 * parse an extended data field into an array
 *
 * @param array $chunk_lines Lines to parse
 * @param boolean $encode Optional, HTML encoded
 * @return array Assoc. array keyed on tags found in lines
 */
function expandChunks($chunk_lines, $encode)
{
    $data = array();
    $data_found = false;
    $i = 0;

    foreach ($chunk_lines as $line) {

        // skip empty lines until we hit data
        // needed to make sure we don't try to parse empty chunks
        if(!$data_found && !$line) {
            continue;
        }
        $data_found = true;
    
        // is this line a tag?
        if (preg_match('/^\[\[(.*)\]\]$/', $line, $matches)) {
            $tag = $matches[1];

            // is this a new record?
            if (isset($start_tag) && $tag == $start_tag) {
                $i++;
            }
            if (empty($start_tag)) {
                $start_tag = $tag;
            }

            $data[$i][$tag] = array();
            continue;
        }

        if (empty($tag)) {
            trigger_error('Malformed extended data field. No starting tag found.', E_USER_WARNING);
        }
        array_push($data[$i][$tag], $line);
    }

    foreach ($data as $i => $record) {
        foreach($record as $k => $v) {
            $data[$i][$k] = implode("\n", $v);

            if ($encode) {
                $data[$i][$k] = htmlspecialchars($data[$k]);
            }
        }
    }
    return $data;
}

/**
 * return the code part of the script
 *
 * Returns everything up to the first DATA marker
 * @param $filename File to retrieve code from
 * @return string
 */
function getCode($filename) {
    $code_lines = array();
    $file = array();
    $file = file($filename);

    list($start, $end) = findMarkers($file, 'DATA');

    // this will be all the data, including markers within the data section
    // $start is the beginning of the data section. $start-1 is the last line of code
    $code_lines = array_slice($file, 0, $start -1);
    $code = array();
    $code = implode('', $code_lines);

    return $code;
}

/**
 * has the user changed data from the original file?
 *
 * Compares data chankes to chunks extracted from the file
 * @param array $file File information array
 * @param array $new_chunks New data to compare
 * @return boolean
 */
function dataChanged($file, $new_chunks)
{
    $changed = false;
    $cmp_chunks = array_keys($new_chunks);

    // get chunks to compare to from the most recent version
    $old_chunks =& getData($file['basename'], $cmp_chunks);

    flattenChunks($old_chunks);
    flattenChunks($new_chunks);

    // strip CR from incoming data
    foreach ($new_chunks as $k => $v) {
        $new_chunks[$k] = str_replace(chr(13), '', $v);

        // compare this chunk
        if ($new_chunks[$k] != $old_chunks[$k]) {
            return true;
        }
    }
    // if we get here, there were no changes
    return false;
}

/**
 * converts extended data chunks to one dimensional assoc arrays
 *
 * Extended data arrays will be flattened, regular data arrays
 * will remain untouched.
 * 
 * @param arrayref $chunk Data chunk to flatten
 */
function flattenChunks(&$chunks)
{
    foreach ($chunks as $label => $chunk) {
        if (is_array($chunk)) {
            // temporary string for holding new chunk data
            $t = '';
            foreach ($chunk as $i => $record) {
                foreach ($record as $k => $v) {
                    $t .= '[[' . $k . ']]' . "\n";
                    $t .= $v . "\n";
                }
            }
            $chunks[$label] = $t;
        }
    }
}

/**
 * convert an array of data chunks to a string
 *
 * Turns an array of page chunks (as would be returned by getData)
 * into a string with appropriate chunk markers and protective comments
 * @param array $chunks
 * @return string
 * @see getData()
 */
function serializePageData($chunks)
{
    $data_string = "/* DATA */";
    foreach($chunks as $label => $html) {

        // strip CR
        $html = str_replace(chr(13),'',$html);

        // add protective comments
        $html = preg_replace("/\n(.+)/", "\n// $1", $html);

        // add chunk start and end markers
        // we need to make sure we don't add any extra
        // newlines here
        $data_string .= "\n/* " . $label . " */\n// "
            .  rtrim($html)
            . "\n/* " . $label . " */\n";
    }
    $data_string .= "/* DATA */\n?>\n";
    return $data_string;
}

/**
 * backs up most recent version of the page
 *
 * @param $file File info array
 */
function backupLastVersion($file)
{
    $lastver = hivno($file);
    $lastver++;

    $new_version_num = sprintf("%05u", $lastver);

    $new_filename = $file['name'] . '.' . $new_version_num . '.' . $file['extension'];
    rename($file['basename'], $new_filename);
}

/**
 * replaces old data with new data
 *
 * @param array $page_chunks Data from last version of file
 * @param array $new_chunks New data
 * @return array
 */
function updateChunks($page_chunks, $new_chunks)
{
    global $VALID_DATA_FIELDS;

    // replace old data with new data for any chunks that exist
    // check against valid data fields in case new ones have been added
    foreach($VALID_DATA_FIELDS as $field) {

        // if the field wasn't submitted, we don't want to update it
        // $page_chunks[$field] may not be initialized yet
        if (isset($new_chunks[$field])
            && @$page_chunks[$field] != $new_chunks[$field]) {

            $page_chunks[$field] = $new_chunks[$field];
        }
    }

    return $page_chunks;
}

/**
 * trims whitespace, expands tabs
 *
 * @param array $new_chunks Data chunks to format
 * @param boolean $expand_tabs Expand tabs?
 * @param boolean $rtrim Trim whitespace at end of line?
 * @return array Formatted chunks
 */
function formatWhitespace($new_chunks, $expand_tabs, $rtrim)
{
    if ($rtrim) {
        foreach($new_chunks as $k => $v) {
            $new_chunks[$k] = preg_replace("/\s+?\n/", "\n", $v);
        }
    }

    if ($expand_tabs) {
        foreach($new_chunks as $k => $v) {
            $new_chunks[$k] = preg_replace("/\t/", '    ', $v);
        }
    }

    return $new_chunks;
}

/**
 * simple HTML cleanup
 *
 * Runs a series of regular expressions against the submitted
 * text to perform basic HTML tidy functions
 *
 * @param arrayref $html chunks to clean
 * @return arrayref
 */
function cleanHtml($chunks)
{
    $filters = array(
        "/<p>/",
        "/<\/p>/",
        "/<br>/",
        "/<br \/>/",
        "/<ul>/",
        "/\n+/s",

        "/\n*<li>(.*?)<\/li>/",
        "/<\/ul>/",
    );

    $replacements = array(
        "\n<p>\n",
        "\n</p>\n",
        "\n<br />\n",
        "\n<br />\n",
        "\n<ul>",
        "\n",

        "\n    <li>$1</li>",
        "\n</ul>\n",
    );

    $clean = array();
    foreach($chunks as $k => $v) {
        $clean[$k] = preg_replace($filters, $replacements, $v);
    }

    return $clean;
}

/**
 * deletes records from an extended data chunk
 *
 * Reads in specified extended data chunks and unsets
 * offsets specified for each chunk. Does not save the
 * modified data.
 *
 * @param array $file File info array
 * @param array $del_records Assoc array in the form:
 * <code>
 * $del_recordsd['EXT_TRACKBACK'] = array(0,2,3);
 * </code>
 * @return array Modified extended data chunk
 */
function delExtRecords($file, $del_records)
{
    $page_chunks =& getData($file['basename'], array_keys($del_records));

    foreach ($page_chunks as $chunk_name => $chunk_data) {
        foreach ($del_records[$chunk_name] as $del_index ) {
            unset($page_chunks[$chunk_name][$del_index]);
        }
    }
    return $page_chunks;
}

/**
 * save data to file
 *
 * Saves one or more data chunks, optionally to a new file.
 * If saving a new file, it will not back up the old one
 * as it will not be overwritten.
 * @param array $file File info array
 * @param array $new_chunks Data chunks to save
 * @param array $current_user Current user information
 * @param array $new_file Optional, file info array for a different
 * file to save to.
 * @param array $append_chunks Optional, list of chunks that should be
 * appended to existing data, rather than overwriting it.
 */
function saveData($file, $new_chunks, $current_user, $new_file = false, $append_chunks = false)
{
    flattenChunks($new_chunks);
    foreach($new_chunks as $k => $v) {
        $new_chunks[$k] = stripslashes($v);
    }


    // $full_page will contain the full file to be written
    // for now, we just want the code section
    $full_page = getCode($file['basename']);

    // get the old page data
    $page_chunks =& getData($file['basename']);
    flattenChunks($page_chunks);

    // these chunks will be added to existing data
    // make sure we don't lose the old info
    if ($append_chunks) {
        foreach ($append_chunks as $chunk_name) {
            $new_chunks[$chunk_name] = $page_chunks[$chunk_name] . $new_chunks[$chunk_name];
        }
    }

    $new_chunks = processTokens($new_chunks, $current_user);

    // update any changed data
    $page_chunks = updateChunks($page_chunks, $new_chunks);

    $title = getPageTitle($page_chunks);
    if ($title) {
        $page_chunks['META_PAGE_TITLE'] = getPageTitle($page_chunks);
    }

    $full_page .= serializePageData($page_chunks);

    // TODO
    // this could use some error checking to make sure
    // the backup works before writing out the new file

    // backup most recent version
    // unless we're creating a new file
    if (!$new_file) {
        backupLastVersion($file);
    }

    if ($new_file) {
        $file['basename'] = $new_file['basename'];
    }

    // write out new file
    $fh = fopen($file['basename'], 'w');
    fwrite($fh, $full_page, strlen($full_page));
    fclose($fh);

    return true;
}

/**
 * Find the page title in data chunks
 *
 * @param reference $data_chunks reference to an array of data
 * chunks to scan for the title
 * @return mixed title as string if found, false if not found
 */
function getPageTitle(&$page_chunks)
{
    // find our title
    foreach ($page_chunks as $name => $chunk) {
        if (preg_match('/<title>(.*?)<\/title>/si', $chunk, $matches)) {
            return $matches[1];
        }
    }
    return false;
}

/**
 * remove images from server
 *
 * @param array $file File info array
 * @param array $imgs Images to delete
 * @return int Count of images deleted
 */
function delImages($file, $imgs)
{
    $del_count = 0;
    foreach($imgs as $img) {
        if (file_exists($file['imagedir'].'/'.$img) ) {
            unlink($file['imagedir'].'/'.$img);
            $del_count++;
        }
    }

    $existing_images = 0;
    // delete the image directory, too if empty..
    if ($h = opendir('./'.$file['imagedir']) ) {
        while (false !== ($v = readdir($h))) {
            if ($v != '.' && $v != '..') {
                $existing_images++;
            }
        }
        closedir($h);
    }

    if (!$existing_images){
        rmdir($file['imagedir']);
    }

    return $del_count;
}

/**
 * retrieve list of images for this page
 *
 * @param array $file File info array
 * @return array
 */
function getImages($file)
{
    $images = array();
    if (is_dir($file['imagedir'])) {
        if ($h = opendir('./' . $file['imagedir'])) {

            while(false !== ($v = readdir($h)) ) {
                if ($v != '.' && $v != '..') {
                    array_push($images, $v); 
                }
            }
            closedir($h);
        }
    }
    return $images;
}

/**
 * save images
 *
 * perform checks on uploaded images and upload them if they pass,
 * returns error messages if not.
 * @param array $file File info array
 * @return array Status messages
 */

function saveImages($file, $size_limit = true)
{

    global $CFG;

    // success/fail status for each image
    // $upldoad_status[$filename]['status'] = false;
    // $upldoad_status[$filename]['msg'] = 'file exists';
    $upload_status = array();

    // track the files that are ok to save
    $valid_files = array();

    // tests for allowed files
    while (list($key, $val) = each($_FILES)) {
        if (empty($_FILES[$key]['name']) || !$_FILES[$key]['name']) {
            continue;
        }

        // check that it's a valid image file
        $filetype = $_FILES[$key]['type'];
        if ($filetype != "image/pjpeg"
            && $filetype != "image/jpeg"
            && $filetype != "image/gif"
            && $filetype != "image/x-png"
            && $filetype != "image/png") {

            $upload_status[$key]['name'] = $_FILES[$key]['name'];
            $upload_status[$key]['status'] = false;
            $upload_status[$key]['msg'] = 'invalid file type';
            continue;
        }

        // check that it's not over the max size
        if ($size_limit) {
            if ($_FILES[$key]['size'] > $CFG['max_image_size']) {
                $upload_status[$key]['name'] = $_FILES[$key]['name'];
                $upload_status[$key]['status'] = false;
                $upload_status[$key]['msg'] = 'file to large';
                continue;
            }
        }

        // does a file with this name already exist?
        if (file_exists($file['imagedir'] . '/' . $_FILES[$key]['name'])) {
            $upload_status[$key]['name'] = $_FILES[$key]['name'];
            $upload_status[$key]['status'] = false;
            $upload_status[$key]['msg'] = 'file already exists on server';
            continue;
        }

        // if we're here, it's passed the tests.
        $valid_files[$key] = $_FILES[$key];
    }

    // TODO
    // could use some error checking
    // do we need to create an images directory?
    if (!file_exists($file['imagedir'])) {

        // need to create the images directory
        mkdir($file['imagedir'], 0777);

        // chmod the dir just in case
        chmod($file['imagedir'], 0777);
    }

    // move the valid files
    foreach($valid_files as $k => $img) {
        $uploadfile = $file['imagedir'] . '/' . $img['name'];

        if (!move_uploaded_file($img['tmp_name'], $uploadfile)) {
            $upload_status[$k]['name'] = $_FILES[$k]['name'];
            $upload_status[$k]['status'] = false;
            $upload_status[$k]['msg'] = 'unknown error';

        // upload successful
        } else {
            // make sure we can read the file
            chmod($file['imagedir'] . '/' . $img['name'], 0644);
            $upload_status[$k]['name'] = $_FILES[$k]['name'];
            $upload_status[$k]['status'] = true;
        }
    }
    return $upload_status;
}

/**
 * deletes history files
 *
 * @param array $del_files Files to delete
 * @return boolean,array List of files deleted, false if none
 */
function delHistory($del_files)
{
    $success = array();
    foreach($del_files as $file) {
        if (file_exists($file)){
            unlink($file);
            array_push($success, $file);
        }
    }
    return count($success) ? $success : false;
}

/** strip everything not within body tags
 *
 * Used by writeRss() to remove anything that's
 * not contained within body tags.
 * @param array $data Array of lines to examine.
 * This must be the diff_info array returned by getDiffs()
 * returned by getDiffs();
 * @return array
 */
function stripNonBodyLines($lines)
{
    // if these lines have an opening tag in it, find it
    $body_start = false;
    $body_start_index = 0;
    foreach ($lines as $k => $v) {
        if (preg_match('/<body/i', $v['line'])) {
            $body_start = true;
            break;
        }
        $body_start_index++;
    }

    // if these lines have a closing tag, find it
    $body_end = false;
    $body_end_index = 0;
    foreach ($lines as $k => $v) {
        if (preg_match('/<\/body>/i', $v['line'])) {
            $body_end = true;
            break;
        }
        $body_end_index++;
    }

    // if we found an opening tag remove any content on the line before it
    if ($body_start) {
        $lines[$body_start_index]['line'] = preg_replace('/.*?<body.*?>?/', '', $lines[$body_start_index]['line']);

        // if there's nothing left on the <body> line, we'll want to strip it
        if (!$lines[$body_start_index]['line']) {
            $body_end_index++;
        }
    } else {
        $body_start_index = 0;
    }

    // if we found a closing tag remove any content on the line after it
    if ($body_end) {
        $lines[$body_end_index]['line'] = preg_replace('/<\/body>.*/', '', $lines[$body_end_index]['line']);

        // if there's nothing left on the </body> line, we'll want to strip it
        if (!$lines[$body_end_index]['line']) {
            $body_end_index--;
        }
    } else {
        $body_end_index = count($lines);
    }

    // get the lines between
    return array_slice($lines, $body_start_index, $body_end_index);
}

/**
 * write RSS feed
 *
 * writeRSS() will only diff content found between the opening
 * and closing body tags of the document. Extended data fields
 * will be expanded.
 * @param array $file File info array
 * @param array $diffs Diffs to write to the feed
 * @param array $rss_data_fields Data fields to write to the feed
 * @param array $meta_data_fields Data fields with page meta information
 * @param array $data_field_labels Used in item titles to
 * identify what part of the page changed
 * @param boolean $body_only Defaults to false. If true, will
 * strip everything that's not contained within body tags
 * @return boolean True on success, false on failure
 */
function writeRss($file, $diffs, $rss_data_fields, $meta_data_fields, $data_field_labels, $body_only = false)
{
    global $CFG;
    global $RSS;
    global $TOKENS;

    $fh = fopen($file['rss_diff'], 'w');
    if (!$fh) {
        return false;
    }

    // create rss feed
    $feed = '<rss version="2.0" '
        . 'xmlns:content="http://purl.org/rss/1.0/modules/content/" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/">'
        .  '<channel>';

    $html_feed = $feed;

    foreach($RSS as $tag => $value) {
        if ($value) {
            // skip any tag ending in '_diff'. we'll handle these explictly below
            if (substr($tag, -5) == '_diff') {
                continue;
            }
            // don't include these two
            if ($tag == 'feed_file' || $tag == 'diff_feed_file') {
                continue;
            }

            // diff feed may have different values. key will
            // always have '_diff' appended
            if (isset($RSS[$tag . '_diff'])) {
                $feed .="\n" . '<' . $tag . '>' . $RSS[$tag . '_diff'] . '</' . $tag . '>' . "\n";
                $html_feed .="\n" . '<' . $tag . '>' . $value . '</' . $tag . '>' . "\n";
            } else {
                $feed .="\n" . '<' . $tag . '>' . $value . '</' . $tag . '>' . "\n";
                $html_feed .="\n" . '<' . $tag . '>' . $value . '</' . $tag . '>' . "\n";
            }
        }
    }

    $current_data =& getData($file['basename'], $rss_data_fields);

    $chunks =& getData($file['basename']);

    // we need to make sure our callback tokens our expanded
    foreach ($chunks as $name => $chunk) {
        // set the 'html' element for tokens with callbacks
        foreach ($TOKENS as $k => $token) {
            if (isset($token['eval'])) {
                $TOKENS[$k]['html'] = eval($token['eval']);
            }
        }
    }

    flattenChunks($current_data);

    $meta_data =& getData($file['basename'], $meta_data_fields);
    $meta_data['unix_mtime'] = filemtime($file['basename']);

    // start item, set item title, description, pubDate
    $feed .= "\n<item>\n";
    $feed .= "<title>Current";

    $html_feed .= "\n<item>\n";
    $html_feed .= "<title>Current";

    if (isset($meta_data['META_PAGE_TITLE']) && $meta_data['META_PAGE_TITLE']) {
        $feed .= ': ' . $meta_data['META_PAGE_TITLE'];
        $html_feed .= ': ' . $meta_data['META_PAGE_TITLE'];
    }
    $feed .= "</title>";
    $html_feed .= "</title>";

    if (isset($meta_data['META_EDIT_COMMENT']) && $meta_data['META_EDIT_COMMENT']) {
        $feed .= "<description>" . $meta_data['META_EDIT_COMMENT'] .  "</description>\n";
        $feed .= "<dc:subject>" . $meta_data['META_EDIT_COMMENT'] .  "</dc:subject>\n";

        $html_feed .= "<description>" . $meta_data['META_EDIT_COMMENT'] .  "</description>\n";
        $html_feed .= "<dc:subject>" . $meta_data['META_EDIT_COMMENT'] .  "</dc:subject>\n";
    }

    if (isset($meta_data['META_EDITOR_EMAIL']) && $meta_data['META_EDITOR_EMAIL']) {
        $feed .= "<author>" . $meta_data['META_EDITOR_EMAIL'];
        $html_feed .= "<author>" . $meta_data['META_EDITOR_EMAIL'];

        if (isset($meta_data['META_EDITOR']) && $meta_data['META_EDITOR']) {
            $feed .= ' (' . $meta_data['META_EDITOR'] . ')';
            $html_feed .= ' (' . $meta_data['META_EDITOR'] . ')';
        }
        $feed .= "</author>\n";
        $html_feed .= "</author>\n";
    }

    if (isset($meta_data['unix_mtime']) && $meta_data['unix_mtime']) {
        $feed .= '<pubDate>' . gmdate($CFG['rss_date_format'], $meta_data['unix_mtime']+1). "</pubDate>\n";
        $html_feed .= '<pubDate>' . gmdate($CFG['rss_date_format'], $meta_data['unix_mtime']). "</pubDate>\n";
    }

    $feed .= '<guid isPermaLink="false">0';
    $feed .= time();
    $feed .= '</guid>' . "\n";

    if (!$meta_data['unix_mtime']) {
        $meta_data['unix_mtime'] = time();
    }
    $html_feed .= "<guid isPermaLink=\"false\">" . $meta_data['unix_mtime'] . "</guid>\n";
   
    $feed .="<content:encoded><![CDATA[\n";
    $html_feed .="<content:encoded><![CDATA[\n";


    $feed .= '<table border="0" cellspacing="5" cellpadding="0">';
    if (isset($meta_data['META_EDIT_COMMENT']) && $meta_data['META_EDIT_COMMENT']) {
        $feed .= '<tr><td colspan="2">' . $meta_data['META_EDIT_COMMENT'] .  "</td></tr>\n";
    }

    if (isset($meta_data['META_EDITOR_EMAIL']) && $meta_data['META_EDITOR_EMAIL']) {
        $feed .= '<tr><td>Edited by:</td><td>' . $meta_data['META_EDITOR_EMAIL'];

        if (isset($meta_data['META_EDITOR']) && $meta_data['META_EDITOR']) {
            $feed .= ' (' . $meta_data['META_EDITOR'] . ')';
        }
        $feed .= "</td></tr>\n";
    }

    if (isset($meta_data['unix_mtime']) && $meta_data['unix_mtime']) {
        $feed .= '<tr><td>Modified:</td><td>' . gmdate($CFG['rss_date_format'], $meta_data['unix_mtime']). "</td></tr>\n";
    }
    $feed .= "</table>\n";


    foreach ($current_data as $name => $chunk) {

        // strip anyting before <body> or after </body>
        $chunk = preg_replace('/.*?<body.*?>/is', '', $chunk);
        $chunk = preg_replace('/<\/body>.*/is', '', $chunk);

        // html feed
        // skip extended data chunks. these
        // must be included with tokens
        if ($chunk && strstr($name, 'EXT_')===false) {
            $t = expandTokens($chunk);
            // remove anything in <script> tags
            $t = preg_replace('/<script.*?>.*?<\/script>/is', '', $t);

            $html_feed .= $t;
        }

        if ($chunk) {
            // special formatting for extended data chunks
            if (strstr($name, 'EXT_')) {

                // we'll need this later so we can add some whitespace
                // at the beginning of each record
                preg_match('/\[\[(.+?)\]\]/s', $chunk, $matches);
                $first_tag[$name] = $matches[1];

                $feed .= '<br /><br /><table border="0" cellspacing="0" cellpadding="0" width="100%" bgcolor="#cccccc"><tr><td><b>' . $data_field_labels[$name] . '</b></td></tr></table>';
                $chunk = htmlspecialchars($chunk);

                $chunk = preg_replace("/\[\[(" . $first_tag[$name] . ")\]\]/s", "<br />[[$1]]", $chunk);
                $chunk = preg_replace('/\[\[(.*?)\]\]/s', "<b>$1:</b>", $chunk);
                $feed .= nl2br($chunk);
            } else {
                $feed .= nl2br(htmlspecialchars($chunk));
            }
            $feed .= '<br />';
        }
    }

    // close current
    $feed .="]]></content:encoded></item>\n";

    // a few convenience variables...
    $bg_a = $CFG['rss_diff_bg_a'];
    $font_a = $CFG['rss_diff_font_a'];

    $bg_b = $CFG['rss_diff_bg_b'];
    $font_b = $CFG['rss_diff_font_b'];

    $bg_both = $CFG['rss_diff_bg_both'];
    $font_both = $CFG['rss_diff_font_both'];

    // create the individual items
    foreach ($diffs as $diff) {

        // start item, set item title, description, pubDate
        $feed .= "\n<item>\n";

        if (isset($diff['META_PAGE_TITLE']) && $diff['META_PAGE_TITLE']) {
            $feed .= "<title>" . $diff['META_PAGE_TITLE'] . ': ' . $diff['file_a'] . "</title>\n";
        } else {
            $feed .= "<title>No Title: " . ': ' . $diff['file_a'] . "</title>\n";
        }
        if (isset($diff['META_EDIT_COMMENT']) && $diff['META_EDIT_COMMENT']) {
            $feed .= "<description>" . $diff['META_EDIT_COMMENT'] .  "</description>\n";
            $feed .= "<dc:subject>" . $diff['META_EDIT_COMMENT'] .  "</dc:subject>\n";
        }

        if (isset($diff['META_EDITOR_EMAIL']) && $diff['META_EDITOR_EMAIL']) {
            $feed .= "<author>" . $diff['META_EDITOR_EMAIL'];

            if (isset($diff['META_EDITOR']) && $diff['META_EDITOR']) {
                $feed .= ' (' . $diff['META_EDITOR'] . ')';
            }
            $feed .= "</author>\n";
        }

        if (isset($diff['unix_mtime']) && $diff['unix_mtime']) {
            $feed .= '<pubDate>' . gmdate($CFG['rss_date_format'], $diff['unix_mtime']). "</pubDate>\n";
        }

        $feed .= '<guid isPermaLink="false">' . $diff['unix_mtime']. "</guid>\n";
       
        // start content
        $feed .="<content:encoded><![CDATA[\n<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">";


        $feed .= '<tr><td><table border="0" cellspacing="5" cellpadding="0">';
        if (isset($diff['META_EDIT_COMMENT']) && $diff['META_EDIT_COMMENT']) {
            $feed .= "<tr><td colspan=\"2\">" . $diff['META_EDIT_COMMENT'] .  "</td></tr>\n";
        }

        if (isset($diff['META_EDITOR_EMAIL']) && $diff['META_EDITOR_EMAIL']) {
            $feed .= "<tr><td>Edited by:</td><td>" . $diff['META_EDITOR_EMAIL'];

            if (isset($diff['META_EDITOR']) && $diff['META_EDITOR']) {
                $feed .= ' (' . $diff['META_EDITOR'] . ')';
            }
            $feed .= "</td></tr>\n";
        }

        if (isset($diff['unix_mtime']) && $diff['unix_mtime']) {
            $feed .= '<tr><td>Modified:</td><td>' . gmdate($CFG['rss_date_format'], $diff['unix_mtime']). "</td></tr>\n";
        }
        $feed .= "</table></td></tr>\n";

        $feed .= '<tr><td><table border="0" cellspacing="5" cellpadding="0"><tr><td bgcolor="' . $bg_a . '">';
        $feed .= '<font ' . $font_a . '>Added in ' . $diff['file_a'] . '</font>';
        $feed .= '</td>';

        $feed .= '<td bgcolor="' . $bg_b . '">';
        $feed .= '<font ' . $font_b . '>Removed from  ' . $diff['file_b'] . '</font>';
        $feed .= "</td></tr></table></td></tr>\n";

        foreach ($diff['diffs'] as $chunk_name => $diff_info) {

            $in_ext = false;

            if (strstr($chunk_name, 'EXT_')) {
                $feed .= '<tr><td>&nbsp;</td></tr>';
                $feed .= '<tr><td bgcolor="#cccccc"><b>' . $data_field_labels[$chunk_name] . '</b></td></tr>';
                $in_ext = true;
            }

            $diff_info = stripNonBodyLines($diff_info);

            if (count($diff_info)) {

                foreach ($diff_info as $diff_line) {
                    if ($diff_line['file'] == 'a') :
                        $bg = $bg_a;
                        $font = $font_a;

                    elseif ($diff_line['file'] == 'b') :
                        $bg = $bg_b;
                        $font = $font_b;

                    else:
                        $bg = $bg_both;
                        $font = $font_both;

                    endif;

                    $diff_line['line'] = htmlspecialchars($diff_line['line']);

                    // different formatting for extended data chunks
                    if ($in_ext) {

                        // we'll use this to see if this line is a record identifier or not
                        $t = $diff_line['line'];

                        if (isset($first_tag[$chunk_name])) {
                            $diff_line['line'] = preg_replace("/\[\[($first_tag[$chunk_name])\]\]/s", "<b>$1:</b>", $diff_line['line']);

                            // has the line changed? if it has, this is a new record
                            // add some space
                            if ($diff_line['line'] != $t) {
                                $feed .= '<tr><td>&nbsp;</td></tr>';
                            }

                        }
                        $diff_line['line'] = preg_replace('/^\[\[(.+?)\]\]/s', "<b>$1:</b>", $diff_line['line']);

                        $feed .= '<tr><td bgcolor="' . $bg . '"><font ' . $font . '>' . $diff_line['line'] . "</font></td></tr>\n";

                    } else {
                        $diff_line['line'] = preg_replace('/^\[\[(.+?)\]\]/s', "<b>$1:</b>", $diff_line['line']);
                        $feed .= '<tr><td bgcolor="' . $bg . '"><font ' . $font . '>' . $diff_line['line'] . "</font></td></tr>\n";
                    }
                }
            }
        }

        // close item
        $feed .="</table>]]></content:encoded>\n</item>\n";
    }
    $feed .= "\n</channel>\n</rss>";

    fwrite($fh, $feed, strlen($feed));
    fclose($fh);

    $html_feed .="]]></content:encoded></item>\n";
    $html_feed .= "\n</channel>\n</rss>";

    // strip sections we don't want in the feed
    $html_feed = preg_replace('/<\!--\setp\sstart\snofeed\s-->.*?<\!--\setp\send\snofeed\s-->/is', '', $html_feed);

    $fh_html = fopen($file['rss'], 'w');
    fwrite($fh_html, $html_feed, strlen($html_feed));
    fclose($fh_html);

    return true;
}

/**
 * adds a comment
 *
 * @param array $file File info array
 * @param array $current_user Current user info array
 * @param array $comment Comment with the keys
 * 'name' and 'comment'
 */
function saveComment($file, $current_user, $comment)
{
    GLOBAL $CFG;
    $dest = 'EXT_COMMENTS';
    if ($CFG['moderate_comments']) {
        // We only moderate if the user is the guest user.
        if ($current_user['name'] == 'guest') {
            $dest = 'EXT_UNMODERATED';
        }
    }
    $data[$dest][0] = $comment;

    $comment['dest'] = $dest;
    $data['META_EDITOR'] = $comment['name'];
    $data['META_EDITOR_EMAIL'] = $comment['email'];
    $data['META_EDIT_COMMENT'] = 'Comment added';
    saveData($file, $data, $current_user, false, array($dest));
}

/**
 * records trackback
 *
 * @param array $file File info array
 * @param array $current_user Current user info array
 * @param array $tb_info Trackback data, with the keys
 * 'title', 'url', 'excerpt' and 'blog_name'
 * @return false,string Returns False on success, an error message on fail.
 */
function registerTrackback($file, $current_user, $tb_info)
{
    GLOBAL $CFG;
    $dest = 'EXT_TRACKBACK';
    if ($CFG['moderate_trackbacks']) {
        // If enabled, we moderate all trackbacks.
        $dest = 'EXT_UNMOD_TB';
    }
    $data[$dest][0] = $tb_info;

    $data['META_EDITOR'] = $tb_info['blog_name'];
    $data['META_EDITOR_EMAIL'] = '';
    $data['META_EDIT_COMMENT'] = 'Trackback ping from ' . $tb_info['url'];
    saveData($file, $data, $current_user, false, array($dest));

    return false;
}

/**
 * records successful trackback ping
 *
 * @param array $file File info array
 * @param array $current_user Current user info array
 * @param array $tb_info Trackback data, with the keys
 * 'ping' and 'time'
 * @return false,string Returns False on success, an error message on fail
 */
function recordSentTrackback($file, $current_user, $tb_info)
{
    $data['EXT_TRACKBACK_SENT'][0] = $tb_info;

    $data['META_EDITOR'] = $current_user['name'];
    $data['META_EDITOR_EMAIL'] = $current_user['email'];
    $data['META_EDIT_COMMENT'] = 'Sent Trackback Ping: ' . $tb_info['ping'];
    saveData($file, $data, $current_user, false, array('EXT_TRACKBACK_SENT'));
}

/**
 * sends a response to a trackback ping
 *
 * @param string $msg Error message, if any.
 */
function trackbackResponse($msg = false)
{
    print "Content-Type: text/xml\n\n";
    print '<?xml version="1.0" encoding="iso-8859-1"?>' . "\n" . "<response>\n";

    if ($msg) {
        print "<error>1</error>\n<message>$msg</message>\n";
    } else {
        print "<error>0</error>\n";
    }

    print "</response>\n";
}



/**
 * <em>Not currently used</em>. retrieve RDF data from a page
 *
 * Fetches a page and parses it for RDF data for
 * trackback auto-discovery
 * @param string $url
 * @return boolean,array Array with keys 'ping' and 'title'
 * false on failure
 * @see http://www.movabletype.org/docs/mttrackback.html#retrieving%20trackback%20pings
 */
/*
function autoDiscover($url)
{
    $html = file_get_contents($url);
    if (!$html) {
        return false;
    }

    // get all RDF data
    if (preg_match_all('/<rdf:RDF(.*?)<\/rdf:RDF>/s', $html, $rdfs)) {

        // scan RDF data for our url
        $rdf = $rdfs[1];
        for ($i = 0; $i < count($rdf); $i++) {

            // look for trackback:ping, if not, try about
            if (preg_match('/trackback:ping="([^"]+)"/', $rdf[$i], $matches)) {
                $tb['ping'] = $matches[1];

                // this is it. get the title
                if (preg_match('/dc:title="([^"]+)"/', $rdf[$i], $matches)) {
                    $tb['title'] = $matches[1];
                } else {
                    $tb['title'] = '';
                }

                break;

            } elseif (preg_match('/about="([^"]+)"/', $rdf[$i], $matches)) {
                $tb['ping'] = $matches[1];

                // this is it. get the title
                if (preg_match('/dc:title="([^"]+)"/', $rdf[$i], $matches)) {
                    $tb['title'] = $matches[1];
                } else {
                    $tb['title'] = '';
                }

                break;
            }
        }
    } else {
        print 'no rdf' ;
        return false;
    }
    return $tb;
}
*/

/**
 * sends a POST request
 *
 * @param string $url URL to send to
 * @param array $data Data to send.
 * @return array Assoc. array with keys keys 'headers', 'content', 'error'.
 * Error will be false on success, or an error message on failure.
 */
function sendPost($data, $content_type, $server, $post_to = '')
{
    global $VERSION;
    global $NAME;
    global $CFG;

    $return_data = array('headers' => '', 'content' => '', 'error' => false);

    // set up headers
    $request  = "POST $post_to HTTP/1.1\r\n";
    $request .= "Host: $server\r\n";
    $request .= 'User-Agent: ' . $NAME . '/' . $VERSION . "\r\n";
    $request .= 'Accept: text/xml,application/xml,application/xhtml+xml,text/html,text/plain' . "\r\n";
    $request .= 'Referer: ' . $CFG['server_name'] . "\r\n";
    $request .= "Cache-Control: max-age=0\r\n";

    $length = strlen($data);
    $request .= "Content-Type: $content_type\r\n";
    $request .= "Content-Length: $length\r\n";
    $request .= "Connection: close\r\n";
    $request .= "\r\n";
    $request .= $data;

    $socket  = fsockopen($server, 80, $errno, $errstr);

    if($socket) {
        fputs($socket, $request);

        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 4096);
        }
        fclose($socket);

        preg_match('/(.*?)\r?\n\r?\n(.*)/s', $response, $matches);

        if (empty($matches[2])) {
            $return_data['error'] = 'The server did not return any data';
        } else {
            $return_data['headers'] = $matches[1];
            $return_data['content'] = $matches[2];
        }

    } else {
        if (isset($errstr)) {
            $return_data['error'] = $errstr;
        } else {
            $return_data['error'] = 'An unspecified error occurred';
        }
    }
    return $return_data;
}

function weblogUpdatesPing($server, $post_to = '/')
{
    global $CFG;
    global $RSS;

    $data = '<?xml version="1.0"?>' . "\n" .
        "<methodCall>\n" .
        "<methodName>weblogUpdates.ping</methodName>\n" .
        "<params>\n" .
        "<param>\n" .
        "<value>" . $RSS['title'] . "</value>\n" .
        "</param>\n" .
        "<param>\n" .
        "<value>" . $CFG['protocol'] . '://' . $CFG['server_name'] . $_SERVER['PHP_SELF'] . "</value>\n" .
        "</param>\n" .
        "</params>\n" .
        "</methodCall>\n";

    $response = sendPost($data, 'text/xml', $server, $post_to);
    $status = parseWeblogUpdateResponse($response);
    if (!$status) {
        return 'success';
    } else {
        return $status;
    }
}

function weblogUpdatesExtendedPing($server, $post_to = '/')
{
    global $CFG;
    global $RSS;
    $data = '<?xml version="1.0" encoding="iso-8859-1"?>' . "\n" .
        "<methodCall>\n" .
        "<methodName>weblogUpdates.extendedPing</methodName>\n" .
        "<params>\n" .
        "<param>\n" .
        "<value>" . $RSS['title'] . "</value>\n" .
        "</param>\n" .
        "<param>\n" .
        "<value>" . $CFG['protocol'] . '://' . $CFG['server_name'] . $_SERVER['PHP_SELF'] . "</value>\n" .
        "</param>\n" .
        "<param>\n" .
        "<value></value>\n" .
        "</param>\n" .
        "<param>\n" .
        "<value>" . $CFG['protocol'] . '://' . $RSS['feed_file'] . "</value>\n" .
        "</param>\n" .
        "<param>\n" .
        "<value>0</value>\n" .
        "</param>\n" .
        "</params>\n" .
        "</methodCall>\n";

    $response = sendPost($data, 'text/xml', $server, $post_to);
    $status = parseWeblogUpdateResponse($response);
    if (!$status) {
        return 'success';
    } else {
        return $status;
    }

}

function parseWeblogUpdateResponse($response)
{
    print_r($respons);
    if (!$response['error']) {
        // we're only interested in the error code and response, if any
        preg_match('/<name>flerror<\/name><value><boolean>\s*(\d)\s*<\/boolean>/', $response['content'], $matches);
        $error = $matches[1];

        // $error is 1 on failure, 0 on success
        if (!$error) {
            $status = false;
        } else {
            preg_match('/<string>\s*(.*)\s*<\/string>/', $response['content'], $matches);
            $status = $matches[1];
        }

    } else {
        $status = 'Ping failed: ' . $return['error'];
    }
    return $status;
}

function parseTrackbackResponse($response)
{
    if (!$response['error']) {
        // we're only interested in the error code and response, if any
        preg_match('/<error>\s?(\d)\s?<\/error>/', $response['content'], $matches);
        $error = $matches[1];

        // $error is 1 on failure, 0 on success
        if (!$error) {
            $status = false;
        } else {
            preg_match('/<message>([^<]*)<\/message>/', $response['content'], $matches);
            $status = $matches[1];
        }

    } else {
        $status = 'Ping failed: ' . $return['error'];
    }
    return $status;
}

/**
 * send a trackback ping
 *
 * @param string $url URL to send trackback to
 * @param array $tb Trackback information to send.
 * Should have keys 'title', 'excerpt', 'blog_name', 'url'
 * although only 'url' is required.
 * @return boolean,string False on success, error message on failure.
 */
function sendTrackback($url, $tb)
{

    $post_data = '';
    foreach ($tb as $k => $v ){
        $post_data .= urlencode($k) . "=" . urlencode($v) . '&';
    }
    // remove extraneous &
    $post_data = substr($post_data, 0, -1);

    $t = parse_url($url);
    $server = $t['host'];
    $post_to = $t['path'];

    $response = sendPost($post_data, 'application/x-www-form-urlencoded', $server, $post_to);
    $status = parseTrackbackResponse($response);
    return $status;
}

/**
 * get list of history files for page
 *
 * @param array $file File info array
 * @param array $meta_data_fields Meta data fields to retrieve from history files
 * @return array Keyed on history file name, nested arrays
 * with keys 'mtime' (formatted modification time), 'unix_mtime',
 * and those specified in $meta_data_fields
 */
function getHistory($file, $meta_data_fields)
{
    global $CFG;
    // init array to load history files into
    $history_files=array();

    if ($h = opendir('.')) {
        while( false !== ($v = readdir($h)) ) {
            if ($v != '.' && $v != '..' && !is_dir($v) ) {
                $x = explode('.',$v);

                // skip lock, xml and preview files
                if ($x[0] == $file['name']) {
                    if ($x[1] != $file['extension']
                        && end($x) != $CFG['lockext']
                        && end($x) != 'xml'
                        && $x[1] != 'preview') {

                        $history_files[$v] = array();
                    }
                }
            }
        } // end while

        closedir($h);

        // add entry for the current file
        $history_files[$file['basename']] = array();

        // get meta info for each history file
        foreach($history_files as $k => $v) {
            $history_files[$k] =& getData($k, $meta_data_fields);
            // get modification time
            $history_files[$k]['mtime'] = date($CFG['secondarydateformat'], filemtime($k));
            // store the unix timestamp as well...this should be the format stored in 'mtime'
            // but leaving this way for the moment for legacy reasons
            $history_files[$k]['unix_mtime'] = filemtime($k);
        }

        $history_files = array_reverse($history_files);
    }

    return $history_files;
}

/**
 * gets a series of diffs, possibly spanning multiple history files
 *
 * Will return a nested array of diff lines, with each diff set in it's
 * own array, eg.
 *
 * <code>
 * $diffs[0]['file'] = 'index.php';
 * $diffs[0]['mtime'] = '03/14/04 11:43pm'; // format specified in $CFG
 * $diffs[0]['unix_mtime'] = 1075315519;
 * $diffs[0]['editor'] = 'editor';
 * $diffs[0]['diffs'] = array()             // as returned by getDiffs()
 * </code>
 * @see getDiffs()
 * @param string $file File info array
 * @param array $meta_data_fields Meta data fields to retrieve from history files
 * @param int $num_diffs Maximum number of chunks in which changes are present to return.
 * Note that this number is <em>not</em> absolute. If getDiffs() returns a
 * number of chunks exceeding the limit set in $num_diffs on a single invocation,
 * more than $num_diffs will be returned. This should be treated as an approximate
 * limit, useful for not retrieving (much) more than you need. Defaults to 0 (all).
 * @param array $diff_chunks Optional, chunks to compare, defaults
 * to all valid data fields
 * @param array $show Optional, which matches to return data on.
 * 'a' will be lines only found in the first file, 'b' lines in the second,
 * 'all' lines found in both files. Defaults to 'a', 'b' and 'all'.
 * @return array Nested array of diff lines, with meta-info
 */
function getMultiDiffs($file, $meta_data_fields, $num_diffs = 0, $diff_chunks = false, $show = array('a', 'b', 'all'))
{
    global $VALID_DATA_FIELDS;

    if (!$diff_chunks) {
        $diff_chunks = $VALID_DATA_FIELDS;
    }
    $history_files = getHistory($file, $meta_data_fields);

    $diffs = array();
    $i = 0;
    $diff_count = 0;

    // we'll need the name of the next file. these are stored in the keys of $history_files
    $next_filenames = array_keys($history_files);

    // get the diffs for each history file
    foreach ($history_files as $hist_name => $hist_data) {
        // get the names of the files to compare. this will be the current $hist_name
        // and the next one in the array. if there is no next element, we're done
        // we skip the first one - that's the current file
        $next_hist_name = next($next_filenames);
        if (!$next_hist_name) {
            break;
        }
        // add the meta info for the file
        $diffs[$i] = $hist_data;
        $diffs[$i]['file_a'] = $hist_name;
        $diffs[$i]['file_b'] = $next_hist_name;
        $diffs[$i]['diffs'] = getDiffs($hist_name, $next_hist_name, $diff_chunks, $show);
        //$diff_count += count($diffs[$i]['diffs']);
        $diff_count++;

        // if this pushed us over the limit, stop
        if ($num_diffs & ($diff_count >= $num_diffs)) {
            break;
        }

        $i++;
    }
    return $diffs;
}


/**
 * get diffs for specified data
 *
 * @param string $aname file 1 to compare
 * @param string $bname file 2 to compare
 * @param array $diff_chunks Optional, chunks to compare, defaults
 * to all valid data fields
 * @param array $show Optional, which matches to return data on.
 * 'a' will be lines only found in the first file, 'b' lines in the second,
 * 'all' lines found in both files. Defaults to 'a', 'b' and 'all'.
 * @return array Associative array keyed on chunk name.
 * @see getChunkDiffs()
 */
function getDiffs($aname, $bname, $diff_chunks = false, $show = array('a', 'b', 'all'))
{
    global $VALID_DATA_FIELDS;

    if (!$diff_chunks) {
        $diff_chunks = $VALID_DATA_FIELDS;
    }

    $a_data =& getDataLinesFromFile($aname, 'DATA');
    $b_data =& getDataLinesFromFile($bname, 'DATA');

    $diffs = array();
    foreach($diff_chunks as $chunk) {
        $diffs[$chunk] = array();
        $diffs[$chunk] = getChunkDiffs($a_data, $b_data, $chunk, $show);
    }
    return $diffs;
}

/**
 * get diffs for one chunk of data
 *
 * @param array $a_data Data lines from file 1
 * @param array $b_data Data lines from file 2
 * @param string $chunk name of the chunk we're comparing
 * @param array $show Optional, which matches to return data on.
 * 'a' will be lines only found in the first file, 'b' lines in the second,
 * 'all' lines found in both files. Defaults to 'a', 'b' and 'all'.
 * @return array Array of lines that were compared.
 * Each array is a nested assoc. array with the keys 'line' (the actual
 * text of the line) and 'file' (whether it was found in 'file1', 'file2' or 'all')
 */
function getChunkDiffs($a_data, $b_data, $chunk, $show = array('a', 'b', 'all'))
{
    global $TOKENS;

    // convenience variables for which diffs we want to return
    $show_a = false;
    $show_b = false;
    $show_all = false;

    if (in_array('a', $show)) {
        $show_a = true;
    }
    if (in_array('b', $show)) {
        $show_b = true;
    }
    if (in_array('all', $show)) {
        $show_all = true;
    }

    // diffs for the chunk will be in an assoc. array
    // each element contains the line, and the file it
    // was found in ('a', 'b', 'all'), eg.,
    // $diffs[0]['line'] = 'some text';
    // $diffs[0]['file'] = 'a';
    $diffs = array();

    // set starting values
    $blocksize = 3;
    $line_count = 0;

    $a = getChunkLines($a_data, $chunk);
    $b = getChunkLines($b_data, $chunk);

    // strip auto-tokens. these shouldn't show in diffs.
    foreach ($TOKENS as $token) {
        foreach ($a as $k => $v) {
            // submitted chunk
            if (isset($token['auto_open']) || isset($token['auto_close'])) {
                $a[$k] = str_replace($token['token'], '', $v);
            }
        }
        foreach ($b as $k => $v) {
            // submitted chunk
            if (isset($token['auto_open']) || isset($token['auto_close'])) {
                $b[$k] = str_replace($token['token'], '', $v);
            }
        }
    }


    // set starting values
    $alimit = count($a);
    $blimit = count($b);
    $acount = 0;
    $bcount = 0;

    while( ($acount < $alimit) && ($bcount < $blimit) ) {

        if (!strcmp($a[$acount], $b[$bcount])) {
    
            //got a match on line $acount / $bcount 
            if ($show_all) {
                array_push($diffs, array('line' => $a[$acount], 'file' => 'all'));
            }

            $acount++;
            $bcount++;

        } else {

            // match next block
            list($amatch, $bmatch, $matchlength) = matchTerms($blocksize, $a, $b, $alimit, $blimit, $acount, $bcount);

            if ($amatch < 0) {
                //reached eof without finding a matching block
                //output to eof and exit
                for($x = $acount; $x < $alimit; $x++) {
                    if ($show_a) {
                        array_push($diffs, array('line' => $a[$x], 'file' => 'a'));
                    }
                }

                for ($x = $bcount; $x < $blimit; $x++) {
                    if ($show_b) {
                        array_push($diffs, array('line' => $b[$x], 'file' => 'b'));
                    }
                }

                //reset the counter to eof
                $acount = $alimit;
                $bcount = $blimit;
            
            } else {
                // found a matching block
                for($x = $acount; $x < $amatch; $x++) {
                    if ($show_a) {
                        array_push($diffs, array('line' => $a[$x], 'file' => 'a'));
                    }
                }            

                for ($x = $bcount; $x < $bmatch; $x++) {
                    if ($show_b) {
                        array_push($diffs, array('line' => $b[$x], 'file' => 'b'));
                    }
                }

                // block starting on $amatch / $bmatch for $matchlength lines
                for($x = $amatch; $x < ($amatch + $matchlength); $x++) {
                    if (isset($a[$x]) && $show_all) {
                        array_push($diffs, array('line' => $a[$x], 'file' => 'all'));
                    }
                }
                $acount = $amatch + $matchlength;
                $bcount = $bmatch + $matchlength;
            }
        }
        // recycle through the file if not at eof

    }

    // this catches any additions made to the end of a chunk
    // if not other changes were made, the matching will have terminated
    // at the end of the shortest block
    if ($acount < $alimit) {
        while ($acount < $alimit) {
            if ($show_a) {
                array_push($diffs, array('line' => $a[$acount], 'file' => 'a'));
            }
            $acount++;
        }
    }
    if ($bcount < $blimit) {
        while ($bcount < $blimit) {
            if ($show_b) {
                array_push($diffs, array('line' => $b[$bcount], 'file' => 'b'));
            }
            $bcount++;
        }
    }
    return $diffs;
}

/**
 * match terms in the file up to x lines 
 *
 * @see getDiffs()
 */

function matchTerms($xlines, $a, $b, $alimit, $blimit, $acount, $bcount){

    $atotallines = $alimit - $acount;
    $btotallines = $blimit - $bcount;    
    $acountstart = $acount;
    $bcountstart = $bcount;

    $match_found = false;

    while(!$match_found){
        // get the initial arguments into comparable terms
        // init the terms
        $terma = "";
        $termb = "";

        for($x = $acount; $x < ($acount + $xlines); $x++) {
            if (isset($a[$x])) {
                $terma .= $a[$x];
            }
        }
        for($x = $bcount; $x < ($bcount + $xlines); $x++){
            if (isset($b[$x])) {
                $termb .= $b[$x];
            }
        }
 
        if (!strcmp($terma, $termb)) {
            $match_found = true;
            $returndata = array($acount, $bcount, $xlines);
        } else {

            // no match found.. 
            if ($bcount < $blimit) {
                // move file b counter forward 1
                $bcount++;
            } else {
                // move file a counter forward 1, reset file b counter
                $acount++;
                $bcount = $blimit - $btotallines;          
            }

            // if we have reached the end of the file with no match...
            if ( ($acount >= $alimit) && ($bcount >= $blimit) ) {

                // not really true, but it breaks out of this while loop
                $match_found = true;

                // sending back this data tells the calling function that no match was found
                $returndata = array(-1, -1, -1);
            }
        }
 
    } 
    return $returndata;
}

/**
 * get the highest version number for this file
 *
 * @param array $file File info array
 * @return string Highest version number, 5 digit number like 00001, 00002, etc
 */
function hivno($file)
{

    $filename = $file['name'];

    $z=0;

    if ($h = opendir('.')) {
        while (false !== ($v = readdir($h))) {

            if ($v != '.' && $v != '..') {
                $x = explode('.',$v);
                if ($x[0] == $filename && $x[1] != 'xml') {
                    if ($x[1] > $z and $x[1] != $file['extension']) {
                        $z=$x[1];
                    }
                }
            }
        }
        closedir($h);
        return $z;
    }
}

/**
 * send email on page updates
 *
 * @param array $users User info array
 * @param array $current_user Current user info array
 * @param array $file File info array
 */

function emailNotifications($users, $current_user, $file)
{
    global $CFG;

    // email users
    if ($CFG['email_users']) {
        foreach($users as $user) {
            if ($user['email']) {
                $subject = $file['name'] . '.' . $file['extension'] . ' has been updated';
                $message = 'The file ' . $file['name'] . '.' . $file['extension'] .
                    ' on the server ' . $CFG['server_name'];

                $message .= ' was edited by the user ' . $current_user['name'] . ' on ' . date($CFG['dateformat']);

                $success = mail($user['email'], $subject, $message,
                    "From: $CFG[admin_email]\r\n"
                    ."Reply-To: $CFG[admin_email]\r\n"
                    ."X-Mailer: PHP/" . phpversion()."/ edithispage.php");
            }
        }
    }
}

/**
 * strip slashes and encode HTML
 *
 * Convenience function. Strips slashes and HTML encodes string
 * @param string $string String to clean and encode
 * @return string
 */
function htmlClean($string)
{
    return stripslashes(htmlspecialchars($string));
}

/**
 * removes backslashes, strip tags and inserts line breaks
 *
 * Convenience function for incoming data
 * @return string
 */
function cleanUserInput($string, $allowed_html = '')
{
    $return = nl2br(strip_tags(stripslashes($string), $allowed_html));

    // if there are any anchor tags, add rel="nofollow"
    // we'll only need to do this if $allowed_html is not empty - 
    // otherwise there won't be any anchor tags
    if ($allowed_html) {
        $return = preg_replace('/<a([^>]+)>/', "<a$1 rel=\"nofollow\">", $return);
    }

    return $return;
}

/**
 * make sure comment does not match any disallowed regexes
 *
 * @param string $comment body of comment
 * @param array $rules patterns to match against
 * @param array $threshold how many matches are allowed. If this number is exceeded
 * the comment will be rejected
 * @return boolean
 */
function commentIsValid(&$comment, &$rules, $threshold)
{
    $found = 0;
    foreach ($rules as $rule) {
        $found += preg_match_all($rule, $comment, $t);
    }

    return $found < $threshold
        ? true
        : false;
}

/* PLUGIN::functionality */

/**
 * End Functions
 */
/**
 * Begin Initialization
 */

// make sure this script isn't being called directly
$f1 = pathinfo($_SERVER['PHP_SELF']);
$f2 = pathinfo( __FILE__);
if ($f1['basename'] == $f2['basename']) {
    exit;
}

if (isset($CFG['session_save_path']) && $CFG['session_save_path']) {
    session_save_path($CFG['session_save_path']);
}

session_start();

/**
 * file name information
 *
 * Information on this file, and files and directories
 * associated with it.
 * Keys: 'dirname', 'name', 'basename', 'extension',
 * 'imagedir', 'rss'
 * @global array $file
 */
$file = pathinfo($_SERVER['PHP_SELF']);

// this removes the extension from the basename of the script.
$file['name'] = substr($file['basename'], 0, strlen($file['basename']) - strlen($file['extension']) - 1);

$file['imagedir'] = $file['name'] . '-images';

$file['rss'] = $file['name'] . '.xml';
$file['rss_diff'] = $file['name'] . '_diff.xml';

// Grab the lock for this file to enable after_lock token creation.
$lock = getLockFile($file);

/* PLUGIN::tokens::after_lock */

// RSS init
// get the default account to use for managing editor
// and web master
foreach ($users as $user) {
    if ($user['group'] == 'super-editor') {
        $default_editor = $user;
        break;
    }
}

if (!$RSS['managingEditor'] && isset($default_editor)) {
    $RSS['managingEditor'] = $default_editor['email'];
}

if (!$RSS['webMaster'] && isset($default_editor)) {
    $RSS['webMaster'] = $default_editor['email'];
}

/**
 * The URL for this channel
 *
 * @global type var
 */
$RSS['link'] = $CFG['protocol'] . '://' . $CFG['server_name'] .  $_SERVER['PHP_SELF'];

$t = pathinfo($_SERVER['PHP_SELF']);
$fname = $CFG['server_name'] . $t['dirname'] . '/' . substr($t['basename'], 0, strlen($t['basename']) - strlen($t['extension']) - 1);

/**
 * RSS feed file
 * @global type var
 */
$RSS['feed_file'] = $fname . '.xml';

/**
 * RSS diff feed file
 * @global type var
 */
$RSS['diff_feed_file'] = $fname . '_diff.xml';

/**
 * RSS image link, if an image is being used
 *
 * @global string $RSS['image']['link']
 */
if ($RSS['image']) {
    $RSS['image']['link'] = $RSS['link'];
}

// find out what we're doing
// examine POST and GET variables for any keys prefixed with 'action_',
// this will give us the requested user action
// prefer POST to GET
// default action is viewing the page

/**
 * Alternative file listing
 * 
 * These ten lines of code will produce a listing of all available
 * content pages and add an "action_edit=1" argument as query string
 * to each filelink. This way you can remove "__EDIT_BUTTON__" from
 * the content page.
 *
 * Usage: http://example.com/index.php?admin
 * Contributed by Urs Gehrig <urs@circle.ch>
 */
if ($_SERVER["argv"][0] == 'admin') {

    $t = pathinfo(__FILE__);
    $contentfiles = glob('*.' . $t['extension']);

    echo '<html><head><title>Pages In This Directory</title></head><body>';
    echo "<h3>Available content pages to edit:</h3>";

    foreach($contentfiles as $key => $val) {
        if (($val == $t['basename'])
            || preg_match('/^editthispage_/', basename($val))
            || basename($val) == 'install-etp.php'
            || isHistoryFile($val)) {

            continue;
        }
        printf("<a href='%s?action_edit=1' title=''>%s</a><br />", $val, $val);
    }
    echo '</body></html>';
    exit;
}

/**
 * what action the script should perform
 *
 * Set via GET or POST variables with a name of the form
 * "action_someaction", prefer POST to GET/
 * @global string $action
 */
$action = 'view_page';
foreach ($_GET as $k => $v) {
    if (preg_match('/^action_(.*)/', $k, $matches)) {
        $action = $matches[1];
        break;
    }
}
foreach ($_POST as $k => $v) {
    if (preg_match('/^action_(.*)/', $k, $matches)) {
        $action = $matches[1];
        break;
    }
}

// if 'url' is specified, this is a trackback ping
if (isset($_POST['url'])) {
    $action = 'catch_ping';
}
if (isset($_GET['url'])) {
    $action = 'catch_ping';
    $_POST['url'] = $_GET['url'];
}

// if there was an action, store it in case their authorization fails
// we'll need it when we display the login form
// only store this if we don't have an incoming continue_action so that
// it's not overwritten
$continue_action = empty($_POST['continue_action']) ? $action : $_POST['continue_action'];


// get current user, if already logged in
$auth_user = isset($_SESSION['auth_user']) ? $_SESSION['auth_user'] : false;

// if user is unset, check for hashed login
if (!$auth_user && $hashed_action) {
    // so we need to ensure they are valid ($user + $hash).
    $possible_user = getUser($users, $hashed_user);
    if ($possible_user && md5($possible_user['name'].$possible_user['password']) == $hashed_hash) {
        $auth_user = $hashed_user;
    }
}

// if a username isn't set, but anonymous access is allowed, or if this
// is a trackback ping
// make sure the username and password are set to the guest default
if ((!$auth_user && $guest_access_allowed) || ($action == 'catch_ping')) {
    $auth_user = 'guest';
    $auth_pass = 'guest';
    $_SESSION['auth_user'] = 'guest';
}

$current_user = getUser($users, $auth_user);
if (!$current_user) {
    // This is probably a user from another ETP installation.
    $auth_user = 'guest';
    $auth_pass = 'guest';
    $_SESSION['auth_user'] = 'guest';
    $current_user = getUser($users, $auth_user);
}

// check user authorizations. if they can edit, they can also save and preview
if (in_array('edit', $auth_actions[$current_user['group']])) {
    array_push($auth_actions[$current_user['group']], 'save');
    array_push($auth_actions[$current_user['group']], 'preview');
}

// check again, including the 'all' type.
// if aye, they can see the # of unmoderated trackbacks/comments
if (in_array('edit', $auth_actions[$current_user['group']]) ||
    in_array('all',  $auth_actions[$current_user['group']])) {
    if ($CFG['moderate_comments']) {
        $mod_chunks =& getData($file['basename'], array('EXT_UNMODERATED'));
        $mod_list = $mod_chunks['EXT_UNMODERATED'];
        $mod_count = count($mod_list);
        if ($mod_count) $EXT_HTML['EXT_COMMENTS.open'] .= "<b>There are unmoderated entries ($mod_count).</b> ";
    }
    if ($CFG['moderate_trackbacks']) {
        $mod_chunks =& getData($file['basename'], array('EXT_UNMOD_TB'));
        $mod_list = $mod_chunks['EXT_UNMOD_TB'];
        $mod_count = count($mod_list);
        if ($mod_count) $EXT_HTML['EXT_TRACKBACK.open'] .= "<b>There are unmoderated trackbacks ($mod_count).</b> ";
    }
}
// This seems to go against conventions somewhat, but...
if ($CFG['moderate_comments']) $EXT_HTML['EXT_COMMENTS.open'] .= "<i><small>Comments are moderated, which means your comment will not be displayed until it has been approved.</small></i>";
if ($CFG['moderate_trackbacks']) $EXT_HTML['EXT_TRACKBACK.open'] .= "<i><small>Trackbacks are moderated, which means they do not appear immediately.</small></i>";

// check user authorizations. if they can edit messages, they can also save them
if (in_array('edit_messages', $auth_actions[$current_user['group']])) {
    array_push($auth_actions[$current_user['group']], 'save_messages');
}

// everyone gets to login and logout
// trackbacks are ok, too
array_push($auth_actions[$current_user['group']], 'do_login');
array_push($auth_actions[$current_user['group']], 'show_login');
array_push($auth_actions[$current_user['group']], 'logout');
array_push($auth_actions[$current_user['group']], 'cancel');
array_push($auth_actions[$current_user['group']], 'catch_ping');

$is_auth = isAuth($auth_actions, $current_user, $action);

// login failed, or they're not authorized for the request action
// if they're attempting to login, this will be processed in the switch below
if ($action != 'do_login' && !($current_user && $is_auth)) {
    $action = 'show_login';
}

// need to check for an attempted login before hitting
// the switch below. this could affect the action being performed
if ($action == 'do_login') {
    $is_auth = false;
    $login_success = doLogin($users, $_POST['user'], $_POST['pass']);
    if ($login_success) {
        // get the new user data
        $current_user = getUser($users, $_POST['user'], $_POST['pass']);
        $_SESSION['auth_user'] = $current_user['name'];

        // check that this user is authorized for this action
        $is_auth = isAuth($auth_actions, $current_user, $continue_action);
    }

    if (!$login_success || !$is_auth) {
        $action = 'show_login';
    } else {
        // login was successful, auth check was successful,
        // restore requested action
        $action = $_POST['continue_action'];
    }
}

// error flag
$err = false;

// any message stored here for later delivery
$message = '';

/**
 * flags controlling what HTML needs to be displayed as
 * a result of the user action.
 * Key is the fragment to display, value should be true.
 * to be shown. If the fragment is not to be displayed
 * it should be unset()
 * valid keys are:
 * <ul>
 * <li>message</li>
 * <li>msg_nonav</li>
 * <li>override_lock</li>
 * <li>edit_page</li>
 * <li>continue_and_save</li>
 * <li>message_upload</li>
 * <li>history</li>
 * <li>diffs</li>
 * <li>edit_or_view</li>
 * </ul>
 *
 * @global array $display
 */
$display = array();

// check for tokens that expand to code
foreach ($TOKENS as $name => $token) {
    if (isset($token['callback'])) {
        $TOKENS[$name]['eval'] = makeCallback($token);
    }
}

/**
 * End Initialization
 */

/**
 * Begin Control Logic
 */

// only error checks, actions which need to be performed
// and flag setting should be in this switch block
// information retrieved for display follows
$output_rss = false;

switch ($action) {

    /* PLUGIN::control_logic */
    
    /* PLUGIN::control_logic::before_logout */

    case 'logout' :
        doLogout();
        removeLock($file, $current_user['name']);
        $display['msg_nonav'] = true;
        $message = 'You are logged out. <a href="' . $_SERVER['PHP_SELF'] . '?action_view_page=1">Continue?</a>';
    break;

    /* PLUGIN::control_logic::after_logout */

    /* PLUGIN::control_logic::before_show_login */
    
    case 'show_login':
        $display['msg_nonav'] = true;
        $display['show_login'] = true;

        if (!$current_user) {
            $err = true;
            $message = 'The username and password did not match. Try again?';

        } else {
            $err = true;
            $t =& getData($file['basename'], array('MSG_AUTH_FAILED'));
            $message = $t['MSG_AUTH_FAILED'];
        }
    break;

    /* PLUGIN::control_logic::after_show_login */

    /* PLUGIN::control_logic::before_view_page */

    case 'view_page':
        $display['page'] = true;

        // make sure we have RSS feeds
        // we may not if page was freshly installed, copied or renamed
        // just check for the regular feed - both regular and diff are
        // written out at the same time
        if (!file_exists($file['rss'])) {
            $output_rss = true;
        }

    break;

    /* PLUGIN::control_logic::after_view_page */

    /* PLUGIN::control_logic::before_edit */
    
    case 'edit':

        $original_filename = isHistoryFile($file['basename']);
        if ($original_filename) {
            $err = true;
            $message = 'History files cannot be edited. <a href="' . $original_filename . '">Click here</a> to continue';
            $display['message'] = true;
        break;
        }

        $original_filename = isPreviewFile($file['basename'], $current_user['name']);
        if ($original_filename) {
            $err = true;
            $message = 'Preview files cannot be edited. <a href="#" onclick="self.close()">Click here</a> to close the preview window.';
            $display['msg_nonav'] = true;
        break;
        }

        $lock_info = getLockFile($file);
        if ($lock_info && $lock_info['user'] != $current_user['name']) {

            /* PLUGIN::control_logic::edit::is_locked */

        // file wasn't locked, or lock is stale. create a new one
        } else {
            $success = lockFile($file, $current_user['name']);

            // unable to write lock file. likely permission error
            if (!$success) {
                $err = true;
                $display['msg_nonav'] = true;
                $display['cannot_lock'] = true;
            break;
            } else {
                // get new lock info
                $lock_info = getLockFile($file);
                $display['edit_page'] = true;
            }
        }

    break;

    /* PLUGIN::control_logic::after_edit */
    
    /* PLUGIN::control_logic::before_edit_messages */
    
    case 'edit_messages':

        $lock_info = getLockFile($file);
        if ($lock_info && $lock_info['user'] != $current_user['name']) {
            $err = true;
            $display['msg_nonav'] = true;
            $display['override_lock'] = true;

        // file wasn't locked, or lock is stale. create a new one
        } else {
            $success = lockFile($file, $current_user['name']);

            // unable to write lock file. likely permission error
            if (!$success) {
                $err = true;
                $display['msg_nonav'] = true;
                $display['cannot_lock'] = true;
                break;
            } else {
                // get new lock info
                $lock_info = getLockFile($file);
                $display['edit_messages'] = true;
            }
        }

    break;

    /* PLUGIN::control_logic::after_edit_messages */

    /* PLUGIN::control_logic::before_save_messages */   
    
    case 'save_messages':
        $lock_info = getLockFile($file);

        $display['edit_messages'] = true;

        $new_chunks = $_POST['edtpag'];
        if (!dataChanged($file, $new_chunks)) {
            $message = 'Messages were not changed.';
            $display['message'] = true;
            break;
        }


        // if the file is locked
        // note that if the user auth is a hashed action, the user is non-interactive.
        // we thus choose to override the lock rather than lose our submission
        if (!$hashed_action && $lock_info && $lock_info['user'] !=  $current_user['name']) {
            $err = true;
            $display['continue_and_save'] = true;
            $display['msg_nonav'] = true;

            break;
        }

        // we need the new meta info as well as the new content
        $new_chunks = $_POST['edtpag'];
        $new_chunks['META_EDITOR'] = $current_user['name'];
        $new_chunks['META_EDITOR_EMAIL'] = $current_user['email'];

        saveData($file, $new_chunks, $current_user);

        $message = 'File saved: ' .$file['basename'];
        $display['message'] = true;

        $display['msg_nonav'] = true;
        $display['edit_or_view'] = true;

    break;

    /* PLUGIN::control_logic::after_save_messages */

    /* PLUGIN::control_logic::before_override_lock */
    
    // there was a lock file, but we're overriding it.
    case 'override_lock':
        lockFile($file, $current_user['name']);
        $display['edit_page'] = true;

        // we need the lock info so we can display it to the user later
        $lock_info = getLockFile($file);
    break;

    /* PLUGIN::control_logic::after_override_lock */

    /* PLUGIN::control_logic::before_preview */
    
    case 'preview':
        $lock_info = getLockFile($file);

        $preview_file = $file;
        $preview_file['name'] =  $file['name'] . '.preview.' . $current_user['name'];
        $preview_file['basename'] = $file['name'] . '.preview.' . $current_user['name'] . '.' . $file['extension'];

        $new_chunks = $_POST['edtpag'];

        // optionally, format tabs and trim lines
        $new_chunks = formatWhitespace($new_chunks, $_POST['expand_tabs'], $_POST['rtrim']);

        // optionally, tidy HTML
        if (isset($_POST['clean_html']) && $_POST['clean_html']) {
            $new_chunks = cleanHtml($new_chunks);
        }
        saveData($file, $new_chunks, $current_user, $preview_file);
        $display['edit_page'] = true;

        $showing_preview_file = true;

        // we'll need to restore the proper filename later
        $original_file = $file;
        $file = $preview_file;

    break;
    
    /* PLUGIN::control_logic::after_preview */

    /* PLUGIN::control_logic::before_save */
    
    case 'save':
        $lock_info = getLockFile($file);

        $display['edit_page'] = true;

        $new_chunks = $_POST['edtpag'];
        if (!dataChanged($file, $new_chunks)) {
            $message = 'File was not changed.';
            $display['message'] = true;
            break;
        }


        // if the file is locked
        if ($lock_info && $lock_info['user'] !=  $current_user['name']) {
            $err = true;
            $display['continue_and_save'] = true;
            $display['msg_nonav'] = true;

            break;
        }

        // we need the new meta info as well as the new content
        $new_chunks = $_POST['edtpag'];
        $new_chunks['META_EDITOR'] = $current_user['name'];
        $new_chunks['META_EDITOR_EMAIL'] = $current_user['email'];

        // optionally, format tabs and trim lines
        $new_chunks = formatWhitespace($new_chunks, $_POST['expand_tabs'], $_POST['rtrim']);

        // optionally, tidy HTML
        if (isset($_POST['clean_html']) && $_POST['clean_html']) {
            $new_chunks = cleanHtml($new_chunks);
        }
        saveData($file, $new_chunks, $current_user);

        // flag to output RSS feed
        $output_rss = true;

        emailNotifications($users, $current_user, $file);

        $message = 'File saved: ' .$file['basename'];
        $display['message'] = true;

        $display['msg_nonav'] = true;
        $display['edit_or_view'] = true;

    break;

    /* PLUGIN::control_logic::after_save */

    /* PLUGIN::control_logic::before_send_notification_pings */
    
    case 'send_notification_pings':
        $message = '';
        if (isset($_POST['ping_technorati'])) {
            $message .= 'Pinging Technorati...';
            $message .= weblogUpdatesPing('rpc.technorati.com', '/rpc/ping');
            $message .= '<br />';
        }
        if (isset($_POST['ping_blogs'])) {
            $message .= 'Pinging blo.gs...';
            $message .= weblogUpdatesExtendedPing('ping.blo.gs');
            $message .= '<br />';
        }
        $display['message'] = true;
        $display['edit_page'] = true;
        $lock_info = getLockFile($file);

    break;

    /* PLUGIN::control_logic::after_send_notification_pings */

    /* PLUGIN::control_logic::before_continue_and_save */
    
    case 'continue_and_save':
        lockFile($file, $current_user['name']);
        $lock_info = getLockFile($file);
        $new_chunks = $_POST['edtpag'];
        if (!dataChanged($file, $new_chunks)) {
            $message = 'file not changed';
            $display['message'] = true;
            break;
        }

        $new_chunks = $_POST['edtpag'];
        $new_chunks['META_EDITOR'] = $current_user['name'];
        $new_chunks['META_EDITOR_EMAIL'] = $current_user['email'];

        $new_chunks = formatWhitespace($new_chunks, $_POST['expand_tabs'], $_POST['rtrim']);

        // optionally, tidy HTML
        if (isset($_POST['clean_html']) && $_POST['clean_html']) {
            $new_chunks = cleanHtml($new_chunks);
        }
        saveData($file, $new_chunks, $current_user);

        // flag to output RSS feed
        $output_rss = true;

        $message = 'File Saved: ' .$file['basename'] . '.';
        $title = 'Edit this page: ' . $file['name'] . '.' . $file['extension'] . ' saved successfully';

        $display['message'] = true;
        $display['edit_page'] = true;
    break;
    
    /* PLUGIN::control_logic::after_continue_and_save */

/* PLUGIN::control_logic::before_add_comment */

case 'add_comment':
$display['page'] = true;

if ($_POST['comment']['body']) {
    $valid_comment = true;
    /* PLUGIN::control_logic::add_comment::validate */
    if ($valid_comment && commentIsValid($_POST['comment']['body'], $comment_rules, $comment_rules_threshold)) {
        $comment['name'] = cleanUserInput($_POST['comment']['name']);
        $comment['email'] = cleanUserInput($_POST['comment']['email']);
        $comment['url'] = cleanUserInput($_POST['comment']['url']);
        $comment['body'] = cleanUserInput($_POST['comment']['body'], $CFG['allowed_html']);
        $comment['time'] = date($CFG['dateformat'], time());
        if (strlen($comment['url']) < 7 || substr($comment['url'], 0, 7) != "http://" || substr($comment['url'], 0, 8) != "https://") {
            $comment['url'] = "http://" . $comment['url'];
        }
        saveComment($file, $current_user, $comment);
        
        // flag to output RSS feed
        $output_rss = true;
    }
}

// redirect to avoid multiple comment postings
$redirect = true;

break;

/* PLUGIN::control_logic::after_add_comment */

    /* PLUGIN::control_logic::before_upload_images */

    case 'upload_images':
        $lock_info = getLockFile($file);

        // don't check for size limit if this group
        // has no image restrictions
        if (in_array($current_user['group'], $no_image_limit)) {
            $limit = false;
        } else {
            $limit = true;
        }

        $upload_status = saveImages($file, $limit);
        $message = 'Image upload:';
        $display['message'] = true;
        $display['message_upload'] = true;
        $display['edit_page'] = true;
    break;

    /* PLUGIN::control_logic::after_upload_images */

    /* PLUGIN::control_logic::before_delete_images */
    
    case 'delete_images':
        $lock_info = getLockFile($file);

        if (isset($_POST['del_images'])) {
            $del_count = delImages($file, $_POST['del_images']);
            $message = $del_count;
            $message .= $del_count == 1 ? ' image' : ' images';
            $message .= ' deleted';
        } else {
            $message = 'No images marked for deletion.';
        }

        $display['message'] = true;
        $display['edit_page'] = true;
    break;

    /* PLUGIN::control_logic::after_delete_images */

    /* PLUGIN::control_logic::before_rename_file */
    
    case 'rename_file':
        $new_chunks = $_POST['edtpag'];
        $new_file['name'] = $_POST['newfilename'];
        $new_file['basename'] = $_POST['newfilename'] . '.' . $file['extension'];
        $new_file['imagedir'] = $file['name'] . '-images';

        saveData($file, $new_chunks, $current_user, $new_file);

        // redirect to new file
        header('Location: ' . $_POST['newfilename'] . '.' . $file['extension'] . '?action_edit=1');

        exit;

    /* PLUGIN::control_logic::after_rename_file */

    /* PLUGIN::control_logic::before_create_page */
        
    case 'create_page':
        $code_filename = 'editthispage_' . $file['name'] . '.php';
        $data = "<?php\n"
            . "require_once('" . $code_filename . "');\n"
            . "/* DATA */\n"
            . $data_file . "\n"
            . '/* VERSION */' . "\n"
            . '// ' . $VERSION . "\n"
            . '/* VERSION */' . "\n"

            . '/* PAGE_HEADER */' . "\n"
            . '// <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n"
            . '// <html>' . "\n"

            . '// <head>' . "\n"
            . '// <title>EditThisPage</title>' . "\n"

            . '// __META_RSS_AUTODISCOVERY__' . "\n"
            . '// __TRACKBACK_RDF__' . "\n"

            . '// </head>' . "\n"
            . '// <body>' . "\n"
            . '/* PAGE_HEADER */' . "\n"

            . '/* PAGE_MAIN_CONTENT */' . "\n"
            . '// <p>__RSS_FEED__</p>' . "\n"
            . '// <p>__RSS_DIFF_FEED__</p>' . "\n"
            . '/* PAGE_MAIN_CONTENT */' . "\n"

            . '/* PAGE_FOOTER */' . "\n"
            . '// __HIDDEN_AREA_BUTTON__' . "\n"
            . '// __HIDDEN_AREA_START__' . "\n"

            . '// __COMMENTS__' . "\n"
            . '// __COMMENT_FORM__' . "\n"
            . '// __TRACKBACKS__' . "\n"

            . '// __HIDDEN_AREA_END__' . "\n"

            . '// __EDIT_BUTTON__' . "\n"
            . '// </body>' . "\n"
            . '// </html>' . "\n"
            . '/* PAGE_FOOTER */' . "\n"

            . '/* META_EDITOR */' . "\n"
            . '// ' . "\n"
            . '/* META_EDITOR */' . "\n"

            . '/* META_EDITOR_EMAIL */' . "\n"
            . '// ' . "\n"
            . '/* META_EDITOR_EMAIL */' . "\n"

            . '/* META_EDIT_COMMENT */' . "\n"
            . '// ' . "\n"
            . '/* META_EDIT_COMMENT */' . "\n"

            . '/* META_PAGE_TITLE */' . "\n"
            . '// EditThisPage' . "\n"
            . '/* META_PAGE_TITLE */' . "\n"

            . '/* MSG_AUTH_FAILED */' . "\n"
            . '// You are not logged in, or not authorized for this action.' . "\n"
            . '/* MSG_AUTH_FAILED */' . "\n"

            . '/* EXT_TRACKBACK */' . "\n"
            . '// ' . "\n"
            . '/* EXT_TRACKBACK */' . "\n"

            . '/* EXT_TRACKBACK_SENT */' . "\n"
            . '// ' . "\n"
            . '/* EXT_TRACKBACK_SENT */' . "\n"

            . '/* EXT_COMMENTS */' . "\n"
            . '// ' . "\n"
            . '/* EXT_COMMENTS */' . "\n"
            . "/* DATA */\n"
            . "?>\n";

        $fh = fopen($_POST['createpagename'] . '.' . $file['extension'], 'w');
        fwrite($fh,$data);
        fclose($fh);

        // redirect to new file
        header('Location: ' . $_POST['createpagename'] . '.' . $file['extension'] . '?action_edit=1');

        exit;

    /* PLUGIN::control_logic::after_create_page */

    /* PLUGIN::control_logic::before_view_history */
        
    case 'view_history':
        $lock_info = getLockFile($file);
        $display['history'] = true;
    break;

    /* PLUGIN::control_logic::after_view_history */

    /* PLUGIN::control_logic::before_delete_history */

    case 'delete_history':
        $lock_info = getLockFile($file);
        $deleted_histories = delHistory($_POST['history_files']);
        $message = 'History deleted';

        $display['message'] = true;
        $display['history'] = true;

    break;

    /* PLUGIN::control_logic::after_delete_history */

    /* PLUGIN::control_logic::before_view_diffs */

    case 'view_diffs':
        $lock_info = getLockFile($file);
        // make sure two files are specified
        if (isset($_POST['comparefile1']) && isset($_POST['comparefile2'])) {
            $display['diffs'] = true;
        } else {
            $err = true;
            $display['message'] = true;
            $display['history'] = true;
            $message = 'You must specify two files to compare.';
        }
    break;

    /* PLUGIN::control_logic::after_view_diffs */

    /* PLUGIN::control_logic::before_catch_ping */

    // register trackback ping
    case 'catch_ping':
        $tb_info = array();
        $tb_info['url'] = $_POST['url'];
        $tb_info['title'] = isset($_POST['title']) && $_POST['title']
            ? $_POST['title']
            : $_POST['url'];

        $tb_info['excerpt'] = isset($_POST['excerpt']) && $_POST['excerpt']
            ? $_POST['excerpt']
            : '';

        $tb_info['blog_name'] = isset($_POST['blog_name']) && $_POST['blog_name']
            ? $_POST['blog_name']
            : '';

        $tb_info['time'] = date($CFG['dateformat'], time());

        $error = registerTrackback($file, $current_user, $tb_info);
        if (!$error) {
            trackbackResponse();
        } else {
            trackbackResponse($error);
        }
        $output_rss = true;
        $no_script_output = true;

    /* PLUGIN::control_logic::after_catch_ping */

    /* PLUGIN::control_logic::before_send_ping */

    // send trackback ping
    case 'send_ping':
        $lock_info = getLockFile($file);
        $display['edit_page'] = true;

        $tb_info['url'] = $CFG['protocol'] . '://' . $CFG['server_name'] . $_SERVER['PHP_SELF'];
        $tb_info['title'] = $RSS['title'];
        $tb_info['excerpt'] = $RSS['description'];
        $tb_info['blog_name'] = $RSS['title'];

        $status = sendTrackback($_POST['ping'], $tb_info);
        if ($status)  {
            $err = true;
            $display['message'] = true;
            $message = 'Trackback failed for ' . $_POST['ping'] . ': ' . $status . '.';

            break;
        } else {
            recordSentTrackback($file, $current_user, array( 'ping' => $_POST['ping'], 'time' => date($CFG['dateformat'], time())));

            $display['message'] = true;
            $message = 'Trackback successfully sent to ' . $_POST['ping'] . '.';
        }

    break;

    /* PLUGIN::control_logic::after_send_ping */

    /* PLUGIN::control_logic::before_view_trackbacks_comments */
    
    case 'view_trackbacks_comments':
        $lock_info = getLockFile($file);
        $display['edit_page'] = true;
    break;

    /* PLUGIN::control_logic::after_view_trackbacks_comments */

    /* PLUGIN::control_logic::before_delete_trackbacks */
    
    case 'delete_trackbacks':
        $tb = isset($_POST['trackbacks']) ? $_POST['trackbacks'] : array();

        $new_chunks = delExtRecords($file, array('EXT_TRACKBACK' => $tb));

        saveData($file, $new_chunks, $current_user);

        $lock_info = getLockFile($file);
        $display['edit_page'] = true;

    break;

    /* PLUGIN::control_logic::after_delete_trackbacks */

    /* PLUGIN::control_logic::before_delete_comments */
    
    case 'delete_comments':
        $comments = isset($_POST['comments']) ? $_POST['comments'] : array();

        $new_chunks = delExtRecords($file, array('EXT_COMMENTS' => $comments));

        saveData($file, $new_chunks, $current_user);

        $lock_info = getLockFile($file);
        $display['edit_page'] = true;

    break;

    /* PLUGIN::control_logic::after_delete_comments */

    /* PLUGIN::control_logic::before_moderate */
    
    case 'moderate':
        $comments = isset($_POST['moderation']) ? $_POST['moderation'] : array();
        $data['EXT_COMMENTS'] = array();
        
        // Grab the available unmoderated entries.
        $mod_chunks =& getData($file['basename'], array('EXT_UNMODERATED'));
        $mod_list = $mod_chunks['EXT_UNMODERATED'];
        
        // Extract the just-now-moderated-and-approved comments and put in $data.
        // The just-moderated-and-not-approved comments will be deleted.
        foreach ($comments as $comment) {
            $data['EXT_COMMENTS'][] = $mod_list[$comment];
            unset($mod_list[$comment]);
        }

        /* PLUGIN::control_logic::moderate::about_to_moderate */
        
        // Add the new comments.
        saveData($file, $data, $current_user, false, array('EXT_COMMENTS'));
        // Clear the unmoderated entries.
        saveData($file, array('EXT_UNMODERATED' => array()), $current_user);
        
        $lock_info = getLockFile($file);
        $display['edit_page'] = true;
    break;

    /* PLUGIN::control_logic::after_moderate */

    /* PLUGIN::control_logic::before_tb_moderate */
    
    case 'tb_moderate':
        $trackbacks = isset($_POST['tb_moderation']) ? $_POST['tb_moderation'] : array();
        $data['EXT_TRACKBACK'] = array();
        
        // Grab the available unmoderated entries.
        $mod_chunks =& getData($file['basename'], array('EXT_UNMOD_TB'));
        $mod_list = $mod_chunks['EXT_UNMOD_TB'];
        
        // Extract the just-now-moderated-and-approved trackbacks and put in $data.
        // The just-moderated-and-not-approved trackbacks will be deleted.
        foreach ($trackbacks as $trackback) {
            $data['EXT_TRACKBACK'][] = $mod_list[$trackback];
        }
        
        // Add the new trackbacks.
        saveData($file, $data, $current_user, false, array('EXT_TRACKBACK'));
        // Clear the unmoderated entries.
        saveData($file, array('EXT_UNMOD_TB' => array()), $current_user);
        
        $lock_info = getLockFile($file);
        $display['edit_page'] = true;
    break;

    /* PLUGIN::control_logic::after_tb_moderate */

    /* PLUGIN::control_logic::before_cancel */
    
    case 'cancel':
        removeLock($file, $current_user['name']);
        $display['page'] = true;
    break;

    /* PLUGIN::control_logic::after_cancel */

}

// meta data is required for all but displaying the page
if (empty($display['page'])) {
    $meta_data =& getData($file['basename'], $meta_data_fields);

    // if the title has not been set, set it here
    $title = 'EditThisPage: ' . $meta_data['META_PAGE_TITLE'];
}

// get information required for displaying different pages/elements
if (isset($display['edit_page'])) {
    $ext_fields = $trackback_data_fields;
    array_push($ext_fields, 'EXT_COMMENTS');
    array_push($ext_fields, 'EXT_UNMODERATED');
    array_push($ext_fields, 'EXT_UNMOD_TB');
    $ext_list =& getData($file['basename'], $ext_fields);

    // don't html encode the data yet. must be raw for getPageInfo
    $html =& getData($file['basename'], $html_data_fields);

    flattenChunks($html);

    // strip auto tokens. These aren't editable
    $html = clearAutoTokens($html);
    $images = getImages($file);

    // get line count, word count, and file size
    $page_info = getPageInfo($html);

    // html encode each data chunk
    foreach($html as $k => $v) {
        $html[$k] = htmlspecialchars($v);
    }

    // we'll need the preview file name
    $preview_filename = $file['name'] . '.preview.' . $current_user['name'] . '.' . $file['extension'];
}

if (isset($display['edit_messages'])) {
    // don't html encode the data yet. must be raw for getPageInfo
    $html =& getData($file['basename'], $message_data_fields);

    flattenChunks($html);

    // strip auto tokens. These aren't editable
    $html = clearAutoTokens($html);
    $images = getImages($file);

    // get line count, word count, and file size
    $page_info = getPageInfo($html);

    // html encode each data chunk
    foreach($html as $k => $v) {
        $html[$k] = htmlspecialchars($v);
    }
}

if (isset($display['override_lock'])) {
    $html =& getData($file['basename'], $html_data_fields, true);
}

if (isset($display['history'])) {
    $history_files = getHistory($file, $meta_data_fields);
}
if (isset($display['diffs'])) {
    $diff_list = getDiffs($_POST['comparefile1'], $_POST['comparefile2'], $diff_data_fields, $diff_files);
}
// we need to make sure our callback tokens our expanded
if (isset($display['page']) || $output_rss) {
    $chunks =& getData($file['basename'], $html_data_fields);

    foreach ($chunks as $name => $chunk) {
        // set the 'html' element for tokens with callbacks
        foreach ($TOKENS as $k => $token) {
            if (isset($token['eval'])) {
                $TOKENS[$k]['html'] = eval($token['eval']);
            }
        }
    }
}

// if we're displaying the page, that's all we need to do. output and exit
if (isset($display['page'])) {
    $output = '';

    foreach ($chunks as $name => $chunk) {
        // skip extended data chunks. these
        // must be included with tokens
        if (is_array($chunk)) {
            continue;
        } else {
            // do page includes, if enabled
            if ($CFG['allow_includes']) {
                $result = "";
                do {
                    $a = strpos($chunk, "__PAGE_INCLUDE(");
                    $b = strpos($chunk, ")__");
                    if ($b > 0) {
                        $result .= substr($chunk, 0, $a);
                        $page    = substr($chunk, $a + 15, $b - $a - 15);
                        if (strpos($page, "..") !== false) {
                            $page = "please_do_not_use_.._in_page_includes";
                        } else if (strpos($page, "/") !== false) {
                            $page = "please_do_not_use_slash_in_page_includes";
                        }
                        $chunk   = substr($chunk, $b + 3);
                        /* try { */
                        ob_start();
                        @include($page);
                        $result .= ob_get_contents();
                        ob_end_clean();
                        /* } catch (Exception $e) {
                           $result .= "<!-- [$e] -->";
                           } */
                    }
                } while ($b > 0);
                $chunk = $result . $chunk;
            }
            $output .= expandTokens($chunk);
        }
    }

    // was this a preview file? if so, delete ourself
    if (strstr($file['name'], '.preview.' . $current_user['name'])) {
        if (file_exists($file['basename'])) {
           unlink($file['basename']);

           // and make sure that we don't try output an RSS feed
           $output_rss = false;
        }
    }
}

if ($output_rss) {
    // max_rss_items is reduced by one. The first item will be the current body
    $rss_diffs = getMultiDiffs($file, $meta_data_fields, $CFG['max_rss_items'] - 1, $diff_data_fields, $diff_files);

    writeRss($file, $rss_diffs, $diff_data_fields, $meta_data_fields, $data_field_labels, true);
}

// if we need to exit before we print out any HTML,
// but after writing out the feeds, to it now
// currently only applicable to trackbacks
if ($no_script_output) {
    exit;
}

if (isset($redirect)) {
    header('Location: ' . $_SERVER['PHP_SELF']);
}

if (isset($display['page'])) {
    print $output;
    exit;
}

/**
 * End Control Logic
 */
/**
 * Begin Administration Page Generation
 */

/* PLUGIN::admin */

// start page header
// this will be the same for all admin pages
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta http-equiv="Content-Style-Type" content="text/css" />
<title><?= $title ?></title>
<style type="text/css">

/* style for submit buttons with class="submit" */
body {
    color: #000000;
    background-color: #ffffff;
    margin: 0px;
    padding: 0px;
    font-family: sans-serif;
    font-size: small;
}

em {
    font-style: normal;
    font-weight: 700;
}

input.on {
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#000000;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;
    width:100%;
}

span.link {
    cursor: pointer;
    color: #00c;
    text-decoration: none;
}
span.link:Hover {
    text-decoration: underline;
}

input.off {
    border:1px solid;
    border-color:#666666 #333333 #333333 #666666;
    font:bold small arial, verdana,sans-serif;
    color:#666666;
    background-color: #b7bdc7;
    text-decoration:none;
    margin:1px;
    padding: 3px;
    width:100%;
}

input[type="file"] {
    margin:2px 0px 2px 0px;
}

textarea{
    font-size: 1em;
    font-family: Courier, Monospace;
    white-space: pre;
    width: 95%;
    height: 200px;
    margin: 0px;
    padding: 0px;
}

fieldset {
    border:1px solid #444444;
    margin: 10px 5px;
    padding: 5px;
    color: #000000;
    font-size: small;
}

legend {
    color: #000000;
}


h1 {
    font-weight: 700;
    font-size: 1.4em;
    color: #000000;
}

h1.admintitle{
    margin: 0px;
    padding: 10px;
    color: #676d87;
    background-color: #e4e7ed;
    border-bottom: 2px solid #b7bdc7;
}

table {
    border: none;
    border:1px solid #aaaaaa;
    border-collapse: collapse;
    padding: 1px;
    margin: 3px 0px 3px 3px;
    width: 100%;
}

ul.messagelist {
    list-style: none;
    margin-bottom: 1.2em;
    padding: 0px;
}
ul.messagelist li {
    margin-bottom: 2px;
}

table.statustable {
    border: none;
    border-collapse: collapse;
    padding: 1px;
    margin: 0px;
    width: 100%;
}


.tr1 {
    background-color:#b7bdc7;
}
.tr2 {
    background-color:#e4e7ed;
}
/*
pre {
    overflow:scroll;
    overflow-x: auto;
    overflow-y: visible;
    padding-bottom: 25px;
    width: 99%;
}
*/

/* following .file classes are used in displaying diffs */
.file1 {
    background-color:#c0c088;
    font-family:Courier, Monospace;
    font-size:small;
}
.file2 {
    background-color:#336699;
    color: #ffffff;
    font-family:Courier, Monospace;
    font-size:small;
}
.fileboth {
    background-color:#FFFFFF;
    font-family:Courier, Monospace;
    font-size:small;
}
/* navigation and controls */

#editcolumn {
    position: absolute;
    float: left;
    width: 58%;
    border:2px solid #b7bdc7;
    background-color: #e4e7ed;
    margin: 2% 0% 2% 2%;
}

#controlcolumn {
    position: absolute;
    float: left;
    width: 35%;
    margin: 2% 0% 2% 62%;
    padding: 0px;
    border:2px solid #b7bdc7;
    background-color: #e4e7ed;
}

#nonavblock {
    width: 35%;
    padding: 10px;
    border:2px solid #b7bdc7;
    background-color: #e4e7ed;
    margin:10% 30% 10% 30%;

}

#nonavblock input {
    width: 48%;
}

#statusblock {
    padding: 5px;
}

#editblock {
    clear: left;
    margin: 3px;
}

#lockmessage {
    margin: 0px;
    padding: 5px;
}

#historyblock {
    clear: left;
    float: left;
    padding: 5px;
}

#trackbackblock {
    clear: left;
    padding: 5px;
}

#loginblock {
    padding: 0px 5px;
    margin: 0px
    background-color: #e4e7ed;
    text-align: right;
}

#messageblock {
    padding: 0px 5px;
    margin: 5px;
    background-color: #f4f7fd;
    border:1px solid #444444;
}

#editor_comment {
    width: 90%;
}

p.error {
    color: #990000;
    font-weight: 700;
}

a:active {
    color: #6666cc;
    text-decoration: none;
    font-weight: 700;
}
a:link {
    color: #6666cc;
    text-decoration: none;
    font-weight: 700;
}
a:visited {
    color: #6666cc;
    text-decoration: none;
    font-weight: 700;
}
a:hover {
    color: #6666cc;
    text-decoration: underline;
    font-weight: 700;
}

/* PLUGIN::admin::css */

</style>

<script language="JavaScript" type="text/javascript">

var button_status = new Array;
button_status['submit_button'] = <?=  isset($_COOKIE['action_save_state']) && $_COOKIE['action_save_state'] == 'on'
            ? 'true'
            : 'false';
            ?>


function setCookie(name, value, expires, path, domain, secure)
{
    var curCookie = name + "=" + escape(value) +
        ((expires) ? "; expires=" + expires.toGMTString() : "") +
        ((path) ? "; path=" + path : "") +
        ((domain) ? "; domain=" + domain : "") +
        ((secure) ? "; secure" : "");
    document.cookie = curCookie;
}

function buttonOn(button_id, button_name)
{
    el = document.getElementById(button_id);
    // activate button
    if (button_status[button_id] != true) {
        button_status[button_id] = true;
        el.className = 'on';

        // remember our state
        // makes sure that the submit button does
        // not become deactivated when navigating pages or previewing
        setCookie(button_name + '_state', 'on');
    }
}
function buttonOff(button_id, button_name)
{
    el = document.getElementById(button_id);
    // de-activate button
    if (button_status[button_id] == true) {
        button_status[button_id] = false;
        el.className = 'off';

        // remember our state
        // makes sure that the submit button does
        // not become deactivated when navigating pages or previewing
        setCookie(button_name + '_state', 'off');
    }
}

function checkall(base)
{
    var l, item;
    var i, ix;
    for (var n = base.nextSibling; n; n = n.nextSibling) {
        if (n.nodeType == 1) {
            l  = n.getElementsByTagName('input');
            ix = l.length;
            for (i = 0; i < ix; i++) {
                item = l.item(i);
                if (item.getAttribute('type') == 'checkbox') {
                    item.checked=!item.checked;
                }
            }
        }
    }
}

<?php
if (isset($showing_preview_file)):
    ?>

    var pop_window;
    function popWin(pop_url)
    {
        pop_window = window.open(pop_url,'thewindow','scrollbars');
        setTimeout('focusPop(pop_window)',2000);
    }

    function focusPop(pop_window){
        pop_window.focus();
    }

    popWin('<?= $preview_file['basename'] ?>');

    <?php
    $file = $original_file;
endif;
?>

</script>


</head>

<body>


<h1 class="admintitle">
EditThisPage v<?= $VERSION ?>: <?= $meta_data['META_PAGE_TITLE'] ?> (<?= $file['basename'] ?>)
</h1>



<?php
// messages that should not have standard navigation
if (isset($display['msg_nonav'])) :
    ?>

    <div id="nonavblock">

    <?php
    if (isset($display['override_lock'])) :
        ?>

        <h1>Warning!</h1>
        <p>
        <?= $meta_data['META_PAGE_TITLE'] ?> is currently being edited by <?= $lock_info['user'] ?>
        </p>
        <p>
        File was locked for editing on <?= date($CFG['dateformat'], $lock_info['created']) ?>
        </p>

        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">

        <input type="submit" class="on" name="action_override_lock" value="Override" />
        <input type="button" class="on" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_page=1';return false;" name="action_view_page" value="Cancel" />

        </form>

        <?php
    elseif (isset($display['continue_and_save'])) :
        ?>

        <h1>Warning!</h1>
        <p>
        <?= $meta_data['META_PAGE_TITLE'] ?> has been opened for editing by <?= $lock_info['user'] ?> at <?= date($CFG['dateformat'], $lock_info['created']) ?>
        </p><p>
        If you choose to continue, you may want to check the history file to make sure that you haven't overwritten any important changes.
        </p>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="hidden" name="renamefile" value="<?= $_POST['renamefile'] ?>" />
        <input type="hidden" name="newfilename" value="<?= $_POST['newfilename'] ?>'" />

        <?php
        foreach($_POST['edtpag'] as $chunk_name => $data) :
            ?>

            <input type="hidden" name="edtpag[<?= $chunk_name ?>]" value="<?= htmlClean($data) ?>" />

            <?php
        endforeach;
        ?>

        <input type="submit" class="on" name="action_continue_and_save" value="Continue and Save" />
        <input type="button" class="on" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" name="action_cancel" value="Cancel Edit" />
        </form>

        <?php
    elseif (isset($display['edit_or_view'])) :
        ?>

        <h1><?= $meta_data['META_PAGE_TITLE'] ?> (<?= $file['basename'] ?>) saved.</h1>
        <p>
        Would you like to return to the administrative pages, or view the page?
        </p>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="submit" class="on" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit=1';return false;" name="action_edit" value="Back to Admin" />
        <input type="submit" class="on" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" name="action_cancel" value="View Page" />
        </form>

        <?php
    elseif (isset($display['show_login'])) :
        ?>

        <h1><?= $meta_data['META_PAGE_TITLE'] ?>: Login</h1>
        <p>
        <?= $message ?>
        </p>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        Username:
        <br />
        <input type="text" name="user" />
        <br />
        Password:
        <br />
        <input type="password" name="pass" />
        <br /><br />
        <input type="hidden" name="continue_action" value="<?= $continue_action ?>" />

        <?php
        // if there's any incoming post data, make sure we save it
        foreach($_POST as $k1 => $v1) :
            // skip any actions and the user and pass fields
            if (strpos($k1, 'action_') === 0
               || $k1 == 'user'
               || $k1 == 'pass') {
                continue;
            }

            if (is_array($v1)) :
                foreach($v1 as $k2 => $v2) :
                    ?>

                <input type="hidden" name="<?= $k1 ?>[<?= $k2 ?>]" value="<?= htmlClean($v2) ?>" />

                    <?php
                endforeach;
            else:
                ?>

                <input type="hidden" name="<?= $k1 ?>" value="<?= htmlClean($v1) ?>" />

            <?php
            endif;
        endforeach;
        ?>
        
        <input type="button" class="on" name="backbutton" value="Back" onclick="history.back();" />
        <input type="submit" class="on" name="action_do_login" value="Login" />
        </form>

        <?php
    elseif (isset($display['logout'])) :
        ?>

        <h1>You are logged out</h1>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="button" class="on" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_page=1';return false;" name="action_view_page" value="Continue" />
        </form>

        <?php
    elseif (isset($display['cannot_lock'])) :
        ?>

        <h1>Unable to write lockfile</h1>
        <p>This is likely because the permissions are not set correctly. To set permissions:</p>
        <p>Change the file and folder's group to the appropriate webserver group:</p>
        <p>On <em>Redhat Linux with Apache</em>, this would be by default the 'nobody' group.</p>
        <ul>
        <li>The command would be "chgrp nobody newpagename.php"</li>
        <li>The command would be "chgrp nobody.</li>
        </ul>

        <p>On <em>Debian Linux with Apache</em>, this would be by default the 'www-data' group.</p>
        <ul>
        <li>The command would be "chgrp www-data newpagename.php"</li>
        <li>The command would be "chgrp www-data.</li>
        </ul>
        <p>Change the file and directory's group write permissions:</p>
        <p>On <em>any Unix/Linux</em> system:</p>
        </p>
        <ul>
        <li>The command to change the file would be "chmod g+w pagename.php"</li>
        <li>The command to change the directory would be "chmod g+w ."</li>
        </ul>

        <?php
    else:
        ?>

        <p><?= $message ?></p>

        <?php
    endif;
    ?>

    <!-- close nonavblock -->
    </div>

    <?php
    // TODO/maint.: Split into multiple files.
   
// these are the standard edit pages
else:

    $editcolumn_id = "editcolumn";

    /* PLUGIN::admin::edit::init */

    ?>

    <form method="post" name="editform" action="<?= $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">

    <div id="<?= $editcolumn_id ?>">

    <?php
    // administrative elements
    if (isset($display['edit_page'])) :
        ?>

        <div id="editblock">

        <?php
        foreach($html as $chunk_name => $data) :
            // only display the areas that they can edit
            if (!in_array($chunk_name, $auth_edit_fields[$current_user['group']])) :
                continue;
            endif;

            /* PLUGIN::admin::edit::print_field */

        endforeach;

        // are they authorized to leave a comment?
        if (in_array('META_EDIT_COMMENT', $auth_edit_fields[$current_user['group']])) :
            ?>

            <fieldset>
            <legend><?= $data_field_labels['META_EDIT_COMMENT'] ?></legend>
            <input type="text" id="editor_comment" name="edtpag[META_EDIT_COMMENT]" maxlength="255" onkeypress="buttonOn('submit_button', 'action_save');" value="<?= @$_POST['edtpag']['META_EDIT_COMMENT'] ?>" />

            </fieldset>

            <?php
        endif;
        ?>

        <!-- close editblock -->
        </div>

        <?php
    endif;

    // message editing
    if (isset($display['edit_messages'])) :
        ?>

        <div id="editblock">

        <?php
        foreach($html as $chunk_name => $data) :
            // only display the areas that they can edit
            if (!in_array($chunk_name, $auth_edit_fields[$current_user['group']])) :
                continue;
            endif;
            ?>

            <fieldset>
            <legend><?= $data_field_labels[$chunk_name] ?>:
            &nbsp;&nbsp;
            Size: <?= $page_info[$chunk_name]['size'] ?> characters
            &nbsp;&nbsp;
            Lines: <?= $page_info[$chunk_name]['lines'] ?>
            &nbsp;&nbsp;
            Word Count: <?= $page_info[$chunk_name]['words'] ?>
            </legend>

            <textarea name="edtpag[<?= $chunk_name ?>]" rows="15" cols="20" onkeypress="buttonOn('submit_button', 'action_save');"><?= $data ?></textarea>
            </fieldset>

            <?php
        endforeach;
        ?>

        <!-- close editblock -->
        </div>

        <?php
    endif;

    if (isset($display['history'])) :
        ?>

        <div id="historyblock">

        <input type="hidden" name="history" value="true" />

        <table>
        <tr>

        <th>Filename</th>
        <th>Date Modified</th>
        <th>Modified By</th>
        <th>Delete?</th>
        <th colspan="2">Diffs</th>
    
        </tr>

        <?php

        $i = 0;
        foreach($history_files as $hfile => $hmeta) :
            $rowclass = $i % 2 ? 'tr2' : 'tr1';

            // auto select this version for comparefile1 and
            // most recent version for comparefile2
            // $i == 0 for this version,
            // $i == 1 for most recent
            $cmp_1_checked = '';
            $cmp_2_checked = '';
            if ($i == 0) {
                $cmp_1_checked = ' checked="checked"';
                $cmp_2_checked = '';
            } elseif ($i == 1) {
                $cmp_1_checked = '';
                $cmp_2_checked = ' checked="checked"';
            }

            ?>

            <tr class="<?=$rowclass ?>">
        
            <td>
            <a href="<?= $hfile ?>"><?= $hfile ?></a>
            </td>

            <td>
            <?= $hmeta['mtime'] ?>
            </td>
        
            <td>
            <?= $hmeta['META_EDITOR'] ?>
            </td>

            <td style="text-align: center">

            <?php
            // don't display the delete box for the current version
            if ($i) :
                ?>

               <input type="checkbox" name="history_files[<?= $i ?>]" value="<?= $hfile ?>" />

                <?php
            endif;
            ?>
            </td>

            <td style="text-align: center">
            <input type="radio" name="comparefile1" value="<?= $hfile ?>" <?= $cmp_1_checked ?> />
            </td>

            <td style="text-align: center">
            <input type="radio" name="comparefile2" value="<?= $hfile ?>" <?= $cmp_2_checked ?> />
            </td>

            </tr>

            <tr class="<?=$rowclass ?>">
            <td colspan="6">

            <p><?= $hmeta['META_EDIT_COMMENT'] ?></p>

            </td>
            </tr>

            <?php
            $i++;
        endforeach;
        ?>

        </table>

        <!-- close historyblock -->
        </div>

        <?php
    endif;

    if (isset($display['diffs'])) :
        ?>

        <h2>Viewing diffs between:
        <span class="file1"><?= $_POST['comparefile1'] ?></span>
        and
        <span class="file2"><?= $_POST['comparefile2'] ?></span>
        </h2>

        <div class="fileboth">
        <pre><?php
        foreach($diff_list as $chunk_name => $chunk):
            if (strstr($chunk_name, 'EXT_')) {
                print'<br /><span style="font-weight:700">' . $data_field_labels[$chunk_name] . '</span>';
            }

            // must be printed - this is in a <pre> block
            // print($data_field_labels[$chunk_name]);

            foreach($chunk as $diff) :
                if ($diff['file'] == 'a') :
                    $class = 'file1';
                elseif ($diff['file'] == 'b') :
                    $class = 'file2';
                else:
                    $class = 'fileboth';
                endif;

                // special formatting for extended data chunks
                if (strstr($chunk_name, 'EXT_')) {

                    // we'll need this later so we can add some whitespace
                    // at the beginning of each record
                    if (empty($first_tag[$chunk_name])) {
                        if (preg_match('/\[\[(.+?)\]\]/s', $diff['line'], $matches) ) {
                            $first_tag[$chunk_name] = $matches[1];
                        }
                    }
                }

                $diff['line'] = htmlspecialchars($diff['line']);

                if (isset($first_tag[$chunk_name])) {
                    $diff['line'] = preg_replace("/\[\[($first_tag[$chunk_name])\]\]/s", "<br /><span style=\"font-weight:700\">$1:</span>", $diff['line']);
                }
                $diff['line'] = preg_replace('/^\[\[(.+?)\]\]/s', "<span style=\"font-weight:700\">$1:</span>", $diff['line']);

                // must be printed - this is in a <pre> block
                print("<div class='<?= $class ?>'>" . wordwrap($diff['line'],70) . '</div>');
            endforeach;
        endforeach;
        ?>
        </pre>
        </div>

        <?php
    endif;
    if (isset($display['edit_page'])) :
        ?>
        <div id="trackbackblock">

        <fieldset>
        <legend>Unmoderated trackbacks</legend>

        <?php
        if (!count($ext_list['EXT_UNMOD_TB'])):
            ?>
            No trackbacks to moderate
            <?php
        else:
             ?>

            <table>
            <tr>
            <th>
            Trackback
            </th>
            <th>
            Approve?
            </th>
            </tr>

            <?php
            $i = 0;
            foreach ($ext_list['EXT_UNMOD_TB'] as $key => $comment) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                ?>

                <tr class="<?=$rowclass ?>">
                <td>
                <p>
                <b>From <?= $comment['blog_name'] ?> (see link):</b><br/>
                <?= htmlentities(substr($comment['title'].": ".$comment['excerpt'],0, 100)) ?>...
                </p>

                <p>
                Posted by
                <?php
                if($comment['email']) :
                    ?>
                    <a href="mailto:<?= $comment['email'] ?>" title="Email: <?= $comment['email'] ?>"><?= $comment['name'] ?></a>
                    <?php
                else:
                    ?>
                    <?= $comment['name'] ?>
                    <?php
                endif;
                ?>

                at
                <?= $comment['time'] ?>

                <?php
                if ($comment['url']) :
                    ?>
                    <br />
                    <a href="<?= $comment['url'] ?>" title="URL: <?= $comment['url'] ?>">Link</a>
                    <?php
                endif;
                ?>
                </p>
                </td>

                <td style="text-align: center; vertical-align:top">
                <input type="checkbox" name="tb_moderation[]" value="<?= $key ?>" /> | <span class="link" onclick="checkall(this.parentNode.parentNode);">Check all below</span>
                </td>


                </tr>

                <?php
                $i++;
            endforeach;
            ?>

            </table>

            <input type="submit" name="action_tb_moderate" class="on" style="width:18em" value="Moderate" onclick="return confirm('Checked trackbacks are approved; unchecked trackbacks ARE DELETED. Proceed?');" />

            <?php
        endif;
        ?>

        </fieldset>

        <fieldset>
        <legend>Trackbacks Received</legend>

        <?php
        if (!count($ext_list['EXT_TRACKBACK'])):
            ?>

            <p>No trackbacks received</p>

            <?php
        else:
            ?>

            <table>
            <tr>
            <th>Title</th>
            <th>Blog</th>
            <th>Delete?</th>
            </tr>

            <?php
            $i = 0;
            foreach ($ext_list['EXT_TRACKBACK'] as $key => $trackback) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                ?>

                <tr class="<?=$rowclass ?>">
            
                <td>
                <a href="<?= $trackback['url'] ?>" target="_blank"><?= $trackback['title'] ?></a>
                </td>

                <td style="vertical-align:top">
                <?= $trackback['blog_name'] ?>
                </td>

                <td style="text-align: center; veritcal-align:top">
                <input type="checkbox" name="trackbacks[]" value="<?= $key ?>" /> | <span class="link" onclick="checkall(this.parentNode.parentNode);">Check all below</span>
                </td>

                </tr>

                <tr class="<?=$rowclass ?>">
                <td colspan="3">

                <p><?= $trackback['excerpt'] ?><br />
                Received: <?= $trackback['time'] ?></p>

                </td>
                </tr>

                <?php
                $i++;
            endforeach;
            ?>

            </table>

            <input type="submit" name="action_delete_trackbacks" class="on" style="width:18em" value="Delete Trackbacks" onclick="return confirm('This will delete all checked trackbacks. Are you sure?');" />

            <?php
        endif;
        ?>

        </fieldset>

        <fieldset>
        <legend>Trackbacks Sent</legend>

        <?php
        if (!count($ext_list['EXT_TRACKBACK_SENT'])):
            ?>
            <p>No trackbacks sent</p>
            <?php
        else:
            ?>

            <table>
            <tr>
            <th>URL</th>
            <th>Date</th>
            </tr>

            <?php
            $i = 0;
            foreach ($ext_list['EXT_TRACKBACK_SENT'] as $key => $trackback) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                ?>

                <tr class="<?=$rowclass ?>">
            
                <td>
                <?= $trackback['ping'] ?>
                </td>

                <td>
                <?= $trackback['time'] ?>
                </td>
            
                </tr>

                <?php
                $i++;
            endforeach;
            ?>

            </table>

            <?php
        endif;
        ?>

        </fieldset>

        <fieldset>
        <legend>Send Trackback Ping</legend>
        <input type="text" style="width:90%;" name="ping" />
        <br />
        <input type="submit" name="action_send_ping" class="on" style="width:18em" value="Send Trackback" />
        </fieldset>

        <fieldset>
        <legend>Moderation</legend>

        <?php
        if (!count($ext_list['EXT_UNMODERATED'])):
            ?>
            No comments to moderate
            <?php
        else:
             ?>

            <table>
            <tr>
            <th>
            Post
            </th>
            <th>
            Approve?
            </th>
            </tr>

            <?php
            $i = 0;
            foreach ($ext_list['EXT_UNMODERATED'] as $key => $comment) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                ?>

                <tr class="<?=$rowclass ?>">
                <td>
                <p>
                <?= htmlentities(substr($comment['body'],0, 100)) ?>...
                </p>

                <p>
                Posted by
                <?php
                if($comment['email']) :
                    ?>
                    <a href="mailto:<?= $comment['email'] ?>" title="Email: <?= $comment['email'] ?>"><?= $comment['name'] ?></a>
                    <?php
                else:
                    ?>
                    <?= $comment['name'] ?>
                    <?php
                endif;
                ?>

                at
                <?= $comment['time'] ?>

                <?php
                if ($comment['url']) :
                    ?>
                    <br />
                    <a href="<?= $comment['url'] ?>" title="URL: <?= $comment['url'] ?>">Link</a>
                    <?php
                endif;
                ?>
                </p>
                </td>

                <td style="text-align: center; vertical-align:top">
                <input type="checkbox" name="moderation[]" value="<?= $key ?>" /> | <span class="link" onclick="checkall(this.parentNode.parentNode);">Check all below</span>
                </td>


                </tr>

                <?php
                $i++;
            endforeach;
            ?>

            </table>

            <input type="submit" name="action_moderate" class="on" style="width:18em" value="Moderate" onclick="return confirm('Checked comments are approved; unchecked comments ARE DELETED. Proceed?');" />

            <?php
        endif;
        ?>

        </fieldset>

        <fieldset>
        <legend>Comments</legend>

        <?php
        if (!count($ext_list['EXT_COMMENTS'])):
            ?>
            No comments to display
            <?php
        else:
            ?>

            <table>
            <tr>
            <th>
            Post
            </th>
            <th>
            Delete?
            </th>
            </tr>

            <?php
            $i = 0;
            foreach ($ext_list['EXT_COMMENTS'] as $key => $comment) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                ?>

                <tr class="<?=$rowclass ?>">
                <td>
                <p> 
                <?= htmlentities(substr($comment['body'],0, 100)) ?>...
                </p>

                <p>
                Posted by
                <?php
                if($comment['email']) :
                    ?>
                    <a href="mailto:<?= $comment['email'] ?>" title="Email: <?= $comment['email'] ?>"><?= $comment['name'] ?></a>
                    <?php
                else:
                    ?>
                    <?= $comment['name'] ?>
                    <?php
                endif;
                ?>

                at
                <?= $comment['time'] ?>

                <?php
                if ($comment['url']) :
                    ?>
                    <br />
                    <a href="<?= $comment['url'] ?>" title="URL: <?= $comment['url'] ?>">Link</a>
                    <?php
                endif;
                ?>
                </p>
                </td>

                <td style="text-align: center; vertical-align:top">
                <input type="checkbox" name="comments[]" value="<?= $key ?>" /> | <span class="link" onclick="checkall(this.parentNode.parentNode);">Check all below</span>
                </td>
            
                </tr>

                <?php
                $i++;
            endforeach;
            ?>

            </table>

            <input type="submit" name="action_delete_comments" class="on" style="width:18em" value="Delete Comments" onclick="return confirm('This will delete all checked comments. Are you sure?');" />

            <?php
        endif;
        ?>

        </fieldset>
        </div>

        <?php
    endif;
    // the block below is always displayed on admin pages
    ?>

    <!-- close editcolumn -->
    </div>

    <div id="controlcolumn">

    <div id="loginblock">
    <p>
    <em>Logged in:</em> <?= $current_user['name'] ?>
    &nbsp;&nbsp;
    <a href="<?= $_SERVER['PHP_SELF'] ?>?action_logout=1">Logout</a>
    </p>
    </div>


    <p id="lockmessage">
    Other users will be warned until <?= date($CFG['dateformat'], $lock_info['created']) ?> that you are editing this page.
    </p>

    <?php
    // messages
    if (isset($display['message'])) :
        ?>

        <div id="messageblock">

        <?php
        if ($err) :
            ?>

            <p class="error">
            An error was encountered:
            </p>

            <?php
        endif;
        ?>

        <p>
        <em><?= $message ?></em>
        </p>

        <?php
        if (isset($display['message_upload'])) :
            if (!count($upload_status)) :
                ?>

                <p>No files were specified for upload.</p>

                <?php
            else:
                ?>
                <ul class="messagelist">
                <?php
                foreach($upload_status as $file_key => $status) :
                    if ($status['status']) :
                        ?>

                        <li><?= $status['name'] ?> uploaded successfully.</li>

                        <?php
                    else:
                        ?>

                        <li><?= $status['name'] ?> upload failed: <?= $status['msg'] ?>.</li>

                        <?php
                    endif;
                endforeach;
                ?>
                <!-- close messagelist -->
                </ul>
                <?php
            endif;
        endif;
        ?>

        <!-- close messageblock -->
        </div>

        <?php
    endif;
    ?>

    <fieldset>
    <legend>Actions</legend>


    <?php
    if (isset($display['edit_page'])) :

        $class = isset($_COOKIE['action_save_state'])
            ? $_COOKIE['action_save_state']
            : 'off';
        ?>

        <input type="submit" name="action_save" value="Submit Changes" class="<?= $class ?>" onclick="if (button_status['submit_button']) { buttonOff('submit_button', 'action_save'); return true; } else { return false; }" id='submit_button' />
        <input type="submit" name="action_preview" value="Preview in New Window" class="on" />

        <input type="button" name="action_view_history" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_history=1';return false;" value="Page History" class="on" />
        <input type="button" name="action_edit_messages" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit_messages=1';return false;" class="on" value="Edit Messages" />
        <input type="button" name="action_cancel" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" value="Cancel Edit" class="on" />

    <?php
    elseif (isset($display['edit_messages'])) :
        ?>

        <input type="submit" name="action_save_messages" value="Submit Changes" class="off" onclick="return button_status['submit_button'];" id='submit_button' />

        <input type="button" name="action_edit" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit=1';return false;" class="on" value="Edit Page" />
        <input type="button" name="action_view_history" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_history=1';return false;" value="Page History" class="on" />
        <input type="button" name="action_cancel" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" value="Cancel Edit" class="on" />

        <?php
    elseif (isset($display['history'])) :
        ?>

        <input type="submit" name="action_view_diffs" value="Run Diffs" class="on" />
        <input type="submit" class="on" name="action_delete_history" value="Delete Checked" onclick="return confirm('Are you sure? This cannot be undone!');" />

        <input type="button" name="action_edit" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit=1';return false;" class="on" value="Edit Page" />
        <input type="button" name="action_edit_messages" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit_messages=1';return false;" class="on" value="Edit Messages" />
        <input type="button" name="action_cancel" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_page=1';return false;" class="on" value="Current Page" />

        <?php

    elseif (isset($display['diffs'])) :
        ?>

        <input type="button" name="action_edit" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit=1';return false;" class="on" value="Edit Page" />
        <input type="button" name="action_view_history" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_history=1';return false;" class="on" value="Return to History" />
        <input type="button" name="action_edit_messages" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit_messages=1';return false;" class="on" value="Edit Messages" />
        <input type="button" name="action_cancel" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" class="on" value="Current Page" />

        <?php
    else :
        ?>

        <input type="button" name="action_edit" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit=1';return false;" class="on" value="Edit Page" />
        <input type="button" name="action_view_history" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_view_history=1';return false;" class="on" value="Page History" />
        <input type="button" name="action_edit_messages" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_edit_messages=1';return false;" class="on" value="Edit Messages" />
        <input type="button" name="action_cancel" onclick="document.location='<?= $_SERVER['PHP_SELF'] ?>?action_cancel=1';return false;" class="on" value="Current Page" />

        <?php
    endif;
    ?>

    </fieldset>

    <?php
    if (isset($display['edit_page'])) :
        ?>

        <div id="statusblock">

        <table class="statustable">

        <tr>
        <td><em>Filename:</em></td>
        <td><?= $file['basename'] ?></td>
        </tr>

        <tr>
        <td><em>Page Size:</em></td>
        <td><?= $page_info['total']['size'] ?> characters</td>
        </tr>

        <tr>
        <td><em>Lines:</em></td>
        <td><?= $page_info['total']['lines'] ?></td>
        </tr>

        <tr>
        <td><em>Word Count:</em></td>
        <td><?= $page_info['total']['words'] ?></td>
        </tr>
        </table>

        <!-- close statusblock -->
        </div>

        <?php
        if (isAuth($auth_actions, $current_user, 'send_notification_pings')) :
            ?>

            <fieldset>
            <legend>Send Notification Pings</legend>
            <input type="checkbox" name="ping_technorati"> Technorati<br />
            <input type="checkbox" name="ping_blogs"> blo.gs<br />
            <br />
            <input type="submit" name="action_send_notification_pings" class="on" value="Send Notification" />
            </fieldset>

            <?php
        endif;

        if (isAuth($auth_actions, $current_user, 'rename_file')) :
            ?>

            <fieldset>
            <legend>Rename file</legend>
            <input type="text" name="newfilename" />.<?= $file['extension'] ?>
            <br />
            <input type="submit" class="on" name="action_rename_file" value="Rename" />
            </fieldset>

            <?php
        endif;
        ?>

        <?php
        if (isAuth($auth_actions, $current_user, 'create_page')) :
            ?>

            <fieldset>
            <legend>Create new page</legend>
            <input type="text" name="createpagename" />.<?= $file['extension'] ?>
            <br />
            <input type="submit" class="on" name="action_create_page" value="Create" />
            </fieldset>

            <?php
        endif;
        ?>

        <fieldset>
        <legend>Convert tabs to spaces?</legend>
        <input type="radio" name="expand_tabs" value="1" checked="checked" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;Yes
        <input type="radio" name="expand_tabs" value="0" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;No
        </fieldset>

        <fieldset>
        <legend>Trim whitespace?</legend>
        <input type="radio" name="rtrim" value="1" checked="checked" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;Yes
        <input type="radio" name="rtrim" value="0" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;No
        </fieldset>

        <fieldset>
        <legend>Tidy HTML?</legend>
        <input type="radio" name="clean_html" value="1" checked="checked" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;Yes
        <input type="radio" name="clean_html" value="0" onchange="buttonOn('submit_button', 'action_save');" /> &nbsp;No
        </fieldset>


        <fieldset>
        <legend>Images in <?= $file['imagedir'] ?>/:</legend>

        <?php
        if (!count($images)) :
            ?>
            <p>No images uploaded.</p>
            <?php
        else:
            ?>

            <table>
            <tr>
            <th>Filename</th>
            <th>Delete?</th>
            </tr>

            <?php
            // output existing images
            $i = 0;
            foreach($images as $image) :
                $rowclass = $i % 2 ? 'tr2' : 'tr1';
                $i++;
                ?>

                <tr class="<?= $rowclass ?>">
                <td>
                <a href="<?= $file['imagedir'] . '/' . $image ?>" target="_blank"><?= $image ?></a>
                </td>
        
                <td style="text-align:right">
                <input type="checkbox" name="del_images[<?= $i ?>]" value="<?= $image ?>" />
                </td>
                </tr>

                <?php
            endforeach;
            ?>

            </table>
            <input type="submit" class="on" name="action_delete_images" value="Delete Checked" />
            <?php
        endif;
        ?>

        </fieldset>
   
        <?php
        $image_count = count($images);
        // if this group has no limit on images, set $image_count to zero
        if (in_array($current_user['group'], $no_image_limit)) {
            $image_count = 0;
        }

        if ($image_count < $CFG['image_upload_limit']) :
            ?>

            <fieldset>
            <legend>Upload additional images</legend>

            <?php
            while ($image_count < $CFG['image_upload_limit']) :
                ?>

                <input type="file" name="image-<?= $image_count ?>" />
                <br/>

                <?php
                $image_count++;
            endwhile;
            ?>

            <input type="submit" class="on" name="action_upload_images" value="Upload Images" />
            </fieldset>

            <?php
        endif;
    endif;
    ?>

    <!-- close controlcolumn -->
    </div>

    </form>

    <?php
endif;
?>

<div style="clear:both">&nbsp;</div>
</body>
</html>
<?php
// end page footer

/**
 * End Administration Page Generation
 */

/* DATA */
/* VERSION */
// 0.8
/* VERSION */

/* PAGE_HEADER */
// <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
// <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
// <head>
// <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/> 
// <title>%%TITLE%%</title>
// __META_RSS_AUTODISCOVERY__
// __TRACKBACK_RDF__
// </head>
// <body>
/* PAGE_HEADER */

/* PAGE_MAIN_CONTENT */
// <p>
// __RSS_FEED__
// </p>
// <p>
// __RSS_DIFF_FEED__
// </p>
/* PAGE_MAIN_CONTENT */

/* PAGE_FOOTER */
// __HIDDEN_AREA_BUTTON__
// __HIDDEN_AREA_START__
// __COMMENTS__
// __COMMENT_FORM__
// __TRACKBACKS__
// __HIDDEN_AREA_END__
// __EDIT_BUTTON__
// <div style="clear:both">&nbsp;</div>
// </body>
// </html>
/* PAGE_FOOTER */

/* META_EDITOR */
// 
/* META_EDITOR */

/* META_EDITOR_EMAIL */
// 
/* META_EDITOR_EMAIL */

/* META_EDIT_COMMENT */
// 
/* META_EDIT_COMMENT */

/* META_PAGE_TITLE */
// %%TITLE%%
/* META_PAGE_TITLE */

/* MSG_AUTH_FAILED */
// You are not logged in, or not authorized for this action.
/* MSG_AUTH_FAILED */

/* EXT_TRACKBACK */
// 
/* EXT_TRACKBACK */

/* EXT_TRACKBACK_SENT */
// 
/* EXT_TRACKBACK_SENT */

/* EXT_COMMENTS */
// 
/* EXT_COMMENTS */
/* DATA */
/* ETP_FOOT */
?>
