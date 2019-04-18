<?
require_once 'lib/main.inc.php';
require_once 'lib/Game.class.php';
require_once 'lib/Subject.class.php';

$user = User::require_login();

$head = [];
$body = [];

function all_users_option($uid=null) {
    static $users;
    if (!isset($users)) {
        $users = User::find();
    }
    ob_start();
    ?>
<option disabled <?=$uid?'':'selected'?>>Select a user</option>
<? foreach ($users as $user): ?>
    <option value="<?=$user->name?>" <?=$user->id==$uid?'selected':''?>><?=htmlspecialchars($user->nickname)?></option>
<? endforeach; ?>
    <?
    return ob_get_clean();
}

$game = null;
$subj = null;
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', $_SERVER['PATH_INFO']);
    if (end($parts) && end($parts)[0] == '_') {
        $verb = array_pop($parts);
        $_SERVER['PATH_INFO'] = implode('/', $parts);
    } else {
        $verb = '';
    }

    if ($verb != '_new') {
        $game = Game::from_path($_SERVER['PATH_INFO'], true);
        if (!$game) {
            respond_err(404);
        }
        $subj = Subject::by_id($game->subj_id);
    }

    if ($verb) {
        User::require_admin($user);
    }

    if ($verb == '_edit') {
        $game->load_users();
        if (isset($_POST['name'])) {
            $art = Subject::by_url($game, '//'.$_POST['art-url']);
            $_POST['subj_id'] = $art->id;
            unset($_POST['art-url']);
            $game->update($_POST);
            respond_redirect($game->url());
        } else {
            $title = $game->name . ' | RPG Wiki';
            $links = [
                'View' => $game->url(),
                'Edit' => null
            ];
        }
    } elseif ($verb == '_new') {
        if (isset($_POST['name'])) {
            $game = Game::create($_POST);
            respond_redirect($game->url());
        } else {
            $title = 'New Game | RPG Wiki';
            $links = [
                'Index' => '..',
                'New' => null
            ];
        }
    } elseif ($verb == '_del') {
        $game->delete();
        respond_redirect('/games');
    } else {
        $game->load_users();
        $gms = [];
        $players = [];
        foreach ($game->users as $u) {
            if ($u['role'] == 'gm') {
                $gms[] = $u;
            } else {
                $players[] = $u;
            }
        }
        $players = array_merge($gms, $players);
        $title = $game->name . ' | RPG Wiki';
        $links = [
            'View' => null,
            'Edit' => $game->url() . '/_edit'
        ];
    }
} else {
    $verb = '';
    $games = Game::all();
    $title = 'Game Index | RPG Wiki';
    $links = [
        'Index' => null,
        'New' => '/game/_new'
    ];
}

if (!$user->admin) {
    if (isset($links['New'])) {
        unset($links['New']);
    }
    if (isset($links['Edit'])) {
        unset($links['Edit']);
    }
}

ob_start();
?>
<script src="/scripts/editor.js"></script>
<script>
function select_vals() {
    var vals = {};
    $('.game select.user').each(function() {
        vals[$(this).val()] = true;
    });
    return vals;
}

function $masters() {
    return $('.game select.master option:not([disabled])');
}

function update_selects() {
    var vals = select_vals();
    var selects = $('.game select.user');
    $masters().each(function() {
        var val = $(this).val();
        selects.each(function() {
            var $sel = $(this);
            var $opt = $sel.find('option[value="'+val+'"]');
            var x = 0;
            if (vals[val]) {
                x = 2;
                if ($sel.val() != val) {
                    $opt.hide();
                    x = 1;
                }
            } else {
                $opt.show();
                x = 3;
            }
        });
    });
}

function del_user(event) {
    event.preventDefault();
    $(this).closest('div').remove();
    update_selects();
}

$(function() {
    update_selects();
    $('.game select.user').on('change', update_selects);
    $('.game a.new').click(function(event) {
        event.preventDefault();
        if ($('.game div.user').length >= $masters().length) {
            alert('This game already has a slot for every user.');
            return;
        }
        var elem = $('.templates .user-tmpl').clone();
        elem.find('select.user').on('change', update_selects);
        elem.find('a.del').click(del_user);
        $('.game .users').append(elem);
        update_selects();
    });
    $('.game a.del').click(del_user);
});
</script>
<?
$head[] = ob_get_clean();

