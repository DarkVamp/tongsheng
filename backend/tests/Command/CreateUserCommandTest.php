<?php

namespace App\Tests\Command;

use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateUserCommandTest extends ApiTestCase
{
    public function testCreateTeacherUser(): void
    {
        $app = new Application(static::$kernel);
        $command = $app->find('app:user:create');
        $tester = new CommandTester($command);

        $tester->setInputs([
            'newteacher@test.com',
            'Test Teacher',
            'teacher',
            'securepassword',
        ]);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('newteacher@test.com', $tester->getDisplay());

        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'newteacher@test.com']);
        self::assertNotNull($user);
        self::assertSame('teacher', $user->getRole());
        self::assertSame('Test Teacher', $user->getFamilyName());
    }

    public function testCreateFamilyMemberUser(): void
    {
        $app = new Application(static::$kernel);
        $command = $app->find('app:user:create');
        $tester = new CommandTester($command);

        $tester->setInputs([
            'newmember@test.com',
            'Test Member',
            'family',
            'memberpass',
        ]);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'newmember@test.com']);
        self::assertNotNull($user);
        self::assertSame('family', $user->getRole());
    }
}
