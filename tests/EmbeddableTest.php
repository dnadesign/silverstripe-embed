<?php

namespace gorriecoe\Embed\Test;

use gorriecoe\Embed\Extensions\Embeddable;
use gorriecoe\Embed\Models\Embed;
use gorriecoe\Embed\Service\Fetcher;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;

class EmbeddableTest extends SapphireTest
{
    private const FAKE_EMBED = [
        'title' => 'Test title',
        'description' => '',
        'type' => '',
        'html' => '',
        'width' => '',
        'height' => '',
    ];

    /**
     * Mocks a fetcher so live cURL requests aren't made for test data
     * this could be done in setUp() but then tests that don't use it will fail the `once` assertion
     *
     * @param array $extra
     * @return Embed
     */
    private function getConfiguredEmbed(array $extra = []): Embed
    {
        $return = array_merge(self::FAKE_EMBED, $extra);
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->method('fetchFrom')->willReturn($return);
        $embed = new Embed();
        $embed->setFetcher($embedSpy);
        return $embed;
    }

    public function testSettingEmbedFetchersWorks()
    {
        $embed = $this->getConfiguredEmbed();
        $embed->getFetcher()->expects($this->once())->method('fetchFrom');
        $embed->EmbedSourceURL = 'test.test';
        $embed->onBeforeWrite();
        $this->assertSame('Test title', $embed->EmbedTitle);
    }

    public function testGetEmbedWithNoTypeDoesNotCauseAnError()
    {
        $this->assertSame('', (new Embed())->getEmbed());
    }

    public function testValidateDefault()
    {
        $embed = $this->getConfiguredEmbed(['type' => 'test']);
        $fetcher = $embed->getFetcher();
        $fetcher->expects($this->never())->method('fetchFrom');
        $config = $embed->config();
        $config->set('validate_embed', false);
        $config->set('allowed_embed_types', null);
        $embed->EmbedSourceURL = 'test.test';
        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($fetcher);
        $embeddable->validate($validation);
        $this->assertTrue($validation->isValid());
    }

    public function testValidateWithGoodType()
    {
        $embed = $this->getConfiguredEmbed(['type' => 'test']);
        $fetcher = $embed->getFetcher();
        $fetcher->expects($this->once())->method('fetchFrom');
        $config = $embed::config();
        $config->set('validate_embed', true);
        $config->set('allowed_embed_types', ['test']);
        $embed->EmbedSourceURL = 'test.test';
        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($fetcher);
        $embeddable->validate($validation);
        $this->assertTrue($validation->isValid());
    }

    public function testValidateWithBadType()
    {
        $embed = $this->getConfiguredEmbed(['type' => 'test']);
        $fetcher = $embed->getFetcher();
        $fetcher->expects($this->once())->method('fetchFrom');
        $config = $embed::config();
        $config->set('validate_embed', true);
        $config->set('allowed_embed_types', ['video']);
        $embed->EmbedSourceURL = 'test.test';
        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($fetcher);
        $embeddable->validate($validation);
        $this->assertFalse($validation->isValid());
    }

    public function testValidateWithAnyType()
    {
        $embed = $this->getConfiguredEmbed(['type' => 'test']);
        $fetcher = $embed->getFetcher();
        $fetcher->expects($this->never())->method('fetchFrom');
        $config = $embed->config();
        $config->set('validate_embed', true);
        $config->set('allowed_embed_types', null);
        $embed->EmbedSourceURL = 'test.test';
        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($fetcher);
        $embeddable->validate($validation);
        $this->assertTrue($validation->isValid());
    }

    public function testValidateWithNoChangeToEmbedSourceUrl()
    {
        $return = array_merge(self::FAKE_EMBED, ['type' => 'test']);
        $embedSpy = $this->createMock(Fetcher::class);
        $embedSpy->expects($this->never())->method('fetchFrom')->willReturn($return);
        $embed = new Embed(['EmbedSourceURL' => 'test.test'], DataObject::CREATE_MEMORY_HYDRATED);
        $embed->setFetcher($embedSpy);

        $config = $embed->config();
        $config->set('validate_embed', true);
        $config->set('allowed_embed_types', ['test']);

        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($embedSpy);
        $embeddable->validate($validation);
        $this->assertTrue($validation->isValid());
    }

    public function testEmbedDataIsMemoryCachedForTheRequest()
    {
        $embed = $this->getConfiguredEmbed();
        $fetcher = $embed->getFetcher();
        $fetcher->expects($this->once())->method('fetchFrom');
        $config = $embed::config();
        $config->set('validate_embed', true);
        $config->set('allowed_embed_types', null);
        $embed->EmbedSourceURL = 'test.test';
        $validation = new ValidationResult();
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($fetcher);
        $embeddable->validate($validation); // trigger fetchEmbedData
        $embeddable->onBeforeWrite(); // trigger fetchEmbedData again
    }

    public function testOnBeforeWriteFillsEmbedData()
    {
        $embed = $this->getConfiguredEmbed([
            'type' => 'test',
            'version' => '1.0',
            'title' => 'Test title',
            'description' => 'test description',
            'html' => '<p>test</p>',
            'width' => '100',
            'height' => '50',
            ]);
        $embed::config()->set('cache_image_as_asset', false);
        $embed->EmbedSourceURL = 'test.test';
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($embed->getFetcher());
        $embeddable->onBeforeWrite();

        $this->assertSame('Test title', $embed->EmbedTitle);
        $this->assertSame('test', $embed->EmbedType);
        $this->assertSame('test.test', $embed->EmbedSourceURL);
        $this->assertSame(null, $embed->EmbedSourceImageURL);
        $this->assertSame('<p>test</p>', $embed->EmbedHTML);
        $this->assertSame('100', $embed->EmbedWidth);
        $this->assertSame('50', $embed->EmbedHeight);
        $this->assertSame(2, $embed->EmbedAspectRatio);
        $this->assertSame('test description', $embed->EmbedDescription);
    }

    public function testEmbedTypePhotoSetsEmbedSourceImageUrl()
    {
        $embed = $this->getConfiguredEmbed([
            'type' => 'photo',
            'title' => 'Test photo title',
            'url' => 'test.test/image.bmp',
        ]);
        $embed::config()->set('cache_image_as_asset', false);
        $embed->EmbedSourceURL = 'test.test';
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($embed->getFetcher());
        $embeddable->onBeforeWrite();

        $this->assertSame('test.test/image.bmp', $embed->EmbedSourceImageURL);
    }

    public function testVimeoReferrerPolicyAttributeInjection()
    {
        $embed = $this->getConfiguredEmbed([
            'type' => 'video',
            'html' => '<iframe />',
        ]);
        $embed::config()->set('cache_image_as_asset', false);
        $embed->EmbedSourceURL = 'test.test/vimeo.com';
        $embeddable = new Embeddable();
        $embeddable->setOwner($embed);
        $embeddable->setFetcher($embed->getFetcher());
        $embeddable->onBeforeWrite();

        $this->assertSame('<iframe referrerpolicy="strict-origin" />', $embed->EmbedHTML);
    }
}
