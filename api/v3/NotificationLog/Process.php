<?php
/**
 * Process incoming payment notifications for a time period.
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
function civicrm_api3_notification_log_process($params) {
  $logs = civicrm_api3('SystemLog', 'get', array('timestamp' => array('BETWEEN' => array($params['start_time'], $params['end_time']))));
  $errors = array();
  foreach ($logs['values'] as $id => $values) {
    try {
      civicrm_api3('NotificationLog', 'retry', array('system_log_id' => $values['id']));
    }
    catch (CiviCRM_API3_Exception $e) {
      if ($e->getMessage() == 'DB Error: already exists') {
        $logs['values'][$id]['already_processed'] = TRUE;
      }
      else {
        $logs['values'][$id]['error'] = $e->getMessage();
      }
    }
  }
  return civicrm_api3_create_success($logs['values'], $params, 'NotificationLog', 'process');
}


/**
 * Specifications for retrying a transaction.
 *
 * @param array $params
 */
function _civicrm_api3_notification_log_process_spec(&$params) {
  $params['start_time']['api.default'] = '-24 hours';
  $params['start_time']['title'] = ts('Start Time');
  $params['start_time']['type'] = CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME;
  $params['end_time']['api.default'] = 'now';
  $params['end_time']['title'] = ts('End Time');
  $params['end_time']['type'] = CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME;
}
