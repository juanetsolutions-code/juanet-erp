@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">{{ $project->name }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md border">
            <h2 class="text-xl font-semibold mb-4">Milestones</h2>
            <!-- Milestones -->
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md border">
            <h2 class="text-xl font-semibold mb-4">Discussions</h2>
            <!-- Discussions -->
        </div>
    </div>
</div>
@endsection