$head[] = ace_head();

ob_start();
?>
<div class="templates">
    <div class="user user-tmpl">
        <label>User <select name="user[]" class="user"><?=all_users_option()?></select></label>
        <label>Role <select name="role[]"><option value="player">Player</option><option value="gm">GM</option></select></label>
        <? if ($game): ?>
            <label>Article URL <input type="text" name="art[]"></label>
        <? endif; ?>
        <a href="#" class="del"><i class="far fa-trash-alt"></i></a>
    </div>
</div>
<?
$body[] = ob_get_clean();

ob_start();
    ?>
<? if ($verb == '_edit' || $verb == '_new'): ?>
    <form class="game edit" method="POST">
        <select class="master"><?=all_users_option()?></select>
        <? if ($game): ?>
            <h1>Editing <?=htmlspecialchars($game->name)?></h1>
        <? else: ?>
            <h1>New Game</h1>
        <? endif; ?>
        <label>Name <input type="text" name="name" value="<?=$game?htmlspecialchars($game->name):''?>"></label>
        <label>URL <input type="text" name="url" value="<?=$game?htmlspecialchars($game->_url):''?>"></label>
        <div class="users">
            <?
            if ($game) {
                foreach ($game->users as $u) {
                    if ($u['subj']) {
                        $art = Subject::by_id($u['subj'])->_url;
                    } else {
                        $art = '';
                    }
                    ?>
                    <div class="user">
                        <label>User <select name="user[]" class="user"><?=all_users_option($u['user'])?></select></label>
                        <label>Role <select name="role[]"><option value="player" <?=$u['role']=='player'?'selected':''?>>Player</option><option value="gm" <?=$u['role']=='gm'?'selected':''?>>GM</option></select></label>
                        <label>Article URL <input type="text" name="art[]" value="<?=$art?>"></label>
                        <a href="#" class="del"><i class="far fa-trash-alt"></i></a>
                    </div>
                    <?
                }
            }
            ?>
        </div>
        <a href="#" class="new"><i class="fas fa-plus"></i> Add User</a>
        <? if ($game): ?>
            <label class="article">Home Article URL <input type="text" name="art-url" required value="<?=$subj->_url?>"></label>
        <? else: ?>
            <h2>Home Article</h2>
            <label class="input-name">Name <input type="text" name="art-name" required></label>
            <label class="input-url">URL <input type="text" name="art-url" required></label>
            <label>GM-Public Section</label>
            <input type="hidden" name="gmpub">
            <div class="editor gmpub"></div>
            <a href="https://asciidoctor.org/docs/asciidoc-syntax-quick-reference/" target="_blank">Asciidoc formatting guide</a>
        <? endif; ?>
        <input type="submit" value="<?=$verb=='_new'?'Create':'Update'?>">
    </form>
<? else: ?>
    <? if ($game): ?>
        <div class="game">
        <h1><?=htmlspecialchars($game->name)?></h1>
        <h2><a href="<?=$subj->url()?>">Home Page</a></h2>
        <h2><a href="<?=$game->art_url()?>">Article index</a></h2>
        <h2>Participants</h2>
        <ul>
            <? foreach ($players as $u): ?>
                <? $i_user = User::load(['id' => $u['user']]); ?>
                <li>
                    <a href="<?=$i_user->url()?>"><?=htmlspecialchars($i_user->nickname)?></a>
                    <? if ($u['role'] == 'gm'): ?>
                        (GM)
                    <? endif; ?>
                    <? if ($u['subj']): ?>
                        <? $subj = Subject::by_id($u['subj']); ?>
                        as <a href="<?=$subj->url()?>"><?=htmlspecialchars($subj->name)?></a>
                    <? endif; ?>
                </li>
            <? endforeach; ?>
        </ul>
        </div>
    <? else: ?>
        <div class="game-list">
        <h1>Games</h1>
        <ul>
            <? foreach ($games as $game): ?>
                <li><a href="<?=$game->url()?>"><?=htmlspecialchars($game->name)?></a></li>
            <? endforeach; ?>
        </ul>
        </div>
    <? endif; ?>
<? endif; ?>
<?
$body[] = ob_get_clean();

$body[] = ace_end();

page_std([], head(['title' => $title], ...$head), pheader($links), psidebar(), $body, pfooter());

?>
