<?

require_once 'base.inc.php';

function session($duration_sec) {
    session_start();

    ini_set('session.cookie_lifetime', $duration_sec);
    ini_set('session.gc_maxlifetime', $duration_sec);

    if (isset($_SESSION['last_activity']) && ($_SERVER['REQUEST_TIME'] - $_SESSION['last_activity']) > $duration_sec || !isset($_SESSION['last_activity'])) {
        session_unset();
        session_destroy();
        session_start();
    }

    $_SESSION['last_activity'] = $_SERVER['REQUEST_TIME'];
}

function session_ids() {
    $ids = [];
    $files = scandir(session_save_path());
    foreach ($files as $file) {
        $id = str_replace("sess_", "", $file);
        if (strpos($id, ".") === false) {
            $ids[] = $id;
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
    $orig_id = session_id();
    session_abort();
    foreach (session_ids() as $id) {
        if ($id == $orig_id) {
            continue;
        }
        session_id($id);
        session_start(['use_cookies' => false,
                       'use_only_cookies' => true]);
        // header_remove("Set-Cookie");
        if (isset($_SESSION['user']) && $_SESSION['user'] == $user_id) {
            session_destroy();
        } else {
            session_abort();
        }
    }
    session_id($orig_id);
    session_start();
}

session(3600 * 24); // 1 day

?>
