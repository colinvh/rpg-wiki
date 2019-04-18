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
<title><?=htmlspecialchars($meta['title'])?></title>
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

function pheader($links=[], $url=null) {
    global $user;
    ob_start();
    ?>
<div class="header">
<div class="top-right">
    <? if ($url): ?>
        <form class="search" action="<?=$url?>/_search" method="GET">
            <input type="text" name="s">
            <input type="submit" value="Search">
        </form>
    <? endif; ?>
    <div class="user">
        <? if ($user): ?>
            <a href="<?=$user->url()?>"><i class="fas fa-user"></i> <?=$user->nickname?></a>
            <a href="/logout">Log Out</a>
        <? else: ?>
            <span><i class="fas fa-user"></i> Not Logged In</span>
            <a href="/login">Log In</a>
        <? endif; ?>
    </div>
</div>
<div class="links">
    <? foreach ($links as $name => $link): ?>
        <? if ($link): ?>
            <? $target = ''; ?>
            <? if (is_array($link)) {
                $href = $link['href'];
                if (isset($link['target'])) {
                    $target = 'target="' . $link['target'] . '"';
                }
            } else {
                $href = $link;
            } ?>
            <div class="link"><a href="<?=$href?>" <?=$target?>><?=$name?></a></div>
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
        <li><a href="/file">Upload file</a></li>
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

function asciidoctor() {
    ob_start();
    ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/asciidoctor.js/1.5.9/asciidoctor.min.js" integrity="sha256-iA57v0fmHTuxd66SWiO2OAKXXYS7bOoZzdkAOlvkwCc=" crossorigin="anonymous"></script>
<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/asciidoctor.js/1.5.9/css/asciidoctor.min.css" integrity="sha256-AQiLdWVIAXPJbLjLPbWq0X05+lkuIpthQixX+bKOsUI=" crossorigin="anonymous"> -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/asciidoctor.js/1.5.9/nashorn/asciidoctor.min.js" integrity="sha256-inp1Sma6qVn0K1HwzTcwlFLgDaB4zABXjvVoARVvAlA=" crossorigin="anonymous"></script> -->
    <?
    return ob_get_clean();
}

function highlightjs() {
    ob_start();
    ?>
<link rel="stylesheet" href="/styles/highlightjs/default.min.css">
<script src="/scripts/highlight.min.js"></script>
    <?
    return ob_get_clean();
}

function ace_head() {
    // https://cdnjs.com/libraries/ace/
    ob_start();
    ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.3/ace.js" integrity="sha256-gkWBmkjy/8e1QUz5tv4CCYgEtjR8sRlGiXsMeebVeUo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.3/mode-asciidoc.js" integrity="sha256-foO+naVcsNURuv1DvMLWB8FZJV8FD4o05xzautlSFak=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.3/theme-xcode.js" integrity="sha256-jLrBzcgtXmwiAviu7yWHnlKYsdjqI5d7r5dEOlfaTW4=" crossorigin="anonymous"></script>
<script>
$(function() {
    $('form.edit').submit(function() {
        for (var type in {gmpriv:1, gmpub:1, plr:1}) {
            var $field = $('input[name="' + type + '"]');
            if (type in editors) {
                $field.val(editors[type].getValue());
            } else {
                $field.val('');
            }
        }
    });
});
</script>
    <?
    return ob_get_clean();
}

function ace_end() {
    // https://ace.c9.io/#nav=howto
    // https://ace.c9.io/build/kitchen-sink.html
    ob_start();
    ?>
<script>
var editors = {};
for (var type in {gmpriv:1, gmpub:1, plr:1}) {
    var $ed = $('.editor.' + type);
    if ($ed.length) {
        editors[type] = ace.edit($ed[0]);
        editors[type].setTheme("ace/theme/xcode");
        editors[type].session.setMode("ace/mode/asciidoc");
        editors[type].session.setUseWrapMode(true);
    }
}
</script>
    <?
    return ob_get_clean();
}

?>
