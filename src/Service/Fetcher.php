<?php

namespace gorriecoe\Embed\Service;

interface Fetcher
{
    public function fetchFrom(string $url): object;
}
