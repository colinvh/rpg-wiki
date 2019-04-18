<?
require_once 'base.inc.php';

class File {
    static $error_msg = [
        UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
        UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
        UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded",
        UPLOAD_ERR_NO_FILE    => "No file was uploaded",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
        UPLOAD_ERR_EXTENSION  => "File upload stopped by extension"
    ];

    static function create(&$file, $url) {
        global $user;
        $conn = db_conn();
        $old = $file['tmp_name'];
        $pend = static::pend_path(basename($file['tmp_name']));
        if (!move_uploaded_file($old, $pend)) {
            trigger_error(static::$error_msg[$file['error']]);
        }
        $hash = hash('sha256', file_get_contents($pend));
        $new = static::path($hash);
        $newpath = pathinfo($new, PATHINFO_DIRNAME);
        if (!file_exists($newpath)) {
            mkdir($newpath, 0750, true);
        }
        rename($pend, $new);
        $stmt = $conn->prepare('INSERT INTO `upload` (url, uploader, hash) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $url, $user->id, $hash);
        $stmt->execute();
        $result = new static($conn->insert_id, $url, $user->id, date(DATEFMT_MYSQL), $hash);
        $stmt->close();
        return $result;
    }

    static function load($where, $respond=false) {
        $files = static::find($where);
        if (sizeof($files)) {
            return $files[0];
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
                throw new NotFoundException("Uploaded file not found ($search).");
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
        $stmt = db_conn()->prepare("SELECT id, url, uploader, date, hash FROM `upload` $clause ORDER BY id DESC");
        if ($clause) {
            $stmt->bind_param($types, ...array_values($where));
        }
        $stmt->bind_result($id, $url, $uploader, $date, $hash);
        $stmt->execute();
        $result = [];
        while ($stmt->fetch()) {
            $result[] = new static($id, $url, $uploader, $date, $hash);
        }
        $stmt->close();
        return $result;
    }

    static function from_path($path) {
        return static::load(['url' => substr($path, 1)], true);
    }

    static function path($hash) {
        return $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $hash;
    }

    static function pend_path($url) {
        return $_SERVER['DOCUMENT_ROOT'] . '/upload-pending/' . $url;
    }

    function __construct($id, $url, $uploader, $date, $hash) {
        $this->id = $id;
        $this->_url = $url;
        $this->uploader = $uploader;
        $this->date = $date;
        $this->hash = $hash;
    }

    function delete() {
        global $user;
        $conn = db_conn();
        $stmt = $conn->prepare('INSERT INTO `upload` (url, uploader, hash) VALUES (?, ?, null)');
        $stmt->bind_param('ss', $this->_url, $user->id);
        $stmt->execute();
        $result = new static($conn->insert_id, $this->_url, $user->id, date(DATEFMT_MYSQL), null);
        $stmt->close();
        return $result;
    }

    function contents() {
        return file_get_contents(static::path($this->hash));
    }

    function url() {
        return '/file/' . $this->_url;
    }

    function raw_url() {
        return '/f/' . $this->_url;
    }
}

?>
