<?php

namespace App\EventSubscriber;

use App\Entity\Relic;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Contracts\Translation\TranslatorInterface;

class RelicWorkflowSubscriber implements EventSubscriberInterface
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $fromAddress;
    private Environment $twig;
    private TranslatorInterface $translator;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger, string $fromAddress, Environment $twig, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->fromAddress = $fromAddress;
        $this->twig = $twig;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.relic_approval.completed.approve' => 'onRelicApproved',
            'workflow.relic_approval.completed.reject' => 'onRelicRejected',
        ];
    }

    public function onRelicApproved(Event $event): void
    {
        /** @var Relic $relic */
        $relic = $event->getSubject();
        $creatorEmail = $relic->getCreator()?->getEmail();
        if (!$creatorEmail) {
            $this->logger->warning('Relic creator email missing; skipping approval email', [
                'relicId' => $relic->getId(),
            ]);
            return;
        }

        $subject = $this->translator->trans('email.relic.approved.subject');
        $body = $this->twig->render('emails/relic_approved.html.twig', [
            'relic' => $relic,
            'userName' => $relic->getCreator()?->getUsername() ?? null,
        ]);
        $this->sendEmail($creatorEmail, $subject, $body, 'approval');
    }

    public function onRelicRejected(Event $event): void
    {
        /** @var Relic $relic */
        $relic = $event->getSubject();
        $creatorEmail = $relic->getCreator()?->getEmail();
        if (!$creatorEmail) {
            $this->logger->warning('Relic creator email missing; skipping rejection email', [
                'relicId' => $relic->getId(),
            ]);
            return;
        }

        $subject = $this->translator->trans('email.relic.rejected.subject');
        $body = $this->twig->render('emails/relic_rejected.html.twig', [
            'relic' => $relic,
            'userName' => $relic->getCreator()?->getUsername() ?? null,
        ]);
        $this->sendEmail($creatorEmail, $subject, $body, 'rejection', [
            'relicId' => $relic->getId(),
        ]);
    }

    private function sendEmail(string $to, string $subject, string $htmlBody, string $context, array $extraContext = []): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning(sprintf('Failed to send %s email', $context), array_merge([
                'to' => $to,
                'error' => $e->getMessage(),
            ], $extraContext));
        }
    }
}