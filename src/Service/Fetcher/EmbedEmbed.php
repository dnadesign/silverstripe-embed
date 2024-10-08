<?php

namespace gorriecoe\Embed\Service\Fetcher;

use Embed\Embed;
use gorriecoe\Embed\Service\Fetcher;

class EmbedEmbed implements Fetcher
{
    public function fetchFrom(string $url): object
    {
        return (new Embed())->get($url);
    }
}
