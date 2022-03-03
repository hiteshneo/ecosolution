<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use TCG\Voyager\Facades\Voyager;

class DailyIncome extends Command{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:income';
   

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
            
            $getuser = DB::table('memberships')->select('user_id as userID')->where('expired_date', '>', date('Y-m-d H:i:s'))->get();
            if($getuser){
                foreach ($getuser as $value) {
                    $inputS = array(
                        'user_id' => $value->userID,
                        'membership_id' => 0,
                        'earning' => Voyager::setting('site.daily_income', ''),
                        'earn_type' => 'Reward Points',
                        'trasaction_status' => 'Success',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    );

                    DB::table('user_earnings')->insertGetId($inputS);
                }
            }
            \Log::info("Cron is working fine!");
        }catch(\Illuminate\Database\QueryException $ex){ 
                dd($ex);
        }
    }
}