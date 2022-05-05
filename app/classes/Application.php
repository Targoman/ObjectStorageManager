<?php
// @author: Kambiz Zandi <kambizzandi@gmail.com>

namespace Targoman\ObjectStorageManager\classes;

use Framework\core\Application as BaseApplication;

class Application extends BaseApplication {

    public function run() {
        $a = shell_exec('ps -aux | grep "ObjectStorageManager.php" | grep "php "');
        if (substr_count($a, "\n") > 2) {
            echo "ObjectStorageManager is running\n";
            return;
        }

        echo "Starting Object Storage Manager\n";
    }

};
