<?php
return [
    'settings' => [
        'displayErrorDetails' => false, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => dirname(__DIR__) . '/templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => getenv('LOG_FILE'),
            'level' => \Monolog\Logger::DEBUG,
        ],
        
        // Database
        'db' => [
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_DB'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASS'),
        ],
        
        // Mailgun
        'mailgun' => [
            'api_key' => getenv('MAILGUN_KEY'),
            'template_path' => dirname(__DIR__) . '/templates/email',
            'instance' => getenv('MAILGUN_INSTANCE'),
        ],

        // Storage settings
        'storage' => [
            'static_path' => dirname(__DIR__) . '/public/static',
            'temp_path' => '/tmp',
        ],

        // App settings
        'app' => [
            'data_api' => getenv('DATA_API'),
            'core_api' => getenv('CORE_API'),
            'site' => getenv('SITE'),
            'jwt_secret' => getenv('JWT_SECRET'),
            'jwt_lifetime' => "1 month",
            'origin' => getenv('ORIGIN'),
            'user_status' => ['active', 'inactive', 'blocked'],
            'user_role' => ['user', 'moderator', 'admin'],
            'handle_type' => ['user', 'model', 'draft'],
            'static_path' => '/static',
            'female_measurements' => ['underBust'],
            'motd' => 'Please keep in mind that freesewing.org is in beta',
        ],
        'badges' => [
            'login' => '2017',
        ],

        // Migration settings
        'mmp' => [
            'public_path' => 'https://makemypattern.com/sites/default/files/styles/user_picture/public',
        ],
        
        // Measurement titles
        'measurements' => [
            'acrossBack' => 'Across back',
            'bicepsCircumference' => 'Biceps circumference',
            'centerBackNeckToWaist' => 'Centerback neck to waist',
            'chestCircumference' => 'Chest circumference',
            'headCircumference' => 'Head circumference',
            'hipsCircumference' => 'Hips circumference',
            'hipsToUpperLeg' => 'Hips to upper leg',
            'inseam' => 'Inseam',
            'naturalWaist' => 'Natural waist',
            'naturalWaistToHip' => 'Natural waist to hip',
            'naturalWaistToUnderbust' => 'Natural waist to underbust',
            'neckCircumference' => 'Neck circumference',
            'seatCircumference' => 'Seat circumference',
            'seatDepth' => 'Seat depth',
            'shoulderSlope' => 'Shoulder slope',
            'shoulderToElbow' => 'Shoulder to elbow',
            'shoulderToShoulder' => 'Shoulder to shoulder',
            'shoulderToWrist' => 'Shoulder to wrist',
            'underBust' => 'Underbust',
            'upperLegCircumference' => 'Upper leg circumference',
            'wristCircumference' => 'Wrist circumference',
        ],
    ],
];
