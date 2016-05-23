<?php

/**
 * This file is Part of eghojansu/Wai
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */

namespace Wai\Test;

use Wai;

class WaiTest extends \PHPUnit_Framework_TestCase
{
    protected function getInstalationConfig($version)
    {
        $instalationConfig = [
            'version'      => $version,
            'workingDir'   => realpath(__DIR__.'/..').'/tmp',
            'schemaDir'    => realpath(__DIR__.'/..').'/data/schema',
            'database'     => [
                'dsn'      => 'mysql:host=127.0.0.1',
                'username' => 'root',
                'password' => null,
                'options'  => [],
                'dbname'   => 'test_wai',
                'dropdb'   => true,
            ],
        ];

        return $instalationConfig;
    }

    protected function thirdSchemaFile()
    {
        $cfg = $this->getInstalationConfig(null);
        $file = $cfg['schemaDir'] . '/3 third.sql';

        return $file;
    }

    protected function createThirdSchema()
    {
        $file = $this->thirdSchemaFile();
        file_put_contents($file, <<<SQL
CREATE TABLE test_third_one (
    id int(11) not null auto_increment,
    name varchar(25) null default null,
    primary key (id)
);

CREATE TABLE test_third_two (
    id int(11) not null auto_increment,
    name varchar(25) null default null,
    primary key (id)
);
SQL
);
    }

    public function teardown()
    {
        $file = $this->thirdSchemaFile();
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function testFirstInstall()
    {
        Wai::start(__FILE__, __LINE__);
        $instalationConfig = $this->getInstalationConfig('0.1.0');
        Wai::setup($instalationConfig);
        if (Wai::isNotInstalled()) {
            Wai::handleInstallation();
        }
        Wai::finish(__FILE__, __LINE__);
        $result = Wai::result();

        $this->assertContains('Database installation complete!', $result);
        $this->assertTrue(file_exists(Wai::getInstalledVersionFile()));
        $this->assertTrue(file_exists(Wai::getInstalledSchemaFile()));
    }

    /**
     * @depends testFirstInstall
     */
    public function testSecondInstall()
    {
        $file = __FILE__;
        Wai::start($file, $startLine = __LINE__);
        $instalationConfig = $this->getInstalationConfig('0.2.0');
        // create new schema
        $this->createThirdSchema();
        Wai::setup($instalationConfig);
        if (Wai::isNotInstalled()) {
            $callbacks = [function() {
                file_put_contents(Wai::getWorkingDir().'test_callback', 'blank');
            }];
            $callbacksAfter = [function() {
                file_put_contents(Wai::getWorkingDir().'test_callback_after', 'blank');
            }];
            Wai::handleInstallation($callbacks, $callbacksAfter);
        }
        Wai::finish($file, $endLine = __LINE__);
        $result = Wai::result();

        $this->assertContains('Database installation complete!', $result);
        $this->assertContains('You can remove line in '.$file.' start from #'.$startLine.' until #'.$endLine, $result);
        $this->assertTrue(file_exists(Wai::getInstalledVersionFile()));
        $this->assertTrue(file_exists(Wai::getInstalledSchemaFile()));
    }
}