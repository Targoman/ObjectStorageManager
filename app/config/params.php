<?php
// @author: Kambiz Zandi <kambizzandi@gmail.com>

return [
    "app" => [
        "fetchlimit" => "100",
        "emailFrom" => "",
    ],
    "components" => [
        "db" => [
            "host" => "127.0.0.1",
            "port" => "3306",
            "username" => "",
            "password" => "",
            "schema" => "",
        ],
        "smsgateway" => [
            "username" => "",
            "password" => "",
            // "linenumber" => "",
            "bodyid" => [
                "service1" => [
                    "en" => "",
                    "fa" => "",
                ],
                "service2" => [
                    "en" => "",
                    "fa" => "",
                ],
            ],
        ],
        "mailer" => [
            "transport" => [
                'scheme' => "smtp",
                "host" => "",
                "username" => "",
                "password" => "",
                "port" => "",
                "options" => [
                    "verify_peer" => false,
                ],
            ],
        ],
    ],
];
