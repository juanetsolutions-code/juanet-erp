@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Client Dashboard</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($projects as $project)
            <div class="bg-white p-6 rounded-lg shadow-md border">
                <h2 class="text-xl font-semibold">{{ $project->name }}</h2>
                <p>Status: {{ $project->status }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
