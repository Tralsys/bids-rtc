<?php

declare(strict_types=1);

namespace BidsRtc\Backend\Tests\Integration\Repository;

use BidsRtc\Backend\Model\DbSdpAnswer;
use BidsRtc\Backend\Model\DbSdpRecord;
use BidsRtc\Backend\Repository\SdpTableRepository;
use BidsRtc\Backend\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Integration tests for SdpTableRepository.
 */
class SdpTableRepositoryTest extends IntegrationTestCase
{
    private const TEST_USER_ID = 'test-user-id';
    private const TEST_ROLE    = 'provider';

    private function hashedUserId(): string
    {
        return hash('sha256', self::TEST_USER_ID);
    }

    private function makeRepo(): SdpTableRepository
    {
        return new SdpTableRepository(self::$pdo);
    }

    // -------------------------------------------------------------------------
    // Test 1: insertOffer + selectOne returns the inserted DbSdpRecord
    // -------------------------------------------------------------------------

    public function testInsertAndSelectOne(): void
    {
        $repo           = $this->makeRepo();
        $offerClientId  = Uuid::uuid7();
        $hashedUserId   = $this->hashedUserId();
        $protectedOffer = 'encrypted-offer-bytes';

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, $protectedOffer);
        $this->assertNotNull($sdpId);

