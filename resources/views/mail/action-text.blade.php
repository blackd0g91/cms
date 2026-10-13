{{ $siteName }}

{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
{{ $actionText }}: {{ $actionUrl }}
@foreach ($links ?? [] as $label => $url)
{{ $label }}: {{ $url }}
@endforeach

{{ $outro }}
