<?php

require __DIR__.'/../vendor/autoload.php';


// Authentication related helpers

function getUnauthenticatedGoogleClient() {
  $client = new Google_Client();
  $client->setApplicationName('Handsfree Item Index Manipulator');
  $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
  $client->setAuthConfig(json_decode(GOOGLE_CLIENT_SECRET, true));
  $client->setAccessType('offline');
  $client->setPrompt('select_account consent');

  return $client;
}

function linkGoogleAccount($code) {
  $client = getUnauthenticatedGoogleClient();

  // Exchange authorization code for an access token.
  $access_token = $client->fetchAccessTokenWithAuthCode($code);
  $client->setAccessToken($access_token);

  return $access_token;
}

function getGoogleSheetsService($access_token) {
  $client = getUnauthenticatedGoogleClient();
  $client->setAccessToken($access_token);

  if ($client->isAccessTokenExpired()) {
    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
  }

  $service = new Google_Service_Sheets($client);

  return $service;
}


// Sheet manipulation related helpers

function getSheetValues($service, $open_sheet) {
  return $service->spreadsheets_values->get($open_sheet['spreadsheet_id'], $open_sheet['sheet_title'], ['majorDimension'=>'ROWS'])->getValues() ?? [];
}

function updateSheetValues($service, $open_sheet, $row_index, $column_index, $values) {
  $values_height = count($values);
  $values_width = max(array_map('count', $values));

  $grid_range = new Google_Service_Sheets_GridRange();
  $grid_range->setSheetId($open_sheet['sheet_id']);
  $grid_range->setStartRowIndex($row_index);
  $grid_range->setEndRowIndex($row_index + $values_height);
  $grid_range->setStartColumnIndex($column_index);
  $grid_range->setEndColumnIndex($column_index + $values_width);

  $data_filter = new Google_Service_Sheets_DataFilter();
  $data_filter->setGridRange($grid_range);

  $value_range = new Google_Service_Sheets_DataFilterValueRange();
  $value_range->setDataFilter($data_filter);
  $value_range->setMajorDimension('ROWS');
  $value_range->setValues($values);

  $update_request = new Google_Service_Sheets_BatchUpdateValuesByDataFilterRequest();
  $update_request->setValueInputOption('USER_ENTERED');
  $update_request->setData([$value_range]);

  return $service->spreadsheets_values->batchUpdateByDataFilter($open_sheet['spreadsheet_id'], $update_request);
}

function deleteRowFromSheet($service, $open_sheet, $row_index) {
  $dimension_range = new Google_Service_Sheets_DimensionRange();
  $dimension_range->setSheetId($open_sheet['sheet_id']);
  $dimension_range->setDimension('ROWS');
  $dimension_range->setStartIndex($row_index);
  $dimension_range->setEndIndex($row_index + 1);

  $delete_dimension_request = new Google_Service_Sheets_DeleteDimensionRequest();
  $delete_dimension_request->setRange($dimension_range);

  $update_request = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
  $update_request->setRequests([['deleteDimension' => $delete_dimension_request]]);

  return $service->spreadsheets->batchUpdate($open_sheet['spreadsheet_id'], $update_request);
}


class SheetManipulationException extends Exception {} // Following functions may throw this

function findFieldInRowWithName($sheet_values, $item_name, $field_name) {
  $sheet_header = array_map('mb_strtolower', $sheet_values[0] ?? []);
  array_shift($sheet_values);

  $name_field_index = array_search('name', $sheet_header);
  if ($name_field_index === false) {
    throw new SheetManipulationException("Cannot find the \"Name\" column in the spreadsheet.");
  }
  $target_field_index = array_search(mb_strtolower($field_name), $sheet_header);
  if ($target_field_index === false) {
    throw new SheetManipulationException("Cannot find the \"$field_name\" column in the spreadsheet.");
  }

  $target_row_index = null;
  foreach ($sheet_values as $i => $row) {
    if (mb_strtolower($row[$name_field_index]) === mb_strtolower($item_name)) {
      $target_row_index = $i + 1;
      break;
    }
  }
  if ($target_row_index === null) {
    throw new SheetManipulationException("Cannot find a row with name \"$item_name\" in the spreadsheet.");
  }

  return [$target_row_index, $target_field_index];
}
