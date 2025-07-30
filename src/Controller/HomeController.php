<?php

namespace App\Controller;

use App\Service\WifParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/wifviewer', name: 'app_wif_viewer')]
    public function wifViewer(Request $request, WifParser $wifParser): Response
    {
        $weavingData = null;
        $error = null;
        $fileName = '';

        if ($request->isMethod('POST')) {
            $uploadedFile = $request->files->get('wif_file');
            
            if ($uploadedFile && $uploadedFile->isValid()) {
                try {
                    $fileName = $uploadedFile->getClientOriginalName();
                    $fileContent = file_get_contents($uploadedFile->getPathname());
                    
                    if ($fileContent === false) {
                        throw new \Exception('Could not read uploaded file');
                    }
                    
                    $weavingData = $wifParser->parse($fileContent);
                } catch (\Exception $e) {
                    $error = 'Error processing WIF file: ' . $e->getMessage();
                }
            } else {
                $error = 'Please select a valid WIF file to upload.';
            }
        }

        return $this->render('wif_viewer/index.html.twig', [
            'weavingData' => $weavingData,
            'error' => $error,
            'fileName' => $fileName,
        ]);
    }
} 