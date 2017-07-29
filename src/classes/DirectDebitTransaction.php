<?php
namespace DirectDebit\Classes;

use DirectDebit\Exceptions\ExceptionArray;
use DirectDebit\Exceptions\ValidationException;
use DirectDebit\Exceptions\ValidationExceptionValueTooLong;
use Exception;
use LibBankaccount\Configuration;

class DirectDebitTransaction {
  /**
   * @var Configuration
   */
  protected $configuration;

	protected $directDebit; //Überweisung zu der diese Transaktion gehört
	public $endToEndId; //Eindeutige ID für Transaktion
	public $amount; //InstructedAmount
	public $currency; //Währung
	public $mandateId; //Restricted-IdentificationSEPA2
	public $bic;
	public $iban;
	public $name; //Name des Zahlungspflichtigen (max 70 Zeichen)
	public $mandateDate; //Datum an dem das Mandat unterschrieben wurde
	public $message; //Verwendungszweck (max 140 Zeichen)

	public $orgnlMandateId; //Mandat hat sich geändert
	public $orgnlDbtrAcct; //Konto hat sich bei der gleichen bank geändert
	public $debitorBankChanged;


	/**
	 * Konstruktor
	 *
   * @param Configuration $configuration Configuration
	 * @param DirectDebit $directDebit Parent DirectDebit
	 * @param integer $endToEndId Eindeutige ID für Transaktion
	 * @param string $mandateId MandateID
	 * @param string $mandateDate Date of signature (2000-01-01)
	 * @param string $name Name des Zahlungspflichtigen
	 * @param string $iban IBAN
	 * @param string $bic BIC
	 * @param float $amount Instructed Amount
	 * @param string $currency Währung
	 * @param string $message Verwendungszweck
	 * @param string $orgnlMandateId Original MandateID
	 * @param string $orgnlDbtrAcct Original Debitor Account
	 * @param boolean $debitorBankChanged Debitor switched to another bank
	 */
	public function __construct($configuration, $directDebit, $endToEndId, $mandateId, $mandateDate,
			$name, $iban, $bic, $amount, $currency = "EUR", $message, $orgnlMandateId = NULL,
			$orgnlDbtrAcct = NULL, $debitorBankChanged = false) {

	  $this->configuration = $configuration;
    $this->setDirectDebit($directDebit);
    $this->setEndToEndId($endToEndId);
    $this->setMandateId($mandateId);
    $this->setMandateDate($mandateDate);
    $this->setName($name, true);
    $this->setIban($iban);
    $this->setBic($bic);
    $this->setAmount($amount);
    $this->setCurrency($currency);
    $this->setMessage($message);
    $this->setOrgnlMandateId($orgnlMandateId);
    $this->setOrgnlDbtrAcct($orgnlDbtrAcct);
    $this->setDebitorBankChanged($debitorBankChanged);

	}

  /**
   * @param DirectDebit $directDebit
   *
   * @throws Exception
   */
  public function setDirectDebit($directDebit) {
    if(!is_a($directDebit,"DirectDebit\Classes\DirectDebit")){
      throw new Exception("You can only add DirectDebit Objects");
    }
    $this->directDebit = $directDebit;
  }

