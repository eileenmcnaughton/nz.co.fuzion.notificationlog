<?php

/**
 * Process incoming payment notifications.
 *
 * Note that Omnipay payment processors will use the functionality in Omnipay.
 *
 * @param array $params
 *
 * @return array
 *   API result array
 * @throws \API_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_notification_log_retry($params) {
  if (!empty($params['system_log_id'])) {
    // lets replace params with this rather than allow altering
    $logEntries = civicrm_api3('system_log', 'get', array(
      'id' => $params['system_log_id'],
      'return' => 'context, timestamp, message',
    ));
  }
  foreach ($logEntries['values'] as $logEntry) {
    if (_civicrm_api3_notification_log_process($logEntry)) {
      return civicrm_api3_create_success(1, $params);
    }
    throw new API_Exception('payment retry failed');
  }
}

/**
 * Process the log entry.
 *
 * @param array $logEntry
 *
 * @return bool
 */
function _civicrm_api3_notification_log_process($logEntry) {
  if ($logEntry['message'] == 'payment_notification processor_name=AuthNet') {
    $anet = new CRM_Core_Payment_AuthorizeNetIPN(
      array_merge(json_decode($logEntry['context'], TRUE), array('receive_date' => $logEntry['timestamp']))
    );
    $anet->main();
  }
  elseif($logEntry['message'] =='payment_notification processor_name=PayPal') {
    $payPal = new CRM_Core_Payment_PayPalProIPN(
      array_merge(json_decode($logEntry['context'], TRUE), array('receive_date' => $logEntry['timestamp']))
    );
    $payPal->main();
  }
  else {
    throw new API_Exception('unsupported processor');
  }
  return TRUE;
}

/**
 * Specifications for retrying a transaction.
 *
 * @param array $params
 */
function _civicrm_api3_notification_log_retry_spec(&$params) {
  $params['system_log_id']['api.required'] = TRUE;
}

