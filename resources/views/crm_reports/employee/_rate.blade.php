{{-- A percentage with a thin in-cell bar. --}}
<td class="num" data-v="{{ $v }}">
    <div class="crm-rate">
        <span>{{ number_format((float) $v, 1) }}%</span>
        <span class="crm-rate-track" aria-hidden="true"><span class="crm-rate-fill" style="width: {{ max(0, min(100, (float) $v)) }}%"></span></span>
    </div>
</td>
