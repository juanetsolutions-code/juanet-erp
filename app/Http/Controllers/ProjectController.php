<?php

namespace App\Http\Controllers;

use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function show(Project $project)
    {
        $project->load(['milestones', 'comments', 'files']);
        return view('client_portal.project.show', compact('project'));
    }
}
