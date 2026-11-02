<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Medicine interaction reference (feature 52) and the symptom questionnaire
 * (feature 54). Both are deliberately conservative: the interaction list is
 * small and says so; the questionnaire sorts answers into four kinds of
 * next step and never names a condition.
 */
final class ClinicalReferenceService
{
    public const TRIAGE_OUTCOMES = [
        'emergency' => 'Call 112 now',
        'urgent' => 'See a doctor today',
        'test' => 'Get tested and book a consultation',
        'self_care' => 'Look after yourself at home and watch for changes',
    ];
    public const RED_FLAGS = [
        'breathing' => 'Severe difficulty breathing, or breathing that is getting rapidly worse',
        'chest' => 'Chest pain or pressure that does not go away',
        'confusion' => 'New confusion, or hard to wake',
        'lips' => 'Bluish lips or face',
        'bleeding' => 'Bleeding that will not stop',
        'stroke' => 'Face drooping, arm weakness or slurred speech',
    ];
    public const SYMPTOMS = [
        'fever' => 'Fever', 'cough' => 'Cough', 'sore_throat' => 'Sore throat', 'breathless' => 'Breathless on light activity',
        'body_aches' => 'Body or joint aches', 'rash' => 'Rash', 'vomiting' => 'Vomiting', 'diarrhoea' => 'Diarrhoea',
        'headache' => 'Headache', 'taste_smell' => 'Loss of taste or smell', 'bleeding_gums' => 'Bleeding gums or nose', 'abdominal_pain' => 'Stomach pain',
    ];
    public const RISKS = ['age_60' => '60 or older', 'pregnant' => 'Pregnant', 'chronic' => 'Long-term heart, lung, kidney or liver condition, or diabetes', 'immune' => 'Weakened immune system or on treatment that lowers immunity'];

    /** @var array<string,mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return $this->data ??= json_decode((string) file_get_contents(VQ_ROOT . '/database/seeds/drug-interactions.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function normalise(string $name): string
    {
        $n = mb_strtolower(trim((string) preg_replace('/\s+\d+(\.\d+)?\s*(mg|mcg|g|ml)\b.*$/i', '', $name)));
        return $this->data()['aliases'][$n] ?? $n;
    }

    /**
     * @param list<string> $names
     * @return array{medicines:list<string>,found:list<array<string,string>>,unknown:list<string>}
     */
    public function interactions(array $names): array
    {
        $d = $this->data();
        $meds = array_values(array_unique(array_filter(array_map(fn ($n) => $this->normalise((string) $n), $names), static fn ($n) => $n !== '' && mb_strlen($n) <= 60)));
        if (count($meds) < 2 || count($meds) > 8) {
            throw new ValidationFailed(['medicines' => 'Enter between two and eight medicines.']);
        }
        $memberOf = static function (string $med, string $key) use ($d): bool {
            return $med === $key || in_array($med, $d['groups'][$key] ?? [], true);
        };
        $known = [];
        foreach ($d['interactions'] as $i) {
            foreach ([$i['a'], $i['b']] as $k) {
                $known[$k] = true;
                foreach ($d['groups'][$k] ?? [] as $m) {
                    $known[$m] = true;
                }
            }
        }
        $found = [];
        for ($x = 0; $x < count($meds); $x++) {
            for ($y = $x + 1; $y < count($meds); $y++) {
                foreach ($d['interactions'] as $i) {
                    $hit = ($memberOf($meds[$x], $i['a']) && $memberOf($meds[$y], $i['b'])) || ($memberOf($meds[$y], $i['a']) && $memberOf($meds[$x], $i['b']));
                    if ($hit) {
                        $found[] = ['pair' => $meds[$x] . ' + ' . $meds[$y], 'severity' => $i['severity'], 'effect' => $i['effect'], 'advice' => $i['advice']];
                        break;
                    }
                }
            }
        }
        $rank = ['contraindicated' => 0, 'major' => 1, 'moderate' => 2];
        usort($found, static fn ($a, $b) => ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));
        return ['medicines' => $meds, 'found' => $found, 'unknown' => array_values(array_filter($meds, static fn ($m) => !isset($known[$m])))];
    }

    /**
     * Rule-based next-step guidance. Red flags win; otherwise severity,
     * duration and risk factors decide between same-day care, testing and
     * self-care.
     *
     * @param array<string,mixed> $input red_flags[], symptoms[], days, risks[], worse
     * @return array{outcome:string,reasons:list<string>}
     */
    public static function triage(array $input): array
    {
        $pick = static fn (string $k, array $allowed): array => array_values(array_intersect(is_array($input[$k] ?? null) ? array_map('strval', $input[$k]) : [], array_keys($allowed)));
        $flags = $pick('red_flags', self::RED_FLAGS);
        $symptoms = $pick('symptoms', self::SYMPTOMS);
        $risks = $pick('risks', self::RISKS);
        $days = (int) ($input['days'] ?? 0);
        $worse = ($input['worse'] ?? '') === '1';
        $reasons = [];
        if ($flags !== []) {
            foreach ($flags as $f) {
                $reasons[] = self::RED_FLAGS[$f];
            }
            return ['outcome' => 'emergency', 'reasons' => $reasons];
        }
        if ($symptoms === []) {
            return ['outcome' => 'self_care', 'reasons' => ['No symptoms were chosen.']];
        }
        $urgent = [];
        if (in_array('breathless', $symptoms, true)) {
            $urgent[] = 'Breathless on light activity';
        }
        if (in_array('bleeding_gums', $symptoms, true) && in_array('fever', $symptoms, true)) {
            $urgent[] = 'Fever with bleeding from the gums or nose';
        }
        if (in_array('fever', $symptoms, true) && $days >= 3 && $risks !== []) {
            $urgent[] = 'Fever for three days or more with a risk factor';
        }
        if ($worse && $risks !== []) {
            $urgent[] = 'Getting worse, with a risk factor';
        }
        if (in_array('vomiting', $symptoms, true) && in_array('diarrhoea', $symptoms, true) && in_array('age_60', $risks, true)) {
            $urgent[] = 'Vomiting and diarrhoea at 60 or older (risk of dehydration)';
        }
        if ($urgent !== []) {
            return ['outcome' => 'urgent', 'reasons' => $urgent];
        }
        if (in_array('fever', $symptoms, true) || $days >= 7 || $worse) {
            $reasons[] = in_array('fever', $symptoms, true) ? 'Fever' : ($days >= 7 ? 'Symptoms for a week or more' : 'Symptoms getting worse');
            return ['outcome' => 'test', 'reasons' => $reasons];
        }
        return ['outcome' => 'self_care', 'reasons' => ['Mild symptoms for less than a week, with no warning signs']];
    }

    /** @param array<string,mixed> $input */
    public function saveTriage(int $userId, array $input, string $outcome): void
    {
        $keep = array_intersect_key($input, array_flip(['red_flags', 'symptoms', 'days', 'risks', 'worse']));
        $this->app->db->insert('INSERT INTO triage_assessments (user_id, answers, outcome, created_at) VALUES (:u, :a, :o, :t)',
            ['u' => $userId, 'a' => mb_substr((string) json_encode($keep), 0, 2000), 'o' => $outcome, 't' => $this->app->clock->nowSql()]);
    }
}
