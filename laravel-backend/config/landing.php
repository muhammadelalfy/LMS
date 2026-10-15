<?php

/*
|--------------------------------------------------------------------------
| Public landing page
|--------------------------------------------------------------------------
|
| Store links and contact details shown on the central domain's home page.
| A link left empty renders its button as "soon" instead of a dead link.
|
*/

return [
    'name' => env('LANDING_NAME', 'مُدرّسى'),
    'android_url' => env('LANDING_ANDROID_URL', ''),
    'ios_url' => env('LANDING_IOS_URL', ''),
    'web_url' => env('LANDING_WEB_URL', ''),
    'contact_email' => env('LANDING_CONTACT_EMAIL', ''),
];
