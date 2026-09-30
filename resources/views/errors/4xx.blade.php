{{--
    Any other client error. Laravel ships its own pages for 401 to 429, and
    those win over this one, so the ones that can happen here have a file.
--}}
@extends('errors.layouts.site', [
    'title' => 'Something is off with this request',
    'message' => 'The page could not be shown. Check the address, or start again from the home page.',
])
