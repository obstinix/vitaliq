<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use InvalidArgumentException;

/**
 * Plain PHP templates. Templates must escape every dynamic value with e().
 */
final class View
{
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(private readonly string $directory)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'public'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile('layouts/' . $layout, ['content' => $content] + $data);
    }

    /** @param array<string,mixed> $data */
    public function partial(string $name, array $data = []): string
    {
        return $this->renderFile('partials/' . $name, $data);
    }

    /** @param array<string,mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $template)) {
            throw new InvalidArgumentException('Invalid template name.');
        }
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new InvalidArgumentException('Template not found: ' . $template);
        }
        $vars = $data + $this->shared;
        $vars['view'] = $this;
        $render = static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            $render($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
