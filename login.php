<?
require_once 'lib/session.inc.php';
require_once 'lib/site.inc.php';
require_once 'lib/db.inc.php';

if (isset($_POST['location'])) {
    $location = $_POST['location'];
} elseif (isset($_GET['location'])) {
    $location = $_GET['location'];
} else {
    $location = '/';
}

$logged_in = false;
if (isset($_POST['name']) && isset($_POST['password'])) {
    $user = User::load(['name' => $_POST['name']]);
    if ($user->check_password($_POST['password'])) {
        $_SESSION['user'] = $user->id;
        header('Location: ' . $location);
        exit();
    } else {
        $error = true;
        $logged_in = false;
    }
} else {
    $error = false;
    $user = User::from_session();
    $logged_in = (bool) $user;
}

$name = isset($_POST['name']) ? $_POST['name'] : '';
$admin_err = isset($_GET['admin_err']) ? $_GET['admin_err'] : '';

ob_start();
?>
<form class="main login" method="POST">
    <input type="hidden" name="location" value="<?=$location?>">
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
        <div><a href="/create-account">Create account</a></div>
        <div><a href="/forgot-password">Forgot username or password?</a></div>
    <? endif; ?>
</form>
<?
page([], head(), pheader(), ob_get_clean(), pfooter());
?>
