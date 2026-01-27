<?php

namespace App\Controller;

use App\Entity\Relic;
use App\Enum\RelicStatus;
use App\Repository\RelicRepository;
use App\Service\RelicWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/relic/workflow')]
final class RelicWorkflowController extends AbstractController
{
    #[Route('/pending', name: 'app_pending_relics', methods: ['GET'])]
    public function pendingRelics(RelicRepository $relicRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('relic/pending.html.twig', [
            'relics' => $relicRepository->findByStatus(RelicStatus::PENDING),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_relic_approve', methods: ['GET', 'POST'])]
    public function approve(
        Request $request, 
        Relic $relic, 
        EntityManagerInterface $entityManager,
        RelicWorkflowService $workflowService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('approve'.$relic->getId(), $request->getPayload()->getString('_token'))) {
                try {
                    $workflowService->approve($relic);
                    $entityManager->flush();
                    $this->addFlash('success', 'common.relic.approve.success');
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
            return $this->redirectToRoute('app_pending_relics', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('relic/approve.html.twig', [
            'relic' => $relic,
            'can_approve' => $workflowService->canApprove($relic),
            'can_reject' => $workflowService->canReject($relic),
        ]);
    }

    #[Route('/{id}/reject', name: 'app_relic_reject', methods: ['POST'])]
    public function reject(
        Request $request, 
        Relic $relic, 
        EntityManagerInterface $entityManager,
        RelicWorkflowService $workflowService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('reject'.$relic->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $reason = $request->getPayload()->getString('rejection_reason');
                $workflowService->reject($relic, $reason);
                $entityManager->flush();
                $this->addFlash('success', 'common.relic.reject.success');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_pending_relics', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/resubmit', name: 'app_relic_resubmit', methods: ['GET', 'POST'])]
    public function resubmit(
        Request $request, 
        Relic $relic, 
        EntityManagerInterface $entityManager,
        RelicWorkflowService $workflowService
    ): Response
    {
        // Check if the current user is the creator of the relic
        $currentUser = $this->getUser();
        if (!$currentUser || $relic->getCreator()->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Only the creator of the relic can resubmit it.');
        }
        
        // Check if the workflow allows resubmission
        if (!$workflowService->canResubmit($relic)) {
            $this->addFlash('error', 'This relic cannot be resubmitted');
            return $this->redirectToRoute('app_relic_show', ['id' => $relic->getId()], Response::HTTP_SEE_OTHER);
        }

        // Create form for editing the relic
        $form = $this->createForm(\App\Form\RelicType::class, $relic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload if provided
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageService = $this->container->get(\App\Service\ImageService::class);
                $image = $imageService->createRelicImage($imageFile, $relic, $this->getUser());
                $relic->addImage($image);
            }
            
            // Apply the workflow transition
            try {
                $workflowService->resubmit($relic);
                $entityManager->flush();
                $this->addFlash('success', 'common.relic.resubmit.success');
                return $this->redirectToRoute('app_relic_index', [], Response::HTTP_SEE_OTHER);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Display the form for GET requests or if form submission failed
        return $this->render('relic/resubmit.html.twig', [
            'relic' => $relic,
            'form' => $form,
        ]);
    }
}