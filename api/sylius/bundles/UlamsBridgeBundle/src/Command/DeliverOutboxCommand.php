<?php

declare(strict_types=1);

namespace Ulams\Bridge\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ulams\Bridge\Entity\ChannelLink;
use Ulams\Bridge\Entity\OutboxEvent;
use Ulams\Bridge\Repository\OutboxEventRepository;
use Ulams\Bridge\Service\SecretBox;
use Ulams\Bridge\Service\WebhookSigner;

/**
 * Delivers outbox events to the LMS: one signed POST per event to the URL of the channel's link, retried with
 * exponential backoff for 72 hours (the feed covers anything later). A 2xx answer (202 stored, 200 duplicate) marks it
 * delivered. Runs as the `sylius-worker` service with `--watch`; `--once` drains what is due and exits.
 */
#[AsCommand(name: 'ulams:outbox:deliver', description: 'Deliver outbox events to the LMS (signed webhooks)')]
final class DeliverOutboxCommand extends Command
{
    public function __construct(
        private readonly OutboxEventRepository $outbox,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        private readonly SecretBox $secrets,
        private readonly WebhookSigner $signer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_NONE, 'Keep running and poll for new events')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds between polls with --watch', '3')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Events per pass', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        do {
            $delivered = $this->pass((int) $input->getOption('limit'), $output);
            if ($delivered > 0) {
                $output->writeln("Delivered {$delivered} event(s).");
            }
            if ($input->getOption('watch')) {
                sleep(max(1, (int) $input->getOption('sleep')));
                $this->em->clear();
            }
        } while ($input->getOption('watch'));

        return Command::SUCCESS;
    }

    public function pass(int $limit, ?OutputInterface $output = null): int
    {
        $delivered = 0;
        $links = [];
        foreach ($this->outbox->due($limit) as $event) {
            $links[$event->getChannelCode()] ??= $this->em->find(ChannelLink::class, $event->getChannelCode());
            $link = $links[$event->getChannelCode()];
            if ($link === null) {
                $event->markFailed('No channel link: the LMS has not provisioned this channel.');
                continue;
            }
            $delivered += $this->deliver($event, $link) ? 1 : 0;
        }
        $this->em->flush();

        return $delivered;
    }

    private function deliver(OutboxEvent $event, ChannelLink $link): bool
    {
        $body = json_encode($event->getPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        try {
            $response = $this->http->request('POST', $link->getWebhookUrl(), [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Ulams-Event-Id' => $event->getId(),
                    'Ulams-Signature' => $this->signer->sign($body, $this->secrets->open($link->getSealedSecret())),
                ],
                'timeout' => 10,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
        } catch (\Throwable $e) {
            $event->markFailed($e::class . ': ' . $e->getMessage());

            return false;
        }
        if ($status >= 200 && $status < 300) {
            $event->markDelivered();

            return true;
        }
        $event->markFailed("The LMS answered HTTP {$status}.");

        return false;
    }
}
