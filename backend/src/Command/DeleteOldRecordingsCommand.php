<?php

namespace App\Command;

use App\Repository\RecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:recordings:delete-expired', description: 'Delete recordings past their retention date')]
class DeleteOldRecordingsCommand extends Command
{
    public function __construct(
        private RecordingRepository $recordingRepository,
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/var/recordings')]
        private string $recordingsDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expired = $this->recordingRepository->findExpired();
        $deleted = 0;

        foreach ($expired as $recording) {
            $path = $this->recordingsDir . '/' . $recording->getFilename();
            if (file_exists($path)) {
                unlink($path);
            }
            $this->em->remove($recording);
            $deleted++;
        }

        $this->em->flush();
        $output->writeln("Deleted $deleted expired recording(s).");

        return Command::SUCCESS;
    }
}
