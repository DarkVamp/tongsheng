<?php

namespace App\Tests\Command;

use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteOldRecordingsCommandTest extends ApiTestCase
{
    private function runCommand(): CommandTester
    {
        $app = new Application(static::$kernel);
        $command = $app->find('app:recordings:delete-expired');
        $tester = new CommandTester($command);
        $tester->execute([]);
        return $tester;
    }

    public function testNoExpiredRecordings(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_cmd_noexp');

        // Recording with future deleteAt — should NOT be deleted
        $this->createRecording($member, new \DateTimeImmutable('+1 week'));

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 0', $tester->getDisplay());
    }

    public function testDeletesExpiredRecordingWithFile(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_cmd_exp_file');
        $rec = $this->createRecording($member, new \DateTimeImmutable('-1 day'));
        $recId = $rec->getId(); // save before command removes the entity

        // Create actual file
        $userDir = self::TEST_RECORDINGS_DIR . '/' . $member->getId();
        mkdir($userDir, 0755, true);
        $filePath = $userDir . '/' . $rec->getFilename();
        file_put_contents($filePath, 'audio data');

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 1', $tester->getDisplay());
        self::assertFileDoesNotExist($filePath);

        // Verify DB entry removed
        $this->em->clear();
        $found = $this->em->find(\App\Entity\Recording::class, $recId);
        self::assertNull($found);
    }

    public function testDeletesExpiredRecordingWithoutFile(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_cmd_exp_nofile');
        $rec = $this->createRecording($member, new \DateTimeImmutable('-1 day'));
        // No file on disk

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 1', $tester->getDisplay());
    }

    public function testRecordingWithNullDeleteAtIsNotDeleted(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_cmd_null_delete');
        $this->createRecording($member, null); // no deleteAt

        $tester = $this->runCommand();
        self::assertStringContainsString('Deleted 0', $tester->getDisplay());
    }
}
