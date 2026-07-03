<?php

namespace App\Services\DTO;

use App\Models\SearchIndex;

class SearchResultDto implements \JsonSerializable
{
    public string $id;
    public ?string $searchableId;
    public ?string $searchableType;
    public string $module;
    public string $title;
    public ?string $description;
    public ?string $url;
    public float $score;
    public ?string $highlight;
    public string $createdAt;

    public function __construct(
        string $id,
        ?string $searchableId,
        ?string $searchableType,
        string $module,
        string $title,
        ?string $description,
        ?string $url,
        float $score,
        ?string $highlight,
        string $createdAt
    ) {
        $this->id = $id;
        $this->searchableId = $searchableId;
        $this->searchableType = $searchableType;
        $this->module = $module;
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
        $this->score = $score;
        $this->highlight = $highlight;
        $this->createdAt = $createdAt;
    }

    /**
     * Map a SearchIndex Eloquent model or raw array to a SearchResultDto.
     */
    public static function fromModel(SearchIndex $model, float $score = 1.0, ?string $highlight = null): self
    {
        return new self(
            $model->id,
            $model->searchable_id,
            $model->searchable_type,
            $model->module,
            $model->title,
            $model->description,
            $model->url,
            $score ?? $model->score ?? 1.0,
            $highlight ?? $model->highlight ?? $model->description,
            $model->created_at ? $model->created_at->toIso8601String() : now()->toIso8601String()
        );
    }

    /**
     * Serialize the DTO for JSON responses.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'searchable_id' => $this->searchableId,
            'searchable_type' => $this->searchableType,
            'module' => $this->module,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'score' => $this->score,
            'highlight' => $this->highlight,
            'created_at' => $this->createdAt,
        ];
    }
}
