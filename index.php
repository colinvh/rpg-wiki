<?
require_once 'lib/db.inc.php';

$user = User::require_login();

if ($_SERVER['PATH_INFO']) {
	$game = Game::from_path($_SERVER['PATH_INFO']);
	$sub = Subject::from_path($_SERVER['PATH_INFO']);
}

?>
