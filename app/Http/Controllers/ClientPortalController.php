<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Domain\Project\Repositories\ProjectRepository;

class ClientPortalController extends Controller
{
    public function __construct(private ProjectRepository $projectRepository) {}

    public function dashboard()
    {
        $projects = $this->projectRepository->findForClient(auth()->id());
        return view('client_portal.dashboard', compact('projects'));
    }
}
