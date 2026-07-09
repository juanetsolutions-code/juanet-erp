<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Domain\Project\Services\ProjectService;

class ProjectIntakeController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    public function create()
    {
        return view('client_portal.intake');
    }

    public function store(Request $request)
    {
        $this->projectService->initiateProject(array_merge($request->all(), ['user_id' => auth()->id(), 'status' => 'initiated']));
        return redirect()->route('client.dashboard');
    }
}
