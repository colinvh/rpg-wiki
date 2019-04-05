<?

function conn() {
    static $conn;
    static $conf = [
        'server' => 'localhost',
        'db' => 'rpg_wiki',
        'user' => 'rpg_wiki',
        'pass' => '***********'
    ];

    if (!isset($conn)) {
        $conn = new mysqli($conf['server'], $conf['user'], $conf['pass'], $conf['db']);
        if ($conn->connect_errno) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset('utf8');

        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ALL & ~MYSQLI_REPORT_INDEX;
    }

    return $conn;
}

function make_url($path, $query=[]) {
    $result = $path;
    if ($path == '/login' && isset($query['location']) && $query['location'] == '/') {
        unset($query['location']);
    }
    $char = '?';
    foreach ($query as $key => $val) {
        $result .= $char . urlencode($key) . '=' . urlencode($val);
        $char = '&';
    }
    return $result;
}

class NotFoundException extends Exception {}

class Game {
    static $cache = ['id' => [],
                     'url' => []];

    static function create($new) {
        $conn = conn();
        $stmt = $conn->prepare('INSERT INTO `game` (url, name) VALUES (?, ?)');
        $stmt->bind_param('ss', $new['url'], $new['name']);
        $stmt->execute();
        $result = new static($conn->insert_id, $new['url'], $new['name']);
        $stmt->close();
        return $result;
    }

