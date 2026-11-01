@if ($errors->any())
    <div
        role="alert"
        class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
    >
        <p class="font-medium">Review the Crew record:</p>
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
