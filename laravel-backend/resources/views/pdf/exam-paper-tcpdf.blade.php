<style>
body { font-family: dejavusans; color: #193f45; font-size: 10pt; direction: rtl; }
.brand { color: #ae7650; font-size: 9pt; font-weight: bold; }
h1 { color: #173f46; font-size: 18pt; margin: 3pt 0; }
.muted { color: #776f64; }
.header { border-bottom: 2px solid #16846d; padding-bottom: 8pt; margin-bottom: 10pt; }
.meta { color: #665f55; font-size: 9pt; }
.instructions { border: 1px solid #dfd3c2; background-color: #faf4e9; padding: 7pt; margin-bottom: 10pt; }
.question { border: 1px solid #e3d8ca; padding: 9pt; margin-bottom: 9pt; }
.question-head { border-bottom: 1px dashed #d9cdbd; padding-bottom: 5pt; margin-bottom: 6pt; }
.question-number { color: #16846d; font-weight: bold; }
.question-points { color: #a56d39; font-size: 9pt; }
.option { border: 1px solid #e4dace; padding: 5pt; margin-top: 4pt; }
.checkbox { font-size: 14pt; color: #16846d; }
.answer-lines { border-bottom: 1px solid #d8ccbb; height: 52pt; }
.watermark { color: #b87945; font-size: 25pt; font-weight: bold; opacity: 0.1; text-align: center; }
.footer { color: #8a8175; font-size: 8pt; text-align: center; border-top: 1px solid #dfd3c2; padding-top: 5pt; }
</style>
<div class="watermark">{{ $watermark }}</div>
<table class="header" cellpadding="2" cellspacing="0" width="100%">
    <tr>
        <td width="58%"><span class="brand">الامتياز في الرياضيات</span><h1>{{ $template->title }}</h1><span class="muted">{{ $template->department?->name ?: 'اختبار رياضيات' }}</span></td>
        <td width="42%" class="meta"><b>الصف:</b> {{ $template->grade ?: 'كل الصفوف' }}<br/><b>المدة:</b> {{ $template->duration_minutes }} دقيقة<br/><b>عدد الأسئلة:</b> {{ $template->questions->count() }}</td>
    </tr>
</table>
@if($template->instructions)
    <div class="instructions"><b>تعليمات الامتحان</b><br/>{{ $template->instructions }}</div>
@endif
@foreach($template->questions as $index => $question)
    <div class="question">
        <div class="question-head"><span class="question-number">☑ السؤال {{ $index + 1 }}</span><span class="question-points">{{ $question->points }} درجة</span></div>
        <div>{!! $question->prompt_html !!}</div>
        @if($question->type === 'mcq' && is_array($question->options))
            @foreach($question->options as $option)
                <div class="option"><span class="checkbox">☐</span>&nbsp;{{ $option }}</div>
            @endforeach
        @else
            <div class="answer-lines"></div>
        @endif
        <div class="watermark">{{ $watermark }}</div>
    </div>
@endforeach
<div class="footer">نسخة امتحان رسمية — {{ $watermark }}</div>