    static function all() {
        $stmt = conn()->prepare('SELECT id, url, name FROM `game`');
        $stmt->bind_result($id, $url, $name);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $url, $name);
        }
        $stmt->close();
        foreach ($result as $res) {
            $res->load_users();
        }
        return $result;
    }

    static function load($by=[]) {
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
            $stmt = conn()->prepare("SELECT id, url, name FROM `game` WHERE $var = ?");
            $stmt->bind_param($ptype, $param);
            $stmt->bind_result($id, $url, $name);
            $stmt->execute();
            if ($stmt->fetch()) {
                $result = new static($id, $url, $name);
            } else {
                static::$cache[$var][$param] = null;
            }
            $stmt->close();
        }
        if (is_null(static::$cache[$var][$param])) {
            throw new NotFoundException("Game not found ($var = $param).");
        }
        return static::$cache[$var][$param];
    }

    protected function __construct($id, $url, $name) {
        $this->id = $id;
        $this->url = $url;
        $this->name = $name;
        static::$cache['id'][$id] = $this;
        static::$cache['url'][$url] = $this;
    }

    protected function load_users() {
        $stmt = conn()->prepare('SELECT user_id, gm FROM `game_user` WHERE game_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->bind_result($uid, $gm);
        $stmt->execute();
        $this->users = [];
        while ($stmt->fetch()) {
            $this->users[$uid] = $gm ? 'gm' : 'player';
        }
        $stmt->close();
    }

    function update($new) {
        if (!empty($new['url'])) {
            $this->url = $new['url'];
        }
        if (!empty($new['name'])) {
            $this->name = $new['name'];
        }
        $stmt = conn()->prepare('UPDATE `game` SET url = ?, name = ? WHERE id = ?');
        $stmt->bind_param('sisssi', $this->url, $this->name, $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function delete() {
        $conn = conn();
        $stmt = $conn->prepare('DELETE FROM `game` WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM `game_user` WHERE game_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM `subject` WHERE game_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function add_user($uid, $gm) {
        $stmt = conn()->prepare('INSERT INTO `game_user` (game_id, user_id, gm) VALUES (?, ?, ?)');
        $stmt->bind_param('iii', $this->id, $uid, $gm);
        $stmt->execute();
        $stmt->close();
        $this->users[$uid] = $gm ? 'gm' : 'player';
    }

    function remove_user($uid) {
        $stmt = conn()->prepare('DELETE FROM `game_user` WHERE game_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $this->id, $uid);
        $stmt->execute();
        $stmt->close();
        unset($this->users[$uid]);
    }
}

class Subject {
    static $cache = ['id' => [],
                     'url' => []];

    static function create($gid, $new) {
        $conn = conn();
        $stmt = $conn->prepare('INSERT INTO `subject` (game_id, url, name) VALUES (?, ?, ?)');
        $stmt->bind_param('isssss', $gid, $new['url'], $new['name']);
        $stmt->execute();
        $result = new static($conn->insert_id, $gid, $new['url'], $new['name']);
        $stmt->close();
        $result->update_content($new);
        return $result;
    }

    static function all() {
        $stmt = conn()->prepare('SELECT id, game_id, url, name FROM `subject`');
        $stmt->bind_result($id, $gid, $url, $name);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $gid, $url, $name);
        }
        $stmt->close();
        return $result;
    }

    static function by_id($id) {
        $stmt = conn()->prepare('SELECT game_id, url, name FROM `subject` WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->bind_result($gid, $url, $name);
        $stmt->execute();
        $found = $stmt->fetch();
        $result = new static($id, $gid, $url, $name);
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject #$id not found.");
        }
        return $result;
    }

    static function by_url($game, $url) {
        $stmt = conn()->prepare('SELECT id, name FROM `subject` WHERE game_id = ? AND url = ?');
        $stmt->bind_param('is', $game->id, $url);
        $stmt->bind_result($id, $name);
        $stmt->execute();
        $found = $stmt->fetch();
        $result = new static($id, $game->id, $url, $name);
        $stmt->close();
        if (!$found) {
            throw new NotFoundException("Subject not found ({$game->path}/$url).");
        }
        return $result;
    }

    protected $id;
    protected $game_id;
    protected $url;
    protected $name;

    protected function __construct($id, $gid, $url, $name) {
        $this->id = $id;
        $this->game_id = $gid;
        $this->url = $url;
        $this->name = $name;
        static::$cache[$id] = $this;
        if (!isset(static::$cache[$gid])) {
            static::$cache[$gid] = [];
        }
        static::$cache[$gid][$url] = $this;
        $this->heads = Revision::heads($this->id);
    }

    protected function content($type, $content=null) {
        switch ($type) {
            case 'gmpriv':
            case 'gmpub':
            case 'plr':
                if (is_null($content)) {
                    return $this->heads[$type]->content();
                } else {
                    $this->heads[$type] = Revision::create($this->id, $type, $content);
                    return;
                }
                break;

            default:
                $caller = debug_backtrace()[0];
                trigger_error("Invalid content type: '$type' in {$caller['file']} on line {$caller['line']}", E_USER_ERROR);
                return null;
        }
    }

    function update($new) {
        if (!empty($new['gid'])) {
            $this->game_id = $new['gid'];
        }
        if (!empty($new['url'])) {
            $this->url = $new['url'];
        }
        if (!empty($new['name'])) {
            $this->name = $new['name'];
        }
        $stmt = conn()->prepare('UPDATE `subject` SET game_id = ?, url = ?, name = ? WHERE id = ?');
        $stmt->bind_param('issi', $this->game_id, $this->url, $this->name, $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function delete() {
        $conn = conn();
        $stmt = $conn->prepare('DELETE FROM `subject` WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
    }
}

class Revision {
    static function create($sid, $type, $content) {
        $conn = conn();
        if ($content == '') {
            $hash = null;
        } else {
            $hash = hash('sha256', $content);
            if (!file_exists("$hash.txt")) {
                file_put_contents("$hash.txt", $content);
            }
        }
        $stmt = $conn->prepare('INSERT INTO `revision` (subj_id, type, hash) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $sid, $type, $hash);
        $stmt->execute();
        $result = new static($conn->insert_id, $sid, $type, date('Y-m-d H:i:s'), $hash);
        $stmt->close();
        return $result;
    }

    static function heads($sid) {
        $stmt = conn()->prepare('SELECT id, type, date, hash FROM `head` WHERE subj_id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->bind_result($id, $type, $date, $hash);
        $stmt->execute();
        $heads = [];
        while ($stmt->fetch()) {
            $heads[$type] = new static($id, $sid, $type, $date, $hash);
        }
        $stmt->close();
        foreach (['gmpriv', 'gmpub', 'plr'] as $type) {
            if (!isset($heads[$type])) {
                $heads[$type] = new static(null, $sid, $type, null, null);
            }
        }
        return $heads;
    }

    static function by_subject($sid) {
        $stmt = conn()->prepare('SELECT id, type, date, hash FROM `revision` WHERE subj_id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->bind_result($id, $type, $date, $hash);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $sid, $type, $date, $hash);
        }
        $stmt->close();
        return $result;
    }

    protected $id;
    protected $sid;
    protected $type;
    protected $date;
    protected $hash;

    protected function __construct($id, $sid, $type, $date, $hash) {
        $this->id = $id;
        $this->sid = $sid;
        $this->type = $type;
        $this->date = $date;
        $this->hash = $hash;
    }

    function content() {
        if (is_null($this->hash)) {
            return '';
        } else {
            return file_get_contents("{$this->hash}.txt");
        }
    }
}

class User implements \JsonSerializable {
    static function create($new) {
        if (!isset($new['admin'])) {
            $new['admin'] = false;
        }
        $new['password'] = password_hash($new['password'], PASSWORD_BCRYPT);
        $conn = conn();
        $stmt = $conn->prepare('INSERT INTO `user` (name, admin, nickname, email, password) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sisss', $new['name'], $new['admin'], $new['nickname'], $new['email'], $new['password']);
        $stmt->execute();
        $result = new static($conn->insert_id, $new['name'], $new['admin'], $new['nickname'], $new['email'], $new['password']);
        $stmt->close();
        return $result;
    }

    static function load($where) {
        $users = static::find($where);
        if (sizeof($users)) {
            return $users[0];
        } else {
            $search = '';
            foreach ($where as $key => $value) {
                if ($search) {
                    $search .= ' and ';
                }
                $search .= "$key = \"$value\"";
            }
            throw new NotFoundException("User not found ($search).");
        }
    }

    static function find($where=[]) {
        $clause = '';
        $types = '';
        foreach ($where as $key => $value) {
            if ($clause) {
                $clause .= ' AND ';
            }
            $clause .= "$key = ?";
            $types .= $key == 'id' ? 'i' : 's';
        }
        if ($clause) {
            $clause = 'WHERE ' . $clause;
        }
        $stmt = conn()->prepare("SELECT id, name, admin, nickname, email, password FROM `user` $clause");
        if ($clause) {
            $stmt->bind_param($types, ...array_values($where));
        }
        $stmt->bind_result($id, $name, $admin, $nickname, $email, $password);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $name, $admin, $nickname, $email, $password);
        }
        $stmt->close();
        return $result;
    }

    static function from_session() {
        if (isset($_SESSION['user'])) {
            return static::load(['id' => $_SESSION['user']]);
        }
    }

    static function require_login() {
        $user = static::from_session();
        if ($user) {
            return $user;
        }
        $query = [
            'location' => $_SERVER['REQUEST_URI']
        ];
        header('Location: ' . make_url('/login', $query));
        exit();
    }

    static function require_admin() {
        $user = static::from_session();
        $query = [
            'location' => $_SERVER['REQUEST_URI']
        ];
        if ($user) {
            if ($user->admin) {
                return $user;
            } else {
                $query['admin_err'] = 1;
            }
        }
        header('Location: ' . make_url('/login', $query));
        exit();
    }

    public $id;
    public $name;
    public $admin;
    public $nickname;
    public $email;
    protected $password;

    protected function __construct($id, $name, $admin, $nickname, $email, $password) {
        $this->id = $id;
        $this->name = $name;
        $this->admin = $admin;
        $this->nickname = $nickname;
        $this->email = $email;
        $this->password = $password;
    }

    protected function load_games() {
        $stmt = conn()->prepare('SELECT game_id, gm FROM `game_user` WHERE user_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->bind_result($gid, $gm);
        $stmt->execute();
        $this->games = [];
        while ($stmt->fetch()) {
            $this->games[] = [
                'id' => $gid,
                'role' => $gm ? 'gm' : 'player'
            ];
        }
        $stmt->close();
    }

    function __get($name) {
        switch ($name) {
            case 'password':
                return $this->get_pass();

            default:
                $caller = debug_backtrace()[0];
                trigger_error('Undefined property: ' . $name . ' in ' . $caller['file'] . ' on line ' . $caller['line'], E_USER_ERROR);
                return null;
        }
    }

    function __set($name, $value) {
        switch ($name) {
            case 'password':
                $this->set_pass($value);

            default:
                $caller = debug_backtrace()[0];
                trigger_error('Undefined property: ' . $name . ' in ' . $caller['file'] . ' on line ' . $caller['line'], E_USER_ERROR);
                return null;
        }
    }

    function __isset($name) {
        switch ($name) {
            case 'password':
                return isset($this->password);

            default:
                $caller = debug_backtrace()[0];
                trigger_error('Undefined property: ' . $name . ' in ' . $caller['file'] . ' on line ' . $caller['line'], E_USER_ERROR);
                return null;
        }
    }

    function __unset($name) {
        switch ($name) {
            case 'password':
                $this->password = '';

            default:
                $caller = debug_backtrace()[0];
                trigger_error('Undefined property: ' . $name . ' in ' . $caller['file'] . ' on line ' . $caller['line'], E_USER_ERROR);
                return null;
        }
    }

    function jsonSerialize() {
        $vals = [];
        $reflect = new ReflectionClass($this);
        foreach ($reflect->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $vals[$prop->name] = $prop->getValue($this);
        }
        $vals['password'] = $this->get_pass();
        return $vals;
    }

    protected function get_pass() {
        if ($this->password) {
            return '*****';
        } else {
            return null;
        }
    }

    protected function set_pass($password) {
        if (!empty($password)) {
            $this->password = password_hash($password, PASSWORD_BCRYPT);
        }
    }

    function url($base='/') {
        return $base . 'admin/user/' . $this->name;
    }

    function update($new) {
        if (!empty($new['name'])) {
            $this->name = $new['name'];
        }
        if (array_key_exists('admin', $new)) {
            $this->admin = $new['admin'];
        }
        if (!empty($new['nickname'])) {
            $this->nickname = $new['nickname'];
        }
        if (!empty($new['email'])) {
            $this->email = $new['email'];
        }
        $password_changed = false;
        if (!empty($new['password'])) {
            $this->set_pass($new['password']);
            $password_changed = true;
        }
        $stmt = conn()->prepare('UPDATE `user` SET name = ?, admin = ?, nickname = ?, email = ?, password = ? WHERE id = ?');
        $stmt->bind_param('sisssi', $this->name, $this->admin, $this->nickname, $this->email, $this->password, $this->id);
        $stmt->execute();
        $stmt->close();
        if ($password_changed) {
            expire_sessions($this->id);
        }
    }

    function delete() {
        $conn = conn();
        $stmt = $conn->prepare('DELETE FROM `user` WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM `game_user` WHERE user_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function check_password($password) {
        return password_verify($password, $this->password);
    }
}

class AuthenticationCode {
    static function create($expires, $auth) {
        $conn = conn();
        $code = static::generate_code();
        $stmt = $conn->prepare('INSERT INTO `auth_code` (code, expires, auth) VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)');
        $stmt->bind_param('sis', $code, $expires, $auth);
        $stmt->execute();
        $result = new static($conn->insert_id, $code, $auth);
        $stmt->close();
        return $result;
    }

    static function load($code) {
        static::expire_auth_codes();
        $stmt = conn()->prepare('SELECT id, auth FROM `auth_code` WHERE code = ?');
        $stmt->bind_param('s', $code);
        $stmt->bind_result($id, $auth);
        $stmt->execute();
        if ($stmt->fetch()) {
            $result = new static($id, $code, $auth);
        }
        $stmt->close();
        if (isset($result)) {
            return $result;
        } else {
            throw new NotFoundException("Authentication code \"$code\" not found.");
        }
    }

    static function expire_auth_codes() {
        $stmt = conn()->prepare('DELETE FROM `auth_code` WHERE expires < NOW()');
        $stmt->execute();
        $stmt->close();
    }

    static protected function generate_code() {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-._~';
        $max = strlen($chars) - 1;
        $code = '';
        for ($i = 0; $i < 50; ++$i) {
            $code .= $chars[random_int(0, $max)];
        }
        return $code;
    }

    protected function __construct($id, $code, $auth, $expires=null) {
        $this->id = $id;
        $this->code = $code;
        $this->auth = $auth;
        $this->expires = $expires;
    }

    function delete() {
        $stmt = conn()->prepare('DELETE FROM `auth_code` WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function confirm($type, $value=null) {
        $parts = explode(':', $this->auth);
        if ($parts[0] == $type) {
            if (is_null($value)) {
                return $parts[1];
            } else {
                return $value == $parts[1];
            }
        }
    }
}

?>
