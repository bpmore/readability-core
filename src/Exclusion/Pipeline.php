<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Exclusion;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * The exclusions a site has configured, applied in order. Whole blocks go
 * first, so the text-level work runs over less text; proper names go last,
 * because they are for the formulas alone and the rules want the document
 * before them: `$pipeline->without(ProperNames::class)`.
 */
final class Pipeline implements Exclusion
{
    /**
     * @param  list<Exclusion>  $exclusions
     */
    public function __construct(private readonly array $exclusions) {}

    /**
     * From the `exclude` section of a site's config. Every kind is on unless
     * switched off; the lists are empty unless filled.
     *
     *     'exclude' => [
     *         'code' => true, 'blockquotes' => true, 'references' => true,
     *         'addresses' => true, 'figures' => true, 'proper_names' => true,
     *         'html_classes' => ['no-readability'], 'bard_sets' => ['code', 'citation'],
     *     ]
     *
     * The extractors need two of these too, for what an exclusion cannot
     * reach once it is inside a block: `html_classes` and, when `code` is
     * on, `Code::INLINE_TAGS` and `Code::INLINE_MARKS`.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $on = static fn (string $key): bool => (bool) ($config[$key] ?? true);
        $list = static fn (string $key): array => array_values(array_filter(array_map(strval(...), (array) ($config[$key] ?? [])), static fn (string $s): bool => $s !== ''));

        $exclusions = [];

        if ($list('html_classes') !== []) {
            $exclusions[] = new IgnoredClasses($list('html_classes'));
        }

        if ($list('bard_sets') !== []) {
            $exclusions[] = new IgnoredSets($list('bard_sets'));
        }

        if ($on('code')) {
            $exclusions[] = new Code;
        }

        if ($on('blockquotes')) {
            $exclusions[] = new Quotes;
        }

        if ($on('references')) {
            $exclusions[] = new References;
        }

        if ($on('figures')) {
            $exclusions[] = new FiguresInTables;
        }

        if ($on('addresses')) {
            $exclusions[] = new Addresses;
        }

        if ($on('proper_names')) {
            $exclusions[] = new ProperNames;
        }

        return new self($exclusions);
    }

    /** The same pipeline with these added at the end. */
    public function with(Exclusion ...$exclusions): self
    {
        return new self([...$this->exclusions, ...array_values($exclusions)]);
    }

    /**
     * The same pipeline without the exclusions of these classes.
     *
     * @param  class-string<Exclusion>  ...$classes
     */
    public function without(string ...$classes): self
    {
        return new self(array_values(array_filter(
            $this->exclusions,
            static fn (Exclusion $exclusion): bool => ! in_array($exclusion::class, $classes, true),
        )));
    }

    /**
     * @return list<Exclusion>
     */
    public function exclusions(): array
    {
        return $this->exclusions;
    }

    public function apply(Document $document): Document
    {
        foreach ($this->exclusions as $exclusion) {
            $document = $exclusion->apply($document);
        }

        return $document;
    }
}
