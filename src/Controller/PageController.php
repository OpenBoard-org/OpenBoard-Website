<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

class PageController extends AbstractController
{
    private array $supportedLocales;

    public function __construct(
        private ParameterBagInterface $parameterBag,
        private TranslatorInterface $translator,
    )
    {
        $translationsDir = $this->parameterBag->get('kernel.project_dir') . '/translations';
        $this->supportedLocales = $this->discoverSupportedLocales($translationsDir);
    }

    #[Route('/', name: 'home_root')]
    public function homeRoot(Request $request): Response
    {
        $sessionLocale = $request->hasSession() ? $request->getSession()->get('_locale') : null;
        if ($sessionLocale && in_array($sessionLocale, $this->supportedLocales, true)) {
            return $this->redirectToRoute('home', ['_locale' => $sessionLocale]);
        }

        $preferred = $request->getPreferredLanguage($this->supportedLocales) ?: 'en';
        return $this->redirectToRoute('home', ['_locale' => $preferred]);
    }

    #[Route(
        '/{_locale}',
        name: 'home',
        requirements: ['_locale' => '[a-zA-Z]{2}(?:_[a-zA-Z]{2})?']
    )]
    public function home(Request $request): Response
    {
        $this->persistLocale($request);
        return $this->renderPage('pages/home.html.twig');
    }

    #[Route(
        '/{_locale}/download',
        name: 'download',
        requirements: ['_locale' => '[a-zA-Z]{2}(?:_[a-zA-Z]{2})?']
    )]
    public function download(Request $request): Response
    {
        $this->persistLocale($request);

        $downloads = [
            [
                'image' => 'images/OS-X-logo.png',
                'name_key' => 'download.platforms.macos.name',
                'note_key' => 'download.platforms.macos.note',
                'cta_key' => 'download.platforms.macos.cta',
                'url' => 'https://github.com/OpenBoard-org/OpenBoard/releases/download/v1.7.5/OpenBoard-1.7.5.dmg',
            ],
            [
                'image' => 'images/Windows-logo.png',
                'name_key' => 'download.platforms.windows.name',
                'note_key' => 'download.platforms.windows.note',
                'cta_key' => 'download.platforms.windows.cta',
                'url' => 'https://github.com/OpenBoard-org/OpenBoard/releases/download/v1.7.5/OpenBoard_Installer_1.7.5.exe',
            ],
            [
                'image' => 'images/Debian-logo.png',
                'name_key' => 'download.platforms.debian.name',
                'note_key' => 'download.platforms.debian.note',
                'cta_key' => 'download.platforms.debian.cta',
                'url' => 'https://github.com/OpenBoard-org/OpenBoard/releases/download/v1.7.5/openboard_debian_12_1.7.5_amd64.deb',
            ],
        ];

        $changelog = $this->buildChangelog($request->getLocale());

        return $this->renderPage('pages/download.html.twig', [
            'downloads' => $downloads,
            'otherPlatformsUrl' => 'https://github.com/OpenBoard-org/OpenBoard/wiki/Downloads#other-platforms',
            'releasesUrl' => 'https://github.com/OpenBoard-org/OpenBoard/releases',
            'communityPackagesUrl' => 'https://github.com/OpenBoard-org/OpenBoard/wiki/Downloads#other-platforms',
            'changelog' => $changelog,
        ]);
    }

    #[Route(
        '/{_locale}/documentation',
        name: 'documentation',
        requirements: ['_locale' => '[a-zA-Z]{2}(?:_[a-zA-Z]{2})?']
    )]
    public function documentation(Request $request): Response
    {
        $this->persistLocale($request);
        return $this->renderPage('pages/documentation.html.twig');
    }

    #[Route(
        '/{_locale}/github',
        name: 'github',
        requirements: ['_locale' => '[a-zA-Z]{2}(?:_[a-zA-Z]{2})?']
    )]
    public function github(Request $request): Response
    {
        $this->persistLocale($request);
        return $this->renderPage('pages/github.html.twig');
    }

    #[Route(
        '/{_locale}/support',
        name: 'support',
        requirements: ['_locale' => '[a-zA-Z]{2}(?:_[a-zA-Z]{2})?']
    )]
    public function support(Request $request): Response
    {
        $this->persistLocale($request);
        return $this->renderPage('pages/support.html.twig');
    }

    /**
     * Load changelog YAML and translate items.
     */
    private function buildChangelog(string $locale): array
    {
        $baseDir = $this->getParameter('kernel.project_dir') . '/translations';
        $localizedPath = sprintf('%s/changelog.%s.yaml', $baseDir, $locale);
        $fallbackPath = sprintf('%s/changelog.en.yaml', $baseDir);

        $path = is_file($localizedPath) ? $localizedPath : $fallbackPath;
        $data = Yaml::parseFile($path);

        $translated = [];
        foreach ($data['releases'] ?? [] as $release) {
            $sections = [];
            foreach ($release['sections'] ?? [] as $section) {
                $sections[] = [
                    'title' => $section['title'] ?? '',
                    'items' => $section['items'] ?? [],
                ];
            }
            $translated[] = [
                'version' => $release['version'] ?? '',
                'sections' => $sections,
            ];
        }

        return $translated;
    }

    private function persistLocale(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $request->getLocale());
        }
    }

    private function renderPage(string $template, array $parameters = []): Response
    {
        return $this->render($template, $parameters + [
            'supportedLocales' => $this->supportedLocales,
            'languageLabels' => $this->buildLanguageLabels(),
        ]);
    }

    /**
     * Discover available locales by scanning translation filenames.
     */
    private function discoverSupportedLocales(string $translationsDir): array
    {
        if (!is_dir($translationsDir)) {
            return ['en'];
        }

        $locales = [];
        foreach (new \FilesystemIterator($translationsDir) as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (preg_match('/\.(?P<locale>[a-z]{2}(?:_[A-Z]{2})?)\.[^.]+$/i', $fileInfo->getFilename(), $matches)) {
                $locales[] = strtolower($matches['locale']);
            }
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales ?: ['en'];
    }

    /**
     * Build localized labels for the language switcher using each locale's own catalog.
     */
    private function buildLanguageLabels(): array
    {
        $labels = [];

        foreach ($this->supportedLocales as $code) {
            $key = 'nav.languages.' . $code;
            $translated = $this->translator->trans($key, locale: $code);
            $labels[$code] = ($translated === $key) ? strtoupper($code) : $translated;
        }

        return $labels;
    }
}
