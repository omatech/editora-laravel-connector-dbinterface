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
        // A published object of a class that has no page controller, such as a part shown only inside other pages.
        if ($slug === 'element-without-page') {
            return ['id' => 43, 'type' => 'Instance', 'class_tag' => 'Element_Without_Page'];
        }
        $visible = $slug === 'published' || $this->preview;

        return ['id' => 42, 'type' => $visible ? 'Instance' : 'Error', 'class_tag' => $visible ? 'PreviewPage' : 'Error_404'];
    }

    public function otherLanguagesUrl(int $id, string $language): array
    {
        return [];
    }
}
