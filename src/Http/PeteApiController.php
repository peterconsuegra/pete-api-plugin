<?php

namespace Pete\PeteApi\Http;


use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Site;
use App\User;
use App\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Log;
use Illuminate\Support\Facades\Redirect;
use App\PeteOption;
use DB;
use Route;
use Carbon\Carbon;
use Crypt;
use DateTime;

class PeteApiController extends Controller {
	
	protected $user;
	
	public function __construct(Request $request){
		
		if(env('DEBUG') == "active"){
			Log::info("Credenciales");
			Log::info(Input::get('email'));
			Log::info(Input::get('pete_token'));
		}
			   
			$email = Input::get('email');
			$pete_token = Input::get('pete_token');
		 	$controller = substr(class_basename(Route::currentRouteAction()), 0, (strpos(class_basename(Route::currentRouteAction()), '@') -0) );
		 	$action = explode('@',Route::currentRouteAction())[1];
			$user = User::where('email', $email)->first();
			$pete_token_decrypted = Crypt::decrypt($user->pete_token);
			
			if(env('DEBUG') == "active"){
				Log::info("pete_token_decrypted: $pete_token_decrypted");
				Log::info("controller: $controller");
				Log::info("action: $action");
				$input = $request->all();
				Log::info($input);
			}

			if($pete_token_decrypted == $pete_token){
				$this->user = $user;
			}
	    }

	/**
	* #################################################
	* SITES CRUD
	* #################################################
	*/
	
	public function create_site(){
			
		if(isset($this->user)){
		
		$pete_options = new PeteOption();
		$wp_user = Input::get('wp_user');
		$db_root_pass = env('ROOT_PASS');
		$user_email = Input::get('email');
		
		$wp_pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10); 
		
		$db_name = "db_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);	
		$db_user = "usr_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
		$db_user_pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
		 
		Log::info("check 1");
		
		$new_site = new Site();
		$new_site->barsite_id = Input::get('barsite_id');
		$new_site->domain_linode_id = Input::get('domain_linode_id');
		$new_site->subdomain_linode_id = Input::get('subdomain_linode_id');
		$new_site->action_name = "New";
		$new_site->wp_user = $wp_user;
		$new_site->theme = Input::get('theme');
		$new_site->name = Input::get('subdomain_prefix');
		$new_site->url = Input::get('subdomain');
		$new_site->linode_id = Input::get('linode_id');
		$new_site->user_id = $this->user->id;
		$new_site->first_password =  Crypt::encrypt($wp_pass);
		$new_site->name = Input::get('subdomain_prefix');
		$new_site->barserver_id = Input::get('barserver_id');
		$new_site->backup_days = 2;
		Log::info("check 2");
		$new_site->import_wordpress(Input::get('theme_file'),["db_name" => $db_name, "db_user" => $db_user, "db_user_pass" => $db_user_pass]);
		Log::info("check 3");
		
		$os_distribution = $pete_options->get_meta_value('os_distribution');
		
		if ($os_distribution=="docker") {
			
			$host = 'mysql';
			$db_user = "root";
			$db_user_pass = env('PETE_ROOT_PASS');
		}else{
			$host = 'localhost';
		}
		
		//CREATE ADMIN USER WITH FIRST PASSWORD
		$conn=mysqli_connect($host,$db_user,$db_user_pass,$db_name);
		// Check connection
		if (mysqli_connect_errno())
		  {
		  Log::info("Failed to connect to MySQL: " . mysqli_connect_error());
		  }
		  $now = Carbon::now();
		  $now_string = $now->format('Y/m/d H:i:s');
		  
		  $sql1 = "INSERT INTO `wp_users` (`user_login`, `user_pass`, `user_nicename`, `user_email`,`user_status`,`user_registered`,`display_name`)
 VALUES ('$wp_user', MD5('$wp_pass'), '$new_site->wp_user', '$user_email', '0','$now_string','$wp_user')";
		  
		  if ($conn->query($sql1) === TRUE) {
		      Log::info("New record created successfully");
		  } else {
		      Log::info("Error: " . $conn->error);
		  }
		  
		  $sql2 = "INSERT INTO `wp_usermeta` (`umeta_id`, `user_id`, `meta_key`, `meta_value`) 
 VALUES (NULL, (Select max(id) FROM wp_users), 
 'wp_capabilities', 'a:1:{s:13:".'"administrator"'.';s:1:"1";}'."'".")";
 
