<?php

declare(strict_types=1);

namespace Bpmore\ReadabilityCore\Rule;

use Bpmore\ReadabilityCore\Text\Document;

/**
 * A verb hiding in a noun. "Make a decision" is "decide" with a word in
 * front and a word behind; "the implementation of the plan" is somebody
 * implementing it, unsaid. Nouns in "-tion", "-ment", "-ance" and their
 * kin are where verbs go to hide, and a sentence built on them has no
 * one doing anything.
 *
 * Two shapes are found: a light verb and the noun ("make a decision",
 * "conduct an investigation"), and the noun with "of" after it ("the
 * implementation of"). Only nouns on the list count: the ending alone
 * would take "make an appointment" and "the department of health" for
 * nominalizations, and they are not. A tip, with the verb to say
 * instead.
 */
final class Nominalization extends ConfiguredRule
{
    public const HANDLE = 'nominalization';

    /**
     * Common nominalizations and the verb inside each.
     *
     * @var array<string, string>
     */
    public const VERBS = [
        'decision' => 'decide', 'investigation' => 'investigate', 'explanation' => 'explain', 'consideration' => 'consider',
        'agreement' => 'agree', 'assessment' => 'assess', 'evaluation' => 'evaluate', 'implementation' => 'implement',
        'recommendation' => 'recommend', 'application' => 'apply', 'payment' => 'pay', 'improvement' => 'improve',
        'reduction' => 'reduce', 'provision' => 'provide', 'performance' => 'perform', 'assistance' => 'assist',
        'referral' => 'refer', 'approval' => 'approve', 'arrival' => 'arrive', 'discussion' => 'discuss',
        'submission' => 'submit', 'collection' => 'collect', 'completion' => 'complete', 'notification' => 'notify',
        'registration' => 'register', 'confirmation' => 'confirm', 'cancellation' => 'cancel', 'determination' => 'determine',
        'examination' => 'examine', 'treatment' => 'treat', 'adjustment' => 'adjust', 'measurement' => 'measure',
        'requirement' => 'require', 'statement' => 'state', 'announcement' => 'announce', 'appearance' => 'appear',
        'acceptance' => 'accept', 'attendance' => 'attend', 'compliance' => 'comply', 'preference' => 'prefer',
        'occurrence' => 'occur', 'reference' => 'refer', 'failure' => 'fail', 'removal' => 'remove', 'renewal' => 'renew',
        'denial' => 'deny', 'proposal' => 'propose', 'disposal' => 'dispose', 'selection' => 'select', 'protection' => 'protect',
        'prevention' => 'prevent', 'detection' => 'detect', 'inspection' => 'inspect', 'connection' => 'connect',
        'correction' => 'correct', 'description' => 'describe', 'prescription' => 'prescribe', 'preparation' => 'prepare',
        'administration' => 'administer', 'authorization' => 'authorize', 'utilization' => 'use', 'verification' => 'verify',
        'identification' => 'identify', 'modification' => 'modify', 'clarification' => 'clarify', 'specification' => 'specify',
        'demonstration' => 'demonstrate', 'participation' => 'participate', 'communication' => 'communicate',
        'consultation' => 'consult', 'observation' => 'observe', 'transmission' => 'transmit', 'admission' => 'admit',
        'permission' => 'permit', 'response' => 'respond', 'analysis' => 'analyze', 'delivery' => 'deliver',
        'development' => 'develop', 'management' => 'manage', 'enrollment' => 'enroll', 'employment' => 'employ',
        'commitment' => 'commit', 'achievement' => 'achieve', 'establishment' => 'establish', 'involvement' => 'involve',
        'maintenance' => 'maintain', 'guidance' => 'guide',
        'attempt' => 'try', 'request' => 'ask', 'inquiry' => 'ask', 'enquiry' => 'ask',
    ];

    /** @var list<string> Verbs that carry a nominalization: "make a decision", "conduct an investigation". */
    private const LIGHT_VERBS = [
        'make', 'makes', 'made', 'making', 'take', 'takes', 'took', 'taken', 'taking', 'give', 'gives', 'gave', 'given', 'giving',
        'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'provide', 'provides', 'provided', 'providing',
        'perform', 'performs', 'performed', 'performing', 'conduct', 'conducts', 'conducted', 'conducting',
        'reach', 'reaches', 'reached', 'reaching', 'undertake', 'undertakes', 'undertook', 'undertaken', 'undertaking',
        'carry out', 'carries out', 'carried out', 'carrying out', 'come to', 'comes to', 'came to', 'coming to',
        'offer', 'offers', 'offered', 'offering', 'submit', 'submits', 'submitted', 'submitting', 'issue', 'issues', 'issued', 'issuing',
        'put in', 'puts in', 'obtain', 'obtains', 'obtained', 'obtaining',
    ];

    private readonly string $lightVerbPattern;

    private readonly string $ofPattern;

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $verbs = implode('|', array_map(static fn (string $v): string => str_replace(' ', '\s+', preg_quote($v, '/')), self::LIGHT_VERBS));
        $nouns = '(?:'.implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), array_keys(self::VERBS))).')';

        // "make a careful decision", "conducted an investigation", "gave consideration"
        $this->lightVerbPattern = '/(?<![\p{L}\p{N}])(?:'.$verbs.')\s+(?:(?:a|an|the|this|that|your|our|their|its|his|her|my|some|any|no|full|careful|proper|further|final|formal|initial|thorough|prompt|early)\s+){0,2}('.$nouns.')(?![\p{L}\p{N}])/iu';

        // "the implementation of", "our assessment of"
        $this->ofPattern = '/(?<![\p{L}\p{N}])(?:the|this|that|your|our|their|its|his|her|my|any|no)\s+('.$nouns.')\s+of(?![\p{L}\p{N}])/iu';
    }

    public function handle(): string
    {
        return self::HANDLE;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Tip;
    }

    public function check(Document $document): array
    {
        $findings = [];

        foreach ($document->blocks as $index => $block) {
            $taken = [];

            foreach ([$this->lightVerbPattern, $this->ofPattern] as $pattern) {
                preg_match_all($pattern, $block->text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

                foreach ($matches as $match) {
                    [$written, $offset] = $match[0];
                    $noun = strtolower($match[1][0]);
                    $end = $offset + strlen($written);

                    foreach ($taken as [$from, $to]) {
                        if ($offset < $to && $end > $from) {
                            continue 2;
                        }
                    }

                    $taken[] = [$offset, $end];
                    $verb = self::VERBS[$noun];

                    $findings[] = $this->finding(
                        sprintf('"%s" hides the verb. Say "%s".', $written, $verb),
                        new Location($index, $offset, strlen($written)),
                        $written,
                        $verb,
                    );
                }
            }
        }

        usort($findings, static fn (Finding $a, Finding $b): int => [$a->location?->block, $a->location?->offset] <=> [$b->location?->block, $b->location?->offset]);

        return $findings;
    }
}
