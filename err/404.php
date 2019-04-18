<?
require_once '../lib/main.inc.php';

$user = User::from_session();

ob_start();
?>
<section class="http-error">
    <h1>Not Found</h1>
    <h2>HTTP error 404</h2>
    <p>The requested resource was not found.</p>
</section>
<?
page_std([], head(['title' => 'Not Found | RPG Wiki']), pheader(), psidebar(), [ob_get_clean()], pfooter());

?>
