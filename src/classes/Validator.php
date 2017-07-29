<?php
namespace DirectDebit\Classes;

use LibBankaccount\Account;
use LibBankaccount\Configuration;

class Validator {

	const anyBICIdentifier = "anyBICIdentifier";
	const bicIdentifier = "bicIdentifier";
	const countryCode = "countryCode";
	const activeOrHistoricCurrencyCode = "activeOrHistoricCurrencyCode";
	const activeOrHistoricCurrencyCodeEUR = "activeOrHistoricCurrencyCodeEUR";
	const decimalTime = "decimalTime";
	const iBAN2007Identifier = "iBAN2007Identifier";
	const max1025Text = "max1025Text";
	const max140Text = "max140Text";
	const max15NumericText = "max15NumericText";
	const max35Text = "max35Text";
	const max70Text = "max70Text";
	const restrictedIdentificationSEPA1 = "restrictedIdentificationSEPA1";
	const restrictedIdentificationSEPA2 = "restrictedIdentificationSEPA2";
	const restrictedPersonIdentifierSEPA = "restrictedPersonIdentifierSEPA";
	const chargeBearerTypeSEPACode = "chargeBearerTypeSEPACode";
	const sequenceType1Code = "sequenceType1Code";
	const externalLocalInstrument1Code = "externalLocalInstrument1Code";
	const transactionGroupStatus1CodeSEPA = "transactionGroupStatus1CodeSEPA";
	const isoDateTime = "isoDateTime";
	const isoDate = "isoDate";
	const decimalNumber = "decimalNumber";
	const amountSEPA = "amountSEPA";

	public static function anyBICIdentifier($param) {
		return self::bicIdentifier($param);
	}

	public static function bicIdentifier($param) {
		if (preg_match("/^[A-Z]{6,6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3,3}){0,1}$/", $param) === 1){
      /* $account = new Account();
      $account->setBic($param);
      if($account->validateBIC())
      //TODO Does not work with international bics
      //Posible solution: Addtional parameter to switch this check off for
      //international transactions
      */
        return true;
    }

    return false;
	}

	public static function countryCode($param) {
		if (preg_match("/^[A-Z]{2,2}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function activeOrHistoricCurrencyCode($param) {
		if (preg_match("/^[A-Z]{3,3}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function activeOrHistoricCurrencyCodeEUR($param) {
		return $param === "EUR";
	}

	public static function decimalNumber($param) {
		if(is_float($param))
			return true;
		else
			return false;
	}

	public static function decimalTime($param) {
		if (preg_match("/^[0-9]{9,9}$/", $param) === 1)
			return true;
		else
			return false;
	}

  /**
   * @param string $param
   * @param Configuration $configuration
   * @return bool
   */
	public static function iBAN2007Identifier($param, $configuration) {
		if (preg_match("/^[A-Z]{2,2}[0-9]{2,2}[a-zA-Z0-9]{1,30}$/", $param) === 1) {
      $account = new Account($configuration);
      $account->setIban($param);
      if ($account->validateIban())
        return true;
    }

		return false;
	}

	public static function text($param) {
		if (preg_match("/^([A-Za-z0-9]|[\+|\?|\/|\-|:|\(|\)|\.|,|'|Ö|Ä|Ü|ö|ä|ü|ß|&|\*|\$|\%| ])*$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function max1025Text($param) {
		return strlen($param)<=1025 && strlen($param)>0;
	}

	public static function max140Text($param) {
		return strlen($param)<=140 && strlen($param)>0;
	}

	public static function max15NumericText($param) {
		if (preg_match("/^[0-9]{1,15}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function max35Text($param) {
		return strlen($param)<=35 && strlen($param)>0;
	}

	public static function max70Text($param) {
		return strlen($param)<=70 && strlen($param)>0;
	}

	public static function restrictedIdentificationSEPA1($param) {
		if (preg_match("/^([A-Za-z0-9]|[\+|\?|\/|\-|:|\(|\)|\.|,|'| ]){1,35}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function restrictedIdentificationSEPA2($param) {
		if (preg_match("/^([A-Za-z0-9]|[\+|\?|\/|\-|:|\(|\)|\.|,|']){1,35}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function restrictedPersonIdentifierSEPA($param) {
		if (preg_match("/^[a-zA-Z]{2,2}[0-9]{2,2}([A-Za-z0-9]|[\+|\?|\/|\-|:|\(|\)|\.|,|']){3,3}([A-Za-z0-9]|[\+|\?|\/|\-|:|\(|\)|\.|,|']){1,28}$/", $param) === 1)
			return true;
		else
			return false;
	}

	public static function chargeBearerTypeSEPACode($param) {
		$codes = array('SLEV', 'SCOR');
		if(is_numeric(array_search($param, $codes)))
			return true;
		else
			return false;
	}

	public static function sequenceType1Code($param) {
		$codes = array('FRST', 'RCUR', 'FNAL', 'OOFF');
		if(is_numeric(array_search($param, $codes)))
			return true;
		else
			return false;
	}

	public static function externalLocalInstrument1Code($param) {
		$codes = array('CORE', 'COR1', 'B2B');
		if(is_numeric(array_search($param, $codes)))
			return true;
		else
			return false;
	}

	public static function transactionGroupStatus1CodeSEPA($param) {
		$codes = array('RJCT');
		if(is_numeric(array_search($param, $codes)))
			return true;
		else
			return false;
	}

	public static function isoDateTime($param) {
		if (preg_match('/^'.
				'(\d{4})-(\d{2})-(\d{2})T'. // YYYY-MM-DDT ex: 2014-01-01T
				'(\d{2}):(\d{2}):(\d{2})(\.\d+){0,1}'.  // HH-MM-SS  ex: 17:00:00.000
				'(Z|((-|\+)\d{2}:\d{2}))'.  // Z or +01:00 or -01:00
				'$/', $param, $parts) == true)
		{
			try {
				new \DateTime($param);
				return true;
			}
			catch ( \Exception $e)
			{
				return false;
			}
		} else {
			return false;
		}
	}

	public static function isoDate($param) {
		if (preg_match('/^'.
				'(\d{4})-(\d{2})-(\d{2})'. // YYYY-MM-DDT ex: 2014-01-01
				'$/', $param, $parts) == true)
		{
			try {
				new \DateTime($param);
				return true;
			}
			catch ( \Exception $e)
			{
				return false;
			}
		} else {
			return false;
		}
	}

	public static function amountSEPA($param) {
		if (preg_match("/^\d+\.\d{2}$/", $param) === 1)
			return true;
		else
			return false;
	}
}