<?
require_once 'base.inc.php';

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

class User implements \JsonSerializable {
    static function create($new) {
        static::validate_name($new['name']);
        if (!isset($new['admin'])) {
            $new['admin'] = false;
        }
        $new['password'] = password_hash($new['password'], PASSWORD_BCRYPT);
        $conn = db_conn();
        $stmt = $conn->prepare('INSERT INTO `user` (name, admin, nickname, email, password) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sisss', $new['name'], $new['admin'], $new['nickname'], $new['email'], $new['password']);
        $stmt->execute();
        $result = new static($conn->insert_id, $new['name'], $new['admin'], $new['nickname'], $new['email'], $new['password']);
        $stmt->close();
        return $result;
    }

    static function load($where, $respond=false) {
        $users = static::find($where);
        if (sizeof($users)) {
            return $users[0];
        } else {
            if ($respond) {
                respond_err(404);
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
        $stmt = db_conn()->prepare("SELECT id, name, admin, nickname, email, password FROM `user` $clause");
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

    static function from_path($path, $respond=false) {
        return static::load(['name' => substr($path, 1)], $respond);
    }

    static function require_login($user=null) {
        if (!$user) {
            $user = static::from_session();
        }
        if ($user) {
            return $user;
        }
        $query = [
            'location' => $_SERVER['REQUEST_URI']
        ];
        respond_redirect(make_url('/login', $query));
    }

    static function require_admin($user=null) {
        if (!$user) {
            $user = static::from_session();
        }
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
        respond_redirect(make_url('/login', $query));
    }

    static function validate_name($name) {
        if ($name[0] == '_') {
            throw new InvalidURLException("Reserved name: cannot begin with '_'");
        }
    }

    public $id;
    public $name;
    public $admin;
    public $nickname;
    public $email;
    public $games = [];
    protected $password;

    protected function __construct($id, $name, $admin, $nickname, $email, $password) {
        $this->id = $id;
        $this->name = $name;
        $this->admin = $admin;
        $this->nickname = $nickname;
        $this->email = $email;
        $this->password = $password;
    }

    function load_games() {
        $stmt = db_conn()->prepare('SELECT game_id, gm, subj_id FROM `game_user` WHERE user_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->bind_result($gid, $gm, $sid);
        $stmt->execute();
        while ($stmt->fetch()) {
            $this->games[$gid] = [
                'id' => $gid,
                'role' => $gm ? 'gm' : 'player',
                'subj' => $sid
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

    function update($new) {
        if (!empty($new['name'])) {
            static::validate_name($new['name']);
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
        $stmt = db_conn()->prepare('UPDATE `user` SET name = ?, admin = ?, nickname = ?, email = ?, password = ? WHERE id = ?');
        $stmt->bind_param('sisssi', $this->name, $this->admin, $this->nickname, $this->email, $this->password, $this->id);
        $stmt->execute();
        $stmt->close();
        if ($password_changed) {
            expire_sessions($this->id);
        }
    }

    function delete() {
        $conn = db_conn();
        $stmt = $conn->prepare('DELETE FROM `user` WHERE id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM `game_user` WHERE user_id = ?');
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $stmt->close();
    }

    function url() {
        return '/user/' . $this->name;
    }

    function check_password($password) {
        return password_verify($password, $this->password);
    }
}

?>
