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
    public function index(Request $request): Response
    {
        // Load catalog data
        $catalogPath = $this->getParameter('kernel.project_dir') . '/data/catalog.json';
        $catalogData = [];
        
        if (file_exists($catalogPath)) {
            $catalogContent = file_get_contents($catalogPath);
            $catalogData = json_decode($catalogContent, true) ?? [];
        }
        
        // Get filter parameters
        $maxShafts = $request->query->get('max_shafts');
        $maxTreadles = $request->query->get('max_treadles');
        $primaryColor = $request->query->get('primary_color');
        $secondaryColor = $request->query->get('secondary_color');
        $alternatingWarpColor = $request->query->get('alternating_warp_color');
        $alternatingWarpSpan = $request->query->get('alternating_warp_span');
        $alternatingWarpOffset = $request->query->get('alternating_warp_offset');
        $alternatingWarpEnabled = $request->query->get('alternating_warp_enabled');
        
        // Apply filters
        $filteredData = $catalogData;
        if ($maxShafts !== null && $maxShafts !== '') {
            $maxShafts = (int) $maxShafts;
            $filteredData = array_filter($filteredData, fn($pattern) => $pattern['shafts'] <= $maxShafts);
        }
        
        if ($maxTreadles !== null && $maxTreadles !== '') {
            $maxTreadles = (int) $maxTreadles;
            $filteredData = array_filter($filteredData, fn($pattern) => $pattern['treadles'] <= $maxTreadles);
        }
        
        // Reset array keys after filtering
        $filteredData = array_values($filteredData);
        
        // Pagination logic
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 52;
        $totalPatterns = count($filteredData);
        $totalPatternsUnfiltered = count($catalogData);
        $totalPages = max(1, ceil($totalPatterns / $perPage));
        
        // Ensure page doesn't exceed available pages
        $page = min($page, $totalPages);
        
        $offset = ($page - 1) * $perPage;
        $paginatedPatterns = array_slice($filteredData, $offset, $perPage);
        
        return $this->render('pattern_browser/index.html.twig', [
            'patterns' => $paginatedPatterns,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPatterns' => $totalPatterns,
            'totalPatternsUnfiltered' => $totalPatternsUnfiltered,
            'perPage' => $perPage,
            'filters' => [
                'maxShafts' => $maxShafts,
                'maxTreadles' => $maxTreadles,
                'primaryColor' => $primaryColor,
                'secondaryColor' => $secondaryColor,
                'alternatingWarpColor' => $alternatingWarpColor,
                'alternatingWarpSpan' => $alternatingWarpSpan,
                'alternatingWarpOffset' => $alternatingWarpOffset,
                'alternatingWarpEnabled' => $alternatingWarpEnabled
            ]
        ]);
    }

    #[Route('/tools', name: 'app_tools')]
    public function tools(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/pattern/{filename}', name: 'app_pattern_preview')]
    public function patternPreview(string $filename, WifParser $wifParser): Response
    {
        $wifPath = $this->getParameter('kernel.project_dir') . '/data/wif/' . $filename . '.wif';
        
        if (!file_exists($wifPath)) {
            throw $this->createNotFoundException('Pattern file not found.');
        }
        
        try {
            $fileContent = file_get_contents($wifPath);
            $weavingData = $wifParser->parse($fileContent);
            
            return $this->render('pattern_preview/index.html.twig', [
                'weavingData' => $weavingData,
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error loading pattern: ' . $e->getMessage());
        }
    }

    #[Route('/pattern/{filename}/preview', name: 'app_pattern_preview_api', methods: ['POST'])]
    public function patternPreviewApi(string $filename, Request $request, WifParser $wifParser): Response
    {
        $wifPath = $this->getParameter('kernel.project_dir') . '/data/wif/' . $filename . '.wif';
        
        if (!file_exists($wifPath)) {
            return $this->json(['error' => 'Pattern file not found'], 404);
        }
        
        try {
            $fileContent = file_get_contents($wifPath);
            $weavingData = $wifParser->parse($fileContent);
            
            // Get color overrides from request body
            $requestData = json_decode($request->getContent(), true) ?? [];
            $primaryColor = $requestData['primaryColor'] ?? null;
            $secondaryColor = $requestData['secondaryColor'] ?? null;
            $alternatingWarpColor = $requestData['alternatingWarpColor'] ?? null;
            $alternatingWarpSpan = $requestData['alternatingWarpSpan'] ?? null;
            $alternatingWarpOffset = $requestData['alternatingWarpOffset'] ?? null;
            $alternatingWarpEnabled = $requestData['alternatingWarpEnabled'] ?? null;
            
            // Apply color overrides if provided
            $colors = $weavingData['colors'];
            if ($primaryColor && preg_match('/^#[a-fA-F0-9]{6}$/', $primaryColor)) {
                $rgb = $this->hexToRgb($primaryColor);
                if ($rgb && isset($colors['colors'][1])) {
                    $colors['colors'][1] = $rgb;
                }
            }
            
            if ($secondaryColor && preg_match('/^#[a-fA-F0-9]{6}$/', $secondaryColor)) {
                $rgb = $this->hexToRgb($secondaryColor);
                if ($rgb && isset($colors['colors'][2])) {
                    $colors['colors'][2] = $rgb;
                }
            }
            
            // Apply alternating warp pattern if provided and enabled
            $pattern = $weavingData['pattern'];
            if ($alternatingWarpEnabled && $alternatingWarpColor && $alternatingWarpSpan && preg_match('/^#[a-fA-F0-9]{6}$/', $alternatingWarpColor)) {
                $alternatingRgb = $this->hexToRgb($alternatingWarpColor);
                $span = (int) $alternatingWarpSpan;
                $offset = (int) ($alternatingWarpOffset ?? 0);
                
                if ($alternatingRgb && $span > 0 && $pattern) {
                    // Add the alternating color to the color palette
                    $alternatingColorIndex = max(array_keys($colors['colors'])) + 1;
                    $colors['colors'][$alternatingColorIndex] = $alternatingRgb;
                    
                    // Modify pattern to use alternating warp colors
                    $pattern = $this->applyAlternatingWarp($pattern, $alternatingColorIndex, $span, $offset);
                }
            }
            
            // Return only essential data for preview
            return $this->json([
                'pattern' => $pattern,
                'colors' => $colors,
                'metadata' => [
                    'title' => $weavingData['metadata']['title']
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error loading pattern: ' . $e->getMessage()], 500);
        }
    }

    private function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 6) {
            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2))
            ];
        }
        
        return null;
    }

    private function applyAlternatingWarp(array $pattern, int $alternatingColorIndex, int $span, int $offset = 0): array
    {
        $modifiedPattern = $pattern;
        
        foreach ($modifiedPattern as $pickIndex => &$row) {
            foreach ($row as $threadIndex => &$cell) {
                // Apply alternating pattern to warp threads (when warp is visible - isUp = true)
                if ($cell['isUp']) {
                    // Apply offset to thread position before calculating stripe group
                    $adjustedThreadIndex = $threadIndex + $offset;
                    
                    // Calculate which stripe this thread belongs to (0-based thread index)
                    $stripeGroup = intval($adjustedThreadIndex / $span);
                    
                    // Alternate between original warp color and alternating color
                    if ($stripeGroup % 2 === 1) {
                        $cell['warpColor'] = $alternatingColorIndex;
                        $cell['displayColor'] = $alternatingColorIndex;
                    }
                }
            }
        }
        
        return $modifiedPattern;
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