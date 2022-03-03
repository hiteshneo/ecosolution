<?php

namespace App\Http\Controllers\API\V1;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Image;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Models\VoxoMedias;
use App\Models\VoxoMediaComment;
use App\Models\VoxoMediaLike;
use TCG\Voyager\Models\Post;
use Illuminate\Support\Str;
use App\Models\PostsMedia;
use App\Models\PostReport;
use App\Models\CommentLike;
use App\Models\UserSaveMedias;
use App\Models\AppReport;
use Vimeo\Laravel\Facades\Vimeo;

/**
 * @group Authentication
 *
 * Class AuthController
 *
 * Fullfills all aspects related to authenticate a user.
 */
class VoxoController extends APIController
{
    public function saveReels(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media' => 'required',
            //'description' => 'required',
            'audio_name' => 'required',
            'post_type' => 'required',
            'language_id' => 'required',
        ]);
        
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        
        $postData = $request->all();
        DB::beginTransaction();
        try {
                $saveData = new Post();
                $saveData->author_id = $user->id;
                $saveData->post_type = $postData['post_type'];
                $saveData->audio_name = $postData['audio_name'];
                $saveData->body = $postData['description'];
                $saveData->language_id = $postData['language_id'];
                $saveData->save();
                if($postData['post_type'] == 'REEL'){
                    
                    if ($request->file('media')) {
                        
                        $allowedfileExtension=['mp4'];
                        $image = $request->file('media');
                        
                        foreach ($image as $files) {
                            $check=in_array($files->getClientOriginalExtension(),$allowedfileExtension);
                            if($check){
                                
                                try {
                                    $fileSize = $files->getSize();

                                    if($fileSize > '6291456'){
                                        $s3 = Storage::disk('voxo');
                                        $file_name = 'video_'.time() . "." . $files->getClientOriginalExtension();
                                        $s3->put($file_name, file_get_contents($files), 'public');
                                        $s3filePath = '/posts/'.$file_name;
                                        $postReel = ENV('AWS_URL').$s3filePath;
                                        $destinationPathVideo = '/var/www/html/storage/app/public/posts/'.$file_name.'';  
                                    }else{
                                        $s3 = Storage::disk('s3');
                                        $file_name = 'video_'.time() . "." . $files->getClientOriginalExtension();
                                        $s3filePath = '/posts/'.$file_name;
                                        $s3->put($s3filePath, file_get_contents($files), 'public');
                                        $postReel = ENV('AWS_URL').$s3filePath;
                                        $destinationPathVideo = $postReel;  
                                    }
                                    
                                    $file = Storage::disk('s3')->exists($s3filePath);
                                    if($file){
                                        $postReel = ENV('AWS_URL').$s3filePath;
                                        $isLocal = 0;
                                    } else {
                                        $postReel = ENV('APP_URL').'/storage/app/public/posts/compressed/'.$file_name;
                                        $isLocal = 1;
                                    }

                                    // $s3 = Storage::disk('voxo');
                                    // $file_name = 'video_'.time() . "." . $files->getClientOriginalExtension();
                                    // $s3->put($file_name, file_get_contents($files), 'public');
                                    // $s3filePath = '/posts/'.$file_name;
                                    // $postReel = ENV('AWS_URL').$s3filePath;

                                    $destinationPath = '/var/www/html/storage/app/public/posts/video_play'.$saveData->id.'.jpg';  
                                    
                                    $cmd ="ffmpeg -i $destinationPathVideo -deinterlace -an -ss 1 -t 00:00:01 -r 1 -y -vcodec mjpeg -f mjpeg $destinationPath 2>&1" ; 
                                    shell_exec($cmd);
                                    
                                    
                                    $s3 = Storage::disk('s3');
                                    $s3filePath2 = '/posts/video_play'.$saveData->id.'.jpg';
                                    $s3->put($s3filePath2, file_get_contents($destinationPath), 'public');
                                    $thumbImage = ENV('AWS_URL').$s3filePath2;

                                    //unlink($destinationPath);

                                } catch (\Exception $e) {
                                    // something went wrong
                                    echo $e->getMessage();
                                    
                                }
                                $imageData = new PostsMedia();
                                $imageData->voxo_media_id = $saveData->id;
                                $imageData->media_files = $postReel;
                                $imageData->media_image = $thumbImage;
                                $imageData->media_files_name = $file_name;
                                $imageData->media_image_name = 'video_play'.$saveData->id.'.jpg';
                                $imageData->media_type =$postData['post_type'];
                                $imageData->mime_type = $files->getClientOriginalExtension();
                                $imageData->save();
                                Post::where('id', $saveData->id)->update(['is_local' => $isLocal]);
                            }else{
                                $resp = [
                                    'status' => false,
                                    'data' => '',
                                    'message' => 'Warning! Sorry Only Upload mp4',
                                    'error' => true,
                                    'errors' => '',
                                ];
                                return response()->json($resp, $this->statusCode);
                            }
                        }
                        Post::where('id', $saveData->id)->update(['image' => $thumbImage]);
                        $getVoxoMedia = Post::with('postMedia')->where('id', $saveData->id)->first();
                        $data['id'] = $getVoxoMedia->id;
                        $data['user_id'] = $getVoxoMedia->author_id;
                        $data['mediafiles'] = $getVoxoMedia->postMedia;
                        $data['description'] = $getVoxoMedia->body;
                        $data['audio_name'] = $getVoxoMedia->audio_name;
                        $resp = [
                            'status' => true,
                            //'data' => $data,
                            'data' => '',
                            'message' => 'Your reel under process. It will take 5 to 10 minutes to show in your account.',
                            'error' => false,
                            'errors' => '',
                        ];
                        $pass = 1;
                        DB::commit();
                    }else{
                        $resp = [
                            'status' => false,
                            'data' => '',
                            'message' => 'Please upload media file.',
                            'error' => true,
                            'errors' => '',
                        ];
                    }
                }else{
                    if ($request->hasFile('media')) {
                        $allowedfileExtension=['jpg','png','jpeg'];
                        $image = $request->file('media');
                        foreach ($image as $key => $files) {
                            $check=in_array($files->getClientOriginalExtension(),$allowedfileExtension);
                            
                            if($check){
                                
                                $imageSize = getimagesize($files);
                                $width = $imageSize[0];
                                $height = $imageSize[1];
                                // if($width > 1000){
                                //     $width = ($width*75)/100;
                                //     $height = ($height*75)/100;
                                // }else{
                                //     $width = ($width*50)/100;
                                //     $height = ($height*50)/100;
                                // }

                                if($width > 1000){
                                    $width = ($width*50)/100;
                                    $height = ($height*50)/100;
                                }
                                
                                $s3 = Storage::disk('s3');
                                $file_name = 'img_'.$key.time() . "." . $files->getClientOriginalExtension();
                                $s3filePath = '/posts/'.$file_name;
                                $s3->put($s3filePath, file_get_contents($files), 'public');
                                $postImage = ENV('AWS_URL').$s3filePath;
                                
                                $thumb_file_name = 'thumb_img_'.$key.time() . "." . $files->getClientOriginalExtension();
                                $s3thumbfilePath = '/postthumbs/'.$thumb_file_name;
                                $imgThumb = Image::make($files->getRealPath())->resize($width, $height)->stream(); ##create thumbnail
                                
                                $path = Storage::disk('s3');
                                $path->put($s3thumbfilePath, $imgThumb->__toString(),'public');
                                //$path->put($s3thumbfilePath, file_get_contents($files),'public');
                                $postThumbImage = ENV('AWS_URL').$s3thumbfilePath;

                                

                                $imageData = new PostsMedia();
                                $imageData->voxo_media_id = $saveData->id;
                                $imageData->media_files = $postImage;
                                $imageData->media_image = $postThumbImage;
                                $imageData->media_files_name = $file_name;
                                $imageData->media_image_name = $thumb_file_name;
                                $imageData->media_type = $postData['post_type'];
                                $imageData->mime_type = $files->getClientOriginalExtension();
                                $imageData->image_width = $width;
                                $imageData->image_height = $height;
                                $imageData->save();
                                Post::where('id', $saveData->id)->update(['is_local' => 0]);
                            }else{
                                $resp = [
                                    'status' => false,
                                    'data' => '',
                                    'message' => 'Warning! Sorry Only Upload jpg, png, jpeg',
                                    'error' => true,
                                    'errors' => '',
                                ];
                                return response()->json($resp, $this->statusCode);
                            }
                        }   
                        Post::where('id', $saveData->id)->update(['image' => $postImage]);
                        $getVoxoMedia = Post::with('postMedia')->where('id', $saveData->id)->first();
                        $data['id'] = $getVoxoMedia->id;
                        $data['user_id'] = $getVoxoMedia->author_id;
                        $data['mediafiles'] = $getVoxoMedia->postMedia;
                        $data['description'] = $getVoxoMedia->body;
                        $data['audio_name'] = $getVoxoMedia->audio_name;
                        $resp = [
                            'status' => true,
                            'data' => $data,
                            'message' => 'Uploaded successfully.',
                            'error' => false,
                            'errors' => '',
                        ];
                        DB::commit();
                        $pass = 1;
                    }else{
                        $resp = [
                            'status' => false,
                            'data' => '',
                            'message' => 'Please upload media file.',
                            'error' => true,
                            'errors' => '',
                        ];
                    }
                }
                

            
        } catch (\Exception $e) {
            DB::rollback();
            echo $e->getMessage();
            // something went wrong
        }
        return response()->json($resp, $this->statusCode);
    }

    public function saveComment(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
            'comment' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $arr = [
            'user_id' => $user->id,
            'voxo_media_id' => $request->get('media_id'),
            'comment' => $request->get('comment'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        VoxoMediaComment::insert($arr);

        return $this->respond([
            'status' => true,
            'message' => 'Commented successfully.',
            'data' => '',
            'error' => false,
            'errors' => '',
        ]);
    }

    public function saveLikes(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
            'like' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $arr = [
            'user_id' => $user->id,
            'voxo_media_id' => $data['media_id'],
            'like_count' => $data['like'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if($data['like'] == 1){
            
                $checkLike = VoxoMediaLike::where(['user_id' => $user->id, 'voxo_media_id' => $data['media_id']])->count();
                if($checkLike == 0){
                    VoxoMediaLike::insert($arr);
                }
                $message = 'Liked successfully.';
            
        }else{
            VoxoMediaLike::where('voxo_media_id', $data['media_id'])->delete();
            $message = 'Disliked successfully.';
        }

        return $this->respond([
            'status' => true,
            'message' => $message,
            'data' => '',
            'is_like' => $data['like'],
            'error' => false,
            'errors' => '',
        ]);
    }

    public function getComments(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $data = VoxoMediaComment::with('users')->withcount('commentlike')->where('voxo_media_id', $request->get('media_id'))->orderBy('id', 'asc')->paginate(10);

        return $this->respond([
            'status' => true,
            'message' => 'success',
            'data' => $data,
            'error' => false,
            'errors' => '',
        ]);
    }

    public function deletePost(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        DB::beginTransaction();
        try {
            Post::where('id', $request->get('media_id'))->delete();
            VoxoMediaComment::where('voxo_media_id', $request->get('media_id'))->delete();
            VoxoMediaLike::where('voxo_media_id', $request->get('media_id'))->delete();
            PostsMedia::where('voxo_media_id', $request->get('media_id'))->delete();
            PostReport::where('voxo_report_id', $request->get('media_id'))->delete();
            UserSaveMedias::where('voxo_media_id', $request->get('media_id'))->delete();

            DB::commit();

            return $this->respond([
                'status' => true,
                'message' => 'Deleted Successfully.',
                'data' => '',
                'error' => false,
                'errors' => '',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            echo "Message :".$e->getMessage();
        }
        return $this->respond([
            'status' => true,
            'message' => 'Can not delete this post.',
            'data' => '',
            'error' => false,
            'errors' => '',
        ]);
    }

    public function getReport(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'report_id' => 'required'
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $data = PostReport::where('voxo_report_id', $request->get('report_id'))->paginate(10);

        return $this->respond([
            'status' => true,
            'message' => 'success',
            'data' => $data,
            'error' => false,
            'errors' => '',
        ]);
    }

    public function saveReport(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'report_id' => 'required',
            //'report' => 'required',
            'type' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $checkReport = PostReport::where(['report_user_id' => $user->id, 'voxo_report_id' => $request->get('report_id')])->count();
        if($checkReport == 0){
            $arr = [
                'report_user_id' => $user->id,
                'voxo_report_id' => $request->get('report_id'),
                'reporst_text' => $request->get('report') !== null ? $request->get('report') : '',
                'type' => $request->get('type'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            PostReport::insert($arr);
            if($request->get('type') == 'USER'){
                $updateCountData = User::find($request->get('report_id'));
            }else{
                $updateCountData = Post::find($request->get('report_id'));
            }
            $reportC = $updateCountData->report_count + 1;
            $updateCountData->update(['report_count' => $reportC]);
            $message = 'Reported successfully.';
            return $this->respond([
                'status' => true,
                'message' => $message,
                'data' => '',
                'error' => false,
                'errors' => '',
            ]);
        }else{
            $message = 'Already Reported.';
            return $this->respond([
                'status' => false,
                'message' => $message,
                'data' => '',
                'error' => true,
                'errors' => '',
            ]);
        }
        
    }

    public function commentLike(Request $request){
        
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
            'like' => 'required',
            'comment_id'=> 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $arr = [
            'user_id' => $user->id,
            'voxo_media_id' => $data['media_id'],
            'comment_id' => $data['comment_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if($data['like'] == 1){
            $checkLike = CommentLike::where(['user_id' => $user->id, 'voxo_media_id' => $data['media_id'], 'comment_id' => $data['comment_id']])->count();
            if($checkLike == 0){
                CommentLike::insert($arr);
            }
            $message = 'Liked successfully.';
        }else{
            CommentLike::where(['user_id' => $user->id, 'voxo_media_id' => $data['media_id'], 'comment_id' => $data['comment_id']])->delete();
            $message = 'Disliked successfully.';
        }

        return $this->respond([
            'status' => true,
            'message' => $message,
            'data' => '',
            'is_like' => $data['like'],
            'error' => false,
            'errors' => '',
        ]);
    }

    public function saveFavourite(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'media_id' => 'required',
            'fav' => 'required',
            'type' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $arr = [
            'user_id' => $user->id,
            'voxo_media_id' => $data['media_id'],
            'type' => $data['type'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if($data['fav'] == 1){
            $checkSelfLike = Post::where(['author_id' => $user->id, 'id' => $data['media_id']])->count();
            if($checkSelfLike == 0){
                $checkLike = UserSaveMedias::where(['user_id' => $user->id, 'voxo_media_id' => $data['media_id']])->count();
                if($checkLike == 0){
                    UserSaveMedias::insert($arr);
                }
                $message = 'Added successfully.';   
            }else{
                $message = 'Not allowed.';   
            }
        }else{
            UserSaveMedias::where('voxo_media_id', $data['media_id'])->delete();
            $message = 'Removed successfully.';
        }

        return $this->respond([
            'status' => true,
            'message' => $message,
            'data' => '',
            'fav' => $data['fav'],
            'error' => false,
            'errors' => '',
        ]);
    }

    public function appReport(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'report' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $arr = [
            'report_user_id' => $user->id,
            'reporst_text' => $request->get('report') !== null ? $request->get('report') : '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        AppReport::insert($arr);

        return $this->respond([
            'status' => true,
            'message' => 'Reported successfully.',
            'data' => '',
            'error' => false,
            'errors' => '',
        ]);
    }

    // public function uploadvideo(Request $request)
    // {
    //     $client = new Vimeo("c292e90a6d38934ef197420b50e8f029bc2bf750", "zsdczWqUXK0eXPcJNK8dwE2/tu/0Y5nOOO69FTYW6uWixOqs8rKwAhj7fhMhrEAe1RtTGryianwBbr6+SsCrOTxbCAmm5N9HUi/SKituKVcJom/XwI+7/JvVwEmmqQTZ", "e7893ef0260bf020614aba090e7406d3");
    //     $token = $client->clientCredentials(scope);
    //     $image = $request->file('media');
    //     $file_name = "http://18.204.96.100/storage/app/public/posts/compressed/video_1634352239.mp4";
    //     Vimeo::upload($image);

    //     echo "Your video URI is: " . $image;
    // }
}