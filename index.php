<?
require_once 'lib/session.inc.php';
require_once 'lib/db.inc.php';
require_once 'lib/site.inc.php';

$user = User::require_login();

if ($_SERVER['PATH_INFO']) {
	$game = Game::from_path($_SERVER['PATH_INFO']);
	$subj = Subject::from_path($_SERVER['PATH_INFO']);
}

if (isset($subj)) {
	$title = $subj->name . ' | RPG Wiki';
} else {
	$title = 'Home | RPG Wiki';
}

ob_start();
?>
HOME
<?
page([], head(['title' => $title]), pheader(), ob_get_clean(), pfooter());

?>
