<?php

declare(strict_types=1);

namespace Tests;

use Oscryn\Database\DBConnector;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER = [];

        DBConnector::purge();
        DBConnector::connection()->exec('PRAGMA foreign_keys = ON');

        $this->migrate();
    }

    protected function tearDown(): void
    {
        DBConnector::purge();

        parent::tearDown();
    }

    protected function migrate(): void
    {
        $pdo = DBConnector::connection();

        $pdo->exec(
            'CREATE TABLE todos ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            .'title VARCHAR(255) NOT NULL, '
            .'completed INTEGER NOT NULL DEFAULT 0, '
            .'created_at DATETIME NULL, '
            .'updated_at DATETIME NULL, '
            .'deleted_at DATETIME NULL'
            .')'
        );

        $pdo->exec(
            'CREATE TABLE users ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            .'name VARCHAR(255) NOT NULL, '
            .'email VARCHAR(255) NOT NULL, '
            .'password VARCHAR(255) NOT NULL, '
            .'created_at DATETIME NULL, '
            .'updated_at DATETIME NULL'
            .')'
        );

        $pdo->exec(
            'CREATE TABLE posts ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            .'user_id INTEGER NOT NULL, '
            .'title VARCHAR(255) NOT NULL, '
            .'created_at DATETIME NULL, '
            .'updated_at DATETIME NULL'
            .')'
        );

        $pdo->exec(
            'CREATE TABLE notes ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            .'body VARCHAR(255) NOT NULL, '
            .'created_at DATETIME NULL, '
            .'updated_at DATETIME NULL, '
            .'deleted_at DATETIME NULL'
            .')'
        );

        $pdo->exec(
            'CREATE TABLE widgets ('
            .'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            .'int_col INTEGER NULL, '
            .'float_col REAL NULL, '
            .'str_col VARCHAR(255) NULL, '
            .'json_col TEXT NULL, '
            .'bool_col INTEGER NULL, '
            .'name VARCHAR(255) NULL, '
            .'created_at DATETIME NULL, '
            .'updated_at DATETIME NULL'
            .')'
        );
    }
}
