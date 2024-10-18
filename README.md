# Silverstripe embed

Adds embed and video a DataObjects along with a DataExtension to apply embed capabilities to existing objects.

## Installation

[Composer](https://getcomposer.org/) is the recommended way of installing SilverStripe modules.

```sh
composer require gorriecoe/silverstripe-embed
```

## Requirements

- silverstripe/framework ^4 | ^5

## Maintainers

- [Gorrie Coe](https://github.com/gorriecoe)
- [DNA Design](https://www.dna.co.nz)

## Usage

As a relationship to Embed Dataobjects

```php
use gorriecoe\Embed\Models\Embed;

class ClassName extends DataObject
{
    private static $has_one = [
        'Embed' => Embed::class,
        'Video' => Video::class
    ];

    public function getCMSFields()
    {
        // ...
        $fields->addFieldsToTab(
            'Main',
            [
                HasOneButtonField::create('Embed', 'Embed', $this),
                HasOneButtonField::create('Video', 'Video', $this),
            ]
        );
        // ...
    }
}

```

Or update current DataObject to be Embeddable with DataExtension

```php
use gorriecoe\Embed\Extensions\Embeddable;

class ClassName extends DataObject
{
    private static $extensions = [
        Embeddable::class,
    ];

    /**
     * List the allowed included embed types.  If null all are allowed.
     * @var array
     */
    private static $allowed_embed_types = [
        'video',
        'photo'
    ];

    /**
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static $embed_tab = 'Main';
}

```

## Options

Several options can be set via `Config` to control the behaviour of `silverstripe-embed`
These are set via the DataObject the `Embeddable` data extension is applied to (which includes both `Embed` and `Video`)
E.g. as in the data extension example above with the `embed_tab` option.

- `embed_tab` _string_ **Default**: `"Main"` - Name of the CMS edit form tab to display under.
- `allowed_embed_types` _array of strings_ **Default**: `null` - allowed "types" or `null` for any.
- `validate_embed` _bool_ **Default**: `false` - Whether to fetch and evaluate the validity of the embed URL (e.g. on save). Relies on `allowed_embed_types`.
- `cache_image_as_asset` _bool_ **Default**: `false` - Whether to download and save copies of thumbnails or photo type embeds as assets.
- `embed_folder` _string_ **Default**: `null` - Name of the folder to store downloaded assets into. Will fall back to class name if not set. Relies on `cache_image_as_asset`.
