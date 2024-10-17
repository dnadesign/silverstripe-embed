<?php

namespace gorriecoe\Embed\Test\http;

use gorriecoe\Embed\Models\Embed;
use gorriecoe\Embed\Service\Fetcher;
use RuntimeException;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Injector\Factory;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class AssetIntegrationTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testOnBeforeWriteWithSavePhoto()
    {
        $return = [
            'type' => 'photo',
            'version' => '1.0',
            'title' => 'Test photo',
            'url' => __DIR__ . '/fixtures/photo.png',
            'width' => '338',
            'height' => '248',
        ];
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->method('fetchFrom')->willReturn($return);
        $embed = new Embed();
        $embed->setFetcher($embedSpy);
        $config = $embed::config();
        $config->set('cache_image_as_asset', true);
        $config->set('embed_folder', 'test-photo');
        $embed->EmbedSourceURL = 'test.test';

        $this->assertCount(0, Image::get());
        $this->assertCount(0, Folder::get());

        $embed->write();

        $this->assertCount(1, Image::get());
        $this->assertCount(1, Folder::get());
        $photo = Image::get()->first();
        $folder = $photo->Parent();
        $this->assertSame('Test photo', $photo->Title);
        $this->assertSame('test-photo', $folder->Title);
        $this->assertSame('test-photo/photo.png', $photo->Filename);
    }

    public function testOnBeforeWriteWithSaveExtensionlessThumbnail()
    {
        $return = [
            'type' => 'video',
            'version' => '1.0',
            'title' => 'Test video',
            'html' => '<video />',
            'width' => '480',
            'height' => '240',
            'thumbnail_url' => __DIR__ . '/fixtures/thumbnail',
            'thumbnail_width' => '299',
            'thumbnail_height' => '186',
        ];
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->method('fetchFrom')->willReturn($return);
        $embed = new Embed();
        $embed->setFetcher($embedSpy);
        $config = $embed::config();
        $config->set('cache_image_as_asset', true);
        $config->set('embed_folder', 'thumbnails');
        $embed->EmbedSourceURL = 'test.test';

        $this->assertCount(0, Image::get());
        $this->assertCount(0, Folder::get());

        $embed->write();

        $this->assertCount(1, Image::get());
        $this->assertCount(1, Folder::get());
        $photo = Image::get()->first();
        $folder = $photo->Parent();
        $this->assertSame('Test video thumbnail', $photo->Title);
        $this->assertSame('thumbnails', $folder->Title);
        $this->assertSame('thumbnails/thumbnail.jpeg', $photo->Filename);
    }

    public function testOnBeforeWriteWithNonImage()
    {
        $return = [
            'type' => 'photo',
            'version' => '1.0',
            'title' => 'Test photo',
            'url' => __DIR__ . '/fixtures/nonimage.txt',
            'width' => '338',
            'height' => '248',
        ];
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->method('fetchFrom')->willReturn($return);
        $embed = new Embed();
        $embed->setFetcher($embedSpy);
        $config = $embed::config();
        $config->set('cache_image_as_asset', true);
        $config->set('embed_folder', 'bad-images');
        $embed->EmbedSourceURL = 'test.test';

        $this->assertCount(0, Image::get());
        $this->assertCount(0, Folder::get());

        $this->expectException(RuntimeException::class);
        $embed->write();

        $this->assertCount(0, Image::get());
        $this->assertCount(0, Folder::get());
    }
}
