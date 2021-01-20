<?php

// Location where the webhook script can be reached
define('WEBHOOK_URL_PATH', 'https://bot.fiveyellowmice.com/item-index-manipulator/actions-webhook.php');

// Randomly generated string
define('WEBHOOK_TOKEN', '');

// Goto Google API Console - APIs & Services - Credentials (https://console.developers.google.com/apis/credentials),
// create an OAuth 2.0 Client ID, select "Desktop app" applicatin type,
// then download the client secret JSON file, paste its content here
define('GOOGLE_CLIENT_SECRET', <<<'END'
{}
END
);
