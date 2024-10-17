<?php

namespace gorriecoe\Embed\Service;

interface Fetcher
{
    /**
     * Fetch embed data from URL, returning a keyed array due to unknown keys
     * which allows for more freedom in returned data
     * (since PHP 8.2 deprecates dynamic properties)
     *
     * @param string $url
     * @return array
     */
    public function fetchFrom(string $url): array;
}
