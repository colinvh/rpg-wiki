<?

function page_std($meta=[], $head, $header, $sidebar, $contents, $footer) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html><head>
<?=$head?>
</head><body class="standard">
<div class="grid-standard">
<?=$header?>
<?=$sidebar?>
<div class="content">
<? foreach ($contents as $c): ?>
    <?=$c?>

<? endforeach; ?>
</div>
<?=$footer?>
</div>
</body></html>
    <?
}

function head($meta=[], ...$contents) {
    ob_start();
    ?>
<meta charset="utf-8">
<title><?=$meta['title']?></title>
<? if (array_key_exists('description', $meta)): ?>
    <meta name="description" content="<?=$meta['description']?>">
<? endif; ?>
<? if (array_key_exists('keywords', $meta)): ?>
    <meta name="keywords" content="<?=$meta['keywords']?>">
<? endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#ab1212">
<meta name="msapplication-TileColor" content="#ab1212">
<meta name="theme-color" content="#ffffff">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
<!-- <link rel="stylesheet" href="/style/fonts.css"> -->
<link rel="stylesheet" href="/style/site.css">

<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<? foreach ($contents as $c): ?>
    <?=$c?>

<? endforeach; ?>
    <?
    return ob_get_clean();
}

function pheader($links=[]) {
    global $user;
    ob_start();
    ?>
<div class="header">
<div class="user">
    <? if ($user): ?>
        <a href="<?=$user->url()?>"><i class="fas fa-user"></i> <?=$user->nickname?></a>
        <a href="/logout">Log Out</a>
    <? else: ?>
        <span><i class="fas fa-user"></i> Not Logged In</span>
        <a href="/login">Log In</a>
    <? endif; ?>
</div>
<div class="links">
    <? foreach ($links as $name => $link): ?>
        <? if ($link): ?>
            <div class="link"><a href="<?=$link?>"><?=$name?></a></div>
        <? else: ?>
            <div class="active"><div><?=$name?></div></div>
        <? endif; ?>
    <? endforeach; ?>
</div>
</div>
    <?
    return ob_get_clean();
}

function psidebar() {
    global $user;
    ob_start();
    ?>
<div class="sidebar">
<img class="logo" src="/img/logo-head.png">
<div class="beta">βετα</div>
<? if ($user): ?>
    <ul>
        <li><a href="/game">Games index</a></li>
        <li><a href="/user">Users index</a></li>
    </ul>
<? endif; ?>
</div>
    <?
    return ob_get_clean();
}

function pfooter() {
    ob_start();
    ?>
<div class="footer">

</div>
    <?
    return ob_get_clean();
}

function simplemde() {
    ob_start();
    ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.css">
<script src="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.js"></script>
<script src="/scripts/simplemde.js"></script>
    <?
    return ob_get_clean();
}

?>
