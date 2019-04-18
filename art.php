<?
require_once 'lib/main.inc.php';
require_once 'lib/Subject.class.php';

$user = User::require_login();
$user->load_games();

$head = [];
$body = [];

function show_revs($revs, $promote=true) {
    $first = true;
    ob_start();
    foreach ($revs as $rev) {
        $user = User::load(['id' => $rev->author]);
        ?>
<div>
    <a href="<?=$rev->url()?>"><?=$rev->date?></a>
    by <a href="<?=$user->url()?>"><?=$user->nickname?></a>
    <? if ($first): ?>
        (current)
    <? elseif ($promote): ?>
        (<a href="_pro?rev=<?=$rev->id?>">promote to current</a>)
    <? endif; ?>
</div>
        <?
        $first = false;
    }
    return ob_get_clean();
}

function editor($subj, $type) {
    ob_start();
    ?>
<input type="hidden" name="<?=$type?>">
<div class="editor <?=$type?>"><?=htmlspecialchars($subj->content($type, 'edit'))?></div>
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
if ($subj->_url == '' && $verb == '') {
    unset($subj);
}

if (isset($user->games[$game->id])) {
    if ($user->games[$game->id]['role'] == 'gm') {
        $gm = true;
        $player = false;
    } else {
        $gm = false;
        $player = true;
    }
} else {
    $gm = false;
    $player = false;
}

if (isset($subj)) {
    $links = [
        'View' => $subj->url(),
        'Edit' => $subj->url() . '/_edit',
        'History' => $subj->url() . '/_hist',
        'New' => $game->art_url('_new')
    ];
}

if ($verb == '_edit' || $verb == '_new') {
    if (!($gm || $player)) {
        respond_err(404);
    }
    if (isset($_POST['name'])) {
        if ($player) {
            unset($_POST['gmpriv']);
            unset($_POST['gmpub']);
        }
        $subj->update($_POST);
        respond_redirect($subj->url());
    }
    if ($verb == '_edit') {
        $links['Edit'] = null;
    } elseif ($verb == '_new') {
        $links = [
            'Create' => null
        ];
    }
} elseif ($verb == '_del') {
    if (!($gm || $player)) {
        respond_err(404);
    }
    if ($gm) {
        $subj->delete();
    } else {
        $subj->update_content(['plr' => '']);
        if ($subj->heads['gmpub']->hash) {
            respond_redirect($subj->url());
        }
    }
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
    $links['History'] = null;
} elseif ($verb == '_rev' || $verb == '_pro') {
    $rev = Revision::by_id($_GET['rev']);
    if ($rev->subj_id != $subj->id) {
        respond_err(404);
    }
    if (substr($rev->type, 0, 2) == 'gm' && !$gm) {
        respond_err(404);
    }
    if ($verb == '_pro') {
        if (!($gm || $player)) {
            respond_err(404);
        }
        $subj->update_content([$rev->type => $rev->editor_content()]);
        respond_redirect($subj->url());
    }
} elseif ($verb == '_search') {
    $search_start_time = microtime(true);
    $subjs = Subject::search($game->id, $gm, $_GET['s']);
    $search_time = microtime(true) - $search_start_time;
    if ($search_time < 1) {
        $search_time *= 1000;
        $fmt = "%.2f ms";
    } else {
        $fmt = "%.3f sec";
    }
    $search_time = sprintf($fmt, $search_time);
} else {
    if (isset($subj)) {
        if ($subj->id) {
            $links['View'] = null;
        } else {
            $links = [
                'View' => null,
                'Create' => $game->art_url('_new')
            ];
        }
    } else {
        $links = [
            'Index' => null,
            'New' => $game->art_url('_new')
        ];
    }
}

if (isset($subj)) {
    if ($subj->name) {
        $title = $subj->name . ' | RPG Wiki';
    } else {
        if ($verb == '_new') {
            $title = 'New Article | RPG Wiki';
        } elseif ($verb == '_search') {
            $title = 'Search Results | RPG Wiki';
        } else {
            $title = 'Untitled Article | RPG Wiki';
        }
    }
} else {
    $title = 'Article Index: ' . $game->name . ' | RPG Wiki';
}

$head[] = asciidoctor();
$head[] = ace_head();

ob_start();
?>
<script src="/scripts/editor.js"></script>
<script>
$(function() {
    var $plr_view_cb = $('.subject .player-view');
    var orig_url = location.href;
    $plr_view_cb.change(function() {
        var $private = $('.gm-private');
        if ($('.gm-public, .player').length == 0) {
            var $warn = $('.reload-warning');
            var url;
            var vis;
            if (this.checked) {
                url = '<?=$game->art_url()?>';
                vis = 'hidden';
                $warn.show();
            } else {
                url = orig_url;
                vis = 'visible';
                $warn.hide();
            }
            window.history.replaceState({}, $('title').text(), url);
            $('.art-title').css('visibility', vis);
        }
        if (this.checked) {
            $private.hide();
        } else {
            $private.show();
        }
    });
    $plr_view_cb.change();
    // this doesn't fire in time, hence $warn above
    $(window).on('beforeunload', function() {
        window.history.replaceState({}, $('title').text(), orig_url);
    });

    var $plr_edit_cb = $('.subject .player-edit');
    $plr_edit_cb.change(function() {
        var $viewer = $('.subject.edit .plr .viewer');
        var $editor = $('.subject.edit .plr .editor');
        if (this.checked) {
            $editor.show();
            $viewer.hide();
        } else {
            $editor.hide();
            $viewer.show();
        }
    });
    $plr_edit_cb.change();

    var $contents = $('.gm-private>div, .gm-public>div, .player>div, .subject.edit .viewer');
    $contents.each(function() {
        var $this = $(this);
        $this.html(Asciidoctor().convert($this.html()));
    });
    $('.gm-private>div colgroup, .gm-public>div colgroup, .player>div colgroup').remove();
    $contents.show();
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
        <label class="input-name">Name <input type="text" name="name" value="<?=htmlspecialchars($subj->name)?>" selected></label>
        <label class="input-url">URL <input type="text" name="url" value="<?=htmlspecialchars($subj->_url)?>"></label>
        <? if ($gm) : ?>
            <label>GM-Private Section</label>
            <?=editor($subj, 'gmpriv')?>
            <label>GM-Public Section</label>
            <?=editor($subj, 'gmpub')?>
            <label>Player Section</label>
            <div>
                <label><input type="checkbox" class="player-edit"> Edit</label>
                <div class="plr">
                    <div class="viewer">
                        <?=$subj->content('plr')?>
                    </div>
                    <div class="editor">
                        <?=editor($subj, 'plr')?>
                    </div>
                </div>
            </div>
        <? else: ?>
            <? $gmpub = $subj->content('gmpub'); ?>
            <? if ($gmpub): ?>
                <label>GM Section</label>
                <div><?=$gmpub?></div>
            <? endif; ?>
            <label>Player Section</label>
            <?=editor($subj, 'plr')?>
        <? endif; ?>
        <div>
            <a href="https://asciidoctor.org/docs/asciidoc-syntax-quick-reference/" target="_blank">Asciidoc formatting guide</a>
            <small>(article link: <tt>{{article-name}}</tt> | upload url: <tt>/file/upload-name.txt</tt> | image url: <tt>/f/image.jpg</tt>)</small>
        </div>
        <input type="submit" value="<?=$verb=='_new'?'Create':'Update'?>">
    </form>
<? elseif ($verb == '_hist'): ?>
    <div class="subject history">
        <h1><?=htmlspecialchars($subj->name)?>: Revision History</h1>
        <? if ($gm) : ?>
            <h2>GM-Private Revisions</h2>
            <?=show_revs($revisions['gmpriv'])?>
            <h2>GM-Public Revisions</h2>
            <?=show_revs($revisions['gmpub'], $gm)?>
        <? endif; ?>
        <h2>Player Revisions</h2>
        <?=show_revs($revisions['plr'], $gm || $player)?>
    </div>
<? elseif ($verb == '_rev'): ?>
    <div class="subject view">
        <h1><?=htmlspecialchars($subj->name)?></h1>
        <section>
            <? if ($rev->type == 'gmpriv'): ?>
                <h2>GM-Private Section</h2>
            <? elseif ($rev->type == 'gmpub'): ?>
                <h2>GM<?=$gm?'-Public':''?> Section</h2>
            <? elseif ($rev->type == 'plr'): ?>
                <h2>Player Section</h2>
            <? endif; ?>
            <?=$rev->content()?>
        </section>
    </div>
<? elseif ($verb == '_search'): ?>
    <div class="subject search">
        <h1>Search Results</h1>
        <h2>for "<?=$_GET['s']?>"</h2>
        <h3>(took <?=$search_time?>)</h3>
        <? if (count($subjs)): ?>
            <? foreach ($subjs as $subj): ?>
                <? $gm_only = is_null($subj->heads['gmpub']->hash) && is_null($subj->heads['plr']->hash); ?>
                <div><a href="<?=$subj->url()?>"><?=htmlspecialchars($subj->name)?></a> <?=$gm_only?'[P]':''?></div>
            <? endforeach; ?>
        <? else: ?>
            <div class="not-found">Nothing found</div>
        <? endif; ?>
    </div>
<? else: ?>
    <? if (isset($subj)): ?>
        <? $gmpub = $subj->content('gmpub'); ?>
        <? $plr = $subj->content('plr'); ?>
        <div class="subject view">
            <h1 class="art-title"><?=htmlspecialchars($subj->name)?></h1>
            <? if ($gm): ?>
                <label><input type="checkbox" class="player-view"> Player View</label>
                <div class="reload-warning result error" style="display: none;">Warning: Do not reload the page while in Player View; you will be taken to the article index if you do.</div>
                <? $gmpriv = $subj->content('gmpriv'); ?>
                <? if ($gmpriv): ?>
                    <section class="gm-private">
                        <h2>GM-Private Section</h2>
                        <div style="display: none;"><?=$gmpriv?></div>
                    </section>
                <? endif; ?>
            <? endif; ?>
            <? if ($gmpub): ?>
                <section class="gm-public">
                    <h2>GM<?=$gm?'-Public':''?> Section</h2>
                    <div style="display: none;"><?=$gmpub?></div>
                </section>
            <? endif; ?>
            <? if ($plr): ?>
                <section class="player">
                    <h2>Player Section</h2>
                    <div style="display: none;"><?=$plr?></div>
                </section>
            <? endif; ?>
        </div>
    <? else: ?>
        <h1><?=htmlspecialchars($game->name)?></h1>
        <h2>Article Index</h2>
        <ul>
            <? foreach (Subject::all($game->id) as $subj): ?>
                <? $hide = is_null($subj->heads['gmpub']->hash) && is_null($subj->heads['plr']->hash); ?>
                <? if ($hide && !$gm) {
                    continue;
                } ?>
                <li><a href="<?=$subj->url()?>"><?=htmlspecialchars($subj->name)?></a> <?=$hide?'[P]':''?></li>
            <? endforeach; ?>
        </ul>
    <? endif; ?>
<? endif; ?>
<?
$body[] = ob_get_clean();

$body[] = ace_end();

page_std([], head(['title' => $title], ...$head), pheader($links, $game->art_url()), psidebar(), $body, pfooter());

?>
