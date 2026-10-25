<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
class CreateCmsAdmin extends Command
{
    protected $signature='cms:create-admin {--email= : Administrator email} {--name=Culbertson Administrator : Administrator name}';
    protected $description='Create the first CMS administrator and save credentials in a private local file';
    public function handle():int{
        if(User::where('is_admin',true)->exists()){$this->info('An administrator already exists; credentials were not changed.');return 0;}
        if(app()->environment('production') || $this->option('email')) {
            $email=$this->option('email')?:$this->ask('Administrator email');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Enter a valid email address.');return 1;}
            if(User::where('email',$email)->exists()){$this->error('This email already belongs to an existing account.');return 1;}
            $password=$this->secret('New administrator password (at least 12 characters)');
            if(!is_string($password)||strlen($password)<12){$this->error('Use at least 12 characters.');return 1;}
            if($this->secret('Confirm password')!==$password){$this->error('Passwords do not match.');return 1;}
            $user=new User;$user->forceFill(['name'=>$this->option('name'),'email'=>$email,'password'=>Hash::make($password),'is_admin'=>true])->save();
            $this->info('Administrator created. Sign in at '.rtrim(config('app.url'),'/').'/admin/login');return 0;
        }
        $password=bin2hex(random_bytes(12)).'!Aa';
        $user=new User;$user->forceFill(['name'=>'Culbertson Administrator','email'=>'admin@culbertson.local','password'=>Hash::make($password),'is_admin'=>true])->save();
        $path=dirname(base_path()).'/.local-setup/culbertson-laravel-admin.txt';
        if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
        file_put_contents($path,"Culbertson CMS\nURL: http://localhost:8083/admin/login\nEmail: admin@culbertson.local\nPassword: ".$password."\n",LOCK_EX);
        $this->info('CMS administrator created. Login details saved in .local-setup/culbertson-laravel-admin.txt outside the public directory.');return 0;
    }
}
