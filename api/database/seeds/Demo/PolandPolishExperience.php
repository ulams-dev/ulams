<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\PolandArt;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;

/**
 * "Polska w liczbach": the Polish twin of {@see PolandExperience}. It plays the same poland package (which
 * has Polish text for every step) and has the same chapters, quizzes and sources, written in Polish in
 * Demo/content/poland/pl/. It is a separate course, so a learner picks a language once and every
 * lesson, question and certificate is in it. PolandExperience::run() seeds it after the English course.
 */
class PolandPolishExperience extends PolandExperience
{
    protected function polishVariant(): bool
    {
        return true;
    }

    protected function contentDir(): string
    {
        return 'poland/pl';
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'Polska w liczbach',
            'subtitle' => 'Pięć dekad zmian w publicznych danych: co się poprawiło, a co wciąż trudne',
            'summary' => 'Czytaj Polskę na interaktywnej mapie i na wykresach, rozdział po rozdziale. Każda liczba ma podane publiczne źródło.',
            'description' => $this->markdown('pl/course-description'),
            'level' => 'Początkujący',
            'language' => 'pl',
            'duration' => 'W swoim tempie',
            'hours_to_complete' => null, // dostęp bezterminowy: liczba oznaczałaby termin dla każdego uczącego się
            'target_group' => 'Wszyscy ciekawi, jak zmieniła się Polska: uczniowie, nauczyciele, dziennikarze',
            'public' => true,
            'fields' => [
                'experience' => 'poland',
                'landing' => [
                    'headline' => 'Polska odczytana z danych',
                    'subheadline' => 'Darmowy kurs na interaktywnej mapie i wykresach, ze źródłem przy każdej liczbie.',
                    'cta' => 'Zacznij kurs, za darmo',
                    'faq' => [
                        ['q' => 'Czy kurs jest darmowy?', 'a' => 'Tak. Każdy rozdział, quiz i certyfikat są darmowe.'],
                        ['q' => 'Skąd pochodzą liczby?', 'a' => 'Z publicznych statystyk. Przy każdej liczbie w lekcji jest numerowane źródło, które można otworzyć.'],
                        ['q' => 'Czy jest wersja angielska?', 'a' => 'Tak, „Poland, measured”: ten sam kurs po angielsku.'],
                    ],
                    'lists' => [
                        'sources' => ['Oficjalne statystyki publiczne', 'Każdy wykres i warstwa mapy podaje źródło', 'Przypisy w tekście lekcji prowadzą do danych', 'Tekst kursu na licencji CC BY 4.0'],
                        'features' => ['Poznaj mapę', 'Czytaj wykresy', 'Sprawdź się w quizie'],
                    ],
                ],
            ],
        ];
    }

    protected function tags(): array
    {
        return ['polska', 'dane', 'mapy', 'wykresy', 'za darmo'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image-pl.png', fn () => PolandArt::cover('Za darmo · mapa i wykresy', 'Polska w liczbach', 'Zmiany w publicznych danych')),
            'poster' => $this->assets->image('course-poster-pl.png', fn () => PolandArt::cover('Za darmo · mapa i wykresy', 'Polska w liczbach', 'Zmiany w publicznych danych', 1280, 720)),
            'teaser' => null,
        ];
    }

    protected function certificateName(): string
    {
        return 'Polska w liczbach: certyfikat';
    }

    /** The poland certificate with its labels in Polish. */
    protected function certificateContent(): string
    {
        $labels = [
            'Certificate of Completion' => 'Certyfikat ukończenia',
            'Poland, Measured certifies that' => 'Polska w liczbach potwierdza, że',
            'has read the map, checked the sources and completed' => 'przeczytał(a) mapę, sprawdził(a) źródła i ukończył(a) kurs',
            'Completed on' => 'Data ukończenia',
            'Certificate no.' => 'Nr certyfikatu',
            'Cartographer' => 'Kartograf',
            'Scan to verify' => 'Zeskanuj, aby zweryfikować',
        ];
        return $this->translate(CertificateTemplates::content($this->key()), $labels);
    }

    /** @param array<string, string> $labels */
    private function translate(string $json, array $labels): string
    {
        $template = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $walk = function (&$node) use (&$walk, $labels): void {
            if (!is_array($node)) {
                return;
            }
            if (isset($node['content']) && is_string($node['content']) && isset($labels[$node['content']])) {
                $node['content'] = $labels[$node['content']];
            }
            foreach ($node as &$child) {
                $walk($child);
            }
        };
        $walk($template);

        return json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
