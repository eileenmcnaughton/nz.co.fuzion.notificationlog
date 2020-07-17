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
 * @throws \API_Exception
 */
function _civicrm_api3_notification_log_process($logEntry) {
  $processorId = NULL;
  // Determine which style of IPN we're using and get the processor name.
  if (substr($logEntry['message'], 0, 36) == 'payment_notification processor_name=') {
    $processorName = substr($logEntry['message'], 36);
  }
  elseif ($logEntry['message'] == 'payment_notification PayPal_Standard') {
    $processorName = 'PayPal_Standard';
  }
  elseif (substr($logEntry['message'], 0, 34) == 'payment_notification processor_id=') {
    $processorId = substr($logEntry['message'], 34);
    $processorTypeId = civicrm_api3('PaymentProcessor', 'getvalue', array('id' => $processorId, 'return' => 'payment_processor_type_id'));
    $processorName = civicrm_api3('PaymentProcessorType', 'getvalue', array('id' => $processorTypeId, 'return' => 'name'));
  }
  else {
    throw new API_Exception('unsupported processor');
  }

  // Build the parameter array for the IPN class.
  if (in_array($processorName, ['AuthNet', 'PayPal', 'Paypal_Standard'])) {
    $ipnParams = array_merge(json_decode($logEntry['context'], TRUE), array('receive_date' => $logEntry['timestamp']));
    $ipnParams['processor_id'] = $processorId;
  }
  else {
    $ipnParams = $logEntry['context'];
    if ($processorId) {
      $_GET['processor_id'] = $processorId;
    }
  }

  // Pick the IPN class based on the processor name.
  switch ($processorName) {
    case 'AuthNet':
      $ipnClass = new CRM_Core_Payment_AuthorizeNetIPN($ipnParams);
      break;

    case 'PayPal':
      $ipnClass = new CRM_Core_Payment_PayPalProIPN($ipnParams);
      break;

    case 'PayPal_Standard':
      $ipnClass = new CRM_Core_Payment_PayPalIPN($ipnParams);
      break;

    case 'Stripe':
      $ipnClass = new CRM_Core_Payment_StripeIPN($ipnParams);
      break;

    case 'AuthorizeNeteCheck':
    case 'AuthorizeNetCreditcard':
      $ipnClass = new CRM_Core_Payment_AuthNetIPN($ipnParams);
      break;

    default:
      throw new API_Exception('unsupported processor');
  }
  $ipnClass->main();
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
