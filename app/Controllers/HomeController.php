<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Models\Modulo;

class HomeController extends BaseController
{
public function index()
{
 
    $data = [
    'title'   => 'Portal3',
    'scripts'=> []
    ];

    View::render('home/index', $data, 'main');
}
}
