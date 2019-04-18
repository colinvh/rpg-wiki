<?
require_once 'lib/main.inc.php';
require_once 'lib/Game.class.php';
require_once 'lib/Subject.class.php';

$user = User::from_session();

$ed_user = null;
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', $_SERVER['PATH_INFO']);
    if (end($parts) && end($parts)[0] == '_') {
        $verb = array_pop($parts);
        $_SERVER['PATH_INFO'] = implode('/', $parts);
    } else {
        $verb = '';
    }

    $self = false;
    if ($verb == '_new') {
        if (!$user) {
            $self = true;
        }
    } else {
        User::require_login($user);
        $ed_user = User::from_path($_SERVER['PATH_INFO'], true);
        if ($user->id == $ed_user->id) {
            $self = true;
        }
    }

    if ($verb) {
        if ($verb == '_edit') {
            if (!$self) {
                User::require_admin($user);
            }
        } elseif ($verb != '_new') {
            User::require_admin($user);
        }
    }

    if ($verb == '_edit') {
        if (isset($_POST['name'])) {
            $ed_user->update($_POST);
            respond_redirect($ed_user->url());
        } else {
            $title = $ed_user->name . ' | RPG Wiki';
            $links = [
                'View' => $ed_user->url(),
                'Edit' => false
            ];
        }
    } elseif ($verb == '_new') {
        if (isset($_POST['name'])) {
            $ed_user = User::create($_POST);
            respond_redirect($ed_user->url());
        } else {
            $title = 'New User | RPG Wiki';
            $links = [
                'Index' => '/user',
                'New' => false
            ];
        }
    } elseif ($verb == '_del') {
        $ed_user->delete();
        respond_redirect('/ed_users');
    } else {
        $ed_user->load_games();
        $title = $ed_user->nickname . ' | RPG Wiki';
        $links = [
            'View' => false
        ];
        if ($user->admin || $self) {
            $links['Edit'] = $ed_user->url() . '/_edit';
        }
    }
} else {
    User::require_login($user);
    $verb = '';
    $users = User::find();
    $title = 'User Index | RPG Wiki';
    $links = [
        'Index' => false
    ];
}

if ($user && $user->admin && !isset($links['New'])) {
    $links['New'] = '/user/_new';
} elseif ($verb == '_new' && $self) {
    $links = ['New' => false];
}

ob_start();
    ?>
<? if ($verb == '_edit' || $verb == '_new'): ?>
    <form class="user edit" method="POST">
        <? if (!$self): ?>
            <input type="hidden" name="admin" value="0">
        <? endif; ?>
        <? if ($ed_user): ?>
            <? if ($self): ?>
                <h1>Editing Profile</h1>
            <? else: ?>
                <h1>Editing <?=htmlspecialchars($ed_user->nickname)?></h1>
            <? endif; ?>
        <? else: ?>
            <? if ($self): ?>
                <h1>Create Account</h1>
            <? else: ?>
                <h1>New User</h1>
            <? endif; ?>
        <? endif; ?>
        <label>Username <input type="text" name="name" required value="<?=$ed_user?htmlspecialchars($ed_user->name):''?>"></label>
        <label>Password <input type="password" name="password" <?=$ed_user?'placeholder="••••••••"':'required'?>></label>
        <? if (!$self): ?>
            <label>Admin <input type="checkbox" name="admin" value="1"<?=$ed_user&&$ed_user->admin?' checked':''?>></label>
        <? endif; ?>
        <label>Nickname <input type="text" name="nickname" required value="<?=$ed_user?htmlspecialchars($ed_user->nickname):''?>"></label>
        <label>Email <input type="email" name="email" required value="<?=$ed_user?htmlspecialchars($ed_user->email):''?>"></label>
        <input type="submit" value="<?=$verb=='_new'?'Create':'Update'?>">
    </form>
<? else: ?>
    <? if ($ed_user): ?>
        <div class="user">
        <h1><?=htmlspecialchars($ed_user->nickname)?><?=$self?' (you)':''?></h1>
        <h2>Games</h2>
        <ul>
            <? foreach ($ed_user->games as $g): ?>
                <? $game = Game::load(['id' => $g['id']]); ?>
                <li>
                    <a href="<?=$game->url()?>"><?=htmlspecialchars($game->name)?></a>
                    <? if ($g['role'] == 'gm'): ?>
                        (GM)
                    <? endif; ?>
                    <? if ($g['subj']): ?>
                        <? $subj = Subject::by_id($g['subj']); ?>
                        as <a href="<?=$subj->url()?>"><?=htmlspecialchars($subj->name)?></a>
                    <? endif; ?>
                </li>
            <? endforeach; ?>
        </ul>
        </div>
    <? else: ?>
        <div class="user-list">
        <h1>Users</h1>
        <ul>
            <? foreach ($users as $u): ?>
                <li><a href="<?=$u->url()?>"><?=htmlspecialchars($u->nickname)?></a></li>
            <? endforeach; ?>
        </ul>
        </div>
    <? endif; ?>
<? endif; ?>
    <?
page_std([], head(['title' => $title]), pheader($links), psidebar(), [ob_get_clean()], pfooter());

?>
