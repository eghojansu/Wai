<?php

/**
 * This file is Part of eghojansu/Wai
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */

/**
 * Wai main class
 */
class Wai
{
    //! Package info
    const
        PACKAGE = 'eghojansu/wai',
        VERSION = '0.1.2';

    //! filename
    const
        INSTALLED_FILENAME = 'wai_installed',
        INSTALLED_SCHEMA   = 'wai_schema';
    //! installed version delimiter
    const VERSION_DELIMITER  = "\n";
    //! status
    const
        STATUS_FAILED = 0,
        STATUS_SUCCESS = 1;

    /**
     * Config
     * @var array
     */
    protected static $config = [
        // current version
        'version'      => '0.1.0',
        // used for saving installed version and schema
        'workingDir'   => 'tmp/',
        // schema directory that contains every schema that need to be installed
        // filename should be prefixed by ordered number
        'schemaDir'    => 'app/schema/',
        // argument for constructing PDO class
        'database'     => [
            // dsn, string, without database name
            'dsn'      => 'mysql:host=127.0.0.1',
            // username, string
            'username' => 'root',
            // password, string
            'password' => null,
            // options, array
            'options'  => [],
            // database name
            'dbname'   => 'test_wai',
            // drop db first
            'dropdb'   => false,
        ],
    ];
    /**
     * Info
     * @var array
     */
    protected static $info = [
        'file'      => null,
        'lineStart' => null,
        'lineEnd'   => null,
    ];
    /**
     * Handle result
     * @var string
     */
    protected static $message;
    /**
     * Status
     * @var bool
     */
    protected static $status;

    /**
     * getInstalledVersionFile
     * @return string
     */
    public static function getInstalledVersionFile()
    {
        $file = self::getWorkingDir().self::INSTALLED_FILENAME;

        return $file;
    }

    /**
     * getInstalledSchemaFile
     * @return string
     */
    public static function getInstalledSchemaFile()
    {
        $file = self::getWorkingDir().self::INSTALLED_SCHEMA;

        return $file;
    }

    /**
     * Start flag
     * @param  string $file
     * @param  string|integer $lineNumber
     */
    public static function start($file, $lineNumber)
    {
        self::$info['file'] = $file;
        self::$info['lineStart'] = $lineNumber;
    }

    /**
     * End flag
     * @param  string $file
     * @param  string|integer $lineNumber
     */
    public static function finish($file, $lineNumber)
    {
        self::$info['file'] = $file;
        self::$info['lineEnd'] = $lineNumber;
    }

    /**
     * Setup config
     * @param  array  $config
     */
    public static function setup(array $config)
    {
        self::$config = $config + self::$config;
    }

    /**
     * Check if it was installed
     * @return bool
     */
    public static function isInstalled()
    {
        return self::getCurrentVersion() === self::getInstalledVersion();
    }

    /**
     * Check if it was not installed
     * @return bool
     */
    public static function isNotInstalled()
    {
        return self::getCurrentVersion() !== self::getInstalledVersion();
    }

    /**
     * Check failed status
     * @return bool
     */
    public static function failed()
    {
        return !self::success();
    }

    /**
     * Check success status
     * @return bool
     */
    public static function success()
    {
        return self::$status === self::STATUS_SUCCESS;
    }

    /**
     * Handle instalation
     * @param  array $beforeDB
     * @param  array $afterDB
     * @return bool
     */
    public static function handleInstallation(array $beforeDB = [], array $afterDB = [])
    {
        self::$status = self::STATUS_SUCCESS;
        self::doInstallAdditionalProcedure($beforeDB);
        $installed  = self::doInstallDatabaseProcedure();
        self::doInstallAdditionalProcedure($afterDB);

        if (self::success()) {
            self::addInstalledSchema($installed);
            self::addInstalledVersion();
        }

        return self::success();
    }

    /**
     * Get result
     * @return string
     */
    public static function result()
    {
        $result = self::$message;

        if (self::success()) {
            $result .= (self::$info['file']? self::VERSION_DELIMITER
                . 'You can remove line in '.self::$info['file']
                . (self::$info['lineStart']?' start from line '.self::$info['lineStart']:'')
                . (self::$info['lineEnd']?' until line '.self::$info['lineEnd']:'')
                . self::VERSION_DELIMITER
                . ' (you can remove between that line)'
                : ''
              );
        }

        return $result;
    }

    /**
     * Get current version
     * @return string
     */
    public static function getCurrentVersion()
    {
        return self::$config['version'];
    }

    /**
     * Get Installed version
     * @return string|null
     */
    public static function getInstalledVersion()
    {
        $file = self::getInstalledVersionFile();

        if (is_readable($file)) {
            $content = file_get_contents($file);
            $content = explode(self::VERSION_DELIMITER, $content);
            $content = array_filter($content);
            $last    = array_pop($content);

            return $last;
        }

        return null;
    }

    /**
     * Get working dir
     * @return string
     */
    public static function getWorkingDir()
    {
        self::createWorkingDir();

        return self::fixSlashes(self::$config['workingDir']);
    }

    /**
     * Get schema dir
     * @return string
     */
    public static function getSchemaDir()
    {
        return self::fixSlashes(self::$config['schemaDir']);
    }

    /**
     * Fix slashes and append slash at the end
     * @param  string $dir
     * @return string
     */
    public static function fixSlashes($dir)
    {
        return rtrim(strtr($dir, '\\', '/'), '/').'/';
    }

