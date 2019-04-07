<?
require_once 'lib/session.inc.php';
require_once 'lib/db.inc.php';
require_once 'lib/site.inc.php';

$user = User::require_login();
$user->load_games();

if (isset($_SERVER['PATH_INFO'])) {
    $game = Game::from_path($_SERVER['PATH_INFO']);
    $subj = Subject::from_path($_SERVER['PATH_INFO']);
}

ob_start();
if (isset($subj)) {
    $title = $subj->name . ' | RPG Wiki';
    if ($user->games[$game->id]['role'] == 'gm') {
        echo $subj->content('gmpriv');
    }
    echo $subj->content('gmpub');
    echo $subj->content('plr');
} else {
    $title = 'Home | RPG Wiki';
    ?>
<h1>Your Games</h1>
<ul>
    <? foreach ($user->games as $game_arr): ?>
        <? $game = Game::load(['id' => $game_arr['id']]); ?>
        <li><a href="<?=$game->url()?>"><?=$game->name?></a> (<?=$game_arr['role']?>)</li>
    <? endforeach; ?>
</ul>
    <?
}
page([], head(['title' => $title]), pheader(), ob_get_clean(), pfooter());

?>
