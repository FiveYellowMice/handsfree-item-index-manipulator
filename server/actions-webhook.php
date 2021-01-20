<?php

require_once 'config.php';

require __DIR__.'/vendor/autoload.php';
require_once 'lib/google-sheets.php';


function return_error($code, $message) {
  http_response_code($code);
  header('Content-Type: text/plain');
  echo $message."\n";
  die();
}

// Return a response with a simple message, and optionally with redirect scene
function simple_message($response, $message, $next_scene = null) {
  $response['prompt'] = [
    'override' => false,
    'firstSimple' => [
      'speech' => $message,
    ],
  ];
  if ($next_scene) {
    $response['scene']['next'] = [
      'name' => $next_scene,
    ];
  }
  return $response;
}

class ActionsHandler {
  // Flags
  const NO_FLAG = 0;
  const REQUIRE_AUTHENTICATION = 1;
  const REQUIRE_OPEN_SHEET = 2;

  private $flags;
  private $func;

  public function __construct($flags, $func) {
    $this->flags = $flags;
    $this->func = $func;
  }

  public function handle($request) {
    // Pre-fill session ID, and preserve stored values by default
    $response = [
      'session' => [
        'id' => $request['session']['id'],
        'params' => $request['session']['params'],
      ],
      'user' => [
        'params' => $request['user']['params'],
      ],
      'scene' => [],
    ];

    // Checks authentication to Google Sheets
    if ($request['user']['params']['access_token'] ?? null) {
      $service = getGoogleSheetsService(json_decode($request['user']['params']['access_token'], true));
    } else {
      $service = null;
    }
    // Helper variables
    $sheets_mapping = $request['user']['params']['sheets_mapping'] ?? [];
    $open_sheet = $sheets_mapping[$request['session']['params']['sheet_name'] ?? null] ?? null;

    if (($this->flags & ActionsHandler::REQUIRE_AUTHENTICATION) && !$service) {
      // Prompt user to authenticate before continuing, redirect to LinkAccount scene
      $response = simple_message($response, 'To manipulate your item indices in Google Sheets, you need to link your Google account and allow me to edit the spreadsheets.', 'LinkAccount');

    } elseif (($this->flags & ActionsHandler::REQUIRE_OPEN_SHEET) && !$open_sheet) {
      // Prompt user to open a sheet before continuing, redirect to Work scene
      $response = simple_message($response, 'No spreadsheet is currently opened. Open one by saying "open spreadsheet" and the name of the spreadsheet to open.', 'Work');

    } else {
      // If user is authenticated or handler don't need it, invoke the handler with pre-filled response and helper variables
      $response = call_user_func($this->func, $request, $response, $service, $sheets_mapping, $open_sheet);
    }

    // Post-process the response

    // Make the sheet_name type an enum of linked sheet names
    $response['session']['typeOverrides'] = [
      [
        'name' => 'sheet_name',
        'mode' => 'TYPE_REPLACE',
        'synonym' => [
          'entries' => array_map(function ($n) { return [
            'name' => $n,
            'synonyms' => [$n],
            'display' => [
              'title' => $n,
            ],
          ]; }, array_keys($sheets_mapping))
        ],
      ],
    ];

    // Make empty objects actual PHP objects so json_encode doesn't make them empty arrays, we can't use JSON_FORCE_OBJECT flag because there are other empty arrays.
    $response['session']['params'] = (object) $response['session']['params'];
    $response['user']['params'] = (object) $response['user']['params'];
    $response['scene'] = (object) $response['scene'];

    return $response;
  }
}



// Declare the handlers, a handler's function can accept these parameters:
// $request, $response, $service, $sheets_mapping, $open_sheet
// and then return a response
$actions_handlers = [];


$actions_handlers['start'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  if (count($sheets_mapping) === 0) {
    return simple_message($response, 'You have no spreadsheets to work on. To link a spreadsheet, say "link spreadsheet".');
  }

  $response = simple_message($response, 'What should I do?');

  return $response;
});


$actions_handlers['read_field'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION | ActionsHandler::REQUIRE_OPEN_SHEET, function ($request, $response, $service, $sheets_mapping, $open_sheet) {
  $item_name = $request['intent']['params']['item_name']['resolved'] ?? null;
  $field_name = $request['intent']['params']['field_name']['resolved'] ?? null;

  $sheet_values = getSheetValues($service, $open_sheet);

  try {
    [$target_row_index, $target_field_index] = findFieldInRowWithName($sheet_values, $item_name, $field_name);
  } catch (SheetManipulationException $ex) {
    return simple_message($response, $ex->getMessage());
  }

  $original_field_value = $sheet_values[$target_row_index][$target_field_index];

  return simple_message($response, "The $field_name of $item_name is $original_field_value.");
});


