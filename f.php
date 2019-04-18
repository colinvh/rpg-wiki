<?
require_once 'lib/main.inc.php';
require_once 'lib/File.class.php';

$user = User::require_login();

$file = null;
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', $_SERVER['PATH_INFO']);
    if (end($parts) && end($parts)[0] == '_') {
        $verb = array_pop($parts);
        $_SERVER['PATH_INFO'] = implode('/', $parts);
    }
    $file = File::from_path($_SERVER['PATH_INFO']);
}
if (!$file) {
    respond_err(404);
}

if (isset($_GET['dl']) && $_GET['dl']) {
    header('Content-Disposition: attachment; filename="' . basename($file->_url) . '"');
}
echo $file->contents();

?>
