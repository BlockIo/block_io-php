<?php

use PHPUnit\Framework\TestCase;

// include all the BitWasp/Bitcoin stuff we'll use here
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PrivateKeyInterface;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\SignatureHash\SigHash;

class BitwaspBitcoinMinimalBranchTest extends TestCase
{
    private $keys;
    private $network;
    private $privkey1;
    private $privkey2;
    
    protected function setUp(): void {
        parent::setUp();

        // private keys for tests
        $this->keys = ["ef4fc6cfd682494093bbadf041ba4341afbe22b224432e21a4bc4470c5b939d4",
                       "123f37eb9a7f24a120969a1b2d6ac4859fb8080cfc2e8d703abae0f44305fc12"];

        // we will use litecoin testnet
        $this->network = (new NetworkFactory())->litecoinTestnet();

        // private key objects using BitWasp/Bitcoin
        $privkeyFactory = new PrivateKeyFactory();
        $this->privkey1 = $privkeyFactory->fromHexCompressed($this->keys[0]);
        $this->privkey2 = $privkeyFactory->fromHexCompressed($this->keys[1]);
        
    }

    protected function tearDown(): void {
        parent::tearDown();
    }

    public function testPublicKeys(): void {

        $this->assertEquals($this->privkey1->getPublicKey()->getHex(), "03820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e");
        $this->assertEquals($this->privkey2->getPublicKey()->getHex(), "0238de8c9eb2842ecaf0cc61ee6ba23fe4e46f1cfd82eac0910e1d8e865bd76df9");
            
    }

    public function testP2WPKHOverP2SHAddress(): void {
        // test generation of P2WPKH-over-P2SH address

        $pubkeyhash = $this->privkey1->getPublicKey()->getPubKeyHash();
        $witnessProgram = WitnessProgram::v0($pubkeyhash);
        $scriptHash = $witnessProgram->getScript()->getScriptHash();
        $this->assertEquals((new ScriptHashAddress($scriptHash))->getAddress($this->network), "Qgn9vENxxnNCPun8CN6KR1PPB7WCo9oxqc");
        
    }
    
    public function testP2WPKHAddress(): void {
        // test generation of P2WPKH address

        $pubkeyhash = $this->privkey1->getPublicKey()->getPubKeyHash();
        $witnessProgram = WitnessProgram::V0($pubkeyhash);
        $this->assertEquals((new SegwitAddress($witnessProgram))->getAddress($this->network), "tltc1qk2erszs7fp407kh94e6v3yhfq2njczjvg4hnz6");
        
    }
    
    public function testP2SHAddress(): void {
        // test generation of P2SH address

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $p2shMultisig = new P2shScript($multisig);
        $this->assertEquals($p2shMultisig->getAddress()->getAddress($this->network), "QPZMy7ivpYdkJRLhtTx7tj5Fa4doQ2auWk");

    }

    public function testP2WSHOverP2SHAddress(): void {
        // test generation of P2WSH-over-P2SH address

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $p2shwrappedMultisig = new P2shScript(new WitnessScript($multisig));
        $this->assertEquals($p2shwrappedMultisig->getAddress()->getAddress($this->network), "QeyxkrKbgKvxbBY1HLiBYjMnZx1HDRMYmd");

    }

    public function testWitnessV0Address(): void {
        // test generation of WITNESS_V0 address

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $witnessScript = (new WitnessScript($multisig));
        $this->assertEquals($witnessScript->getAddress()->getAddress($this->network), "tltc1q6s4cxsg5q4vm0ksst6rxn68h6ksrwumy9tvzgqa6jxuqllxyzh0qxt7q8g");

    }

    public function testP2PKHAddress(): void {
        // test generation of P2PKH address
        
        $pubkeyhash = $this->privkey1->getPublicKey()->getPubKeyHash();
        $this->assertEquals((new PayToPubKeyHashAddress($pubkeyhash))->getAddress($this->network), "mwop54ocwGjeErSTLCKgKxrdYp1k9o6Cgk");
        
    }
}

?>
