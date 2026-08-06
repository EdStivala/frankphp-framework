<?php
namespace App\Controllers;

use DateTime;
use DateTimeInterface;
use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
// use App\Models\Opportunity;
// use App\Models\Task;
// use App\Services\ForecastDataService;

class HomeController extends BaseController
{

    public function dashboard(Request $request, array $params)
    {
		$tenant = $request->tenant;
		$userID = $request->user['id'];

        return $this->view('home/dashboard', ['tenant'=>$tenant,'user'=>$request->user]);
    }
}