        $record = $repo->selectOne($sdpId, $hashedUserId, $offerClientId);
        $this->assertNotNull($record);
        $this->assertInstanceOf(DbSdpRecord::class, $record);
        $this->assertSame($sdpId->toString(), $record->sdp_id->toString());
        $this->assertSame($offerClientId->toString(), $record->offer_client_id->toString());
        $this->assertSame(self::TEST_ROLE, $record->role);
        $this->assertSame($hashedUserId, $record->user_id_hash);
        $this->assertSame($protectedOffer, $record->protected_offer);
        $this->assertNull($record->answer_client_id);
    }

    // -------------------------------------------------------------------------
    // Test 2: insertOffer returns a UuidInterface
    // -------------------------------------------------------------------------

    public function testInsertReturnsUuid(): void
    {
        $repo          = $this->makeRepo();
        $offerClientId = Uuid::uuid7();
        $hashedUserId  = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-data');
        $this->assertInstanceOf(UuidInterface::class, $sdpId);
    }

    // -------------------------------------------------------------------------
    // Test 3: selectOne with an unknown sdpId returns null
    // -------------------------------------------------------------------------

    public function testSelectOneNotFound(): void
    {
        $repo          = $this->makeRepo();
        $hashedUserId  = $this->hashedUserId();
        $unknownSdpId  = Uuid::uuid7();
        $offerClientId = Uuid::uuid7();

        $result = $repo->selectOne($unknownSdpId, $hashedUserId, $offerClientId);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 4: getAnswer before any answer is set returns null
    // -------------------------------------------------------------------------

    public function testGetAnswerBeforeSet(): void
    {
        $repo          = $this->makeRepo();
        $offerClientId = Uuid::uuid7();
        $hashedUserId  = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $answer = $repo->getAnswer($sdpId, $hashedUserId, $offerClientId);
        $this->assertNull($answer);
    }

    // -------------------------------------------------------------------------
    // Test 5: setAnswer + getAnswer returns DbSdpAnswer with matching answer bytes
    // -------------------------------------------------------------------------

    public function testSetAndGetAnswer(): void
    {
        $repo            = $this->makeRepo();
        $offerClientId   = Uuid::uuid7();
        $answerClientId  = Uuid::uuid7();
        $hashedUserId    = $this->hashedUserId();
        $protectedAnswer = 'encrypted-answer-bytes';

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        // Set answer_client_id first (required by setAnswer's WHERE clause)
        $updated = $repo->setOfferAsProcessing(
            $hashedUserId,
            self::TEST_ROLE,
            $answerClientId,
            [],
            60,
        );
        $this->assertSame(1, $updated);

        $setRowCount = $repo->setAnswer($hashedUserId, $sdpId, $answerClientId, $protectedAnswer);
        $this->assertSame(1, $setRowCount);

        $answer = $repo->getAnswer($sdpId, $hashedUserId, $offerClientId);
        $this->assertNotNull($answer);
        $this->assertInstanceOf(DbSdpAnswer::class, $answer);
        $this->assertSame($sdpId->toString(), $answer->sdp_id->toString());
        $this->assertSame($answerClientId->toString(), $answer->answer_client_id->toString());
        $this->assertSame($protectedAnswer, $answer->protected_answer);
    }

    // -------------------------------------------------------------------------
    // Test 6: setOfferAsProcessing updates the matching offer and returns 1
    // -------------------------------------------------------------------------

    public function testSetOfferAsProcessing(): void
    {
        $repo           = $this->makeRepo();
        $offerClientId  = Uuid::uuid7();
        $answerClientId = Uuid::uuid7();
        $hashedUserId   = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $rowCount = $repo->setOfferAsProcessing(
            $hashedUserId,
            self::TEST_ROLE,
            $answerClientId,
            [],
            60,
        );
        $this->assertSame(1, $rowCount);
    }

    // -------------------------------------------------------------------------
    // Test 7: unsetOfferAsProcessing clears answer_client_id and returns 1
    // -------------------------------------------------------------------------

    public function testUnsetOfferAsProcessing(): void
    {
        $repo           = $this->makeRepo();
        $offerClientId  = Uuid::uuid7();
        $answerClientId = Uuid::uuid7();
        $hashedUserId   = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $repo->setOfferAsProcessing($hashedUserId, self::TEST_ROLE, $answerClientId, [], 60);

        $rowCount = $repo->unsetOfferAsProcessing($hashedUserId, $answerClientId);
        $this->assertSame(1, $rowCount);

        // Verify answer_client_id is null again
        $record = $repo->selectOne($sdpId, $hashedUserId, $offerClientId);
        $this->assertNotNull($record);
        $this->assertNull($record->answer_client_id);
    }

    // -------------------------------------------------------------------------
    // Test 8: getOfferListWithAnswerId returns the record after setOfferAsProcessing
    // -------------------------------------------------------------------------

    public function testGetOfferListWithAnswerId(): void
    {
        $repo           = $this->makeRepo();
        $offerClientId  = Uuid::uuid7();
        $answerClientId = Uuid::uuid7();
        $hashedUserId   = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $repo->setOfferAsProcessing($hashedUserId, self::TEST_ROLE, $answerClientId, [], 60);

        $offerList = $repo->getOfferListWithAnswerId($hashedUserId, $answerClientId);
        $this->assertCount(1, $offerList);
        $this->assertSame($sdpId->toString(), $offerList[0]->sdp_id->toString());
        $this->assertSame($answerClientId->toString(), $offerList[0]->answer_client_id->toString());
    }

    // -------------------------------------------------------------------------
    // Test 9: deleteRecord soft-deletes the record; selectOne returns null; rowCount = 1
    // -------------------------------------------------------------------------

    public function testDeleteRecord(): void
    {
        $repo          = $this->makeRepo();
        $offerClientId = Uuid::uuid7();
        $hashedUserId  = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $rowCount = $repo->deleteRecord($hashedUserId, $sdpId, $offerClientId);
        $this->assertSame(1, $rowCount);

        $result = $repo->selectOne($sdpId, $hashedUserId, $offerClientId);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 10: deleteRecordByClientId soft-deletes all records for the client
    // -------------------------------------------------------------------------

    public function testDeleteRecordByClientId(): void
    {
        $repo          = $this->makeRepo();
        $offerClientId = Uuid::uuid7();
        $hashedUserId  = $this->hashedUserId();

        $sdpId = $repo->insertOffer($hashedUserId, $offerClientId, self::TEST_ROLE, 'offer-bytes');
        $this->assertNotNull($sdpId);

        $rowCount = $repo->deleteRecordByClientId($hashedUserId, $offerClientId);
        $this->assertGreaterThanOrEqual(1, $rowCount);

        $result = $repo->selectOne($sdpId, $hashedUserId, $offerClientId);
        $this->assertNull($result);
    }
}
