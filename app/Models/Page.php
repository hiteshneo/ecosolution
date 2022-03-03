<?php

namespace App\Models;

use App\Models\Traits\ModelAttributes;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Attributes\PageAttributes;
use App\Models\Traits\Relationships\PageRelationships;

class Page extends BaseModel
{
    use SoftDeletes, ModelAttributes, PageRelationships, PageAttributes;

    /**
     * The guarded field which are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'page_slug',
        'description',
        'cannonical_link',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'excerpt',
        'author_id',
        'image',
        'slug',
        'meta_description',
        'meta_keywords',
        'deleted_at',
    ];

    /**
     * Casts.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
    ];
}