<?
require_once 'base.inc.php';
require_once 'lib/Game.class.php';
// require_once 'vendor/Gregwar/RST/autoload.php';
// require_once 'vendor/Netcarver/Textile/Parser.php';
// require_once 'vendor/Netcarver/Textile/DataBag.php';
// require_once 'vendor/Netcarver/Textile/Tag.php';
// require_once 'vendor/htmlpurifier/library/HTMLPurifier.auto.php';
require_once 'vendor/cebe/markdown/autoload.php';
// require_once 'vendor/wikirenderer/src/WikiRenderer.lib.php';
// require_once 'vendor/wikirenderer/src/rules/phpwiki_to_dokuwiki.php';
// require_once 'vendor/wikirenderer/src/rules/dokuwiki_to_xhtml.php';
// require_once 'vendor/mediawiki/includes/WebStart.php';
// require_once 'vendor/mediawiki/includes/parser/ParserOptions.php';
require_once 'vendor/creole/autoload.php';

// use Gregwar\RST\Parser;

define('LINK_REGEX', '/{{(?:((?:[^}|]|}[^}|])+)\|)?((?:[^}]|}[^}])+)}}/');

class Subject {
    static $cache = ['id' => [],
                     'url' => []];

    static function create($gid, $new, $existing=null) {
        $conn = db_conn();
        static::validate_url($new['url']);
        $stmt = $conn->prepare('INSERT INTO `subject` (game_id, url, name) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $gid, $new['url'], $new['name']);
        $stmt->execute();
        if ($existing) {
            $existing->id = $conn->insert_id;
            $existing->game_id = $gid;
            $existing->_url = $new['url'];
            $existing->name = $new['name'];
            $result = $existing;
        } else {
            $result = new static($conn->insert_id, $gid, $new['url'], $new['name']);
        }
        $stmt->close();
        $result->load_heads();
        $result->update_content($new);
        return $result;
    }

    static function all($gid) {
        $stmt = db_conn()->prepare('SELECT id, url, name FROM `subject` WHERE game_id = ?'); // AND deleted = 0
        $stmt->bind_param('i', $gid);
        $stmt->bind_result($id, $url, $name);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $gid, $url, $name);
        }
        $stmt->close();
        foreach ($result as $res) {
            $res->load_heads();
        }
        return $result;
    }

    static function search($gid, $gm, $search) {
        $terms = preg_split('/\s+/', $search);
        $result = [];
        $all = static::all($gid);
        foreach ($all as $subj) {
            if (static::_search($subj, $gm, $terms)) {
                $result[] = $subj;
            }
        }
        return $result;
    }

    static function _search($subj, $gm, $terms) {
        foreach ($terms as $t) {
            if (static::word_prefix_match($subj->name, $t)) {
                return true;
            }
        }
        $types = [];
        if ($gm) {
            $types[] = 'gmpriv';
        }
        $types[] = 'gmpub';
        $types[] = 'plr';
        foreach ($types as $type) {
            $content = $subj->heads[$type]->editor_content();
            foreach ($terms as $t) {
            if (static::word_prefix_match($content, $t)) {
                    return true;
                }
            }
        }
    }

    static function word_prefix_match($content, $word) {
        // errlog($word);
        $clean = preg_quote($word);
        // errlog($clean);
        $pattern = "/\b$clean/";
        // errlog($pattern)
        return preg_match($pattern, $content);
    }

    static function by_id($id) {
        $stmt = db_conn()->prepare('SELECT game_id, url, name FROM `subject` WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->bind_result($gid, $url, $name);
        $stmt->execute();
        $found = $stmt->fetch();
        $result = new static($id, $gid, $url, $name);
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject #$id not found.");
        }
        $result->load_heads();
        return $result;
    }

    static function by_url($game, $url) {
        $url = implode('/', array_slice(explode('/', $url), 2));
        $stmt = db_conn()->prepare('SELECT id, name FROM `subject` WHERE game_id = ? AND url = ?');
        $stmt->bind_param('is', $game->id, $url);
        $stmt->bind_result($id, $name);
        $stmt->execute();
        $found = $stmt->fetch();
        $result = new static($id, $game->id, $url, $name);
        $result->game = $game;
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject not found ({$game->_url}/$url).");
        }
        $result->load_heads();
        return $result;
    }

    static function from_path($path) {
        $parts = explode('/', $path);
        $spath = implode('/', array_slice($parts, 2));
        $game = Game::from_article_path($path);
        try {
            $result = static::by_url($game, $path);
        } catch (NotFoundException $ex) {
            $result = new static(null, $game->id, $spath, '');
            $result->load_heads();
        }
        $result->game = $game;
        return $result;
    }

    static function id_from_url($game, $url) {
        $stmt = db_conn()->prepare('SELECT id FROM `subject` WHERE game_id = ? AND url = ?');
        $stmt->bind_param('is', $game->id, $url);
        $stmt->bind_result($id);
        $stmt->execute();
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject $url from game {$game->name} not found.");
        }
        return $id;
    }

    static function url_from_id($id) {
        $stmt = db_conn()->prepare('SELECT url FROM `subject` WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->bind_result($url);
        $stmt->execute();
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject #$id not found.");
        }
        return $url;
    }

    static function validate_url($url) {
        foreach (explode('/', $url) as $seg) {
            if ($seg[0] == '_') {
                throw new InvalidURLException("Reserved URL: path segment cannot begin with '_'");
            }
        }
    }

    static function make_url($gid, $_url) {
        static $cache = [];
        if (isset($cache[$gid])) {
            $game_url = $cache[$gid];
        } else {
            $game_url = $cache[$gid] = Game::url_from_id($gid);
        }
        return "/art/$game_url/$_url";
    }

    public $id;
    public $game_id;
    public $_url;
    public $name;
    public $heads = [];

    protected function __construct($id, $gid, $url, $name) {
        $this->id = $id;
        $this->game_id = $gid;
        $this->_url = $url;
        $this->name = $name;
        static::$cache['id'][$id] = $this;
        if (!isset(static::$cache['url'][$gid])) {
            static::$cache['url'][$gid] = [];
        }
        static::$cache['url'][$gid][$url] = $this;
    }

    function load_heads($run=true) {
        $this->heads = Revision::heads($this->id, $run);
    }

    function update_content($new) {
        if (isset($new['gmpriv'])) {
            if ($new['gmpriv'] != $this->heads['gmpriv']->editor_content()) {
                $this->heads['gmpriv'] = Revision::create($this, 'gmpriv', $new['gmpriv']);
            }
        }
        if (isset($new['gmpub'])) {
            if ($new['gmpub'] != $this->heads['gmpub']->editor_content()) {
                $this->heads['gmpub'] = Revision::create($this, 'gmpub', $new['gmpub']);
            }
        }
        if (isset($new['plr'])) {
            if ($new['plr'] != $this->heads['plr']->editor_content()) {
                $this->heads['plr'] = Revision::create($this, 'plr', $new['plr']);
            }
        }
    }

    function content($type, $for='view') {
        switch ($type) {
            case 'gmpriv':
            case 'gmpub':
            case 'plr':
                switch ($for) {
                    case 'view':
                        return $this->heads[$type]->content();

                    case 'edit':
                        return $this->heads[$type]->editor_content();

                    case 'store':
                        return $this->heads[$type]->raw_content();

                    default:
                        $caller = debug_backtrace()[0];
                        trigger_error("Invalid content purpose: '$for' in {$caller['file']} on line {$caller['line']}", E_USER_ERROR);
                        return null;
                }

            default:
                $caller = debug_backtrace()[0];
                trigger_error("Invalid content type: '$type' in {$caller['file']} on line {$caller['line']}", E_USER_ERROR);
                return null;
        }
    }

    function update($new) {
        if (is_null($this->id)) {
            static::create($this->game_id, $new, $this);
            return;
        }
        if (!empty($new['gid'])) {
            $this->game_id = $new['gid'];
        }
        if (!empty($new['url'])) {
            static::validate_url($new['url']);
            $this->_url = $new['url'];
        }
        if (!empty($new['name'])) {
            $this->name = $new['name'];
        }
        $stmt = db_conn()->prepare('UPDATE `subject` SET game_id = ?, url = ?, name = ? WHERE id = ?');
        $stmt->bind_param('issi', $this->game_id, $this->_url, $this->name, $this->id);
        $stmt->execute();
        $stmt->close();
        $this->update_content($new);
    }

    function delete() {
        $stmt = db_conn()->prepare('UPDATE `subject` SET deleted = 1 WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        $this->update_content(['gmpriv' => '',
                               'gmpub' => '',
                               'plr' => '']);
        // $stmt = db_conn()->prepare('DELETE FROM `subject` WHERE id = ?');
        // $stmt->bind_param('i', $this->id);
        // $stmt->execute();
        // $stmt->close();
    }

    function url() {
        if (isset($this->game)) {
            return $this->game->art_url($this->_url);
        } else {
            return static::make_url($this->game_id, $this->_url);
        }
    }
}