  /**
   * @param int $endToEndId
   *
   * @throws ValidationException
   */
  public function setEndToEndId($endToEndId) {
    if(!Validator::restrictedIdentificationSEPA1($endToEndId))
      throw new ValidationException("endToEndId", "The value '" . $endToEndId . "' for endToEndId
			is not valid");
    $this->endToEndId = $endToEndId;
  }

  /**
   * @param float $amount
   *
   * @throws ValidationException
   */
  public function setAmount($amount) {
  	$amount = str_replace(" ","",$amount);
  	
    if(!Validator::amountSEPA($amount))
      throw new ValidationException("amount", "The value " . $amount . " for amount is not
			valid");
    $this->amount = $amount;
  }

  /**
   * @param string $currency
   *
   * @throws ValidationException
   */
  public function setCurrency($currency) {
  	$currency = str_replace(" ","",$currency);
  	
    if(!Validator::activeOrHistoricCurrencyCode($currency))
      throw new ValidationException("currency", "The value " . $currency . " for currency is not
      valid");
    $this->currency = $currency;
  }

  /**
   * @param string $mandateId
   *
   * @throws ValidationException
   */
  public function setMandateId($mandateId) {
  	$mandateId = str_replace(" ","",$mandateId);
  	
  	if(!Validator::restrictedIdentificationSEPA2($mandateId))
      throw new ValidationException("mandateId", "The value '" . $mandateId . "' for mandateId is
			not valid");
    $this->mandateId = $mandateId;
  }

  /**
   * @param string $bic
   *
   * @throws ValidationException
   */
  public function setBic($bic) {
  	$bic = str_replace(" ","",$bic);
  	$bic = strtoupper($bic);  	
  	
    if(!Validator::bicIdentifier($bic))
      throw new ValidationException("bic", "The value '" . $bic . "' for bic is not valid");
    $this->bic = $bic;
  }

  /**
   * @param string $iban
   *
   * @throws ValidationException
   */
  public function setIban($iban) {
    $iban = str_replace(" ","",$iban);
    $iban = strtoupper($iban);

    if(!Validator::iBAN2007Identifier($iban, $this->configuration))
      throw new ValidationException("iban", "The value '" . $iban . "' for iban is not valid");
    $this->iban = $iban;
  }

  /**
   * @param string $name
   *
   * @throws ValidationException
   * @throws ValidationExceptionValueTooLong
   */
  public function setName($name, $autofix = false) {
    if($autofix == true)
      $name = DirectDebit::convertText($name);

    if(!Validator::max70Text($name))
      throw new ValidationExceptionValueTooLong('name', 'The name must not be empty or longer than 70 characters');
    if(!Validator::text($name))
      throw new ValidationException('name' , "The value '" . $name . "' for name is not valid");
    $this->name = $name;
  }

  /**
   * @param string $mandateDate
   *
   * @throws ValidationException
   */
  public function setMandateDate($mandateDate) {
  	$mandateDate = str_replace(" ","",$mandateDate);
  	
    if(!Validator::isoDate($mandateDate))
      throw new ValidationException("mandateDate", "The value '" . $mandateDate . "' for
			mandateDate is not valid");
    $this->mandateDate = $mandateDate;
  }

  /**
   * @param string $message
   *
   * @throws ValidationException
   * @throws ValidationExceptionValueTooLong
   */
  public function setMessage($message) {
    if(!Validator::max140Text($message))
      throw new ValidationExceptionValueTooLong('message', 'The name must not be empty or longer
      than 140 characters');
    if(!Validator::text($message))
      throw new ValidationException('message' , "The value " . $message . " for message is not
			valid");
    $this->message = $message;
  }

  /**
   * @param null|string $orgnlMandateId
   *
   * @throws ValidationException
   */
  public function setOrgnlMandateId($orgnlMandateId) {
  	$orgnlMandateId = str_replace(" ","",$orgnlMandateId);
  	
    if(empty($orgnlMandateId))
      $orgnlMandateId = NULL;
    if($orgnlMandateId != NULL && !Validator::restrictedIdentificationSEPA2($orgnlMandateId))
      throw new ValidationException("orgnlMandateId", "The value '" . $orgnlMandateId . "' for orgnlMandateId is not valid");
    $this->orgnlMandateId = $orgnlMandateId;
  }

  /**
   * @param null|string $orgnlDbtrAcct
   *
   * @throws ValidationException
   */
  public function setOrgnlDbtrAcct($orgnlDbtrAcct) {
  	$orgnlDbtrAcct = str_replace(" ","",$orgnlDbtrAcct);
  	$orgnlDbtrAcct = strtoupper($orgnlDbtrAcct);
  	
    if(empty($orgnlDbtrAcct))
      $orgnlDbtrAcct = NULL;
    if($orgnlDbtrAcct != NULL && !Validator::iBAN2007Identifier($orgnlDbtrAcct, $this->configuration))
      throw new ValidationException("orgnlDbtrAcct", "The value '" . $orgnlDbtrAcct . "' for orgnlDbtrAcct is not valid");
    $this->orgnlDbtrAcct = $orgnlDbtrAcct;
  }

  /**
   * @param boolean $debitorBankChanged
   *
   * @throws ValidationException
   */
  public function setDebitorBankChanged($debitorBankChanged) {
    if($debitorBankChanged == true && $this->directDebit->getSequenceType() != 'FRST')
      throw new ValidationException('sequenceType', 'The Sequence Type needs to be FRST.');
    $this->debitorBankChanged = $debitorBankChanged;
  }

	/**
	 * Wurde das Mandat (seit dem letzten Einzug) verändert?
	 * @return boolean
	 */
	public function getAmdmntInd() {
		/*
		 * Es gibt 4 Möglichkeiten was sich geändert haben könnte:
		* 1. MandatID: OrgnlMdntID enthält das alte Mandat
		* 2. Name / ID des Zahlungsempfängers: OrgnlCdtrSchmeId muss gefüllt werden
		* 3. Debitor hat neues Konto bei der gleichen Bank: OrgnlDbtrAcct
		* 4. Konto des Debitors (neue Bank): OrgnlDbtrAgt enthält SMNDA --> FRST
		*/
		if (!empty($this->orgnlMandateId) || !empty($this->orgnlDbtrAcct) || $this->directDebit->isNameOrCIChanged() ||
      $this->debitorBankChanged) {
			return true;
		}
		return false;
	}

    public function getAmount(){
        return $this->amount;
    }

	/**
	 * ================
	 * Static functions
	 * ================
	 */

	/**
	 * Creates EndToEndID
	 * Es wird empfohlen, jede Lastschrift mit einer eindeutigen Referenz zu belegen
	 * @param number $transactionNumber Laufende Nummer der Transaktionen oder MandatID
	 * @return string
	 */
	public static function generateEndToEndId($transactionNumber){
		return substr($transactionNumber . "-" . date('YmdHis', time()), 0,35);
	}

  /**
   * Try to parse a csv-file to transactions
   * @param resource $file
   * @param string $delimiter
   * @param DirectDebit $dD
   * @param Configuration $configuration Configuration
   * @return DirectDebitTransaction[]
   * @throws ExceptionArray
   */
  public static function csvfile2Transactions($file, $delimiter, $dD, $configuration){
    //Read line by line
    $transactions = array();
    $i = 0;
    $exceptions = array();
    while (($line = fgetcsv($file, 0, $delimiter)) !== FALSE) {
      if(count($line) >= 8) {
        if(!isset($line[8])) //orgnlMandateId
          $line[8] = null; //orgnlDbtrAcct
        if(!isset($line[9]))
          $line[9] = null;

        try {
          $i++;

          if ($line[7] == "1")
            $debitorBankChanged = true;
          else
            $debitorBankChanged = false;

          $transactions[] = new DirectDebitTransaction($configuration, $dD,
            DirectDebitTransaction::generateEndToEndId($i), $line[0], $line[1],
            $line[2], $line[3], $line[4], $line[5], "EUR", $line[6], $line[8],
            $line[9], $debitorBankChanged);
        } catch (ValidationException $e) {
          $exceptions[] = new ValidationException($e->field, "Transaction: " .
            implode($delimiter, $line) . " <br />
          " . $e->getMessage());
        }
      }
      else {
        //Line imcomplete
        $exceptions[] = new Exception("Line imcomplete or wrong delimiter: " . implode($delimiter, $line));
      }
    }

    if(!empty($exceptions))
      throw new ExceptionArray($exceptions);

    return $transactions;
  }
}