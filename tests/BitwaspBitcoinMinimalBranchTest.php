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
    private $networkFee;
    
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

        // the network fee we will use for transaction tests
        $this->networkFee = 10000;
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

    // signatures used by PHPECC 1.0.0 do not use low R signatures
    // TODO revise these tests once we start using low R signatures (PHPECC)
    
    public function testTransactionP2SHToP2WSHOverP2SH(): void {
        // test spending P2SH outputs and sending to P2WSH-over-P2SH address

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $p2shMultisig = new P2shScript($multisig);
        $p2shwrappedMultisig = new P2shScript(new WitnessScript($multisig));
        
        $from_address = $p2shMultisig->getAddress()->getAddress($this->network);
        $to_address = $p2shwrappedMultisig->getAddress()->getAddress($this->network);

        $this->assertEquals($from_address, "QPZMy7ivpYdkJRLhtTx7tj5Fa4doQ2auWk");
        $this->assertEquals($to_address, "QeyxkrKbgKvxbBY1HLiBYjMnZx1HDRMYmd");

        $prevOutputValue = 1000000000;
        
        $outpoint = new OutPoint(Buffer::hex('4ad80b9776f574a125f89e96bda75bb6fe046f7560847d16446bbdcdc160be62'), 1);
        $txOut = new TransactionOutput($prevOutputValue, (new AddressCreator())->fromString($from_address, $this->network)->getScriptPubKey());
        $outputValue = $prevOutputValue - $this->networkFee;

        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())                      
                  ->get();

        $this->assertEquals($unsigned->getHex(), "010000000162be60c1cdbd6b44167d8460756f04feb65ba7bd969ef825a174f576970bd84a0100000000ffffffff01f0a29a3b0000000017a914c99a494597ade09b5194f9ec8e02d96607ae64798700000000");

        $signer = new Signer($unsigned);

        $signData = (new SignData())->p2sh($p2shMultisig);
        $input = $signer->input(0, $txOut, $signData);

        $sigHash0 = $input->getSigHash(SigHash::ALL);

        $this->assertEquals($sigHash0->getHex(), "93a075651d1b6b79cd9bf128bf5e15001fe65865defea6cedab0a1da438f565e");

        // sign the input
        $input->sign($this->privkey1);
        $input->sign($this->privkey2);

        // TODO change when using low R signatures in the future

        $this->assertEquals($signer->get()->getHex(), "010000000162be60c1cdbd6b44167d8460756f04feb65ba7bd969ef825a174f576970bd84a01000000da00473044022009143b07279ef6d5317865672e9fc28ada31314abf242ae786917b92cf027ac002207544d055f2b8bb249dc0294d565c6d538f4e04f9b142331fa103d82e0498a181014830450221009ce297b1eba341be03e0ae656ac0233464c8249d36f3659676b01c45c74808680220252d0c54d56d78b4193cc1c63b7b8dc2a6b9889bed5dc3555571c8aaa1a710e70147522103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e210238de8c9eb2842ecaf0cc61ee6ba23fe4e46f1cfd82eac0910e1d8e865bd76df952aeffffffff01f0a29a3b0000000017a914c99a494597ade09b5194f9ec8e02d96607ae64798700000000");

        $this->assertEquals($signer->get()->getTxId()->getHex(), "754162225c3f2b8ce476d1df7a0b35f04ba6fe24fd6c0fd89e31c5e54d4eaec1");
    }

    public function testTransactionP2WSHOverP2SHtoP2WPKH(): void {
        // test spending P2WSH-over-P2SH outputs and sending to P2WPKH address

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $p2shwrappedMultisig = new P2shScript(new WitnessScript($multisig));
        $from_address = $p2shwrappedMultisig->getAddress()->getAddress($this->network); // P2WSH-over-P2SH
            
        $to_address = "tltc1qk2erszs7fp407kh94e6v3yhfq2njczjvg4hnz6"; // P2WPKH                                                                                                                                     
        $prevTxId = "2464c6122378ee5ed9a42d5192e15713b107924d05d15b58254eb7b2030118c7"; // low-R. non-low-R is: 754162225c3f2b8ce476d1df7a0b35f04ba6fe24fd6c0fd89e31c5e54d4eaec1
        $prevOutputNo = 0;
        $prevOutputValue = 1000000000 - $this->networkFee; // it's spending the previous output, which was 10 LTC minus $networkFee
        $outputValue = $prevOutputValue - $this->networkFee;

        $outpoint = new OutPoint(Buffer::hex($prevTxId), 0);
        $txOut = new TransactionOutput($prevOutputValue, (new AddressCreator())->fromString($from_address, $this->network)->getScriptPubKey());
 
        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())
                  ->get();

        $this->assertEquals($unsigned->getHex(), "0100000001c7180103b2b74e25585bd1054d9207b11357e192512da4d95eee782312c664240000000000ffffffff01e07b9a3b00000000160014b2b2380a1e486aff5ae5ae74c892e902a72c0a4c00000000");
        
        $signer = new Signer($unsigned);
        $signData = (new SignData())->p2sh($p2shwrappedMultisig)->p2wsh(new WitnessScript($multisig));
        $input = $signer->input(0, $txOut, $signData);
        $sigHash0 = $input->getSigHash(SigHash::ALL);

        $this->assertEquals($sigHash0->getHex(), "e1c684f769c0e186be215ece3b7c1f3f23985ecbafafe0c8d43936fcd79eafdc");

        // sign the transaction
        $input->sign($this->privkey1);
        $input->sign($this->privkey2);

        $this->assertEquals($signer->get()->getHex(), "01000000000101c7180103b2b74e25585bd1054d9207b11357e192512da4d95eee782312c664240000000023220020d42b8341140559b7da105e8669e8f7d5a03773642ad82403ba91b80ffcc415deffffffff01e07b9a3b00000000160014b2b2380a1e486aff5ae5ae74c892e902a72c0a4c0400473044022067c9f8ed5c8f0770be1b7d44ade72c4d976a2b0e6c4df39ea70923daff26ea5e02205894350de5304d446343fbf95245cd656876a11c94025554bf878b3ecf90db720147304402204ee76a1814b3eb289e492409bd29ebb77088c9c20645c8a63c75bfe44eac41f70220232bcd35a0cc78e88dfa59dc15331023c3d3bb3a8b63e6b753c8ab4599b7bd290147522103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e210238de8c9eb2842ecaf0cc61ee6ba23fe4e46f1cfd82eac0910e1d8e865bd76df952ae00000000");

        $this->assertEquals($signer->get()->getTxId()->getHex(), "66a78d3cda988e4c90611b192ae5bd02e0fa70c08c3219110c02594802a42c01");
    }

    public function testTransactionP2WPKHToWitnessV0(): void {
        // test spending P2WPKH outputs and sending to WITNESS_V0 address

        $pubkeyHash1 = $this->privkey1->getPublicKey()->getPubKeyHash();
        $p2wpkhWP = WitnessProgram::v0($pubkeyHash1);
        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $from_address = new SegwitAddress($p2wpkhWP); // p2wpkh         
        $to_address = (new WitnessScript($multisig))->getAddress()->getAddress($this->network); // p2wsh (witness_v0)

        $prevTxId = "66a78d3cda988e4c90611b192ae5bd02e0fa70c08c3219110c02594802a42c01"; // from testTransactionP2WSHOverP2SHtoP2WPKH
        $prevOutputNo = 0;
        $prevOutputValue = 1000000000 - $this->networkFee - $this->networkFee; // it's spending the previous output, which was 10 LTC minus 2x network fees

        $outputValue = $prevOutputValue - $this->networkFee;

        $outpoint = new OutPoint(Buffer::hex($prevTxId), 0);
        $txOut = new TransactionOutput($prevOutputValue, (new SegwitAddress($p2wpkhWP))->getScriptPubKey());

        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())
                  ->get();

        $this->assertEquals($unsigned->getHex(), "0100000001012ca4024859020c1119328cc070fae002bde52a191b61904c8e98da3c8da7660000000000ffffffff01d0549a3b00000000220020d42b8341140559b7da105e8669e8f7d5a03773642ad82403ba91b80ffcc415de00000000");

        $signer = new Signer($unsigned);
        $input = $signer->input(0, $txOut);

        $sigHash0 = $input->getSigHash(SigHash::ALL)->getHex();

        $this->assertEquals($sigHash0, "ff94560e1ca289de4d661695029f495dde37b16bddd6645fb65c8f61decec22c");

        // sign the transaction
        $input->sign($this->privkey1);
        $signed = $signer->get();

        // not using low R
        $this->assertEquals($signed->getHex(), "01000000000101012ca4024859020c1119328cc070fae002bde52a191b61904c8e98da3c8da7660000000000ffffffff01d0549a3b00000000220020d42b8341140559b7da105e8669e8f7d5a03773642ad82403ba91b80ffcc415de02483045022100c5db5e86122fd9609dda1f17a6dd3527074ef9b301fd23273b3940bfd8225e4e0220089b268c45437f5ac692be0d546971e84e7dc59c6ce309f5a10dd7cdcc4bb683012103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e00000000");

        $this->assertEquals($signed->getTxId()->getHex(), "d14891128bc4c72dfa45269f302edf690289214874c5ee40b118c1d5465319e6");

    }

    public function testTranasctionWitnessV0ToP2WPKHOverP2SH(): void {
        // test spending WITNESS_V0 outputs to P2WPKH-over-P2SH address

        $pubkeyHash1 = $this->privkey1->getPublicKey()->getPubKeyHash();
        $p2wpkhWP = WitnessProgram::v0($pubkeyHash1);
        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        

        $from_address = (new WitnessScript($multisig))->getAddress()->getAddress($this->network); // p2wsh (witness_v0)
        $to_address = (new ScriptHashAddress($p2wpkhWP->getScript()->getScriptHash()))->getAddress($this->network);
        
        $prevTxId = "d14891128bc4c72dfa45269f302edf690289214874c5ee40b118c1d5465319e6"; 
        $prevOutputNo = 0;
        $prevOutputValue = 1000000000 - $this->networkFee - $this->networkFee - $this->networkFee; // spending previous output

        $outputValue = $prevOutputValue - $this->networkFee;

        $outpoint = new OutPoint(Buffer::hex($prevTxId), 0);
        $txOut = new TransactionOutput($prevOutputValue, (new WitnessScript($multisig))->getAddress()->getScriptPubKey());

        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())
                  ->get();

        $this->assertEquals($unsigned->getHex(), "0100000001e6195346d5c118b140eec5744821890269df2e309f2645fa2dc7c48b129148d10000000000ffffffff01c02d9a3b0000000017a914dd4edd1406541e476450fda7924720fe19f337b98700000000");

        $signer = new Signer($unsigned);
        $signData = (new SignData())->p2wsh(new WitnessScript($multisig));
        $input = $signer->input(0, $txOut, $signData);
        $sigHash0 = $input->getSigHash(SigHash::ALL)->getHex();

        $this->assertEquals($sigHash0, "bd77fd23a1e80c3670d7a547ce45031f5f611e4dc49a2eb65def2e6db841e011");

        // sign the transaction
        $input->sign($this->privkey1);
        $input->sign($this->privkey2);

        // again, not using low Rs so the payload does not match Ruby lib
        $this->assertEquals($signer->get()->getHex(), "01000000000101e6195346d5c118b140eec5744821890269df2e309f2645fa2dc7c48b129148d10000000000ffffffff01c02d9a3b0000000017a914dd4edd1406541e476450fda7924720fe19f337b9870400483045022100b6b658f7d3d592645cdc7ca21d45504ffde7d9b2ef22e97b7b57c507e952b006022059631267d3fcdfb06a4efdf940dabaf022e051bda9d93de2ef400e94ea2b39be01473044022033d8136791bc5658700b385ca5728b9e188a3ba1aa3bc691d6adfd1b8431cee6022073d565e5d1e96c0257f7cefdab946e48fb3857248f49048e00f6b701e97457c30147522103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e210238de8c9eb2842ecaf0cc61ee6ba23fe4e46f1cfd82eac0910e1d8e865bd76df952ae00000000");

        $this->assertEquals($signer->get()->getTxId()->getHex(), "d76dd93d5afbc8cb3bfd487445fac9f81d7ae409723990f7744f398feae9c0e4");
        
    }

    public function testTransactionP2WPKHOverP2SHToP2PKH(): void {
        // test spending P2WPKH-over-P2SH outputs to P2PKH address

        $pubkeyHash1 = $this->privkey1->getPublicKey()->getPubKeyHash();
        $p2wpkhWP = WitnessProgram::v0($pubkeyHash1);

        $from_address = (new ScriptHashAddress($p2wpkhWP->getScript()->getScriptHash()))->getAddress($this->network);
        $to_address = (new PayToPubKeyHashAddress($pubkeyHash1))->getAddress($this->network);

        $prevTxId = "d76dd93d5afbc8cb3bfd487445fac9f81d7ae409723990f7744f398feae9c0e4";
        $prevOutputNo = 0;
        $prevOutputValue = 1000000000 - $this->networkFee - $this->networkFee - $this->networkFee - $this->networkFee; // spending previous output

        $outputValue = $prevOutputValue - $this->networkFee;

        $outpoint = new OutPoint(Buffer::hex($prevTxId), 0);
        $txOut = new TransactionOutput($prevOutputValue, (new AddressCreator())->fromString($from_address, $this->network)->getScriptPubKey());

        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())
                  ->get();

        $this->assertEquals($unsigned->getHex(), "0100000001e4c0e9ea8f394f74f790397209e47a1df8c9fa457448fd3bcbc8fb5a3dd96dd70000000000ffffffff01b0069a3b000000001976a914b2b2380a1e486aff5ae5ae74c892e902a72c0a4c88ac00000000");

        $signer = new Signer($unsigned);
        $signData = (new SignData())->p2sh(ScriptFactory::scriptPubKey()->p2wkh($this->privkey1->getPubKeyHash()));
        $input = $signer->input(0, $txOut, $signData);
        $sigHash0 = $input->getSigHash(SigHash::ALL)->getHex();

        $this->assertEquals($sigHash0, "59e2322a152dbad2c283232bd098a55c61bc0cd324dfd85311a0a9e73053d46b");

        // sign the transaction
        $input->sign($this->privkey1);

        $this->assertEquals($signer->get()->getHex(), "01000000000101e4c0e9ea8f394f74f790397209e47a1df8c9fa457448fd3bcbc8fb5a3dd96dd70000000017160014b2b2380a1e486aff5ae5ae74c892e902a72c0a4cffffffff01b0069a3b000000001976a914b2b2380a1e486aff5ae5ae74c892e902a72c0a4c88ac02473044022067efbe904404b388bf11cf8af610f2efa95ac943a67071c3c5fe0332286d672e02205f3917d8967d7f32fb65c0808c6c0de7dda8a080bf92f80c1ee13d33757fd1df012103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38e00000000");

        $this->assertEquals($signer->get()->getTxId()->getHex(), "74b178c39268acd0663c88d3a56665b2f5335b60711445a5f8cd8aa59c2c7d38");
        
    }

    public function testTransactionP2PKHToP2SH(): void {
        // test spending P2PKH outputs to P2SH address

        $pubkeyHash1 = $this->privkey1->getPublicKey()->getPubKeyHash();
        $multisig = ScriptFactory::scriptPubKey()->multisig(2, [$this->privkey1->getPublicKey(), $this->privkey2->getPublicKey()], false); // don't sort
        $from_address = (new PayToPubKeyHashAddress($pubkeyHash1))->getAddress($this->network); // P2PKH
        $to_address = (new P2shScript($multisig))->getAddress()->getAddress($this->network); // P2SH

        $prevTxId = "74b178c39268acd0663c88d3a56665b2f5335b60711445a5f8cd8aa59c2c7d38";
        $prevOutputNo = 0;
        $prevOutputValue = 1000000000 - $this->networkFee - $this->networkFee - $this->networkFee - $this->networkFee - $this->networkFee; // spending previous output

        $outputValue = $prevOutputValue - $this->networkFee;

        $outpoint = new OutPoint(Buffer::hex($prevTxId), 0);
        $txOut = new TransactionOutput($prevOutputValue, (new AddressCreator())->fromString($from_address, $this->network)->getScriptPubKey());

        $unsigned = (new TxBuilder())
                  ->spendOutPoint($outpoint)
                  ->output($outputValue, (new AddressCreator())->fromString($to_address, $this->network)->getScriptPubKey())
                  ->get();

        $this->assertEquals($unsigned->getHex(), "0100000001387d2c9ca58acdf8a5451471605b33f5b26566a5d3883c66d0ac6892c378b1740000000000ffffffff01a0df993b0000000017a9142069605a7742286aef950b68ae7818f7294e876c8700000000");

        $signer = new Signer($unsigned);
        $input = $signer->input(0, $txOut);
        $sigHash0 = $input->getSigHash(SigHash::ALL)->getHex();

        $this->assertEquals($sigHash0, "ae52a447200543a0e5a5ca8de0bad10eebb411748d137f7b2fba380b98ea6651");

        // sign the transaction
        $input->sign($this->privkey1);

        $this->assertEquals($signer->get()->getHex(), "0100000001387d2c9ca58acdf8a5451471605b33f5b26566a5d3883c66d0ac6892c378b174000000006b483045022100ff4e36c565f31768ffba7eebdb7c9e0384cfcac1fa6a91d11cbded10873100b002206de5452115e1cc7a3baecd786a04b5f0b533b7b6cee5b05f4cc60f92bc993e7a012103820317ad251bca573c8fda2b8f26ffc9aae9d5ecb15b50ee08d8f9e009def38effffffff01a0df993b0000000017a9142069605a7742286aef950b68ae7818f7294e876c8700000000");

        $this->assertEquals($signer->get()->getTxId()->getHex(), "64e1ad990d0e6a50485f39ebb9e98db96cba420bd1eaca6350543f2385e54e62");
        
    }
    
}
?>
