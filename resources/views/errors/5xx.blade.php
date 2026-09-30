{{-- Any other server error. Laravel ships its own 500 and 503 pages, so those have a file too. --}}
@extends('errors.layouts.standalone', [
    'title' => 'Something went wrong',
    'message' => 'The server could not show this page. Try again in a minute.',
])
