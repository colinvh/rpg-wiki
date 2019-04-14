<?
require_once 'credentials.inc.php';

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

class NotFoundException extends Exception {}
class InvalidURLException extends Exception {}

function errlog(...$vars) {
    ob_start();
    var_dump(...$vars);
    error_log(ob_get_clean());
}

function respond_err($code=500) {
    http_response_code($code);
    require "{$_SERVER['DOCUMENT_ROOT']}/err/$code.php";
    exit_clean();
}

function respond_redirect($dest, $code=302) {
    http_response_code($code);
    header("Location: $dest");
    exit_clean();
}

function exit_clean() {
    session_write_close();
    exit();
}

function db_conn() {
    static $conn;
    if (!isset($conn)) {
        $conf = db_conf();
        $conn = new mysqli($conf['server'], $conf['user'], $conf['pass'], $conf['db']);
        if ($conn->connect_errno) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset('utf8');

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ALL & ~MYSQLI_REPORT_INDEX;
    }
    return $conn;
}

?>
