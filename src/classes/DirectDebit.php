<?php
namespace DirectDebit\Classes;

use DirectDebit\Exceptions\ValidationException;
use DirectDebit\Exceptions\ValidationExceptionValueTooLong;
use DOMDocument;
use Exception;
use LibBankaccount\Configuration;

class DirectDebit {

  /**
   * @var Configuration
   */
  protected $configuration;

  protected $msgId; // RestrictedIdentificationSEPA1 ([A-Za-z0-9]|[\+|\?|/|\-|:|\(|\)|\.|,|'| ]){1,35} max-length: 35
  /**
   * Auftraggeber, max 70 Zeichen
   * @var string
   */
  protected $name;  // Max70Text
  protected $paymentId; // // RestrictedIdentificationSEPA1 ([A-Za-z0-9]|[\+|\?|/|\-|:|\(|\)|\.|,|'| ]){1,35} max-length: 35

  /**
   * Nur CORE (SEPA-Basislastschrift),
   * COR1 (SEPA-Basislastschrift mit D-1-Vereinbarung)
   * und B2B (SEPA-Firmenlastschrift) ist zulässig.
   * @var string
   */
  protected $localInstrument; // Type of Debit (CORE = Basislastschrift)

  /**
   * Der SequenceType gibt an, ob es sich um eine Erst-, Folge- ,
   * Einmal- oder letztmalige Lastschrift handelt.
   * Zulässige Werte: FRST, RCUR, OOFF, FNAL. Muss FRST sein,
   * wenn <OrgnlDbtrAgt> = SMNDA (Same Mandat New Debtor Agent =
   * Bank hat sich geändert) und <AmdmntInd> = true (Mandat wurde verändert)
   * dann muss dieses Feld mit FRST belegt sein
   * (Macht Sinn: Wenn von einer neuen Bank das erste Mal eingezogen wird,
   * muss wieder FRST ausgeführt werden)
   *
   * Aufteilung in 2 Gruppen:
   * - Folgelastschrift (RCUR) auch bei Änderungen des Mandatats,
   *   dem Zahlungsempfänger, oder einer Kontoänderung des Debitors
   *   bei der gleichen Bank
   * - Erstlastschrift (FRST) bei neuem Mandat oder Wechsel
   *   des Kunden zu einer anderen Bank
   *
   * Theoretisch könnten auch beide Gruppen in einer XML-Datei behandelt werden
   * (mit mehreren PmtInf Blöcken)
   * @var string
   */
  protected $sequenceType; // FRST vs. RCUR, OOFF, FNAL

  /**
   * Fälligkeitsdatum der Lastschrift
   * @var string ISODate
   */
  protected $collectionDate; // ISODate Y-m-d e.g. 2010-12-03

  /**
   * IBAN der Kreditors
   * @var string
   */
  protected $iban;

  /**
   * BIC des Kreditors
   * @var string BIC
   */
  protected $bic;

  /**
   * GläubigerID. Validator: p. 56
   * @var string
   */
  protected $ci;

  /*
   * Name oder ID des Creditors (Zahlungsempfänger) haben sich geändert
  */
  protected $orgnlCdtrSchmeName = NULL;
  protected $orgnlCdtrSchmeId = NULL;


  /**
   *
   * @var DirectDebitTransaction[]
   */
  protected $transactions = array();

  /**
   * DirectDebit constructor.
   * @param Configuration $configuration
   */
  public function __construct($configuration) {
    $this->configuration = $configuration;
  }

  public function setMsgId($msgId){
  	if(!Validator::restrictedIdentificationSEPA1($msgId))
      throw new ValidationException('msgId', 'The message id is not a valid value');
    $this->msgId = $msgId;
    return $this;
  }

  public function setName($name, $autofix = FALSE) {
    if($autofix) {
      $name = self::convertText($name);
      $name = substr($name, 0, 70);
    } else {
    	if(!Validator::max70Text($name))
    		throw new ValidationExceptionValueTooLong('name', 'The name must not be empty or longer than 70 characters');
      if(!Validator::text($name))
        throw new ValidationException('name' , 'The name is not a valid value');
    }
    $this->name = $name;
    return $this;
  }

