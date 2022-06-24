<?php

class CRM_Tokens_PicumTokenHelper {
  protected static $_singleton;
  private $translations;

  private $tokenList = [
    'membership_fee_table' => 'Membership Fee Table',
    'debit_note_date' => 'Debit Note Date',
    'debit_note_due_date' => 'Debit Note Due Date',
    'pending_fees_label' => 'Pending fees (label)',
    'pending_fees_list' => 'Pending fees (list)',
  ];

  private $tokenListInThreeLanguages = [];

  public static function singleton() {
    if (!isset(self::$_singleton)) {
      self::$_singleton = new CRM_Tokens_PicumTokenHelper();
    }
    return self::$_singleton;
  }

  public function __construct() {
    foreach ($this->tokenList as $k => $v) {
      $this->tokenListInThreeLanguages["picum.{$k}_en"] = $v . " EN";
      $this->tokenListInThreeLanguages["picum.{$k}_fr"] = $v . " FR";
      $this->tokenListInThreeLanguages["picum.{$k}_es"] = $v . " ES";
    }

    $this->setTranslations();
  }

  public function getTokenList() {
    return $this->tokenListInThreeLanguages;
  }
  
  public function replacePicumtokens(&$values, $cids, $job = null, $tokens = [], $context = null) {
    $lang = '';
    if ($this->hasPicumToken('membership_fee_table', $tokens, $lang)) {
      $this->setMembershipFeeTable($lang, 'membership_fee_table', $values, $cids);
    }

    if ($this->hasPicumToken('debit_note_date', $tokens, $lang)) {
      $this->setDebitNoteDate($lang, 'debit_note_date', $values, $cids);
    }

    if ($this->hasPicumToken('debit_note_due_date', $tokens, $lang)) {
      $this->setDebitNotedueDate($lang, 'debit_note_due_date', $values, $cids);
    }

    if ($this->hasPicumToken('pending_fees_label', $tokens, $lang)) {
      $this->setPendingFeesLabel($lang, 'pending_fees_label', $values, $cids);
    }
    if ($this->hasPicumToken('pending_fees_list', $tokens, $lang)) {
      $this->setPendingFeesList($lang, 'pending_fees_list', $values, $cids);
    }
  }

