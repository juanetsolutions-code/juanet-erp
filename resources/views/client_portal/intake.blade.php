@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Project Intake</h1>
    <form action="{{ route('project.intake.store') }}" method="POST" class="bg-white p-6 rounded-lg shadow-md border">
        @csrf
        <input type="text" name="name" placeholder="Project Name" class="w-full p-2 mb-4 border rounded">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Submit Request</button>
    </form>
</div>
@endsection