  public function setPaymentId($paymentId) {
  	if(!Validator::restrictedIdentificationSEPA1($paymentId))
      throw new ValidationException('paymentId', 'The payment id is not a valid value');
    $this->paymentId = $paymentId;
    return $this;
  }

  public function setLocalInstrument($localInstrument = "CORE") {
  	if(!Validator::externalLocalInstrument1Code($localInstrument))
      throw new ValidationException('localInstrument', 'The code for localinstrument is not valid');
    $this->localInstrument = $localInstrument;
    return $this;
  }

  public function setSequenceType($sequenceType) {
  	if(!Validator::sequenceType1Code($sequenceType))
  		throw new ValidationException('sequenceType', 'The code for sequenceType is not valid');

    //Check if sequenceType fits to all transactions
    if($sequenceType != "FRST") {
      foreach ($this->transactions as $tx) {
        if ($tx->debitorBankChanged == true)
          throw new ValidationException('sequenceType', 'For a debitor with changed bank account the sequenceType must be FRST.');
      }
    }

    $this->sequenceType = $sequenceType;
    return $this;
  }

  /**
   * Date of Debit collection
   * Format: Y-m-d e.g. 2010-12-0
   * @param $collectionDate
   *
   * @return  $this
   * @throws ValidationException
   */
  public function setCollectionDate($collectionDate) {
  	if(!Validator::isoDate($collectionDate))
  		throw new ValidationException('collectionDate', 'The value for collectionDate is not valid');

    //TODO date needs to be in the future
    if(strtotime($collectionDate) < time())
      throw new ValidationException('collectionDate', 'The collectionDate needs to be in the future');

    $this->collectionDate = $collectionDate;
    return $this;
  }

  public function setIban($iban) {
  	if(!Validator::iBAN2007Identifier($iban, $this->configuration))
  		throw new ValidationException('iban', 'The value for iban is not valid');
    $this->iban = $iban;
    return $this;
  }

  public function setBic($bic) {
  	if(!Validator::bicIdentifier($bic))
  		throw new ValidationException('bic', 'The value for bic is not valid');
    $this->bic = $bic;
    return $this;
  }

  public function setCi($ci) {
  	if(!Validator::restrictedPersonIdentifierSEPA($ci))
  		throw new ValidationException('ci', 'The value for creditor identifier is not valid');
    $this->ci = $ci;
    return $this;
  }

  public function setOrgnlCdtrSchmeName($name, $autofix = false){
  	if($autofix) {
  		$name = self::convertText($name);
  		$name = substr($name, 0, 70);
  	} else {
      if(empty($orgnlCdtrSchmeName))
        $orgnlCdtrSchmeName = NULL;
      else {
        if(!Validator::max70Text($name))
          throw new ValidationExceptionValueTooLong('orgnlCdtrSchmeName', 'The orgnlCdtrSchmeName must not be empty or longer than 70 characters');
        if(!Validator::text($name))
          throw new ValidationException('orgnlCdtrSchmeName' , 'The orgnlCdtrSchmeName is not a valid value');
      }
  	}
  	$this->orgnlCdtrSchmeName = $name;
  	return $this;
  }

  public function setOrgnlCdtrSchmeId($ci) {
    if(empty($orgnlCdtrSchmeName))
      $orgnlCdtrSchmeName = NULL;
    else {
      if(!Validator::restrictedPersonIdentifierSEPA($ci))
        throw new ValidationException('ci', 'The value for creditor identifier is not valid');
    }
  	$this->orgnlCdtrSchmeId = $ci;
  	return $this;
  }

  /**

   * @param DirectDebitTransaction[] $transactions
   *
*@return DirectDebit
   */
  public function setTransactions($transactions) {
    $this->transactions = array();
    return self::addTransactions($transactions);
  }

  /**
   * Add additional transaction
   *
   *@param DirectDebitTransaction $tx
   *
   *@throws Exception
   * @return DirectDebit
   */
  public function addTransaction($tx) {
  	if(!is_a($tx,"DirectDebit\Classes\DirectDebitTransaction")){
  		throw new Exception("You can only add transactions");
  	}
  	$this->transactions[] = $tx;
  	return $this;
  }

