<?
require_once 'lib/session.inc.php';
require_once 'lib/AuthenticationCode.class.php';
require_once 'lib/User.class.php';
require_once 'lib/mail.inc.php';
require_once 'lib/site.inc.php';

define('AUTH_EXP_SEC', 86400 * 5);
define('AUTH_EXP_EN', 'five days');

$user = User::from_session();

$error = null;
if (isset($_POST['auth'])) {
    $auth_code = $_POST['auth'];
} elseif (isset($_GET['auth'])) {
    $auth_code = $_GET['auth'];
} else {
    $auth_code = null;
}
if ($auth_code) {
    try {
        $auth_code = AuthenticationCode::load($auth_code);
    } catch (NotFoundException $e) {
        $auth_code = null;
        $error = $e->getMessage();
    }
}

function send_code($auth_code, $user) {
    $subject = 'RPG Wiki password reset link';
    $url = 'https://rpg-wiki.divitu.com/forgot-password?auth=' . $auth_code;
    ob_start();
    ?>
<html><body>
<p>Your username is <?=$user->name?>.  <a href="<?=$url?>">Update your RPG Wiki password.</a></p>
</body></html>
    <?
    $html_body = ob_get_clean();
    $text_body = "Your username is $user->name.  Click the following link to update your RPG Wiki password:\n$url";
    send_mail($subject, $html_body, $text_body, $user);
}

function process($action) {
    global $auth_code;
    if ($action == 'update-password') {
        $ed_user = User::load(['email' => $auth_code->confirm('email')]);
        $_SESSION['user'] = $ed_user->id;
        session_write_close();
        $ed_user->update(['password' => $_POST['password']]);
        $auth_code->delete();
        header('Location: /');
        exit();
    } elseif ($action == 'send-code') {
        $ed_user = User::load(['email' => $_POST['email']]);
        if (!$ed_user) {
            return 'User does not exist.';
        }
        $auth_code = AuthenticationCode::create(AUTH_EXP_SEC, 'email:' . $ed_user->email);
        send_code($auth_code->code, $ed_user);
    }
    return null;
}

if ($auth_code) {
    if (isset($_POST['password'])) {
        $action = 'update-password';
    } else {
        $action = 'request-password';
    }
} else {
    if (isset($_POST['email'])) {
        $action = 'send-code';
    } else {
        $action = 'request-email';
    }
}
if (!$error) {
    $error = process($action);
}

ob_start();
?>
<div class="forgot-password">
<? if ($action == 'request-password'): ?>
    <form method="POST" action="?">
        <h1>Reset Password</h1>
        <input type="hidden" name="auth" value="<?=$auth_code->code?>">
        <div><label>Password: <input name="password" type="password" required></label></div>
        <input type="submit" value="Change Password">
    </form>
<? elseif (($error && $action == 'send-code') || $action == 'request-email'): ?>
    <form method="POST" action="?">
        <h1>Send Password Reset</h1>
        <? if ($error): ?>
            <div class="result error"><?=htmlspecialchars($error)?></div>
        <? endif; ?>
        <label>Email address: <input type="email" name="email"></label>
        <input type="submit" value="Send Email">
    </form>
<? elseif ($action == 'send-code'): ?>
    <h1>Reset Sent</h1>
    <div class="results">A link has been sent to the email you specified.  Follow the link to finish resetting your password.  The link will expire in <?=AUTH_EXP_EN?>.</div>
<? endif; ?>
</div>
<?

page_std([], head(['title' => 'Reset Password | RPG Wiki']), pheader(), psidebar(), [ob_get_clean()], pfooter());

?>