$actions_handlers['modify_number_field'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION | ActionsHandler::REQUIRE_OPEN_SHEET, function ($request, $response, $service, $sheets_mapping, $open_sheet) {
  $item_name = $request['intent']['params']['item_name']['resolved'] ?? null;
  $field_name = $request['intent']['params']['field_name']['resolved'] ?? null;
  $field_value_number = $request['intent']['params']['field_value_number']['resolved'] ?? null;
  $field_value_increment = $request['intent']['params']['field_value_increment']['resolved'] ?? null;
  $field_value_decrement = $request['intent']['params']['field_value_decrement']['resolved'] ?? null;

  if ($field_value_number !== null) {
    $modification_verb = 'change';
  } elseif ($field_value_increment !== null) {
    $modification_verb = 'increase';
  } elseif ($field_value_decrement !== null) {
    $modification_verb = 'decrease';
  } else {
    return simple_message($response, "You need to specify the number for the $field_name of $item_name to change to, increase by or decrease by.");
  }

  $sheet_values = getSheetValues($service, $open_sheet);

  try {
    [$target_row_index, $target_field_index] = findFieldInRowWithName($sheet_values, $item_name, $field_name);
  } catch (SheetManipulationException $ex) {
    return simple_message($response, $ex->getMessage());
  }

  $original_field_value = $sheet_values[$target_row_index][$target_field_index];

  switch ($modification_verb) {
    case 'change':
      $new_field_vale = $field_value_number;
      break;
    case 'increase':
      $new_field_vale = floatval($original_field_value) + $field_value_increment;
      break;
    case 'decrease':
      $new_field_vale = floatval($original_field_value) - $field_value_decrement;
      break;
  }

  updateSheetValues($service, $open_sheet, $target_row_index, $target_field_index, [[$new_field_vale]]);

  return simple_message($response, "The $field_name of $item_name has been ${modification_verb}d from $original_field_value to $new_field_vale.");
});


$actions_handlers['remove_record'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION | ActionsHandler::REQUIRE_OPEN_SHEET, function ($request, $response, $service, $sheets_mapping, $open_sheet) {
  $item_name = $request['intent']['params']['item_name']['resolved'] ?? null;

  $sheet_values = getSheetValues($service, $open_sheet);

  try {
    [$target_row_index, $target_field_index] = findFieldInRowWithName($sheet_values, $item_name, 'name');
  } catch (SheetManipulationException $ex) {
    return simple_message($response, $ex->getMessage());
  }

  deleteRowFromSheet($service, $open_sheet, $target_row_index);

  return simple_message($response, "$item_name has been removed.");
});


$actions_handlers['link_account'] = new ActionsHandler(ActionsHandler::NO_FLAG, function ($request, $response) {
  if (!in_array('WEB_LINK', $request['device']['capabilities'])) {
    return simple_message($response, 'To link an account, you need to switch to a device capable of opening web links, like a phone.', 'actions.scene.END_CONVERSATION');
  }

  $client = getUnauthenticatedGoogleClient();
  $auth_url = $client->createAuthUrl();

  $response['prompt'] = [
    'override' => false,
    'firstSimple' => [
      'speech' => 'Click the button to authenticate to you Google Account.',
    ],
    'content' => [
      'card' => [
        'text' => 'Click the button below:',
        'button' => [
          'name' => 'Link Account',
          'open' => [
            'url' => $auth_url,
          ],
        ],
      ],
    ],
  ];

  return $response;
});


$actions_handlers['verify_code'] = new ActionsHandler(ActionsHandler::NO_FLAG, function ($request, $response) {
  $verification_code = $request['scene']['slots']['verification_code']['value'];
  try {
    $access_token = linkGoogleAccount($verification_code);
  } catch (Exception $ex) {
    $access_token = ['error' => $ex->getMessage()];
  }

  if (array_key_exists('error', $access_token)) {
    return simple_message($response, 'Authentication failed: '.$access_token['error'], 'actions.scene.END_CONVERSATION');
  }

  $response['user']['params']['access_token'] = json_encode($access_token);

  return simple_message($response, 'Account linking successful.', 'Work');
});


