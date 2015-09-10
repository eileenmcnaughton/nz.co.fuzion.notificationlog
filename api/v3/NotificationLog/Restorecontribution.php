<?php

/**
 * Restore a deleted contribution.
 *
 * Sometimes the deletion of a contribution will cause subsequent payments not
 *  to show up in CiviCRM. If they are in the log we might be able to restore them...
 *
 * @param array $params
 *
 * @return array
 *   API result array
 * @throws \API_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_notification_log_restorecontribution($params) {
  $queryParams = array(1 => array($params['id'], 'Integer'));
  $query = array();
  $contributionResult = CRM_Core_DAO::executeQuery(
    "SELECT * FROM log_civicrm_contribution WHERE id = %1 AND log_action != 'Delete'",
    $queryParams
  );
  $contributionFields = civicrm_api3('contribution', 'getfields', array('action' => 'create'));
  $lineItemFields = civicrm_api3('line_item', 'getfields', array('action' => 'create'));
  // We want to INSERT this contribution with an ID - DAO won't allow that!
  // also we can't retrieve from log in the same transaction as we save to contribution
  // triggers will prevent that.

  while ($contributionResult->fetch()) {
    $insertParams = array();
    foreach ($contributionFields['values'] as $field => $spec) {
      if (!empty($contributionResult->$field)) {
        $insertParams[$field] = "'" . $contributionResult->$field . "'";
      }
    }
    $query[] = "INSERT INTO civicrm_contribution (" . implode(',', array_keys($insertParams))
      . ") values (" . implode(',', $insertParams) . ')';

    $lineItemResult = CRM_Core_DAO::executeQuery(
      "SELECT * FROM log_civicrm_line_item WHERE contribution_id = %1 AND log_action != 'Delete'",
      $queryParams
    );
    while ($lineItemResult->fetch()) {
      $insertParams = array();
      foreach ($lineItemFields['values'] as $field => $spec) {
        if (!empty($lineItemResult->$field)) {
          $insertParams[$field] = "'" . $lineItemResult->$field . "'";
        }
      }
      $query[] = "INSERT INTO civicrm_line_item (" . implode(',', array_keys($insertParams))
        . ") values (" . implode(',', $insertParams) . ')';
    }

    print_r($query);
  }
}

/**
 * Specifications for retrying a transaction.
 *
 * @param array $params
 */
function _civicrm_api3_notification_log_restorecontribution_spec(&$params) {
  $params['id']['api.required'] = TRUE;
}
