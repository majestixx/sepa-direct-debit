<?php
use DirectDebit\Classes\DirectDebit;
use DirectDebit\Classes\DirectDebitTransaction;
use DirectDebit\Classes\Validator;

require_once (__DIR__ . "/../vendor/autoload.php");

class DirectDebitTest extends PHPUnit_Framework_TestCase {

  /**
   * @var DirectDebit
   */
  protected $object;

  /**
   * @var \LibBankaccount\Configuration
   */
  protected $configuration;

  protected function setUp()
  {
    $this->configuration = new \LibBankaccount\Configuration("localhost","root","","sepa");

    $this->object = DirectDebit::createDirectDebit($this->configuration,'DE00ZZZ00099999999','Initiator ' .
      'Name', 'DE87200500001234567890', 'BANKDEFFXXX', '2025-12-03', 'FRST', 'CORE',
      'Original Creditor Name', 'AA00ZZZOriginalCreditorID');
  }

  public function testGenerateXML()
  {
    $this->object->setPaymentId('Payment-ID');
    $this->object->setMsgId('Message-ID');

    $transactions[] = new DirectDebitTransaction($this->configuration, $this->object, 'OriginatorID1234',
      'Mandate-Id', '2010-11-20', 'Debtor Name', 'DE21500500009876543210',
      'SPUEDE2UXXX', 6543.14,'EUR', 'Unstructured Remittance Information');

    $transactions[] = new DirectDebitTransaction($this->configuration, $this->object, 'OriginatorID1235',
      'Other-Mandate-Id', '2010-11-20', 'Other Debtor Name', 'DE21500500001234567897',
      'SPUEDE2UXXX', 112.72,'EUR', 'Unstructured Remittance Information');

    $this->object->setTransactions($transactions);

    //Read reference file
    $filename = __DIR__ . "/data/sepa.xml";
    $file = fopen($filename, "r");
    $content = fread($file, filesize($filename));

    $content = str_replace("<CreDtTm>2017-07-26T09:09:30+02:00</CreDtTm>", "<CreDtTm>" . date('c') . "</CreDtTm>", $content);
    $this->assertXmlStringEqualsXmlString($content, $this->object->generateXML());
  }

  public function testGetCummulatedAmount(){

    $count = 0;
    $tx1 = new DirectDebitTransaction($this->configuration, $this->object, $count++,
					'Mandate-Id', '2010-11-20', 'Debtor Name', 'DE21500500009876543210',
					'SPUEDE2UXXX', 6543.14, 'EUR','Unstructured Remittance Information');
    $this->object->addTransaction($tx1);

    $tx2 = new DirectDebitTransaction($this->configuration, $this->object, $count++,
					'Mandate-Id', '2010-11-20', 'Debtor Name', 'DE21500500009876543210',
					'SPUEDE2UXXX', 6543.14, 'EUR', 'Unstructured Remittance Information');
    $this->object->addTransaction($tx2);

    $this->assertTrue(Validator::decimalNumber($this->object->getCummulatedAmount()));
    $this->assertEquals($this->object->getCummulatedAmount(), $tx1->getAmount() + $tx2->getAmount());
  }

  public function testConvertText() {
    // Remove illegal characters
    print(DirectDebit::convertText('[]}_""'));
    $this->assertEquals(DirectDebit::convertText('[]}_""'), '');

    // Pass all allowed characters
    $this->assertEquals(DirectDebit::convertText("A-Za-z0-9':?,- ()+./ÖÄÜöäüß&*$%"), "A-Za-z0-9':?,- ()+./ÖÄÜöäüß&*$%");
  }
}
