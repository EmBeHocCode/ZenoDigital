<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;

    public function __construct(array $config)
    {
        $this->db = Database::getConnection($config['db']);
    }
}
