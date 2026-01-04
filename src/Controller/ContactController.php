<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for the contact page
 */
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly ContactRepository $contactRepository,
        private readonly string $adminEmail,
        private readonly string $noReplyEmail,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setIpAddress($request->getClientIp());

            $this->contactRepository->save($contact, true);

            try {
                // Send email to admin
                $email = (new TemplatedEmail())
                    ->from($this->noReplyEmail)
                    ->to($this->adminEmail)
                    ->replyTo($contact->getEmail())
                    ->subject(sprintf('[Contact Form] %s - %s', $contact->getSubject(), $contact->getName()))
                    ->htmlTemplate('emails/contact.html.twig')
                    ->context([
                        'name' => $contact->getName(),
                        'contactEmail' => $contact->getEmail(),
                        'subject' => $contact->getSubject(),
                        'contactMessage' => $contact->getMessage(),
                        'contactId' => $contact->getId(),
                    ]);

                $this->mailer->send($email);

                // Send confirmation email to user
                $confirmationEmail = (new TemplatedEmail())
                    ->from($this->noReplyEmail)
                    ->to($contact->getEmail())
                    ->subject($this->translator->trans('contact.confirmation.subject', [], 'landing'))
                    ->htmlTemplate('emails/contact_confirmation.html.twig')
                    ->context([
                        'name' => $contact->getName(),
                    ]);

                $this->mailer->send($confirmationEmail);

                $this->addFlash('success', $this->translator->trans('contact.form.success', [], 'landing'));

                // Redirect to prevent form resubmission
                return $this->redirectToRoute('app_contact');
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Failed to send contact form email', [
                    'error' => $e->getMessage(),
                    'email' => $contact->getEmail(),
                ]);

                $this->addFlash('error', $this->translator->trans('contact.form.error', [], 'landing'));
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}