$actions_handlers['link_sheet'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  $sheet_name = $request['scene']['slots']['sheet_name']['value'];
  $sheet_url = $request['scene']['slots']['sheet_url']['value'];

  preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)(?:.*[#&]gid=([0-9]+))?/', $sheet_url, $matches, PREG_UNMATCHED_AS_NULL);
  $spreadsheet_id = $matches[1];
  $sheet_id = intval($matches[2] ?? 0);

  $sheets_properties = $service->spreadsheets->get($spreadsheet_id, ['fields' => 'sheets.properties'])->getSheets();
  foreach ($sheets_properties as $sheet_properties) {
    if ($sheet_properties->getProperties()->getSheetId() === $sheet_id) {
      $sheet_title = $sheet_properties->getProperties()->getTitle();
      break;
    }
  }

  $sheets_mapping[$sheet_name] = [
    'spreadsheet_id' => $spreadsheet_id,
    'sheet_id' => $sheet_id,
    'sheet_title' => $sheet_title,
  ];
  $response['user']['params']['sheets_mapping'] = $sheets_mapping;
  $response['session']['params']['sheet_name'] = $sheet_name;

  return simple_message($response, "Spreadsheet $sheet_name has been linked.", 'Work');
});


$actions_handlers['unlink_sheet'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  $sheet_name = $request['intent']['params']['sheet_name']['resolved'] ?? $request['session']['params']['sheet_name'] ?? null;

  if (!$sheet_name) {
    return simple_message($response, "You need to specify a spreadsheet to unlink.");
  }

  if (!($sheets_mapping[$sheet_name] ?? null)) {
    return simple_message($response, "Spreadsheet $sheet_name does not exist.");
  }

  unset($sheets_mapping[$sheet_name]);
  $response['user']['params']['sheets_mapping'] = $sheets_mapping;
  $response['session']['params']['sheet_name'] = null;

  return simple_message($response, "Spreadsheet $sheet_name has been unlinked.", 'Work');
});


$actions_handlers['open_sheet'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  $sheet_name = $request['intent']['params']['sheet_name']['resolved'] ?? null;

  if (!array_key_exists($sheet_name, $sheets_mapping)) {
    return simple_message($response, "Spreadsheet $sheet_name does not exist.");
  }

  $response['session']['params']['sheet_name'] = $sheet_name;

  return simple_message($response, "You are now working on $sheet_name.", 'Work');
});


$actions_handlers['list_sheets'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  if (count($sheets_mapping) === 0) {
    $response = simple_message($response, "There are no linked spreadsheets.");

  } else {
    $response['prompt'] = [
      'override' => false,
      'firstSimple' => [
        'speech' => 'You have the following spreadsheets: '.implode(', ', array_keys($sheets_mapping)),
        'text' => 'You have the following spreadsheets:',
      ],
      'content' => [
        'list' => [
          'items' => array_map(function ($n) { return [
            'key' => $n,
          ]; }, array_keys($sheets_mapping)),
        ],
      ],
    ];
  }

  return $response;
});


$actions_handlers['validate_sheet_name'] = new ActionsHandler(ActionsHandler::REQUIRE_AUTHENTICATION, function ($request, $response, $service, $sheets_mapping) {
  $response['scene']['slots'] = $request['scene']['slots'];
  $sheet_name = $request['scene']['slots']['sheet_name']['value'];

  if (!array_key_exists($sheet_name, $sheets_mapping)) {
    $response['scene']['slots']['sheet_name']['status'] = 'INVALID';
    $response = simple_message($response, "Spreadsheet $sheet_name does not exist.");
  }

  return $response;
});



// Request starts here
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return_error(405, 'Method not allowed.');
}

if (!hash_equals($_GET['token'] ?? '', WEBHOOK_TOKEN)) {
  return_error(403, 'Incorrect webhook token.');
}

$request = json_decode(file_get_contents('php://input'), true);
if (!$request) return_error(400, 'Invalid JSON.');

// Print the request to console for debugging
if (intval(getenv('PHP_DEBUG'))) {
  error_log(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |  JSON_UNESCAPED_UNICODE));
}

// Find the appropriate handler, if none found, assign a default handler that just show an error message to user
$actions_handler = $actions_handlers[$request['handler']['name']] ?? new ActionsHandler(ActionsHandler::NO_FLAG, function ($request, $response) {
  return simple_message($response, 'This handler has not been created yet.');
});

// Handle the request, get the response, finally send it
$response = $actions_handler->handle($request);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES |  JSON_UNESCAPED_UNICODE);
