<?
require_once 'base.inc.php';

class AuthenticationCode {
    static function create($expires, $auth) {
        $conn = db_conn();
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
        $stmt = db_conn()->prepare('SELECT id, auth FROM `auth_code` WHERE code = ?');
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
        $stmt = db_conn()->prepare('DELETE FROM `auth_code` WHERE expires < NOW()');
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
        $stmt = db_conn()->prepare('DELETE FROM `auth_code` WHERE id = ?');
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
