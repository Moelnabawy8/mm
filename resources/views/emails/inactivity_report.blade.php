@php
    // ألوان هادئة ومتباينة تكفي لعميل بريد لا يدعم CSS خارجيًّا — كل شيء inline عمدًا.
    $ink = '#1B3A5C'; $muted = '#6B7A8C'; $line = '#DCE3EA';
    $danger = '#C0392B'; $warn = '#B7791F'; $ok = '#1E7E4E';
    $th = 'padding:8px 10px;text-align:right;font-size:12px;color:' . $muted . ';border-bottom:2px solid ' . $line . ';font-weight:700;';
    $td = 'padding:8px 10px;text-align:right;font-size:13px;color:' . $ink . ';border-bottom:1px solid ' . $line . ';';
@endphp
<div dir="rtl" style="font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#F7F6F2;padding:24px;">
  <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid {{ $line }};border-radius:10px;overflow:hidden;">

    <div style="background:{{ $ink }};padding:18px 22px;">
      <div style="color:#fff;font-size:18px;font-weight:700;">{{ __('Weekly inactivity report') }}</div>
      <div style="color:#A9C0D6;font-size:12px;margin-top:4px;">
        {{ __('Generated at') }}: <span dir="ltr">{{ $report['generated_at'] }}</span> ·
        {{ __('Threshold') }}: <span dir="ltr">{{ $report['threshold'] }}</span> {{ __('days') }}
      </div>
    </div>

    @unless($report['enforcing'])
      <div style="background:#FFF8E1;border-bottom:1px solid {{ $line }};padding:12px 22px;color:{{ $warn }};font-size:13px;">
        <strong>{{ __('Report-only mode') }}.</strong>
        {{ __('No account was blocked. Enable enforcement from the report page when you are ready.') }}
      </div>
    @endunless

    <div style="padding:18px 22px;">
      <table role="presentation" width="100%" style="border-collapse:collapse;margin-bottom:18px;">
        <tr>
          @foreach ([
            [__('Inactive'), count($report['candidates']), $danger],
            [__('Approaching'), count($report['warning']), $warn],
            [__('Currently blocked'), count($report['banned']), $ink],
            [__('Active'), count($report['active']), $ok],
          ] as [$label, $n, $colour])
            <td style="text-align:center;padding:10px 6px;border:1px solid {{ $line }};border-radius:8px;">
              <div style="font-size:22px;font-weight:700;color:{{ $colour }};" dir="ltr">{{ $n }}</div>
              <div style="font-size:11px;color:{{ $muted }};margin-top:2px;">{{ $label }}</div>
            </td>
          @endforeach
        </tr>
      </table>

      @foreach ([
        ['candidates', __('Inactive — met the threshold'), $danger],
        ['banned',     __('Currently blocked'),            $ink],
        ['warning',    __('Approaching the threshold'),    $warn],
      ] as [$key, $title, $colour])
        @if (count($report[$key]))
          <div style="font-size:14px;font-weight:700;color:{{ $colour }};margin:18px 0 8px;">
            {{ $title }} (<span dir="ltr">{{ count($report[$key]) }}</span>)
          </div>
          <table role="presentation" width="100%" style="border-collapse:collapse;">
            <tr>
              <th style="{{ $th }}">{{ __('Name') }}</th>
              <th style="{{ $th }}">{{ __('Role') }}</th>
              <th style="{{ $th }}">{{ __('Last login') }}</th>
              <th style="{{ $th }}">{{ __('Last app use') }}</th>
              <th style="{{ $th }}">{{ __('Idle days') }}</th>
            </tr>
            @foreach ($report[$key] as $r)
              <tr>
                <td style="{{ $td }}">{{ $r['name'] }}<div style="font-size:11px;color:{{ $muted }};" dir="ltr">{{ $r['email'] }}</div></td>
                <td style="{{ $td }}" dir="ltr">{{ $r['type'] ?: '—' }}</td>
                <td style="{{ $td }}" dir="ltr">{{ $r['last_login_at'] ?: '—' }}</td>
                <td style="{{ $td }}" dir="ltr">{{ $r['token_used_at'] ?: '—' }}</td>
                <td style="{{ $td }};color:{{ $colour }};font-weight:700;" dir="ltr">{{ $r['idle_days'] }}</td>
              </tr>
            @endforeach
          </table>
        @endif
      @endforeach

      @if (count($report['exempt']))
        <div style="font-size:13px;color:{{ $muted }};margin-top:20px;padding-top:12px;border-top:1px solid {{ $line }};">
          <span dir="ltr">{{ count($report['exempt']) }}</span> {{ __('accounts were exempt (management, new accounts, approved leave or former employees).') }}
        </div>
      @endif
    </div>

    <div style="background:#F7F6F2;padding:14px 22px;font-size:11px;color:{{ $muted }};border-top:1px solid {{ $line }};">
      My-Communication Academy — ERP · {{ __('This report is generated automatically every Saturday.') }}
    </div>
  </div>
</div>
