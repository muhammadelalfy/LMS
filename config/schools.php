<?php

/*
 * What each plan allows. A school's own `max_students` (set by the operator)
 * overrides its plan's; null means unlimited.
 */
return [
    'default_plan' => 'trial',

    'plans' => [
        'trial' => ['label' => 'تجريبية', 'max_students' => 30, 'trial_days' => 14],
        'basic' => ['label' => 'أساسية', 'max_students' => 200, 'trial_days' => null],
        'pro' => ['label' => 'احترافية', 'max_students' => 1000, 'trial_days' => null],
        'enterprise' => ['label' => 'مؤسسات', 'max_students' => null, 'trial_days' => null],
    ],

    // Names a school cannot take as its subdomain.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'central', 'mail', 'ftp', 'static', 'cdn', 'ws', 'media', 'turn', 'status', 'support', 'billing'],

    // A school's own settings (stored with the school) that replace the platform's.
    'settings' => [
        'fawry_merchant_code' => 'services.fawry.merchant_code',
        'fawry_secure_key' => 'services.fawry.secure_key',
        'sms_gateway_url' => 'services.sms.url',
        'sms_gateway_token' => 'services.sms.token',
        'sms_sender' => 'services.sms.sender',
        'sms_webhook_secret' => 'services.sms.webhook_secret',
        'whatsapp_token' => 'services.whatsapp.token',
        'whatsapp_phone_number_id' => 'services.whatsapp.phone_number_id',
        'whatsapp_app_secret' => 'services.whatsapp.app_secret',
        'whatsapp_verify_token' => 'services.whatsapp.verify_token',
    ],
];
