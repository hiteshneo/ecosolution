<?php

namespace App\Models;

use App\Models\Traits\ModelAttributes;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\VoxoMediaComment;
use App\Models\VoxoMediaLike;
use Illuminate\Support\Str;
use App\Models\PostsMedia;
use TCG\Voyager\Facades\Voyager;
use App\User;
use App\Models\Hashtag;
use App\Models\PostTag;

class Post extends BaseModel
{
    use ModelAttributes;

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'content',
        'image',
        'video',
        'emoji',
        'status',
    ];

    /**
     * Dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Statuses.
     *
     * @var array
     */
    protected $statuses = [
        0 => 'InActive',
        1 => 'Published',
    ];

    protected $hidden = [
        'image'
    ];

    protected $appends = ['share_link', 'full_image',];
    
    public function getShareLinkAttribute()
    {
        return env('APP_URL').'/post/'.$this->id;
    }
    public function comments()
    {
        return $this->hasMany(VoxoMediaComment::class, 'voxo_media_id');
    }

    public function likes()
    {
        return $this->hasMany(VoxoMediaLike::class, 'voxo_media_id');
    }

    /**
     * @param $title
     * @param int $id
     * @return string
     * @throws \Exception
     */
    public static function createSlug($title, $id = 0)
    {
        // Normalize the title
        $slug = Str::slug($title);

        // Get any that could possibly be related.
        // This cuts the queries down by doing it once.
        $allSlugs = Post::getRelatedSlugs($slug, $id);

        // If we haven't used it before then we are all good.
        if (! $allSlugs->contains('slug', $slug)){
            return $slug;
        }

        // Just append numbers like a savage until we find not used.
        for ($i = 1; $i <= 10; $i++) {
            $newSlug = $slug.'-'.$i;
            if (! $allSlugs->contains('slug', $newSlug)) {
                return $newSlug;
            }
        }

        throw new \Exception('Can not create a unique slug');
    }

    protected static function getRelatedSlugs($slug, $id = 0)
    {
        return Post::select('slug')->where('slug', 'like', $slug.'%')
            ->where('id', '<>', $id)
            ->get();
    }
  
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function postMedia()
    {
        return $this->hasMany(PostsMedia::class, 'voxo_media_id');
    }

    public function getFullImageAttribute()
    {
        if (!empty($this->image)) {
            //return Storage::disk('profile_upload')->url($this->image);
            return $this->image;
        } else {
            return env('APP_URL').'/storage/app/public/posts/default.png';
        }
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    public function islike()
    {
        return $this->belongsTo(VoxoMediaLike::class,'author_id', 'user_id')->where('voxo_media_id', 'id');
    }

    public function posttags()
    {
        return $this->hasMany(PostTag::class, 'post_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function postReport()
    {
        return $this->hasMany(PostReport::class, 'voxo_report_id');
    }
}