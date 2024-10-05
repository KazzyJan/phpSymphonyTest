<?php

namespace App\Command;

use App\Repository\UrlRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SendNewUrlsCommand extends Command
{
    protected static $defaultName = 'app:send-new-urls';
    protected static $defaultDescription = 'Add a short description for your command';
    private $urlRepository;
    private $params;
    private $lastSentFile = 'var/last_sent.txt';

    public function __construct(UrlRepository $urlRepository, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->urlRepository = $urlRepository;
        $this->params = $params;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
            ->setDescription('Send new URLs to the configured API endpoint.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lastSentTime = $this->getLastSentTime();

        $newUrls = $this->urlRepository->findUrlsCreatedAfter($lastSentTime);

        if (empty($newUrls)) {
            $io->success('No new URLs to send.');
            return Command::SUCCESS;
        }

        $data = [];
        foreach ($newUrls as $url) {
            $data[] = [
                'url' => $url->getUrl(),
                'createdAt' => $url->getCreatedDate()->format('Y-m-d H:i:s')
            ];
        }

        $apiEndpoint = $this->params->get('api_endpoint');
        $jsonData = json_encode($data);

        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);


        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode === 200) {
            $this->setLastSentTime();
            $io->success('URLs have been sent successfully.');

            $io->writeln('Response from API: ' . $response);
        } else {
            $io->error('Failed to send URLs. HTTP status code: ' . $httpCode);
        }

        return Command::SUCCESS;
    }

    private function getLastSentTime(): \DateTimeImmutable
    {
        if (!file_exists($this->lastSentFile)) {
            return new \DateTimeImmutable('@0');
        }
        $timestamp = file_get_contents($this->lastSentFile);
        return new \DateTimeImmutable('@' . $timestamp);
    }
    private function setLastSentTime()
    {
        $currentTime = new \DateTime('now', new \DateTimeZone('Europe/Moscow'));

        $currentTime->modify('+3 hours');
        //Что то со временем на сервеере, не успел до конца разобраться, но в sql и php разное время. Тк не успел, так оставил.

        file_put_contents($this->lastSentFile, $currentTime->getTimestamp());
    }
}
