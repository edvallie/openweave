<?php

namespace App\Controller;

use App\Service\WifParser;
use App\Repository\PatternFavoriteRepository;
use App\Repository\PatternRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, PatternRepository $patternRepository): Response
    {
        // Get filter parameters
        $search = $request->query->get('search');
        $minShafts = $request->query->get('min_shafts');
        $maxShafts = $request->query->get('max_shafts');
        $minTreadles = $request->query->get('min_treadles');
        $maxTreadles = $request->query->get('max_treadles');
        $primaryColor = $request->query->get('primary_color');
        $secondaryColor = $request->query->get('secondary_color');
        $alternatingWarpColor = $request->query->get('alternating_warp_color');
        $alternatingWarpSpan = $request->query->get('alternating_warp_span');
        $alternatingWarpOffset = $request->query->get('alternating_warp_offset');
        $alternatingWarpEnabled = $request->query->get('alternating_warp_enabled');
        
        // Build filters array
        $filters = [];
        if ($search !== null && $search !== '') {
            $filters['search'] = trim($search);
        }
        if ($minShafts !== null && $minShafts !== '') {
            $filters['minShafts'] = (int) $minShafts;
        }
        if ($maxShafts !== null && $maxShafts !== '') {
            $filters['maxShafts'] = (int) $maxShafts;
        }
        if ($minTreadles !== null && $minTreadles !== '') {
            $filters['minTreadles'] = (int) $minTreadles;
        }
        if ($maxTreadles !== null && $maxTreadles !== '') {
            $filters['maxTreadles'] = (int) $maxTreadles;
        }
        
        // Pagination logic
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 52;
        
        // Get patterns from database with filters and pagination
        $patterns = $patternRepository->findWithFilters($filters, $page, $perPage);
        $totalPatterns = $patternRepository->countWithFilters($filters);
        $totalPatternsUnfiltered = $patternRepository->countWithFilters([]);
        $totalPages = max(1, ceil($totalPatterns / $perPage));
        
        // Ensure page doesn't exceed available pages
        $page = min($page, $totalPages);
        
        return $this->render('pattern_browser/index.html.twig', [
            'patterns' => $patterns,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPatterns' => $totalPatterns,
            'totalPatternsUnfiltered' => $totalPatternsUnfiltered,
            'perPage' => $perPage,
            'filters' => [
                'search' => $search,
                'minShafts' => $minShafts,
                'maxShafts' => $maxShafts,
                'minTreadles' => $minTreadles,
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

    #[Route('/favorites', name: 'app_favorites')]
    public function favorites(Request $request, PatternFavoriteRepository $favoriteRepository, PatternRepository $patternRepository): Response
    {
        // Check if user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get user's favorite patterns
        $favorites = $favoriteRepository->findBy(['account' => $user], ['id' => 'DESC']);
        
        // Convert favorites to pattern data format for the template
        $patterns = [];
        foreach ($favorites as $favorite) {
            $pattern = $favorite->getPattern();
            if ($pattern) {
                $patterns[] = $pattern;
            }
        }

        // Get filter parameters (same as index method)
        $search = $request->query->get('search');
        $minShafts = $request->query->get('min_shafts');
        $maxShafts = $request->query->get('max_shafts');
        $minTreadles = $request->query->get('min_treadles');
        $maxTreadles = $request->query->get('max_treadles');
        $primaryColor = $request->query->get('primary_color');
        $secondaryColor = $request->query->get('secondary_color');
        $alternatingWarpColor = $request->query->get('alternating_warp_color');
        $alternatingWarpSpan = $request->query->get('alternating_warp_span');
        $alternatingWarpOffset = $request->query->get('alternating_warp_offset');
        $alternatingWarpEnabled = $request->query->get('alternating_warp_enabled');
        
        // Build filters array
        $filters = [];
        if ($search !== null && $search !== '') {
            $filters['search'] = trim($search);
        }
        if ($minShafts !== null && $minShafts !== '') {
            $filters['minShafts'] = (int) $minShafts;
        }
        if ($maxShafts !== null && $maxShafts !== '') {
            $filters['maxShafts'] = (int) $maxShafts;
        }
        if ($minTreadles !== null && $minTreadles !== '') {
            $filters['minTreadles'] = (int) $minTreadles;
        }
        if ($maxTreadles !== null && $maxTreadles !== '') {
            $filters['maxTreadles'] = (int) $maxTreadles;
        }
        
        // Apply filters to favorites using array_filter for now
        $filteredPatterns = $patterns;
        
        if ($search !== null && $search !== '') {
            $search = trim($search);
            $filteredPatterns = array_filter($filteredPatterns, function($pattern) use ($search) {
                return stripos($pattern->getTitle(), $search) !== false;
            });
        }
        
        if ($minShafts !== null && $minShafts !== '') {
            $minShafts = (int) $minShafts;
            $filteredPatterns = array_filter($filteredPatterns, fn($pattern) => $pattern->getShafts() >= $minShafts);
        }
        
        if ($maxShafts !== null && $maxShafts !== '') {
            $maxShafts = (int) $maxShafts;
            $filteredPatterns = array_filter($filteredPatterns, fn($pattern) => $pattern->getShafts() <= $maxShafts);
        }
        
        if ($minTreadles !== null && $minTreadles !== '') {
            $minTreadles = (int) $minTreadles;
            $filteredPatterns = array_filter($filteredPatterns, fn($pattern) => $pattern->getTreadles() >= $minTreadles);
        }
        
        if ($maxTreadles !== null && $maxTreadles !== '') {
            $maxTreadles = (int) $maxTreadles;
            $filteredPatterns = array_filter($filteredPatterns, fn($pattern) => $pattern->getTreadles() <= $maxTreadles);
        }
        
        // Reset array keys after filtering
        $filteredPatterns = array_values($filteredPatterns);
        
        // Pagination logic
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 52;
        $totalPatterns = count($filteredPatterns);
        $totalPatternsUnfiltered = count($patterns);
        $totalPages = max(1, ceil($totalPatterns / $perPage));
        
        // Ensure page doesn't exceed available pages
        $page = min($page, $totalPages);
        
        $offset = ($page - 1) * $perPage;
        $paginatedPatterns = array_slice($filteredPatterns, $offset, $perPage);
        
        return $this->render('pattern_browser/index.html.twig', [
            'patterns' => $paginatedPatterns,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPatterns' => $totalPatterns,
            'totalPatternsUnfiltered' => $totalPatternsUnfiltered,
            'perPage' => $perPage,
            'filters' => [
                'search' => $search,
                'minShafts' => $minShafts,
                'maxShafts' => $maxShafts,
                'minTreadles' => $minTreadles,
                'maxTreadles' => $maxTreadles,
                'primaryColor' => $primaryColor,
                'secondaryColor' => $secondaryColor,
                'alternatingWarpColor' => $alternatingWarpColor,
                'alternatingWarpSpan' => $alternatingWarpSpan,
                'alternatingWarpOffset' => $alternatingWarpOffset,
                'alternatingWarpEnabled' => $alternatingWarpEnabled
            ],
            'isFavoritesPage' => true
        ]);
    }

    #[Route('/pattern/{id}', name: 'app_pattern_preview', requirements: ['id' => '\d+'])]
    public function patternPreview(int $id, WifParser $wifParser, PatternRepository $patternRepository): Response
    {
        $pattern = $patternRepository->find($id);
        
        if (!$pattern) {
            throw $this->createNotFoundException('Pattern not found.');
        }
        
        try {
            $weavingData = $wifParser->parse($pattern->getWif());
            
            return $this->render('pattern_preview/index.html.twig', [
                'weavingData' => $weavingData,
                'pattern' => $pattern
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error loading pattern: ' . $e->getMessage());
        }
    }

    #[Route('/pattern/{id}/preview', name: 'app_pattern_preview_api', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function patternPreviewApi(int $id, Request $request, WifParser $wifParser, PatternRepository $patternRepository): Response
    {
        $pattern = $patternRepository->find($id);
        
        if (!$pattern) {
            return $this->json(['error' => 'Pattern not found'], 404);
        }
        
        try {
            $weavingData = $wifParser->parse($pattern->getWif());
            
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