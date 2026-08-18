<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #193f45; font-family: DejaVu Sans, sans-serif; direction: rtl; font-size: 11px; line-height: 1.85; }
        .paper { position: relative; overflow: hidden; padding: 20px 22px; border: 1px solid #d7cbb9; border-radius: 14px; }
        .watermark { position: fixed; z-index: -1; top: 38%; right: 12%; width: 76%; text-align: center; color: #b87945; font-size: 48px; font-weight: bold; opacity: {{ $watermarkOpacity }}; transform: rotate(-28deg); white-space: nowrap; }
        .watermark-small { position: absolute; z-index: -1; top: 48%; right: 12%; width: 76%; text-align: center; color: #b87945; font-size: 28px; font-weight: bold; opacity: {{ min($watermarkOpacity * 0.8, 0.4) }}; transform: rotate(-28deg); white-space: nowrap; }
        .brand { color: #ae7650; font-size: 9px; font-weight: bold; letter-spacing: 1px; }
        h1 { margin: 4px 0; font-size: 20px; color: #173f46; }
        .muted { color: #776f64; }
        .header { border-bottom: 2px solid #16846d; padding-bottom: 12px; margin-bottom: 14px; }
        .header-table, .meta-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .meta { width: 42%; padding-top: 7px; }
        .meta-table td { padding: 3px 0; color: #665f55; font-size: 9px; }
        .meta-table td:first-child { font-weight: bold; color: #193f45; width: 42%; }
        .instructions { border: 1px solid #dfd3c2; border-radius: 9px; background: #faf4e9; padding: 9px 12px; margin-bottom: 14px; }
        .instructions strong { color: #173f46; }
        .instructions p { margin: 3px 0 0; }
        .question { position: relative; overflow: hidden; page-break-inside: avoid; border: 1px solid #e3d8ca; border-radius: 9px; padding: 12px 13px; margin-bottom: 11px; background: rgba(255,253,248,.92); }
        .question-watermark { position: absolute; z-index: 0; top: 42%; right: 10%; width: 80%; text-align: center; color: #b87945; font-size: 25px; font-weight: bold; opacity: .08; transform: rotate(-24deg); white-space: nowrap; }
        .question > *:not(.question-watermark) { position: relative; z-index: 1; }
        .question-head { border-bottom: 1px dashed #d9cdbd; padding-bottom: 6px; margin-bottom: 7px; }
        .question-number { color: #16846d; font-weight: bold; }
        .question-points { float: left; color: #a56d39; font-size: 9px; }
        .prompt { clear: both; margin-top: 3px; }
        .check { display: inline-block; width: 13px; height: 13px; line-height: 12px; text-align: center; border: 1px solid #16846d; border-radius: 50%; color: #16846d; font-size: 9px; font-weight: bold; margin-left: 5px; }
        .options { list-style: none; padding: 0; margin: 7px 0 0; }
        .options li { margin: 4px 0; padding: 4px 7px; border: 1px solid #e4dace; border-radius: 6px; }
        .answer-lines { height: 72px; margin-top: 9px; background: repeating-linear-gradient(to bottom, transparent 0, transparent 20px, #d8ccbb 21px, transparent 22px); }
        .footer { margin-top: 14px; border-top: 1px solid #dfd3c2; padding-top: 6px; color: #8a8175; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="paper">
        <div class="watermark">{{ $watermark }}</div>
        <div class="watermark-small">{{ $watermark }}</div>
        <header class="header">
            <table class="header-table">
                <tr>
                    <td>
                        <div class="brand">الامتياز في الرياضيات</div>
                        <h1>{{ $template->title }}</h1>
                        <div class="muted">{{ $template->department?->name ?: 'اختبار رياضيات' }}</div>
                    </td>
                    <td class="meta">
                        <table class="meta-table">
                            <tr><td>الصف</td><td>{{ $template->grade ?: 'كل الصفوف' }}</td></tr>
                            <tr><td>المدة</td><td>{{ $template->duration_minutes }} دقيقة</td></tr>
                            <tr><td>عدد الأسئلة</td><td>{{ $template->questions->count() }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </header>
        @if($template->instructions)
            <section class="instructions"><strong>تعليمات الامتحان</strong><p>{{ $template->instructions }}</p></section>
        @endif
        @foreach($template->questions as $index => $question)
            <section class="question">
                <div class="question-watermark">{{ $watermark }}</div>
                <div class="question-head"><span class="question-number"><span class="check">✓</span>السؤال {{ $index + 1 }}</span><span class="question-points">{{ $question->points }} درجة</span></div>
                <div class="prompt">{!! $question->prompt_html !!}</div>
                @if($question->type === 'mcq' && is_array($question->options))
                    <ul class="options">@foreach($question->options as $option)<li><span class="check">✓</span>{{ $option }}</li>@endforeach</ul>
                @else
                    <div class="answer-lines"></div>
                @endif
            </section>
        @endforeach
        <div class="footer">نسخة امتحان رسمية — {{ $watermark }}</div>
    </div>
</body>
</html>
