<?php
class UserModel {
    const FILE_NAME =  '../data/.htpasswd';
    const DB_NAME = '../data/user.db';
    const DB_FILE = '../data/user.sqlite.sql';
    /**
     * @var
     */
    private $_db = null;

    /**
     * @var null
     */
    private static $_instance = null;

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     *
     */
    public function __construct()
    {
        // SQLite
        try {
            $this->_db = new PDO('sqlite:' . self::DB_NAME);
            if (is_file(self::DB_NAME) and filesize(self::DB_NAME) == 0) {
                $sqls = file_get_contents(self::DB_FILE);
                if (!$sqls) {
                    throw new Exception ("Could not open the file!");
                }
                foreach (explode(';', $sqls) as $sql) {
                    if (trim($sql) !== '') {
                        $this->_db->exec($sql);
                    }
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        unset($this->_db);
    }

    /**
     * @param $email
     * @param $password
     * @param $salt
     * @param $iter
     */
    private function saveUserPDO($email, $password, $salt, $iter)
    {
        $data = array($email, $password, $salt, $iter);
        try {
            $sql = "INSERT INTO user (email,password, salt, iter) VALUES (?,?,?,?)";
            $sth = $this->_db->prepare($sql);
            $sth->execute($data);
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }

    /**
     * @param $email
     * @return mixed
     */
    public function userExistsPDO($email) {
        $sql = "SELECT email, password, salt, iter FROM user WHERE email = '$email'";
        $sth = $this->_db->prepare($sql);
        $sth->execute();
        return $sth->fetch();
    }

    /**
     * @param $email
     * @return bool
     */
    public function userExists($email) {
        if (!is_file(self::FILE_NAME)){
            return false;
        }
        $file = file(self::FILE_NAME);
        foreach ($file as $line) {
            if (strpos($line, $email) !== false)
                return $line;
        }
        return false;
    }

    /**
     * @param $string
     * @param $salt
     * @param $iterationCount
     * @return string
     */
    public function getHash($string, $salt, $iterationCount) {
        for ($i = 0; $i < $iterationCount; $i++) {
            $string = sha1($string . $salt);
        }
        return $string;
    }

    /**
     * @param $email
     * @param $password
     * @return bool
     */
    public function saveHash($email, $password) {
        $salt = str_replace('=', '', base64_encode(md5(microtime())));
        $iteration=rand (10,50);
        $hash = $this->getHash($password,$salt,$iteration);
        $str = "$email:$hash:$salt:$iteration\n";
        //if (file_put_contents(self::FILE_NAME, $str, FILE_APPEND)){
        if ($this->saveUserPDO($email,$hash,$salt,$iteration)){
            return true;
        } else {
            return false;
        }

    }
}