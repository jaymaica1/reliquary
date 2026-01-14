<?php

namespace App\Controller;

use App\Entity\Image;
use App\Form\ProfileType;
use App\Service\DataExportService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private DataExportService $dataExportService
    ) {
    }

    #[Route('/', name: 'app_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, ImageService $imageService): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle profile image upload
            $profileImageFile = $form->get('profileImage')->getData();

            if ($profileImageFile) {
                // Remove existing profile image if any
                if (!$user->getImages()->isEmpty()) {
                    $existingImage = $user->getImages()->first();
                    $imageService->deleteImage($existingImage);
                    $user->removeImage($existingImage);
                    $entityManager->remove($existingImage);
                }

                // Create new UserImage entity using the ImageService
                $image = $imageService->createUserImage($profileImageFile, $user, $this->getUser());
                $user->addImage($image);
            }

            // Handle Geolocation privacy
            if (!$user->isAllowGeolocationStorage()) {
                $user->setLatitude(null);
                $user->setLongitude(null);
                $user->setGeolocationTimestamp(null);
            }

            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('success', [], 'profile'));
            return $this->redirectToRoute('app_profile_show');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/export', name: 'app_profile_export', methods: ['GET'])]
    public function export(): Response
    {
        $user = $this->getUser();
        $data = $this->dataExportService->exportUserData($user);
        $json = $this->dataExportService->formatAsJson($data);

        $response = new Response($json);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'reliquary_data_export.json'
        );

        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        ImageService $imageService
    ): Response {
        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_profile_show');
        }

        $user = $this->getUser();

        // Handle related data
        // 1. Relics: Assign to a system user or anonymize? 
        // Plan says: "Should they be deleted or assigned to a 'System' user?"
        // Usually, for contributions, we anonymize rather than delete to preserve history.
        // Let's find or create a 'System' user.
        $systemUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['username' => 'System']);
        
        foreach ($user->getRelics() as $relic) {
            if ($systemUser) {
                $relic->setCreator($systemUser);
            } else {
                $relic->setCreator(null);
            }
        }

        // 2. Images: Images uploaded by user.
        // UserImage (profile pic) should be deleted.
        foreach ($user->getImages() as $image) {
            $imageService->deleteImage($image);
            $entityManager->remove($image);
        }

        // 3. Delete the user
        $entityManager->remove($user);
        $entityManager->flush();

        // Invalidate session
        $request->getSession()->invalidate();
        $tokenStorage->setToken(null);

        $this->addFlash('success', $this->translator->trans('account_deleted', [], 'profile'));

        return $this->redirectToRoute('app_home');
    }
}
