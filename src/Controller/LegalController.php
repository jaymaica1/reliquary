<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for legal pages like Privacy Policy and Terms of Use
 */
final class LegalController extends AbstractController
{
    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/terms-of-use', name: 'app_terms_of_use')]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }

    #[Route('/guidelines', name: 'app_guidelines')]
    public function guidelines(): Response
    {
        return $this->render('legal/guidelines.html.twig');
    }
}
