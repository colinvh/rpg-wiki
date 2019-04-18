<?
require_once '../lib/main.inc.php';

$user = User::from_session();

ob_start();
?>
<section class="http-error">
    <h1>Forbidden</h1>
    <h2>HTTP error 403</h2>
    <p>The requested resource requires authentication.</p>
</section>
<?
page_std([], head(['title' => 'Forbidden | RPG Wiki']), pheader(), psidebar(), [ob_get_clean()], pfooter());

?>
