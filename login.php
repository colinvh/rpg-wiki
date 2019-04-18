<?
require_once 'lib/main.inc.php';

if (isset($_POST['location'])) {
    $loc_url = $location = $_POST['location'];
} elseif (isset($_GET['location'])) {
    $loc_url = $location = $_GET['location'];
} else {
    $location = '';
    $loc_url = '/';
}

$name = isset($_POST['name']) ? $_POST['name'] : '';
$admin_err = isset($_GET['admin_err']) ? $_GET['admin_err'] : '';

$logged_in = false;
if (isset($_POST['name']) && isset($_POST['password'])) {
    $user = User::load(['name' => $_POST['name']]);
    if ($user->check_password($_POST['password'])) {
        $_SESSION['user'] = $user->id;
        respond_redirect($loc_url);
    } else {
        $error = true;
        $logged_in = false;
    }
} else {
    $error = false;
    $user = User::from_session();
    $logged_in = (bool) $user;
    if ($logged_in && $location) {
        if (!$user->admin == !$admin_err) {
            respond_redirect($loc_url);
        }
    }
}

ob_start();
?>
<form class="login" method="POST">
    <h1>Log In</h1>
    <? if ($location): ?>
        <input type="hidden" name="location" value="<?=$location?>">
    <? endif; ?>
    <? if ($admin_err): ?>
        <div class="result error">Administrator access required.</div>
    <? endif; ?>
    <? if ($logged_in): ?>
        <div class="result logged-in">You are already logged in, but you may log into a different account below.</div>
    <? endif; ?>
    <? if ($error): ?>
        <div class="result error">Username and password do not match.</div>
    <? endif; ?>
    <input name="name" placeholder="username" value="<?=$name?>" required>
    <input type="password" name="password" placeholder="password" required>
    <input type="submit" value="Log In">
    <? if (!$logged_in): ?>
        <div><a href="/user/_new">Create account</a></div>
        <div><a href="/forgot-password">Forgot username or password?</a></div>
    <? endif; ?>
</form>
<?
page_std([], head(['title' => 'RPG Wiki']), pheader(), psidebar(), [ob_get_clean()], pfooter());

?>
