<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Collection;

class DocumentChainService
{
    private const MAX_UPSTREAM_STEPS = 8;

    private const MAX_DOWNSTREAM_RECORDS = 8;

    private const SUMMARY_COLUMNS = [
        'id',
        'type',
        'document_number',
        'status',
        'related_document_id',
        'issue_date',
    ];

    public function chainFor(Document $document): array
    {
        $visited = [];
        $truncated = false;

        $upstream = $this->upstream($document, $visited, $truncated);
        $downstream = $this->downstream($document, $visited);
        $nodes = $upstream->merge($downstream)->values();

        return [
            'has_chain' => $nodes->count() > 1,
            'truncated' => $truncated,
            'nodes' => $nodes->map(fn (Document $node) => $this->summary($node))->all(),
        ];
    }

    private function upstream(Document $document, array &$visited, bool &$truncated): Collection
    {
        $nodes = collect();
        $current = $document;

        for ($step = 0; $step < self::MAX_UPSTREAM_STEPS; $step++) {
            if (isset($visited[$current->id])) {
                $truncated = true;
                break;
            }

            $visited[$current->id] = true;
            $nodes->push($current);

            if (! $current->related_document_id) {
                break;
            }

            $parent = Document::query()
                ->select(self::SUMMARY_COLUMNS)
                ->find($current->related_document_id);

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        if ($nodes->count() >= self::MAX_UPSTREAM_STEPS && $current->related_document_id) {
            $truncated = true;
        }

        return $nodes->reverse()->values();
    }

    private function downstream(Document $document, array &$visited): Collection
    {
        return Document::query()
            ->select(self::SUMMARY_COLUMNS)
            ->where('related_document_id', $document->id)
            ->whereNotIn('id', array_keys($visited))
            ->orderBy('issue_date')
            ->orderBy('id')
            ->limit(self::MAX_DOWNSTREAM_RECORDS)
            ->get()
            ->each(function (Document $node) use (&$visited): void {
                $visited[$node->id] = true;
            });
    }

    private function summary(Document $document): array
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));

        return [
            'id' => $document->id,
            'document_number' => $document->document_number,
            'type_label' => $meta['singular'],
            'status' => $document->status,
            'status_label' => $document->statusDisplay(),
        ];
    }
}
