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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="a[...]
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"><[...]
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
        <h2 class="card-title">Notify Users on Page Changes</h2>
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
        <div class="form-check mb-4">
            <input type="checkbox" name="email_users" id="email_users" class="form-check-input"<?= $CFG['email_users'] == "true" ? ' checked' : ''?>>
            <label for="email_users" class="form-check-label">
                <strong>Should the users (<?= $ulist ?>) be emailed when a page has been modified?</strong>
            </label>
        </div>

        <h3>Set Administrator Email</h3>
        <div class="alert alert-info">
        If Email Notifications is enabled, this address will be the address notifications will appear
        to be from, and the address used for replies to that email.
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="admin_name" class="form-label">Name</label>
                <input type="text" name="admin_name" id="admin_name" class="form-control" value="<?= htmlClean($CFG['admin_name']) ?>">
            </div>
            <div class="col-md-6">
                <label for="admin_email" class="form-label">Email</label>
                <input type="email" name="admin_email" id="admin_email" class="form-control" value="<?= htmlClean($CFG['admin_email']) ?>">
            </div>
            <div class="col-12">
                <label for="admin_notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                <div class="form-text mb-2">
                    Optionally, add a note regarding the administrator. This is not used in the script,
                    it is only saved in the configuration section for later reference.
                </div>
                <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"><?= htmlClean($CFG['admin_notes']) ?></textarea>
            </div>
        </div>
        <?php
        else:
        ?>
        <div class="alert alert-warning">No email addresses were entered. The Notify Users feature is redundant.</div>
        <?php
        endif;
        ?>

        <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['email_notifications'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Next »</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
        </div>
        </div>

        </form>

        <?php
    break;

    case $steps['page_name'] :
        ?>
        <h2 class="card-title">Page Name</h2>

        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['page_name'] ?>" />
        <input type="hidden" name="process" value="1" />

        <?php

        if ($upgrade_performed == true || $upgrade_performed == "true") {
            ?>
            <div class="alert alert-warning">
                <strong>Upgrading:</strong> You are performing an upgrade, and thus the individual pages will not be affected. 
                There is currently <strong>no way</strong> to upgrade the actual pages themselves (what is being upgraded is the 
                'editthispage_XYZ.php' file, which contains the EditThisPage code). If you would rather make a fresh install, 
                move aside or delete the 'editthispage_*' files out of the installation directory AND the install-etp.php file, 
                put a fresh copy of install-etp.php in the directory and rerun the installation.
            </div>
            <?php
        } else {
        if (isset($msgs['failed_page_name']) && $msgs['failed_page_name']) :
            ?>
            <?php
            if ($msgs['failed_page_name_empty']) :
                ?>
                <div class="alert alert-danger">You need to set a name for the new page.</div>
                <?php
            endif;
            if ($msgs['failed_page_name_exists']) :
                ?>
                <div class="alert alert-danger">A file with that name already exists.</div>
                <?php
            endif;
            if ($msgs['failed_page_name_no_extension']) :
                ?>
                <div class="alert alert-danger">Please include an extension for the name your page will be saved under (e.g. 'index.php').</div>
                <?php
            endif;
        endif;
        if (!$page_name) $page_name = "index.php";
        ?>

        <div class="mb-3">
            <label for="pagename" class="form-label">Page Filename</label>
            <input type="text" name="pagename" id="pagename" class="form-control" value="<?= @$page_name ?>" />
            <div class="form-text">
                The name your page will be saved under. This should include the extension, but not the path to the file, e.g. "index.php".
            </div>
        </div>

        <div class="mb-3">
            <label for="page_title" class="form-label">Page Title</label>
            <input type="text" name="page_title" id="page_title" class="form-control" value="<?= @$page_title ?>" />
            <div class="form-text">
                The title for your page. This will be displayed in the title bar of the browser and RSS feeds.
            </div>
        </div>
        <?php } ?>

        <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['page_name'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Next »</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
        </div>
        </div>

        </form>

        <?php
    break;

    case $steps['rss_setup'] :
        ?>

        <h2 class="card-title">RSS Options</h2>
        <form action="<?= $PHP_SELF ?>" method="post">
        <input type="hidden" name="install_step" value="<?= $steps['rss_setup'] ?>" />
        <input type="hidden" name="process" value="1" />
        
        <div class="alert alert-info">The RSS shows recent versions of your page.</div>
        
        <div class="row g-3">
            <div class="col-md-6">
                <label for="feed_title" class="form-label">Feed Title</label>
                <input type="text" name="feed_title" id="feed_title" class="form-control" value="<?= !$RSS['title'] ? $page_title . ' RSS Title' : htmlClean($RSS['title']) ?>">
            </div>
            <div class="col-md-6">
                <label for="feed_description" class="form-label">Feed Description</label>
                <input type="text" name="feed_description" id="feed_description" class="form-control" value="<?= !$RSS['description'] ? $page_title . ' RSS Feed' : htmlClean($RSS['description']) ?>">
            </div>
        </div>

        <h3 class="mt-4">RSS Diff Feed Options</h3>
        <div class="alert alert-info">The RSS diff feed shows recent additions and deletions to your page. Set the feed title and description here.</div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="feed_title_diff" class="form-label">Diff Feed Title</label>
                <input type="text" name="feed_title_diff" id="feed_title_diff" class="form-control" value="<?= !$RSS['title_diff'] ? $page_title . ' Changes' : htmlClean($RSS['title_diff']) ?>">
            </div>
            <div class="col-md-6">
                <label for="feed_description_diff" class="form-label">Diff Feed Description</label>
                <input type="text" name="feed_description_diff" id="feed_description_diff" class="form-control" value="<?= !$RSS['description_diff'] ? $page_title . ' Changes Feed' : htmlClean($RSS['d[...]
            </div>
        </div>

        <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['rss_setup'] ?>'">« Back</button>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Next »</button>
            <button type="submit" name="finish" class="btn btn-success">Finish</button>
        </div>
        </div>

        </form>

        <?php
    break;

case $steps['plugins']:
?>
<form action="<?= $PHP_SELF ?>" method="post">
<input type="hidden" name="install_step" value="<?= $steps['plugins'] ?>" />
<input type="hidden" name="process" value="1" />
<h2 class="card-title">Plugins</h2>
<div class="alert alert-info">The plugins listed below will be installed, as they are located in the "plugin" directory. Plugins may report errors here, as well, in case they discover that they will n[...]
<ul class="list-group list-group-flush">
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
            $statusBadge = $PLUGIN_ERROR ? '<span class="badge bg-danger me-2">✗</span>' : '<span class="badge bg-success me-2">✓</span>';
            $statusText = $PLUGIN_ERROR ? '<span class="text-danger">' . $PLUGIN_ERROR . '</span>' : '<span class="text-success">OK</span>';
            echo "<li class='list-group-item d-flex align-items-center'>$statusBadge <strong>" . $PLUGIN_NAME . "</strong> (v" . $PLUGIN_VER . ") [$entry] → $statusText</li>";
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
if ($notaplug) { 
    echo "<li class='list-group-item text-muted'><span class='badge bg-secondary me-2'>i</span>The following files do not claim that they are plugins, which may mean they are support files for one: <s[...]
}
if ($unwritable) { 
    echo "<li class='list-group-item text-danger'><span class='badge bg-danger me-2'>✗</span>The following plugins require that the install script can modify the plugin files directly, but the files[...]
}
if (!$got_plugs) { 
    echo "<li class='list-group-item text-muted'><span class='badge bg-light text-dark me-2'>-</span>No plugins were found.</li>"; 
}
?>  
</ul>

<?php if ($got_error): ?>
<div class="alert alert-danger mt-3">
There were errors in at least one of the plugins listed above. ETP will still attempt to install the plugin in question, but there's of course the chance that things crash and burn if you don't fix th[...]
</div>
<?php else: ?>
<div class="alert alert-info mt-3">
If a plugin is not listed above, you need to copy its .php file to the plugin directory and then reload this page. If a plugin is listed that you do not want, delete the file from the "plugin" directo[...]
</div>
<?php endif; ?>

<div class="d-flex justify-content-between gap-2 mt-4">
<button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['plugins'] ?>'">« Back</button>
<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Next »</button>
    <button type="submit" name="finish" class="btn btn-success">Finish</button>
</div>
</div>

</form>
<?php
break;

    case $steps['confirm']:
        ?>


        <?php
        if ($msgs['failed_required_settings']) :
            ?>
            <h2 class="card-title text-danger">Configuration Error</h2>
            <div class="alert alert-danger">Not all required settings have been configured:</div>

            <?php
            if ($msgs['no_hostname']) :
                ?>
                <div class="alert alert-warning">
                You need to configure your hostname.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['hostname'] ?>" class="btn btn-sm btn-primary">Set Hostname</a>
                </div>
                <?php
            endif;
            if ($msgs['no_user_accounts']) :
                ?>
                <div class="alert alert-warning">
                No user accounts have been created; your page will not be accessible.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['create_accounts'] ?>" class="btn btn-sm btn-primary">Create Accounts</a>
                </div>
                <?php
            endif;
            if ($msgs['no_page_name']) :
                ?>
                <div class="alert alert-warning">
                You need to set a file name for your page.<br />
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['page_name'] ?>" class="btn btn-sm btn-primary">Set Page Name</a>
                </div>
                <?php
            endif;
            ?>
            <?php
        else:
            ?>

            <h2 class="card-title">Confirm Settings</h2>

            <div class="alert alert-info">These user accounts have been created:</div>

            <table class="table table-striped table-hover">
            <thead class="table-light">
            <tr>
            <th>Username</th>
            <th>Group</th>
            <th>Email</th>
            </tr>
            </thead>
            <tbody>

            <?php
            foreach ($users as $k => $v):
                $badgeClass = $v['group'] == 'super-editor' ? 'bg-success' : 
                              ($v['group'] == 'editor' ? 'bg-primary' : 'bg-secondary');
                ?>
                <tr>
                <td><?= $v['name'] ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $v['group'] ?></span></td>
                <td><?= $v['email'] ?></td>
                </tr>

                <?php
            endforeach;
            ?>
            </tbody>
            </table>

            <?php
            if ($msgs['no_super_editor']) :
                ?>
                <div class="alert alert-warning">
                <strong>No super-editor account was found.</strong> You can add one in
                <a href="<?= $PHP_SELF ?>?install_step=<?= $steps['create_accounts'] ?>" class="btn btn-sm btn-primary">Create Accounts</a>.
                If no super-editor account is created, some functions will not be available.
                </div>
                <?php
            endif;
            ?>

            <div class="alert alert-success">
            Your page will be saved as <strong><?= $page_name ?></strong>
            </div>

            <p>
            If you are satisfied with these settings, click Install to complete your installation.
            </p>

            <form action="<?= $PHP_SELF ?>" method="post">
            <input type="hidden" name="install_step" value="<?= $next_steps['confirm'] ?>" />
            <input type="hidden" name="process" value="1" />
            <div class="d-flex justify-content-between gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" onclick="document.location='<?= $PHP_SELF ?>?install_step=<?= $prev_steps['confirm'] ?>'">« Back</button>
            <button type="submit" class="btn btn-success btn-lg">Install »</button>
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
// Use a safe fallback chain to avoid undefined index notices (fixes SERVER_ADDR warning
// and prevents header output which causes session_start() to fail later).
$CFG['server_address'] = isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR']
    ? $_SERVER['SERVER_ADDR']
    : (
        isset($_SERVER['LOCAL_ADDR']) && $_SERVER['LOCAL_ADDR']
            ? $_SERVER['LOCAL_ADDR']
            : (
                isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']
                    ? $_SERVER['REMOTE_ADDR']
                    : (isset($_SERVER['SERVER_NAME']) ? gethostbyname($_SERVER['SERVER_NAME']) : '127.0.0.1')
            )
    );

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

// Ensure LOCK_VARS is an array to avoid "Automatic conversion of false to array" deprecation
// (this prevents code that does $LOCK_VARS[$k] = $v; from triggering a deprecation notice).
$LOCK_VARS = array();

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

...
/* rest of file unchanged */
