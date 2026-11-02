<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Services\Assistant\AnthropicProvider;
use Vitaliq\Services\Assistant\AssistantProvider;

/**
 * Health information assistant (feature 51). The provider is configured
 * with AI_PROVIDER (none | anthropic), AI_API_KEY and AI_MODEL. Before any
 * provider is called, the question is screened for emergency signs; those
 * get fixed emergency guidance and are never sent out. The assistant gives
 * general information only, never a diagnosis or a prescription, and its
 * answers are not checked by a clinician.
 */
final class AssistantService
{
    public const DISCLAIMER = 'General health information only, not a diagnosis or medical advice, and not checked by a clinician. In an emergency in India, call 112.';
    public const MAX_CHARS = 1000;
    public const PER_HOUR = 20;

    /** Phrases that suggest an emergency; matched case-insensitively. */
    private const EMERGENCY = [
        'chest pain', 'chest tightness', "can't breathe", 'cannot breathe', 'not breathing', 'difficulty breathing', 'struggling to breathe',
        'unconscious', 'fainted', 'seizure', 'fitting', 'stroke', 'face drooping', 'slurred speech', 'severe bleeding', 'bleeding heavily',
        'suicide', 'kill myself', 'end my life', 'self harm', 'self-harm', 'overdose', 'poisoned', 'blue lips', 'anaphylaxis', 'throat swelling',
    ];

    private const SYSTEM = <<<'TXT'
        You are the health information assistant in Vitaliq, a testing and health records service in India. You are talking with a patient or a family member.
        - Give clear, general health information in plain language, in a few short paragraphs at most.
        - You do not diagnose, prescribe, or change anyone's treatment. When symptoms, results or medicines need a judgment, say plainly that a doctor or pharmacist should advise, and say how soon (today, within a few days, or at the next routine visit).
        - If anything suggests an emergency, tell the person to call 112 or go to the nearest emergency department now, before anything else.
        - You cannot see the person's records, test results or appointments. Do not claim to. If they ask about a specific result, explain what the test generally measures and suggest discussing the result with their doctor.
        - Vitaliq can book COVID-19 tests, laboratory tests and appointments (including video teleconsultations), and keeps a health passport and document vault. Point to these when they genuinely help.
        - If you are unsure, say so. Do not invent facts, figures or sources.
        - Answer in the language the person writes in.
        TXT;

    private ?AssistantProvider $provider = null;

    public function __construct(private readonly App $app)
    {
    }

    /** Replace the provider (tests, or a deployment-specific adapter). */
    public function useProvider(?AssistantProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function provider(): ?AssistantProvider
    {
        if ($this->provider !== null) {
            return $this->provider;
        }
        $c = $this->app->config['ai'] ?? [];
        if (($c['provider'] ?? 'none') === 'anthropic' && ($c['api_key'] ?? '') !== '') {
            return $this->provider = new AnthropicProvider((string) $c['api_key'], (string) ($c['model'] ?: 'claude-opus-5-5'));
        }
        return null;
    }

    public static function isEmergency(string $text): bool
    {
        $t = mb_strtolower($text);
        foreach (self::EMERGENCY as $phrase) {
            if (str_contains($t, $phrase)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    public function history(int $userId, int $limit = 30): array
    {
        return array_reverse($this->app->db->all('SELECT * FROM assistant_messages WHERE user_id = :u ORDER BY id DESC LIMIT :l', ['u' => $userId, 'l' => $limit]));
    }

    public function clear(int $userId): void
    {
        $this->app->db->execute('DELETE FROM assistant_messages WHERE user_id = :u', ['u' => $userId]);
    }

    /** @return array{text:string,source:string} */
    public function ask(int $userId, string $question): array
    {
        $question = trim((string) preg_replace('/[^\P{Cc}\n]/u', '', $question));
        if ($question === '' || mb_strlen($question) > self::MAX_CHARS) {
            throw new ValidationFailed(['question' => 'Write a question of up to ' . self::MAX_CHARS . ' characters.']);
        }
        if (!$this->app->rateLimiter()->hit('assistant:' . $userId, self::PER_HOUR, 3600)) {
            throw new ValidationFailed(['question' => 'You can ask ' . self::PER_HOUR . ' questions an hour. Please try again later.']);
        }
        $this->store($userId, 'user', $question, 'provider');

        if (self::isEmergency($question)) {
            $answer = 'What you describe could be an emergency. Call 112 now, or go to the nearest emergency department. If someone is with you, ask them to help. Do not wait for an online answer.';
            $this->store($userId, 'assistant', $answer, 'safety');
            return ['text' => $answer, 'source' => 'safety'];
        }

        $provider = $this->provider();
        if ($provider === null) {
            $answer = 'The assistant is not switched on for this service yet. For questions about symptoms, use "Check symptoms"; for anything urgent, call 112; to talk to a clinician, book a teleconsultation.';
            $this->store($userId, 'assistant', $answer, 'unavailable');
            return ['text' => $answer, 'source' => 'unavailable'];
        }

        // Last 10 turns as context; the provider never sees names or records.
        $messages = [];
        foreach (array_slice($this->history($userId, 20), -10) as $m) {
            if ($m['source'] === 'provider' || $m['role'] === 'user') {
                $messages[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
            }
        }
        try {
            $r = $provider->reply(self::SYSTEM, $messages);
        } catch (RuntimeException $e) {
            $this->app->logger->log('warning', 'Assistant provider failed: ' . $e->getMessage());
            $answer = $e->getMessage() . ' For anything urgent, call 112.';
            $this->store($userId, 'assistant', $answer, 'unavailable');
            return ['text' => $answer, 'source' => 'unavailable'];
        }
        $answer = $r['refused'] || $r['text'] === ''
            ? 'I cannot help with that question. Please ask a doctor or pharmacist, or book a teleconsultation.'
            : mb_substr($r['text'], 0, 6000);
        $this->store($userId, 'assistant', $answer, $r['refused'] ? 'safety' : 'provider');
        return ['text' => $answer, 'source' => $r['refused'] ? 'safety' : 'provider'];
    }

    private function store(int $userId, string $role, string $content, string $source): void
    {
        $this->app->db->insert('INSERT INTO assistant_messages (user_id, role, content, source, created_at) VALUES (:u, :r, :c, :s, :t)',
            ['u' => $userId, 'r' => $role, 'c' => $content, 's' => $source, 't' => $this->app->clock->nowSql()]);
    }
}