class Revision {
    static function create($subj, $type, $content) {
        global $user;
        $conn = db_conn();
        if ($content == '') {
            $hash = null;
        } else {
            if (isset($subj->game)) {
                $game = $subj->game;
            } else {
                $game = Game::load(['id' => $subj->game_id]);
            }
            $content = preg_replace_callback(LINK_REGEX, function($match) use ($game) {
                $title = $match[1];
                if ($title) {
                    $title .=  '|';
                }
                $subj = Subject::by_url($game, '//' . $match[2]);
                return '{{'."$title{$subj->id}}}";
            }, $content);
            $hash = hash('sha256', $content);
            $path = static::path($hash);
            if (!file_exists($path)) {
                file_put_contents($path, $content);
            }
        }
        $stmt = $conn->prepare('INSERT INTO `revision` (subj_id, type, author, hash) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isis', $subj->id, $type, $user->id, $hash);
        $stmt->execute();
        $result = new static($conn->insert_id, $subj->id, $type, $user->id, date(DATEFMT_MYSQL), $hash);
        $stmt->close();
        return $result;
    }

    static function heads($sid, $run=true) {
        if ($run) {
            $stmt = db_conn()->prepare('SELECT id, type, date, author, hash FROM `head` WHERE subj_id = ?');
            $stmt->bind_param('i', $sid);
            $stmt->bind_result($id, $type, $date, $author, $hash);
            $stmt->execute();
            $heads = [];
            while ($stmt->fetch()) {
                $heads[$type] = new static($id, $sid, $type, $date, $author, $hash);
            }
            $stmt->close();
        }
        foreach (['gmpriv', 'gmpub', 'plr'] as $type) {
            if (!isset($heads[$type])) {
                $heads[$type] = new static(null, $sid, $type, null, null, null);
            }
        }
        return $heads;
    }

