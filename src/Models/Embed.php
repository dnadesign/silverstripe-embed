<?php

namespace gorriecoe\Embed\Models;

use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use gorriecoe\Embed\Extensions\Embeddable;

/**
 * Embed
 **/
class Embed extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'Embed';

    /**
     * Singular name for CMS
     * @var string
     */
    private static $singular_name = 'Embed';

    /**
     * Plural name for CMS
     * @var string
     */
    private static $plural_name = 'Embed';

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'EmbedTitle' => 'Title',
        'EmbedType' => 'Type',
        'EmbedSourceURL' => 'URL'
    ];

    /**
     * Defines extension names and parameters to be applied
     * to this object upon construction.
     * @var array
     */
    private static $extensions = [
        Embeddable::class
    ];

    /**
     * Defines upload folder for embedded assets
     *
     * @var string
     */
    private static $embed_folder = 'Embed';

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(
            TabSet::create(
                "Root",
                Tab::create("Main")
            )
            ->setTitle(_t('SiteTree.TABMAIN', "Main"))
        );
        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Alias for EmbedTitle
     * This is used by CMS Title and breadcrumbs.
     * @return string
     */
    public function getTitle()
    {
        return $this->EmbedTitle;
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return self
     */
    public function setClass($class)
    {
        $this->setEmbedClass($class);
        return $this;
    }

    /**
     * Returns the classes for this embed.
     * @return string
     */
    public function getClass()
    {
        return $this->EmbedClass;
    }

    /**
     * Renders an HTML anchor tag for this link
     * @return string HTML
     */
    public function forTemplate()
    {
        return $this->Embed;
    }
}
