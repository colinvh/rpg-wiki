<?
require_once 'base.inc.php';
require_once 'lib/Game.class.php';
// require_once 'vendor/Gregwar/RST/autoload.php';
// require_once 'vendor/Netcarver/Textile/Parser.php';
// require_once 'vendor/Netcarver/Textile/DataBag.php';
// require_once 'vendor/Netcarver/Textile/Tag.php';
require_once 'vendor/htmlpurifier/library/HTMLPurifier.auto.php';
require_once 'vendor/cebe/markdown/autoload.php';

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

    static function all() {
        $stmt = db_conn()->prepare('SELECT id, game_id, url, name FROM `subject` WHERE deleted = 0');
        $stmt->bind_result($id, $gid, $url, $name);
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
        $stmt->bind_param('is', $id);
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
            if ($new['gmpriv'] != $this->heads['gmpriv']->content()) {
                $this->heads['gmpriv'] = Revision::create($this, 'gmpriv', $new['gmpriv']);
            }
        }
        if (isset($new['gmpub'])) {
            if ($new['gmpub'] != $this->heads['gmpub']->content()) {
                $this->heads['gmpub'] = Revision::create($this, 'gmpub', $new['gmpub']);
            }
        }
        if (isset($new['plr'])) {
            if ($new['plr'] != $this->heads['plr']->content()) {
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

    function set_content($type, $content) {
        switch ($type) {
            case 'gmpriv':
            case 'gmpub':
            case 'plr':
                if ($this->heads[$type]->content() != $content) {
                    $this->heads[$type] = Revision::create($this, $type, $content);
                }
                return;

            default:
                $caller = debug_backtrace()[0];
                trigger_error("Invalid content type: '$type' in {$caller['file']} on line {$caller['line']}", E_USER_ERROR);
                return null;
        }
    }

    function html_content_rst($type) {
        static $parser = null;
        if (!$parser) {
            $parser = new Gregwar\RST\Parser();
        }
        return $parser->parse($this->content($type));
    }

    function html_content_textile($type) {
        static $parser = null;
        if (!$parser) {
            $parser = new \Netcarver\Textile\Parser();
            $parser->setDocumentType('html5');
            $parser->setRestricted(true);
        }
        return $parser->parse($this->content($type));
    }

    function html_content($type) {
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
        $clean = $purifier->purify($this->content($type));
        return $parser->parse($clean);
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
        $game = Game::load(['id' => $this->game_id]);
        return $game->art_url($this->_url);
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
                $game = Game::by_id($subj->game_id);
            }
            errlog($content);
            $content = preg_replace_callback(LINK_REGEX, function($match) use ($game) {
                errlog($match);
                $title = $match[1];
                if ($title) {
                    $title .=  '|';
                }
                $subj = Subject::by_url($game, '//' . $match[2]);
                return '{{'."$title{$subj->id}}}";
            }, $content);
            errlog($content);
            $hash = hash('sha256', $content);
            $path = static::path($hash);
            if (!file_exists($path)) {
                file_put_contents($path, $content);
            }
        }
        $stmt = $conn->prepare('INSERT INTO `revision` (subj_id, type, author, hash) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isis', $subj->id, $type, $user->id, $hash);
        $stmt->execute();
        $result = new static($conn->insert_id, $subj->id, $type, $user->id, date('Y-m-d H:i:s'), $hash);
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
        $stmt = db_conn()->prepare('SELECT id, type, date, author, hash FROM `revision` WHERE subj_id = ?');
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

    public $id;
    public $sid;
    public $type;
    public $date;
    public $hash;

    protected function __construct($id, $sid, $type, $date, $author, $hash) {
        $this->id = $id;
        $this->sid = $sid;
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
        errlog($content);
        $content = preg_replace_callback(LINK_REGEX, function($match) {
            $title = $match[1];
            $subj = Subject::by_id($match[2]);
            if (!$title) {
                $title =  $subj->name;
            }
            return "[$title]({$subj->url()})";
        }, $content);
        errlog($content);
        return $content;
    }

    function editor_content() {
        $content = $this->raw_content();
        errlog($content);
        $content = preg_replace_callback(LINK_REGEX, function($match) {
            $title = $match[1];
            if ($title) {
                $title .=  '|';
            }
            $subj = Subject::by_id($match[2]);
            return '{{'."$title{$subj->_url}}}";
        }, $content);
        errlog($content);
        return $content;
    }
}

?>
