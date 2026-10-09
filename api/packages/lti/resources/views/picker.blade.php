<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add a course</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #1f2933; margin: 0; padding: 24px; max-width: 48rem; }
        h1 { font-size: 1.25rem; margin-top: 0; }
        fieldset { border: 0; padding: 0; margin: 0 0 16px; }
        label { display: flex; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #e4e7eb; }
        input[type=checkbox] { width: 20px; height: 20px; }
        button { font: inherit; padding: 8px 16px; }
        :focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <h1>Add a course from {{ config('app.name') }}</h1>
    @if (count($courses) === 0)
        <p role="status">There are no published courses yet.</p>
    @else
        <form method="post" action="{{ $action }}">
            <input type="hidden" name="form_token" value="{{ $formToken }}">
            <fieldset>
                <legend>Choose the courses to link</legend>
                @foreach ($courses as $course)
                    <label>
                        <input type="checkbox" name="course_ids[]" value="{{ $course['id'] }}">
                        <span>{{ $course['title'] }}</span>
                    </label>
                @endforeach
            </fieldset>
            <button type="submit">Add selected courses</button>
        </form>
    @endif
</main>
</body>
</html>
