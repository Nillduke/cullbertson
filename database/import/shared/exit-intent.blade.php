<dialog id="exit-intent-dialog" class="exit-intent" aria-labelledby="exit-intent-title" aria-describedby="exit-intent-description" data-preview="{{ app()->environment('local') && request()->boolean('preview_exit') ? 'true' : 'false' }}">
    <div class="exit-intent-photo">
        <img src="{{ asset('assets/quote-family.jpg') }}" alt="A family enjoying time together outdoors" width="3768" height="3014" loading="lazy">
    </div>
    <button type="button" class="exit-intent-close" aria-label="Close coverage reminder" data-dismiss-exit>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <div class="exit-intent-content">
        <span class="exit-intent-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/></svg> BEFORE YOU GO</span>
        <h2 id="exit-intent-title" tabindex="-1">Your Florida life.<br><span>Let’s help protect it.</span></h2>
        <p id="exit-intent-description">Explore coverage for your home, auto, family and more, with personal guidance from our Fort Myers team.</p>
        <a class="exit-intent-cta" href="{{ url('/get-a-quote/') }}">Explore my coverage <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
        <button type="button" class="exit-intent-decline" data-dismiss-exit>No thanks, I’ll keep browsing</button>
    </div>
</dialog>
