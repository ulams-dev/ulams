{{ __('The scheduled report ":name" was not sent to these recipients:', ['name' => $reportName]) }}

@foreach ($skipped as $entry)
- {{ $entry['name'] }}: {{ $entry['reason'] }}
@endforeach

{{ __('A report runs with the permissions of each recipient. Change the recipients of the schedule or the permissions of these people to send it to them.') }}