    static function by_id($id) {
        $stmt = db_conn()->prepare('SELECT subj_id, type, date, author, hash FROM `revision` WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->bind_result($sid, $type, $date, $author, $hash);
        $stmt->execute();
        $found = $stmt->fetch();
        $stmt->close();
        if ($found) {
            return new static($id, $sid, $type, $date, $author, $hash);
        } else {
            throw new NotFoundException("Revision '$id' not found.");
        }
    }

    static function by_subject($sid) {
        $stmt = db_conn()->prepare('SELECT id, type, date, author, hash FROM `revision` WHERE subj_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $sid);
        $stmt->bind_result($id, $type, $date, $author, $hash);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $sid, $type, $date, $author, $hash);
        }
        $stmt->close();
        return $result;
    }

    static function path($hash) {
        return $_SERVER['DOCUMENT_ROOT'] . '/revs/' . $hash . '.txt';
    }

    static function parse_rst($content) {
        static $parser = null;
        if (!$parser) {
            $parser = new Gregwar\RST\Parser();
        }
        return $parser->parse($content);
    }

    static function parse_textile($content) {
        static $parser = null;
        if (!$parser) {
            $parser = new \Netcarver\Textile\Parser();
            $parser->setDocumentType('html5');
            $parser->setRestricted(true);
        }
        return $parser->parse($content);
    }

    static function parse_markdown($content) {
        static $purifier = null;
        static $parser = null;
        if (!$purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.AllowedElements', [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'hr',
                'pre', 'code',
                'blockquote',
                'table', 'tr', 'td', 'th', 'thead', 'tbody',
                'strong', 'em', 'b', 'i', 'u', 's', 'span',
                'a', 'p', 'br', #'nobr',
                'ul', 'ol', 'li',
                'img',
            ]);
            $config->set('HTML.AllowedAttributes', ['th.align', 'td.align', 'ol.start', 'code.class', 'img.src']);
            $purifier = new HTMLPurifier($config);
        }
        if (!$parser) {
            $parser = new \cebe\markdown\Markdown();
            $parser->html5 = true;
            $parser->keepListStartNumber = true;
        }

        $clean = $purifier->purify($content);
        return $parser->parse($clean);
    }

    static function parse_wr($content) {
        static $parser = null;
        static $parser2;
        if (!$parser) {
            $parser = new WikiRenderer('phpwiki_to_dokuwiki');
            $parser2 = new WikiRenderer('dokuwiki_to_xhtml');
        }
        return $parser2->render($parser->render($content));
    }

    static function parse_mediawiki($content) {
        static $parser = null;
        static $opts;
        if (!$parser) {
            $parser = new Parser();
            $opts = new ParserOptions();
        }
        $output = $parser->parse($content, '', $opts);
        return $output->getText();
    }

    static function parse_creole($content) {
        static $parser = null;
        if (!$parser) {
            $parser = new \softark\creole\Creole();
            $parser->html5 = true;
            $parser->keepListStartNumber = true;
        }

        return $parser->parse($content);
    }

    static function parse($content) {
        return $content;
    }

    public $id;
    public $sid;
    public $type;
    public $date;
    public $hash;

    protected function __construct($id, $sid, $type, $date, $author, $hash) {
        $this->id = $id;
        $this->subj_id = $sid;
        $this->type = $type;
        $this->date = $date;
        $this->author = $author;
        $this->hash = $hash;
    }

    function raw_content() {
        if (is_null($this->hash)) {
            return '';
        } else {
            $content = file_get_contents(static::path($this->hash));
            return $content;
        }
    }

    function content() {
        $content = $this->raw_content();
        $content = preg_replace_callback(LINK_REGEX, function($match) {
            $title = $match[1];
            $subj = Subject::by_id($match[2]);
            if (!$title) {
                $title =  $subj->name;
            }
            $url = $subj->url();
            return "link:{$url}[$title]";
        }, $content);
        return static::parse($content);
    }

    function editor_content() {
        $content = $this->raw_content();
        $content = preg_replace_callback(LINK_REGEX, function($match) {
            $title = $match[1];
            if ($title) {
                $title .=  '|';
            }
            $subj = Subject::by_id($match[2]);
            return '{{'."$title{$subj->_url}}}";
        }, $content);
        return $content;
    }

    function url() {
        $subj = Subject::by_id($this->subj_id);
        return $subj->url() . '/_rev?rev=' . $this->id;
    }
}

?>
