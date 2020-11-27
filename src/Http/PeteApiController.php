<?php


namespace Pete\PeteApi\Http;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Site;
use Input;
use Illuminate\Http\Request;
use App\PeteOption;
use Validator;
use Illuminate\Support\Facades\Redirect;
use Log;
use View;

class PeteApiController extends Controller
{
	
	public function __construct(Request $request){
	    
	    $this->middleware('auth');
		$dashboard_url = env("DASHBOARD_URL");
		$viewsw = "/sites";
		
		//DEBUGING PARAMS
		$debug = env('DEBUG');
		if($debug == "active"){
			$inputs = $request->all();
			Log::info($inputs);
		}
		
		$system_vars = parent::__construct();
		$pete_options = $system_vars["pete_options"];
		$sidebar_options = $system_vars["sidebar_options"];
		$current_user = Auth::user(); 
		View::share(compact('dashboard_url','viewsw','pete_options','system_vars','sidebar_options','current_user'));
		   
	}
  	
	public function test_demo(){
				
		$viewsw = "/import_wordpress";
		return view("pete-api-plugin::test_demo")->with('viewsw',$viewsw);
	}
	
	
}
