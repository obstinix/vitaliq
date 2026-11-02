<?php

declare(strict_types=1);

namespace Vitaliq\Services\Assistant;

/** A model that answers health-information questions (feature 51). */
interface AssistantProvider
{
    public function name(): string;

    /**
     * @param list<array{role:string,content:string}> $messages oldest first, ending with the user's question
     * @return array{text:string,refused:bool}
     */
    public function reply(string $system, array $messages): array;
}
