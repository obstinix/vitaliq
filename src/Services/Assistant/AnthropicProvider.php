<?php

declare(strict_types=1);

namespace Vitaliq\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use RuntimeException;

/**
 * Claude through the official Anthropic PHP SDK. Low effort suits short
 * conversational answers; server-side fallbacks ("default") let the API
 * retry on its configured substitute if the model declines for policy
 * reasons, so a cautious decline on a health question does not leave the
 * person without an answer.
 */
final class AnthropicProvider implements AssistantProvider
{
    public function __construct(private readonly string $apiKey, private readonly string $model)
    {
    }

    public function name(): string
    {
        return 'Anthropic Claude (' . $this->model . ')';
    }

    public function reply(string $system, array $messages): array
    {
        $client = new Client(apiKey: $this->apiKey);
        try {
            $message = $client->beta->messages->create(
                model: $this->model,
                maxTokens: 16000,
                system: $system,
                messages: $messages,
                outputConfig: ['effort' => 'low'],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException $e) {
            throw new RuntimeException('The assistant is busy. Please try again in a minute.', 0, $e);
        } catch (APIStatusException $e) {
            throw new RuntimeException('The assistant service returned an error.', 0, $e);
        } catch (APIConnectionException $e) {
            throw new RuntimeException('The assistant service could not be reached.', 0, $e);
        }
        if ($message->stopReason === 'refusal') {
            return ['text' => '', 'refused' => true];
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        return ['text' => trim($text), 'refused' => false];
    }
}
