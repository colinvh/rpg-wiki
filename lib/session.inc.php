<?
require_once 'base.inc.php';

define('SESSION_DURATION_SEC', 60 * 60 * 24); // 1 day

(function() {
    ini_set('session.cookie_lifetime', SESSION_DURATION_SEC);
    ini_set('session.gc_maxlifetime', SESSION_DURATION_SEC);

    session_start();

    $destroy = false;
    if (isset($_SESSION['last_activity'])) {
        if ($_SERVER['REQUEST_TIME'] - $_SESSION['last_activity'] > SESSION_DURATION_SEC) {
            $destroy = true;
        }
    } else {
        if (!empty($_SESSION)) {
            $destroy = true;
        }
    }
    if ($destroy) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['last_activity'] = $_SERVER['REQUEST_TIME'];
})();

function session_ids() {
    $ids = [];
    $path = session_save_path();
    $uid = posix_getuid();
    foreach (scandir($path) as $file) {
        $id = str_replace("sess_", "", $file);
        if (strpos($id, ".") === false) {
            try {
                if (fileowner("$path/$file") == $uid) {
                    // if (is_writable("$path/$file")) {
                        $ids[] = $id;
                    // }
                }
            } catch (ErrorException $ex) {
                ;
            }
        }
    }
    return $ids;
}

function read_session_data() {
    $orig_id = session_id();
    $sess = [];
    foreach (session_ids() as $id) {
        session_id($id);
        session_start(['use_cookies' => false,
                       'use_only_cookies' => true]);
        $sess[$id] = $_SESSION;
        session_abort();
    }
    session_id($orig_id);
    session_start();
    return $sess;
}

function replace_session_data($id, $data) {
    $orig_id = session_id();
    session_id($id);
    session_start(['use_cookies' => false,
                   'use_only_cookies' => true]);
    $_SESSION = $data;
    session_write_close();
    session_id($orig_id);
    session_start();
}

function expire_sessions($user_id) {
    return;
    $orig_id = session_id();
    session_abort();
    foreach (session_ids() as $id) {
        if ($id == $orig_id) {
            continue;
        }
        session_id($id);
        try {
            session_start(['use_cookies' => false,
                           'use_only_cookies' => true]);
            if (isset($_SESSION['user']) && $_SESSION['user'] == $user_id) {
                session_destroy();
            } else {
                session_abort();
            }
        } catch (ErrorException $ex) {
            $message = $ex->getMessage();
            if (substr($message, -22) != 'Permission denied (13)') {
                error_log($message);
            }
        }
    }
    session_id($orig_id);
    session_start();
}

?>
