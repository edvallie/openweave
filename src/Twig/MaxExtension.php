<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MaxExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('max', [$this, 'getMax']),
        ];
    }

    public function getMax(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        
        return max($array);
    }
} 