    /**
     * Get pdo instance
     * @return PDO
     */
    public static function getPDO()
    {
        $db = self::$config['database'];

        if (!isset($db['dsn'], $db['username'], $db['dbname'])) {
            throw new InvalidArgumentException('Invalid database configuration!');
        }

        $pdo = new PDO($db['dsn'], $db['username'],
            isset($db['password'])?$db['password']:null,
            isset($db['options'])?$db['options']:[]);
        if (isset($db['dropdb'])) {
            $pdo->exec('DROP DATABASE IF EXISTS '.$db['dbname']);
        }
        $pdo->exec('CREATE DATABASE IF NOT EXISTS '.$db['dbname']);
        $pdo->exec('USE '.$db['dbname']);

        return $pdo;
    }

    /**
     * Get schema files
     * @return array
     */
    public static function getSchemaFiles()
    {
        $contents = self::readDir(self::getSchemaDir(), ['sql']);

        return $contents;
    }

    /**
     * Get installed schema
     * @return array
     */
    public static function getInstalledSchemas()
    {
        $file = self::getInstalledSchemaFile();

        if (is_readable($file)) {
            $content = file_get_contents($file);
            $content = explode(self::VERSION_DELIMITER, $content);
            $content = array_filter($content);

            return $content;
        }

        return [];
    }

    /**
     * Get schema to install
     * @return array
     */
    public static function getSchemaToInstall()
    {
        $schemaDir = self::getSchemaDir();
        $schemas = self::getSchemaFiles();
        $installed = self::getInstalledSchemas();

        foreach ($schemas as $key => $schema) {
            $schema = str_replace($schemaDir, '', $schema);
            if (in_array($schema, $installed)) {
                unset($schemas[$key]);
            }
        }

        return $schemas;
    }

    /**
     * Hold
     */
    public static function hold()
    {
        $message = self::result();
        if ($message) {
            echo $message;
            die;
        }
    }

    /**
     * Read dir
     * @param  string $dir
     * @param  array  $exts allowed extension
     * @return array
     */
    public static function readDir($dir, array $exts = [])
    {
        $contents = [];
        if (false === file_exists($dir)) {
            return $contents;
        }

        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $entry) {
            $basename = $entry->getBasename();
            if ($entry->isDot() || '.' === $basename[0]) {
                continue;
            }
            if ($entry->isDir()) {
                $contents = array_merge($contents, self::readDir($entry->getPathname(), $exts));
            } else {
                $ext = $entry->getExtension();
                if ($exts && !in_array($ext, $exts)) {
                    continue;
                }
                $contents[] = $entry->getPathname();
            }
        }

        rsort($contents);

        return $contents;
    }

    /**
     * Create working dir if not exists
     */
    protected static function createWorkingDir()
    {
        $workingDir = self::fixSlashes(self::$config['workingDir']);
        if (false === file_exists($workingDir)) {
            mkdir($workingDir, true);
            chmod($workingDir, 0777);
        }
        self::$config['workingDir'] = $workingDir;
    }

    /**
     * Install database
     */
    protected static function doInstallDatabaseProcedure()
    {
        if (self::failed()) {
            return [];
        }

        $pdo = self::getPDO();
        $schemas = self::getSchemaToInstall();

        $pattern = '/^(?<no>\d+)\w*.+$/';
        usort($schemas, function($a, $b) use ($pattern) {
            // get number
            if (preg_match($pattern, $a, $match)) {
                $a = 1*$match['no'];

                if (preg_match($pattern, $b, $match)) {
                    $b = 1*$match['no'];
                }
            }

            return $a === $b ? 0 : ( $a < $b ? -1 : 1);
        });

        $errors = [];
        foreach ($schemas as $key => $schema) {
            $sql = file_get_contents($schema);
            if ($sql) {
                $pdo->exec($sql);
                if ('00000' !== $pdo->errorCode()) {
                    $e = $pdo->errorInfo();
                    $errors[$schema] = $e[2];
                }
            }
        }

        if ($errors) {
            self::$message = 'Database installation incomplete!'
                           . self::VERSION_DELIMITER
                           . 'Error in file(s) :'
                           . self::VERSION_DELIMITER;
            foreach ($errors as $file => $error) {
                self::$message .= $file .' ('.$error.')'.self::VERSION_DELIMITER;
            }
            self::$status = self::STATUS_FAILED;
        } else {
            self::$message = 'Database installation complete!';
        }

        return $schemas;
    }

    /**
     * Additional procedure
     * @param  array $additionalProcedures
     */
    protected static function doInstallAdditionalProcedure(array $additionalProcedures)
    {
        $success = self::success();
        foreach ($success?$additionalProcedures:[] as $callback) {
            $success &= !(false === call_user_func($callback));
            if (!$success) {
                break;
            }
        }
        self::$status = $success?self::STATUS_SUCCESS:self::STATUS_FAILED;

        return $success;
    }

    /**
     * Record installed version
     */
    protected static function addInstalledVersion()
    {
        if (false !== ($handle = fopen(self::getInstalledVersionFile(), 'a'))) {
            fwrite($handle, self::$config['version'].self::VERSION_DELIMITER);
            fclose($handle);
        }
    }

    /**
     * Record installed schema
     * @param array $schemas
     */
    protected static function addInstalledSchema(array $schemas)
    {
        if (false !== ($handle = fopen(self::getInstalledSchemaFile(), 'a'))) {
            $schemas = implode(self::VERSION_DELIMITER, $schemas);
            $schemas = str_replace(self::getSchemaDir(), '', $schemas);
            fwrite($handle, $schemas.self::VERSION_DELIMITER);
            fclose($handle);
        }
    }
}