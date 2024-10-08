<?php

namespace gorriecoe\Embed\Test;

use gorriecoe\Embed\Models\Embed;
use gorriecoe\Embed\Service\Fetcher;
use SilverStripe\Dev\SapphireTest;

class EmbeddableTest extends SapphireTest
{
    private const FAKE_EMBED = [
        'title' => 'Test title',
        'description' => '',
        'type' => '',
        'code' => [
            'html' => '',
            'width' => '',
            'height' => '',
            'ratio' => '',
        ],
    ];

    private function embedData()
    {
        $fakeEmbed = (object)self::FAKE_EMBED;
        $fakeEmbed->code = (object)$fakeEmbed->code;
        return $fakeEmbed;
    }

    public function testSettingEmbedFetchersWorks()
    {
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->expects($this->once())->method('fetchFrom')->willReturn($this->embedData());
        $embed = new Embed();
        $embed->setFetcher($embedSpy);
        $embed->EmbedSourceURL = 'test.test';
        $embed->onBeforeWrite();
        $this->assertSame('Test title', $embed->EmbedTitle);
    }
}
