<?php

declare(strict_types=1);

use Bpmore\ReadabilityCore\Rule\ConfiguredRule;
use Bpmore\ReadabilityCore\Rule\Finding;
use Bpmore\ReadabilityCore\Rule\Findings;
use Bpmore\ReadabilityCore\Rule\Location;
use Bpmore\ReadabilityCore\Rule\Rule;
use Bpmore\ReadabilityCore\Rule\RuleSet;
use Bpmore\ReadabilityCore\Rule\Severity;
use Bpmore\ReadabilityCore\Text\Document;

/**
 * A rule that flags every block whose text contains a word from its
 * options: enough to exercise the contracts without depending on any
 * real rule. Real rules get their own files and their own tests.
 */
final class FlagsWords extends ConfiguredRule
{
    public function handle(): string
    {
        return 'flags_words';
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Warning;
    }

    public function check(Document $document): array
    {
        $findings = [];

        foreach ($document->blocks as $index => $block) {
            foreach ((array) $this->option('words', []) as $word) {
                $offset = strpos($block->text, $word);

                if ($offset !== false) {
                    $findings[] = $this->finding('Say something plainer than "'.$word.'".', new Location($index, $offset, strlen($word)), $word, suggestion: 'use');
                }
            }
        }

        return $findings;
    }
}

/** A rule about the document as a whole: no location. */
final class CountsBlocks implements Rule
{
    public function handle(): string
    {
        return 'counts_blocks';
    }

    public function check(Document $document): array
    {
        return [new Finding($this->handle(), Severity::Tip, count($document).' blocks.')];
    }
}

function registry(): array
{
    return [
        'flags_words' => fn (array $options): Rule => new FlagsWords($options),
        'counts_blocks' => fn (array $options): Rule => new CountsBlocks,
    ];
}

it('lets a rule be tested on its own with a document and nothing else', function () {
    $rule = new FlagsWords(['words' => ['utilize']]);
    $findings = $rule->check(paragraphs('We utilize forms.', 'Fine.', 'Please utilize the door.'));

    expect($findings)->toHaveCount(2);
    expect($findings[0])->toBeInstanceOf(Finding::class);
    expect($findings[0]->rule)->toBe('flags_words');
    expect($findings[0]->severity)->toBe(Severity::Warning);
    expect($findings[0]->message)->toBe('Say something plainer than "utilize".');
    expect($findings[0]->location)->toEqual(new Location(0, 3, 7));
    expect($findings[0]->location->end())->toBe(10);
    expect($findings[0]->excerpt)->toBe('utilize');
    expect($findings[0]->suggestion)->toBe('use');
    expect($findings[1]->location->block)->toBe(2);
});

it('takes its severity from the config, or its own default without one, and refuses nonsense', function () {
    expect((new FlagsWords)->check(paragraphs('x'))[0] ?? null)->toBeNull();
    expect((new FlagsWords(['words' => ['x'], 'severity' => 'error']))->check(paragraphs('x'))[0]->severity)->toBe(Severity::Error);
    expect((new FlagsWords(['words' => ['x'], 'severity' => 'TIP']))->check(paragraphs('x'))[0]->severity)->toBe(Severity::Tip);
    expect((new FlagsWords(['words' => ['x'], 'severity' => '']))->check(paragraphs('x'))[0]->severity)->toBe(Severity::Warning);

    expect(fn () => new FlagsWords(['severity' => 'critical']))->toThrow(InvalidArgumentException::class, 'not a severity');
});

it('reads a whole-number option and falls back to the default on nonsense', function () {
    $rule = new class(['words' => 40, 'bad' => 'lots', 'zero' => 0]) extends ConfiguredRule
    {
        public function handle(): string
        {
            return 'probe';
        }

        protected function defaultSeverity(): Severity
        {
            return Severity::Tip;
        }

        public function check(Document $document): array
        {
            return [];
        }

        public function probe(): array
        {
            return [$this->intOption('words', 25), $this->intOption('bad', 25), $this->intOption('zero', 25), $this->intOption('missing', 25)];
        }
    };

    expect($rule->probe())->toBe([40, 25, 25, 25]);
});

