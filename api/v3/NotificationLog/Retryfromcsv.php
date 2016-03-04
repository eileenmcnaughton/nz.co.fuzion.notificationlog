<?php

function _civicrm_api3_notification_log_retryfromcsv_spec(&$params) {
  $params['csv']['api.required'] = TRUE;
  $params['payment_processor']['api.required'] = TRUE;
  // @Todo: Additional params for parsing CSV
}

function civicrm_api3_notification_log_retryfromcsv($params) {
  $csv_file = file($params['csv']);
  if ($csv_file === FALSE) {
    throw new API_Exception('an error occurred reading from CSV file');
  }

  $logs = array_map('str_getcsv', $csv_file);

  foreach ($logs as $i => $log) {
    // Skip CSV headers
    if ($i == 0) {
      $headers = $log;
      continue;
    }
    $response = array();
    foreach ($log as $j => $entry) {
      $response[$headers[$j]] = $entry;
    }

    // Get subscription_id
    $subscription_id = CRM_Core_DAO::singleValueQuery("SELECT processor_id FROM civicrm_contribution_recur r 
                                    LEFT JOIN civicrm_contribution c 
                                    ON c.contribution_recur_id = r.id
                                    WHERE c.id = {$response['x_invoice_num']}");
    if (empty($subscription_id)) {
      print "Failed to find subscription id for invoice {$response['x_invoice_num']} \n";
      continue;
    }
    $submit_date = DateTime::createFromFormat('d-M-Y h:i:s A e', $response['x_submit_date']);

    if ($params['payment_processor'] == 'AuthNet') {
      $anet = new CRM_Core_Payment_AuthorizeNetIPN(
        array_merge($response, array('x_subscription_id' => $subscription_id, 'receive_date' => $submit_date->format('Y-m-d H:i:s')))
      );
      try {
        $anet->main();
      }
      catch (Exception $e) {
        // pass
      }
    }
  }
}

