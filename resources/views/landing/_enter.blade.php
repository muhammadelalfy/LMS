{{-- The way into the actual platform. On a school's own address it is that school's sign-in; on the
     main site there is no school yet, so it opens the "enter your school code" box (or goes to
     LANDING_WEB_URL when one is configured). --}}
@if ($target)
    <a class="{{ $class }}" href="{{ $target }}">{{ $label }}</a>
@else
    <button type="button" class="{{ $class }}" data-open-school aria-haspopup="dialog">{{ $label }}</button>
@endif
