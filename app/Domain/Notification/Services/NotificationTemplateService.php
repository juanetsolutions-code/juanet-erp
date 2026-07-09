<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Repositories\NotificationTemplateRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Blade;

class NotificationTemplateService
{
    protected NotificationTemplateRepositoryInterface $repository;

    public function __construct(NotificationTemplateRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create or update a template.
     */
    public function saveTemplate(array $data): NotificationTemplate
    {
        if (isset($data['id'])) {
            $template = $this->repository->update($data['id'], $data);
        } else {
            $template = $this->repository->create($data);
        }
        return $template;
    }

    /**
     * Render a template with dynamic variables.
     */
    public function render(string $templateName, array $variables, ?string $organizationId = null): array
    {
        $template = $this->repository->findByName($templateName, $organizationId);

        if (!$template) {
            // Fallback: generate default body from variables/name
            $subject = 'Notification: ' . Str::title(str_replace('.', ' ', $templateName));
            $bodyMarkdown = 'Event occurred on ' . $templateName . '. Details: ' . json_encode($variables);
            $bodyHtml = '<p>' . e($bodyMarkdown) . '</p>';
        } else {
            $subject = $this->compileString($template->subject ?? '', $variables);
            $bodyMarkdown = $this->compileString($template->body_markdown, $variables);
            
            if ($template->body_html) {
                $bodyHtml = $this->compileString($template->body_html, $variables);
            } else {
                // Compile Markdown to HTML
                $bodyHtml = Str::markdown($bodyMarkdown);
            }
        }

        return [
            'subject' => $subject,
            'markdown' => $bodyMarkdown,
            'html' => $bodyHtml,
        ];
    }

    /**
     * Helper to replace {{ variable }} placeholders or evaluate Blade if needed.
     */
    protected function compileString(string $string, array $variables): string
    {
        // Simple mustache replacement
        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $string = str_replace('{{ ' . $key . ' }}', (string)$value, $string);
                $string = str_replace('{{' . $key . '}}', (string)$value, $string);
            } elseif (is_array($value)) {
                $string = str_replace('{{ ' . $key . ' }}', json_encode($value), $string);
            }
        }

        // Support blade rendering if string contains @ or is_numeric
        try {
            if (Str::contains($string, ['@if', '@foreach', '{{ $'])) {
                return Blade::render($string, $variables);
            }
        } catch (\Throwable $e) {
            // Fallback to basic string if blade rendering fails
        }

        return $string;
    }
}
