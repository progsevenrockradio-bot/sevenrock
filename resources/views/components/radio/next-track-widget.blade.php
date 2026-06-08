@props(['variant' => 'dock'])

<div class="radio-player-nexttrack-shell radio-player-nexttrack-shell--{{ $variant }}" x-cloak>
    <div class="radio-player-nexttrack-title">Siguiente canción</div>
    <!-- RadioBOSS CloudNextTrack Widget (Start) -->
    <div class='rbcloud_nexttrack' style='display: flex; align-items: center;'>
        <div>
            <a target='_blank' rel="noopener noreferrer" href='https://c30.radioboss.fm/w/artwork_next/569.jpg'>
                <img id='rbcloud_nt_c753' src='https://c30.radioboss.fm/w/artwork_next/569.jpg' width='65' height='65' alt='cover art'>
            </a>
        </div>
        <div style='margin-left: 5pt;'>
            <div id='rbcloud_nt_a753' style='font-weight: bold'></div>
            <div id='rbcloud_nt_t753'>...</div>
        </div>
    </div>
    <script src='https://c30.radioboss.fm/w/nexttrack.js?u=569&amp;wid=753&amp;tf=1'></script>
    <!-- RadioBOSS Cloud NextTrack Widget (End) -->
</div>