  /**
   * Add multiple transactions
   *
*@param DirectDebitTransaction[] $txs Array of transaction
   *
* @return DirectDebit
   */
  public function addTransactions($txs) {
  	foreach ($txs as $tx){
  		$this->addTransaction($tx);
  	}
  	return $this;
  }

  /**
   * @return mixed
   */
  public function getMsgId() {
    return $this->msgId;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return mixed
   */
  public function getPaymentId() {
    return $this->paymentId;
  }

  /**
   * @return string
   */
  public function getLocalInstrument() {
    return $this->localInstrument;
  }

  /**
   * @return string
   */
  public function getSequenceType() {
    return $this->sequenceType;
  }

  /**
   * @return string
   */
  public function getCollectionDate() {
    return $this->collectionDate;
  }

  /**
   * @return string
   */
  public function getIban() {
    return $this->iban;
  }

  /**
   * @return string
   */
  public function getBic() {
    return $this->bic;
  }

  /**
   * @return string
   */
  public function getCi() {
    return $this->ci;
  }

  /**
   * @return null
   */
  public function getOrgnlCdtrSchmeName() {
    return $this->orgnlCdtrSchmeName;
  }

  /**
   * @return null
   */
  public function getOrgnlCdtrSchmeId() {
    return $this->orgnlCdtrSchmeId;
  }

  /**
   * @return DirectDebitTransaction[]
   */
  public function getTransactions() {
    return $this->transactions;
  }

  public function generateXML() {
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;

    // Build Document-Root
    $document = $dom->createElement('Document');
    $document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.003.02');
    $document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $document->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.008.003.02 pain.008.003.02.xsd');
    $dom->appendChild($document);

    // Build Content-Root
    $content = $dom->createElement('CstmrDrctDbtInitn');
    $document->appendChild($content);

    // Build Header
    $header = $dom->createElement('GrpHdr');
    $content->appendChild($header);

    $header->appendChild($dom->createElement('MsgId', $this->msgId));
    $header->appendChild($dom->createElement('CreDtTm', date('c')));
    $header->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
    $header->appendChild($initatorName = $dom->createElement('InitgPty'));
    $initatorName->appendChild($dom->createElement('Nm', $this->name));

    // PaymentInfo
    $paymentInfo = $dom->createElement('PmtInf');
    $content->appendChild($paymentInfo);

    $paymentInfo->appendChild($dom->createElement('PmtInfId', $this->paymentId));
    $paymentInfo->appendChild($dom->createElement('PmtMtd', 'DD'));
    $paymentInfo->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
    $paymentInfo->appendChild($dom->createElement('CtrlSum', $this->getCummulatedAmount()));
    $paymentInfo->appendChild($pmtTpInf = $dom->createElement('PmtTpInf'));
    $pmtTpInf->appendChild($svcLvl = $dom->createElement('SvcLvl'));
    $svcLvl->appendChild($dom->createElement('Cd', 'SEPA'));
    $pmtTpInf->appendChild($lclInstrm = $dom->createElement('LclInstrm'));
    $lclInstrm->appendChild($dom->createElement('Cd', $this->localInstrument));
    $pmtTpInf->appendChild($dom->createElement('SeqTp', $this->sequenceType));

    // Collection Date
    $paymentInfo->appendChild($dom->createElement('ReqdColltnDt', $this->collectionDate));

    // Creditor Info
    $paymentInfo->appendChild($cdtr = $dom->createElement('Cdtr'));
    $cdtr->appendChild($dom->createElement('Nm', $this->name));

    $paymentInfo->appendChild($cdtrAcct = $dom->createElement('CdtrAcct'));
    $cdtrAcct->appendChild($id = $dom->createElement('Id'));
    $id->appendChild($dom->createElement('IBAN', $this->iban));

    $paymentInfo->appendChild($cdtrAgt = $dom->createElement('CdtrAgt'));
    $cdtrAgt->appendChild($finInstnId = $dom->createElement('FinInstnId'));
    $finInstnId->appendChild($dom->createElement('BIC', $this->bic));

    $paymentInfo->appendChild($dom->createElement('ChrgBr', 'SLEV'));

    // Creditor Scheme
    $paymentInfo->appendChild($cdtrSchmeId = $dom->createElement('CdtrSchmeId'));
    $cdtrSchmeId->appendChild($id = $dom->createElement('Id'));
    $id->appendChild($prvtId = $dom->createElement('PrvtId'));
    $prvtId->appendChild($othr = $dom->createElement('Othr'));
    $othr->appendChild($dom->createElement('Id', $this->ci));
    $othr->appendChild($schmeNm = $dom->createElement('SchmeNm'));
    $schmeNm->appendChild($dom->createElement('Prtry', 'SEPA'));

    // Add Transactions
    foreach($this->transactions as $transaction) {
      $drctDbtTxInf = $dom->createElement('DrctDbtTxInf');
      $paymentInfo->appendChild($drctDbtTxInf);

      $drctDbtTxInf->appendChild($pmtId = $dom->createElement('PmtId'));
      $pmtId->appendChild($dom->createElement('EndToEndId', $transaction->endToEndId));

      $drctDbtTxInf->appendChild($instdAmt =  $dom->createElement('InstdAmt', $transaction->amount));
      $instdAmt->setAttribute('Ccy', $transaction->currency);
      //$drctDbtTxInf->appendChild($dom->createElement('InstdAmt', $transaction->amount));

      $directDebitTransaction = $dom->createElement('DrctDbtTx');
      $drctDbtTxInf->appendChild($directDebitTransaction);

      // All about the mandate
      $directDebitTransaction->appendChild($mndtRltdInf = $dom->createElement('MndtRltdInf'));
      $mndtRltdInf->appendChild($dom->createElement('MndtId', $transaction->mandateId));
      $mndtRltdInf->appendChild($dom->createElement('DtOfSgntr', $transaction->mandateDate));

      if ($transaction->getAmdmntInd()) {
      /*
       * Es gibt 4 Möglichkeiten was sich geändert haben könnte:
       * 1. MandatID: OrgnlMdntID enthält das alte Mandat
       * 2. Name / ID des Zahlungsempfängers: OrgnlCdtrSchmeId muss gefüllt werden
       * 3. Debitor hat neues Konto bei der gleichen Bank: OrgnlDbtrAcct
       * 4. Konto des Debitors (neue Bank): OrgnlDbtrAgt enthält SMNDA --> FRST
       */
        $mndtRltdInf->appendChild($dom->createElement('AmdmntInd', 'true'));
        $mndtRltdInf->appendChild($amdmntInfDtls = $dom->createElement('AmdmntInfDtls'));

        // Mandate has changed
        if(!empty($transaction->orgnlMandateId)) {
          $amdmntInfDtls->appendChild($dom->createElement('OrgnlMndtId',
            $transaction->orgnlMandateId));
        }

        if ($this->isNameOrCIChanged()) {
          $amdmntInfDtls->appendChild($orgnlCdtrSchmeId = $dom->createElement('OrgnlCdtrSchmeId'));

          //Creditor Name has changed
          if(!empty($this->orgnlCdtrSchmeName)) {
            $orgnlCdtrSchmeId->appendChild($dom->createElement('Nm', $this->orgnlCdtrSchmeName));
          }

          //Creditor ID has changed
          if(!empty($this->orgnlCdtrSchmeId)) {
            $orgnlCdtrSchmeId->appendChild($orgnlCdtrSchmeIdId = $dom->createElement('Id'));
            $orgnlCdtrSchmeIdId->appendChild($orgnlPrvtId = $dom->createElement('PrvtId'));
            $orgnlPrvtId->appendChild($orgnlOthr = $dom->createElement('Othr'));
            $orgnlOthr->appendChild($dom->createElement('Id', $this->orgnlCdtrSchmeId));
            $orgnlOthr->appendChild($orgnlSchmeNm = $dom->createElement('SchmeNm'));
            $orgnlSchmeNm->appendChild($dom->createElement('Prtry', 'SEPA'));
          }
        }

        //Debitor account has changed at the same bank
        if(!empty($transaction->orgnlDbtrAcct)){
          $amdmntInfDtls->appendChild($orgnlDbtrAcct = $dom->createElement('OrgnlDbtrAcct'));
          $orgnlDbtrAcct->appendChild($orgnlId = $dom->createElement('Id'));
          $orgnlId->appendChild($dom->createElement("IBAN", $transaction->orgnlDbtrAcct));
        }

        //Debitor bank has changed
        if($transaction->debitorBankChanged == true) {
          $amdmntInfDtls->appendChild($orgnlDbtrAgt = $dom->createElement('OrgnlDbtrAgt'));
          $orgnlDbtrAgt->appendChild($orgnlFinInstnId = $dom->createElement('FinInstnId'));
          $orgnlFinInstnId->appendChild($finInstnIdOthr = $dom->createElement('Othr'));
          $finInstnIdOthr->appendChild($dom->createElement('Id', 'SMNDA'));
        }
      }

      // Payment data
      $drctDbtTxInf->appendChild($dbtrAgt = $dom->createElement('DbtrAgt'));
      $dbtrAgt->appendChild($finInstnId = $dom->createElement('FinInstnId'));
      $finInstnId->appendChild($dom->createElement('BIC', $transaction->bic));

      $drctDbtTxInf->appendChild($dbtr = $dom->createElement('Dbtr'));
      $dbtr->appendChild($dom->createElement('Nm', $transaction->name));

      $drctDbtTxInf->appendChild($dbtrAcct = $dom->createElement('DbtrAcct'));
      $dbtrAcct->appendChild($id = $dom->createElement('Id'));
      $id->appendChild($dom->createElement('IBAN', $transaction->iban));

      $drctDbtTxInf->appendChild($rmtInf = $dom->createElement('RmtInf'));
      $rmtInf->appendChild($dom->createElement('Ustrd', $transaction->message));
    }

    // XML exportieren
    return $dom->saveXML();
  }
    
  function getCummulatedAmount() {
    $amount = 0;

    foreach ($this->transactions as $transaction) {
      $amount += $transaction->getAmount();
    }

    return $amount;
  }

  /**
   * @return boolean
   */
  public function isNameOrCIChanged() {
    return ($this->orgnlCdtrSchmeName != NULL || $this->orgnlCdtrSchmeId != NULL);
  }

  /*
   * Static functions
   *
   */

  /**
   * Create a new DirectDebit with default values
   *
   * @param Configuration $configuration
   * @param string $ci CreditorIdentifier
   * @param string $name Name of Creditor
   * @param string $iban Iban of Creditor
   * @param string $bic Bic of Creditor
   * @param string $collectionDate Collection date of debit
   * @param string $sequenceType Sequence Type
   * @param string $localInstument Local Instrument
   * @param null   $orgnlCdtrSchmeName Old Creditor Name
   * @param null   $orgnlCdtrSchmeId Old Creditor Identifier
   * @param DirectDebitTransaction[]  $transactions Array of Transactions
   *
   * @return DirectDebit
   * @throws ValidationException
   * @throws ValidationExceptionValueTooLong
   */
  public static function createDirectDebit($configuration, $ci, $name, $iban, $bic, $collectionDate,
                                           $sequenceType, $localInstument = "CORE",
                                           $orgnlCdtrSchmeName = NULL, $orgnlCdtrSchmeId = NULL,
                                           $transactions = array()){
    $dd = new DirectDebit($configuration);
    $dd->setCi($ci);
    $dd->setName($name,true);
    $dd->setIban($iban);
    $dd->setBic($bic);
    $dd->setCollectionDate($collectionDate);
    $dd->setSequenceType($sequenceType);
    $dd->setLocalInstrument($localInstument);
    $dd->setOrgnlCdtrSchmeId($orgnlCdtrSchmeId);
    $dd->setOrgnlCdtrSchmeName($orgnlCdtrSchmeName, true);
    $dd->setTransactions($transactions);

    //Generate some values
    $dd->setMsgId(substr(time(),0,35));
    $dd->setPaymentId(substr(time(),0,35));

    return $dd;
    }

  public static function convertText($subject) {
    // Remove everything that is not compatible with the standart
    // Allowed characters are: A-Za-z0-9':?,- ()+./ÖÄÜöäüß&*$%
    // See page 23 of spec
    return preg_replace("/[^A-Za-z0-9':\?,\-\(\)\+\.\/ÖÄÜöäüß&\*\$% ]/", "", $subject);
  }
}
