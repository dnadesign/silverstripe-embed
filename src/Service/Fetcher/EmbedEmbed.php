<?php

namespace gorriecoe\Embed\Service\Fetcher;

use Embed\Embed;
use gorriecoe\Embed\Service\Fetcher;

class EmbedEmbed implements Fetcher
{
    public function fetchFrom(string $url): array
    {
        $embed = (new Embed())->get($url);
        $oembed = $embed->getOEmbed()->all();
        $description = $embed->description;
        if (!empty($description)) {
            $oembed['description'] = $description;
        }
        return $oembed;
    }
}
