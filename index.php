<?
require_once 'lib/session.inc.php';
require_once 'lib/db.inc.php';
require_once 'lib/site.inc.php';

$user = User::require_login();

if ($_SERVER['PATH_INFO']) {
	$game = Game::from_path($_SERVER['PATH_INFO']);
	$sub = Subject::from_path($_SERVER['PATH_INFO']);
}

ob_start();
?>
HOME
<?
page([], head(), pheader(), ob_get_clean(), pfooter());

?>
