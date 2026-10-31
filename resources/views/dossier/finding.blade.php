{{-- One finding inside the dossier: claim, detail, confidence, content date and every source with its full URL. Expects $finding, $dt, $d, $confWord from dossier.blade.php. Strings come from lang/{en,fr}/dossier.php. --}}
<div class="finding">
    <p class="claim">{{ $finding['claim'] }}@if (! empty($finding['untranslated']) && app()->getLocale() !== 'en') <span class="tag tag-en">{{ __('dossier.tags.english_only') }}</span>@endif</p>
    @if (! empty($finding['detail']))
        <p class="detail">{{ $finding['detail'] }}</p>
    @endif
    <p class="meta">
        @if (isset($finding['confidence'])){{ __('dossier.finding.confidence') }}{{ __('dossier.colon') }} <b>{{ $confWord($finding['confidence']) }}</b> ({{ $finding['confidence'] }})@endif
        @if (! empty($finding['content_date'])) · {{ __('dossier.finding.content_dated') }} <b>{{ $d($finding['content_date']) }}</b>@endif
        @if (! empty($finding['by'])) · {{ __('dossier.finding.filed_by', ['name' => $finding['by']]) }}@endif
        @if (! empty($finding['key'])) · <span class="mono">{{ $finding['key'] }}</span>@endif
    </p>
    @if (empty($finding['sources']))
        <p class="none">{{ __('dossier.finding.no_source') }}</p>
    @else
        <ol class="sources">
            @foreach ($finding['sources'] as $source)
                <li>
                    <a class="cite n" href="#src-{{ $source['n'] }}">[{{ $source['n'] }}]</a>
                    <a class="url" href="{{ $source['url'] }}" target="_blank" rel="noreferrer">{{ $source['url'] }}</a>
                    <div class="src-meta">
                        @if (! empty($source['title'])){{ $source['title'] }} · @endif
                        {{ __('dossier.finding.retrieved', ['at' => $dt($source['retrieved_at'])]) }}
                        @if (! empty($source['content_date'])) · {{ __('dossier.finding.content_dated_source', ['date' => $d($source['content_date'])]) }}@endif
                    </div>
                    @if (! empty($source['quote']))
                        <blockquote>“{{ $source['quote'] }}”</blockquote>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
