<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contacts')]
#[IsGranted('ROLE_ADMIN')]
class AdminContactController extends AbstractController
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
    ) {
    }

    #[Route('', name: 'admin_contact_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $status = $request->query->get('status');

        if ($status && in_array($status, ['new', 'read', 'resolved'])) {
            $query = $this->contactRepository->findByStatusQuery($status);
        } else {
            $query = $this->contactRepository->findAllOrderedQuery();
        }

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            15
        );

        $statistics = $this->contactRepository->getStatistics();

        return $this->render('admin/contact/index.html.twig', [
            'pagination' => $pagination,
            'statistics' => $statistics,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/{id}', name: 'admin_contact_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        // Mark as read when viewed
        $contact->markAsRead();
        $this->contactRepository->save($contact, true);

        return $this->render('admin/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_contact_status', methods: ['POST'])]
    public function updateStatus(Request $request, Contact $contact): Response
    {
        $status = $request->request->get('status');

        if (in_array($status, ['new', 'read', 'resolved'])) {
            $contact->setStatus($status);
            $this->contactRepository->save($contact, true);

            $this->addFlash('success', 'Status updated successfully.');
        }

        return $this->redirectToRoute('admin_contact_show', ['id' => $contact->getId()]);
    }

    #[Route('/{id}/notes', name: 'admin_contact_notes', methods: ['POST'])]
    public function updateNotes(Request $request, Contact $contact): Response
    {
        $notes = $request->request->get('notes');

        $contact->setAdminNotes($notes);
        $this->contactRepository->save($contact, true);

        $this->addFlash('success', 'Notes updated successfully.');

        return $this->redirectToRoute('admin_contact_show', ['id' => $contact->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_contact_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact): Response
    {
        if ($this->isCsrfTokenValid('delete'.$contact->getId(), $request->request->get('_token'))) {
            $this->contactRepository->remove($contact, true);
            $this->addFlash('success', 'Contact message deleted successfully.');
        }

        return $this->redirectToRoute('admin_contact_index');
    }
}
