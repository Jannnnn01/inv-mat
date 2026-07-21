<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Health extends BaseConfig
{
    public bool $databaseCheck = false;
}
