<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class AdminController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';

        return $this->render('admin/home/index.html.twig', [
            'categories' => $this->extractCategories($baseDir),
            'docs' => $this->collectDocs($baseDir),
        ]);
    }

    #[Route('/admin/docs', name: 'admin_docs')]
    public function docs(): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $files = $this->collectDocs($baseDir);

        return $this->render('admin/docs/index.html.twig', [
            'docs' => $files,
        ]);
    }

    #[Route('/admin/docs/new', name: 'admin_docs_new', methods: ['GET', 'POST'])]
    public function docsNew(Request $request): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $categories = $this->extractCategories($baseDir);

        if ($request->isMethod('POST')) {
            $categorySelect = trim((string) $request->request->get('category_select', ''));
            $categoryCustom = trim((string) $request->request->get('category_custom', ''));
            $category = $categoryCustom !== '' ? $categoryCustom : $categorySelect;
            $slug = trim((string) $request->request->get('slug', ''));
            $content = (string) $request->request->get('content', '');

            if ($category !== '' && !preg_match('/^[a-z0-9-]+$/i', $category)) {
                $this->addFlash('danger', 'Nom de catégorie invalide.');
                return $this->redirectToRoute('admin_docs_new');
            }

            if (!preg_match('/^[a-z0-9-]+$/i', $slug)) {
                $this->addFlash('danger', 'Nom de fichier invalide.');
                return $this->redirectToRoute('admin_docs_new');
            }

            $fs = new Filesystem();
            $targetDir = rtrim($baseDir . '/' . ltrim($category, '/'), '/');
            if ($category === '') {
                $targetDir = $baseDir;
            }

            $path = $targetDir . '/' . $slug . '.md';
            if ($fs->exists($path)) {
                $this->addFlash('danger', 'Un fichier avec ce nom existe déjà.');
                return $this->redirectToRoute('admin_docs_new');
            }

            $fs->mkdir($targetDir);
            $fs->dumpFile($path, $content !== '' ? $content : "# " . ucfirst(str_replace('-', ' ', $slug)) . "\n\nContenu du document.");

            $fullSlug = ltrim(($category ? $category . '/' : '') . $slug, '/');
            $this->addFlash('success', 'Fichier créé.');
            return $this->redirectToRoute('admin_docs_edit', ['slug' => $fullSlug]);
        }

        return $this->render('admin/docs/new.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/docs/{slug}/edit', name: 'admin_docs_edit', requirements: ['slug' => '[A-Za-z0-9\/-]+'], methods: ['GET', 'POST'])]
    public function docsEdit(Request $request, string $slug): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $baseDirReal = realpath($baseDir) ?: $baseDir;

        $slug = trim($slug, '/');
        $candidates = [
            $baseDir . '/' . $slug . '.md',
        ];
        if (!str_starts_with($slug, 'categories/')) {
            $candidates[] = $baseDir . '/categories/' . $slug . '.md';
        }

        $realPath = null;
        $relative = null;

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
            break;
        }

        if (!$realPath || $relative === null) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        if ($request->isMethod('POST')) {
            $content = (string) $request->request->get('content', '');
            file_put_contents($realPath, $content);
            $this->addFlash('success', 'Fichier mis à jour.');
            return $this->redirectToRoute('admin_docs_edit', ['slug' => $slug]);
        }

        return $this->render('admin/docs/edit.html.twig', [
            'slug' => $slug,
            'relative_path' => $relative,
            'content' => file_get_contents($realPath) ?: '',
        ]);
    }

    #[Route('/admin/docs/{slug}/download', name: 'admin_docs_download', requirements: ['slug' => '[A-Za-z0-9\/-]+'], methods: ['GET'])]
    public function docsDownload(string $slug): BinaryFileResponse
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $baseDirReal = realpath($baseDir) ?: $baseDir;

        $slug = trim($slug, '/');
        $candidates = [
            $baseDir . '/' . $slug . '.md',
        ];
        if (!str_starts_with($slug, 'categories/')) {
            $candidates[] = $baseDir . '/categories/' . $slug . '.md';
        }

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if (!$resolved || !str_starts_with($resolved, $baseDirReal) || !is_file($resolved)) {
                continue;
            }

            $response = new BinaryFileResponse($resolved);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($slug) . '.md'
            );
            return $response;
        }

        throw $this->createNotFoundException('Fichier introuvable.');
    }

    #[Route('/admin/docs/{slug}/delete', name: 'admin_docs_delete', requirements: ['slug' => '[A-Za-z0-9\/-]+'], methods: ['POST'])]
    public function docsDelete(Request $request, string $slug): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_doc_' . $slug, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_docs');
        }

        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $baseDirReal = realpath($baseDir) ?: $baseDir;

        $slug = trim($slug, '/');
        $candidates = [
            $baseDir . '/' . $slug . '.md',
        ];
        if (!str_starts_with($slug, 'categories/')) {
            $candidates[] = $baseDir . '/categories/' . $slug . '.md';
        }

        $fs = new Filesystem();
        $removed = false;

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if (!$resolved || !str_starts_with($resolved, $baseDirReal) || !is_file($resolved)) {
                continue;
            }
            $fs->remove($resolved);
            $removed = true;
        }

        if ($removed) {
            $this->addFlash('success', 'Document supprimé.');
        } else {
            $this->addFlash('warning', 'Document introuvable.');
        }

        return $this->redirectToRoute('admin_docs');
    }

    #[Route('/admin/categories', name: 'admin_categories')]
    public function categories(): Response
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $categories = [];

        if (is_dir($baseDir)) {
            $finder = new Finder();
            $finder->files()->in($baseDir)->name('*.md');

            foreach ($finder as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                if (str_starts_with($relative, 'categories/')) {
                    $relative = substr($relative, strlen('categories/'));
                }

                $slug = explode('/', $relative)[0] ?? null;
                if (!$slug) {
                    continue;
                }

                $label = ucfirst(str_replace('-', ' ', $slug));
                $categories[$slug] = [
                    'slug' => $slug,
                    'label' => $label,
                ];
            }
        }

        return $this->render('admin/categories/index.html.twig', [
            'categories' => array_values($categories),
        ]);
    }

    #[Route('/admin/categories/create', name: 'admin_categories_create', methods: ['POST'])]
    public function createCategory(Request $request): RedirectResponse
    {
        $slug = (string) $request->request->get('slug', '');
        $slug = trim($slug);

        if (!preg_match('/^[a-z0-9-]+$/i', $slug)) {
            $this->addFlash('danger', 'Slug invalide. Utilisez uniquement lettres, chiffres et tirets.');
            return $this->redirectToRoute('admin_categories');
        }

        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $fs = new Filesystem();
        $created = false;

        foreach ([$baseDir, $baseDir . '/categories'] as $dir) {
            $dirPath = $dir . '/' . $slug;
            $filePath = $dir . '/' . $slug . '.md';

            if ($fs->exists($dirPath) || $fs->exists($filePath)) {
                $this->addFlash('danger', 'Cette catégorie existe déjà.');
                return $this->redirectToRoute('admin_categories');
            }
        }

        // Crée un dossier pour la catégorie et un index.md
        $targetDir = $baseDir . '/' . $slug;
        $fs->mkdir($targetDir);
        $fs->dumpFile($targetDir . '/index.md', "# " . ucfirst(str_replace('-', ' ', $slug)) . "\n\nContenu de la catégorie.");
        $created = true;

        if ($created) {
            $this->addFlash('success', 'Catégorie créée.');
        } else {
            $this->addFlash('warning', 'Catégorie non créée.');
        }

        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/admin/categories/{slug}/edit', name: 'admin_categories_edit', methods: ['POST'])]
    public function editCategory(Request $request, string $slug): RedirectResponse
    {
        $newSlug = (string) $request->request->get('slug_new', '');

        if (!preg_match('/^[a-z0-9-]+$/i', $slug) || !preg_match('/^[a-z0-9-]+$/i', $newSlug)) {
            $this->addFlash('danger', 'Slug invalide.');
            return $this->redirectToRoute('admin_categories');
        }

        if ($slug === $newSlug) {
            $this->addFlash('info', 'Aucune modification effectuée.');
            return $this->redirectToRoute('admin_categories');
        }

        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $fs = new Filesystem();
        $renamed = false;

        foreach ([$baseDir, $baseDir . '/categories'] as $dir) {
            $dirOld = $dir . '/' . $slug;
            $dirNew = $dir . '/' . $newSlug;
            if (is_dir($dirOld)) {
                if ($fs->exists($dirNew)) {
                    $this->addFlash('danger', 'Une catégorie porte déjà ce nom.');
                    return $this->redirectToRoute('admin_categories');
                }
                $fs->rename($dirOld, $dirNew);
                $renamed = true;
            }

            $fileOld = $dir . '/' . $slug . '.md';
            $fileNew = $dir . '/' . $newSlug . '.md';
            if (is_file($fileOld)) {
                if ($fs->exists($fileNew)) {
                    $this->addFlash('danger', 'Un fichier porte déjà ce slug.');
                    return $this->redirectToRoute('admin_categories');
                }
                $fs->rename($fileOld, $fileNew);
                $renamed = true;
            }
        }

        if (!$renamed) {
            $this->addFlash('warning', 'Aucune ressource trouvée pour cette catégorie.');
        } else {
            $this->addFlash('success', 'Catégorie renommée.');
        }

        return $this->redirectToRoute('admin_categories');
    }

    #[Route('/admin/categories/{slug}/delete', name: 'admin_categories_delete', methods: ['POST'])]
    public function deleteCategory(Request $request, string $slug): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_category_' . $slug, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_categories');
        }

        if (!preg_match('/^[a-z0-9-]+$/i', $slug)) {
            $this->addFlash('danger', 'Slug invalide.');
            return $this->redirectToRoute('admin_categories');
        }

        $baseDir = $this->getParameter('kernel.project_dir') . '/content/docs';
        $fs = new Filesystem();
        $removed = false;

        foreach ([$baseDir, $baseDir . '/categories'] as $dir) {
            $dirPath = $dir . '/' . $slug;
            if (is_dir($dirPath)) {
                $fs->remove($dirPath);
                $removed = true;
            }

            $filePath = $dir . '/' . $slug . '.md';
            if (is_file($filePath)) {
                $fs->remove($filePath);
                $removed = true;
            }
        }

        if (!$removed) {
            $this->addFlash('warning', 'Aucune ressource trouvée pour cette catégorie.');
        } else {
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categories');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Markdown');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        // yield MenuItem::linkToCrud('The Label', 'fas fa-list', EntityClass::class);
    }

    /**
     * Retourne les catégories déduites des fichiers Markdown.
     */
    private function extractCategories(string $baseDir): array
    {
        $categories = [];

        if (!is_dir($baseDir)) {
            return $categories;
        }

        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.md');

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (str_starts_with($relative, 'categories/')) {
                $relative = substr($relative, strlen('categories/'));
            }

            $slug = explode('/', $relative)[0] ?? null;
            if (!$slug) {
                continue;
            }

            $label = ucfirst(str_replace('-', ' ', $slug));
            $categories[$slug] = [
                'slug' => $slug,
                'label' => $label,
            ];
        }

        return array_values($categories);
    }

    /**
     * Liste les fichiers Markdown avec leur slug/chemin.
     */
    private function collectDocs(string $baseDir): array
    {
        $files = [];

        if (!is_dir($baseDir)) {
            return $files;
        }

        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.md')->sortByName();

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $slug = $relative;
            if (str_starts_with($relative, 'categories/')) {
                $slug = substr($relative, strlen('categories/'));
            }
            $slug = substr($slug, 0, -3); // remove .md

            $parts = array_map(
                fn (string $part) => ucfirst(str_replace('-', ' ', $part)),
                explode('/', $slug)
            );

            $files[] = [
                'slug' => $slug,
                'label' => implode(' / ', $parts),
                'path' => $relative,
            ];
        }

        return $files;
    }
}
