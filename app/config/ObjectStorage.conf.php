<?php
// @author: Kambiz Zandi <kambizzandi@gmail.com>

return [
    "app" => [
    ],
    "components" => [
        "db" => [
            "class" => "Framework\\db\\MySql",
        ],
        "smsgateway" => [
            "class" => "Targoman\\ObjectStorageManager\\gateways\\sms\\FaraPayamak",
        ],
        "mailer" => [
            "class" => "Targoman\\ObjectStorageManager\\gateways\\email\\SymfonyMailer",
            "transport" => [
            ],
        ],
    ],
];
