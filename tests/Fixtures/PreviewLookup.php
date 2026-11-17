<?php

namespace Tests\Fixtures;

final class PreviewLookup
{
    public bool $preview = false;
    public int $lookups = 0;

    public function setParams(array $params): void
    {
        $this->preview = $params['preview'];
    }

    public function getUrlData(string $language, string $slug): array
    {
        $this->lookups++;
        $visible = $slug === 'published' || $this->preview;

        return ['id' => 42, 'type' => $visible ? 'Instance' : 'Error', 'class_tag' => $visible ? 'PreviewPage' : 'Error_404'];
    }

    public function otherLanguagesUrl(int $id, string $language): array
    {
        return [];
    }
}
