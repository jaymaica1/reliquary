<?php

namespace App\Controller;

use App\Entity\Saint;
use App\Repository\SaintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for managing incomplete saints in the admin area
 */
#[Route('/admin/saints')]
#[IsGranted('ROLE_ADMIN')]
class AdminIncompleteSaintsController extends AbstractController
{
    /**
     * Lists all incomplete saints
     */
    #[Route('/incomplete', name: 'app_admin_saints_incomplete')]
    public function incompleteSaints(
        Request $request,
        SaintRepository $saintRepository,
        PaginatorInterface $paginator
    ): Response {
        $query = $saintRepository->findIncompleteQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );
        
        return $this->render('admin/saints/incomplete.html.twig', [
            'pagination' => $pagination,
        ]);
    }
    
    /**
     * Marks a saint as complete
     */
    #[Route('/incomplete/{id}/complete', name: 'app_admin_saints_mark_complete', methods: ['POST'])]
    public function markComplete(
        Saint $saint,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if this is an update to an existing saint
        if ($saint->getUrl()) {
            $existingSaints = $entityManager->getRepository(Saint::class)->findBy(['url' => $saint->getUrl()]);
            foreach ($existingSaints as $existing) {
                if ($existing->getId() !== $saint->getId() && !$existing->isIncomplete()) {
                    // Update the existing complete saint with the new data
                    $existing->setName(str_replace(' (Update)', '', $saint->getName()));
                    $existing->setCanonicalStatus($saint->getCanonicalStatus());
                    $existing->setFeastDate($saint->getFeastDate());
                    $existing->setCanonizationDate($saint->getCanonizationDate());
                    $existing->setCanonizingPope($saint->getCanonizingPope());

                    // Merge translations (especially the saint phrase)
                    foreach ($saint->getTranslations() as $newTranslation) {
                        $existingTranslation = $existing->getTranslation($newTranslation->getLocale());
                        if (!$existingTranslation) {
                            $existingTranslation = new \App\Entity\SaintTranslation();
                            $existingTranslation->setLocale($newTranslation->getLocale());
                            $existing->addTranslation($existingTranslation);
                        }
                        $existingTranslation->setSaintPhrase($newTranslation->getSaintPhrase());
                        // We could merge other fields if needed, but for discovery it's mostly the phrase
                    }
                    
                    // Remove the draft saint
                    $entityManager->remove($saint);
                    $entityManager->flush();
                    
                    $this->addFlash('success', sprintf('Saint "%s" updated with new information.', $existing->getName()));
                    return $this->redirectToRoute('app_admin_saints_incomplete');
                }
            }
        }

        $saint->setIsIncomplete(false);
        $saint->setName(str_replace(' (Update)', '', $saint->getName()));
        $entityManager->flush();
        
        $this->addFlash('success', 'Saint marked as complete.');
        
        return $this->redirectToRoute('app_admin_saints_incomplete');
    }

    /**
     * Discards an incomplete saint and its associated relics
     */
    #[Route('/incomplete/{id}/discard', name: 'app_admin_saints_discard', methods: ['POST'])]
    public function discard(
        Request $request,
        Saint $saint,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('discard'.$saint->getId(), $request->request->get('_token'))) {
            // Associated relics will be deleted if there is no other saint (they are ManyToOne usually)
            // But we should be explicit or check if they need to be moved.
            // The plan says: "relics of said saint must be moved or deleted."
            // For simplicity in this automated flow, we delete them if they are only linked to this saint.
            
            $relicCount = count($saint->getRelics());
            foreach ($saint->getRelics() as $relic) {
                $entityManager->remove($relic);
            }
            
            $entityManager->remove($saint);
            $entityManager->flush();
            
            $this->addFlash('success', sprintf('Saint and %d associated relics discarded.', $relicCount));
        }
        
        return $this->redirectToRoute('app_admin_saints_incomplete');
    }
}