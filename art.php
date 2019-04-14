<?
require_once 'lib/session.inc.php';
require_once 'lib/site.inc.php';
require_once 'lib/Subject.class.php';
require_once 'lib/User.class.php';

$user = User::require_login();
$user->load_games();

$head = [];
$body = [];

function show_rev($rev, $promote=true) {
    $user = User::load(['id' => $rev->author]);
    ob_start();
    ?>
<div>
    <?=$rev->date?>
    by <a href="<?=$user->url()?>"><?=$user->nickname?></a>
    <? if ($promote): ?>
        (<a href="_pro?rev=<?=$rev->id?>">promote to current</a>)
    <? endif; ?>
</div>
    <?
    return ob_get_clean();
}

function editor($subj, $type) {
    ob_start();
    ?>
<div>
    <textarea name="<?=$type?>" class="markdown"><?=htmlspecialchars($subj->content($type, 'edit'))?></textarea>
</div>
    <?
    return ob_get_clean();
}



if (!isset($_SERVER['PATH_INFO'])) {
    respond_err(404);
}

$parts = explode('/', $_SERVER['PATH_INFO']);
if (end($parts) && end($parts)[0] == '_') {
    $verb = array_pop($parts);
    $_SERVER['PATH_INFO'] = implode('/', $parts);
} else {
    $verb = '';
}
$subj = Subject::from_path($_SERVER['PATH_INFO']);
$game = $subj->game;

$gm = isset($user->games[$game->id]) && $user->games[$game->id]['role'] == 'gm';

if ($verb == '_edit') {
    if (isset($_POST['name'])) {
        $subj->update($_POST);
        $subj->update_content($_POST);
        respond_redirect($subj->url());
    }
    $head[] = simplemde();
    $links = [
        'View' => $subj->url(),
        'Edit' => null,
        'History' => $subj->url() . '/_hist'
    ];
} elseif ($verb == '_new') {
    if (isset($_POST['name'])) {
        $subj->update($_POST);
        $subj->update_content($_POST);
        respond_redirect($subj->url());
    }
    $head[] = simplemde();
    $links = [
        'View' => $subj->url(),
        'Create' => null
    ];
} elseif ($verb == '_del') {
    $subj->delete();
    respond_redirect($game->url());
} elseif ($verb == '_hist') {
    $revisions = [
        'gmpriv' => [],
        'gmpub' => [],
        'plr' => [],
    ];
    foreach (Revision::by_subject($subj->id) as $rev) {
        $revisions[$rev->type][] = $rev;
    }
    $links = [
        'View' => $subj->url(),
        'Edit' => $subj->url() . '/_edit',
        'History' => null
    ];
} elseif ($verb == '_pro') {
    $rev = Revision::by_id($_GET['rev']);
    if (substr($rev->type, 0, 2) == 'gm') {
        User::require_admin($user);
    }
    $subj->content($rev->type, $rev->content());
    respond_redirect($subj->url());
} else {
    if ($subj->id) {
        $links = [
            'View' => null,
            'Edit' => $subj->url() . '/_edit',
            'History' => $subj->url() . '/_hist'
        ];
    } else {
        $links = [
            'View' => null,
            'Create' => $subj->url() . '/_new'
        ];
    }
}

if (!array_key_exists('Create', $links)) {
    $links['New'] = $game->art_url('_new');
}

if ($subj->name) {
    $title = $subj->name . ' | RPG Wiki';
} else {
    if ($verb == '_new') {
        $title = 'New Article | RPG Wiki';
    } else {
        $title = 'Untitled Article | RPG Wiki';
    }
}

ob_start();
?>
<script>
$(function() {
    var $cbox = $('.subject .player-view');
    $cbox.change(function() {
        var $private = $('.gm-private');
        if (this.checked) {
            $private.hide();
        } else {
            $private.show();
        }
    });
    $cbox.change();
});
</script>
<?
$head[] = ob_get_clean();

ob_start();
?>
<? if ($verb == '_edit' || $verb == '_new'): ?>
    <form class="subject edit" method="POST">
        <? if ($subj->name): ?>
            <h1>Editing <?=htmlspecialchars($subj->name)?></h1>
        <? else: ?>
            <h1>New Article</h1>
        <? endif; ?>
        <label class="input-name">Name <input type="text" name="name" value="<?=htmlspecialchars($subj->name)?>"></label>
        <label class="input-url">URL <input type="text" name="url" value="<?=htmlspecialchars($subj->_url)?>"></label>
        <? if ($gm) : ?>
            <label>GM-Private Section</label>
            <?=editor($subj, 'gmpriv')?>
            <label>GM-Public Section</label>
            <?=editor($subj, 'gmpub')?>
            <? $plr = $subj->html_content('plr'); ?>
            <? if ($plr): ?>
                <label>Player Section</label>
                <div><?=$plr?></div>
            <? endif; ?>
        <? else: ?>
            <? $gmpub = $subj->html_content('gmpub'); ?>
            <? if ($gmpub): ?>
                <label>GM Section</label>
                <div><?=$gmpub?></div>
            <? endif; ?>
            <label>Player Section</label>
            <?=editor($subj, 'plr')?>
        <? endif; ?>
        <a href="https://daringfireball.net/projects/markdown/syntax">Markdown formatting guide</a>
        <input type="submit" value="<?=$verb=='_new'?'Create':'Update'?>">
    </form>
<? elseif ($verb == '_hist'): ?>
    <div class="subject history">
        <h1><?=htmlspecialchars($subj->name)?>: Revision History</h1>
        <? if ($gm) : ?>
            <h2>GM-Private Revisions</h2>
            <? foreach ($revisions['gmpriv'] as $rev): ?>
                <?=show_rev($rev)?>
            <? endforeach; ?>
        <? endif; ?>
        <h2>GM<?=$gm?'-Public':''?> Revisions</h2>
        <? foreach ($revisions['gmpub'] as $rev): ?>
            <?=show_rev($rev, $gm)?>
        <? endforeach; ?>
        <h2>Player Revisions</h2>
        <? foreach ($revisions['plr'] as $rev): ?>
            <?=show_rev($rev)?>
        <? endforeach; ?>
    </div>
<? else: ?>
    <? $gmpub = $subj->html_content('gmpub'); ?>
    <? $plr = $subj->html_content('plr'); ?>
    <div class="subject view">
        <h1><?=htmlspecialchars($subj->name)?></h1>
        <? if ($gm): ?>
            <label><input type="checkbox" class="player-view"> Player view</label>
            <? $gmpriv = $subj->html_content('gmpriv'); ?>
            <? if ($gmpriv): ?>
                <section class="gm-private">
                    <h2>GM-Private Section</h2>
                    <?=$gmpriv?>
                </section>
            <? endif; ?>
            <? if ($gmpub): ?>
                <section>
                    <h2>GM-Public Section</h2>
                    <?=$gmpub?>
                </section>
            <? endif; ?>
        <? else: ?>
            <? if ($gmpub): ?>
                <section>
                    <h2>GM Section</h2>
                    <?=$gmpub?>
                </section>
            <? endif; ?>
        <? endif; ?>
        <? if ($plr): ?>
            <section>
                <h2>Player Section</h2>
                <?=$plr?>
            </section>
        <? endif; ?>
    </div>
<? endif; ?>
<?
$body[] = ob_get_clean();

page_std([], head(['title' => $title], ...$head), pheader($links), psidebar(), $body, pfooter());

?>