  private function hasPicumToken($token, $tokens, &$lang) {
    if (array_key_exists('picum', $tokens)) {
      $langs = ['en', 'fr', 'es'];
      foreach ($langs as $possibleLang) {
        $lang = $possibleLang;
        if ($this->hasPicumTokenWithLang($token . '_' . $lang, $tokens)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  private function hasPicumTokenWithLang($token, $tokens) {
    if (in_array($token, $tokens['picum']) || array_key_exists($token, $tokens['picum'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function setMembershipFeeTable($lang, $rawTokenName, &$values, $cids) {
    $tokenName = "picum.{$rawTokenName}_$lang";

    foreach ($cids as $cid) {
      $this->setMembershipFeeTableForCid($lang, $tokenName, $values, $cid);
    }
  }

  private function setMembershipFeeTableForCid($lang, $tokenName, &$values, $cid) {
    $orgId = $this->getEmployer($cid);
    if ($orgId) {
      $this->setMembershipFeeTableForCidWithOrgId($lang, $tokenName,$values, $cid, $orgId);
    }
    else {
      $values[$cid][$tokenName] = 'ERROR: employer not found';
    }
  }

  private function setMembershipFeeTableForCidWithOrgId($lang, $tokenName, &$values, $cid, $orgId) {
    $tableLines = [];
    $total = 0.00;
    $currentYear = (int)date('Y');
    $mostRecentlyPaidMembershipFee = $this->getMostRecentlyPaidMembershipFee($orgId);

    for ($year = $currentYear; $year < $currentYear - 3; $year--) {
      $contrib = $this->getPendingContribForYear($year, $orgId);
      if ($contrib) {
        if ($year != $currentYear) {
          $comment = $this->translations['STILL_OPEN'][$lang];
        }
        else {
          if ($mostRecentlyPaidMembershipFee) {
            $comment = $this->translations['PREVIOUS_CONTRIBUTION'][$lang];
          }
          else {
            $comment = $this->translations['FREE_GUIDELINES'][$lang];
          }
        }
        $tableLines[] = [$year, $mostRecentlyPaidMembershipFee, $comment];

        if ($mostRecentlyPaidMembershipFee) {
          $total += $mostRecentlyPaidMembershipFee;
        }
      }
    }

    $values[$cid][$tokenName] = $this->buildMembershipFeeTable($tableLines, $total, $lang);
  }

  private function buildMembershipFeeTable($tableLines, $total, $lang) {
    // build the table
    $table = '<table style="border: 1px solid black;width=100%">';
    foreach ($tableLines as $p) {
      $table .= '<tr style="border: 1px solid black;padding:5px">';
      $table .= '<td style="border: 1px solid black;padding:5px">' . $this->translations['MEMBERSHIP_FEE_YEAR'][$lang] . $p[0] . '</td>';
      if ($p[1] > 0) {
        $formattedPrice = sprintf('%0.2f euro', $p[1]);
      }
      else {
        $formattedPrice = '<i>euro</i>';
      }

      $table .= '<td style="border: 1px solid black;padding:5px">' . $formattedPrice . '</td>';
      $table .= '<td style="border: 1px solid black;padding:5px">' . $p[2] . '</td>';
      $table .= '</tr>';
    }

    // add the total and close the table
    $table .= '<tr style="border: 1px solid black;padding:5px">';
    $table .= '<td style="border: 1px solid black; font-weight: bold; text-align: right;padding:5px">' . $this->translations['TOTAL'][$lang] . '</td>';
    if ($total > 0) {
      $formattedTotal = sprintf('%0.2f euro', $total);
    }
    else {
      $formattedTotal = '<i>euro</i>';
    }

    $table .= '<td style="border: 1px solid black; font-weight: bold;padding:5px">' . $formattedTotal . '</td>';
    $table .= '<td style="border: 1px solid black; font-weight: bold;padding:5px">&nbsp;</td>';
    $table .= '</tr></table>';

    return $table;
  }

  private function getMostRecentlyPaidMembershipFee($orgId) {
    $MEMBER_DUES = 2;
    $year = (int)date('Y');

    $price = 0;
    for ($i = 0; $i <= 3; $i++) {
      $sql = "select ifnull(sum(total_amount), 0) total_amount from civicrm_contribution where financial_type_id = $MEMBER_DUES and contact_id = $orgId and trim(source) = '" . 'Fee ' . ($year - $i) . "'";
      $price = CRM_Core_DAO::singleValueQuery($sql);
      if (isset($price) && $price > 0) {
        break;
      }
    }

    return $price;
  }

  public function setDebitNoteDate($lang, $rawTokenName, &$values, $cids) {
    $tokenName = "picum.{$rawTokenName}_$lang";
    $date = new DateTime();

    // format the date in the correct language
    $formattedDate = $this->getFormattedDate($lang, $date);

    foreach ($cids as $cid) {
      $values[$cid][$tokenName] = $formattedDate;
    }
  }

  public function setDebitNoteDueDate($lang, $rawTokenName, &$values, $cids) {
    $tokenName = "picum.{$rawTokenName}_$lang";

    // date of today + 30 days
    $date = new DateTime();
    $date->add(new DateInterval('P30D'));

    // format the date in the correct language
    $formattedDate = $this->getFormattedDate($lang, $date);

    foreach ($cids as $cid) {
      $values[$cid][$tokenName] = $formattedDate;
    }
  }

  public function setPendingFeesLabel($lang, $rawTokenName, &$values, $cids) {
    $tokenName = "picum.{$rawTokenName}_$lang";

    foreach ($cids as $cid) {
      $values[$cid][$tokenName] = $this->translations['PENDING_PAYMENTS'][$lang];
    }
  }

  public function setPendingFeesList($lang, $rawTokenName, &$values, $cids) {
    $tokenName = "picum.{$rawTokenName}_$lang";

    foreach ($cids as $cid) {
      $values[$cid][$tokenName] = $this->getPendingFeesList($cid);
    }
  }

  private function getFormattedDate($lang, $date) {
    $months = [];
    $months['en'] = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $months['fr'] = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $months['es'] = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    return $date->format('j') . ' ' . $months[$lang][$date->format('n')] . ' ' . $date->format('Y');
  }

  private function getEmployer($cid) {
    $sql = "select employer_id from civicrm_contact where id = $cid";
    $orgId = CRM_Core_DAO::singleValueQuery($sql);
    return $orgId;
  }

  private function getPendingContribForYear($year, $orgId) {
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
        trim(source) = concat('Fee ', $year)
      and 
        contribution_status_id = $STATUS_PENDING 
      and 
        financial_type_id = $MEMBER_DUES
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return $dao;
    }
    else {
      return FALSE;
    }
  }

  private function getPendingFeesList($cid) {
    $sql = "
      select 
        ifnull(GROUP_CONCAT(c.source order by c.source SEPARATOR ', '), '') fee_list
      from 
        civicrm_contribution c
      where
        c.contribution_status_id = (
          select
            ov.value
          from 
            civicrm_option_value ov 
          inner join
            civicrm_option_group og on ov.option_group_id = og.id
          where 
            og.name = 'contribution_status'
          and
            ov.name = 'Pending'
        )
        and
          c.contact_id = $cid
    ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function setTranslations() {
    $this->translations['MEMBERSHIP_FEE_YEAR']['en'] = 'Membership fee year ';
    $this->translations['MEMBERSHIP_FEE_YEAR']['fr'] = 'Frais d\'adhésion année ';
    $this->translations['MEMBERSHIP_FEE_YEAR']['es'] = 'Cuota de membresía año  ';

    $this->translations['TOTAL']['en'] = 'Total';
    $this->translations['TOTAL']['fr'] = 'Total';
    $this->translations['TOTAL']['es'] = 'Total';

    $this->translations['STILL_OPEN']['en'] = 'Still open, according to our records. Please advise.';
    $this->translations['STILL_OPEN']['fr'] = 'Toujours en attente selon nos archives. Veuillez nous en informer.';
    $this->translations['STILL_OPEN']['es'] = 'La contribución sigue pendiente de pago según nuestras cuentas. Quedamos a la espera de su confirmación.';


    $this->translations['PREVIOUS_CONTRIBUTION']['en'] = 'Amount based on your previous contribution.';
    $this->translations['PREVIOUS_CONTRIBUTION']['fr'] = 'Montant basé sur votre cotisation précédente.';
    $this->translations['PREVIOUS_CONTRIBUTION']['es'] = 'Cantidad basada en su contribución anterior.';

    $this->translations['FREE_GUIDELINES']['en'] = 'See table below for the fee guidelines.';
    $this->translations['FREE_GUIDELINES']['fr'] = 'Voir le tableau ci-dessous pour les frais d\'adhésion.';
    $this->translations['FREE_GUIDELINES']['es'] = 'Vea la tabla a continuación con la referencia de las cuotas de membresía.';

    $this->translations['PENDING_PAYMENTS']['en'] = 'Pending payment(s)';
    $this->translations['PENDING_PAYMENTS']['fr'] = 'En attente de paiement';
    $this->translations['PENDING_PAYMENTS']['es'] = 'Pendiente de pago';
  }
}
