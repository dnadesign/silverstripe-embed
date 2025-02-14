<?php

namespace gorriecoe\Embed\Extensions;

use Embed\Embed;
use gorriecoe\HTMLTag\View\HTMLTag;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\RequiredFields;
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
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static $embed_tab = 'Main';

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

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $tab = $owner->config()->get('embed_tab');
        $tab = isset($tab) ? $tab : 'Main';

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
            array(
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
                        _t(__CLASS__ . '.SOURCEURLDESCRIPTION', 'Specify a external URL. Format for Youtube: https://www.youtube.com/watch?v=9bZkp7q19f0 Vimeo: https://player.vimeo.com/video/226053498')
                    ),
                UploadField::create(
                    'EmbedImage',
                    _t(__CLASS__ . '.IMAGELABEL', 'Image')
                )
                    ->setFolderName($owner->EmbedFolder)
                    ->setAllowedFileCategories(['image'])
                    ->setDescription('Upload an image to use as a thumbnail for the embed.'),
                TextareaField::create(
                    'EmbedDescription',
                    _t(__CLASS__ . '.DESCRIPTIONLABEL', 'Description')
                )
            )
        );

        if (isset($owner->AllowedEmbedTypes) && Count($owner->AllowedEmbedTypes) > 1) {
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
        if ($sourceURL = $owner->EmbedSourceURL) {
            $embed = new Embed();
            $embed = $embed->get($sourceURL);

            if ($owner->EmbedTitle == '') {
                $owner->EmbedTitle = $embed->title;
            }
            if ($owner->EmbedDescription == '') {
                $owner->EmbedDescription = $embed->description;
            }
            $changes = $owner->getChangedFields();
            if (isset($changes['EmbedSourceURL'])) {
                $owner->EmbedHTML = $this->addReferrerPolicyForVimeo($embed->code->html, $sourceURL);
                $owner->EmbedType = 'video';
                $owner->EmbedWidth = $embed->code->width;
                $owner->EmbedHeight = $embed->code->height;
                $owner->EmbedAspectRatio = $embed->code->ratio;

                // TODO: This doesn't work. Images are too small and Vimeo returns images without a file extension
                // if ($owner->EmbedSourceImageURL != (string) $embed->image) {
                //     $owner->EmbedSourceImageURL = (string) $embed->image;
                //     $fileExplode = explode('.', $embed->image);
                //     $fileExtension = end($fileExplode);
                //     $fileName = Convert::raw2url($owner->obj('EmbedTitle')->LimitCharacters(55)) . '.' . $fileExtension;
                //     $parentFolder = Folder::find_or_make($owner->EmbedFolder);

                //     $imageObject = DataObject::get_one(
                //         Image::class,
                //         [
                //             'Name' => $fileName,
                //             'ParentID' => $parentFolder->ID
                //         ]
                //     );

                //     if(!$imageObject){
                //         // Save image to server
                //         $imageObject = Image::create();
                //         $imageObject->setFromString(
                //             file_get_contents($embed->Image),
                //             $owner->EmbedFolder . '/' . $fileName,
                //             null,
                //             null,
                //             [
                //                 'conflict' => AssetStore::CONFLICT_OVERWRITE
                //             ]
                //         );
                //     }

                //     // Check existing for image object or create new
                //     $imageObject->ParentID = $parentFolder->ID;
                //     $imageObject->Name = $fileName;
                //     $imageObject->Title = $embed->getTitle();
                //     $imageObject->OwnerID = (Member::currentUserID() ? Member::currentUserID() : 0);
                //     $imageObject->ShowInSearch = false;
                //     $imageObject->write();

                //     $owner->EmbedImageID = $imageObject->ID;
                // }
            }
        }
    }

    /**
     * @return array()|null
     */
    public function getAllowedEmbedTypes()
    {
        return $this->owner->config()->get('allowed_embed_types');
    }

    // TODO: This doesn't work with latest embed/embed
    // /**
    //  * @param  ValidationResult $validationResult
    //  * @return ValidationResult
    //  */
    // public function validate(ValidationResult $validationResult)
    // {
    //     $owner = $this->owner;
    //     $allowed_types = $owner->AllowedEmbedTypes;
    //     $sourceURL = $owner->EmbedSourceURL;
    //     if ($sourceURL && isset($allowed_types)) {
    //         $embed = new Embed();
    //         $embed = $embed->get($sourceURL);
    //         if (!in_array($embed->Type, $allowed_types)) {
    //             $string = implode(', ', $allowed_types);
    //             $string = (substr($string, -1) == ',') ? substr_replace($string, ' or', -1) : $string;
    //             $validationResult->addError(
    //                 _t(__CLASS__ . '.ERRORNOTSTRING', "The embed content is not a $string")
    //             );
    //         }
    //     }
    //     return $validationResult;
    // }

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
    protected function addReferrerPolicyForVimeo($html, $sourceURL) {
        if (strpos($sourceURL, 'vimeo.com') !== false) {
            return str_replace('<iframe ', '<iframe referrerpolicy="strict-origin" ', $html);
        }
        return $html;
    }
}
