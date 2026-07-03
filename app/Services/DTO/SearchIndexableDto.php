<?php

namespace App\Services\DTO;

class SearchIndexableDto
{
    public string $id;
    public string $class;
    public ?string $organizationId;
    public string $module;
    public string $title;
    public ?string $description;
    public ?string $content;
    public ?string $url;
    public ?string $permissionRequired;
    public ?array $embedding;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->class = $data['class'] ?? 'GenericSearchable';
        $this->organizationId = $data['organization_id'] ?? null;
        $this->module = $data['module'] ?? 'general';
        $this->title = $data['title'];
        $this->description = $data['description'] ?? null;
        $this->content = $data['content'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->permissionRequired = $data['permission_required'] ?? null;
        $this->embedding = $data['embedding'] ?? null;
    }
}
