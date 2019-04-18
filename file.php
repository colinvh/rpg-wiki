<?
require_once 'lib/main.inc.php';
require_once 'lib/File.class.php';

$user = User::require_login();

$head = [];
$body = [];

$file = null;
$verb = '';
$links = ['Upload' => '/file'];
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', $_SERVER['PATH_INFO']);
    if (end($parts) && end($parts)[0] == '_') {
        $verb = array_pop($parts);
        $_SERVER['PATH_INFO'] = implode('/', $parts);
    }
    $file = File::from_path($_SERVER['PATH_INFO']);
    $title = $file->_url . ' | RPG Wiki';
    $links['Meta'] = $file->url();
    $links['History'] = $file->url() . '/_hist';
    $links['Replace'] = $file->url() . '/_replace';
    if ($user->admin || $user->id == $ed_user->id) {
        $links['Delete'] = $file->url() . '/_del';
    }
    $links['View <i class="fas fa-location-arrow"></i>'] = [
        'href' => $file->raw_url(),
        'target' => '_blank'
    ];
    $links['Download <i class="fas fa-download"></i>'] = $file->raw_url() . '?dl=1';
}

if ($verb == '_replace') {
    if (isset($_FILES['file'])) {
        $file = File::create($_FILES['file'], $file->_url);
        respond_redirect($file->url());
    }
    $links['Replace'] = null;
} elseif ($verb == '_del') {
    if (isset($_POST['submit'])) {
        $file->delete();
        respond_redirect($file->url());
    }
    $links['Delete'] = null;
} else {
    if ($file) {
        $ed_user = User::load(['id' => $file->uploader]);
        $links['Meta'] = null;
    } else {
        if (isset($_FILES['file'])) {
            $file = File::create($_FILES['file'], $_POST['url']);
            respond_redirect($file->url());
        }
        $title = 'New File | RPG Wiki';
        $links = ['Upload' => null];
    }
}

ob_start();
?>
<script>
$(function() {
    var auto_url = '';
    $('input[type="file"]').change(function() {
        var $url = $('input[name="url"]');
        if ($url.val() == auto_url) {
            auto_url = this.value.split('\\').pop();
            $url.val(auto_url);
        }
    });
});
</script>
<?
$head[] = ob_get_clean();

ob_start();
?>
<? if ($verb == '_replace'): ?>
    <form class="file edit" method="POST" enctype="multipart/form-data">
        <h1>Replace <?=htmlspecialchars($file->_url)?></h1>
        <input name="file" type="file">
        <input type="submit" value="Replace File">
    </form>
<? elseif ($verb == '_del'): ?>
    <form class="file delete" method="POST">
        <h1>Delete <?=htmlspecialchars($file->_url)?></h1>
        <input type="submit" name="submit" value="Confirm">
    </form>
<? elseif ($verb == '_hist'): ?>
    <div class="file hist">
        <h1>History: <?=htmlspecialchars($file->_url)?></h1>
        <? $first = true; ?>
        <? foreach (File::find(['url' => $file->_url]) as $i_file): ?>
            <? $ed_user = User::load(['id' => $i_file->uploader]); ?>
            <div>
                <a href="<?=$i_file->url()?>"><?=$i_file->date?></a>
                <?=$i_file->hash?'uploaded':'deleted'?> by <a href="<?=$ed_user->url()?>"><?=$ed_user->nickname?></a>
                <? if ($first): ?>
                    (current)
                <? else: ?>
                    (<a href="_pro?ver=<?=$i_file->id?>">promote to current</a>)
                <? endif; ?>
            </div>
            <? $first = false; ?>
        <? endforeach; ?>
    </div>
<? else: ?>
    <? if ($file): ?>
        <div class="file">
            <h1><?=htmlspecialchars($file->_url)?></h1>
            <p><?=$file->hash?'Uploaded':'Deleted'?> by <a href="<?=$ed_user->url()?>"><?=$ed_user->nickname?></a> on <?=$file->date?>.</p>
        </div>
    <? else: ?>
        <form class="file edit" method="POST" enctype="multipart/form-data">
            <h1>Upload File</h1>
            <input name="file" type="file">
            <label>File name <input name="url" type="text"></label>
            <input type="submit" value="Upload File">
        </form>
    <? endif; ?>
<? endif; ?>
<?
$body[] = ob_get_clean();

page_std([], head(['title' => $title], ...$head), pheader($links), psidebar(), $body, pfooter());

?>
