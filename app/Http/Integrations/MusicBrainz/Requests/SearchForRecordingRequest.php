<?php

namespace App\Http\Integrations\MusicBrainz\Requests;

use App\Helpers\LuceneQuery;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchForRecordingRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $title,
        private readonly ?string $artistName = null,
        private readonly ?string $albumName = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/recording';
    }

    /** @inheritdoc */
    protected function defaultQuery(): array
    {
        $terms = ['recording:' . LuceneQuery::phrase($this->title)];

        if ($this->artistName) {
            $terms[] = 'artist:' . LuceneQuery::phrase($this->artistName);
        }

        if ($this->albumName) {
            $terms[] = 'release:' . LuceneQuery::phrase($this->albumName);
        }

        return [
            'query' => implode(' AND ', $terms),
            'limit' => 5,
        ];
    }
}
