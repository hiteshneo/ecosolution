<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeclareResult extends Command{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'declare:result';
   

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

        $time=date('Y-m-d H:i:s');
        try{
            $query  = "SELECT tcppu.*, usr1.id as user_id FROM tbl_customer_plans_child_counts tcppu LEFT JOIN users usr1 ON (usr1.id=tcppu.user_id)";
           
            $getParentLevelDistribution  = DB::SELECT($query);

            $queryEarningres;
            //dd($getParentLevelDistribution); die;
            foreach ($getParentLevelDistribution as $key => $value) {
           
                $user_id      =   $value->user_id;
                $count_level_1 =  $value->count_level_1 > 0 ? $value->count_level_1 *2:0;
                $count_level_2 =  $value->count_level_2 > 0 ? $value->count_level_2 *2:0;
                $count_level_3 =  $value->count_level_3 > 0 ? $value->count_level_3 *3:0;
                $count_level_4 =  $value->count_level_4 > 0 ? $value->count_level_4 *3:0;
                $count_level_5 =  $value->count_level_5 > 0 ? $value->count_level_5 *4:0;
                $count_level_6 =  $value->count_level_6 > 0 ? $value->count_level_6 *5:0;
                $count_level_7 =  $value->count_level_7 > 0 ? $value->count_level_7 *6:0;
                $count_level_8 =  $value->count_level_8 > 0 ? $value->count_level_8 *7:0;
                
                $totalCountValue =  $count_level_1 +  $count_level_2 +  $count_level_3 + $count_level_4+ $count_level_5+ $count_level_6+ $count_level_7+ $count_level_8; 
                $getMemberShipPlan = DB::table('memberships')->where(['user_id'=>$user_id])->get()->count();
             
                if($totalCountValue > 0 && $getMemberShipPlan >= 1)
                {
                    $updateEarning1 = "UPDATE tbl_customer_plans_child_counts SET level_1_earning= $count_level_1 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id;
                    DB::UPDATE($updateEarning1); 

                    $updateEarning2 = "UPDATE tbl_customer_plans_child_counts SET level_2_earning=$count_level_2 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning2); 

                    $updateEarning3 = "UPDATE tbl_customer_plans_child_counts SET level_3_earning= $count_level_3 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning3); 

                    $updateEarning4 = "UPDATE tbl_customer_plans_child_counts SET level_4_earning= $count_level_4 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning4); 

                     $updateEarning5 = "UPDATE tbl_customer_plans_child_counts SET level_5_earning= $count_level_5 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning5); 

                     $updateEarning6 = "UPDATE tbl_customer_plans_child_counts SET level_6_earning= $count_level_6 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning6); 

                     $updateEarning7 = "UPDATE tbl_customer_plans_child_counts SET level_7_earning= $count_level_7 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning7); 


                    /*$updateEarning1 = "UPDATE tbl_customer_plans_child_counts SET level_1_earning=level_1_earning + $count_level_1 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id;
                    DB::UPDATE($updateEarning1); 

                    $updateEarning2 = "UPDATE tbl_customer_plans_child_counts SET level_2_earning=level_2_earning + $count_level_2 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning2); 

                    $updateEarning3 = "UPDATE tbl_customer_plans_child_counts SET level_3_earning=level_3_earning + $count_level_3 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning3); 

                    $updateEarning4 = "UPDATE tbl_customer_plans_child_counts SET level_4_earning=level_4_earning + $count_level_4 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning4); 

                     $updateEarning5 = "UPDATE tbl_customer_plans_child_counts SET level_5_earning=level_5_earning + $count_level_5 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning5); 

                     $updateEarning6 = "UPDATE tbl_customer_plans_child_counts SET level_6_earning=level_6_earning + $count_level_6 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning6); 

                     $updateEarning7 = "UPDATE tbl_customer_plans_child_counts SET level_7_earning=level_7_earning + $count_level_7 WHERE EXISTS(select 1 from memberships where user_id=".$user_id.") AND  user_id=".$user_id; 
                     DB::UPDATE($updateEarning7); */

                   // $queryEarningres  = DB::INSERT($queryEarning);  

                    $queryEarning = "INSERT INTO user_earnings (user_id,membership_id,earning,earn_type,trasaction_status,created_at,updated_at) VALUES($user_id,0,$totalCountValue,'Reward Points','Success','$time','$time')"; 
                    $queryEarningres  = DB::INSERT($queryEarning);   
                } 
            }

            \Log::info("Cron is working fine!");

            
        }catch(\Illuminate\Database\QueryException $ex){ 
                dd($ex);
        }
    }
}