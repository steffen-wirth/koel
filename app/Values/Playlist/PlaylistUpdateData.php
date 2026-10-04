<?php

namespace App\Values\Playlist;

use App\Values\SmartPlaylist\SmartPlaylistRuleGroupCollection;
use App\Values\SmartPlaylist\SmartPlaylistSelection;
use Illuminate\Contracts\Support\Arrayable;

final readonly class PlaylistUpdateData implements Arrayable
{
    private function __construct(
        public string $name,
        public string $description,
        public ?string $folderId,
        public ?string $folderName,
        public ?string $cover,
        public ?SmartPlaylistRuleGroupCollection $ruleGroups,
        public ?SmartPlaylistSelection $selection = null,
    ) {}

    public static function make(
        string $name,
        string $description = '',
        ?string $folderId = null,
        ?string $folderName = null,
        ?string $cover = null,
        ?SmartPlaylistRuleGroupCollection $ruleGroups = null,
        ?SmartPlaylistSelection $selection = null,
    ): self {
        return new self(
            name: $name,
            description: $description,
            folderId: $folderId,
            folderName: $folderName,
            cover: $cover,
            ruleGroups: $ruleGroups,
            selection: $selection,
        );
    }

    /** @inheritdoc */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'folder_id' => $this->folderId,
            'cover' => $this->cover,
            'rule_groups' => $this->ruleGroups,
            'selection' => $this->selection,
        ];
    }
}
