@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Remplacé par une image intégrée à l'envoi (EmbedEmailLogo). --}}
<img src="{{ \App\Listeners\EmbedEmailLogo::PLACEHOLDER }}" class="logo" width="150" alt="PressLink">
</a>
</td>
</tr>