it('runs every rule and returns what they found in reading order, whole-document findings last', function () {
    $set = new RuleSet([new CountsBlocks, new FlagsWords(['words' => ['utilize', 'leverage']])]);
    $findings = $set->check(paragraphs('Leverage it, then utilize it.', 'We leverage and utilize.'));

    expect($findings)->toHaveCount(4);
    expect(array_map(fn (Finding $f) => [$f->rule, $f->location?->block, $f->location?->offset], $findings->all()))->toBe([
        ['flags_words', 0, 18],
        ['flags_words', 1, 3],
        ['flags_words', 1, 16],
        ['counts_blocks', null, null],
    ]);
});

it('can be filtered by rule, severity, block, and scope', function () {
    $findings = (new RuleSet([new CountsBlocks, new FlagsWords(['words' => ['utilize']])]))->check(paragraphs('utilize', 'utilize'));

    expect($findings->ofRule('flags_words'))->toHaveCount(2);
    expect($findings->ofRule('counts_blocks', 'flags_words'))->toHaveCount(3);
    expect($findings->ofSeverity(Severity::Tip))->toHaveCount(1);
    expect($findings->ofSeverity(Severity::Error))->toHaveCount(0);
    expect($findings->inBlock(1))->toHaveCount(1);
    expect($findings->aboutWholeDocument())->toHaveCount(1);
    expect($findings->aboutWholeDocument()->all()[0]->isAboutWholeDocument())->toBeTrue();
    expect(Findings::none()->isEmpty())->toBeTrue();
    expect(Findings::none()->merge($findings))->toHaveCount(3);
    expect(iterator_to_array($findings))->toBe($findings->all());
});

it('is built from the rules config: options by handle, defaults for the unmentioned, enabled false to leave one out', function () {
    $set = RuleSet::fromConfig(['flags_words' => ['words' => ['utilize'], 'severity' => 'error']], registry());
    expect($set->handles())->toBe(['flags_words', 'counts_blocks']);
    expect($set->check(paragraphs('utilize'))->ofRule('flags_words')->all()[0]->severity)->toBe(Severity::Error);

    expect(RuleSet::fromConfig([], registry())->handles())->toBe(['flags_words', 'counts_blocks']);
    expect(RuleSet::fromConfig(['counts_blocks' => ['enabled' => false]], registry())->handles())->toBe(['flags_words']);
    expect(RuleSet::fromConfig(['counts_blocks' => false], registry())->handles())->toBe(['flags_words', 'counts_blocks']);
});

it('refuses a rule handle it has never heard of, naming the ones it has', function () {
    RuleSet::fromConfig(['long_sentance' => ['words' => 25]], registry());
})->throws(InvalidArgumentException::class, 'No rule is called "long_sentance". The rules are: flags_words, counts_blocks.');

it('ships these rules, in this order', function () {
    expect(RuleSet::fromConfig([])->handles())->toBe(['long_sentence', 'very_long_sentence']);
    expect(fn () => RuleSet::fromConfig(['anything' => []]))->toThrow(InvalidArgumentException::class, 'The rules are: long_sentence, very_long_sentence.');
    expect(RuleSet::fromConfig([], [])->handles())->toBe([]);
});

it('can be taken apart by handle and added to', function () {
    $set = new RuleSet([new CountsBlocks, new FlagsWords]);

    expect($set->without('counts_blocks')->handles())->toBe(['flags_words']);
    expect($set->without('counts_blocks')->with(new CountsBlocks)->handles())->toBe(['flags_words', 'counts_blocks']);
    expect($set->rules())->toHaveCount(2);
});

it('orders severities: error, warning, tip', function () {
    expect(Severity::Error->rank())->toBeLessThan(Severity::Warning->rank());
    expect(Severity::Warning->rank())->toBeLessThan(Severity::Tip->rank());
    expect(Severity::fromConfig(Severity::Tip, Severity::Error))->toBe(Severity::Tip);
});