 		if ($conn->query($sql2) === TRUE) {
     	   Log::info("New record created successfully");
 	  	}else {
     	   Log::info("Error: " . $conn->error);
 	  	}
		
		$sql3 = "INSERT INTO `wp_usermeta` (`umeta_id`, `user_id`, `meta_key`, `meta_value`) 
 VALUES (NULL, (Select max(id) FROM wp_users), 'wp_user_level', '10')";
		
 		if ($conn->query($sql3) === TRUE) {
     	   Log::info("New record created successfully");
 	  	}else {
     	   Log::info("Error: " . $conn->error);
 	  	}

		$conn->close();
		
		$var_array = ["subscription_id" => Input::get('subscription_id'), "pete_token" => Input::get('pete_token'), "email" => Input::get('email'), "barserver_ip" => Input::get('barserver_ip'), "message" => ""];	
		
		Log::info("DB NAME: ".$new_site->db_name);
		
		if($this->user->shared){
			$this->user->grant_privileges_to_db($new_site->db_name,$new_site->name);
		}
		
		return response()->json($var_array);
		
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
		
		
	}
	
	
	public function publish_site(){
		
		if(isset($this->user)){
			
			$domain = Input::get('domain');
			$site_name = str_replace(".","",$domain);
			$url_check= Site::where("url",Input::get('domain'))->first();
			$name_check= Site::where("name",$site_name)->first();
			
			if(Input::get('domain')=="0"){
			 return response()->json(['error' => 'true', 'message_en'=>'Please select a domain.', "message_es" => "Por favor seleccione un dominio"]);
			}else if(isset($url_check)){
			  return response()->json(['error' => 'true', 'message_en'=>'Domain is already used.', "message_es" => "El dominio ya esta siendo usado"]);	
			}else if(isset($name_check)){
			  return response()->json(['error' => 'true', 'message_en'=>'Project name already used.', "message_es" => "El nombre del proy"]);
			}
			else{
				
				/*
				1) Create Snapshot
				2) Import Snapshot
				3) Delete Snapshot
				*/
				
				$site_to_clone = Site::findOrFail(Input::get('pete_id'));
				$backup = $site_to_clone->snapshot_creation("oclone");	

				$pete_options = new PeteOption();
				$base_path = base_path();
				$backup_file = $base_path. "/backups/$backup->site_id/".$backup->file_name;	
					
				$new_site = new Site();
				$new_site->barsite_id = Input::get('barsite_id');
				$new_site->action_name = "Clone";
				$new_site->name = $site_name;
				$new_site->url = Input::get('domain');
				$new_site->published = true;
				$new_site->barsite_id = null;
				$new_site->wp_user = $backup->wp_user;
				$new_site->theme = $backup->theme;
				$new_site->user_id = $this->user->id;
				$new_site->first_password = $backup->first_password;
				$new_site->barserver_id = $backup->barserver_id;
				$new_site->backup_days = 2;
				$db_name = "db_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
				$db_user = "usr_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
				$db_user_pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10); 
				$new_site->import_wordpress($backup_file,["db_name" => $db_name, "db_user" => $db_user, "db_user_pass" => $db_user_pass]);

				//$backup->delete();
				
				$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "message" => "", "table_id" => Input::get('table_id')];
				
				if($this->user->shared){
					$this->user->grant_privileges_to_db($new_site->db_name,$new_site->name);
				}
			
				return response()->json($var_array);
			}
			
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function clone_site(){
		if(isset($this->user)){
			
			if($this->user->shared){
				
				return response()->json(['error' => 'true', 'message_en'=>'Oops! Looks like you need something a bit stronger 😉 Upgrade your plan to On the Rocks and get a Virtual Private Server that supports your performance needs.', "message_es" => "Oops! Parece que necesitas algo un poco más fuerte 😉 Sube tu plan al nivel <a style='color:#fff; font-weight: bold; text-decoration: underline;' href='/producto/plan-en-las-rocas/'>En Las Rocas</a> y consigue un Virtual Private server que soportará tus necesidades de rendimiento."]);
				
			}else{
				
				/*
				1) Create Snapshot
				2) Import Snapshot
				3) Delete Snapshot
				*/
				
				$site_to_clone = Site::findOrFail(Input::get('pete_id'));
				$backup = $site_to_clone->snapshot_creation("oclone");	

				$pete_options = new PeteOption();
				$base_path = base_path();
				$backup_file = $base_path . "/backups/$backup->site_id/".$backup->file_name;	
					
				$new_site = new Site();
				$new_site->barsite_id = Input::get('barsite_id');
				$new_site->action_name = "Clone";
				$new_site->name = Input::get('subdomain_prefix');
				$new_site->url = Input::get('subdomain');
				$new_site->wp_user = $backup->wp_user;
				$new_site->theme = $backup->theme;
				$new_site->user_id = $this->user->id;
				$new_site->first_password = $backup->first_password;
				$new_site->barserver_id = $backup->barserver_id;
				$new_site->backup_days = 2;
				$db_name = "db_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
				$db_user = "usr_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
				$db_user_pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10); 
				$new_site->import_wordpress($backup_file,["db_name" => $db_name, "db_user" => $db_user, "db_user_pass" => $db_user_pass]);

				$backup->delete();
				
				$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "message" => "", "table_id" => Input::get('table_id')];
				
				return response()->json($var_array);
			}
			
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function restore_site_from_backup(){
		if(isset($this->user)){
			
			$backup_id = Input::get('backup_id');			
			$backup = Backup::findOrFail(Input::get('backup_id'));
			$pete_options = new PeteOption();
			$base_path = base_path();
			$backup_file = $base_path."/backups/$backup->site_id/".$backup->file_name;	
					
			$new_site = new Site();
			$new_site->barsite_id = Input::get('barsite_id');
			$new_site->action_name = "Restore";
			$new_site->name = Input::get('subdomain_prefix');
			$new_site->url = Input::get('subdomain');
			$new_site->wp_user = $backup->wp_user;
			$new_site->theme = $backup->theme;
			$new_site->user_id = $this->user->id;
			$new_site->first_password = $backup->first_password;
			$new_site->barserver_id = $backup->barserver_id;
			$new_site->backup_days = 2;
			$db_name = "db_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
			$db_user = "usr_" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
			$db_user_pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10); 
			
			$new_site->import_wordpress($backup_file,["db_name" => $db_name, "db_user" => $db_user, "db_user_pass" => $db_user_pass]);
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "message" => ""];
			
			if($this->user->shared){
				$this->user->grant_privileges_to_db($new_site->db_name,$new_site->name);
			}
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}

	public function download_site_from_backup(){
		if(isset($this->user)){

			$pete_options = new PeteOption();			
			$backup_id = Input::get('backup_id');			
			$backup = Backup::findOrFail(Input::get('backup_id'));
			
			$base_path = base_path();
			chdir("$base_path/scripts/");
			
			$backup_file = $base_path . "/backups/$backup->site_id/".$backup->file_name;	
			$download_file = $base_path . "/public/downloads/".$backup->file_name;	
			$download_folder = $base_path . "/public/downloads";
			
			$output = "";
			$output .= shell_exec("mkdir -p $download_folder");
			$output .= shell_exec("rm -rf $download_file");
			$output = shell_exec("cp $backup_file $download_file");
			$debug = env('DEBUG');
			
			if($debug == "active"){
				Log::info("command:");
				Log::info("cp $backup_file $download_file");
				Log::info("output:");
				Log::info($output);
			}	

			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "file_name" => $backup->file_name, "message" => ""];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	
	public function snapshot_creation_site(){
		
		$label = Input::get('snapshot_label');
		$site_id = Input::get('pete_id');
		
		//ERROR CASES FOR BACKUP MODEL
		//1. LABEL FORMAT 
		//2. SITE_ID AND LABEL UNIQUE
		
		if(!preg_match('/^[a-zA-Z0-9-_]+$/',$label)){
			return response()->json(['error' => 'true', 'message_en'=>'Invalid Title.', "message_es" => "Titulo Invalido."]);
		}else{
			
			$site = Site::findOrFail($site_id);
			if($this->user->can_create_snapshot()){
                	
				$site->snapshot_creation($label);	
				$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "message" => "", "table_id" => Input::get('table_id')];
			
				return response()->json($var_array);
				
			}else{
				
				return response()->json(['error' => 'true', 'message_en'=>'Oops! Looks like you need something a bit stronger 😉 Upgrade your plan to On the Rocks and get a Virtual Private Server that supports your performance needs.', "message_es" => "Oops! Parece que necesitas algo un poco más fuerte 😉 Sube tu plan al nivel <a style='color:#fff; font-weight: bold; text-decoration: underline;' href='/producto/plan-en-las-rocas/'>En Las Rocas</a> y consigue un Virtual Private server que soportará tus necesidades de rendimiento."]);
			}
		}	
		
	}
	
	public function delete_backup(){
		if(isset($this->user)){
						
			$backup = Backup::findOrFail(Input::get('backup_id'));
			$backup_copy = $backup;
			
			if($backup->manual==true){
				$this->user->snapshot = false;
				$this->user->save();
			}
			
			//$backup->delete_backup();
			$backup->delete();
				
	   	 	$var_array = ["backup" => $backup_copy, "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];	
			
			return response()->json($var_array);
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function delete_site(){
		if(isset($this->user)){
			
			$site = Site::findOrFail(Input::get('pete_id'));
			$site_response = $site;
			
			$site->delete_wordpress();
			$site->delete();
			
	   	 	$var_array = ["site" => $site_response,"email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];	
			$site->delete();
				return response()->json($var_array);
			}else{
				return response()->json(['message'=>'Invalid Credentials']);
			}
	}
	
	public function restore_site(){
		if(isset($this->user)){
			
			$site = Site::withTrashed()->findOrFail(Input::get('pete_id'));
			$checkurl = str_replace("_odeleted_$site->id","",$site->url);			
			$cheksite = Site::where("url",$checkurl)->first();
			
			if(isset($cheksite)){
				return response()->json(['error' => 'true', 'message_en'=>'URL is being used.', "message_es" => "La URL esta siendo usada."]);
			}
			
			$site->restore();	
			$site->restore_wordpress();
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "subscription_id" => Input::get('subscription_id')];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	
	public function destroy_site()
	{
		if(isset($this->user)){
			
		 	$site = Site::onlyTrashed()->findOrFail(Input::get('pete_id'));	
		 	$site->force_delete_wordpress();
			$oldsite = $site;
		 	$site->forceDelete();
		
			$var_array = ["site" => $oldsite, "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			return response()->json($var_array);
		
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function continue_site(){
		if(isset($this->user)){
			$site = Site::findOrFail(Input::get('pete_id'));
			$site->continue_wordpress();
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function suspend_site(){
		if(isset($this->user)){
			$site = Site::findOrFail(Input::get('pete_id'));
			$site->suspend_wordpress();
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function set_backup_days(){
		if(isset($this->user)){
			$site = Site::findOrFail(Input::get('pete_id'));
			$site->backup_days = Input::get('backup_days');
			$site->save();
			
			Log::info("backup_days: ".$site->backup_days);
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	

	public function mod_site(){
		if(isset($this->user)){
			
			$site = Site::findOrFail(Input::get('pete_id'));
			$mod_sw = Input::get('mod_sw');
			$site->mod_wordpress($mod_sw);
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			return response()->json($var_array);
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
		
	public function reload_pete(){
				
		if(isset($this->user)){
			
			Site::reload_server();
		   
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
		
	}
	
	public function list_sites(){
		if(isset($this->user)){
			
			$sites = $this->user->my_sites()->get();
			$time = Carbon::now();
			$server_time = $time->format('Y/m/d H:i:s');
				
			$var_array = ["sites" => $sites, "barserver_id" => Input::get('barserver_id'), "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'),"server_time" => $server_time];
			return response()->json($var_array);
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
			
	}
	
	public function list_trash(){
		if(isset($this->user)){
						
			$sites = $this->user->my_trash_sites()->get();
			$var_array = ["sites" => $sites, "barserver_id" => Input::get('barserver_id'), "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];	
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
			
	}
	
	public function list_backups(){
		if(isset($this->user)){
			
			
			$backups = $this->user->my_backups()->get();
			
			$var_array = ["sites" => $backups, "subscription_id" => Input::get('subscription_id'), "barserver_id" => Input::get('barserver_id'), "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];	
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
			
	}
	
	
	public function list_snapshots(){
		if(isset($this->user)){
			
			$backups = $this->user->my_snapshots()->get();
			
			$var_array = ["sites" => $backups, "barserver_id" => Input::get('barserver_id'), "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];	
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
			
	}
	
	public function get_first_password(){
		if(isset($this->user)){

			$site = Site::findOrFail(Input::get('pete_id'));				
			if(isset($site->first_password)){
				$wp_pass = Crypt::decrypt($site->first_password);	
			}else{
				$wp_pass = "undefined";
			}
			
			$var_array = ["site" => $site, "barserver_id" => Input::get('barserver_id'), "password" => $wp_pass];
				
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function get_server_info(){
				
		if(isset($this->user)){
			
			$pete_options = new PeteOption();
			$shared = Input::get('shared'); 
			$os_distribution = $pete_options->get_meta_value('os_distribution');
			
			if($os_distribution == "darwin"){
				
				if($shared == true){
					$disk_total_size = "10 Gi";
					$disk_used = "1 Gi";
					$disk_free = "9 Gi";
				}else{
					$aux = shell_exec("df -h | grep /dev/disk");
					$array = preg_split('/\s+/', $aux);
					
					if($this->user->admin != true){
						$disk_total_size = "5 GB";
						$disk_used = "1 GB";
						$disk_free = "4 GB";
					}else{
					
						$disk_total_size = $array[1];
						$disk_used = $array[2];
						$disk_free = $array[3];
					}
				}
				
   				$apache_v_dialog=explode(' P',apache_get_version())[0];
   				$php_v_dialog = "PHP: ".PHP_VERSION;
   				$mysql_v_dialog = shell_exec('mysql -V');
				$os_v_dialog = $pete_options->get_meta_value('os') . " 10.14";
				
			}else if($os_distribution == "ubuntu"){
				
				if($this->user->admin != true){
					$disk_total_size = "5 GB";
					$disk_used = "1 GB";
					$disk_free = "4 GB";
				}else{
					$aux = shell_exec("df -h | grep /dev/sda");
					$array = explode("  ", $aux);
					$disk_total_size = $array[4];
					$disk_used = $array[5];
					$disk_free = $array[6];
				}
				
   				$apache_v_dialog = shell_exec('apache2 -v | grep version');
				$apache_v_dialog = preg_replace("/Server version:\s+/", "", $apache_v_dialog);
				
   				$php_v_dialog = shell_exec('php -r "echo phpversion();"');
				$php_v_dialog = "PHP: ".substr($php_v_dialog, 0, 6);
				
   				$mysql_v_dialog = shell_exec('mysql -V');
				
				$os_v_dialog = shell_exec('lsb_release -a | grep Description');
				$os_v_dialog = preg_replace("/Description:\s+/", "", $os_v_dialog);
				
			}else if($os_distribution == "docker"){
				
				if($this->user->admin != true){
					$disk_total_size = "5 GB";
					$disk_used = "1 GB";
					$disk_free = "4 GB";
				}else{
					$aux = shell_exec("df -h | grep overlay");
					$array = explode("  ", $aux);
					$disk_total_size = $array[4];
					$disk_used = $array[5];
					$disk_free = $array[6];
				}
				
   				$apache_v_dialog = shell_exec('apache2 -v | grep version');
				$apache_v_dialog = preg_replace("/Server version:\s+/", "", $apache_v_dialog);
				
   				$php_v_dialog = shell_exec('php -r "echo phpversion();"');
				$php_v_dialog = "PHP: ".substr($php_v_dialog, 0, 6);
				
   				$mysql_v_dialog = shell_exec('mysql -V');
				
				$os_v_dialog = shell_exec('lsb_release -a | grep Description');
				$os_v_dialog = preg_replace("/Description:\s+/", "", $os_v_dialog);
			}
			
			$time = Carbon::now();
			$server_time = $time->format('Y/m/d H:i:s');
			
			
			$var_array = ["subscription_id" => Input::get('subscription_id'), "barserver_id" => Input::get('barserver_id'), "email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip'), "disk_total_size" => $disk_total_size, "disk_used" => $disk_used, "disk_free" =>$disk_free, "server_time" => $server_time, "apache_v_dialog" => $apache_v_dialog, "php_v_dialog" => $php_v_dialog, "mysql_v_dialog" => $mysql_v_dialog, "os_v_dialog" => $os_v_dialog ];
				
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
		
	}
	
	public function create_shared_user(){
		
		if(isset($this->user)){
			
			$new_user_pete_info = Input::get('new_user_pete_info');
			$new_user_pete_token = Input::get('new_user_pete_token');
			$new_user_username = Input::get('new_user_username');
			$new_user_email = Input::get('new_user_email');
			$new_user_db = Input::get('new_user_db');
			$new_user_db_pass = Input::get('new_user_db_pass');
			
			$user = new User();
			$user->name = $new_user_username;
			$user->email = $new_user_email;
			$user->password = bcrypt($new_user_pete_info);
			$user->pete_token = Crypt::encrypt($new_user_pete_token);
			$user->admin = false;
			$user->shared = true;
			$user->user_db = $new_user_db;
			$user->db_info = Crypt::encrypt($new_user_db_pass);
			
			$user->create_db_user();
			
			//Filemanager Logic 
			$pete_options = new PeteOption();
			$app_root = $pete_options->get_meta_value('app_root');
			
			$user->filemanager_info = password_hash($new_user_pete_info, PASSWORD_DEFAULT);
			$user->filemanager_directory = $app_root.'/folders'."/".$user->name;
			
			shell_exec("cd $app_root/folders && mkdir $user->name");
			
			$user->save();
			
			$var_array = ["user" => $user];
				
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function suspend_service(){
		if(isset($this->user)){
			
			$sites = $this->user->my_sites()->get();
			foreach ($sites as $site ){
				$site->suspend_wordpress();
			}
			Site::reload_server();
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function activate_service(){
		if(isset($this->user)){
			
			$sites = $this->user->my_sites()->get();
			foreach ($sites as $site ){
				$site->continue_wordpress();
			}
			Site::reload_server();
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			
			return response()->json($var_array);
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function phpmyadmin_sw(){
		if(isset($this->user)){
			
			$pete_options = new PeteOption();
			$os_distribution = $pete_options->get_meta_value('os_distribution');
			$sw = Input::get('phpmyadmin_sw');
			if($os_distribution=="ubuntu"){
				Site::filemanager($sw);
				Site::reload_server();
			}else{
				Log::info("docker is not supported yet");
				Log::info("SW: ".$sw);
			}
			
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			return response()->json($var_array);
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
	public function filemanager_sw(){
		
		if(isset($this->user)){
			
			$pete_options = new PeteOption();
			$os_distribution = $pete_options->get_meta_value('os_distribution');
			$sw = Input::get('filemanager_sw');
			if($os_distribution=="ubuntu"){
				Site::filemanager($sw);
				Site::reload_server();
			}else{
				Log::info("docker is not supported yet");
				Log::info("SW: ".$sw);
			}
			
			$var_array = ["email" => Input::get('email'), "pete_token" => Input::get('pete_token'), "barserver_ip" => Input::get('barserver_ip')];
			return response()->json($var_array);
			
		}else{
			return response()->json(['message'=>'Invalid Credentials']);
		}
	}
	
}