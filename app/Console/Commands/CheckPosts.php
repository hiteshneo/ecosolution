<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CheckPosts extends Command{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:posts';
   

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    public function __construct(){
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){
        
        $this->info('Cron Cummand Run successfully!');
        
        try{
           
            $getPosts = DB::table('posts')->select('posts.id','posts_medias.media_files_name','posts_medias.media_files')
            ->leftJoin('posts_medias', 'posts.id', '=', 'posts_medias.voxo_media_id')
            ->where('posts.is_local', 1)->get();
            if($getPosts){
                foreach ($getPosts as $value) {
                    
                    $file = Storage::disk('s3')->exists('/posts/'.$value->media_files_name);
                    if($file){
                        $s3filePath = '/posts/'.$value->media_files_name;
                        $postReel = ENV('AWS_URL').$s3filePath;
                        $isLocal = 0;
                    } else {
                        $postReel = $value->media_files;
                        $isLocal = 1;
                    }

                    DB::table('posts_medias')->where('voxo_media_id', $value->id)->update(['media_files' => $postReel]);
                    DB::table('posts')->where('id', $value->id)->update(['is_local' => $isLocal]);
                }
            }
            \Log::info("Cron is working fine!");
        }catch(\Illuminate\Database\QueryException $ex){ 
                dd($ex);
        }
    }
}