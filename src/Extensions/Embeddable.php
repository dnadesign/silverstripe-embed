<?php

namespace gorriecoe\Embed\Extensions;

use Embed\Embed;
use gorriecoe\Embed\Service\Fetcher;
use gorriecoe\HTMLTag\View\HTMLTag;
use RuntimeException;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

/**
 * Embeddable
 *
 * @package silverstripe
 * @subpackage mysite
 */
class Embeddable extends DataExtension
{
    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'EmbedTitle' => 'Varchar(255)',
        'EmbedType' => 'Varchar',
        'EmbedSourceURL' => 'Varchar(255)',
        'EmbedSourceImageURL' => 'Varchar(255)',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Varchar',
        'EmbedHeight' => 'Varchar',
        'EmbedAspectRatio' => 'Varchar',
        'EmbedDescription' => 'HTMLText'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'EmbedImage' => Image::class
    ];

    /**
     * Relationship version ownership
     * @var array
     */
    private static $owns = [
        'EmbedImage'
    ];

    /**
     * Defines tab to insert the embed fields into in the CMS
     *
     * @config
     * @var string
     */
    private static $embed_tab = 'Main';

    /**
     * List the allowed included embed types. If null all are allowed.
     *
     * @config
     * @var array|null
     */
    private static $allowed_embed_types = null;

    /**
     * Whether to not to assert the embed data is OK within the {@see validate()} method
     * Works in tandem with allowed_embed_types
     *
     * @config
     * @var boolean
     */
    private static $validate_embed = false;

    /**
     * Download and store either a photo or a thumbnail (depending on embed type) as a Silverstripe asset
     *
     * @config
     * @var boolean
     */
    private static $cache_image_as_asset = false;

    /**
     * Name of the folder to store assets in
     * @see $cache_image_as_asset
     *
     * @var string
     */
    private static $embed_folder;

    /**
     * List of custom CSS classes for template.
     * @var array
     */
    protected $classes = [];

    /**
     * Defines the template to render the embed in.
     * @var string
     */
    protected $template = 'Embed';

    private ?Fetcher $fetcher = null;

    private array $embedData = [];

    public function getFetcher(): Fetcher
    {
        if (!$this->fetcher) {
            $this->fetcher = Injector::inst()->get(Fetcher::class);
        }
        return $this->fetcher;
    }

    public function setFetcher(Fetcher $fetcher)
    {
        $this->fetcher = $fetcher;
        return $this->owner;
    }

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $tab = $owner->config()->get('embed_tab') ?? 'Main';

        // Ensure these fields don't get added by fields scaffold
        $fields->removeByName([
            'EmbedTitle',
            'EmbedType',
            'EmbedSourceURL',
            'EmbedSourceImageURL',
            'EmbedHTML',
            'EmbedWidth',
            'EmbedHeight',
            'EmbedAspectRatio',
            'EmbedDescription',
            'EmbedImage'
        ]);

        $fields->addFieldsToTab(
            'Root.' . $tab,
            [
                TextField::create(
                    'EmbedTitle',
                    _t(__CLASS__ . '.TITLELABEL', 'Title')
                )
                    ->setDescription(
                        _t(__CLASS__ . '.TITLEDESCRIPTION', 'Optional. Will be auto-generated if left blank')
                    ),
                TextField::create(
                    'EmbedSourceURL',
                    _t(__CLASS__ . '.SOURCEURLLABEL', 'Source URL')
                )
                    ->setDescription(
                        _t(
                            __CLASS__ . '.SOURCEURLDESCRIPTION',
                            'Specify a external URL.'
                            . ' Format for Youtube: https://www.youtube.com/watch?v=9bZkp7q19f0'
                            . ' Vimeo: https://player.vimeo.com/video/226053498'
                        )
                    ),
                UploadField::create(
                    'EmbedImage',
                    _t(__CLASS__ . '.IMAGELABEL', 'Image')
                )
                    ->setFolderName($owner->EmbedFolder)
                    ->setAllowedFileCategories(['image'])
                    ->setDescription(
                        _t(
                            __CLASS__ . '.IMAGEDESCRIPTION',
                            'Upload an image to use as a thumbnail for the embed.'
                        )
                    ),
                TextareaField::create(
                    'EmbedDescription',
                    _t(__CLASS__ . '.DESCRIPTIONLABEL', 'Description')
                )
            ]
        );

        if (
            isset($owner->AllowedEmbedTypes)
            && is_array($owner->AllowedEmbedTypes)
            && count($owner->AllowedEmbedTypes) > 1
        ) {
            $fields->addFieldToTab(
                'Root.' . $tab,
                ReadonlyField::create(
                    'EmbedType',
                    _t(__CLASS__ . '.TYPELABEL', 'Type')
                ),
                'EmbedImage'
            );
        }

        return $fields;
    }

    public function getCMSValidator()
    {
        return RequiredFields::create(
            'EmbedSourceURL',
            'EmbedImage'
        );
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        $owner = $this->owner;

        if (empty($owner->EmbedSourceURL) || !$owner->isChanged('EmbedSourceURL')) {
            return;
        }

        $embedData = $this->fetchEmbedData();
        if (empty($embedData)) {
            return;
        }

        $owner->EmbedHTML = $this->addReferrerPolicyForVimeo($embedData['html'] ?? '', $owner->EmbedSourceURL);
        unset($embedData['html']);
        $width = (int)$embedData['width'] ?? null;
        $height = (int)$embedData['height'] ?? null;
        $owner->EmbedAspectRatio = ($width & $height) ? $width / $height : null;

        foreach ($embedData as $name => $value) {
            $field = 'Embed' . ucwords($name);
            if ($owner->hasField($field)) {
                $owner->$field = $value;
            }
        }

        $imageUrl = $embedData['thumbnail_url'] ?? '';
        $titleSuffix = ' thumbnail';

        if ($embedData['type'] === 'photo' && isset($embedData['url'])) {
            $imageUrl = $embedData['url'];
            $titleSuffix = '';
            $owner->EmbedSourceImageURL = $imageUrl;
        }

        if ((bool)$owner->config()->get('cache_image_as_asset') && $imageUrl) {
            $image = $owner->EmbedImage();
            try {
                $urlData = parse_url($imageUrl);
                $filename = pathinfo($urlData['path'] ?? '', PATHINFO_FILENAME);
                $imageData = $this->fetchImageData($imageUrl);
                $fileInfo = finfo_open();
                $mimeType = finfo_buffer($fileInfo, $imageData, FILEINFO_MIME);
                if (explode('/', $mimeType)[0] !== 'image') {
                    throw new RuntimeException("Image URL does not have an image mime type: $mimeType ($imageUrl)");
                }
                $possibleExtensions = explode('/', finfo_buffer($fileInfo, $imageData, FILEINFO_EXTENSION));
                $extension = array_shift($possibleExtensions);
                if ($extension === '???') {
                    throw new RuntimeException("Image mime type does not have known extensions: $mimeType");
                }
                $image->update([
                    'Name' => $filename,
                    'Title' => $embedData['title'] . $titleSuffix,
                ]);
                $image->setFromString($imageData, "{$filename}.{$extension}");
                if (!$image->exists()) {
                    $image->update([
                        'ParentID' => Folder::find_or_make($owner->EmbedFolder)->ID,
                        'OwnerID' => Security::getCurrentUser()?->ID,
                        'ShowInSearch' => false,
                    ]);
                }
                $image->write();
                $owner->EmbedImageID = $image->ID;
            } catch (Throwable $e) {
                $log->error($e);
            }
        }
    }

    protected function fetchEmbedData(): array
    {
        if (empty($this->embedData)) {
            $this->embedData = $this->getFetcher()->fetchFrom($this->owner->EmbedSourceURL);
        }
        return $this->embedData;
    }

    protected function fetchImageData(string $url): string
    {
        return file_get_contents($url);
    }

    /**
     * @return array
     */
    public function getAllowedEmbedTypes(): array
    {
        return (array)$this->owner->config()->get('allowed_embed_types') ?? [];
    }

    /**
     * @param  ValidationResult $validationResult
     * @return ValidationResult
     */
    public function validate(ValidationResult $validationResult)
    {
        $owner = $this->owner;
        $allowedTypes = $owner->AllowedEmbedTypes; // allows owner to override with its own getter
        $sourceURL = $owner->EmbedSourceURL;
        if (
            (bool)$owner->config()->get('validate_embed')
            && $owner->isChanged('EmbedSourceURL')
            && $sourceURL
            && is_array($allowedTypes)
            && !empty($allowedTypes)
            && !in_array($this->fetchEmbedData()['type'] ?? null, $allowedTypes)
        ) {
            $validationResult->addError(_t(
                __CLASS__ . '.TYPEERROR',
                "The embed type is not one of the allowed types: {allowed}",
                ['allowed' => implode(', ', $allowedTypes)]
            ));
        }
        return $validationResult;
    }

    /**
     * @return string
     */
    public function getEmbedFolder()
    {
        $owner = $this->owner;
        $folder = $owner->config()->get('embed_folder');
        if (!isset($folder)) {
            $folder = $owner->ClassName;
        }
        return $folder;
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return DataObject Owner
     */
    public function setEmbedClass($class)
    {
        $classes = ($class) ? explode(' ', $class) : [];
        foreach ($classes as $key => $value) {
            $this->classes[$value] = $value;
        }
        return $this->owner;
    }

    /**
     * Returns the classes for this embed.
     * @return string
     */
    public function getEmbedClass()
    {
        $classes = $this->classes;
        if (Count($classes)) {
            return implode(' ', $classes);
        }
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return DataObject Owner
     */
    public function setEmbedTemplate($template)
    {
        if (isset($template)) {
            $this->template = $template;
        }
        return $this->owner;
    }

    /**
     * Renders embed into appropriate template HTML
     * @return HTML
     */
    public function getEmbed()
    {
        $owner = $this->owner;
        $title = $owner->EmbedTitle;
        $class = $owner->EmbedClass;
        $type = $owner->EmbedType;
        $template = $this->template;
        $embedHTML = $owner->EmbedHTML;
        $sourceURL = $owner->EmbedSourceURL;
        $templates = [];
        if ($type) {
            $templates[] = $template . '_' . $type;
        }
        $templates[] = $template;
        if (SSViewer::hasTemplate($templates)) {
            return $owner->renderWith($templates);
        }
        switch ($type) {
            case 'video':
            case 'rich':
                $html = HTMLTag::create($embedHTML, 'div');
                break;
            case 'link':
                $html = HTMLTag::create($title, 'a')->setAttribute('href', $sourceURL);
                break;
            case 'photo':
                $html = HTMLTag::create($sourceURL, 'img')->setAttribute([
                    'width' => $this->Width,
                    'height' => $this->Height,
                    'alt' => $title
                ]);
                break;
            default:
                return '';
        }
        return $html->setClass($class);
    }

    /**
     * Vimeo videos may have domain-specific embed restrictions.
     * This method ensures that only the origin (domain) of the referring document is
     * sent as the referrer to Vimeo, potentially bypassing any restrictions set by
     * video owners on Vimeo for specific domains.
     *
     * @param string $html The embed HTML.
     * @param string $sourceURL The source URL of the embed.
     * @return string Modified embed HTML.
     */
    protected function addReferrerPolicyForVimeo($html, $sourceURL)
    {
        if (strpos($sourceURL, 'vimeo.com') !== false) {
            return str_replace('<iframe ', '<iframe referrerpolicy="strict-origin" ', $html);
        }
        return $html;
    }
}
