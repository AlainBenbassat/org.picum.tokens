<?php

require_once 'tokens.civix.php';
use CRM_Tokens_ExtensionUtil as E;

function tokens_civicrm_tokens(&$tokens) {
  $tokens['picum'] = [
    'picum.membership_fee_table_en' => 'Membership Fee Table EN',
    'picum.membership_fee_table_fr' => 'Membership Fee Table FR',
    'picum.membership_fee_table_es' => 'Membership Fee Table ES',
  ];
}

function tokens_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = [], $context = null) {
  // sometimes $cids is not an array
  if (!is_array($cids)) {
    $cids = [$cids];
  }

  if (array_key_exists('picum', $tokens) && in_array('membership_fee_table_en', $tokens['picum'])) {
    tokens_get_picum_membership_fee_table('en', 'picum.membership_fee_table_en', $values, $cids);
  }
  elseif (array_key_exists('picum', $tokens) && in_array('membership_fee_table_fr', $tokens['picum'])) {
    tokens_get_picum_membership_fee_table('fr', 'picum.membership_fee_table_fr', $values, $cids);
  }
  elseif (array_key_exists('picum', $tokens) && in_array('membership_fee_table_es', $tokens['picum'])) {
    tokens_get_picum_membership_fee_table('es', 'picum.membership_fee_table_es', $values, $cids);
  }
}

function tokens_get_picum_membership_fee_table($lang, $tokenName, &$values, $cids) {
  $line = [
    'en' => 'Membership fee year ',
    'fr' => 'Membership fee year ',
    'es' => 'Membership fee year ',
  ];
  $lineTotal = [
    'en' => 'Total',
    'fr' => 'Total',
    'es' => 'Total',
  ];


  foreach ($cids as $cid) {
    // check the type of contact
    if ($values[$cid]['contact_type'] == 'Individual') {
      // get the employer
      $orgId = tokens_get_employer($cid);
      if ($orgId) {
        $tableLines = [];

        // get the current year
        $year = date('Y');

        // get the maximum member dues ever paid
        $price = tokens_get_picum_max_contrib($orgId);
        if (!$price) {
          $price = 500; // WHAT SHOULD WE DO!!!!!!!!!!!!!!!!!!!!!!!!!
        }

        $total = 0.00;

        // get the pending contribs for the 3 previous years
        $i = 2;
        while ($i >= 0) {
          $y = $year - $i;
          $contrib = tokens_get_picum_pending_contrib_for_year($y, $orgId);
          if ($contrib) {
            $tableLines[$y] = $price;
            $total += $price;
          }

          $i--;
        }

        // build the table
        $table = '<table>';
        foreach ($tableLines as $y => $p) {
          $table .= '<tr style="border: 1px solid black"><td style="border: 1px solid black">' . $line[$lang] . $y . '</td><td style="border: 1px solid black">' . $p . ' euro</td></tr>';
        }

        // add the total and close the table
        $table .= '<tr style="border: 1px solid black"><td style="border: 1px solid black; font-weight: bold; text-align: right">' . $lineTotal[$lang] . '</td><td style="border: 1px solid black; font-weight: bold">' . sprintf('%0.2f', $total) . ' euro</td></tr></table>';
        $values[$cid][$tokenName] = $table;
      }
      else {
        // no employer
        $values[$cid][$tokenName] = 'ERROR: employer not found';
      }
    }
    else {
      // not an individual
      $values[$cid][$tokenName] = 'ERROR: ' . $values[$cid]['contact_type'] . ' is not the expected contact type';
    }
  }
}

function tokens_get_picum_max_contrib($orgId) {
  $MEMBER_DUES = 2;
  $sql = "select max(total_amount) from civicrm_contribution where financial_type_id = $MEMBER_DUES and contact_id = $orgId";
  return CRM_Core_DAO::singleValueQuery($sql);
}

function tokens_get_picum_pending_contrib_for_year($year, $orgId) {
  $MEMBER_DUES = 2;
  $STATUS_PENDING = 2;

  $sql = "
    select 
      * 
    from 
      civicrm_contribution 
    where 
      contact_id = $orgId 
    and 
      year(receive_date) = $year
    and 
      contribution_status_id = $STATUS_PENDING 
    and financial_type_id = $MEMBER_DUES
  ";
  $dao = CRM_Core_DAO::executeQuery($sql);
  if ($dao->fetch()) {
    return $dao;
  }
  else {
    return FALSE;
  }
}

function tokens_get_employer($personId) {
  $sql = "select employer_id from civicrm_contact where id = $personId";
  $orgId = CRM_Core_DAO::singleValueQuery($sql);
  return $orgId;
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/ 
 */
function tokens_civicrm_config(&$config) {
  _tokens_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function tokens_civicrm_xmlMenu(&$files) {
  _tokens_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function tokens_civicrm_install() {
  _tokens_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function tokens_civicrm_postInstall() {
  _tokens_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function tokens_civicrm_uninstall() {
  _tokens_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function tokens_civicrm_enable() {
  _tokens_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function tokens_civicrm_disable() {
  _tokens_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function tokens_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _tokens_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function tokens_civicrm_managed(&$entities) {
  _tokens_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function tokens_civicrm_caseTypes(&$caseTypes) {
  _tokens_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function tokens_civicrm_angularModules(&$angularModules) {
  _tokens_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function tokens_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _tokens_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function tokens_civicrm_entityTypes(&$entityTypes) {
  _tokens_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function tokens_civicrm_themes(&$themes) {
  _tokens_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
function tokens_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 *
function tokens_civicrm_navigationMenu(&$menu) {
  _tokens_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _tokens_civix_navigationMenu($menu);
} // */
