<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function __invoke(): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';

        // Menu latéral = tous les fichiers .md
        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.md')->sortByName();

        $nav = [];
        // Génération de la liste des catégories
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (str_starts_with($relative, 'categories/')) {
                $relative = substr($relative, strlen('categories/'));
            }

            $slugValue = substr($relative, 0, -3); // remove ".md"

            // Génération du label à partir du slug 
            $parts = array_map(
                fn (string $part) => ucfirst(str_replace('-', ' ', $part)),
                explode('/', $slugValue)
            );

            //
            $nav[] = [
                'slug' => $slugValue,
                'label' => implode(' / ', $parts),
            ];
        }

        // Regroupement par catégorie 
        $categoryMap = [];
        foreach ($nav as $item) {
            $segments = explode('/', $item['slug']);
            $categoryKey = $segments[0];
            $label = ucfirst(str_replace('-', ' ', $categoryKey));

            if (!isset($categoryMap[$categoryKey])) {
                $categoryMap[$categoryKey] = [
                    'slug' => $item['slug'],
                    'label' => $label,
                ];
            }

            if (($segments[1] ?? null) === 'index' || $item['slug'] === 'index') {
                $categoryMap[$categoryKey]['slug'] = $item['slug'];
            }
        }

        return $this->render('home/index.html.twig', [
            'categories' => array_values($categoryMap),
        ]);
    }
}
