<?
require_once 'lib/session.inc.php';
require_once 'lib/Game.class.php';
require_once 'lib/User.class.php';
require_once 'lib/site.inc.php';

$user = User::require_login();
$user->load_games();

ob_start();
$title = 'Home | RPG Wiki';
?>
<div class="index">
<h1>Your Games</h1>
<ul>
    <? foreach ($user->games as $game_arr): ?>
        <? $game = Game::load(['id' => $game_arr['id']]); ?>
        <li><a href="<?=$game->url()?>"><?=$game->name?></a> (<?=$game_arr['role']?>)</li>
    <? endforeach; ?>
</ul>
</div>
<?
page_std([], head(['title' => $title]), pheader(), psidebar(), [ob_get_clean()], pfooter());

?>
