<?
require_once 'lib/session.inc.php';

unset($_SESSION['user']);
respond_redirect('/');

?>
