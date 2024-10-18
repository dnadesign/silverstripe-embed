<?php

namespace gorriecoe\Embed\Models;

use gorriecoe\Embed\Models\Embed;

/**
 * EmbedVideo
 **/
class Video extends Embed
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'Video';

    /**
     * Singular name for CMS
     * @var string
     */
    private static $singular_name = 'Video';

    /**
     * Plural name for CMS
     * @var string
     */
    private static $plural_name = 'Video';

    /**
     * List the allowed included embed types.  If null all are allowed.
     *
     * @var array
     */
    private static $allowed_embed_types = [
        'video'
    ];

    /**
     * Defines upload folder for embedded assets
     *
     * @var string
     */
    private static $embed_folder = 'Video';
}
