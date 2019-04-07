<?
function page_std($meta=[], $head, $header, $sidebar, $contents, $footer) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html><head>
<?=$head?>
</head><body class="grid-standard">
<?=$header?>
<?=$sidebar?>
<div class="content">
<? foreach ($contents as $c): ?>
    <?=$c?>

<? endforeach; ?>
</div>
<?=$footer?>
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
<link rel="stylesheet" href="/style/fonts.css">
<link rel="stylesheet" href="/style/site.css">

<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<? foreach ($contents as $c): ?>
    <?=$c?>

<? endforeach; ?>
    <?
    return ob_get_clean();
}

function pheader() {
    ob_start();
    ?>
<div class="header">
HEAD
</div>
    <?
    return ob_get_clean();
}

function psidebar() {
    ob_start();
    ?>
<div class="sidebar">
SIDE
</div>
    <?
    return ob_get_clean();
}

function pfooter() {
    ob_start();
    ?>
<div class="footer">
FOOT
</div>
    <?
    return ob_get_clean();
}

?>
