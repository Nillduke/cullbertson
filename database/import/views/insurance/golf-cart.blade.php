@extends('layouts.app')

@section('content')
<main id="main" class="insurance-detail">
    <section class="insurance-heading" aria-labelledby="insurance-title">
        <div class="shell insurance-detail-shell">
            <nav class="insurance-breadcrumbs" aria-label="Breadcrumb">
                <a href="{{ url('/') }}">Home</a><span aria-hidden="true">›</span><a href="{{ url('/insurance/') }}">Insurance</a><span aria-hidden="true">›</span><span aria-current="page">Golf cart</span>
            </nav>
            <p class="insurance-eyebrow">HERE FOR YOUR FLORIDA LIFE</p>
            <h1 id="insurance-title">Golf Cart Insurance</h1>
            <p class="insurance-heading-subtitle">For the course. The community. The life you love.</p>
        </div>
    </section>

    <div class="shell insurance-detail-shell insurance-hero-photo">
        <img src="{{ asset('assets/insurance/golf-cart-family.webp') }}" alt="A family enjoying a golf cart ride in the Florida sunshine" width="1920" height="1076" fetchpriority="high">
    </div>

    <section class="insurance-introduction" aria-labelledby="insurance-intro-title">
        <div class="insurance-reading-width">
            <h2 id="insurance-intro-title">Cruise with confidence on the green or around your community. Let’s find golf cart coverage that fits your Florida lifestyle.</h2>
            <p>Golf cart insurance helps protect owners of recreational and street-legal golf carts against accidents, theft and liability. Whether you use your cart on the course or in your neighborhood, our Fort Myers team can help you explore your options.</p>
            <a class="insurance-text-link" href="#coverage-options">Explore coverage options <span aria-hidden="true">↓</span></a>
        </div>
    </section>

    <section class="insurance-quote-band insurance-page-frame" id="golf-cart-quote" aria-labelledby="golf-cart-quote-title">
        <div class="shell insurance-detail-shell insurance-quote-inner">
            <div>
                <p class="insurance-eyebrow">LOCAL PEOPLE. PERSONAL GUIDANCE.</p>
                <h2 id="golf-cart-quote-title">Find golf cart coverage<br>that fits your life.</h2>
                <p>Tell us a little about your cart and how you use it. Start with your ZIP code, and our team can help with the next steps.</p>
            </div>
            <form method="get" action="{{ url('/get-a-quote/golf-cart-insurance-quote/') }}" class="insurance-zip-form">
                <label for="golf-cart-zip">Your ZIP code</label>
                <div class="insurance-zip-controls"><input id="golf-cart-zip" type="text" name="zip" placeholder="Enter ZIP code" inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{5}" maxlength="5" required title="Enter a five-digit ZIP code"><button type="submit">Get a quote <span aria-hidden="true">→</span></button></div>
                <span class="insurance-quote-alternative">Prefer to talk? <a href="tel:12392107700">(239) 210-7700</a></span>
            </form>
        </div>
    </section>

    <section class="insurance-feature insurance-page-frame" aria-labelledby="golf-cart-feature-title">
        <div class="insurance-feature-copy">
            <p class="insurance-eyebrow">MADE FOR YOUR EVERYDAY ADVENTURES</p>
            <h2 id="golf-cart-feature-title">A small cart.<br>A big part of your life.</h2>
            <p>From a round of golf to a ride around the neighborhood, your golf cart deserves coverage that reflects how you actually use it.</p>
            <ul class="insurance-feature-points">
                <li>Protection options for accidents, theft and liability</li>
                <li>Coverage for recreational and street-legal golf carts</li>
                <li>Personal guidance for your Florida lifestyle</li>
            </ul>
            <a class="insurance-text-link" href="#golf-cart-quote">Start your quote <span aria-hidden="true">→</span></a>
        </div>
        <div class="insurance-feature-image"><img src="{{ asset('assets/insurance/golf-cart-coverage.jpg') }}" alt="A golf cart parked outdoors on a sunny day" width="1200" height="800" loading="lazy"></div>
    </section>

    <section id="coverage-options" class="shell insurance-detail-shell insurance-coverage" aria-labelledby="golf-cart-coverage-title">
        <div class="insurance-section-heading"><p class="insurance-eyebrow">UNDERSTAND YOUR OPTIONS</p><h2 id="golf-cart-coverage-title">What does golf cart insurance cover?</h2><p>Build a policy around your cart, your passengers and the way you get around. Options may include:</p></div>
        <div class="insurance-coverage-list">
            @foreach([
                ['Liability', 'Helps with costs if you are responsible for injuries to others or damage to their property.'],
                ['Collision', 'Helps pay for damage to your golf cart resulting from a collision, subject to your policy and deductible.'],
                ['Comprehensive', 'Helps protect against covered non-collision incidents, such as theft, vandalism, fire and certain weather events.'],
                ['Uninsured & underinsured motorist', 'Protection options for an accident involving someone who has no insurance or insufficient coverage.'],
                ['Medical payments', 'Helps with covered medical expenses for injuries to you or your passengers.'],
                ['Accessories & upgrades', 'Ask about protection for custom wheels, seats, windshields, paint and other accessories.'],
                ['Street-legal use', 'Discuss coverage for use on permitted public roads, based on your cart, its classification and policy terms.']
            ] as [$name, $explanation])
            <div class="insurance-coverage-item"><span class="insurance-coverage-check" aria-hidden="true">✓</span><div><h3>{{ $name }}</h3><p>{{ $explanation }}</p></div></div>
            @endforeach
        </div>
        <p class="insurance-coverage-note">Available coverage, exclusions, limits and deductibles vary by insurer and policy. Your agent can explain the details before you choose.</p>
    </section>

    <section class="insurance-questions" aria-labelledby="golf-cart-questions-title">
        <div class="insurance-reading-width">
            <p class="insurance-eyebrow">A LITTLE CLARITY GOES A LONG WAY</p>
            <h2 id="golf-cart-questions-title">Know your coverage.<br>Enjoy your ride.</h2>
            <details class="insurance-accordion" open>
                <summary>What is golf cart insurance?<span aria-hidden="true"></span></summary>
                <div><p>It is insurance designed for recreational and street-legal golf carts. A policy can combine liability protection with coverage for damage to the cart, theft, passengers and accessories. The right options depend on your cart and where and how it is used.</p></div>
            </details>
            <details class="insurance-accordion">
                <summary>What doesn’t golf cart insurance cover?<span aria-hidden="true"></span></summary>
                <div><p>Policies have exclusions and limits. Ask your agent about:</p><ul><li>Wear and tear or mechanical breakdown</li><li>Drivers or uses excluded by the policy</li><li>Driving a cart in areas where its use is not permitted</li><li>Intentional damage</li><li>Claims above your coverage limits or below your deductible</li><li>Weather events or other losses excluded by your particular policy</li></ul></div>
            </details>
            <details class="insurance-accordion">
                <summary>What should I ask before choosing a policy?<span aria-hidden="true"></span></summary>
                <div><ul><li>What coverage applies to my cart and the places where I drive it?</li><li>Does the policy cover recreational use, street-legal use, or both?</li><li>What liability limits should I discuss with my agent?</li><li>How are hurricane damage and flooding treated?</li><li>Are comprehensive and collision coverage included?</li><li>Is use away from the golf course covered?</li><li>How are custom wheels, seats and other accessories protected?</li><li>Are discounts available when combining policies?</li><li>How do I report a claim and what happens next?</li><li>What limits, deductibles and exclusions should I understand?</li></ul></div>
            </details>
        </div>
    </section>

    <section class="shell insurance-detail-shell insurance-resources" aria-label="More ways we can help">
        <div class="insurance-resource-column"><p class="insurance-eyebrow">YOUR FLORIDA LIFE</p><h2>More of life.<br>Covered.</h2><p>Your cart is just one part of the picture. Explore protection for your home, car and everyday adventures.</p><a class="insurance-text-link" href="{{ url('/insurance/') }}">Explore our insurance <span aria-hidden="true">→</span></a><a class="insurance-text-link" href="{{ url('/quote/?service=home-auto') }}">Home + auto quotes <span aria-hidden="true">→</span></a></div>
        <div class="insurance-resource-column"><p class="insurance-eyebrow">HELPFUL READING</p><h2>A little more<br>peace of mind.</h2><p>Practical guidance to help you understand insurance and prepare for the unexpected.</p><a class="insurance-text-link" href="{{ url('/theft-and-vandalism-coverage-for-car-insurance/') }}">Theft & vandalism coverage <span aria-hidden="true">→</span></a><a class="insurance-text-link" href="{{ url('/is-roadside-assistance-included-in-your-car-insurance-policy/') }}">Roadside assistance <span aria-hidden="true">→</span></a><a class="insurance-text-link" href="{{ url('/expert-insurance-insights-and-tips-culbertson-agency-articles/') }}">All insurance articles <span aria-hidden="true">→</span></a></div>
        <div class="insurance-resource-column"><p class="insurance-eyebrow">HERE WHEN YOU NEED US</p><h2>Let’s talk<br>about your cart.</h2><p>Have a question about coverage, a policy or a claim? Our Fort Myers team is here to help.</p><a class="insurance-text-link" href="{{ url('/contact-us/') }}">Contact our team <span aria-hidden="true">→</span></a><a class="insurance-text-link" href="{{ url('/claims/') }}">Claims support <span aria-hidden="true">→</span></a><a class="insurance-resource-phone" href="tel:12392107700">(239) 210-7700</a></div>
    </section>
</main>
@endsection
