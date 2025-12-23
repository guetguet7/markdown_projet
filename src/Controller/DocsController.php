<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Finder\Finder;

final class DocsController extends AbstractController
{
    #[Route('/docs/{slug}', name: 'app_docs', requirements: ['slug' => '[A-Za-z0-9\/-]+' ], defaults: ['slug' => 'index'])]
    public function show(string $slug): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $baseDirReal = realpath($baseDir) ?: $baseDir;

        $slug = trim($slug, '/');
        $slug = $slug === '' ? 'index' : $slug;

        $candidates = [
            $baseDir . '/' . $slug . '.md',
        ];

        // Permet de servir des fichiers placés dans content/docs/categories/{langage}/...
        if (!str_starts_with($slug, 'categories/')) {
            $candidates[] = $baseDir . '/categories/' . $slug . '.md';
        }

        $realPath = null;
        $slugNormalized = null;

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if (!$resolved || !str_starts_with($resolved, $baseDirReal) || !is_file($resolved)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($resolved, strlen($baseDirReal) + 1));
            if (str_starts_with($relative, 'categories/')) {
                $relative = substr($relative, strlen('categories/'));
            }

            $realPath = $resolved;
            $slugNormalized = substr($relative, 0, -3);
            break;
        }

        if (!$realPath) {
            throw $this->createNotFoundException('Page de documentation introuvable.');
        }

        // Menu latéral = tous les fichiers .md
        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.md')->sortByName();

        $nav = [];
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (str_starts_with($relative, 'categories/')) {
                $relative = substr($relative, strlen('categories/'));
            }

            $slugValue = substr($relative, 0, -3); // remove ".md"

            $parts = array_map(
                fn (string $part) => ucfirst(str_replace('-', ' ', $part)),
                explode('/', $slugValue)
            );

            $nav[] = [
                'slug' => $slugValue,
                'label' => implode(' / ', $parts),
            ];
        }

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

            // Priorité à un éventuel index dans la catégorie
            if (($segments[1] ?? null) === 'index' || $item['slug'] === 'index') {
                $categoryMap[$categoryKey]['slug'] = $item['slug'];
            }
        }

        return $this->render('docs/show.html.twig', [
            'slug' => $slugNormalized ?? $slug,
            'nav' => $nav,
            'categories' => array_values($categoryMap),
            'markdown' => file_get_contents($realPath) ?: '',
        ]);
    }
}
