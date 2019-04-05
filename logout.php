<?
require_once 'lib/session.inc.php';
unset($_SESSION['user']);
header('Location: /');
?>
