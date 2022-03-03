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
use App\Models\Post;
use Illuminate\Support\Str;
use App\Models\PostsMedia;
use App\Models\Hashtag;
use App\Models\PostReport;
use App\Models\CommentLike;
use App\Models\UserSaveMedias;
use App\Models\AppReport;
use Vimeo\Laravel\Facades\Vimeo;
use App\Models\PostTag;
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
            'audio_name' => 'required',
            'post_type' => 'required',
            'language_id' => 'required',
            'comment_allow' => 'required',
            'share_allow' => 'required',
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
                $saveData->comment_allow = $postData['comment_allow'];
                $saveData->share_allow = $postData['share_allow'];
                $saveData->save();
                
                if($postData['post_type'] == 'REEL'){
                    
                    if ($request->file('media')) {
                        
                        $allowedfileExtension=['mp4'];
                        $image = $request->file('media');
                        
                        foreach ($image as $files) {
                            $file_name = 'video_'.time() . "." . $files->getClientOriginalExtension();
                            try {
                                $uri = Vimeo::upload($files, array(
                                    'name' => 'Video' . time()
                                ));

                                $video_data = Vimeo::request($uri);

                                if ($video_data['status'] == 200) {
                                    $output = array(
                                        "type" => "success",
                                        "link" => $video_data['body']['link']
                                    );
                                }
                            } catch (VimeoUploadException $e) {
                                $error = 'Error uploading ' . $file_name . "\n";
                                $error .= 'Server reported: ' . $e->getMessage() . "\n";
                                $output = array(
                                    "type" => "error",
                                    "error_message" => $error
                                );
                            } catch (VimeoRequestException $e) {
                                $error = 'There was an error making the request.' . "\n";
                                $error .= 'Server reported: ' . $e->getMessage() . "\n";
                                $output = array(
                                    "type" => "error",
                                    "error_message" => $error
                                );
                            }

                            $response = json_encode($output);
                            return $response;

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
                                if(isset($postData['hashtag'])){
                                    $hashTags = explode(',', $postData['hashtag']);
                                    if($hashTags){
                                        foreach ($hashTags as $key => $value) {
                                            $saveTagData = new PostTag();
                                            $saveTagData->post_id = $saveData->id;
                                            $saveTagData->tag_id = $value;
                                            $saveTagData->save();
                                        }
                                    }
                                }
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

                        $getPostTag = PostTag::where('post_id', $saveData->id)->get();
                        $getHashTagData = array();
                        foreach ($getPostTag as $tagValue) {
                            $getHashTag = Hashtag::where('id', $tagValue->tag_id)->first();
                            $row['id'] = $getHashTag->id;
                            $row['name'] = $getHashTag->hashtag;
                            $getHashTagData[] = $row;
                        }
                        
                        $data['hashtag'] = $getHashTagData;
                        
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
            PostTag::where('post_id', $request->get('media_id'))->delete();
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

    public function allHashtagPost(Request $request)
    {
        $user = Auth::user();

        $tagIds = $getVoxoMediaData2 = array();
        $tagsData2 = array();
        $page = $request->has('page') ? $request->get('page') : 0;
        $limit = $request->has('limit') ? $request->get('limit') : 10;

        $dataTag = Hashtag::skip(($page*$limit))->limit($limit)->get();

        $topUsersJoinQuery = DB::table('user_follow')
            ->select('follower_id', DB::raw('COUNT(follower_id) AS count'))
            ->groupBy('follower_id'); //this will fetch the followers ids and group them.

        $top_users = DB::table('users')->select('*')
            ->join(DB::raw('(' . $topUsersJoinQuery->toSql() . ') i'), function ($join)
            {
                $join->on('i.follower_id', '=', 'users.id');
            })
            ->orderBy('count', 'desc')
            ->select('name','id','user_name', 'thumb_avatar as thumb_image', 'avatar as full_image', DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id) as totalfollower'), DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id AND user_follow.user_id = '.$user->id.') as isFollow'))
            ->take(10)
            ->get();
        

        foreach ($dataTag as $key => $value) {
            $dataHashTag = PostTag::where('tag_id', $value->id)->get();
            $tagsData = $tagDataPostId = array();
            $rows['id'] = $value->id;
            $rows['hashtag'] = $value->hashtag;
            $rows['hashtag_image'] = $value->image;
            $rows['total_video'] = $dataHashTag->count();
            if($dataHashTag){
                foreach ($dataHashTag as $tagData) {
                    $tagDataPostId[] = $tagData->post_id;
                }
                    if($user && $user->id > 0){
                        $postDataQuery = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where voxo_media_likes.user_id = '.$user->id.' and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('posttags.hashtags', 'postMedia', 'users')->whereIn('posts.id', $tagDataPostId)->where('is_local', '0')->orderBy('posts.id', 'DESC')->limit(10)->get();
                    }else{
                        $postDataQuery = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where voxo_media_likes.user_id = 0 and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = 0 and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('posttags.hashtags', 'postMedia', 'users')->whereIn('posts.id', $tagDataPostId)->where('is_local', '0')->orderBy('id', 'DESC')->limit(10)->get();
                    }
                    foreach ($postDataQuery as $postList) {
                        $rows2['id'] = $postList->id;
                        $rows2['author_id'] = $postList->author_id;
                        $rows2['post_type'] = $postList->post_type;
                        $rows2['audio_name'] = $postList->audio_name;
                        $rows2['body'] = $postList->body;
                        $rows2['language_id'] = $postList->language_id;
                        $rows2['report_count'] = $postList->report_count;
                        $rows2['is_local'] = $postList->is_local;
                        $rows2['is_read'] = $postList->is_read;
                        $rows2['status'] = $postList->status;
                        $rows2['created_at'] = $postList->created_at;
                        $rows2['updated_at'] = $postList->updated_at;
                        $rows2['comments_count'] = $postList->comments_count;
                        $rows2['likes_count'] = $postList->likes_count;
                        $rows2['islike_count'] = $postList->islike_count;
                        $rows2['isfav_count'] = $postList->isfav_count;
                        $rows2['comment_allow'] = $postList->comment_allow;
                        $rows2['share_allow'] = $postList->share_allow;
                        $rows2['share_link'] = $postList->share_link;
                        $rows2['full_image'] = $postList->full_image;
                        $rows2['posttags'] = $postList->posttags;
                        $rows2['post_media'] = $postList->postMedia;
                        $rows2['users'] = $postList->users;
                        $tagsData[] = $rows2; 
                    }
                
            }
            
            $rows['postdata'] = $tagsData;
            
            $tagsData2[] = $rows; 
        }
        
        $resp = [
            'status' => true,
            'data' => $tagsData2,
            'creators' => $top_users,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);

    }

    public function hashtagPost(Request $request)
    {
        $hashtagId = $request->get('id');
        $user = Auth::user();
        $dataTag = PostTag::where('tag_id', $hashtagId)->get();
        $dataHashTag = Hashtag::where('id', $hashtagId)->first();

        $page = $request->has('page') ? $request->get('page') : 0;
        $limit = $request->has('limit') ? $request->get('limit') : 10;

        $getVoxoMediaData2 = $postID = array();
        
        foreach ($dataTag as $key => $value) {
            
            $postID[] = $value->post_id;
            
        }
            $tagsData = array();
            if($user && $user->id > 0){
                $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where voxo_media_likes.user_id = '.$user->id.' and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('posttags.hashtags', 'postMedia', 'users')->whereIn('posts.id', $postID);
            }else{
                $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = 0 and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = 0 and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('posttags.hashtags', 'postMedia', 'users')->whereIn('posts.id', $postID)->where('is_local', '0');
            }
            if(isset($request->language_id) && $request->language_id != ''){
                $query->where('language_id', $request->language_id);
            }
            $getVoxoMedia = $query->orderBy('id', 'DESC')->skip(($page*$limit))->limit($limit)->get();
           
            foreach ($getVoxoMedia as $postList) {
                $rows2['id'] = $postList->id;
                $rows2['author_id'] = $postList->author_id;
                $rows2['post_type'] = $postList->post_type;
                $rows2['audio_name'] = $postList->audio_name;
                $rows2['body'] = $postList->body;
                $rows2['language_id'] = $postList->language_id;
                $rows2['report_count'] = $postList->report_count;
                $rows2['is_local'] = $postList->is_local;
                $rows2['is_read'] = $postList->is_read;
                $rows2['status'] = $postList->status;
                $rows2['created_at'] = $postList->created_at;
                $rows2['updated_at'] = $postList->updated_at;
                $rows2['comments_count'] = $postList->comments_count;
                $rows2['likes_count'] = $postList->likes_count;
                $rows2['islike_count'] = $postList->islike_count;
                $rows2['isfav_count'] = $postList->isfav_count;
                $rows2['comment_allow'] = $postList->comment_allow;
                $rows2['share_allow'] = $postList->share_allow;
                $rows2['share_link'] = $postList->share_link;
                $rows2['full_image'] = $postList->full_image;
                $rows2['posttags'] = $postList->posttags;
                $rows2['post_media'] = $postList->postMedia;
                $rows2['users'] = $postList->users;
                $tagsData[] = $rows2; 
            }
        //$getVoxoMediaData2[] = $row;
        $resp = [
            'status' => true,
            'data' => $tagsData,
            'hashtag' =>$dataHashTag,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);

    }

    public function hashtagList(Request $request)
    {
        $data = Hashtag::paginate(20);
        $resp = [
            'status' => true,
            'data' => $data,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }


    public function demoupload(Request $request)
    {
        if ($request->file('media')) {
                        
            $allowedfileExtension=['mp4'];
            $image = $request->file('media');
            
            foreach ($image as $files) {
                $file_name = 'video_'.time() . "." . $files->getClientOriginalExtension();
                try {
                    $uri = Vimeo::upload($files, array(
                        'name' => 'Video' . time()
                    ));

                    $video_data = Vimeo::request($uri);

                    if ($video_data['status'] == 200) {
                        $output = array(
                            "type" => "success",
                            "link" => $video_data['body']['link']
                        );
                    }
                } catch (VimeoUploadException $e) {
                    $error = 'Error uploading ' . $file_name . "\n";
                    $error .= 'Server reported: ' . $e->getMessage() . "\n";
                    $output = array(
                        "type" => "error",
                        "error_message" => $error
                    );
                } catch (VimeoRequestException $e) {
                    $error = 'There was an error making the request.' . "\n";
                    $error .= 'Server reported: ' . $e->getMessage() . "\n";
                    $output = array(
                        "type" => "error",
                        "error_message" => $error
                    );
                }

               
            }
            $response = json_encode($output);
                return $response;
        }
    }
}