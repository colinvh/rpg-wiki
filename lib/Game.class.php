<?
require_once 'base.inc.php';
require_once 'lib/Subject.class.php';
require_once 'lib/User.class.php';

class Game {
    static $cache = ['id' => [],
                     'url' => []];

    static function create($new) {
        $conn = db_conn();
        static::validate_url($new['url']);
        $stmt = $conn->prepare('INSERT INTO `game` (url, name) VALUES (?, ?)');
        $stmt->bind_param('ss', $new['url'], $new['name']);
        $stmt->execute();
        $result = new static($conn->insert_id, $new['url'], $new['name']);
        $stmt->close();
        $result->update_users($new);
        $art_new = [
            'name' => $new['art-name'],
            'url' => $new['art-url'],
            'gmpub' => $new['art-gmpub']
        ];
        $art = Subject::create($result->id, $art_new);
        $result->update(['subj_id' => $art->id]);
        return $result;
    }

    static function all() {
        $stmt = db_conn()->prepare('SELECT id, url, name, subj_id FROM `game`');
        $stmt->bind_result($id, $url, $name, $sid);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $url, $name, $sid);
        }
        $stmt->close();
        return $result;
    }

    static function load($by=[], $respond=false) {
        if (isset($by['id'])) {
            $var = 'id';
            $param = $by['id'];
            $ptype = 'i';
        } else {
            $var = 'url';
            $param = $by['url'];
            $ptype = 's';
        }
        if (!isset(static::$cache[$var][$param])) {
            $stmt = db_conn()->prepare("SELECT id, url, name, subj_id FROM `game` WHERE $var = ?");
            $stmt->bind_param($ptype, $param);
            $stmt->bind_result($id, $url, $name, $sid);
            $stmt->execute();
            if ($stmt->fetch()) {
                $result = new static($id, $url, $name, $sid);
            } else {
                static::$cache[$var][$param] = null;
            }
            $stmt->close();
        }
        if (is_null(static::$cache[$var][$param])) {
            if ($respond) {
                respond_err(404);
            } else {
                throw new NotFoundException("Game not found ($var = '$param').");
            }
        }
        return static::$cache[$var][$param];
    }

    static function from_path($path, $respond=false) {
        $parts = explode('/', $path);
        if (count($parts) > 2) {
            if ($respond) {
                respond_err(404);
            } else {
                $path = substr($path, 1);
                throw new NotFoundException("Game not found (url = '$path').");
            }
        }
        return static::load(['url' => $parts[1]]);
    }

    static function from_article_path($path, $respond=false) {
        $parts = explode('/', $path);
        return static::load(['url' => $parts[1]]);
    }

    static function validate_url($url) {
        if ($url[0] == '_') {
            throw new InvalidURLException("Reserved URL: cannot begin with '_'");
        }
        if (strpos($url, '/') !== false) {
            throw new InvalidURLException("Reserved URL: cannot contain '/'");
        }
    }

    protected function __construct($id, $url, $name, $sid=0) {
        $this->id = $id;
        $this->_url = $url;
        $this->name = $name;
        $this->subj_id = $sid;
        $this->users = [];
        static::$cache['id'][$id] = $this;
        static::$cache['url'][$url] = $this;
    }

    function load_users() {
        $stmt = db_conn()->prepare('SELECT user_id, gm, subj_id FROM `game_user` WHERE game_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->bind_result($uid, $gm, $sid);
        $stmt->execute();
        $this->users = [];
        while ($stmt->fetch()) {
            $this->users[$uid] = [
                'user' => $uid,
                'subj' => $sid,
                'role' => $gm ? 'gm' : 'player'];
        }
        $stmt->close();
    }

    function update($new) {
        if (!empty($new['url'])) {
            static::validate_url($new['url']);
            $this->_url = $new['url'];
        }
        if (!empty($new['name'])) {
            $this->name = $new['name'];
        }
        if (!empty($new['subj_id'])) {
            $this->subj_id = $new['subj_id'];
        }
        $stmt = db_conn()->prepare('UPDATE `game` SET url = ?, name = ?, subj_id = ? WHERE id = ?');
        $stmt->bind_param('ssii', $this->_url, $this->name, $this->subj_id, $this->id);
        $stmt->execute();
        $stmt->close();
        $this->update_users($new);
    }

    function update_users($new) {
        $users = [];
        foreach ($this->users as $u) {
            $user = User::load(['id' => $u['user']]);
            $users[$user->name] = $user;
        }
        if (isset($new['user'])) {
            for ($i = 0; $i < count($new['user']); $i++) {
                $uname = $new['user'][$i];
                $gm = $new['role'][$i] == 'gm' ? 1 : 0;
                if (isset($new['art']) && $new['art'][$i]) {
                    $sid = Subject::id_from_url($this, $new['art'][$i]);
                } else {
                    $sid = null;
                }
                if (isset($users[$uname])) {
                    $this->update_user($users[$uname]->id, $gm, $sid);
                    unset($users[$uname]);
                } else {
                    $user = User::load(['name' => $uname]);
                    $this->add_user($user->id, $gm, $sid);
                }
            }
        }
        foreach ($users as $user) {
            $this->remove_user($user->id);
        }
    }

    function delete() {
        $stmt = db_conn()->prepare('UPDATE `game` SET deleted = 1 WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        // $conn = db_conn();
        // $stmt = $conn->prepare('DELETE FROM `game` WHERE id = ?');
        // $stmt->bind_param('i', $this->id);
        // $stmt->execute();
        // $stmt->close();
        // $stmt = $conn->prepare('DELETE FROM `game_user` WHERE game_id = ?');
        // $stmt->bind_param('i', $this->id);
        // $stmt->execute();
        // $stmt->close();
        // $stmt = $conn->prepare('DELETE FROM `subject` WHERE game_id = ?');
        // $stmt->bind_param('i', $this->id);
        // $stmt->execute();
        // $stmt->close();
    }

    function add_user($uid, $gm, $sid=null) {
        $stmt = db_conn()->prepare('INSERT INTO `game_user` (game_id, user_id, gm, subj_id) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiii', $this->id, $uid, $gm, $sid);
        $stmt->execute();
        $stmt->close();
        $this->users[$uid] = [
            'user' => $uid,
            'subj' => $sid,
            'role' => $gm ? 'gm' : 'player'
        ];
    }

    function update_user($uid, $gm, $sid=null) {
        $stmt = db_conn()->prepare('UPDATE `game_user` SET gm = ?, subj_id = ? WHERE game_id = ? AND user_id = ?');
        $stmt->bind_param('iiii', $gm, $sid, $this->id, $uid);
        $stmt->execute();
        $stmt->close();
        $this->users[$uid]['subj'] = $sid;
        $this->users[$uid]['role'] = $gm ? 'gm' : 'player';
    }

    function remove_user($uid) {
        $stmt = db_conn()->prepare('DELETE FROM `game_user` WHERE game_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $this->id, $uid);
        $stmt->execute();
        $stmt->close();
        unset($this->users[$uid]);
    }

    function url() {
        return '/game/' . $this->_url;
    }

    function art_url($art='') {
        if ($art) {
            $sep = '/';
        } else {
            $sep = '';
        }
        return "/art/{$this->_url}$sep$art";
    }
}

?>
