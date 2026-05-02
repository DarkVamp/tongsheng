<?php

namespace App\Tests;

use App\Entity\Comment;
use App\Entity\Family;
use App\Entity\Recording;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected const TEST_RECORDINGS_DIR = '/tmp/tongsheng_test_recordings';

    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        if (!self::$schemaCreated) {
            $conn = $this->em->getConnection();
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($conn->createSchemaManager()->listTableNames() as $table) {
                $conn->executeStatement("DROP TABLE `$table`");
            }
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
            self::$schemaCreated = true;
        }

        $this->truncateAllTables();

        @mkdir(self::TEST_RECORDINGS_DIR, 0755, true);
    }

    private function truncateAllTables(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['comment_reactions', 'comments', 'recordings', 'attendance', 'lessons', 'invitations', 'users', 'families'] as $table) {
            $conn->executeStatement("TRUNCATE TABLE `$table`");
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        if (is_dir(self::TEST_RECORDINGS_DIR)) {
            $this->rmdirRecursive(self::TEST_RECORDINGS_DIR);
        }
        parent::tearDown();
    }

    private function rmdirRecursive(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = "$dir/$entry";
            is_dir($path) ? $this->rmdirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function hasher(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    protected function createTeacher(string $email = 'teacher@test.com', string $token = 'tok_teacher'): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setFamilyName('Lehrerin')
            ->setRole('teacher')
            ->setApiToken($token);
        $user->setPassword($this->hasher()->hashPassword($user, 'secret123'));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    protected function createFamily(string $name = 'TestFamilie'): Family
    {
        $family = new Family();
        $family->setName($name);
        $this->em->persist($family);
        $this->em->flush();
        return $family;
    }

    protected function createMember(
        Family $family,
        string $email = 'member@test.com',
        string $token = 'tok_member',
        string $password = 'secret123'
    ): User {
        $user = new User();
        $user->setEmail($email)
            ->setFamilyName('Mitglied')
            ->setRole('family_member')
            ->setFamily($family)
            ->setApiToken($token);
        $user->setPassword($this->hasher()->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    protected function createRecording(User $user, ?\DateTimeImmutable $deleteAt = null): Recording
    {
        $rec = new Recording();
        $rec->setUser($user)
            ->setFilename('test.webm')
            ->setMimeType('audio/webm')
            ->setFileSize(1000)
            ->setDeleteAt($deleteAt);
        $this->em->persist($rec);
        $this->em->flush();
        return $rec;
    }

    protected function createRecordingFile(int $userId, string $filename, string $content = 'fake audio'): void
    {
        $dir = self::TEST_RECORDINGS_DIR . '/' . $userId;
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $filename, $content);
    }

    protected function addComment(Recording $recording, User $author, string $content = 'Test comment'): Comment
    {
        $comment = new Comment();
        $comment->setRecording($recording)->setContent($content)->setAuthor($author);
        $this->em->persist($comment);
        $this->em->flush();
        return $comment;
    }

    protected function req(string $method, string $uri, array $json = [], string $token = '', array $files = []): void
    {
        $this->em->clear(); // flush EM identity map so HTTP request reads fresh from DB
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== '') {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request(
            $method, $uri, [], $files, $server,
            ($json && !$files) ? json_encode($json) : null
        );
    }

    protected function httpStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}
