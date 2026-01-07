<?php
namespace App\Controllers;

class HomeController extends BaseController{
    
    public function index(){
       
        $data = [
            'title' => 'Portal3'
        ];
        
        $this->view('home/index.php', $data);
       
    }

}