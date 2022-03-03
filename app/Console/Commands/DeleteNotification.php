<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteNotification extends Command{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:notification';
   

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
           
            DB::table('notifications')->whereDate('created_at', '<=', now()->subDays(30))->delete();
            
            \Log::info("Cron is working fine!");
        }catch(\Illuminate\Database\QueryException $ex){ 
                dd($ex);
        }
    }
}